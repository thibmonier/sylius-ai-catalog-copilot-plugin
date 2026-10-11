<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAiCatalogCopilotPlugin\Provenance\ProvenanceProviderInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Repository\AiSuggestionRepositoryInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Twig\AiCopilotExtension;

final class AiCopilotExtensionTest extends TestCase
{
    public function testFlaggedClaimsAreMarkedInTheEscapedText(): void
    {
        $warnings = [
            ['code' => 'environmental_claim_flagged', 'params' => ['claim' => 'eco-friendly', 'type' => 'generic']],
            ['code' => 'environmental_claim_substantiated', 'params' => ['claim' => 'sustainable', 'type' => 'generic', 'evidence' => 'Ecolabel: EU Ecolabel']],
            ['code' => 'environmental_claim_removed', 'params' => ['claim' => 'carbon neutral', 'type' => 'carbon_neutral']],
            ['code' => 'truncated', 'params' => ['max' => 160]],
        ];

        $html = $this->extension()->highlightClaims("<b>Eco-friendly</b> & sustainable.\nNot carbon neutral.", $warnings);

        self::assertSame(
            '&lt;b&gt;<mark class="tmonier-ai-copilot-claim" data-test-tmonier-ai-copilot-claim>Eco-friendly</mark>&lt;/b&gt; &amp; <mark class="tmonier-ai-copilot-claim" data-test-tmonier-ai-copilot-claim>sustainable</mark>.<br />' . "\n" . 'Not carbon neutral.',
            $html,
        );
        self::assertSame(['eco-friendly'], $this->extension()->flaggedClaims($warnings));
    }

    public function testATextWithoutClaimsIsOnlyEscaped(): void
    {
        self::assertSame('A &lt;linen&gt; shirt.', $this->extension()->highlightClaims('A <linen> shirt.', []));
    }

    private function extension(): AiCopilotExtension
    {
        return new AiCopilotExtension(
            $this->createMock(ProvenanceProviderInterface::class),
            $this->createMock(AiSuggestionRepositoryInterface::class),
            'demo',
            'en_US',
        );
    }
}
