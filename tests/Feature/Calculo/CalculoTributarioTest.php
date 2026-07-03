<?php

declare(strict_types=1);

namespace Tests\Feature\Calculo;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Modules\NfeBrasil\Events\FiscalRuleCreated;
use Modules\NfeBrasil\Events\FiscalRuleDeleted;
use Modules\NfeBrasil\Events\FiscalRuleUpdated;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;
use Modules\NFSe\DTO\NfseEmissaoPayload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CAMADA FISCAL — Dente de cálculo do MOTOR TRIBUTÁRIO (a PROVA real).
 * @see memory/requisitos/_Governanca/programa-ondas/onda-1-sells/1.4-dente-calculo.md (o padrão do dente)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * A REGRA MESTRE (memory/proibicoes.md §"CÁLCULO DE VALOR ou ESTOQUE") vale igual pro
 * VALOR TRIBUTÁRIO: um imposto errado num centavo escala pra multa fiscal + NFe rejeitada
 * na SEFAZ. O motor tributário (ICMS/PIS/COFINS/IPI) e o cálculo de ISS da NFSe são o
 * coração fiscal compartilhado entre Fiscal, NfeBrasil e NFSe. Este dente converte o
 * controle MANUAL de cálculo em controle AUTOMÁTICO no arredondamento de centavo tributário
 * — a mesma classe de bug do `num_uf` (incidente 2026-06-05), agora no imposto.
 *
 * O QUE JÁ ESTAVA COBERTO (e este teste NÃO duplica — comparar-não-duplicar):
 *   - CASCADE de seleção da regra (Níveis 1→4 override→exata→padrão→default), CST/CSOSN,
 *     cache em memória, isolamento multi-tenant → Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php
 *   - Wiring SpedIcmsIpiGeneratorService ↔ motor (via MOCK) → Modules/Fiscal/Tests/Feature/SpedMotorTributarioIntegrationTest.php
 *   - `updateGroupTaxAmount` (app/Utils/TaxUtil.php) → dente de Financeiro (#3710)
 *
 * O QUE ESTE TESTE ADICIONA (ângulos INDEFESOS hoje — verificado 2026-07-03):
 *   1. Golden do ARREDONDAMENTO real do motor: todo teste existente usa números redondos
 *      (100 × 0,18 = 18,0) que NUNCA exercem o `fmt()` (round 2 casas). Aqui: base
 *      FRACIONÁRIA que produz 3ª casa → prova que arredonda ao centavo (12,34 × 0,18 =
 *      2,2212 → 2,22; NÃO 2,2212 e NÃO 22.212 no vetor num_uf).
 *   2. Property SEFAZ (faixa de valores): o valor do imposto SEMPRE tem ≤2 casas E nunca
 *      desvia da base×alíquota além de meio centavo (mata o bug de escala ×10^5 do num_uf).
 *   3. Golden do ISS (NfseEmissaoPayload::valorIss) — cálculo HOJE SEM NENHUM teste:
 *      valorServicos × aliquotaIss arredondado (333,33 × 0,05 = 16,6665 → 16,67).
 *   4. Caracterização do ISS RETIDO: `issRetido=true → valorIss()=0.0`. Trava o
 *      comportamento ATUAL; a fonte-de-verdade legal (LC 116/2003 + layout do município)
 *      fica pra US separada se precisar mudar.
 *   5. Discriminação RED: reproduz INLINE o strip-do-ponto (vetor num_uf) sobre o valor
 *      tributário e prova que o motor atual NÃO infla — o green tem poder de pegar a regressão.
 *
 * CONTRATO (âncoras externas — não derivadas do código, anti-tautologia):
 *   - Arredondamento monetário em 2 casas = layout NFe/NFS-e SEFAZ (campos vXxx com 2 decimais).
 *   - ICMS = base × alíquota (Lei Kandir / RICMS); ISS = base × alíquota (LC 116/2003 Art. 7º).
 *   - ISS retido na fonte: responsabilidade do tomador (LC 116/2003 Art. 6º) — comportamento
 *     atual do destaque = 0,0 (caracterizado, não decidido aqui).
 *
 * ⛔ TEST-ONLY: este arquivo NÃO altera nenhum método de cálculo tributário. Qualquer
 *    mudança em `MotorTributarioService::fmt`/`aplicarRegra` ou em `NfseEmissaoPayload::valorIss`
 *    é alteração de VALOR em prod → REGRA MESTRE (dupla confirmação + antes→depois + OK [W]),
 *    US separada, nunca carona no PR do teste.
 */
class CalculoTributarioTest extends TestCase
{
    use DatabaseTransactions;

    private MotorTributarioService $motor;

    protected function setUp(): void
    {
        parent::setUp();

        // NfeFiscalRule::create dispara evento de sync (SyncFiscalRuleToTaxRate, ADR ARQ-0005).
        // Isolamos o side effect — o dente mede só o cálculo, não o listener (que tem teste próprio).
        Event::fake([FiscalRuleCreated::class, FiscalRuleUpdated::class, FiscalRuleDeleted::class]);

        $this->motor = new MotorTributarioService;
    }

    // =========================================================================
    // 1) GOLDEN — o motor arredonda o ICMS ao centavo (base FRACIONÁRIA)
    // =========================================================================

    /**
     * Base × alíquota que cai em 3ª/4ª casa → tem que virar 2 casas SEFAZ.
     * Números conferidos À MÃO (2º caminho independente, espírito REGRA MESTRE):
     *
     *   12,34 × 0,18 = 2,2212        → arredonda pra BAIXO → 2,22
     *   199,99 × 0,18 = 35,9982      → arredonda pra CIMA  → 36,00
     *   77,77 × 0,07 = 5,4439        → arredonda pra BAIXO → 5,44
     *   250,05 × 0,12 = 30,006       → arredonda pra BAIXO → 30,01
     *
     * Se `fmt()` truncasse/deixasse a 3ª casa, o valor bruto (2,2212) difere do
     * esperado (2,22) em 0,0012 — acima do delta 0,0001 → o teste FALHA (RED).
     *
     * @return array<string, array{0: float, 1: float, 2: float}>
     */
    public static function icmsFracionarioProvider(): array
    {
        return [
            'arredonda pra baixo (2,2212→2,22)'  => [12.34, 0.18, 2.22],
            'arredonda pra cima (35,9982→36,00)' => [199.99, 0.18, 36.00],
            'baixo com 7% (5,4439→5,44)'         => [77.77, 0.07, 5.44],
            'cima na 3a casa (30,006→30,01)'     => [250.05, 0.12, 30.01],
        ];
    }

    #[Test]
    #[DataProvider('icmsFracionarioProvider')]
    public function motor_arredonda_icms_ao_centavo(float $valor, float $aliquota, float $esperado): void
    {
        // NCM sintético (não colide com regra real de biz=1 — DatabaseTransactions reverte).
        $ncm = '99887766';
        NfeFiscalRule::create([
            'business_id'     => 1,
            'ncm'             => $ncm,
            'uf_origem'       => 'SP',
            'uf_destino'      => null, // Nível 3 (regra padrão NCM)
            'cfop'            => '5102',
            'csosn'           => '102',
            'aliquota_icms'   => $aliquota,
            'aliquota_pis'    => 0.0,
            'aliquota_cofins' => 0.0,
            'aliquota_ipi'    => 0.0,
        ]);

        $tributo = $this->motor->calcular(
            new ProdutoFiscalContext(ncm: $ncm, valor: $valor),
            businessId: 1, ufOrigem: 'SP', ufDestino: 'SP',
        );

        $this->assertSame(3, $tributo->nivel_usado, 'Deveria cair no Nível 3 (regra padrão NCM).');
        $this->assertEqualsWithDelta(
            $esperado,
            $tributo->valor_icms,
            0.0001,
            "ICMS de {$valor} × {$aliquota} = {$tributo->valor_icms} (esperado {$esperado}); " .
            'não arredondou ao centavo (bug de 3ª casa) ou inflou (vetor num_uf).'
        );
    }

    // =========================================================================
    // 2) PROPERTY — contrato SEFAZ sobre uma FAIXA de valores
    // =========================================================================

    /**
     * Faixa determinística (base, alíquota). Pra cada uma, o valor do imposto TEM que:
     *   (a) ter no máximo 2 casas decimais  — layout NFe SEFAZ (campo monetário);
     *   (b) não desviar de base×alíquota além de meio centavo — ICMS = base × alíquota
     *       (Lei Kandir) + 1 passo de arredondamento.
     * O bug de escala do `num_uf` (×10^5) viola (b) grosseiramente → property pega.
     *
     * @return array<string, array{0: float, 1: float}>
     */
    public static function faixaBaseAliquotaProvider(): array
    {
        return [
            'centavos baixos'    => [0.05, 0.18],
            'dez e pouco'        => [12.34, 0.07],
            'cem quebrado'       => [147.77, 0.12],
            'base do incidente'  => [227.90, 0.18],
            'milhar'             => [1329.05, 0.04],
            'milhar cheio'       => [1234.56, 0.185],
            'dez mil'            => [25000.07, 0.18],
            'quase um milhao'    => [999999.99, 0.18],
        ];
    }

    #[Test]
    #[DataProvider('faixaBaseAliquotaProvider')]
    public function property_imposto_tem_2_casas_e_nao_desvia_da_base_vezes_aliquota(float $base, float $aliquota): void
    {
        $ncm = '99887766';
        NfeFiscalRule::create([
            'business_id'     => 1,
            'ncm'             => $ncm,
            'uf_origem'       => 'SP',
            'uf_destino'      => null,
            'cfop'            => '5102',
            'csosn'           => '102',
            'aliquota_icms'   => $aliquota,
            'aliquota_pis'    => 0.0,
            'aliquota_cofins' => 0.0,
            'aliquota_ipi'    => 0.0,
        ]);

        $vi = $this->motor->calcular(
            new ProdutoFiscalContext(ncm: $ncm, valor: $base),
            businessId: 1, ufOrigem: 'SP', ufDestino: 'SP',
        )->valor_icms;

        // (a) 2 casas SEFAZ: valor×100 é (praticamente) inteiro.
        $centavos = $vi * 100;
        $this->assertEqualsWithDelta(
            round($centavos),
            $centavos,
            1e-6,
            "valor_icms {$vi} tem mais de 2 casas decimais — viola o campo monetário do layout NFe SEFAZ."
        );

        // (b) não desvia da base×alíquota além de meio centavo (mata o bug de escala num_uf).
        $this->assertEqualsWithDelta(
            $base * $aliquota,
            $vi,
            0.005,
            "valor_icms {$vi} desviou de base×alíquota (" . ($base * $aliquota) . ') além de meio centavo — ' .
            'arredondamento errado ou inflação de escala (vetor num_uf ×10^5).'
        );
    }

    // =========================================================================
    // 3) GOLDEN — ISS da NFSe (NfseEmissaoPayload::valorIss) — 0 teste hoje
    // =========================================================================

    /**
     * Números conferidos à mão:
     *   333,33 × 0,05 = 16,6665  → arredonda pra CIMA  → 16,67
     *   100,10 × 0,02 = 2,002    → arredonda pra BAIXO → 2,00
     *   500,00 × 0,05 = 25,00    → exato               → 25,00
     *
     * @return array<string, array{0: float, 1: float, 2: float}>
     */
    public static function issProvider(): array
    {
        return [
            'cima (16,6665→16,67)'   => [333.33, 0.05, 16.67],
            'baixo (2,002→2,00)'     => [100.10, 0.02, 2.00],
            'exato (25,00)'          => [500.00, 0.05, 25.00],
        ];
    }

    #[Test]
    #[DataProvider('issProvider')]
    public function iss_arredonda_ao_centavo(float $valorServicos, float $aliquotaIss, float $esperado): void
    {
        $payload = $this->payloadNfse($valorServicos, $aliquotaIss, issRetido: false);

        $this->assertEqualsWithDelta(
            $esperado,
            $payload->valorIss(),
            0.0001,
            "ISS de {$valorServicos} × {$aliquotaIss} = {$payload->valorIss()} (esperado {$esperado}); não arredondou ao centavo."
        );
    }

    // =========================================================================
    // 4) CARACTERIZAÇÃO — ISS retido zera o destaque (comportamento ATUAL)
    // =========================================================================

    /**
     * `valorIss()` HOJE retorna 0,0 quando `issRetido=true`, INDEPENDENTE de valor/alíquota.
     * Trava esse contrato: quem cadastrou "retido" espera destaque zerado (tomador recolhe,
     * LC 116/2003 Art. 6º). Se alguém "corrigir" pra calcular mesmo retido, este teste pega.
     * UNIFICAR/mudar essa regra = mudança de valor em prod → US separada sob REGRA MESTRE.
     */
    #[Test]
    public function iss_retido_zera_destaque_independente_de_valor(): void
    {
        // Mesmo com base e alíquota que dariam 25,00, o retido zera.
        $retido = $this->payloadNfse(500.00, 0.05, issRetido: true);
        $this->assertSame(
            0.0,
            $retido->valorIss(),
            'ISS retido deve zerar o destaque (tomador recolhe). Mudou? → REGRA MESTRE, US separada.'
        );

        // Controle: o MESMO valor SEM retenção calcula normal (prova que o 0,0 veio do retido, não de base zero).
        $naoRetido = $this->payloadNfse(500.00, 0.05, issRetido: false);
        $this->assertEqualsWithDelta(25.00, $naoRetido->valorIss(), 0.0001, 'Sem retenção deve calcular 25,00.');
    }

    // =========================================================================
    // 5) DISCRIMINAÇÃO RED — o strip-do-ponto (vetor num_uf) seria pego
    // =========================================================================

    /**
     * TEST-ONLY não pode mutar o código pra provar o RED. Reproduzimos INLINE a classe de
     * bug do incidente 2026-06-05 (tratar ponto decimal como separador de milhar e strippar)
     * aplicada a um valor tributário, e travamos que o motor ATUAL não converge com ela.
     *
     *   ICMS correto de 12,34 × 0,18 = 2,22
     *   Se o valor "2.2212" fosse re-parseado strippando o ".", viraria 22212 (×10^4).
     *   Enquanto os dois divergirem, o golden #1 tem poder de pegar a regressão.
     */
    #[Test]
    public function discriminacao_strip_do_ponto_no_valor_tributario_seria_red(): void
    {
        $ncm = '99887766';
        NfeFiscalRule::create([
            'business_id'     => 1,
            'ncm'             => $ncm,
            'uf_origem'       => 'SP',
            'uf_destino'      => null,
            'cfop'            => '5102',
            'csosn'           => '102',
            'aliquota_icms'   => 0.18,
            'aliquota_pis'    => 0.0,
            'aliquota_cofins' => 0.0,
            'aliquota_ipi'    => 0.0,
        ]);

        $viAtual = $this->motor->calcular(
            new ProdutoFiscalContext(ncm: $ncm, valor: 12.34),
            businessId: 1, ufOrigem: 'SP', ufDestino: 'SP',
        )->valor_icms;

        // Como a lógica BUGADA trataria a string do valor bruto (ponto = milhar, strip).
        $valorBrutoStr = '2.2212';
        $bugado = (float) str_replace('.', '', $valorBrutoStr); // 22212.0

        $this->assertEqualsWithDelta(2.22, $viAtual, 0.0001, 'O motor atual arredonda certo (2,22).');
        $this->assertEqualsWithDelta(22212.0, $bugado, 0.001, 'Sanidade do vetor: o strip inflava mesmo.');

        // O discriminador: enquanto divergirem em ordens de grandeza, o green tem valor.
        $this->assertGreaterThan(
            1000.0,
            abs($bugado - $viAtual),
            'valor tributário convergiu com a versão que strippa o ponto — regressão da classe num_uf.'
        );
    }

    // ---------------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------------

    private function payloadNfse(float $valorServicos, float $aliquotaIss, bool $issRetido): NfseEmissaoPayload
    {
        return new NfseEmissaoPayload(
            businessId:     1,
            rpsNumero:      'RPS-1',
            competencia:    Carbon::create(2026, 7, 1),
            tomadorNome:    'Tomador Teste',
            tomadorCnpj:    null,
            tomadorCpf:     null,
            tomadorEmail:   null,
            descricao:      'Servico de teste',
            lc116Codigo:    '01.01',
            valorServicos:  $valorServicos,
            aliquotaIss:    $aliquotaIss,
            issRetido:      $issRetido,
        );
    }
}
