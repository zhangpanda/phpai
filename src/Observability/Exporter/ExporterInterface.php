<?php

declare(strict_types=1);

namespace Synapse\Observability\Exporter;

use Synapse\Observability\Span;

interface ExporterInterface
{
    public function export(Span $span): void;
    public function flush(): void;
}
