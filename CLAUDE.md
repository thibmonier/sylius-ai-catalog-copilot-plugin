# CLAUDE.md

Sylius 2 plugin: reviewable AI suggestions (descriptions, metas, translations) for products,
through Symfony AI. Functional spec and decisions: `SPEC.md`. Public docs: `README.md`.

## Commands

PHP needs more than 128 MB to compile the test application container: prefix console and
PHPStan calls with `php -d memory_limit=2G` when running locally.

```bash
docker compose up -d mysql mailhog        # ports come from compose.override.yml (gitignored)
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite non-unit   # functional + integration, need the test DB + Sylius fixtures
vendor/bin/phpstan analyse                # level max, src + tests
vendor/bin/ecs check
vendor/bin/console lint:container
vendor/bin/console tmonier:ai-copilot:generate --field=description --locale=en_US --only-empty --dry-run
```

Local env overrides (gitignored): `compose.override.yml` (MySQL published on 3324, MailHog on
8043/1043), `tests/TestApplication/.env.local` (DATABASE_URL + MAILER_DSN) and
`.env.test.local` (DATABASE_URL). Databases: `tmonier_sylius_ai_copilot_dev` / `_test`. Dev server:
`symfony server:start -d --port=8090 --no-tls` (stop it afterwards).

## Architecture

- `src/Entity/AiSuggestion` (pending / accepted / rejected) and `AiProvenance` (one row per
  product translation + field written from an accepted suggestion). XML mappings in
  `config/doctrine` (mapped superclasses), resources `tmonier_ai_copilot.ai_suggestion|ai_provenance`.
- Pipeline: `Facts/ProductFactsExtractor` -> `Prompt/TwigPromptBuilder` (`templates/prompts`) ->
  `Generator/TextGeneratorInterface` -> `Guard/ContentGuard` -> `Suggestion/SuggestionGenerator`.
- `Generator/PlatformTextGenerator` is the ONLY class touching the Symfony AI API (0.x, breaks every
  minor). `DemoTextGenerator` is the default and the dev / test generator.
- `Suggestion/SuggestionApplier` writes the field (refuses stale suggestions), records the
  provenance, flushes, dispatches `Event/AiSuggestionAccepted`.
- `Controller/Admin/SuggestionController`: CSRF-protected POST generate / accept / reject / bulk.
  Index and show pages use the Sylius ResourceController with `hook_prefix`.
- Twig hooks in `config/twig_hooks/admin.yaml`; the optional shop notice hook is prepended by the
  extension (enabled by `provenance.shop_notice`).
- `Command/GenerateSuggestionsCommand`, `Fixture/DemoSuggestionsFixture` (always the demo generator).

## Rules

- Never call a real AI service in tests, CI or the dev env. Fake at the `TextGeneratorInterface`
  level; use `Symfony\AI\Platform\Test\InMemoryPlatform` only for the adapter test.
- Never write on a product without an explicit acceptance; never inject values into the product
  form (Live Component) client-side.
- Prompts contain catalog data only, never customer data. Keep the rules of `system.txt.twig`.
- Stored texts are plain text (Sylius escapes and `nl2br`s them): never `|raw`.
- Keep `symfony/ai-*` pinned to one minor; bump with a release and update SPEC.md section 2.
- Model class parameters `%tmonier_ai_copilot.model.*.class%` are set in `prepend()` because grids
  are processed before the extension loads.
- Migrations use the Schema API (MySQL, MariaDB, PostgreSQL) and contain plugin tables only.
- Every translation key must exist in en, fr, de, es, it, nl, pl (`TranslationsTest`). Clear the
  cache (dev and test) after adding a translation file.
- Functional tests run in a transaction rolled back after each test (kernel not rebooted) and
  create their own products (`CopilotTestTrait::createProduct()`): Sylius fixtures are random.
  Requests reset the entity manager: use `reload()` to read entities after a request.
