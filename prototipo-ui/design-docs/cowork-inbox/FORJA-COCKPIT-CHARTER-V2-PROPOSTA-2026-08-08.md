---
id: forja-cockpit-charter-v2-PROPOSTA
page: /forja (rota do protótipo: `projects` / `teammcp` no app único)
component_prototipo: forja-page.jsx · forja-aprova.jsx · forja-data.jsx · forja-mcp.jsx · forja-integra.jsx
component_producao: resources/js/Pages/team-mcp/Forja/Cockpit.tsx (+ _components)
substitui: resources/js/Pages/team-mcp/Forja/Cockpit.charter.md (v1, errata 2026-07-27)
owner: wagner
status: PROPOSTA — [W] decide, [CL] transporta e numera
last_validated: "2026-08-08"
tier: A
pedido_relacionado: cowork-inbox/FORJA-TOPNAV-3GRUPOS-LEVA1-2026-08-08.md
---

# Page Charter — Forja (cockpit do cowork loop) · v2 PROPOSTA

> **Em uma frase:** a Forja é onde o [W] **governa a construção do próprio oimpresso** — a equipe (humanos no Claude Code + agentes) trabalha conectada ao MCP; nada vira oficial sem o aval dele.

## 1) Definições (o vocabulário da tela — copy literal)

| Termo | Definição |
|---|---|
| **Forja** | Módulo de desenvolvimento do próprio sistema. Não gerencia trabalho de cliente — gerencia a fábrica do software. |
| **Projeção** | A Forja LÊ o estado que já existe (mcp_tasks, PRs, ADRs, sessões cc, gates) e o mostra. O git é a fonte da verdade; escrita na tela = **proposta** que segue pro transporte. Sem dado fantasma. |
| **Issue** | Unidade de trabalho projetada de `mcp_tasks` project=FORJA. Tipos: tela · gate · adr · bug · refino · infra · doc · épico. |
| **Fase (F0→F4)** | Posição no protocolo — **só existe pra trabalho do pipeline de tela** (tela · refino visual · bug de UI · épico de tela). Cada fase tem **dono** e **condição de saída** (tabela §3). O board mostra F0→F3.5; no F4 (merge) o card sai do quadro e vira changelog — canon `ForjaQuadro.tsx` @main. |
| **Status (execução)** | Vocabulário canon pra TODO trabalho (`taskTokens.ts` @main): Backlog · A fazer · Fazendo · Revisão · Concluído · Bloqueada — dot estilo Stripe, nunca bg-fill. Infra, gate, ADR e doc têm SÓ status (F1.5 Critique/F2 Screenshot não se aplicam). |
| **Onda** | Ciclo de entrega com janela e milestone (~FA-1, Q1…). |
| **Aprovação** | Submissão de uma PESSOA (ou agente) esperando o aval do [W]: plano · modificação · design · proposta. |
| **Handoff** | Pacote F1→F3 (Cowork→Code). Estados: pending / applied / merged / stale (>3d) / blocked. `gateConflito` = ack diz verde mas o CI não confirma. |
| **Frescor** | Se o dado do issue foi conferido contra `@main` nesta sessão: lido / sync Nd / **inferido** (não verificado — suspeito). |
| **Gate** | Check objetivo de CI que trava avanço de fase (conformance, e2e, a11y…). Regra ligável na aba Saúde. |
| **Critique** | Score do gate F1.5 por handoff (≥80 passa). Base da coluna de qualidade do placar. |
| **Retrabalho** | Handoff `blocked` devolvido ao autor. Medido, nunca auto-relatado. |

## 2) Missão e jobs (por que cada aba existe)

A Forja responde 5 perguntas, uma por lente — **3 grupos de topnav** (aba de topo só pro que se usa todo dia; o resto é segmento):

- **Trabalho** — `Aprovações` (landing): *"o que chegou e precisa do meu aval?"* · `Trabalho`: *"o que fazer, em que ordem, onde está parado?"* — UMA tela sobre `mcp_tasks` com **escopo por Frente**: `FORJA` (rank/pin, épicos, ondas com encerrar/carga, sub-visões Lista|Quadro de 2 eixos) e `Todas as frentes` (conceito Tasks canon: KPIs-filtro, status, ActorSeal, bulk). Backlog, Quadro e Tarefas como abas separadas foram **fundidos** — eram 3 projeções da mesma entidade ([W] 2026-08-08).
- **Esteira** — `Saúde`: *"o loop está saudável (WIP, aging, gates, automação)?"* · `MCP`: *"o que os agentes podem fazer e o que fizeram (contrato/tokens/auditoria + handoffs)?"*
- **Histórico** — `Changelog`: *"o que já saiu (PR/ADR/sessão/onda)?"* · `Integrador`: referência da absorção Forja↔TeamMcp (documento, não operação).

**Backlog × Quadro = os MESMOS issues, duas lentes.** Backlog é lista ordenada (rank híbrido: score `prio × tempo parado × quantos destrava`, bloqueado desce, **pin** manual fura a fila) — otimizada pra priorizar. O Quadro tem **dois eixos**: **Pipeline de telas** (F0→F3.5, só issues com fase — o ciclo do design até a acessibilidade) e **Execução** (status canon, todo o trabalho — infra/gate/ADR incluídos). A Triagem NÃO é aba: propostas de agente entram na mesa de Aprovações como tipo `Proposta`.

## 3) O protocolo F0→F4 (colunas do Quadro — copy literal dos cabeçalhos)

| Fase | Dono | O que faz | Sai quando |
|---|---|---|---|
| F0 Brief | [W] | você escreve o pedido | brief aceito → agente assume |
| F1 Design | [CC] | protótipo visual no Cowork | handoff + ✓ lido @main |
| F1.5 Critique | [CD] | avaliação heurística do design | score ≥ 80 |
| F2 Screenshot | [W2] | VOCÊ aprova o visual | seu aprovo (gate F2) |
| F3 Code | [CL] | implementação Inertia/React real | PR aberto + gates verdes |
| F3.5 A11y | [CA] | acessibilidade WCAG 2.1 AA | a11y verde |
| F4 Merge | [W2] | VOCÊ funde o PR | merge → entra no changelog |

Um card andando da esquerda pra direita = uma tela do ERP saindo do pedido até o merge. **O board vai até F3.5** (texto-âncora canon: “O ciclo de vida de cada tela, do brief à acessibilidade”); F4 não é coluna — mergeou, saiu do quadro, virou changelog. O quadro tem filtro por papel e o eixo Execução pro trabalho sem fase.

## 4) Mesa de Aprovações (landing) — contrato de comportamento

- **Ao vivo no MCP**: um card por pessoa (Felipe sênior · Maiara júnior · Eliana júnior · Luiz artista) — status executando / **espera você** / offline, o que está fazendo, custo hoje.
- **Fila única** (mais antigo primeiro), artefato no centro, 4 tipos com verbo próprio: Plano → *Aprovar plano* (passos + selo Tier 0 + escopo + risco/custo) · Modificação → *Aprovar aplicação* (diff por arquivo + gates do PR ao vivo) · Design → *Aprovar screenshot (F2)* (imagem grande; o aprovo É o gate F2) · Proposta → *Aprovar → backlog* (dossiê do analista como overlay).
- **Ações**: Aprovar `a` · Devolver com comentário `d` (comentário obrigatório, vai pra sessão da pessoa) · Rejeitar `x`; `j/k` navega. Toda decisão sai da fila, atualiza o badge e audita.
- **Placar da equipe de agentes** (embaixo): heartbeat (alerta `sem sinal` >24h + ação verificar) · sessões hoje · custo/quota BRL com barra (âmbar >60%, vermelho >85%) · critique médio + mini-série · retrabalho · entregas 7d. Fontes: cc_sessions + handoffs + gates + quotas da tela Equipe.

## 5) Non-Goals

❌ Tabela/entidade nova (projeção sobre `mcp_tasks`; pin/rank = user-pref). ❌ Workflow configurável — F0→F4 é constituição. ❌ Merge ou `constituicao.edit` pela UI (só [W2]/[W]). ❌ Série temporal antes do trends builder (non-goal herdado do Scorecard). ❌ Token raw persistido/logado (Tier 0 ADR 0081). ❌ Emoji, cor crua, `rounded-xl+`, inglês em UI.

## 6) Anti-hooks

❌ Agente nunca aprova nada sozinho — propõe; [W] decide. ❌ Métrica de agente nunca auto-relatada — só medida (CI, sessions, handoffs). ❌ A tela nunca grava no git — escrita vira proposta + transporte zero-toque.

## 7) Verificação do protótipo — 2026-08-08 (testes executados por [CC] via probing, console limpo)

| # | Critério | Resultado |
|---|---|---|
| T1 | Topnav em 3 grupos rotulados (Trabalho · Esteira · Histórico), sem aba Triagem | ✅ |
| T2 | Mesa: 7 na fila (4 tipos), 4 pessoas ao vivo, placar 5 agentes + alerta "1 sem sinal" | ✅ |
| T3 | Aprovar Proposta muda estado real: fila 7→6, badge 9→8, toast, issue promovido | ✅ |
| T4 | FORJA-150 aparece no Backlog após aprovo; rank automático (P0 primeiro); roll-up do épico presente | ✅ |
| T5 | Quadro: 7 colunas com dono + "o que faz" + "sai quando"; filtro [CL] → 5 cards, todos [CL] | ✅ |
| T6 (turnos anteriores) | Pin fura fila e persiste · autocomplete `is: @ ~ tipo:` · massa fase/papel/prio/onda · header não esconde "Novo issue" ≤1380px · contraste dark dos tints | ✅ |
| T7 | SLA na mesa: espera ≥2h vermelho (Eliana 3h), ≥30min âmbar (Maiara 1h); toda decisão tem Desfazer por 6s — proposta desfeita voltou pra fila (badge restaurado) | ✅ |
| T8 | Onda como ciclo: encerrar ~FA-1 → FORJA-141 carregado pra ~FA-2 com selo `carry ×1`, FA-2 virou ativa, fechamento entrou no changelog; carga por tamanho no header do grupo (`1M · 1G`) | ✅ |
| T9 | Conceito corrigido contra @main (19:54Z): Quadro com 2 eixos — Pipeline de telas F0→F3.5 (só issues com fase; F4 fora do board) × Execução (status canon Backlog→Concluído, dot Stripe); infra/gate/ADR sem fase mostram StatusPill na lista e “Execução” no drawer | ✅ |
| T10 | Fusão Backlog+Quadro+Tarefas → aba única **Trabalho** com escopo Frente; preferências antigas migram; alternar frente/visão preserva estado | ✅ |
| T12 | Gantt (conceito de `Forja/Roadmap/Gantt.tsx` @main) como 3ª sub-visão de Trabalho: 9 grupos por módulo, 23 barras com progresso por status, 2 bloqueadas sinalizadas, linha do hoje, arrastar = reagenda SÓ o prazo (toast cita mcp_task_events), clique abre o drawer único; filtros/KPIs da tela valem no Gantt | ✅ |
| T11 | Fusão TOTAL (PR-6 melhor-dos-dois): UMA Lista serve as duas frentes — Todas = 25 itens (FORJA + tasks) com OwnerSeal nome/[papel] (6 agentes), Lock, esforço tam\|h, KPI-filtros e ordem rank\|execução (default por frente); UM Quadro — eixo Execução com as 4 colunas ativas canon (A fazer→Concluído, 18 cards cross-frente) × eixo Pipeline F0→F3.5; UM drawer (task sem frescor mostra Execução, sem stepper); componente Tarefas aposentado (zero duplicação) | ✅ |

Pendente de produção (não testável no protótipo): rotas `/forja/aprovacoes` + 301, auditoria em `mcp_audit_log`, badge vivo por prop deferida.
