<?php

declare(strict_types=1);

namespace App\Service\Catalog;

/**
 * Résultat (ou aperçu) d'un import de catalogue, lisible tel quel dans l'admin et la console.
 */
final class ImportReport
{
    /** @var list<array{name: string, variants: int, visible: bool}> */
    public array $created = [];

    /** @var list<array{name: string, changes: list<array{field: string, old: string, new: string}>}> */
    public array $updated = [];

    /** @var list<string> */
    public array $notSynced = [];

    /** @var list<string> */
    public array $categoriesCreated = [];

    public int $unchanged = 0;

    /** @var list<string> */
    public array $warnings = [];

    public function __construct(
        public readonly string $sourceLabel,
        public readonly bool $applied,
    ) {
    }

    public function hasChanges(): bool
    {
        return [] !== $this->created || [] !== $this->updated || [] !== $this->notSynced || [] !== $this->categoriesCreated;
    }

    public function summary(): string
    {
        return \sprintf(
            '%d créé(s), %d modifié(s), %d inchangé(s), %d marqué(s) « non synchronisé », %d catégorie(s) créée(s).',
            \count($this->created),
            \count($this->updated),
            $this->unchanged,
            \count($this->notSynced),
            \count($this->categoriesCreated),
        );
    }
}
