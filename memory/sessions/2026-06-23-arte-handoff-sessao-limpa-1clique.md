---
slug: 2026-06-23-arte-handoff-sessao-limpa-1clique
title: "Estado-da-arte: handoff por-tela → sessão limpa rule-seeded disparada com 1 clique × oimpresso"
type: session
status: live
authority: reference
module: _DesignSystem
created: 2026-06-23
agent: estado-da-arte
related_adrs: [0114, 0282, 0104, 0297, 0293, 0093, 0105, 0106]
related_docs:
  - .claude/skills/aplicar-prototipo/SKILL.md
  - prototipo-ui/RUNBOOK-aplicar-prototipo-orquestracao.md
  - .claude/agents/coordenador-paralelo.md
  - memory/decisions/proposals/2026-06-23-prototipo-ssot-unico-com-historico.md
  - prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md
  - memory/sessions/2026-06-22-arte-design-to-code-sdd.md
---

# Estado-da-arte — handoff por-tela → sessão limpa rule-seeded → 1 clique

> **Ideia do Wagner (2026-06-23):** cada tela alterada (ou mudança de DS) abre uma **SESSÃO NOVA LIMPA**, seedada com (a) **as regras** (estrutura + charter da tela + pode/não-pode) + (b) o **HANDOFF do que fazer**. Vira uma **task** que ele **dispara/aprova com 1 CLIQUE**. Acha que é o novo padrão do DS.
>
> **Veredito de uma linha:** isso NÃO é hipótese nova — é exatamente o padrão `orchestrator-worker` + `issue→agente autônomo` que o mercado consagrou em 2026, e o oimpresso **já implementa ~80% dele** (skill `aplicar-prototipo` Fase 3/4). O gap é de **automação do gatilho**, não de conceito. Complementa o doc de ontem ([2026-06-22-arte-design-to-code-sdd](2026-06-22-arte-design-to-code-sdd.md)) — aquele foi sobre *fidelidade design→código*; este é sobre *como a task nasce e dispara sozinha*.

---

## 1. PESQUISA — como os melhores fazem (limpa, 2026)

| Player / produto | Mecanismo concreto do "task → sessão isolada → 1 clique" | Por que é referência |
|---|---|---|
| **GitHub Copilot coding agent** (GA 2026) | Você **atribui uma Issue ao "Copilot"** (1 clique: assignee, igual a um humano — web/mobile/CLI). O agente sobe num **ambiente efêmero isolado**, lê só a Issue + contexto do repo, trabalha autônomo, **push incremental num PR draft** e te devolve pra review. O "spec" é o corpo da Issue. | Define o padrão **issue=spec, atribuição=1 clique, sessão isolada efêmera, PR draft = output reviewable**. Humano só aprova o PR. |
| **Cursor background agents + worktrees** (Cursor 2.x/3) | Cada task roda em **git worktree isolado** próprio (arquivos/deps/mudanças confinados; main intocado); até 8 em paralelo, cada um clona o repo na nuvem e devolve um PR. Worktree = isolamento filesystem que evita agentes pisarem uns nos outros. | É **exatamente** o "Agent(isolation: worktree)" que o RUNBOOK do oimpresso só descreve aspiracionalmente. Prova que 1-task-1-worktree-paralelo é viável e mainstream. |
| **Linear AI Agents + Triage Intelligence** (2026) | **Atribui a Issue a um agente** = dispara o workflow dele (delegação = mesma UX de delegar a humano). **Triage rules / automations** roteiam Issue que entra no Triage e podem **delegar a um agente automaticamente** por label/team/propriedade — open-ended instructions. | One-click delegation + **gatilho automático por regra** (não precisa nem do clique quando a regra casa). Modelo de "issue tracker = infra de agente". |
| **Figma Dev Mode + Code Connect + MCP** | Para "mudança de DS": Code Connect mapeia componente Figma↔código real (`*.figma.tsx`); `get_code_connect_map` resolve o componente; MCP server entrega ao agente **dados de design estruturados** (variants/tokens/auto-layout), não pixel. Handoff por-componente vira **contrato máquina-legível**. | Padrão de facto do handoff design→code; o agente para de adivinhar do screenshot. É o que falta pro elo "mudança de DS → o que mudou + onde aplicar". |
| **GitHub Spec Kit / Kiro (SDD 2026)** | A **"constitution"** (markdown de princípios imutáveis) é o contrato persistente por-sessão; o spec por-tarefa (requirements/design/tasks) é executável pelo agente. Anti-alucinação: `[NEEDS CLARIFICATION]` quando o spec é vago — flag em vez de inventar. | Como o spec **seeda** o agente com fidelidade. O marcador de clarificação é a defesa anti-alucinação que o GAP-SPEC do oimpresso já espelha (`_pendente_` / "gap incerto = pergunta"). |

**Síntese da Fase 1:** o padrão maduro 2026 é **`spec/issue por-tarefa` (contrato) → `atribuição/clique` (gatilho, às vezes regra automática) → `sessão isolada efêmera por worktree` (execução) → `PR draft` (review reviewable) → humano aprova**. As regras globais vivem numa "constitution" persistente; o "o que fazer" vive no spec da tarefa; a fidelidade vem de design-as-contract (Code Connect) + marcador de clarificação.

---

## 2. COMPARA — o que o oimpresso JÁ tem que realiza a ideia

| Dimensão da ideia do Wagner | Estado-da-arte (Fase 1) | Estado oimpresso hoje | Distância |
|---|---|---|---|
| **Spec/handoff por-tela (o "o que fazer")** | Issue/spec por-tarefa, executável | **GAP-SPEC** (`<tela>-gap.md`, template no RUNBOOK): tela dividida em PARTES + o quê/porquê/esforço/risco/ordem. `aplicar-prototipo` Fase 3 embute no `tasks-create` (MCP) = a "ordem de serviço" da sessão. | **curta — bate** |
| **Regras que seedam (a "constitution")** | constitution markdown imutável + spec | `CLAUDE.md` + skills Tier A always-on + **charter por-tela** (`*.charter.md` = o que a tela é + props/estado/pode-não-pode) + `COWORK-ESTRUTURA-E-TELAS.md` (contrato). Charters confirmados vivos em `Pages/**`. | **curta — bate** |
| **1 task = 1 contexto limpo** | sessão efêmera por issue | Fase 4 = "**1 sessão LIMPA por tela**, carrega SÓ o `<tela>-gap.md` + skills auto". Regra de ouro: separar análise barata (1x) da aplicação cara (por tela) = economia O(1) vs O(N). | **curta — bate** |
| **Isolamento (worktree)** | git worktree por agente (Cursor) | RUNBOOK **descreve** `Agent(isolation:"worktree")`, mas o mecanismo real (`coordenador-paralelo`) **spawna N agents na MESMA worktree** (lição Omnichannel: worktree-filha mata o agente). Isolamento é **PARCIAL** (DS compartilhado serializa). | **média** |
| **1 clique pra disparar** | atribuir Issue ao agente (Copilot/Linear) | **Não há mecanismo oimpresso** — o "chip de 1 clique" é a **feature NATIVA do Claude Code** (Tasks/background). `spawn_task` não existe no repo (confirmado: 0 hits). Hoje o disparo é Wagner colar a "linha de 1 linha" do RUNBOOK numa sessão nova. | **média-longa** |
| **Gerar a task automaticamente do diff** | Triage rule delega ao agente | **Não automatizado.** Fase 0 detecta diff via `git diff`; a SSOT proposal torna o diff **determinístico** (`git diff` do `cowork/` overwrite+commit). Mas transformar "diff tocou tela X" → "task criada + seedada" é **manual** (roda Fase 0/1/3 à mão). | **longa** |
| **Intake do handoff** | Issue tracker como infra | `cowork-intake` Issue template + `cowork-inbox` workflow existem (ADR 0282), mas **adoção = ZERO** (RUNBOOK admite: "na prática é handoff/bundle"). | **média** |
| **Aprovar com 1 clique** | aprovar PR draft | Portão = **Wagner aprova o SCREENSHOT** (não tabela) → merge com `pr-ui-judge` + `visual-regression` + `contrato-de-tela`. Re-aplicação = gate automático, olho do Wagner só acima do limiar. Aprovação é via PR (1 clique no merge). | **curta — bate** |
| **Fidelidade / anti-alucinação** | `[NEEDS CLARIFICATION]`, Code Connect | GAP-SPEC: "gap incerto = `_pendente_`/pergunta" (LICOES_F3). `anchor-lint` (ADR 0297) força sync spec↔código com estado `zombie` — **supera** o paper. Falta o map design↔código máquina-legível (`<tela>.map.json` proposto, **0 existem hoje**). | **curta no spec / longa no map** |
| **Mudança de DS (token) como gatilho** | DTCG `.tokens.json` + Style Dictionary; diff de token propaga | Mapeamento oklch→Tailwind **na cabeça do agente**; sem `.tokens.json`. Há branches `onda-dtcg-*` em voo (worktrees `dtcg-ativar`/`agent-...-dtcg-tokens`), mas não landed. "Mudança de DS dispara task" não tem trilho. | **longa** |

**Leitura honesta:** o **conceito** da ideia do Wagner já está documentado e ~80% operante — ele basicamente descreveu a Fase 4 da própria skill `aplicar-prototipo` (que nasceu de um pedido dele em 2026-06-22). O que falta é **mecanizar a cola**: diff→task automática, seed empacotado (charter+gap+regras num payload), isolamento real por worktree, e um gatilho de 1 clique que não dependa de colar prompt. Onde o oimpresso **supera o mercado**: o portão por-screenshot (mais rigoroso que aprovar diff Chromatic) e `anchor-lint` com estado `zombie`.

---

## 3. AVALIA — o que falta pra virar "1 clique → sessão limpa rule-seeded por tela" como padrão

| Gap | Impacto | Esforço (IA-pair, ADR 0106) | Pré-req? | Reusa vs constrói |
|---|---|---|---|---|
| **G1 — Empacotador de seed (`seed-tela.mjs`)**: gera 1 payload por tela = charter + `<tela>-gap.md` + regras Tier 0 + skills-a-disparar + ranges de linha. É o "corpo da Issue" que seeda a sessão limpa. | **alto** | ~1-2h | nenhum (GAP-SPEC e charter já existem) | **constrói** fino (lê o que já existe; não inventa formato) |
| **G2 — Gerar task do diff automaticamente** (`diff→task`): após o overwrite+commit do `cowork/` (SSOT proposal), `git diff` → telas tocadas → `tasks-create` (MCP) já seedado pelo G1. Tira o passo manual de rodar Fase 0/1/3 à mão. | **alto** | ~2-3h | **SSOT proposal aprovada** (diff determinístico) + G1 | **reusa** Fase 0 + `tasks-create`; constrói o gatilho |
| **G3 — Isolamento real por worktree** no spawn: hoje `coordenador-paralelo` = mesma worktree. Adotar 1-worktree-por-tela (padrão Cursor) pra telas de arquivos disjuntos, mantendo serialização da fundação DS. | **médio** | ~2h + validação | lição Omnichannel (worktree-filha mata agente) — validar receita | **constrói** (revisar o pattern do coordenador) |
| **G4 — Botão de 1 clique de verdade**: hoje depende de colar prompt OU da feature nativa de Tasks do Claude Code. Avaliar usar Tasks nativo (chip) seedado pelo G1, OU `cowork-inbox` → atribuição que dispara. | **médio** | ~1h (avaliação) a ~4h (trilho) | G1 (precisa do seed pronto) | **reusa** Claude Code Tasks nativo > constrói trilho próprio |
| **G5 — Gate "módulo silenciado" + flags de governança como CHECK** (não só lembrete): das 5 flags da Fase 2, só 2 têm gate. `silenced:true` no front-matter + check CI que barra PR tocando `Pages/<Mod>/` silenciado. | **médio** | ~1-2h | nenhum | **constrói** check (padrão `cowork-ssot-guard` já existe) |
| **G6 — `<tela>.map.json` (Code Connect-like)**: bloco-protótipo↔arquivo/range + sha; faz a sessão ler só os trechos + invalida gap quando re-exporta. **0 existem hoje.** | **médio** | ~3-4h por tela inicial, depois barato | SSOT (sha estável) | **constrói** (espelha Figma Code Connect) |
| **G7 — Mudança de DS (token) como gatilho**: DTCG `.tokens.json` + Style Dictionary; diff de token → telas afetadas → tasks. | **alto-DS / baixo-frequência** | ~1 dia | ondas `dtcg-*` landarem primeiro | **reusa** branches dtcg em voo; constrói o gatilho depois |

### Proposta refinada (ranqueada impacto × esforço)

O caminho de menor atrito que entrega a ideia completa do Wagner é a sequência **G1 → G2 → G4**, porque:
- **G1 (empacotador de seed)** é o tijolo de tudo — sem o payload empacotado, nem o gatilho automático nem o 1-clique têm o que disparar. Alto-impacto, baixo-esforço, **zero pré-req bloqueante** (charter + GAP-SPEC + regras Tier 0 já existem; só precisam ser **costurados num payload**, não inventados).
- **G2 (diff→task)** transforma o handoff manual em automático, mas **depende da SSOT proposal ser aprovada** (pra o diff ser determinístico) — por isso vem depois.
- **G4 (1 clique)** muito provavelmente se resolve **reusando a feature nativa de Tasks/background do Claude Code** (o "chip") seedada pelo G1 — antes de construir trilho próprio, avaliar o nativo (mais barato, menos manutenção).
- **G3/G6** elevam a qualidade (isolamento, fidelidade) mas não bloqueiam o MVP da ideia.
- **G7** é o caso "mudança de DS" — alto valor mas espera as ondas DTCG landarem.

**Regra de reúso:** quase tudo **reusa** (charter, GAP-SPEC, `tasks-create`, `cowork-ssot-guard` como template de check, Tasks nativo). O único "construir" essencial e barato é **G1** (o empacotador) e **G2** (o gatilho do diff).

---

## Recomendação final

**Comece por G1 — o empacotador de seed (`scripts/governance/seed-tela.mjs`).** É alto-impacto, baixo-esforço (~1-2h IA-pair), sem pré-req bloqueante, e é o tijolo do qual G2 (diff→task) e G4 (1 clique) dependem. Ele formaliza a parte mais frágil hoje: o "o que carregar na sessão limpa" é prosa no RUNBOOK; vira um payload máquina-gerado (charter + gap + regras Tier 0 + skills + ranges).

**Próxima ação concreta hoje:** especificar o contrato de saída do `seed-tela.mjs` — dado `<Mod>/<Tela>`, ele emite o bloco markdown que seeda a sessão limpa (front-matter com tela/charter-path/gap-path/governança + corpo = regras Tier 0 + a linha-de-1-linha do RUNBOOK). Validar com 1 tela real que já tem GAP-SPEC (`Sells/vendas-gap.md` é o único que existe hoje).

**Não fazer agora:** G7 (DTCG) — espera as ondas `dtcg-*` em voo; construir trilho de 1-clique próprio antes de avaliar o Tasks nativo do Claude Code (risco de reinventar o chip).
