<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\Registration;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV des inscriptions (ouvrable directement dans Excel / LibreOffice :
 * UTF-8 avec BOM, séparateur point-virgule).
 */
final readonly class RegistrationCsvExporter
{
    private const array HEADER = ['Atelier', 'Date', 'Lieu', 'Statut de la date', 'Prénom', 'Nom', 'Email', 'Téléphone', 'Nom affiché en public', 'Inscrit le'];

    /**
     * @param iterable<Registration> $registrations
     */
    public function response(iterable $registrations, string $filename): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($registrations): void {
            $out = fopen('php://output', 'w');
            if (false === $out) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::HEADER, ';', '"', '');
            foreach ($registrations as $registration) {
                $session = $registration->getSession();
                fputcsv($out, array_map($this->neutralize(...), [
                    (string) $session?->getWorkshop()?->getTitle(),
                    (string) $session?->getStartsAt()?->format('d/m/Y H:i'),
                    (string) $session?->getEffectiveLocation(),
                    (string) $session?->getStatus()->label(),
                    (string) $registration->getFirstName(),
                    (string) $registration->getLastName(),
                    (string) $registration->getEmail(),
                    (string) $registration->getPhone(),
                    $registration->isShowNamePublicly() ? 'oui' : 'non',
                    $registration->getCreatedAt()->format('d/m/Y H:i'),
                ]), ';', '"', '');
            }
            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename));
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /** Empêche l'injection de formules dans le tableur (=, +, -, @ en début de cellule). */
    private function neutralize(string $value): string
    {
        return '' !== $value && \in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
