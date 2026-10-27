<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CPF do contador (US-FISCAL-011). O registro 0100 da EFD-Contribuições exige o CPF do contabilista
 * responsável, e o cadastro de 2026-10-07 só guardava nome, e-mail e CRC. Nullable: quem já tem
 * contador cadastrado não quebra; o gerador da EFD recusa o arquivo enquanto o CPF faltar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfe_contadores') || Schema::hasColumn('nfe_contadores', 'cpf')) {
            return;
        }

        Schema::table('nfe_contadores', function (Blueprint $table) {
            $table->char('cpf', 11)->nullable()->after('crc');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('nfe_contadores') && Schema::hasColumn('nfe_contadores', 'cpf')) {
            Schema::table('nfe_contadores', function (Blueprint $table) {
                $table->dropColumn('cpf');
            });
        }
    }
};
