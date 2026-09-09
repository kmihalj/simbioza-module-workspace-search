<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\Migration\ReversibleMigrationInterface;
use AaiEduHr\HeartPhrameModuleOrm\Database\Schema\Blueprint;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\ModuleWorkspaceSearch;

return new class implements ReversibleMigrationInterface {
    /** HR: Dodaje autora zadnje objavljene izmjene u izvedeni indeks. EN: Adds the last published editor to the derived index. */
    public function up(Database $db): void
    {
        $schema = $db->schema();
        $tableName = ModuleWorkspaceSearch::TABLE_INDEX;
        if (!$schema->hasTable($tableName)) {
            return;
        }

        $schema->table($tableName, static function (Blueprint $table) use ($schema, $tableName): void {
            if (!$schema->hasColumn($tableName, 'modified_by_user_id')) {
                $table->bigInteger('modified_by_user_id')->unsigned()->nullable()->index();
            }
            if (!$schema->hasColumn($tableName, 'modified_by_name')) {
                $table->string('modified_by_name', 190)->nullable()->index();
            }
            if (!$schema->hasColumn($tableName, 'modified_at')) {
                $table->timestamp('modified_at')->nullable()->index();
            }
        });
    }

    /** HR: Uklanja metapodatke zadnje izmjene iz izvedenog indeksa. EN: Removes last-modification metadata from the derived index. */
    public function down(Database $db): void
    {
        $schema = $db->schema();
        $tableName = ModuleWorkspaceSearch::TABLE_INDEX;
        foreach (['modified_by_user_id', 'modified_by_name', 'modified_at'] as $column) {
            if ($schema->hasColumn($tableName, $column)) {
                $schema->table($tableName, static fn(Blueprint $table): mixed => $table->dropColumn($column));
            }
        }
    }
};
