<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Tributacao;

use App\Concerns\HasBusinessScope;
use App\Scopes\ScopeByBusiness;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\Concerns\ResolveUfEmitente;
use Modules\NfeBrasil\Services\DanfeService;
use Modules\NfeBrasil\Services\FatorRService;
use Throwable;

/**
 * Fechamento do mês para o contador — as 10 conferências da aba "Contador"
 * (playbook Fiscal thread 28 · D-FECHAMENTO · D-REJEITADA · R-FISC-040/041/042).
 *
 * SÓ INFORMA. Não gera DAS, não transmite PGDAS, não bloqueia emissão nem fechamento
 * (D-REJEITADA: nota rejeitada pendente aparece como pendência com ação, não trava nada).
 *
 * O QUE O CONTADOR VÊ (D-FECHAMENTO): contagens e somas do mês. Nenhuma venda, cliente,
 * número de nota ou valor de nota individual sai daqui — os métodos de medição devolvem só
 * agregados.
 *
 * FONTES, uma por conferência (cada uma lê do dono do dado, nunca de cópia):
 *   CT01 · CT02  `nfe_emissoes` do mês, por status.
 *   CT03         receita bruta de 12 meses = mesma definição do `FatorRService::apurar()`
 *                (vendas `sell/final` − devoluções `sell_return/final`), janela que termina no
 *                último dia da competência.
 *   CT04         itens dos XML autorizados do mês (CFOP + CSOSN/CST + vProd − vDesc) e NFS-e
 *                emitidas, contra a receita do mês do Financeiro (mesma definição do CT03).
 *   CT05         `FatorRService::apurar()` da competência.
 *   CT06 · CT07  `nfe_dfe_recebidos` do mês.
 *   CT08 · CT09  sem fonte hoje — dizem por quê, em vez de mostrar "ok".
 *   CT10         `nfe_revisoes_contador` pendentes ou com ajuste pedido, de regra vigente no mês.
 *
 * LEI (lida em planalto.gov.br/ccivil_03/leis/lcp/lcp123.htm em 2026-10-14):
 *   LC 123/2006, art. 13-A: "Para efeito de recolhimento do ICMS e do ISS no Simples Nacional,
 *   o limite máximo de que trata o inciso II do caput do art. 3o será de R$ 3.600.000,00 (três
 *   milhões e seiscentos mil reais), observado o disposto nos §§ 11, 13, 14 e 15 do mesmo
 *   artigo, nos §§ 17 e 17-A do art. 18 e no § 4o do art. 19."
 *   LC 123/2006, art. 18, § 4o: "O contribuinte deverá considerar, destacadamente, para fim de
 *   pagamento, as receitas decorrentes da: I - revenda de mercadorias, que serão tributadas na
 *   forma do Anexo I desta Lei Complementar; II - venda de mercadorias industrializadas pelo
 *   contribuinte, que serão tributadas na forma do Anexo II desta Lei Complementar; III -
 *   prestação de serviços de que trata o § 5o-B deste artigo [...]"
 *   LC 123/2006, art. 18, § 4o-A: "O contribuinte deverá segregar, também, as receitas: I -
 *   decorrentes de operações ou prestações sujeitas à tributação concentrada em uma única etapa
 *   (monofásica), bem como, em relação ao ICMS, que o imposto já tenha sido recolhido por
 *   substituto tributário ou por antecipação tributária com encerramento de tributação; [...]"
 *   ⚠️ O playbook cita "art. 18 §4º" para a ST; a segregação da ST está no § 4o-A, I.
 *   O sublimite é verificado aqui sobre 12 meses, como no protótipo: é AVISO. A exclusão formal
 *   segue os §§ 11-15 do art. 3o, que esta conferência não aplica.
 *
 * TIER 0 (ADR 0093): a página do contador é pública e sem sessão — o escopo global não filtra.
 * Toda query daqui leva `business_id` explícito.
 */
final class FechamentoContadorService
{
    use ResolveUfEmitente;

    /** LC 123/2006 art. 13-A — R$ 3.600.000,00, em centavos. */
    public const SUBLIMITE_CENTAVOS = 360_000_000;

    /** Aviso a 90% do sublimite (aceite R-FISC-041). */
    private const AVISO_PCT = 90;

    public const OK = 'ok';
    public const ATENCAO = 'atencao';
    public const PENDENTE = 'pendente';
    public const SEM_FONTE = 'sem_fonte';

    /** Linhas da segregação (art. 18 § 4o e § 4o-A, I). */
    public const SEG_REVENDA = 'Revenda sem ST';
    public const SEG_REVENDA_ST = 'Revenda com ST já retida';
    public const SEG_PRODUCAO = 'Produção própria';
    public const SEG_SERVICOS = 'Serviços (NFS-e)';

    /**
     * CFOP de venda de produção do estabelecimento (sufixo, vale 5xxx e 6xxx).
     * 101 produção · 103/105 produção fora do estabelecimento · 109 Zona Franca · 111/113 ·
     * 116/118 entrega futura · 122/124/125 industrialização por encomenda · 401 produção com ST.
     */
    private const SUFIXOS_PRODUCAO = ['101', '103', '105', '109', '111', '113', '116', '118', '122', '124', '125', '401'];

    /** CSOSN 500 / CST 60: ICMS cobrado anteriormente por substituição tributária. */
    private const ICMS_ST_RETIDO = ['500', '60'];

    /** cUF (IBGE) → sigla, para ler a UF do emitente nos 2 primeiros dígitos da chave. */
    private const CUF = [
        '11' => 'RO', '12' => 'AC', '13' => 'AM', '14' => 'RR', '15' => 'PA', '16' => 'AP', '17' => 'TO',
        '21' => 'MA', '22' => 'PI', '23' => 'CE', '24' => 'RN', '25' => 'PB', '26' => 'PE', '27' => 'AL',
        '28' => 'SE', '29' => 'BA', '31' => 'MG', '32' => 'ES', '33' => 'RJ', '35' => 'SP', '41' => 'PR',
        '42' => 'SC', '43' => 'RS', '50' => 'MS', '51' => 'MT', '52' => 'GO', '53' => 'DF',
    ];

    public function __construct(
        private readonly FatorRService $fatorR,
        private readonly DanfeService $danfe,
    ) {}

    /**
     * As 10 conferências da competência.
     *
     * @return array{competencia: string, conferencias: list<array{id: string, titulo: string, lei: string, situacao: string, obtido: string, acao: ?string}>}
     */
    public function fechar(int $businessId, CarbonImmutable $competencia): array
    {
        return [
            'competencia' => $competencia->format('m/Y'),
            'conferencias' => $this->conferir($this->medir($businessId, $competencia)),
        ];
    }

    /**
     * Lê o mês. Só agregados.
     *
     * @return array<string, mixed>
     */
    public function medir(int $businessId, CarbonImmutable $competencia): array
    {
        $inicio = $competencia->startOfMonth();
        $proximo = $inicio->addMonthNoOverflow();

        $sublimite = $this->fatorR->apurar($businessId, $proximo);
        $fator = $this->fatorR->apurar($businessId, $inicio);

        return [
            'notas' => $this->contarNotas($businessId, $inicio, $proximo),
            'receita12m_centavos' => self::centavos($sublimite['receita']),
            'receita12m_de' => $sublimite['de'],
            'receita12m_ate' => $sublimite['ate'],
            'receita_mes_centavos' => $this->receitaDoMes($businessId, $inicio, $proximo),
            'segregacao' => $this->segregar($businessId, $inicio, $proximo),
            'fator_r' => $fator,
            'entradas' => $this->entradas($businessId, $inicio, $proximo),
            'nfse_com_retencao' => $this->nfseComRetencao($businessId, $inicio, $proximo),
            'sem_aceite' => $this->semAceite($businessId, $inicio, $proximo),
        ];
    }

    /**
     * Decide cada conferência a partir das medidas. Puro: não lê banco.
     *
     * @param  array<string, mixed>  $m
     * @return list<array{id: string, titulo: string, lei: string, situacao: string, obtido: string, acao: ?string}>
     */
    public function conferir(array $m): array
    {
        return [
            $this->ct01($m['notas']),
            $this->ct02($m['notas']),
            $this->ct03((int) $m['receita12m_centavos'], (string) ($m['receita12m_de'] ?? ''), (string) ($m['receita12m_ate'] ?? '')),
            $this->ct04($m['segregacao'], (int) $m['receita_mes_centavos']),
            $this->ct05($m['fator_r']),
            $this->ct06($m['entradas']),
            $this->ct07($m['entradas']),
            self::linha('CT08', 'Devolução sem nota de origem', 'NF-e finalidade 4', self::SEM_FONTE,
                'Este fechamento ainda não cruza as devoluções de venda com as NF-e de devolução (finalidade 4).',
                'Conferir as devoluções do mês fora do ERP até a emissão de devolução existir.'),
            $this->ct09((int) $m['nfse_com_retencao']),
            $this->ct10((int) $m['sem_aceite']),
        ];
    }

    // ------------------------------------------------------------------ conferências

    /** @param array<string, int> $n */
    private function ct01(array $n): array
    {
        $soma = $n['autorizadas'] + $n['canceladas'] + $n['denegadas'] + $n['rejeitadas'] + $n['inutilizadas'];
        $obtido = sprintf(
            '%d autorizadas + %d canceladas + %d denegadas + %d rejeitadas%s = %d emitidas',
            $n['autorizadas'], $n['canceladas'], $n['denegadas'], $n['rejeitadas'],
            $n['inutilizadas'] > 0 ? ' + ' . $n['inutilizadas'] . ' inutilizadas' : '',
            $soma,
        );
        if ($n['em_andamento'] > 0) {
            return self::linha('CT01', 'Notas do mês fecham', 'escrituração', self::PENDENTE,
                $obtido . ' · ' . $n['em_andamento'] . ' ainda sem resposta da SEFAZ',
                'Esperar o retorno da SEFAZ ou reenviar as notas sem resposta antes de fechar.');
        }

        return self::linha('CT01', 'Notas do mês fecham', 'escrituração', self::OK, $obtido, null);
    }

    /** @param array<string, int> $n */
    private function ct02(array $n): array
    {
        if ($n['rejeitadas'] === 0) {
            return self::linha('CT02', 'Rejeitadas sem resolver antes de fechar', 'rotina', self::OK,
                'Nenhuma nota rejeitada pendente', null);
        }

        // D-REJEITADA: não bloqueia o fechamento — é pendência com ação.
        return self::linha('CT02', 'Rejeitadas sem resolver antes de fechar', 'rotina', self::PENDENTE,
            $n['rejeitadas'] . ' notas rejeitadas sem reenvio nem inutilização',
            'Resolver ou inutilizar a numeração.');
    }

    private function ct03(int $receita12m, string $de, string $ate): array
    {
        $titulo = 'Sublimite do Simples (ICMS/ISS fora do DAS)';
        $lei = 'LC 123/2006, art. 13-A';
        $periodo = $de !== '' ? sprintf(' (%s a %s)', self::data($de), self::data($ate)) : '';
        $obtido = sprintf('%s em 12 meses%s · %s do sublimite de %s',
            self::brl($receita12m), $periodo, self::pct($receita12m, self::SUBLIMITE_CENTAVOS), self::brl(self::SUBLIMITE_CENTAVOS));

        if ($receita12m > self::SUBLIMITE_CENTAVOS) {
            return self::linha('CT03', $titulo, $lei, self::PENDENTE, $obtido,
                'Passou do sublimite: ICMS e ISS saem do DAS e as notas passam a destacar. Avisar o dono.');
        }
        // Inteiros: receita × 100 ≥ sublimite × 90, sem float na fronteira.
        if ($receita12m * 100 >= self::SUBLIMITE_CENTAVOS * self::AVISO_PCT) {
            return self::linha('CT03', $titulo, $lei, self::ATENCAO, $obtido, 'Acima de 90% do sublimite: avisar o dono.');
        }

        return self::linha('CT03', $titulo, $lei, self::OK, $obtido, null);
    }

    /** @param array{linhas: array<string, int>, notas_sem_xml: int} $seg */
    private function ct04(array $seg, int $receitaMes): array
    {
        $titulo = 'Segregação de receitas no PGDAS-D';
        $lei = 'LC 123/2006, art. 18 §4º e §4º-A, I';
        $soma = array_sum($seg['linhas']);
        $partes = [];
        foreach ($seg['linhas'] as $rotulo => $valor) {
            $partes[] = $rotulo . ' ' . self::brl($valor);
        }
        $obtido = implode(' · ', $partes) . ' = ' . self::brl($soma);

        if ($seg['notas_sem_xml'] > 0) {
            return self::linha('CT04', $titulo, $lei, self::ATENCAO,
                $obtido . ' · ' . $seg['notas_sem_xml'] . ' notas autorizadas sem XML para ler',
                'A segregação está incompleta: falta o XML dessas notas no arquivo.');
        }
        if ($soma !== $receitaMes) {
            return self::linha('CT04', $titulo, $lei, self::ATENCAO,
                $obtido . ' · receita do mês no Financeiro ' . self::brl($receitaMes),
                'A soma das notas difere da receita do mês: confira vendas sem nota ou nota sem venda. A receita com ST já retida sai separada, senão paga ICMS duas vezes no DAS.');
        }

        return self::linha('CT04', $titulo, $lei, self::OK, $obtido, null);
    }

    /** @param array<string, mixed> $f */
    private function ct05(array $f): array
    {
        $titulo = 'Fator R do mês';
        $lei = 'LC 123/2006, art. 18 §5º-J';
        if ($f['sem_dado']) {
            return self::linha('CT05', $titulo, $lei, self::SEM_FONTE, 'Sem receita nos 12 meses anteriores: não há Fator R para calcular.', null);
        }
        // Usa o anexo devolvido, nunca recalcula pela razão arredondada (_saida-24 §5).
        $obtido = sprintf('%s → Anexo %s', number_format((float) $f['razao'] * 100, 2, ',', '.') . '%', $f['anexo']);
        if ($f['folha_parcial']) {
            return self::linha('CT05', $titulo, $lei, self::ATENCAO,
                $obtido . ' · com a folha do ERP, sem encargos, FGTS e pró-labore',
                'Pela LC 123/2006 art. 18 §24 a folha inclui encargos, FGTS e pró-labore: confira com a folha completa antes de escolher o anexo.');
        }

        return self::linha('CT05', $titulo, $lei, self::OK, $obtido, null);
    }

    /** @param array{total: int, interestaduais: int, sem_manifestacao: int} $e */
    private function ct06(array $e): array
    {
        $titulo = 'DIFAL de compra pra uso, consumo ou ativo';
        $lei = 'LC 87/1996 · LC 190/2022';
        if ($e['total'] === 0) {
            return self::linha('CT06', $titulo, $lei, self::ATENCAO,
                'Nenhuma entrada chegou pela consulta de notas recebidas (DF-e) no mês.',
                'Confira se a consulta à SEFAZ está ativa; sem ela, o ERP não sabe das compras.');
        }
        if ($e['interestaduais'] === 0) {
            return self::linha('CT06', $titulo, $lei, self::OK, 'Nenhuma entrada de outra UF no mês', null);
        }

        // A destinação (revenda × uso/consumo/ativo) ainda não é registrada na entrada: a ADR de
        // entrada por XML (thread 11) está em proposta. Por isso pede classificação, não afirma DIFAL.
        return self::linha('CT06', $titulo, $lei, self::ATENCAO,
            $e['interestaduais'] . ' entradas de outra UF no mês',
            'Classificar cada uma: se não for revenda (uso, consumo ou ativo), há DIFAL a recolher mesmo no Simples.');
    }

    /** @param array{total: int, interestaduais: int, sem_manifestacao: int} $e */
    private function ct07(array $e): array
    {
        $titulo = 'Entradas sem manifestação';
        $lei = 'manifestação do destinatário';
        if ($e['total'] === 0) {
            return self::linha('CT07', $titulo, $lei, self::ATENCAO,
                'Nenhuma entrada chegou pela consulta de notas recebidas (DF-e) no mês.',
                'Confira se a consulta à SEFAZ está ativa.');
        }
        if ($e['sem_manifestacao'] > 0) {
            return self::linha('CT07', $titulo, $lei, self::PENDENTE,
                $e['sem_manifestacao'] . ' de ' . $e['total'] . ' entradas sem manifestação',
                'Manifestar na tela Manifesto DF-e.');
        }

        return self::linha('CT07', $titulo, $lei, self::OK, 'Todas as ' . $e['total'] . ' entradas manifestadas', null);
    }

    private function ct09(int $comRetencao): array
    {
        return self::linha('CT09', 'Retenções da NFS-e batem com o recebido', 'Lei 10.833/2003 · ISS municipal', self::SEM_FONTE,
            $comRetencao . ' NFS-e com retenção no mês · o Financeiro não registra a retenção recebida, então não há com o que comparar',
            $comRetencao > 0 ? 'Conferir as retenções dessas NFS-e no extrato até o Financeiro registrar a retenção.' : null);
    }

    private function ct10(int $semAceite): array
    {
        $titulo = 'Regras vigentes no mês sem aceite';
        if ($semAceite === 0) {
            return self::linha('CT10', $titulo, 'D-CONTADOR', self::OK, 'Todas as mudanças de regra vigentes foram aceitas', null);
        }

        return self::linha('CT10', $titulo, 'D-CONTADOR', self::PENDENTE,
            $semAceite . ' mudanças de regra vigentes no mês sem aceite', 'Revisar na lista de regras abaixo.');
    }

    // ------------------------------------------------------------------ medições

    /** @return array{autorizadas: int, canceladas: int, denegadas: int, rejeitadas: int, inutilizadas: int, em_andamento: int} */
    private function contarNotas(int $biz, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        // A rejeitada não tem `emitido_em` (só a autorização preenche): o mês dela é o do registro.
        $por = DB::table('nfe_emissoes')
            ->where('business_id', $biz)
            ->whereNull('deleted_at')
            ->whereRaw('COALESCE(emitido_em, created_at) >= ? AND COALESCE(emitido_em, created_at) < ?',
                [$de->toDateTimeString(), $ate->toDateTimeString()])
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as n')
            ->pluck('n', 'status');

        $n = fn (string ...$s): int => (int) array_sum(array_map(fn ($k) => (int) ($por[$k] ?? 0), $s));

        return [
            'autorizadas' => $n('autorizada'),
            'canceladas' => $n('cancelada'),
            'denegadas' => $n('denegada'),
            'rejeitadas' => $n('rejeitada'),
            'inutilizadas' => $n('inutilizada'),
            'em_andamento' => $n('pendente', 'enviando', 'erro_envio'),
        ];
    }

    /** Receita do mês: mesma definição do FatorRService (vendas − devoluções finalizadas). */
    private function receitaDoMes(int $biz, CarbonImmutable $de, CarbonImmutable $ate): int
    {
        $soma = fn (string $tipo): float => (float) DB::table('transactions')
            ->where('business_id', $biz)
            ->where('type', $tipo)
            ->where('status', 'final')
            ->where('transaction_date', '>=', $de->toDateTimeString())
            ->where('transaction_date', '<', $ate->toDateTimeString())
            ->sum('final_total');

        return self::centavos($soma('sell') - $soma('sell_return'));
    }

    /** @return array{linhas: array<string, int>, notas_sem_xml: int} */
    private function segregar(int $biz, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $linhas = [self::SEG_REVENDA => 0, self::SEG_REVENDA_ST => 0, self::SEG_PRODUCAO => 0, self::SEG_SERVICOS => 0];
        $semXml = 0;

        // SUPERADMIN: página pública do contador — sem sessão o escopo não filtra, e com sessão de
        // OUTRA empresa filtraria errado. A fronteira é o business do link, explícito no where.
        NfeEmissao::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $biz)
            ->where('status', 'autorizada')
            ->where('emitido_em', '>=', $de->toDateTimeString())
            ->where('emitido_em', '<', $ate->toDateTimeString())
            ->orderBy('id')
            ->chunk(200, function ($emissoes) use (&$linhas, &$semXml) {
                foreach ($emissoes as $e) {
                    try {
                        $xml = $this->danfe->obterXmlContents($e);
                    } catch (Throwable) {
                        $semXml++;

                        continue;
                    }
                    foreach (self::segregarXml($xml) as $rotulo => $valor) {
                        $linhas[$rotulo] += $valor;
                    }
                }
            });

        if (Schema::hasTable('nfse_emissoes')) {
            $linhas[self::SEG_SERVICOS] = self::centavos((float) DB::table('nfse_emissoes')
                ->where('business_id', $biz)
                ->where('status', 'emitida')
                ->where('competencia', '>=', $de->toDateString())
                ->where('competencia', '<', $ate->toDateString())
                ->sum('valor_servicos'));
        }

        return ['linhas' => $linhas, 'notas_sem_xml' => $semXml];
    }

    /**
     * Segrega os itens de UM XML de NF-e/NFC-e (puro). Item = vProd − vDesc, em centavos.
     *
     * @return array<string, int>
     */
    public static function segregarXml(string $xml): array
    {
        $out = [self::SEG_REVENDA => 0, self::SEG_REVENDA_ST => 0, self::SEG_PRODUCAO => 0];
        $dom = new DOMDocument();
        if (@$dom->loadXML($xml) === false) {
            return $out;
        }

        foreach ($dom->getElementsByTagName('det') as $det) {
            /** @var DOMElement $det */
            $cfop = self::texto($det, 'CFOP');
            $valor = self::centavosTexto(self::texto($det, 'vProd')) - self::centavosTexto(self::texto($det, 'vDesc'));
            $icms = self::texto($det, 'CSOSN') ?? self::texto($det, 'CST');

            if ($icms !== null && in_array($icms, self::ICMS_ST_RETIDO, true)) {
                $out[self::SEG_REVENDA_ST] += $valor;
            } elseif ($cfop !== null && in_array(substr($cfop, 1), self::SUFIXOS_PRODUCAO, true)) {
                $out[self::SEG_PRODUCAO] += $valor;
            } else {
                $out[self::SEG_REVENDA] += $valor;
            }
        }

        return $out;
    }

    /** @return array{total: int, interestaduais: int, sem_manifestacao: int} */
    private function entradas(int $biz, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $rows = DB::table('nfe_dfe_recebidos')
            ->where('business_id', $biz)
            ->where('data_emissao', '>=', $de->toDateTimeString())
            ->where('data_emissao', '<', $ate->toDateTimeString())
            ->get(['chave_44', 'status_manifestacao']);

        $uf = $this->resolverUfEmitente($biz);

        return [
            'total' => $rows->count(),
            'interestaduais' => $rows->filter(fn ($r) => (self::CUF[substr((string) $r->chave_44, 0, 2)] ?? $uf) !== $uf)->count(),
            'sem_manifestacao' => $rows->where('status_manifestacao', 'pendente')->count(),
        ];
    }

    private function nfseComRetencao(int $biz, CarbonImmutable $de, CarbonImmutable $ate): int
    {
        if (! Schema::hasTable('nfse_emissoes') || ! Schema::hasColumn('nfse_emissoes', 'retencao_federal_sujeito')) {
            return 0;
        }

        return DB::table('nfse_emissoes')
            ->where('business_id', $biz)
            ->where('status', 'emitida')
            ->where('competencia', '>=', $de->toDateString())
            ->where('competencia', '<', $ate->toDateString())
            ->where(fn ($q) => $q->where('valor_ret_pis', '>', 0)->orWhere('valor_ret_cofins', '>', 0)->orWhere('valor_ret_csll', '>', 0))
            ->count();
    }

    private function semAceite(int $biz, CarbonImmutable $de, CarbonImmutable $ate): int
    {
        $fimMes = $ate->subDay()->toDateString();

        return DB::table('nfe_revisoes_contador as v')
            ->join('nfe_fiscal_rules as r', 'r.id', '=', 'v.regra_id')
            ->where('v.business_id', $biz)
            ->where('r.business_id', $biz)
            ->whereNull('r.deleted_at')
            ->whereIn('v.status', ['pendente', 'ajuste_pedido'])
            ->where(fn ($q) => $q->whereNull('r.valida_de')->orWhere('r.valida_de', '<=', $fimMes))
            ->where(fn ($q) => $q->whereNull('r.valida_ate')->orWhere('r.valida_ate', '>=', $de->toDateString()))
            ->count();
    }

    // ------------------------------------------------------------------ apoio

    private static function linha(string $id, string $titulo, string $lei, string $situacao, string $obtido, ?string $acao): array
    {
        return ['id' => $id, 'titulo' => $titulo, 'lei' => $lei, 'situacao' => $situacao, 'obtido' => $obtido, 'acao' => $acao];
    }

    private static function texto(DOMElement $el, string $tag): ?string
    {
        $n = $el->getElementsByTagName($tag)->item(0);

        return $n === null ? null : trim($n->textContent);
    }

    /** "1234.56" → 123456. Sem float: o XML traz no máximo 2 casas em vProd/vDesc. */
    private static function centavosTexto(?string $v): int
    {
        if ($v === null || $v === '') {
            return 0;
        }
        [$int, $dec] = array_pad(explode('.', $v, 2), 2, '0');

        return (int) $int * 100 + (int) str_pad(substr($dec, 0, 2), 2, '0');
    }

    private static function centavos(float $v): int
    {
        return (int) round($v * 100);
    }

    private static function brl(int $centavos): string
    {
        return 'R$ ' . number_format($centavos / 100, 2, ',', '.');
    }

    private static function pct(int $parte, int $todo): string
    {
        return number_format($parte * 100 / $todo, 1, ',', '.') . '%';
    }

    private static function data(string $iso): string
    {
        return CarbonImmutable::parse($iso)->format('d/m/Y');
    }
}
