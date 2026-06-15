<?php

declare(strict_types=1);

namespace Synapse\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Synapse\Agent\Agent;
use Synapse\Agent\Team;
use Synapse\Chat\ChatInterface;
use Synapse\Chat\Message;
use Synapse\Chat\Response;
use Synapse\Chat\ToolCall;
use Synapse\Chat\Usage;
use Synapse\Tools\AsTool;
use Synapse\Tools\Param;

/**
 * Integration tests: Agent workflow, Tool calling, Team collaboration.
 * Uses mock providers (no real API calls).
 */
final class AgentIntegrationTest extends TestCase
{
    public function testAgentCompletesWithoutTools(): void
    {
        $provider = $this->staticProvider('The answer is 42.');
        $agent = Agent::create()->provider($provider)->system('Answer briefly');

        $response = $agent->run('What is the meaning of life?');

        $this->assertSame('The answer is 42.', $response->content);
        $this->assertSame([], $response->steps);
    }

    public function testAgentCallsToolAndReturns(): void
    {
        // Simulates: 1st call returns tool_call, 2nd call returns final answer
        $call = 0;
        $provider = new class($call) implements ChatInterface {
            private int $call;
            public function __construct(int &$call) { $this->call = &$call; }
            public function send(array $messages, array $options = []): Response
            {
                $this->call++;
                if ($this->call === 1) {
                    return new Response('', [new ToolCall('call_1', 'get_temp', ['city' => 'Beijing'])], new Usage(10, 10, 20), 'mock', 'tool_calls');
                }
                return new Response('Beijing is 25°C', [], new Usage(10, 10, 20), 'mock', 'stop');
            }
        };

        $tool = new class {
            #[AsTool(description: 'Get temperature')]
            public function get_temp(#[Param(description: 'City')] string $city): string
            {
                return json_encode(['city' => $city, 'temp' => 25]);
            }
        };

        $agent = Agent::create()
            ->provider($provider)
            ->system('Weather assistant')
            ->tools([$tool])
            ->maxIterations(5);

        $response = $agent->run('What is the temperature in Beijing?');

        $this->assertSame('Beijing is 25°C', $response->content);
        $this->assertCount(1, $response->steps);
        $this->assertSame('get_temp', $response->steps[0]['tool']);
    }

    public function testAgentRespectsMaxIterations(): void
    {
        // Provider always returns tool calls — agent should stop at maxIterations
        $provider = new class implements ChatInterface {
            public function send(array $messages, array $options = []): Response
            {
                return new Response('', [new ToolCall('c', 'noop', [])], new Usage(1, 1, 2), 'mock', 'tool_calls');
            }
        };

        $tool = new class {
            #[AsTool(description: 'noop')]
            public function noop(): string { return 'ok'; }
        };

        $agent = Agent::create()
            ->provider($provider)
            ->tools([$tool])
            ->maxIterations(3);

        $response = $agent->run('loop forever');
        $this->assertSame('Max iterations reached.', $response->content);
        $this->assertCount(3, $response->steps);
    }

    public function testTeamPipelinePassesOutputAsInput(): void
    {
        $upper = $this->transformProvider(fn($input) => strtoupper($input));
        $prefix = $this->transformProvider(fn($input) => "PREFIX: {$input}");

        $team = Team::create()
            ->add('upper', Agent::create()->provider($upper)->system(''))
            ->add('prefix', Agent::create()->provider($prefix)->system(''));

        $result = $team->pipeline('hello');
        $this->assertSame('PREFIX: HELLO', $result->content);
    }

    public function testTeamRouterDelegatesToCorrectAgent(): void
    {
        $team = Team::create()
            ->add('math', Agent::create()->provider($this->staticProvider('4'))->system(''))
            ->add('joke', Agent::create()->provider($this->staticProvider('Why did the chicken...'))->system(''))
            ->router(Agent::create()->provider($this->staticProvider('math'))->system(''));

        $result = $team->route('2+2?');
        $this->assertSame('4', $result->content);
        $this->assertSame('math', $result->steps[0]->agent);
    }

    private function staticProvider(string $response): ChatInterface
    {
        return new class($response) implements ChatInterface {
            public function __construct(private readonly string $r) {}
            public function send(array $messages, array $options = []): Response
            {
                return new Response($this->r, [], new Usage(5, 5, 10), 'mock', 'stop');
            }
        };
    }

    private function transformProvider(callable $fn): ChatInterface
    {
        return new class($fn) implements ChatInterface {
            public function __construct(private readonly \Closure $fn) {}
            public function send(array $messages, array $options = []): Response
            {
                $lastMsg = end($messages);
                $content = ($this->fn)($lastMsg->content ?? '');
                return new Response($content, [], new Usage(5, 5, 10), 'mock', 'stop');
            }
        };
    }
}
