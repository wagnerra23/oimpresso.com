---
slug: 0245-jana-advisor-modo-consultor-clarify
number: 245
title: "Jana Modo Consultor (Advisor) — clarify reativo (cascata Decidir→Clarificar→Responder)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-06-02"
proposed_at: "2026-06-02"
module: jana
quarter: 2026-Q2
supersedes: []
related:
  - 0035-stack-ai-canonica-wagner-2026-04-26
  - 0091-daily-brief
  - 0141-agents-tool-use-pattern-claude-code
  - 0093-multi-tenant-isolation-tier-0
  - 0047-wagner-solo-sprint-memoria-agente
---

# ADR 0245 — Jana "Modo Consultor" (Advisor): clarify reativo

> Promove a proposta `memory/decisions/proposals/jana-advisor-modo-consultor.md` (§10.4),
> mergeada em [PR #2134](https://github.com/wagnerra23/oimpresso.com/pull/2134), a decisão canônica.
> **Metade A** desta ADR. A **Metade B** (próxima-melhor-pergunta proativa) entra em ADR/PR próprio.

## Contexto

O chat da Jana respondia toda mensagem direto: **chutava** quando a intenção era ambígua e às
vezes **perguntava** quando era só falta de dado (que ele busca sozinho via tool/RAG). Confundir
**ambiguidade-de-intenção** com **falta-de-dado** é o erro nº1 dos LLMs.

Estado-da-arte 2025 que ancora a decisão:
- **Active Task Disambiguation** (ICLR 2025 Spotlight): qualidade vem de **fazer perguntas melhores**,
  não só dar respostas melhores — a pergunta de **maior ganho de informação**.
- **INTENT-SIM** (NAACL 2025): **decoupla** ambiguidade-de-intenção (→ perguntar) de falta-de-dado
  (→ buscar). Cascata p/ latência.

Princípio: subir o raciocínio é **andaime** (scaffold), **não** troca de modelo — a escolha de
scaffold move desempenho em até ~30pp no mesmo modelo.

## Decisão

Introduzir a cascata **Decidir → Clarificar → Responder** no chat da Jana, como capacidade
**aditiva** que **estende** (não recria) os Agents/driver/`MemoriaContrato`:

1. **Decidir (barato):** heurística local zero-LLM (`ClarifyCascadeService::pareceCinza`) resolve
   ~80% direto → responde. Default conservador = responder.
2. **Clarificar (caro, só no ~20% "cinza"):** `ClarificadorAgent` (5º agente, `HasStructuredOutput`,
   **roteamento de modelo seletivo difícil→frontier** via config) decide `claro | falta_dado | ambiguo`
   e, se ambíguo, devolve a pergunta de **maior ganho de informação**.
3. **Responder:** com a intenção resolvida, segue o pipeline normal (`ChatCopilotoAgent`).

Garantias duras (Tier 0):
- **Default-OFF** (`copiloto.clarify.enabled`) — com a flag OFF o pipeline de chat é
  **byte-idêntico** ao legado (mesma postura de `contextual_retrieval` / `peso_real`).
- **Fail-open:** qualquer erro → responde (a cascata nunca quebra o chat).
- **Honestidade:** não inventa pergunta; só clarifica quando o disambiguador devolve uma de alto valor.
- **Anti-loop:** não pergunta 2× seguidas (marcador TTL em cache).
- **PII Tier 0 (ADR 0093):** histórico/contexto vão PII-redigidos pro disambiguador.
- **Medição:** log `copiloto-ai` → evento `clarify_event` (gray-hit, taxa de clarify, false-clarify).

Roteamento de modelo: `copiloto.clarify.model` (default `gpt-4o`, provider `openai` já configurado —
mais forte que o `gpt-4o-mini` do chat, mas só dispara no cinza). Toggle e modelo são **env-driven**
(`JANA_CLARIFY_ENABLED` / `JANA_CLARIFY_MODEL`) para controle por ambiente (homolog liga, prod espera).

## Consequências

**Positivas:**
- Conserta o pior hábito (chutar no ambíguo) sem trocar de modelo — andaime barato.
- Menos re-trabalho do gestor; menos resposta-errada-confiante.
- Base p/ a Metade B (a IA passa a **pautar**, não só responder).

**Custos/riscos:**
- +1 chamada LLM (frontier) no ~20% cinza — mitigado pela cascata (heurística resolve ~80% free).
- Risco de **false-clarify** (perguntar no óbvio) — mitigado por heurística conservadora +
  gate de confiança + medição `clarify_event`.
- Métrica **pergunta→ação** (sinal de valor real) precisa de hook no frontend — pendência aberta
  (ver RUNBOOK).

**Rollout:** ligar em **homolog** primeiro (`JANA_CLARIFY_ENABLED=true`, modelo `gpt-4o`), medir
`clarify_event` 1-2 semanas, então decidir prod. Sem migration — flag reverte sem rollback.

## Referências

- PR #2134 (Metade A) · proposta `proposals/jana-advisor-modo-consultor.md`
- RUNBOOK `memory/requisitos/Jana/RUNBOOK-jana-advisor-clarify.md`
- Active Task Disambiguation (ICLR 2025 Spotlight) · INTENT-SIM (NAACL 2025)
