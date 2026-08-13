---
date: "2026-08-13"
time: "20:56 BRT"
slug: espelho-cowork-medir-vs-consertar
tldr: "Três PRs no espelho Cowork (#5754/#5757/#5758). O medidor de frescor media a si mesmo: o --export-from escrevia antes de medir, e o \"11 medidos, 0 stale\" que publiquei era eco do meu próprio export — 2 arquivos ESTAVAM stale. Separado em --snapshot-from e provado contra o vivo por hash. No caminho construí ferramenta lendo de lugar que [W] baniu em 07-01 e que eu citara na mesma sessão: revertido (LC-19 n+2). Organizar virou achado: 13 dos 15 soltos na raiz eram duplicata, 7 DEFASADAS."
prs: [5754, 5757, 5758]
decided_by: [W]
related_adrs:
  - 0374-emenda-0315-espelho-cowork-e-rota-prevista
  - 0324-frescor-espelho-cowork-dispatch-sla-limite-plataforma
  - 0325-import-prototipo-designsync-pull-direto
next_steps:
  - "[W] DECIDE — conflito da ADR 0374: ela manda exportar pelo raw.content do get_file e proíbe transcrever, mas 173 dos 191 arquivos voltam INLINE (só 18 passam de 64KB e são persistidos em disco). A rota canônica é incumprível em 91% do acervo, e os 15 que desceram no #5743 eram todos pequenos = todos transcritos. Registrado no corpo do #5757. Chip aberto."
  - "[W] DECIDE — os 101 arquivos que o Cowork já arquivou (69 .tsx + 32 sob _arquivo/) seguem no espelho: 0 consumidores, 0 efeito em medição, 840KB. Remover é clareza, não conserto. Chip aberto."
  - "[W] DECIDE — related_us do Sells/Caixa: o charter-us-lint (advisory) ficou vermelho porque MEU toque acordou dívida grandfathered. NÃO inventei a US: ela não existe no SPEC e a tela está em rascunho aguardando sua aprovação pra virar live."
  - "113 arquivos do espelho seguem UNCHECKED contra o vivo — o --sla reporta LAST-PARTIAL (honesto) em vez de fingir FRESH. Começar pelos >64KB, que são fiéis por construção. Chip aberto."
  - "Skill aplicar-prototipo ainda instrui importar por ZIP (importar-bundle.mjs), caminho que [W] declarou morto. Os scripts ficaram de propósito (poda de capacidade é decisão [W]); o que precisa mudar é a instrução. Chip aberto."
  - "2 ponteiros podres PRÉ-EXISTENTES em resources/ (prototipo-ui/financeiro-app.jsx, prototipo-ui/fiscal-page.css) — confirmado com git cat-file que já não existiam antes do #5758. Chip aberto."
  - "Conferir ~10:36Z de 14/08: o watchdog G6 fica vermelho até a próxima run AGENDADA do mv-metabolismo. A causa já está corrigida na main (hasPagesDir passou a aceitar Array nos #5728/#5741); a run que falhou rodou em commit anterior ao fix. Não é required."
---

# Espelho Cowork — o medidor que media a si mesmo

## Estado MCP no momento do fechamento

Consultado agora, e o resultado é ele mesmo um dado:

| tool | resultado |
|---|---|
| `cycles-active` | **Nenhum cycle ATIVO em COPI** |
| `whats-active` | ⚠️ **CEGO** — "pipeline de ingest SEM heartbeat fresco (fresh=0 · stale=0 · dead=95)". Ele mesmo avisa: *"NÃO assuma escopo livre"* |
| `sessions-recent limit:3` | os 3 mais recentes são de **jun/2026** (2026-06-23, 06-22, 06-21) — o índice não reflete os session logs de hoje |
| PRs desta sessão | #5754, #5757, #5758 — **todos MERGED** |

**Colisão conferida por git, já que o `whats-active` está cego** ([W] avisou no meio da sessão
que outra sessão trabalhava): dos 6 PRs abertos, só o **#5585** toca `prototipo-ui/` — e em
`CODE_NOTES.md`, que eu não toco. Ele não cita nenhum dos 13 arquivos removidos. **Sem colisão.**

## O que ficou no main

- **`--snapshot-from`** — mede sem escrever. O ciclo honesto passa a ser
  `get_file` → **medir** → `--compare --check` → só então `--export-from`.
- **`_stalePreExport`** no snapshot e no ledger — a rodada distingue *"estava em dia"* de
  *"acabei de arrumar"*.
- **Protocolo reconciliado** — a FASE −1 do `protocolo.config.mjs` lista só o caminho canônico;
  o ZIP saiu das fases (os scripts ficaram, com a razão anotada).
- **`FORA_DESTA_CONTA`** — constante que registra que Venda e Produto vêm de **outra conta de
  design** ([L]/[M]), então "não achei no espelho" ali é origem externa, não drift.
- **13 duplicatas removidas** da raiz de `prototipo-ui/` + 28 ponteiros vivos reapontados.
- **Limite do `cowork-ssot-guard` escrito no cabeçalho dele**, com o número: ele varre só dentro
  de `prototipo-ui/`, e o FP da regra sintática que fecharia o buraco foi medido e **reprova**
  (24 hits, ~5 FP por construção, 19 cópias declaradas).

## Verificado depois do merge

Browser, não olho: os 13 removidos seguem **200 OK** (servidos do espelho), **0 imports
quebrados**, **0 erros JS**, app monta com **885 elementos**, sidebar `oklch(0.21 0.025 295)`
dark-fixo canon, `window.JanaPage` existe. Único 404 é o `_ds_bundle.js`, bundle compilado do DS
que o repo não tem — já documentado, com fallback.

Gates locais na main mergeada: `cowork-ssot-guard` OK · `ancora-guard` OK (**209 charters**,
âncoras no lugar fixo e vivas) · `protocolo.config --selftest` OK · suíte do frescor **109
asserts**.

## O que eu errei (está no §5 e no ledger)

- **LC-19 n+2** — construí `--snapshot-from-tree` lendo do lugar banido, tendo **citado o guard
  que o proíbe na mesma sessão**. Medi o repo inteiro e não medi a decisão. [W] cortou em uma
  linha; o CI estava verde.
- **LC-08 n+4** — afirmei que 3 arquivos da raiz eram âncora de charter, medindo o **nome** e não
  o path. Os `related_prototype` já estavam certos; o defeito era o `visual_source` ao lado.
- **Placar inflado, corrigido a [W]** — o *"11 medidos, 0 stale"* que publiquei antes era eco do
  meu próprio export.
