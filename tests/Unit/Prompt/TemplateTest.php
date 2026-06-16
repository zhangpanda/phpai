<?php

declare(strict_types=1);

namespace PHPAI\Tests\Unit\Prompt;

use PHPUnit\Framework\TestCase;
use PHPAI\Prompt\Template;

final class TemplateTest extends TestCase
{
    public function testRendersVariables(): void
    {
        $result = Template::from('Hello {{name}}, you are {{age}} years old.')
            ->with('name', 'Alice')
            ->with('age', '30')
            ->render();

        $this->assertSame('Hello Alice, you are 30 years old.', $result);
    }

    public function testRendersConditionals(): void
    {
        $template = Template::from('Hello{{#if title}} {{title}}{{/if}} {{name}}');

        $with = $template->with('name', 'Alice')->with('title', 'Dr.')->render();
        $without = $template->with('name', 'Bob')->render();

        $this->assertSame('Hello Dr. Alice', $with);
        $this->assertSame('Hello Bob', $without);
    }

    public function testWithIfConditional(): void
    {
        $template = Template::from('{{greeting}} {{name}}')
            ->with('name', 'World')
            ->withIf(true, 'greeting', 'Hello')
            ->render();

        $this->assertSame('Hello World', $template);
    }

    public function testImmutability(): void
    {
        $base = Template::from('{{x}}');
        $a = $base->with('x', 'A');
        $b = $base->with('x', 'B');

        $this->assertSame('A', $a->render());
        $this->assertSame('B', $b->render());
    }
}
