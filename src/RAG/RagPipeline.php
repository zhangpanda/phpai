<?php

declare(strict_types=1);

namespace PHPAI\RAG;

use PHPAI\Chat\ChatInterface;
use PHPAI\Chat\Message;

final class RagPipeline
{
    public function __construct(
        private readonly LoaderInterface $loader,
        private readonly SplitterInterface $splitter,
        private readonly EmbeddingInterface $embedding,
        private readonly VectorStoreInterface $store,
    ) {}

    /** Index documents from source, returns chunk count */
    public function index(string $source): int
    {
        $documents = $this->loader->load($source);
        $totalChunks = 0;

        foreach ($documents as $doc) {
            $chunks = $this->splitter->split($doc);
            $texts = array_map(fn(Chunk $c) => $c->content, $chunks);
            $embeddings = $this->embedding->embedBatch($texts);

            $embeddedChunks = [];
            foreach ($chunks as $i => $chunk) {
                $embeddedChunks[] = new Chunk(
                    content: $chunk->content,
                    embedding: $embeddings[$i],
                    metadata: $chunk->metadata,
                    id: $chunk->id,
                );
            }

            $this->store->upsert($embeddedChunks);
            $totalChunks += count($embeddedChunks);
        }

        return $totalChunks;
    }

    /** Query with RAG: retrieve context then generate answer */
    public function query(string $question, ChatInterface $chat, int $topK = 5): string
    {
        $queryEmbedding = $this->embedding->embed($question);
        $chunks = $this->store->search($queryEmbedding, $topK);
        $context = implode("\n\n", array_map(fn(Chunk $c) => $c->content, $chunks));

        $response = $chat->send([
            Message::system("Answer based on the following context:\n\n{$context}"),
            Message::user($question),
        ]);

        return $response->content;
    }
}
