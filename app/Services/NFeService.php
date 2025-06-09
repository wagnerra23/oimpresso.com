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

class NFeService{

	private $config; 
	private $tools;

	public function __construct($config){
		$business_id = request()->session()->get('user.business_id');
		$certificado = Business::find($business_id);
		$this->config = $config;
		$this->tools = new Tools(json_encode($config), Certificate::readPfx($certificado->certificado, base64_decode($certificado->senha_certificado)));
		$this->tools->model('55');
		
	}

	public function gerarNFe($venda){

		$business_id = request()->session()->get('user.business_id');
		$config = Business::find($business_id);

		$nfe = new Make();
		$stdInNFe = new \stdClass();
		$stdInNFe->versao = '4.00'; 
		$stdInNFe->Id = null; 
		$stdInNFe->pk_nItem = ''; 

		$infNFe = $nfe->taginfNFe($stdInNFe);

		$vendaLast = $venda->lastNFe();
		$lastNumero = $vendaLast;
		
		$stdIde = new \stdClass();
		$stdIde->cUF = $config->getcUF($config->cidade->uf);
		$stdIde->cNF = rand(11111,99999);
		// $stdIde->natOp = $venda->natureza->natureza;
		$stdIde->natOp = $venda->natureza->natureza;

		// $stdIde->indPag = 1; //NÃO EXISTE MAIS NA VERSÃO 4.00 // forma de pagamento

		$stdIde->mod = 55;
		$stdIde->serie = $config->numero_serie_nfe;
		$stdIde->nNF = (int)$lastNumero+1;
		$stdIde->dhEmi = date("Y-m-d\TH:i:sP");
		$stdIde->dhSaiEnt = date("Y-m-d\TH:i:sP");
		$stdIde->tpNF = 1;
		$stdIde->idDest = $config->cidade->uf != $venda->contact->cidade->uf ? 2 : 1;
		$stdIde->cMunFG = $config->cidade->officeimpresso_codigo;

		$stdIde->tpImp = 1;
		$stdIde->tpEmis = 1;
		$stdIde->cDV = 0;
		$stdIde->tpAmb = $config->ambiente;
		$stdIde->finNFe = 1;
		$stdIde->indFinal = $venda->contact->consumidor_final;
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
		// $stdEmit->IM = $ie;

		$emit = $nfe->tagemit($stdEmit);

		// ENDERECO EMITENTE
		$stdEnderEmit = new \stdClass();
		$stdEnderEmit->xLgr = $config->rua;
		$stdEnderEmit->nro = $config->numero;
		$stdEnderEmit->xCpl = "";
		
		$stdEnderEmit->xBairro = $config->bairro;
		$stdEnderEmit->cMun = $config->cidade->officeimpresso_codigo;
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
		$stdDest->xNome = $venda->contact->name;

		if($venda->contact->contribuinte){
			if($venda->contact->ie_rg == 'ISENTO' || $venda->contact->ie_rg == NULL){
				$stdDest->indIEDest = "2";
			}else{
				$stdDest->indIEDest = "1";
			}
			
		}else{
			$stdDest->indIEDest = "9";
		}



		$cnpj_cpf = str_replace(".", "", $venda->contact->cpf_cnpj);
		$cnpj_cpf = str_replace("/", "", $cnpj_cpf);
		$cnpj_cpf = str_replace("-", "", $cnpj_cpf);

		if(strlen($cnpj_cpf) == 14){
			$stdDest->CNPJ = $cnpj_cpf;
			$ie = str_replace(".", "", $venda->contact->ie_rg);
			$ie = str_replace("/", "", $ie);
			$ie = str_replace("-", "", $ie);
			$stdDest->IE = $ie;
		}
		else{
			$stdDest->CPF = $cnpj_cpf;
		} 

		$dest = $nfe->tagdest($stdDest);
		
		$stdEnderDest = new \stdClass();
		$stdEnderDest->xLgr = $venda->contact->rua;
		$stdEnderDest->nro = $venda->contact->numero;
		$stdEnderDest->xCpl = "";
		$stdEnderDest->xBairro = $venda->contact->bairro;
		$stdEnderDest->cMun = $venda->contact->cidade->officeimpresso_codigo;
		$stdEnderDest->xMun = strtoupper($venda->contact->cidade->nome);
		$stdEnderDest->UF = $venda->contact->cidade->uf;

		$cep = str_replace("-", "", $venda->contact->cep);
		$cep = str_replace(".", "", $cep);
		$stdEnderDest->CEP = $cep;
		$stdEnderDest->cPais = "1058";
		$stdEnderDest->xPais = "BRASIL";

		$enderDest = $nfe->tagenderDest($stdEnderDest);


		$somaProdutos = 0;
		$somaICMS = 0;
		//PRODUTOS
		$itemCont = 0;

		$totalItens = count($venda->sell_lines);
		$somaFrete = 0;
		$somaDesconto = 0;
		$somaISS = 0;
		$somaServico = 0;
		$totalDesconto = $venda->total_before_tax -  $venda->final_total + $venda->valor_frete;
		foreach($venda->sell_lines as $i){
			$itemCont++;

			$stdProd = new \stdClass();
			$stdProd->item = $itemCont;
			$stdProd->cEAN = !$this->validaEan13($i->product->sku) ? $i->product->sku : 'SEM GTIN';
			$stdProd->cEANTrib = !$this->validaEan13($i->product->sku) ? $i->product->sku : 'SEM GTIN';
			$stdProd->cProd = $i->product->id;
			$stdProd->xProd = $i->product->name;
			$ncm = $i->product->ncm;
			$ncm = str_replace(".", "", $ncm);
			if($i->product->cst_csosn == '500' || $i->product->cst_csosn == '60'){
				$stdProd->cBenef = 'SEM CBENEF';
			}
			if($i->product->perc_iss > 0){
				$stdProd->NCM = '00';
			}else{
				$stdProd->NCM = $ncm;
			}
			
			$stdProd->CFOP = $config->cidade->uf != $venda->contact->cidade->uf ?
			$i->product->cfop_externo : $i->product->cfop_interno;


			$stdProd->uCom = $i->product->unit->short_name;
			$stdProd->qCom = $i->quantity;
			$stdProd->vUnCom = $this->format($i->unit_price);
			$stdProd->vProd = $this->format(($i->quantity * $i->unit_price));
			$stdProd->uTrib = $i->product->unit->short_name;
			$stdProd->qTrib = $i->quantity;
			$stdProd->vUnTrib = $this->format($i->unit_price);
			$stdProd->indTot = 1;
			$somaProdutos += ($i->quantity * $i->unit_price);

			$vDesc = 0;
			// if($totalDesconto > 0){
			// 	if($itemCont < sizeof($venda->sell_lines)){
			// 		$stdProd->vDesc = $this->format($totalDesconto/$totalItens);
			// 		$somaDesconto += $vDesc = $totalDesconto/$totalItens;
			// 	}else{
			// 		$stdProd->vDesc = $somaDesconto = $vDesc = $totalDesconto - $somaDesconto;
			// 	}
			// }

			if($totalDesconto >= 0.1){
				if($itemCont < sizeof($venda->sell_lines)){
					$totalVenda = $venda->final_total;

					$media = (((($stdProd->vProd - $totalVenda)/$totalVenda))*100);
					$media = 100 - ($media * -1);

					$tempDesc = ($totalDesconto*$media)/100;
					$somaDesconto += $tempDesc;

					$stdProd->vDesc = $this->format($tempDesc);
				}else{
					$stdProd->vDesc = $this->format($totalDesconto - $somaDesconto);
				}
			}


			if($venda->valor_frete > 0){
				$somaFrete += $vFt = $venda->valor_frete/$totalItens;
				$stdProd->vFrete = $this->format($vFt);
				$somaProdutos += $vFt;
			}
			// return $stdProd;
			$prod = $nfe->tagprod($stdProd);
			


		//TAG IMPOSTO

			$stdImposto = new \stdClass();
			$stdImposto->item = $itemCont;
			if($i->product->perc_iss > 0){
				$stdImposto->vTotTrib = 0.00;
			}

			$imposto = $nfe->tagimposto($stdImposto);

			// ICMS
			if($i->product->perc_iss == 0){
				// regime normal
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
			} 

			else
			{
				$valorIss = ($i->unit_price * $i->quantidade) - $vDesc;
				$somaServico += $valorIss;
				$valorIss = $valorIss * ($i->product->perc_iss/100);
				$somaISS += $valorIss;


				$std = new \stdClass();
				$std->item = $itemCont; 
				$std->vBC = $stdProd->vProd;
				$std->vAliq = $i->product->perc_iss;
				$std->vISSQN = $this->format($valorIss);
				$std->cMunFG = $config->codMun;
				$std->cListServ = $i->product->cListServ;
				$std->indISS = 1;
				$std->indIncentivo = 1;

				$nfe->tagISSQN($std);
			}


				//PIS
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


				//IPI

			$stdIPI = new \stdClass();
			$stdIPI->item = $itemCont; 
				//999 – para tributação normal IPI
			$stdIPI->cEnq = '999'; 
			$stdIPI->CST = $i->product->cst_ipi;
			$stdIPI->vBC = $this->format($i->product->perc_ipi) > 0 ? $stdProd->vProd : 0.00;
			$stdIPI->pIPI = $this->format($i->product->perc_ipi);
			$stdIPI->vIPI = $stdProd->vProd * $this->format(($i->product->perc_ipi/100));


			$nfe->tagIPI($stdIPI);


			//TAG ANP

			// if(strlen($i->product->descricao_anp) > 5){
			// 	$stdComb = new \stdClass();
			// 	$stdComb->item = 1; 
			// 	$stdComb->cProdANP = $i->product->codigo_anp;
			// 	$stdComb->descANP = $i->product->descricao_anp; 
			// 	$stdComb->UFCons = $venda->cliente->cidade->uf;

			// 	$nfe->tagcomb($stdComb);
			// }


			$cest = $i->product->cest;
			$cest = str_replace(".", "", $cest);
			$stdProd->CEST = $cest;
			if(strlen($cest) > 2){
				$std = new \stdClass();
				$std->item = $itemCont; 
				$std->CEST = $cest;
				$nfe->tagCEST($std);
			}	
		}

		$stdICMSTot = new \stdClass();
		$stdICMSTot->vProd = 0;
		$stdICMSTot->vBC = $config->regime == 3 ? $this->format($somaProdutos) : 0.00;
		$stdICMSTot->vICMS = $this->format($somaICMS);
		$stdICMSTot->vICMSDeson = 0.00;
		$stdICMSTot->vBCST = 0.00;
		$stdICMSTot->vST = 0.00;

		$stdICMSTot->vFrete = $this->format($venda->valor_frete);

		$stdICMSTot->vSeg = 0.00;
		$stdICMSTot->vDesc = $this->format($totalDesconto);
		$stdICMSTot->vII = 0.00;
		$stdICMSTot->vIPI = 0.00;
		$stdICMSTot->vPIS = 0.00;
		$stdICMSTot->vCOFINS = 0.00;
		$stdICMSTot->vOutro = 0.00;
		
		$stdICMSTot->vNF = $this->format($somaProdutos - $totalDesconto);


		$stdICMSTot->vNF = 
		$this->format(($somaProdutos)-$totalDesconto);

		$stdICMSTot->vTotTrib = 0.00;
		$ICMSTot = $nfe->tagICMSTot($stdICMSTot);

		//inicio totalizao issqn

		if($somaISS > 0){
			$std = new \stdClass();
			$std->vServ = $this->format($somaServico + $venda->desconto);
			$std->vBC = $this->format($somaServico);
			$std->vISS = $this->format($somaISS);
			$std->dCompet = date('Y-m-d');

			$std->cRegTrib = 6;

			$nfe->tagISSQNTot($std);
		}

		//fim totalizao issqn

		$stdTransp = new \stdClass();
		$stdTransp->modFrete = '9';

		$transp = $nfe->tagtransp($stdTransp);

		if($venda->transportadora != null){
			$std = new \stdClass();
			$std->xNome = $venda->transportadora->razao_social;

			$std->xEnder = $venda->transportadora->logradouro;
			$std->xMun = strtoupper($venda->transportadora->cidade->nome);
			$std->UF = $venda->transportadora->cidade->uf;


			$cnpj_cpf = $venda->transportadora->cnpj_cpf;
			$cnpj_cpf = str_replace(".", "", $venda->transportadora->cnpj_cpf);
			$cnpj_cpf = str_replace("/", "", $cnpj_cpf);
			$cnpj_cpf = str_replace("-", "", $cnpj_cpf);

			if(strlen($cnpj_cpf) == 14) $std->CNPJ = $cnpj_cpf;
			else $std->CPF = $cnpj_cpf;

			$nfe->tagtransporta($std);
		}

		if($venda->placa != '' && $venda->uf != ''){
			$std = new \stdClass();
			$placa = str_replace("-", "", $venda->placa);
			$std->placa = strtoupper($placa);
			$std->UF = $venda->uf;

			if($config->cidade->uf == $venda->contact->cidade->uf){
				$nfe->tagveicTransp($std);
			}
		}

		if($venda->qtd_volumes > 0 && $venda->peso_liquido > 0
			&& $venda->peso_bruto > 0){
			$stdVol = new \stdClass();
			$stdVol->item = 1;
			$stdVol->qVol = $venda->qtd_volumes;
			$stdVol->esp = $venda->especie;

			$stdVol->nVol = $venda->numeracao_volumes ?? 0;
			$stdVol->pesoL = $venda->peso_liquido;
			$stdVol->pesoB = $venda->peso_bruto;
			$vol = $nfe->tagvol($stdVol);
		}


		$stdFat = new \stdClass();
		$stdFat->nFat = (int)$lastNumero+1;
		$stdFat->vOrig = $this->format($somaProdutos);
		$stdFat->vDesc = $this->format($totalDesconto);
		$stdFat->vLiq = $this->format($somaProdutos - $totalDesconto);

		$fatura = $nfe->tagfat($stdFat);
		

		if(count($venda->payment_lines) > 1){
			$contFatura = 1;
			foreach($venda->payment_lines as $ft){
				$stdDup = new \stdClass();
				$stdDup->nDup = "00".$contFatura;
				$stdDup->dVenc = substr($ft->paid_on, 0, 10);
				$stdDup->vDup = $this->format($ft->amount);

				$nfe->tagdup($stdDup);
				$contFatura++;
			}
		}else{
			$pay = $venda->payment_lines[0];
			$stdDup = new \stdClass();
			$stdDup->nDup = '001';
			if($pay->paid_on) $stdDup->dVenc = substr($pay->paid_on, 0, 10);
			else $stdDup->dVenc = date('Y-m-d');
			$stdDup->vDup =  $this->format($somaProdutos-$totalDesconto);

			$nfe->tagdup($stdDup);
		}




		$stdPag = new \stdClass();
		$pag = $nfe->tagpag($stdPag);

		$stdDetPag = new \stdClass();


		$stdDetPag->tPag = '01';
		// $stdDetPag->vPag = $venda->tipo_pagamento != '90' ? $this->format($stdProd->vProd - $venda->desconto) : 0.00; 
		$stdDetPag->vPag = $this->format($somaProdutos - $totalDesconto); 

		if($venda->tipo_pagamento == '03' || $venda->tipo_pagamento == '04'){
			$stdDetPag->CNPJ = '12345678901234';
			$stdDetPag->tBand = '01';
			$stdDetPag->cAut = '3333333';
			$stdDetPag->tpIntegra = 1;
		}
		$stdDetPag->indPag = $venda->forma_pagamento == 'a_vista' ?  0 : 1; 

		$detPag = $nfe->tagdetPag($stdDetPag);



		$stdInfoAdic = new \stdClass();
		$stdInfoAdic->infCpl = $venda->additional_notes;

		$infoAdic = $nfe->taginfAdic($stdInfoAdic);



		$std = new \stdClass();
		$std->CNPJ = getenv('RESP_CNPJ'); //CNPJ da pessoa jurídica responsável pelo sistema utilizado na emissão do documento fiscal eletrônico
		$std->xContato= getenv('RESP_NOME'); //Nome da pessoa a ser contatada
		$std->email = getenv('RESP_EMAIL'); //E-mail da pessoa jurídica a ser contatada
		$std->fone = getenv('RESP_FONE'); //Telefone da pessoa jurídica/física a ser contatada
		$nfe->taginfRespTec($std);
		
		if(getenv("AUTXML")){
			$std = new \stdClass();
			$std->CNPJ = getenv("AUTXML"); 
			$std->CPF = null;
			$nfe->tagautXML($std);
		}

		try{
			$nfe->montaNFe();
			$arr = [
				'chave' => $nfe->getChave(),
				'xml' => $nfe->getXML(),
				'nNf' => $stdIde->nNF
			];
			return $arr;

		}catch(\Exception $e){
			return [
				'xml_erros' => $nfe->getErrors()
			];
		}
	}

	public function format($number, $dec = 2){
		return number_format((float) $number, $dec, ".", "");
	}


	public function consultaCadastro($cnpj, $uf){
		try {

			$iest = '';
			$cpf = '';
			$response = $this->tools->sefazCadastro($uf, $cnpj, $iest, $cpf);

			$stdCl = new Standardize($response);

			$std = $stdCl->toStd();

			$arr = $stdCl->toArray();

			$json = $stdCl->toJson();

			echo $json;

		} catch (\Exception $e) {
			echo $e->getMessage();
		}
	}

	public function consultaChave($chave){
		$response = $this->tools->sefazConsultaChave($chave);

		$stdCl = new Standardize($response);
		$arr = $stdCl->toArray();
		return $arr;
	}

	public function consultar($venda){
		try {
			$chave = $venda->chave;
			$this->tools->model('55');

			$chave = $venda->chave;
			$response = $this->tools->sefazConsultaChave($chave);

			$stdCl = new Standardize($response);
			$arr = $stdCl->toArray();

			// $arr = json_decode($json);
			return $arr;

		} catch (\Exception $e) {
			echo $e->getMessage();
		}
	}

	public function inutilizar($nInicio, $nFinal, $justificativa){
		try{

			$nSerie = $config->numero_serie_nfe;
			$nIni = $nInicio;
			$nFin = $nFinal;
			$xJust = $justificativa;
			$response = $this->tools->sefazInutiliza($nSerie, $nIni, $nFin, $xJust);

			$stdCl = new Standardize($response);
			$std = $stdCl->toStd();
			$arr = $stdCl->toArray();
			$json = $stdCl->toJson();

			return $arr;

		} catch (\Exception $e) {
			echo $e->getMessage();
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
        //TRATAR
			} else {
				$cStat = $std->retEvento->infEvento->cStat;
				$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
				if ($cStat == '101' || $cStat == '135' || $cStat == '155' ) {
            //SUCESSO PROTOCOLAR A SOLICITAÇÂO ANTES DE GUARDAR
					$xml = Complements::toAuthorize($this->tools->lastRequest, $response);

					if(!is_dir(public_path('xml_nfe_cancelada/'.$cnpj))){
						mkdir(public_path('xml_nfe_cancelada/'.$cnpj), 0777, true);
					}
					file_put_contents(public_path('xml_nfe_cancelada/'.$cnpj.'/'.$chave.'.xml'), $xml);

					return $arr;
				} else {
            //houve alguma falha no evento 
            //TRATAR
					return ['erro' => true, 'data' => $arr, 'status' => 402];	
				}
			}    
		} catch (\Exception $e) {
			echo $e->getMessage();
    //TRATAR
		}
	}

	public function cartaCorrecao($venda, $correcao, $cnpj){
		try {

			$chave = $venda->chave;
			$xCorrecao = $correcao;
			$nSeqEvento = $venda->sequencia_cce+1;
			$response = $this->tools->sefazCCe($chave, $xCorrecao, $nSeqEvento);
			sleep(2);

			$stdCl = new Standardize($response);

			$std = $stdCl->toStd();

			$arr = $stdCl->toArray();

			$json = $stdCl->toJson();

			if ($std->cStat != 128) {
        //TRATAR
			} else {
				$cStat = $std->retEvento->infEvento->cStat;
				if ($cStat == '135' || $cStat == '136') {
					$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
            //SUCESSO PROTOCOLAR A SOLICITAÇÂO ANTES DE GUARDAR
					$xml = Complements::toAuthorize($this->tools->lastRequest, $response);

					if(!is_dir(public_path('xml_nfe_correcao/'.$cnpj))){
						mkdir(public_path('xml_nfe_correcao/'.$cnpj), 0777, true);
					}
					file_put_contents(public_path('xml_nfe_correcao/'.$cnpj.'/'.$chave.'.xml'), $xml);

					$venda->sequencia_cce = $venda->sequencia_cce + 1;
					$venda->save();
					return $arr;

				} else {
					return ['erro' => true, 'data' => $arr, 'status' => 402];	

				}
			}    
		} catch (\Exception $e) {
			return $e->getMessage();
		}
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

			$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
			try {
				$xml = Complements::toAuthorize($signXml, $protocolo);
				header('Content-type: text/xml; charset=UTF-8');

				if(!is_dir(public_path('xml_nfe/'.$cnpj))){
					mkdir(public_path('xml_nfe/'.$cnpj), 0777, true);
				}
				file_put_contents(public_path('xml_nfe/'.$cnpj.'/'.$chave.'.xml'), $xml);
				// return $recibo;
				return ['successo' => true, 'recibo' => $recibo];

				// $this->printDanfe($xml);
			} catch (\Exception $e) {
				return ['erro' => true, 'protocolo' => $st->toJson($protocolo), 'status' => 401];
			}

		} catch(\Exception $e){
			return "erro: ".$e->getMessage() ;
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
