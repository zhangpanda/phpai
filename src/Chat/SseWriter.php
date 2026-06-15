<?php

declare(strict_types=1);

namespace Synapse\Chat;

/**
 * Framework-agnostic SSE (Server-Sent Events) writer for streaming AI responses.
 *
 * Usage in plain PHP:
 *   SseWriter::start();
 *   foreach (Chat::stream($chat, $messages) as $chunk) {
 *       SseWriter::event($chunk);
 *   }
 *   SseWriter::done();
 *
 * Usage with one call:
 *   SseWriter::stream($chat, $messages);
 */
final class SseWriter
{
    public static function start(): void
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        if (ob_get_level() > 0) {
            ob_end_flush();
        }
    }

    public static function event(string $data, string $type = ''): void
    {
        if ($type !== '') {
            echo "event: {$type}\n";
        }
        echo "data: " . json_encode(['content' => $data]) . "\n\n";
        flush();
    }

    public static function done(): void
    {
        echo "data: [DONE]\n\n";
        flush();
    }

    /**
     * One-shot: sends headers, streams all chunks, and closes.
     *
     * @param list<Message> $messages
     * @param array<string, mixed> $options
     */
    public static function stream(ChatInterface $chat, array $messages, array $options = []): void
    {
        self::start();

        $stream = Chat::stream($chat, $messages, $options);
        foreach ($stream as $chunk) {
            self::event($chunk);
        }

        self::done();
    }
}
