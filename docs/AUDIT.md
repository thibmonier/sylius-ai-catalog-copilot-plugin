# Audit qualité — sylius-ai-catalog-copilot-plugin

> Généré le 2026-10-14 · Branche `chore/quality-tooling` · Outillage claude-craft (ECS, PHPStan, Rector, PHPUnit) exécuté via Docker `ghcr.io/sylius/sylius-php:8.4-alpine`.

## 1. Audit factuel (outils)

| Contrôle | Commande | Résultat |
|---|---|---|
| Style (ECS) | `vendor/bin/ecs check` | ✅ 0 erreur |
| Analyse statique (PHPStan **level max**) | `vendor/bin/phpstan analyse` | ✅ 0 erreur |
| Tests unitaires | `vendor/bin/phpunit --testsuite=unit` | ✅ 335 tests, 1717 assertions |
| Validation composer | `composer validate --strict` | ✅ valide |
| Modernisation (Rector, dry-run) | `vendor/bin/rector process --dry-run` | ⚠️ 24 fichiers avec suggestions |
| Tests `non-unit` (functional/integration) | — | ⏭️ non exécutés localement (DB + app Sylius requise) — couverts par le CI |
| Vulnérabilités dépendances | `composer audit` | ⚠️ 2 paquets abandonnés (transitifs : `behat/transliterator`, `php-http/message-factory`) — 0 CVE de sécurité |

**Rector** vient d'être ajouté (`rector/rector ^2.0`, `rector.php`, cibles `make rector` / `make rector-fix`). Les 24 fichiers signalés relèvent principalement de `ArrowFunctionDelegatingCallToFirstClassCallableRector` — suggestions de modernisation non bloquantes, non appliquées. Lancer `make rector-fix` pour les appliquer puis relancer ECS/PHPStan/tests.

**Paquets abandonnés** : `behat/transliterator` et `php-http/message-factory` sont des dépendances transitives de `sylius/sylius`. Aucune action requise côté plugin.

## 2. Audit qualitatif (revue par agents)

| Dimension | Score | Synthèse |
|---|---|---|
| PHP / Clean Archi / SOLID | **8/10** | Typage strict sur 46 fichiers, `final` sur toutes les classes de service, pipeline propre Facts → Prompt → Generator → Guard → Suggestion. Légère complexité sur `ContentGuard` (~218 lignes, multiple concerns). |
| Intégration Symfony / Sylius | **8/10** | Resource system Sylius bien câblé, Twig hooks à jour (vs old SyliusThemeBundleInterface), `ClockInterface` injecté pour la testabilité, fixture `DemoSuggestionsFixture` isole le faux générateur. `AiSuggestion` non-final intentionnellement (mapped superclass). |
| Sécurité (OWASP 2025) | **8/10** | CSRF sur toutes les actions mutantes, `ContentGuard` sanitise le texte AI (HtmlSanitizer, script/style droppés), `redirectBack` protégé contre l'open redirect (chemins relatifs uniquement, pas de `//` ni `\`), validation des IDs en bulk. Risques résiduels : pas de rate-limiting sur la génération, `$decision` non contraint côté routeur. |

> Caveats : la suite non-unit (functional/integration) n'a pas été exécutée. La compatibilité exacte des API Symfony AI 0.x (qui break à chaque minor) est à vérifier avant release.

### Points forts

- `declare(strict_types=1)` + classes `final` sur tous les services, guards et controllers.
- Pipeline clairement délimité : `ProductFactsExtractor` → `TwigPromptBuilder` → `TextGeneratorInterface` → `ContentGuard` → `SuggestionGenerator` — une seule classe touche l'API Symfony AI (`PlatformTextGenerator`).
- `SuggestionApplier` : vérification de fraîcheur (`normalize($previousText) !== normalize($suggestion->getOriginalText())`) avant toute écriture — impossible d'accepter une suggestion obsolète.
- `ContentGuard` : détection et suppression des allégations environnementales infondées (EU Green Claims Directive, Annex I point 4c), sanitisation HTML, limites de champ.
- `SuggestionController` : CSRF sur `generate`, `decide`, `bulkDecide` ; `MAX_BULK_ITEMS = 100` ; `redirectBack` safe.
- Provenance enregistrée à l'acceptation (`AiProvenance`) : traçabilité complète de l'origine du texte.
- 335 tests unitaires, 1717 assertions — couverture comportementale dense.

## 3. Recommandations priorisées

### 🟠 Majeur

1. **Pas de rate-limiting sur la génération AI** (`src/Controller/Admin/SuggestionController.php:50-73`). Un administrateur peut déclencher des centaines de requêtes vers l'API tierce (Anthropic, OpenAI…) sans aucune limite, exposant à des coûts non maîtrisés et un déni de service sur le quota API. → Ajouter un Symfony RateLimiter (token bucket) par utilisateur admin sur l'action `generateAction`, configurable via `tmonier_ai_copilot.rate_limit`.

2. **`$decision` non contraint dans le routeur** (`src/Controller/Admin/SuggestionController.php:75,97`). Le paramètre `{decision}` accepte n'importe quelle valeur ; si un chemin non prévu passe, `decide()` appelle `reject()` par défaut silencieusement (aucun log). → Ajouter `requirements: { decision: 'accept|reject' }` dans la config de routing, ou valider explicitement en tête de méthode avec une `BadRequestHttpException`.

3. **`AiSuggestion::__construct()` utilise `new \DateTimeImmutable()`** (`src/Entity/AiSuggestion.php:53`). Le `createdAt` est fixé à l'instanciation sans `ClockInterface`, rendant les tests qui ont besoin de contrôler la date difficiles (workaround : `setCreatedAt()` exposé, mais c'est un setter public supplémentaire). → Documenter ce choix (standard Doctrine) ou retirer `setCreatedAt()` si non utilisé hors tests.

4. **`ContentGuard` viole légèrement SRP** (`src/Guard/ContentGuard.php`, 218 lignes). La classe cumule sanitisation HTML, détection de claims environnementaux, normalisation des keywords, troncature et trimQuotes. → Extraire `PlainTextConverter` et `KeywordsNormalizer` en collaborateurs injectés pour faciliter les tests et la maintenabilité.

### 🟡 Mineur

- `PlatformTextGenerator` catch `\Throwable` (ligne 47) : trop large, peut masquer des erreurs PHP fatales ou des erreurs de configuration non liées à l'API. → Restreindre aux exceptions Symfony AI connues, puis laisser les autres remonter.
- `AiSuggestion` entity non-`final` par nécessité (mapped superclass Sylius) — ajouter un commentaire expliquant ce choix pour éviter toute tentative future de le rendre `final`.
- `EnvironmentalClaimDetector` : listes de mots-clés codées en dur (revendications carbon neutral, biodiversité…). Si les régulations évoluent (EU Green Claims Directive publiée), une mise à jour manuelle est requise. → Documenter dans SPEC.md la source et la version de chaque liste.
- `SuggestionGenerator` : si `ContentGuard::apply()` retourne une chaîne vide après suppression de claims, la suggestion est quand même persistée avec `suggestedText = ''`. → Ajouter une vérification et lever une `SuggestionException` explicite si le texte final est vide.
- Paquets transitifs abandonnés (`behat/transliterator`, `php-http/message-factory`) : surveiller la migration côté Sylius, pas d'action requise maintenant.
- Appliquer les 24 suggestions Rector (`make rector-fix`).

### ℹ️ Pour information (choix de conception assumés)

- `DemoTextGenerator` est le générateur par défaut en dev/test : aucun appel API réel possible sans configuration explicite — conformément aux `Rules` du `CLAUDE.md`.
- Les textes produits sont stockés en plain text (`ContentGuard` retire toute balise) et rendus via `nl2br` + escape Sylius : pas de `|raw`, risque XSS nul.
- La provenance (`AiProvenance`) est optionnelle (`recordProvenance: bool`) — désactivable pour les cas d'usage sans besoin d'audit trail.
- `symfony/ai-*` intentionnellement pincé à un minor (0.14) : breaks à chaque minor, explicitement documenté dans SPEC.md et CLAUDE.md.

## 4. Verdict

Plugin **bien architecturé et discipliné** : outillage au vert (ECS 0 erreur, PHPStan level max 0 erreur, 335 tests unitaires, composer valide, 0 CVE), typage strict généralisé, pipeline AI clairement délimité avec isolation de la couche AI, sécurité correcte (CSRF, sanitisation, anti open-redirect). Les axes d'amélioration prioritaires sont le **rate-limiting** sur la génération (risque coût/quota API) et la **contrainte du paramètre `$decision`** dans le routeur — deux défauts relativement simples à corriger avant une release production. `ContentGuard` mérite un découpage SRP mais fonctionne correctement. Rien de bloquant côté outillage qualité.
