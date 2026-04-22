<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddContactAndLocationIdToJournalEntriesTable extends Migration
{
    public $withinTransaction = false;

    public function up()
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            if (Schema::hasColumn('journal_entries', 'client_id')) {
                $this->dropIndexIfExists('client_id_index');
                $this->dropIndexIfExists('branch_id_index');
                DB::statement('ALTER TABLE `journal_entries` CHANGE `client_id` `contact_id` INT(10) UNSIGNED NULL DEFAULT NULL;');
                DB::statement('ALTER TABLE `journal_entries` CHANGE `branch_id` `location_id` INT(10) UNSIGNED NULL DEFAULT NULL;');
            }
            if (!$this->indexExists('journal_entries_contact_id_index')) {
                $table->index('contact_id');
            }
            if (!$this->indexExists('journal_entries_location_id_index')) {
                $table->index('location_id');
            }
        });
    }

    public function down()
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            if (Schema::hasColumn('journal_entries', 'contact_id')) {
                $this->dropIndexIfExists('journal_entries_contact_id_index');
                $this->dropIndexIfExists('journal_entries_location_id_index');
                DB::statement('ALTER TABLE `journal_entries` CHANGE `contact_id` `client_id` INT(10) UNSIGNED NULL DEFAULT NULL;');
                DB::statement('ALTER TABLE `journal_entries` CHANGE `location_id` `branch_id` INT(10) UNSIGNED NULL DEFAULT NULL;');
            }
            if (!$this->indexExists('client_id_index')) {
                $table->index('client_id', 'client_id_index');
            }
            if (!$this->indexExists('branch_id_index')) {
                $table->index('branch_id', 'branch_id_index');
            }
        });
    }

    private function indexExists(string $indexName): bool
    {
        $results = DB::select("SHOW INDEXES FROM `journal_entries` WHERE Key_name = ?", [$indexName]);
        return !empty($results);
    }

    private function dropIndexIfExists(string $indexName): void
    {
        if ($this->indexExists($indexName)) {
            DB::statement("ALTER TABLE `journal_entries` DROP INDEX `{$indexName}`");
        }
    }
}