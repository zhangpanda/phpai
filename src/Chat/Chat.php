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
     * @param class-string<T> $outputClass
     * @return T
     */
    public static function structured(ChatInterface $chat, array $messages, string $outputClass): object
    {
        self::$extractor ??= new SchemaExtractor();
        self::$deserializer ??= new Deserializer();

        $schema = self::$extractor->extract($outputClass);

        $response = $chat->send($messages, [
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'response',
                    'schema' => $schema,
                    'strict' => true,
                ],
            ],
        ]);

        return self::$deserializer->deserialize($response->content, $outputClass);
    }

    /**
     * Stream a chat response (OpenAI compatible providers).
     */
    public static function stream(ChatInterface $chat, array $messages, array $options = []): StreamResponse
    {
        if (!$chat instanceof Provider\OpenAI && !method_exists($chat, 'streamRaw')) {
            throw new \RuntimeException('Provider does not support streaming');
        }

        $options['stream'] = true;
        $generator = $chat->streamRaw($messages, $options);
        return new StreamResponse($generator);
    }
}
