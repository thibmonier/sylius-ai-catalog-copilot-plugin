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
        $facts = new ProductFacts('en_US', 'SHIRT', 'Shirt', [], [['name' => 'Certification', 'value' => 'EU Ecolabel', 'code' => 'certification']]);

        $result = $this->guard()->apply(ProductField::DESCRIPTION, 'An eco-friendly shirt.', $facts);

        self::assertSame('An eco-friendly shirt.', $result->text);
        // The reviewer sees why the claim was kept.
        self::assertSame([[
            'code' => 'environmental_claim_substantiated',
            'params' => ['claim' => 'eco-friendly', 'type' => 'generic', 'evidence' => 'Certification: EU Ecolabel'],
        ]], $result->warnings);
    }

    public function testANegativeEcolabelAttributeDoesNotKeepTheClaim(): void
    {
        $facts = new ProductFacts('en_US', 'SHIRT', 'Shirt', [], [['name' => 'Ecolabel', 'value' => 'no', 'code' => 'ecolabel']]);

        $result = $this->guard()->apply(ProductField::DESCRIPTION, 'A cotton shirt. An eco-friendly shirt.', $facts);

        self::assertSame('A cotton shirt.', $result->text);
        self::assertSame('environmental_claim_removed', $result->warnings[0]['code']);
    }

    public function testElementsOutsideTheTextSetKeepTheirText(): void
    {
        $guard = $this->guard();

        self::assertSame('Hello world text', $guard->apply(ProductField::DESCRIPTION, '<article><p>Hello world text</p></article>', $this->facts())->text);
        self::assertSame("Intro.\n\nWeight 2kg\nSize XL", $guard->apply(ProductField::DESCRIPTION, 'Intro.<table><tr><td>Weight</td><td>2kg</td></tr><tr><th>Size</th><td>XL</td></tr></table>', $this->facts())->text);
        self::assertSame("Weight\n2kg", $guard->apply(ProductField::DESCRIPTION, '<dl><dt>Weight</dt><dd>2kg</dd></dl>', $this->facts())->text);
        self::assertSame('Size XL fits', $guard->apply(ProductField::SHORT_DESCRIPTION, 'Size <q>XL</q> fits', $this->facts())->text);
        self::assertSame('Main text', $guard->apply(ProductField::DESCRIPTION, '<main><custom-tag>Main text</custom-tag></main>', $this->facts())->text);
    }

    public function testDangerousElementsAreDroppedWithTheirContent(): void
    {
        $text = 'Shirt.<style>p{}</style><iframe src="x">frame</iframe><template>tpl</template><object>obj</object><svg><text>svg</text></svg><noscript>ns</noscript>';

        self::assertSame('Shirt.', $this->guard()->apply(ProductField::DESCRIPTION, $text, $this->facts())->text);
    }

    public function testMarkdownCodeFencesAreRemoved(): void
    {
        self::assertSame('A linen shirt.', $this->guard()->apply(ProductField::DESCRIPTION, "```html\n<p>A linen shirt.</p>\n```", $this->facts())->text);
        self::assertSame('A linen shirt.', $this->guard()->apply(ProductField::META_DESCRIPTION, "```\nA linen shirt.\n```", $this->facts())->text);
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
