<?php

namespace Modules\Connector\Http\Controllers;

use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
     * @return Response
     */
    public function index()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $is_demo = (config('app.env') == 'demo');

        $business_id = request()->session()->get('user.business_id');
        $clients = Passport::client()
                    ->leftJoin('users as u', 'oauth_clients.user_id', '=', 'u.id')
                    ->where('u.business_id', $business_id)
                    ->where('password_client', 1)
                    ->select('oauth_clients.*')
                    ->get()
                    ->makeVisible('secret');

        return view('connector::clients.index')->with(compact('clients', 'is_demo'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('connector::create');
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
            $client = Passport::client()->forceFill([
                'user_id' => auth()->user()->id,
                'name' => $request->input('name'),
                'secret' => Str::random(40),
                'redirect' => 'http://localhost',
                'personal_access_client' => 0,
                'password_client' => 1,
                'revoked' => false,
            ]);

            $client->save();

            $this->auditar($client, 'connector_client_created');

            $output = ['success' => true,
                'msg' => __('lang_v1.added_success'),
            ];
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

    public function regenerate()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            Artisan::call('passport:install --force');
            // Artisan::call('scribe:generate');

            $output = ['success' => 1,
                'msg' => __('lang_v1.success'),
            ];
        } catch (Exception $e) {
            $output = ['success' => 1,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect()->back()->with('status', $output);
    }
}
