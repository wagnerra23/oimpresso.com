<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('docs_pages', function (Blueprint $table) {
            $table->id();
            $table->string('path', 191)->unique();           // /ponto/espelho
            $table->string('component', 255);                 // Ponto/Espelho/Show.tsx
            $table->string('module', 64)->index();            // PontoWr2
            $table->enum('status', ['planejada', 'em-dev', 'implementada', 'deprecated'])
                ->default('planejada');
            $table->json('stories')->nullable();              // ["US-PONTO-003"]
            $table->json('rules')->nullable();                // ["R-PONTO-002"]
            $table->json('adrs')->nullable();                 // ["0001", "0003"]
            $table->json('tests')->nullable();                // ["Modules/.../PontoEspelhoTest"]
            $table->string('file_path', 500);                 // resources/js/Pages/...
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['module', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docs_pages');
    }
};
