<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FeatureFlagAudit;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Wrapper sobre a REST API admin do GrowthBook OSS self-hosted (CT 100).
 *
 * Complementa FeatureFlagService (que SÓ LÊ flags via SDK key) com operações de
 * escrita: listar features, ler 1 feature, adicionar/remover rules de targeting
 * por business_id, ativar/desativar features por environment.
 *
 * Auditado em `feature_flag_audits` (append-only) — captura mudanças via os 3
 * canais (Artisan, MCP tools, painel admin /admin/feature-flags).
 *
 * Auth: Bearer Personal Access Token (env `GROWTHBOOK_ADMIN_API_TOKEN`,
 * formato `secret_admin_xxxxx`). Token gerado em
 * https://growthbook.oimpresso.com → Settings → Personal Access Tokens.
 *
 * Refs: ADR 0093 (multi-tenant Tier 0), ADR 0094 (Constituição §princípio 7
 * transparência → audit log obrigatório), US-INFRA-001 (GrowthBook leitura) + US-INFRA-008 (escrita admin).
 */
class GrowthBookAdminService
{
    private const REQUEST_TIMEOUT_SECONDS = 10;

    private const DEFAULT_ENV = 'production';

    /** Convenção: rule de targeting por business_id usa id estável `biz-{N}`. */
    private const BIZ_RULE_ID_PREFIX = 'biz-';

    private string $apiHost;

    private string $apiToken;

    /** Projeto GrowthBook das features criadas por createBooleanFeature. */
    private string $project;

    public function __construct()
    {
        // A REST API admin do GrowthBook vive no MESMO host do SDK read
        // (GROWTHBOOK_API_HOST), sob /api/v1. O default antigo
        // (growthbook.oimpresso.com/api/v1) apontava pro frontend Next.js, que
        // responde HTTP 404 (HTML da SPA) em rotas /api/v1/* — quebrava
        // flag:list / flag:set / flag:env-toggle quando só o token estava no .env.
        // Derivar de GROWTHBOOK_API_HOST mantém consistência com FeatureFlagService
        // (leitura) e evita divergência de host. Override explícito via
        // GROWTHBOOK_ADMIN_API_HOST continua respeitado.
        // env() primeiro (testes usam putenv); config() quando o config está
        // cacheado em produção e env() vem vazio — ver config/feature-flags.php.
        $gb = (array) config('feature-flags.growthbook', []);
        $sdkHost = (string) (env('GROWTHBOOK_API_HOST') ?: ($gb['api_host'] ?? '') ?: 'https://growthbook-api.oimpresso.com');
        $this->apiHost = rtrim((string) (
            env('GROWTHBOOK_ADMIN_API_HOST') ?: ($gb['admin_api_host'] ?? '') ?: rtrim($sdkHost, '/') . '/api/v1'
        ), '/');
        $this->apiToken = (string) (env('GROWTHBOOK_ADMIN_API_TOKEN') ?: ($gb['admin_api_token'] ?? ''));
        // Só config(): a chave nova não entra no baseline do larastan.noEnvCallsOutsideOfConfig.
        $this->project = (string) ($gb['project'] ?? '');
    }

    public function isConfigured(): bool
    {
        return $this->apiToken !== '' && $this->apiHost !== '';
    }

    /**
     * Lista features (paginado pelo GrowthBook; aqui pegamos primeira página
     * com limite alto — projeto interno tem ≤50 flags previstas).
     *
     * @return array<int, array{id:string, defaultValue:mixed, valueType:string, rules:array}>
     */
    public function listFeatures(?string $projectId = null): array
    {
        $this->assertConfigured();

        $query = ['limit' => 100];
        if ($projectId !== null) {
            $query['projectId'] = $projectId;
        }

        $response = $this->http()->get($this->apiHost . '/features', $query);

        if (! $response->successful()) {
            throw new RuntimeException(
                "GrowthBook /features falhou: HTTP {$response->status()} — {$response->body()}"
            );
        }

        return (array) $response->json('features', []);
    }

    /**
     * Lê 1 feature por chave (ex: 'useV2SellsCreate').
     *
     * @return array{id:string, defaultValue:mixed, valueType:string, rules:array}|null
     */
    public function getFeature(string $key): ?array
    {
        $this->assertConfigured();
        $this->assertKeyValid($key);

        $response = $this->http()->get($this->apiHost . '/features/' . rawurlencode($key));

        // Feature inexistente: a API do GrowthBook responde HTTP 400 com
        // "Could not find a feature with that key" (medido em prod 2026-11-03),
        // não 404. Outro 400 segue sendo erro.
        if ($response->status() === 404
            || ($response->status() === 400 && str_contains((string) $response->json('message'), 'Could not find a feature'))) {
            return null;
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                "GrowthBook /features/{$key} falhou: HTTP {$response->status()} — {$response->body()}"
            );
        }

        return (array) $response->json('feature');
    }

    /**
     * Cria uma feature booleana DESLIGADA: defaultValue false, sem rule, environment ligado
     * (com o environment desligado nem a rule por empresa vale). Ligar pra uma empresa é
     * `setBizRule` depois. Idempotente: se a feature já existe, devolve a existente sem tocar.
     *
     * @return array{criada: bool, feature: array}
     */
    public function createBooleanFeature(string $key, string $descricao, string $env = self::DEFAULT_ENV): array
    {
        $this->assertConfigured();
        $this->assertKeyValid($key);

        $existente = $this->getFeature($key);
        if ($existente !== null) {
            return ['criada' => false, 'feature' => $existente];
        }

        $payload = [
            'id'           => $key,
            'owner'        => 'oimpresso-api',
            'description'  => $descricao,
            'valueType'    => 'boolean',
            'defaultValue' => 'false',
            'environments' => [$env => ['enabled' => true, 'rules' => []]],
        ];
        if ($this->project !== '') {
            $payload['project'] = $this->project;
        }

        $response = $this->http()->post($this->apiHost . '/features', $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                "GrowthBook POST /features ({$key}) falhou: HTTP {$response->status()} — {$response->body()}"
            );
        }

        $this->audit(
            action: 'feature_create',
            flagKey: $key,
            environment: $env,
            payloadBefore: [],
            payloadAfter: ['defaultValue' => 'false', 'rules' => []],
            summary: "Feature {$key} criada desligada (defaultValue=false, sem rule)"
        );

        return ['criada' => true, 'feature' => (array) $response->json('feature')];
    }

    /**
     * Adiciona/atualiza uma rule de targeting pra um business_id específico.
     *
     * Convenção: 1 business_id = 1 rule com id `biz-{N}` e
     * condition `{"business_id": N}`. Upsert por id estável.
     *
     * @param  string  $key  Chave da feature (ex: 'useV2SellsCreate')
     * @param  int  $bizId  business_id alvo (ex: 4)
     * @param  bool  $forceValue  Valor que será forçado (true=feature ON pra esse biz)
     * @param  string  $env  Environment (default: production)
     * @return array  Feature atualizada
     */
    public function setBizRule(string $key, int $bizId, bool $forceValue, string $env = self::DEFAULT_ENV): array
    {
        $this->assertConfigured();
        $this->assertKeyValid($key);

        $feature = $this->getFeature($key);
        if ($feature === null) {
            throw new RuntimeException("Feature '{$key}' não existe no GrowthBook. Crie via UI primeiro.");
        }

        $ruleId = self::BIZ_RULE_ID_PREFIX . $bizId;
        $newRule = [
            'id'           => $ruleId,
            'type'         => 'force',
            'description'  => "Targeting business_id={$bizId} (gerenciado pela API oimpresso)",
            'condition'    => json_encode(['business_id' => $bizId], JSON_THROW_ON_ERROR),
            'enabled'      => true,
            'value'        => $forceValue ? 'true' : 'false',
        ];

        $rulesByEnv = $this->extractRulesByEnv($feature, $env);
        $rulesByEnv = $this->upsertRule($rulesByEnv, $newRule);

        $beforeRules = $this->extractRulesByEnv($feature, $env);
        $updated = $this->updateFeatureRules($key, $env, $rulesByEnv, $feature);

        $this->audit(
            action: 'rule_upsert',
            flagKey: $key,
            environment: $env,
            payloadBefore: ['rules' => $beforeRules],
            payloadAfter: ['rules' => $rulesByEnv],
            summary: "Rule {$ruleId} setada (value={$newRule['value']})"
        );

        return $updated;
    }

    /**
     * Remove a rule de targeting de um business_id específico (rule com id `biz-{N}`).
     * No-op se a rule não existir.
     */
    public function removeBizRule(string $key, int $bizId, string $env = self::DEFAULT_ENV): array
    {
        $this->assertConfigured();
        $this->assertKeyValid($key);

        $feature = $this->getFeature($key);
        if ($feature === null) {
            throw new RuntimeException("Feature '{$key}' não existe.");
        }

        $ruleId = self::BIZ_RULE_ID_PREFIX . $bizId;
        $rulesByEnv = $this->extractRulesByEnv($feature, $env);
        $beforeRules = $rulesByEnv;
        $filtered = array_values(array_filter($rulesByEnv, fn ($r) => ($r['id'] ?? null) !== $ruleId));

        if (count($filtered) === count($rulesByEnv)) {
            return $feature;  // No-op
        }

        $updated = $this->updateFeatureRules($key, $env, $filtered, $feature);

        $this->audit(
            action: 'rule_remove',
            flagKey: $key,
            environment: $env,
            payloadBefore: ['rules' => $beforeRules],
            payloadAfter: ['rules' => $filtered],
            summary: "Rule {$ruleId} removida"
        );

        return $updated;
    }

    /**
     * Liga/desliga a feature inteira em um environment (mata-switch).
     */
    public function setEnvEnabled(string $key, bool $enabled, string $env = self::DEFAULT_ENV): array
    {
        $this->assertConfigured();
        $this->assertKeyValid($key);

        $feature = $this->getFeature($key);
        if ($feature === null) {
            throw new RuntimeException("Feature '{$key}' não existe.");
        }

        $payload = [
            'environments' => [
                $env => [
                    'enabled' => $enabled,
                    // Preserva rules existentes — só toca enabled.
                    'rules'   => $this->extractRulesByEnv($feature, $env),
                ],
            ],
        ];

        $response = $this->http()->post($this->apiHost . '/features/' . rawurlencode($key), $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                "GrowthBook POST /features/{$key} falhou: HTTP {$response->status()} — {$response->body()}"
            );
        }

        $updated = (array) $response->json('feature');

        $this->audit(
            action: 'env_toggle',
            flagKey: $key,
            environment: $env,
            payloadBefore: ['enabled' => $this->extractEnvEnabled($feature, $env)],
            payloadAfter: ['enabled' => $enabled],
            summary: "Environment {$env} enabled={$this->boolStr($enabled)}"
        );

        return $updated;
    }

    /**
     * Limpa cache local do FeatureFlagService (Laravel) — força re-fetch imediato
     * em vez de esperar TTL 60s. Útil pós-mudança via UI/API pra invalidar mais rápido.
     */
    public function clearLocalCache(): void
    {
        app(FeatureFlagService::class)->clearCache();
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->apiToken)
            ->acceptJson()
            ->asJson()
            ->timeout(self::REQUEST_TIMEOUT_SECONDS);
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'GrowthBookAdminService não configurado. Defina GROWTHBOOK_ADMIN_API_TOKEN '
                . 'no .env (token gerado em https://growthbook.oimpresso.com → Settings → '
                . 'Personal Access Tokens). O host da REST API é derivado de GROWTHBOOK_API_HOST '
                . '(+ /api/v1); só defina GROWTHBOOK_ADMIN_API_HOST pra sobrescrever.'
            );
        }
    }

    private function assertKeyValid(string $key): void
    {
        if ($key === '' || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_\-]{0,99}$/', $key)) {
            throw new RuntimeException(
                "Feature key inválida: '{$key}'. Deve começar com letra e conter só [a-zA-Z0-9_-] (1-100 chars)."
            );
        }
    }

    /**
     * @param  array  $feature  Feature retornada por /features/{id}
     * @return array<int, array<string, mixed>>
     */
    private function extractRulesByEnv(array $feature, string $env): array
    {
        $envs = $feature['environments'] ?? [];
        $envData = $envs[$env] ?? [];

        return (array) ($envData['rules'] ?? []);
    }

    private function extractEnvEnabled(array $feature, string $env): bool
    {
        $envs = $feature['environments'] ?? [];
        $envData = $envs[$env] ?? [];

        return (bool) ($envData['enabled'] ?? false);
    }

    /**
     * Upsert por id: substitui rule existente OU adiciona no topo (rules têm
     * precedência por ordem; targeting específico vem antes do defaultValue).
     *
     * @param  array<int, array<string, mixed>>  $rules
     * @param  array<string, mixed>  $newRule
     * @return array<int, array<string, mixed>>
     */
    private function upsertRule(array $rules, array $newRule): array
    {
        $id = (string) ($newRule['id'] ?? '');
        $replaced = false;
        $out = [];

        foreach ($rules as $r) {
            if (($r['id'] ?? null) === $id) {
                $out[] = $newRule;
                $replaced = true;
            } else {
                $out[] = $r;
            }
        }

        if (! $replaced) {
            // Adicionar no topo pra ter precedência sobre defaultValue.
            array_unshift($out, $newRule);
        }

        return $out;
    }

    /**
     * PUT-style: substitui rules do environment inteiro. GrowthBook não tem
     * endpoint dedicado pra rules; passa pela atualização da feature inteira.
     */
    private function updateFeatureRules(string $key, string $env, array $rules, array $feature): array
    {
        // O GrowthBook exige `enabled` em todo environment enviado: só com `rules` ele responde
        // HTTP 400 "environments.production.enabled expected boolean" e nenhuma regra por
        // empresa grava. Repete o estado atual (padrão ligado) para não mudar o mata-switch.
        $payload = [
            'environments' => [
                $env => [
                    'enabled' => (bool) ($feature['environments'][$env]['enabled'] ?? true),
                    'rules'   => $rules,
                ],
            ],
        ];

        $response = $this->http()->post($this->apiHost . '/features/' . rawurlencode($key), $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                "GrowthBook POST /features/{$key} falhou: HTTP {$response->status()} — {$response->body()}"
            );
        }

        return (array) $response->json('feature');
    }

    /**
     * Grava em feature_flag_audits. Falha silenciosamente (log warning) pra não
     * quebrar operação principal se a tabela ainda não existe (ex: migration
     * pendente em fresh install).
     */
    private function audit(
        string $action,
        string $flagKey,
        ?string $environment,
        array $payloadBefore,
        array $payloadAfter,
        string $summary,
    ): void {
        try {
            FeatureFlagAudit::create([
                'actor_id'       => auth()->id(),
                'actor_label'    => $this->resolveActorLabel(),
                'flag_key'       => $flagKey,
                'action'         => $action,
                'environment'    => $environment,
                'payload_before' => $payloadBefore,
                'payload_after'  => $payloadAfter,
                'diff_summary'   => $summary,
            ]);
        } catch (Throwable $e) {
            Log::warning('GrowthBookAdminService: falha ao gravar audit (não-fatal)', [
                'flag' => $flagKey,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveActorLabel(): string
    {
        if (app()->runningInConsole()) {
            // Artisan ou MCP server (CLI)
            $cmd = (string) ($_SERVER['argv'][1] ?? 'unknown');
            return 'cli:' . substr($cmd, 0, 60);
        }

        $user = auth()->user();
        if ($user !== null) {
            return 'web:' . ($user->email ?? ('user-' . $user->id));
        }

        return 'anonymous';
    }

    private function boolStr(bool $v): string
    {
        return $v ? 'true' : 'false';
    }
}
