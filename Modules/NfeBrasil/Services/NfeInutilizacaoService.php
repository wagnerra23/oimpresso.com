<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeInutilizacao;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Tools;
use RuntimeException;

/**
 * US-SELL-030 — Service de inutilização SEFAZ de faixa de números NFe.
 *
 * Quando emissão rejeitada/denegada/erro_envio gera "buraco" no sequencial
 * fiscal (número foi pego no banco mas não autorizado pela SEFAZ), o caminho
 * fiscal correto é INUTILIZAR a faixa via processo SEFAZ próprio (cstat=102).
 *
 * Sem isso, fechamento anual fiscal acusa gaps e gera multa.
 *
 * Regras SEFAZ:
 *   - Justificativa: 15-255 caracteres obrigatórios
 *   - Faixa: numero_de ≤ numero_ate, mesmo ano/série/modelo
 *   - Cross-tenant guard: businessId do parâmetro DEVE bater com
 *     session('user.business_id') (proteção contra spoofing por API)
 *
 * Side-effects:
 *   - Persiste em nfe_inutilizacoes (status=autorizado se cstat=102)
 *   - Atualiza nfe_emissoes da faixa com status='inutilizada' (preserva
 *     registro pra rastreabilidade fiscal)
 *
 * Refs:
 *   - CASOS-USO-PIPELINE-VENDAS.md §CU-01 G2
 *   - SPEC.md US-SELL-030
 *   - CONFAZ Ajuste SINIEF 07/2005 Art. 14
 */
class NfeInutilizacaoService
{
    public function __construct(
        private readonly CertificadoService $certificadoService,
        /**
         * @var Closure|null Override pra testes — fn(array $configJson, array $certData): Tools
         */
        private readonly ?Closure $toolsFactory = null,
    ) {}

    public function inutilizar(
        int $businessId,
        string $modelo,
        string $serie,
        int $numeroDe,
        int $numeroAte,
        string $justificativa,
    ): NfeInutilizacao {
        // ── Validações ──────────────────────────────────────────────────────
        $this->validarEntrada($businessId, $modelo, $serie, $numeroDe, $numeroAte, $justificativa);

        // ── SEFAZ inutilização ──────────────────────────────────────────────
        $cstat = null;
        $xMotivo = null;
        $xmlRet = null;

        try {
            $certData = $this->certificadoService->carregarParaSefaz($businessId);
            $tools = $this->buildTools($businessId, $certData);

            $business = DB::table('business')->where('id', $businessId)->first();
            if (! $business) {
                throw new RuntimeException("Business {$businessId} não encontrado.");
            }

            $ano = (int) date('y'); // 2 dígitos
            $cnpj = preg_replace('/\D/', '', (string) ($business->tax_number ?? '00000000000000'));

            // Tools::sefazInutiliza(nSerie, nIni, nFin, xJust, tpAmb=null, modelo)
            // NFePHP\NFe\Tools::model exige ?int — cast obrigatório (modelo vem
            // string da fachada pra preservar zero-padding "55"/"65")
            $tools->model((int) $modelo);
            $xmlRet = $tools->sefazInutiliza(
                (int) $serie,
                $numeroDe,
                $numeroAte,
                $justificativa,
            );

            // Parse simples — cstat e xMotivo do retorno SEFAZ
            if ($xmlRet && preg_match('/<cStat>(\d+)<\/cStat>/', $xmlRet, $m)) {
                $cstat = $m[1];
            }
            if ($xmlRet && preg_match('/<xMotivo>([^<]+)<\/xMotivo>/', $xmlRet, $m)) {
                $xMotivo = $m[1];
            }
        } catch (\Throwable $e) {
            Log::error('NfeInutilizacaoService: falha SEFAZ', [
                'business_id' => $businessId,
                'modelo' => $modelo,
                'serie' => $serie,
                'numero_de' => $numeroDe,
                'numero_ate' => $numeroAte,
                'error' => $e->getMessage(),
            ]);
            // Persiste tentativa com status=rejeitado e re-lança
            $this->persistir($businessId, $modelo, $serie, $numeroDe, $numeroAte, $justificativa, 'rejeitado', null, ['erro' => $e->getMessage()]);
            throw new RuntimeException(
                "Falha SEFAZ ao inutilizar faixa [{$numeroDe}..{$numeroAte}]: {$e->getMessage()}",
                previous: $e,
            );
        }

        // ── Persistência ────────────────────────────────────────────────────
        $status = $cstat === '102' ? 'autorizado' : 'rejeitado';
        $inut = $this->persistir(
            $businessId, $modelo, $serie, $numeroDe, $numeroAte, $justificativa,
            $status, $cstat, ['xml_ret' => $xmlRet, 'x_motivo' => $xMotivo],
        );

        // ── Marca emissões da faixa como inutilizada ────────────────────────
        if ($status === 'autorizado') {
            NfeEmissao::withTrashed()
                ->where('business_id', $businessId)
                ->where('modelo', $modelo)
                ->where('serie', $serie)
                ->whereBetween('numero', [$numeroDe, $numeroAte])
                ->update(['status' => 'inutilizada']);

            Log::info('NfeInutilizacaoService: faixa autorizada', [
                'business_id' => $businessId,
                'faixa' => "[{$numeroDe}..{$numeroAte}]",
                'inutilizacao_id' => $inut->id,
            ]);
        }

        return $inut;
    }

    private function validarEntrada(
        int $businessId,
        string $modelo,
        string $serie,
        int $numeroDe,
        int $numeroAte,
        string $justificativa,
    ): void {
        if (! in_array($modelo, ['55', '65'], true)) {
            throw new InvalidArgumentException("Modelo inválido: {$modelo}. Aceito: 55 ou 65.");
        }

        if ($numeroDe < 1 || $numeroAte < $numeroDe) {
            throw new InvalidArgumentException(
                "Faixa inválida: numero_de={$numeroDe}, numero_ate={$numeroAte}. " .
                'numero_de deve ser ≥ 1 e numero_ate ≥ numero_de.'
            );
        }

        $len = mb_strlen($justificativa);
        if ($len < 15 || $len > 255) {
            throw new InvalidArgumentException(
                "Justificativa deve ter 15-255 caracteres (regra SEFAZ). " .
                "Recebido: {$len} chars."
            );
        }

        // Cross-tenant guard — só permite inutilizar do próprio business
        $sessionBiz = session('user.business_id');
        if ($sessionBiz !== null && (int) $sessionBiz !== $businessId) {
            throw new UnauthorizedActionException(
                "Cross-tenant attempt: session biz={$sessionBiz} tentou inutilizar biz={$businessId}"
            );
        }
    }

    private function persistir(
        int $businessId,
        string $modelo,
        string $serie,
        int $numeroDe,
        int $numeroAte,
        string $justificativa,
        string $status,
        ?string $cstat,
        array $payload,
    ): NfeInutilizacao {
        return NfeInutilizacao::create([
            'business_id' => $businessId,
            'modelo' => $modelo,
            'serie' => $serie,
            'numero_de' => $numeroDe,
            'numero_ate' => $numeroAte,
            'justificativa' => $justificativa,
            'status' => $status,
            'cstat' => $cstat,
            'autorizada_em' => $status === 'autorizado' ? now() : null,
            'payload_json' => $payload,
        ]);
    }

    private function buildTools(int $businessId, array $certData): Tools
    {
        if ($this->toolsFactory !== null) {
            return ($this->toolsFactory)($this->buildConfig($businessId), $certData);
        }

        $cert = Certificate::readPfx($certData['pfx_binary'], $certData['senha']);
        return new Tools($this->buildConfig($businessId), $cert);
    }

    private function buildConfig(int $businessId): string
    {
        $business = DB::table('business')->where('id', $businessId)->first();
        $uf = strtoupper((string) ($business->state ?? 'SP'));
        $cnpj = preg_replace('/\D/', '', (string) ($business->tax_number ?? ''));

        return json_encode([
            'atualizacao' => date('Y-m-d H:i:s'),
            'tpAmb' => (int) (env('NFE_AMBIENTE', 2)), // 2=homologação default seguro
            'razaosocial' => $business->name ?? '',
            'siglaUF' => $uf,
            'cnpj' => $cnpj,
            'schemes' => 'PL_009_V4',
            'versao' => '4.00',
            'tokenIBPT' => '',
            'CSC' => '',
            'CSCid' => '',
        ]);
    }
}
