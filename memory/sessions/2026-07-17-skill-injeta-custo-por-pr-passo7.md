---
date: "2026-07-17"
hour: "20:53 BRT"
topic: "Custo por PR — passo 7 da skill governance-pr-summary injeta o bloco no corpo do PR (pós-merge, advisory); fecha o item 2 do mandato \"fazer o número chegar no PR\""
authors: [C]
prs: [4491]
related_adrs:
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0314-poda-gates-onda-2-lei-fusoes
---

# Passo 7: o número do custo chega no corpo do PR

## TL;DR

A skill `governance-pr-summary` ganhou o **passo 7**: pós-merge, roda `agent-cost-per-pr.mjs --pr <N>` local e injeta o bloco de custo (`<!-- agent-cost-per-pr -->`) no corpo do PR — fecha o **item 2** do mandato ("fazer o número chegar no PR"). **[PR #4491](https://github.com/wagnerra23/oimpresso.com/pull/4491) MERGED** (só a skill, +79/−2). Item 1 (a ferramenta `--pr`) já landou via **#4488**; mecanismo integrado **provado ao vivo** (`--pr 4491` → bloco com número real). Advisory, USD-only, zero R$.

## Contexto

Registro do **item 2** do mandato custo-por-PR — *"fazer o número chegar no PR"*. A ferramenta `scripts/governance/agent-cost-per-pr.mjs` já imprimia o bloco pronto (`renderPrBlockMd`, marcador `<!-- agent-cost-per-pr -->`), mas **nada a invocava no fluxo real**. Fechado em **[PR #4491](https://github.com/wagnerra23/oimpresso.com/pull/4491) (MERGED)** — só a skill `governance-pr-summary` mudou (+79/−2). O **arco da ferramenta** (unidade=sessão, cobertura de alocação, revisão adversarial) está no session log irmão `2026-07-17-custo-por-pr-sessao-unidade-cobertura-alocacao.md` — este não duplica, complementa.

## O que entrou

Passo 7 da skill: **após o merge**, rodar `node scripts/governance/agent-cost-per-pr.mjs --pr <N>` **local** e injetar o bloco no corpo do PR via `gh pr edit --body-file`. Decisões de desenho, todas verificadas empiricamente (não assumidas):

1. **LOCAL, não CI** — o CI não vê o JSONL do Claude Code (`~/.claude/projects`, G5). Colar agregado do cron seria teatro (não é o custo *deste* PR).
2. **PÓS-merge, não "ao abrir"** — correção à redação do mandato. O tool lê `gh pr list --state merged` + filtra `mergedAt`; um PR **aberto** nunca casa → sairia sempre `não medido`. O número só materializa depois do merge. Provado rodando o tool contra PR aberto (degrada) vs mergeado (número).
3. **Guard do marcador é load-bearing** — a versão de main sem `--pr` cospe o relatório humano (`═══`); o guard `case … "<!-- agent-cost-per-pr -->"*)` só injeta se a 1ª linha for o marcador canônico, senão `*)` = no-op. Sem o guard, colaria o relatório inteiro.
4. **Idempotente** — `awk` **ancorado** (`^<!-- … -->[[:space:]]*$`) corta o bloco antigo e re-anexa; prefixo **byte-idêntico em 3 passadas**, exatamente 1 bloco; menção inline do marcador (prosa/code-fence) **sobrevive** (sem truncagem). O awk também segura linhas em branco → sem *creep* de blank line a cada push.
5. **Degrada honesto** — PR aberto / sem sessão casada → `_sem sessão local casada — não medido (G1/G3)_`, sem inventar número.
6. **Advisory, Tier-0 limpo** — RELATO, não gate (ADR 0271/0314); USD/tokens, **zero R$** por construção do tool.

## Self-degrading (a dependência do item 1)

Na hora do commit, o modo `--pr`/`renderPrBlockMd` (item 1) **não estava em main** — vivia em branch de landing não-mergeada. A skill nasceu **self-degrading**: enquanto `--pr` não está no checkout, o guard cai no `*)` e o passo 7 vira no-op; ativa sozinho quando o item 1 landa. Por isso o #4491 pôde mergear independente da ordem (autorização [W] "PR agora contra main").

## Integração — provado ao vivo

Durante o fechamento, **descobri que o item 1 JÁ landou**: **[PR #4488](https://github.com/wagnerra23/oimpresso.com/pull/4488) (MERGED)** — sessão irmã (`silly-varahamihira`) rebaseou os 5 commits da ferramenta no main fresco. Então **não criei PR duplicado** da branch `competent-bassi` (seria re-landar o mesmo código, conflito append-only). Confirmado em `origin/main`: tool tem `--pr`/`renderPrBlockMd`; skill tem o passo 7.

Prova end-to-end com a **ferramenta real do main**: `--pr 4491` → bloco com número real (matched por `citacao`, sinal válido). O mecanismo completo (tool #4488 + skill #4491) está **vivo em main**.

## CI

- **Todos os required verdes** (#4491): 76 pass, 2 skip.
- Único vermelho: `module-grades-gate` — **advisory** (demovido de required pela ADR 0314 D-1) e **alheio ao meu diff** (reportou `KB 77→76 (−1)`, 35 módulos estáveis; meu PR não toca KB). É drift live-vs-baseline pré-existente no main. **Não mexi** no `module-grades-baseline.json` pra silenciar — mascararia drift de outro dono (lápide §5 "guard/baseline que engole divergência").

## Lições

- **Verificar a propriedade certa, empiricamente**: o mandato dizia "ao abrir"; medi o comportamento do tool (`--state merged`) e corrigi pra "pós-merge" antes de escrever a instrução. Assumir "ao abrir" teria feito o passo 7 sempre dizer "não medido".
- **Trabalho de feature vai no worktree** (R8/ADR 0233): parte desta sessão rodou por engano no checkout MAIN (`cd /d/oimpresso.com`) — o `--delete-branch` restaurou o serving pra `main`, sem dano, mas o registro foi feito no worktree isolado, correto.
- **Não re-landar trabalho de sessão paralela**: git é a ponte; #4488 já cobria o item 1 — confirmar antes de criar PR evitou duplicação.

Off-cycle · worktree `suspicious-clarke-967365` @ origin/main.
