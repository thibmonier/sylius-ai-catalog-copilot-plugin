<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\Suggestion;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUser;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\CatalogTrait;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiProvenance;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiProvenanceInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestion;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Event\AiSuggestionAccepted;
use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;
use Tmonier\SyliusAiCatalogCopilotPlugin\Repository\AiProvenanceRepositoryInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionApplier;
use Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionException;

final class SuggestionApplierTest extends TestCase
{
    use CatalogTrait;

    /** @var list<object> */
    private array $persisted = [];

    private int $flushes = 0;

    /** @var list<AiSuggestionAccepted> */
    private array $events = [];

    private ?AiProvenanceInterface $existingProvenance = null;

    public function testAcceptingWritesTheTextRecordsTheProvenanceAndDispatchesTheEvent(): void
    {
        $product = $this->product(['en_US' => ['name' => 'Linen shirt', 'description' => 'Old text']]);
        $suggestion = $this->suggestion($product, 'Old text', 'New text');
        $admin = new AdminUser();

        $this->applier()->accept($suggestion, $admin);

        self::assertSame('New text', $this->translation($product, 'en_US')->getDescription());
        self::assertSame(AiSuggestionInterface::STATUS_ACCEPTED, $suggestion->getStatus());
        self::assertSame($admin, $suggestion->getDecidedBy());
        self::assertSame('2026-10-11 12:00:00', $suggestion->getDecidedAt()?->format('Y-m-d H:i:s'));
        self::assertCount(1, $this->persisted);
        $provenance = $this->persisted[0];
        self::assertInstanceOf(AiProvenanceInterface::class, $provenance);
        self::assertSame($product->getTranslations()->get('en_US'), $provenance->getTranslation());
        self::assertSame(ProductField::DESCRIPTION, $provenance->getField());
        self::assertSame('fake-model', $provenance->getModel());
        self::assertTrue($provenance->matches('New text'));
        self::assertSame(1, $this->flushes);
        self::assertCount(1, $this->events);
        self::assertSame('Old text', $this->events[0]->previousText);
        self::assertSame('New text', $this->events[0]->newText);
        self::assertSame($product, $this->events[0]->product);
        self::assertSame($admin, $this->events[0]->acceptedBy);
    }

    public function testAnExistingProvenanceIsUpdated(): void
    {
        $product = $this->product();
        $this->existingProvenance = new AiProvenance();

        $this->applier()->accept($this->suggestion($product, null, 'New text'), null);

        self::assertSame([], $this->persisted);
        self::assertTrue($this->existingProvenance->matches('New text'));
    }

    public function testProvenanceCanBeDisabled(): void
    {
        $this->applier(false)->accept($this->suggestion($this->product(), null, 'New text'), null);

        self::assertSame([], $this->persisted);
        self::assertCount(1, $this->events);
    }

    public function testAFieldChangedSinceTheGenerationIsNotOverwritten(): void
    {
        $product = $this->product(['en_US' => ['name' => 'Linen shirt', 'description' => 'Edited by a human']]);
        $suggestion = $this->suggestion($product, 'Old text', 'New text');

        try {
            $this->applier()->accept($suggestion, null);
            self::fail('A stale suggestion was accepted.');
        } catch (SuggestionException $exception) {
            self::assertSame('tmonier_ai_copilot.error.stale', $exception->getMessage());
        }

        self::assertSame('Edited by a human', $this->translation($product, 'en_US')->getDescription());
        self::assertTrue($suggestion->isPending());
        self::assertSame(0, $this->flushes);
        self::assertSame([], $this->events);
    }

    public function testWhitespaceAndLineEndingsDoNotMakeASuggestionStale(): void
    {
        $product = $this->product(['en_US' => ['name' => 'Linen shirt', 'description' => "Line 1\r\nLine 2 "]]);

        $this->applier()->accept($this->suggestion($product, "Line 1\nLine 2", 'New text'), null);

        self::assertSame('New text', $this->translation($product, 'en_US')->getDescription());
    }

    public function testAMissingTranslationIsRefused(): void
    {
        $suggestion = $this->suggestion($this->product(), null, 'Nouveau texte');
        $suggestion->setLocaleCode('fr_FR');

        $this->expectExceptionObject(new SuggestionException('tmonier_ai_copilot.error.missing_translation'));

        $this->applier()->accept($suggestion, null);
    }

    public function testRejectingKeepsTheProductUntouched(): void
    {
        $product = $this->product(['en_US' => ['name' => 'Linen shirt', 'description' => 'Old text']]);
        $suggestion = $this->suggestion($product, 'Old text', 'New text');

        $this->applier()->reject($suggestion, null);

        self::assertSame(AiSuggestionInterface::STATUS_REJECTED, $suggestion->getStatus());
        self::assertSame('Old text', $this->translation($product, 'en_US')->getDescription());
        self::assertSame(1, $this->flushes);
        self::assertSame([], $this->events);
    }

    public function testADecidedSuggestionCannotBeDecidedAgain(): void
    {
        $suggestion = $this->suggestion($this->product(), null, 'New text');
        $this->applier()->reject($suggestion, null);

        $this->expectExceptionObject(new SuggestionException('tmonier_ai_copilot.error.already_decided'));

        $this->applier()->accept($suggestion, null);
    }

    private function suggestion(ProductInterface $product, ?string $original, string $suggested): AiSuggestion
    {
        $suggestion = new AiSuggestion();
        $suggestion->setProduct($product);
        $suggestion->setLocaleCode('en_US');
        $suggestion->setField(ProductField::DESCRIPTION);
        $suggestion->setOriginalText($original);
        $suggestion->setSuggestedText($suggested);
        $suggestion->setGenerator('platform');
        $suggestion->setModel('fake-model');

        return $suggestion;
    }

    private function applier(bool $recordProvenance = true): SuggestionApplier
    {
        $repository = $this->createMock(AiProvenanceRepositoryInterface::class);
        $repository->method('findOneByTranslationAndField')->willReturnCallback(fn (): ?AiProvenanceInterface => $this->existingProvenance);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $object): void {
            $this->persisted[] = $object;
        });
        $entityManager->method('flush')->willReturnCallback(function (): void {
            ++$this->flushes;
        });

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(AiSuggestionAccepted::class, function (AiSuggestionAccepted $event): void {
            $this->events[] = $event;
        });

        $factory = $this->createMock(FactoryInterface::class);
        $factory->method('createNew')->willReturnCallback(static fn (): AiProvenance => new AiProvenance());

        return new SuggestionApplier($repository, $factory, $entityManager, $dispatcher, new MockClock('2026-10-11 12:00:00'), $recordProvenance);
    }
}
