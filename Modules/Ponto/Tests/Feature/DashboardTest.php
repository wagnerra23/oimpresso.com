<?php

namespace Modules\Ponto\Tests\Feature;

/**
 * Feature test do Dashboard do PontoWR2.
 *
 * @covers-us US-PONT-006
 */
class DashboardTest extends PontoTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function dashboard_exige_autenticacao(): void
    {
        $this->get('/ponto')->assertRedirect('/login');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function dashboard_renderiza_componente_correto_com_props_esperados(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/ponto');

        $this->assertInertiaComponent($response, 'Ponto/Dashboard/Index');

        // ── CONTRATO DEFER (corrigido 2026-08-24) ────────────────────────────────
        // Este caso afirmava que as props caras vinham no PRIMEIRO render. Elas NAO
        // vem: o Controller as entrega via `Inertia::defer` (RUNBOOK-inertia-defer-pattern
        // + proibicoes.md §Sempre-fazer), e prop deferida ausente do payload inicial E o
        // ponto do padrao. O irmao `DashboardDeferredContractTest` PROVA o defer e passava
        // verde ao lado deste — os dois no mesmo modulo, contradizendo-se, porque NENHUM
        // rodava em lane. Medido no CT100 em 2026-08-23.
        //
        // Agora o caso prova o contrato de verdade, nos DOIS lados: ausente no eager,
        // presente e bem-formado no partial reload.
        $props = $response->json('props');
        foreach (['kpis', 'aprovacoes', 'atividade_recente', 'serie_7dias'] as $deferida) {
            $this->assertArrayNotHasKey(
                $deferida,
                $props,
                "Prop '{$deferida}' e Inertia::defer — nao pode vir no primeiro render."
            );
        }
        // eager de verdade continua vindo (senao o assert acima passaria por tela vazia)
        $this->assertArrayHasKey('server_time', $props);

        $partial = $this->inertiaPartialGet(
            '/ponto',
            ['kpis', 'serie_7dias'],
            'Ponto/Dashboard/Index'
        );
        $partial->assertStatus(200);
        $resolvidas = $partial->json('props');

        $this->assertArrayHasKey('kpis', $resolvidas);

        // SUBCONJUNTO, nao igualdade de conjunto (corrigido 2026-08-24).
        //
        // Estava `assertEqualsCanonicalizing` com 6 chaves exatas, e o CI reprovou: o
        // #6160 ("aplica o Painel do prototipo") somou `aprovacoes_urgentes` e
        // `ultima_marcacao`, entao hoje sao 8. Igualdade de conjunto quebra a cada KPI
        // NOVO — e um KPI novo nao e regressao, e produto.
        //
        // Trocar por "exatamente estas 8" so adiaria o problema E tornaria o assert um
        // espelho da implementacao (§5 2026-06-05: teste que deriva do codigo, nao do
        // contrato). O que este caso tem a defender e o inverso: nenhuma das chaves que
        // o painel PROMETE pode sumir do payload. Adicionar e livre; remover reprova.
        //
        // A copy e a ORDEM dos KPIs na tela sao contrato do `ponto-painel.contract.json`,
        // e quem as guarda e o `PontoDashboardContratoTest` (UC-PAINEL-01) — nao aqui.
        foreach (['colaboradores_ativos', 'presentes_agora', 'atrasos_hoje',
                  'faltas_hoje', 'he_mes_minutos', 'aprovacoes_pendentes'] as $kpi) {
            $this->assertArrayHasKey(
                $kpi,
                $resolvidas['kpis'],
                "KPI '{$kpi}' sumiu do payload — o painel promete esse numero."
            );
        }

        // Série tem 7 dias (hoje + 6 anteriores)
        $this->assertCount(7, $resolvidas['serie_7dias']);
        $this->assertEqualsCanonicalizing(
            ['data', 'label', 'trabalhado', 'he'],
            array_keys($resolvidas['serie_7dias'][0])
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function dashboard_shell_menu_contem_ponto_wr2(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/ponto');

        $menu = $response->json('props.shell.menu');
        $this->assertIsArray($menu);
        $this->assertGreaterThan(0, count($menu), 'Menu do shell deve ter pelo menos 1 item');
    }

    /**
     * Forma do item "Ponto" na sidebar = protótipo (`prototipo-ui/cowork/Wagner/data.jsx`, grupo RH:
     * `{ id: "ponto", label: "Ponto" }`, 1º do grupo), soberano no eixo FORMA (ADR UI-0029).
     *
     * Até 2026-09-28 o DataController declarava `$menu->dropdown(...)`: o shell recebia o item com
     * `href: "/#"` e 12 `children`, e a sidebar o desenhava como botão sem link, depois de HRM e
     * Essenciais (medido em produção, biz=1). Este teste lê o menu que o shell ENTREGA, não o fonte.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function sidebar_item_ponto_e_link_direto_antes_do_hrm(): void
    {
        $this->actAsAdmin();

        // Pré-condição: o `modifyAdminMenu` só publica o Ponto se o business tiver o pacote
        // `ponto_module` OU se o usuário for superadmin E existir `ponto_version` na tabela
        // `system` (ModuleUtil::isModuleInstalled). No CI nenhuma das duas vale (medido: o item
        // não vinha no menu). Montamos a 2ª SEM deixar rastro: superadmin só em memória
        // (Gate::before, sem tocar papel) e a linha de `system` numa transação revertida no fim
        // — a base do CT 100 persiste entre runs (§5 2026-09-18).
        \Illuminate\Support\Facades\Gate::before(fn ($user, $ability) => $ability === 'superadmin' ? true : null);
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            if (empty(\App\System::getProperty('ponto_version'))) {
                \Illuminate\Support\Facades\DB::table('system')->insert(['key' => 'ponto_version', 'value' => 'teste']);
            }
            $this->assertSidebarItemPonto($this->inertiaGet('/ponto'));
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }

    private function assertSidebarItemPonto($response): void
    {
        $menu = (array) $response->json('props.shell.menu');
        $rotulo = __('pontowr2::ponto.module_label');
        $idx = null;
        foreach ($menu as $i => $item) {
            if (($item['label'] ?? null) === $rotulo) {
                $idx = $i;
                break;
            }
        }

        // Anti-vácuo (LC-13): sem o item, os asserts abaixo não mediriam nada.
        $this->assertNotNull($idx, "O shell não entregou o item \"{$rotulo}\" no menu — o caso não exerce a forma do item.");
        $ponto = $menu[$idx];

        $this->assertEmpty(
            $ponto['children'] ?? [],
            'O item Ponto não pode ter filhos: com filhos a sidebar o desenha como botão de dropdown sem link, '
            . 'e o protótipo desenha um item simples.'
        );
        $this->assertStringEndsWith('/ponto', (string) ($ponto['href'] ?? ''),
            'O item Ponto tem de levar ao painel do Ponto (link direto, ADR 0180), não a "/#".'
        );

        // O PontoSubNav monta as abas das telas do Ponto a partir destes dois campos.
        $this->assertNotEmpty($ponto['ghosts'] ?? [], 'Os ghosts do Ponto alimentam as abas das telas — não podem sumir.');
        $this->assertArrayHasKey('primary', $ponto, 'O primary alimenta a ação do cabeçalho das telas do Ponto.');
        // [W] 2026-09-29: o destino é o painel (/ponto) e não existe tela web de bater ponto,
        // então o rótulo antigo "Bater ponto" prometia uma ação inexistente.
        $this->assertSame('Painel do ponto', $ponto['primary']['label'] ?? null,
            'O primary leva ao painel do Ponto — o rótulo tem de dizer isso, não "Bater ponto".'
        );
        // [W] 2026-09-29: o botão só navega — o PageHeaderTabs tira o "+" de criação quando
        // o primary declara `acao: navegar` (o adapter repassa o array inteiro).
        $this->assertSame('navegar', $ponto['primary']['acao'] ?? null,
            'O primary do Ponto tem de declarar acao=navegar, senão o botão aparece como "+ Painel do ponto".'
        );

        // Ordem do protótipo: Ponto antes do HRM. Só mede quando o HRM também está no menu.
        foreach ($menu as $i => $item) {
            if (($item['label'] ?? null) === 'HRM') {
                $this->assertLessThan($i, $idx, 'No protótipo o Ponto é o 1º item do grupo RH, antes do HRM.');
            }
        }
    }
}
