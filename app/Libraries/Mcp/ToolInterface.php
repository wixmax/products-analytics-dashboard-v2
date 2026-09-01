<?php

namespace App\Libraries\Mcp;

/**
 * Interface ToolInterface
 * Standard contract for all MCP (Model Context Protocol) tools.
 */
interface ToolInterface
{
    /**
     * Unique identifier/name of the tool.
     */
    public function getName(): string;

    /**
     * Human-readable description of what the tool accomplishes.
     */
    public function getDescription(): string;

    /**
     * JSON Schema object defining input arguments.
     */
    public function getInputSchema(): array;

    /**
     * Execute the tool with supplied arguments and context.
     *
     * @param array $args Tool arguments passed in JSON-RPC call
     * @param array|null $context Context containing auth user, tenant, etc.
     * @return array Result array to be formatted in tool response
     */
    public function execute(array $args, ?array $context = null): array;
}
