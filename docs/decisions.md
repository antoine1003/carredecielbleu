# Décisions techniques

Journal des décisions prises pendant le développement. Les règles durables sont reportées dans `CLAUDE.md`.

## 2026-10-26 — Cadrage (phase 0)

### Analyse de l'export SumUp (`docs/samples/sumup-export.csv`)
- UTF-8 sans BOM, séparateur `,`, guillemets `"`, champs multi-lignes possibles (ex. SEO description du bon cadeau). Lecture avec `fgetcsv(..., escape: '')`.
- 47 colonnes, 48 lignes de données dont 6 lignes de variantes.
- `Item id (Do not change)` est toujours rempli ; `SKU` est presque toujours vide → clé de rapprochement `sumupItemId`, repli `sku`.
- Variantes : ligne parente (Item id, pas de Variant id, pas de prix) puis lignes sans `Item name` avec `Variations`, `Variant id`, prix et quantité propres.
- `Track inventory? = No` → la quantité (0) n'a pas de sens.
- Noms et catégories avec espaces de fin (« Fleurs ») → `trim()`. Description en HTML → assainie (symfony/html-sanitizer). Prix `30.00` → centimes (int).
- Colonnes retenues : Item name, Variations, Price, Track inventory?, Quantity, SKU, Description, Category, Display item in Online Store?, SEO title, SEO description, Item id, Variant id. Le mapping est centralisé dans `SumupCsvParser`.

### Choix validés avec le client
| Sujet | Décision |
|---|---|
| Stock | Non suivi = disponible (« Sur commande ») ; suivi à 0 = comportement épuisé réglable (global + par produit) |
| Lien d'achat | `purchaseUrl` saisi à la main ; sinon bouton « Me contacter » |
| Images | Upload manuel dans l'admin, WebP multi-tailles (GD natif) ; images SumUp ignorées |
| Variantes | Entité `ProductVariant` |
| Visibilité | `Display item in Online Store?` = valeur initiale à la création uniquement |
| Domaine / mails | Placeholders en variables d'environnement, envoi synchrone, MailDev en local |

### Socle
- Symfony 8.1.9, Doctrine ORM 3.7 / DoctrineBundle 3.3, EasyAdmin 5.8, PHPStan 2 (niveau 8), php-cs-fixer 3 (`@Symfony` + `@Symfony:risky` + `declare_strict_types`).
- EasyAdmin 5 : routes chargées par `config/routes/easyadmin.yaml` (`type: easyadmin.routes`, fourni par la recette) ; le dashboard se déclarera avec `#[AdminDashboard(routePath: '/admin', routeName: 'admin')]`.
- Lazy objects natifs : toujours actifs avec DoctrineBundle 3.x.

## 2026-10-26 — Direction artistique (phase 1)

- Trois directions proposées (A « Plomb & lumière », B « Lumière d'estuaire », C « Nef nocturne »). **B retenue** par l'artisane, avec des couleurs encore à ajuster ensemble.
- Couleurs et polices entièrement pilotées par variables CSS (`_tokens.css` : palette → rôles ; `--font-display` / `--font-body`) pour permettre ces retouches sans toucher aux composants.
- Polices : Gloock (titres) et Nunito Sans variable (texte), sous-ensemble latin en woff2, licence OFL (`assets/fonts/OFL-*.txt`), préchargées dans `base.html.twig`.
- Contrastes AA vérifiés : le vert « salicorne » (#6E9A4F) ne sert qu'au décor ; une variante foncée (#476F30) sert au texte « En stock ».
- Visuels provisoires en verre (`<twig:GlassArt>`) tant que les photos ne sont pas uploadées.
- Pas de menu burger : 4 liens de navigation qui passent à la ligne, aucun JS nécessaire.

## 2026-10-26 — Boutique et import CSV (phase 2)

- Base de données : MariaDB/MySQL (SQL relationnel), via Doctrine ORM. Tables InnoDB + utf8mb4 imposées dans la config (le MariaDB de WAMP crée du MyISAM par défaut, sans clés étrangères).
- Modèle : `Product` → `ProductVariant` (au moins une, prix/stock propres) et `ProductImage` ; `Category` reconnue par son nom SumUp (`sumupName`), son nom affiché reste modifiable.
- Produit ou variante « non synchronisé » = retiré de SumUp : conservé en base, masqué du site public.
- Prix à 0 € (bon cadeau) affiché « Prix sur demande ».
- Produit épuisé masqué → sa page renvoie 404 (à revoir en phase SEO : 410 ou redirection).
- Admin livré dès cette phase (connexion, produits, catégories, pages, réglages, écran d'import) car l'import doit être protégé ; les finitions restent en phase 4 (photos, tableau de bord, ateliers).
- Écran d'import : upload → aperçu des changements champ par champ → validation. Fichier stocké hors `public/` (`var/imports/`), nom aléatoire, supprimé après usage ou au bout d'une heure.
- Nouvelles dépendances légères : `twig/string-extra` (troncature des meta descriptions), `symfony/clock`, `symfony/ux-twig-component` déclaré explicitement.

## 2026-10-26 — Ateliers et emails (phase 3)

- Modèle : `Workshop` → `WorkshopSession` (dates, statut ouvert / confirmé / complet / annulé) → `Registration` (une ligne par personne et par date, unicité date + email, `submissionId` commun aux dates cochées ensemble).
- Places : capacité par date, sinon celle de l'atelier ; une date sans place restante n'est plus proposée. Verrou SQL (`PESSIMISTIC_WRITE`) pendant l'inscription pour ne pas dépasser la capacité.
- Affichage public : nombre d'inscrits et places restantes ; « Prénom I. » uniquement avec l'accord explicite (case dédiée, décochée par défaut).
- Annulation : jeton de 64 caractères hexadécimaux (32 octets aléatoires) par inscription ; page de confirmation en GET sans effet, suppression en POST + CSRF.
- Confirmation de la date retenue depuis le tableau dates × inscrits de l'admin ; emails de confirmation et d'information envoyés immédiatement (synchrone). Annulation des autres dates en option.
- RGPD : minimum de données (prénom, nom, email, téléphone facultatif), consentement obligatoire, suppression par la personne (lien) ou par l'admin, purge automatique 90 jours après l'atelier (`app:registrations:purge`, à planifier en cron). Durée à reporter dans la politique de confidentialité.
- Export CSV (atelier ou global) : UTF-8 avec BOM, séparateur point-virgule, cellules commençant par = + - @ neutralisées (injection de formules).
- En local : MailDev (SMTP 1025, lecture sur http://localhost:1080).

## 2026-10-31 — Newsletter Brevo

- Inscription directe à une liste Brevo depuis le site (simple opt-in, demandé par le client) : `POST /v3/contacts` avec `listIds` et `updateEnabled: true` (un contact déjà connu est simplement ajouté à la liste). Le site ne conserve aucune adresse.
- Clé API dans la variable d'environnement `BREVO_API_KEY` (choix du client, conforme à la règle « secrets hors base »). L'admin règle activation, ID de liste et textes ; il affiche si la clé est présente.
- Formulaire : bandeau sur l'accueil, version compacte dans le pied de page des autres pages. CSRF, champ piège, limitation 5 / 15 min / IP.
- Vérifié avec un faux serveur Brevo local (`BREVO_API_URL`) : requête, en-tête `api-key`, corps JSON, cas d'erreur 401. Pas encore testé avec un vrai compte Brevo.
- Piste RGPD non retenue pour l'instant : le double opt-in Brevo (email de confirmation avant ajout à la liste), activable plus tard via `POST /v3/contacts/doubleOptinConfirmation`.
- `symfony/http-client` déclaré explicitement. Mise à jour corrective Symfony 8.1.10 (faille CVE-2026-45070 dans `symfony/mime`).

## 2026-10-31 — Anti-robots : captcha ALTCHA

- Constat : CSRF + champ piège + limitation de débit arrêtent le spam de masse, mais pas un robot ciblé ; risques réels = fausses inscriptions qui occupent les places des ateliers, et inscription d'adresses de tiers à la newsletter (simple opt-in).
- Choix : ALTCHA auto-hébergé (preuve de travail PBKDF2 calculée par le navigateur), retenu plutôt que Cloudflare Turnstile ou Google reCAPTCHA pour respecter « pas de tracker tiers » et éviter un bandeau cookies. Librairie PHP et widget sous licence MIT, gratuits ; rien à installer sur le VPS hors `composer install` / `importmap`.
- Atelier : case visible qui se coche seule dès qu'on remplit le formulaire. Newsletter : mode invisible.
- Serveur : preuve signée (clé `ALTCHA_HMAC_KEY`), valable 30 min, à usage unique (empreinte en cache jusqu'à expiration).
- Vérifié : inscription réelle par le navigateur acceptée ; service testé directement (preuve valide acceptée ; rejouée, trafiquée, signée par une autre clé, absente ou sans clé serveur refusées). Les tests curl de bout en bout ont buté sur le CSRF (cookies de curl sous Git Bash), pas sur le captcha.
- Correction au passage : texte des champs blanc sur blanc dans le bandeau newsletter (couleur héritée du bloc sombre).

## 2026-10-31 — Photos produits et galerie

- Admin : onglet « Photos » du produit (collection de photos, ordre = ordre affiché, texte alternatif vide = nom du produit). Validation JPEG/PNG/WebP, 10 Mo, 50 mégapixels maximum ; conversion GD en WebP (480, 960, 1600 px, sans agrandissement, orientation EXIF corrigée) sous un nom aléatoire ; fichiers supprimés quand la photo est retirée ou remplacée. Pas de dépendance ajoutée (pas de VichUploader ni LiipImagine).
- Fiche produit : ruban à défilement natif (scroll-snap : glisser au doigt, flèches, miniatures, clavier), visionneuse plein écran en `<dialog>` natif (Échap, clic sur le fond, flèches du clavier). Sans JavaScript, chaque photo reste un lien vers sa grande version. Pas d'effet au survol sur la fiche (choix de la cliente). Boutique : image de la carte cliquable vers la fiche (lien masqué aux lecteurs d'écran, le titre porte le lien) et léger zoom au survol (500 ms, réduit à 250 ms mais conservé si `prefers-reduced-motion`, à la demande de la cliente).
- Mobile : les photos prennent toute la largeur ; l'illustration provisoire (produit sans photo) garde sa largeur réduite.
- Vérifié : upload de 3 photos par le vrai formulaire (lignes en base, 8 fichiers WebP), navigation flèches/miniatures/compteur, visionneuse (ouverture sur la bonne photo, clavier, retour du focus sur la photo regardée), 1280 px et 375 px sans débordement, produit sans photo inchangé.

## 2026-10-31 — Admin produit entièrement modifiable

- Demande : plus de champ affiché mais non modifiable, sauf les champs techniques. Nom, catégorie, description, référencement, prix et stock deviennent modifiables ; identifiant SumUp, état de synchronisation et dates restent en lecture seule.
- Conséquence assumée : pour un produit synchronisé, ces champs restent la propriété de SumUp et sont remplacés au prochain import (aide sous chaque champ). Pas de verrouillage champ par champ (aurait demandé un changement de schéma).
- Déclinaisons : onglet « Prix et déclinaisons », formulaire imbriqué (`ProductVariantCrudController`, `useEntryCrudForm`), ajout, suppression, prix en euros (stocké en centimes), suivi du stock. Une déclinaison ajoutée à la main a le statut « Saisi à la main » et n'est jamais touchée par l'import.
- Création de produit à la main réactivée : une déclinaison unique, stock non suivi par défaut.
- Vérifié dans l'admin : modification d'un stock, ajout puis suppression d'une déclinaison, création d'un produit à la main (données de test remises en état).

## 2026-10-31 — Police des titres : Alegreya

- Gloock jugée peu lisible et trop serrée. Cinq propositions comparées sur de vrais titres du site (Fraunces, Young Serif, Newsreader, Alegreya, Bricolage Grotesque) : choix d'Alegreya, tracé calligraphique.
- Version variable (graisses 400 à 900), sous-ensemble latin en woff2 (43 Ko), issue de `@fontsource-variable/alegreya` 5.3.0, licence OFL (`assets/fonts/OFL-alegreya.txt`). Titres en graisse 500, approche du h1 ramenée à -0,005em. Fichiers Gloock retirés.

## 2026-10-31 — Photos et vidéo des ateliers

- Atelier : plusieurs photos (entité `WorkshopImage`, table `workshop_image`) et un lien vidéo (`workshop.video_url`), migration `Version20261031125355`.
- Code photo mis en commun avec les produits : `ResponsiveImage` (interface) + `ResponsiveImageTrait` (colonnes identiques, `product_image` inchangée), `ImageProcessor` (dossier par type : `uploads/products`, `uploads/workshops`), `ImageCollectionProcessor` (conversion, texte alternatif par défaut, ordre), `ImageFileRemover`, `ResponsiveImageType`, Twig `image_attrs()`, gabarit `partials/_gallery.html.twig`.
- Vidéo : YouTube reconnu (watch, youtu.be, shorts, embed, live ; https uniquement) et intégré en « façade » : vignette locale aux couleurs du site, lecteur `youtube-nocookie.com` créé seulement au clic. Respecte « pas de tracker tiers » tant que le visiteur ne clique pas ; mention sous la vidéo. Autre lien (Vimeo, page…) : bouton externe.
- Vérifié : envoi de 2 photos par le formulaire admin (WebP 480/960/1600 dans `uploads/workshops/`), page publique à 1280 et 375 px sans débordement, aucune requête YouTube avant le clic, lecteur inséré au clic avec titre et focus. Lecture réelle non vérifiable dans le navigateur de test (sites externes bloqués). Produits non régressés (galerie, cartes, aperçu admin).

## 2026-10-31 — Suivi des ateliers plus visuel

- Constat : le tableau personnes × dates ne montrait pas d'un coup d'œil la date retenue ni le nombre d'inscrits ; peu adapté à une utilisatrice peu à l'aise avec l'informatique.
- Accueil de l'admin (`/admin`, avant : redirection vers les produits) : ateliers à venir triés par urgence (date à choisir, puis date retenue, puis en attente), avec bouton « Choisir la date » / « Voir le suivi ».
- Liste des ateliers : colonne « Situation » (badge + précision), le titre ouvre le suivi ; la page « détail » est remplacée par le suivi.
- Suivi d'un atelier : bandeau « où en est-on » (date retenue avec participants et coordonnées, bouton « Écrire aux participants » en copie cachée, ou date la plus demandée à retenir), dates en lignes avec mini-calendrier, jauge, noms cliquables, bouton « Retenir cette date » (fenêtre EasyAdmin `twig:ea:Modal` qui annonce le nombre d'emails de chaque sorte ; « ne plus proposer les autres dates » coché par défaut). Dates passées et tableau détaillé repliés.
- Couleurs de l'admin via les variables Bootstrap (`--bs-*`) : suivent le thème clair/sombre d'EasyAdmin.
- Vérifié sur un atelier de test (supprimé ensuite) : état « date à choisir », date la plus demandée, décompte annoncé (3 confirmations, 1 information), confirmation réelle (statuts, autres dates annulées, message de résultat), liste, accueil, 1280 px sombre et 375 px clair.

## 2026-10-31 — Choix des dates en tuiles (formulaire d'inscription)

- Trois propositions comparées (tuiles calendrier, vitraux, liste à interrupteurs) : choix des tuiles.
- Chaque date est une tuile cliquable (`date-tile`, macro `date_tile` de `templates/workshop/_macros.html.twig`) : mini-calendrier (mois, jour, jour de la semaine) qui passe à l'encre une fois choisie, horaire et lieu, places en pastilles (une par place, pleine si prise ; texte seul au-delà de 12 places), inscrits (prénom + initiale si accord), mention « Date confirmée », coche ronde. Date complète : tuile en pointillés, non cliquable.
- La vraie case à cocher reste dans la tuile (invisible, couvre toute la tuile) : clavier, lecteurs d'écran et envoi sans JavaScript inchangés. Erreur « Cochez au moins une date » reliée au groupe (`aria-describedby`).
- Vérifié : sélection au clic, page atelier à 1280 et 375 px sans débordement, message d'erreur, styleguide (les trois états).

## 2026-10-31 — Photos entières et visionneuse noire

- Photos d'atelier : plus d'arche ni de recadrage (`object-fit: contain`), cadre aux coins arrondis qui prend les proportions de la première photo (`--gallery-ratio`, 75 % de la hauteur d'écran au plus) ; une photo d'un autre format s'affiche entière avec un fond clair. Les produits gardent l'arche.
- Visionneuse plein écran (produits et ateliers) : fond noir légèrement transparent (`--color-overlay`, nouvelle teinte `--palette-noir`) au lieu du bleu encre.

## 2026-10-31 — Logo de l'artisane

- Logo fourni (vitrail rond, PNG 640 px transparent), original conservé dans `docs/brand/logo-carre-de-ciel-bleu.png`.
- Versions web générées en WebP dans `assets/images/` : 96 px (en-tête, affiché à 44 px), 320 et 640 px (accueil, `srcset`, image LCP en `fetchpriority="high"`).
- Remplace l'illustration « lune » de l'accueil et la petite fenêtre en ogive de l'en-tête. Texte alternatif vide : le nom du site est écrit juste à côté (en-tête) ou le logo est décoratif (accueil).
- Piste non faite : favicon et image de partage (Open Graph) à partir du logo.

## 2026-10-31 — Acompte des ateliers

- Choix : acompte pour valider la place une fois la date confirmée, payé sur SumUp (lien saisi sur l'atelier : article « Acompte » de la boutique en ligne SumUp). Pas de RIB (rapprochement manuel, délais), pas d'API SumUp (clé secrète, notifications, erreurs à gérer) : l'artisane coche « Acompte reçu ». Le site ne touche jamais à l'argent.
- Réglages › Ateliers : délai de paiement (5 jours après la confirmation par défaut), remboursement intégral jusqu'à N jours avant l'atelier (7 par défaut), conditions complémentaires (texte libre, par défaut : remboursement si l'atelier est annulé par l'artisane).
- Inscription : conditions affichées et case obligatoire (atelier avec acompte seulement), acceptation horodatée. Email récapitulatif : acompte annoncé. Email de confirmation : bloc acompte (montant, date limite, bouton SumUp, conditions).
- Suivi de l'atelier : « N acomptes reçus sur M », badge par participant (reçu / en attente / en retard), bouton « Acompte reçu » (et « Annuler »), bouton « Relancer » (email de rappel, date de relance mémorisée). Retard : simple signalement, la place n'est pas libérée automatiquement.
- Annulation : message adapté (remboursé / conservé) avant et après l'annulation ; email à l'artisane pour toute annulation d'une date confirmée.
- Juridique (à faire valider par l'artisane : comptable, CMA) : pour une activité de loisir à date déterminée, le droit de rétractation de 14 jours ne s'applique normalement pas, mais les conditions doivent être claires et acceptées avant paiement.
- Migration `Version20261031135213`. Vérifié de bout en bout sur un atelier de test (supprimé ensuite) : refus sans case cochée, inscription, emails (récapitulatif, confirmation avec acompte, relance) dans MailDev, « Acompte reçu », page d'annulation dans les deux cas (remboursé / conservé), annulation réelle et email à l'artisane.

## 2026-10-31 — Message de réussite et email « acompte reçu »

- Messages de réussite du site (inscription enregistrée, annulation faite, messages flash « success ») : alerte verte `notice--success` (fond vert pâle, pastille cochée), couleurs `--color-success*` dans les tokens. Après inscription, le message rappelle la suite (email de confirmation, lien d'acompte si l'atelier en demande un).
- Quand l'artisane clique « Acompte reçu » dans le suivi, la personne reçoit l'email « Votre place est réservée » (inscription définitivement validée, date limite de remboursement, lien d'annulation). Rien n'est envoyé si l'acompte est saisi à la main dans la fiche d'inscription, ni quand on décoche.

## 2026-10-31 — Disponibilités multiples après confirmation

- Quand une date est retenue, les personnes inscrites à cette date (même adresse email) sont retirées des autres dates à venir non confirmées de l'atelier (ouvertes, complètes ou annulées) : places libérées, plus de doublon si une autre date est confirmée plus tard, pas de données inutiles. Automatique, sans case à cocher. Leur email de confirmation le mentionne ; la fenêtre « Retenir cette date » annonce le nombre d'inscriptions retirées ; le message de résultat aussi.
- Les personnes qui n'étaient pas disponibles à la date retenue gardent leurs autres inscriptions (email « la date est fixée » inchangé).
- Correction au passage : les inscrits d'une date déjà confirmée ne reçoivent plus l'email d'information si une autre date est confirmée (cas impossible depuis le suivi actuel, qui ne propose plus « Retenir » une fois une date retenue ; vérifié par relecture et PHPStan seulement).
- Vérifié sur un atelier de test (supprimé ensuite) : décompte annoncé dans la fenêtre, inscriptions retirées en base, emails (mention de retrait pour les confirmés, information pour les autres), message de résultat.

## 2026-10-31 — Plusieurs groupes pour un même atelier

- Une fois une date retenue, les autres dates à venir non retenues (ouvertes, complètes ou annulées) gardent leur bouton « Retenir cette date » : l'artisane peut faire un second groupe. Une date annulée retenue redevient une date confirmée (la fenêtre le signale).
- Suivi : un bandeau par date retenue (« Groupe 1 », « Groupe 2 »… avec participants, acomptes, relance), et une invitation quand des personnes attendent sur des dates pas encore retenues. Liste et accueil : « et N autre(s) date(s) retenue(s) ».
- Les inscrits d'une date déjà retenue ne reçoivent plus l'email d'information quand une autre date est retenue.
- Vérifié sur un atelier de test (supprimé ensuite) : 1re date retenue en gardant les autres ouvertes (retrait du doublon, invitation affichée), 2e date retenue (deux bandeaux, seule la personne de la date restante informée, date restante annulée), ligne de la liste.
