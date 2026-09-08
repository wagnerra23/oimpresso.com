# Session — design-arte: auditoria UX/UI do módulo Forja

> **Data:** 2026-09-01 · **Agente:** `design-arte` (auditoria estratégica UX/UI)
> **Mandato:** auditar/comparar o módulo Forja — NÃO tocar código, NÃO commitar, NÃO criar task. Entregar CAPTERRA-DESIGN-FICHA + nota 0-100 + gaps priorizados.
> **Base de leitura:** `origin/main` SHA `cb2e3be` (working tree local estava 82 commits atrás — ignorado). Todas as checagens via `git show origin/main:<path>`.
> **Entregável primário:** [`memory/requisitos/Forja/CAPTERRA-DESIGN-FICHA.md`](../requisitos/Forja/CAPTERRA-DESIGN-FICHA.md).

---

## Seção 1 — Research cliente (persona real, não inventada)

Persona **corrigida**: Forja é o "Linear/Jira interno" do time oimpresso (5 pessoas: [W]/[F]/[M]/[L]/[E]), não a Larissa/vestuário. Uso técnico/semi-técnico, desktop 1280–1440px, teclado-first desejado. Premissa distintiva já fixada no `CAPTERRA-INVENTARIO.md`: **um dos operadores é um agente** — tasks nascem via tool MCP e o autor some ao fim da sessão (Daily Brief #461: 519 sem dono, mais antiga 95d).

5 jobs-to-be-done (derivados dos charters/SPEC): (1) "o que fazer agora" · (2) mover trabalho no fluxo · (3) triar órfãs · (4) ver progresso · (5) auditar mudanças.

3 fricções com fonte (não impressão):
- **Duas casas** pro mesmo trabalho (cockpit `/forja` × telas nativas) — MOVIDO, não fundido; decisão [W] pendente (BRIEFING/SCOPE).
- **Cycle que não acontece** (`cycles-active` vazio 2026-08-04) — Burndown/Board degradam pra vazio.
- **Fila órfã sem gatilho** — superfície de Triage existe, falta o empurrão (INVENTARIO proposta 002).

Memória de design **creditada, não reinventada**: parti do `CAPTERRA-INVENTARIO.md` (auditoria funcional 2026-08-04, que já fechou o gap do ADR 0100) e adicionei o eixo **forma** (UX/UI) que faltava.

## Seção 2 — Pesquisa SOTA 2026

Players (corretos p/ ferramenta interna): Linear (top), Height, Shortcut, Jira Cloud (mainstream/pesado), Notion Projects, GitHub Projects, Plane.so (único self-host com premissa igual). Padrões 2026 relevantes:
- **Skeleton screens** = expectativa quando carga ∈ ~400ms–3s (janela exata do `Inertia::defer` da Forja).
- **Cmd+K** = padrão de SaaS >10 features — Forja **já tem** (global no AppShellV2).
- **Optimistic UI** como decisão de design — Board **já faz** (409+revert); resto do módulo não.

## Seção 3 — Comparação nas 15 dimensões

Medições diretas em `origin/main` (reprodutíveis):

- **Skeleton/Deferred:** `grep -c "Deferred|Skeleton|animate-pulse"` = **0 em 15/15 telas**. Defer é usado no backend, mas o front mostra `EMPTY_KPIS`/`[]` e "pisca" pro valor real. → D-007 = 4.
- **Raw palette** (`(bg|text|border)-(slate|blue|emerald|amber|red|green|gray|...)-NNN`): DetailSheet **12** (re-implementa `STATUS_BADGE` cru linhas 140-146 em vez do canon `@/Components/board/badges` que a Board usa!), Roadmap **5** (hand-rolled), Triage **5**, Burndown/MyWork 1. → D-004 = 6.
- **a11y** (`aria-|role=`): Activity **0**, Roadmap **0**, Aprovacoes 6, Trabalho 11, Inbox 4; `text-[10px]/[11px]` pervasivo. → D-011 = 5.
- **Duas gerações de DS convivem:** telas antigas (Board/Backlog/Activity) usam `@/Components/shared/PageHeader` sem primitivos; telas novas (Trabalho/Gantt) usam `@/Components/PageHeader` + `@/Components/layout` (Grid/Inline/Stack). O `Trabalho/Index.design-spec.json` (derivado) reporta 9 structural_violations mesmo sendo a mais nova — mas é a única que usa layout primitives. → D-003/D-004.
- **Board (âncora) é forte:** optimistic-lock com **409 Conflict** + revert + `role=alert` + auto-dismiss 5s; atalhos J/K/E/A/Enter/?/Esc com typing-guard, overlay `ShortcutsOverlay`, e **lane de CI dedicada** (`forja-shortcuts-gate.yml`). DetailSheet é a fonte declarada do SaleSheet.
- **Navegação bifurcada** confirmada no código: breadcrumb "Project Mgmt" (`/project-mgmt/*`) nas telas antigas × `ForjaHub` tabs (`/forja/*`) nas novas (Gantt importa `../../team-mcp/Forja/_components/ForjaHub`).

Tabela completa das 15 dimensões com notas na FICHA §2.

## Seção 4 — Nota + recomendação

- **Módulo Forja: 63/100.** Board (âncora): **70/100.** Linear (top): ~92. Jira (mainstream): ~72.
- **Gap pro topo −29 / pro mainstream −9** (parte do −9 é intencional: recusa do peso da Jira por premissa).
- **Causa raiz:** features construídas, acabamento transversal não fechado — loading sem skeleton, navegação em 2 portas, palette crua nas telas de detalhe/roadmap.

**Gaps A (conformidade DS — fechar primeiro):** G1 skeleton 0/15 [P0] · G2 erradicar raw palette incl. DetailSheet-âncora [P0] · G3 unificar 2 gerações de layout [P1] · G4 a11y uniforme [P1] · G5 error UX no Backlog bulk [P1].
**Gaps B (feature de mercado):** G6 resolver duas casas [decisão W, maior impacto] · G7 toast global [vale] · G8 presence [NÃO — premissa recusada] · G9 onboarding [baixo sinal].

**Ação imediata:** leva G1+G2 (skeleton + raw palette) — mecânico, baixo risco, fora de Tier 0, ataca as 2 dimensões de menor nota, cabe no fator 10x. **NÃO** começar por G6 (é decisão de produto [W]).

---

## Método / disciplina

- Auditoria pura — zero edição de código, zero commit, zero task MCP criada. Dois artefatos escritos: esta sessão + a FICHA canônica.
- Nota **não inflada**: 63 declarado como 63; Board reconhecido como genuinamente maduro (70) para não subestimar e justificar trabalho.
- Gaps separados por natureza (conformidade nossa × feature de mercado) e cada feature de mercado passou pela pergunta de premissa (`proibicoes §5 2026-07-16`) — G8 recusado por premissa já medida no INVENTARIO.
