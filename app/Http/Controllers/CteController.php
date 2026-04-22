<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Cte;
use App\Veiculo;
use App\Contact;
use App\City;
use App\MedidaCte;
use App\ComponenteCte;
use App\NaturezaOperacao;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;
use App\Utils\ModuleUtil;
use App\System;

use NFePHP\DA\CTe\Dacte;
use NFePHP\DA\CTe\Daevento;
use App\Services\CTeService;
use App\Business;

class CteController extends Controller
{
	public function __construct(ModuleUtil $moduleUtil)
	{
		$this->moduleUtil = $moduleUtil;
	}

	public function index(){
		if (!auth()->user()->can('user.view') && !auth()->user()->can('user.create')) {
			abort(403, 'Unauthorized action.');
		}

		if (request()->ajax()) {
			$business_id = request()->session()->get('user.business_id');
			$user_id = request()->session()->get('user.id');
			$ctes = Cte::where('business_id', $business_id)
			->select(['id', 'valor_transporte', 'valor_receber', 'valor_carga', 'produto_predominante', 'data_previsata_entrega', 'estado']);


			return Datatables::of($ctes)

			->addColumn(
				'action',
				'<a href="/cte/edit/{{$id}}" class="btn btn-xs btn-primary"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</a>
				&nbsp;<a href="/cte/delete/{{$id}}" class="btn btn-xs btn-danger delete_user_button"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</a>'
			)
			->addColumn('action', function ($row) {
				$t = Cte::find($row->id);
				$html = '';

				if($t->cte_numero > 0){
					$html = '<a class="btn btn-xs btn-danger" href="/cte/ver/'.$row->id.'">Ver CT-e</a>';
				}else{
					$html = '<a class="btn btn-xs btn-primary" href="/cte/gerar/'.$row->id.'">Gerar CT-e</a>';
					$html .= '&nbsp;<a href="/cte/delete/{{$id}}" class="btn btn-xs btn-danger delete_user_button">Remover</a>';

				}

				return $html;
			})

			->removeColumn('id')
			->rawColumns(['action'])
			->make(true);

		}
		return view('cte.list');
	}

	public function new(){

		if (!auth()->user()->can('user.create')) {
			abort(403, 'Unauthorized action.');
		}

		$business_id = request()->session()->get('user.business_id');

		$tipos = Veiculo::tipos();
		$tiposRodado = Veiculo::tiposRodado();
		$tiposCarroceria = Veiculo::tiposCarroceria();
		$tiposProprietario = Veiculo::tiposProprietario();
		$ufs = Veiculo::cUF();

        //Check if subscribed or not, then check for users quota
		if (!$this->moduleUtil->isSubscribed($business_id)) {
			return $this->moduleUtil->expiredResponse();
		} elseif (!$this->moduleUtil->isQuotaAvailable('naturezas', $business_id)) {
			return $this->moduleUtil->quotaExpiredResponse('naturezas', $business_id, action('NaturezaController@index'));
		}

		$roles  = $this->getRolesArray($business_id);
		$username_ext = $this->getUsernameExtension();
		

        //Get user form part from modules

		$lastCte = Cte::lastCTeAux($business_id);
		$unidadesMedida = Cte::unidadesMedida();
		$tiposMedida = Cte::tiposMedida();
		$tiposTomador = Cte::tiposTomador();
		$naturezas = $this->prepareNaturezas();
		$modals = Cte::modals();
		$veiculos = $this->prepareVeiculos();
		$clientesAux =  Contact::where('business_id', $business_id)->get();

		foreach($clientesAux as $c){
			$c->cidade;
		}
		
		$clientes =  $this->prepareClientes();
		$cidades =  $this->prepareCities();

		return view('cte.register')
		->with(compact('roles', 'username_ext', 'cidades', 'clientesAux', 'clientes', 'lastCte', 'unidadesMedida', 'tiposMedida', 'tiposTomador', 'naturezas', 'modals', 'veiculos'));
	}

	private function prepareCities(){
		$cities = City::all();
		$temp = [];
		foreach($cities as $c){
            // array_push($temp, $c->id => $c->nome);
			$temp[$c->id] = $c->nome . " ($c->uf)";
		}
		return $temp;
	}

	private function prepareNaturezas(){
		$business_id = request()->session()->get('user.business_id');

		$naturezas = NaturezaOperacao::
		where('business_id', $business_id)
		->get();
		$temp = [];
		foreach($naturezas as $c){
			$temp[$c->id] = $c->natureza;
		}
		return $temp;
	}

	private function prepareVeiculos(){
		$business_id = request()->session()->get('user.business_id');

		$veiculos = Veiculo::
		where('business_id', $business_id)
		->get();
		$temp = [];
		foreach($veiculos as $v){
			$temp[$v->id] = "$v->placa - $v->modelo";
		}
		return $temp;
	}

	private function prepareClientes(){
		$business_id = request()->session()->get('user.business_id');

		$clientes = Contact::
		where('business_id', $business_id)
		->orderBy('name')
		->get();
		$temp = [];
		foreach($clientes as $c){
			if($c->name != 'Cliente padrão')
				$temp[$c->id] = $c->name . " ($c->cpf_cnpj)";
		}
		return $temp;
	}


	private function getRolesArray($business_id)
	{
		$roles_array = Role::where('business_id', $business_id)->get()->pluck('name', 'id');
		$roles = [];

		$is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);

		foreach ($roles_array as $key => $value) {
			if (!$is_admin && $value == 'Admin#' . $business_id) {
				continue;
			}
			$roles[$key] = str_replace('#' . $business_id, '', $value);
		}
		return $roles;
	}

	private function getUsernameExtension()
	{
		$extension = !empty(System::getProperty('enable_business_based_username')) ? '-' .str_pad(session()->get('business.id'), 2, 0, STR_PAD_LEFT) : null;
		return $extension;
	}

	public function save(Request $request){
		$business_id = request()->session()->get('user.business_id');
		$user_id = request()->session()->get('user.id');
		try{
			$data = [
				'business_id' => $business_id,
				'chave_nfe' => $request->chave_nfe ?? '',
				'remetente_id' => $request->remetente_id,
				'destinatario_id' => $request->destinatario_id,
				'usuario_id' => $user_id,
				'natureza_id' => $request->natureza_id,
				'tomador' => $request->tomador,
				'municipio_envio' => $request->cidade_envio,
				'municipio_inicio' => $request->cidade_inicio,
				'municipio_fim' => $request->cidade_fim,
				'logradouro_tomador' => $request->rua_tomador,
				'numero_tomador' => $request->numero_tomador,
				'bairro_tomador' => $request->bairro_tomador,
				'cep_tomador' => $request->cep_tomador,
				'municipio_tomador' => $request->cidade_tomador,
				'observacao' => $cte['obs'] ?? '',
				'data_previsata_entrega' => $this->parseDate($request->data_prevista_entrega),
				'produto_predominante' => $request->prod_predominante,
				'cte_numero' => 0,
				'sequencia_cce' => 0,
				'chave' => '',
				'path_xml' => '',
				'estado' => 'DISPONIVEL',

				'valor_transporte' => str_replace(",", ".", $request->valor_transporte),
				'valor_receber' => str_replace(",", ".", $request->valor_receber),
				'valor_carga' => str_replace(",", ".", $request->valor_carga),

				'retira' => $request->retira,
				'detalhes_retira' => $request->detalhes_retira ?? '',
				'modal' => $request->modal_transp,
				'veiculo_id' => $request->veiculo_id,
				'tpDoc' => $request->tpDoc ?? '',
				'descOutros' => $request->descOutros ?? '',
				'nDoc' => $request->nDoc ?? 0,
				'vDocFisc' => $request->vDocFisc ?? 0
			];

			$result = Cte::create($data);

			$medidas = json_decode($request->medidas);
		// print_r($medidas);
			foreach($medidas as $m){
				$medida = MedidaCte::create([
					'cod_unidade' => $m->unidade_medida,
					'tipo_medida'=> $m->tipo_medida,
					'quantidade_carga' => str_replace(",", ".", $m->quantidade),
					'cte_id' => $result->id
				]);

			}

			$componentes = json_decode($request->componentes);
		// print_r($medidas);
			foreach($componentes as $c){
				$componente = ComponenteCte::create([
					'nome' => $c->nome,
					'valor' => str_replace(",", ".", $c->valor),
					'cte_id' => $result->id
				]);

			}
			$output = [
				'success' => 1,
				'msg' => "Cte Gerada!!"
			];
		}catch(\Exception $e){
			$output = [
				'success' => false,
				'msg' => "Erro ao gerar CTe"
			];
		}

		return redirect('cte')->with('status', $output);
	}

	private function parseDate($date, $plusDay = false){
		if($plusDay == false)
			return date('Y-m-d', strtotime(str_replace("/", "-", $date)));
		else
			return date('Y-m-d', strtotime("+1 day",strtotime(str_replace("/", "-", $date))));
	}

	public function delete($id){
		if (!auth()->user()->can('user.delete')) {
			abort(403, 'Unauthorized action.');
		}

		try {
			$business_id = request()->session()->get('user.business_id');

			Cte::where('business_id', $business_id)
			->where('id', $id)->delete();

			$output = [
				'success' => true,
				'msg' => 'Registro removido'
			];
		} catch (\Exception $e) {
			\Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());

			$output = [
				'success' => false,
				'msg' => __("messages.something_went_wrong")
			];
		}

		return redirect('cte')->with('status', $output);
	}

	public function gerar($id){
		$business_id = request()->session()->get('user.business_id');

		$cte = Cte::where('business_id', $business_id)
		->where('id', $id)
		->first();

		if(!$cte){
			abort(403, 'Unauthorized action.');
		}


		if($cte->cte_numero > 0){
			return redirect('/cte/ver/'.$cte->id);
		}

		return view('cte.gerar')
		->with(compact('cte'));

	}

	public function renderizar($id){
		$business_id = request()->session()->get('user.business_id');

		$cte = Cte::where('business_id', $business_id)
		->where('id', $id)
		->first();

		if(!$cte){
			abort(403, 'Unauthorized action.');
		}

		$config = Business::find($business_id);

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);


		$cte_service = new CTeService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => (int)$config->ambiente,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->cidade->uf,
			"cnpj" => $cnpj,
			"schemes" => "PL_CTe_300",
			"versao" => '3.00',
			"proxyConf" => [
				"proxyIp" => "",
				"proxyPort" => "",
				"proxyUser" => "",
				"proxyPass" => ""
			]
		]);

		try{
			$doc = $cte_service->gerarCTe($cte);
			if(!isset($doc['erros_xml'])){
				$xml = $doc['xml'];
				$dacte = new Dacte($xml);
				$dacte->monta();
				$pdf = $dacte->render();
				return response($pdf)
				->header('Content-Type', 'application/pdf');

			}else{
				foreach($doc['erros_xml'] as $e){
					echo $e . "<br>";
				}
			}

		}catch(\Excption $e){
			echo $e->getMessage();
		}
	}

	public function gerarXml($id){
		$business_id = request()->session()->get('user.business_id');

		$cte = Cte::where('business_id', $business_id)
		->where('id', $id)
		->first();

		if(!$cte){
			abort(403, 'Unauthorized action.');
		}

		$config = Business::find($business_id);

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);


		$cte_service = new CTeService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => (int)$config->ambiente,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->cidade->uf,
			"cnpj" => $cnpj,
			"schemes" => "PL_CTe_300",
			"versao" => '3.00',
			"proxyConf" => [
				"proxyIp" => "",
				"proxyPort" => "",
				"proxyUser" => "",
				"proxyPass" => ""
			]
		]);

		try{
			$doc = $cte_service->gerarCTe($cte);
			if(!isset($doc['erros_xml'])){
				$xml = $doc['xml'];
				// $dacte = new Dacte($xml);
				// $dacte->monta();
				// $pdf = $dacte->render();
				return response($xml)
				->header('Content-Type', 'application/xml');

			}else{
				foreach($doc['erros_xml'] as $e){
					echo $e . "<br>";
				}
			}

		}catch(\Excption $e){
			echo $e->getMessage();
		}
	}

	public function transmitir(Request $request){
		$business_id = request()->session()->get('user.business_id');

		$cte = Cte::where('business_id', $business_id)
		->where('id', $request->id)
		->first();

		if(!$cte){
			abort(403, 'Unauthorized action.');
		}

		$config = Business::find($business_id);

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);


		$cte_service = new CTeService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => (int)$config->ambiente,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->cidade->uf,
			"cnpj" => $cnpj,
			"schemes" => "PL_CTe_300",
			"versao" => '3.00',
			"proxyConf" => [
				"proxyIp" => "",
				"proxyPort" => "",
				"proxyUser" => "",
				"proxyPass" => ""
			]
		]);


		if($cte->estado == 'REJEITADO' || $cte->estado == 'DISPONIVEL'){
			header('Content-type: text/html; charset=UTF-8');

			$doc = $cte_service->gerarCTe($cte);
			if(!isset($doc['erros_xml'])){
			// return response()->json($signed, 200);

				$signed = $cte_service->sign($doc['xml']);
			// return response()->json($signed, 200);
				$resultado = $cte_service->transmitir($signed, $doc['chave'], $cnpj);

				if(isset($resultado['successo'])){
					$cte->chave = $doc['chave'];
					$cte->cte_numero = $doc['nCte'];
					$cte->path_xml = $cte['chave'] . '.xml';
					$cte->estado = 'APROVADO';
					$cte->save();
					return response()->json($resultado, 200);

				}else{
					$cte->estado = 'REJEITADO';
					$cte->save();
					if(isset($resultado['protocolo'])){
						return response()->json($resultado['protocolo'], $resultado['status']);
					}else{
						return response()->json($resultado, 404);
					}
				}
			}else{
				return response()->json($doc['xml_erros'][0], 407);

			}

		}else{
			return response()->json("Este CT-e já esta aprovada", 403);
		}
	}

	public function imprimir($id){

		$business_id = request()->session()->get('user.business_id');
		$cte = Cte::where('business_id', $business_id)
		->where('id', $id)
		->first();

		$business = Business::find($business_id);
		$cnpj = str_replace(".", "", $business->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		if(!$cte){
			abort(403, 'Unauthorized action.');
		}

		$logo = '';
		if($business->logo){
			$logo = 'data://text/plain;base64,'. base64_encode(file_get_contents(
				public_path('uploads/business_logos/' . $business->logo)));
		}

		try {
			if(file_exists(public_path('xml_cte/'.$cnpj.'/'.$cte->chave.'.xml'))){
				$xml = file_get_contents(public_path('xml_cte/'.$cnpj.'/'.$cte->chave.'.xml'));

				$dacte = new Dacte($xml);

				// $dacte->creditsIntegratorFooter('WEBNFe Sistemas - http://www.webenf.com.br');
				$dacte->monta();
				$pdf = $dacte->render();

				return response($pdf)
				->header('Content-Type', 'application/pdf');
			}else{
				return redirect('/sells')
				->with('status', [
					'success' => 0,
					'msg' => 'Arquivo não encontrado!!'
				]);
			}
		} catch (InvalidArgumentException $e) {
			echo "Ocorreu um erro durante o processamento :" . $e->getMessage();
		}  

	}

	public function imprimirCancelamento($id){

		$business_id = request()->session()->get('user.business_id');
		$cte = Cte::where('business_id', $business_id)
		->where('id', $id)
		->first();

		$business = Business::find($business_id);
		$cnpj = str_replace(".", "", $business->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		if(!$cte){
			abort(403, 'Unauthorized action.');
		}

		$logo = '';
		if($business->logo){
			$logo = 'data://text/plain;base64,'. base64_encode(file_get_contents(
				public_path('uploads/business_logos/' . $business->logo)));
		}

		try {
			if(file_exists(public_path('xml_cte_cancelada/'.$cnpj.'/'.$cte->chave.'.xml'))){
				$xml = file_get_contents(public_path('xml_cte_cancelada/'.$cnpj.'/'.$cte->chave.'.xml'));

				$dacte = new Dacte($xml);

				// $dacte->creditsIntegratorFooter('WEBNFe Sistemas - http://www.webenf.com.br');
				$dacte->monta();
				$pdf = $dacte->render();

				return response($pdf)
				->header('Content-Type', 'application/pdf');
			}else{
				return redirect('/cte')
				->with('status', [
					'success' => 0,
					'msg' => 'Arquivo não encontrado!!'
				]);
			}
		} catch (InvalidArgumentException $e) {
			echo "Ocorreu um erro durante o processamento :" . $e->getMessage();
		}  

	}


	public function ver($id){

		$business_id = request()->session()->get('user.business_id');
		$cte = Cte::where('business_id', $business_id)
		->where('id', $id)
		->first();

		if(!$cte){
			abort(403, 'Unauthorized action.');
		}

		$business = Business::find($business_id);

		if($cte->cte_numero == 0){
			return redirect('/cte/gerar/'.$cte->id);
		}

		return view('cte.ver')
		->with(compact('cte', 'business'));
	}

	public function baixarXml($id){
		if (!auth()->user()->can('user.create')) {
			abort(403, 'Unauthorized action.');
		}

		$business_id = request()->session()->get('user.business_id');
		$cte = Cte::where('business_id', $business_id)
		->where('id', $id)
		->first();

		$business = Business::find($business_id);
		$cnpj = str_replace(".", "", $business->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		if(!$cte){
			abort(403, 'Unauthorized action.');
		}
		if(file_exists(public_path('xml_cte/'.$cnpj.'/'.$cte->chave.'.xml'))){
			return response()->download(public_path('xml_cte/'.$cnpj.'/'.$cte->chave.'.xml'));
		}else{
			return redirect()->back()
			->with('status', [
				'success' => 0,
				'msg' => 'Arquivo não encontrado!!'
			]);
		}
	}

	public function corrigir(Request $request){

		$business_id = request()->session()->get('user.business_id');
		$cte = Cte::where('business_id', $business_id)
		->where('id', $request->id)
		->first();

		$config = Business::find($business_id);
		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);


		$cte_service = new CTeService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => (int)$config->ambiente,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->cidade->uf,
			"cnpj" => $cnpj,
			"schemes" => "PL_CTe_300",
			"versao" => '3.00',
			"proxyConf" => [
				"proxyIp" => "",
				"proxyPort" => "",
				"proxyUser" => "",
				"proxyPass" => ""
			]
		]);


		$doc = $cte_service->cartaCorrecao($cte, $request->justificativa, $cnpj);
		if(!isset($doc['erro'])){
			return response()->json($nfe, 200);

		}else{
			return response()->json($nfe, $nfe['status']);
		}

	}

	public function cancelar(Request $request){

		$business_id = request()->session()->get('user.business_id');
		$cte = Cte::where('business_id', $business_id)
		->where('id', $request->id)
		->first();

		$config = Business::find($business_id);
		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);


		$cte_service = new CTeService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => (int)$config->ambiente,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->cidade->uf,
			"cnpj" => $cnpj,
			"schemes" => "PL_CTe_300",
			"versao" => '3.00',
			"proxyConf" => [
				"proxyIp" => "",
				"proxyPort" => "",
				"proxyUser" => "",
				"proxyPass" => ""
			]
		]);


		$doc = $cte_service->cancelar($cte, $request->justificativa, $cnpj);
		if(!isset($doc['erro'])){

			$cte->estado = 'CANCELADO';
			$cte->save();
			return response()->json($doc, 200);


		}else{
			return response()->json($doc, $doc['status']);
		}

	}




	//Arquivs XML

	public function xmls(){
		$business_id = request()->session()->get('user.business_id');
		$aprovadas = [];
		$canceladas = [];

		$business = Business::find($business_id);
		return view('cte.lista')
		->with(compact('canceladas', 'aprovadas', 'business'));
	}

	public function filtroXml(Request $request){
		$data_inicio = str_replace("/", "-", $request->data_inicio);
		$data_final = str_replace("/", "-", $request->data_final);

		$data_inicio_convert =  \Carbon\Carbon::parse($data_inicio)->format('Y-m-d');
		$data_final_convert =  \Carbon\Carbon::parse($data_final)->format('Y-m-d');
		$data_final_convert = date('Y-m-d', strtotime($data_final_convert. ' + 1 days'));

		$business_id = request()->session()->get('user.business_id');

		$aprovadas = Cte::where('business_id', $business_id)
		->whereBetween('created_at', [
			$data_inicio_convert, 
			$data_final_convert])
		->where('cte_numero', '>', 0)
		->where('estado', 'APROVADO')
		->orderBy('id', 'desc')
		->get();

		$canceladas = Cte::where('business_id', $business_id)
		->whereBetween('created_at', [
			$data_inicio_convert, 
			$data_final_convert])
		->where('cte_numero', '>', 0)
		->where('estado', 'CANCELADO')
		->orderBy('id', 'desc')
		->get();

		$business = Business::find($business_id);
		$cnpj = str_replace(".", "", $business->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		$msg = [];

		if(sizeof($aprovadas) > 0){
			try{
				$zip_file = public_path('xml_cte/'.$cnpj.'/'.'xml.zip');
				$zip = new \ZipArchive();
				$zip->open($zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

				foreach($aprovadas as $n){

					if(file_exists(public_path('xml_cte/'.$cnpj.'/'.$n->chave.'.xml'))){
						$zip->addFile(public_path('xml_cte/'.$cnpj.'/'.$n->chave.'.xml'), $n->chave . '.xml');
					}

				}
				$zip->close();
			}catch(\Exception $e){
				array_push($msg, "Erro ao gerar arquivo de XML!!");
			}

		}

		if(sizeof($canceladas) > 0){

			try{
				$zip_file = public_path('xml_cte_cancelada/'.$cnpj.'/'.'xml_cancelado.zip');
				$zip = new \ZipArchive();
				$zip->open($zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

				foreach($canceladas as $n){

					if(file_exists(public_path('xml_cte_cancelada/'.$cnpj.'/'.$n->chave.'.xml'))){
						$zip->addFile(public_path('xml_cte_cancelada/'.$cnpj.'/'.$n->chave.'.xml'), $n->chave . '.xml');
					}

				}
				$zip->close();
			}catch(\Exception $e){
				array_push($msg, "Erro ao gerar arquivo de XML de Cancelamento!!");
			}

		}

		return view('cte.lista')
		->with(compact('canceladas', 'aprovadas', 'business', 'data_inicio', 'data_final', 'msg'));
	}

	public function baixarZipXmlAprovado(){
		$business_id = request()->session()->get('user.business_id');
		$business = Business::find($business_id);
		$cnpj = str_replace(".", "", $business->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);
		if(file_exists(public_path('xml_cte/'.$cnpj.'/'.'xml.zip'))){
			return response()->download(public_path('xml_cte/'.$cnpj.'/'.'xml.zip'));
		}else{
			return redirect()->back()
			->with('status', [
				'success' => 0,
				'msg' => 'Arquivo não encontrado!!'
			]);
		}
	}

	public function baixarZipXmlReprovado(){
		$business_id = request()->session()->get('user.business_id');
		$business = Business::find($business_id);
		$cnpj = str_replace(".", "", $business->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);
		if(file_exists(public_path('xml_cte_cancelada/'.$cnpj.'/'.'xml_cancelado.zip'))){
			return response()->download(public_path('xml_cte_cancelada/'.$cnpj.'/'.'xml_cancelado.zip'));
		}else{
			return redirect()->back()
			->with('status', [
				'success' => 0,
				'msg' => 'Arquivo não encontrado!!'
			]);
		}
	}

	public function consultar(Request $request){

		$business_id = request()->session()->get('user.business_id');
		$cte = Cte::where('business_id', $business_id)
		->where('id', $request->id)
		->first();

		$config = Business::find($business_id);
		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);


		$cte_service = new CTeService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => (int)$config->ambiente,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->cidade->uf,
			"cnpj" => $cnpj,
			"schemes" => "PL_CTe_300",
			"versao" => '3.00',
			"proxyConf" => [
				"proxyIp" => "",
				"proxyPort" => "",
				"proxyUser" => "",
				"proxyPass" => ""
			]
		]);


		try{
			$res = $cte_service->consultar($cte);
			return response()->json($res, 200);
		}catch(\Exception $e){
			return response()->json($e->getMessage(), 401);

		}

	}


}
