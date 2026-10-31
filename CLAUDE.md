# CLAUDE.md — Carré de ciel bleu

Site vitrine d'une artisane vitrailliste (auto-entreprise « Carré de ciel bleu ») : vitrine des produits en stock + gestion des ateliers de vitrail. Site 100 % en français. Le paiement n'est JAMAIS géré par le site : les achats se font sur SumUp via un lien externe.

## Stack

- Symfony 8.1.x (dernière version stable), PHP 8.4 minimum (exigé par Symfony 8.1), Twig en rendu serveur (SEO)
- Doctrine ORM 3 + MariaDB/MySQL, migrations versionnées. Sur PHP 8.4, utiliser les lazy objects natifs de Doctrine
- Admin : EasyAdmin 5.x (`easycorp/easyadmin-bundle` ^5.0). NE PAS utiliser la syntaxe EasyAdmin 4 (voir section dédiée)
- Important : tes connaissances sur Symfony 8.1 et EasyAdmin 5 peuvent être incomplètes. Vérifie dans la documentation officielle (Context7 MCP si disponible) avant d'écrire du code sur une API que tu ne connais pas parfaitement
- Front : AssetMapper + Stimulus/Turbo, CSS maison avec variables CSS. Pas de build Node lourd, JS minimal
- Mailer : Symfony Mailer (templates Twig)
- Prod : VPS Hostinger, Apache + PHP-FPM, DocumentRoot sur `public/`

## Commandes

```bash
symfony serve -d                       # serveur local
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load # données de démo (jamais en prod)
php bin/console app:catalog:import <fichier.csv> [--dry-run]   # import du CSV SumUp
php bin/console app:admin:create <email>   # compte admin (mot de passe demandé masqué)
php bin/console app:registrations:purge [--days=90] [--dry-run]   # RGPD : purge des inscriptions d'ateliers passés (cron quotidien)
php bin/console asset-map:compile      # assets de prod
composer qa                            # phpstan + php-cs-fixer (dry-run)
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix
```

Si `composer qa` n'existe pas encore, le créer dans les scripts de composer.json.

## EasyAdmin 5 : points d'attention

- Le DashboardController se déclare avec l'attribut `#[AdminDashboard]` (et non plus avec une `#[Route]` classique sur `index()`).
- Dans le menu, `linkToCrud()` est supprimé : utiliser `linkTo()` avec la classe du CrudController.
- La configuration de routes EasyAdmin est obligatoire en 5.x : suivre la doc officielle, ne pas la deviner.
- Ajustements visuels de l'admin : `assets/styles/admin.css`, chargé par `DashboardController::configureAssets()` (contenu pleine largeur, poignée de largeur masquée). La doc de l'API installée est dans `vendor/easycorp/easyadmin-bundle/skills/easyadmin/`.
- Pour intégrer une action Symfony perso dans l'admin (ex. écran d'import CSV, tableau de confirmation des sessions), utiliser l'attribut `#[AdminRoute]` ou une action de CRUD, selon ce que recommande la doc.
- Écrans admin sur mesure (import CSV, tableau dates × inscrits) : composants Twig EasyAdmin plutôt qu'un back-office parallèle.
- Contenu à vérifier systématiquement dans la doc avant d'écrire : champs (`ImageField`, `MoneyField`, `AssociationField`), actions personnalisées, filtres, batch actions.

## Architecture

```
src/
  Controller/        # contrôleurs publics, fins (pas de logique métier)
  Controller/Admin/  # EasyAdmin : Dashboard + CrudControllers
  Entity/            # Product, ProductVariant, ProductImage, Category, Workshop, WorkshopSession, Registration, SiteSetting, Page
  Repository/
  Service/
    Catalog/         # CatalogImporter, SumupCsvParser, ProductSourceInterface
    Workshop/        # RegistrationService, SessionConfirmationService
    Seo/             # SeoMetaProvider, JsonLd builders, SitemapBuilder
  Message/ + Handler # envois d'emails (Messenger si utile)
templates/           # base.html.twig, pages/, components/, emails/
assets/              # styles/ (tokens, base, components, pages), controllers/ (Stimulus)
docs/                # samples/sumup-export.csv, décisions techniques
```

Règles : logique métier dans `Service/`, pas dans les contrôleurs ni les entités. Injection par constructeur, `readonly` quand possible, types stricts partout (`declare(strict_types=1)`), attributs PHP 8 pour le mapping/routing.

## Règles métier clés

### Catalogue (SumUp, V1 = import CSV)
- L'API publique SumUp n'expose pas le catalogue : ne jamais supposer un appel API pour lire produits/stock.
- Import via l'admin (upload, aperçu des changements, validation) ET via `app:catalog:import`.
- Le mapping des colonnes du CSV est centralisé et configurable (un seul endroit : `SumupCsvParser`). Le fichier d'exemple réel est dans `docs/samples/`.
- Clé de rapprochement : `sumupItemId` si présent, sinon `sku`.
- L'import NE DOIT JAMAIS écraser les champs enrichis côté site (photos supplémentaires, dimensions, technique, texte d'histoire, visibilité, `purchaseUrl` saisi à la main). Il met à jour : nom, prix, stock, description de base, catégorie.
- Produit absent du CSV → marqué « non synchronisé » (et masqué du site public), jamais supprimé automatiquement. Idem pour une variante.
- Variantes SumUp : entité `ProductVariant` (clé `sumupVariantId`, prix et stock propres). Dans le CSV, une ligne parente (Item id sans Variant id) est suivie de lignes sans nom portant `Variations` + `Variant id`. Prix public : « à partir de ».
- Stock : `Track inventory = No` → stock non suivi = disponible (« Sur commande »). Stock suivi à 0 → comportement « épuisé » réglable (global dans les réglages, surchargeable par produit : masquer / afficher « Épuisé » / afficher « Sur commande »). Seuls les produits visibles sont publics.
- `Display item in Online Store?` : valeur initiale de la visibilité à la création uniquement, jamais réécrite ensuite.
- Images : uploadées à la main dans l'admin (les URLs d'images SumUp du CSV sont ignorées).
- Bouton d'achat : lien externe vers `purchaseUrl` (saisi à la main, absent du CSV), `target="_blank" rel="noopener noreferrer"`, libellé « Acheter sur SumUp ». Sans `purchaseUrl` → bouton « Me contacter ».

### Ateliers
- Workshop → plusieurs WorkshopSession → plusieurs Registration.
- Une personne peut s'inscrire à PLUSIEURS sessions du même atelier (sondage de disponibilités).
- Statuts de session : ouvert, confirmé, complet, annulé.
- L'admin voit un tableau dates × inscrits, confirme la date retenue → emails de confirmation (inscrits de cette date) et d'information (autres inscrits).
- Annulation par lien tokenisé (token aléatoire non devinable).
- Affichage public des inscrits : prénom + initiale du nom UNIQUEMENT si opt-in explicite, sinon seulement le nombre. Jamais d'email ni de téléphone en public.
- Protection formulaires : CSRF, honeypot (champ `website`), rate limiting (`workshop_registration` : 5 inscriptions valides / 15 min / IP ; les soumissions en erreur ne comptent pas). En test local, vider le compteur avec `php bin/console cache:pool:clear cache.rate_limiter`.
- Disponibilité d'une date (`SessionAvailability`) : statut ouvert ou confirmé, date future, places restantes (capacité de la date, sinon celle de l'atelier). Le statut « complet » est un choix manuel de l'admin.
- Confirmation (`SessionConfirmationService`) : une seule fois par date ; un inscrit de la date confirmée ne reçoit jamais aussi l'email d'information ; option « annuler les autres dates ».
- Annulation : la page GET n'a aucun effet (pré-chargement des liens par les clients mail), l'annulation se fait en POST + CSRF et SUPPRIME l'inscription.
- Conservation : inscriptions supprimées 90 jours après la date de l'atelier (`app:registrations:purge`, cron quotidien).
- Emails : un échec d'envoi est journalisé (sans l'adresse) et signalé, sans annuler l'inscription ni la confirmation. Couleurs des emails écrites en dur dans `templates/emails/_layout.html.twig` (seule exception aux tokens : les clients mail ignorent les variables CSS).
- Aucun paiement d'atelier géré par le site.

### Newsletter (Brevo)
- Inscription directe (simple opt-in) : `NewsletterSubscriber` appelle l'API Brevo `POST /v3/contacts` (`updateEnabled: true`, ajout à la liste réglée dans l'admin). Le site ne stocke aucune adresse.
- Clé API UNIQUEMENT dans la variable d'environnement `BREVO_API_KEY` (jamais en base ni dans l'admin) ; `BREVO_API_URL` permet de pointer vers un faux serveur en test. L'admin (Réglages › Newsletter) règle l'activation, l'ID de liste et les textes, et indique si la clé est présente.
- Le formulaire ne s'affiche que si la newsletter est activée, une liste choisie ET la clé présente (`newsletter_available()`) : bandeau sur l'accueil, version compacte dans le pied de page des autres pages (jamais deux formulaires sur une page).
- Protection : CSRF, honeypot `website`, rate limiting `newsletter_subscription` (5 / 15 min / IP), redirection de retour limitée aux chemins internes. Échec Brevo : message neutre au public, erreur journalisée sans l'adresse.

## Design — identité « Carré de ciel bleu »

- Ambiance : lumière à travers le verre, nature, couleurs de vitrail. Doit se démarquer d'un template générique.
- Direction retenue : « Lumière d'estuaire » (fond brume, encre bleue, éclats de verre translucides, visuels en fenêtres en ogive). Les couleurs ne sont PAS définitives : l'artisane va les retoucher.
- `assets/styles/_tokens.css` en deux niveaux : `--palette-*` (valeurs brutes, le seul endroit à modifier pour changer une teinte) puis rôles `--color-*` (texte, fond, action, statuts…). Les composants n'utilisent QUE les rôles (ou `--palette-*` dans `color-mix()` pour des nuances). Ne jamais coder une couleur en dur ailleurs.
- Polices via `--font-display` (Gloock) et `--font-body` (Nunito Sans) ; fichiers woff2 dans `assets/fonts/`, `@font-face` et mode d'emploi pour en changer dans `assets/styles/_fonts.css`. Deux familles maximum, servies en local (pas de CDN de fonts).
- `/_styleguide` (dev uniquement) montre palette, contrastes calculés en direct, typographie et composants : le relire après tout changement de couleur ou de police.
- Composants Twig anonymes dans `templates/components/` (`<twig:ProductCard>`, `<twig:Status>`, `<twig:GlassArt>`, `<twig:Shards>`).
- Éléments récurrents : formes organiques, motifs de plombs/cloisons, transparences. Animations sobres, respecter `prefers-reduced-motion`.
- Mobile-first. Tester systématiquement 375 px, 768 px, 1280 px.
- Accessibilité WCAG AA : contrastes, focus visibles, navigation clavier, `alt` pertinents, un seul `h1` par page.
- Utiliser le skill `frontend-design` pour tout travail d'interface.

## SEO (non négociable)

- Chaque page : `<title>` et meta description uniques, canonical, Open Graph / Twitter cards, via `SeoMetaProvider`.
- JSON-LD : Organization/LocalBusiness (accueil), Product + Offer + availability (fiche produit), Event (sessions d'atelier).
- `sitemap.xml` dynamique, `robots.txt`, URLs propres avec slugs, 404/301 gérées.
- Images : WebP, plusieurs tailles (`srcset`), `loading="lazy"` sauf image LCP, dimensions width/height renseignées.
- Viser de bons Core Web Vitals : pas de JS bloquant, CSS critique léger.
- Pas de tracker tiers. Analytics respectueux de la vie privée uniquement ; sinon bandeau cookies conforme.
- Pages légales : mentions légales, politique de confidentialité.

## Sécurité et RGPD

- Secrets uniquement dans `.env.local` / variables d'environnement serveur. Ne jamais committer `.env.local`, de clés ou d'exports clients.
- Données personnelles (inscriptions) : minimum nécessaire, durée de conservation documentée, export/suppression possible depuis l'admin.
- Uploads : valider type et taille, renommer les fichiers, ne jamais exécuter ni servir depuis un chemin non contrôlé.
- Admin : accès par rôle, mots de passe hashés, protection de la page de login. Connexion sur `/admin/connexion` (gabarit EasyAdmin, CSRF, 5 tentatives/minute). En dev, les fixtures créent `admin@carredecielbleu.test` avec le mot de passe `FIXTURES_ADMIN_PASSWORD` de `.env.dev`.

## Qualité et workflow

- Travailler par phases, avec un commit clair par phase (Conventional Commits : `feat:`, `fix:`, `chore:`…).
- Avant de déclarer une phase terminée : `composer qa` vert (PHPStan + php-cs-fixer) + vérification visuelle mobile/desktop.
- Pas de tests unitaires ni fonctionnels pour le moment : ne pas installer PHPUnit, ne pas écrire de tests, sauf si je le demande. La vérification repose sur PHPStan, php-cs-fixer et le contrôle manuel (visuel, parcours d'inscription, import CSV avec le fichier d'exemple).
- Les points sensibles (import CSV sans écrasement des champs enrichis, inscription multi-dates, annulation par token) sont à vérifier à la main en fin de phase, et le résumé de phase indique ce qui a été vérifié.
- Demander avant tout changement d'architecture, ajout de dépendance lourde ou modification du schéma de données déjà en production.
- Ne pas inventer de contenu commercial (prix, textes de l'artisane, avis, adresses) : utiliser des placeholders explicites ou les données fournies.
- Garder ce fichier à jour quand une convention ou une décision change.

## Déploiement (Apache sur VPS)

- PHP 8.4+ obligatoire sur le VPS (vérifier la version avec `php -v` et la config PHP-FPM utilisée par Apache). Extensions requises : intl, mbstring, pdo_mysql, gd ou imagick (images), zip, xml, curl, opcache.
- DocumentRoot = `public/`, `AllowOverride All`, `mod_rewrite` actif, PHP-FPM.
- HTTPS (Let's Encrypt), redirection http → https et www → apex (ou l'inverse, un seul choix), en-têtes de sécurité de base.
- Procédure de déploiement documentée dans `README.md` : `composer install --no-dev --optimize-autoloader`, migrations, `asset-map:compile`, `cache:clear` en `APP_ENV=prod`.
- Sauvegarde régulière de la base et du dossier d'uploads.

## Décisions déjà prises

- Symfony 8.1 (version standard, hors LTS) : prévoir de suivre les mises à jour mineures régulièrement. Le support d'une version standard est court (fin de support de 8.1 : janvier 2027), donc planifier le passage à 8.2 puis, à terme, à la prochaine LTS.
- EasyAdmin 5 (pas 4).
- V1 : catalogue alimenté par import CSV SumUp (pas d'API catalogue).
- Rendu serveur Twig (pas d'Angular) pour le SEO et la maintenabilité.
- Paiements 100 % sur SumUp, ni panier ni checkout sur le site.
- `config.platform.php = 8.4.1` dans composer.json : les dépendances restent compatibles avec la prod PHP 8.4 même si le poste local est en PHP 8.5.
- Doctrine : les lazy objects natifs sont toujours actifs avec DoctrineBundle 3.x (l'option `enable_native_lazy_objects` est dépréciée) ; rien à configurer.
- Emails envoyés en synchrone en V1 (pas de worker Messenger sur le VPS). MailDev en local (SMTP `localhost:1025`, lecture sur http://localhost:1080, `MAILER_DSN` dans `.env.dev`) ; sans MailDev, mettre `MAILER_DSN=null://null` dans `.env.dev.local` et lire les emails dans le profiler.
- Fuseau : `Europe/Paris` fixé dans `Kernel::boot()` (dates d'ateliers stockées et affichées en heure de Paris).
- Turbo Drive est actif : un formulaire soumis doit rediriger (303) en cas de succès, ou répondre 422 s'il est invalide.
- Base locale : MariaDB 11.3 de WAMP (port 3307), configurée dans `.env.local`. Tables forcées en InnoDB + utf8mb4 (`default_table_options` dans doctrine.yaml).
- PHPStan : `doctrine.allowNullablePropertyForRequiredField: true`, car les propriétés éditées par formulaire sont nullables (exigence Symfony Forms / EasyAdmin) alors que la colonne est NOT NULL.
- Texte riche (descriptions, pages) : assaini avec le sanitizer `app.rich_text` à l'import ET à l'affichage (`|sanitize_html('app.rich_text')`).
- Windows : passer les contraintes Composer avec `~` ou les écrire dans composer.json (`composer.bat` mange le caractère `^`).