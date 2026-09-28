---
sessao: "17"
titulo: "data-contract no .tsx — saída da thread"
autor: "[CL]"
criado: 2026-09-28
base: ad23c9ff9
thread: 17-data-contract-no-tsx.md
veredito: "em andamento — PR-1 (2 de 26 ids) aberto; PR-2/3/4 esperam PRs abertos que tocam os mesmos .tsx"
---

# _saída 17 · `data-contract` no `.tsx`

Recibo único da thread, atualizado a cada PR.

## Como foi medido

- **Os 26 ids** saem do build: `grep -o 'contrato="[^"]*"' prototipo-ui/cowork/Wagner/ponto-telas.jsx | sort -u` → **26**, protótipo @ `2e3f8adb4e`. Bate com a tabela da thread e com o `_PATCH-INDICE` §2.
- **Colisão antes de editar** (a thread manda parar se outro PR toca `Pages/Ponto`): em 2026-09-28 havia três abertos — #8079 (`Aprovacoes/Index.tsx`, `Escalas/Index.tsx`), #8077 (`BancoHoras/Show.tsx`) e #8078 (`Configuracoes/Index.tsx`) — e uma sessão acabara de editar `Intercorrencias/Create.tsx`. A gerente reagrupou os PRs por **arquivo livre**, não por módulo.
- **Nenhum `.tsx` do Ponto** tinha `data-contract` nessas regiões, logo o "PARAR SE — o vivo já tem outro string" não se aplicou.
- **Granularidade:** no protótipo o id fica no `<Card>` da região; no vivo também, no `<Card>` do DS (`Components/ui/card.tsx` repassa `...props` ao `<div>`, então o atributo chega ao DOM).
- **Map:** a parte correspondente passa de `vivo.ancora: false` para `vivo.ancora: "<id>"` — é o que torna a âncora verificável.
- **Mordida provada:** tirado o atributo do `Intercorrencias/Index.tsx` com o map declarando a âncora, `design-code-map-check --check --strict` sai **rc=1** com `[DRIFT] … data-contract="intercorrencias-intercorrencias" NÃO existe`. Restaurado, rc=0.

## PRs

| PR | ids | arquivo `.tsx` | parte do map | estado |
|---|---|---|---|---|
| PR-1 | `intercorrencias-intercorrencias` | `Intercorrencias/Index.tsx` (`<Card>` da lista) | `intercorrencias-index.map.json` · `lista-de-intercorrencias` | aberto |
| PR-1 | `bancohoras-saldos-por-colaborador` | `BancoHoras/Index.tsx` (`<Card>` da tabela) | `banco-horas-index.map.json` · `saldos-por-colaborador` | aberto |
| PR-2 | `aprovacoes-fila-de-aprovacoes` · `escalas-escalas-cadastradas` · `bancohoras-historico-de-movimentos` · `bancohoras-ajuste-manual` | `Aprovacoes/Index`, `Escalas/Index`, `BancoHoras/Show` | — | espera #8079 e #8077 |
| PR-3 | colaboradores ×3 · importações ×6 · relatórios ×2 | `Colaboradores/*`, `Importacoes/*` (Index, Create, Show), `Relatorios/Index` | — | espera #8073 |
| PR-4 | configurações ×7 | `Configuracoes/Index`, `Configuracoes/Reps` | — | espera #8078 |
| — | `intercorrencias-card` · `escalaform-card` (ids feios) | `Intercorrencias/Create`, `Escalas/Form` | — | a decidir com os gaps; `Create` espera a outra sessão terminar |

## Portões do PR-1

- `node scripts/governance/design-code-map-check.mjs --check --strict` → rc=0; âncora estável 84 → **86**/629.
- `contrato-de-tela` (`--map --check`, `--contract`) → limpo.
- typecheck e ESLint: este worktree não tem `node_modules`; o veredito é o do CI.
- Diff dos `.tsx`: 1 linha cada, só o atributo.
