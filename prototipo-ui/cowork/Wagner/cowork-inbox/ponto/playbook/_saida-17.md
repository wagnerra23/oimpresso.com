---
sessao: "17"
titulo: "data-contract no .tsx — saída da thread"
autor: "[CL]"
criado: 2026-09-28
base: ad23c9ff9
thread: 17-data-contract-no-tsx.md
veredito: "em andamento — 14 de 26 ids em PRs abertos (#8088, #8090, #8091, #8096); 6 fora por regra; faltam PR-2 (espera #8079/#8077) e os 2 ids feios"
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
| PR-1 #8088 | `intercorrencias-intercorrencias` | `Intercorrencias/Index.tsx` (`<Card>` da lista) | `intercorrencias-index.map.json` · `lista-de-intercorrencias` | aberto |
| PR-1 #8088 | `bancohoras-saldos-por-colaborador` | `BancoHoras/Index.tsx` (`<Card>` da tabela) | `banco-horas-index.map.json` · `saldos-por-colaborador` | aberto |
| PR-2 | `aprovacoes-fila-de-aprovacoes` · `escalas-escalas-cadastradas` · `bancohoras-historico-de-movimentos` · `bancohoras-ajuste-manual` | `Aprovacoes/Index`, `Escalas/Index`, `BancoHoras/Show` | — | espera #8079 e #8077 |
| PR-3a #8090 | `colaboradores-colaboradores` | `Colaboradores/Index.tsx:101` | `colaboradores-index.map.json` · `lista-de-colaboradores` | aberto |
| PR-3a #8090 | `colaboradorform-configuracao-de-ponto` | `Colaboradores/Edit.tsx:85` (card único "Identificação", paridade de campos) | `colaboradores-edit.map.json` · `configuracao-de-ponto-campos` | aberto |
| PR-3b #8091 | `importacoes-historico-de-importacoes` | `Importacoes/Index.tsx:74` | `importacoes-index.map.json` · `historico-de-importacoes` | aberto |
| PR-3b #8091 | `importacoes-upload-do-arquivo` | `Importacoes/Create.tsx:74` (card da página própria) | `importacoes-create.map.json` · `tela-propria-ou-card-inline` | aberto |
| PR-3b #8091 | `importacoes-dados-do-arquivo` · `importacoes-resumo-do-processamento` | `Importacoes/Show.tsx:91` · `:109` | `importacoes-show.map.json` · `dados-do-arquivo` · `resumo-do-processamento` | aberto |
| fora (a nascer) | `colaboradorform-dados-do-hrm` · `importacoes-diagnostico-do-processamento` · `importacoes-amostra-de-erros` · `relatorios-pedidos-desta-sessao` | — | gap: ausente no vivo | sem id até a região nascer |
| fora (sai do protótipo) | `relatorios-gerar` | — | `D-REL-FLUXO`: filtros globais; o wizard sai do protótipo (R2) | sem id; o protótipo remove |
| PR-4 #8096 | `configuracoes-regras-clt-reforma-trabalhista` · `configuracoes-banco-de-horas` · `configuracoes-rep-e-imutabilidade-de-marcacoes` · `configuracoes-afd-importacao-esocial` | `Configuracoes/Index.tsx:111` · `:131` · `:148` · `:178` | `configuracoes-index.map.json` (4 partes; status re-medido pós-#8078) | aberto |
| PR-4 #8096 | `configuracoes-cadastrar-novo-rep` · `configuracoes-reps-cadastrados` | `Configuracoes/Reps.tsx:85` · `:140` | `configuracoes-reps.map.json` | aberto |
| fora (a nascer) | `configuracoes-ia-do-ponto` | — | gap: ausente no vivo (`D-CFG-IA` pendente) | sem id |
| — | `intercorrencias-card` · `escalaform-card` (ids feios) | `Intercorrencias/Create`, `Escalas/Form` | — | a decidir com os gaps; `Create` espera a outra sessão terminar |

## Portões do PR-1

- `node scripts/governance/design-code-map-check.mjs --check --strict` → rc=0; âncora estável 84 → **86**/629.
- `contrato-de-tela` (`--map --check`, `--contract`) → limpo.
- typecheck e ESLint: este worktree não tem `node_modules`; o veredito é o do CI.
- Diff dos `.tsx`: 1 linha cada, só o atributo.
- `contrato-de-tela`: `contrato-de-tela.test.mjs`, `--preflight origin/main` (depois do rebase), `--anti-tautologia`, `--omission <merge-base>` e o laço de contratos sem `EXEMPLO` → todos limpos.
- **Zero mudança visual (estático):** o único CSS de produção que usa `data-contract` como seletor é `.arq-page [data-contract=abas]` (`resources/css/cowork-arquivos-bundle.css:129-130`), escopado a Arquivos. Nenhum seletor casa os dois ids novos.
- **O atributo chega ao DOM:** em prod (2026-09-28, `/ponto`), o precedente idêntico `<Card data-contract="painel-fila-aprovacoes">` renderiza `DIV[data-contract=painel-fila-aprovacoes]`. O `<Card>` do DS repassa o atributo.
- **Antes, medido em prod** (biz do usuário logado, DOM estável em duas leituras): `/ponto/intercorrencias` → 0 `data-contract` fora do sidebar; card da lista 545×340 em (24,358). `/ponto/banco-horas` → 0; card da tabela 544×310 em (24,705). **Depois:** a medir pós-deploy — mesmo card, mesma geometria, com o atributo.
