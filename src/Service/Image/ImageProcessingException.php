<?php

declare(strict_types=1);

namespace App\Service\Image;

/**
 * Photo refusée ou impossible à traiter. Le message est destiné à l'utilisatrice de l'admin.
 */
final class ImageProcessingException extends \RuntimeException
{
}
