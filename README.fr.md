# Sylius AI Catalog Copilot Plugin

> English: [README.md](README.md) <!-- vitrine:lang -->

[![Build](https://github.com/thibmonier/sylius-ai-catalog-copilot-plugin/actions/workflows/build.yaml/badge.svg)](https://github.com/thibmonier/sylius-ai-catalog-copilot-plugin/actions/workflows/build.yaml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**Des suggestions d'IA à relire pour les descriptions de produits, les méta-données SEO et les traductions dans le back-office de Sylius 2.**

Rédiger et traduire les textes des produits est la partie la plus lente du travail sur le catalogue. Les modèles
de langage y excellent, mais un marchand ne peut pas les laisser publier des textes non relus : les modèles inventent
des faits, et dans l'UE certaines allégations (« écologique », « neutre en carbone ») sont désormais interdites lorsqu'elles
ne sont pas étayées.

Ce plugin demande au modèle de votre choix (Mistral, OpenAI, Anthropic, un Ollama auto-hébergé...
via [Symfony AI](https://symfony.com/doc/current/ai/index.html)) des **suggestions**.
Rien n'est écrit sur un produit tant qu'un administrateur ne l'a pas accepté.

## Fonctionnalités

| Besoin | Ce que fait le plugin |
|---|---|
| Rédiger les textes d'un produit | **Barre d'outils du copilote IA** dans chaque langue de l'onglet *Traductions* du produit : *Générer* la description, la description courte, la méta-description ou les méta-mots-clés. |
| Traduire un produit | *Traduire depuis &lt;default locale&gt;* dans les autres langues, champ par champ. |
| Garder le contrôle | Chaque réponse devient une **suggestion en attente**. La page du produit les liste avec **Accepter** / **Rejeter** ; une page côte à côte compare le texte actuel et le texte proposé. Accepter écrit le champ côté serveur et recharge la page : le formulaire du produit (un Live Component) n'est jamais modifié dans le navigateur. |
| Relire en masse | **Grille des suggestions IA** (menu Catalogue) avec des filtres (état, champ, produit, langue), accepter / rejeter par ligne et **accepter / rejeter en action groupée**. |
| Ne jamais écraser un humain | Une suggestion est refusée si le champ a changé depuis sa génération. Les anciennes suggestions en attente du même champ sont rejetées lorsqu'une nouvelle est générée. |
| Des faits, pas des hallucinations | Les prompts donnent au modèle le nom du produit, les catégories, les attributs et les options comme **seuls faits** qu'il peut énoncer. Ils ne contiennent jamais de données de clients. |
| Allégations environnementales dans l'UE | Les allégations génériques (« eco-friendly », « green », « energy efficient », « écologique », « nachhaltigen », « ekologicznego », « biodegradable », « carbon neutral »... avec leurs flexions dans 7 langues) qui ne reposent pas sur un attribut d'écolabel sont **signalées** : surlignées dans le texte, avec un avertissement dans la grille, la page du produit et la page de comparaison, avant que le relecteur n'accepte. Les allégations de neutralité carbone / climatique, de zéro émission nette et de compensation (interdites purement et simplement) sont **toujours retirées**. Un mode `remove` retire toutes les allégations non étayées. Voir [Notes juridiques](#notes-juridiques). |
| Une sortie propre | Texte brut uniquement (HTML passé par Symfony HtmlSanitizer : le texte de chaque élément est conservé, les tableaux et les listes deviennent des lignes, les scripts, styles et contenus embarqués sont supprimés, les blocs Markdown retirés), limites de longueur (méta-description de 50 à 160 caractères, tronquée à une limite de mot), avertissements affichés sur la suggestion. |
| Savoir ce qui a été écrit par l'IA | **Marqueurs de provenance** : modèle et dates enregistrés pour chaque champ écrit à partir d'une suggestion, un badge dans l'administration, un avis facultatif dans la boutique et des attributs `data-ai-*`. Les textes du générateur de démonstration ne sont jamais présentés comme générés par IA. |
| Génération en masse | `tmonier:ai-copilot:generate --field=description --locale=en_US --only-empty` crée des suggestions en attente pour de nombreux produits (plafonné par exécution, avec un curseur pour continuer). |
| L'essayer sans clé d'API | Le **générateur de démonstration** par défaut assemble des textes à partir des faits du produit avec des phrases fixes dans la langue de la locale (en, fr, de, es, it, nl, pl ; anglais sinon), sans aucun appel à une IA ; « Traduire depuis » reconstruit le texte à partir des données du produit au lieu de le traduire. Ses suggestions sont clairement étiquetées dans l'administration. |
| Intégrer | Événement `AiSuggestionAccepted` après chaque suggestion acceptée. |

Également inclus : traductions en anglais, français, allemand, espagnol, italien, néerlandais et polonais ;
prompts sous forme de templates Twig surchargeables ; Twig Hooks partout ; prise en charge de MySQL, MariaDB et PostgreSQL.

## Captures d'écran

![Grille des suggestions IA dans l'administration de Sylius avec des suggestions en attente, acceptées et rejetées, l'acceptation / le rejet en action groupée et des avertissements sur les allégations environnementales](docs/images/admin-ai-suggestions-grid.jpg)

![Page d'édition d'un produit, onglet Traductions, avec la barre d'outils du copilote IA sous la traduction anglaise](docs/images/admin-product-copilot-toolbar.jpg)

![Carte des suggestions IA en attente sur la page d'édition d'un produit avec les actions Comparer, Accepter et Rejeter](docs/images/admin-product-pending-suggestions.jpg)

![Relecture côte à côte d'une suggestion de description signalant une allégation environnementale « eco-friendly » non étayée](docs/images/admin-ai-suggestion-review.jpg)

![Suggestion acceptée après relecture : message de succès, date de décision et relecteur](docs/images/admin-ai-suggestion-accepted.jpg)

## Prérequis

| | Version |
|---|---|
| PHP | 8.2+ (Sylius 2.3 exige 8.3+) |
| Sylius | 2.2, 2.3 |
| Symfony | 7.4, 8.0 (Symfony 8 avec Sylius 2.3 uniquement) |
| Symfony AI | `symfony/ai-bundle` et `symfony/ai-platform` **0.14.x** |

Symfony 6.4 n'est pas pris en charge : Symfony AI exige Symfony 7.3+.

**Symfony AI est en 0.x.** Chaque version mineure (environ une par mois) peut casser son API ; ce plugin épingle donc
`^0.14` (>= 0.14.0, < 0.15) et une nouvelle version du plugin suit chaque version mineure de Symfony AI (un job CI
hebdomadaire teste la suivante). Jusqu'à Symfony AI 1.0, le plugin ne peut pas être installé à côté d'un paquet
qui épingle une autre version mineure de Symfony AI.

## Installation

1. Installez le paquet, ainsi que le pont Symfony AI de votre fournisseur si vous en utilisez un :

    ```bash
    composer require tmonier/sylius-ai-catalog-copilot-plugin
    composer require symfony/ai-mistral-platform   # or ai-open-ai-platform, ai-anthropic-platform, ai-ollama-platform
    ```

2. Enregistrez les bundles dans `config/bundles.php` (Symfony Flex le fait automatiquement dès qu'une recipe existe) :

    ```php
    Symfony\AI\AiBundle\AiBundle::class => ['all' => true],
    Tmonier\SyliusAiCatalogCopilotPlugin\TmonierSyliusAiCatalogCopilotPlugin::class => ['all' => true],
    ```

3. Importez la configuration dans `config/packages/tmonier_sylius_ai_catalog_copilot.yaml` :

    ```yaml
    imports:
        - { resource: "@TmonierSyliusAiCatalogCopilotPlugin/config/config.yaml" }
    ```

4. Importez les routes de l'administration dans `config/routes/tmonier_sylius_ai_catalog_copilot.yaml` :

    ```yaml
    tmonier_sylius_ai_catalog_copilot:
        resource: "@TmonierSyliusAiCatalogCopilotPlugin/config/routes.yaml"
    ```

5. Exécutez les migrations (deux tables : `tmonier_ai_copilot_suggestion`, `tmonier_ai_copilot_provenance`) :

    ```bash
    bin/console doctrine:migrations:migrate
    ```

Le plugin fonctionne désormais avec le **générateur de démonstration** : ses phrases fixes ne font que montrer le workflow. Pour une vraie rédaction et de vraies traductions de vos textes, il faut un vrai modèle : branchez-en un comme ci-dessous.

## Brancher un modèle

Les plateformes et les identifiants se configurent avec `symfony/ai-bundle`, à partir de variables d'environnement.
**Aucun identifiant n'est jamais stocké en base de données.** Le plugin n'a besoin que de l'identifiant de service de la
plateforme (`ai.platform.<name>`) et d'un nom de modèle.

```yaml
# config/packages/ai.yaml
ai:
    platform:
        mistral:
            api_key: '%env(MISTRAL_API_KEY)%'
        # openai:
        #     api_key: '%env(OPENAI_API_KEY)%'
        # anthropic:
        #     api_key: '%env(ANTHROPIC_API_KEY)%'
        # ollama:
        #     endpoint: '%env(OLLAMA_HOST_URL)%'   # e.g. http://localhost:11434

# config/packages/tmonier_sylius_ai_catalog_copilot.yaml
tmonier_sylius_ai_catalog_copilot:
    generator:
        type: platform
        platform: ai.platform.mistral        # ai.platform.openai, ai.platform.anthropic, ai.platform.ollama
        model: mistral-small-latest           # any model name the platform knows
        options:
            temperature: 0.3
```

```dotenv
# .env.local - never commit it
MISTRAL_API_KEY=...
```

Un Ollama auto-hébergé garde les données des produits sur vos serveurs. Quel que soit le fournisseur, seules les données
du catalogue sont envoyées (nom du produit, code, catégories, attributs, options, et le texte source pour une
traduction).

Pour utiliser autre chose (un modèle local, un proxy, une passerelle d'entreprise), implémentez
`Tmonier\SyliusAiCatalogCopilotPlugin\Generator\TextGeneratorInterface` et définissez
`generator: { type: service, service: app.my_generator }`.

Les modèles plus récents que le catalogue de modèles de Symfony AI de la version mineure installée (par exemple `claude-haiku-5-5`
avec Symfony AI 0.14) sont refusés par la plateforme : définissez `model_class` avec la classe de modèle du pont
(`Symfony\AI\Platform\Bridge\Anthropic\Claude` pour Anthropic) pour utiliser n'importe quel identifiant de modèle. Le
`type` du générateur peut aussi être une variable d'environnement (`'%env(TMONIER_AI_GENERATOR)%'`, valeur
`demo` ou `platform`), résolue à l'exécution ; en mode `demo`, la plateforme n'est jamais créée. Lorsque la
plateforme ne peut pas être créée (par exemple clé d'API manquante), l'administration affiche un message d'erreur et rien n'est
généré. Les clés d'API restent dans des variables d'environnement : elles ne sont jamais stockées en base de données, affichées
dans l'administration, ni écrites dans les logs (les échecs sont journalisés avec la classe et le message de l'exception
uniquement).

## Configuration

```yaml
# config/packages/tmonier_sylius_ai_catalog_copilot.yaml
tmonier_sylius_ai_catalog_copilot:
    generator:
        type: demo                  # demo (default, no AI call) | platform | service
    source_locale: ~                # locale of the "Translate from" buttons, default %locale%
    max_products_per_run: 50        # cap of tmonier:ai-copilot:generate
    environmental_claims:
        mode: flag                  # flag (default): keep and highlight for the reviewer | remove
                                    # neutrality / net zero / offsetting claims are removed in both modes
        recognised_labels: []       # extra recognised ecolabel names (EN ISO 14024 type I)
        ecolabel_attributes: [ecolabel, eco_label, eu_ecolabel, environmental_label, certification, certifications]
        biodegradable_attributes: [biodegradable, biodegradability, compostable, compostability]
    limits:                         # characters, per field
        meta_description: { min: 50, max: 160 }
        short_description: { max: 1000 }
    provenance:
        enabled: true               # record which fields come from accepted AI suggestions
        shop_notice: false          # show "written with AI" under the shop product description
    rate_limit:
        enabled: true               # limit AI generation calls from the admin UI (symfony/rate-limiter)
        per_admin: 30               # maximum generations per admin user per interval
        global: 300                 # maximum generations across all admins per interval
        interval: '1 hour'          # sliding-window interval
        cache_pool: cache.rate_limiter  # cache pool for rate-limiter state
```

Le limiteur de débit utilise deux compteurs à fenêtre glissante indépendants : un par administrateur (clé
`getUserIdentifier()`) et un global (clé `'global'`). Les deux jetons sont consommés à chaque
génération ; si l'un des compteurs est épuisé, la requête est refusée avec un message flash d'erreur et la
page du produit est rechargée — rien n'est généré. L'administrateur peut réessayer une fois son quota reconstitué.
Définissez `enabled: false` si votre infrastructure limite déjà ces routes, ou si vous exécutez le
plugin dans un contexte sans cache persistant (la commande en ligne de commande n'est jamais limitée en débit).

Le pool `cache.rate_limiter` provient des valeurs par défaut du composant Rate Limiter de Symfony. Pour utiliser un
pool dédié (recommandé en production) :

```yaml
framework:
    cache:
        pools:
            cache.my_copilot_limiter:
                adapter: cache.adapter.redis  # or filesystem, apcu…

tmonier_sylius_ai_catalog_copilot:
    rate_limit:
        cache_pool: cache.my_copilot_limiter
```

## Utilisation

- **Page du produit** (*Catalogue > Produits > Modifier*, onglet *Traductions*) : dans chaque langue, *Générer*
  écrit un champ à partir des faits du produit ; *Traduire depuis ...* traduit le champ de la langue
  source. Enregistrez d'abord vos modifications : la page est rechargée. La carte *Suggestions IA en attente* sous
  le formulaire liste les suggestions avec *Accepter*, *Rejeter* et *Comparer*.
- **Grille des suggestions IA** (*Catalogue > Suggestions IA*) : filtrez, acceptez ou rejetez une ligne, ou
  sélectionnez des lignes et utilisez *Accepter la sélection* / *Rejeter la sélection* (100 au plus à la fois).
- **Ligne de commande** :

    ```bash
    bin/console tmonier:ai-copilot:generate --field=meta_description --locale=en_US --only-empty --dry-run
    bin/console tmonier:ai-copilot:generate --field=meta_description --locale=en_US --only-empty
    bin/console tmonier:ai-copilot:generate --field=description --locale=fr_FR --source-locale=en_US --product=SHIRT_01
    ```

    Les produits doivent déjà exister dans la langue cible. Les produits qui ont déjà une suggestion
    pour le champ dans la langue (en attente, acceptée ou rejetée) sont ignorés ; ajoutez
    `--include-processed` pour régénérer ceux déjà tranchés. `--only-empty` traite les textes composés d'espaces ou
    uniquement de balisage comme vides, et les textes sources vides sont ignorés. Lorsqu'une exécution atteint sa limite,
    elle affiche `--after-id=<id>` pour continuer avec les produits suivants. `--limit` doit être un entier
    positif (plafonné par `max_products_per_run`).

## Personnalisation

- Les **prompts** sont des templates Twig : `system.txt.twig` (règles), un template par champ,
  `translate.txt.twig` et `_facts.txt.twig`. Surchargez-les dans
  `templates/bundles/TmonierSyliusAiCatalogCopilotPlugin/prompts/`. Conservez les règles de
  `system.txt.twig` (faits uniquement, aucune allégation environnementale, texte brut).
- **Faits du produit** : décorez `tmonier_ai_copilot.facts_extractor`
  (`ProductFactsExtractorInterface`) pour ajouter des données du catalogue. N'ajoutez jamais de données de clients.
- **Provenance dans vos templates** :

    ```twig
    <div {{ tmonier_ai_copilot_provenance_attributes(product, 'description') }}>{{ product.description|nl2br }}</div>
    {# data-ai-generated="true" data-ai-edited="false" data-ai-model="mistral-small-latest" data-ai-generated-at="2026-10-11T09:00:00+00:00" #}

    {% set provenance = tmonier_ai_copilot_provenance(product, 'meta_description') %}
    {% if provenance and provenance.aiGenerated %}...{% endif %}
    ```

    Le code PHP peut utiliser `ProvenanceProviderInterface`. Un champ réécrit par un humain après
    l'acceptation est signalé comme `edited` (`data-ai-generated="false"`) ; les fins de ligne et les espaces
    environnants ne comptent pas comme une modification. Les textes du générateur de démonstration sont signalés
    avec `data-ai-generator="demo"` et `data-ai-generated="false"`, et n'obtiennent jamais l'avis dans la boutique.
- **Événements** : écoutez `Tmonier\SyliusAiCatalogCopilotPlugin\Event\AiSuggestionAccepted`
  (suggestion, produit, texte précédent, nouveau texte, administrateur), émis après le flush.

### Plugins associés

- [tmonier/sylius-audit-log-plugin](https://github.com/thibmonier/sylius-audit-log-plugin)
  enregistre la modification du produit faite par une suggestion acceptée, comme n'importe quelle autre modification de l'administration.
- [tmonier/sylius-bulk-actions-plugin](https://github.com/thibmonier/sylius-bulk-actions-plugin)
  pourrait proposer une action groupée « Générer des suggestions IA » sur la grille des produits (pas encore réalisé).

Les deux sont facultatifs : ce plugin ne dépend d'aucun des deux.

## Notes juridiques

*Cette section est une information, pas un conseil juridique. Vérifiez vos obligations avec un juriste.*

- **Règlement européen sur l'IA, article 50(2)** : les fournisseurs de systèmes d'IA qui génèrent du texte doivent marquer la sortie de manière
  lisible par une machine. Un marchand qui exécute ce pipeline sur son catalogue peut être considéré comme un
  fournisseur, et l'exemption open source de l'article 2(12) ne couvre pas l'article 50. Le
  plugin enregistre donc par défaut la provenance de chaque champ accepté (modèle, dates de génération
  et d'acceptation) et peut l'exposer sous forme d'attributs `data-ai-*` et d'un avis dans la boutique. L'article
  50(4) (textes publiés pour informer le public sur des questions d'intérêt public) ne vise pas
  les pages de produits. La date d'application de l'article 50 pourrait être reportée par le « Digital Omnibus » ;
  au moment de la rédaction, ce report n'était pas publié au Journal officiel.
- **Directive (UE) 2024/825** (autonomisation des consommateurs en vue de la transition écologique), applicable depuis le
  27 septembre 2026 : les allégations environnementales génériques (« eco-friendly », « green », « climate friendly »,
  « biodegradable »...) sont interdites sauf si le produit a une performance environnementale excellente reconnue
  (écolabel de l'UE, un écolabel de type I selon l'EN ISO 14024...), et les allégations de neutralité
  climatique fondées sur la compensation sont interdites purement et simplement (annexe I point 4c). Les prompts interdisent ces
  allégations, et le garde-fou de contenu les recherche dans sept langues (radicaux avec leurs
  flexions). Par défaut (`mode: flag`), une allégation non étayée est **conservée et surlignée** :
  la page de la suggestion, la page du produit et la grille la marquent et avertissent le relecteur avant
  l'acceptation, afin que le relecteur décide (ne la garder que si elle est étayée, sinon la modifier
  ou rejeter). Les allégations de neutralité carbone ou climatique, de zéro émission nette, d'absence d'émissions et de compensation
  sont **toujours retirées**, quel que soit le mode. `mode: remove` retire aussi les
  phrases comportant d'autres allégations non étayées.
  Une allégation générique n'est conservée que si l'un des `ecolabel_attributes` désigne un label reconnu
  (par exemple *Certification : EU Ecolabel*) et que la valeur ne contient aucun mot de négation, d'expiration ou d'attente
  (« no », « not », « expired », « candidate », « pending », « abgelaufen »...) ; « biodegradable » ou
  « compostable » seulement si l'un des `biodegradable_attributes` commence par un « oui » explicite
  (oui, ja, sí, tak, certified...) ou désigne une norme de compostabilité (EN 13432...). Les mots de couleur ne sont des allégations
  que dans des expressions écologiques (« green product », « greener », « produit vert »), jamais comme simples adjectifs
  de couleur, et pas lorsque le nom du produit, les attributs ou les valeurs d'option donnent cette couleur.
  OEKO-TEX n'est pas une allégation. Une allégation conservée est présentée au relecteur avec l'attribut qui l'étaye.

  **Le garde-fou est un filtre de mots-clés au mieux, pas un conseil juridique.** Il ne détecte pas les allégations formulées
  autrement et peut signaler (ou, pour les allégations de neutralité et en mode `remove`, retirer) une phrase
  légitime : vérifiez les avertissements. L'administrateur qui accepte une suggestion reste responsable
  du texte publié : lisez chaque suggestion avant de l'accepter. Les transpositions
  nationales varient (la France en discutait encore en octobre 2026).
- **Protection des données** : les prompts ne contiennent que des données du catalogue, jamais de données de clients. Les
  conditions de votre fournisseur s'appliquent toujours aux données de produits que vous envoyez ; un modèle auto-hébergé évite de les envoyer.

## Limitations connues

- **Garde-fou des allégations environnementales : lacunes connues.** C'est un filtre de mots-clés au mieux dans sept
  langues ; le relecteur reste responsable. Lacunes connues :
  - les allégations formulées en phrases entières (« we care about the planet », « inspired by nature », « a
    small step for the Earth ») ou dans d'autres langues ne sont pas détectées ;
  - un « öko » seul n'est pas une allégation (seulement les composés « Öko-… ») ; un « eco » seul est ignoré à côté de
    « mode », « programme », « de »/« dell' » (écho) et de mots similaires, de sorte qu'un « Eco » utilisé comme allégation dans un tel
    contexte est manqué ;
  - « energy efficiency class / Energieeffizienzklasse / classe d'efficacité énergétique » est
    traité comme l'étiquette énergie, pas comme une allégation ;
  - les expressions de couleur sont limitées aux noms courants (« green product/choice/alternative »...) ; une couleur
    donnée par le produit les exempte, même lorsque le texte a le sens écologique ;
  - les valeurs de preuve sont lues avec des listes de mots : une négation formulée autrement (« we gave up the EU
    Ecolabel ») peut encore être prise pour une preuve, et une valeur indiquant une restriction sans rapport
    avant le nom du label (« not tested on animals, EU Ecolabel ») est rejetée.
- Un champ à la fois ; pas de fenêtre d'aperçu, pas de streaming. La génération est synchrone (quelques secondes
  avec un modèle hébergé).
- Pas encore de limiteur de débit ni de plafond de budget : seulement `max_products_per_run` pour la commande. Le nombre de tokens est
  enregistré lorsque la plateforme le renvoie.
- Le garde-fou de contenu repose sur des mots-clés ; il ne détecte pas tous les faits ou allégations inventés.
- Une langue doit exister sur le produit (nom et slug enregistrés) avant que des textes puissent y être générés.
- Les noms de produits, les slugs, les taxons et les images sont hors périmètre ; pas de publication automatique.
- Le RBAC de Sylius Plus n'est pas intégré : tous les administrateurs peuvent générer, accepter et rejeter.

## Développement

```bash
cp compose.override.dist.yml compose.override.yml   # publishes MySQL on 3306 and MailHog on 8025; change the ports if needed
docker compose up -d mysql mailhog
composer install
(cd vendor/sylius/test-application && yarn install && yarn build)
vendor/bin/console doctrine:database:create
vendor/bin/console doctrine:migrations:migrate -n
vendor/bin/console sylius:fixtures:load -n   # includes demo pending / accepted / rejected suggestions
symfony server:start -d --no-tls             # admin: /admin, sylius / sylius

vendor/bin/phpunit --testsuite unit
APP_ENV=test vendor/bin/phpunit --testsuite non-unit   # needs a test database with fixtures
vendor/bin/phpstan analyse
vendor/bin/ecs check
```

L'application de test utilise le générateur de démonstration : aucun test, job CI ou environnement de développement n'appelle un service
d'IA. L'adaptateur Symfony AI est testé avec l'`InMemoryPlatform` de Symfony AI.


### Essayer avec un vrai modèle (Anthropic Claude)

L'application de test est câblée sur Anthropic mais utilise le générateur de démonstration sauf indication contraire
(les tests et la CI forcent toujours le générateur de démonstration : aucun appel d'API).

1. Mettez ces lignes dans `tests/TestApplication/.env.local` (ignoré par git, ne le commitez jamais) :

    ```dotenv
    ANTHROPIC_API_KEY=sk-ant-...
    TMONIER_AI_GENERATOR=platform
    # optional, default claude-haiku-5-5 (cheapest); claude-sonnet-5-5 for better wording
    TMONIER_AI_MODEL=claude-haiku-5-5
    ```

2. Videz le cache : `vendor/bin/console cache:clear`.
3. Dans l'administration, générez ou traduisez de nouveau un champ (*Générer* / *Traduire depuis* dans l'onglet
   *Traductions* du produit), puis relisez la suggestion en attente.

Chaque clic est un appel d'API payant (de quelques centaines à quelques milliers de tokens). Seules les données du catalogue sont
envoyées à Anthropic : nom et code du produit, catégories, attributs, options et, pour une
traduction, le texte source ; jamais de données de clients. Supprimez les lignes (ou définissez
`TMONIER_AI_GENERATOR=demo`) et videz le cache pour revenir au générateur de démonstration.

<!-- vitrine:demo-b2b:start -->
## Démo B2B

Ce plugin est utilisé dans la [démo B2B Sylius](https://github.com/thibmonier/sylius-b2b-demo) : une boutique Sylius 2.3 avec des données d'exemple fictives, où les plugins B2B fonctionnent ensemble. Le code source, l'installation et les fixtures de la démo sont dans ce dépôt.

- Démo en ligne : [sylius-b2b.thibaut-monier.fr](https://sylius-b2b.thibaut-monier.fr)
- Auteur : [Thibaut Monier](https://www.linkedin.com/in/thibautmonier/)

La démo est un exemple, pas une configuration de production.
<!-- vitrine:demo-b2b:end -->

## Licence

MIT. Voir [LICENSE](LICENSE).
