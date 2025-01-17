<?php

namespace UseTheFork\Synapse\Integrations\Connectors\Claude\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;
use UseTheFork\Synapse\Constants\Role;
use UseTheFork\Synapse\Enums\FinishReason;
use UseTheFork\Synapse\ValueObject\Message;

class ChatRequest extends Request implements HasBody
{
    use HasJsonBody;

    /**
     * The HTTP method
     */
    protected Method $method = Method::POST;

    private string $system = '';

    public function __construct(
        public readonly array $prompt,
        public readonly array $tools,
        public readonly array $extraAgentArgs = []
    ) {}

    public function createDtoFromResponse(Response $response): Message
    {
        $data = $response->array();
        $message = [];

        $message['role'] = 'assistant';
        $message['finish_reason'] = $this->convertResponseType($data['stop_reason']) ?? '';
        foreach ($data['content'] as $choice) {

            if ($choice['type'] === 'text') {
                $message['content'] = $choice['text'];
            } else {
                $message['tool_call_id'] = $choice['id'];
                $message['tool_name'] = $choice['name'];
                $message['tool_arguments'] = json_encode($choice['input']);
                $message['role'] = Role::TOOL;
            }
        }

        return Message::make($message);
    }

    private function convertResponseType($stopReason): string
    {
        return match ($stopReason) {
            'tool_use' => FinishReason::TOOL_CALL->value,
            default => FinishReason::STOP->value,
        };
    }

    /**
     * Data to be sent in the body of the request
     */
    public function defaultBody(): array
    {
        $model = config('synapse.integrations.claude.chat_model');

        $payload = [
            'model' => $model,
            'messages' => $this->formatMessages(),
            'system' => $this->system,
            'max_tokens' => 4096,
        ];

        if ($this->tools !== []) {
            $payload['tools'] = $this->formatTools();
        }

        return [
            ...$payload,
            ...$this->extraAgentArgs,
        ];

    }

    private function formatMessages(): array
    {

        $payload = collect();
        foreach ($this->prompt as $message) {
            switch ($message->role()) {
                case Role::SYSTEM:
                    $this->system = $message->content();
                    break;
                case Role::TOOL:
                    $toolPayload = $this->formatToolMessage($message);
                    $payload->push(...$toolPayload);
                    break;
                case Role::ASSISTANT:
                    $assistantPayload = $this->formatAssistantMessage($message);
                    $payload->push($assistantPayload);
                    break;
                default:
                    $payload->push([
                        'role' => $message->role(),
                        'content' => $message->content(),
                    ]);
            }
        }

        return $payload->values()->toArray();
    }

    private function formatToolMessage(Message $message): array
    {
        $message = $message->toArray();

        // Claude requires tool responses to be multipart agent and user responses.
        $payload[] = [
            'role' => 'assistant',
            'content' => [
                [
                    'type' => 'text',
                    'text' => $message['content'],
                ],
                [
                    'type' => 'tool_use',
                    'id' => $message['tool_call_id'],
                    'name' => $message['tool_name'],
                    'input' => json_decode($message['tool_arguments']),
                ],
            ],
        ];
        $payload[] = [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'tool_result',
                    'tool_use_id' => $message['tool_call_id'],
                    'content' => $message['tool_content'],
                ],
            ],
        ];

        return $payload;
    }

    private function formatAssistantMessage(Message $message): array
    {
        $message = $message->toArray();

        $content[] = ['type' => 'text', 'text' => $message['content']];

        if (! empty($message['tool_call_id'])) {
            $content[] = [
                'type' => 'tool_use',
                'id' => $message['tool_call_id'],
                'name' => $message['tool_name'],
                'input' => json_decode($message['tool_arguments'], true),
            ];
        }

        return [
            'role' => 'assistant',
            'content' => $content,
        ];
    }

    private function formatTools(): array
    {
        return array_values(array_map(function (array $tool) {
            $claudeTool = $tool['definition']['function'];
            $claudeTool['input_schema'] = $claudeTool['parameters'];
            unset($claudeTool['parameters']);

            return $claudeTool;
        }, $this->tools));
    }

    /**
     * The endpoint
     */
    public function resolveEndpoint(): string
    {
        return '/messages';
    }
}
