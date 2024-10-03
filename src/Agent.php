<?php

declare(strict_types=1);

namespace UseTheFork\Synapse;

use Exception;
use InvalidArgumentException;
use Throwable;
use UseTheFork\Synapse\AgentTask\PendingAgentTask;
use UseTheFork\Synapse\Constants\Role;
use UseTheFork\Synapse\Contracts\Agent\HasIntegration;
use UseTheFork\Synapse\Contracts\Agent\HasMemory;
use UseTheFork\Synapse\Enums\FinishReason;
use UseTheFork\Synapse\Exceptions\MaximumIterationsException;
use UseTheFork\Synapse\Exceptions\UnknownFinishReasonException;
use UseTheFork\Synapse\Traits\Agent\LogsAgentActivity;
use UseTheFork\Synapse\Traits\Agent\ManagesIntegration;
use UseTheFork\Synapse\Traits\Agent\ManagesMemory;
use UseTheFork\Synapse\Traits\Agent\ManagesTools;
use UseTheFork\Synapse\Traits\HasMiddleware;
use UseTheFork\Synapse\ValueObject\Message;

class Agent implements HasIntegration, HasMemory
{
    use HasMiddleware;
    use LogsAgentActivity,
        ManagesIntegration,
        ManagesMemory,
        ManagesTools;

    /**
     * a keyed array of values to be used as extra inputs that are passed to the prompt when it is generated.
     */
    protected array $extraInputs = [];

    /**
     * The view to use when generating the prompt for this agent
     */
    protected string $promptView;

    /**
     * The maximum number "loops" that this agent should run
     */
    protected int $maximumIterations = 25;

    /**
     * Initializes the agent.
     *
     * This method is called upon object creation to initialize the agent.
     * It is responsible for performing any necessary setup tasks.
     *
     * @throws Throwable
     */
    public function __construct()
    {
        $this->initializeIntegration();
    }

    /**
     * Create a new PendingAgentTask
     */
    public function createPendingAgentTask(array $input, array $extraAgentArgs): PendingAgentTask
    {
        return new PendingAgentTask($this, $input, $extraAgentArgs);
    }

    /**
     * Handles the user input and extra agent arguments to retrieve the response.
     *
     * @param  array|null  $input  The input array.
     * @param  array|null  $extraAgentArgs  The extra agent arguments array.
     * @return Message The final message from the agent.
     *
     * @throws Throwable
     */
    public function handle(?array $input, ?array $extraAgentArgs = []): Message
    {
        $pendingAgentTask = $this->getAnswer($input, $extraAgentArgs);

        $pendingAgentTask = $pendingAgentTask->middleware()->executeEndThreadPipeline($pendingAgentTask);

        return $pendingAgentTask->currentIteration()->getResponse();
    }

    /**
     * @throws Throwable
     */
    protected function getAnswer(?array $input, ?array $extraAgentArgs = []): PendingAgentTask
    {
        $pendingAgentTask = $this->createPendingAgentTask($input, $extraAgentArgs);

        for ($i = 1; $i <= $this->maximumIterations; $i++) {

            $pendingAgentTask->middleware()->executeStartIterationPipeline($pendingAgentTask);

            $promptChain = $this->parsePrompt(
                $this->getPrompt($pendingAgentTask)
            );

            $pendingAgentTask->currentIteration()->setPromptChain($promptChain);

            // Create the Chat request we will be sending.
            $this->integration->handlePendingAgentTaskCompletion($pendingAgentTask);
            $pendingAgentTask->middleware()->executeIntegrationResponsePipeline($pendingAgentTask);

            switch ($pendingAgentTask->currentIteration()->finishReason()) {
                case FinishReason::TOOL_CALL:
                    $pendingAgentTask->middleware()->executeStartToolCallPipeline($pendingAgentTask);
                    $this->handleTools($pendingAgentTask);
                    $pendingAgentTask->middleware()->executeEndToolCallPipeline($pendingAgentTask);
                    break;
                case FinishReason::STOP:
                    $pendingAgentTask->middleware()->executeAgentFinishPipeline($pendingAgentTask);

                    return $pendingAgentTask;
                default:
                    throw new UnknownFinishReasonException("{$pendingAgentTask->currentIteration()->finishReason()} is not a valid finish reason.");
            }
            $pendingAgentTask->middleware()->executeEndIterationPipeline($pendingAgentTask);
        }

        throw new MaximumIterationsException($this->maximumIterations);
    }

    /**
     * Parses a prompt and extracts message blocks.
     *
     * @param  string  $prompt  The prompt view to parse.
     * @return array The extracted message blocks as an array of Message objects.
     *
     * @throws InvalidArgumentException If a message block does not define a type.
     * @throws Throwable If an error occurs during parsing.
     */
    protected function parsePrompt(string $prompt): array
    {

        $prompts = [];
        // Adjusted pattern to account for possible newlines, nested content, and the new 'image' attribute
        $pattern = '/<message\s+type=[\'"](?P<role>\w+)[\'"](?:\s+tool=[\'"](?P<tool>[\w\-+=\/]+)[\'"])?(?:\s+image=[\'"](?P<image>[\w\-+=\/]+)[\'"])?\s*>\s*(?P<message>.*?)\s*<\/message>/s';
        preg_match_all($pattern, $prompt, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $role = $match['role'] ?? null;
            $tool = $match['tool'] ?? null;
            $image = $match['image'] ?? null;
            $promptContent = $match['message'] ?? '';

            $promptContent = trim($promptContent);

            if (! $role) {
                throw new InvalidArgumentException("Each message block must define a type.\nExample:\n<message type='assistant'>Foo {bar}</message>");
            }
            $messageData = [
                'role' => $role,
                'content' => $promptContent,
            ];
            if ($tool) {
                $tool = json_decode(base64_decode($tool), true);
                $messageData['tool_call_id'] = $tool['tool_call_id'];
                $messageData['tool_name'] = $tool['tool_name'] ?? null;
                $messageData['tool_arguments'] = $tool['tool_arguments'] ?? null;
                $messageData['tool_content'] = $tool['tool_content'] ?? null;
            }
            if ($image) {
                $image = json_decode(base64_decode($image), true);
                // attach the image data to the message.
                $messageData['image'] = $image;
            }
            $prompts[] = Message::make($messageData);
        }

        if ($prompts === [] && (trim($prompt) !== '' && trim($prompt) !== '0')) {
            // The whole document is a prompt
            $prompts[] = Message::make([
                'role' => Role::USER,
                'content' => trim($prompt),
            ]);
        }

        return $prompts;
    }

    /**
     * Retrieves the prompt view, based on the provided inputs.
     *
     * @param  PendingAgentTask  $pendingAgentTask  The inputs for the prompt.
     * @return string The rendered prompt view.
     *
     * @throws Throwable
     */
    public function getPrompt(PendingAgentTask $pendingAgentTask): string
    {

        $inputs = $pendingAgentTask->inputs();

        $toolNames = array_keys($this->tools);

        if (isset($inputs['image'])) {
            $inputs['image'] = base64_encode(json_encode($inputs['image']));
        }

        return view($this->promptView, [
            ...$inputs,
            ...$this->extraInputs,
            'tools' => $toolNames,
        ])->render();
    }

    /**
     * Handles the AI response tool calls.
     *
     *
     * @throws Throwable
     */
    private function handleTools(PendingAgentTask $pendingAgentTask): void
    {

        $response = $pendingAgentTask->currentIteration()->getResponse()->toArray();

        if (! empty($response['tool_call_id'])) {
            $toolResult = $this->executeToolCall($response, $pendingAgentTask);

            $response['tool_content'] = $toolResult;
        }

        $pendingAgentTask->currentIteration()->setResponse(Message::make($response));
    }

    /**
     * Executes a tool call.
     *
     * This method is responsible for calling a tool function with the given arguments
     * and returning the result as a string.
     *
     * @param  array  $toolCall  The tool call data, containing the name of the function and its arguments.
     * @return string The result of the tool call.
     *
     * @throws Exception If an error occurs while calling the tool function.
     * @throws Throwable If JSON decoding of the arguments fails.
     */
    private function executeToolCall(array $toolCall, PendingAgentTask $pendingAgentTask): string
    {
        try {
            return $this->call(
                $pendingAgentTask,
                $toolCall['tool_name'],
                json_decode($toolCall['tool_arguments'], true, 512, JSON_THROW_ON_ERROR),
            );

        } catch (Exception $e) {
            throw new Exception("Error calling tool: {$e->getMessage()}", $e->getCode(), $e);
        }
    }
}
