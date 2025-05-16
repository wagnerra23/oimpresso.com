<?php

namespace App\Services;
use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Common\Standardize;
use App\Certificado;
use App\Business;
use App\Devolucao;
use NFePHP\NFe\Complements;
use NFePHP\DA\NFe\Danfe;
use NFePHP\DA\Legacy\FilesFolders;
use NFePHP\Common\Soap\SoapCurl;

error_reporting(E_ALL);
ini_set('display_errors', 'On');

class DevolucaoService{

	private $config; 
	private $tools;

	public function __construct($config){
		$business_id = request()->session()->get('user.business_id');
		$certificado = Business::find($business_id);
		$this->config = $config;
		$this->tools = new Tools(json_encode($config), Certificate::readPfx($certificado->certificado, base64_decode($certificado->senha_certificado)));
		$this->tools->model('55');
		
	}

	public function gerarDevolucao($devolucao){

		$business_id = request()->session()->get('user.business_id');
		$config = Business::find($business_id);

		$nfe = new Make();
		$stdInNFe = new \stdClass();
		$stdInNFe->versao = '4.00'; 
		$stdInNFe->Id = null; 
		$stdInNFe->pk_nItem = ''; 

		$infNFe = $nfe->taginfNFe($stdInNFe);

		$lastNumero = Devolucao::lastNFe($business_id);
		
		$stdIde = new \stdClass();
		$stdIde->cUF = $config->getcUF($config->cidade->uf);
		$stdIde->cNF = rand(11111,99999);
		// $stdIde->natOp = $venda->natureza->natureza;
		$stdIde->natOp = $devolucao->natureza->natureza;

		// $stdIde->indPag = 1; //NÃO EXISTE MAIS NA VERSÃO 4.00 // forma de pagamento

		$stdIde->mod = 55;
		$stdIde->serie = $config->numero_serie_nfe;
		$stdIde->nNF = (int)$lastNumero+1;
		$stdIde->dhEmi = date("Y-m-d\TH:i:sP");
		$stdIde->dhSaiEnt = date("Y-m-d\TH:i:sP");
		$stdIde->tpNF = 1;
		$stdIde->idDest = $config->cidade->uf != $devolucao->contact->cidade->uf ? 2 : 1;
		$stdIde->cMunFG = $config->cidade->codigo;

		$stdIde->tpImp = 1;
		$stdIde->tpEmis = 1;
		$stdIde->cDV = 0;
		$stdIde->tpAmb = $config->ambiente;
		$stdIde->finNFe = 4; 
		$stdIde->indFinal = $devolucao->contact->consumidor_final;
		$stdIde->indPres = 1;
		$stdIde->procEmi = '0';
		$stdIde->verProc = '2.0';
		// $stdIde->dhCont = null;
		// $stdIde->xJust = null;

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
		$stdEmit->IM = $ie;


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
		$stdDest = new \stdClass();
		$stdDest->xNome = $devolucao->contact->name;

		if($devolucao->contact->contribuinte){
			if($devolucao->contact->ie_rg == 'ISENTO' || $devolucao->contact->ie_rg == NULL){
				$stdDest->indIEDest = "2";
			}else{
				$stdDest->indIEDest = "1";
			}

		}else{
			$stdDest->indIEDest = "9";
		}



		$cnpj_cpf = str_replace(".", "", $devolucao->contact->cpf_cnpj);
		$cnpj_cpf = str_replace("/", "", $cnpj_cpf);
		$cnpj_cpf = str_replace("-", "", $cnpj_cpf);

		if(strlen($cnpj_cpf) == 14){
			$stdDest->CNPJ = $cnpj_cpf;
			$ie = str_replace(".", "", $devolucao->contact->ie_rg);
			$ie = str_replace("/", "", $ie);
			$ie = str_replace("-", "", $ie);
			$stdDest->IE = $ie;
		}
		else{
			$stdDest->CPF = $cnpj_cpf;
		} 

		$dest = $nfe->tagdest($stdDest);



		$stdEnderDest = new \stdClass();
		$stdEnderDest->xLgr = $devolucao->contact->rua;
		$stdEnderDest->nro = $devolucao->contact->numero;
		$stdEnderDest->xCpl = "";
		$stdEnderDest->xBairro = $devolucao->contact->bairro;
		$stdEnderDest->cMun = $devolucao->contact->cidade->codigo;
		$stdEnderDest->xMun = strtoupper($devolucao->contact->cidade->nome);
		$stdEnderDest->UF = $devolucao->contact->cidade->uf;

		$cep = str_replace("-", "", $devolucao->contact->cep);
		$cep = str_replace(".", "", $cep);
		$stdEnderDest->CEP = $cep;
		$stdEnderDest->cPais = "1058";
		$stdEnderDest->xPais = "BRASIL";

		$enderDest = $nfe->tagenderDest($stdEnderDest);


		$somaProdutos = 0;
		$somaICMS = 0;
		//PRODUTOS
		$itemCont = 0;

		$totalItens = count($devolucao->itens);
		$somaFrete = 0;


		$std = new \stdClass();
		$std->refNFe = $devolucao->chave_nf_entrada;

		$nfe->tagrefNFe($std);

		foreach($devolucao->itens as $i){
			$itemCont++;

			$stdProd = new \stdClass();
			$stdProd->item = $itemCont;
			$stdProd->cEAN = $i->codBarras;
			$stdProd->cEANTrib = $i->codBarras;
			$stdProd->cProd = $i->cod;
			$stdProd->xProd = $i->nome;
			$ncm = $i->ncm;
			$ncm = str_replace(".", "", $ncm);
			$stdProd->NCM = $ncm;
			$stdProd->CFOP = $config->UF != $devolucao->contact->cidade->uf ?
			$devolucao->natureza->cfop_saida_estadual : $devolucao->natureza->cfop_saida_inter_estadual;
			
			$stdProd->uCom = $i->unidade_medida;
			$stdProd->qCom = $i->quantidade;
			$stdProd->vUnCom = $this->format($i->valor_unit);
			$stdProd->vProd = $this->format(($i->quantidade * $i->valor_unit));
			$stdProd->uTrib = $i->unidade_medida;
			$stdProd->qTrib = $i->quantidade;
			$stdProd->vUnTrib = $this->format($i->valor_unit);
			$stdProd->indTot = 1;
			$somaProdutos += ($i->quantidade * $i->valor_unit);

			// if($devolucao->vDesc > 0){
			// 	$stdProd->vDesc = $this->format($devolucao->vDesc/$totalItens);
			// }

			// if($venda->frete){
			// 	if($venda->frete->valor > 0){
			// 		$somaFrete += $vFt = $venda->frete->valor/$totalItens;
			// 		$stdProd->vFrete = $this->format($vFt);
			// 	}
			// }

			$prod = $nfe->tagprod($stdProd);

		//TAG IMPOSTO

			$stdImposto = new \stdClass();
			$stdImposto->item = $itemCont;

			$imposto = $nfe->tagimposto($stdImposto);

			// ICMS
			if($config->regime == 3){ // regime normal

				//$venda->produto->CST  CST
				
				$stdICMS = new \stdClass();
				$stdICMS->item = $itemCont; 
				$stdICMS->orig = 0;
				$stdICMS->CST = '00';
				$stdICMS->modBC = 0;
				$stdICMS->vBC = 0.00;
				$stdICMS->pICMS = 0.00;
				$stdICMS->vICMS = 0.00;

				$somaICMS += 0;
				$ICMS = $nfe->tagICMS($stdICMS);

			}else{ // regime simples

				//$venda->produto->CST CSOSN
				
				$stdICMS = new \stdClass();
				
				$stdICMS->item = $itemCont; 
				$stdICMS->orig = 0;
				$stdICMS->CSOSN = '101';
				$stdICMS->pCredSN = 0.00;
				$stdICMS->vCredICMSSN = 0.00;
				$ICMS = $nfe->tagICMSSN($stdICMS);

				$somaICMS = 0;
			}

			
			$stdPIS = new \stdClass();//PIS
			$stdPIS->item = $itemCont; 
			$stdPIS->CST = '49';
			$stdPIS->vBC = 0.00;
			$stdPIS->pPIS = 0.00;
			$stdPIS->vPIS = 0.00;
			$PIS = $nfe->tagPIS($stdPIS);


			$stdCOFINS = new \stdClass();//COFINS
			$stdCOFINS->item = $itemCont; 
			$stdCOFINS->CST = '49';
			$stdCOFINS->vBC = 0.00;
			$stdCOFINS->pCOFINS = 0.00;
			$stdCOFINS->vCOFINS = 0.00;
			$COFINS = $nfe->tagCOFINS($stdCOFINS);

			

			// $std = new \stdClass();//IPI
			// $std->item = $itemCont; 
			// $std->clEnq = null;
			// $std->CNPJProd = null;
			// $std->cSelo = null;
			// $std->qSelo = null;
			// $std->cEnq = '999'; //999 – para tributação normal IPI
			// $std->CST = $i->produto->CST_IPI;
			// $std->vBC = 0.00;
			// $std->pIPI = 0.00;
			// $std->vIPI = 0.00;
			// $std->qUnid = null;
			// $std->vUnid = null;

			// $nfe->tagIPI($std);
		}



		$stdICMSTot = new \stdClass();
		$stdICMSTot->vBC = 0.00;
		$stdICMSTot->vICMS = $this->format($somaICMS);
		$stdICMSTot->vICMSDeson = 0.00;
		$stdICMSTot->vBCST = 0.00;
		$stdICMSTot->vST = 0.00;
		$stdICMSTot->vProd = $this->format($somaProdutos);

		$stdICMSTot->vFrete = 0.00;

		$stdICMSTot->vSeg = 0.00;
		$stdICMSTot->vDesc = 0.00;
		// $stdICMSTot->vDesc = $this->format($devolucao->vDesc);
		$stdICMSTot->vII = 0.00;
		$stdICMSTot->vIPI = 0.00;
		$stdICMSTot->vPIS = 0.00;
		$stdICMSTot->vCOFINS = 0.00;
		$stdICMSTot->vOutro = 0.00;

		if($devolucao->vFrete){
			$stdICMSTot->vNF = 
			$this->format(($somaProdutos));
		} 
		else $stdICMSTot->vNF = $this->format($somaProdutos-$devolucao->vDesc);

		$stdICMSTot->vTotTrib = 0.00;
		$ICMSTot = $nfe->tagICMSTot($stdICMSTot);


		$stdTransp = new \stdClass();
		$stdTransp->modFrete = '9';

		$transp = $nfe->tagtransp($stdTransp);


		$stdPag = new \stdClass();
		$pag = $nfe->tagpag($stdPag);

		$stdDetPag = new \stdClass();


		$stdDetPag->tPag = '90';
		$stdDetPag->vPag = 0.00; 

		$stdDetPag->indPag = '0'; // sem pagamento 

		$detPag = $nfe->tagdetPag($stdDetPag);


		$stdInfoAdic = new \stdClass();
		$stdInfoAdic->infCpl = $devolucao->observacao;

		$infoAdic = $nfe->taginfAdic($stdInfoAdic);



		$std = new \stdClass();
		$std->CNPJ = getenv('RESP_CNPJ'); //CNPJ da pessoa jurídica responsável pelo sistema utilizado na emissão do documento fiscal eletrônico
		$std->xContato= getenv('RESP_NOME'); //Nome da pessoa a ser contatada
		$std->email = getenv('RESP_EMAIL'); //E-mail da pessoa jurídica a ser contatada
		$std->fone = getenv('RESP_FONE'); //Telefone da pessoa jurídica/física a ser contatada
		
		
		$nfe->taginfRespTec($std);

		if($nfe->montaNFe()){
			$arr = [
				'chave' => $nfe->getChave(),
				'xml' => $nfe->getXML(),
				'nNf' => $stdIde->nNF
			];
			return $arr;
		} else {
			throw new Exception("Erro ao gerar NFe");
		}

	}

	public function sign($xml){
		return $this->tools->signNFe($xml);
	}

	public function transmitir($signXml, $chave, $cnpj){
		try{
			$idLote = str_pad(100, 15, '0', STR_PAD_LEFT);
			$resp = $this->tools->sefazEnviaLote([$signXml], $idLote);

			$st = new Standardize();
			$std = $st->toStd($resp);

			if ($std->cStat != 103) {

				return "[$std->cStat] - $std->xMotivo";
			}
			$recibo = $std->infRec->nRec; 
			$protocolo = $this->tools->sefazConsultaRecibo($recibo);
			//return $protocolo;
			$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
			try {
				$xml = Complements::toAuthorize($signXml, $protocolo);
				header('Content-type: text/xml; charset=UTF-8');
				if(!is_dir(public_path('xml_devolucao/'.$cnpj))){
					mkdir(public_path('xml_devolucao/'.$cnpj), 0777, true);
				}
				file_put_contents(public_path('xml_devolucao/'.$cnpj.'/'.$chave.'.xml'), $xml);
				return $recibo;
				// $this->printDanfe($xml);
			} catch (\Exception $e) {
				return ['erro' => true, 'protocolo' => $st->toJson($protocolo), 'status' => 401];
			}

		} catch(\Exception $e){
			return ['erro' => true, 'protocolo' => $e->getMessage(), 'status' => 401];
		}

	}	

	public function format($number, $dec = 2){
		return number_format((float) $number, $dec, ".", "");
	}

	public function cancelar($devolucao, $justificativa, $cnpj){
		try {

			$chave = $devolucao->chave_gerada;
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
        //TRATAR
			} else {
				$cStat = $std->retEvento->infEvento->cStat;
				$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
				if ($cStat == '101' || $cStat == '135' || $cStat == '155' ) {
            //SUCESSO PROTOCOLAR A SOLICITAÇÂO ANTES DE GUARDAR
					$xml = Complements::toAuthorize($this->tools->lastRequest, $response);
					if(!is_dir(public_path('xml_devolucao_cancelado/'.$cnpj))){
						mkdir(public_path('xml_devolucao_cancelado/'.$cnpj), 0777, true);
					}
					file_put_contents(public_path('xml_devolucao_cancelado/'.$cnpj.'/'.$chave.'.xml'), $xml);
					return $arr;
				} else {
					return ['erro' => true, 'data' => $arr, 'status' => 402];	
				}
			}    
		} catch (\Exception $e) {
			return $e->getMessage();
			return ['erro' => true, 'data' => $e->getMessage(), 'status' => 402];
    //TRATAR
		}
	}

}