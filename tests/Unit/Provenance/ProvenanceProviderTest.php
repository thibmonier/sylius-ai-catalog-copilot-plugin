<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\Provenance;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\CatalogTrait;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiProvenance;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestion;
use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;
use Tmonier\SyliusAiCatalogCopilotPlugin\Provenance\ProvenanceProvider;
use Tmonier\SyliusAiCatalogCopilotPlugin\Provenance\ProvenanceView;
use Tmonier\SyliusAiCatalogCopilotPlugin\Repository\AiProvenanceRepositoryInterface;

final class ProvenanceProviderTest extends TestCase
{
    use CatalogTrait;

    public function testItMarksAiTextsAndDetectsHumanEdits(): void
    {
        $product = $this->product(['en_US' => ['name' => 'Linen shirt', 'description' => 'Edited by a human']]);
        $translation = $product->getTranslations()->get('en_US');
        self::assertInstanceOf(ProductTranslationInterface::class, $translation);
        $translation->setMetaDescription('AI meta description');
        $this->setId($translation, 7);

        $repository = $this->createMock(AiProvenanceRepositoryInterface::class);
        $repository->expects(self::once())->method('findByTranslation')->with($translation)->willReturn([
            ProductField::META_DESCRIPTION => $this->provenance(ProductField::META_DESCRIPTION, 'AI meta description'),
            ProductField::DESCRIPTION => $this->provenance(ProductField::DESCRIPTION, 'AI description'),
        ]);
        $provider = new ProvenanceProvider($repository);

        $meta = $provider->get($product, ProductField::META_DESCRIPTION, 'en_US');
        self::assertNotNull($meta);
        self::assertTrue($meta->isAiGenerated());
        self::assertSame([
            'data-ai-generated' => 'true',
            'data-ai-edited' => 'false',
            'data-ai-generator' => 'platform',
            'data-ai-model' => 'mistral-small-latest',
            'data-ai-generated-at' => '2026-10-01T09:00:00+00:00',
        ], $meta->toDataAttributes());

        $description = $provider->get($product, ProductField::DESCRIPTION, 'en_US');
        self::assertSame(ProvenanceView::STATUS_EDITED, $description?->status);
        self::assertNull($provider->get($product, ProductField::META_KEYWORDS, 'en_US'));
        self::assertSame([], $provider->all($product, 'fr_FR'));
    }

    public function testDemoTextsAreNeverReportedAsAiGenerated(): void
    {
        $product = $this->product(['en_US' => ['name' => 'Linen shirt', 'description' => 'Demo text']]);
        $translation = $this->translation($product, 'en_US');
        $this->setId($translation, 8);
        $repository = $this->createMock(AiProvenanceRepositoryInterface::class);
        $repository->method('findByTranslation')->willReturn([
            ProductField::DESCRIPTION => $this->provenance(ProductField::DESCRIPTION, 'Demo text', 'demo'),
        ]);

        $view = (new ProvenanceProvider($repository))->get($product, ProductField::DESCRIPTION, 'en_US');

        self::assertNotNull($view);
        self::assertSame(ProvenanceView::STATUS_GENERATED, $view->status);
        self::assertTrue($view->isDemo());
        self::assertFalse($view->isAiGenerated());
        self::assertSame('demo', $view->getLabel());
        self::assertSame('false', $view->toDataAttributes()['data-ai-generated']);
        self::assertSame('demo', $view->toDataAttributes()['data-ai-generator']);
    }

    public function testCrlfLineEndingsOfASavedFormKeepTheMarking(): void
    {
        $product = $this->product(['en_US' => ['name' => 'Linen shirt', 'description' => "Para 1.\r\n\r\nPara 2.\r\n"]]);
        $translation = $this->translation($product, 'en_US');
        $this->setId($translation, 9);
        $repository = $this->createMock(AiProvenanceRepositoryInterface::class);
        $repository->method('findByTranslation')->willReturn([
            ProductField::DESCRIPTION => $this->provenance(ProductField::DESCRIPTION, "Para 1.\n\nPara 2."),
        ]);

        self::assertTrue((new ProvenanceProvider($repository))->get($product, ProductField::DESCRIPTION, 'en_US')?->isAiGenerated());
    }

    public function testUnsavedTranslationsHaveNoProvenance(): void
    {
        $repository = $this->createMock(AiProvenanceRepositoryInterface::class);
        $repository->expects(self::never())->method('findByTranslation');

        self::assertSame([], (new ProvenanceProvider($repository))->all($this->product(), 'en_US'));
    }

    private function provenance(string $field, string $text, string $generator = 'platform'): AiProvenance
    {
        $suggestion = new AiSuggestion();
        $suggestion->setField($field);
        $suggestion->setGenerator($generator);
        $suggestion->setModel('mistral-small-latest');
        $suggestion->setCreatedAt(new \DateTimeImmutable('2026-10-01 09:00:00', new \DateTimeZone('UTC')));
        $provenance = new AiProvenance();
        $provenance->record($suggestion, $text, new \DateTimeImmutable('2026-10-02 10:00:00'));

        return $provenance;
    }

    private function setId(object $object, int $id): void
    {
        $property = new \ReflectionProperty($object, 'id');
        $property->setValue($object, $id);
    }
}
