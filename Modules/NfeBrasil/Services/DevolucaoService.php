<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Models\NfeEmissao;
use RuntimeException;

/**
 * Playbook Fiscal thread 20 · devolução de venda (R-NFE-026 · R-NFE-027 · US-NFE-064).
 *
 * A regra da devolução é uma só: **a base e as alíquotas vêm da nota de origem, nunca da regra
 * vigente**. Por isso este serviço não recebe o `MotorTributarioService` — ele lê o XML que a
 * SEFAZ autorizou (`nfe_emissoes.xml_path`) e devolve a fração da linha. Se a regra do ICMS mudou
 * de 18% para 12% depois da venda, a devolução continua em 18%: é o crédito que o cliente tomou
 * (ou o débito que a empresa pagou) que está sendo estornado.
 *
 * Valor (REGRA MESTRE, memory/proibicoes.md) — cada campo monetário do item é rateado pela
 * quantidade ACUMULADA, em centavos:
 *
 *     parte = round(c × (já + agora) / q) − round(c × já / q)        c = centavos da origem
 *
 * Assim a soma de todas as devoluções de uma linha bate exatamente com a origem: 4/10 + 6/10 = 10/10,
 * sem centavo sobrando nem faltando. A alíquota é copiada como texto da origem.
 */
class DevolucaoService
{
    /** Status de devolução que já consomem saldo (número declarado ou a caminho). */
    public const STATUS_QUE_CONSOMEM_SALDO = ['autorizada', 'pendente', 'enviando', 'contingencia'];

    /**
     * CFOP de venda → CFOP da devolução. Só o que é certo entra; CFOP fora da tabela recusa
     * em vez de inventar (o contador decide o caso raro).
     */
    private const CFOP_ESPELHO = [
        '101' => '201', // venda de produção → devolução de venda de produção
        '102' => '202', // venda de mercadoria de terceiros → devolução idem
        '401' => '410', // venda de produção com ST → devolução com ST
        '403' => '411', // venda de terceiros com ST (substituto) → devolução com ST
        '405' => '411', // venda de terceiros com ST (substituído) → devolução com ST
    ];

    public function __construct(private readonly NfeService $nfeService) {}

    /** 5102 → 1202 · 6102 → 2202 · 5405 → 1411. */
    public static function cfopEspelho(string $cfop): string
    {
        $cfop = preg_replace('/\D/', '', $cfop);
        $prefixo = ['5' => '1', '6' => '2', '7' => '3'][$cfop[0] ?? ''] ?? null;
        $sufixo = self::CFOP_ESPELHO[substr($cfop, 1)] ?? null;
        if (strlen($cfop) !== 4 || $prefixo === null || $sufixo === null) {
            throw new RuntimeException("CFOP {$cfop} da nota de origem sem espelho de devolução conhecido — informe o CFOP com o contador.");
        }

        return $prefixo . $sufixo;
    }

    /**
     * Emite a NF-e (modelo 55) de devolução de uma nota autorizada.
     *
     * @param array<int,float> $quantidades nItem da origem => quantidade a devolver
     * @param array<string,mixed> $destOverride destinatário quando a origem não tem (NFC-e anônima)
     */
    public function emitirDevolucao(int $businessId, int $origemEmissaoId, array $quantidades, array $destOverride = []): NfeEmissao
    {
        // Tier 0: a origem só é achada dentro da empresa.
        $origem = NfeEmissao::where('business_id', $businessId)->where('id', $origemEmissaoId)->first();
        if (! $origem) {
            throw new RuntimeException("Nota {$origemEmissaoId} não encontrada nesta empresa.");
        }
        if ($origem->status !== 'autorizada' || strlen((string) $origem->chave_44) !== 44 || empty($origem->xml_path)) {
            throw new RuntimeException("Só se devolve nota autorizada com XML guardado (nota {$origem->numero}: {$origem->status}).");
        }
        $xml = Storage::get($origem->xml_path);
        if (! is_string($xml) || $xml === '') {
            throw new RuntimeException("XML da nota {$origem->numero} não foi encontrado em {$origem->xml_path}.");
        }

        $montado = $this->montarDevolucao(
            xmlOrigem: $xml,
            quantidades: $quantidades,
            jaDevolvido: $this->saldoDevolvido($businessId, (int) $origem->id),
        );

        $dest = $destOverride + $montado['dest'];
        if (empty($dest['cnpj']) && empty($dest['cpf'])) {
            throw new RuntimeException('A nota de origem não identifica o destinatário — informe CPF ou CNPJ para devolver.');
        }

        return $this->nfeService->emitir($businessId, [
            'modelo'      => '55',
            'nat_op'      => 'DEVOLUCAO DE VENDA',
            'fin_nfe'     => 4,
            'tp_nf'       => 0,
            'ref_nfe'     => (string) $origem->chave_44,
            'dest'        => $dest,
            'dets'        => $montado['dets'],
            'total'       => $montado['total'],
            // Devolução não tem pagamento: tPag 90 = sem pagamento.
            'pag'         => [['tpag' => '90', 'vpag' => 0]],
            'valor_total' => $montado['total']['v_nf'],
            'metadata'    => ['devolucao' => [
                'origem_id'    => (int) $origem->id,
                'chave_origem' => (string) $origem->chave_44,
                'itens'        => $montado['itens'],
            ]],
            'inf_cpl'     => "Devolucao referente a NF-e {$origem->numero} serie {$origem->serie}, chave {$origem->chave_44}.",
        ]);
    }

    /**
     * Quanto de cada item da origem já foi devolvido (R-NFE-027). Devolução rejeitada ou
     * inutilizada não conta — o número não foi declarado.
     *
     * @return array<int,float> nItem => quantidade
     */
    public function saldoDevolvido(int $businessId, int $origemEmissaoId): array
    {
        $ja = [];
        $devolucoes = NfeEmissao::where('business_id', $businessId)
            ->where('metadata->devolucao->origem_id', $origemEmissaoId)
            ->whereIn('status', self::STATUS_QUE_CONSOMEM_SALDO)
            ->get(['id', 'metadata']);
        foreach ($devolucoes as $d) {
            foreach ((array) ($d->metadata['devolucao']['itens'] ?? []) as $nItem => $qtd) {
                $ja[(int) $nItem] = ($ja[(int) $nItem] ?? 0.0) + (float) $qtd;
            }
        }

        return $ja;
    }

    /**
     * Monta `dets` + `total` + `dest` da devolução a partir do XML da origem. Sem banco.
     *
     * @param array<int,float> $quantidades nItem => quantidade a devolver agora
     * @param array<int,float> $jaDevolvido nItem => quantidade já devolvida antes
     * @return array{dets:list<array<string,mixed>>,total:array<string,float>,dest:array<string,string>,itens:array<int,float>,chave:string}
     */
    public function montarDevolucao(string $xmlOrigem, array $quantidades, array $jaDevolvido = []): array
    {
        $dom = new DOMDocument();
        if (! @$dom->loadXML($xmlOrigem)) {
            throw new RuntimeException('XML da nota de origem ilegível.');
        }
        $quantidades = array_filter($quantidades, fn ($q) => (float) $q > 0);
        if ($quantidades === []) {
            throw new RuntimeException('Informe ao menos um item com quantidade a devolver.');
        }

        $dets = [];
        $itens = [];
        $tot = ['v_prod' => 0, 'v_desc' => 0, 'v_frete' => 0, 'v_outro' => 0, 'v_bc_icms' => 0,
            'v_icms' => 0, 'v_pis' => 0, 'v_cofins' => 0, 'v_ibs' => 0, 'v_cbs' => 0];
        $achados = [];

        foreach ($dom->getElementsByTagName('det') as $det) {
            /** @var DOMElement $det */
            $nItem = (int) $det->getAttribute('nItem');
            if (! isset($quantidades[$nItem])) {
                continue;
            }
            $achados[] = $nItem;
            $prod = $this->filho($det, 'prod');
            $qOrig = (float) $this->texto($prod, 'qCom');
            $qJa = (float) ($jaDevolvido[$nItem] ?? 0);
            $qAgora = (float) $quantidades[$nItem];
            // Quantidade em décimos de milésimo (qCom tem 4 casas no leiaute): conta inteira.
            [$q, $ja, $depois] = [self::q4($qOrig), self::q4($qJa), self::q4($qJa + $qAgora)];
            if ($q <= 0) {
                throw new RuntimeException("Item {$nItem} da origem sem quantidade.");
            }
            if ($depois > $q) {
                $saldo = ($q - $ja) / 10000.0;
                throw new RuntimeException("Item {$nItem}: devolver {$qAgora} passa da quantidade vendida (saldo {$saldo}).");
            }
            $parte = fn (string $valor): int => self::parte(self::c($valor), $ja, $depois, $q);

            $vProd = $parte($this->texto($prod, 'vProd'));
            $vDesc = $parte($this->texto($prod, 'vDesc'));
            $vFrete = $parte($this->texto($prod, 'vFrete'));
            $vOutro = $parte($this->texto($prod, 'vOutro'));

            $imposto = $this->filho($det, 'imposto');
            $icms = $this->grupo($imposto, 'ICMS');
            $pis = $this->grupo($imposto, 'PIS');
            $cofins = $this->grupo($imposto, 'COFINS');
            $ibscbs = $this->filho($imposto, 'IBSCBS');

            $vBcIcms = $parte($this->texto($icms, 'vBC'));
            $vIcms = $parte($this->texto($icms, 'vICMS'));
            $vPis = $parte($this->texto($pis, 'vPIS'));
            $vCofins = $parte($this->texto($cofins, 'vCOFINS'));
            $vIbs = $parte($this->texto($ibscbs, 'vIBSUF'));
            $vCbs = $parte($this->texto($ibscbs, 'vCBS'));

            $vUn = (float) $this->texto($prod, 'vUnCom');
            $dets[] = [
                'cprod'   => $this->texto($prod, 'cProd'),
                'xprod'   => $this->texto($prod, 'xProd'),
                'ncm'     => $this->texto($prod, 'NCM'),
                'cfop'    => self::cfopEspelho($this->texto($prod, 'CFOP')),
                'ucm'     => $this->texto($prod, 'uCom') ?: 'UN',
                'qcom'    => $qAgora,
                'vuncom'  => $vUn,
                'vprod'   => $vProd / 100.0,
                'utrib'   => $this->texto($prod, 'uTrib') ?: 'UN',
                'qtrib'   => $qAgora,
                'vuntrib' => (float) ($this->texto($prod, 'vUnTrib') ?: $vUn),
                'dec_vun' => 10,
                'vdesc'   => $vDesc / 100.0,
                'vfrete'  => $vFrete / 100.0,
                'voutro'  => $vOutro / 100.0,
                'ind_tot' => 1,
                'icms'    => [
                    'cst_csosn' => $this->texto($icms, 'CST') ?: $this->texto($icms, 'CSOSN'),
                    'orig'      => (int) $this->texto($icms, 'orig'),
                    'modbc'     => (int) ($this->texto($icms, 'modBC') ?: 3),
                    'vbc'       => $vBcIcms / 100.0,
                    'picms'     => (float) $this->texto($icms, 'pICMS'),
                    'vicms'     => $vIcms / 100.0,
                ],
                'pis'     => [
                    'cst'  => $this->texto($pis, 'CST') ?: '07',
                    'vbc'  => $parte($this->texto($pis, 'vBC')) / 100.0,
                    'ppis' => (float) $this->texto($pis, 'pPIS'),
                    'vpis' => $vPis / 100.0,
                ],
                'cofins'  => [
                    'cst'     => $this->texto($cofins, 'CST') ?: '07',
                    'vbc'     => $parte($this->texto($cofins, 'vBC')) / 100.0,
                    'pcofins' => (float) $this->texto($cofins, 'pCOFINS'),
                    'vcofins' => $vCofins / 100.0,
                ],
                'ibscbs'  => [
                    'cst'          => $this->texto($ibscbs, 'CST') ?: null,
                    'c_class_trib' => $this->texto($ibscbs, 'cClassTrib') ?: null,
                    'vbc'          => $parte($this->texto($ibscbs, 'vBC')) / 100.0,
                    // O XML guarda percentual; o `det` guarda fração (adicionarItem × 100).
                    'aliquota_ibs' => (float) $this->texto($ibscbs, 'pIBSUF') / 100.0,
                    'aliquota_cbs' => (float) $this->texto($ibscbs, 'pCBS') / 100.0,
                    'valor_ibs'    => $vIbs / 100.0,
                    'valor_cbs'    => $vCbs / 100.0,
                ],
            ];
            $itens[$nItem] = $qAgora;

            foreach (['v_prod' => $vProd, 'v_desc' => $vDesc, 'v_frete' => $vFrete, 'v_outro' => $vOutro,
                'v_bc_icms' => $vBcIcms, 'v_icms' => $vIcms, 'v_pis' => $vPis, 'v_cofins' => $vCofins,
                'v_ibs' => $vIbs, 'v_cbs' => $vCbs] as $k => $v) {
                $tot[$k] += $v;
            }
        }

        $faltando = array_diff(array_keys($quantidades), $achados);
        if ($faltando !== []) {
            throw new RuntimeException('Item(ns) ' . implode(', ', $faltando) . ' não existem na nota de origem.');
        }

        $total = array_map(fn (int $c) => $c / 100.0, $tot);
        $total['v_nf'] = ($tot['v_prod'] - $tot['v_desc'] + $tot['v_frete'] + $tot['v_outro']) / 100.0;

        $chave = '';
        $inf = $dom->getElementsByTagName('infNFe')->item(0);
        if ($inf instanceof DOMElement) {
            $chave = preg_replace('/\D/', '', $inf->getAttribute('Id'));
        }

        return ['dets' => $dets, 'total' => $total, 'dest' => $this->dest($dom), 'itens' => $itens, 'chave' => $chave];
    }

    /** Destinatário da origem no formato do `dadosNfe['dest']`. */
    private function dest(DOMDocument $dom): array
    {
        $dest = $dom->getElementsByTagName('dest')->item(0);
        if (! $dest instanceof DOMElement) {
            return [];
        }
        $end = $this->filho($dest, 'enderDest');
        $out = [
            'cnpj'          => $this->texto($dest, 'CNPJ'),
            'cpf'           => $this->texto($dest, 'CPF'),
            'nome'          => $this->texto($dest, 'xNome'),
            'ind_ie_dest'   => $this->texto($dest, 'indIEDest') ?: '9',
            'ie'            => $this->texto($dest, 'IE'),
            'email'         => $this->texto($dest, 'email'),
            'logradouro'    => $this->texto($end, 'xLgr'),
            'numero'        => $this->texto($end, 'nro'),
            'bairro'        => $this->texto($end, 'xBairro'),
            'cod_municipio' => $this->texto($end, 'cMun'),
            'municipio'     => $this->texto($end, 'xMun'),
            'uf'            => $this->texto($end, 'UF'),
            'cep'           => $this->texto($end, 'CEP'),
        ];

        return array_filter($out, fn ($v) => $v !== '');
    }

    private static function q4(float $q): int
    {
        return (int) round($q * 10000);
    }

    private static function c(string $valor): int
    {
        return (int) round((float) $valor * 100);
    }

    /** Fatia em centavos pela quantidade acumulada (ver docblock da classe). */
    private static function parte(int $centavos, int $ja, int $depois, int $q): int
    {
        return (int) round($centavos * $depois / $q) - (int) round($centavos * $ja / $q);
    }

    private function filho(?DOMElement $pai, string $nome): ?DOMElement
    {
        if ($pai === null) {
            return null;
        }
        foreach ($pai->childNodes as $n) {
            if ($n instanceof DOMElement && $n->localName === $nome) {
                return $n;
            }
        }

        return null;
    }

    /** ICMS/PIS/COFINS têm um subgrupo variável (ICMS00, ICMSSN102, PISAliq...): devolve ele. */
    private function grupo(?DOMElement $imposto, string $nome): ?DOMElement
    {
        $g = $this->filho($imposto, $nome);
        if ($g === null) {
            return null;
        }
        foreach ($g->childNodes as $n) {
            if ($n instanceof DOMElement) {
                return $n;
            }
        }

        return null;
    }

    /** Texto do primeiro descendente com esse nome ('' se não houver). */
    private function texto(?DOMElement $pai, string $nome): string
    {
        if ($pai === null) {
            return '';
        }
        $el = $pai->getElementsByTagName($nome)->item(0);

        return $el ? trim($el->textContent) : '';
    }
}
