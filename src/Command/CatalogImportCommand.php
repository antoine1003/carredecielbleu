<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Catalog\CatalogImporter;
use App\Service\Catalog\CatalogSourceException;
use App\Service\Catalog\ImportReport;
use App\Service\Catalog\SumupCsvParser;
use App\Service\Catalog\SumupCsvSource;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:catalog:import', description: 'Importe le catalogue depuis l\'export CSV des articles SumUp')]
final readonly class CatalogImportCommand
{
    public function __construct(
        private SumupCsvParser $parser,
        private CatalogImporter $importer,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Chemin du fichier CSV exporté depuis SumUp')]
        string $file,
        #[Option(description: 'Affiche les changements sans rien enregistrer', name: 'dry-run')]
        bool $dryRun = false,
    ): int {
        $source = new SumupCsvSource($this->parser, $file);

        try {
            $report = $dryRun ? $this->importer->preview($source) : $this->importer->import($source);
        } catch (CatalogSourceException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->render($io, $report);

        return Command::SUCCESS;
    }

    private function render(SymfonyStyle $io, ImportReport $report): void
    {
        $io->title(($report->applied ? 'Import' : 'Aperçu (rien n\'est enregistré)').' : '.$report->sourceLabel);

        if ([] !== $report->created) {
            $io->section('Produits créés');
            $io->listing(array_map(
                static fn (array $c): string => \sprintf('%s (%d variante(s), %s)', $c['name'], $c['variants'], $c['visible'] ? 'visible' : 'masqué'),
                $report->created,
            ));
        }
        if ([] !== $report->updated) {
            $io->section('Produits modifiés');
            $rows = [];
            foreach ($report->updated as $update) {
                foreach ($update['changes'] as $change) {
                    $rows[] = [$update['name'], $change['field'], $change['old'], $change['new']];
                }
            }
            $io->table(['Produit', 'Champ', 'Avant', 'Après'], $rows);
        }
        if ([] !== $report->notSynced) {
            $io->section('Absents du fichier (marqués « non synchronisé », non supprimés)');
            $io->listing($report->notSynced);
        }
        if ([] !== $report->categoriesCreated) {
            $io->section('Catégories créées');
            $io->listing($report->categoriesCreated);
        }
        if ([] !== $report->warnings) {
            $io->warning($report->warnings);
        }

        $io->success($report->summary());
    }
}
