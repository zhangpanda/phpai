<?php

declare(strict_types=1);

namespace PHPAI\RAG;

final class TextFileLoader implements LoaderInterface
{
    public function load(string $source): array
    {
        if (is_dir($source)) {
            $docs = [];
            $files = glob($source . '/*.{txt,md}', GLOB_BRACE);
            foreach ($files ?: [] as $file) {
                if (!is_file($file)) {
                    continue;
                }
                $content = file_get_contents($file);
                if ($content === false) {
                    continue;
                }
                $docs[] = new Document(
                    content: $content,
                    metadata: ['source' => $file],
                );
            }
            return $docs;
        }

        if (!is_file($source)) {
            throw new \RuntimeException("File not found: {$source}");
        }

        $content = file_get_contents($source);
        if ($content === false) {
            throw new \RuntimeException("Cannot read file: {$source}");
        }

        return [new Document(
            content: $content,
            metadata: ['source' => $source],
        )];
    }
}
