<?php

declare(strict_types=1);

namespace PHPAI\Laravel\Http;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PHPAI\Chat\ChatInterface;
use PHPAI\Chat\Chat;
use PHPAI\Chat\Message;

final class SseStream
{
    /**
     * Create an SSE StreamedResponse from a chat streaming call.
     *
     * @param ChatInterface $chat
     * @param list<Message> $messages
     * @param array<string, mixed> $options
     */
    public static function response(ChatInterface $chat, array $messages, array $options = []): StreamedResponse
    {
        return new StreamedResponse(function () use ($chat, $messages, $options) {
            $stream = Chat::stream($chat, $messages, $options);

            foreach ($stream as $chunk) {
                echo "data: " . json_encode(['content' => $chunk]) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            echo "data: [DONE]\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
