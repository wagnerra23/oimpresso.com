<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Transaction;
use App\Business;
use NFePHP\DA\NFe\Danfce;
use NFePHP\DA\NFe\Cupom;
use NFePHP\DA\Legacy\FilesFolders;
use NFePHP\DA\NFe\Daevento;
use App\Services\NFCeService;

class NfceController extends Controller
{

	public function transmtir(Request $request){

		$business_id = request()->session()->get('user.business_id');
		$transaction = Transaction::where('business_id', $business_id)
		->where('id', $request->id)
		->first();

		if(!$transaction){
			return response()->json('erro', 403);
		}

		$config = Business::find($business_id);


		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		$ncfe_service = new NFCeService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => (int)$config->ambiente,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->cidade->uf,
			"cnpj" => $cnpj,
			"schemes" => "PL_009_V4",
			"versao" => "4.00",
			"tokenIBPT" => "AAAAAAA",
			"CSC" => $config->csc,
			"CSCid" => $config->csc_id
		]);

		if($transaction->estado == 'REJEITADO' || $transaction->estado == 'NOVO'){
			header('Content-type: text/html; charset=UTF-8');

			$nfe = $ncfe_service->gerarNFCe($transaction);
			// return response()->json($signed, 200);
			if(!isset($nfe['erros_xml'])){
				$signed = $ncfe_service->sign($nfe['xml']);
			// return response()->json($signed, 200);
				$resultado = $ncfe_service->transmitir($signed, $nfe['chave'], $cnpj);

				if(!isset($resultado['erro'])){
					$transaction->chave = $nfe['chave'];
					$transaction->numero_nfce = $nfe['nNf'];
					$transaction->estado = 'APROVADO';
					$transaction->save();
					return response()->json($resultado, 200);

				}else{
					$transaction->estado = 'REJEITADO';
					$transaction->save();
					return response()->json($resultado['protocolo'], $resultado['status']);
				}
			}else{
				return response()->json($nfe['erros_xml'][0], 407);

			}

		}else{
			return response()->json("Esta NFC-e já esta aprovada", 200);
		}

		return response()->json($xml, 200);

	}

	public function gerar($id){

		$business_id = request()->session()->get('user.business_id');
		$transaction = Transaction::where('business_id', $business_id)
		->where('id', $id)
		->first();

		$business = Business::find($business_id);

		if(!$transaction){
			abort(403, 'Unauthorized action.');
		}

		if($transaction->numero_nfce > 0){
			return redirect('/nfce/ver/'.$transaction->id);
		}

		$erros = [];

		if($business->cnpj == '00.000.000/0000-00'){
			$msg = 'Informe a configuração do emitente';
			array_push($erros, $msg);
		}

		if(sizeof($erros) > 0){
			return view('nfe.erros')
			->with(compact('erros'));
		}

		return view('nfce.gerar')
		->with(compact('transaction', 'business'));
	}

	public function gerarXml($id){

		$business_id = request()->session()->get('user.business_id');
		$transaction = Transaction::where('business_id', $business_id)
		->where('id', $id)
		->first();

		if(!$transaction){
			abort(403, 'Unauthorized action.');
		}

		$config = Business::find($business_id);

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		$nfce_service = new NFCeService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => (int)$config->ambiente,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->cidade->uf,
			"cnpj" => $cnpj,
			"schemes" => "PL_009_V4",
			"versao" => "4.00",
			"tokenIBPT" => "AAAAAAA",
			"CSC" => $config->csc,
			"CSCid" => $config->csc_id
		]);

		$nfe = $nfce_service->gerarNFCe($transaction);
		if(!isset($nfe['erros_xml'])){
			$xml = $nfe['xml'];

			return response($xml)
			->header('Content-Type', 'application/xml');
		}else{
			foreach($nfe['erros_xml'] as $e){
				echo $e . "<br>";
			}
		}
	}

	public function renderizarDanfce($id){

		$business_id = request()->session()->get('user.business_id');
		$transaction = Transaction::where('business_id', $business_id)
		->where('id', $id)
		->first();

		if(!$transaction){
			abort(403, 'Unauthorized action.');
		}

		$config = Business::find($business_id);

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		$nfce_service = new NFCeService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => (int)$config->ambiente,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->cidade->uf,
			"cnpj" => $cnpj,
			"schemes" => "PL_009_V4",
			"versao" => "4.00",
			"tokenIBPT" => "AAAAAAA",
			"CSC" => $config->csc,
			"CSCid" => $config->csc_id
		]);

		$nfe = $nfce_service->gerarNFCe($transaction);
		// print_r($nfe);

		try {
			$xml = $nfe['xml'];

		// echo public_path('uploads/business_logos/' . $config->logo);

			$danfe = new Danfce($xml);
			$id = $danfe->monta();
			$pdf = $danfe->render();
			return response($pdf)
			->header('Content-Type', 'application/pdf');
		} catch (InvalidArgumentException $e) {
			echo "Ocorreu um erro durante o processamento :" . $e->getMessage();
		}  

	}

	public function imprimir($id){

		$business_id = request()->session()->get('user.business_id');
		$transaction = Transaction::where('business_id', $business_id)
		->where('id', $id)
		->first();

		$business = Business::find($business_id);
		$cnpj = str_replace(".", "", $business->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		if(!$transaction){
			abort(403, 'Unauthorized action.');
		}

		$logo = '';
		if($business->logo){
			$logo = 'data://text/plain;base64,'. base64_encode(file_get_contents(
				public_path('uploads/business_logos/' . $business->logo)));
		}

		try {
			if(file_exists(public_path('xml_nfce/'.$cnpj.'/'.$transaction->chave.'.xml'))){
				$xml = file_get_contents(public_path('xml_nfce/'.$cnpj.'/'.$transaction->chave.'.xml'));

				$danfe = new Danfce($xml);
				$id = $danfe->monta($logo);
				$pdf = $danfe->render();
				return response($pdf)
				->header('Content-Type', 'application/pdf');
			}else{
				return redirect()->back()
				->with('status', [
					'success' => 0,
					'msg' => 'Arquivo não encontrado!!'
				]);
			}


		} catch (InvalidArgumentException $e) {
			echo "Ocorreu um erro durante o processamento :" . $e->getMessage();
		}  

	}

	public function imprimirNaoFiscal($id){

		$business_id = request()->session()->get('user.business_id');
		$transaction = Transaction::where('business_id', $business_id)
		->where('id', $id)
		->first();

		$business = Business::find($business_id);
		$cnpj = str_replace(".", "", $business->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		if(!$transaction){
			abort(403, 'Unauthorized action.');
		}

		$logo = '';
		if($business->logo){
			$logo = public_path('uploads/business_logos/' . $business->logo);
		}

		try {

			// $danfe = new Cupom($transaction);
			// $id = $danfe->monta($logo);
			// $pdf = $danfe->render();
			// return response($pdf)
			// ->header('Content-Type', 'application/pdf');

			$cupom = new Cupom($transaction, $logo, $business);
			$cupom->monta();
			$pdf = $cupom->render();

			return response($pdf)
			->header('Content-Type', 'application/pdf');


		} catch (InvalidArgumentException $e) {
			echo "Ocorreu um erro durante o processamento :" . $e->getMessage();
		}  

	}

	public function ver($id){

		$business_id = request()->session()->get('user.business_id');
		$transaction = Transaction::where('business_id', $business_id)
		->where('id', $id)
		->first();

		$business = Business::find($business_id);

		if(!$transaction){
			abort(403, 'Unauthorized action.');
		}

		if($transaction->numero_nfce == 0){
			return redirect('/nfce/gerar/'.$transaction->id);
		}

		return view('nfce.ver')
		->with(compact('transaction', 'business'));
	}

	public function baixarXml($id){

		$business_id = request()->session()->get('user.business_id');
		$transaction = Transaction::where('business_id', $business_id)
		->where('id', $id)
		->first();

		$business = Business::find($business_id);
		$cnpj = str_replace(".", "", $business->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		if(!$transaction){
			abort(403, 'Unauthorized action.');
		}

		if(file_exists(public_path('xml_nfce/'.$cnpj.'/'.$transaction->chave.'.xml'))){
			return response()->download(public_path('xml_nfce/'.$cnpj.'/'.$transaction->chave.'.xml'));
		}else{
			return redirect()->back()
			->with('status', [
				'success' => 0,
				'msg' => 'Arquivo não encontrado!!'
			]);
		}
	}

	public function cancelar(Request $request){

		$business_id = request()->session()->get('user.business_id');
		$transaction = Transaction::where('business_id', $business_id)
		->where('id', $request->id)
		->first();

		$config = Business::find($business_id);
		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);


		$nfce_service = new NFCeService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => (int)$config->ambiente,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->cidade->uf,
			"cnpj" => $cnpj,
			"schemes" => "PL_009_V4",
			"versao" => "4.00",
			"tokenIBPT" => "AAAAAAA",
			"CSC" => $config->csc,
			"CSCid" => $config->csc_id
		]);


		$nfe = $nfce_service->cancelar($transaction, $request->justificativa, $cnpj);
		if(!isset($nfe['erro'])){

			$transaction->estado = 'CANCELADO';
			$transaction->save();
			return response()->json($nfe, 200);


		}else{
			return response()->json($nfe, $nfe['status']);
		}
	}

	public function lista(){

		$business_id = request()->session()->get('user.business_id');
		$notasAprovadas = [];
		$notasCanceladas = [];
		$business = Business::find($business_id);

		return view('nfce.lista')
		->with(compact('notasCanceladas', 'notasAprovadas', 'business'));

	}

	public function filtro(Request $request){

		$data_inicio = str_replace("/", "-", $request->data_inicio);
		$data_final = str_replace("/", "-", $request->data_final);

		$data_inicio_convert =  \Carbon\Carbon::parse($data_inicio)->format('Y-m-d');
		$data_final_convert =  \Carbon\Carbon::parse($data_final)->format('Y-m-d');
		$data_final_convert = date('Y-m-d', strtotime($data_final_convert. ' + 1 days'));

		$business_id = request()->session()->get('user.business_id');
		$notasAprovadas = Transaction::where('business_id', $business_id)
		->whereBetween('created_at', [
			$data_inicio_convert, 
			$data_final_convert])
		->where('numero_nfce', '>', 0)
		->where('estado', 'APROVADO')
		->orderBy('id', 'desc')
		->get();

		$notasCanceladas = Transaction::where('business_id', $business_id)
		->whereBetween('created_at', [
			$data_inicio_convert, 
			$data_final_convert])
		->where('numero_nfce', '>', 0)
		->where('estado', 'CANCELADO')

		->orderBy('id', 'desc')
		->get();

		$msg = [];

		$business = Business::find($business_id);
		$cnpj = str_replace(".", "", $business->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		if(sizeof($notasAprovadas) > 0){

			try{
				$zip_file = public_path('xml_nfce/'.$cnpj.'/'.'xml.zip');
				$zip = new \ZipArchive();
				$zip->open($zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

				foreach($notasAprovadas as $n){

					if(file_exists(public_path('xml_nfce/'.$cnpj.'/'.$n->chave.'.xml'))){
						$zip->addFile(public_path('xml_nfce/'.$cnpj.'/'.$n->chave.'.xml'), $n->chave . '.xml');
					}

				}
				$zip->close();
			}catch(\Exception $e){
				array_push($msg, "Erro ao gerar arquivo de XML!!");
			}

		}

		if(sizeof($notasCanceladas) > 0){
			try{
				$zip_file = public_path('xml_nfce_cancelada/'.$cnpj.'/'.'xml_cancelado.zip');
				$zip = new \ZipArchive();
				$zip->open($zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

				foreach($notasCanceladas as $n){

					if(file_exists(public_path('xml_nfce_cancelada/'.$cnpj.'/'.$n->chave.'.xml'))){
						$zip->addFile(public_path('xml_nfce_cancelada/'.$cnpj.'/'.$n->chave.'.xml'), $n->chave . '.xml');
					}

				}
				$zip->close();
			}catch(\Exception $e){
				array_push($msg, "Erro ao gerar arquivo de XML de Cancelamento!!");
			}

		}

		return view('nfce.lista')
		->with(compact('notasCanceladas', 'notasAprovadas', 'business', 'data_inicio', 'data_final', 'msg'));
	}

	public function baixarZipXmlAprovado(){
		$business_id = request()->session()->get('user.business_id');
		$business = Business::find($business_id);
		$cnpj = str_replace(".", "", $business->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);
		if(file_exists(public_path('xml_nfce/'.$cnpj.'/'.'xml.zip'))){
			return response()->download(public_path('xml_nfce/'.$cnpj.'/'.'xml.zip'));
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
		if(file_exists(public_path('xml_nfce_cancelada/'.$cnpj.'/'.'xml_cancelado.zip'))){
			return response()->download(public_path('xml_nfce_cancelada/'.$cnpj.'/'.'xml_cancelado.zip'));
		}else{
			return redirect()->back()
			->with('status', [
				'success' => 0,
				'msg' => 'Arquivo não encontrado!!'
			]);
		}
	}

}
