<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rb_charge_attempts')) {
            return; // idempotente — ADR tech/0008
        }

        Schema::create('rb_charge_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('invoice_id')->constrained('rb_invoices')->cascadeOnDelete();
            $table->string('gateway', 30)->index();
            $table->unsignedSmallInteger('attempt_n')
                ->comment('1ª tentativa = 1, retry = 2, 3, ...');
            $table->enum('status', ['pending', 'sent', 'succeeded', 'failed', 'soft_decline', 'hard_decline'])
                ->index();
            $table->json('request_json')->nullable();
            $table->json('response_json')->nullable();
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            // append-only — não tem updated_at
            $table->timestamp('created_at')->useCurrent();

            $table->index(['invoice_id', 'attempt_n'], 'rb_charge_inv_attempt_idx');
            $table->unique(['invoice_id', 'attempt_n'], 'rb_charge_inv_attempt_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rb_charge_attempts');
    }
};
