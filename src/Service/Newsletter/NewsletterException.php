<?php

declare(strict_types=1);

namespace App\Service\Newsletter;

/**
 * Inscription à la newsletter impossible (service indisponible ou refus de Brevo).
 * Le message est technique : il est journalisé, jamais affiché tel quel au public.
 */
final class NewsletterException extends \RuntimeException
{
}
