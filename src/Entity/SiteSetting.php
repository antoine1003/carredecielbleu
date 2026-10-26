<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SoldOutBehavior;
use App\Repository\SiteSettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Réglages du site : une seule ligne, éditée depuis l'admin.
 * Les textes par défaut sont des placeholders explicites à remplacer par l'artisane.
 */
#[ORM\Entity(repositoryClass: SiteSettingRepository::class)]
class SiteSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private string $siteName = 'Carré de ciel bleu';

    /** Grand titre de l'accueil. */
    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private string $homeTitle = 'Vous sentez le parfum de la couleur ?';

    /** Phrase sous le titre de l'accueil. */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $homeIntro = 'Vitraux faits main. [Phrase de présentation de l\'artisane]';

    /** Meta description de l'accueil. */
    #[ORM\Column(length: 300)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 300)]
    private string $homeMetaDescription = '[Description de l\'accueil pour les moteurs de recherche, 150 caractères environ]';

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private string $city = '[Ville]';

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    private ?string $contactEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(protocols: ['https'], requireTld: true)]
    private ?string $instagramUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(protocols: ['https'], requireTld: true)]
    private ?string $facebookUrl = null;

    /** Comportement par défaut des produits épuisés (stock suivi à zéro). */
    #[ORM\Column(length: 32, enumType: SoldOutBehavior::class)]
    private SoldOutBehavior $soldOutBehavior = SoldOutBehavior::Hide;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSiteName(): string
    {
        return $this->siteName;
    }

    public function setSiteName(?string $siteName): static
    {
        $this->siteName = (string) $siteName;

        return $this;
    }

    public function getHomeTitle(): string
    {
        return $this->homeTitle;
    }

    public function setHomeTitle(?string $homeTitle): static
    {
        $this->homeTitle = (string) $homeTitle;

        return $this;
    }

    public function getHomeIntro(): string
    {
        return $this->homeIntro;
    }

    public function setHomeIntro(?string $homeIntro): static
    {
        $this->homeIntro = (string) $homeIntro;

        return $this;
    }

    public function getHomeMetaDescription(): string
    {
        return $this->homeMetaDescription;
    }

    public function setHomeMetaDescription(?string $homeMetaDescription): static
    {
        $this->homeMetaDescription = (string) $homeMetaDescription;

        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = (string) $city;

        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): static
    {
        $this->contactEmail = $contactEmail;

        return $this;
    }

    public function getInstagramUrl(): ?string
    {
        return $this->instagramUrl;
    }

    public function setInstagramUrl(?string $instagramUrl): static
    {
        $this->instagramUrl = $instagramUrl;

        return $this;
    }

    public function getFacebookUrl(): ?string
    {
        return $this->facebookUrl;
    }

    public function setFacebookUrl(?string $facebookUrl): static
    {
        $this->facebookUrl = $facebookUrl;

        return $this;
    }

    public function getSoldOutBehavior(): SoldOutBehavior
    {
        return $this->soldOutBehavior;
    }

    public function setSoldOutBehavior(SoldOutBehavior $soldOutBehavior): static
    {
        $this->soldOutBehavior = $soldOutBehavior;

        return $this;
    }
}
