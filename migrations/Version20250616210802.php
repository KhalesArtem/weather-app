<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates weather_data table for caching weather API responses
 */
final class Version20250616210802 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create weather_data table for caching weather information and messenger_messages table';
    }

    public function up(Schema $schema): void
    {
        // Create weather_data table for caching weather API responses
        if (!$schema->hasTable('weather_data')) {
            $table = $schema->createTable('weather_data');

            $table->addColumn('id', Types::INTEGER)
                ->setAutoincrement(true)
                ->setNotnull(true);

            $table->addColumn('city', Types::STRING)
                ->setLength(255)
                ->setNotnull(true);
            $table->addColumn('country', Types::STRING)
                ->setLength(255)
                ->setNotnull(true);

            $table->addColumn('temperature', Types::FLOAT)
                ->setNotnull(true);
            $table->addColumn('condition', Types::STRING)
                ->setLength(255)
                ->setNotnull(true);
            $table->addColumn('humidity', Types::INTEGER)
                ->setNotnull(true);
            $table->addColumn('wind_speed', Types::FLOAT)
                ->setNotnull(true);

            $table->addColumn('last_updated', Types::DATETIME_MUTABLE)
                ->setNotnull(true);
            $table->addColumn('created_at', Types::DATETIME_MUTABLE)
                ->setNotnull(true);
            $table->addColumn('api_last_updated', Types::STRING)
                ->setLength(255)
                ->setNotnull(true);

            $table->setPrimaryKey(['id']);

            $table->addIndex(['city'], 'idx_weather_data_city');
            $table->addIndex(['last_updated'], 'idx_weather_data_last_updated');
            $table->addIndex(['created_at'], 'idx_weather_data_created_at');
        }

        if (!$schema->hasTable('messenger_messages')) {
            $table = $schema->createTable('messenger_messages');

            $table->addColumn('id', Types::BIGINT)
                ->setAutoincrement(true)
                ->setNotnull(true);
            $table->addColumn('body', Types::TEXT)
                ->setNotnull(true);
            $table->addColumn('headers', Types::TEXT)
                ->setNotnull(true);
            $table->addColumn('queue_name', Types::STRING)
                ->setLength(190)
                ->setNotnull(true);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE)
                ->setNotnull(true);
            $table->addColumn('available_at', Types::DATETIME_IMMUTABLE)
                ->setNotnull(true);
            $table->addColumn('delivered_at', Types::DATETIME_IMMUTABLE)
                ->setNotnull(false);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['queue_name'], 'IDX_75EA56E0FB7336F0');
            $table->addIndex(['available_at'], 'IDX_75EA56E0E3BD61CE');
            $table->addIndex(['delivered_at'], 'IDX_75EA56E016BA31DB');
        }
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('weather_data');
        $schema->dropTable('messenger_messages');
    }
}
