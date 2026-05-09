<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ACL atendente↔número Whatsapp — Q1 + Q5 do ADR 0117.
 *
 * Atendente fixado num número via ACL própria (não polui Spatie permissions
 * com N permissões/número/business — ver alternativa Q5-i rejeitada em
 * ADR 0117).
 *
 * Permissão Spatie `whatsapp.send` continua valendo (gating de quem pode
 * usar Whatsapp do business). Filtro per-phone vem desta tabela:
 *
 *   $phoneIds = WhatsappPhoneUserAccess::where('user_id', auth()->id())
 *       ->pluck('whatsapp_business_phone_id');
 *   WhatsappConversation::whereIn('whatsapp_business_phone_id', $phoneIds)->...
 *
 * Admin/superadmin (Gate `whatsapp.view-all-phones`) bypassa ACL — vê todos
 * números do business.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id`
 * no Model via trait `HasBusinessScope`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_phone_user_access', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('whatsapp_business_phone_id');
            $table->unsignedInteger('user_id');
            $table->timestamps();

            $table->unique(
                ['whatsapp_business_phone_id', 'user_id'],
                'wpua_phone_user_unq'
            );
            $table->index(['business_id', 'user_id'], 'wpua_biz_user_idx');

            $table->foreign('whatsapp_business_phone_id', 'wpua_phone_fk')
                ->references('id')->on('whatsapp_business_phones')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_phone_user_access');
    }
};
