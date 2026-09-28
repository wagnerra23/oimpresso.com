---
sessao: "_saida-01"
thread: "01 · Contrato de Tela manufacturing-index + data-contract no Index.tsx"
dono: "[CL]"
data: 2026-09-28
prefixo_tocado: governance/design/contracts/manufacturing-index.contract.json · resources/js/Pages/Manufacturing/Index.tsx
fora_do_prefixo: resources/js/Pages/Manufacturing/Index.casos.md (last_run + trilha, exigido pelo G-6 do casos-gate)
base_lida: wagnerra23/oimpresso.com@main 89b3b13db (thread 04 já mergeada no #7989)
---
# _saida-01

## 1 · Feito

- `governance/design/contracts/manufacturing-index.contract.json`, no molde do
  `manufacturing-recipes.contract.json`. As 5 seções, a ordem (`cabecalho → abas → kpis → filtros
  → lista`) e a copy literal são as do PEDIDO §"O que sobra" item 1, sem acréscimo.
- `Index.tsx`: `data-contract` nas 5 regiões, e nada além de atributos. O `PageHeader` canon não
  repassa atributos arbitrários, então o cabeçalho ganhou um `<div data-contract="cabecalho">` em
  volta, como o `Backup/Index.tsx` já faz. Ele só substitui o `<header>` como filho do
  `space-y-6`, e o espaçamento não muda.
- Guarda do índice mantida: a situação segue `StatusBadge kind="producao"`.

## 2 · O esqueleto do `gerar-contrato.mjs` não foi usado, e por quê

`node scripts/design/gerar-contrato.mjs Manufacturing/Index` leu o `manufacturing-index-gap.md` e
devolveu outras 5 seções: header-do-modulo, kpis, ordenacao-por-coluna, paginacao e
drawer-de-detalhe-da-ordem. São as partes marcadas "Decidir" no gap. Três delas a tela não
constrói, e pinar no contrato o que não existe faria o gate reprovar por algo que não é defeito.
O contrato segue o PEDIDO, e a decisão construir/rejeitar continua no gap.md. Está registrado em
`_pendente_w` do próprio contrato.

## 3 · Verificação

| item | resultado |
|---|---|
| `node scripts/contrato-de-tela.mjs --contract …manufacturing-index.contract.json` | exit 0, 5 seções OK, ordem coerente |
| sanidade: `"Custo unit."` → `"Custo unitário"` no contrato | exit 1, `copy ausente em "lista": "Custo unitário"` |
| sanidade: âncora `kpis` removida do `.tsx` | exit 1, `seção "kpis" sem âncora data-contract no alvo` |
| arquivos restaurados | hash do contrato idêntico antes/depois; gate verde de novo |
| `casos-coverage-guard` | sem violação nova |
| `pageheader-migration-guard` | nenhuma dívida tocada |
| eslint no `Index.tsx` · vitest do UC-OP-06 | exit 0 · 5 passed |

O CI descobre o contrato sozinho (`contrato-de-tela.yml` roda `git ls-files '*.contract.json'`).

## 4 · Smoke da thread 04 em prod (feito nesta sessão, 2026-09-28)

Deploy com o `fd28cf64f` concluído. Tela em prod, empresa WR2 Sistemas (biz=1), medida no DOM e
nas requisições XHR:
- cabeçalho canon: `<header>` com borda inferior, h1 22px/600, sem ícone, "Nova produção" em
  `oklch(0.55 0.15 295)`; botão "Aplicar intervalo de datas" ausente;
- De sozinho → 0 requisições; Até com `0002-`/`0020-`/`0202-09-30` → 0; Até `2026-09-30` → 1,
  com `X-Inertia-Partial-Data: productions,summary,filters`;
- apagar só o De → 0; apagar os dois → 1, lista volta às 2 ordens;
- intervalo 2020–2030 → 1 requisição, 2 linhas; "Só finalizadas" (controle) → 1, `?is_final=1`, 1 linha.
