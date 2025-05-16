<?php

namespace Modules\Officeimpresso\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Officeimpresso\Entities\Licenca_Computador;
use App\Business;
use Modules\Superadmin\Entities\Subscription;
use Modules\Superadmin\Entities\Package;


class LicencaComputadorController extends Controller
{
    /**
     * Display a listing of all resources.
     * @return Response
     */
    public function index()
    {
        // Obter o business_id da sessão do usuário logado
        $business_id = request()->session()->get('user.business_id');

        // Filtrar as licenças que pertencem ao business_id do usuário
        $licencas = Licenca_Computador::where('business_id', $business_id)->get();

        // Retornar a view com as licenças
        return view('officeimpresso::licenca_computador.index', compact('licencas'));

    }
    
    /**
     * Display a listing of all resources.
     * @return Response
     */
    public function computadores()
    {
        // Obter o business_id da sessão do usuário logado
        $business_id = request()->session()->get('user.business_id');

        //Get active subscription and upcoming subscriptions.
        $active = Subscription::active_subscription($business_id);
    
        $package = Package::find($active->package_id)->first();

        // Filtrar as licenças que pertencem ao business_id do usuário
        $licencas = Licenca_Computador::where('business_id', $business_id)->get();

        $empresa = business::where('id', $business_id)->first(); 

        // Retornar a view com as licenças
        return view('officeimpresso::licenca_computador.computadores', compact('licencas', 'empresa','active','package'));

    }

    /**
     * Display a listing of all resources.
     * @return Response
     */
    public function viewLicencas($id)
    {

        //Get active subscription and upcoming subscriptions.
        $active = Subscription::active_subscription($id);
    
        $package = Package::find($active->package_id)->first();
        
        // Filtrar as licenças que pertencem ao business_id do usuário
        $licencas = Licenca_Computador::where('business_id', $id)->get();

        $empresa = business::where('id', $id)->first(); 

        // Retornar a view com as licenças
        return view('officeimpresso::licenca_computador.computadores', compact('licencas', 'empresa','active','package'));

    }

    /**
     * Display a listing of all resources.
     * @return Response
     */
    public function businessall()
    {
        // Obter todas as licenças
        $business = business::where('is_officeimpresso', true)->get();  // aqui tem que criar esse campo

        // Retornar a view com todas as licenças
        return view('officeimpresso::licenca_computador.businessall', compact('business'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validação dos dados recebidos
        $validated = $request->validate([
            'licenca_id' => 'required|exists:licenca,id',
            'hd' => 'required|unique:licenca_computador,hd',
            'processador' => 'required',
            'memoria' => 'required',
            'versao_exe' => 'required',
            'bloqueado' => 'boolean',
        ]);

        // Criação de um novo registro
        $computador = Licenca_Computador::create($validated);

        return response()->json($computador, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $computador = Licenca_Computador::find($id);

        if (!$computador) {
            return response()->json(['error' => 'Computador não encontrado'], 404);
        }

        return response()->json($computador, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Validação dos dados recebidos
        $validated = $request->validate([
            'licenca_id' => 'required|exists:licenca,id',
            'hd' => 'required|unique:licenca_computador,hd,' . $id,
            'processador' => 'required',
            'memoria' => 'required',
            'versao_exe' => 'required',
            'bloqueado' => 'boolean',
        ]);

        // Encontrar o computador pelo ID
        $computador = Licenca_Computador::find($id);

        if (!$computador) {
            return response()->json(['error' => 'Computador não encontrado'], 404);
        }

        // Atualizar os dados do computador
        $computador->update($validated);

        return response()->json($computador, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $computador = Licenca_Computador::find($id);

        if (!$computador) {
            return response()->json(['error' => 'Computador não encontrado'], 404);
        }

        $computador->delete();

        return response()->json(['message' => 'Computador deletado com sucesso'], 200);
    }

    public function toggleBlock($id)
    {
        try {
            // Encontra o computador pelo ID
            $licenca = Licenca_Computador::findOrFail($id);
    
            // Alterna o status de bloqueio
            $licenca->bloqueado = !$licenca->bloqueado;
            $licenca->save();
    
            // Retorna uma mensagem de sucesso
            return redirect()->back()->with('status', 'Status de bloqueio alterado com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao alterar o status de bloqueio.');
        }
    }

    public function businessupdate(Request $request, $id)
    {
        try {
            // Valida os dados enviados
            $request->validate([
                'caminho_banco' => 'nullable|string|max:255',
                'versao_obrigatoria' => 'nullable|string|max:50',
                'versao_disponivel' => 'nullable|string|max:50',
            ]);
        
            // Busca a empresa pelo ID e atualiza os dados
            $empresa = Business::findOrFail($id);
            $empresa->caminho_banco_servidor = $request->caminho_banco_servidor;
            $empresa->versao_obrigatoria = $request->versao_obrigatoria;
            $empresa->versao_disponivel = $request->versao_disponivel;
            $empresa->officeimpresso_numerodemaquinas = $request->officeimpresso_numerodemaquinas;            
            $empresa->save();
        
            // Retorna para a página com uma mensagem de sucesso
            return redirect()->back()->with('status', 'Dados da empresa atualizados com sucesso!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao alterar os dados da empresa.');
        }
    }

    public function businessbloqueado($id)
    {
        try {  
            // Busca a empresa pelo ID e atualiza os dados
            $empresa = Business::findOrFail($id);
            $empresa->officeimpresso_bloqueado = !$empresa->officeimpresso_bloqueado;
            $empresa->save();
        
            // Retorna para a página com uma mensagem de sucesso
            return redirect()->back()->with('status', 'Status de bloqueio alterado com sucesso!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao alterar o status de bloqueio.');
        }
    } 
    
}
