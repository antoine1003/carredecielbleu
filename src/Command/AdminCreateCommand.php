<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:admin:create', description: 'Crée un compte administrateur, ou change le mot de passe d\'un compte existant')]
final readonly class AdminCreateCommand
{
    private const int MIN_PASSWORD_LENGTH = 12;

    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $hasher,
        private ValidatorInterface $validator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Adresse email du compte')]
        string $email,
    ): int {
        $violations = $this->validator->validate($email, [new Assert\NotBlank(), new Assert\Email()]);
        if (\count($violations) > 0) {
            $io->error('Adresse email invalide.');

            return Command::FAILURE;
        }

        // Le mot de passe est demandé de façon masquée : jamais en argument (historique du shell).
        $password = (string) $io->askHidden(\sprintf('Mot de passe (%d caractères minimum)', self::MIN_PASSWORD_LENGTH));
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $io->error(\sprintf('Le mot de passe doit contenir au moins %d caractères.', self::MIN_PASSWORD_LENGTH));

            return Command::FAILURE;
        }
        if ($password !== (string) $io->askHidden('Confirmez le mot de passe')) {
            $io->error('Les deux mots de passe ne correspondent pas.');

            return Command::FAILURE;
        }

        $user = $this->users->findOneBy(['email' => mb_strtolower(trim($email))]);
        $isNew = null === $user;
        $user ??= (new User())->setEmail($email);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success($isNew ? 'Compte administrateur créé.' : 'Mot de passe mis à jour.');

        return Command::SUCCESS;
    }
}
