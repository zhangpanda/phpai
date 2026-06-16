<?php

declare(strict_types=1);

namespace Synapse\Tests\Unit\Chat;

use PHPUnit\Framework\TestCase;
use Synapse\Chat\Chat;
use Synapse\Chat\ChatInterface;
use Synapse\Chat\Message;
use Synapse\Chat\Response;
use Synapse\Chat\StreamableInterface;
use Synapse\Chat\StreamResponse;
use Synapse\Chat\Usage;

final class StreamTest extends TestCase
{
    public function testStreamReturnsStreamResponse(): void
    {
        $provider = new class implements ChatInterface, StreamableInterface {
            public function send(array $messages, array $options = []): Response
            {
                return new Response('full', [], new Usage(0, 0, 0), 'test', 'stop');
            }

            public function streamRaw(array $messages, array $options = []): \Generator
            {
                yield 'Hello';
                yield ' world';
            }
        };

        $stream = Chat::stream($provider, [Message::user('hi')]);
        $this->assertInstanceOf(StreamResponse::class, $stream);

        $chunks = [];
        foreach ($stream as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertSame(['Hello', ' world'], $chunks);
        $this->assertSame('Hello world', $stream->getFullContent());
    }

    public function testStreamThrowsForNonStreamableProvider(): void
    {
        $provider = new class implements ChatInterface {
            public function send(array $messages, array $options = []): Response
            {
                return new Response('full', [], new Usage(0, 0, 0), 'test', 'stop');
            }
        };

        $this->expectException(\RuntimeException::class);
        Chat::stream($provider, [Message::user('hi')]);
    }
}
