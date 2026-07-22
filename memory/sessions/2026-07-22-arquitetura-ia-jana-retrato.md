---
date: "2026-07-22"
topic: "Retrato da arquitetura de IA da Jana (profissionalização): 3 notas medidas + veredito CONSOLIDAR"
type: session
status: done
authors: [C]
module: Jana
prs: [4674]
pergunta_origem: "estrutura profissional de IA — arquitetura (auditar e propor profissionalização da arquitetura de IA existente)"
related_adrs:
  - 0035-stack-ai-canonica-wagner-2026-04-26
  - 0048-framework-agentes-laravel-ai-vizra-rejeitada
  - 0052-memoria-jana-3-angulos-faturamento
  - 0053-mcp-server-governanca-como-produto
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0234-automation-registry-mcp
fontes_medidas:
  - memory/requisitos/Jana/IA-MATURITY-FICHA.md
  - memory/requisitos/Jana/AUDITORIA-IA-OS-2026-06-06.md
  - memory/governance/vital-signs.json
---

# Retrato — arquitetura de IA da Jana (2026-07-22)

> **Pedido [W]:** "estrutura profissional de IA" → refinado para **auditar e propor a
> profissionalização da arquitetura de IA que já existe** (`Modules/Jana`), não construir do zero.
>
> **Método:** ambiente de nuvem efêmero (sem `vendor/`/`.env`, sem tailscale pro CT 100), então
> a nota **não** foi recomputada aqui — seria parcial/enganosa (várias dimensões leem o MySQL prod).
> O retrato usa as **medições já commitadas no git** (recibos datados) + leitura estrutural de
> `Modules/Jana/` + 2 arquivos lidos a fundo (`Contracts/AiAdapter.php`, `Ai/Agents/ChatCopilotoAgent.php`).
> Disciplina LC-08: fato derivado = aponta pro dono + carrega recibo datado; não afirmação atemporal.

## Conclusão de cima

**Não é caso de "estruturar" — já é uma estrutura de IA de nível profissional/world-class.**
A auditoria sênior mais recente e code-verified (06-06) recomenda **CONSOLIDAR, não construir**.
O que falta pra 100 é majoritariamente **decisão de custo do [W]** (Brain B, OTel collector),
não engenharia. Teto pragmático honesto sem ligar Brain B: **~90-92%**.

## Três notas medidas — NÃO se misturam (cada uma mede coisa diferente)

| Nota | Mede | Valor | Recibo (data) | Fonte |
|---|---|---|---|---|
| **AI maturity** | capacidades IA da Jana vs mercado (12 dim) | 88/100 | 2026-05-16 ⚠️ **stale** | `IA-MATURITY-FICHA.md` |
| **IA-OS sistêmica** | pilha agêntica L1–L7 inteira | **79/100** (era 68 em 29/05) | **2026-06-06** ✅ code-verified | `AUDITORIA-IA-OS-2026-06-06.md` |
| **Screen grade** | 11 telas de UI da Jana (≠ arquitetura) | média 76 | 2026-07-06 | `vital-signs.json` |

Para "arquitetura", a régua correta é a **do meio (79/100)** — a mais fresca e a única verificada
contra código. A ficha de AI maturity (88/100) está **stale**: os 3 gaps P0 que ela cravava como
"próxima onda" (RAGAS CI gate, drift sentinel canary, Langfuse) foram **fechados** (manifesto
"FECHAR O LOOP DO IA-OS" 2026-05-29 + auditoria 06-06 confirmam em código).

## O que já é profissional (CONSOLIDAR)

- **Camadas A/B/C/D todas povoadas**, congeladas por ADR (`laravel/ai ^0.6.3` canônico; Vizra
  rejeitada formal — ADR 0035/0048).
  - **A** — `Contracts/AiAdapter.php` + `LaravelAiSdkDriver` (canônico) + `OpenAiDirectDriver`
    (fallback) + `Ai/Cache/PromptCacheConfig` (prompt-cache Anthropic).
  - **B** — 15 agents em `Ai/Agents/` sobre `laravel/ai` (`HasTools`/`HasProviderOptions`/`MaxSteps`).
  - **C** — `Services/Memoria/` + `Services/Retrieval/`: hierarquia de reranker completa
    (`Rrf/Bge/Llm/Null`), HyDE, contextual retrieval, semantic + negative cache, freshness
    pipeline, bi-temporal, profile distiller, summarizer.
  - **D** — 5 tools read-only Brief Diário + MCP server `mcp.oimpresso.com` (CT 100).
- **Cognição world-class:** gold-set anti-alucinação 100→115; 3 RAGAS gates em CI; drift sentinel;
  Langfuse deployado de verdade no CT 100 (10/05).
- **Governança + Tier 0 exemplares:** Automation Registry (ADR 0234), ADRs append-only,
  `business_id` global scope, PII redaction pré-LLM BR. Verificado no código: `ChatCopilotoAgent`
  faz tool-use com `business_id` do constructor (nunca do LLM), fail-safe zero-tool sem tenant,
  e prompt-cache Anthropic em blocos bem construído.

## Gaps de engenharia reais que sobram (pequenos)

1. 🔴 **Self-audit aponta pro arquivo errado** — `SystemAuditCommand::checkEvalCiGate()` procura
   `eval-recall-gate.yml` que **não existe**; os gates reais são `jana-ragas-gate.yml` /
   `ragas-gate.yml` / `jana-ragas-canary.yml`. Falso-negativo no próprio health-check da IA.
   Esforço ~0.1 d/pp. **Único fix de código Tier-0-limpo, baixo risco.** (Fonte: auditoria 06-06 §0.)
2. 🟡 **OTel collector não deployado** no CT 100 (Langfuse sim, collector não) → observability
   parcial em ~15 módulos. **Bloqueado por decisão de custo [W]**, não por código.

## Gaps que são decisão do [W], não dívida técnica

- **Brain B desligado** (Sonnet/Opus) — trade-off de custo consciente (ADR 0094 §2 tiered cost).
  Gap barato alternativo apontado pela auditoria: camada "Jana-as-assignee read-only" (Brain A
  `gpt-4o-mini`) entregaria efeito sem custo recorrente alto.
- **Multi-modal (voz)** — fase 2, só com sinal de cliente (ADR 0105).

## Próximo passo recomendado

CONSOLIDAR. Se houver energia de engenharia, o **fix #1** (self-audit false-negative) é o único
Tier-0-limpo pra pegar já — fecha um furo do próprio health-check da IA. Restante = decisão de
custo do [W], fora do escopo de engenharia.
