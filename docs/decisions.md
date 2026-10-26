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
| Domaine / mails | Placeholders en variables d'environnement, envoi synchrone, Mailpit en local |

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
