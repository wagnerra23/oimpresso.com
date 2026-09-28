---
sessao: "17"
titulo: "data-contract no .tsx — saída da thread"
autor: "[CL]"
criado: 2026-09-28
base: ad23c9ff9
thread: 17-data-contract-no-tsx.md
veredito: "em andamento — 8 de 26 ids em PRs abertos (#8088, #8090, #8091); 5 fora por regra; PR-2 espera #8079/#8077, PR-4 espera #8078"
---

# _saída 17 · `data-contract` no `.tsx`

Recibo único da thread, atualizado a cada PR.

## Como foi medido

- **Os 26 ids** saem do build: `grep -o 'contrato="[^"]*"' prototipo-ui/cowork/Wagner/ponto-telas.jsx | sort -u` → **26**, protótipo @ `2e3f8adb4e`. Bate com a tabela da thread e com o `_PATCH-INDICE` §2.
- **Colisão antes de editar** (a thread manda parar se outro PR toca `Pages/Ponto`): em 2026-09-28 havia três abertos — #8079 (`Aprovacoes/Index.tsx`, `Escalas/Index.tsx`), #8077 (`BancoHoras/Show.tsx`) e #8078 (`Configuracoes/Index.tsx`) — e uma sessão acabara de editar `Intercorrencias/Create.tsx`. A gerente reagrupou os PRs por **arquivo livre**, não por módulo.
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
| PR-4 | configurações ×7 | `Configuracoes/Index`, `Configuracoes/Reps` | — | espera #8078 |
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
