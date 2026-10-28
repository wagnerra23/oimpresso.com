<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-RB-011 (reaberta por [W] 2026-10-27) — cartão do cliente salvo como TOKEN do gateway.
 *
 * Não existe coluna para número nem CVV: o cartão é digitado na página hospedada do gateway e o
 * oimpresso recebe só o token, os últimos 4 dígitos e a bandeira (PCI). `token` é cifrado pela
 * aplicação (cast `encrypted`); `token_hash` (sha256) existe só para não salvar o mesmo cartão duas
 * vezes, porque texto cifrado não indexa.
 *
 * `rb_subscriptions.cartao_salvo_id` diz em qual cartão a assinatura é cobrada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rb_cartoes_salvos')) {
            Schema::create('rb_cartoes_salvos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedInteger('contact_id')->index();
                $t->string('gateway_key', 30);
                $t->text('token');
                $t->char('token_hash', 64);
                $t->char('ultimos_4', 4)->nullable();
                $t->string('bandeira', 30)->nullable();
                $t->enum('status', ['ativo', 'removido'])->default('ativo');
                $t->unsignedBigInteger('origem_cobranca_id')->nullable();
                $t->timestamps();

                $t->unique(['business_id', 'token_hash'], 'rb_cartoes_biz_token_unique');
                $t->foreign('business_id', 'rb_cartoes_business_fk')->references('id')->on('business')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('rb_subscriptions') && ! Schema::hasColumn('rb_subscriptions', 'cartao_salvo_id')) {
            Schema::table('rb_subscriptions', function (Blueprint $t) {
                $t->unsignedBigInteger('cartao_salvo_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('rb_subscriptions', 'cartao_salvo_id')) {
            Schema::table('rb_subscriptions', fn (Blueprint $t) => $t->dropColumn('cartao_salvo_id'));
        }
        Schema::dropIfExists('rb_cartoes_salvos');
    }
};
