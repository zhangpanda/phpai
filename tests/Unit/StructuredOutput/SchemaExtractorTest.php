<?php

declare(strict_types=1);

namespace PHPAI\Tests\Unit\StructuredOutput;

use PHPUnit\Framework\TestCase;
use PHPAI\StructuredOutput\AsOutput;
use PHPAI\StructuredOutput\Param;
use PHPAI\StructuredOutput\SchemaExtractor;

#[AsOutput(description: 'A test output')]
class TestOutput
{
    #[Param(description: 'The name', required: true)]
    public string $name;

    #[Param(description: 'The score')]
    public int $score;

    #[Param(description: 'Optional tags', required: false)]
    public ?array $tags = null;
}

final class SchemaExtractorTest extends TestCase
{
    public function testExtractsSchemaFromAttributes(): void
    {
        $extractor = new SchemaExtractor();
        $schema = $extractor->extract(TestOutput::class);

        $this->assertSame('object', $schema['type']);
        $this->assertSame('A test output', $schema['description']);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertArrayHasKey('score', $schema['properties']);
        $this->assertSame('string', $schema['properties']['name']['type']);
        $this->assertSame('integer', $schema['properties']['score']['type']);
        $this->assertContains('name', $schema['required']);
        $this->assertContains('score', $schema['required']);
    }
}
