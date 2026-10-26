<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\WorkshopSession;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Données du formulaire d'inscription (une personne, une ou plusieurs dates).
 */
final class RegistrationRequest
{
    /** @var list<WorkshopSession> */
    #[Assert\Count(min: 1, minMessage: 'Cochez au moins une date.')]
    public array $sessions = [];

    #[Assert\NotBlank(message: 'Indiquez votre prénom.')]
    #[Assert\Length(max: 80)]
    public ?string $firstName = null;

    #[Assert\NotBlank(message: 'Indiquez votre nom.')]
    #[Assert\Length(max: 80)]
    public ?string $lastName = null;

    #[Assert\NotBlank(message: 'Indiquez votre adresse email.')]
    #[Assert\Email(message: 'Indiquez une adresse email complète, par exemple prenom@exemple.fr.')]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Length(max: 30)]
    #[Assert\Regex('/^[0-9 +().-]*$/', message: 'Le numéro ne peut contenir que des chiffres, espaces et + ( ) . -')]
    public ?string $phone = null;

    public bool $showNamePublicly = false;

    #[Assert\IsTrue(message: 'Votre accord est nécessaire pour enregistrer l\'inscription.')]
    public bool $consent = false;
}
