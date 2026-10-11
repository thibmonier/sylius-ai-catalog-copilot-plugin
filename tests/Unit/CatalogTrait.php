<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit;

use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslation;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Guard\FieldLimits;
use Tmonier\SyliusAiCatalogCopilotPlugin\Prompt\TwigPromptBuilder;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

trait CatalogTrait
{
    /** @param array<string, array{name: string, description?: string|null}> $translations indexed by locale */
    private function product(array $translations = ['en_US' => ['name' => 'Linen shirt']], string $code = 'LINEN_SHIRT'): ProductInterface
    {
        $product = new Product();
        $product->setCode($code);
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        foreach ($translations as $locale => $data) {
            $translation = new ProductTranslation();
            $translation->setLocale($locale);
            $translation->setName($data['name']);
            $translation->setSlug(strtolower($code) . '-' . $locale);
            $translation->setDescription($data['description'] ?? null);
            $product->addTranslation($translation);
        }

        return $product;
    }

    private function translation(ProductInterface $product, string $locale): ProductTranslationInterface
    {
        $translation = $product->getTranslations()->get($locale);
        self::assertInstanceOf(ProductTranslationInterface::class, $translation);

        return $translation;
    }

    private function promptBuilder(): TwigPromptBuilder
    {
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../templates', 'TmonierSyliusAiCatalogCopilotPlugin');

        return new TwigPromptBuilder(new Environment($loader, ['strict_variables' => true, 'autoescape' => 'name']), new FieldLimits());
    }
}
