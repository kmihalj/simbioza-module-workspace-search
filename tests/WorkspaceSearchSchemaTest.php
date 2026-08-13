<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspaceSearch\Tests;

use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\Migration\ReversibleMigrationInterface;
use AaiEduHr\HeartPhrameModuleWorkspaceSearch\ModuleWorkspaceSearch;
use HeartPhrame\Config\Config;
use HeartPhrame\Helper\Helper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleWorkspaceSearch::class)]
final class WorkspaceSearchSchemaTest extends TestCase
{
    /**
     * HR: Potvrđuje da je izvedeni indeks prenosiv i potpuno reverzibilan na SQLiteu.
     * EN: Confirms the derived index is portable and fully reversible on SQLite.
     */
    public function testMigrationCreatesAndDropsSearchIndex(): void
    {
        $helper = new Helper();
        $database = new Database(new Config($helper, [
            'database' => [
                'connections' => [
                    'default' => ['driver' => 'sqlite', 'database' => ':memory:'],
                ],
            ],
        ]), $helper);
        $migration = require dirname(__DIR__) . '/resources/migrations/initial_workspace_search_schema.php';

        $this->assertInstanceOf(ReversibleMigrationInterface::class, $migration);
        $migration->up($database);
        $this->assertTrue($database->schema()->hasTable(ModuleWorkspaceSearch::TABLE_INDEX));

        $this->assertTrue($database->schema()->hasColumns(
            ModuleWorkspaceSearch::TABLE_INDEX,
            ['normalized_text', 'language_code', 'content_hash'],
        ));

        $migration->down($database);
        $this->assertFalse($database->schema()->hasTable(ModuleWorkspaceSearch::TABLE_INDEX));
    }
}
