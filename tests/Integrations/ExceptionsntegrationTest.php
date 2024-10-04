<?php

declare(strict_types=1);

use UseTheFork\Synapse\Agent;
use UseTheFork\Synapse\Contracts\Agent\HasOutputSchema;
use UseTheFork\Synapse\Contracts\Integration;
use UseTheFork\Synapse\Contracts\Memory;
use UseTheFork\Synapse\Exceptions\MissingResolverException;
use UseTheFork\Synapse\Integrations\OpenAIIntegration;
use UseTheFork\Synapse\Memory\CollectionMemory;
use UseTheFork\Synapse\Traits\Agent\ValidatesOutputSchema;
use UseTheFork\Synapse\ValueObject\SchemaRule;

test('Resolve Memory', function (): void {

    class ExceptionTestAgent extends Agent implements HasOutputSchema
    {
        use ValidatesOutputSchema;

        protected string $promptView = 'synapse::Prompts.SimplePrompt';

        public function resolveIntegration(): Integration
        {
            return new OpenAIIntegration;
        }

        public function resolveOutputSchema(): array
        {
            return [
                SchemaRule::make([
                    'name' => 'answer',
                    'rules' => 'required|string',
                    'description' => 'your final answer to the query.',
                ]),
            ];
        }
    }

    $agent = new ExceptionTestAgent;
    $message = $agent->handle(['input' => 'hello!']);
})->throws(MissingResolverException::class, 'The "ManagesMemory" trait requires a "resolveMemory" method.');

test('Resolve Integration', function (): void {

    class ResolveIntegrationExceptionTestAgent extends Agent implements HasOutputSchema
    {
        use ValidatesOutputSchema;

        protected string $promptView = 'synapse::Prompts.SimplePrompt';

        public function resolveMemory(): Memory
        {
            return new CollectionMemory;
        }

        public function resolveOutputSchema(): array
        {
            return [
                SchemaRule::make([
                    'name' => 'answer',
                    'rules' => 'required|string',
                    'description' => 'your final answer to the query.',
                ]),
            ];
        }
    }

    $agent = new ResolveIntegrationExceptionTestAgent;
    $message = $agent->handle(['input' => 'hello!']);
})->throws(MissingResolverException::class, 'The "ManagesIntegration" trait requires a "resolveIntegration" method.');

test('Resolve OutputSchema', function (): void {

    class ResolveOutputSchemaExceptionTestAgent extends Agent implements HasOutputSchema
    {
        use ValidatesOutputSchema;

        protected string $promptView = 'synapse::Prompts.SimplePrompt';

        public function resolveMemory(): Memory
        {
            return new CollectionMemory;
        }

        public function resolveIntegration(): Integration
        {
            return new OpenAIIntegration;
        }
    }

    $agent = new ResolveOutputSchemaExceptionTestAgent;
    $message = $agent->handle(['input' => 'hello!']);
})->throws(MissingResolverException::class, 'The "ValidatesOutputSchema" trait requires a "resolveOutputSchema" method.');
