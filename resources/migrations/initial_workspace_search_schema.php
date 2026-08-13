<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\Migration\ReversibleMigrationInterface;
use AaiEduHr\HeartPhrameModuleOrm\Database\Schema\Blueprint;
use AaiEduHr\HeartPhrameModuleWorkspaceSearch\ModuleWorkspaceSearch;

return new class implements ReversibleMigrationInterface {
    /**
     * HR: Kreira prenosivi indeks objavljenih Workspace stranica. ACL se ne
     *     kopira u indeks, nego se ponovno računa za svaki zahtjev.
     * EN: Creates the portable index of published Workspace pages. ACL is not
     *     copied into the index; it is recalculated for every request.
     */
    public function up(Database $db): void
    {
        $schema = $db->schema();
        if ($schema->hasTable(ModuleWorkspaceSearch::TABLE_INDEX)) {
            return;
        }

        $schema->create(ModuleWorkspaceSearch::TABLE_INDEX, static function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('workspace_id')->unsigned()->index();
            $table->bigInteger('node_id')->unsigned()->index();
            $table->string('workspace_slug', 128)->index();
            $table->string('workspace_name', 190)->index();
            $table->string('node_slug', 128)->index();
            $table->string('document_key', 190)->index();
            $table->string('language_code', 16)->index();
            $table->string('title', 255)->index();
            $table->longText('body_text');
            $table->longText('normalized_text');
            $table->bigInteger('author_user_id')->unsigned()->nullable()->index();
            $table->string('author_name', 190)->nullable()->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->integer('version_number')->unsigned();
            $table->string('content_hash', 64)->index();
            $table->timestamp('indexed_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['node_id', 'language_code'], 'workspace_search_node_locale_unique');
            $table->index(
                ['workspace_id', 'language_code', 'published_at'],
                'workspace_search_filter_idx',
            );
        });
    }

    /**
     * HR: Uklanja isključivo izvedeni indeks pretrage.
     * EN: Removes only the derived search index.
     */
    public function down(Database $db): void
    {
        $db->schema()->dropIfExists(ModuleWorkspaceSearch::TABLE_INDEX);
    }
};
