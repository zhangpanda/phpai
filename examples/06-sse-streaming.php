<?php

/**
 * Example: SSE Streaming for Web Apps
 *
 * This endpoint streams AI responses to the browser via Server-Sent Events.
 * Works with any PHP setup (plain PHP, Slim, Symfony, Laravel).
 *
 * Frontend usage:
 *   const source = new EventSource('/api/chat-stream?q=Hello');
 *   source.onmessage = (e) => {
 *       if (e.data === '[DONE]') { source.close(); return; }
 *       const { content } = JSON.parse(e.data);
 *       document.getElementById('output').textContent += content;
 *   };
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Synapse\Chat\Message;
use Synapse\Chat\Provider\DeepSeek;
use Synapse\Chat\SseWriter;

$chat = new DeepSeek(apiKey: $_ENV['DEEPSEEK_API_KEY'] ?? getenv('DEEPSEEK_API_KEY') ?: die('Set DEEPSEEK_API_KEY'));

$question = $_GET['q'] ?? '用一句话介绍 PHP 8.3';
if (strlen($question) > 2000) {
    http_response_code(400);
    die('Input too long (max 2000 chars)');
}

// One-liner: sends headers, streams chunks, closes connection
SseWriter::stream($chat, [
    Message::system('你是一个简洁的 PHP 助手'),
    Message::user($question),
]);
