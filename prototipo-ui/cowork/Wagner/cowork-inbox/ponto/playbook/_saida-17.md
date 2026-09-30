---
sessao: "17"
titulo: "data-contract no .tsx — saída da thread"
autor: "[CL]"
criado: 2026-09-28
base: ad23c9ff9
thread: 17-data-contract-no-tsx.md
veredito: "concluída — 20 de 26 ids no main (14 em #8088/#8090/#8091/#8096, 1 no #8114, 1 no #8115, 2 no #8113 + #8119, 2 renomeados no #8120); 6 fora por regra; smoke em prod 2026-09-29: 5 de 5 ids medidos no DOM, 2 de BancoHoras/Show só no bundle (sem colaborador no business)"
---

# _saída 17 · `data-contract` no `.tsx`

Recibo único da thread, atualizado a cada PR.

## Como foi medido

- **Os 26 ids** saem do build: `grep -o 'contrato="[^"]*"' prototipo-ui/cowork/Wagner/ponto-telas.jsx | sort -u` → **26**, protótipo @ `2e3f8adb4e`. Bate com a tabela da thread e com o `_PATCH-INDICE` §2.
- **Colisão antes de editar** (a thread manda parar se outro PR toca `Pages/Ponto`): em 2026-09-28 havia três abertos — #8079 (`Aprovacoes/Index.tsx`, `Escalas/Index.tsx`), #8077 (`BancoHoras/Show.tsx`) e #8078 (`Configuracoes/Index.tsx`) — e uma sessão acabara de editar `Intercorrencias/Create.tsx`. A gerente reagrupou os PRs por **arquivo livre**, não por módulo.
- **G-6 do casos-gate:** mudar o `.tsx`, mesmo só com um atributo, deixa o `.casos.md` da tela velho (§5 2026-07-27). Cada PR bumpa só o `last_run` para 2026-09-28, o mesmo valor que os PRs da thread 27 (#8093, #8097) usam; assim as edições são idênticas e não geram conflito. Medido nas 9 telas: `data-contract` aparece 0 vezes nos casos e nos testes que citam os UCs, e não há snapshot de DOM. O único teste que renderiza tela (`tests/js/ponto-colaboradores-redacao.test.tsx`, Colaboradores/Index) consulta por `getByText`, que o atributo não altera. A tabela por tela está no corpo de cada PR. Por causa disso, o PR-3b ficou com 9 arquivos, um acima do teto.
- **PR-4 depois do #8078:** o gap de Configurações/Index foi medido antes dele; o PR-4 re-mediu as regiões no arquivo novo e registrou no gap (seção datada) e no map que as 4 partes "dado quebrado" passaram a paridade.
- **PR-3 dividido em 3a/3b:** Colaboradores + Importações somam 10 arquivos, acima do teto de 8.
- **Nenhum `.tsx` do Ponto** tinha `data-contract` nessas regiões, logo o "PARAR SE — o vivo já tem outro string" não se aplicou.
- **Granularidade:** no protótipo o id fica no `<Card>` da região; no vivo também, no `<Card>` do DS (`Components/ui/card.tsx` repassa `...props` ao `<div>`, então o atributo chega ao DOM).
- **Map:** a parte correspondente passa de `vivo.ancora: false` para `vivo.ancora: "<id>"` — é o que torna a âncora verificável.
- **Mordida provada:** tirado o atributo do `Intercorrencias/Index.tsx` com o map declarando a âncora, `design-code-map-check --check --strict` sai **rc=1** com `[DRIFT] … data-contract="intercorrencias-intercorrencias" NÃO existe`. Restaurado, rc=0.

## PRs

| PR | ids | arquivo `.tsx` | parte do map | estado |
|---|---|---|---|---|
| PR-1 #8088 | `intercorrencias-intercorrencias` | `Intercorrencias/Index.tsx` (`<Card>` da lista) | `intercorrencias-index.map.json` · `lista-de-intercorrencias` | mergeado 2026-09-28 |
| PR-1 #8088 | `bancohoras-saldos-por-colaborador` | `BancoHoras/Index.tsx` (`<Card>` da tabela) | `banco-horas-index.map.json` · `saldos-por-colaborador` | mergeado 2026-09-28 |
| PR-2 #8114 | `aprovacoes-fila-de-aprovacoes` | `Aprovacoes/Index.tsx:330` (`<Card>` da fila) | `aprovacoes-index.map.json` · `fila-de-aprovacoes` | mergeado 2026-09-29 |
| #8115 (forma de Escalas, `claude/ponto-escalas-forma`) | `escalas-escalas-cadastradas` | `Escalas/Index.tsx` (`<Card>` da lista) | `escalas-index.map.json` · parte da lista | mergeado 2026-09-29 — o id foi gravado no PR da sessão que aplicou a forma do protótipo, como combinado em 2026-09-28, para não haver dois PRs no mesmo arquivo |
| #8113 + PR-2b #8119 | `bancohoras-historico-de-movimentos` · `bancohoras-ajuste-manual` | `BancoHoras/Show.tsx:179` · `:270` (gravados pelo #8113, mergeado) | `banco-horas-show.map.json` · `historico-de-movimentos` · `ajuste-manual` (declarados no #8119) | mergeado 2026-09-29 (#8113 em 2026-09-28) |
| PR-3a #8090 | `colaboradores-colaboradores` | `Colaboradores/Index.tsx:101` | `colaboradores-index.map.json` · `lista-de-colaboradores` | mergeado 2026-09-28 |
| PR-3a #8090 | `colaboradorform-configuracao-de-ponto` | `Colaboradores/Edit.tsx:85` (card único "Identificação", paridade de campos) | `colaboradores-edit.map.json` · `configuracao-de-ponto-campos` | mergeado 2026-09-28 |
| PR-3b #8091 | `importacoes-historico-de-importacoes` | `Importacoes/Index.tsx:74` | `importacoes-index.map.json` · `historico-de-importacoes` | mergeado 2026-09-28 |
| PR-3b #8091 | `importacoes-upload-do-arquivo` | `Importacoes/Create.tsx:74` (card da página própria) | `importacoes-create.map.json` · `tela-propria-ou-card-inline` | mergeado 2026-09-28 |
| PR-3b #8091 | `importacoes-dados-do-arquivo` · `importacoes-resumo-do-processamento` | `Importacoes/Show.tsx:91` · `:109` | `importacoes-show.map.json` · `dados-do-arquivo` · `resumo-do-processamento` | mergeado 2026-09-28 |
| fora (a nascer) | `colaboradorform-dados-do-hrm` · `importacoes-diagnostico-do-processamento` · `importacoes-amostra-de-erros` · `relatorios-pedidos-desta-sessao` | — | gap: ausente no vivo | sem id até a região nascer |
| fora (sai do protótipo) | `relatorios-gerar` | — | `D-REL-FLUXO`: filtros globais; o wizard sai do protótipo (R2) | sem id; o protótipo remove |
| PR-4 #8096 | `configuracoes-regras-clt-reforma-trabalhista` · `configuracoes-banco-de-horas` · `configuracoes-rep-e-imutabilidade-de-marcacoes` · `configuracoes-afd-importacao-esocial` | `Configuracoes/Index.tsx:111` · `:131` · `:148` · `:178` | `configuracoes-index.map.json` (4 partes; status re-medido pós-#8078) | mergeado 2026-09-28 |
| PR-4 #8096 | `configuracoes-cadastrar-novo-rep` · `configuracoes-reps-cadastrados` | `Configuracoes/Reps.tsx:85` · `:140` | `configuracoes-reps.map.json` | mergeado 2026-09-28 |
| fora (a nascer) | `configuracoes-ia-do-ponto` | — | gap: ausente no vivo (`D-CFG-IA` pendente) | sem id |
| PR-5 #8120 | `intercorrencias-card` → `intercorrencias-dados-da-ocorrencia` · `escalaform-card` → `escalaform-dados-da-escala` | `Intercorrencias/Create.tsx:241` (card "Dados da ocorrência") · `Escalas/Form.tsx:89` (card "Dados da escala") | sem âncora de map: as partes dos dois maps são por campo | mergeado 2026-09-29; renomeado nos dois lados, espelho subido ao Cowork e re-verificado no ledger |

## Portões do PR-1

- `node scripts/governance/design-code-map-check.mjs --check --strict` → rc=0; âncora estável 84 → **86**/629.
- `contrato-de-tela` (`--map --check`, `--contract`) → limpo.
- typecheck e ESLint: este worktree não tem `node_modules`; o veredito é o do CI.
- Diff dos `.tsx`: 1 linha cada, só o atributo.
- `contrato-de-tela`: `contrato-de-tela.test.mjs`, `--preflight origin/main` (depois do rebase), `--anti-tautologia`, `--omission <merge-base>` e o laço de contratos sem `EXEMPLO` → todos limpos.
- **Zero mudança visual (estático):** o único CSS de produção que usa `data-contract` como seletor é `.arq-page [data-contract=abas]` (`resources/css/cowork-arquivos-bundle.css:129-130`), escopado a Arquivos. Nenhum seletor casa os dois ids novos.
- **O atributo chega ao DOM:** em prod (2026-09-28, `/ponto`), o precedente idêntico `<Card data-contract="painel-fila-aprovacoes">` renderiza `DIV[data-contract=painel-fila-aprovacoes]`. O `<Card>` do DS repassa o atributo.
- **Antes, medido em prod** (biz do usuário logado, DOM estável em duas leituras): `/ponto/intercorrencias` → 0 `data-contract` fora do sidebar; card da lista 545×340 em (24,358). `/ponto/banco-horas` → 0; card da tabela 544×310 em (24,705). **Depois:** a medir pós-deploy — mesmo card, mesma geometria, com o atributo.

## PR-2 (2026-09-28)

- **Colisão:** `whats-active` + `gh pr list --state open`. `Aprovacoes/Index.tsx`: a sessão do W9 (`claude/ponto-nav-13-abas`) só troca o header e os imports; o card da fila não é tocado. `Escalas/Index.tsx`: a sessão da forma do protótipo reescreve o corpo e já grava `escalas-escalas-cadastradas` no PR dela. `BancoHoras/Show.tsx`: #8113 aberto, logo fica para o PR-2b.
- **Medição (Aprovações):** 5 UCs no `.casos.md` (UC-PAPR-01..05), citados por `JornadaWorkflowContratoTest.php` e `tests/js/ponto-aprovacoes-lote-dialogo.test.tsx`. `data-contract` aparece 0 vezes no casos e nos 2 testes; o teste JS não usa snapshot. O `last_run` já era `2026-09-28` no main, então o G-6 não pediu bump. Existe baseline de pixel (`PixelBaselineTest/it_Ponto_Aprovacoes…snap`); um atributo não muda pixel, e não se gera `.snap` (ADR 0411).
- **Portões:** `design-code-map-check --check --strict` → rc=0, âncora estável **99**/668 no main de hoje. `contrato-de-tela`: `.test.mjs`, `--preflight origin/main`, `--anti-tautologia`, `--omission <merge-base>` → limpos.

## PR-2b #8119 (2026-09-29)

- O #8113 (forma do detalhe, mergeado em 2026-09-28) já gravou no `BancoHoras/Show.tsx` os dois ids, com a string idêntica à do `ponto-telas.jsx:380` e `:395`. O PR-2b só declara as duas âncoras no map; nenhum `.tsx` muda, e por isso o G-6 não é acionado.
- `design-code-map-check --check --strict` → rc=0. Mordida: com `bancohoras-ajuste-manual` trocado no `.tsx`, rc=1 com `[DRIFT]`; arquivo restaurado, hash conferido.
- **Fora do escopo, registrado:** o #8113 também gravou `bancohoras-colaborador`, `bancohoras-kpis-do-extrato` e `bancohoras-legal`, que não existem no protótipo (`grep -c 'contrato="<id>"' ponto-telas.jsx` = 0 para os três). No map ficam `ancora: false`; para virarem âncora, o par nasce no protótipo, pelo Cowork.

## PR-5 #8120 (2026-09-29)

- **Nome da região:** os dois gaps (`intercorrencias-create-gap.md`, `escalas-form-gap.md`) ganharam uma seção que nomeia a região — a condição da thread para renomear. O nome segue o padrão `<tela>-<título do card>`, com o título fixo do vivo (no protótipo o título é dinâmico, e foi isso que gerou o id feio).
- **Dois lados no mesmo PR:** `ponto-telas.jsx:299` e `:529` × `Create.tsx:241` e `Form.tsx:89`, string idêntica.
- **Subida ao Cowork** (opt-in [W] 2026-09-29): antes de escrever, o `ponto-telas.jsx` vivo era idêntico ao espelho do main (71.747 bytes). `DesignSync.finalize_plan` + `write_files` com `localPath`; leitura de volta idêntica ao espelho editado (71.773 bytes). Ledger por `--snapshot-from` + `--compare --check --ledger` (1 sync, rodada parcial declarada); `pendentes-cowork --registrar-envio`. `cowork-mirror-freshness --unverified --check`: `mexido-depois` 1 → 0.
- **Efeito nos maps:** o hash de conteúdo do protótipo mudou (`e4d0b5a3707e` → `e8b74d75e0d5`); os 15 maps do Ponto receberam só o `prototipo_sha` novo. `design-code-map-check --check --strict` rc=0.
- **Histórico preservado:** `17-data-contract-no-tsx.md`, `20-gap-intercorrencias.md`, `_PATCH-INDICE-2026-09-14.md` e `github.md` seguem citando os ids feios — são registro datado de quando nasceram.

## Smoke em produção (2026-09-29)

- **Deploy medido:** run do `deploy.yml` em `f37c125f35`, concluído com sucesso; contém #8114, #8115, #8119, #8120 e #8135 (conferido por `git merge-base --is-ancestor`). Os deploys de `e1b5dd63ad`, `06a4e21091` e `971c2fd789` foram cancelados por pushes mais novos.
- **Como:** navegador interno, sessão do business logado (WR2 Sistemas). Em cada tela, sonda `[data-contract]` sem os ids `sb-*` do sidebar, lida duas vezes com 3 s de intervalo; as duas leituras bateram em todas.
- **Sonda cega na 1ª tentativa, corrigida:** o 1º filtro descartava elementos dentro de um ancestral de sidebar e deu 0 em Aprovações; a contagem sem filtro mostrou o id presente. Os números abaixo são da sonda corrigida.

| tela | id | resultado no DOM |
|---|---|---|
| `/ponto/aprovacoes` | `aprovacoes-fila-de-aprovacoes` | presente, `display:flex`, 544×340, card da fila (estado vazio: sem pendência no filtro padrão) |
| `/ponto/intercorrencias/create` | `intercorrencias-dados-da-ocorrencia` | presente, `display:flex`, 842 px de altura, card "Dados da ocorrência" com 14 campos e a nota "Salvar cria um rascunho…" |
| `/ponto/escalas/create` | `escalaform-dados-da-escala` | presente, `display:flex`, 553 px de altura, card "Dados da escala" com 7 campos |
| `/ponto/escalas` | `escalas-escalas-cadastradas` | presente, `display:block`, 496×360, card "Escalas cadastradas (0 no business)" |
| `/ponto/banco-horas` | `bancohoras-saldos-por-colaborador` | presente (lista vazia) |

- **BancoHoras/Show não medido no DOM:** o business não tem colaborador no Ponto (`/ponto/colaboradores` e `/ponto/banco-horas` vazios), então não há detalhe para abrir, e criar dado em produção para o teste está fora de questão. Medido no bundle servido: `build-inertia/manifest.json` → `resources/js/Pages/Ponto/BancoHoras/Show.tsx` → `assets/Show-WkseeyWP.js`, que contém `bancohoras-historico-de-movimentos` (1) e `bancohoras-ajuste-manual` (1). Controles do mesmo método: positivo `aprovacoes-fila-de-aprovacoes` no chunk de Aprovações = 1; negativo (id inventado) = 0; ids feios antigos no chunk de `Escalas/Form` e `Intercorrencias/Create` = 0. Isso prova que o código em produção carrega os ids; não prova a renderização.
- As larguras de 220 e 320 px refletem o painel estreito do navegador interno, não a tela real.
