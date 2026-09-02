<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-NFE-006 / ADR TECH-0002 (NfeBrasil) — saúde da SEFAZ por UF.
 *
 * ⚠️ TABELA SEM `business_id`, DE PROPÓSITO — e isto é decisão da ADR, não esquecimento.
 * "SEFAZ-SP está fora" é o MESMO fato para todos os tenants: é estado de um serviço
 * externo do governo, não dado de negócio. Replicar por business duplicaria N vezes a
 * mesma linha e, pior, deixaria a falha de rede de um tenant contaminar a leitura do
 * outro. A ADR TECH-0002 especifica `PRIMARY KEY (uf)`.
 *
 * O corte multi-tenant fica onde importa: a OBSERVAÇÃO é global (aqui), a DECISÃO de
 * emitir em contingência é por tenant (`nfe_business_configs.em_contingencia`) — porque
 * a ADR rejeitou auto-ativação. Nenhum tenant é arrastado para contingência por esta tabela.
 * Registrada em governance/multi-tenant-scope-baseline.json > allowlist, com razão.
 *
 * `status` é STRING, não ENUM, seguindo o precedente de `reforma_tributaria_modo`
 * (migration 2026_07_03_000000): sinal de saúde de infraestrutura não é vocabulário de
 * domínio e não deve entrar no dicionário memory/dominio/fiscal-faturamento.md.
 * Valores válidos (verde|amarelo|vermelho) são validados na app.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_sefaz_status')) {
            return;
        }

        Schema::create('nfe_sefaz_status', function (Blueprint $table) {
            $table->char('uf', 2)->primary()
                ->comment('UF do autorizador (SP, SC, RS...) ou SVAN/SVRS. Chave natural — 1 linha por autorizador.');

            $table->string('status', 10)->default('verde')
                ->comment('verde=respondendo | amarelo=lento | vermelho=fora. String (não enum) por não ser vocabulário de domínio — ver docblock.');

            $table->timestamp('last_check_at')->nullable()
                ->comment('Último ping do SefazHealthCheckJob. NULL = nunca medido — NÃO confundir com "no ar" (fail-open é o erro que a lápide de 2026-07-29 cataloga).');

            $table->unsignedInteger('last_response_ms')->nullable()
                ->comment('Latência da última resposta. NULL quando o ping falhou — ausência de medição, não latência zero.');

            $table->unsignedInteger('consecutive_failures')->default(0)
                ->comment('Falhas seguidas. ADR TECH-0002: 3 => sugerir contingência (SUGERIR — a ativação é do tenant).');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_sefaz_status');
    }
};
