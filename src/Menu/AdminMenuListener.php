<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $catalog = $event->getMenu()->getChild('catalog');
        if (!$catalog instanceof \Knp\Menu\ItemInterface) {
            return;
        }

        $catalog
            ->addChild('tmonier_ai_suggestions', ['route' => 'tmonier_ai_copilot_admin_ai_suggestion_index'])
            ->setLabel('tmonier_ai_copilot.ui.ai_suggestions')
            ->setLabelAttribute('icon', 'tabler:sparkles')
        ;
    }
}
