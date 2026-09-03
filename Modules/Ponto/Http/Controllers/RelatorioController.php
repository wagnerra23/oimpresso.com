<?php

namespace Modules\Ponto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ponto\Entities\Colaborador;

class RelatorioController extends Controller
{
    /**
     * Único relatório do catálogo com gerador vivo hoje.
     *
     * Ele NÃO é gerado aqui: o PDF do espelho existe e funciona desde sempre pelo
     * fluxo F3 do SDD (`EspelhoController@imprimir`), e é **por colaborador**.
     * Este controller é o atalho do catálogo pra lá — nunca um segundo gerador
     * (duplicar o `ReportService::espelhoPdf` seria abrir 2º dono do mesmo tema).
     */
    private const CHAVE_ESPELHO = 'espelho';

    /**
     * Lista de relatórios disponíveis.
     *
     * Chave `disponivel`: se o relatório já foi implementado em ReportService.
     * Hoje só `espelho` está pronto (funcional); os demais retornam RuntimeException.
     * Quando cada um for implementado, trocar `false` por `true`.
     *
     * Chave `requer_colaborador`: se o relatório sai POR COLABORADOR e não em lote.
     * O front usa isso pra travar o botão enquanto o operador não escolher um — quem
     * decide isso é o backend, não o `.tsx` (hardcodar 'espelho' no front faria a
     * regra viver em 2 lugares e drifar no primeiro relatório novo).
     */
    public function index(Request $request): Response
    {
        $businessId = session('business.id') ?: $request->user()->business_id;

        $relatorios = [
            ['chave' => 'afd',         'titulo' => 'AFD (Portaria 671/2021)', 'descricao' => 'Arquivo Fonte de Dados',          'icone' => 'FileText',      'cor' => 'blue',    'disponivel' => false, 'requer_colaborador' => false],
            ['chave' => 'afdt',        'titulo' => 'AFDT',                     'descricao' => 'Arquivo Fonte de Dados Tratados', 'icone' => 'FileCheck',     'cor' => 'blue',    'disponivel' => false, 'requer_colaborador' => false],
            ['chave' => 'aej',         'titulo' => 'AEJ',                      'descricao' => 'Apuração Eletrônica de Jornada',  'icone' => 'FileSpreadsheet','cor' => 'blue',    'disponivel' => false, 'requer_colaborador' => false],
            ['chave' => self::CHAVE_ESPELHO, 'titulo' => 'Espelho de Ponto',   'descricao' => 'PDF mensal por colaborador',      'icone' => 'ClipboardList', 'cor' => 'emerald', 'disponivel' => true,  'requer_colaborador' => true],
            ['chave' => 'he',          'titulo' => 'Horas Extras',             'descricao' => 'Relatório consolidado do mês',    'icone' => 'Clock',         'cor' => 'amber',   'disponivel' => false, 'requer_colaborador' => false],
            ['chave' => 'banco-horas', 'titulo' => 'Banco de Horas',           'descricao' => 'Saldos e movimentações',          'icone' => 'PiggyBank',     'cor' => 'emerald', 'disponivel' => false, 'requer_colaborador' => false],
            ['chave' => 'atrasos',     'titulo' => 'Atrasos e Faltas',         'descricao' => 'Por colaborador/departamento',    'icone' => 'AlertCircle',   'cor' => 'red',     'disponivel' => false, 'requer_colaborador' => false],
            ['chave' => 'esocial',     'titulo' => 'Eventos eSocial',          'descricao' => 'S-1010 / S-2230 / S-2240',        'icone' => 'Send',          'cor' => 'violet',  'disponivel' => false, 'requer_colaborador' => false],
        ];

        return Inertia::render('Ponto/Relatorios/Index', [
            'relatorios'    => $relatorios,
            'colaboradores' => $this->buildColaboradoresElegiveis($businessId),
        ]);
    }

    /**
     * Colaboradores que o operador pode escolher no filtro.
     *
     * ⚠️ SEM PII: só `id` e `nome`. `cpf` e `pis` existem na entity e NÃO entram
     * aqui — este payload alimenta um `<Select>`, e dado sensível que não é exibido
     * não deve trafegar (proibicoes.md §Multi-tenant; a tela de Colaboradores é a
     * única do módulo que legitimamente carrega CPF/PIS).
     *
     * ⚠️ EAGER de propósito, contra o default `Inertia::defer` do módulo: esta prop
     * NÃO é conteúdo, é o que decide o estado (habilitado/travado) do botão "Gerar"
     * dos relatórios `requer_colaborador`. Diferida, o primeiro paint renderizaria
     * "Nenhum colaborador com ponto" — uma frase FALSA — até a closure resolver.
     * A query é uma só, indexada por `business_id`, sobre tabela de cadastro.
     */
    private function buildColaboradoresElegiveis(int $businessId): array
    {
        return Colaborador::where('business_id', $businessId)
            ->where('controla_ponto', true)
            ->whereNull('desligamento')
            ->with(['user:id,first_name,last_name'])
            ->orderBy('matricula')
            ->get(['id', 'matricula', 'user_id'])
            ->map(fn ($c) => [
                'id'   => $c->id,
                'nome' => trim(
                    optional($c->user)->first_name . ' ' . optional($c->user)->last_name
                ) ?: ($c->matricula ?? '—'),
            ])
            ->all();
    }

    /**
     * Atalho do catálogo para o gerador do relatório.
     *
     * Só o `espelho` tem destino hoje. Os outros 7 seguem `abort(501)` — e seguem
     * `disponivel: false` no catálogo, então o botão deles nem fica clicável: é o
     * `CU-PONTO-14` ("o catálogo não promete o que não entrega") cumprido dos dois
     * lados, front e back.
     *
     * ANTES desta mudança o 501 valia pra QUALQUER chave, inclusive `espelho` —
     * que é `disponivel: true`. Ou seja: o único botão habilitado da tela levava a
     * um 501. O PDF sempre existiu (F3); o catálogo é que não estava ligado nele.
     */
    public function gerar(Request $request, string $chave)
    {
        if ($chave !== self::CHAVE_ESPELHO) {
            abort(501, "Implementar geração de '{$chave}' em ReportService.");
        }

        // `colaborador` é REQUIRED de propósito: o espelho é por pessoa, e não
        // existe "espelho de todos" (seria relatório em lote, que não existe —
        // decisão [W] 2026-08-28). Sem isso, um pedido sem colaborador cairia
        // silenciosamente em algum default e entregaria o documento ERRADO — e
        // espelho de ponto é peça de fiscalização (Portaria MTP 671/2021 Art. 85).
        $dados = $request->validate([
            'colaborador' => 'required|integer|min:1',
            'periodo'     => 'nullable|date_format:Y-m',
        ]);

        $businessId = session('business.id') ?: $request->user()->business_id;

        // Tier 0 (ADR 0093). O `EspelhoController@imprimir` também valida o tenant
        // (`Colaborador::where('business_id')->findOrFail()`), então isto é a SEGUNDA
        // camada, não a única — mas redirecionar o operador para um 404 do outro
        // controller é pior que recusar aqui, e deixar o id alheio atravessar este
        // método faria a defesa depender de quem está do outro lado do redirect.
        abort_unless(
            Colaborador::where('business_id', $businessId)
                ->whereKey($dados['colaborador'])
                ->exists(),
            404
        );

        return redirect()->route('ponto.espelho.imprimir', [
            'colaborador' => $dados['colaborador'],
            'mes'         => $dados['periodo'] ?? now()->format('Y-m'),
        ]);
    }
}
