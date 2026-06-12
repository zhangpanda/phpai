<?php

/**
 * Example 1: Simple Chat
 *
 * Usage: php examples/01-simple-chat.php
 * Requires: OPENAI_API_KEY environment variable
 */

require __DIR__ . '/../vendor/autoload.php';

use Synapse\Chat\Message;
use Synapse\Chat\Provider\OpenAI;

$chat = new OpenAI(
    apiKey: getenv('OPENAI_API_KEY') ?: throw new RuntimeException('Set OPENAI_API_KEY'),
);

$response = $chat->send([
    Message::system('You are a helpful PHP expert. Be concise.'),
    Message::user('What is the most important feature in PHP 8.3?'),
]);

echo "Response: {$response->content}\n";
echo "Tokens: {$response->usage->totalTokens}\n";
echo "Model: {$response->model}\n";
