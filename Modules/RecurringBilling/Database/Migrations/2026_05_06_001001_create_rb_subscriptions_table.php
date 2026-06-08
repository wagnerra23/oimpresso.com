<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rb_subscriptions')) {
            return; // idempotente — ADR tech/0008
        }

        Schema::create('rb_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('plan_id')->constrained('rb_plans')->cascadeOnDelete();
            $table->unsignedInteger('contact_id')
                ->comment('FK contacts.id (UltimatePOS legado: int unsigned)');
            $table->enum('status', ['trialing', 'active', 'paused', 'canceled', 'past_due'])
                ->default('active')->index();
            $table->date('start_date');
            $table->date('next_due_date')->index();
            $table->date('billing_anchor_date')
                ->comment('Dia do mês que vira a fatura (ex: 5 = fatura todo dia 5)');
            $table->dateTime('canceled_at')->nullable();
            $table->dateTime('paused_at')->nullable();
            $table->unsignedInteger('conta_bancaria_id')->nullable()
                ->comment('Override: gateway específico pra cobrar este contrato');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'status'], 'rb_subs_biz_status_idx');
            $table->index('contact_id', 'rb_subs_contact_idx');

            $table->foreign('contact_id', 'rb_subs_contact_fk')
                ->references('id')->on('contacts')->cascadeOnDelete();
            $table->foreign('conta_bancaria_id', 'rb_subs_conta_fk')
                ->references('id')->on('fin_contas_bancarias')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rb_subscriptions');
    }
};
