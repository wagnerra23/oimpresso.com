<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\HealthCheckService;

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
            'gateway_key'      => 'required|string|in:inter,c6,asaas,bcb_pix,pesapal',
            'ambiente'         => 'required|string|in:production,sandbox',
            'nome_display'     => 'nullable|string|max:191',
            'conta_bancaria_id' => 'nullable|integer|exists:accounts,id',
            'config_json'      => 'required|array',
            'ativo'            => 'sometimes|boolean',
            'cert_file'        => 'sometimes|file|mimes:crt,pem,cer|max:32',
            'key_file'         => 'sometimes|file|mimes:key,pem|max:32',
            'cert_password'    => 'sometimes|nullable|string|max:191',
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
     */
    public function update(Request $request, int $credentialId): JsonResponse
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
