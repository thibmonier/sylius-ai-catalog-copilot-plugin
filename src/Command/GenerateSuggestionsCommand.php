<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Command;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
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
    private const BATCH_SIZE = 100;

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
            ->addOption('after-id', null, InputOption::VALUE_REQUIRED, 'Only products with a greater id (cursor printed at the end of a run)')
            ->addOption('include-processed', null, InputOption::VALUE_NONE, 'Also products whose field already has an accepted or rejected suggestion in the locale')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the products without calling the generator')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $field = $input->getOption('field');
        $field = is_string($field) ? $field : '';
        $localeCode = $input->getOption('locale');
        $localeCode = is_string($localeCode) ? $localeCode : '';
        $sourceLocaleCode = $input->getOption('source-locale');
        $sourceLocaleCode = is_string($sourceLocaleCode) && '' !== $sourceLocaleCode ? $sourceLocaleCode : null;
        if (!ProductField::isValid($field) || '' === $localeCode) {
            $io->error(sprintf('Both --field (%s) and --locale are required.', implode(', ', ProductField::ALL)));

            return Command::INVALID;
        }

        $limit = $this->positiveIntegerOption($input, 'limit');
        $afterId = $this->positiveIntegerOption($input, 'after-id', true);
        if (false === $limit || false === $afterId) {
            $io->error('--limit must be a positive integer and --after-id a non-negative integer.');

            return Command::INVALID;
        }
        $limit = min($this->maxProductsPerRun, $limit ?? $this->maxProductsPerRun);

        /** @var list<string> $codes */
        $codes = $input->getOption('product');
        $products = $this->findProducts(
            $field,
            $localeCode,
            $sourceLocaleCode,
            (bool) $input->getOption('only-empty'),
            (bool) $input->getOption('include-processed'),
            $codes,
            $afterId ?? 0,
            $limit,
        );
        if ([] === $products) {
            $io->success('No product to process.');

            return Command::SUCCESS;
        }
        $lastProduct = $products[array_key_last($products)];
        $cursor = sprintf('Next run: add --after-id=%d to continue after the last product of this run.', $this->idOf($lastProduct));

        if ((bool) $input->getOption('dry-run')) {
            $io->listing(array_map(static fn (ProductInterface $product): string => (string) $product->getCode(), $products));
            $io->note(sprintf('%d product(s) would get a "%s" suggestion in %s (dry run, nothing generated).', count($products), $field, $localeCode));
            if (count($products) === $limit) {
                $io->writeln($cursor);
            }

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
        if (count($products) === $limit) {
            $io->writeln($cursor);
        }

        return 0 === $failed ? Command::SUCCESS : Command::FAILURE;
    }

    /** Null when the option is absent, false when it is not a positive (or, with $allowZero, non-negative) integer. */
    private function positiveIntegerOption(InputInterface $input, string $name, bool $allowZero = false): int|false|null
    {
        $value = $input->getOption($name);
        if (null === $value) {
            return null;
        }
        if (!is_string($value) || !ctype_digit($value) || (!$allowZero && 0 === (int) $value)) {
            return false;
        }

        return (int) $value;
    }

    /**
     * Products translated in the locale, by increasing id after the cursor, without a pending
     * suggestion for the field (nor a decided one unless $includeProcessed). Emptiness is checked
     * in PHP: whitespace or markup only counts as empty.
     *
     * @param list<string> $codes
     *
     * @return list<ProductInterface>
     */
    private function findProducts(
        string $field,
        string $localeCode,
        ?string $sourceLocaleCode,
        bool $onlyEmpty,
        bool $includeProcessed,
        array $codes,
        int $afterId,
        int $limit,
    ): array {
        $products = [];
        while (count($products) < $limit) {
            $batch = $this->findCandidates($field, $localeCode, $sourceLocaleCode, $includeProcessed, $codes, $afterId);
            if ([] === $batch) {
                break;
            }

            foreach ($batch as $product) {
                $afterId = $this->idOf($product);
                $translations = $product->getTranslations();
                $target = $translations->get($localeCode);
                $source = null === $sourceLocaleCode ? null : $translations->get($sourceLocaleCode);
                if ($onlyEmpty && $target instanceof ProductTranslationInterface && !$this->isBlank(ProductField::getValue($target, $field))) {
                    continue;
                }
                if (null !== $sourceLocaleCode && (!$source instanceof ProductTranslationInterface || $this->isBlank(ProductField::getValue($source, $field)))) {
                    continue;
                }

                $products[] = $product;
                if (count($products) >= $limit) {
                    break;
                }
            }
        }

        return $products;
    }

    /**
     * @param list<string> $codes
     *
     * @return list<ProductInterface>
     */
    private function findCandidates(string $field, string $localeCode, ?string $sourceLocaleCode, bool $includeProcessed, array $codes, int $afterId): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('product')
            ->from($this->productClass, 'product')
            ->innerJoin('product.translations', 'translation', 'WITH', 'translation.locale = :locale')
            ->andWhere('product.id > :afterId')
            ->andWhere(sprintf(
                'NOT EXISTS (SELECT suggestion.id FROM %s suggestion WHERE suggestion.product = product AND suggestion.localeCode = :locale AND suggestion.field = :field%s)',
                $this->suggestionClass,
                $includeProcessed ? ' AND suggestion.status = :pending' : '',
            ))
            ->setParameter('locale', $localeCode)
            ->setParameter('field', $field)
            ->setParameter('afterId', $afterId)
            ->orderBy('product.id', 'ASC')
            ->setMaxResults(self::BATCH_SIZE)
        ;

        if ($includeProcessed) {
            $queryBuilder->setParameter('pending', AiSuggestionInterface::STATUS_PENDING);
        }
        if (null !== $sourceLocaleCode) {
            $queryBuilder
                ->innerJoin('product.translations', 'source', 'WITH', 'source.locale = :sourceLocale')
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

    private function idOf(ProductInterface $product): int
    {
        $id = $product->getId();

        return is_int($id) ? $id : 0;
    }

    /** Empty for a reader: nothing left once markup, entities and whitespace are removed. */
    private function isBlank(?string $text): bool
    {
        if (null === $text) {
            return true;
        }

        $text = html_entity_decode(strip_tags($text), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return '' === trim((string) preg_replace('/[\s\x{00A0}\x{200B}]+/u', '', $text));
    }
}
