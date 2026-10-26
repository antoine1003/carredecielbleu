<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Page;
use App\Entity\SiteSetting;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Service\Catalog\CatalogImporter;
use App\Service\Catalog\SumupCsvParser;
use App\Service\Catalog\SumupCsvSource;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Données de démonstration (JAMAIS en prod) : catalogue importé depuis docs/samples/sumup-export.csv,
 * pages avec placeholders explicites, réglages par défaut et un compte admin local.
 */
final class AppFixtures extends Fixture
{
    /** Produits de démo qui reçoivent un faux lien d'achat pour montrer le bouton « Acheter sur SumUp ». */
    private const array DEMO_PURCHASE_LINKS = ['abeille-charpentiere-bleue', 'bouquet-eternel-de-tulipes', 'vitrail-piou-piou'];

    public function __construct(
        private readonly CatalogImporter $importer,
        private readonly SumupCsvParser $parser,
        private readonly ProductRepository $products,
        private readonly UserPasswordHasherInterface $hasher,
        #[Autowire('%kernel.project_dir%/docs/samples/sumup-export.csv')]
        private readonly string $sampleCsv,
        #[Autowire('%env(default::FIXTURES_ADMIN_PASSWORD)%')]
        private readonly ?string $adminPassword = null,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $manager->persist(new SiteSetting());

        foreach ($this->pages() as [$slug, $title, $inFooter, $content]) {
            $manager->persist((new Page())
                ->setSlug($slug)
                ->setTitle($title)
                ->setInFooter($inFooter)
                ->setPublished(true)
                ->setContent($content));
        }

        if (null !== $this->adminPassword && '' !== $this->adminPassword) {
            $admin = (new User())->setEmail('admin@carredecielbleu.test')->setRoles(['ROLE_ADMIN']);
            $admin->setPassword($this->hasher->hashPassword($admin, $this->adminPassword));
            $manager->persist($admin);
        }

        $manager->flush();

        $this->importer->import(new SumupCsvSource($this->parser, $this->sampleCsv));

        foreach ($this->products->findBy(['slug' => self::DEMO_PURCHASE_LINKS]) as $product) {
            $product->setPurchaseUrl('https://example.invalid/sumup/'.$product->getSlug())->setFeatured(true);
        }
        $manager->flush();
    }

    /** @return list<array{string, string, bool, string}> */
    private function pages(): array
    {
        return [
            ['l-atelier', 'L\'atelier', false, '<p>[Présentation de l\'artisane et de son atelier : parcours, technique Tiffany, inspirations. Texte à écrire par l\'artisane.]</p>'],
            ['contact', 'Contact', false, '<p>[Comment contacter l\'artisane : email, réseaux sociaux, atelier sur rendez-vous… Texte à écrire.]</p>'],
            ['mentions-legales', 'Mentions légales', true, '<p>[Mentions légales de l\'auto-entreprise : nom, SIRET, adresse, hébergeur (Hostinger), directrice de publication. À compléter.]</p>'],
            ['politique-de-confidentialite', 'Politique de confidentialité', true, '<p>[Données collectées (inscriptions aux ateliers), finalité, durée de conservation, droits d\'accès et de suppression, contact. À compléter.]</p>'],
        ];
    }
}
