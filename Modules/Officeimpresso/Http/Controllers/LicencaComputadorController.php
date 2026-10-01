<?php

namespace Modules\Officeimpresso\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Services\FeatureFlagService;
use Modules\Officeimpresso\Entities\Licenca_Computador;
use Modules\Officeimpresso\Entities\LicencaLog;
use App\Support\Privacy\PiiRedactor;
use Modules\Officeimpresso\Http\Requests\StoreLicencaRequest;
use Modules\Officeimpresso\Http\Requests\RevokeLicencaRequest;
use Modules\Officeimpresso\Services\LicencaService;
use App\Business;
use Modules\Superadmin\Entities\Subscription;
use Modules\Superadmin\Entities\Package;

/**
 * Wave 16 governance D4 Architecture: Controller magro, regras de negocio
 * delegadas a LicencaService (Service injetado via DI no constructor).
 */
class LicencaComputadorController extends Controller
{
    /** Flag do caminho React da lista (`useV2<Modulo><Tela>`), default OFF — RUNBOOK-licencas §F2. */
    private const FLAG_V2 = 'useV2OfficeimpressoLicencas';

    public function __construct(private LicencaService $licencaService)
    {
    }

    /**
     * Autoriza a LEITURA da gestão de licenças (empresas licenciadas, máquinas,
     * pacote). Aceita o `superadmin` (acesso histórico) OU a permissão delegável
     * `officeimpresso.access`, que pode ser concedida ao suporte com login
     * próprio SEM abrir o Financeiro (gated por `superadmin`).
     *
     * Antes desta guarda o grupo de rotas /officeimpresso/* pedia só `auth` —
     * o menu escondia os links (DataController::modifyAdminMenu) mas a URL
     * direta era acessível a QUALQUER usuário autenticado, de qualquer
     * business. Esconder link não é autorização.
     */
    private function authorizeAccess(): void
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('officeimpresso.access'),
            403,
            'Unauthorized action.'
        );
    }

    /**
     * Autoriza ESCRITA em máquina individual (liberar/bloquear, criar, editar).
     * É a tarefa de assistência do dia a dia — delegável ao suporte via
     * `officeimpresso.licencas.gerenciar`.
     *
     * Ações que atingem a EMPRESA inteira (businessupdate, businessbloqueado) e
     * a exclusão têm guardas próprias — ter `gerenciar` NÃO as concede.
     */
    private function authorizeGerenciar(): void
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('officeimpresso.licencas.gerenciar'),
            403,
            'Unauthorized action.'
        );
    }

    /**
     * Autoriza escopo EMPRESA INTEIRA — versão obrigatória de todos os desktops
     * do cliente (businessupdate) e bloqueio/liberação do cliente inteiro
     * (businessbloqueado).
     *
     * Delegável via `officeimpresso.empresa.gerenciar` (decisão [W] 2026-07-30:
     * "pode liberar" — o suporte assume a gestão de licenças ponta a ponta, sem
     * o superadmin como gargalo). Ambas as ações são REVERSÍVEIS: bloquear e
     * liberar são o mesmo toggle, e a versão obrigatória se reescreve.
     *
     * Separada de authorizeExcluir() de propósito — bloquear o cliente é
     * reversível, apagar o registro não é. Mesma lógica que já separa `access`
     * (ver) de `licencas.gerenciar` (mexer).
     */
    private function authorizeEmpresa(): void
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('officeimpresso.empresa.gerenciar'),
            403,
            'Unauthorized action.'
        );
    }

    /**
     * Autoriza EXCLUSÃO de licença — destrutivo e irreversível (o registro sai
     * do banco; o histórico de acesso em licenca_log fica órfão).
     *
     * Delegável via `officeimpresso.licencas.excluir`, permissão própria e
     * separada: quem pode bloquear uma máquina não deveria apagá-la por tabela.
     */
    private function authorizeExcluir(): void
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('officeimpresso.licencas.excluir'),
            403,
            'Unauthorized action.'
        );
    }

    /**
     * Lista de licenças (máquinas). Caminho dual: com a flag `useV2OfficeimpressoLicencas`
     * ligada responde a tela Inertia `Officeimpresso/Licencas/Index` (thread Officeimpresso/06
     * PR-a, 2026-10-01); desligada, segue o Blade de sempre — rota de fuga até o cutover.
     * Só a flag decide: o first load do Inertia não manda `X-Inertia` (RUNBOOK-licencas §F2).
     *
     * @return \Illuminate\View\View|\Inertia\Response
     */
    public function index()
    {
        $this->authorizeAccess();

        $business_id = (int) request()->session()->get('user.business_id');

        if (app(FeatureFlagService::class)->isOn(self::FLAG_V2, ['business_id' => $business_id])) {
            $todas = $this->podeVerTodasEmpresas();

            return Inertia::render('Officeimpresso/Licencas/Index', [
                'permissions' => [
                    'pode_ver_todas_empresas' => $todas,
                    'pode_gerenciar' => auth()->user()->can('superadmin')
                        || auth()->user()->can('officeimpresso.licencas.gerenciar'),
                ],
                'licencas'    => Inertia::defer(fn () => $this->buildLicencasPayload($business_id, $todas)),
                // Drawer (PR-b): só vem quando o drawer pede (`only: ['detalhe']`, `?licenca=`).
                'detalhe'     => Inertia::optional(fn () => $this->buildDetalhe((int) request()->query('licenca'), $business_id, $todas)),
            ]);
        }

        $licencas = $this->licencaService->listarPorEmpresa($business_id);

        return view('officeimpresso::licenca_computador.index', compact('licencas'));
    }

    /**
     * Quem vê máquina de todos os negócios nesta tela: só `superadmin`. Quem tem
     * `officeimpresso.access` sem superadmin segue vendo só o negócio da sessão — é a regra
     * do `index()` desde sempre e esta tela não a amplia (thread 06, 2026-10-01).
     */
    private function podeVerTodasEmpresas(): bool
    {
        return (bool) auth()->user()->can('superadmin');
    }

    /**
     * DTO explícito de cada máquina — nunca o model: `Licenca_Computador` não tem `$hidden`
     * e o banco ainda guarda `senha`/`contra_senha`/`serial`. Só as colunas abaixo saem.
     *
     * `hd_compartilhado` = quantos OUTROS negócios têm o mesmo HD (L4: a API atualiza todas
     * as linhas do HD e recusa se qualquer uma estiver bloqueada). Contado sobre as linhas já
     * escopadas — quem não vê os outros negócios não fica sabendo deles (Tier 0).
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildLicencasPayload(int $business_id, bool $todas): array
    {
        $linhas = DB::table('licenca_computador as lc')
            ->leftJoin('business as b', 'b.id', '=', 'lc.business_id')
            // SUPERADMIN: sem filtro de negócio só quando $todas (superadmin) — senão, a sessão.
            ->when(! $todas, fn ($q) => $q->where('lc.business_id', $business_id))
            ->orderByDesc('lc.id')
            ->get([
                'lc.id', 'lc.business_id', 'b.name as empresa', 'b.versao_obrigatoria',
                'lc.hostname', 'lc.user_win', 'lc.hd', 'lc.versao_exe', 'lc.versao_banco',
                'lc.dt_ultimo_acesso', 'lc.dt_validade', 'lc.bloqueado', 'lc.motivo',
            ]);

        $bizPorHd = $linhas->filter(fn ($l) => filled($l->hd))
            ->groupBy('hd')
            ->map(fn ($g) => $g->pluck('business_id')->unique()->count());

        $agora = now();

        return $linhas->map(function ($l) use ($bizPorHd, $agora) {
            $acesso = $l->dt_ultimo_acesso ? Carbon::parse($l->dt_ultimo_acesso) : null;
            // Vocabulário do protótipo (`StatusBadge kind="frescor"`): <24 h · <7 d · <30 d · resto/nunca.
            $frescor = match (true) {
                $acesso === null => 'distante',
                $acesso->gt($agora->copy()->subDay()) => 'recente',
                $acesso->gt($agora->copy()->subDays(7)) => 'fresc',
                $acesso->gt($agora->copy()->subDays(30)) => 'frio',
                default => 'distante',
            };

            return [
                'id'                 => (int) $l->id,
                'business_id'        => (int) $l->business_id,
                'empresa'            => $l->empresa,
                'hostname'           => $l->hostname,
                'user_win'           => $l->user_win,
                'hd'                 => $l->hd,
                'versao_exe'         => $l->versao_exe,
                'versao_banco'       => $l->versao_banco,
                'versao_obrigatoria' => $l->versao_obrigatoria,
                'dt_ultimo_acesso'   => $acesso?->toIso8601String(),
                'frescor'            => $frescor,
                'dt_validade'        => $l->dt_validade ? Carbon::parse($l->dt_validade)->toDateString() : null,
                'bloqueado'          => (bool) $l->bloqueado,
                'motivo'             => $l->motivo,
                'hd_compartilhado'   => filled($l->hd) ? max(0, ($bizPorHd[$l->hd] ?? 1) - 1) : 0,
            ];
        })->values()->all();
    }

    /**
     * Ficha do equipamento + histórico (drawer PT-02, thread 06 PR-b). Mesma regra de visão da
     * lista: fora do escopo devolve null. Ficha é DTO explícito — `senha`, `contra_senha`,
     * `serial`, `token`, `usuario` e `conexao` nunca saem. `motivo` aqui é a mensagem que o
     * desktop recebe ao ser recusado (a API devolve `N;<motivo>`); o motivo do operador fica no
     * histórico, em `licenca_log` (source `admin_action`).
     *
     * @return array<string, mixed>|null
     */
    private function buildDetalhe(int $id, int $business_id, bool $todas): ?array
    {
        $m = DB::table('licenca_computador as lc')
            ->leftJoin('business as b', 'b.id', '=', 'lc.business_id')
            // SUPERADMIN: sem filtro de negócio só quando $todas (superadmin) — senão, a sessão.
            ->when(! $todas, fn ($q) => $q->where('lc.business_id', $business_id))
            ->where('lc.id', $id)
            ->first([
                'lc.id', 'lc.business_id', 'b.name as empresa', 'b.versao_obrigatoria',
                'lc.hostname', 'lc.user_win', 'lc.hd', 'lc.sistema_operacional', 'lc.ip_interno',
                'lc.processador', 'lc.memoria', 'lc.pasta_instalacao', 'lc.versao_exe', 'lc.versao_banco',
                'lc.created_at', 'lc.dt_ultimo_acesso', 'lc.dt_ultima_assistencia', 'lc.dt_validade',
                'lc.bloqueado', 'lc.motivo',
            ]);
        if (! $m) {
            return null;
        }
        $m->id = (int) $m->id;
        $m->business_id = (int) $m->business_id;
        $m->bloqueado = (bool) $m->bloqueado;

        $historico = LicencaLog::query()
            ->leftJoin('users as u', 'u.id', '=', 'licenca_log.user_id')
            ->where('licenca_log.licenca_id', $m->id)
            ->where(fn ($q) => $q->where('licenca_log.business_id', $m->business_id)->orWhereNull('licenca_log.business_id'))
            ->orderByDesc('licenca_log.created_at')
            ->limit(50)
            ->get([
                'licenca_log.id', 'licenca_log.created_at', 'licenca_log.event', 'licenca_log.source',
                'licenca_log.ip', 'licenca_log.endpoint', 'licenca_log.http_status', 'licenca_log.error_code',
                'licenca_log.metadata', 'u.first_name', 'u.surname',
            ])
            ->map(function ($g) {
                $admin = $g->source === 'admin_action';

                return [
                    'id'          => (int) $g->id,
                    'quando'      => $g->created_at?->toIso8601String(),
                    'evento'      => $g->event,
                    'origem'      => $g->source,
                    'ip'          => $g->ip,
                    'rota'        => $g->endpoint,
                    'http_status' => $g->http_status,
                    'erro'        => $g->error_code,
                    'motivo'      => $admin ? ($g->metadata['motivo'] ?? null) : null,
                    'autor'       => $admin ? (trim(($g->first_name ?? '') . ' ' . ($g->surname ?? '')) ?: null) : null,
                ];
            })->values()->all();

        return ['ficha' => (array) $m, 'historico' => $historico];
    }

    /**
     * View "computadores" — pagina principal do cliente.
     */
    public function computadores()
    {
        $this->authorizeAccess();

        $business_id = request()->session()->get('user.business_id');

        $active = Subscription::active_subscription($business_id);
        $package = $active ? Package::find($active->package_id) : null;

        $licencas = $this->licencaService->listarPorEmpresa($business_id);
        $empresa = Business::where('id', $business_id)->first();

        return view('officeimpresso::licenca_computador.computadores', compact('licencas', 'empresa', 'active', 'package'));
    }

    /**
     * View superadmin: lista licencas de uma empresa qualquer.
     */
    public function viewLicencas($id)
    {
        $this->authorizeAccess();

        $active = Subscription::active_subscription($id);
        $package = $active ? Package::find($active->package_id) : null;

        $licencas = $this->licencaService->listarPorEmpresa($id);
        $empresa = Business::where('id', $id)->first();

        return view('officeimpresso::licenca_computador.computadores', compact('licencas', 'empresa', 'active', 'package'));
    }

    /**
     * View superadmin: todas empresas com officeimpresso ativo.
     */
    public function businessall()
    {
        $this->authorizeAccess();

        $business = $this->licencaService->listarEmpresasComDesktop();

        return view('officeimpresso::licenca_computador.businessall', compact('business'));
    }

    /**
     * Form criar.
     */
    public function create()
    {
        $this->authorizeGerenciar();

        return view('officeimpresso::licenca_computador.create');
    }

    /**
     * Form editar (scopado a business_id da sessao).
     */
    public function edit($id)
    {
        $this->authorizeGerenciar();

        $business_id = request()->session()->get('user.business_id');
        $licenca = $this->licencaService->buscarParaEdit((int) $id, (int) $business_id);

        return view('officeimpresso::licenca_computador.create', compact('licenca'));
    }

    public function store(StoreLicencaRequest $request)
    {
        $this->authorizeGerenciar();

        $computador = $this->licencaService->criar($request->validated());

        return response()->json($computador, 201);
    }

    public function show($id)
    {
        $this->authorizeAccess();

        $computador = Licenca_Computador::find($id);
        if (! $computador) {
            return response()->json(['error' => 'Computador não encontrado'], 404);
        }
        return response()->json($computador, 200);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeGerenciar();

        $validated = $request->validate([
            'licenca_id' => 'required|exists:licenca,id',
            'hd' => 'required|unique:licenca_computador,hd,' . $id,
            'processador' => 'required',
            'memoria' => 'required',
            'versao_exe' => 'required',
            'bloqueado' => 'boolean',
        ]);

        $computador = $this->licencaService->atualizar((int) $id, $validated);
        if (! $computador) {
            return response()->json(['error' => 'Computador não encontrado'], 404);
        }
        return response()->json($computador, 200);
    }

    public function destroy($id)
    {
        $this->authorizeExcluir();

        $ok = $this->licencaService->remover((int) $id);
        if (! $ok) {
            return response()->json(['error' => 'Computador não encontrado'], 404);
        }
        return response()->json(['message' => 'Computador deletado com sucesso'], 200);
    }

    public function toggleBlock(RevokeLicencaRequest $request, $id)
    {
        $this->authorizeGerenciar();

        // Drawer da lista (thread 06 PR-b) manda a INTENÇÃO (`bloquear`) com motivo obrigatório.
        // Sem `bloquear` (Blade, tela de Logs) segue o toggle de sempre — nada muda para eles.
        if ($request->has('bloquear')) {
            return $this->bloquearComMotivo($request, (int) $id);
        }

        try {
            $this->licencaService->alternarBloqueio((int) $id);
            return redirect()->back()->with('status', 'Status de bloqueio alterado com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao alterar o status de bloqueio.');
        }
    }

    /**
     * Bloqueia/libera com motivo. Mesmo alvo e mesma ação do toggle (`alternarBloqueio`) — a regra
     * do que o desktop recebe não muda: `licenca_computador.motivo` (a mensagem `N;<motivo>`) NÃO é
     * reescrito. O motivo do operador vai para `licenca_log` (source `admin_action`) com o
     * `business_id` do EQUIPAMENTO, não o da sessão — o superadmin age em outro negócio, e é o
     * negócio do equipamento que precisa encontrar o registro (o `Util::activityLog` usaria a sessão).
     */
    private function bloquearComMotivo(Request $request, int $id)
    {
        $dados = $request->validate([
            'bloquear' => ['required', 'boolean'],
            'motivo'   => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'motivo.required' => 'Informe o motivo — ele fica no histórico da máquina.',
            'motivo.min'      => 'Motivo precisa ter pelo menos 5 caracteres.',
            'motivo.max'      => 'Motivo não pode passar de 500 caracteres.',
        ]);
        $bloquear = (bool) $dados['bloquear'];
        $licenca = Licenca_Computador::findOrFail($id);

        // Intenção já cumprida (duplo clique, outra aba): não inverte de volta.
        if ((bool) $licenca->bloqueado === $bloquear) {
            throw ValidationException::withMessages([
                'bloquear' => $bloquear ? 'Esta máquina já está bloqueada.' : 'Esta máquina já está liberada.',
            ]);
        }

        // Texto livre que fica no log para sempre: CPF/CNPJ/e-mail/telefone saem redigidos.
        $motivo = app(PiiRedactor::class)->redact(trim($dados['motivo']));

        DB::transaction(function () use ($licenca, $bloquear, $motivo, $request) {
            $this->licencaService->alternarBloqueio((int) $licenca->id);
            LicencaLog::create([
                'licenca_id'  => $licenca->id,
                'business_id' => $licenca->business_id,
                'user_id'     => auth()->id(),
                'event'       => $bloquear ? 'maquina_bloqueada' : 'maquina_liberada',
                'ip'          => $request->ip(),
                'endpoint'    => 'licenca_computador/' . $licenca->id . '/toggle-block',
                'http_method' => 'POST',
                'metadata'    => ['motivo' => $motivo],
                'source'      => 'admin_action',
                'created_at'  => now(),
            ]);
        });

        return redirect()->back()->with('status', $bloquear ? 'Máquina bloqueada.' : 'Máquina liberada.');
    }

    public function businessupdate(Request $request, $id)
    {
        $this->authorizeEmpresa();

        try {
            $request->validate([
                'caminho_banco' => 'nullable|string|max:255',
                'versao_obrigatoria' => 'nullable|string|max:50',
                'versao_disponivel' => 'nullable|string|max:50',
            ]);

            $this->licencaService->atualizarEmpresa((int) $id, $request->only([
                'caminho_banco_servidor', 'versao_obrigatoria', 'versao_disponivel',
                'officeimpresso_numerodemaquinas',
            ]));

            return redirect()->back()->with('status', 'Dados da empresa atualizados com sucesso!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao alterar os dados da empresa.');
        }
    }

    public function businessbloqueado($id)
    {
        $this->authorizeEmpresa();

        try {
            $this->licencaService->alternarBloqueioEmpresa((int) $id);
            return redirect()->back()->with('status', 'Status de bloqueio alterado com sucesso!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao alterar o status de bloqueio.');
        }
    }
}
