<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ProductImage;
use App\Service\Image\ProductImageProcessor;
use App\Twig\ProductImageExtension;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Une photo de produit dans l'admin : fichier à envoyer (ou à remplacer) et texte alternatif.
 *
 * @extends AbstractType<ProductImage>
 */
final class ProductImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'Photo',
                'required' => false,
                'attr' => ['accept' => implode(',', ProductImageProcessor::ALLOWED_MIME_TYPES)],
                'help' => 'JPEG, PNG ou WebP, 10 Mo maximum. Convertie automatiquement pour le web.',
            ])
            ->add('alt', TextType::class, [
                'label' => 'Description de la photo',
                'required' => false,
                'help' => 'Ce que montre la photo, pour les personnes malvoyantes et Google. Vide = nom du produit.',
                'attr' => ['maxlength' => 255],
            ]);

        // Photo déjà enregistrée : aperçu au-dessus du champ, qui sert alors à la remplacer.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            $image = $event->getData();
            if (!$image instanceof ProductImage || null === $image->getFilename() || [] === $image->getWidths()) {
                return;
            }
            $src = \sprintf('%s/%s-%d.webp', ProductImageExtension::PUBLIC_DIR, $image->getFilename(), min($image->getWidths()));
            $event->getForm()->add('file', FileType::class, [
                'label' => 'Remplacer la photo',
                'required' => false,
                'attr' => ['accept' => implode(',', ProductImageProcessor::ALLOWED_MIME_TYPES)],
                'help' => \sprintf('<img src="%s" alt="" style="display:block;max-width:160px;max-height:160px;border-radius:6px;margin-bottom:.4rem">Laisser vide pour garder cette photo.', htmlspecialchars($src)),
                'help_html' => true,
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ProductImage::class]);
    }
}
