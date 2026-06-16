<?php

declare(strict_types=1);

namespace Synapse\Tests\Unit\Agent;

use PHPUnit\Framework\TestCase;
use Synapse\Agent\Agent;
use Synapse\Agent\Team;
use Synapse\Agent\TeamResult;
use Synapse\Chat\ChatInterface;
use Synapse\Chat\Message;
use Synapse\Chat\Response;
use Synapse\Chat\Usage;

final class TeamTest extends TestCase
{
    public function testPipelineRunsAgentsInSequence(): void
    {
        $team = Team::create()
            ->add('translator', Agent::create()->provider($this->mockProvider('Translated: {input}'))->system('Translate'))
            ->add('summarizer', Agent::create()->provider($this->mockProvider('Summary: {input}'))->system('Summarize'));

        $result = $team->pipeline('Hello world');

        $this->assertInstanceOf(TeamResult::class, $result);
        $this->assertCount(2, $result->steps);
        $this->assertSame('translator', $result->steps[0]->agent);
        $this->assertSame('summarizer', $result->steps[1]->agent);
    }

    public function testPipelineWithCustomOrder(): void
    {
        $team = Team::create()
            ->add('a', Agent::create()->provider($this->mockProvider('A'))->system('A'))
            ->add('b', Agent::create()->provider($this->mockProvider('B'))->system('B'));

        $result = $team->pipeline('input', ['b', 'a']);
        $this->assertSame('b', $result->steps[0]->agent);
        $this->assertSame('a', $result->steps[1]->agent);
    }

    public function testPipelineThrowsOnUnknownAgent(): void
    {
        $team = Team::create()
            ->add('a', Agent::create()->provider($this->mockProvider('A'))->system('A'));

        $this->expectException(\InvalidArgumentException::class);
        $team->pipeline('input', ['nonexistent']);
    }

    public function testRouteThrowsWithoutRouter(): void
    {
        $team = Team::create()
            ->add('a', Agent::create()->provider($this->mockProvider('A'))->system('A'));

        $this->expectException(\RuntimeException::class);
        $team->route('input');
    }

    public function testRouteDispatchesToCorrectAgent(): void
    {
        $team = Team::create()
            ->add('coder', Agent::create()->provider($this->mockProvider('code result'))->system('Code'))
            ->add('writer', Agent::create()->provider($this->mockProvider('writing result'))->system('Write'))
            ->router(Agent::create()->provider($this->mockProvider('coder'))->system('Pick agent'));

        $result = $team->route('Write some PHP code');
        $this->assertSame('code result', $result->content);
        $this->assertSame('coder', $result->steps[0]->agent);
    }

    private function mockProvider(string $fixedResponse): ChatInterface
    {
        return new class($fixedResponse) implements ChatInterface {
            public function __construct(private readonly string $response) {}

            public function send(array $messages, array $options = []): Response
            {
                $content = str_replace('{input}', end($messages)->content ?? '', $this->response);
                return new Response($content, [], new Usage(10, 10, 20), 'mock', 'stop');
            }
        };
    }
}
