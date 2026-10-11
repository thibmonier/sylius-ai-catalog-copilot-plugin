# Functional specification - AI catalog copilot

Status: v1, 2026-10-11. Public documentation: `README.md`.

## 1. Scope (v1)

In scope:

- Generation of `ProductTranslation` fields: `description`, `shortDescription`,
  `metaDescription`, `metaKeywords`, from the product facts; translation of one of these fields
  from a source locale to a target locale.
- Suggestions stored as entities (`AiSuggestion`), pending until an administrator accepts or
  rejects them. **Nothing is written on a product without acceptance.**
- Admin: one toolbar per locale in the product translations, a pending suggestions card after
  the product form, a suggestions grid with single and bulk accept / reject, a comparison page.
- Command `tmonier:ai-copilot:generate --field --locale [--source-locale] [--only-empty]
  [--product]* [--limit] [--dry-run]`, capped by `max_products_per_run`.
- Provenance markers on by default; content guard (facts only, environmental claims, plain
  text, length limits).
- Model and platform chosen by configuration through `symfony/ai-bundle`; no credentials in the
  database; a deterministic demo generator by default.

Out of scope (v1): preview modal as a Live Component, streaming, rate limiter and budget cap
(only `max_products_per_run`), taxon suggestions (v1.1), Symfony Workflow on suggestions,
multimodal generation (images), chatbot, automatic publication, credentials management UI,
Symfony 6.4.

## 2. Versions verified (2026-10-11)

| Package | Constraint | Verified |
|---|---|---|
| `symfony/ai-bundle` | `^0.14` | v0.14.1 (2026-09-26) is the latest; requires PHP >= 8.2, Symfony `^7.3\|^8.0`, `symfony/ai-platform ^0.14` |
| `symfony/ai-platform` | `^0.14` | v0.14.1; `Symfony\AI\Platform\Test\InMemoryPlatform` exists |
| Provider bridges | suggested | `symfony/ai-mistral-platform`, `ai-open-ai-platform`, `ai-anthropic-platform`, `ai-ollama-platform` exist on Packagist; the bundle refuses a platform config without its bridge |
| `sylius/sylius` | `^2.2` | Composer resolves 2.3.0 on PHP 8.3+/8.5 and 2.2.10 with `platform.php = 8.2.29` (dry runs); Symfony 8 resolves with Sylius 2.3 |
| `symfony/html-sanitizer` | `^7.4 \|\| ^8.0` | 7.4.20 |

Upgrade policy: Symfony AI is 0.x and publishes a minor about every month, each may break. The
plugin pins one minor (`^0.14` = `>=0.14.0 <0.15`) and ships a new release per Symfony AI minor.
The weekly `symfony-ai-next.yaml` workflow requires the next minor only (constraint replaced on
the fly), checks it is really installed and runs PHPStan and the tests: a break makes the run red
(no `continue-on-error`; the workflow is not a required check). When the next minor is not on
Packagist yet, it reports a notice and skips the tests. The only Symfony AI
code is `PlatformTextGenerator` (`PlatformInterface::invoke()`, `MessageBag`, `Message`,
`DeferredResult::asText()`, token usage metadata) and its test; everything else depends on the
plugin's own `TextGeneratorInterface`.

CI matrix: PHP 8.3/8.4 x Sylius 2.2/2.3 on Symfony 7.4 and MySQL 8.4, plus PHP 8.2 / Sylius 2.2 /
Symfony 7.4, PHP 8.4 / Sylius 2.3 / Symfony 8.0, MariaDB 11.4 on Sylius 2.2 and PostgreSQL 17 on
Sylius 2.3. No Symfony 6.4 job (Symfony AI), no PHP 8.2 + Sylius 2.3 (Sylius 2.3 requires PHP
8.3+) and no Symfony 8 + Sylius 2.2 (unsupported by Sylius 2.2). PHPStan runs on every job.

## 3. Data model

`tmonier_ai_copilot_suggestion` (`AiSuggestion`): product (cascade delete), locale code, field,
source locale code (translation) or null, original text (field value at generation time),
suggested text, status `pending|accepted|rejected`, generator type (`demo|platform|service`),
model, prompt hash (sha256 of system + user messages), input / output tokens (when the platform
reports them), warnings (JSON list `{code, params}`), requested by / decided by (admin users, set
null on delete), created / decided dates.

`tmonier_ai_copilot_provenance` (`AiProvenance`): one row per product translation and field
(unique), written when a suggestion is accepted: generator, model, generation date, acceptance
date, sha256 of the accepted text, suggestion (set null on delete). Core entities are not
modified.

## 4. Generation pipeline

1. `ProductFactsExtractor`: name, code, main taxon + taxons, attributes (one value per attribute:
   target locale, else fallback locale, else non-translatable; attributes set only in the target
   locale included; select choices translated, booleans as yes/no, dates as Y-m-d, values capped
   at 500 characters, attribute codes kept for the claims guard), options with only the values
   used by the enabled variants. Names come from the target locale, then the fallback locale.
   Translations are read with `getTranslations()->get()`, never `getTranslation()`, which would
   create and attach empty translations that the next flush inserts. **No customer data.**
2. `TwigPromptBuilder`: `prompts/system.txt.twig` (language, facts-only rule, data-not-instructions
   rule, environmental claims rule, plain text, length) + `prompts/<field>.txt.twig` or
   `prompts/translate.txt.twig`, overridable under
   `templates/bundles/TmonierSyliusAiCatalogCopilotPlugin/prompts/` (the spec's
   `@TmonierAiCopilot` namespace is in practice the bundle namespace
   `@TmonierSyliusAiCatalogCopilotPlugin`).
3. `TextGeneratorInterface`: `PlatformTextGenerator` (Symfony AI platform service + model +
   options), `DemoTextGenerator` (deterministic, no call, English text assembled from the facts,
   `[locale] source` for translations, one product out of three gets "eco-friendly" (flagged) and "carbon neutral" (removed) to
   demonstrate the guard), or an application service. Platform errors become
   `GenerationFailedException`, logged on the `tmonier_ai_copilot` channel and shown as a generic
   flash.
4. `ContentGuard`:
   - Markdown code fences, emphasis and headings removed; HTML through Symfony HtmlSanitizer with
     `Block` as default action (any element is removed but its text kept: tables, definition
     lists, `article`, `q`, custom tags) and an explicit drop list (script, style, template,
     iframe, object, embed, svg, media, form controls...), raw-text elements also stripped before
     parsing; block ends, table rows and `dt`/`dd` become line breaks; entities decoded; Sylius shop templates render descriptions escaped with `nl2br`, so the
     stored value is plain text. Meta fields become one line.
   - `EnvironmentalClaimDetector` (best-effort keyword filter; the reviewer stays responsible):
     inflection-tolerant patterns (stems + letters, whole words,
     typographic apostrophes normalised) for the Annex I examples and their translations in
     en/fr/de/es/it/nl/pl: generic claims (eco-friendly, green, ecological, sustainable, climate /
     carbon friendly, energy efficient, bio-based, standalone "eco"...), "biodegradable" /
     "compostable", and carbon / climate neutrality, net zero, emissions-free and offsetting
     ("compensated") claims. Unicode hyphens, dashes and non-breaking spaces are normalised.
     Overlapping matches are reported once (the longest). Colour words (green, vert, grün,
     verde, groen, zielony) are claims only inside eco phrases ("green product", "greener",
     "produit vert", "grünes Produkt"...), and not when the product name, attribute values or
     option values contain the same colour root; the German roots are exact word forms
     (Gründer, Gründlich are not colours), "Öko-Tex"/"Ökotex" and "ökonomisch" are excluded.
     Substantiation needs an explicit attribute whose value does not start or end with a denial
     (no, not, without, never, nein, aucun...; "no" followed by a number is a registration
     number), has no withdrawal or postponement status anywhere (expired, withdrawn, refused,
     lapsed, candidate, pending, TBD, failed, abgelaufen...) and no denial or qualifier in the
     three words before the label name (not, without, former, lacks, candidate for...); other
     negations ("EU Ecolabel, not tested on animals", "sin fosfatos") do not count. Neutrality
     and offsetting claims (including verbs: "we offset our emissions", "nous compensons nos
     émissions") win over overlapping generic matches, and a flagged claim whose sentence is
     removed with them is reported as removed. Bare "eco" next to programme / mode words or
     meaning "echo" (es/it) is not a claim. Colour phrases are exempted only for "colour + noun"
     phrases, compound colour names (Dunkelgrün) count: generic claims an `ecolabel_attributes` code whose value names a recognised label
     (EU Ecolabel, Blauer Engel, Nordic Swan... + configurable list) or an affirmative value on an
     attribute named after such a label; "biodegradable" a `biodegradable_attributes` code whose
     value starts with an explicit affirmative word or names a compostability standard; neutrality claims never. Mode
     `flag` (default, product decision of 2026-10-11) keeps the claim, highlights it (`<mark>`)
     in the suggestion page, the product page card and the grid, and shows an alert before the
     accept buttons; `remove` drops the sentence (or keyword) containing the claim. Carbon /
     climate neutrality, net zero and offsetting claims are removed in both modes (banned
     outright, Annex I point 4c). Every claim adds a warning; a substantiated claim adds a
     warning with its evidence.
   - Meta keywords normalised (lower case, comma separated, deduplicated).
   - Length: truncated at a word boundary (keywords: whole keywords) above the maximum, warning
     below the minimum. Defaults: description 5000, short description 1000, meta description
     50-160, meta keywords 255; meta fields never exceed their VARCHAR(255) column.
   - An empty result is refused.
5. `SuggestionGenerator` rejects the older pending suggestions of the same product / locale /
   field and saves the new pending one.

## 5. Decisions

- `SuggestionApplier::accept()` refuses (`stale`) when the field differs (after trimming and
  line-ending normalisation) from the original text stored at generation: a human edit is never
  overwritten. It refuses a locale without translation (a translation needs a name and a slug).
  It writes the field, records the provenance, flushes, then dispatches `AiSuggestionAccepted`.
- Accept / reject are CSRF-protected POST actions redirecting back (`_redirect` limited to local
  paths). The toolbar buttons use the HTML `form` attribute to submit a form rendered after the
  product form (forms cannot be nested), so the product Live Component is never touched
  client-side and needs no JavaScript.
- Bulk actions reuse the Sylius admin `data-bulk-delete` script (selected rows copied into
  `ids[]`), 100 suggestions at most; undecidable ones are skipped and counted.
- Provenance: marked "generated" while the hash of the normalised field (CRLF/CR to LF, trimmed)
  equals the accepted text, "edited" after a human rewrite; a form save with CRLF line endings is
  not an edit. Demo generator texts are recorded but never reported as AI-generated
  (`data-ai-generated="false"`, `data-ai-generator="demo"`, no shop notice, admin badge "demo
  text").
- Command: products by increasing id after `--after-id` (printed at the end of a full run),
  skipping products with a pending suggestion for the field and, unless `--include-processed`,
  with a decided one; `--only-empty` and the source check treat whitespace or markup-only texts
  as empty; an invalid `--limit` is an error. Exposed in the admin toolbar, as `data-ai-*` attributes
  (`tmonier_ai_copilot_provenance_attributes()`), via `ProvenanceProviderInterface`, and as an
  optional shop notice under the description (hook
  `sylius_shop.product.show.content.info.overview.accordion.details`, disabled by default).
- Twig hooks verified in Sylius 2.3.0 (vendor) and 2.2.10 (GitHub tag):
  `sylius_admin.product.update.content.form.sections.translations` (children name, slug,
  description, meta_keywords, meta_description, short_description; context `form`, `locale`),
  `sylius_admin.product.update.content` and the shop hook above.
- The demo fixture `tmonier_ai_copilot_demo_suggestions` always uses the demo generator, even when
  a platform is configured, so loading fixtures never calls an AI service.
- Tests never call an AI service: a fake at the level of `TextGeneratorInterface` and the demo
  generator; the Symfony AI adapter is tested with `InMemoryPlatform` only. No custom
  "hash platform" against the unstable 0.x API.

## 6. Legal basis (not legal advice)

- AI Act (Regulation (EU) 2024/1689) art. 50(2): machine-readable marking of AI-generated text by
  providers; a merchant running the pipeline may qualify as provider; the open source exemption
  of art. 2(12) does not cover art. 50; art. 50(4) does not target product pages. The postponement
  of art. 50 announced with the Digital Omnibus was not published in the Official Journal when
  this was written (unverified).
- Directive (EU) 2024/825, applicable since 2026-09-27: bans generic environmental claims without
  recognised excellent environmental performance and neutrality claims based on offsetting
  (Annex I points 4a and 4c of Directive 2005/29/EC as amended). French transposition still under
  discussion in early October 2026.

## 7. Risks

- Co-installability: `^0.14` excludes packages pinned to other Symfony AI minors (e.g.
  `guiziweb/sylius-grid-assistant-plugin` requires `ai-platform ^0.8` and Symfony 7.3).
- Symfony AI 0.x breaking changes (see the upgrade policy).
- Sylius core invests in Symfony AI and MCP; competing plugins (e.g. `acseo/sylius-geo-plugin`).
- Hallucinations: mitigated by facts-only prompts, the guard and mandatory human review, not
  eliminated.

## 8. Sources

- https://packagist.org/packages/symfony/ai-bundle and https://repo.packagist.org/p2/symfony/ai-bundle.json
- https://repo.packagist.org/p2/symfony/ai-platform.json
- https://github.com/symfony/ai-platform/blob/main/src/Test/InMemoryPlatform.php
- https://repo.packagist.org/p2/sylius/sylius.json
- https://repo.packagist.org/p2/guiziweb/sylius-grid-assistant-plugin.json
- https://raw.githubusercontent.com/Sylius/Sylius/v2.2.10/src/Sylius/Bundle/AdminBundle/Resources/config/app/twig_hooks/product/update.yaml
- https://eur-lex.europa.eu/eli/reg/2024/1689/oj (AI Act)
- https://eur-lex.europa.eu/eli/dir/2024/825/oj/eng
- https://cms.law/en/bgr/legal-updates/eu-ai-act-developments-key-political-agreement-on-the-digital-omnibus-on-ai-implementation-timeline-and-transparency-consultation
- Research memo: `docs/2026-10-11-memo-idees-ia-carbone-outbox.md` in the plugins workspace.
