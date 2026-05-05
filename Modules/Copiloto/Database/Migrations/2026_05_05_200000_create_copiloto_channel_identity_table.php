<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0074 + 0075 — Channel adapter pattern.
 *
 * Mapeia (channel, wire_id) → (business_id, user_id). Multi-tenant scope
 * obrigatório: NUNCA buscar mensagem de wire_id sem passar por aqui.
 *
 * `opted_in_at` IS NULL → primeiro turn pede consentimento LGPD; só depois
 * libera chat livre. Ver flow em ChannelIdentityResolver.
 *
 * `revoked_at` IS NOT NULL → usuário pediu SAIR (LGPD Art. 18 — direito ao
 * esquecimento). Hard delete cascata é responsabilidade do controller admin.
 */
class CreateCopilotoChannelIdentityTable extends Migration
{
    public function up(): void
    {
        Schema::create('copiloto_channel_identity', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('channel', 30)
                ->comment('evolution|zapi|meta|web');

            $table->string('wire_id', 60)
                ->comment('+5511XXX... pra WhatsApp; web:user_id pra web');

            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('user_id');

            $table->timestamp('opted_in_at')->nullable()
                ->comment('NULL = ainda não consentiu LGPD; segura chat livre');

            $table->timestamp('revoked_at')->nullable()
                ->comment('LGPD opt-out — disparado por mensagem SAIR');

            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();

            $table->timestamps();

            $table->unique(['channel', 'wire_id'], 'cci_channel_wire_unique');
            $table->index(['business_id', 'channel'], 'cci_biz_channel_idx');
            $table->index('user_id', 'cci_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copiloto_channel_identity');
    }
}
