<?php

declare(strict_types=1);

namespace UseTheFork\Synapse\OutputSchema\Concerns;

use Illuminate\Support\Facades\Validator;
use Throwable;
use UseTheFork\Synapse\Integrations\ValueObjects\Message;
use UseTheFork\Synapse\OutputSchema\ValueObjects\SchemaRule;

/**
 * Indicates if the agent has an output schema.
 */
trait HasOutputSchema
{
    protected bool $hasOutputSchema = true;

    protected array $outputSchema = [];

    /**
     * Adds an output rule to the application.
     *
     * @param  SchemaRule  $rule  The output rule to be added.
     */
    public function addOutputRule(SchemaRule $rule): void
    {
        $this->outputSchema[] = $rule;
    }

    /**
     * Performs validation on the given response.
     *
     * @param  string  $response  The response to validate.
     * @return mixed If validation passes, it returns the validated response. Otherwise, it enters a loop and performs revalidation.
     *
     * @throws Throwable
     */
    protected function doValidate(string $response): mixed
    {

        if (! $this->hasOutputSchema) {
            return $response;
        }

        $outputSchema = [];
        collect($this->outputSchema)->each(function ($rule) use (&$outputSchema): void {
            $outputSchema[$rule->getName()] = $rule->getRules();
        });

        while (true) {
            $result = $this->parseResponse($response);
            $errorsAsString = '';
            if (! empty($result)) {
                $validator = Validator::make($result, $outputSchema);
                if (! $validator->fails()) {
                    return $validator->validated();
                }

                $errors = $validator->errors()->toArray();
                $errorsFlat = array_reduce($errors, function ($carry, $item): array {
                    return array_merge($carry, is_array($item) ? $item : [$item]);
                }, []);
                $errorsAsString = "### Here are the errors that Failed validation \n".implode("\n", $errorsFlat)."\n\n";
            }
            $response = $this->doRevalidate($response, $errorsAsString);
            //since all integrations return a Message value object we need to grab the content
            $response = $response->content();
        }
    }

    /**
     * Parses the input response and returns it as an associative array.
     *
     * @param  string  $input  The input response to parse.
     * @return array|null The parsed response as an associative array, or null if parsing fails.
     */
    protected function parseResponse(string $input): ?array
    {
        return json_decode(
            str($input)->replace([
                '```json',
                '```',
            ], '')->toString(), true
        );
    }

    /**
     * Performs revalidation on the given result.
     *
     * @param  string  $result  The result to revalidate.
     * @param  string  $errors  The validation errors.
     * @return mixed The result of handling the validation completion.
     *
     * @throws Throwable
     */
    protected function doRevalidate(string $result, string $errors = ''): mixed
    {

        $prompt = view('synapse::Prompts.ReValidateResponsePrompt', [
            'outputRules' => $this->getOutputSchema(),
            'errors' => $errors,
            'result' => $result,
        ])->render();

        $prompt = Message::make([
            'role' => 'user',
            'content' => $prompt,
        ]);

        return $this->integration->handleValidationCompletion($prompt);
    }

    /**
     * Retrieves the output rules as a JSON string.
     *
     * @return string|null The output rules encoded as a JSON string. Returns null if there are no output rules.
     */
    public function getOutputSchema(): ?string
    {
        if (! $this->hasOutputSchema) {
            return null;
        }

        $outputParserPromptPart = [];
        foreach ($this->outputSchema as $rule) {
            $outputParserPromptPart[$rule->getName()] = "({$rule->getRules()}) {$rule->getDescription()}";
        }

        return "```json\n".json_encode($outputParserPromptPart, JSON_PRETTY_PRINT)."\n```";
    }

    /**
     * Sets the output rules for validation.
     *
     * @param  array  $rules  The output rules to be set.
     */
    public function setOutputSchema(array $rules = []): void
    {
        $this->outputSchema = $rules;
    }

    /**
     * sets the initial output schema type this agent will use.
     */
    protected function initializeOutputSchema(): void
    {
        $this->outputSchema = $this->registerOutputSchema();
    }

    protected function registerOutputSchema(): array
    {
        return [];
    }
}
