<?php

declare(strict_types=1);

namespace Tests\Feature\Calculo;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\RecurringBilling\Models\Invoice;
use Modules\RecurringBilling\Models\Plan;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Models\SubscriptionEvent;
use Modules\RecurringBilling\Services\AssinaturaCobrancaService;
use Modules\RecurringBilling\Services\AssinaturaService;
use Modules\RecurringBilling\Services\InvoiceGeneratorService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Onda 1.4 — Dente de cálculo aplicado ao RecurringBilling (cobrança recorrente).
 * @see memory/requisitos/_Governanca/programa-ondas/onda-1-sells/1.4-dente-calculo.md
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * O QUE É (E O QUE NÃO É) O "CÁLCULO" DO RECURRINGBILLING — verificado 2026-07-03
 * ─────────────────────────────────────────────────────────────────────────────
 * O briefing da task mencionava pró-rata, cupom/desconto recorrente e take rate.
 * VERIFICADO no código (grep em todo Modules/RecurringBilling/): NENHUM desses existe.
 * Não há proration, discount, coupon nem take_rate/GMV/split em Service, Model,
 * Controller ou migration. Inventar teste pra feature inexistente seria tautológico
 * (proibicoes.md §"Teste que deriva do CÓDIGO"). Então o "dente de cálculo" real do
 * RB reduz a TRÊS superfícies concretas — e é isso que este arquivo trava:
 *
 *   1. FIDELIDADE DE VALOR — o valor final da fatura = valor do plano, sem inflar.
 *      É o análogo do vetor num_uf (incidente 2026-06-05) no fluxo recorrente:
 *      InvoiceGeneratorService::run() copia plan.valor → invoice.valor. Um valor de
 *      milhar (1.234,56) tem que sobreviver ponta-a-ponta MESMO o corpo do
 *      SubscriptionEvent formatando pt-BR "R$ 1.234,56". "0 teste end-to-end do VALOR
 *      final da fatura" era o gap explícito.
 *
 *   2. AVANÇO DE CICLO (NoOverflow) — InvoiceGeneratorService::avancarCiclo() usa
 *      addMonth*NoOverflow pra preservar o anchor dia 31 (jan 31 + 1 mês = fev 28, NÃO
 *      transborda pra mar). O teste 3 do InvoiceGeneratorServiceTest só exercita
 *      dia-10/monthly (nunca transborda). O EDGE dia-31 + os ciclos quarterly/
 *      semiannual/yearly estavam SEM teste.
 *
 *   3. DIVERGÊNCIA DAS TRÊS IMPLEMENTAÇÕES DE "PRÓXIMO VENCIMENTO" — o análogo exato
 *      de getTotalPaid ≠ getTotalAmountPaid (§Alvos da Onda 1.4). Existem TRÊS cópias
 *      da mesma conta, com definições divergentes:
 *        A InvoiceGeneratorService::avancarCiclo         — enum EN (monthly...),  NoOverflow, default=+1mês
 *        B AssinaturaService::calcularProximoVencimento  — enum PT (mensal...),   Overflow,   default=NO-OP
 *        C AssinaturaCobrancaService::recalcularProximaCobranca — enum PT,        Overflow,   default=NO-OP
 *      E o storage tem vocabulário SPLIT: rb_plans.ciclo é enum EN; metadata['ciclo']
 *      (path de edição FIN-004) é PT. As três divergem no anchor dia-31 (NoOverflow vs
 *      Overflow) E no default silencioso (A avança, B/C ficam parados) — bug latente de
 *      re-cobrança presa se o vocabulário cruzar. O teste CARACTERIZA e nomeia a fonte
 *      de verdade (o JOB que fatura de verdade = A, NoOverflow); NÃO unifica.
 *
 * O QUE JÁ ESTAVA COBERTO (e este teste NÃO duplica):
 *   - InvoiceGeneratorService happy-path, idempotência, dry-run, lead-days, cross-tenant,
 *     evento, avanço monthly dia-10 → Modules/RecurringBilling/Tests/Feature/InvoiceGeneratorServiceTest.php
 *
 * ⛔ TEST-ONLY (REGRA MESTRE — memory/proibicoes.md §"CÁLCULO DE VALOR ou ESTOQUE"):
 *    este arquivo NÃO altera nenhum método de cálculo. UNIFICAR as três implementações
 *    de próximo-vencimento (ou trocar Overflow↔NoOverflow) é mudança de valor/data em
 *    prod → US separada sob REGRA MESTRE (dupla confirmação + tabela antes→depois +
 *    OK [W]). Este teste só trava o comportamento ATUAL de cada método (caracterização).
 *
 * Multi-tenant Tier 0 (ADR 0093) + biz=1 (ADR 0101): NUNCA biz=4 (ROTA LIVRE PROD).
 * Rodar no CT100/MySQL (proibicoes Tier 0 — nunca local nem Hostinger).
 */
class CalculoRecurringBillingTest extends TestCase
{
    use DatabaseTransactions;

    // =========================================================================
    // 1) FIDELIDADE DE VALOR — plan.valor → invoice.valor, sem inflar (end-to-end)
    // =========================================================================

    /**
     * Faixa de valores que atravessa o pipeline recorrente. Se algum dia o gerador
     * introduzisse um format→reparse pt-BR (o corpo do evento JÁ formata com
     * number_format), um valor de milhar poderia inflar. Aqui provamos que o valor
     * numérico da fatura é FIEL ao do plano (arredondado a 2 casas pela coluna decimal).
     *
     * @return array<string, array{0: float}>
     */
    public static function valoresPlanoProvider(): array
    {
        return [
            'centavo'            => [0.01],
            'noventa e nove'     => [99.90],
            'quebrado'           => [149.99],
            'antes do desconto'  => [227.90],
            'milhar (vetor)'     => [1234.56],
            'cinco digitos'      => [25000.00],
            'milhoes'            => [1234567.89],
        ];
    }

    #[Test]
    #[DataProvider('valoresPlanoProvider')]
    public function property_valor_do_plano_chega_fiel_na_fatura(float $valor): void
    {
        [$plan, $sub] = $this->novoPlanoComAssinatura($valor, 'monthly', '2026-07-10');

        // O tenant canônico (biz=1, clone-de-prod) pode ter outras assinaturas dogfood
        // vencidas — por isso NÃO ancoramos no agregado stats['generated'] (é por-business),
        // e sim na fatura da NOSSA subscription. Tudo dentro de DatabaseTransactions (rollback).
        (new InvoiceGeneratorService())->run(businessId: (int) $sub->business_id, date: '2026-07-10');

        $invoice = Invoice::query()->where('subscription_id', $sub->id)->first();
        $this->assertNotNull($invoice, "Sem invoice pro valor {$valor} — gerador não faturou a assinatura.");

        // decimal:2 arredonda pela coluna; comparamos contra o valor arredondado a 2 casas.
        $esperado = round($valor, 2);
        $this->assertEqualsWithDelta(
            $esperado,
            (float) $invoice->valor,
            0.005,
            "invoice.valor = {$invoice->valor} (esperado {$esperado}) — pipeline recorrente distorceu o valor do plano."
        );
    }

    /**
     * GOLDEN — o vetor de milhar ponta-a-ponta. plan.valor = 1234.56:
     *   - invoice.valor tem que ser 1234.56 (NÃO 123456, NÃO 1234560).
     *   - o corpo do SubscriptionEvent mostra pt-BR "R$ 1.234,56" (display), provando que
     *     o módulo PRODUZ string formatada a partir do valor — logo um reparse futuro
     *     inflaria. O teto de sanidade trava a inflação.
     */
    #[Test]
    public function golden_valor_milhar_sobrevive_ponta_a_ponta(): void
    {
        [$plan, $sub] = $this->novoPlanoComAssinatura(1234.56, 'monthly', '2026-07-10');

        (new InvoiceGeneratorService())->run(businessId: 1, date: '2026-07-10');

        $invoice = Invoice::query()->where('subscription_id', $sub->id)->first();
        $this->assertNotNull($invoice);

        $this->assertEqualsWithDelta(1234.56, (float) $invoice->valor, 0.0001, 'Valor de milhar inflou na fatura.');

        // Teto de sanidade: uma fatura de assinatura NUNCA pode virar 10^2× o plano.
        $this->assertLessThan(
            100000.0,
            (float) $invoice->valor,
            "invoice.valor {$invoice->valor} explodiu — vetor de inflação tipo num_uf no fluxo recorrente."
        );

        $event = SubscriptionEvent::query()->where('subscription_id', $sub->id)->first();
        $this->assertNotNull($event);
        $this->assertStringContainsString(
            'R$ 1.234,56',
            (string) $event->body,
            'O corpo do evento deveria exibir o valor formatado pt-BR "R$ 1.234,56".'
        );
    }

    // =========================================================================
    // 2) AVANÇO DE CICLO (NoOverflow) — o edge dia-31 + os 4 ciclos (A)
    // =========================================================================

    /**
     * Golden calendário (verdade externa, NÃO lida da implementação):
     *   fev/2026 tem 28 dias (2026 não é bissexto). NoOverflow "gruda" no último dia.
     *
     * @return array<string, array{0:string, 1:string, 2:string}>
     */
    public static function avancoCicloProvider(): array
    {
        return [
            'monthly dia-15'                 => ['2026-01-15', 'monthly',    '2026-02-15'],
            'monthly anchor-31 → fev-28'     => ['2026-01-31', 'monthly',    '2026-02-28'],
            'quarterly +3'                   => ['2026-01-15', 'quarterly',  '2026-04-15'],
            'semiannual +6'                  => ['2026-01-15', 'semiannual', '2026-07-15'],
            'yearly +1ano'                   => ['2026-01-15', 'yearly',     '2027-01-15'],
            'yearly bissexto→comum 29→28'    => ['2024-02-29', 'yearly',     '2025-02-28'],
            'custom = fallback monthly'      => ['2026-01-31', 'custom',     '2026-02-28'],
        ];
    }

    #[Test]
    #[DataProvider('avancoCicloProvider')]
    public function golden_avanco_de_ciclo_noOverflow(string $base, string $ciclo, string $esperado): void
    {
        $this->assertSame(
            $esperado,
            $this->avancarCiclo($base, $ciclo),
            "avancarCiclo({$base}, {$ciclo}) deveria dar {$esperado} (NoOverflow preserva anchor)."
        );
    }

    // =========================================================================
    // 3) CARACTERIZAÇÃO DA DIVERGÊNCIA — três "próximo vencimento" divergem (A≠B≠C)
    //    Análogo direto de getTotalPaid (líquido) ≠ getTotalAmountPaid (bruto).
    // =========================================================================

    /**
     * DIVERGÊNCIA #1 — anchor dia-31: NoOverflow (A, o JOB que fatura) vs Overflow (B, edição).
     *
     *   A avancarCiclo('2026-01-31','monthly')        → FICA em fevereiro (dia 28).
     *   B calcularProximoVencimento('2026-01-31','mensal') → TRANSBORDA pra março.
     *
     * FONTE DE VERDADE do anchor real é A (InvoiceGeneratorService — é ele que gera a
     * cobrança). O path de edição (B/C) faz Overflow e "pula" o mês do vencimento.
     * Unificar = mudança de data de cobrança em prod → US separada sob REGRA MESTRE.
     */
    #[Test]
    public function divergencia_anchor_31_noOverflow_vs_overflow(): void
    {
        $a = $this->avancarCiclo('2026-01-31', 'monthly');                 // NoOverflow
        $b = $this->calcularProximoVencimento('2026-01-31', 'mensal');     // Overflow

        // A caracterização de A: NoOverflow gruda no último dia de fevereiro.
        $this->assertSame('2026-02-28', $a, 'A (avancarCiclo) deveria grudar em fev-28 (NoOverflow).');
        $this->assertSame(2, Carbon::parse($a)->month, 'A tem que FICAR em fevereiro.');

        // A caracterização de B: Overflow empurra pra FORA de fevereiro (março).
        $this->assertSame(3, Carbon::parse($b)->month, 'B (calcularProximoVencimento) deveria transbordar pra março (Overflow).');

        // O discriminador: enquanto A e B divergirem, o teste tem poder de pegar a regressão.
        // Se convergirem (alguém alinhar Overflow↔NoOverflow), este assert quebra = RED consciente
        // → sinal pra abrir a US de unificação sob REGRA MESTRE.
        $this->assertNotSame(
            $a,
            $b,
            'A e B convergiram no anchor dia-31 — decisão de unificação NÃO passou pela REGRA MESTRE?'
        );
    }

    /**
     * DIVERGÊNCIA #2 — vocabulário cruzado + default silencioso (o bug latente perigoso).
     *
     * rb_plans.ciclo é enum EN ('monthly'); metadata['ciclo'] do path de edição é PT ('mensal').
     * Se o vocabulário cruzar:
     *   A avancarCiclo(base,'mensal')  → default TOLERANTE → +1 mês (avança).
     *   B calcularProximoVencimento(base,'monthly') → default NO-OP → data CONGELA.
     *   C recalcularProximaCobranca(base,'monthly') → default NO-OP → data CONGELA.
     *
     * B/C NÃO avançarem = re-cobrança presa (a fatura nunca "vira"). Caracteriza o risco;
     * a correção (unificar vocabulário/helper) é US separada sob REGRA MESTRE.
     */
    #[Test]
    public function divergencia_vocabulario_cruzado_default_silencioso(): void
    {
        $base = '2026-01-15';

        // A é tolerante ao termo PT desconhecido: cai no default = +1 mês NoOverflow.
        $this->assertSame('2026-02-15', $this->avancarCiclo($base, 'mensal'), 'A deveria avançar 1 mês no default.');

        // B e C, ao receberem o termo EN desconhecido, caem no default NO-OP → NÃO avançam.
        $this->assertSame($base, $this->calcularProximoVencimento($base, 'monthly'), 'B congela no default (não avança).');
        $this->assertSame($base, $this->recalcularProximaCobranca($base, 'monthly'), 'C congela no default (não avança).');
    }

    /**
     * DIVERGÊNCIA #3 — B e C se DIZEM "helper compartilhado" mas são DUPLICADOS.
     * (docblock de AssinaturaService: "Helper compartilhado com ...recalcularProximaCobranca".)
     * Enquanto forem duas cópias, este teste trava que estão em SINCRONIA hoje — se uma
     * derivar da outra amanhã, o teste pega. (A cura real é serem UMA função só — US separada.)
     *
     * @return array<string, array{0:string, 1:string}>
     */
    public static function bcSyncProvider(): array
    {
        return [
            'mensal dia-15'   => ['2026-01-15', 'mensal'],
            'mensal anchor-31' => ['2026-01-31', 'mensal'],
            'trimestral'      => ['2026-01-15', 'trimestral'],
            'semestral'       => ['2026-01-15', 'semestral'],
            'anual'           => ['2026-01-15', 'anual'],
            'termo EN (no-op)' => ['2026-01-15', 'monthly'],
        ];
    }

    #[Test]
    #[DataProvider('bcSyncProvider')]
    public function caracterizacao_B_e_C_ainda_sao_identicos(string $base, string $ciclo): void
    {
        $this->assertSame(
            $this->calcularProximoVencimento($base, $ciclo),
            $this->recalcularProximaCobranca($base, $ciclo),
            "B e C divergiram em ({$base}, {$ciclo}) — a duplicação 'compartilhada' driftou."
        );
    }

    // =========================================================================
    // 4) DISCRIMINADOR RED — prova que o golden de ciclo tem dente (sem mutar prod)
    // =========================================================================

    /**
     * TEST-ONLY não muta o código de prod pra provar o RED. Reproduzimos INLINE a versão
     * BUGADA (addMonth com Overflow, o que aconteceria se alguém removesse o "NoOverflow")
     * e travamos o contrato: no anchor dia-31 a versão atual (NoOverflow) FICA em fevereiro,
     * a bugada TRANSBORDA pra março. Enquanto divergirem, o golden da Seção 2 tem poder de
     * pegar a regressão. Se convergirem (avancarCiclo voltar a Overflow), a Seção 2 quebra = RED.
     */
    #[Test]
    public function discriminacao_versao_overflow_seria_red(): void
    {
        $anchor = '2026-01-31';

        $atual  = $this->avancarCiclo($anchor, 'monthly');                       // NoOverflow (fix vigente)
        $bugado = Carbon::parse($anchor)->addMonth()->toDateString();            // Overflow (regressão hipotética)

        $this->assertSame('2026-02-28', $atual, 'Sanidade: a versão atual gruda em fev-28 (NoOverflow).');
        $this->assertSame(3, Carbon::parse($bugado)->month, 'Sanidade do vetor: a versão Overflow transborda pra março.');

        // O discriminador: os dois caminhos DIVERGEM de mês. Enquanto divergirem, o golden
        // NoOverflow tem valor. Se convergirem, é regressão do anchor de cobrança.
        $this->assertNotSame(
            Carbon::parse($atual)->month,
            Carbon::parse($bugado)->month,
            'avancarCiclo convergiu com a versão Overflow — regressão do anchor dia-31.'
        );
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Cria Plan + Subscription ativa em biz=1 (ADR 0101) com um contact real seedado.
     * Skip-graceful (acionável) quando o seed mínimo do tenant canônico não rodou.
     *
     * @return array{0: Plan, 1: Subscription}
     */
    private function novoPlanoComAssinatura(float $valor, string $ciclo, string $nextDueDate): array
    {
        $tenant = $this->seededTenant(); // biz=1 canônico; markTestSkipped acionável se ausente.

        $contact = DB::table('contacts')->where('business_id', $tenant->id)->first();
        if (! $contact) {
            $this->markTestSkipped('Sem contact seedado no business canônico (FK rb_subscriptions.contact_id).');
        }

        $plan = Plan::create([
            'business_id' => $tenant->id,
            'name'        => "Plano dente-calc {$ciclo}",
            'slug'        => 'plano-dente-calc-'.uniqid(),
            'valor'       => $valor,
            'ciclo'       => $ciclo,
            'ativo'       => true,
        ]);

        $sub = Subscription::create([
            'business_id'         => $tenant->id,
            'plan_id'             => $plan->id,
            'contact_id'          => (int) $contact->id,
            'status'              => 'active',
            'start_date'          => $nextDueDate,
            'next_due_date'       => $nextDueDate,
            'billing_anchor_date' => $nextDueDate,
        ]);

        return [$plan, $sub];
    }

    /** A — InvoiceGeneratorService::avancarCiclo (privado, NoOverflow, enum EN). White-box characterization. */
    private function avancarCiclo(string $base, string $ciclo): string
    {
        $svc = new InvoiceGeneratorService();
        $m = new ReflectionMethod($svc, 'avancarCiclo');
        $m->setAccessible(true);

        return $m->invoke($svc, Carbon::parse($base), $ciclo);
    }

    /** B — AssinaturaService::calcularProximoVencimento (público, Overflow, enum PT). */
    private function calcularProximoVencimento(string $base, string $ciclo): string
    {
        return app(AssinaturaService::class)->calcularProximoVencimento($base, $ciclo);
    }

    /** C — AssinaturaCobrancaService::recalcularProximaCobranca (privado, Overflow, enum PT). */
    private function recalcularProximaCobranca(string $base, string $ciclo): string
    {
        $svc = app(AssinaturaCobrancaService::class);
        $m = new ReflectionMethod($svc, 'recalcularProximaCobranca');
        $m->setAccessible(true);

        return $m->invoke($svc, $base, $ciclo);
    }
}
