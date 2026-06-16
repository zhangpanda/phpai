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

use PHPAI\MCP\McpServer;
use PHPAI\MCP\McpTool;
use PHPAI\MCP\McpResource;
use PHPAI\MCP\Param;

class DemoTools
{
    #[McpTool(description: '计算数学表达式')]
    public function calculate(string $expression): string
    {
        // Safe: only allow digits, operators, spaces, parentheses, dots
        if (!preg_match('/^[\d\s+\-*\/().]+$/', $expression)) {
            return "错误: 不合法的表达式";
        }
        try {
            $result = eval("return ({$expression});"); // @phpstan-ignore-line — validated
            return "计算结果: {$expression} = {$result}";
        } catch (\Throwable $e) {
            return "错误: " . $e->getMessage();
        }
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
