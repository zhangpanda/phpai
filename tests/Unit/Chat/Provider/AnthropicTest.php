<?php

declare(strict_types=1);

namespace PHPAI\Tests\Unit\Chat\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as HttpResponse;
use PHPUnit\Framework\TestCase;
use PHPAI\Chat\Message;
use PHPAI\Chat\Provider\Anthropic;

final class AnthropicTest extends TestCase
{
    public function testSendsRequestAndParsesResponse(): void
    {
        $mock = new MockHandler([
            new HttpResponse(200, [], json_encode([
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'model' => 'claude-sonnet-4-20250514',
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ])),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $anthropic = new Anthropic(apiKey: 'test-key', client: $client);

        $response = $anthropic->send([
            Message::system('You are helpful.'),
            Message::user('Hi'),
        ]);

        $this->assertSame('Hello!', $response->content);
        $this->assertSame(10, $response->usage->promptTokens);
        $this->assertSame(5, $response->usage->completionTokens);
        $this->assertSame('end_turn', $response->finishReason);
    }

    public function testParsesToolCalls(): void
    {
        $mock = new MockHandler([
            new HttpResponse(200, [], json_encode([
                'content' => [
                    ['type' => 'text', 'text' => ''],
                    ['type' => 'tool_use', 'id' => 'call_1', 'name' => 'get_weather', 'input' => ['city' => 'Tokyo']],
                ],
                'model' => 'claude-sonnet-4-20250514',
                'stop_reason' => 'tool_use',
                'usage' => ['input_tokens' => 20, 'output_tokens' => 15],
            ])),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $anthropic = new Anthropic(apiKey: 'test-key', client: $client);

        $response = $anthropic->send([Message::user('Weather in Tokyo?')]);

        $this->assertTrue($response->hasToolCalls());
        $this->assertCount(1, $response->toolCalls);
        $this->assertSame('get_weather', $response->toolCalls[0]->name);
        $this->assertSame(['city' => 'Tokyo'], $response->toolCalls[0]->arguments);
    }
}
