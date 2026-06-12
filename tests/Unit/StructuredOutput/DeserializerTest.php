<?php

declare(strict_types=1);

namespace Synapse\Tests\Unit\StructuredOutput;

use PHPUnit\Framework\TestCase;
use Synapse\StructuredOutput\Deserializer;

class SimpleOutput
{
    public string $name;
    public int $age;
    public ?string $email = null;
}

final class DeserializerTest extends TestCase
{
    public function testDeserializesJsonToObject(): void
    {
        $deserializer = new Deserializer();
        $json = '{"name": "Alice", "age": 30, "email": "alice@example.com"}';

        $result = $deserializer->deserialize($json, SimpleOutput::class);

        $this->assertInstanceOf(SimpleOutput::class, $result);
        $this->assertSame('Alice', $result->name);
        $this->assertSame(30, $result->age);
        $this->assertSame('alice@example.com', $result->email);
    }

    public function testHandlesMissingOptionalFields(): void
    {
        $deserializer = new Deserializer();
        $json = '{"name": "Bob", "age": 25}';

        $result = $deserializer->deserialize($json, SimpleOutput::class);

        $this->assertSame('Bob', $result->name);
        $this->assertNull($result->email);
    }
}
