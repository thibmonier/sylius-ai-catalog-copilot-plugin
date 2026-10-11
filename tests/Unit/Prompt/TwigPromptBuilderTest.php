<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\Prompt;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFacts;
use Tmonier\SyliusAiCatalogCopilotPlugin\Guard\FieldLimits;
use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;
use Tmonier\SyliusAiCatalogCopilotPlugin\Prompt\TwigPromptBuilder;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class TwigPromptBuilderTest extends TestCase
{
    public function testEveryFieldHasAPromptBuiltFromTheFactsOnly(): void
    {
        $builder = $this->builder();

        foreach (ProductField::ALL as $field) {
            $prompt = $builder->buildGeneration($field, $this->facts());

            self::assertStringContainsString('Write in French (France) (fr_FR) only.', $prompt->system);
            self::assertStringContainsString('ONLY facts you may state', $prompt->system);
            self::assertStringContainsString('environmental claims', $prompt->system);
            self::assertStringContainsString("<product_facts>\nProduct name: Chemise en lin\nProduct code: LINEN_SHIRT\nCategories: Chemises\nAttributes:\n- Couleur: Bleu\n- Matière: Lin\nAvailable options:\n- Taille: S, M\n</product_facts>", $prompt->user);
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $prompt->hash());
        }
    }

    public function testTheMetaDescriptionPromptCarriesTheLengthLimits(): void
    {
        $prompt = $this->builder()->buildGeneration(ProductField::META_DESCRIPTION, $this->facts());

        self::assertStringContainsString('between 50 and 160 characters', $prompt->user);
        self::assertStringContainsString('under 160 characters and over 50 characters', $prompt->system);
    }

    public function testTheTranslationPromptCarriesTheSourceText(): void
    {
        $prompt = $this->builder()->buildTranslation(ProductField::SHORT_DESCRIPTION, $this->facts(), 'en_US', 'A blue linen shirt.');

        self::assertStringContainsString('Translate the short description of the product below from English (United States) (en_US) to French (France) (fr_FR).', $prompt->user);
        self::assertStringContainsString("<source_text>\nA blue linen shirt.\n</source_text>", $prompt->user);
    }

    public function testTheHashChangesWithTheFacts(): void
    {
        $builder = $this->builder();
        $other = new ProductFacts('fr_FR', 'LINEN_SHIRT', 'Chemise en lin', [], [['name' => 'Couleur', 'value' => 'Rouge']]);

        self::assertNotSame(
            $builder->buildGeneration(ProductField::DESCRIPTION, $this->facts())->hash(),
            $builder->buildGeneration(ProductField::DESCRIPTION, $other)->hash(),
        );
    }

    private function builder(): TwigPromptBuilder
    {
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../../templates', 'TmonierSyliusAiCatalogCopilotPlugin');

        return new TwigPromptBuilder(new Environment($loader, ['strict_variables' => true, 'autoescape' => 'name']), new FieldLimits());
    }

    private function facts(): ProductFacts
    {
        return new ProductFacts(
            'fr_FR',
            'LINEN_SHIRT',
            'Chemise en lin',
            ['Chemises'],
            [['name' => 'Couleur', 'value' => 'Bleu'], ['name' => 'Matière', 'value' => 'Lin']],
            [['name' => 'Taille', 'values' => ['S', 'M']]],
        );
    }
}
