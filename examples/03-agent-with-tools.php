<?php

/**
 * Example 3: Agent with Tools
 *
 * Demonstrates building an Agent that uses tools via ReAct loop.
 */

require __DIR__ . '/../vendor/autoload.php';

use Synapse\Agent\Agent;
use Synapse\Agent\Memory\BufferMemory;
use Synapse\Chat\Provider\OpenAI;
use Synapse\Tools\AsTool;
use Synapse\Tools\Param;

// Define tools using Attributes
class MathTool
{
    #[AsTool(description: 'Calculate a math expression (supports +, -, *, / with integers)')]
    public function calculate(
        #[Param(description: 'Math expression, e.g. "2 + 2 * 3"')] string $expression,
    ): string {
        // Safe evaluation: only allow digits, operators, spaces, parentheses, dots
        if (!preg_match('/^[\d\s+\-*\/().]+$/', $expression)) {
            return "Error: invalid expression";
        }
        try {
            $result = eval("return ({$expression});"); // @phpstan-ignore-line — validated input
            return "Result: {$result}";
        } catch (\Throwable $e) {
            return "Error: " . $e->getMessage();
        }
    }
}

class WeatherTool
{
    #[AsTool(description: 'Get current weather for a city')]
    public function getWeather(
        #[Param(description: 'City name')] string $city,
    ): string {
        // Simulated weather data
        $temps = ['Beijing' => 28, 'Tokyo' => 22, 'London' => 15, 'New York' => 25];
        $temp = $temps[$city] ?? rand(10, 35);
        return json_encode(['city' => $city, 'temperature' => $temp, 'unit' => 'celsius']);
    }
}

// Build the Agent
$agent = Agent::create()
    ->provider(new OpenAI(
        apiKey: getenv('OPENAI_API_KEY') ?: throw new RuntimeException('Set OPENAI_API_KEY'),
        model: 'gpt-4o-mini',
    ))
    ->system('You are a helpful assistant. Use tools when needed.')
    ->tools([new MathTool(), new WeatherTool()])
    ->memory(new BufferMemory(maxMessages: 20))
    ->maxIterations(5);

// Run the agent
$response = $agent->run('What is the weather in Beijing? Also what is 123 * 456?');

echo "Answer: {$response->content}\n\n";
echo "Steps taken:\n";
foreach ($response->steps as $i => $step) {
    echo "  " . ($i + 1) . ". Called {$step['tool']}(" . json_encode($step['args']) . ")\n";
    echo "     → {$step['result']}\n";
}
echo "\nTotal tokens: {$response->totalUsage->totalTokens}\n";
