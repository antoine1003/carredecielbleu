<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Vidéos des ateliers : reconnaît un lien YouTube pour l'intégrer (lecteur chargé seulement au clic,
 * en mode « youtube-nocookie »). Tout autre lien est affiché comme un simple lien externe.
 */
final class VideoExtension extends AbstractExtension
{
    private const string YOUTUBE_PATTERN = '~^https://(?:(?:www\.|m\.)?youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})(?:[?&#/].*)?$~';

    public function getFunctions(): array
    {
        return [
            new TwigFunction('youtube_id', $this->youtubeId(...)),
        ];
    }

    /** Identifiant de la vidéo YouTube (11 caractères), ou null si le lien n'est pas YouTube. */
    public function youtubeId(?string $url): ?string
    {
        if (null === $url || 1 !== preg_match(self::YOUTUBE_PATTERN, trim($url), $matches)) {
            return null;
        }

        return $matches[1];
    }
}
