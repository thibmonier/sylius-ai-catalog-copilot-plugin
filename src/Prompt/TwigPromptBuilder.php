<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Prompt;

use Symfony\Component\Intl\Locales;
use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFacts;
use Tmonier\SyliusAiCatalogCopilotPlugin\Guard\FieldLimits;
use Twig\Environment;

/**
 * Renders the prompts from Twig templates, overridable in the application under
 * templates/bundles/TmonierSyliusAiCatalogCopilotPlugin/prompts/.
 */
final class TwigPromptBuilder implements PromptBuilderInterface
{
    public const TEMPLATE_PREFIX = '@TmonierSyliusAiCatalogCopilotPlugin/prompts/';

    public function __construct(
        private readonly Environment $twig,
        private readonly FieldLimits $limits,
    ) {
    }

    public function buildGeneration(string $field, ProductFacts $facts): Prompt
    {
        $context = $this->context($field, $facts);

        return new Prompt(
            trim($this->twig->render(self::TEMPLATE_PREFIX . 'system.txt.twig', $context)),
            trim($this->twig->render(self::TEMPLATE_PREFIX . $field . '.txt.twig', $context)),
        );
    }

    public function buildTranslation(string $field, ProductFacts $facts, string $sourceLocaleCode, string $sourceText): Prompt
    {
        $context = $this->context($field, $facts) + [
            'source_locale_code' => $sourceLocaleCode,
            'source_language' => self::languageName($sourceLocaleCode),
            'source_text' => $sourceText,
        ];

        return new Prompt(
            trim($this->twig->render(self::TEMPLATE_PREFIX . 'system.txt.twig', $context)),
            trim($this->twig->render(self::TEMPLATE_PREFIX . 'translate.txt.twig', $context)),
        );
    }

    public static function languageName(string $localeCode): string
    {
        return Locales::exists($localeCode) ? Locales::getName($localeCode, 'en') : $localeCode;
    }

    /** @return array<string, mixed> */
    private function context(string $field, ProductFacts $facts): array
    {
        return [
            'field' => $field,
            'facts' => $facts,
            'locale_code' => $facts->localeCode,
            'language' => self::languageName($facts->localeCode),
            'min_length' => $this->limits->minLength($field),
            'max_length' => $this->limits->maxLength($field),
        ];
    }
}
