<?php

declare(strict_types=1);

namespace App\Service\Catalog;

/**
 * Source de catalogue : un fichier d'export CSV SumUp.
 */
final readonly class SumupCsvSource implements ProductSourceInterface
{
    public function __construct(
        private SumupCsvParser $parser,
        private string $path,
        private ?string $label = null,
    ) {
    }

    public function read(): CatalogData
    {
        return $this->parser->parseFile($this->path);
    }

    public function getLabel(): string
    {
        return $this->label ?? basename($this->path);
    }
}
