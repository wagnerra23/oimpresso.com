---
slug: 0167-errata-0130-indice-handoff-historico-longo
number: 167
title: "Errata ADR 0130 — Índice de handoff mantém histórico longo (não trunca 5)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-17"
accepted_at: 2026-05-17
review_at: 2026-11-17
module: Governance
quarter: 2026-Q2
tags: [errata, handoff, memoria, governanca, indice, append-only]
supersedes: []
supersedes_partially: []
superseded_by: []
amends: [0130]
related: [0061-conhecimento-canonico-git-mcp-zero-automem, 0070-jira-style-task-management-current-md-removed, 0094-constituicao-v2-7-camadas-8-principios, 0119-paralelismo-sessoes-whats-active-tier-1, 0130-handoff-append-only-mcp-first, 0131-tiering-memoria-canonico-local-segredo]
pii: false
review_triggers:
  - "Diretório `memory/handoffs/` ultrapassar 200 arquivos (vs gate antigo 100 em ADR 0130) — avaliar arquivamento `memory/handoffs/_archive/YYYY/`"
  - "Índice `memory/08-handoff.md` ultrapassar 300 linhas — avaliar agrupamento por mês/quarter"
  - "Tool MCP `handoff-list` ou `handoff-fetch` criadas — índice pode encolher pra stub apontando pra tools (cenário review_trigger original da ADR 0130)"
  - "Brief-fetch passar a expor histórico de handoffs nativamente — reavaliar necessidade do índice longo"
---

# ADR 0167 — Errata ADR 0130: Índice mantém histórico longo

## Status

**Accepted** — errata complementar à [ADR 0130](0130-handoff-append-only-mcp-first.md). NÃO substitui (append-only Tier 0 IRREVOGÁVEL — [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) §3 + proibições.md §Memória/governança). Formaliza convenção que já operava de facto.

## 1. Contexto — drift detectado 2026-05-17

[ADR 0130](0130-handoff-append-only-mcp-first.md) §2 declarou textualmente:

> *"Índice **pode ser editado** (é o único arquivo de governança que muda no fluxo normal). Mas só pra: adicionar entrada nova no topo, truncar lista pros 5 mais recentes (resto fica em `memory/handoffs/` acessível por glob)"*

Auditoria 2026-05-17 (sessão `sharp-shannon-c7ae87` consolidando checklist pós-merge em [memory/reference/checklist-pos-merge.md](../reference/checklist-pos-merge.md)) detectou que [`memory/08-handoff.md`](../08-handoff.md) tem ~35 entradas, não 5.

Convenção evoluiu de facto entre 2026-05-10 (ADR 0130 aceita) e 2026-05-17. Wagner, consultado: **"não pode truncar"**.

## 2. Decisão

§2 da ADR 0130 fica emendado:

| Item | ADR 0130 original | ADR 0167 errata |
|---|---|---|
| Truncamento do índice | Pros 5 mais recentes | **Sem truncamento** — histórico longo mantido |
| Tamanho esperado do índice | ~30 linhas | Crescente (~35 linhas em 2026-05-17; review trigger em 300 linhas) |
| O que o índice é | Lista enxuta + ponteiros | Mapa narrativo cronológico do projeto + ponteiros |
| Manutenção | Adiciona no topo + truncar 5º | Adiciona no topo, nada mais |

Demais regras da ADR 0130 (handoffs append-only em `memory/handoffs/YYYY-MM-DD-HHMM-<slug>.md`, MCP-first OBRIGATÓRIO antes do Write, seção `## Estado MCP no momento do fechamento` como prova) **continuam intactas**.

## 3. Justificativa

3 razões observadas em prática 2026-05-10 a 2026-05-17:

1. **Mapa narrativo serve descoberta retroativa** — dev novo entrante (Felipe/Maiara/Eliana/Luiz no time MCP) varre índice e vê evolução do projeto sem precisar `ls memory/handoffs/`. Linhas-resumo no índice condensam contexto que `brief-fetch` (Tier A) não cobre (foco brief é estado VIVO, não histórico interpretativo).
2. **Custo trivial** — índice é só ponteiros (35 linhas atuais ≈ 7KB). Handoffs reais ficam em `memory/handoffs/` (350+ KB). Truncar pros 5 não economiza nada material.
3. **Brief-fetch + tools MCP cobrem estado vivo** — quem quer "o que tá acontecendo agora" usa `brief-fetch`/`my-work`/`cycles-active`, não índice de handoff. Índice serve propósito ortogonal: cronologia interpretativa append-only.

## 4. Implicações operacionais

### O que muda

- Skill `memory-sync` SKILL.md §"Handoff append-only" linha *"truncar 5º item se passou"* → **"adicionar entrada nova no topo, nada mais"**
- Doc `memory/how-trabalhar.md` §"Ao terminar uma sessão" passo 2 (se mencionar truncamento) → idem
- Doc `memory/reference/checklist-pos-merge.md` §5c (criado no mesmo PR desta errata) já alinhado com esta decisão

### O que NÃO muda

- ADR 0130 §1 (diretório `memory/handoffs/` append-only) — IRREVOGÁVEL
- ADR 0130 §3 (MCP-first checklist obrigatório antes do Write) — IRREVOGÁVEL
- ADR 0130 §6 (hook bloqueador dormente — ativa se reincidência overwrite) — mantido
- Conteúdo do índice (linhas existentes não são editadas, só adicionadas no topo)

## 5. Limite operacional (review_trigger)

Quando índice passar de **300 linhas** (~50-60 entradas) considerar:

- (a) Agrupar por quarter: `## Q2-2026` / `## Q3-2026` headers separadores
- (b) Mover entradas >6 meses pra `memory/08-handoff-archive.md` (continuando append-only)
- (c) Criar tool MCP `handoff-list --since/--until` que reduz índice a stub

Nenhuma das 3 opções é pré-decidida — depende do estado do projeto em 2026-11-17 (review formal desta errata).

## 6. Append-only verificação

Esta ADR:
- ✅ NÃO edita ADR 0130 (append-only Tier 0 IRREVOGÁVEL)
- ✅ Tem `amends: [0130]` declarado no frontmatter
- ✅ ADR 0130 segue `accepted` — esta errata não muda status
- ✅ Cadeia de auditoria: leitor da 0130 → vê `related: [...0167...]` quando essa ref for adicionada (não-bloqueante pra mergeer esta errata)

## 7. Referências

- [ADR 0130](0130-handoff-append-only-mcp-first.md) — original handoff append-only + MCP-first (emendada por esta errata)
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 mãe (princípio 7 transparência fundamenta histórico longo)
- [ADR 0061](0061-conhecimento-canonico-git-mcp-zero-automem.md) — zero auto-mem privada (histórico canon vai pro git, time MCP enxerga)
- [ADR 0070](0070-jira-style-task-management-current-md-removed.md) — tasks Jira-style (handoff narrativo é complemento ortogonal)
- [memory/reference/checklist-pos-merge.md](../reference/checklist-pos-merge.md) — doc operacional onde esta decisão é citada no passo 5c
