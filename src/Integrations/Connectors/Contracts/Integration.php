<?php

declare(strict_types=1);

namespace UseTheFork\Synapse\Integrations\Connectors\Contracts;

use UseTheFork\Synapse\Integrations\ValueObjects\Message;
use UseTheFork\Synapse\Integrations\ValueObjects\Response;
use UseTheFork\Synapse\Tools\Contracts\Tool;

interface Integration
{
    /**
     * Handles the request to generate a chat response.
     *
     * @param  Message[]  $prompt  The chat prompt.
     * @param  Tool[]  $tools  Tools the agent has access to.
     * @param  array  $extraAgentArgs  Extra arguments to be passed to the agent.
     */
    public function handleCompletion(array $prompt, array $tools = [], array $extraAgentArgs = []): Response;

    /**
     * Forces a model to output its response in a specific format.
     *
     * @param  Message  $prompt  The chat message that is used for validation.
     * @param  array  $extraAgentArgs  Extra arguments to be passed to the agent.
     * @return Response The response from the chat request.
     */
    public function handleValidationCompletion(Message $prompt, array $extraAgentArgs = []): Response;
}