<?php

declare(strict_types=1);

namespace App\Service\Catalog;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Lit l'export CSV des articles SumUp (« items export »).
 *
 * SEUL endroit où les colonnes du CSV SumUp sont connues : si SumUp renomme une colonne,
 * modifier uniquement la constante COLUMNS. Exemple réel : docs/samples/sumup-export.csv.
 *
 * Structure de l'export :
 * - produit simple : une ligne avec « Item id » et « Variant id » ;
 * - produit à variantes : une ligne parente (« Item id », pas de « Variant id », pas de prix)
 *   suivie de lignes sans « Item name » portant « Variations », « Variant id », prix et quantité.
 */
final readonly class SumupCsvParser
{
    /** Clé interne => en-tête exact dans le CSV SumUp. */
    public const array COLUMNS = [
        'name' => 'Item name',
        'variation' => 'Variations',
        'price' => 'Price',
        'trackInventory' => 'Track inventory? (Yes/No)',
        'quantity' => 'Quantity',
        'sku' => 'SKU',
        'description' => 'Description (Online Store and Invoices only)',
        'category' => 'Category',
        'displayInOnlineStore' => 'Display item in Online Store? (Yes/No)',
        'seoTitle' => 'SEO title (Online Store only)',
        'seoDescription' => 'SEO description (Online Store only)',
        'itemId' => 'Item id (Do not change)',
        'variantId' => 'Variant id (Do not change)',
    ];

    /** Colonnes sans lesquelles l'import n'a pas de sens. */
    private const array REQUIRED = ['name', 'variation', 'price', 'trackInventory', 'quantity', 'itemId', 'variantId'];

    public const int MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(
        #[Target('app.rich_text')]
        private HtmlSanitizerInterface $sanitizer,
    ) {
    }

    /**
     * @throws CatalogSourceException
     */
    public function parseFile(string $path): CatalogData
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new CatalogSourceException('Le fichier CSV est introuvable ou illisible.');
        }
        if (filesize($path) > self::MAX_BYTES) {
            throw new CatalogSourceException('Le fichier CSV dépasse 5 Mo : est-ce bien l\'export des articles SumUp ?');
        }

        $content = (string) file_get_contents($path);

        return $this->parseString($content);
    }

    /**
     * @throws CatalogSourceException
     */
    public function parseString(string $content): CatalogData
    {
        $content = $this->toUtf8($content);

        $stream = fopen('php://temp', 'r+');
        if (false === $stream) {
            throw new CatalogSourceException('Impossible de lire le fichier CSV.');
        }
        fwrite($stream, $content);
        rewind($stream);

        try {
            return $this->parseStream($stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param resource $stream
     */
    private function parseStream($stream): CatalogData
    {
        $header = fgetcsv($stream, null, ',', '"', '');
        if (false === $header || [null] === $header) {
            throw new CatalogSourceException('Le fichier CSV est vide.');
        }
        $positions = $this->mapHeader($header);

        $items = [];
        $warnings = [];
        /** @var array<string, mixed>|null $parent ligne parente en cours (produit à variantes) */
        $parent = null;
        /** @var list<CatalogVariant> $parentVariants */
        $parentVariants = [];
        $record = 1;

        while (false !== ($row = fgetcsv($stream, null, ',', '"', ''))) {
            ++$record;
            if ([null] === $row || '' === trim(implode('', array_map('strval', $row)))) {
                continue;
            }
            $data = $this->extract($row, $positions);

            $isVariantRow = '' === $data['name'] && '' === $data['itemId'] && '' !== $data['variantId'];

            if ($isVariantRow) {
                if (null === $parent) {
                    $warnings[] = \sprintf('Enregistrement %d : variante « %s » sans produit parent, ignorée.', $record, $data['variation']);
                    continue;
                }
                $parentVariants[] = $this->buildVariant($data, $record, $parent['trackInventory'], $data['variation']);
                continue;
            }

            // Nouvelle ligne produit : on clôture le produit à variantes précédent.
            if (null !== $parent) {
                $this->closeParent($parent, $parentVariants, $items, $warnings);
                $parent = null;
                $parentVariants = [];
            }

            if ('' === $data['name']) {
                $warnings[] = \sprintf('Enregistrement %d : produit sans nom, ignoré.', $record);
                continue;
            }
            if ('' === $data['itemId'] && '' === $data['sku']) {
                $warnings[] = \sprintf('Enregistrement %d : « %s » n\'a ni identifiant SumUp ni SKU, ignoré.', $record, $data['name']);
                continue;
            }

            $base = [
                'itemId' => $this->nullable($data['itemId']),
                'sku' => $this->nullable($data['sku']),
                'name' => $data['name'],
                'description' => $this->cleanHtml($data['description']),
                'category' => $this->nullable($data['category']),
                'displayed' => $this->yes($data['displayInOnlineStore']),
                'seoTitle' => $this->nullable($data['seoTitle']),
                'seoDescription' => $this->nullable($data['seoDescription']),
                'trackInventory' => $this->yes($data['trackInventory']),
                'record' => $record,
            ];

            if ('' === $data['variantId']) {
                // Ligne parente : les variantes suivent.
                $parent = $base;
                continue;
            }

            $items[] = $this->buildItem($base, [$this->buildVariant($data, $record, $base['trackInventory'], null)]);
        }

        if (null !== $parent) {
            $this->closeParent($parent, $parentVariants, $items, $warnings);
        }

        if ([] === $items) {
            throw new CatalogSourceException('Aucun produit trouvé dans le fichier CSV.');
        }

        return new CatalogData($items, $warnings);
    }

    /**
     * @param list<string|null> $header
     *
     * @return array<string, int>
     */
    private function mapHeader(array $header): array
    {
        $header = array_map(static fn (?string $h): string => trim((string) $h), $header);
        $positions = [];
        foreach (self::COLUMNS as $key => $label) {
            $index = array_search($label, $header, true);
            if (false !== $index) {
                $positions[$key] = (int) $index;
            }
        }

        $missing = array_diff(self::REQUIRED, array_keys($positions));
        if ([] !== $missing) {
            $labels = array_map(static fn (string $key): string => '« '.self::COLUMNS[$key].' »', $missing);

            throw new CatalogSourceException(\sprintf('Ce fichier ne ressemble pas à l\'export des articles SumUp : colonne(s) manquante(s) %s.', implode(', ', $labels)));
        }

        return $positions;
    }

    /**
     * @param list<string|null>  $row
     * @param array<string, int> $positions
     *
     * @return array<string, string>
     */
    private function extract(array $row, array $positions): array
    {
        $data = [];
        foreach (array_keys(self::COLUMNS) as $key) {
            $value = isset($positions[$key]) ? ($row[$positions[$key]] ?? '') : '';
            $data[$key] = trim((string) $value);
        }

        return $data;
    }

    /**
     * @param array<string, string> $data
     */
    private function buildVariant(array $data, int $record, bool $parentTracksInventory, ?string $name): CatalogVariant
    {
        // Les lignes de variantes laissent souvent « Track inventory » vide : on hérite du parent.
        $track = '' === $data['trackInventory'] ? $parentTracksInventory : $this->yes($data['trackInventory']);

        return new CatalogVariant(
            variantId: $this->nullable($data['variantId']),
            name: $this->nullable((string) $name),
            priceCents: $this->parsePrice($data['price'], $record),
            trackInventory: $track,
            stock: $this->parseQuantity($data['quantity'], $record),
        );
    }

    /**
     * @param array<string, mixed> $parent
     * @param list<CatalogVariant> $variants
     * @param list<CatalogItem>    $items
     * @param list<string>         $warnings
     */
    private function closeParent(array $parent, array $variants, array &$items, array &$warnings): void
    {
        if ([] === $variants) {
            $warnings[] = \sprintf('Enregistrement %d : « %s » n\'a ni identifiant de variante ni variantes, ignoré.', $parent['record'], $parent['name']);

            return;
        }

        $items[] = $this->buildItem($parent, $variants);
    }

    /**
     * @param array<string, mixed> $base
     * @param list<CatalogVariant> $variants
     */
    private function buildItem(array $base, array $variants): CatalogItem
    {
        return new CatalogItem(
            itemId: $base['itemId'],
            sku: $base['sku'],
            name: $base['name'],
            description: $base['description'],
            categoryName: $base['category'],
            displayedInOnlineStore: $base['displayed'],
            seoTitle: $base['seoTitle'],
            seoDescription: $base['seoDescription'],
            variants: $variants,
            line: $base['record'],
        );
    }

    private function parsePrice(string $value, int $record): ?int
    {
        if ('' === $value) {
            return null;
        }
        $normalized = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $value);
        if (1 !== preg_match('/^\d+(\.\d{1,2})?$/', $normalized)) {
            throw new CatalogSourceException(\sprintf('Enregistrement %d : prix « %s » illisible.', $record, $value));
        }

        return (int) round((float) $normalized * 100);
    }

    private function parseQuantity(string $value, int $record): int
    {
        if ('' === $value) {
            return 0;
        }
        if (1 !== preg_match('/^-?\d+([.,]0+)?$/', $value)) {
            throw new CatalogSourceException(\sprintf('Enregistrement %d : quantité « %s » illisible.', $record, $value));
        }

        return max(0, (int) $value);
    }

    private function yes(string $value): bool
    {
        return \in_array(mb_strtolower($value), ['yes', 'oui', 'true', '1'], true);
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return '' === $value ? null : $value;
    }

    private function cleanHtml(string $html): ?string
    {
        if ('' === trim($html)) {
            return null;
        }

        $clean = $this->sanitizer->sanitize($html);
        // Paragraphes vides laissés par l'éditeur SumUp (<p><br></p>).
        $clean = (string) preg_replace('#<p>(\s|&nbsp;|<br\s*/?>)*</p>#i', '', $clean);
        $clean = trim($clean);

        return '' === $clean ? null : $clean;
    }

    private function toUtf8(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        return $content;
    }
}
