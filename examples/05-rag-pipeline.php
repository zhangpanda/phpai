<?php

/**
 * Example 5: RAG Pipeline
 *
 * Demonstrates indexing documents and querying with retrieval-augmented generation.
 * Requires: OPENAI_API_KEY environment variable
 */

require __DIR__ . '/../vendor/autoload.php';

use Synapse\Chat\Provider\OpenAI;
use Synapse\RAG\InMemoryStore;
use Synapse\RAG\OpenAIEmbedding;
use Synapse\RAG\RagPipeline;
use Synapse\RAG\RecursiveCharacterSplitter;
use Synapse\RAG\TextFileLoader;

$apiKey = getenv('OPENAI_API_KEY') ?: throw new RuntimeException('Set OPENAI_API_KEY');

$rag = new RagPipeline(
    loader: new TextFileLoader(),
    splitter: new RecursiveCharacterSplitter(chunkSize: 500, overlap: 50),
    embedding: new OpenAIEmbedding(apiKey: $apiKey),
    store: new InMemoryStore(),
);

// 索引文档（示例：索引当前项目的 README）
$indexed = $rag->index(__DIR__ . '/../README.md');
echo "已索引 {$indexed} 个文本块\n\n";

// 基于文档回答问题
$chat = new OpenAI(apiKey: $apiKey);
$answer = $rag->query('Synapse 支持哪些 LLM Provider？', $chat);

echo "问题: Synapse 支持哪些 LLM Provider？\n";
echo "回答: {$answer}\n";
