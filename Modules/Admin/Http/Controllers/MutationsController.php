<?php

namespace Modules\Admin\Http\Controllers;

use App\Util\OtelHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Admin\Services\AdminAuditLogger;

/**
 * MutationsController — 3 ações mutacionais Admin Center (Sprint 2).
 *
 * Padrão double-confirmation pra todas:
 *   1. Cliente envia POST com `reason` (string >= 5 chars) + `confirm` (bool true)
 *   2. Server valida + grava audit log ANTES da execução
 *   3. Executa ação + grava resultado em audit
 *
 * 3 actions:
 *   - applyCurador:   move/copia arquivos do batch aprovado (não mexe filesystem;
 *                     marca rows arquivos como `applied` em homolog stub Sprint 2)
 *   - regenerateToken: rotaciona token MCP server (mcp_tokens.token_hash)
 *   - runHealthCheck: dispara `php artisan jana:health-check --json` async, salva
 *                     snapshot em storage/app/jana-health-snapshot.json
 *
 * Auth gate via middleware stack: tailscale-only -> auth -> is-wagner.
 *
 * @see memory/decisions/0122-admin-center-ct100.md §3 ações mutacionais
 */
class MutationsController extends Controller
{
    public function __construct(
        protected AdminAuditLogger $audit,
    ) {}

    /**
     * POST /admin/mutations/curador/apply
     * Body: { batch_id: string, reason: string, confirm: true }
     */
    public function applyCurador(Request $request): JsonResponse
    {
        // Wave 27 D9: span hot-path mutation Admin (apply curador).
        // Attributes: NO PII — apenas batch_id + result count. Zero-cost com otel.enabled=false.
        return OtelHelper::spanBiz('admin.mutations.curador_apply', function () use ($request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'batch_id' => 'required|string|max:64',
            'reason'   => 'required|string|min:5|max:500',
            'confirm'  => 'required|boolean|in:1,true',
        ]);

        if ($validator->fails()) {
            return $this->fail('curador_apply.validation', $validator->errors()->all(), $request);
        }

        $batchId = $request->string('batch_id')->toString();
        $reason  = $request->string('reason')->toString();

        $this->audit->log('curador.apply.requested', [
            'batch_id' => $batchId,
            'reason'   => $reason,
        ], $request);

        // Sprint 2 stub: implementação real requer integração com scripts/curador/apply.mjs
        // (ou port pra Modules/Arquivos backbone US-ARQ-008..014).
        // Por ora marca audit + retorna 202 Accepted.

        $appliedCount = 0;
        if (Schema::hasTable('arquivos')) {
            try {
                $appliedCount = DB::table('arquivos')
                    ->where('classified_by', 'like', "%batch:{$batchId}%")
                    ->update([
                        'classified_at' => now(),
                        'updated_at'    => now(),
                    ]);
            } catch (\Throwable $e) {
                Log::warning('admin.mutations.curador_apply.error', ['error' => $e->getMessage()]);
            }
        }

        $this->audit->log('curador.apply.completed', [
            'batch_id'      => $batchId,
            'applied_count' => $appliedCount,
        ], $request);

        return response()->json([
            'ok'            => true,
            'batch_id'      => $batchId,
            'applied_count' => $appliedCount,
            'note'          => $appliedCount === 0
                ? 'Nenhum arquivo encontrado com classified_by batch:'.$batchId.' (stub Sprint 2 — espera Modules/Arquivos pipeline US-ARQ-008..014)'
                : 'Aplicado.',
        ], 202);
        }, ['action' => 'curador_apply', 'component' => 'admin.mutations']);
    }

    /**
     * POST /admin/mutations/mcp-token/regenerate
     * Body: { token_id?: int, reason: string, confirm: true }
     */
    public function regenerateMcpToken(Request $request): JsonResponse
    {
        // Wave 27 D9: span regen MCP token (security-sensitive — sem PII em attributes).
        return OtelHelper::spanBiz('admin.mutations.mcp_token_regenerate', function () use ($request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'token_id' => 'nullable|integer',
            'reason'   => 'required|string|min:5|max:500',
            'confirm'  => 'required|boolean|in:1,true',
        ]);

        if ($validator->fails()) {
            return $this->fail('regen_token.validation', $validator->errors()->all(), $request);
        }

        $tokenId = $request->integer('token_id');
        $reason  = $request->string('reason')->toString();

        if (! Schema::hasTable('mcp_tokens')) {
            $this->audit->log('mcp_token.regenerate.skipped', [
                'reason' => 'mcp_tokens table missing',
            ], $request);
            return response()->json([
                'ok'      => false,
                'reason'  => 'mcp_tokens_table_missing',
                'message' => 'Tabela mcp_tokens não existe. Verifique Modules/Copiloto migrations.',
            ], 404);
        }

        $this->audit->log('mcp_token.regenerate.requested', [
            'token_id' => $tokenId,
            'reason'   => $reason,
        ], $request);

        $newPlain = Str::random(48);
        $newHash  = hash('sha256', $newPlain);

        try {
            $query = DB::table('mcp_tokens');
            if ($tokenId !== null && $tokenId > 0) {
                $query->where('id', $tokenId);
            } else {
                // Default: rotaciona token mais recente do usuário Wagner
                $query->where('user_id', config('admin.wagner_user_id', 1))
                    ->orderByDesc('id')
                    ->limit(1);
            }
            $updated = $query->update([
                'token_hash'        => $newHash,
                'rotated_at'        => now(),
                'rotated_by_reason' => substr($reason, 0, 250),
                'updated_at'        => now(),
            ]);

            $this->audit->log('mcp_token.regenerate.completed', [
                'token_id' => $tokenId,
                'updated'  => $updated,
            ], $request);

            return response()->json([
                'ok'              => true,
                'updated'         => $updated,
                'token_plaintext' => $newPlain, // exibido 1 vez na UI; depois só hash
                'note'            => 'Salve token_plaintext AGORA. Depois desta chamada só hash fica em DB.',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('admin.mutations.regen_token.error', [
                'error'    => $e->getMessage(),
                'token_id' => $tokenId,
            ]);
            $this->audit->log('mcp_token.regenerate.failed', [
                'token_id' => $tokenId,
                'error'    => substr($e->getMessage(), 0, 200),
            ], $request);
            return response()->json([
                'ok'      => false,
                'message' => 'Falha ao rotacionar token. Verifique schema mcp_tokens (precisa colunas rotated_at, rotated_by_reason).',
            ], 500);
        }
        }, ['action' => 'mcp_token_regenerate', 'component' => 'admin.mutations']);
    }

    /**
     * POST /admin/mutations/health-check/run-now
     * Body: { reason: string, confirm: true }
     *
     * Roda `jana:health-check --json` síncrono (cap 30s) e salva snapshot
     * em storage/app/jana-health-snapshot.json. Invalida cache W2/W4/W10.
     */
    public function runHealthCheckNow(Request $request): JsonResponse
    {
        // Wave 27 D9: span health-check sync (operação pesada, cap 30s).
        return OtelHelper::spanBiz('admin.mutations.health_check_run', function () use ($request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'reason'  => 'required|string|min:5|max:500',
            'confirm' => 'required|boolean|in:1,true',
        ]);

        if ($validator->fails()) {
            return $this->fail('health_check.validation', $validator->errors()->all(), $request);
        }

        $reason = $request->string('reason')->toString();

        $this->audit->log('health_check.run_now.requested', [
            'reason' => $reason,
        ], $request);

        try {
            // Cap 30s — health check pesado pode demorar. Se passar, abort + log.
            ini_set('max_execution_time', '30');

            $exitCode = Artisan::call('jana:health-check', ['--json' => true]);
            $output   = Artisan::output();

            // Tenta extrair JSON do output
            $jsonStart = strpos($output, '{');
            $snapshot = null;
            if ($jsonStart !== false) {
                $snapshot = json_decode(substr($output, $jsonStart), true);
            }

            if (is_array($snapshot)) {
                Storage::disk('local')->put(
                    'jana-health-snapshot.json',
                    json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                );
            }

            // Invalida cache widgets dependentes
            Cache::forget('admin.widget.brief'); // brief não depende mas invalida pra rotina
            // HealthSnapshotReader, AdrAlertReader, BrainBCostReader leem direto do file → sem cache.

            $this->audit->log('health_check.run_now.completed', [
                'exit_code'      => $exitCode,
                'snapshot_saved' => is_array($snapshot),
                'output_chars'   => strlen($output),
            ], $request);

            return response()->json([
                'ok'             => true,
                'exit_code'      => $exitCode,
                'snapshot_saved' => is_array($snapshot),
                'overall_status' => $snapshot['overall_status'] ?? 'unknown',
                'check_count'    => is_array($snapshot['checks'] ?? null) ? count($snapshot['checks']) : 0,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('admin.mutations.health_check.error', ['error' => $e->getMessage()]);
            $this->audit->log('health_check.run_now.failed', [
                'error' => substr($e->getMessage(), 0, 200),
            ], $request);
            return response()->json([
                'ok'      => false,
                'message' => 'Falha ao rodar health-check: ' . substr($e->getMessage(), 0, 200),
            ], 500);
        }
        }, ['action' => 'health_check_run_now', 'component' => 'admin.mutations']);
    }

    private function fail(string $audit, array $errors, Request $request): JsonResponse
    {
        $this->audit->log($audit, ['errors' => $errors], $request);
        return response()->json([
            'ok'     => false,
            'errors' => $errors,
        ], 422);
    }
}
