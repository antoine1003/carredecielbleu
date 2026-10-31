<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Page;
use App\Entity\SiteSetting;
use App\Repository\PageRepository;
use App\Service\Newsletter\NewsletterSubscriber;
use App\Service\SiteSettingsProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Données partagées par le gabarit : réglages du site et pages du pied de page.
 */
final class SiteExtension extends AbstractExtension
{
    /** @var list<Page>|null */
    private ?array $footerPages = null;

    public function __construct(
        private readonly SiteSettingsProvider $settings,
        private readonly PageRepository $pages,
        private readonly NewsletterSubscriber $newsletter,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('site_settings', $this->siteSettings(...)),
            new TwigFunction('footer_pages', $this->footerPages(...)),
            new TwigFunction('newsletter_available', $this->newsletter->isAvailable(...)),
        ];
    }

    public function siteSettings(): SiteSetting
    {
        return $this->settings->get();
    }

    /** @return list<Page> */
    public function footerPages(): array
    {
        return $this->footerPages ??= $this->pages->findFooterPages();
    }
}
