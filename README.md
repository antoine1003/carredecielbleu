# Carré de ciel bleu

Site vitrine d'une artisane vitrailliste : boutique (catalogue importé depuis SumUp, achat sur SumUp) et ateliers de vitrail avec inscriptions. Conventions et règles métier : voir [`CLAUDE.md`](CLAUDE.md). Décisions techniques : [`docs/decisions.md`](docs/decisions.md).

## Stack

- PHP 8.4+ (prod) — Symfony 8.1 — Twig — Doctrine ORM 3 + MariaDB/MySQL
- EasyAdmin 5 pour l'administration
- AssetMapper + Stimulus/Turbo (aucun build Node)
- Qualité : PHPStan (niveau 8) + php-cs-fixer

## Installation locale

Prérequis : PHP 8.4+ avec les extensions intl, mbstring, pdo_mysql, gd, zip, xml, curl ; Composer ; [Symfony CLI](https://symfony.com/download) ; MariaDB ou MySQL ; [MailDev](https://github.com/maildev/maildev) (facultatif, pour voir les emails).

```bash
composer install
```

Créer `.env.local` (jamais versionné) avec l'accès à la base locale :

```dotenv
DATABASE_URL="mysql://root:@127.0.0.1:3307/carredecielbleu?serverVersion=11.3.2-MariaDB&charset=utf8mb4"
```

Puis :

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load   # données de démo, jamais en prod (admin local : admin@carredecielbleu.test, mot de passe FIXTURES_ADMIN_PASSWORD dans .env.dev)
symfony serve -d
```

Le site est servi sur https://127.0.0.1:8000 ; l'administration sur /admin (connexion : /admin/connexion). En dev, les emails partent vers MailDev (`smtp://localhost:1025`, emails lisibles sur http://localhost:1080). Sans MailDev, mettez `MAILER_DSN=null://null` dans `.env.dev.local` : les emails sont alors visibles dans le profiler Symfony (onglet « Emails »).

## Commandes utiles

| Commande | Rôle |
|---|---|
| `composer qa` | PHPStan + php-cs-fixer (dry-run) — doit être vert avant chaque commit de phase |
| `composer cs-fix` | Corrige le style de code |
| `php bin/console app:catalog:import <fichier.csv> [--dry-run]` | Import du catalogue depuis l'export SumUp (aussi disponible dans l'admin) |
| `php bin/console app:admin:create <email>` | Crée un compte administrateur (mot de passe demandé de façon masquée) |
| `php bin/console app:registrations:purge [--days=90]` | Supprime les inscriptions des ateliers passés (RGPD) |
| `php bin/console asset-map:compile` | Compile les assets pour la prod |

Fichier d'exemple de l'export SumUp : [`docs/samples/sumup-export.csv`](docs/samples/sumup-export.csv).

## Variables d'environnement

Valeurs par défaut (placeholders) dans `.env` ; valeurs réelles dans `.env.local` en local ou dans les variables d'environnement du serveur en prod.

| Variable | Rôle |
|---|---|
| `APP_ENV`, `APP_SECRET` | Environnement Symfony et secret (générer un secret unique en prod) |
| `DATABASE_URL` | Connexion MariaDB/MySQL |
| `MAILER_DSN` | Serveur SMTP d'envoi |
| `DEFAULT_URI`, `SITE_URL` | URL publique du site (liens absolus, canonical, sitemap, emails) |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Expéditeur des emails |
| `BREVO_API_KEY` | Clé API Brevo pour la newsletter (secret : `.env.local` ou serveur). Vide = formulaire masqué |
| `BREVO_API_URL` | Adresse de l'API Brevo (défaut `https://api.brevo.com/v3`) |
| `ALTCHA_HMAC_KEY` | Clé secrète du captcha ALTCHA, **obligatoire en prod** (sans elle, inscriptions et newsletter sont refusées). Générer avec `php -r "echo bin2hex(random_bytes(32));"` |

## Déploiement (VPS Hostinger, Apache + PHP-FPM)

Procédure détaillée en phase 6. Grandes lignes :

1. PHP 8.4+ sur le VPS (`php -v`, et vérifier la version de PHP-FPM utilisée par Apache) avec les extensions intl, mbstring, pdo_mysql, gd, zip, xml, curl, opcache.
2. DocumentRoot sur `public/`, `AllowOverride All`, `mod_rewrite` actif, HTTPS (Let's Encrypt).
3. À chaque mise en production :

```bash
composer install --no-dev --optimize-autoloader
APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction
APP_ENV=prod php bin/console asset-map:compile
APP_ENV=prod php bin/console cache:clear
```

4. Sauvegarde régulière de la base et du dossier `public/uploads/`.
5. Tâche cron quotidienne pour la purge RGPD des inscriptions :

```bash
15 3 * * * cd /chemin/du/site && APP_ENV=prod php bin/console app:registrations:purge --no-interaction
```
