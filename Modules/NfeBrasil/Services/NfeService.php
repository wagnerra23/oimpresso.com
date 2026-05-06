<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Models\NfeEmissao;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Common\Standardize;
use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;
use RuntimeException;

/**
 * US-NFE-042 · NfeService — emissão NF-e modelo 55 via sped-nfe.
 *
 * Responsabilidades:
 *   - Idempotência por (business_id, transaction_id)
 *   - Próximo número fiscal via nfe_emissoes.max(numero) + lock
 *   - Assinatura + transmissão SEFAZ (mockável via $toolsFactory)
 *   - Persiste XML em storage/app/nfe-brasil/{biz}/notas/{serie}-{numero}.xml
 *   - Grava NfeEmissao com status/cstat/chave_44/motivo
 *   - Atualiza business.ultimo_numero_nfe na autorização
 *
 * Entrada ($dadosNfe):
 *   transaction_id?: int|null   — idempotência
 *   modelo?:         '55'|'65'  — default '55'
 *   serie?:          string     — default business.numero_serie_nfe ?? '1'
 *   numero?:         int|null   — auto se null
 *   nat_op:          string     — natureza da operação (required)
 *   emit?:           array      — sobreposição de dados do emitente (business como default)
 *   dest:            array      — dados do destinatário (required)
 *   dets:            array[]    — itens da nota (required)
 *   total:           array      — totais ICMSTot pré-calculados (required)
 *   pag:             array[]    — pagamentos (required)
 *   valor_total:     float      — total líquido (required)
 *   inf_cpl?:        string     — informações complementares
 *
 * Multi-tenant: business_id sempre escopa. Ver skill multi-tenant-patterns.
 * ADR 0090: CertificadoService já aplica fallback legado — NfeService não duplica.
 */
class NfeService
{
    /**
     * @param CertificadoService $certificadoService
     * @param Closure|null $toolsFactory Override para testes. Assinatura:
     *   fn(string $configJson, array $certData): Tools
     *   $certData = {pfx_binary, senha, ...} — igual ao retorno de carregarParaSefaz()
     */
    public function __construct(
        private readonly CertificadoService $certificadoService,
        private readonly ?Closure $toolsFactory = null,
    ) {}

    /**
     * Emite NF-e via SEFAZ e persiste resultado em nfe_emissoes.
     *
     * @throws RuntimeException Se cert ausente, business não encontrado, ou falha de infra
     */
    public function emitir(int $businessId, array $dadosNfe): NfeEmissao
    {
        $modelo        = $dadosNfe['modelo'] ?? '55';
        $transactionId = isset($dadosNfe['transaction_id']) ? (int) $dadosNfe['transaction_id'] : null;

        // ── 1. Idempotência ─────────────────────────────────────────────────
        if ($transactionId !== null) {
            $existente = NfeEmissao::where('business_id', $businessId)
                ->where('transaction_id', $transactionId)
                ->first();

            if ($existente) {
                Log::info('NfeService: idempotência — emissão existente', [
                    'business_id'    => $businessId,
                    'transaction_id' => $transactionId,
                    'emissao_id'     => $existente->id,
                    'status'         => $existente->status,
                ]);
                return $existente;
            }
        }

        // ── 2. Cert + business ──────────────────────────────────────────────
        $certData = $this->certificadoService->carregarParaSefaz($businessId);

        $business = DB::table('business')->where('id', $businessId)->first();
        if (! $business) {
            throw new RuntimeException("Business {$businessId} não encontrado.");
        }

        $emitOverride = $dadosNfe['emit'] ?? [];
        $serie  = $dadosNfe['serie'] ?? ((string) ($business->numero_serie_nfe ?? '1'));
        $numero = isset($dadosNfe['numero']) ? (int) $dadosNfe['numero'] : null;

        // ── 3. Transação atômica ────────────────────────────────────────────
        return DB::transaction(function () use (
            $businessId, $transactionId, $modelo, $serie, $numero,
            $dadosNfe, $certData, $business, $emitOverride
        ) {
            // Próximo número dentro da transaction com lock
            if ($numero === null) {
                $numero = $this->proximoNumeroLocked($businessId, $modelo, $serie);
            }

            $emissao = NfeEmissao::create([
                'business_id'    => $businessId,
                'transaction_id' => $transactionId,
                'modelo'         => $modelo,
                'serie'          => $serie,
                'numero'         => $numero,
                'status'         => 'pendente',
                'valor_total'    => (float) ($dadosNfe['valor_total'] ?? 0),
            ]);

            try {
                $xml       = $this->buildXml($business, $emissao, $dadosNfe, $emitOverride);
                $tools     = $this->criarTools($business, $certData, $emitOverride);
                $xmlSigned = $tools->signNFe($xml);

                $idLote  = str_pad((string) $emissao->id, 15, '0', STR_PAD_LEFT);
                $response = $tools->sefazEnviaLote([$xmlSigned], $idLote, 1);

                $this->processarRetorno($emissao, $response, $xmlSigned, $businessId, $serie, $numero);

            } catch (\Throwable $e) {
                $emissao->update([
                    'status' => 'rejeitada',
                    'motivo' => 'Erro de transmissão: ' . $e->getMessage(),
                ]);
                Log::error('NfeService: falha na emissão', [
                    'business_id' => $businessId,
                    'emissao_id'  => $emissao->id,
                    'error'       => $e->getMessage(),
                ]);
                throw $e;
            }

            return $emissao->refresh();
        });
    }

    /**
     * Próximo número com SELECT FOR UPDATE no registro da série.
     * Garante unicidade em ambiente concorrente.
     */
    public function proximoNumeroLocked(int $businessId, string $modelo, string $serie): int
    {
        // Lock na row de business pra serializar emissões concorrentes
        DB::table('business')->where('id', $businessId)->lockForUpdate()->value('id');

        $ultimo = NfeEmissao::withTrashed()
            ->where('business_id', $businessId)
            ->where('modelo', $modelo)
            ->where('serie', $serie)
            ->max('numero') ?? 0;

        try {
            $legado = (int) (DB::table('business')
                ->where('id', $businessId)
                ->value('ultimo_numero_nfe') ?? 0);
        } catch (\Throwable) {
            $legado = 0; // coluna ausente no ambiente sem UltimatePOS 3.7
        }

        return max((int) $ultimo, $legado) + 1;
    }

    // ────────────────────────────────────────────────────────────────────────
    // Privados
    // ────────────────────────────────────────────────────────────────────────

    private function criarTools(object $business, array $certData, array $emitOverride): Tools
    {
        $configJson = $this->montarConfigSefaz($business, $emitOverride);

        if ($this->toolsFactory !== null) {
            // Testes passam certData bruto — factory decide se cria Certificate ou não
            return ($this->toolsFactory)($configJson, $certData);
        }

        $cert  = Certificate::readPfx($certData['pfx_binary'], $certData['senha']);
        $tools = new Tools($configJson, $cert);
        $tools->model('55');
        return $tools;
    }

    private function montarConfigSefaz(object $business, array $emitOverride): string
    {
        $cnpj       = preg_replace('/\D/', '', (string) ($emitOverride['cnpj'] ?? $business->cnpj ?? ''));
        $razao      = $emitOverride['razao_social'] ?? $business->razao_social ?? $business->name ?? '';
        $uf         = $emitOverride['uf'] ?? $this->resolverUF($business);
        $tpAmb      = (int) ($emitOverride['ambiente'] ?? $business->ambiente ?? 2);

        return (string) json_encode([
            'atualizacao' => now()->format('Y-m-d\TH:i:s'),
            'tpAmb'       => $tpAmb,
            'razaosocial' => $razao,
            'cnpj'        => $cnpj,
            'siglaUF'     => $uf,
            'schemes'     => 'PL_009_V4',
            'versao'      => '4.00',
            'tokenCSC'    => '',
            'idCSC'       => '',
        ]);
    }

    private function resolverUF(object $business): string
    {
        $loc = DB::table('business_locations')
            ->where('business_id', $business->id)
            ->orderBy('id')
            ->first();

        $state = $loc?->state ?? '';
        // UF brasileira: 2 letras maiúsculas
        if (preg_match('/^[A-Z]{2}$/', $state)) {
            return $state;
        }
        return 'SP';
    }

    /**
     * Monta o XML da NF-e via NFePHP\NFe\Make.
     */
    private function buildXml(object $business, NfeEmissao $emissao, array $dadosNfe, array $emitOverride): string
    {
        $nfe = new Make();

        $std         = new \stdClass();
        $std->versao = '4.00';
        $std->Id     = null;
        $nfe->taginfNFe($std);

        // ── ide ─────────────────────────────────────────────────────────────
        $ufCode = \NFePHP\Common\UFList::getCodeByUF(
            $emitOverride['uf'] ?? $this->resolverUF($business)
        );

        $stdIde            = new \stdClass();
        $stdIde->cUF       = $ufCode;
        $stdIde->cNF       = rand(10000000, 99999999);
        $stdIde->natOp     = $dadosNfe['nat_op'];
        $stdIde->mod       = (int) $emissao->modelo;
        $stdIde->serie     = $emissao->serie;
        $stdIde->nNF       = $emissao->numero;
        $stdIde->dhEmi     = now()->format('Y-m-d\TH:i:sP');
        $stdIde->dhSaiEnt  = now()->format('Y-m-d\TH:i:sP');
        $stdIde->tpNF      = 1;
        $stdIde->idDest    = $this->resolverIdDest($emitOverride, $dadosNfe['dest'] ?? [], $business);
        $stdIde->cMunFG    = (string) ($emitOverride['cod_municipio'] ?? $business->cod_municipio ?? '9999999');
        $stdIde->tpImp     = 1;
        $stdIde->tpEmis    = 1;
        $stdIde->cDV       = 0;
        $stdIde->tpAmb     = (int) ($emitOverride['ambiente'] ?? $business->ambiente ?? 2);
        $stdIde->finNFe    = 1;
        $stdIde->indFinal  = isset($dadosNfe['dest']['cpf']) ? 1 : 0;
        $stdIde->indPres   = 1;
        $stdIde->procEmi   = '0';
        $stdIde->verProc   = '1.0';
        $nfe->tagide($stdIde);

        // ── emit ─────────────────────────────────────────────────────────────
        $crt        = (int) ($emitOverride['crt'] ?? $business->regime ?? 1);
        $stdEmit    = new \stdClass();
        $stdEmit->CNPJ  = preg_replace('/\D/', '', (string) ($emitOverride['cnpj'] ?? $business->cnpj ?? ''));
        $stdEmit->xNome = $emitOverride['razao_social'] ?? $business->razao_social ?? $business->name ?? '';
        $stdEmit->xFant = $emitOverride['nome_fantasia'] ?? $business->name ?? '';
        $stdEmit->IE    = preg_replace('/\D/', '', (string) ($emitOverride['ie'] ?? $business->ie ?? ''));
        $stdEmit->CRT   = $crt;
        $nfe->tagemit($stdEmit);

        $stdEnderEmit          = new \stdClass();
        $stdEnderEmit->xLgr   = $emitOverride['logradouro'] ?? $business->rua ?? '';
        $stdEnderEmit->nro    = $emitOverride['numero_end'] ?? $business->numero ?? 'SN';
        $stdEnderEmit->xBairro = $emitOverride['bairro'] ?? $business->bairro ?? '';
        $stdEnderEmit->cMun   = (string) ($emitOverride['cod_municipio'] ?? $business->cod_municipio ?? '9999999');
        $stdEnderEmit->xMun   = $emitOverride['municipio'] ?? $business->municipio ?? '';
        $stdEnderEmit->UF     = $emitOverride['uf'] ?? $this->resolverUF($business);
        $stdEnderEmit->CEP    = preg_replace('/\D/', '', (string) ($emitOverride['cep'] ?? $business->cep ?? ''));
        $stdEnderEmit->cPais  = '1058';
        $stdEnderEmit->xPais  = 'BRASIL';
        $nfe->tagenderEmit($stdEnderEmit);

        // ── dest ─────────────────────────────────────────────────────────────
        $dest    = $dadosNfe['dest'];
        $stdDest = new \stdClass();
        $stdDest->xNome = $dest['nome'];
        $doc = preg_replace('/\D/', '', (string) ($dest['cnpj'] ?? $dest['cpf'] ?? ''));
        if (strlen($doc) === 14) {
            $stdDest->CNPJ = $doc;
        } else {
            $stdDest->CPF = $doc;
        }
        $stdDest->indIEDest = $dest['ind_ie_dest'] ?? '9';
        if (! empty($dest['ie'])) {
            $stdDest->IE = preg_replace('/\D/', '', (string) $dest['ie']);
        }
        if (! empty($dest['email'])) {
            $stdDest->email = $dest['email'];
        }
        $nfe->tagdest($stdDest);

        $stdEnderDest          = new \stdClass();
        $stdEnderDest->xLgr   = $dest['logradouro'] ?? '';
        $stdEnderDest->nro    = $dest['numero'] ?? 'SN';
        $stdEnderDest->xBairro = $dest['bairro'] ?? '';
        $stdEnderDest->cMun   = (string) ($dest['cod_municipio'] ?? '9999999');
        $stdEnderDest->xMun   = strtoupper((string) ($dest['municipio'] ?? ''));
        $stdEnderDest->UF     = $dest['uf'] ?? 'SP';
        $stdEnderDest->CEP    = preg_replace('/\D/', '', (string) ($dest['cep'] ?? ''));
        $stdEnderDest->cPais  = '1058';
        $stdEnderDest->xPais  = 'BRASIL';
        $nfe->tagenderDest($stdEnderDest);

        // ── dets (itens) ─────────────────────────────────────────────────────
        foreach ($dadosNfe['dets'] as $idx => $det) {
            $item = $idx + 1;
            $this->adicionarItem($nfe, $item, $det, $crt);
        }

        // ── total ────────────────────────────────────────────────────────────
        $total = $dadosNfe['total'];

        $stdICMSTot              = new \stdClass();
        $stdICMSTot->vBC         = $this->fmt($total['v_bc_icms'] ?? 0);
        $stdICMSTot->vICMS       = $this->fmt($total['v_icms'] ?? 0);
        $stdICMSTot->vICMSDeson  = 0.00;
        $stdICMSTot->vFCPUFDest  = 0.00;
        $stdICMSTot->vICMSUFDest = 0.00;
        $stdICMSTot->vICMSUFRemet = 0.00;
        $stdICMSTot->vFCP        = 0.00;
        $stdICMSTot->vBCST       = 0.00;
        $stdICMSTot->vST         = 0.00;
        $stdICMSTot->vFCPST      = 0.00;
        $stdICMSTot->vFCPSTRet   = 0.00;
        $stdICMSTot->vProd       = $this->fmt($total['v_prod'] ?? 0);
        $stdICMSTot->vFrete      = $this->fmt($total['v_frete'] ?? 0);
        $stdICMSTot->vSeg        = 0.00;
        $stdICMSTot->vDesc       = $this->fmt($total['v_desc'] ?? 0);
        $stdICMSTot->vII         = 0.00;
        $stdICMSTot->vIPI        = 0.00;
        $stdICMSTot->vIPIDevol   = 0.00;
        $stdICMSTot->vPIS        = $this->fmt($total['v_pis'] ?? 0);
        $stdICMSTot->vCOFINS     = $this->fmt($total['v_cofins'] ?? 0);
        $stdICMSTot->vOutro      = 0.00;
        $stdICMSTot->vNF         = $this->fmt($total['v_nf'] ?? 0);
        $nfe->tagICMSTot($stdICMSTot);

        // ── transp ───────────────────────────────────────────────────────────
        $stdTransp       = new \stdClass();
        $stdTransp->modFrete = 9; // 9 = sem frete
        $nfe->tagtransp($stdTransp);

        // ── pag ──────────────────────────────────────────────────────────────
        $stdPag = new \stdClass();
        $nfe->tagpag($stdPag);

        foreach ($dadosNfe['pag'] as $pagamento) {
            $stdDetPag        = new \stdClass();
            $stdDetPag->tPag  = (string) ($pagamento['tpag'] ?? '01');
            $stdDetPag->vPag  = $this->fmt($pagamento['vpag'] ?? 0);
            $nfe->tagdetPag($stdDetPag);
        }

        // ── infAdic ──────────────────────────────────────────────────────────
        if (! empty($dadosNfe['inf_cpl'])) {
            $stdInfo         = new \stdClass();
            $stdInfo->infCpl = (string) $dadosNfe['inf_cpl'];
            $nfe->taginfAdic($stdInfo);
        }

        $nfe->montaNFe();
        $errors = $nfe->getErrors();
        if (! empty($errors)) {
            throw new RuntimeException(
                'Erro ao montar NF-e: ' . implode('; ', array_column($errors, 'msg'))
            );
        }

        return $nfe->getXML();
    }

    /**
     * Adiciona item (det + imposto + ICMS + PIS + COFINS) ao Make.
     */
    private function adicionarItem(Make $nfe, int $item, array $det, int $crt): void
    {
        $stdProd         = new \stdClass();
        $stdProd->item   = $item;
        $stdProd->cProd  = (string) ($det['cprod'] ?? $item);
        $stdProd->cEAN   = 'SEM GTIN';
        $stdProd->xProd  = (string) ($det['xprod'] ?? '');
        $stdProd->NCM    = preg_replace('/\D/', '', (string) ($det['ncm'] ?? '00000000'));
        $stdProd->CFOP   = (string) ($det['cfop'] ?? '5102');
        $stdProd->uCom   = (string) ($det['ucm'] ?? 'UN');
        $stdProd->qCom   = $this->fmt((float) ($det['qcom'] ?? 1), 4);
        $stdProd->vUnCom = $this->fmt((float) ($det['vuncom'] ?? 0));
        $stdProd->vProd  = $this->fmt((float) ($det['vprod'] ?? 0));
        $stdProd->cEANTrib = 'SEM GTIN';
        $stdProd->uTrib  = (string) ($det['utrib'] ?? 'UN');
        $stdProd->qTrib  = $this->fmt((float) ($det['qtrib'] ?? 1), 4);
        $stdProd->vUnTrib = $this->fmt((float) ($det['vuntrib'] ?? 0));
        $stdProd->indTot = (int) ($det['ind_tot'] ?? 1);
        if (($det['vdesc'] ?? 0) > 0) {
            $stdProd->vDesc = $this->fmt((float) $det['vdesc']);
        }
        if (($det['vfrete'] ?? 0) > 0) {
            $stdProd->vFrete = $this->fmt((float) $det['vfrete']);
        }
        $nfe->tagprod($stdProd);

        // imposto container
        $stdImp        = new \stdClass();
        $stdImp->item  = $item;
        $nfe->tagimposto($stdImp);

        // ICMS
        $icms = $det['icms'] ?? [];
        $cstCsosn = (string) ($icms['cst_csosn'] ?? '102');
        $orig     = (int) ($icms['orig'] ?? 0);

        if ($crt === 3) {
            // Regime Normal — CST
            $stdICMS         = new \stdClass();
            $stdICMS->item   = $item;
            $stdICMS->orig   = $orig;
            $stdICMS->CST    = $cstCsosn;
            $stdICMS->modBC  = (int) ($icms['modbc'] ?? 3);
            $stdICMS->vBC    = $this->fmt((float) ($icms['vbc'] ?? 0));
            $stdICMS->pICMS  = $this->fmt((float) ($icms['picms'] ?? 0));
            $stdICMS->vICMS  = $this->fmt((float) ($icms['vicms'] ?? 0));
            $nfe->tagICMS($stdICMS);
        } else {
            // Simples Nacional — CSOSN
            $stdICMSSN        = new \stdClass();
            $stdICMSSN->item  = $item;
            $stdICMSSN->orig  = $orig;
            $stdICMSSN->CSOSN = $cstCsosn;
            if (in_array($cstCsosn, ['500', '400', '900'], true)) {
                $stdICMSSN->modBC  = (int) ($icms['modbc'] ?? 3);
                $stdICMSSN->vBC    = $this->fmt((float) ($icms['vbc'] ?? 0));
                $stdICMSSN->pICMS  = $this->fmt((float) ($icms['picms'] ?? 0));
                $stdICMSSN->vICMS  = $this->fmt((float) ($icms['vicms'] ?? 0));
            }
            $nfe->tagICMSSN($stdICMSSN);
        }

        // PIS
        $pis         = $det['pis'] ?? [];
        $stdPIS      = new \stdClass();
        $stdPIS->item = $item;
        $stdPIS->CST  = (string) ($pis['cst'] ?? '07');
        $stdPIS->vBC  = $this->fmt((float) ($pis['vbc'] ?? 0));
        $stdPIS->pPIS = $this->fmt((float) ($pis['ppis'] ?? 0));
        $stdPIS->vPIS = $this->fmt((float) ($pis['vpis'] ?? 0));
        $nfe->tagPIS($stdPIS);

        // COFINS
        $cofins         = $det['cofins'] ?? [];
        $stdCOFINS      = new \stdClass();
        $stdCOFINS->item = $item;
        $stdCOFINS->CST  = (string) ($cofins['cst'] ?? '07');
        $stdCOFINS->vBC  = $this->fmt((float) ($cofins['vbc'] ?? 0));
        $stdCOFINS->pCOFINS = $this->fmt((float) ($cofins['pcofins'] ?? 0));
        $stdCOFINS->vCOFINS = $this->fmt((float) ($cofins['vcofins'] ?? 0));
        $nfe->tagCOFINS($stdCOFINS);
    }

    /**
     * Processa retorno SEFAZ: atualiza NfeEmissao + armazena XML autorizado.
     */
    private function processarRetorno(
        NfeEmissao $emissao,
        string $responseXml,
        string $xmlSigned,
        int $businessId,
        string $serie,
        int $numero
    ): void {
        $std = (new Standardize($responseXml))->toStd();

        // cStat nível lote
        $loteStatus = (string) ($std->cStat ?? '999');

        // Para indSinc=1, o status individual fica em protNFe.infProt
        $infProt  = $std->protNFe->infProt ?? null;
        $cstat    = (string) ($infProt?->cStat ?? $loteStatus);
        $xMotivo  = (string) ($infProt?->xMotivo ?? $std->xMotivo ?? '');
        $chNFe    = (string) ($infProt?->chNFe ?? '');
        $nProt    = (string) ($infProt?->nProt ?? '');
        $dhRecbto = (string) ($infProt?->dhRecbto ?? '');

        // cStat 100 = Autorizado NF-e; 150 = Autorizado fora do prazo
        if (in_array($cstat, ['100', '150'], true)) {
            $xmlPath = sprintf('nfe-brasil/%d/notas/%s-%s.xml', $businessId, $serie, $numero);
            Storage::put($xmlPath, $xmlSigned);

            $emissao->update([
                'status'     => 'autorizada',
                'cstat'      => $cstat,
                'motivo'     => $xMotivo,
                'chave_44'   => $chNFe,
                'xml_path'   => $xmlPath,
                'emitido_em' => $dhRecbto ? now()->parse($dhRecbto) : now(),
                'metadata'   => ['nProt' => $nProt, 'cstat_lote' => $loteStatus],
            ]);

            // Atualiza contador fiscal no business (defensivo — coluna pode não existir em dev)
            try {
                DB::table('business')
                    ->where('id', $businessId)
                    ->update(['ultimo_numero_nfe' => $numero]);
            } catch (\Throwable $e) {
                Log::warning('NfeService: não foi possível atualizar ultimo_numero_nfe', [
                    'business_id' => $businessId,
                    'error'       => $e->getMessage(),
                ]);
            }

            Log::info('NfeService: NF-e autorizada', [
                'business_id' => $businessId,
                'emissao_id'  => $emissao->id,
                'chave_44'    => $chNFe,
                'nProt'       => $nProt,
            ]);
            return;
        }

        // cStat 301, 302 = Denegado (emitente irregular)
        if (in_array($cstat, ['301', '302', '110', '205'], true)) {
            $emissao->update([
                'status' => 'denegada',
                'cstat'  => $cstat,
                'motivo' => $xMotivo,
            ]);
            Log::warning('NfeService: NF-e denegada', [
                'business_id' => $businessId,
                'cstat'       => $cstat,
                'motivo'      => $xMotivo,
            ]);
            return;
        }

        // Demais = rejeitada
        $emissao->update([
            'status' => 'rejeitada',
            'cstat'  => $cstat,
            'motivo' => $xMotivo,
        ]);
        Log::warning('NfeService: NF-e rejeitada', [
            'business_id' => $businessId,
            'cstat'       => $cstat,
            'motivo'      => $xMotivo,
        ]);
    }

    private function resolverIdDest(array $emitOverride, array $dest, object $business): int
    {
        $ufEmit = $emitOverride['uf'] ?? $this->resolverUF($business);
        $ufDest = $dest['uf'] ?? $ufEmit;
        return $ufEmit !== $ufDest ? 2 : 1;
    }

    private function fmt(float $value, int $dec = 2): string
    {
        return number_format($value, $dec, '.', '');
    }
}
