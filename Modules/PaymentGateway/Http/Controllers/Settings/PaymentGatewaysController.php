<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\HealthCheckService;
use Spatie\Activitylog\Models\Activity;

/**
 * Tela /settings/payment-gateways — F3 PaymentGateway UI Tela 2.
 *
 * Persona-foco: Wagner (superadmin / dono). Tela read+toggle — emissão
 * full de credencial fica no SheetNovoGateway (3-step wizard, frontend);
 * health check real via HealthCheckService (Onda 4d.3).
 *
 * Origem: Cowork F1+F1.5 (score 93/100) aprovado [W] 2026-05-19.
 * ADR 0144 + ADR 0170. Charter:
 * resources/js/Pages/Settings/PaymentGateways/Index.charter.md.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL — `PaymentGatewayCredential` herda
 * business_id global scope via HasBusinessScope.
 *
 * Permission canon: `paymentgateway.credenciais.view` (granular). Hoje
 * usamos `system_settings.access` como fallback (UPOS canon) — granular
 * em backlog post-merge.
 */
class PaymentGatewaysController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // TODO[follow-up]: criar permission granular paymentgateway.credenciais.*
        // e adicionar via PermissionSeeder. Por hora usa system_settings.access
        // (UPOS canon — superadmin/owner já têm).
    }

    public function index(Request $request): Response
    {
        $businessId = (int) $request->session()->get('user.business_id', $request->session()->get('business.id', 0));

        return Inertia::render('Settings/PaymentGateways/Index', [
            'today' => CarbonImmutable::today()->toDateString(),

            'accounts' => Inertia::defer(fn () => $this->listarContasDestino($businessId)),
            'gateways' => Inertia::defer(fn () => $this->listarGateways($businessId)),
            'kpis' => Inertia::defer(fn () => $this->kpis($businessId)),
        ]);
    }

    /**
     * Health check endpoint — rodar 1 credencial OU todas (?all=1).
     * Retorna JSON (chamado via fetch/axios direto do frontend).
     */
    public function healthCheck(Request $request, HealthCheckService $svc, ?int $credentialId = null): JsonResponse
    {
        $businessId = (int) $request->session()->get('user.business_id', $request->session()->get('business.id', 0));

        if ($credentialId) {
            $cred = PaymentGatewayCredential::query()
                ->where('business_id', $businessId)
                ->findOrFail($credentialId);

            $h = $svc->check($cred);

            return response()->json([
                'credential_id' => $cred->id,
                'status' => $h->status,
                'latency_ms' => $h->latencyMs,
                'message' => $h->errorMessage,
                'checked_at' => $h->checkedAt->format('c'),
            ]);
        }

        $results = $svc->checkAll($businessId);

        return response()->json(['results' => $results]);
    }

    /**
     * Cria nova credencial PaymentGateway.
     *
     * Onda 5 (2026-05-19) — completa o wizard SheetNovoGateway (frontend
     * F3 PR #1135 entregou UI, faltava endpoint backend).
     *
     * Trust L3 — secrets em config_json. NÃO cifra automaticamente nesta
     * onda (config_json cast 'array' — armazena JSON literal). Encryption
     * automática por field fica em ondas futuras (atualmente .env do prod
     * é fonte primária; credencial é gestão UI complementar).
     *
     * Multi-tenant Tier 0 — business_id derivado de session, NUNCA do payload.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $businessId = (int) $request->session()->get('user.business_id', $request->session()->get('business.id', 0));

        if ($businessId <= 0) {
            return response()->json(['success' => false, 'msg' => 'Sessão sem business_id'], 403);
        }

        $validated = $request->validate([
            'gateway_key'      => 'required|string|in:inter,c6,asaas,bcb_pix,pagarme,sicoob_api',
            'ambiente'         => 'required|string|in:production,sandbox',
            'nome_display'     => 'nullable|string|max:191',
            'conta_bancaria_id' => 'nullable|integer|exists:accounts,id',
            'config_json'      => 'required|array',
            'ativo'            => 'sometimes|boolean',
            'cert_file'        => 'sometimes|file|mimes:crt,pem,cer|max:32',
            'key_file'         => 'sometimes|file|mimes:key,pem|max:32',
            'cert_password'    => 'sometimes|nullable|string|max:191',
            // Onda 4f.sicoob_api PR5 — .pfx PKCS12 + senha (Sicoob/BB/Bradesco)
            'pfx_file'         => 'sometimes|file|mimes:pfx,p12|max:64',
            'pfx_password'     => 'sometimes|nullable|string|max:191',
        ]);

        // Tier 0: garantir conta_bancaria_id pertence ao business_id session
        if (!empty($validated['conta_bancaria_id'])) {
            $ownsAccount = \App\Account::where('id', $validated['conta_bancaria_id'])
                ->where('business_id', $businessId)
                ->exists();
            if (!$ownsAccount) {
                return response()->json(['success' => false, 'msg' => 'Conta não pertence ao business'], 403);
            }
        }

        // Anti-dupla — (business_id, gateway_key, ambiente) é unique no schema
        $duplicate = PaymentGatewayCredential::query()
            ->withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('gateway_key', $validated['gateway_key'])
            ->where('ambiente', $validated['ambiente'])
            ->exists();

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'msg' => sprintf('Já existe credencial %s (%s) em biz=%d. Edite a existente.', $validated['gateway_key'], $validated['ambiente'], $businessId),
            ], 422);
        }

        $configJson = $validated['config_json'];

        $cred = PaymentGatewayCredential::create([
            'business_id'        => $businessId,
            'gateway_key'        => $validated['gateway_key'],
            'ambiente'           => $validated['ambiente'],
            'nome_display'       => $validated['nome_display'] ?? null,
            'conta_bancaria_id'  => $validated['conta_bancaria_id'] ?? null,
            'config_json'        => $configJson,
            'ativo'              => (bool) ($validated['ativo'] ?? true),
            'health_status'      => 'unknown',
        ]);

        if ($request->hasFile('cert_file') || $request->hasFile('key_file')) {
            $certPaths = $this->storeCertFiles($request, $cred, $businessId);
            if (!empty($certPaths)) {
                $configJson = array_merge($configJson, $certPaths);
                if (!empty($validated['cert_password'])) {
                    $configJson['cert_password'] = $validated['cert_password'];
                }
                $cred->update(['config_json' => $configJson]);
            }
        }

        // Onda 4f.sicoob_api PR5 — upload .pfx + senha cifrada via Crypt
        if ($request->hasFile('pfx_file')) {
            $pfxRelative = $this->storeSicoobPfx($request, $businessId);
            $update = ['mtls_pfx_path' => $pfxRelative, 'requires_mtls' => true];
            if (!empty($validated['pfx_password'])) {
                $configJson['mtls_pfx_password_encrypted'] = Crypt::encryptString((string) $validated['pfx_password']);
                $update['config_json'] = $configJson;
            }
            $cred->update($update);
        }

        Log::info('[paymentgateway.credential.created]', [
            'business_id'   => $businessId,
            'credential_id' => $cred->id,
            'gateway_key'   => $cred->gateway_key,
            'ambiente'      => $cred->ambiente,
            'has_cert'      => isset($configJson['certificado_crt']),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'credential_id' => $cred->id,
                'gateway_key' => $cred->gateway_key,
            ], 201);
        }

        return redirect()->route('settings.payment-gateways.index')
            ->with('status', ['success' => 1, 'msg' => 'Credencial criada: ' . ($cred->nome_display ?: $cred->gateway_key)]);
    }

    /**
     * Atualiza credencial existente. Pattern store — apenas campos enviados.
     *
     * Resposta dual: Inertia request → redirect (Inertia router espera 302).
     * Plain JSON request → JSON {success, credential_id} (futuro API).
     */
    public function update(Request $request, int $credentialId): RedirectResponse|JsonResponse
    {
        $businessId = (int) $request->session()->get('user.business_id', $request->session()->get('business.id', 0));

        $cred = PaymentGatewayCredential::query()
            ->where('business_id', $businessId)
            ->findOrFail($credentialId);

        $validated = $request->validate([
            'nome_display'      => 'sometimes|nullable|string|max:191',
            'conta_bancaria_id' => 'sometimes|nullable|integer|exists:accounts,id',
            'config_json'       => 'sometimes|array',
            'ambiente'          => 'sometimes|string|in:production,sandbox',
            'ativo'             => 'sometimes|boolean',
            'cert_file'         => 'sometimes|file|mimes:crt,pem,cer|max:32',
            'key_file'          => 'sometimes|file|mimes:key,pem|max:32',
            'cert_password'     => 'sometimes|nullable|string|max:191',
        ]);

        if (!empty($validated['conta_bancaria_id'])) {
            $ownsAccount = \App\Account::where('id', $validated['conta_bancaria_id'])
                ->where('business_id', $businessId)
                ->exists();
            if (!$ownsAccount) {
                return response()->json(['success' => false, 'msg' => 'Conta não pertence ao business'], 403);
            }
        }

        $payload = array_diff_key($validated, array_flip(['cert_file', 'key_file', 'cert_password']));

        if (array_key_exists('config_json', $payload)) {
            $payload['config_json'] = array_merge($cred->config_json ?? [], $payload['config_json']);
        }

        if ($request->hasFile('cert_file') || $request->hasFile('key_file')) {
            $certPaths = $this->storeCertFiles($request, $cred, $businessId);
            if (!empty($certPaths)) {
                $existing = $payload['config_json'] ?? ($cred->config_json ?? []);
                $payload['config_json'] = array_merge($existing, $certPaths);
                if (!empty($validated['cert_password'])) {
                    $payload['config_json']['cert_password'] = $validated['cert_password'];
                }
            }
        }

        $cred->update($payload);

        Log::info('[paymentgateway.credential.updated]', [
            'business_id'   => $businessId,
            'credential_id' => $cred->id,
            'fields'        => array_keys($payload),
            'has_cert_upload' => $request->hasFile('cert_file') || $request->hasFile('key_file'),
        ]);

        if ($request->header('X-Inertia') || !$request->expectsJson()) {
            return redirect()->route('settings.payment-gateways.index')
                ->with('status', ['success' => 1, 'msg' => 'Credencial atualizada']);
        }

        return response()->json([
            'success' => true,
            'credential_id' => $cred->id,
        ]);
    }

    /**
     * Salva cert.crt + key.key em storage privado.
     * Path: storage/app/private/payment-gateway/{biz}/{cred}/{cert,key}.{ext}
     * Permissions: 0600. Tier 0: business_id no path previne leak.
     * LGPD/PCI: log nunca emite conteúdo.
     *
     * @return array<string, string>
     */

    /**
     * Onda 4f.sicoob_api PR5 — armazena .pfx em
     * storage/app/private/sicoob/{business_id}.pfx (convenção canon usada
     * pelo SicoobApiDriver::resolveMtlsPfxFullPath()).
     *
     * Retorna path RELATIVO (sicoob/{biz}.pfx) — driver prefixa storage_path.
     * chmod 0600 best-effort em Linux (Windows ignora silenciosamente).
     */
    private function storeSicoobPfx(Request $request, int $businessId): string
    {
        /** @var UploadedFile $pfx */
        $pfx = $request->file('pfx_file');
        $relativePath = "sicoob/{$businessId}.pfx";
        $pfx->storeAs('sicoob', "{$businessId}.pfx", 'local');

        $absPath = Storage::disk('local')->path($relativePath);
        if (is_file($absPath)) {
            @chmod($absPath, 0600);
        }

        return $relativePath;
    }

    private function storeCertFiles(Request $request, PaymentGatewayCredential $cred, int $businessId): array
    {
        $paths = [];
        $baseDir = 'payment-gateway/' . $businessId . '/' . $cred->id;

        if ($request->hasFile('cert_file')) {
            /** @var UploadedFile $certFile */
            $certFile = $request->file('cert_file');
            $certPath = $certFile->storeAs($baseDir, 'cert.' . $certFile->getClientOriginalExtension(), 'local');
            $absPath = Storage::disk('local')->path($certPath);
            if (is_file($absPath)) {
                @chmod($absPath, 0600);
            }
            $paths['certificado_crt'] = $absPath;
        }

        if ($request->hasFile('key_file')) {
            /** @var UploadedFile $keyFile */
            $keyFile = $request->file('key_file');
            $keyPath = $keyFile->storeAs($baseDir, 'key.' . $keyFile->getClientOriginalExtension(), 'local');
            $absPath = Storage::disk('local')->path($keyPath);
            if (is_file($absPath)) {
                @chmod($absPath, 0600);
            }
            $paths['certificado_key'] = $absPath;
        }

        return $paths;
    }

    /**
     * Exclui credencial + apaga storage de certs.
     *
     * Onda 5+ (Wagner 2026-05-19) — DELETE /settings/payment-gateways/{id}.
     *
     * Tier 0: business_id da session — credencial cross-tenant NÃO pode
     * ser deletada por business não-owner. findOrFail dentro do scope
     * garante 404 se credential pertence a outro biz.
     *
     * Side effects:
     *   - Apaga arquivos cert.* + key.* em storage/app/private/payment-gateway/{biz}/{cred}/
     *   - Remove o diretório completo se vazio
     *
     * Hard delete (PaymentGatewayCredential não tem soft deletes no schema).
     * Cobranças vinculadas continuam (FK cobrancas.payment_gateway_credential_id
     * permanece NULL após delete — não cascateia pra preservar histórico).
     */
    public function destroy(Request $request, int $credentialId): RedirectResponse|JsonResponse
    {
        $businessId = (int) $request->session()->get('user.business_id', $request->session()->get('business.id', 0));

        $cred = PaymentGatewayCredential::query()
            ->where('business_id', $businessId)
            ->findOrFail($credentialId);

        $certDir = 'payment-gateway/' . $businessId . '/' . $cred->id;
        try {
            Storage::disk('local')->deleteDirectory($certDir);
        } catch (\Throwable $e) {
            Log::warning('[paymentgateway.credential.destroy.storage_cleanup_failed]', [
                'credential_id' => $cred->id,
                'business_id'   => $businessId,
                'exception'     => $e->getMessage(),
            ]);
        }

        $cred->delete();

        Log::info('[paymentgateway.credential.destroyed]', [
            'business_id'   => $businessId,
            'credential_id' => $credentialId,
            'gateway_key'   => $cred->gateway_key,
            'ambiente'      => $cred->ambiente,
        ]);

        if ($request->header('X-Inertia') || !$request->expectsJson()) {
            return redirect()->route('settings.payment-gateways.index')
                ->with('status', ['success' => 1, 'msg' => 'Credencial excluída']);
        }

        return response()->json([
            'success' => true,
            'credential_id' => $credentialId,
        ]);
    }

    /**
     * GET /settings/payment-gateways/{credentialId}/history
     *
     * Trilha de auditoria read-only — agrega rows da `activity_log` (Spatie
     * ActivityLog) com subject_type=PaymentGatewayCredential + subject_id=$credentialId,
     * filtrado por business_id pra Tier 0 safety (ADR 0093).
     *
     * Shape espelha contrato Financeiro `auditTrail` pra reuso de componente UI:
     *   { entries: [{ id, when, when_iso, who, action, event, diff?: {field, from, to} }], total }
     *
     * LGPD/PCI: `config_json` (segredos) NÃO está em `logOnly` do model (linhas 50-58),
     * portanto NUNCA aparece em properties.old/attributes — diff só expõe gateway_key,
     * ambiente, ativo, nome_display, conta_bancaria_id, health_status, health_checked_at.
     *
     * Onda 4e.UI (gap P0 catalogado em estado-da-arte 2026-05-23 — nota 78/100 → 82+).
     */
    public function history(Request $request, int $credentialId): JsonResponse
    {
        $businessId = (int) $request->session()->get('user.business_id', $request->session()->get('business.id', 0));

        // Tier 0: credential precisa ser do business da sessão; senão 404
        $credential = PaymentGatewayCredential::query()
            ->where('business_id', $businessId)
            ->find($credentialId);

        if (! $credential) {
            return response()->json(['entries' => [], 'total' => 0], 404);
        }

        $rows = Activity::query()
            ->where('subject_type', PaymentGatewayCredential::class)
            ->where('subject_id', $credentialId)
            ->where('business_id', $businessId)
            ->with('causer:id,first_name,last_name,username')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $entries = $rows->map(function (Activity $a) {
            $causer = $a->causer;
            $who = $causer
                ? (trim(($causer->first_name ?? '').' '.($causer->last_name ?? '')) ?: ($causer->username ?? 'Sistema'))
                : 'Sistema';

            $action = match ($a->event) {
                'created' => 'criou',
                'updated' => 'editou',
                'deleted' => 'deletou',
                default => $a->description ?: ($a->event ?? 'alterou'),
            };

            $entry = [
                'id'       => $a->id,
                'when'     => $a->created_at?->locale('pt_BR')->isoFormat('DD/MM HH:mm'),
                'when_iso' => $a->created_at?->toIso8601String(),
                'who'      => $who,
                'action'   => $action,
                'event'    => $a->event,
            ];

            // Diff: Spatie LogsActivity grava ['old' => [...], 'attributes' => [...]]
            // quando logOnly + logOnlyDirty ativo (este model usa, line 50-58).
            $props = is_array($a->properties) ? $a->properties : ($a->properties?->toArray() ?? []);
            $old = $props['old'] ?? null;
            $new = $props['attributes'] ?? null;

            if (is_array($old) && is_array($new)) {
                foreach ($new as $field => $to) {
                    $from = $old[$field] ?? null;
                    if ($from !== $to) {
                        $entry['diff'] = [
                            'field' => $field,
                            'from'  => $from,
                            'to'    => $to,
                        ];
                        break; // primeiro campo alterado representa o evento na UI
                    }
                }
            }

            return $entry;
        });

        return response()->json([
            'entries' => $entries,
            'total'   => $entries->count(),
        ]);
    }

    /**
     * GET /settings/payment-gateways/{credentialId}/webhook-events
     *
     * Lista eventos de webhook recebidos pra esta credencial (últimos 50, DESC).
     *
     * Tier 0 (ADR 0093): credential precisa ser do business_id da sessão; eventos
     * herdam mesmo filtro via FK payment_gateway_credential_id + business_id explícito.
     *
     * Shape (espelha contract HistoryEntry pra reuso UI pattern):
     *   { events: [{ id, when, when_iso, evento, gateway_event_id, signature_valid,
     *                processed_at, error_message, cobranca_id }], total }
     *
     * LGPD/PCI: payload completo NÃO retorna (pode conter PII redacted-via-PiiRedactor
     * na hora de gravar, mas evita re-exposição). Só metadados.
     *
     * Onda 4e.UI #2 (gap P0 catalogado em estado-da-arte 2026-05-23) — fecha visibilidade
     * do `gateway_webhook_events` que já existia no DB desde Onda 2 mas nunca foi exposto.
     */
    public function webhookEvents(Request $request, int $credentialId): JsonResponse
    {
        $businessId = (int) $request->session()->get('user.business_id', $request->session()->get('business.id', 0));

        $credential = PaymentGatewayCredential::query()
            ->where('business_id', $businessId)
            ->find($credentialId);

        if (! $credential) {
            return response()->json(['events' => [], 'total' => 0], 404);
        }

        $rows = GatewayWebhookEvent::query()
            ->where('business_id', $businessId)
            ->where('payment_gateway_credential_id', $credentialId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $events = $rows->map(fn (GatewayWebhookEvent $e) => [
            'id'                => $e->id,
            'when'              => $e->created_at?->locale('pt_BR')->isoFormat('DD/MM HH:mm:ss'),
            'when_iso'          => $e->created_at?->toIso8601String(),
            'evento'            => $e->evento,
            'gateway_event_id'  => $e->gateway_event_id,
            'signature_valid'   => (bool) $e->signature_valid,
            'processed_at'      => $e->processed_at?->toIso8601String(),
            'error_message'     => $e->error_message,
            'cobranca_id'       => $e->cobranca_id,
        ]);

        return response()->json([
            'events' => $events,
            'total'  => $events->count(),
        ]);
    }

    /**
     * GET /settings/payment-gateways/{credentialId}/quota
     *
     * Quota tracking MVP — contagem de cobranças emitidas no mês corrente,
     * agrupada por `tipo` (boleto / pix_cob / pix_cobv / pix_recv / card).
     *
     * Gap P1 catalogado em auditoria 2026-05-23: Wagner só descobre que
     * estourou cota grátis do banco (Inter 250 boletos/mês, C6 200/2000)
     * quando vê tarifa cobrada. UI passa a mostrar "Cota usada: X" no
     * DrawerGateway tab Health.
     *
     * MVP: query agregada em `cobrancas` (sem contador persistido). OK até
     * ~10k cobranças/mês per credencial — índice
     * `(business_id, payment_gateway_credential_id)` em created_at via
     * `cobrancas_biz_status_venc` cobre prefix da query.
     *
     * Tier 0 (ADR 0093): credencial precisa ser do business_id da sessão;
     * count herda mesmo filtro via FK + business_id explícito (defense in
     * depth, mesmo com HasBusinessScope no model).
     *
     * Limite per driver (Inter 250 / C6 200|2000) NÃO é avaliado aqui —
     * UI mostra apenas total + breakdown; warning amber/rose fica em
     * follow-up (parse complexo de DRIVERS[*].pricing).
     *
     * Shape:
     *   {
     *     "month": "2026-05",
     *     "counts": {"boleto": 142, "pix_cob": 8, "card": 0},
     *     "total": 150,
     *     "gateway_key": "inter"
     *   }
     *
     * Onda 4e gap #3 — Refs: ADR 0170 + audit 2026-05-23.
     */
    public function quota(Request $request, int $credentialId): JsonResponse
    {
        $businessId = (int) $request->session()->get('user.business_id', $request->session()->get('business.id', 0));

        $credential = PaymentGatewayCredential::query()
            ->where('business_id', $businessId)
            ->find($credentialId);

        if (! $credential) {
            return response()->json([
                'month'       => CarbonImmutable::now()->format('Y-m'),
                'counts'      => [],
                'total'       => 0,
                'gateway_key' => null,
            ], 404);
        }

        $now = CarbonImmutable::now();

        $counts = Cobranca::query()
            ->where('business_id', $businessId)
            ->where('payment_gateway_credential_id', $credentialId)
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->selectRaw('tipo, COUNT(*) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        return response()->json([
            'month'       => $now->format('Y-m'),
            'counts'      => $counts,
            'total'       => array_sum($counts),
            'gateway_key' => $credential->gateway_key,
        ]);
    }

    /**
     * Toggle ativo/inativo da credencial (Trust L3 — confirma front-end).
     */
    public function toggle(Request $request, int $credentialId): JsonResponse
    {
        $businessId = (int) $request->session()->get('user.business_id', $request->session()->get('business.id', 0));

        $cred = PaymentGatewayCredential::query()
            ->where('business_id', $businessId)
            ->findOrFail($credentialId);

        $cred->update(['ativo' => ! $cred->ativo]);

        return response()->json([
            'credential_id' => $cred->id,
            'ativo' => $cred->ativo,
        ]);
    }

    /**
     * Lista contas destino do wizard (step 3).
     *
     * Wagner 2026-05-19 — fix bug crítico: id retornado DEVE ser `account_id`
     * (FK pra `accounts` UPOS), NÃO `fin_contas_bancarias.id`. PaymentGatewayCredential.conta_bancaria_id
     * aponta pra `accounts.id`. Mismatch anterior causou credencial id=1 ser
     * vinculada a Account 12 (CAIXA) quando Wagner escolheu fcb_id=12 (que
     * apontava pra Account 19 - BANCO INTER CNPJ NOVO).
     *
     * @return array<int, array<string, mixed>>
     */
    private function listarContasDestino(int $businessId): array
    {
        return ContaBancaria::query()
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->with('account:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (ContaBancaria $c) => [
                'id' => (int) $c->account_id, // FK pra accounts.id (NÃO fcb.id)
                'name' => $c->account?->name ?? '(sem nome)',
                'agencia' => $c->agencia ?? null,
                'conta' => $c->conta ?? null,
                'banco' => $c->banco_codigo,
                // fcb_id exposto pra possíveis usos futuros (atualmente não usado pelo wizard)
                'fcb_id' => (int) $c->id,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listarGateways(int $businessId): array
    {
        return PaymentGatewayCredential::query()
            ->where('business_id', $businessId)
            ->orderByDesc('ativo')
            ->orderBy('gateway_key')
            ->get()
            ->map(fn (PaymentGatewayCredential $c) => [
                'id' => $c->id,
                'driver' => $c->gateway_key,
                'nome' => $c->nome_display ?: $c->gateway_key,
                'ambiente' => $c->ambiente,
                'ativo' => (bool) $c->ativo,
                'account_id' => $c->conta_bancaria_id,
                'last_check' => $c->health_checked_at?->toIso8601String(),
                'health' => $c->health_status ?? 'ok',
                'latencia' => null,
                'created_at' => $c->created_at?->toDateString(),
                'warn' => $this->warnFor($c),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function kpis(int $businessId): array
    {
        $ativos = PaymentGatewayCredential::query()
            ->where('business_id', $businessId)
            ->where('ativo', true)
            ->count();
        $total = PaymentGatewayCredential::query()
            ->where('business_id', $businessId)
            ->count();
        $fail = PaymentGatewayCredential::query()
            ->where('business_id', $businessId)
            ->where('ativo', true)
            ->where('health_status', '!=', 'ok')
            ->count();
        $cobsHoje = Cobranca::query()
            ->where('business_id', $businessId)
            ->whereDate('created_at', CarbonImmutable::today())
            ->count();

        return [
            'ativos' => $ativos,
            'total' => $total,
            'fail' => $fail,
            'cobs_hoje' => $cobsHoje,
        ];
    }

    private function warnFor(PaymentGatewayCredential $c): ?string
    {
        // Driver legacy = warn
        if ($c->gateway_key === 'pesapal') {
            return 'Deprecated — migrar pra Asaas (BR nativo + 3DS)';
        }
        // mTLS expira em ≤30d (heurística simples — config_json poderia ter expira_em real)
        // Não implementado por enquanto.

        return null;
    }
}
