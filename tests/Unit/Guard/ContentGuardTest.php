<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\Guard;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFacts;
use Tmonier\SyliusAiCatalogCopilotPlugin\Guard\ContentGuard;
use Tmonier\SyliusAiCatalogCopilotPlugin\Guard\EnvironmentalClaimDetector;
use Tmonier\SyliusAiCatalogCopilotPlugin\Guard\FieldLimits;
use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;

final class ContentGuardTest extends TestCase
{
    public function testHtmlBecomesPlainTextAndScriptsAreDropped(): void
    {
        $result = $this->guard()->apply(
            ProductField::DESCRIPTION,
            '<p>Soft <strong>cotton</strong> &amp; linen.</p><script>alert("x")</script><ul><li>Size S</li><li>Size M</li></ul>',
            $this->facts(),
        );

        self::assertSame("Soft cotton & linen.\n\n- Size S\n\n- Size M", $result->text);
        self::assertSame([], $result->warnings);
    }

    public function testMarkdownEmphasisHeadingsAndWrappingQuotesAreRemoved(): void
    {
        self::assertSame('Great shirt for summer.', $this->guard()->apply(ProductField::SHORT_DESCRIPTION, '"**Great** shirt for summer."', $this->facts())->text);
        self::assertSame("Title\nBody.", $this->guard()->apply(ProductField::DESCRIPTION, "## Title\nBody.", $this->facts())->text);
    }

    public function testUnsubstantiatedClaimsAreRemovedWithTheirSentence(): void
    {
        $result = $this->guard()->apply(
            ProductField::DESCRIPTION,
            "A soft cotton shirt. It is eco-friendly and sustainable! Machine washable.\n\nCarbon neutral delivery.",
            $this->facts(),
        );

        self::assertSame('A soft cotton shirt. Machine washable.', $result->text);
        self::assertSame(
            ['environmental_claim_removed', 'environmental_claim_removed', 'environmental_claim_removed'],
            array_column($result->warnings, 'code'),
        );
        self::assertSame(['claim' => 'eco-friendly', 'type' => 'generic'], $result->warnings[0]['params'] ?? null);
    }

    public function testClaimsCanOnlyBeFlagged(): void
    {
        $guard = new ContentGuard(new EnvironmentalClaimDetector(), new FieldLimits(), ContentGuard::CLAIMS_FLAG);

        $result = $guard->apply(ProductField::DESCRIPTION, 'An eco-friendly shirt.', $this->facts());

        self::assertSame('An eco-friendly shirt.', $result->text);
        self::assertSame('environmental_claim_flagged', $result->warnings[0]['code']);
    }

    public function testClaimsBackedByAnEcolabelAttributeAreKept(): void
    {
        $facts = new ProductFacts('en_US', 'SHIRT', 'Shirt', [], [['name' => 'Certification', 'value' => 'EU Ecolabel']]);

        $result = $this->guard()->apply(ProductField::DESCRIPTION, 'An eco-friendly shirt.', $facts);

        self::assertSame('An eco-friendly shirt.', $result->text);
        self::assertSame([], $result->warnings);
    }

    public function testMetaDescriptionIsOneLineTruncatedAtAWordBoundary(): void
    {
        $text = "A comfortable cotton shirt\nwith a modern cut, " . str_repeat('perfect for every day and every season ', 5);

        $result = $this->guard()->apply(ProductField::META_DESCRIPTION, $text, $this->facts());

        self::assertLessThanOrEqual(160, mb_strlen($result->text));
        self::assertStringNotContainsString("\n", $result->text);
        self::assertStringStartsWith('A comfortable cotton shirt with a modern cut,', $result->text);
        self::assertMatchesRegularExpression('/\w$/u', $result->text);
        self::assertSame([['code' => 'truncated', 'params' => ['max' => 160]]], $result->warnings);
    }

    public function testTooShortMetaDescriptionIsFlagged(): void
    {
        $result = $this->guard()->apply(ProductField::META_DESCRIPTION, 'Cotton shirt.', $this->facts());

        self::assertSame('Cotton shirt.', $result->text);
        self::assertSame([['code' => 'too_short', 'params' => ['min' => 50, 'length' => 13]]], $result->warnings);
    }

    public function testMetaKeywordsAreNormalizedDeduplicatedAndClaimsRemoved(): void
    {
        $result = $this->guard()->apply(ProductField::META_KEYWORDS, "Shirt, COTTON; shirt\nEco-friendly, summer.", $this->facts());

        self::assertSame('shirt, cotton, summer', $result->text);
        self::assertSame('environmental_claim_removed', $result->warnings[0]['code']);
    }

    public function testMetaKeywordsAreTruncatedOnWholeKeywords(): void
    {
        $guard = new ContentGuard(new EnvironmentalClaimDetector(), new FieldLimits([ProductField::META_KEYWORDS => ['max' => 20]]));

        self::assertSame('shirt, cotton', $guard->apply(ProductField::META_KEYWORDS, 'shirt, cotton, summer wear', $this->facts())->text);
    }

    private function guard(): ContentGuard
    {
        return new ContentGuard(new EnvironmentalClaimDetector(), new FieldLimits());
    }

    private function facts(): ProductFacts
    {
        return new ProductFacts('en_US', 'SHIRT', 'Shirt', [], [['name' => 'Material', 'value' => 'Cotton']]);
    }
}
