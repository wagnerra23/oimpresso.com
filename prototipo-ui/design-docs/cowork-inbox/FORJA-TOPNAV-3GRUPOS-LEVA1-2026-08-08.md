# PEDIDO PRO [CL] — Forja completa: Mesa de Aprovações + fusão "Trabalho" (Lista·Quadro·Gantt) + topnav 10→3

> **Versão consolidada 2026-08-08 (fim da sessão).** Este é O documento — supersede as versões parciais do mesmo dia.

> **Cole isto UMA vez no Claude Code.** Repo `wagnerra23/oimpresso.com` · branch base `main`.
> Origem do design (F1, Cowork — build exportado em `prototipo-ui/cowork/forja/`): `forja-page.jsx` (shell, Lista, Quadro 2 eixos, Gantt, drawer único) · `forja-aprova.jsx` (mesa + placar) · `forja-data.jsx` (modelo unificado mock) · `forja-page.css` · `forja-mcp.jsx` · `forja-integra.jsx`. Ler como referência visual/gramática, NÃO copiar CSS cru.
> **Soberania:** NÃO editar constituição (ADR/PROTOCOL/BRIEFING). Merge só [W2]. Token raw nunca persistido/logado (Tier 0 ADR 0081).

## 0) Premissa (✓ lido @main 2026-08-08, ~16:13Z)

- `Pages/team-mcp/Forja/Cockpit.tsx` — shell com 5 abas próprias (Triagem/Backlog/Quadro/Changelog/MCP) via prop `tab`, header compartilhado `ForjaHub`, dados via `Inertia::defer`, layout `AppShellV2`.
- `Cockpit.charter.md` (errata 2026-07-27) — **9 itens** de topnav em `config/core_topnavs.php['Forja']` (5 próprios + Tarefas/Equipe/CC Sessions/Saúde absorvidos) — **atualização 2026-08-08 19:54Z: são 10** — entrou **Roadmap (Gantt)** (`/forja/roadmap-gantt`, 2026-08-06 via #5310/ADR 0366, `can` próprio `jana.mcp.tasks.read`, com nota no config de que abriu VAZIA em produção: ghost não alimenta a tela); **Saúde fundida no `/team-mcp/scorecard`** (sem rota `/forja/saude`); permissão `jana.mcp.usage.all`; badge da Triagem **estático** (`'badge' => 3` no config, contador vivo só via prop deferida).
- `_components/`: ForjaHub · ForjaTriage · ForjaBacklog · ForjaQuadro · ForjaChangelog · ForjaMcp · ForjaDossier. Telas absorvidas: `Tasks/Index.tsx` (28.8KB) · `Team/Index.tsx` (32.7KB) · `CcSessions/Index.tsx` · `Scorecard/Index.tsx`.
- **Dupe real:** ForjaBacklog (mcp_tasks project=FORJA) × Tasks/Index (mcp_tasks todos) — dois backlogs no mesmo hub.
- NÃO verifiquei neste turno: `core_topnavs.php` em si, `ForjaController`, services — conferir antes de mexer.

## 1) Diagnóstico (por que 9→3)

9 destinos chapados misturam 3 trabalhos: **fluxo do issue** (Triagem/Backlog/Quadro/Tarefas — mesma entidade, lentes diferentes), **operação da esteira** (Saúde/MCP+Handoffs/Equipe) e **registro** (Changelog/CC Sessions). Handoffs F1→F3 — operação diária, com estado `stale` e `gateConflito` no design — está enterrado dentro da aba MCP. Régua: aba de topo só pro que se usa todo dia; o resto é segmento.

## 2) PRs (1 tela por PR · DS v6 roxo canon · sem cor crua · sem rounded-xl+ · PageHeader canon)

### PR-0 · “Aprovações” — mesa de aprovação (landing; pivô ratificado por [W] 2026-08-08)
- **Entidade central = submissão de PESSOA da equipe** trabalhando no Claude Code via MCP — não issue. Nova rota `/forja/aprovacoes` vira a **landing** de `/forja` (301). **A Triagem deixa de ser aba** ([W] 2026-08-08): as propostas entram na mesa como tipo Proposta (aprovar/rejeitar chama os endpoints existentes `/forja/{id}/{aprovar,rejeitar}`; dossiê abre como overlay) — rota `/forja` antiga da triagem vira redirect.
- **Ao vivo no MCP** — strip por pessoa (Felipe/Maiara/Eliana/Luiz): status executando/espera-você/offline, o que está fazendo, custo hoje. Fonte: sessões cc ativas + heartbeat.
- **Fila única de aval** (mais antigo primeiro), artefato no centro, teclado a/d/x:
  - **Plano** (sênior): objetivo + passos (selo Tier 0) + escopo de arquivos + risco/custo estimado → Aprovar plano / Devolver c/ comentário / Rejeitar. Fonte: `mcp_tasks` tipo plano + payload da sessão.
  - **Modificação** (júnior): resumo + diff por arquivo (+/−) + gates do PR ao vivo → Aprovar aplicação. Fonte: `cowork_handoffs` + checks do PR.
  - **Design** (artista): screenshot grande + tela alvo → **Aprovar = gate F2 do protocolo** (carimba evidência no issue).
  - **Proposta** (agente): projeção da triagem atual → Aprovar→backlog / dossiê completo.
- Devolver exige comentário (vai pra sessão da pessoa); toda decisão audita em `mcp_audit_log`.
  - **Placar da equipe de agentes** (enterprise) — tabela por papel [CC]/[CD]/[CL]/[CA]/[AN]: heartbeat (último sinal, alerta `sem sinal` >24h), sessões hoje, custo hoje/quota BRL com barra (verde→âmbar>60%→vermelho>85%), critique F1.5 médio + mini-série, retrabalho (handoff `blocked` devolvido) e entregas 7d. Fontes que JÁ existem: agg de `mcp_cc_sessions` por papel · `cowork_handoffs` (critique/blocked) · quotas de `mcp_tokens` (tela Equipe) · heartbeat do ingest. Nada auto-relatado; série histórica só quando o trends builder existir (respeitar non-goal do Scorecard).
- Header: contagem “esperando o seu aval” + alerta de handoffs com problema. Badge do segmento = fila (vivo, prop deferida — mesmo padrão do `triagemCount`).
- Referência visual F1: `ForjaAprovacoes` em `forja-aprova.jsx` (classes `ap-*`; placar `fj-team-tbl`).

### PR-1 · Topnav do hub: 10 itens → 3 grupos
- `config/core_topnavs.php['Forja']`: reorganizar em 3 grupos com segmentos:
  - **Trabalho** — Aprovações (landing, badge da fila; absorve a Triagem) · **Trabalho** (funde Backlog+Quadro+Tarefas — ver PR-6) · Roadmap (Gantt)
  - **Esteira** — Saúde (`/team-mcp/scorecard`) · Handoffs (nova rota, ver PR-3) · MCP · Equipe
  - **Histórico** — Changelog · CC Sessions
- Se `ModuleTopNav`/`useAutoModuleNav` não suportar grupo: renderizar o agrupamento no `ForjaHub` (grupo = contêiner com label uppercase 9.5px + segmentos, ver `fj-navgroup` no CSS F1) e manter o config chapado. **Rotas NÃO mudam** — só apresentação.
- Resolver o dupe Backlog×Tarefas no MÍNIMO por subtitle explícito ("project=FORJA" vs "todas as tasks"); fusão real é onda futura, não deste PR.

### PR-2 · ForjaBacklog: Leva 1 — **ABSORVIDO pelo PR-6b** (mantido pelo registro das decisões; implementar dentro da lista unificada)
- **Rank híbrido** ([W] decidiu 2026-08-08): ordenar por score `prio(P0=400/P1=300/P2=200/P3=100) + min(aging,21)×4 + destrava×25 − bloqueado×140`; **pin manual** fura a fila (topo do grupo, persistido como user-pref, chave `forja.pin`). Rodapé declara "ordem: automática + N fixados"; tooltip do dot de prioridade explica o score. Atalho `p`.
- **Roll-up de épico**: linha do épico ganha barra por sub-issue colorida pela fase + `n/N` (F4=concluído); expande inline (chevron, `→`/`←`), filho visível aninha sob o pai sem duplicar na lista.
- **Massa**: barra de seleção com fase · papel · prio · onda (segmentos com régua), além de favoritar/limpar.
- **Autocomplete da query** `is: @ ~ tipo: mod:` no campo de busca (tab completa, ↑↓ escolhe, esc fecha só as sugestões). A gramática já existe — só ensinar na digitação.

### PR-3 · Handoffs à luz
- Quebrar `ForjaMcp.tsx` (30KB): **Handoffs** vira segmento próprio do grupo Esteira (rota `/forja/handoffs`), primeiro plano com heartbeat, filtros por estado e destaque de `stale`/`gateConflito`; **MCP** fica só contrato/tokens/auditoria (MOCKADO por design — enforce é do servidor).

### PR-1.5 · Quadro com 2 eixos (conceito canon — F1 pronto no protótipo)
- **Pipeline de telas** (default): board F0→F3.5 só com issues que têm `forja_fase` — mantém o texto-âncora canon do `ForjaQuadro.tsx`; cabeçalho de coluna ganha dono ([papel]) + “o que faz” + “sai quando”. F4 não é coluna.
- **Execução (status)**: segundo eixo com o vocabulário canon de `taskTokens.ts` (backlog/todo/doing/review/done/blocked, dot Stripe, nunca bg-fill) — é onde infra/gate/ADR vivem. Toggle persistido; drag muda `status` (não fase).
- Regra de dado: **fase só pra trabalho do pipeline de tela**; demais tasks não carregam `forja_fase`.
- Roadmap (Gantt): antes de agrupar, corrigir o vazio de produção (ghost do DataController não alimenta a topnav nova — nota do próprio config).

### PR-4 · Onda como ciclo + tamanho (leva 2 — F1 pronto no protótipo)
- Header do grupo (backlog por onda): estado + janela + **carga por tamanho** (campo `tam` P/M/G/GG em `mcp_tasks`, user-editável) + botão **encerrar onda**: não-concluídos (fase≠F4) carregam pra próxima (selo `carry ×N`), fechamento entra no changelog. Próxima = quem depende dela, senão a primeira planejada.
- Fechamentos acumulados viram a série do ritmo (L-7, pós trends builder).

### PR-5 · SLA + undo na mesa
- `esperaMin` por item: ≥30min âmbar, ≥120min vermelho (tooltip explica).
- Toda decisão mostra **Desfazer** por 6s (proposta desfeita volta pra triagem); depois disso, só pelo fluxo normal.

### PR-6 · Fusão TOTAL das visualizações → “Trabalho” (ratificado [W] 2026-08-08 · plano “melhor dos dois” 2026-08-08)
> A fusão v1 (escopo por Frente alternando componentes) foi F1 provisório. O alvo é **UMA Lista e UM Quadro** servindo qualquer frente — cada visão herdando o melhor dos dois mundos. Resolve o dupe ForjaBacklog×Tasks por eliminação, não por abas.

**PR-6a · Modelo/serviço unificado**
- `TrabalhoService` funde `ForjaBacklogService` + `TasksService`: task = `{ id, frente(project), titulo, modulo, owner{nome, papel?, kind humano|agente}, onda, prio, tam?, estimate_h?, fase?(só pipeline de tela), status(canon), frescor?(FORJA), bloqueada_por[], parent/children }`.
- Rota única `/forja/trabalho` (301 de `/forja/backlog|quadro` e `/team-mcp/tasks`). **SEM filtro/chips de frente** ([W], comentário 2026-08-08): a lista abre sempre com TODAS as mcp_tasks; o recorte FORJA se faz por **agrupar por Frente** ou busca. Nota na barra declara o total.

**PR-6b · Lista unificada (melhor dos dois)**
- Da FORJA: rank híbrido + pin `p` (persistido) · aninhamento/roll-up de épico · visões salvas (agora gravam a frente) · busca DSL com autocomplete (`is: @ ~ tipo: mod:` + novo `frente:`) · headers de onda com carga por tamanho + encerrar onda (carry-over) · FrescorPill · bulk fase/papel/prio/onda.
- Do Tasks: fileira de KPI-filtros (Total · P0 abertas · Fazendo · Bloqueadas) · ActorSeal composto **nome + [papel]** (Bot roxo agente / User neutro) · coluna esforço = `tam` se houver senão `estimate_h` · StatusPill canon quando sem fase · Lock em bloqueada · densidade compacta/normal · “— sem onda —” e done/cancelled nascem colapsados · bulk → status.
- Ordenação: UM toggle `ordem: rank | execução` — **default rank** (decisão L-1 do [W]; sem filtro de frente o rank é a régua única de "o que fazer agora"). Tooltip do dot explica o score.

**PR-6c · Quadro unificado + drawer único**
- Eixo **Execução** (default, todas as frentes): 4 colunas ativas canon (A fazer → Fazendo → Revisão → Concluído); bloqueada = trilho vermelho no card + KPI-filtro (não coluna); drag muda `status` (registra `mcp_task_events`).
- Eixo **Pipeline de telas** (F0→F3.5): só cards com `fase`, de qualquer frente; cabeçalho dono + “o que faz” + “sai quando”; drag muda fase (proposta; gates travam); F4 sai do board → changelog.
- Card unificado: prio dot · id mono · chip tipo/módulo · título 2 linhas · ActorSeal · onda · Frescor (se FORJA) · Lock.
- Drawer único = o rico da FORJA (stepper quando fase / status row quando não; subtarefas, atividade, comentários, anexos) + esforço + ActorSeal. Aposenta `TaskDrawer`.

**Aceite PR-6**: `/forja/trabalho` mostra 100% das mcp_tasks na MESMA lista com rank default; pin, épico, encerrar onda e KPI-filtro funcionam juntos; quadro alterna eixo sem trocar de tela; visão salva restaura agrupamento+ordem; zero componente de lista/quadro duplicado no bundle.

### PR-7 · Gantt entra na fusão (3ª sub-visão de Trabalho — F1 pronto no protótipo)
- `/forja/roadmap-gantt` deixa de ser destino de topnav e vira a sub-visão **Gantt** de `/forja/trabalho` (Lista | Quadro | Gantt), consumindo o MESMO pool/filtros da fusão. Mantém a lib SVAR + drawer/Sheet e o contrato do charter dela: reschedule só de `due_date` (B2), quarter view `/project-mgmt/roadmap` intocado (ADR 0367 D7), limite 500.
- **Pré-requisito**: corrigir o ghost que deixou a tela vazia em produção (nota no próprio `Gantt.tsx`: toda Page sob /forja/* precisa montar `ForjaHub`; o dado não chegava) — já flagrado no PR-1.5.
- Referência visual F1: `GanttView` em `forja-page.jsx` (classes `fj-g-*`; barras por módulo, linha do hoje, atrasada em vermelho).

## 3) Gates
Cada PR: conformance/foundation/stylelint/pageheader verdes · sem cor crua · `tabular-nums` nos contadores · locators `data-testid` · a11y AA (foco visível nos controles novos: pin, chevron, sugestões).

## 4) O que NÃO fazer
- ❌ Rota nova além de `/forja/handoffs`; ❌ tabela/campo novo (pin/rank = user-pref, não schema — schema exige ADR mãe); ❌ mexer em permissão `jana.mcp.usage.all`; ❌ fundir Tarefas×Backlog agora; ❌ workflow configurável (F0→F4 é constituição).

## 5) Ordem de execução sugerida
1. **Ghost do Gantt** (pré-requisito de tudo que toca /forja/*: Page precisa montar ForjaHub — a causa está comentada no próprio Gantt.tsx).
2. **PR-0** Mesa de Aprovações (landing) — maior valor/dia pro [W].
3. **PR-1** topnav 10→3 (Trabalho · Esteira · Histórico).
4. **PR-6a→6b→6c** fusão Trabalho (inclui PR-2 e PR-1.5) → **PR-7** Gantt como 3ª sub-visão.
5. **PR-3** Handoffs à luz → **PR-4** ondas/tamanho → **PR-5** SLA+undo.

## 6) Métricas de aceite ([W2] screenshot)
- `/forja` → 301 → `/forja/aprovacoes`; a mesa lista plano/modificação/design/proposta com artefato no centro; a/d/x decidem; Devolver exige comentário; decisão some da fila, tem Desfazer 6s e audita.
- Placar mostra os 5 papéis com custo/quota, critique médio e retrabalho medidos; papel sem sinal >24h com alerta e ação `verificar`.
- Topnav: 3 grupos rotulados (Trabalho = Aprovações · Trabalho · Roadmap até o PR-7 absorver), ativo destacado, badge vivo em Aprovações. Sem aba Triagem.
- `/forja/trabalho`: Lista abre com TODAS as tasks em rank (P0 no topo, bloqueado no fim), pin persiste, épico expande sem duplicar; `v` circula Lista/Quadro/Gantt; Quadro alterna Pipeline F0→F3.5 × Execução (4 colunas canon); Gantt agrupa por módulo, linha do hoje, arrasto reagenda só o prazo.
- `/forja/handoffs` lista os 6 estados com `stale` e `gateConflito` visíveis sem abrir a aba MCP.

## 7) Estado do F1 (protótipo — o que já está pronto como referência)
- **Build exportado**: `prototipo-ui/cowork/forja/` (page/aprova/data/mcp/integra + css). Rota no app único: sidebar → Forja.
- **Testes executados** (T1–T12, recibos no charter): nav 3 grupos · mesa 4 tipos + decisão real de proposta · rank/pin/épico · quadro dono/faz/sai + filtro papel · SLA/undo · encerrar onda + carry · conceito fase×status corrigido contra @main · fusão total (25 tasks, OwnerSeal, KPI-filtros, 4 colunas) · Gantt (23 barras, 1 vencida, drag de prazo).
- **Docs no inbox**: `FORJA-COCKPIT-CHARTER-V2-PROPOSTA-2026-08-08.md` (definições + contrato + T1–T12) · manual de gestão com UC-01..12 e grade (em `_arquivo/relatorios/`, referência de leitura).

## 8) Relato da sessão (2026-08-08) — decisões do [W], em ordem
1. Diagnóstico Forja × tracker de mercado → rank **híbrido** ratificado (score + pin).
2. Leva 1 (rank/pin · roll-up de épico · massa · autocomplete) aplicada.
3. Topnav agrupada; produção conferida no @main → 9 (depois 10) itens, dupe Backlog×Tasks, Handoffs enterrado, Roadmap novo VAZIO em produção.
4. Pivô da landing: **Mesa de Aprovações** (pessoa/artefato/aval), Triagem absorvida como tipo Proposta; placar de agentes (heartbeat/custo/critique/retrabalho).
5. Conceito corrigido contra o canon: fase F0→F3.5 SÓ pra pipeline de tela; resto usa status canon (dot Stripe); F4 fora do board.
6. Tarefas copiada da produção → depois **fusão total**: UMA Lista + UM Quadro + UM drawer; componente duplicado aposentado.
7. [W] removeu o filtro de frente (sempre todas) e mandou copiar o **Gantt** → 3ª sub-visão com reschedule só de prazo (B2).
Limite conhecido do F1: dados mockados; datas do Gantt são sintéticas (derivadas de status) até `due_date` real; decisões da mesa não persistem além da sessão.
