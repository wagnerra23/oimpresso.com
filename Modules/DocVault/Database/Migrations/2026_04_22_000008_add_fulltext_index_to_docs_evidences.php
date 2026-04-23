<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('docs_evidences')) return;

        // Fulltext só funciona em MySQL 8+ com InnoDB (já é nosso caso).
        // Columns: content (text principal) + notes (anotação do curador).
        try {
            DB::statement('ALTER TABLE docs_evidences ADD FULLTEXT INDEX docs_evidences_fulltext (content, notes)');
        } catch (\Throwable $e) {
            // Já existe ou engine não suporta — log e segue.
            logger()->warning('[DocVault] Fulltext index skip: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE docs_evidences DROP INDEX docs_evidences_fulltext');
        } catch (\Throwable) {}
    }
};
