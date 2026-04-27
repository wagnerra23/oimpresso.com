<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copiloto_memoria_facts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('user_id');
            $table->text('fato');
            $table->json('metadata')->nullable();
            $table->timestamp('valid_from')->useCurrent();
            $table->timestamp('valid_until')->nullable()->comment('NULL = ativo; preenchido = superseded');
            $table->timestamps();
            $table->softDeletes()->comment('LGPD opt-out via esquecer()');

            $table->index(['business_id', 'user_id'], 'cmf_biz_user_idx');
            $table->index(['valid_from', 'valid_until'], 'cmf_validity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copiloto_memoria_facts');
    }
};
