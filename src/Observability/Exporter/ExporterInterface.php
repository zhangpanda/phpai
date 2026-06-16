<?php

declare(strict_types=1);

namespace PHPAI\Observability\Exporter;

use PHPAI\Observability\Span;

interface ExporterInterface
{
    public function export(Span $span): void;
    public function flush(): void;
}
