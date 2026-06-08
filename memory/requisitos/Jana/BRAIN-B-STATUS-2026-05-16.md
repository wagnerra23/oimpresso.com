# Brain B — Status Diagnóstico (2026-05-16)

> Análise documental zero-custo (sem chamada API IA). CYCLE-06 G3 prep demo Jana V2.

## Sumário executivo

**Status:** Brain B configurado em código mas **0% uso 24h** (0/50 cap). Causa-raiz mais provável: **(c) cliente piloto ainda não chegou em fluxo que dispara Brain B** + **(b) ADS roteador dormente (ADR 0105)** — chave Anthropic provavelmente set (caching live em `ChatCopilotoAgent`), mas demo precisa trigger manual.

## Estado atual da configuração

### config/ai.php (linha 11, 42-47)

- **`default` provider:** `openai` (não Anthropic — então Brain A path padrão = GPT)
- **Provider Anthropic registrado:** ✅ sim
  ```php
  'anthropic' => [
      'driver' => 'anthropic',
      'key' => env('ANTHROPIC_API_KEY'),
      'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
  ],
  ```
- **Modelo Anthropic alvo NÃO está em `config/ai.php`** — diferente de OpenAI que tem block `models.text.default/cheapest/smartest`. Anthropic só tem `key + url`. Modelo é escolhido por agent/runtime via `laravel/ai` SDK (provavelmente hardcoded ou via providerOptions per-Agent).
- **`config/jana.php`:** não existe (não há config dedicada Jana — convenções vivem em `Modules/Jana/Ai/`).

### .env.example da raiz

- **Não encontrado `.env.example` na raiz do repo** (Glob retornou só sub-paths: `Modules/Whatsapp/daemon-node/`, `docker/*`, `scripts/dual-brain/`, `infra/proxmox/`, `scripts/legacy-migration/`).
- **`ANTHROPIC_API_KEY` referenciada em `config/ai.php`** — vive no `.env` real (que é git-ignored). Estado real só inspecionável no servidor.

### Agents Brain B existentes (Modules/Jana/Ai/Agents/)

9 agents disponíveis:
1. `BriefDiarioAgent.php` (brief diário automatizado — pode rodar Anthropic)
2. `BriefingAgent.php` — usa `Lab::Anthropic` (linha 84: `if ($providerKey !== Lab::Anthropic->value)`)
3. `ChatCopilotoAgent.php` — usa `Lab::Anthropic` (linha 193) + prompt caching live (ADR-PROMPT-CACHING)
4. `ExtrairFatosAgent.php`
5. `HealthNarratorAgent.php` (cita `custo_brain_b_24h` como health check)
6. `KbAnswerAgent.php`
7. `SinteseSemanalAgent.php`
8. `SugestoesMetasAgent.php`
9. `WeeklyDigestAgent.php`

**2 confirmados Anthropic-aware:** `BriefingAgent` + `ChatCopilotoAgent` (têm `providerOptions(Lab|string)` retornando blocks com `cache_control` quando `Lab::Anthropic`).

### Métrica Brain B (HealthSnapshotService.php:108-128)

Brain B usage é medido da tabela **`jana_mensagens`** (24h window):
- Campos lidos: `tokens_in`, `tokens_out`, `created_at`
- Pricing assumido: `$0.15/1M in + $0.60/1M out` (USD) × `USD_TO_BRL = 5.0`
- **Important:** o cálculo NÃO filtra por provider — todo registro em `jana_mensagens` conta (qualquer Agent que persistiu mensagem).

**Plano de query (NÃO executar):**
```sql
SELECT MAX(created_at) AS last_invocation,
       COUNT(*) AS msgs_24h,
       SUM(tokens_in) AS tin,
       SUM(tokens_out) AS tout
FROM jana_mensagens
WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR);
```

## Três razões possíveis pra 0% uso

### (a) ANTHROPIC_API_KEY ausente no .env de produção

- **Probabilidade:** BAIXA-MÉDIA
- **Evidência contra:** prompt caching está livre em `ChatCopilotoAgent` (sessão 2026-05-09+ commits) — implementação ativa sugere key configurada em algum momento.
- **Evidência a favor:** brief diário relatou "0% (0/50)" — pode indicar fail-silent (Agent cai pra OpenAI default ou retorna mock).
- **Como verificar:** `ssh hostinger 'grep ANTHROPIC_API_KEY /home/.../.env'` (no servidor, não local).

### (b) ADS Roteador dormente bloqueando ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))

- **Probabilidade:** MÉDIA
- **Evidência:** ADR 0105 + ADR 0035 Dual-Brain — `decide(domain, intent, payload)` só ativa Brain B quando policy `REQUIRE_BRAIN_B`. Estado atual: Tier A dormente até S5 (~jul/2026), skill `ads-route` ainda não força routing.
- **Resultado:** Brain A (OpenAI cheaper) responde 100% dos turnos por enquanto.

### (c) Cliente piloto não chegou em fluxo que invoca Brain B

- **Probabilidade:** ALTA
- **Evidência:** Brain B só é exercitado por agents Anthropic-aware (`ChatCopilotoAgent`, `BriefingAgent`). Demo Jana V2 precisa fluxo conversacional real OU job cron. ROTA LIVRE (biz=4) é o único tenant com volume — se Larissa não conversou via Chat copiloto nas últimas 24h, contador fica zerado.

## Recomendação para Wagner (demo CYCLE-06 G3)

1. **Confirmar `ANTHROPIC_API_KEY` no `.env` Hostinger** (1 comando SSH).
2. **NÃO ativar ADS routing pra demo** — usa caminho direto via `ChatCopilotoAgent` (já Anthropic-aware).
3. **Trigger manual em homolog/local** via tinker (ver `BRAIN-B-DEMO-CHECKLIST.md`) — 1 chamada confirma stack ponta-a-ponta.
4. **Definir modelo demo:** `claude-haiku-4-5-20251001` (Haiku é ~10× mais barato que Sonnet/Opus pra demo, dentro do cap diário 8 chamadas).
5. **Adicionar `AI_ANTHROPIC_TEXT_DEFAULT` em `config/ai.php`** (ENV-driven, simétrico ao bloco OpenAI) — gap arquitetural identificado nesta auditoria. Cria PR separado pós-demo.

## Anti-pattern a evitar

NÃO inflar consumo Brain B artificialmente pra "mostrar funcionando" — viola ADR 0105 (cliente como sinal). Demo = 1 chamada manual real, não fake traffic.

## Referências

- ADR 0035 Dual-Brain stack
- ADR 0105 Cliente como sinal
- `Modules/Jana/Ai/Agents/ChatCopilotoAgent.php` linha 184-214 (prompt caching Anthropic)
- `Modules/Jana/Services/HealthSnapshotService.php` linha 108-128 (métrica brain_b)
