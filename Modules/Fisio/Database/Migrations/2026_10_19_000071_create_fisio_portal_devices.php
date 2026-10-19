<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-FISIO-007b — só aditiva.
 *
 *  - `fisio_portal_devices` ("lembrar este aparelho por 30 dias", etapa 02 §3.6 / T-07a): o
 *    aparelho lembrado é um segredo aleatório num cookie httpOnly cifrado; aqui fica SÓ o
 *    sha256 dele, amarrado à linha do token (`token_id`). Link revogado ou regenerado revoga
 *    os aparelhos (PortalTokenService); depois de `remembered_until` o PIN volta.
 *  - `fisio_records.checkin_id`: liga o telemonitoramento (e os adendos de reenvio) ao
 *    check-in do dia que os gerou (Res. COFFITO 619/2025), sem procurar pelo corpo JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fisio_portal_devices')) {
            Schema::create('fisio_portal_devices', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('token_id')->constrained('fisio_portal_tokens');
                $t->char('device_hash', 64)->unique();
                $t->dateTime('remembered_until');
                $t->dateTime('revoked_at')->nullable();
                $t->timestamps();
                $t->index(['business_id', 'token_id'], 'fisio_devices_biz_token_idx');
                $t->foreign('business_id')->references('id')->on('business');
            });
        }

        if (Schema::hasTable('fisio_records') && ! Schema::hasColumn('fisio_records', 'checkin_id')) {
            Schema::table('fisio_records', function (Blueprint $t) {
                $t->foreignId('checkin_id')->nullable()->after('amends_id')->constrained('fisio_checkins');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fisio_records') && Schema::hasColumn('fisio_records', 'checkin_id')) {
            Schema::table('fisio_records', function (Blueprint $t) {
                $t->dropConstrainedForeignId('checkin_id');
            });
        }
        Schema::dropIfExists('fisio_portal_devices');
    }
};
