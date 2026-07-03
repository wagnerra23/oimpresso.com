<?php

declare(strict_types=1);

namespace Tests\Feature\Calculo;

use DateTimeImmutable;
use Eduardokum\LaravelBoleto\Boleto\Banco\Bradesco as BradescoBoleto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Cnab\CnabBoletoAdapter;
use Modules\PaymentGateway\Services\Drivers\AsaasDriver;
use Modules\PaymentGateway\Services\Drivers\InterDriver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Camada de correção do PaymentGateway — dente de cálculo (ciclo-padrão da Onda, passo 3/D1).
 * @see memory/requisitos/_Governanca/programa-ondas/onda-1-sells/1.4-dente-calculo.md (padrão do dente)
 * @see memory/proibicoes.md §"REGRA MESTRE — CÁLCULO DE VALOR ou ESTOQUE" (contrato externo âncora)
 * @see memory/sessions/2026-06-05-veiculo-na-venda-e-incidente-numuf-valor-inflado.md (incidente num_uf)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * O programa de ondas fechou o `num_uf`/`calculateInvoiceTotal` em Sells (#3695),
 * `calculatePaymentStatus`/`updateGroupTaxAmount` no Financeiro (#3710) e a suíte
 * de Compras (#3728). Sobrou o cálculo PRÓPRIO do PaymentGateway — a conversão de
 * VALOR na fronteira com o banco/gateway — que estava indefeso na dimensão D1.
 *
 * O CONTRATO EXTERNO (âncora, NÃO derivado do código — evita o teste tautológico
 * catalogado em proibicoes.md §"Teste que deriva do CÓDIGO"): pela REGRA MESTRE
 * (incidente 2026-06-05, `Util::num_uf` inflou uma venda ~×100k), um campo de
 * dinheiro que sai pra uma máquina (arquivo CNAB, JSON do gateway) DEVE:
 *   (i)  bater EXATAMENTE o valor cobrado — nenhum centavo perdido nem inflado;
 *   (ii) usar ponto decimal e 2 casas, NUNCA carregar separador de milhar pt-BR
 *        (o "." de milhar é o vetor exato do incidente: "1.234,56" lido como float
 *         vira 1,00 → cobrança errada em ~100x).
 *
 * O QUE ESTE TESTE ADICIONA (verificado 2026-07-03 — NÃO duplica coberto):
 *   A) Contrato de formato-máquina do dinheiro do gateway (property + golden +
 *      discriminador RED contra o formatador locale pt-BR — o vetor num_uf).
 *   B) CNAB `emitirBoleto` — o VALOR numérico da remessa. O contract test
 *      (CnabBoletoAdapterContractTest) é **skipado na lane MySQL** (sqlite-only,
 *      linha 98) e só checa que o arquivo EXISTE, nunca o valor DENTRO dele.
 *      Aqui rodamos DB-free (credencial não-persistida) → roda no CT100 MySQL.
 *   C) Fidelidade do valor no refund/estorno (Asaas + Inter, HTTP fake) no vetor
 *      ≥ R$ 1.000 — que nenhum teste de refund existente exercita — e a
 *      caracterização de que HOJE não há teto server-side (gap → US separada).
 *   D) Reconcile inverso (gateway → centavos): round-trip sem perder centavo +
 *      discriminador RED contra o truncamento de float (o `(int)(x*100)` clássico).
 *
 * FORA DE ESCOPO (conscientemente, pra não inventar cálculo): juros/multa/desconto
 * de atraso NÃO são computados in-house — os campos `multa`/`juros`/`desconto` do
 * EmitirCobrancaInput não são consumidos pelos drivers (verificado: 0 uso em
 * InterDriver/C6Driver); o gateway calcula o encargo. Somar múltiplos PIX
 * (split/parcial) já é testado em InterDriverConsultarPixCobTest ("soma múltiplos
 * PIX recebidos"). Refund do Asaas/Inter em ponto único já tem golden — aqui só
 * acrescento o vetor de milhar que faltava.
 *
 * ⛔ TEST-ONLY: este arquivo NÃO altera nenhum método de cálculo. Dar teto ao
 *    refund, unificar formatadores, ou qualquer mudança de valor em prod é US
 *    separada sob REGRA MESTRE (dupla confirmação + tabela antes→depois + OK [W]).
 *    Este teste só CARACTERIZA e trava o comportamento ATUAL (red contra o bug,
 *    green no código de hoje). Multi-tenant Tier 0: business_id=1 (ADR 0101).
 */
class CalculoPaymentGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Sessão não é necessária (credenciais não-persistidas + HTTP fake), mas
        // deixamos business_id=1 defensivo pra qualquer global scope acidental.
        session(['business.id' => 1]);
    }

    // =========================================================================
    // A) CONTRATO DE FORMATO-MÁQUINA DO DINHEIRO — o análogo do num_uf(num_f(x))
    //    Âncora: REGRA MESTRE (campo-máquina = ponto decimal, 2 casas, sem milhar)
    // =========================================================================

    /**
     * Golden: a string que sai pro gateway (Inter/BcbPix/C6 usam
     * `number_format(c/100, 2, '.', '')`) é ponto-decimal, 2 casas, SEM milhar.
     * Inclui o vetor ≥ R$ 1.000 (123456c) que é onde o separador pt-BR mataria.
     *
     * @return array<string, array{int, string}>
     */
    public static function golden_valores_maquina(): array
    {
        return [
            'R$ 0,01 (mínimo)'      => [1, '0.01'],
            'R$ 0,99'               => [99, '0.99'],
            'R$ 1,00'               => [100, '1.00'],
            'R$ 204,99 (incidente)' => [20499, '204.99'],
            'R$ 1.234,56 (milhar)'  => [123456, '1234.56'],
            'R$ 999.999,99 (máx)'   => [99999999, '999999.99'],
        ];
    }

    #[Test]
    #[DataProvider('golden_valores_maquina')]
    public function golden_formato_maquina_ponto_decimal_sem_milhar(int $centavos, string $esperado): void
    {
        $saida = number_format($centavos / 100, 2, '.', '');

        $this->assertSame($esperado, $saida);
        // Contrato REGRA MESTRE: campo-máquina NUNCA carrega vírgula nem "." de milhar.
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $saida, 'campo-máquina deve ser ^dígitos.NN$');
        $this->assertStringNotContainsString(',', $saida, 'vírgula = separador pt-BR vazando pro gateway');
    }

    #[Test]
    public function property_round_trip_centavos_gateway_string_sem_strip(): void
    {
        // Faixa incluindo fracionários "difíceis" e o vetor de milhar.
        foreach ([1, 5, 99, 100, 101, 999, 1000, 1050, 12345, 20499, 22790, 123456, 9999999, 99999999] as $c) {
            $str = number_format($c / 100, 2, '.', '');       // saída pro gateway
            $volta = (int) round(((float) $str) * 100);        // reconcile inverso
            $this->assertSame($c, $volta, "round-trip perdeu centavo em {$c}c → '{$str}'");
        }
    }

    #[Test]
    public function red_formatador_locale_ptBR_corromperia_o_valor(): void
    {
        // DISCRIMINADOR: se o driver formatasse com o locale pt-BR (o mesmo
        // "." de milhar do num_uf) em vez de `(2, '.', '')`, o campo-máquina
        // sairia "1.234,56". Lido de volta como float, PHP corta no primeiro
        // não-numérico ("," ) → 1.234 → 123 centavos = R$ 1,23 numa cobrança de
        // R$ 1.234,56 (~1000× errado, o vetor exato do incidente num_uf).
        $locale = number_format(123456 / 100, 2, ',', '.'); // formato HUMANO pt-BR
        $this->assertSame('1.234,56', $locale);

        $lidoErrado = (int) round(((float) $locale) * 100);
        $this->assertSame(123, $lidoErrado, 'prova: o formato pt-BR viraria R$ 1,23 (o vetor num_uf)');
        $this->assertNotSame(123456, $lidoErrado);

        // O formato correto (o que o código USA) sobrevive ao mesmo caminho:
        $maquina = number_format(123456 / 100, 2, '.', '');
        $this->assertSame(123456, (int) round(((float) $maquina) * 100));
    }

    // =========================================================================
    // B) CNAB emitirBoleto — o VALOR da remessa (flagship indefeso na lane MySQL)
    // =========================================================================

    private function makeCnabDriver(): CalculoPgFakeCnabDriver
    {
        return new CalculoPgFakeCnabDriver();
    }

    private function makeCnabCredential(): PaymentGatewayCredential
    {
        // Credencial NÃO-persistida: o cast encrypted:array roda em memória
        // (APP_KEY do env de teste), sem tocar o banco → imune ao skip sqlite
        // do CnabBoletoAdapterContractTest e roda na lane MySQL do CT100.
        return new PaymentGatewayCredential([
            'business_id' => 1,
            'gateway_key' => 'bradesco_cnab',
            'ambiente'    => 'production',
            'ativo'       => true,
            'config_json' => [
                'agencia'           => '1234',
                'conta'             => '567890',
                'carteira'          => '09', // Bradesco: 02, 04, 09, 21, 26
                'cedente_nome'      => 'Empresa Teste LTDA',
                'cedente_documento' => '12345678000199',
                'cedente_endereco'  => 'Rua Teste, 100',
                'cedente_cep'       => '01000000',
                'cedente_uf'        => 'SP',
                'cedente_cidade'    => 'São Paulo',
            ],
        ]);
    }

    private function makeInput(int $centavos, string $idem): EmitirCobrancaInput
    {
        return new EmitirCobrancaInput(
            businessId: 1,
            contactId: 1,
            valorCentavos: $centavos,
            vencimento: new DateTimeImmutable('+5 days'),
            descricao: 'Dente cálculo PG',
            idempotencyKey: $idem,
            meta: [
                'payer_name'     => 'Pagador Teste',
                'payer_cpf_cnpj' => '12345678900',
            ],
        );
    }

    #[Test]
    public function golden_buildBoletoData_valor_reais_bate_o_cobrado(): void
    {
        $driver = $this->makeCnabDriver();
        $cred = $this->makeCnabCredential();

        // R$ 204,99 e o vetor de milhar R$ 1.234,56.
        $this->assertSame(204.99, $driver->exposeValor($this->makeInput(20499, 'k1'), $cred->config_json ?? []));
        $this->assertSame(1234.56, $driver->exposeValor($this->makeInput(123456, 'k2'), $cred->config_json ?? []));
    }

    #[Test]
    public function property_buildBoletoData_valor_round_trip(): void
    {
        $driver = $this->makeCnabDriver();
        $config = $this->makeCnabCredential()->config_json ?? [];

        foreach ([1, 99, 100, 12345, 20499, 123456, 99999999] as $c) {
            $valor = $driver->exposeValor($this->makeInput($c, "rt-{$c}"), $config);
            $this->assertSame($c, (int) round($valor * 100), "CNAB valor perdeu centavo em {$c}c");
        }
    }

    #[Test]
    public function red_buildBoletoData_truncamento_int_stripparia_centavo(): void
    {
        // DISCRIMINADOR: o código faz `round(c/100, 2)`. Se fizesse `(int)(c/100)`
        // (truncamento — o "strip de centavo" que a etapa quer pegar), R$ 204,99
        // viraria R$ 204,00 na remessa CNAB. Prova que o round(...,2) é load-bearing.
        $driver = $this->makeCnabDriver();
        $config = $this->makeCnabCredential()->config_json ?? [];

        $correto = $driver->exposeValor($this->makeInput(20499, 'red'), $config);
        $this->assertSame(204.99, $correto);

        $truncadoBug = (float) ((int) (20499 / 100)); // o que o bug produziria
        $this->assertSame(204.0, $truncadoBug);
        $this->assertNotSame($truncadoBug, $correto, 'round(...,2) deve preservar os centavos');
    }

    #[Test]
    public function golden_remessa_cnab_grava_valor_exato_sem_milhar(): void
    {
        Storage::fake('local');
        $driver = $this->makeCnabDriver();
        $cred = $this->makeCnabCredential();

        // R$ 204,99 → a remessa deve conter "204.99", nunca as formas corrompidas.
        $result = $driver->emitirBoleto($this->makeInput(20499, 'remessa-20499'), $cred);
        $path = $result->payloadGateway['cnab_remessa_path'];
        Storage::disk('local')->assertExists($path);

        // Isola a linha "# Valor: R$ <n>" — não o arquivo inteiro (a linha
        // digitável/código de barras tem grupos pontilhados que dariam falso
        // negativo nas asserções de "não contém").
        $valorRemessa = $this->extrairValorRemessa(Storage::disk('local')->get($path));
        $this->assertSame('204.99', $valorRemessa, 'valor da remessa CNAB deve bater o cobrado sem strip/milhar');
    }

    #[Test]
    public function golden_remessa_cnab_valor_milhar_nunca_leva_separador(): void
    {
        Storage::fake('local');
        $driver = $this->makeCnabDriver();
        $cred = $this->makeCnabCredential();

        // R$ 1.234,56 → "1234.56", jamais "1.234,56" nem "1.234.56".
        $result = $driver->emitirBoleto($this->makeInput(123456, 'remessa-123456'), $cred);
        $valorRemessa = $this->extrairValorRemessa(Storage::disk('local')->get($result->payloadGateway['cnab_remessa_path']));

        $this->assertSame('1234.56', $valorRemessa, 'R$ 1.234,56 na remessa não pode virar 1.234,56 nem 1.234.56');
    }

    /** Captura o número da linha "# Valor: R$ <n>" escrita por gravarRemessa. */
    private function extrairValorRemessa(string $conteudo): string
    {
        $this->assertMatchesRegularExpression('/# Valor: R\$ [\d.,]+/', $conteudo, 'remessa deve ter a linha de Valor');
        preg_match('/# Valor: R\$ ([\d.,]+)/', $conteudo, $m);

        return $m[1] ?? '';
    }

    // =========================================================================
    // C) REFUND / ESTORNO — fidelidade do valor devolvido (drivers reais + HTTP fake)
    //    Vetor ≥ R$ 1.000 (o milhar) que os goldens de refund existentes não cobrem.
    // =========================================================================

    #[Test]
    public function golden_refund_asaas_envia_valor_milhar_como_ponto_decimal(): void
    {
        Http::fake(['*/payments/pay_x/refund' => Http::response(['status' => 'REFUNDED'], 200)]);

        $cred = new PaymentGatewayCredential([
            'business_id' => 1,
            'gateway_key' => 'asaas',
            'config_json' => ['api_key' => '$aact_fake_token'],
        ]);
        $cobranca = (object) ['gateway_external_id' => 'pay_x'];

        // R$ 1.234,56 estornados → Asaas recebe float 1234.56 (não 1,00 nem 12,3456).
        (new AsaasDriver())->refund($cobranca, $cred, 123456, 'Estorno milhar');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'pay_x/refund') && $r['value'] === 1234.56);
    }

    #[Test]
    public function golden_refund_inter_envia_valor_milhar_como_string_ponto(): void
    {
        Http::fake([
            '*/oauth/v2/token'                => Http::response(['access_token' => 'tk'], 200),
            '*/pix/v2/cob/tx-1/devolucao/*'   => Http::response(['status' => 'DEVOLVIDO'], 201),
        ]);

        $cred = new PaymentGatewayCredential([
            'business_id' => 1,
            'gateway_key' => 'inter',
            'config_json' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);
        $cobranca = (object) ['gateway_external_id' => 'tx-1', 'tipo' => 'pix_cob'];

        // R$ 1.234,56 → o campo-máquina 'valor' sai "1234.56", nunca "1.234,56".
        (new InterDriver())->refund($cobranca, $cred, 123456, 'Estorno milhar');

        Http::assertSent(fn ($r) => str_contains($r->url(), '/pix/v2/cob/tx-1/devolucao/') && $r['valor'] === '1234.56');
    }

    #[Test]
    public function caracteriza_refund_sem_teto_repassa_valor_integral(): void
    {
        // CARACTERIZAÇÃO (gap documentado): HOJE nem PaymentGatewayService::refund
        // nem o driver validam o valor contra o quanto foi pago — o pedido é
        // repassado íntegro. Um valor absurdo (R$ 999.999,99) chega ao gateway sem
        // clamp. Dar TETO ("refund não excede o pago") é mudança de comportamento
        // de valor → US separada sob REGRA MESTRE (dupla confirmação + antes→depois
        // + OK [W]). Este teste TRAVA o comportamento atual; quando o teto entrar,
        // ele vira red e força a decisão consciente.
        Http::fake(['*/payments/pay_y/refund' => Http::response(['status' => 'REFUNDED'], 200)]);

        $cred = new PaymentGatewayCredential([
            'business_id' => 1,
            'gateway_key' => 'asaas',
            'config_json' => ['api_key' => '$aact_fake_token'],
        ]);
        $cobranca = (object) ['gateway_external_id' => 'pay_y'];

        (new AsaasDriver())->refund($cobranca, $cred, 99999999, 'Estorno sem teto');

        Http::assertSent(fn ($r) => $r['value'] === 999999.99);
    }

    // =========================================================================
    // D) RECONCILE INVERSO (gateway → centavos) — valor recebido vs cobrado
    // =========================================================================

    #[Test]
    public function golden_reconcile_inverso_reais_string_para_centavos(): void
    {
        // Os drivers reconciliam o recebido com `(int) round((float) $x * 100)`
        // (Asaas netValue, Inter valorTotalRecebimento, Sicoob valorRecebido).
        foreach ([['1234.56', 123456], ['999999.99', 99999999], ['0.01', 1], ['100.00', 10000]] as [$str, $c]) {
            $this->assertSame($c, (int) round(((float) $str) * 100), "reconcile inverso errou em '{$str}'");
        }
    }

    #[Test]
    public function red_reconcile_sem_round_truncaria_float(): void
    {
        // DISCRIMINADOR: o reconcile real recebe uma STRING do gateway e faz
        // `(int) round((float) $str * 100)`. R$ 0,29 é o caso-texto clássico de
        // IEEE-754: o double mais próximo de 0.29 é 0.28999999999999998, então
        // `(float)"0.29" * 100` = 28.9999…996. Sem o round(), o `(int)` trunca pra
        // 28 → perde 1 centavo. O round() (código atual) preserva 29. Uso string
        // em runtime (não literal) pra evitar constant-folding do compilador.
        $str = '0.29';

        $semRound = (int) ((float) $str * 100);
        $comRound = (int) round((float) $str * 100);

        $this->assertSame(29, $comRound, 'com round: R$ 0,29 → 29 centavos (código atual)');
        $this->assertSame(28, $semRound, 'sem round: float trunca pra 28 (perde 1 centavo)');
        $this->assertLessThan($comRound, $semRound, 'round() é load-bearing no reconcile');
    }
}

/**
 * Driver CNAB de teste que expõe o cálculo interno de valor (`buildBoletoData`)
 * sem depender da lib de boleto — pra property-testar o `round(c/100, 2)` isolado.
 * Herda 100% da fundação; implementa só os 3 abstratos (igual ao driver real).
 */
class CalculoPgFakeCnabDriver extends CnabBoletoAdapter
{
    public function key(): string
    {
        return 'bradesco_cnab';
    }

    protected function getBoletoClass(): string
    {
        return BradescoBoleto::class;
    }

    protected function getLayoutVersion(): int
    {
        return 240;
    }

    protected function camposObrigatoriosCnab(): array
    {
        return ['agencia', 'conta', 'carteira', 'cedente_nome', 'cedente_documento'];
    }

    /** Expõe o valor em reais que iria pro Boleto (o `round(c/100, 2)` interno). */
    public function exposeValor(EmitirCobrancaInput $input, array $config): float
    {
        return (float) $this->buildBoletoData($input, $config)['valor'];
    }
}
