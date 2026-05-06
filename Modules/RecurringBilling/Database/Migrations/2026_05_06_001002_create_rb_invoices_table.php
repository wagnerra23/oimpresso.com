<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rb_invoices')) {
            return; // idempotente — ADR tech/0008
        }

        Schema::create('rb_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('subscription_id')->nullable()
                ->constrained('rb_subscriptions')->nullOnDelete();
            $table->unsignedInteger('contact_id')->index()
                ->comment('Denormalizado pra query rápida (subscriptions podem mover)');
            $table->string('numero_documento', 50)->index()
                ->comment('Display p/ cliente: "INV-2026-0001"');
            $table->decimal('valor', 15, 2);
            $table->enum('status', ['open', 'paid', 'overdue', 'canceled', 'refunded'])
                ->default('open')->index();
            $table->date('vencimento')->index();
            $table->dateTime('pago_em')->nullable();
            $table->string('gateway', 30)->nullable()
                ->comment('inter | c6 | asaas — null antes da 1ª charge attempt');
            $table->string('gateway_ref', 100)->nullable()
                ->comment('ID do pagamento no gateway (ex: pay_xyz no Asaas, codigoSolicitacao Inter)');
            $table->unsignedInteger('conta_bancaria_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'numero_documento'], 'rb_invoices_biz_num_unique');
            $table->index(['business_id', 'status', 'vencimento'], 'rb_invoices_biz_status_venc_idx');

            $table->foreign('contact_id', 'rb_invoices_contact_fk')
                ->references('id')->on('contacts')->cascadeOnDelete();
            $table->foreign('conta_bancaria_id', 'rb_invoices_conta_fk')
                ->references('id')->on('fin_contas_bancarias')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rb_invoices');
    }
};
