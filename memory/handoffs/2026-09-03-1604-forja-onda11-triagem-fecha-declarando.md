---
date: "2026-09-03"
time: "16:04 BRT"
slug: "forja-onda11-triagem-fecha-declarando"
tldr: "A Onda 11 (Triagem) fechou SEM criar tela: medido, a fonte não monta FjTriagemView e Aprovações não contém a Triagem (filtros diferentes; o slot `Proposta` já é `pending_approval`, o estado posterior). [W] decidiu fechar declarando. PR #6683, merge humano. O casos-gate cobrou uma dívida herdada do #6591 (last_run stale) — revalidada e consertada no mesmo PR."
decided_by: ["W"]
prs: [6683]
related_adrs:
  - "0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias"
  - "0374-emenda-0315-espelho-cowork-e-rota-prevista"
  - "0264-governanca-executavel-trio-dominio-e2e"
next_steps:
  - "Merge do #6683 é humano (R10 / ADR 0283) — CI verde exceto o watchdog de crons, que é herdado"
  - "Decisão de design devolvida ao Cowork: a mesa de Aprovações ganha duas faixas (decisão × enriquecimento), ou a Triagem segue superfície própria no build?"
  - "Watchdog de crons vermelho por 2 achados da Jana/RAGAS (canary falhando + baseline parado 64d) — não é deste PR, precisa de dono"
  - "#6671 (regeneração do bundle Cowork) segue aberto: enquanto não desce, fidelidade de qualquer view da Forja que não esteja no espelho é inconclusiva"
---

# Forja Onda 11 (Triagem) — o desfecho foi não construir

**PR:** [#6683](https://github.com/wagnerra23/oimpresso.com/pull/6683) (aberto, **merge é humano** — R10 / ADR 0283)
**Branch:** `claude/forja-onda11-triagem-desfecho` · 3 commits (desfecho · session log · fix do G-6)

## Estado no momento do fechamento

⚠️ **Sem snapshot MCP.** O `brief-fetch` do SessionStart caiu em fallback ("servidor MCP não
respondeu no tempo") e **nenhuma tool MCP do oimpresso está disponível nesta sessão** — conferido
por busca de tools, não presumido. O protocolo ([ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md))
pede `cycles-active` + `my-work` + `sessions-recent` + `decisions-search`. **Não consegui rodá-los,
e não vou fabricar o snapshot.** O que segue é medido em git/gh, que responderam.

- `origin/main` = `0907e16775` no momento do branch; base 0/0 no push.
- Sessões de Forja em voo (por `git branch -r`): `forja-onda7-saude`, `forja-onda6-aprovacoes`,
  `forja-onda10-integrador-medicao`, `forja-onda9-changelog-medicao`, `forja-onda5-quadro-2eixos`,
  `forja-onda6-gantt-replica`, `forja-trabalho-lista-a11y`, `pedido-regenerar-bundle-forja`.
  **Não rodei `whats-active`** (a tool não existe nesta sessão) — mas este PR só toca 3 arquivos e
  nenhum é compartilhado com aquelas ondas.

## A decisão, e por que ela não era nenhuma das duas opções do enunciado

O pedido oferecia: *"destino próprio"* ou *"já absorvida por Aprovações"*. **As duas caem na medição.**

| pergunta | medido | fonte |
|---|---|---|
| Foi absorvida? | **Não.** `McpTask::scopeTriage()` = `owner IS NULL OR priority IS NULL OR status='backlog'`; `ForjaAprovacoesService::fila():79` = `status='pending_approval'`. Nenhum é subconjunto do outro | código |
| É destino próprio? | **Já não é aba** — topnav vivo (`Resources/menus/topnav.php`) = 6 destinos, nenhum é Triagem (saiu na Onda 2, 09-02) | código |
| Então onde vive? | Landing `/forja` (`routes.php:267`) + destino de 4 redirects 301 (`/triage`, `/inbox`, `/burndown`, `/`) + alvo do botão primário `Novo issue` (`ForjaHub.tsx:141`) | código |

**O que fecha:** o slot `Proposta` do protótipo **já está ocupado** aqui — é `pending_approval`, o
estado *posterior* à triagem (`Aprovacoes/Index.tsx:38-39`). Triagem é o **F0**, Aprovações é o
**gate**. Duas etapas do mesmo funil, não duas telas para a mesma coisa.

A perda já estava medida em dois lugares canônicos desde 01-02/09 (`PARIDADE §9.7` e
`UC-FORJA-02`): *"Aprovações abre vazia enquanto a Triagem tem 3 tickets vivos"*. Faltava
**conectar isso à Onda 11 e fechar** — foi o que o PR fez, sem tocar 1 byte de `.tsx`/`.php`/CSS.

## Armadilhas que a próxima sessão herda

1. **A numeração de onda colide entre TRÊS programas.** No pacote de export: Onda 6 = Aprovações,
   Onda 11 = Triagem. Na `PARIDADE §11`: Onda 3 = Aprovações, Onda 6 = Gantt. E existe ainda a
   "Onda 11 = revogação de /project-mgmt" ([#6617](https://github.com/wagnerra23/oimpresso.com/pull/6617)).
   O handoff de hoje 11:20 registra que **por dois dias seguidos** alguém inferiu "a onda X destrava
   Y" pelo título e errou. **Confirme a dependência medindo o PR, nunca pelo número.**
2. **O `casos-guard` é diff-aware — rodá-lo antes de commitar dá falso verde.** Rodei, deu 101
   violações e "sem violações novas"; o CI deu 102 e reprovou. O arquivo ainda não estava no diff.
   Sonda que lê git não enxerga working tree (§5 2026-08-20). **Commite, depois sonde.**
3. **O job do `casos-gate` tem 4 modos**, não 1: `--selftest-diff-aware`, `--report`, sem-arg, e
   `--check-baseline-shrink` contra o baseline de `origin/main`. Rodar um e declarar verde é a
   §5 2026-07-28 — que eu citei no corpo do PR **enquanto a cometia**.
4. **Dívida de `last_run` se acumula em silêncio e cai em quem toca o arquivo.** O #6591 mexeu no
   `Cockpit.tsx` (2026-09-03) e não bumpou o `last_run` (2026-09-02). O gate só acordou quando este
   PR tocou o `casos.md`. Não é injustiça — é o desenho (§5 2026-07-27) — mas quem herdar deve
   **revalidar de verdade** e escrever o que a revalidação cobre, não carimbar a data.

## O que ficou aberto, e de quem é

| aberto | de quem | nota |
|---|---|---|
| Merge do #6683 | **[W]** | R10 / ADR 0283 |
| Aprovações ganha 2 faixas (decisão × enriquecimento)? | **[W] + Cowork** | pergunta devolvida em `prototipo-ui/CODE_NOTES.devolutiva-cowork-onda11-triagem-2026-09-03.md` |
| `jana-ragas-canary.yml` vermelho + `jana-ragas-real-baseline.json` parado 64d | **sem dono** | derruba o watchdog G6 em **todo** PR da janela (visto em `forja-onda1`, `forja-onda7`, `manufacturing-lane-mysql`). Advisory, mas é ruído constante |
| Bundle Cowork sem regenerar | **Cowork** | [#6671](https://github.com/wagnerra23/oimpresso.com/pull/6671). 157 arquivos só no vivo; `forja-triagem.jsx` é um deles |

## Para quem retomar a Onda 11

**Não há onda de re-skin pendente para a Triagem.** Reabrir exige **uma** das duas: a fonte passar a
montar `FjTriagemView`, ou uma onda de **construção** que faça `ForjaAprovacoesService::fila()`
projetar também `McpTask::triage()` e flipe a landing `/forja` + os 4 redirects. A segunda custa
reescrever 4 UCs e é **decisão [W]**, não conserto de layout.
