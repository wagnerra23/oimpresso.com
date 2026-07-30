<?php

namespace Modules\Officeimpresso\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Officeimpresso\Entities\Licenca_Computador;
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
     * Ações que atingem a EMPRESA inteira do cliente (businessupdate — versão
     * obrigatória de todos os desktops; businessbloqueado — derruba o cliente
     * inteiro) e destroy() seguem superadmin-only.
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
     * Autoriza ações destrutivas ou de escopo empresa-inteira — superadmin-only.
     */
    private function authorizeSuperadmin(): void
    {
        abort_unless(auth()->user()->can('superadmin'), 403, 'Unauthorized action.');
    }

    /**
     * Display a listing of all resources.
     */
    public function index()
    {
        $this->authorizeAccess();

        $business_id = request()->session()->get('user.business_id');
        $licencas = $this->licencaService->listarPorEmpresa($business_id);

        return view('officeimpresso::licenca_computador.index', compact('licencas'));
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
        $this->authorizeSuperadmin();

        $ok = $this->licencaService->remover((int) $id);
        if (! $ok) {
            return response()->json(['error' => 'Computador não encontrado'], 404);
        }
        return response()->json(['message' => 'Computador deletado com sucesso'], 200);
    }

    public function toggleBlock(RevokeLicencaRequest $request, $id)
    {
        $this->authorizeGerenciar();

        try {
            $this->licencaService->alternarBloqueio((int) $id);
            return redirect()->back()->with('status', 'Status de bloqueio alterado com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao alterar o status de bloqueio.');
        }
    }

    public function businessupdate(Request $request, $id)
    {
        $this->authorizeSuperadmin();

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
        $this->authorizeSuperadmin();

        try {
            $this->licencaService->alternarBloqueioEmpresa((int) $id);
            return redirect()->back()->with('status', 'Status de bloqueio alterado com sucesso!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao alterar o status de bloqueio.');
        }
    }
}
