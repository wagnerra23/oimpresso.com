# Prompt Caching Live — Anthropic (GAP D4 #5)

> Status: implementado 2026-05-15. Validação 2 semanas pós-deploy via aggregation `prompt_cache_event` no log channel `copiloto-ai`.

Identificado pela auditoria `memoria-senior` em [`memory/audits/AUDITORIA-MEMORIA-2026-05-15.md`](../../audits/AUDITORIA-MEMORIA-2026-05-15.md). Impacto: +1pp na nota memoria-senior (86 → 87, roadmap pra 98). ADR 0053 menciona ~74% cache_read no gasto Wagner — meta 85%+ pós cache_control explícito.

## Por que existe

Anthropic Messages API ([docs](https://docs.anthropic.com/en/docs/build-with-claude/prompt-caching)) reduz custo até 90% e latência até 85% em prompts que reusam contexto — MAS requer flag `"cache_control": {"type": "ephemeral"}` explícita nos blocos cacheáveis. Sem marker, todo request paga input regular full.

oimpresso usa `laravel/ai` ^0.6.3 + driver custom `LaravelAiSdkDriver` (ADR 0035 + ADR 0048). Antes desta entrega:

- `BriefingAgent` / `ChatCopilotoAgent` retornavam `instructions()` como **string** → laravel/ai monta `$body['system'] = $instructions` (string), Anthropic NÃO sabe o que cachear
- Resultado: pagava input regular em system prompt + ContextoNegocio + memoria recall a cada request, mesmo quando idênticos

## Como funciona

Anthropic faz **prefix matching** no prompt completo (`tools → system → messages`, nesta ordem) até o último bloco marcado com `cache_control`. Hit → tokens viram `cache_read_input_tokens` (0.1x preço regular). Miss → tokens viram `cache_creation_input_tokens` (1.25x preço regular; investimento).

### 4 blocos cacheáveis no oimpresso

| Bloco | Conteúdo | Estável? | TTL recomendado |
|---|---|---|---|
| **system** (persona base) | "Você é o Copiloto do oimpresso..." (texto fixo) | sim, raramente muda | `ephemeral` (5min) |
| **business_context** | `ContextoNegocio` formatado (empresa, faturamento 90d, metas) | dentro da sessão sim | `ephemeral` |
| **tool_definitions** | Lista de tools registradas (ex: BriefDiarioAgent → 5 tools) | sim | `ephemeral` |
| **user_message** | Mensagem nova do user | NÃO (sempre novo) | NÃO cachear |

### Pattern canônico laravel/ai (sem fork)

Agent implementa contract `HasProviderOptions`:

```php
class ChatCopilotoAgent implements Agent, HasProviderOptions
{
    public function providerOptions(Lab|string $provider): array
    {
        if ($provider !== Lab::Anthropic) return [];

        return [
            'system' => [
                ['type' => 'text', 'text' => $personaBase],
                ['type' => 'text', 'text' => $businessContext, 'cache_control' => ['type' => 'ephemeral']],
            ],
        ];
    }
}
```

`Laravel\Ai\Gateway\Anthropic\Concerns\BuildsTextRequests::buildTextRequestBody` faz `array_merge($body, $providerOptions)` → sobrescreve `$body['system']` (string default) pela versão blocks.

**Cache_control sempre no ÚLTIMO bloco** — Anthropic cacheia prefixo ATÉ ele. Marcar último cobre persona + ctx + memoria num único hit, gastando 1 dos 4 breakpoints disponíveis.

## Como verificar cache hit em logs

Log channel `copiloto-ai` (config/logging.php) recebe evento estruturado:

```bash
tail -f storage/logs/copiloto-ai.log | grep prompt_cache_event
```

Shape:

```json
{
  "agent": "ChatCopilotoAgent",
  "business_id": 1,
  "conversa_id": 42,
  "cache_read_tokens": 1245,
  "cache_creation_tokens": 0,
  "regular_input_tokens": 87,
  "cache_hit_rate": 0.9347,
  "event_type": "hit"
}
```

Agregação simples (cron daily):

```sql
-- Pseudo (se logs vão pra DB via grafana/loki):
SELECT
  AVG(cache_hit_rate) as hit_rate_avg,
  SUM(cache_read_tokens) as total_cache_read,
  SUM(cache_creation_tokens) as total_cache_write,
  SUM(regular_input_tokens) as total_regular
FROM copiloto_ai_logs
WHERE event = 'prompt_cache_event'
  AND created_at >= NOW() - INTERVAL 7 DAY
GROUP BY business_id;
```

## Custos esperados

Base ADR 0053: ~74% cache_read atual = R$ [redacted Tier 0]k/dia (de R$ [redacted Tier 0]k médio). Esta entrega ataca os 26% restantes que pagavam input regular em system + ctx.

**Estimativa conservadora (validar 2 sem):**
- Meta: 85%+ cache_read hit rate
- Economia mensal: R$ [redacted Tier 0]k × 0.11 (delta) × 30d × 0.9 (desconto cache) = ~R$ [redacted Tier 0]k/mês

**Pricing Anthropic 2026:**
- Input regular: 1.0x
- Cache write (5min TTL): 1.25x — investimento, paga ≥2 hits
- Cache write (1h TTL): 2.0x — paga ≥3 hits
- Cache read: 0.10x — 90% desconto

Sonnet/Opus exigem ≥1024 tokens pra cache; Haiku ≥2048. Threshold conservador no código: 4096 chars (env `COPILOTO_PROMPT_CACHE_MIN_CHARS`, configurável).

## Troubleshooting

### Cache hit rate < 70%

1. Confirmar `COPILOTO_PROMPT_CACHE_ENABLED=true` em `.env` prod
2. Conferir `Modules/Jana/Ai/Agents/<Agent>.php` implementa `HasProviderOptions`
3. Inspecionar log `prompt_cache_event`:
   - Só `event_type=write`? → cache miss constante; ContextoNegocio muda demais por request (timestamp dinâmico injetado?) → estabilizar `formatarContextoNegocio()`
   - Zero eventos? → provider não é Anthropic (default `ai.default=openai`) OU SDK retorna usage sem campos cache → checar versão `laravel/ai >= 0.6.3`
4. ContextoNegocio inclui `now()->toDateString()` — muda de dia em dia (esperado). Janela < 5min ainda hita.

### Erro "system must be an array"

laravel/ai SDK versão < 0.6.3 não tinha suporte a system blocks. Conferir `composer.lock`.

### Cache hit muito caro (cache_write > cache_read)

Indica que conteúdo marcado MUDA antes da TTL expirar. Investigar `formatarContextoNegocio()`:
- Métricas dinâmicas (faturamento agora()) podem virar entre requests
- Solução: arredondar valores numéricos pra centavos OU separar contexto super-volátil em bloco user-message (não cacheado)

### Kill-switch emergencial

```bash
# .env prod
COPILOTO_PROMPT_CACHE_ENABLED=false
php artisan config:cache
```

Driver volta ao caminho default (`system` como string), zero cache markers. Sem impacto funcional além de custo voltar ao baseline.

## Evolução futura

1. **TTL 1h pra system prompts ultra-estáveis** — `BriefingAgent` roda 1x/dia, hoje ephemeral; subir pra `1h` paga write 2x mas hits diários consolidam (avaliar pós-validação).
2. **Tool definitions cacheadas** — `BriefDiarioAgent` declara 5 tools (~800 tokens). Quando suporte a `tools[].cache_control` for adicionado pelo laravel/ai (PR upstream ou patch local), aplicar.
3. **Métrica Langfuse** — propagar `cache_hit_rate` pro Langfuse v3 trace (ADR 0132) — UI de observability já existe, basta adicionar campo no `recordGeneration`.
4. **Auto-detect threshold** — hoje 4096 chars fixo. Quando `tiktoken` PHP virar dep, calcular tokens reais e comparar com `1024 Sonnet / 2048 Haiku` de modo dinâmico.

## Arquivos canon

- [`Modules/Jana/Ai/Cache/PromptCacheConfig.php`](../../../Modules/Jana/Ai/Cache/PromptCacheConfig.php) — source-of-truth
- [`Modules/Jana/Ai/Agents/ChatCopilotoAgent.php`](../../../Modules/Jana/Ai/Agents/ChatCopilotoAgent.php) — implementa `HasProviderOptions`
- [`Modules/Jana/Ai/Agents/BriefingAgent.php`](../../../Modules/Jana/Ai/Agents/BriefingAgent.php) — idem
- [`Modules/Jana/Services/Ai/LaravelAiSdkDriver.php`](../../../Modules/Jana/Services/Ai/LaravelAiSdkDriver.php) — observabilidade `logPromptCacheUsage`
- [`Modules/Jana/Tests/Feature/Ai/PromptCacheConfigTest.php`](../../../Modules/Jana/Tests/Feature/Ai/PromptCacheConfigTest.php) — 9 invariantes

## Referências

- ADR 0035 — stack AI canônica (laravel/ai ^0.6.3)
- ADR 0048 — driver custom (Vizra rejeitada)
- ADR 0053 — MCP server + métricas cache_read atual
- Anthropic Prompt Caching docs (PT-EN, atualizado 2026-Q1)
- laravel/ai PR #166 — `HasProviderOptions` contract
