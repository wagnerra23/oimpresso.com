<?php

declare(strict_types=1);

namespace Modules\Fiscal\Services;

use App\Util\OtelHelper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\NfeBrasil\Models\NfeEmissao;
use RuntimeException;

/**
 * US-FISCAL-016 — Gerador SPED Fiscal EFD-ICMS/IPI (PR #8 Wave MVP).
 *
 * Gera TXT EFD-ICMS/IPI conforme Guia Prático EFD-ICMS/IPI v3.1.1 (CONFAZ).
 *
 * Escopo MVP (este PR):
 *  - Bloco 0: 0000 (abertura) + 0001 + 0005 + 0150 (destinatários) + 0190 (unidades) + 0200 (itens) + 0990
 *  - Bloco C: C001 + C100 (saídas — NfeEmissao status=autorizada) + C170 (itens) + C190 (totalizador) + C990
 *  - Bloco 9: 9001 + 9900 (contadores) + 9990 + 9999
 *
 * Non-Goals (próximos PRs):
 *  - Bloco E (apuração ICMS) — exige saldo credor mês anterior (complexidade)
 *  - Bloco H (inventário anual) — declaração 31/12
 *  - Bloco D (CT-e prestações de serviço transporte) — modelo 67
 *  - Bloco G (ativo imobilizado CIAP)
 *  - Bloco K (controle produção/estoque industrial)
 *  - Entradas (NF-e contra CNPJ via DF-e manifestada) — exige reconciliação cadastro
 *  - PIS/COFINS (EFD-Contribuições separado — outro arquivo)
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *  - HasBusinessScope automático em NfeEmissao
 *  - Cross-tenant guard explícito via session check
 *
 * Layout: pipe-delimited `|REG|c1|c2|...|`, terminator `|\r\n`.
 */
class SpedIcmsIpiGeneratorService
{
    /** @var array<string,int> contador linhas por tipo registro (bloco 9900) */
    private array $contadores = [];

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
            $linhas[] = $this->registroC100($emissao);
            $linhas[] = $this->registroC170($emissao);
            $key = $this->keyTotalizadorC190($emissao);
            $totalizadores[$key] ??= ['cst' => $key, 'cfop' => '5102', 'aliq' => 0, 'vl_opr' => 0, 'vl_bc' => 0, 'vl_icms' => 0];
            $totalizadores[$key]['vl_opr'] += (float) $emissao->valor_total;
        }

        foreach ($totalizadores as $tot) {
            $linhas[] = $this->registroC190($tot);
        }

        $linhas[] = $this->registroC990(count($linhas) - $bloco_c_inicio + 1);

        // ── Bloco 9: Encerramento + contadores ───────────────────────────
        // Estratégia: registros 9900 contam TODOS os tipos do arquivo final
        // (incluindo 9900/9990/9999 que ainda nem foram adicionados). Pre-calcula
        // os totais antes de instanciar as linhas pra evitar self-reference.
        $bloco_9_inicio = count($linhas);
        $linhas[] = $this->registro9001(empty($emissoes) ? 1 : 0);

        // Snapshot dos contadores ANTES do bloco 9900 (incl 9001 já adicionado).
        $regsExistentes = $this->contadores; // copy
        $totalTiposRegistros = count($regsExistentes) + 3; // + 9900, 9990, 9999

        // Quantidade total de 9900 = 1 por reg existente + 3 (self + 9990 + 9999)
        $qtdNoveCemNoveCem = count($regsExistentes) + 3;

        // 1 linha 9900 por tipo já presente (0000, 0001, 0005, 0150, 0190, 0200,
        // 0990, C001, C100, C170, C190, C990, 9001)
        foreach ($regsExistentes as $reg => $cnt) {
            $linhas[] = $this->registro9900($reg, $cnt);
        }
        // 9900 contando a si mesmo (qtd inclui as linhas que serão escritas pra 9990 e 9999)
        $linhas[] = $this->registro9900('9900', $qtdNoveCemNoveCem);
        $linhas[] = $this->registro9900('9990', 1);
        $linhas[] = $this->registro9900('9999', 1);

        // 9990: total de linhas do bloco 9 (até e incluindo 9990)
        $linhas[] = $this->registro9990(count($linhas) - $bloco_9_inicio + 1);

        // 9999: total geral de linhas do arquivo
        $linhas[] = $this->registro9999(count($linhas) + 1);

        // Suprimi unused var
        unset($totalTiposRegistros);

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

    /**
     * 0000 — Abertura do arquivo + identificação do estabelecimento.
     *
     * COD_VER=018 (perfil A obrigatório 2024+), COD_FIN=0 (original),
     * IND_PERFIL=A (perfil completo — Simples Nacional usa B mas defensivo).
     */
    private function registro0000(object $business, CarbonImmutable $ini, CarbonImmutable $fim): string
    {
        return $this->linha('0000', [
            '018',                                    // COD_VER (layout v3.1.1)
            '0',                                      // COD_FIN (0=original, 1=substituto)
            $ini->format('dmY'),                      // DT_INI
            $fim->format('dmY'),                      // DT_FIN
            mb_strtoupper(substr((string) ($business->name ?? ''), 0, 100)), // NOME
            preg_replace('/\D/', '', (string) ($business->tax_number ?? '')), // CNPJ
            '',                                       // CPF (vazio se PJ)
            strtoupper((string) ($business->state ?? 'SP')),  // UF
            (string) ($business->inscricao_estadual ?? ''),  // IE
            $this->codigoIbgeUf($business->state ?? 'SP') . '0000',           // COD_MUN (placeholder — biz fase 2)
            '',                                       // IM (inscrição municipal)
            (string) ($business->state ?? 'SP'),      // SUFRAMA (placeholder)
            'A',                                      // IND_PERFIL (A=completo)
            '1',                                      // IND_ATIV (1=outros; 0=industrial)
        ]);
    }

    private function registro0001(int $indMov): string
    {
        return $this->linha('0001', [(string) $indMov]); // 0=com dados; 1=sem
    }

    private function registro0005(object $business): string
    {
        return $this->linha('0005', [
            mb_strtoupper(substr((string) ($business->name ?? ''), 0, 100)), // FANTASIA
            (string) ($business->zip_code ?? '00000000'),
            substr((string) ($business->landmark ?? 'NAO INFORMADO'), 0, 60), // END
            (string) ($business->city ?? ''),
            (string) ($business->state ?? ''),
            (string) ($business->mobile ?? ''),
            (string) ($business->email ?? ''),
            '',                                       // FAX
        ]);
    }

    private function registro0150(array $p): string
    {
        return $this->linha('0150', [
            (string) $p['cod'],
            substr((string) $p['nome'], 0, 100),
            '01058',                                  // COD_PAIS (Brasil)
            $p['cnpj'],
            $p['cpf'],
            '',                                       // IE
            (string) ($p['cod_mun'] ?? '9999999'),
            (string) ($p['suframa'] ?? ''),
            (string) ($p['end'] ?? 'NAO INFORMADO'),
            '',                                       // NUM
            '',                                       // COMPL
            '',                                       // BAIRRO
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
            '',                                       // COD_BARRA
            '',                                       // COD_ANT_ITEM
            'UN',                                     // UNID_INV
            '00',                                     // TIPO_ITEM (00=mercadoria)
            (string) ($item['ncm'] ?? '00000000'),
            '',                                       // EX_IPI
            (string) ($item['gen'] ?? '00'),          // COD_GEN
            '',                                       // COD_LST
            '',                                       // ALIQ_ICMS
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

    /**
     * C100 — Nota fiscal (NFe/NFCe saída).
     *
     * IND_OPER=1 (saída), IND_EMIT=0 (próprio emitente), MOD=55 ou 65,
     * COD_SIT=00 (autorizada), CHV_NFE=44 dígitos.
     */
    private function registroC100(NfeEmissao $e): string
    {
        $modelo = (string) ($e->modelo ?? '55');
        $valor = (float) $e->valor_total;
        $emitido = $e->emitido_em;

        return $this->linha('C100', [
            '1',                                      // IND_OPER (1=saída)
            '0',                                      // IND_EMIT (0=próprio)
            '',                                       // COD_PART (vazio NFC-e B2C)
            $modelo,                                  // COD_MOD
            '00',                                     // COD_SIT (00=autorizada)
            (string) ($e->serie ?? '1'),              // SER
            (string) $e->numero,                      // NUM_DOC
            (string) ($e->chave_44 ?? str_repeat('0', 44)), // CHV_NFE
            $emitido?->format('dmY') ?? '',           // DT_DOC
            $emitido?->format('dmY') ?? '',           // DT_E_S (entrada/saída)
            number_format($valor, 2, ',', ''),        // VL_DOC
            '0',                                      // IND_PGTO (0=à vista; 1=prazo)
            '0,00',                                   // VL_DESC
            '0,00',                                   // VL_ABAT_NT
            number_format($valor, 2, ',', ''),        // VL_MERC
            '0',                                      // IND_FRT
            '0,00',                                   // VL_FRT
            '0,00',                                   // VL_SEG
            '0,00',                                   // VL_OUT_DA
            number_format($valor, 2, ',', ''),        // VL_BC_ICMS (simplificado)
            '0,00',                                   // VL_ICMS
            '0,00',                                   // VL_BC_ICMS_ST
            '0,00',                                   // VL_ICMS_ST
            '0,00',                                   // VL_IPI
            '0,00',                                   // VL_PIS
            '0,00',                                   // VL_COFINS
            '0,00',                                   // VL_PIS_ST
            '0,00',                                   // VL_COFINS_ST
        ]);
    }

    private function registroC170(NfeEmissao $e): string
    {
        $valor = (float) $e->valor_total;

        return $this->linha('C170', [
            '1',                                      // NUM_ITEM
            'PDV-' . ($e->transaction_id ?? $e->id),  // COD_ITEM
            'Venda PDV #' . ($e->transaction_id ?? $e->id), // DESCR_COMPL
            '1',                                      // QTD
            'UN',                                     // UNID
            number_format($valor, 2, ',', ''),        // VL_ITEM
            '0,00',                                   // VL_DESC
            'N',                                      // IND_MOV (N=movimentou estoque; S=não)
            '102',                                    // CST_ICMS (default simples nacional)
            '5102',                                   // CFOP (venda mercadoria adquirida)
            '',                                       // COD_NAT
            '0,00',                                   // VL_BC_ICMS
            '0,00',                                   // ALIQ_ICMS
            '0,00',                                   // VL_ICMS
            '0,00',                                   // VL_BC_ICMS_ST
            '0,00',                                   // ALIQ_ST
            '0,00',                                   // VL_ICMS_ST
            '',                                       // IND_APUR
            '49',                                     // CST_IPI (49=outras saídas)
            '',                                       // COD_ENQ
            '0,00',                                   // VL_BC_IPI
            '0,00',                                   // ALIQ_IPI
            '0,00',                                   // VL_IPI
            '49',                                     // CST_PIS
            '0,00',                                   // VL_BC_PIS
            '0,00',                                   // ALIQ_PIS
            '0,0000',                                 // QUANT_BC_PIS
            '0,0000',                                 // ALIQ_PIS_QTD
            '0,00',                                   // VL_PIS
            '49',                                     // CST_COFINS
            '0,00',                                   // VL_BC_COFINS
            '0,00',                                   // ALIQ_COFINS
            '0,0000',                                 // QUANT_BC_COFINS
            '0,0000',                                 // ALIQ_COFINS_QTD
            '0,00',                                   // VL_COFINS
            '',                                       // COD_CTA
        ]);
    }

    private function registroC190(array $tot): string
    {
        return $this->linha('C190', [
            '102',                                    // CST_ICMS
            (string) ($tot['cfop'] ?? '5102'),        // CFOP
            number_format($tot['aliq'] ?? 0, 2, ',', ''), // ALIQ_ICMS
            number_format($tot['vl_opr'] ?? 0, 2, ',', ''), // VL_OPR
            number_format($tot['vl_bc'] ?? 0, 2, ',', ''),  // VL_BC_ICMS
            number_format($tot['vl_icms'] ?? 0, 2, ',', ''), // VL_ICMS
            '0,00',                                   // VL_BC_ICMS_ST
            '0,00',                                   // VL_ICMS_ST
            '0,00',                                   // VL_RED_BC
            '0,00',                                   // VL_IPI
            '',                                       // COD_OBS
        ]);
    }

    private function registroC990(int $qtd): string
    {
        return $this->linha('C990', [(string) $qtd]);
    }

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
            $meta = $e->metadata ?? [];
            $cnpj = (string) ($meta['dest_cnpj'] ?? '');
            $cpf = (string) ($meta['dest_cpf'] ?? '');
            if (! $cnpj && ! $cpf) {
                continue; // NFCe B2C anônimo — não registra em 0150
            }
            $cod = 'P-' . ($cnpj ?: $cpf);
            $participantes[$cod] ??= [
                'cod'  => $cod,
                'nome' => (string) ($meta['dest_name'] ?? 'CONSUMIDOR'),
                'cnpj' => $cnpj,
                'cpf'  => $cpf,
            ];
        }
        return array_values($participantes);
    }

    private function extrairItens($emissoes): array
    {
        $itens = [];
        foreach ($emissoes as $e) {
            $cod = 'PDV-' . ($e->transaction_id ?? $e->id);
            $itens[$cod] ??= [
                'cod'   => $cod,
                'descr' => 'Venda PDV #' . ($e->transaction_id ?? $e->id),
                'ncm'   => '00000000',
                'gen'   => '00',
            ];
        }
        return array_values($itens);
    }

    private function keyTotalizadorC190(NfeEmissao $e): string
    {
        return '102'; // CST simples nacional default — Wave futura expande
    }

    private function codigoIbgeUf(string $uf): string
    {
        // 2 primeiros dígitos do COD_MUN IBGE por UF — placeholder.
        // Wave futura: lookup completo por município via business->city_id.
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
