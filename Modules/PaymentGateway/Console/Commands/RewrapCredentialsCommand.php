<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * US-PG-001 (Onda Audit Sênior 2026-05-25) — ADR 0170 §G drift fix.
 *
 * Rewrap idempotente da coluna `payment_gateway_credentials.config_json`
 * pós-troca do cast `'array'` → `'encrypted:array'`. Rows existentes
 * antes do PR estão em JSON plain (VULN P0-#1) — este command faz scan
 * + encrypt-in-place via cast automático do Eloquent.
 *
 * Heurística de detecção (plain vs já encrypted):
 *   Lê valor BRUTO via query builder (bypass do cast Eloquent). Tenta
 *   `Crypt::decryptString($raw)` — Laravel 12 valida MAC + cipher AES-256
 *   antes de retornar. Sucesso → linha já cifrada (skip). DecryptException
 *   → linha em plain JSON → re-grava via `$model->config_json = $array` que
 *   dispara o cast `encrypted:array` no save().
 *
 * **DEFAULT É --dry-run** (consistente com migrate-credentials). Use --apply
 * pra persistir. Idempotente — N execuções com --apply NÃO re-cifram rows
 * já cifradas (skip por heurística).
 *
 * Multi-tenant Tier 0 (ADR 0093): query sem global scope (CLI sem session
 * auth) MAS detecta row com business_id NULL como ALERTA — schema canon não
 * permite isso e indica corruption upstream.
 *
 * Audit trail: registra `mcp_audit_log` row por execução (endpoint
 * `paymentgateway:rewrap-credentials`, status `success|error`, payload
 * resumo {wrapped, skipped, errors}). Sem PII (só IDs + gateway_key).
 *
 * Output: tabela `id | business_id | gateway | ambiente | status` com
 * status em `encrypted`, `wrapped`, `would_wrap`, `error` (cor adequada).
 *
 * Uso:
 *   php artisan paymentgateway:rewrap-credentials                 # dry-run
 *   php artisan paymentgateway:rewrap-credentials --apply         # persiste
 *   php artisan paymentgateway:rewrap-credentials --business=1 --apply
 *
 * @see PaymentGatewayCredential — cast `encrypted:array` (US-PG-001)
 * @see ADR 0170 §G — encryption-at-rest era prometida desde Onda 2
 */
class RewrapCredentialsCommand extends Command
{
    protected $signature = 'paymentgateway:rewrap-credentials
                            {--business= : Restringir a 1 business_id (default: todos)}
                            {--apply : Persistir mudanças (default: dry-run)}
                            {--dry-run : Alias explícito do default (no-op se --apply ausente)}';

    protected $description = 'Rewrap config_json plain → encrypted:array (US-PG-001 ADR 0170 §G fix)';

    public function handle(): int
    {
        $businessId = $this->option('business');
        $apply = (bool) $this->option('apply');
        $mode = $apply ? 'APPLY' : 'DRY-RUN';

        $this->info("PaymentGateway rewrap-credentials — modo {$mode}");
        if ($businessId) {
            $this->line("Restrito a business_id = {$businessId}");
        }

        // Query bruta (bypass cast) — precisa ler payload raw pra detectar
        // se é cipher ou plain JSON. Sem global scope (CLI sem auth).
        $query = DB::table('payment_gateway_credentials')->select(['id', 'business_id', 'gateway_key', 'ambiente', 'config_json']);
        if ($businessId) {
            $query->where('business_id', $businessId);
        }

        $rows = $query->orderBy('id')->get();
        $this->info("Encontradas {$rows->count()} credenciais pra avaliar.");

        if ($rows->isEmpty()) {
            $this->warn('Nada a processar.');

            return self::SUCCESS;
        }

        $tableRows = [];
        $stats = ['encrypted' => 0, 'wrapped' => 0, 'would_wrap' => 0, 'error' => 0, 'business_null' => 0];

        foreach ($rows as $row) {
            $bizId = $row->business_id;
            $rawConfig = $row->config_json;

            // ALERTA defesa em profundidade — schema NOT NULL business_id,
            // mas dump corrompido poderia introduzir. Tier 0 ADR 0093.
            if ($bizId === null) {
                $stats['business_null']++;
                $this->error(sprintf('  ALERTA: credential id=%d sem business_id (corruption?)', $row->id));
            }

            $status = $this->detectAndRewrap($row->id, $rawConfig, $apply);
            $stats[$status] = ($stats[$status] ?? 0) + 1;

            $tableRows[] = [
                'id'          => $row->id,
                'business_id' => $bizId ?? '(NULL!)',
                'gateway'     => $row->gateway_key,
                'ambiente'    => $row->ambiente,
                'status'      => $this->paintStatus($status),
            ];
        }

        $this->newLine();
        $this->table(['ID', 'Business', 'Gateway', 'Ambiente', 'Status'], $tableRows);

        $this->newLine();
        $this->info(sprintf(
            'Resumo %s: %d encrypted (skip) · %d %s · %d errors · %d business_id NULL',
            $mode,
            $stats['encrypted'],
            $apply ? $stats['wrapped'] : $stats['would_wrap'],
            $apply ? 'wrapped' : 'would_wrap',
            $stats['error'],
            $stats['business_null'],
        ));

        if (! $apply && ($stats['would_wrap'] > 0)) {
            $this->newLine();
            $this->warn('Modo DRY-RUN — nada persistido. Rerun com --apply pra cifrar.');
        }

        // Audit log (mcp_audit_log) — append-only. Wraps em try/catch pra
        // tolerar env sem tabela (test sqlite por exemplo).
        $this->registrarAuditLog($apply, $stats, (int) ($businessId ?? 0));

        return $stats['error'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Heurística decryption pra detectar plain vs encrypted, depois
     * decide skip (encrypted) ou wrap (plain → encrypted).
     *
     * @return string Um de: encrypted | wrapped | would_wrap | error
     */
    private function detectAndRewrap(int $credentialId, ?string $raw, bool $apply): string
    {
        if ($raw === null || $raw === '') {
            // Empty config — não é vuln, mas re-saving aciona cast pra cifrar `[]`.
            // Skip silencioso pra não criar trabalho desnecessário.
            return 'encrypted';
        }

        // Tenta decryptString — Laravel valida MAC + AES-256.
        try {
            Crypt::decryptString($raw);

            // Sucesso → já cifrado. Cast `encrypted:array` faz duplo:
            // encryptString(json_encode($array)), então decryptString
            // devolve o JSON. Validamos só que cipher é válido.
            return 'encrypted';
        } catch (DecryptException) {
            // Falha → payload em plain JSON. Decode pra array + re-save.
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                $this->error(sprintf('  id=%d: config_json não é JSON válido nem cipher — manual review', $credentialId));

                return 'error';
            }

            if (! $apply) {
                return 'would_wrap';
            }

            // Wrap-in-place via UPDATE direto — Eloquent->find() iria
            // disparar o cast `encrypted:array` na hidratação e quebrar com
            // "payload is invalid" (raw é plain). Bypass total: encrypta
            // manualmente com Crypt::encryptString(json_encode(...)) — mesma
            // operação que o cast EncryptedAttribute::set() faz internamente.
            try {
                $cipher = Crypt::encryptString(json_encode($decoded, JSON_UNESCAPED_UNICODE));

                $updated = DB::table('payment_gateway_credentials')
                    ->where('id', $credentialId)
                    ->update([
                        'config_json' => $cipher,
                        'updated_at'  => now(),
                    ]);

                return $updated > 0 ? 'wrapped' : 'error';
            } catch (\Throwable $e) {
                Log::error('[paymentgateway.rewrap.failed]', [
                    'credential_id' => $credentialId,
                    'exception'     => $e->getMessage(),
                ]);
                $this->error(sprintf('  id=%d: falha ao re-cifrar — %s', $credentialId, $e->getMessage()));

                return 'error';
            }
        }
    }

    private function paintStatus(string $status): string
    {
        return match ($status) {
            'encrypted'  => '<fg=green>encrypted (skip)</>',
            'wrapped'    => '<fg=yellow>wrapped</>',
            'would_wrap' => '<fg=yellow>would_wrap</>',
            'error'      => '<fg=red>ERROR</>',
            default      => $status,
        };
    }

    /**
     * Append-only audit log (mcp_audit_log via Eloquent McpAuditLog).
     * Tolerante a env sem tabela (test sqlite sem migrations Jana → skip).
     */
    private function registrarAuditLog(bool $apply, array $stats, int $businessIdFilter): void
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('mcp_audit_log')) {
                return;
            }

            \Modules\Jana\Entities\Mcp\McpAuditLog::registrar([
                'endpoint'         => 'paymentgateway:rewrap-credentials',
                'tool_or_resource' => 'rewrap_command',
                'scope_required'   => 'superadmin.cli',
                'status'           => $stats['error'] > 0 ? 'error' : 'success',
                'user_id'          => 0, // CLI sem auth user
                'business_id'      => $businessIdFilter > 0 ? $businessIdFilter : null,
                'payload_summary'  => [
                    'mode'           => $apply ? 'apply' : 'dry-run',
                    'encrypted_skip' => $stats['encrypted'],
                    'wrapped'        => $stats['wrapped'],
                    'would_wrap'     => $stats['would_wrap'],
                    'errors'         => $stats['error'],
                    'business_null'  => $stats['business_null'],
                ],
            ]);
        } catch (\Throwable $e) {
            // Não bloqueia comando se audit log falhar — log local apenas.
            Log::warning('[paymentgateway.rewrap.audit_log_failed]', [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
