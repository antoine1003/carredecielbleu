<?php

declare(strict_types=1);

namespace App\Service\Catalog;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Conserve le CSV uploadé entre l'aperçu et la validation de l'import.
 * Fichiers renommés au hasard, hors du dossier public, supprimés après usage ou après une heure.
 */
final readonly class ImportFileStorage
{
    private const array ALLOWED_MIME_TYPES = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
    private const int TTL_SECONDS = 3600;

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/imports')]
        private string $directory,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * @return string identifiant opaque du fichier stocké
     *
     * @throws CatalogSourceException fichier refusé (message destiné à l'utilisatrice)
     */
    public function store(?UploadedFile $file): string
    {
        if (null === $file || !$file->isValid()) {
            throw new CatalogSourceException('Choisissez le fichier CSV exporté depuis SumUp.');
        }
        if ('csv' !== mb_strtolower($file->getClientOriginalExtension())) {
            throw new CatalogSourceException('Le fichier doit être au format CSV (.csv).');
        }
        if (!\in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new CatalogSourceException('Ce fichier n\'est pas un fichier texte CSV.');
        }
        if ($file->getSize() > SumupCsvParser::MAX_BYTES) {
            throw new CatalogSourceException('Le fichier dépasse 5 Mo.');
        }

        $this->purgeExpired();
        $this->filesystem->mkdir($this->directory, 0o700);
        $id = bin2hex(random_bytes(16));
        $file->move($this->directory, $id.'.csv');

        return $id;
    }

    /**
     * @throws CatalogSourceException fichier expiré ou identifiant invalide
     */
    public function path(string $id): string
    {
        if (1 !== preg_match('/^[a-f0-9]{32}$/', $id)) {
            throw new CatalogSourceException('Fichier d\'import invalide.');
        }
        $path = $this->directory.'/'.$id.'.csv';
        if (!is_file($path)) {
            throw new CatalogSourceException('Le fichier a expiré : importez-le à nouveau.');
        }

        return $path;
    }

    public function remove(string $id): void
    {
        if (1 === preg_match('/^[a-f0-9]{32}$/', $id)) {
            $this->filesystem->remove($this->directory.'/'.$id.'.csv');
        }
    }

    private function purgeExpired(): void
    {
        foreach (glob($this->directory.'/*.csv') ?: [] as $path) {
            if (filemtime($path) < time() - self::TTL_SECONDS) {
                $this->filesystem->remove($path);
            }
        }
    }
}
