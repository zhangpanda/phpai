<?php

/**
 * Example 4: MCP Server
 *
 * Exposes PHP application as an MCP server for Claude Desktop.
 * Usage: php examples/04-mcp-server.php
 *
 * Claude Desktop config (claude_desktop_config.json):
 * {
 *   "mcpServers": {
 *     "demo": { "command": "php", "args": ["/path/to/04-mcp-server.php"] }
 *   }
 * }
 */

require __DIR__ . '/../vendor/autoload.php';

use Synapse\MCP\McpServer;
use Synapse\MCP\McpTool;
use Synapse\MCP\McpResource;
use Synapse\MCP\Param;

class DemoTools
{
    #[McpTool(description: '计算数学表达式')]
    public function calculate(string $expression): string
    {
        $result = eval("return {$expression};");
        return "计算结果: {$expression} = {$result}";
    }

    #[McpTool(description: '获取当前时间')]
    public function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

#[McpResource(uri: 'demo://status', description: '应用状态')]
class StatusResource
{
    public function read(): string
    {
        return json_encode([
            'status' => 'running',
            'php_version' => PHP_VERSION,
            'uptime' => time(),
        ]);
    }
}

McpServer::create('demo-server', '1.0.0')
    ->addTool(new DemoTools())
    ->addResource(new StatusResource())
    ->serveStdio();
