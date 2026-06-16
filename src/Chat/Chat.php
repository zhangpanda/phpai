<?php

declare(strict_types=1);

namespace Synapse\Chat;

use Synapse\StructuredOutput\Deserializer;
use Synapse\StructuredOutput\SchemaExtractor;

/**
 * Adds sendStructured() and stream() convenience methods to a ChatInterface.
 */
final class Chat
{
    private static ?SchemaExtractor $extractor = null;
    private static ?Deserializer $deserializer = null;

    /**
     * @template T of object
     * @param list<Message> $messages
     * @param class-string<T> $outputClass
     * @param array<string, mixed> $options
     * @return T
     */
    public static function structured(ChatInterface $chat, array $messages, string $outputClass, array $options = []): object
    {
        self::$extractor ??= new SchemaExtractor();
        self::$deserializer ??= new Deserializer();

        $schema = self::$extractor->extract($outputClass);

        $options['response_format'] = [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'response',
                'schema' => $schema,
                'strict' => true,
            ],
        ];

        $response = $chat->send($messages, $options);

        return self::$deserializer->deserialize($response->content, $outputClass);
    }

    /**
     * Stream a chat response.
     */
    public static function stream(ChatInterface $chat, array $messages, array $options = []): StreamResponse
    {
        if (!$chat instanceof StreamableInterface) {
            throw new \RuntimeException('Provider does not support streaming');
        }

        $generator = $chat->streamRaw($messages, $options);
        return new StreamResponse($generator);
    }
}
