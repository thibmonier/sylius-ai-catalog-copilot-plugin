<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Generator;

use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;

/**
 * Deterministic generator that never calls any AI service: it assembles English sentences
 * from the product facts. It lets the plugin be tried (and tested) without credentials, and
 * the admin labels its suggestions as coming from the "demo generator".
 *
 * One product out of three gets a generic environmental claim, to show the content guard.
 */
final class DemoTextGenerator implements TextGeneratorInterface
{
    public const TYPE = 'demo';

    public const MODEL = 'demo';

    public function generate(GenerationRequest $request): GeneratedText
    {
        $text = $request->isTranslation()
            ? sprintf('[%s] %s', $request->facts->localeCode, (string) $request->sourceText)
            : match ($request->field) {
                ProductField::DESCRIPTION => $this->description($request),
                ProductField::SHORT_DESCRIPTION => $this->shortDescription($request),
                ProductField::META_DESCRIPTION => $this->metaDescription($request),
                ProductField::META_KEYWORDS => $this->metaKeywords($request),
                default => throw new GenerationFailedException(sprintf('The demo generator cannot write the "%s" field.', $request->field)),
            };

        return new GeneratedText($text, self::MODEL);
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    private function description(GenerationRequest $request): string
    {
        $facts = $request->facts;
        $paragraphs = [
            [] !== $facts->taxons
                ? sprintf('%s is part of our %s range.', $facts->name, $facts->taxons[0])
                : sprintf('Discover %s.', $facts->name),
        ];

        $lines = [];
        foreach ($facts->attributes as $attribute) {
            $lines[] = sprintf('%s: %s.', $attribute['name'], $attribute['value']);
        }
        foreach ($facts->options as $option) {
            $lines[] = sprintf('Available %s: %s.', mb_strtolower($option['name']), implode(', ', $option['values']));
        }
        if ([] !== $lines) {
            $paragraphs[] = implode("\n", $lines);
        }

        if (0 === crc32($facts->code) % 3) {
            $paragraphs[] = 'An eco-friendly choice for every day.';
        }

        return implode("\n\n", $paragraphs);
    }

    private function shortDescription(GenerationRequest $request): string
    {
        $facts = $request->facts;
        $details = array_map(
            static fn (array $attribute): string => sprintf('%s %s', mb_strtolower($attribute['name']), $attribute['value']),
            array_slice($facts->attributes, 0, 2),
        );

        return [] === $details ? sprintf('%s.', $facts->name) : sprintf('%s, %s.', $facts->name, implode(', ', $details));
    }

    private function metaDescription(GenerationRequest $request): string
    {
        $facts = $request->facts;
        $text = [] !== $facts->taxons ? sprintf('%s - %s.', $facts->name, $facts->taxons[0]) : sprintf('%s.', $facts->name);
        foreach (array_slice($facts->attributes, 0, 3) as $attribute) {
            $text .= sprintf(' %s: %s.', $attribute['name'], $attribute['value']);
        }

        return $text . ' Order online today.';
    }

    private function metaKeywords(GenerationRequest $request): string
    {
        $facts = $request->facts;
        $keywords = [$facts->name, ...$facts->taxons];
        foreach ($facts->attributes as $attribute) {
            $keywords[] = $attribute['value'];
        }

        $keywords = array_values(array_unique(array_map(static fn (string $keyword): string => mb_strtolower(trim($keyword)), $keywords)));

        return implode(', ', array_slice($keywords, 0, 10));
    }
}
