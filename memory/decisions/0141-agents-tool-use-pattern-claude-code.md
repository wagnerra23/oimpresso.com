---
slug: 0141-agents-tool-use-pattern-claude-code
number: 141
title: Agents IA com tool use loop (pattern "Claude Code") — Camada B v2
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by:
  - W
decided_at: '2026-05-11'
quarter: 2026-Q2
tags: {}
supersedes: []
related:
  - 0035-stack-ai-canonica-wagner-2026-04-26
  - 0048-framework-agentes-laravel-ai-vizra-rejeitada
  - 0140-jana-pro-produto-comercial-saas
pii: false
---
# ADR 0141 — Agents IA com tool use loop (pattern "Claude Code") — Camada B v2

**Status:** Aceito · Estende [ADR 0048](0048-framework-agentes-laravel-ai-vizra-rejeitada.md) · Define pattern oficial pra Camada B
**Data:** 2026-05-11
**Origem:** Wagner — validação do `BriefDiarioService::snapshot()` (US-COPI-201) em prod biz=1 + decisão de não voltar pra Vizra ADK + intuição de que agents devem "pensar como Claude Code"

---

## Contexto

[ADR 0048](0048-framework-agentes-laravel-ai-vizra-rejeitada.md) rejeitou Vizra ADK e absorveu Camada B na Camada A (`laravel/ai`). Os agents atuais (`BriefingAgent`, `ChatCopilotoAgent`, `HealthNarratorAgent`, `SinteseSemanalAgent`, `SugestoesMetasAgent`, `ExtrairFatosAgent`) são **single-shot**: recebem prompt completo já com dados pré-montados em PHP, devolvem string.

Esse pattern tem 2 limitações que viraram bloqueio pra **JANA Pro** ([ADR 0140](0140-jana-pro-produto-comercial-saas.md)):

1. **Toda lógica de "qual dado buscar" fica no PHP**, não no LLM. Pra brief diário com 5 sources, o PHP monta as 5 strings e injeta tudo no prompt — LLM só formata. Não há **decisão dinâmica** de qual fonte aprofundar.
2. **Não consegue iterar**. Se LLM detecta "5 NF-e rejeitadas, top motivo cstat 781", não há mecanismo pra "agora me dá os documentos individuais pra eu citar". Tudo precisa estar no contexto inicial.

Wagner formulou a direção: **"o pensamento seja o Claude Code"** — agents devem pensar agentic: receber tools, escolher quais chamar, iterar até montar resposta. Igual a mim (Claude Code) que recebo `Read`, `Bash`, `Grep` e decido a sequência.

`laravel/ai` ^0.6.3 já suporta isso nativamente via:
- `Laravel\Ai\Contracts\HasTools` — interface que agent implementa pra declarar tools
- `Laravel\Ai\Contracts\Tool` — cada tool é classe PHP com `description()`, `handle(Request)`, `schema(JsonSchema)`
- Loop tool_use/tool_result é executado pelo SDK **automaticamente** quando agent declara HasTools

---

## Decisão

**Toda nova classe agente do `Modules/Jana/Ai/Agents/` que precisar consultar dados dinâmicos DEVE implementar `Laravel\Ai\Contracts\HasTools` e expor tools.** Agents single-shot (sem tools) ficam permitidos pra casos onde:

- O contexto cabe em ~2k tokens E
- Não há decisão sobre qual fonte aprofundar E
- O agente é puramente formatador (ex: `BriefingAgent` legacy)

Pattern obrigatório pra agents com tool use:

```php
namespace Modules\Jana\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class BriefDiarioAgent implements Agent, HasTools
{
    use Promptable;

    public function __construct(public readonly int $businessId) {}

    public function instructions(): string
    {
        return "Você é Jana Pro. Gere brief executivo BR em markdown ~300 palavras
        usando as tools disponíveis. Foque em 3 sinais críticos. Nunca invente.";
    }

    public function tools(): iterable
    {
        return [
            new Tools\VendasPeriodoTool($this->businessId),
            new Tools\InadimplenciaTool($this->businessId),
            new Tools\TicketsTopTool($this->businessId),
            new Tools\NfeStatusTool($this->businessId),
            new Tools\OportunidadesTool($this->businessId),
        ];
    }
}
```

Cada `Tool`:
- **Recebe `$businessId` no constructor** (Tier 0 IRREVOGÁVEL — [ADR 0093](0093-multi-tenant-isolation-tier-0.md))
- **Nunca lê `$businessId` do prompt LLM** (defesa contra prompt injection: LLM não pode escolher business)
- **Retorna JSON string** pra o LLM (mais compacto que prosa)
- **Graceful degradation**: se dado não existe, retorna `"sem dados"` — não vaza exception

---

## Justificativa

**1. Cognição "estilo Claude Code" é o estado-da-arte:**
- Anthropic Claude Code, OpenAI Codex, Cursor agent mode — todos usam tool use loop
- LLM decide o que precisa olhar, igual humano em research
- Pattern probadamente eficaz pra problemas multi-step

**2. Reduz prompt size:**
- BriefingAgent atual injeta 90d faturamento + metas + módulos = ~1.5k tokens no system
- Agent com tools só injeta system + lista de tools (~300 tokens). LLM pede o que precisa.
- Em conversas longas (Sprint C JANA Pro chat session), diferença vira 50%+ de redução

**3. Compat L13.6/PHP 8.4 nativa:**
- `laravel/ai` v0.6.3 é o pacote oficial Anthropic+Laravel
- HasTools/Tool são parte estável da API (não experimental)
- Zero dependência adicional além do que já está em `composer.json`

**4. Testável Pest sem mock framework:**
- Cada Tool é PHP class normal — testa com Pest direto
- Agent.prompt() pode usar `Ai::fake()` (gateway fake do laravel/ai) pra simular respostas LLM
- Não precisa de Vizra mocks ou abstrações exóticas

**5. Tier 0 mecanicamente garantido:**
- `$businessId` no constructor da Tool nunca vem do LLM
- Mesmo se LLM "alucinar" `business_id: 99`, a tool ignora — usa o do constructor
- Prompt injection via mensagem do usuário não consegue cross-tenant

---

## Consequências

**Positivas:**
- Sprint A US-COPI-202 (BriefDiarioAgent) destrava com pattern reusável
- Sprint B+C JANA Pro chat session ganha agents agentic nativos
- Skill `ticket-triage` (v0.1.0, projectSettings) vira agent operacional sem refactor
- Reduz tokens em conversas longas → reduz custo LLM ~30-50% (estimativa)

**Negativas / Trade-offs:**
- Mais arquivos por agent (5 tools = 5 PHP files vs 1 prompt monolítico)
- Latência primeira resposta maior (LLM faz ≥1 tool call antes de responder) — ~+500ms
  - **Mitigação:** pre-warm cache em `BriefDiarioJob` cron 8h BRT
- Custo extra de tokens "tool result" — mas compensado por system prompt menor
- Debugging exige logs de tool_call/tool_result — adicionar listener `InvokingTool`/`ToolInvoked` em ADR seguinte

**Não-decididos (próximas ADRs se virar problema):**
- Limite máximo de iterações tool loop (default `laravel/ai` desconhecido — investigar antes de prod)
- Custo cap por agent invocation (Sprint B JANA Pro precisa pricing-aware)

---

## Alternativas descartadas

**A) Continuar com agents single-shot, montar JSON gigante no system prompt**
- Não escala pra JANA Pro chat session
- Prompt fica > 4k tokens facilmente → custo + slow first response
- **Rejeitado:** Wagner formulou direção contrária

**B) Voltar pra Vizra ADK agora que tem dashboard etc**
- Vizra ainda quebrado no Laravel 13.6 (sem release oficial em 6 meses)
- ADR 0048 já rejeitou. Reabrir = re-trabalhar trauma técnico
- **Rejeitado:** Wagner explícito 2026-05-11 — "não estou usando mais"

**C) Implementar tool use manual (loop PHP custom)**
- Reimplementaria o que `laravel/ai` HasTools já faz
- Risco de divergência da API Anthropic em evolução
- **Rejeitado:** YAGNI — usar pacote oficial

**D) Subprocess Claude Agent SDK (TypeScript) chamando Anthropic**
- Adiciona Node.js runtime em prod (Hostinger não tem isso)
- Complexidade IPC + governança 2 stacks
- **Rejeitado:** ADR 0062 separação runtime + dev solo

---

## Implementação obrigatória

Pra cada novo agente com tools:

1. **Classe em `Modules/Jana/Ai/Agents/<Nome>Agent.php`** — implementa `Agent` + `HasTools`
2. **Tools em `Modules/Jana/Ai/Tools/<Nome>/<Tool>Tool.php`** — subpasta por agent
3. **Pest test** em `Modules/Jana/Tests/Feature/Ai/<Nome>AgentTest.php` cobrindo:
   - Tools são chamáveis em isolation (sem LLM)
   - Tools respeitam Tier 0 (cross-tenant biz=99 → "sem dados" ou exception)
   - Agent gera resposta não-vazia com `Ai::fake()`
   - Sem PII vazada no output (CPF/CNPJ não aparece)
4. **Comentário PHPDoc no Agent** linkando ADR 0141 + 0048 + 0093

---

## Triggers para reavaliar

Reabrir essa decisão se:

1. `laravel/ai` v1.0+ remover ou breakar `HasTools`/`Tool` interfaces
2. Custo LLM por brief > R$ [redacted Tier 0] (target Sprint B = R$ [redacted Tier 0])
3. Latência primeira resposta brief > 8s (target = ≤5s)
4. Surgir framework PHP com tool use **+ tracing/eval nativos** (laravel/ai ainda não tem)

---

## Referências

- [ADR 0035](0035-stack-ai-canonica-wagner-2026-04-26.md) — Stack canônica IA (3 camadas A/B/C)
- [ADR 0048](0048-framework-agentes-laravel-ai-vizra-rejeitada.md) — Vizra rejeitada, laravel/ai oficial
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0140](0140-jana-pro-produto-comercial-saas.md) — JANA Pro produto SaaS
- `vendor/laravel/ai/src/Contracts/HasTools.php` — interface canônica
- `vendor/laravel/ai/src/Contracts/Tool.php` — interface canônica
- Anthropic tool use docs: https://docs.claude.com/en/docs/build-with-claude/tool-use
- Skill `ticket-triage` v0.1.0 (`.claude/skills/ticket-triage/SKILL.md`) — primeiro agente "estilo Claude Code" especificado, vira operacional via este pattern
