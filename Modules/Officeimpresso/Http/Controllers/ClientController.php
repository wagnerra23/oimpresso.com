<?php

namespace Modules\Officeimpresso\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Laravel\Passport\Passport;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;
use App\Utils\Util;
use Illuminate\Support\Facades\Artisan;

class ClientController extends Controller
{
    public function __construct(Util $util) {
        $this->util = $util;
    }

    /**
     * Autoriza as ações de "liberar cliente" — ver a lista + criar a credencial
     * OAuth (password grant) que cada Delphi usa pra autenticar.
     *
     * Aceita o `superadmin` (acesso histórico) OU a permissão delegável
     * `officeimpresso.clientes.liberar`, que pode ser concedida a um login
     * próprio de funcionário SEM abrir o Financeiro (gated por `superadmin`).
     * destroy()/regenerate() seguem superadmin-only — são destrutivos
     * (apagam credencial / derrubam TODOS os Delphi via passport:install).
     */
    private function authorizeLiberar(): void
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('officeimpresso.clientes.liberar'),
            403,
            'Unauthorized action.'
        );
    }

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        $this->authorizeLiberar();

        $is_demo = (config('app.env') == 'demo');

        $business_id = request()->session()->get('user.business_id');
        // Thread 05 (2026-10-01): o segredo NAO sai do banco para a lista — a coluna nem
        // e selecionada (mesma regra do painel do Connector, #8350). O valor guardado nao
        // muda: o Delphi em campo continua autenticando. Ele aparece UMA vez, no flash da
        // criacao (store), lido aqui e descartado pela sessao.
        $clients = Passport::client()
                    ->join('users as u', 'oauth_clients.user_id', '=', 'u.id')
                    ->where('u.business_id', $business_id)
                    ->where('oauth_clients.password_client', 1)
                    ->select([
                        'oauth_clients.id',
                        'oauth_clients.name',
                        'oauth_clients.password_client',
                        'oauth_clients.personal_access_client',
                    ])
                    ->orderBy('oauth_clients.id')
                    ->get();

        $credencial = $is_demo ? null : session('officeimpresso_credencial');

        return view('officeimpresso::clients.index')->with(compact('clients', 'is_demo', 'credencial'));
    }

    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function create()
    {
        $this->authorizeLiberar();

        return view('officeimpresso::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $this->authorizeLiberar();

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

            // Unica vez que o segredo sai: flash proprio, lido uma vez pela lista (bloco
            // copiavel que fica ate o usuario fechar) — nunca no log nem no status.msg.
            // O Client ID aparece na lista. (Ate 2026-10-01 o Passport 13 gerava UUID no
            // id e o insert colidia; desligado no AuthServiceProvider.)
            session()->flash('officeimpresso_credencial', [
                'name' => $client->name,
                'secret' => $segredo,
            ]);

            $output = ['success' => true,
                            'msg' => __("lang_v1.added_success")
                        ];
        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
            
            $output = ['success' => false,
                            'msg' => __("messages.something_went_wrong")
                        ];
        }

        return redirect()->back()->with('status', $output);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show($id)
    {
        return view('officeimpresso::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Response
     */
    public function edit($id)
    {
        return view('officeimpresso::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy($id)
    {
        if (!auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }
        
        $business_id = request()->session()->get('user.business_id');
        $clients = Passport::client()
                        ->leftJoin('users as u', 'oauth_clients.user_id', '=', 'u.id')
                        ->where('u.business_id', $business_id)
                        ->where('oauth_clients.id', $id)
                        ->delete();

        $output = ['success' => true,
                            'msg' => __("lang_v1.deleted_success")
                        ];
        return redirect()->back()->with('status', $output);
    }

    public function regenerate(){
        if (!auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            Artisan::call('passport:install --force');
            Artisan::call('apidoc:generate');

            $output = ['success' => 1,
                    'msg' => __("lang_v1.success")
                ];

        } catch (Exception $e) {
            $output = ['success' => 1,
                    'msg' => $e->getMessage()
                ];
        }
        
        return redirect()->back()->with('status', $output);
    }
}
