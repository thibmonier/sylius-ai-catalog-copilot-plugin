<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the AI suggestion and provenance tables (tmonier/sylius-ai-catalog-copilot-plugin).';
    }

    public function up(Schema $schema): void
    {
        $suggestion = $schema->createTable('tmonier_ai_copilot_suggestion');
        $suggestion->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $suggestion->addColumn('product_id', Types::INTEGER);
        $suggestion->addColumn('requested_by_id', Types::INTEGER, ['notnull' => false]);
        $suggestion->addColumn('decided_by_id', Types::INTEGER, ['notnull' => false]);
        $suggestion->addColumn('locale_code', Types::STRING, ['length' => 12]);
        $suggestion->addColumn('field', Types::STRING, ['length' => 32]);
        $suggestion->addColumn('source_locale_code', Types::STRING, ['length' => 12, 'notnull' => false]);
        $suggestion->addColumn('original_text', Types::TEXT, ['notnull' => false]);
        $suggestion->addColumn('suggested_text', Types::TEXT);
        $suggestion->addColumn('status', Types::STRING, ['length' => 16]);
        $suggestion->addColumn('generator', Types::STRING, ['length' => 32]);
        $suggestion->addColumn('model', Types::STRING, ['length' => 128]);
        $suggestion->addColumn('prompt_hash', Types::STRING, ['length' => 64]);
        $suggestion->addColumn('input_tokens', Types::INTEGER, ['notnull' => false]);
        $suggestion->addColumn('output_tokens', Types::INTEGER, ['notnull' => false]);
        $suggestion->addColumn('warnings', Types::JSON);
        $suggestion->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $suggestion->addColumn('decided_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $suggestion->setPrimaryKey(['id']);
        $suggestion->addIndex(['product_id'], 'IDX_FA3C61664584665A');
        $suggestion->addIndex(['requested_by_id'], 'IDX_FA3C61664DA1E751');
        $suggestion->addIndex(['decided_by_id'], 'IDX_FA3C6166E26B496B');
        $suggestion->addIndex(['status', 'created_at'], 'tmonier_ai_copilot_suggestion_status_idx');
        $suggestion->addIndex(['product_id', 'locale_code', 'field', 'status'], 'tmonier_ai_copilot_suggestion_target_idx');
        $suggestion->addForeignKeyConstraint('sylius_product', ['product_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_FA3C61664584665A');
        $suggestion->addForeignKeyConstraint('sylius_admin_user', ['requested_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_FA3C61664DA1E751');
        $suggestion->addForeignKeyConstraint('sylius_admin_user', ['decided_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_FA3C6166E26B496B');

        $provenance = $schema->createTable('tmonier_ai_copilot_provenance');
        $provenance->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $provenance->addColumn('translation_id', Types::INTEGER);
        $provenance->addColumn('suggestion_id', Types::INTEGER, ['notnull' => false]);
        $provenance->addColumn('field', Types::STRING, ['length' => 32]);
        $provenance->addColumn('generator', Types::STRING, ['length' => 32]);
        $provenance->addColumn('model', Types::STRING, ['length' => 128]);
        $provenance->addColumn('generated_at', Types::DATETIME_IMMUTABLE);
        $provenance->addColumn('accepted_at', Types::DATETIME_IMMUTABLE);
        $provenance->addColumn('content_hash', Types::STRING, ['length' => 64]);
        $provenance->setPrimaryKey(['id']);
        $provenance->addIndex(['translation_id'], 'IDX_A6B94FFC9CAA2B25');
        $provenance->addIndex(['suggestion_id'], 'IDX_A6B94FFCA41BB822');
        $provenance->addUniqueIndex(['translation_id', 'field'], 'tmonier_ai_copilot_provenance_field_uniq');
        $provenance->addForeignKeyConstraint('sylius_product_translation', ['translation_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_A6B94FFC9CAA2B25');
        $provenance->addForeignKeyConstraint('tmonier_ai_copilot_suggestion', ['suggestion_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_A6B94FFCA41BB822');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('tmonier_ai_copilot_provenance');
        $schema->dropTable('tmonier_ai_copilot_suggestion');
    }
}
