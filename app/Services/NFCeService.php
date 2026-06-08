<?php
namespace App\Services;

use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Common\Standardize;
use App\Business;
use App\Transaction;
use NFePHP\NFe\Complements;
use NFePHP\DA\NFe\Danfe;
use NFePHP\DA\Legacy\FilesFolders;
use NFePHP\Common\Soap\SoapCurl;
use App\Tributacao;

error_reporting(E_ALL);
ini_set('display_errors', 'On');

class NFCeService{

	private $config; 
	private $tools;

	public function __construct($config){
		$business_id = request()->session()->get('user.business_id');
		$certificado = Business::find($business_id);
		$this->config = $config;
		$this->tools = new Tools(json_encode($config), Certificate::readPfx($certificado->certificado, base64_decode($certificado->senha_certificado)));
		$this->tools->model('65');
		
	}

	public function gerarNFCe($venda){
		
		$business_id = request()->session()->get('user.business_id');
		$config = Business::find($business_id);

		$nfe = new Make();
		$stdInNFe = new \stdClass();
		$stdInNFe->versao = '4.00'; //versão do layout
		$stdInNFe->Id = null; //se o Id de 44 digitos não for passado será gerado automaticamente
		$stdInNFe->pk_nItem = ''; //deixe essa variavel sempre como NULL

		$infNFe = $nfe->taginfNFe($stdInNFe);

		//IDE
		$stdIde = new \stdClass();
		$stdIde->cUF = $config->getcUF($config->cidade->uf);
		$stdIde->cNF = rand(11111111, 99999999);
		$stdIde->natOp = 'Venda de produto do estabelecimento';

		// $stdIde->indPag = 1; //NÃO EXISTE MAIS NA VERSÃO 4.00 // forma de pagamento

		$vendaLast = $venda->lastNFCe();
		$lastNumero = $vendaLast;

		$stdIde->mod = 65;
		$stdIde->serie = $config->numero_serie_nfce;
		$stdIde->nNF = (int)$lastNumero+1; //******=========p=p=p=p=p
		$stdIde->dhEmi = date("Y-m-d\TH:i:sP");
		$stdIde->dhSaiEnt = date("Y-m-d\TH:i:sP");
		$stdIde->tpNF = 1;
		$stdIde->idDest = 1;
		$stdIde->cMunFG = $config->cidade->codigo;
		$stdIde->tpImp = 4;
		$stdIde->tpEmis = 1;
		$stdIde->cDV = 0;
		$stdIde->tpAmb = $config->ambiente;
		$stdIde->finNFe = 1;
		$stdIde->indFinal = 1;
		$stdIde->indPres = 1;
		$stdIde->procEmi = '0';
		$stdIde->verProc = '2.0';
		//

		$tagide = $nfe->tagide($stdIde);

		$stdEmit = new \stdClass();
		$stdEmit->xNome = $config->razao_social;
		$stdEmit->xFant = $config->name;

		$ie = str_replace(".", "", $config->ie);
		$ie = str_replace("/", "", $ie);
		$ie = str_replace("-", "", $ie);
		$stdEmit->IE = $ie;
		$stdEmit->CRT = $config->regime;

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$stdEmit->CNPJ = $cnpj;
		// $stdEmit->IM = $ie;

		$emit = $nfe->tagemit($stdEmit);

		// ENDERECO EMITENTE
		$stdEnderEmit = new \stdClass();
		$stdEnderEmit->xLgr = $config->rua;
		$stdEnderEmit->nro = $config->numero;
		$stdEnderEmit->xCpl = "";
		
		$stdEnderEmit->xBairro = $config->bairro;
		$stdEnderEmit->cMun = $config->cidade->codigo;
		$stdEnderEmit->xMun = $config->cidade->nome;
		$stdEnderEmit->UF = $config->cidade->uf;

		$cep = str_replace("-", "", $config->cep);
		$cep = str_replace(".", "", $cep);
		$stdEnderEmit->CEP = $cep;
		$stdEnderEmit->cPais = '1058';
		$stdEnderEmit->xPais = 'BRASIL';

		$enderEmit = $nfe->tagenderEmit($stdEnderEmit);

		// DESTINATARIO
		
		if(strlen($venda->cpf_nota) >= 11){
			$stdDest = new \stdClass();
			$cpf = $venda->cpf_nota;
			$cpf = str_replace(".", "", $cpf);
			$cpf = str_replace("-", "", $cpf);
			$cpf = str_replace(" ", "", $cpf);

			$stdDest->indIEDest = "9";
			$stdDest->CPF = $cpf;
			$dest = $nfe->tagdest($stdDest);
		}



		$somaProdutos = 0;
		$somaICMS = 0;
		//PRODUTOS
		$itemCont = 0;
		$totalItens = count($venda->sell_lines);
		$somaAcrescimo = 0;
		$somaDesconto = 0;
		$totalDesconto = $venda->total_before_tax -  $venda->final_total;
		foreach($venda->sell_lines as $i){
			$itemCont++;

			$stdProd = new \stdClass();
			$stdProd->item = $itemCont;
			$stdProd->cEAN = !$this->validaEan13($i->product->sku) ? $i->product->sku : 'SEM GTIN';
			// $stdProd->cEAN = strlen($i->product->sku) < 7 ? 'SEM GTIN' : $i->product->sku;
			$stdProd->cEANTrib = !$this->validaEan13($i->product->sku) ? $i->product->sku : 'SEM GTIN';
			// $stdProd->cEANTrib = strlen($i->product->sku) < 7 ? 'SEM GTIN' : $i->product->sku;
			$stdProd->cProd = $i->product->id;
			$stdProd->xProd = $i->product->name;

			$ncm = $i->product->ncm;
			$ncm = str_replace(".", "", $ncm);
			$stdProd->NCM = $ncm;

			$stdProd->CFOP = $i->product->cfop_interno;
			$cest = $i->product->cest != null ? $i->product->cest : '';
			$cest = str_replace(".", "", $cest);
			$stdProd->CEST = $cest;
			$stdProd->uCom = $i->product->unit->short_name;
			$stdProd->qCom = $i->quantity;
			$stdProd->vUnCom = $this->format($i->unit_price);
			$stdProd->vProd = $this->format(($i->quantity * $i->unit_price));
			$stdProd->uTrib = $i->product->unit->short_name;
			$stdProd->qTrib = $i->quantity;
			$stdProd->vUnTrib = $this->format($i->unit_price);
			$stdProd->indTot = 1;
			// fim calculo
			

			$vDesc = 0;
			if($totalDesconto > 0){
				if($itemCont < sizeof($venda->sell_lines)){
					$stdProd->vDesc = $this->format($totalDesconto/$totalItens);
					$somaDesconto += $vDesc = $totalDesconto/$totalItens;
				}else{
					$stdProd->vDesc = $somaDesconto = $vDesc = $totalDesconto - $somaDesconto;
				}
			}

			$somaProdutos += $i->quantity * $i->unit_price;


			$prod = $nfe->tagprod($stdProd);

			$stdImposto = new \stdClass();
			$stdImposto->item = $itemCont;

			$imposto = $nfe->tagimposto($stdImposto);

			if($config->regime == 3){ 

				//$venda->produto->CST  CST

				$stdICMS = new \stdClass();
				$stdICMS->item = $itemCont; 
				$stdICMS->orig = 0;
				$stdICMS->CST = $i->product->cst_csosn;
				$stdICMS->modBC = 0;
				$stdICMS->vBC = $this->format($i->unit_price * $i->quantity);
				$stdICMS->pICMS = $this->format($i->product->perc_icms);
				$stdICMS->vICMS = $stdICMS->vBC * ($stdICMS->pICMS/100);

				$somaICMS += (($i->unit_price * $i->quantity) 
					* ($stdICMS->pICMS/100));
				$ICMS = $nfe->tagICMS($stdICMS);


					// regime simples
			}else{ 

				//$venda->produto->CST CSOSN

				$stdICMS = new \stdClass();

				$stdICMS->item = $itemCont; 
				$stdICMS->orig = 0;
				$stdICMS->CSOSN = $i->product->cst_csosn;

				if($i->product->cst_csosn == '500'){
					$stdICMS->vBCSTRet = 0.00;
					$stdICMS->pST = 0.00;
					$stdICMS->vICMSSTRet = 0.00;
				}

				$stdICMS->pCredSN = $this->format($i->product->perc_icms);
				$stdICMS->vCredICMSSN = $this->format($i->product->perc_icms);
				$ICMS = $nfe->tagICMSSN($stdICMS);
				$somaICMS = 0;

			}



			$stdPIS = new \stdClass();
			$stdPIS->item = $itemCont; 
			$stdPIS->CST = $i->product->cst_pis;
			$stdPIS->vBC = $this->format($i->product->perc_pis) > 0 ? $stdProd->vProd : 0.00;
			$stdPIS->pPIS = $this->format($i->product->perc_pis);
			$stdPIS->vPIS = $this->format(($stdProd->vProd * $i->quantity) * 
				($i->product->perc_pis/100));
			$PIS = $nfe->tagPIS($stdPIS);

				//COFINS
			$stdCOFINS = new \stdClass();
			$stdCOFINS->item = $itemCont; 
			$stdCOFINS->CST = $i->product->cst_cofins;
			$stdCOFINS->vBC = $this->format($i->product->perc_cofins) > 0 ? $stdProd->vProd : 0.00;
			$stdCOFINS->pCOFINS = $this->format($i->product->perc_cofins);
			$stdCOFINS->vCOFINS = $this->format(($stdProd->vProd * $i->quantity) * 
				($i->product->perc_cofins/100));
			$COFINS = $nfe->tagCOFINS($stdCOFINS);


		}

		//ICMS TOTAL
		$stdICMSTot = new \stdClass();
		$stdICMSTot->vBC = $config->regime == 3 ? $this->format($somaProdutos) : 0.00;
		$stdICMSTot->vICMS = $this->format($somaICMS);
		$stdICMSTot->vICMSDeson = 0.00;
		$stdICMSTot->vBCST = 0.00;
		$stdICMSTot->vST = 0.00;
		$stdICMSTot->vProd = $this->format($somaProdutos);
		
		$stdICMSTot->vFrete = 0.00;

		$stdICMSTot->vSeg = 0.00;
		$stdICMSTot->vDesc = $this->format($somaDesconto);
		$stdICMSTot->vII = 0.00;
		$stdICMSTot->vIPI = 0.00;
		$stdICMSTot->vPIS = 0.00;
		$stdICMSTot->vCOFINS = 0.00;
		$stdICMSTot->vOutro = 0.00;
		$stdICMSTot->vNF = $this->format($somaProdutos-$somaDesconto);
		$stdICMSTot->vTotTrib = 0.00;
		$ICMSTot = $nfe->tagICMSTot($stdICMSTot);
		

		$stdTransp = new \stdClass();
		$stdTransp->modFrete = 9;

		$transp = $nfe->tagtransp($stdTransp);

		
		$stdPag = new \stdClass();

		$stdPag->vTroco = $this->format($venda->troco); 


		$pag = $nfe->tagpag($stdPag);

		//Resp Tecnico
		$stdResp = new \stdClass();
		$stdResp->CNPJ = getenv('RESP_CNPJ'); 
		$stdResp->xContato= getenv('RESP_NOME');
		$stdResp->email = getenv('RESP_EMAIL'); 
		$stdResp->fone = getenv('RESP_FONE'); 

		$nfe->taginfRespTec($stdResp);

		//DETALHE PAGAMENTO

		$stdDetPag = new \stdClass();
		$stdDetPag->indPag = 0;

		foreach($venda->payment_lines as $det){


			if($det->method == 'cash'){
				$tipo = '01';
			}else if($det->method == 'card'){
				$tipo = '03';
			}else{
				$tipo = '99';
			}
			$stdDetPag->tPag = $tipo;
			// $stdDetPag->tPag = $tipo; 

			$stdDetPag->vPag = $this->format($det->amount); 

			if($tipo == '03' || $tipo == '04'){
				$stdDetPag->CNPJ = '12345678901234';
				$stdDetPag->tBand = '01';
				$stdDetPag->cAut = '3333333';
				$stdDetPag->tpIntegra = 1;
			}

			// $std->tpIntegra = 1; //incluso na NT 2015/002
			// $std->indPag = '0'; //0= Pagamento à Vista 1= Pagamento à Prazo

			$detPag = $nfe->tagdetPag($stdDetPag);
		}
			// die();



		//INFO ADICIONAL
		// $stdInfoAdic = new \stdClass();
		// $stdInfoAdic->infAdFisco = 'informacoes para o fisco';
		// $stdInfoAdic->infCpl = 'informacoes complementares';

		// $infoAdic = $nfe->taginfAdic($stdInfoAdic);
		try{
			$nfe->monta();
			$arr = [
				'chave' => $nfe->getChave(),
				'xml' => $nfe->getXML(),
				'nNf' => $stdIde->nNF,
				'modelo' => $nfe->getModelo()
			];
			return $arr;
		}catch(\Exception $e){
			return [
				'erros_xml' => $nfe->getErrors()
			];
		}


	}

	public function format($number, $dec = 2){
		return number_format((float) $number, $dec, ".", "");
	}

	public function sign($xml){
		return $this->tools->signNFe($xml);
	}

	public function transmitir($signXml, $chave, $cnpj){
		try{
			$idLote = str_pad(100, 15, '0', STR_PAD_LEFT);
			$resp = $this->tools->sefazEnviaLote([$signXml], $idLote);
			sleep(2);
			$st = new Standardize();
			$std = $st->toStd($resp);

			if ($std->cStat != 103) {

				// return "[$std->cStat] - $std->xMotivo";
				return ['erro' => true, 'protocolo' => "[$std->cStat] - $std->xMotivo", 'status' => 402];
			}

			$recibo = $std->infRec->nRec; 
			sleep(3);

			$protocolo = $this->tools->sefazConsultaRecibo($recibo);

			try {
				$xml = Complements::toAuthorize($signXml, $protocolo);
				header('Content-type: text/xml; charset=UTF-8');

				if(!is_dir(public_path('xml_nfce/'.$cnpj))){
					mkdir(public_path('xml_nfce/'.$cnpj), 0777, true);
				}
				file_put_contents(public_path('xml_nfce/'.$cnpj.'/'.$chave.'.xml'), $xml);
				return $recibo;
				// $this->printDanfe($xml);
			} catch (\Exception $e) {
				return ['erro' => true, 'protocolo' => $st->toJson($protocolo), 'status' => 401];
			}

		} catch(\Exception $e){
			return "erro: ".$e->getMessage() ;
		}

	}	

	public function cancelar($venda, $justificativa, $cnpj){
		try {

			$chave = $venda->chave;
			$response = $this->tools->sefazConsultaChave($chave);
			$stdCl = new Standardize($response);
			$arr = $stdCl->toArray();
			sleep(1);
				// return $arr;
			$xJust = $justificativa;

			$nProt = $arr['protNFe']['infProt']['nProt'];

			$response = $this->tools->sefazCancela($chave, $xJust, $nProt);
			sleep(2);
			$stdCl = new Standardize($response);
			$std = $stdCl->toStd();
			$arr = $stdCl->toArray();
			$json = $stdCl->toJson();

			if ($std->cStat != 128) {

			} else {
				$cStat = $std->retEvento->infEvento->cStat;
				$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
				if ($cStat == '101' || $cStat == '135' || $cStat == '155' ) {

					$xml = Complements::toAuthorize($this->tools->lastRequest, $response);

					if(!is_dir(public_path('xml_nfce_cancelada/'.$cnpj))){
						mkdir(public_path('xml_nfce_cancelada/'.$cnpj), 0777, true);
					}
					file_put_contents(public_path('xml_nfce_cancelada/'.$cnpj.'/'.$chave.'.xml'), $xml);

					return $arr;
				} else {

					return ['erro' => true, 'data' => $arr, 'status' => 402];	
				}
			}    
		} catch (\Exception $e) {
			echo $e->getMessage();
    //TRATAR
		}
	}

	private function validaEan13($code){
		if(strlen($code) < 10) return true;
		$weightflag = true;
		$sum = 0;
		for ($i = strlen($code) - 1; $i >= 0; $i--) {
			$sum += (int)$code[$i] * ($weightflag?3:1);
			$weightflag = !$weightflag;
		}
		return (10 - ($sum % 10)) % 10;
	}

}