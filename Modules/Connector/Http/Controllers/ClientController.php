<?php

namespace Modules\Connector\Http\Controllers;

use App\Utils\ModuleUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Passport\Passport;
use Modules\Connector\Http\Requests\StoreOauthClientRequest;

class ClientController extends Controller
{
    public function __construct(Util $util)
    {
        $this->util = $util;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $is_demo = (config('app.env') == 'demo');

        $business_id = request()->session()->get('user.business_id');
        // CONN-O2b · [W] D6: o segredo NAO sai do banco para a lista — a coluna nem e
        // selecionada. O valor guardado nao muda (sem hash, sem rotacao): o WR Comercial
        // em campo continua autenticando.
        // CONN-O3: eager de proposito (nao Inertia::defer) — a lista e o conteudo da tela,
        // tem poucas linhas por negocio, e a credencial recem-criada vem do flash, que so
        // existe nesta requisicao.
        $clients = $is_demo ? collect() : Passport::client()
                    ->join('users as u', 'oauth_clients.user_id', '=', 'u.id')
                    ->where('u.business_id', $business_id)
                    ->where('oauth_clients.password_client', 1)
                    ->select([
                        'oauth_clients.id',
                        'oauth_clients.name',
                        'oauth_clients.created_at',
                        'u.first_name as user_name',
                    ])
                    ->selectSub(function ($q) {
                        // tokens ativos e tocados nas ultimas 24 h (UC-CONN-03)
                        $q->from('oauth_access_tokens')->selectRaw('count(*)')
                            ->whereColumn('oauth_access_tokens.client_id', 'oauth_clients.id')
                            ->where('oauth_access_tokens.revoked', 0)
                            ->where(fn ($w) => $w->whereNull('oauth_access_tokens.expires_at')->orWhere('oauth_access_tokens.expires_at', '>', now()))
                            ->where('oauth_access_tokens.updated_at', '>=', now()->subDay());
                    }, 'active_tokens_24h')
                    ->orderBy('oauth_clients.id')
                    ->get()
                    ->map(fn ($c) => [
                        'id' => (int) $c->id,
                        'name' => (string) $c->name,
                        'user_name' => (string) $c->getAttribute('user_name'),
                        'created_at' => optional($c->created_at)->toDateString(),
                        'active_tokens_24h' => (int) $c->getAttribute('active_tokens_24h'),
                    ])->values();

        // CONN-O3 PR-b · aba Documentacao: o catalogo e LIDO das rotas registradas (nada
        // escrito a mao), entao o KPI e o catalogo contam o mesmo conjunto (UC-CONN-19).
        $endpoints = collect(Route::getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'connector/api/'))
            ->map(function ($r) {
                [$classe, $metodo] = array_pad(explode('@', $r->getActionName(), 2), 2, null);

                return [
                    'metodos' => implode('·', array_values(array_diff($r->methods(), ['HEAD']))),
                    'rota' => substr($r->uri(), strlen('connector/api/')),
                    'acao' => class_basename($classe).($metodo ? '@'.$metodo : ''),
                ];
            })->values();

        return Inertia::render('Api/Index', [
            'clients' => $clients,
            'is_demo' => $is_demo,
            'endpoints_count' => $endpoints->count(),
            'endpoints' => $endpoints,
            // Aba Modulo: so o que se mede daqui (UC-CONN-23). Instalar/atualizar/desinstalar
            // ficam nas confirmacoes do InstallController (GET sem efeito, acao no POST).
            'modulo' => [
                'instalado' => (bool) (new ModuleUtil())->isModuleInstalled('Connector'),
                'versao' => (string) config('connector.module_version', '2.0'),
                'migracoes' => count(glob(module_path('Connector', 'Database/Migrations/*.php')) ?: []),
            ],
            // Unica vez que o segredo sai: o flash da criacao, lido aqui e descartado.
            'credencial' => $is_demo ? null : session('connector_credencial'),
        ]);
    }

    /**
     * CONN-O4: nao ha formulario proprio — criar e um Dialog do painel. A view
     * `connector::create` nunca existiu e o link antigo do menu dava 500 (UC-CONN-15).
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function create()
    {
        return redirect()->action([self::class, 'index']);
    }

    /**
     * Store a newly created resource in storage.
     *
     * D8.c Wave 17: validation + authorization superadmin extraídas
     * pra StoreOauthClientRequest (fail-secure 403 antes do controller).
     *
     * @return Response
     */
    public function store(StoreOauthClientRequest $request)
    {
        try {
            $segredo = Str::random(40);

            $client = Passport::client()->forceFill([
                'user_id' => auth()->user()->id,
                'name' => $request->input('name'),
                'secret' => $segredo,
                'redirect' => 'http://localhost',
                'personal_access_client' => 0,
                'password_client' => 1,
                'revoked' => false,
            ]);

            $client->save();

            $this->auditar($client, 'connector_client_created');

            // CONN-O2b/O3: unica vez que o segredo aparece — num flash proprio, lido uma vez
            // pela lista (bloco copiavel) e descartado pela sessao. Fora do status.msg porque
            // esse vira toast e some sozinho. Nunca no log nem na auditoria.
            session()->flash('connector_credencial', [
                'id' => $client->id,
                'name' => $client->name,
                'secret' => $segredo,
            ]);
            $output = ['success' => true, 'msg' => __('lang_v1.added_success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with('status', $output);
    }

    /**
     * Show the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        return view('connector::show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        return view('connector::edit');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        // Multi-tenant: o client so e encontrado se o dono (users.business_id) for do
        // negocio da sessao. Fora disso, nada e revogado nem apagado (UC-CONN-11).
        $client = Passport::client()
                        ->join('users as u', 'oauth_clients.user_id', '=', 'u.id')
                        ->where('u.business_id', $business_id)
                        ->where('oauth_clients.id', $id)
                        ->select('oauth_clients.id', 'oauth_clients.name')
                        ->first();

        $output = ['success' => false,
            'msg' => __('messages.something_went_wrong'),
        ];

        if ($client !== null) {
            $output = $this->excluirRevogando($client);
        }

        return redirect()->back()->with('status', $output);
    }

    /**
     * @return array<string, mixed>
     */
    private function excluirRevogando($client): array
    {
        // CONN-O2 · [W] D2: excluir revoga em cadeia, na mesma transacao. Sem isso os
        // tokens ja emitidos com o client valiam ate expires_at (UC-CONN-12).
        $revogados = DB::transaction(function () use ($client) {
            $revogados = DB::table('oauth_access_tokens')
                ->where('client_id', $client->id)
                ->where('revoked', 0)
                ->update(['revoked' => 1, 'updated_at' => now()]);

            DB::table('oauth_refresh_tokens')
                ->whereIn('access_token_id', function ($q) use ($client) {
                    $q->select('id')->from('oauth_access_tokens')->where('client_id', $client->id);
                })
                ->where('revoked', 0)
                ->update(['revoked' => 1]);

            DB::table('oauth_clients')->where('id', $client->id)->delete();

            return $revogados;
        });

        $this->auditar($client, 'connector_client_deleted', ['revoked_tokens' => $revogados]);

        return ['success' => true,
            'msg' => __('lang_v1.deleted_success'),
            'revoked_tokens' => $revogados,
        ];
    }

    /**
     * Auditoria de criar/excluir credencial: client_id + nome, NUNCA o segredo.
     * Falha de log nao desfaz a acao ja feita.
     */
    private function auditar($client, string $acao, array $extra = []): void
    {
        try {
            app(Util::class)->activityLog(
                $client,
                $acao,
                null,
                array_merge(['client_id' => $client->id, 'name' => $client->name], $extra),
                false,
                request()->session()->get('user.business_id')
            );
        } catch (\Throwable $e) {
            \Log::warning('Connector: auditoria do client '.$client->id.' falhou: '.$e->getMessage());
        }
    }
}
