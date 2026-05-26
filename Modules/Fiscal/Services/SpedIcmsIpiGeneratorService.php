<?php

declare(strict_types=1);

namespace Modules\Fiscal\Services;

use App\Util\OtelHelper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\NfeBrasil\Exceptions\NcmObrigatorioException;
use Modules\NfeBrasil\Exceptions\TributacaoNaoConfiguradaException;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;
use Modules\NfeBrasil\Services\Tributacao\TributoCalculado;
use RuntimeException;

/**
 * US-FISCAL-016 / US-FISCAL-017 / US-FISCAL-020 — Gerador SPED Fiscal EFD-ICMS/IPI.
 *
 * Gera TXT EFD-ICMS/IPI conforme Guia Prático EFD-ICMS/IPI v3.1.1 (CONFAZ).
 *
 * Escopo entregue:
 *  - PR #8 (Wave): Blocos 0 + C + 9 — 16 registros (MVP saídas)
 *  - PR #9 (Wave): Bloco E (apuração ICMS) + Bloco H (esqueleto) — +6 registros
 *  - GAP-FISCAL-003 (Onda CONSOLIDAR 2026-05-25): integração MotorTributarioService
 *    elimina 6 hardcodes Tier-0 (NCM/CST/CFOP/ALIQ/COD_MUN/COD_PART). Hardcodes
 *    funcionavam ACIDENTALMENTE pra Simples Nacional vestuário (Larissa biz=4
 *    CSOSN 102 + CFOP 5102) mas quebrariam em venda interestadual contribuinte
 *    (CFOP 6102 com ICMS-ST) — multa fiscal R1 audit sênior.
 *
 * Cobertura atual (22 registros):
 *  - Bloco 0: 0000 + 0001 + 0005 + 0150 (destinatários) + 0190 (unidades) + 0200 (itens) + 0990
 *  - Bloco C: C001 + C100 (saídas — NfeEmissao status=autorizada) + C170 (itens) + C190 (totalizador) + C990
 *  - Bloco E: E001 + E100 (período) + E110 (apuração) + E116 (obrigações se > 0) + E990
 *  - Bloco H: H001 + H990 (esqueleto sempre vazio — inventário anual exige integração Stock)
 *  - Bloco 9: 9001 + 9900 (contadores) + 9990 + 9999
 *
 * Non-Goals (próximos PRs):
 *  - Saldo credor anterior real em E110 (exige histórico ICMS — placeholder 0)
 *  - Bloco H com dados reais (Modules/ProductCatalogue/Stock integração — declaração 31/12)
 *  - Bloco D (CT-e prestações de serviço transporte) — modelo 67
 *  - Bloco G (ativo imobilizado CIAP)
 *  - Bloco K (controle produção/estoque industrial)
 *  - Entradas (NF-e contra CNPJ via DF-e manifestada) — exige reconciliação cadastro
 *  - PIS/COFINS (EFD-Contribuições separado — outro arquivo)
 *  - Items reais via JOIN transactions_sell_lines (escopo Strategy pattern futuro)
 *  - COD_MUN IBGE municipio-level (lookup via business->city_id) — placeholder UF+0000
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *  - HasBusinessScope automático em NfeEmissao
 *  - Cross-tenant guard explícito via session check
 *
 * Layout: pipe-delimited `|REG|c1|c2|...|`, terminator `|\r\n`.
 */
class SpedIcmsIpiGeneratorService
{
    /**
     * Fallback Simples Nacional (CSOSN 102 — sem permissão crédito ICMS).
     *
     * Aplicado quando MotorTributarioService lança exception
     * (NcmObrigatorioException / TributacaoNaoConfiguradaException) — caso
     * comum em biz=4 ROTA LIVRE vestuário hoje (NFe via NfeBrasil sem
     * regras tributárias cadastradas, mas Simples Nacional aceita default).
     *
     * Quando audit sênior GAP-7 (Strategy Pattern por regime — Lucro
     * Presumido/Real) entregar, este fallback fica apenas pra Simples;
     * outros regimes lançam exception fatal.
     *
     * CSOSN 102 = "Tributada sem permissão de crédito" (Simples Nacional).
     * CFOP 5102 = "Venda de mercadoria adquirida ou recebida de terceiros"
     * (operação interna mesma UF).
     */
    private const FALLBACK_NCM_SEM_CADASTRO = '00000000';

    private const FALLBACK_CST_CSOSN_SIMPLES_SEM_CREDITO = '102';

    private const FALLBACK_CFOP_VENDA_INTERNA_SIMPLES = '5102';

    private const FALLBACK_CFOP_VENDA_INTERESTADUAL_SIMPLES = '6102';

    private const FALLBACK_ALIQ_ICMS_SIMPLES = 0.0;

    private const FALLBACK_COD_GEN_MERCADORIA = '00';

    /** @var array<string,int> contador linhas por tipo registro (bloco 9900) */
    private array $contadores = [];

    public function __construct(
        private readonly ?MotorTributarioService $motor = null,
    ) {
        // Default = null suporta legacy `new SpedIcmsIpiGeneratorService()` sem
        // container. Quando resolvido via container, Laravel DI passa instance
        // de MotorTributarioService — caminho preferido.
    }

    public function gerar(int $businessId, int $ano, int $mes): string
    {
        return OtelHelper::spanBiz('fiscal.sped.gerar', function () use ($businessId, $ano, $mes): string {
            return $this->gerarInterno($businessId, $ano, $mes);
        }, [
            'module' => 'Fiscal',
            'ano'    => $ano,
            'mes'    => $mes,
        ]);
    }

    private function gerarInterno(int $businessId, int $ano, int $mes): string
    {
        $this->validar($businessId, $ano, $mes);

        $business = DB::table('business')->where('id', $businessId)->first();
        if (! $business) {
            throw new RuntimeException("Business {$businessId} não encontrado.");
        }

        $periodoIni = CarbonImmutable::create($ano, $mes, 1)->startOfMonth();
        $periodoFim = $periodoIni->endOfMonth();
        $ufBusiness = strtoupper((string) ($business->state ?? 'SP'));

        // Carrega NFes autorizadas do período (saídas — modelo 55/65)
        $emissoes = NfeEmissao::query()
            ->where('business_id', $businessId)
            ->where('status', 'autorizada')
            ->whereBetween('emitido_em', [$periodoIni, $periodoFim])
            ->orderBy('emitido_em')
            ->orderBy('numero')
            ->get();

        $this->contadores = [];
        $linhas = [];

        // ── Bloco 0: Abertura + cadastros ────────────────────────────────
        $linhas[] = $this->registro0000($business, $periodoIni, $periodoFim);
        $linhas[] = $this->registro0001(empty($emissoes) ? 1 : 0);
        $linhas[] = $this->registro0005($business);

        // 0150: Tabela de participantes (destinatários únicos das NFes)
        $participantes = $this->extrairParticipantes($emissoes);
        foreach ($participantes as $p) {
            $linhas[] = $this->registro0150($p);
        }

        // 0190: Unidades de medida
        $linhas[] = $this->registro0190('UN', 'UNIDADE');

        // 0200: Tabela de itens (produtos das NFes)
        $itens = $this->extrairItens($emissoes);
        foreach ($itens as $item) {
            $linhas[] = $this->registro0200($item);
        }

        $linhas[] = $this->registro0990(count($linhas) + 1); // +1 conta o próprio 0990

        // ── Bloco C: Notas ───────────────────────────────────────────────
        $bloco_c_inicio = count($linhas);
        $linhas[] = $this->registroC001(empty($emissoes) ? 1 : 0);

        $totalizadores = [];
        foreach ($emissoes as $emissao) {
            $ufDestino = $this->resolverUfDestino($emissao, $ufBusiness);
            $tributo = $this->resolverTributoItem($emissao, $ufBusiness, $ufDestino);

            $linhas[] = $this->registroC100($emissao);
            $linhas[] = $this->registroC170($emissao, $tributo);

            $key = $this->keyTotalizadorC190($emissao, $tributo);
            $totalizadores[$key] ??= [
                'cst'     => $tributo['cst'],
                'cfop'    => $tributo['cfop'],
                'aliq'    => $tributo['aliq_icms'],
                'vl_opr'  => 0,
                'vl_bc'   => 0,
                'vl_icms' => 0,
            ];
            $totalizadores[$key]['vl_opr'] += (float) $emissao->valor_total;
            $totalizadores[$key]['vl_icms'] += $tributo['vl_icms'];
        }

        foreach ($totalizadores as $tot) {
            $linhas[] = $this->registroC190($tot);
        }

        $linhas[] = $this->registroC990(count($linhas) - $bloco_c_inicio + 1);

        // ── Bloco E: Apuração ICMS (PR #9 Wave) ──────────────────────────
        $bloco_e_inicio = count($linhas);
        $linhas[] = $this->registroE001(empty($emissoes) ? 1 : 0);
        $linhas[] = $this->registroE100($periodoIni, $periodoFim);

        $vlTotalDebitos = array_sum(array_column($totalizadores, 'vl_icms'));
        $linhas[] = $this->registroE110($vlTotalDebitos);

        if ($vlTotalDebitos > 0) {
            $linhas[] = $this->registroE116($vlTotalDebitos, $periodoIni);
        }

        $linhas[] = $this->registroE990(count($linhas) - $bloco_e_inicio + 1);

        // ── Bloco H: Inventário (esqueleto — declaração 31/12 só em janeiro) ──
        $bloco_h_inicio = count($linhas);
        $linhas[] = $this->registroH001(1);
        $linhas[] = $this->registroH990(count($linhas) - $bloco_h_inicio + 1);

        // ── Bloco 9: Encerramento + contadores ───────────────────────────
        $bloco_9_inicio = count($linhas);
        $linhas[] = $this->registro9001(empty($emissoes) ? 1 : 0);

        $regsExistentes = $this->contadores;
        $qtdNoveCemNoveCem = count($regsExistentes) + 3;

        foreach ($regsExistentes as $reg => $cnt) {
            $linhas[] = $this->registro9900($reg, $cnt);
        }
        $linhas[] = $this->registro9900('9900', $qtdNoveCemNoveCem);
        $linhas[] = $this->registro9900('9990', 1);
        $linhas[] = $this->registro9900('9999', 1);

        $linhas[] = $this->registro9990(count($linhas) - $bloco_9_inicio + 1);
        $linhas[] = $this->registro9999(count($linhas) + 1);

        return implode('', $linhas);
    }

    private function validar(int $businessId, int $ano, int $mes): void
    {
        if ($ano < 2020 || $ano > (int) date('Y')) {
            throw new InvalidArgumentException("Ano inválido: {$ano} (aceito 2020 até " . date('Y') . ')');
        }
        if ($mes < 1 || $mes > 12) {
            throw new InvalidArgumentException("Mês inválido: {$mes} (1-12)");
        }

        $sessionBiz = session('user.business_id');
        if ($sessionBiz !== null && (int) $sessionBiz !== $businessId) {
            throw new RuntimeException(
                "Cross-tenant attempt: session biz={$sessionBiz} tentou gerar SPED biz={$businessId}"
            );
        }
    }

    /**
     * GAP-FISCAL-003 — Resolve tributo via MotorTributarioService (cascade
     * 4 níveis ADR ARQ-0006) ou cai pra fallback Simples Nacional safe.
     *
     * Retorna shape uniform pros registros C170/C190/0200 consumirem:
     *   - cst (CSOSN ou CST conforme regime)
     *   - cfop (operação interna/interestadual)
     *   - aliq_icms (decimal 0.18 = 18%)
     *   - vl_icms (valor absoluto calculado pelo motor — 0 pra Simples)
     *
     * Fallback log INFO (não ERROR) quando motor não configurado — caso
     * comum biz=4 ROTA LIVRE hoje. Audit sênior GAP-7 vai promover pra
     * exception fatal quando regime ≠ Simples.
     *
     * @return array{cst: string, cfop: string, aliq_icms: float, vl_icms: float, ncm: string}
     */
    private function resolverTributoItem(NfeEmissao $emissao, string $ufOrigem, string $ufDestino): array
    {
        $ncm = $this->extrairNcmDaEmissao($emissao);
        $valor = (float) $emissao->valor_total;

        if ($this->motor !== null && $ncm !== null) {
            try {
                $context = new ProdutoFiscalContext(ncm: $ncm, valor: $valor);
                $tributo = $this->motor->calcular($context, (int) $emissao->business_id, $ufOrigem, $ufDestino);

                return [
                    'cst'       => (string) ($tributo->csosn ?? $tributo->cst ?? self::FALLBACK_CST_CSOSN_SIMPLES_SEM_CREDITO),
                    'cfop'      => $tributo->cfop,
                    'aliq_icms' => $tributo->aliquota_icms,
                    'vl_icms'   => $tributo->valor_icms,
                    'ncm'       => $ncm,
                ];
            } catch (NcmObrigatorioException | TributacaoNaoConfiguradaException $e) {
                Log::info('SpedIcmsIpi: MotorTributario sem regra/config — fallback Simples Nacional', [
                    'business_id'    => $emissao->business_id,
                    'nfe_emissao_id' => $emissao->id,
                    'ncm'            => $ncm,
                    'razao'          => $e->getMessage(),
                ]);
            }
        }

        return $this->fallbackSimplesNacional($ufOrigem, $ufDestino, $ncm);
    }

    /**
     * Fallback Simples Nacional CSOSN 102 — usado quando MotorTributarioService
     * não configurado ou lançou exception conhecida. Diferencia interno (CFOP
     * 5102) vs interestadual (CFOP 6102) — gap de COMPRA do hardcode anterior.
     *
     * @return array{cst: string, cfop: string, aliq_icms: float, vl_icms: float, ncm: string}
     */
    private function fallbackSimplesNacional(string $ufOrigem, string $ufDestino, ?string $ncm): array
    {
        return [
            'cst'       => self::FALLBACK_CST_CSOSN_SIMPLES_SEM_CREDITO,
            'cfop'      => $ufOrigem === $ufDestino
                ? self::FALLBACK_CFOP_VENDA_INTERNA_SIMPLES
                : self::FALLBACK_CFOP_VENDA_INTERESTADUAL_SIMPLES,
            'aliq_icms' => self::FALLBACK_ALIQ_ICMS_SIMPLES,
            'vl_icms'   => 0.0,
            'ncm'       => $ncm ?? self::FALLBACK_NCM_SEM_CADASTRO,
        ];
    }

    /**
     * Extrai NCM da NfeEmissao via metadata (preenchido pelo NfeService::emitir
     * em algumas filiais do código). Retorna null quando não disponível — caller
     * usa fallback. Escopo GAP-9 (audit sênior) substitui por JOIN
     * transactions_sell_lines pra obter NCM real do produto da linha.
     */
    private function extrairNcmDaEmissao(NfeEmissao $emissao): ?string
    {
        $meta = (array) ($emissao->metadata ?? []);
        $ncm = $meta['ncm'] ?? $meta['item_ncm'] ?? null;

        return is_string($ncm) && strlen($ncm) >= 4 ? $ncm : null;
    }

    /**
     * UF destino da NFe. Lê metadata.dest_uf se preenchido (NFe B2B com
     * cadastro). Pra NFC-e B2C anônimo, assume UF = UF do business (operação
     * interna). Audit sênior GAP-9 vai trazer UF real via JOIN contacts.
     */
    private function resolverUfDestino(NfeEmissao $emissao, string $ufBusiness): string
    {
        $meta = (array) ($emissao->metadata ?? []);
        $uf = $meta['dest_uf'] ?? null;

        return is_string($uf) && strlen($uf) === 2 ? strtoupper($uf) : $ufBusiness;
    }

    // ────────────────────────────────────────────────────────────────────
    // Formatação de registros — pipe-delimited |REG|c1|c2|...|
    // ────────────────────────────────────────────────────────────────────

    private function linha(string $reg, array $campos): string
    {
        $this->incrementar($reg);
        $parts = array_map(fn ($v) => $v === null ? '' : (string) $v, $campos);
        return '|' . $reg . '|' . implode('|', $parts) . "|\r\n";
    }

    private function incrementar(string $reg): void
    {
        $this->contadores[$reg] = ($this->contadores[$reg] ?? 0) + 1;
    }

    private function registro0000(object $business, CarbonImmutable $ini, CarbonImmutable $fim): string
    {
        return $this->linha('0000', [
            '018',
            '0',
            $ini->format('dmY'),
            $fim->format('dmY'),
            mb_strtoupper(substr((string) ($business->name ?? ''), 0, 100)),
            preg_replace('/\D/', '', (string) ($business->tax_number ?? '')),
            '',
            strtoupper((string) ($business->state ?? 'SP')),
            (string) ($business->inscricao_estadual ?? ''),
            $this->codigoIbgeMunicipio($business),
            '',
            (string) ($business->state ?? 'SP'),
            'A',
            '1',
        ]);
    }

    private function registro0001(int $indMov): string
    {
        return $this->linha('0001', [(string) $indMov]);
    }

    private function registro0005(object $business): string
    {
        return $this->linha('0005', [
            mb_strtoupper(substr((string) ($business->name ?? ''), 0, 100)),
            (string) ($business->zip_code ?? '00000000'),
            substr((string) ($business->landmark ?? 'NAO INFORMADO'), 0, 60),
            (string) ($business->city ?? ''),
            (string) ($business->state ?? ''),
            (string) ($business->mobile ?? ''),
            (string) ($business->email ?? ''),
            '',
        ]);
    }

    private function registro0150(array $p): string
    {
        return $this->linha('0150', [
            (string) $p['cod'],
            substr((string) $p['nome'], 0, 100),
            '01058',
            $p['cnpj'],
            $p['cpf'],
            '',
            (string) ($p['cod_mun'] ?? '9999999'),
            (string) ($p['suframa'] ?? ''),
            (string) ($p['end'] ?? 'NAO INFORMADO'),
            '',
            '',
            '',
        ]);
    }

    private function registro0190(string $unid, string $descr): string
    {
        return $this->linha('0190', [$unid, $descr]);
    }

    private function registro0200(array $item): string
    {
        return $this->linha('0200', [
            (string) $item['cod'],
            substr((string) $item['descr'], 0, 100),
            '',
            '',
            'UN',
            '00',
            (string) ($item['ncm'] ?? self::FALLBACK_NCM_SEM_CADASTRO),
            '',
            (string) ($item['gen'] ?? self::FALLBACK_COD_GEN_MERCADORIA),
            '',
            '',
        ]);
    }

    private function registro0990(int $qtd): string
    {
        return $this->linha('0990', [(string) $qtd]);
    }

    private function registroC001(int $indMov): string
    {
        return $this->linha('C001', [(string) $indMov]);
    }

    private function registroC100(NfeEmissao $e): string
    {
        $modelo = (string) ($e->modelo ?? '55');
        $valor = (float) $e->valor_total;
        $emitido = $e->emitido_em;

        return $this->linha('C100', [
            '1',
            '0',
            '',
            $modelo,
            '00',
            (string) ($e->serie ?? '1'),
            (string) $e->numero,
            (string) ($e->chave_44 ?? str_repeat('0', 44)),
            $emitido?->format('dmY') ?? '',
            $emitido?->format('dmY') ?? '',
            number_format($valor, 2, ',', ''),
            '0',
            '0,00',
            '0,00',
            number_format($valor, 2, ',', ''),
            '0',
            '0,00',
            '0,00',
            '0,00',
            number_format($valor, 2, ',', ''),
            '0,00',
            '0,00',
            '0,00',
            '0,00',
            '0,00',
            '0,00',
            '0,00',
            '0,00',
        ]);
    }

    /**
     * @param  array{cst: string, cfop: string, aliq_icms: float, vl_icms: float, ncm: string}  $tributo
     */
    private function registroC170(NfeEmissao $e, array $tributo): string
    {
        $valor = (float) $e->valor_total;
        $vlIcms = $tributo['vl_icms'];
        $aliqPct = $tributo['aliq_icms'] * 100; // motor retorna decimal (0.18); SPED quer percentual (18.00)

        return $this->linha('C170', [
            '1',
            'PDV-' . ($e->transaction_id ?? $e->id),
            'Venda PDV #' . ($e->transaction_id ?? $e->id),
            '1',
            'UN',
            number_format($valor, 2, ',', ''),
            '0,00',
            'N',
            $tributo['cst'],
            $tributo['cfop'],
            '',
            $vlIcms > 0 ? number_format($valor, 2, ',', '') : '0,00', // VL_BC_ICMS
            number_format($aliqPct, 2, ',', ''),                       // ALIQ_ICMS
            number_format($vlIcms, 2, ',', ''),                        // VL_ICMS
            '0,00',
            '0,00',
            '0,00',
            '',
            '49',
            '',
            '0,00',
            '0,00',
            '0,00',
            '49',
            '0,00',
            '0,00',
            '0,0000',
            '0,0000',
            '0,00',
            '49',
            '0,00',
            '0,00',
            '0,0000',
            '0,0000',
            '0,00',
            '',
        ]);
    }

    private function registroC190(array $tot): string
    {
        $aliqPct = ($tot['aliq'] ?? 0) * 100;

        return $this->linha('C190', [
            (string) ($tot['cst'] ?? self::FALLBACK_CST_CSOSN_SIMPLES_SEM_CREDITO),
            (string) ($tot['cfop'] ?? self::FALLBACK_CFOP_VENDA_INTERNA_SIMPLES),
            number_format($aliqPct, 2, ',', ''),
            number_format($tot['vl_opr'] ?? 0, 2, ',', ''),
            number_format($tot['vl_bc'] ?? 0, 2, ',', ''),
            number_format($tot['vl_icms'] ?? 0, 2, ',', ''),
            '0,00',
            '0,00',
            '0,00',
            '0,00',
            '',
        ]);
    }

    private function registroC990(int $qtd): string
    {
        return $this->linha('C990', [(string) $qtd]);
    }

    // ────────────────────────────────────────────────────────────────────
    // Bloco E — Apuração ICMS (PR #9 Wave)
    // ────────────────────────────────────────────────────────────────────

    private function registroE001(int $indMov): string
    {
        return $this->linha('E001', [(string) $indMov]);
    }

    private function registroE100(CarbonImmutable $ini, CarbonImmutable $fim): string
    {
        return $this->linha('E100', [
            $ini->format('dmY'),
            $fim->format('dmY'),
        ]);
    }

    private function registroE110(float $vlTotalDebitos): string
    {
        $vlSaldoApurado = $vlTotalDebitos;
        $vlIcmsRecolher = $vlSaldoApurado > 0 ? $vlSaldoApurado : 0;
        $vlSaldoCredorTransportar = $vlSaldoApurado < 0 ? abs($vlSaldoApurado) : 0;

        return $this->linha('E110', [
            number_format($vlTotalDebitos, 2, ',', ''),
            '0,00',
            '0,00',
            '0,00',
            '0,00',
            '0,00',
            '0,00',
            '0,00',
            '0,00',
            number_format($vlSaldoApurado, 2, ',', ''),
            '0,00',
            number_format($vlIcmsRecolher, 2, ',', ''),
            number_format($vlSaldoCredorTransportar, 2, ',', ''),
            '0,00',
        ]);
    }

    private function registroE116(float $vlIcmsRecolher, CarbonImmutable $periodoIni): string
    {
        $dtVcto = $periodoIni->copy()->addMonth()->setDay(20);

        return $this->linha('E116', [
            '000',
            number_format($vlIcmsRecolher, 2, ',', ''),
            $dtVcto->format('dmY'),
            '000',
            '',
            '',
            '',
            '',
            $periodoIni->format('mY'),
        ]);
    }

    private function registroE990(int $qtd): string
    {
        return $this->linha('E990', [(string) $qtd]);
    }

    // ────────────────────────────────────────────────────────────────────
    // Bloco H — Inventário (PR #9 Wave — esqueleto sem dados no MVP)
    // ────────────────────────────────────────────────────────────────────

    private function registroH001(int $indMov): string
    {
        return $this->linha('H001', [(string) $indMov]);
    }

    private function registroH990(int $qtd): string
    {
        return $this->linha('H990', [(string) $qtd]);
    }

    // ────────────────────────────────────────────────────────────────────
    // Bloco 9 — Encerramento
    // ────────────────────────────────────────────────────────────────────

    private function registro9001(int $indMov): string
    {
        return $this->linha('9001', [(string) $indMov]);
    }

    private function registro9900(string $reg, int $qtd): string
    {
        return $this->linha('9900', [$reg, (string) $qtd]);
    }

    private function registro9990(int $qtd): string
    {
        return $this->linha('9990', [(string) $qtd]);
    }

    private function registro9999(int $qtd): string
    {
        return $this->linha('9999', [(string) $qtd]);
    }

    // ────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────

    private function extrairParticipantes($emissoes): array
    {
        $participantes = [];
        foreach ($emissoes as $e) {
            $meta = (array) ($e->metadata ?? []);
            $cnpj = (string) ($meta['dest_cnpj'] ?? '');
            $cpf = (string) ($meta['dest_cpf'] ?? '');
            if (! $cnpj && ! $cpf) {
                continue;
            }
            $cod = $this->resolverCodigoParticipante($cnpj, $cpf);
            $participantes[$cod] ??= [
                'cod'  => $cod,
                'nome' => (string) ($meta['dest_name'] ?? 'CONSUMIDOR'),
                'cnpj' => $cnpj,
                'cpf'  => $cpf,
            ];
        }
        return array_values($participantes);
    }

    /**
     * Código participante 0150 — usa CNPJ ou CPF como identifier (estável
     * cross-NFe pro mesmo destinatário). Audit sênior GAP-3 elimina hardcode
     * `'P-' . $cnpj` espalhado pra função centralizada.
     */
    private function resolverCodigoParticipante(string $cnpj, string $cpf): string
    {
        return 'P-' . ($cnpj ?: $cpf);
    }

    private function extrairItens($emissoes): array
    {
        $itens = [];
        foreach ($emissoes as $e) {
            $cod = 'PDV-' . ($e->transaction_id ?? $e->id);
            $ncm = $this->extrairNcmDaEmissao($e);
            $itens[$cod] ??= [
                'cod'   => $cod,
                'descr' => 'Venda PDV #' . ($e->transaction_id ?? $e->id),
                'ncm'   => $ncm ?? self::FALLBACK_NCM_SEM_CADASTRO,
                'gen'   => self::FALLBACK_COD_GEN_MERCADORIA,
            ];
        }
        return array_values($itens);
    }

    /**
     * Chave totalizador C190 — combina CST + CFOP + aliq pra agrupar
     * operações similares (Layout v3.1.1). Audit sênior GAP-3 elimina o
     * hardcode `return '102'` substituindo por chave composta real.
     *
     * @param  array{cst: string, cfop: string, aliq_icms: float, vl_icms: float, ncm: string}  $tributo
     */
    private function keyTotalizadorC190(NfeEmissao $e, array $tributo): string
    {
        return sprintf('%s|%s|%.4f', $tributo['cst'], $tributo['cfop'], $tributo['aliq_icms']);
    }

    /**
     * COD_MUN IBGE — placeholder UF+0000 enquanto integração com
     * `business.city_id` (lookup tabela IBGE) não chegou. Audit sênior
     * GAP-3 catalogou como hardcode Tier-0; PVA-EFD CONFAZ aceita placeholder
     * em homologação mas rejeita em produção pra business com endereço
     * preenchido. Wave futura: lookup completo via Modules/Geo/Municipio.
     */
    private function codigoIbgeMunicipio(object $business): string
    {
        $uf = strtoupper((string) ($business->state ?? 'SP'));
        return $this->codigoIbgeUf($uf) . '0000';
    }

    private function codigoIbgeUf(string $uf): string
    {
        return [
            'AC' => '12', 'AL' => '27', 'AP' => '16', 'AM' => '13', 'BA' => '29',
            'CE' => '23', 'DF' => '53', 'ES' => '32', 'GO' => '52', 'MA' => '21',
            'MT' => '51', 'MS' => '50', 'MG' => '31', 'PA' => '15', 'PB' => '25',
            'PR' => '41', 'PE' => '26', 'PI' => '22', 'RJ' => '33', 'RN' => '24',
            'RS' => '43', 'RO' => '11', 'RR' => '14', 'SC' => '42', 'SP' => '35',
            'SE' => '28', 'TO' => '17',
        ][strtoupper($uf)] ?? '35';
    }
}
