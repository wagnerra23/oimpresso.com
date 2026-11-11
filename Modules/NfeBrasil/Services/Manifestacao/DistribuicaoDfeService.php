<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Manifestacao;

use App\Util\OtelHelper;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Models\NfeDfeItem;
use Modules\NfeBrasil\Models\NfeDfeNsuState;
use Modules\NfeBrasil\Models\NfeDfeRecebido;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\Entrada\LeitorNfeEntrada;
use Modules\NfeBrasil\Services\Concerns\ResolveUfEmitente;
use Modules\NfeBrasil\Support\AmbienteDoEmitente;
use Modules\NfeBrasil\Support\ArquivoFiscal;
use Modules\NfeBrasil\Support\CnpjDoEmitente;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Tools;
use RuntimeException;

/**
 * US-NFE-051 · Distribuição DFe — baixa NF-e emitidas contra meu CNPJ via NSU.
 *
 * Endpoint nacional SEFAZ: NFeDistribuicaoDFe (não UF-specific).
 * Cursor NSU é IRREVERSÍVEL — perda de last_nsu = perde XMLs históricos.
 *
 * Throttle: 5min entre consultas por business; 1 h depois de cStat 137/656 ou de alcançar o
 * maxNSU (NT 2014.002 — lote 1.3, ver `proximaConsulta()`).
 * Loop: máximo 10 iterações por chamada pra evitar excesso de carga.
 */
class DistribuicaoDfeService
{
    use ResolveUfEmitente;

    public const LOOP_LIMIT_DEFAULT     = 10;
    public const COOLDOWN_MINUTES       = 5;
    public const PRAZO_CONFIRMACAO_DIAS = 180; // NT 2014.002

    public function __construct(
        private readonly CertificadoService $certificadoService,
        private readonly ?Closure $toolsFactory = null,
    ) {}

    /**
     * Puxa lote de DFes recebidos pelo business.
     *
     * D9.a OTel wrap — sefazDistDFe é hot-path HTTP nacional SEFAZ (timeout 30s
     * comum). Atributos incluem `last_nsu` inicial pra correlacionar cursor.
     *
     * @return array{processados: int, last_nsu: int, skipped_throttle?: bool}
     */
    public function puxarLote(int $businessId, int $loopLimit = self::LOOP_LIMIT_DEFAULT): array
    {
        return OtelHelper::spanBiz('nfe.distribuicao_dfe', function () use ($businessId, $loopLimit): array {
            return $this->puxarLoteInterno($businessId, $loopLimit);
        }, [
            'module'     => 'NfeBrasil',
            'loop_limit' => $loopLimit,
        ]);
    }

    /**
     * @internal Corpo real de puxarLote() — separado para wrap OTel.
     */
    private function puxarLoteInterno(int $businessId, int $loopLimit): array
    {
        $state = $this->getOrCreateState($businessId);

        if (! $state->podeConsultarAgora(self::COOLDOWN_MINUTES)) {
            Log::info('DistribuicaoDfeService: throttle cooldown ativo — pulando', [
                'business_id'      => $businessId,
                'ultimo_check_em'  => $state->ultimo_check_em?->toIso8601String(),
            ]);
            return ['processados' => 0, 'last_nsu' => $state->last_nsu, 'skipped_throttle' => true];
        }

        $tools = $this->buildTools($businessId);
        $ultNSU = (int) $state->last_nsu;
        $maxNSU = $ultNSU;
        $processados = 0;
        $iter = 0;
        $ultimoCStat = null;

        do {
            $iter++;
            if ($iter > $loopLimit) {
                Log::info('DistribuicaoDfeService: loop limit atingido', [
                    'business_id' => $businessId,
                    'iterations'  => $iter,
                ]);
                break;
            }

            try {
                $responseXml = $tools->sefazDistDFe($ultNSU);
            } catch (\Throwable $e) {
                Log::warning('DistribuicaoDfeService: falha sefazDistDFe', [
                    'business_id' => $businessId,
                    'ult_nsu'     => $ultNSU,
                    'erro'        => $e->getMessage(),
                ]);
                break;
            }

            $parsed = $this->parseLote($responseXml);
            $ultimoCStat = $parsed['cstat'] ?? null;
            if (! $parsed['ok']) {
                break;
            }

            $maxNSU = max($maxNSU, $parsed['max_nsu']);
            $novoUlt = $parsed['ult_nsu'];

            foreach ($parsed['docs'] as $doc) {
                if ($this->persistirDoc($businessId, $doc)) {
                    $processados++;
                }
            }

            if ($novoUlt <= $ultNSU) {
                break; // SEFAZ não avançou cursor
            }
            $ultNSU = $novoUlt;

            // pausa curta entre iterações (boa cidadania SEFAZ)
            usleep(500000); // 0.5s
        } while ($ultNSU < $maxNSU);

        $state->update([
            'last_nsu'                => $ultNSU,
            'ultimo_check_em'         => now(),
            'proxima_consulta_em'     => $this->proximaConsulta($ultimoCStat, $ultNSU, $maxNSU),
            'ultimo_lote_count'       => $processados,
            'total_xmls_processados'  => $state->total_xmls_processados + $processados,
        ]);

        Log::info('DistribuicaoDfeService: lote processado', [
            'business_id' => $businessId,
            'processados' => $processados,
            'ult_nsu'     => $ultNSU,
            'max_nsu'     => $maxNSU,
        ]);

        return ['processados' => $processados, 'last_nsu' => $ultNSU];
    }

    /**
     * N4-05 (lote 1.3): a SEFAZ pede 1 h depois de "nenhum documento" (137) ou de alcançar o
     * maxNSU, e devolve 656 (consumo indevido, CNPJ bloqueado por 1 h) a quem consultar antes.
     * Nos outros casos (erro de rede, cStat inesperado, lote parcial) vale só o intervalo de 5 min.
     */
    private function proximaConsulta(?string $cStat, int $ultNSU, int $maxNSU): ?\Illuminate\Support\Carbon
    {
        $esperaUmaHora = in_array($cStat, ['137', '656'], true)
            || ($cStat === '138' && $maxNSU > 0 && $ultNSU >= $maxNSU);

        return $esperaUmaHora ? now()->addHour() : null;
    }

    private function getOrCreateState(int $businessId): NfeDfeNsuState
    {
        return NfeDfeNsuState::firstOrCreate(
            ['business_id' => $businessId],
            ['last_nsu' => 0],
        );
    }

    /**
     * @return array{ok: bool, cstat?: string, ult_nsu: int, max_nsu: int, docs: array}
     */
    private function parseLote(string $xml): array
    {
        if ($xml === '' || $xml === null) {
            return ['ok' => false, 'ult_nsu' => 0, 'max_nsu' => 0, 'docs' => []];
        }

        try {
            $dom = new \DOMDocument();
            $dom->loadXML($xml);
        } catch (\Throwable $e) {
            return ['ok' => false, 'ult_nsu' => 0, 'max_nsu' => 0, 'docs' => []];
        }

        $node = $dom->getElementsByTagName('retDistDFeInt')->item(0);
        if (! $node) {
            return ['ok' => false, 'ult_nsu' => 0, 'max_nsu' => 0, 'docs' => []];
        }

        $cStat = $this->tagValue($node, 'cStat');
        $ultNSU = (int) ($this->tagValue($node, 'ultNSU') ?: 0);
        $maxNSU = (int) ($this->tagValue($node, 'maxNSU') ?: 0);

        // 137 = nada novo; 138 = lote retornado; 656 = consumo indevido (bloqueio de 1 h); outros = erro
        if ($cStat === '656') {
            Log::warning('DistribuicaoDfeService: SEFAZ recusou por consumo indevido (656) — próxima consulta em 1 h');
            return ['ok' => false, 'cstat' => $cStat, 'ult_nsu' => $ultNSU, 'max_nsu' => $maxNSU, 'docs' => []];
        }
        if (! in_array($cStat, ['137', '138'], true)) {
            Log::warning('DistribuicaoDfeService: cStat inesperado', ['cStat' => $cStat]);
            return ['ok' => false, 'cstat' => $cStat, 'ult_nsu' => $ultNSU, 'max_nsu' => $maxNSU, 'docs' => []];
        }

        $docs = [];
        if ($cStat === '138') {
            $lote = $node->getElementsByTagName('loteDistDFeInt')->item(0);
            if ($lote) {
                $docZips = $lote->getElementsByTagName('docZip');
                foreach ($docZips as $docZip) {
                    $nsu = (int) $docZip->getAttribute('NSU');
                    $schema = (string) $docZip->getAttribute('schema');
                    $content = gzdecode(base64_decode($docZip->nodeValue));
                    if ($content === false) {
                        continue;
                    }
                    $docs[] = ['nsu' => $nsu, 'schema' => $schema, 'xml' => $content];
                }
            }
        }

        return [
            'ok'      => true,
            'cstat'   => $cStat,
            'ult_nsu' => $ultNSU,
            'max_nsu' => $maxNSU,
            'docs'    => $docs,
        ];
    }

    /**
     * Persiste 1 XML como NfeDfeRecebido + items. Retorna true se foi novo.
     */
    private function persistirDoc(int $businessId, array $doc): bool
    {
        // Apenas resNFe (resumo) e procNFe (NF-e completa) são interessantes
        if (! str_starts_with($doc['schema'], 'resNFe') && ! str_starts_with($doc['schema'], 'procNFe')) {
            return false;
        }

        try {
            $xml = simplexml_load_string($doc['xml']);
        } catch (\Throwable $e) {
            // BUG FIX P0 2026-05-10: NSU é IRREVERSÍVEL — descartar XML sem
            // log mata diagnóstico. Mínimo: gravar evidência antes de pular.
            Log::error('DfeService: XML inválido descartado', [
                'business_id' => $businessId,
                'exception'   => $e->getMessage(),
                'classe'      => $e::class,
                'nsu'         => $doc['nsu'] ?? null,
                'schema'      => $doc['schema'] ?? null,
                'xml_preview' => substr((string) ($doc['xml'] ?? ''), 0, 200),
            ]);
            return false;
        }
        if (! $xml) {
            Log::error('DfeService: XML inválido descartado (simplexml retornou false)', [
                'business_id' => $businessId,
                'nsu'         => $doc['nsu'] ?? null,
                'schema'      => $doc['schema'] ?? null,
                'xml_preview' => substr((string) ($doc['xml'] ?? ''), 0, 200),
            ]);
            return false;
        }

        $extracted = $this->extrairDados($xml, $doc['schema']);
        if (! $extracted) {
            return false;
        }

        // Idempotência por chave
        $existente = NfeDfeRecebido::where('business_id', $businessId)
            ->where('chave_44', $extracted['chave_44'])
            ->first();
        if ($existente) {
            return false;
        }

        $xmlPath = $this->salvarXml($businessId, $extracted['chave_44'], $doc['xml']);

        $dataEmissao = $extracted['data_emissao'] ?? now();
        $prazo = $dataEmissao instanceof \DateTimeInterface
            ? \Carbon\Carbon::instance($dataEmissao)->addDays(self::PRAZO_CONFIRMACAO_DIAS)->toDateString()
            : null;

        $recebido = NfeDfeRecebido::create([
            'business_id'         => $businessId,
            'chave_44'            => $extracted['chave_44'],
            'nsu'                 => $doc['nsu'],
            'cnpj_emitente'       => $extracted['cnpj_emitente'],
            'nome_emitente'       => $extracted['nome_emitente'] ?? null,
            'valor_total'         => $extracted['valor_total'] ?? 0,
            'num_protocolo'       => $extracted['num_protocolo'] ?? null,
            'data_emissao'        => $dataEmissao,
            'xml_path'            => $xmlPath,
            'status_manifestacao' => NfeDfeRecebido::STATUS_PENDENTE,
            'prazo_confirmacao_em' => $prazo,
        ]);

        NfeDfeItem::gravarDaNota($businessId, $recebido->id, $extracted['itens'] ?? []);

        return true;
    }

    /**
     * Extrai dados úteis do XML (resNFe = resumo, procNFe = NF-e completa).
     */
    private function extrairDados(\SimpleXMLElement $xml, string $schema): ?array
    {
        if (str_starts_with($schema, 'resNFe')) {
            return [
                'chave_44'      => (string) $xml->chNFe,
                'cnpj_emitente' => (string) $xml->CNPJ,
                'nome_emitente' => (string) $xml->xNome,
                'valor_total'   => (float) ($xml->vNF ?? 0),
                'num_protocolo' => (string) ($xml->nProt ?? ''),
                'data_emissao'  => isset($xml->dhEmi) ? new \DateTimeImmutable((string) $xml->dhEmi) : null,
                'itens'         => [],
            ];
        }

        if (str_starts_with($schema, 'procNFe')) {
            // Mesmo leitor do upload (ADR entrada por XML, fase 2): cProd, cEAN, uCom, CST e ICMS.
            try {
                return LeitorNfeEntrada::dadosDaNota($xml);
            } catch (\InvalidArgumentException) {
                return null;
            }
        }

        return null;
    }

    private function salvarXml(int $businessId, string $chave, string $xml): string
    {
        $disk = Storage::disk(config('nfebrasil.dfes_recebidos_disk', ArquivoFiscal::DISCO));
        $path = sprintf('nfe-brasil/%d/dfes-recebidos/%s.xml', $businessId, $chave);
        $disk->put($path, $xml);
        return $path;
    }

    private function tagValue(\DOMNode $node, string $tag): ?string
    {
        $list = $node instanceof \DOMElement ? $node->getElementsByTagName($tag) : null;
        return $list && $list->item(0) ? $list->item(0)->nodeValue : null;
    }

    private function buildTools(int $businessId): Tools
    {
        $certData = $this->certificadoService->carregarParaSefaz($businessId);
        $configJson = json_encode($this->buildConfig($businessId), JSON_UNESCAPED_UNICODE);

        if ($this->toolsFactory) {
            return ($this->toolsFactory)($configJson, $certData);
        }

        $tools = new Tools(
            $configJson,
            Certificate::readPfx($certData['pfx_binary'], $certData['senha']),
        );
        $tools->model('55');
        $tools->setEnvironment(AmbienteDoEmitente::resolver($businessId));
        return $tools;
    }

    /**
     * A UF vem de `business_locations`, via `resolverUfEmitente()`. Até 2026-09-04 esta query
     * pedia `business.state`, coluna que não existe — ver o docblock do trait.
     */
    private function buildConfig(int $businessId): array
    {
        $row = DB::table('business')->select(['name'])->where('id', $businessId)->first();
        return [
            'atualizacao' => date('Y-m-d H:i:s'),
            'tpAmb'       => AmbienteDoEmitente::resolver($businessId),
            'razaosocial' => (string) ($row->name ?? ''),
            // R-NFE-041: titular do certificado → business.cnpj → tax_number_1; vazio recusa.
            'cnpj'        => CnpjDoEmitente::obrigatorio($businessId),
            'siglaUF'     => $this->resolverUfEmitente($businessId),
            'schemes'     => 'PL_009_V4',
            'versao'      => '4.00',
            'tokenIBPT'   => '',
        ];
    }
}
