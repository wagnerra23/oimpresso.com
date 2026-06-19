<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Util\OtelHelper;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeEvento;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Common\Standardize;
use NFePHP\NFe\Tools;
use RuntimeException;

/**
 * US-FISCAL-013 — Carta de Correção Eletrônica (CCe — tpEvento 110110).
 *
 * Espelha NfeInutilizacaoService — Service separado pra não inflar NfeService
 * (já com 900+ linhas). Pattern: validação local → SEFAZ → persist NfeEvento.
 *
 * Regras CONFAZ Ajuste SINIEF 07/2005 Art. 14:
 *   - Texto correção: 15-1000 caracteres
 *   - Janela: 720h (30 dias) após autorização da NFe original
 *   - Sequência (nSeqEvento): 1-20 por NFe (cada CCe é nova sequência)
 *   - Status NFe origem: deve ser 'autorizada' (cancelada/rejeitada não corrige)
 *   - Não corrige: valores fiscais, base cálculo, alíquota, dados emit/dest,
 *     data emissão, número/série — apenas info complementar/textual
 *
 * Persistência:
 *   - NfeEvento(tipo='110110', justificativa=textoCorrecao, status,
 *     payload_json→n_seq_evento + xml_ret + x_motivo)
 *   - Idempotência: (emissao_id, n_seq_evento) — re-chamar com mesma sequência
 *     retorna evento existente.
 *
 * Multi-tenant Tier 0 (ADR 0093): cross-tenant guard explícito + global scope.
 */
class NfeCartaCorrecaoService
{
    public function __construct(
        private readonly CertificadoService $certificadoService,
        /** @var Closure|null fn(string $configJson, array $certData): Tools — override pra testes */
        private readonly ?Closure $toolsFactory = null,
    ) {}

    public function aplicar(
        int $businessId,
        int $nfeEmissaoId,
        string $textoCorrecao,
        int $nSeqEvento,
    ): NfeEvento {
        return OtelHelper::spanBiz('nfe.cce', function () use ($businessId, $nfeEmissaoId, $textoCorrecao, $nSeqEvento): NfeEvento {
            return $this->aplicarInterno($businessId, $nfeEmissaoId, $textoCorrecao, $nSeqEvento);
        }, [
            'module'         => 'NfeBrasil',
            'nfe_emissao_id' => $nfeEmissaoId,
            'n_seq_evento'   => $nSeqEvento,
        ]);
    }

    private function aplicarInterno(
        int $businessId,
        int $nfeEmissaoId,
        string $textoCorrecao,
        int $nSeqEvento,
    ): NfeEvento {
        $this->validarEntrada($businessId, $textoCorrecao, $nSeqEvento);

        // SUPERADMIN: $businessId vem por parâmetro (não session); cross-tenant guard explícito abaixo (linha ~78).
        $emissao = NfeEmissao::withoutGlobalScopes()->find($nfeEmissaoId);
        if (! $emissao) {
            throw new RuntimeException("NfeEmissao {$nfeEmissaoId} não encontrada.");
        }

        if ((int) $emissao->business_id !== $businessId) {
            throw new UnauthorizedActionException(
                "Cross-tenant attempt: business {$businessId} tentou CCe NfeEmissao {$nfeEmissaoId} de business {$emissao->business_id}."
            );
        }

        if ($emissao->status !== 'autorizada') {
            throw new RuntimeException(
                "CCe só aplica em NFe autorizada. Status atual: {$emissao->status}."
            );
        }

        // Janela 30d (720h) — CONFAZ Art. 14
        $autorizadaEm = $emissao->emitido_em ?? $emissao->updated_at ?? $emissao->created_at;
        if ($autorizadaEm && $autorizadaEm->diffInHours(now()) > 720) {
            throw new RuntimeException(
                'CCe fora da janela legal (>720h da autorização). CONFAZ SINIEF 07/2005 Art. 14.'
            );
        }

        // Idempotência: mesma (emissao_id, n_seq_evento) → retorna evento existente autorizado
        // SUPERADMIN: filtra por $businessId explícito (param, não session) — defesa em profundidade ADR 0093.
        $eventoExistente = NfeEvento::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('emissao_id', $emissao->id)
            ->where('tipo', '110110')
            ->where('status', 'autorizado')
            ->get()
            ->first(function (NfeEvento $e) use ($nSeqEvento): bool {
                return (int) (($e->payload_json['n_seq_evento'] ?? 0)) === $nSeqEvento;
            });
        if ($eventoExistente) {
            Log::info('NfeCartaCorrecaoService.aplicar: idempotência — CCe sequência já autorizada', [
                'business_id'    => $businessId,
                'nfe_emissao_id' => $emissao->id,
                'n_seq_evento'   => $nSeqEvento,
                'evento_id'      => $eventoExistente->id,
            ]);
            return $eventoExistente;
        }

        $chave = (string) ($emissao->chave_44 ?? '');
        if (strlen($chave) !== 44) {
            throw new RuntimeException("NfeEmissao {$emissao->id} sem chave_44 válida.");
        }

        $business = DB::table('business')->where('id', $businessId)->first();
        if (! $business) {
            throw new RuntimeException("Business {$businessId} não encontrado.");
        }

        $certData = $this->certificadoService->carregarParaSefaz($businessId);
        $xmlResp = null;
        try {
            $tools = $this->buildTools($business, $certData, (string) $emissao->modelo);
            $xmlResp = $tools->sefazCCe($chave, $textoCorrecao, $nSeqEvento);
        } catch (\Throwable $e) {
            Log::error('NfeCartaCorrecaoService.aplicar: falha SEFAZ', [
                'business_id'    => $businessId,
                'nfe_emissao_id' => $emissao->id,
                'n_seq_evento'   => $nSeqEvento,
                'error'          => $e->getMessage(),
            ]);
            $this->persistirEvento($emissao, $textoCorrecao, $nSeqEvento, 'rejeitado', null, ['erro' => $e->getMessage()]);
            throw new RuntimeException(
                "Falha SEFAZ ao aplicar CCe chave={$chave}: {$e->getMessage()}",
                previous: $e,
            );
        }

        $std = (new Standardize((string) $xmlResp))->toStd();
        $retEvento = $std->retEvento ?? null;
        if (is_array($retEvento)) {
            $retEvento = $retEvento[0] ?? null;
        }
        $infEvento = $retEvento->infEvento ?? null;
        $cstat   = (string) ($infEvento->cStat ?? $std->cStat ?? '999');
        $xMotivo = (string) ($infEvento->xMotivo ?? $std->xMotivo ?? '');

        // cstat 135 ou 136 → CCe aceita
        $aceito = in_array($cstat, ['135', '136'], true);

        $evento = $this->persistirEvento(
            $emissao,
            $textoCorrecao,
            $nSeqEvento,
            $aceito ? 'autorizado' : 'rejeitado',
            $cstat,
            ['xml_ret' => $xmlResp, 'x_motivo' => $xMotivo, 'n_seq_evento' => $nSeqEvento],
        );

        if (! $aceito) {
            throw new RuntimeException(
                "SEFAZ rejeitou CCe chave={$chave} seq={$nSeqEvento}: cstat={$cstat} {$xMotivo}"
            );
        }

        Log::info('NfeCartaCorrecaoService.aplicar: CCe autorizada via SEFAZ', [
            'business_id'    => $businessId,
            'nfe_emissao_id' => $emissao->id,
            'n_seq_evento'   => $nSeqEvento,
            'cstat'          => $cstat,
            'evento_id'      => $evento->id,
        ]);

        return $evento;
    }

    private function validarEntrada(int $businessId, string $textoCorrecao, int $nSeqEvento): void
    {
        $len = mb_strlen($textoCorrecao);
        if ($len < 15 || $len > 1000) {
            throw new InvalidArgumentException(
                "Texto correção CCe deve ter 15-1000 caracteres (regra SEFAZ). Recebido: {$len} chars."
            );
        }

        if ($nSeqEvento < 1 || $nSeqEvento > 20) {
            throw new InvalidArgumentException(
                "n_seq_evento CCe deve ser 1-20 (regra SEFAZ Art. 14). Recebido: {$nSeqEvento}."
            );
        }

        $sessionBiz = session('user.business_id');
        if ($sessionBiz !== null && (int) $sessionBiz !== $businessId) {
            throw new UnauthorizedActionException(
                "Cross-tenant attempt: session biz={$sessionBiz} tentou CCe em biz={$businessId}"
            );
        }
    }

    private function persistirEvento(
        NfeEmissao $emissao,
        string $textoCorrecao,
        int $nSeqEvento,
        string $status,
        ?string $cstat,
        array $payload,
    ): NfeEvento {
        return NfeEvento::create([
            'business_id'   => $emissao->business_id,
            'emissao_id'    => $emissao->id,
            'tipo'          => '110110',
            'justificativa' => $textoCorrecao,
            'status'        => $status,
            'cstat_evento'  => $cstat,
            'payload_json'  => array_merge(['n_seq_evento' => $nSeqEvento], $payload),
        ]);
    }

    private function buildTools(object $business, array $certData, string $modelo): Tools
    {
        $configJson = $this->buildConfig($business);

        if ($this->toolsFactory !== null) {
            return ($this->toolsFactory)($configJson, $certData);
        }

        $cert  = Certificate::readPfx($certData['pfx_binary'], $certData['senha']);
        $tools = new Tools($configJson, $cert);
        $tools->model((int) $modelo);
        return $tools;
    }

    private function buildConfig(object $business): string
    {
        $cnpj  = preg_replace('/\D/', '', (string) ($business->cnpj ?? $business->tax_number ?? ''));
        $uf    = strtoupper((string) ($business->state ?? $business->uf ?? 'SP'));
        $razao = $business->razao_social ?? $business->name ?? '';
        $tpAmb = (int) ($business->ambiente ?? env('NFE_AMBIENTE', 2));

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
}
