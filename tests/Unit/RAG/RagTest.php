<?php

declare(strict_types=1);

namespace PHPAI\Tests\Unit\RAG;

use PHPUnit\Framework\TestCase;
use PHPAI\RAG\Chunk;
use PHPAI\RAG\Document;
use PHPAI\RAG\InMemoryStore;
use PHPAI\RAG\RecursiveCharacterSplitter;

final class RagTest extends TestCase
{
    public function testSplitterSplitsDocument(): void
    {
        $splitter = new RecursiveCharacterSplitter(chunkSize: 50, overlap: 10);
        $doc = new Document(content: str_repeat('a', 120), metadata: ['source' => 'test']);

        $chunks = $splitter->split($doc);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(50, strlen($chunk->content));
            $this->assertSame('test', $chunk->metadata['source']);
        }
    }

    public function testInMemoryStoreSearchesCosineSimilarity(): void
    {
        $store = new InMemoryStore();
        $store->upsert([
            new Chunk(content: 'PHP is great', embedding: [1.0, 0.0, 0.0]),
            new Chunk(content: 'Python is popular', embedding: [0.0, 1.0, 0.0]),
            new Chunk(content: 'PHP frameworks', embedding: [0.9, 0.1, 0.0]),
        ]);

        // Search with embedding close to PHP
        $results = $store->search([1.0, 0.0, 0.0], topK: 2);

        $this->assertCount(2, $results);
        $this->assertSame('PHP is great', $results[0]->content);
        $this->assertSame('PHP frameworks', $results[1]->content);
    }

    public function testInMemoryStoreCount(): void
    {
        $store = new InMemoryStore();
        $this->assertSame(0, $store->count());

        $store->upsert([new Chunk(content: 'test', embedding: [1.0])]);
        $this->assertSame(1, $store->count());
    }

    public function testSplitterPreservesMetadata(): void
    {
        $splitter = new RecursiveCharacterSplitter(chunkSize: 100, overlap: 0);
        $doc = new Document(content: 'short text', metadata: ['file' => 'a.txt']);

        $chunks = $splitter->split($doc);

        $this->assertCount(1, $chunks);
        $this->assertSame('a.txt', $chunks[0]->metadata['file']);
        $this->assertSame(0, $chunks[0]->metadata['chunk_index']);
    }
}
