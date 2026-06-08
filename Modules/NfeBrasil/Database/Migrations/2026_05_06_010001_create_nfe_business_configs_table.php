<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_business_configs')) {
            return; // idempotente — ADR tech/0008
        }

        Schema::create('nfe_business_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->unique()
                ->comment('1:1 com business — cada empresa tem 1 config fiscal');

            $table->enum('regime', ['mei', 'simples', 'lucro_presumido', 'lucro_real'])
                ->default('simples');

            $table->json('tributacao_default')
                ->comment('Cascade Nível 4 ADR 0006: csosn|cst, aliquotas default. JSON não-NULL pra simplificar service.');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_business_configs');
    }
};
