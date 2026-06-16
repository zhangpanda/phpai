<?php

/**
 * Example 2: Structured Output
 *
 * Demonstrates using PHP Attributes to define output schema
 * and automatic JSON-to-object hydration.
 */

require __DIR__ . '/../vendor/autoload.php';

use PHPAI\Chat\Message;
use PHPAI\Chat\Provider\OpenAI;
use PHPAI\StructuredOutput\AsOutput;
use PHPAI\StructuredOutput\Deserializer;
use PHPAI\StructuredOutput\Param;
use PHPAI\StructuredOutput\SchemaExtractor;

// Define output structure via Attributes
#[AsOutput(description: 'Sentiment analysis result')]
class SentimentResult
{
    #[Param(description: 'Detected sentiment', enum: ['positive', 'negative', 'neutral'])]
    public string $sentiment;

    #[Param(description: 'Confidence score from 0 to 1')]
    public float $confidence;

    #[Param(description: 'Key phrases that indicate the sentiment')]
    public array $keyPhrases;
}

// Extract JSON Schema from Attributes
$extractor = new SchemaExtractor();
$schema = $extractor->extract(SentimentResult::class);

echo "Generated Schema:\n";
echo json_encode($schema, JSON_PRETTY_PRINT) . "\n\n";

// Send to LLM with schema constraint
$chat = new OpenAI(
    apiKey: getenv('OPENAI_API_KEY') ?: throw new RuntimeException('Set OPENAI_API_KEY'),
);

$response = $chat->send(
    messages: [
        Message::system('Analyze the sentiment. Respond in JSON matching the schema.'),
        Message::user('I absolutely love this new PHP framework! It makes AI integration so easy!'),
    ],
    options: [
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'sentiment',
                'schema' => $schema,
                'strict' => true,
            ],
        ],
    ],
);

// Hydrate JSON response to typed object
$deserializer = new Deserializer();
$result = $deserializer->deserialize($response->content, SentimentResult::class);

echo "Sentiment: {$result->sentiment}\n";
echo "Confidence: {$result->confidence}\n";
echo "Key Phrases: " . implode(', ', $result->keyPhrases) . "\n";
