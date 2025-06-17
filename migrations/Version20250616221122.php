<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename weather_data.condition column to weather_condition to avoid MySQL reserved word conflict
 */
final class Version20250616221122 extends AbstractMigration
{
    private const TABLE_NAME = 'weather_data';
    private const OLD_COLUMN_NAME = 'condition';
    private const NEW_COLUMN_NAME = 'weather_condition';
    private const COLUMN_TYPE = 'VARCHAR(255)';
    
    public function getDescription(): string
    {
        return 'Rename "condition" column to "weather_condition" in weather_data table to avoid MySQL reserved word';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(
            !$this->columnExists(self::TABLE_NAME, self::OLD_COLUMN_NAME),
            sprintf('Column "%s" does not exist in table "%s"', self::OLD_COLUMN_NAME, self::TABLE_NAME)
        );

        $this->skipIf(
            $this->columnExists(self::TABLE_NAME, self::NEW_COLUMN_NAME),
            sprintf('Column "%s" already exists in table "%s"', self::NEW_COLUMN_NAME, self::TABLE_NAME)
        );

        $this->addSql(sprintf(
            'ALTER TABLE %s CHANGE `%s` %s %s NOT NULL',
            self::TABLE_NAME,
            self::OLD_COLUMN_NAME,
            self::NEW_COLUMN_NAME,
            self::COLUMN_TYPE
        ));
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(
            !$this->columnExists(self::TABLE_NAME, self::NEW_COLUMN_NAME),
            sprintf('Column "%s" does not exist in table "%s"', self::NEW_COLUMN_NAME, self::TABLE_NAME)
        );

        $this->skipIf(
            $this->columnExists(self::TABLE_NAME, self::OLD_COLUMN_NAME),
            sprintf('Column "%s" already exists in table "%s"', self::OLD_COLUMN_NAME, self::TABLE_NAME)
        );

        $this->addSql(sprintf(
            'ALTER TABLE %s CHANGE %s `%s` %s NOT NULL',
            self::TABLE_NAME,
            self::NEW_COLUMN_NAME,
            self::OLD_COLUMN_NAME,
            self::COLUMN_TYPE
        ));
    }

    /**
     * Check if a column exists in a table
     */
    private function columnExists(string $tableName, string $columnName): bool
    {
        $sql = sprintf(
            "SELECT COUNT(*) as count FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = '%s' 
             AND COLUMN_NAME = '%s'",
            $tableName,
            $columnName
        );

        $result = $this->connection->fetchAssociative($sql);
        
        return (int) ($result['count'] ?? 0) > 0;
    }

    /**
     * Indicates if this migration should be executed in a transactional manner
     */
    public function isTransactional(): bool
    {
        // ALTER TABLE statements in MySQL cause implicit commits
        // so we can't use transactions for this migration
        return false;
    }
}