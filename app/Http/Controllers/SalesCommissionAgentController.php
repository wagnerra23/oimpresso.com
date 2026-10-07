<?php

namespace App\Http\Controllers;

use App\Transaction;
use App\User;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * CRUD de agente comercial (comissionado).
 *
 * ACESSO: `commission_agent.view` (ler a lista) e `commission_agent.manage` (criar, editar,
 * desmarcar). ATE 2026-08-20 esta tela gateava por `user.view`/`user.create`/`user.update`/
 * `user.delete` — quem apurava comissao precisava, junto, de acesso ao cadastro de USUARIOS
 * do negocio. Acoplamento indevido: sao dois assuntos diferentes, e o mais sensivel vinha
 * de carona. Decisao [W] 2026-08-19.
 *
 * A troca vem acompanhada de backfill
 * (2026_08_20_120000_add_commission_agent_permissions): todo papel que ja chegava aqui pelas
 * permissoes de usuario recebeu as novas. Sem isso, a troca TIRARIA acesso no dia do deploy.
 *
 * O dono do negocio nao depende de nenhuma das duas: `Gate::before` em AuthServiceProvider
 * libera qualquer ability pra quem tem `Admin#{business_id}`.
 */
class SalesCommissionAgentController extends Controller
{
    /**
     * Constructor
     *
     * @param  Util  $commonUtil
     * @return void
     */
    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    /**
     * Lista dos comissionados — Inertia `Comissionados/Index` (thread sistema/playbook/03).
     *
     * A DataTable da Blade (`if (request()->ajax())`) saiu: o Inertia manda `X-Requested-With`
     * em toda visita, entao aquele ramo engoliria a pagina e devolveria o JSON da tabela.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        if (! auth()->user()->can('commission_agent.view') && ! auth()->user()->can('commission_agent.manage')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = (int) request()->session()->get('user.business_id');

        return Inertia::render('Comissionados/Index', [
            // defer: conta as vendas vinculadas de cada agente (RUNBOOK-inertia-defer-pattern).
            'agentes' => Inertia::defer(fn () => $this->agentesDoNegocio($business_id)),
            'pode' => ['gerenciar' => auth()->user()->can('commission_agent.manage')],
        ]);
    }

    /**
     * Comissionados do negocio da sessao, com quantas vendas apontam para cada um.
     *
     * `vendas` e a MESMA contagem que a guarda do destroy() usa (transactions.commission_agent,
     * sem filtro de tipo): a tela avisa antes do clique o que o servidor recusaria depois.
     * O percentual vai como numero; a tela formata em pt-BR e devolve TEXTO para o num_uf.
     *
     * @return array<int, array<string, mixed>>
     */
    private function agentesDoNegocio(int $businessId): array
    {
        $vendas = DB::table('transactions')
            ->where('business_id', $businessId)
            ->whereNotNull('commission_agent')
            ->groupBy('commission_agent')
            ->selectRaw('commission_agent, COUNT(*) as total')
            ->pluck('total', 'commission_agent');

        return DB::table('users')
            ->where('business_id', $businessId)
            ->where('is_cmmsn_agnt', 1)
            ->whereNull('deleted_at')
            ->orderBy('first_name')
            ->get(['id', 'surname', 'first_name', 'last_name', 'email', 'contact_no', 'address', 'cmmsn_percent'])
            ->map(fn ($u) => [
                'id' => (int) $u->id,
                'prefixo' => (string) ($u->surname ?? ''),
                'primeiro_nome' => (string) ($u->first_name ?? ''),
                'sobrenome' => (string) ($u->last_name ?? ''),
                'email' => (string) ($u->email ?? ''),
                'contato' => (string) ($u->contact_no ?? ''),
                'endereco' => (string) ($u->address ?? ''),
                'percentual' => (float) $u->cmmsn_percent,
                'vendas' => (int) ($vendas[$u->id] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('commission_agent.manage')) {
            abort(403, 'Unauthorized action.');
        }

        return view('sales_commission_agent.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('commission_agent.manage')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['surname', 'first_name', 'last_name', 'email', 'address', 'contact_no', 'cmmsn_percent']);
            $input['cmmsn_percent'] = $this->commonUtil->num_uf($input['cmmsn_percent']);
            $business_id = $request->session()->get('user.business_id');

            $existente = $this->usuarioParaMarcarComoComissionado($business_id, $request->input('email'));

            if ($existente !== null) {
                // MARCA o papel na linha que ja existe, em vez de abrir uma segunda.
                //
                // Escrita deliberadamente MINIMA — so o papel e o percentual. Nome, e-mail,
                // endereco e telefone do usuario existente NAO sao sobrescritos pelo que foi
                // digitado no formulario: quem cadastra comissionado nao esta editando o
                // cadastro daquela pessoa, e sobrescrever seria uma perda silenciosa.
                //
                // `allow_login` tambem fica INTACTO. O caminho de criacao grava 0 (agente novo
                // nao loga), mas aplicar isso a um usuario que ja existe TIRARIA O LOGIN DELE
                // — a coluna nasce 1 por default no schema.
                //
                // Array em vez de atribuicao de propriedade: `is_cmmsn_agnt` e tinyint(1) e o
                // Larastan infere bool da propriedade, entao `$u->is_cmmsn_agnt = 1` da erro de
                // tipo (foi o que aconteceu no #5970). Pela chave do array nao ha inferencia —
                // e e o mesmo idioma do update() logo abaixo.
                $existente->update([
                    'is_cmmsn_agnt' => 1,
                    'cmmsn_percent' => $input['cmmsn_percent'],
                ]);

                // Mensagem PROPRIA, nao a de "adicionado": o operador precisa saber que nenhum
                // usuario novo foi criado — a lista vai mostrar o nome que aquela pessoa ja
                // tinha, que pode nao ser o que ele acabou de digitar.
                $output = ['success' => true,
                    'msg' => __('lang_v1.commission_agent_linked_success'),
                ];
            } else {
                $input['business_id'] = $business_id;
                $input['allow_login'] = 0;
                $input['is_cmmsn_agnt'] = 1;

                User::create($input);

                $output = ['success' => true,
                    'msg' => __('lang_v1.commission_agent_added_success'),
                ];
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Usuario do mesmo negocio que deve RECEBER o papel de comissionado, ou null pra criar um novo.
     *
     * O casamento e por e-mail e so vale quando o e-mail identifica UMA pessoa. Os dois casos
     * que devolvem null cairiam, se casassem, exatamente no dedupe automatico que [W] adiou
     * em 2026-08-19 — fundir gente errada em base de producao (biz=4 ROTA LIVRE esta viva):
     *
     *  - E-MAIL VAZIO nao identifica ninguem. O formulario de cadastro NAO exige e-mail
     *    (create.blade.php: o campo nao tem `required`) e `users.email` e nullable no schema,
     *    entao casar por vazio juntaria dois desconhecidos qualquer.
     *  - E-MAIL REPETIDO no mesmo negocio e ambiguo. `users` tem UNIQUE so em `username`
     *    (indice de e-mail nao existe, nem unico nem comum), logo duplicata e um estado
     *    possivel do banco — e escolher um dos dois seria adivinhar qual pessoa e.
     *
     * Nos dois casos o fluxo segue pro caminho antigo (cria), que e o comportamento de hoje:
     * nao piora nada, e nunca funde a pessoa errada.
     *
     * Usuario com soft-delete NAO e alcancado (o escopo padrao do Eloquent ja o exclui, e
     * App\User usa SoftDeletes): remarcar como comissionado alguem que foi excluido seria
     * ressuscitar um cadastro sem que ninguem tenha decidido isso.
     *
     * @param  int|string|null  $businessId
     */
    private function usuarioParaMarcarComoComissionado($businessId, ?string $email): ?User
    {
        $email = trim((string) $email);

        if ($email === '') {
            return null;
        }

        // limit(2) porque a pergunta e "exatamente um?" — nao precisa carregar a lista toda
        // pra distinguir 0, 1 e "mais de um". A comparacao ja e case-insensitive: a tabela e
        // utf8mb4_unicode_ci, entao Maria@x e maria@x sao a mesma chave para o MySQL.
        $candidatos = User::where('business_id', $businessId)
            ->where('email', $email)
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($candidatos->count() !== 1) {
            if ($candidatos->count() > 1) {
                // Visivel de proposito: o cadastro vai criar mais uma linha, e a duplicata de
                // e-mail que causou isso e divida do legado (levantamento a parte, decisao [W]).
                \Log::warning('SalesCommissionAgent: e-mail com mais de um usuario no negocio; criando em vez de unificar.', [
                    'business_id' => $businessId,
                ]);
            }

            return null;
        }

        return $candidatos->first();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('commission_agent.manage')) {
            abort(403, 'Unauthorized action.');
        }

        // Tier 0: so comissionado do negocio da sessao. Ate 2026-10-06 era findOrFail($id) cru,
        // e o formulario abria nome, e-mail e endereco de usuario de OUTRO negocio pelo id na URL.
        $user = User::where('id', $id)
            ->where('business_id', request()->session()->get('user.business_id'))
            ->where('is_cmmsn_agnt', 1)
            ->firstOrFail();

        return view('sales_commission_agent.edit')
                    ->with(compact('user'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('commission_agent.manage')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->only(['surname', 'first_name', 'last_name', 'email', 'address', 'contact_no', 'cmmsn_percent']);
                $input['cmmsn_percent'] = $this->commonUtil->num_uf($input['cmmsn_percent']);
                $business_id = $request->session()->get('user.business_id');

                $user = User::where('id', $id)
                            ->where('business_id', $business_id)
                            ->where('is_cmmsn_agnt', 1)
                            ->first();
                $user->update($input);

                $output = ['success' => true,
                    'msg' => __('lang_v1.commission_agent_updated_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * Retorno heterogeneo por heranca do UltimatePOS: a rota e ajax e o caminho feliz
     * devolve o array `$output` cru (o front le success/msg). A guarda de venda-vinculada
     * precisa de STATUS pra ser distinguivel de um no-op, entao devolve JsonResponse 422.
     * O tipo declarado passa a dizer a verdade em vez de mentir um Response que este
     * metodo nunca retornou.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse|array<string, mixed>|null
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('commission_agent.manage')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');

                $agente = User::where('id', $id)
                    ->where('business_id', $business_id)
                    ->where('is_cmmsn_agnt', 1)
                    ->first();

                if (empty($agente)) {
                    return ['success' => false,
                        'msg' => __('messages.something_went_wrong'),
                    ];
                }

                // GUARDA POR VINCULO DE DADO, nao por regra de negocio: transactions.commission_agent
                // guarda o id do usuario e NAO tem FK (migration 2018_02_26_134500) — nada no banco
                // impediria a venda de ficar apontando pra um agente que sumiu da listagem.
                $vendasVinculadas = Transaction::where('business_id', $business_id)
                    ->where('commission_agent', $agente->id)
                    ->count();

                if ($vendasVinculadas > 0) {
                    return response()->json([
                        'success' => false,
                        'msg' => __('lang_v1.commission_agent_has_sales', ['count' => $vendasVinculadas]),
                    ], 422);
                }

                // Sem venda vinculada: DESMARCA em vez de excluir. O registro em `users` continua
                // servindo login e vinculos; o que sai e o papel de comissionado. O delete anterior
                // era SOFT (o model usa SoftDeletes), mas ainda assim tirava o usuario das consultas
                // normais — e com ele o nome do agente nos relatorios de venda.
                // `false`, nao `0`: o Larastan infere bool do schema (tinyint(1)) e recusa int
                // na atribuicao de propriedade. O valor gravado e o mesmo.
                $agente->is_cmmsn_agnt = false;
                $agente->save();

                $output = ['success' => true,
                    'msg' => __('lang_v1.commission_agent_deleted_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }

        // Requisicao nao-ajax nao tem resposta neste fluxo (a tela so chama por ajax).
        // O return explicito existe porque o tipo declarado exige um em todo caminho.
        return null;
    }
}
