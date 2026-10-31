<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\WorkshopSession;
use App\Service\Workshop\RegistrationRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;

/**
 * Inscription à un atelier : une personne coche toutes les dates qui lui conviennent.
 *
 * @extends AbstractType<RegistrationRequest>
 */
final class RegistrationType extends AbstractType
{
    /** Nom du champ piège à robots (doit rester vide). */
    public const string HONEYPOT = 'website';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<WorkshopSession> $sessions */
        $sessions = $options['sessions'];

        $builder
            ->add('sessions', ChoiceType::class, [
                'label' => 'Dates qui vous conviennent',
                'choices' => $sessions,
                'choice_value' => static fn (?WorkshopSession $s): string => (string) $s?->getId(),
                'choice_label' => static fn (WorkshopSession $s): string => (string) $s->getStartsAt()?->format('d/m/Y H:i'),
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['autocomplete' => 'given-name', 'maxlength' => 80],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => ['autocomplete' => 'family-name', 'maxlength' => 80],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'help' => 'Pour vous envoyer la confirmation et le lien d\'annulation.',
                'attr' => ['autocomplete' => 'email', 'maxlength' => 180],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone (facultatif)',
                'help' => 'Utile seulement en cas d\'imprévu le jour de l\'atelier.',
                'required' => false,
                'attr' => ['autocomplete' => 'tel', 'maxlength' => 30],
            ])
            ->add('showNamePublicly', CheckboxType::class, [
                'label' => 'J\'accepte que mon prénom et l\'initiale de mon nom apparaissent dans la liste publique des inscrits.',
                'required' => false,
            ])
            ->add('consent', CheckboxType::class, [
                'label' => 'J\'accepte que mes coordonnées soient conservées pour organiser l\'atelier, puis supprimées.',
            ])
            ->add(self::HONEYPOT, TextType::class, [
                'label' => 'Ne pas remplir ce champ',
                'mapped' => false,
                'required' => false,
                'attr' => ['autocomplete' => 'off', 'tabindex' => '-1'],
            ]);

        // Atelier avec acompte : les conditions d'annulation doivent être acceptées avant de s'inscrire.
        if ($options['deposit_required']) {
            $builder->add('acceptTerms', CheckboxType::class, [
                'label' => 'J\'ai lu et j\'accepte les conditions d\'acompte et d\'annulation ci-dessus.',
                'constraints' => [new IsTrue(message: 'Acceptez les conditions d\'annulation pour vous inscrire.')],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => RegistrationRequest::class,
                'csrf_token_id' => 'workshop_registration',
                'deposit_required' => false,
            ])
            ->setRequired('sessions')
            ->setAllowedTypes('sessions', 'array')
            ->setAllowedTypes('deposit_required', 'bool');
    }
}
