<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Services\ApuracaoService;
use RuntimeException;

/**
 * Fixture da secao METAS do Painel da Jana (`/ia`) no gate visual L2.
 *
 * ── O DEFEITO QUE ELE FECHA (medido 2026-09-21) ─────────────────────────────
 *
 * O estado `default` fotografava a secao METAS **vazia** — indistinguivel do
 * estado `empty`. Nao e figura de linguagem: `Pages/Jana/Index.tsx` decide por
 * `metas.length === 0`, e nos dois casos renderiza o MESMO card
 * `data-contract="painel-metas-vazio"`. Logo regressao em grade, card, barra de
 * progresso, farol ou projecao passava batida — a baseline nunca teve um card de
 * meta pra perder.
 *
 * As duas contagens que estabelecem isso, com o comando ao lado:
 *
 *   - `$seedJanaVisregFlow` (routes/web.php) semeia UMA transacao vencida e zero
 *     metas: busca por "meta" (case-insensitive) no corpo da closure devolve 0.
 *   - nenhum dos 9 seeders `Visreg*` semeia meta:
 *     `rg -l -e jana_metas -e MetaPeriodo -e 'Entities.Meta' database/seeders/`
 *     devolve rc=1 (controle positivo: o mesmo `rg` com `business_id` devolve 18).
 *
 * E a mesma doenca que o proprio `routes/web.php` ja documenta pros casos irmaos
 * — "dado ausente vira snapshot de tela vazia com nome de default" —, so que na
 * Jana ninguem tinha medido.
 *
 * ── POR QUE AS DATAS SAO LITERAIS E CONGELADAS ──────────────────────────────
 *
 * `Meta::periodoAtual` filtra `data_ini <= now() AND data_fim >= now()` com o
 * `now()` do PHP. E o `now()` do PHP **ESTA congelado no request HTTP**:
 * `AppServiceProvider::boot()` chama `Carbon::setTestNow(config('visreg.freeze_clock'))`
 * antes de qualquer controller, e o docblock de la declara o alcance —
 * "governa `now()`, `Carbon::now()` e o facade `Date`". A env e escrita no `.env`
 * do servidor pelo proprio `visual-regression.yml` (`VISREG_FREEZE_CLOCK` = o
 * mesmo instante de `INSTANTE` abaixo, no heredoc que precede o `key:generate`),
 * e o comentario de la confirma o lado PHP: "o setTestNow so congelava o relogio
 * do PHP".
 *
 * Isso importa porque e o OPOSTO do caso vizinho: o predicado do overdue da Jana
 * usa `CURDATE()` do BANCO, que o `setTestNow` nao governa — por isso LA a data
 * tem de ser fixa e antiga. AQUI o relogio lido e o congelado, entao um periodo
 * fixo que CONTENHA o instante congelado resolve `periodoAtual` e ainda da rotulo
 * estavel ("junho/2026", montado por `periodoLabel` a partir de data_ini/data_fim).
 * Nao ha tensao entre "periodo vigente" e "rotulo estavel": as duas coisas saem do
 * mesmo literal.
 *
 * ⚠️ MANTENHA `INSTANTE`/`PERIODO_*` ALINHADOS AO `VISREG_FREEZE_CLOCK`. Mover o
 * relogio pra fora de `PERIODO_INI..PERIODO_FIM` faz `periodoAtual` virar null nas
 * 5 metas de uma vez, `projecao` virar null, e TODAS cairem em `cinza` — a secao
 * continuaria com 5 cards, entao contagem de card nao flagraria. Quem flagra e a
 * auto-verificacao no fim deste seeder, que compara o farol COMPUTADO com o
 * declarado e explode. Nunca `now()`, nunca data relativa.
 *
 * ── COBERTURA (o que cada meta exercita) ────────────────────────────────────
 *
 *   verde · amarelo · vermelho          → as 3 faixas de `ApuracaoService::farol`
 *   sem apuracao                        → `projecao` null -> `cinza` + o contrato
 *                                         `painel-meta-apurando`
 *   1 apuracao so                       → `painel-meta-sem-historico` (o Sparkline
 *                                         exige >= 2 pontos)
 *
 * De quebra, as 4 unidades do enum (`R$`/`qtd`/`%`/`dias`) e 4 agregacoes
 * aparecem — entao regressao em `formatValue` tambem passa a ter onde doer.
 *
 * VALORES DISTINTOS entre metas e entre apuracoes da mesma meta, de proposito:
 * `ultimaApuracao` e `latestOfMany('data_ref')` e `apuracoes` vem
 * `orderBy('data_ref')` — empate deixaria a ordem por conta do MySQL, e duas
 * linhas trocando de lugar entre execucoes e flake de baseline com cara de
 * regressao (mesma razao declarada no `VisregJanaChatSeeder`).
 *
 * ── TENANCY ─────────────────────────────────────────────────────────────────
 *
 * So biz=1 (ADR 0101/0358). ⛔ NUNCA biz=98: ele e o `Tenant Vazio` do estado
 * `empty` (`VisregEmptyTenantSeeder::BIZ_EMPTY`) — semear meta la quebraria
 * justamente o estado que a Jana ganhou pra fotografar a secao vazia. ⛔ E nunca
 * biz=4, que e cliente real.
 *
 * O `where('business_id')` explicito da verificacao NAO e redundante: quando este
 * seeder roda pelo lever, a sessao ja foi esvaziada (`session()->forget([...])`
 * em `/_visreg-state`), e `ScopeByBusiness::apply` retorna cedo sem filtro nesse
 * caso ("Sessao sem user_id -> sem filtro"). O filtro de tenant e este, nao o
 * scope — e por isso ele e escrito a mao em vez de herdado.
 *
 * ⚠️ VAZA PRO L1 SE NAO FOR LIMPO — mesmo vetor que o lever do overdue ja
 * registrou: a suite do L2 nao usa `RefreshDatabase` e o `visreg-flake-retry.sh`
 * re-roda o arquivo inteiro. A limpeza cirurgica (por slug exato, nunca por
 * range) vive no `visregLimparFixturesDeEstado()` do IsolatedStatesBaselineTest,
 * e as tabelas filhas saem por `ON DELETE CASCADE`.
 *
 * @see routes/web.php                                     (`$seedJanaVisregFlow` — quem invoca)
 * @see tests/Browser/CoreScreens/IsolatedStatesBaselineTest.php (a limpeza)
 * @see Modules/Jana/Services/ApuracaoService.php          (farol/projecao — a regra verificada)
 */
class VisregJanaMetasSeeder extends Seeder
{
    public const BUSINESS_ID = 1;

    /** Contem o `VISREG_FREEZE_CLOCK` — ver o ⚠️ do docblock. */
    public const PERIODO_INI = '2026-06-01';

    public const PERIODO_FIM = '2026-06-30';

    /** Igual ao `VISREG_FREEZE_CLOCK` do workflow. */
    public const INSTANTE = '2026-06-11 12:00:00';

    /** Marca da apuracao desta fixture — entra na UNIQUE `(meta_id, data_ref, fonte_query_hash)`. */
    public const FONTE_HASH = 'visreg-fixture';

    /**
     * O fixture inteiro, declarado.
     *
     * `farol` NAO e dado gravado: e a EXPECTATIVA que a auto-verificacao confere
     * contra o que o `ApuracaoService` calcula. Se as fronteiras -5/-15 mudarem,
     * se a projecao mudar, ou se o relogio congelado sair do periodo, o seeder
     * explode em vez de fotografar 5 cards cinza calados.
     *
     * A aritmetica (progresso = 10,5/29 = 0,3620689655 no instante congelado):
     *   receita    alvo 120000,00  projetado 43448,28  realizado 48900,00  desvio +12,55%  verde
     *   margem     alvo     90,00  projetado    32,59  realizado    29,30  desvio -10,08%  amarelo
     *   pedidos    alvo    800,00  projetado   289,66  realizado   188,00  desvio -35,10%  vermelho
     *   prazo      alvo     45,00  (sem apuracao)                                          cinza
     *   recorrente alvo 250000,00  projetado 90517,24  realizado 96000,00  desvio  +6,06%  verde
     *
     * Margem ate a fronteira mais proxima: 17,55 / 4,92 / 20,10 / — / 11,06 pp.
     */
    private const FIXTURE = [
        [
            'slug' => 'visreg-jana-receita',
            'nome' => 'Receita do mes',
            'unidade' => 'R$',
            'tipo_agregacao' => 'soma',
            'valor_alvo' => 120000.00,
            'apuracoes' => [
                ['2026-06-03', 12400.00],
                ['2026-06-07', 28750.00],
                ['2026-06-11', 48900.00],
            ],
            'farol' => 'verde',
        ],
        [
            'slug' => 'visreg-jana-margem',
            'nome' => 'Margem de contribuicao',
            'unidade' => '%',
            'tipo_agregacao' => 'media',
            'valor_alvo' => 90.00,
            'apuracoes' => [
                ['2026-06-03', 31.40],
                ['2026-06-07', 30.10],
                ['2026-06-11', 29.30],
            ],
            'farol' => 'amarelo',
        ],
        [
            'slug' => 'visreg-jana-pedidos',
            'nome' => 'Pedidos faturados',
            'unidade' => 'qtd',
            'tipo_agregacao' => 'contagem',
            'valor_alvo' => 800.00,
            'apuracoes' => [
                ['2026-06-03', 61.00],
                ['2026-06-07', 124.00],
                ['2026-06-11', 188.00],
            ],
            'farol' => 'vermelho',
        ],
        [
            // SEM apuracao de proposito: `projecao` devolve null (o `$ultima` e null),
            // o farol vira `cinza` e o card cai no `data-contract="painel-meta-apurando"`.
            'slug' => 'visreg-jana-prazo',
            'nome' => 'Prazo medio de entrega',
            'unidade' => 'dias',
            'tipo_agregacao' => 'media',
            'valor_alvo' => 45.00,
            'apuracoes' => [],
            'farol' => 'cinza',
        ],
        [
            // UMA apuracao so: o `Sparkline` exige >= 2 pontos, entao este card
            // renderiza `data-contract="painel-meta-sem-historico"` — com farol
            // `verde`, pra provar que "sem historico" e independente do farol.
            'slug' => 'visreg-jana-recorrente',
            'nome' => 'Receita recorrente',
            'unidade' => 'R$',
            'tipo_agregacao' => 'ultimo',
            'valor_alvo' => 250000.00,
            'apuracoes' => [
                ['2026-06-11', 96000.00],
            ],
            'farol' => 'verde',
        ],
    ];

    /**
     * Slugs exatos — a limpeza do L1 deleta por esta lista, nunca por `LIKE`.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_column(self::FIXTURE, 'slug');
    }

    public function run(): void
    {
        // Pre-condicao AUSENTE sai em silencio (mesmo idioma dos levers irmaos: o
        // insert violaria FK e derrubaria o request do harness). Fixture MENTINDO
        // explode — ver `verificar()`. Os dois casos sao diferentes e tem desfechos
        // diferentes de proposito.
        $business = DB::table('business')->where('id', self::BUSINESS_ID)->exists();

        if (! $business) {
            return;
        }

        $jaSemeado = DB::table('jana_metas')
            ->where('business_id', self::BUSINESS_ID)
            ->whereIn('slug', self::slugs())
            ->count() === count(self::FIXTURE);

        if (! $jaSemeado) {
            $this->semear();
        }

        $this->verificar();
    }

    private function semear(): void
    {
        foreach (self::FIXTURE as $item) {
            // DB::table (nao Eloquent) pelo mesmo motivo do VisregJanaChatSeeder:
            // sem global scope no caminho, o seed e literal e previsivel.
            DB::table('jana_metas')->updateOrInsert(
                [
                    'business_id' => self::BUSINESS_ID,
                    'slug' => $item['slug'],
                ],
                [
                    'nome' => $item['nome'],
                    'unidade' => $item['unidade'],
                    'tipo_agregacao' => $item['tipo_agregacao'],
                    'ativo' => 1,
                    // NULL de proposito: `criada_por_user_id` nao entra no payload do
                    // Painel (conferido em `IndexController::buildMetasPayload`), entao
                    // nao ha render a estabilizar, e a coluna e nullable.
                    'criada_por_user_id' => null,
                    'origem' => 'seed',
                    'created_at' => self::INSTANTE,
                    'updated_at' => self::INSTANTE,
                ]
            );

            $metaId = (int) DB::table('jana_metas')
                ->where('business_id', self::BUSINESS_ID)
                ->where('slug', $item['slug'])
                ->value('id');

            // Idempotente por (meta_id, data_ini): a tabela nao tem UNIQUE, so o
            // indice (meta_id, data_ini) — o par e a chave logica do periodo.
            DB::table('jana_meta_periodos')->updateOrInsert(
                [
                    'meta_id' => $metaId,
                    'data_ini' => self::PERIODO_INI,
                ],
                [
                    'tipo_periodo' => 'mes',
                    'data_fim' => self::PERIODO_FIM,
                    'valor_alvo' => $item['valor_alvo'],
                    'trajetoria' => 'linear',
                    'created_at' => self::INSTANTE,
                    'updated_at' => self::INSTANTE,
                ]
            );

            // Idempotente pela UNIQUE `(meta_id, data_ref, fonte_query_hash)`.
            foreach ($item['apuracoes'] as [$dataRef, $valor]) {
                DB::table('jana_meta_apuracoes')->updateOrInsert(
                    [
                        'meta_id' => $metaId,
                        'data_ref' => $dataRef,
                        'fonte_query_hash' => self::FONTE_HASH,
                    ],
                    [
                        'valor_realizado' => $valor,
                        'calculado_em' => self::INSTANTE,
                        'created_at' => self::INSTANTE,
                        'updated_at' => self::INSTANTE,
                    ]
                );
            }

            // Idempotente pela UNIQUE `(meta_id)`. A coluna tem CHECK json_valid.
            DB::table('jana_meta_fontes')->updateOrInsert(
                ['meta_id' => $metaId],
                [
                    'driver' => 'sql',
                    'config_json' => json_encode(['sql' => 'select 0', 'fixture' => 'visreg']),
                    'cadencia' => 'diaria',
                    'created_at' => self::INSTANTE,
                    'updated_at' => self::INSTANTE,
                ]
            );
        }
    }

    /**
     * A SONDA — e ela roda no chokepoint que o fluxo atravessa.
     *
     * Uma sonda de contagem no step "Seed demo tenant" do workflow contaria ZERO:
     * este seeder e invocado pelo lever `/_visreg-state`, ou seja, DENTRO do
     * request do teste, muito depois daquele step. Sonda em ponto que o fluxo nao
     * atravessa e teatro, e o proprio workflow ja registra a licao vizinha ("esta
     * sonda mede o SEED, nao o RENDER").
     *
     * Aqui a verificacao e do FAROL COMPUTADO, nao da contagem de linha, porque a
     * degradacao silenciosa desta fixture nao e "sumiu": e "virou 5 cards cinza"
     * — que uma contagem de card nao distingue de 5 cards certos.
     *
     * Explode (RuntimeException) em vez de retornar: o request vira 500, o
     * `assertSee` do IsolatedStatesBaselineTest reprova, e o job falha. Quem le a
     * falha acha esta mensagem no log com o farol esperado e o obtido.
     */
    private function verificar(): void
    {
        $esperado = array_column(self::FIXTURE, 'farol', 'slug');

        $metas = Meta::where('business_id', self::BUSINESS_ID)
            ->whereIn('slug', array_keys($esperado))
            ->with(['periodoAtual', 'ultimaApuracao'])
            ->get();

        if ($metas->count() !== count($esperado)) {
            throw new RuntimeException(sprintf(
                'VisregJanaMetasSeeder: esperava %d metas no biz=%d, encontrou %d. '
                . 'Fixture pela metade fotografa estado que ninguem declarou.',
                count($esperado),
                self::BUSINESS_ID,
                $metas->count()
            ));
        }

        $apuracao = app(ApuracaoService::class);

        foreach ($metas as $meta) {
            $obtido = $apuracao->farol($meta);

            if ($obtido !== $esperado[$meta->slug]) {
                throw new RuntimeException(sprintf(
                    'VisregJanaMetasSeeder: meta "%s" calculou farol "%s", o fixture declara "%s". '
                    . 'Suspeito n.1: VISREG_FREEZE_CLOCK saiu de %s..%s (periodoAtual vira null e '
                    . 'TODAS caem em cinza). Suspeito n.2: as fronteiras -5/-15 do ApuracaoService '
                    . 'mudaram. NAO regrave a baseline — conserte a causa.',
                    $meta->slug,
                    $obtido,
                    $esperado[$meta->slug],
                    self::PERIODO_INI,
                    self::PERIODO_FIM
                ));
            }
        }
    }
}
