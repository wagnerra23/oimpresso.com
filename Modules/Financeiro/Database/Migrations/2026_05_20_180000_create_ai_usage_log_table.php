<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onda 23 (2026-05-20) US-FIN-029 — audit cost OCR boleto + outras chamadas LLM Jana/Financeiro.
 *
 * Toda chamada IA (OpenAI Vision, Textract fallback, etc) DEVE gravar entry aqui
 * com cost_usd literal pra Wagner saber quanto está gastando per-business.
 *
 * Multi-tenant Tier 0: business_id obrigatório (ADR 0093).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->string('feature', 64);              // ex: 'financeiro.ocr_boleto', 'jana.chat'
            $table->string('provider', 32);             // ex: 'openai', 'aws_textract', 'anthropic'
            $table->string('model', 64);                // ex: 'gpt-4o', 'textract-async'
            $table->string('operation', 32)->default('extract'); // extract, embed, complete
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6);         // 6 decimais — calls Vision custam $0.000XXX
            $table->string('idempotency_hash', 64)->nullable()->index(); // SHA-256 input → evita re-cobrar
            $table->string('status', 16)->default('ok'); // ok / error / quota_exceeded / timeout
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();       // {filename, mime, ocr_confidence, etc}
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'feature', 'created_at'], 'ai_usage_log_biz_feature_idx');
            $table->index(['business_id', 'status'], 'ai_usage_log_biz_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_log');
    }
};
