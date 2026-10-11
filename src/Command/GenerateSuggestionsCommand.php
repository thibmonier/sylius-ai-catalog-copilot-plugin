<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Command;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;
use Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionException;
use Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionGeneratorInterface;

/**
 * Generates pending suggestions in bulk. Nothing is written on the products: the suggestions
 * are reviewed in the admin grid.
 */
#[AsCommand(name: 'tmonier:ai-copilot:generate', description: 'Generate pending AI suggestions for a product field')]
final class GenerateSuggestionsCommand extends Command
{
    /**
     * @param class-string<ProductInterface> $productClass
     * @param class-string<AiSuggestionInterface> $suggestionClass
     */
    public function __construct(
        private readonly SuggestionGeneratorInterface $suggestionGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly string $productClass,
        private readonly string $suggestionClass,
        private readonly int $maxProductsPerRun,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('field', null, InputOption::VALUE_REQUIRED, sprintf('Field to write: %s', implode(', ', ProductField::ALL)))
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Locale of the texts to write, e.g. en_US')
            ->addOption('source-locale', null, InputOption::VALUE_REQUIRED, 'Translate the field from this locale instead of writing it from the product facts')
            ->addOption('only-empty', null, InputOption::VALUE_NONE, 'Only products whose field is empty in the locale')
            ->addOption('product', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Product code (repeatable)')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of products (capped by max_products_per_run)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the products without calling the generator')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $field = (string) $input->getOption('field');
        $localeCode = (string) $input->getOption('locale');
        $sourceLocaleCode = $input->getOption('source-locale');
        $sourceLocaleCode = is_string($sourceLocaleCode) && '' !== $sourceLocaleCode ? $sourceLocaleCode : null;
        if (!ProductField::isValid($field) || '' === $localeCode) {
            $io->error(sprintf('Both --field (%s) and --locale are required.', implode(', ', ProductField::ALL)));

            return Command::INVALID;
        }

        $limit = $this->maxProductsPerRun;
        $limitOption = $input->getOption('limit');
        if (is_string($limitOption) && ctype_digit($limitOption) && (int) $limitOption > 0) {
            $limit = min($limit, (int) $limitOption);
        }

        /** @var list<string> $codes */
        $codes = $input->getOption('product');
        $products = $this->findProducts($field, $localeCode, $sourceLocaleCode, (bool) $input->getOption('only-empty'), $codes, $limit);
        if ([] === $products) {
            $io->success('No product to process.');

            return Command::SUCCESS;
        }

        if ((bool) $input->getOption('dry-run')) {
            $io->listing(array_map(static fn (ProductInterface $product): string => (string) $product->getCode(), $products));
            $io->note(sprintf('%d product(s) would get a "%s" suggestion in %s (dry run, nothing generated).', count($products), $field, $localeCode));

            return Command::SUCCESS;
        }

        $generated = 0;
        $failed = 0;
        foreach ($products as $product) {
            try {
                $this->suggestionGenerator->generate($product, $field, $localeCode, $sourceLocaleCode);
                ++$generated;
                $io->writeln(sprintf('<info>OK</info> %s', (string) $product->getCode()), OutputInterface::VERBOSITY_VERBOSE);
            } catch (SuggestionException $exception) {
                ++$failed;
                $io->writeln(sprintf('<error>KO</error> %s: %s', (string) $product->getCode(), $this->translator->trans($exception->getMessage(), $exception->getParameters(), 'flashes', 'en')));
            }
        }

        $io->success(sprintf('%d suggestion(s) generated, %d failure(s). Review them in the admin "AI suggestions" grid.', $generated, $failed));

        return 0 === $failed ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Products translated in the locale, without a pending suggestion for the field.
     *
     * @param list<string> $codes
     *
     * @return list<ProductInterface>
     */
    private function findProducts(string $field, string $localeCode, ?string $sourceLocaleCode, bool $onlyEmpty, array $codes, int $limit): array
    {
        $property = ProductField::property($field);
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('product')
            ->from($this->productClass, 'product')
            ->innerJoin('product.translations', 'translation', 'WITH', 'translation.locale = :locale')
            ->andWhere(sprintf(
                'NOT EXISTS (SELECT suggestion.id FROM %s suggestion WHERE suggestion.product = product AND suggestion.localeCode = :locale AND suggestion.field = :field AND suggestion.status = :pending)',
                $this->suggestionClass,
            ))
            ->setParameter('locale', $localeCode)
            ->setParameter('field', $field)
            ->setParameter('pending', AiSuggestionInterface::STATUS_PENDING)
            ->orderBy('product.id', 'ASC')
            ->setMaxResults($limit)
        ;

        if ($onlyEmpty) {
            $queryBuilder->andWhere(sprintf("translation.%1\$s IS NULL OR translation.%1\$s = ''", $property));
        }
        if (null !== $sourceLocaleCode) {
            $queryBuilder
                ->innerJoin('product.translations', 'source', 'WITH', 'source.locale = :sourceLocale')
                ->andWhere(sprintf("source.%1\$s IS NOT NULL AND source.%1\$s <> ''", $property))
                ->setParameter('sourceLocale', $sourceLocaleCode)
            ;
        }
        if ([] !== $codes) {
            $queryBuilder->andWhere('product.code IN (:codes)')->setParameter('codes', $codes);
        }

        /** @var list<ProductInterface> $products */
        $products = $queryBuilder->getQuery()->getResult();

        return $products;
    }
}
