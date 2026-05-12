<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * channel_user_access — ACL atendente↔canal omnichannel (US-WA-068, ADR 0135).
 *
 * NOVA tabela escopada em `channels` (omnichannel polimórfico). Coexiste com
 * `whatsapp_phone_user_access` (tabela legacy escopada em
 * `whatsapp_business_phones`) — não migra, não substitui. Filtragem de inbox
 * por canais permitidos vai pra US-WA-069.
 *
 * Soft revoke (revoked_at NULL = ativo) preserva audit history. UNIQUE inclui
 * revoked_at pra permitir re-grant após revoke (1 par canal/user pode ter N
 * rows históricas mas só 1 com revoked_at IS NULL).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — `business_id` global scope
 * via trait HasBusinessScope no Model.
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-068
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('channel_user_access')) {
            return; // idempotente
        }

        Schema::create('channel_user_access', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('channel_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('granted_by_user_id');
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable()
                ->comment('soft revoke — NULL = ativo. Preserva audit history.');
            $table->unsignedInteger('revoked_by_user_id')->nullable();
            $table->timestamps();

            // UNIQUE inclui revoked_at — permite re-grant após revoke.
            // MySQL trata NULLs como distintos em UNIQUE → múltiplas rows
            // revoked_at != NULL podem coexistir; apenas 1 row revoked_at=NULL
            // por (channel_id, user_id).
            $table->unique(
                ['channel_id', 'user_id', 'revoked_at'],
                'cua_channel_user_unq'
            );
            $table->index(['business_id', 'user_id'], 'cua_biz_user_idx');
            $table->index(['business_id', 'channel_id'], 'cua_biz_channel_idx');

            $table->foreign('channel_id', 'cua_channel_fk')
                ->references('id')->on('channels')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_user_access');
    }
};
