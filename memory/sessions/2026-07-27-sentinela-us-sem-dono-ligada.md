---
date: "2026-07-27"
topic: "Doutrina da entrada única virou medição: a sentinela de US sem dono existia com Pest e ZERO invocadores há 33 dias — ligada (cron + flag no brief). O acervo real é 537 sem dono, não os ≥50 que o triage truncado sugeria"
authors: [C, W]
type: session
module: Jana
pii: false
related_adrs:
  - 0070-jira-style-task-management-current-md-removed
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
---

# Sentinela de US sem dono — ligada

## TL;DR

[W] formulou a doutrina: *"uma única entrada, session log, e sempre um ticket de backlog para um dono"* — e perguntou se era caso de pesquisar fundo e modificar módulo. **Não era.** Medidas as três pernas, duas já eram canon (entrada = chat, verificado 2026-07-16; session log = R12 + skill + hook) e a terceira tinha **máquina pronta e parada**: `mcp:tasks:unassigned` (US-INFRA-043, #3302, 24/06) com Pest, com `--json` escrito explicitamente *"pro Daily Brief"*, e **zero invocadores** por 33 dias.

Ligada em [#4862](https://github.com/wagnerra23/oimpresso.com/pull/4862) (MERGED 21:01 UTC): cron 06:45 BRT + `TasksSemDonoBriefLineService` injetando a flag no brief 6×/dia.

## O que foi medido (e o que a medição corrigiu)

| Pergunta | Fonte | Resultado |
|---|---|---|
| A sentinela é invocada? | `git grep "tasks:unassigned"` (sem corte, contado) | **14 ocorrências, 7 arquivos, 0 invocadores** — nada em Kernel/workflow/package.json/.claude |
| Quantas US sem dono? | tool MCP `triage` | ≥50 — **mas era teto da minha consulta** |
| Quantas de verdade? | comando em **prod** (SSH Hostinger) | **680 não atribuídas · 537 sem dono · mais antiga US-ACCO-001 (87d)** |
| Duplica régua existente? | leitura do irmão agendado | **Não** — `mcp:tasks:health-check` (06:20) mede *staleness*; esta mede *atribuição* |

**A correção do número importa.** Reportei "≥50" por vários turnos — sempre sinalizando que era teto, mas a magnitude real é ~10× maior. Só apareceu quando rodei o comando contra prod em vez de reusar o output truncado da tool. É a classe LC-08 em versão branda: a fonte estava certa, o **recorte** é que mentia.

## O que entrou

- `Modules/Jana/Services/TasksSemDonoBriefLineService.php` — injeta `🟠 US não atribuída: N (M sem dono) — mais antiga: US-XXX (Yd)` na seção FLAGS. **Fonte-única:** chama `detectarNaoAtribuidas()`, não reimplementa a regra (espelha `TasksHealthTool`→`scanStaleness()`).
- `app/Console/Kernel.php` — cron 06:45 BRT. Slot escolhido pelo canon de contenção, **verificado no Kernel** (o doc é de maio e o 06:35 já tinha sido ocupado desde então).
- Teste com **núcleos puros** (`formatar()`/`injetarEm()`), zero DB de propósito: `mcp_tasks` é compartilhada e no CT 100 roda contra MySQL real — assertar contagem de lá seria não-determinístico e limpar a tabela, destrutivo. **10/10 PASS no CI**, executado por nome.
- Correção de honestidade no SPEC: a US-INFRA-043 estava `status: done` com a acceptance #2 nunca entregue.

## O que NÃO entrou, e por quê

- **`--strict` (ratchet) desligado.** Com 680 pendências seria parede, não catraca — o anti-padrão `foundation-ratchet` do §5. O valor do cron é a **série** no log, que é o que sustenta a promoção depois com mordida provada ([ADR 0336](../decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)). Flip é [W].
- **`owner` obrigatório no `tasks-create`.** Gate na entrada seria hostil ao autor — a doença do G-2 já catalogada. Task nascer sem dono é legítimo; o que faltava era **cobrar depois**.

## Dúvida honesta sobre a própria entrega

Com 680, a flag corre risco real de virar **papel de parede**: número enorme e estável ensina o leitor a ignorar. Ela aponta o alvo (a mais antiga, com id e idade), que era a defesa contra virar ruído — mas 680 não dá senso de ação. Levantei a hipótese de que **o número acionável é o fluxo (quantas nasceram sem dono desde ontem), não o acervo** — e NÃO construí. Fica como observação pra [W] decidir depois de ver a linha viva por uma semana. Construir variação por impulso é exatamente o que o §5 proíbe.

## Contexto: não é achado isolado

Uma sessão irmã **do mesmo dia** ([handoff 14:45](../handoffs/2026-07-27-1445-orfaos-ligados-elo-hitl.md)) ligou **13 scripts órfãos** de governança (13→2) e mediu que *12 de 12 sentinelas agendados criavam ZERO task*. Meu PR é o **14º órfão da mesma campanha**, em população diferente (comando artisan, não `scripts/governance/*.mjs`). A contraparte humana já existe em review: `US-TR-309` (tela Triage lista órfãs) + `US-TR-310` (atribuir owner inline).

## Lições

1. **Antes de propor máquina nova, procurar a que já existe e está parada.** O pedido cheirava a "pesquisar e construir"; a resposta era um `git grep` contado. Regra já canon (`proibicoes.md` §"Sempre fazer" — LIGUE A MÁQUINA, 2026-07-26).
2. **Output truncado de tool é recorte, não medida.** `triage limit:50` devolveu 50 e eu carreguei "≥50" por turnos. O oráculo era o comando em prod.
3. **Conflito em arquivo derivado não se resolve escolhendo lado.** `memory/requisitos/Jana/SUPERFICIE.md` conflitou 3× (é regenerado por todo PR que toca `Modules/Jana`); as 3 resoluções foram re-rodar `module-surface.mjs Jana --write`. Escolher `ours`/`theirs` deixaria o índice mentindo sobre a árvore.
4. **Gate vermelho nem sempre acusa o que o nome diz.** O `baseline-tamper-guard` acusou 5 "afrouxamentos" que eu não causei: o main mergeou o #4879 (que ENCOLHEU o baseline) e meu branch carregava a versão de antes. Era **staleness, não adulteração**. O gate oferecia o trailer `BASELINE-GROW` como saída — não usei, porque carimbá-lo registraria no histórico uma intenção que não era a minha. O conserto honesto era trazer o main.
5. **`block-destructive` funcionou.** Barrou meu `--force-with-lease` no rebase; refiz como merge. A branch já estava publicada — o hook estava certo.

## Pointers

- PR: [#4862](https://github.com/wagnerra23/oimpresso.com/pull/4862) · US: `US-INFRA-043` ([SPEC Infra](../requisitos/Infra/SPEC.md))
- Irmã do mesmo dia: [handoff 14:45](../handoffs/2026-07-27-1445-orfaos-ligados-elo-hitl.md)
- Registro do cron: [AUTOMATIONS.md](../governance/AUTOMATIONS.md)
