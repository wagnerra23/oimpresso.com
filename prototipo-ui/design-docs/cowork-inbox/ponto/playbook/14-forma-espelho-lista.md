---
sessao: "14"
titulo: Paridade de FORMA — Espelho · lista (Espelho/Index.tsx)
dono: "[CL]"
base: 2b4a3ec3b48a
prefixo: resources/js/Pages/Ponto/Espelho/Index.tsx
nao_toca: ${PAGES}/Espelho/Show.tsx · _components/MonthHeatmap.tsx · Modules/Ponto/**
depende: ds-atomos 01 · ds-atomos 03 (Toolbar) · thread 03 (a11y) se já aberta
---
# 14 · Espelho · lista — barra de filtros + tabela densa

## A · IDENTIDADE (ancoragem dupla)
- **alvo (layout, read-only):** `prototipo-ui/cowork/ponto-page.jsx` — aba **Espelho de Ponto**, T1 estável **1015 nós**, **1280px**.
- **âncora (código):** `resources/js/Pages/Ponto/Espelho/Index.tsx` — **6.955 B**, sha `5e4a3c209d3f`. Símbolo: `EspelhoIndex` (:42). **Um símbolo, arquivo pequeno: leia inteiro.**
- **oráculo:** `Index.charter.md` (2.898 B) · `EspelhoContratoTest` · `prototipo-ui/contrato/ponto-espelho.contract.json`.
- **NÃO ler:** `Espelho/Show.tsx` (24.831 B) — é a thread 15 · `_components/MonthHeatmap.tsx` (12.796 B) — é a thread 03.
- **contrato vigente:** `ponto-espelho` (5 `data-contract`). Contradição ⇒ o contrato manda e você para.

## B · NÃO INVENTAR
- **Zero CSS novo, zero utilitária de cor/espaço** onde os átomos já cobrem. Zero hex, zero `oklch()` literal.
- **Reusar os átomos que a tela já importa** — eles já têm `aria`/`data-slot`; não reimplemente nenhum.
- **Copy:** literal do protótipo, PT-BR, sentence case. Enum legal (`REP-P`, `NF-e`, `NSR`) mantém a caixa canônica.
- **Dado:** Model/Service/coluna real. Sem fonte ⇒ `—` + linha no PR. Nenhum número inventado.
- **O alvo dos átomos NÃO se repete aqui** — está em `cowork-inbox/ds-atomos/playbook/00-INDICE.md` (§Alvo). Cite por nome, não copie: regra copiada envelhece em paralelo.

## ⛔ O QUE ESTA THREAD NÃO É
Não é "deixar a tela igual ao protótipo **em inventário de seção**". A produção tem seções que o meu protótipo **não** tem, e elas **ficam** — remover é regressão, não paridade. O que se aplica é **forma**: anatomia do átomo, densidade, tipografia, tom e ordem **dentro** das seções que existem nos dois lados.

## C · ALVO MEDIDO (dark, T1 1015 nós, 1280px)
Duas seções, nesta ordem:

| # | seção | nós | forma |
|---:|---|---:|---|
| 1 | barra de filtros | 26 | `Toolbar` da ds-atomos 03 — **6 filhos**: select Mês · select Escala · campo Buscar (**o único que estica**, `1 1 260px`) · check "Só com divergência" · nota de contagem · spacer da zona `right`. Barra **1215×71px** a 1280px, moldura no PAI (radius 12 + border 1px) |
| 2 | card «Colaboradores» | 191 | `Card` **flush** (o único filho é a tabela) com **badge** de contagem · tabela densa: `th` **11px** uppercase `.07em` **`--text-dim`** (⚠ `--text-mute` reprova AA: 3,18) · `td` 12.5px · linha 65px com sub-linha · hover `accent 5%` · rodapé de paginação "N–M de T" + passo por página |

**A produção pagina no servidor** (`LengthAwarePaginator`) — **mantenha**. O rodapé é forma; não troque o regime de paginação nesta thread (é a `D-GRADE`, decisão aberta).

## D · COMO VALIDAR
1. Barra: 6 filhos na ordem, `gap:8px`, `padding:9px 12px`, sem borda dupla com o pai, **um só** spacer.
2. Tabela: `th` a 11px e `--text-dim` (contraste ≥ 4,5 — medido 5,49); nenhum texto pequeno em `--text-mute`.
3. Card com `flush`: sem duplo padding card+tabela.
4. **Guarda:** os 5 `data-contract` do `ponto-espelho` presentes · `EspelhoContratoTest` verde · paginação server-side intacta.
5. Reflow: a 1280px a barra é **uma faixa**; estreitando, quebra sem overflow horizontal.
6. Screenshot prod · dark · 1280px · PLACAR no PR.

## 4-ter · EXECUÇÃO
- **ARQUIVOS A EDITAR:** `${PAGES}/Espelho/Index.tsx` — **só este**.
- **REUSAR:** `Toolbar` (ds-atomos 03) · `Card` com `badge`/`flush` (ds-atomos 01) · `Input`/`Select`/`Checkbox` do `ui/` · `Skeleton` que a tela já usa no `Deferred`.
- **CRIAR:** nada.
- **NÃO TOCAR:** `Show.tsx`, `MonthHeatmap.tsx`, o contrato, o controller.
- **PASSO A PASSO:** 1) medir antes (5 `data-contract`) · 2) filtros → `Toolbar` com as 3 zonas · 3) card → `flush` + `badge` · 4) `th` para 11px/`--text-dim` · 5) contrato + Pest.
- **PARAR SE:** ds-atomos 01/03 não estiverem no `main` · a barra exigir CSS novo · o contrato contradisser a ordem.

## PRÉ / PÓS
- **antes:** filtros soltos (sem `Toolbar`) · `ponto-espelho.contract.json` **presente** · paginação server-side **presente** (guarda).
- **depois:** `Toolbar` com 6 filhos · tabela a 11px/`--text-dim` · card `flush` · contrato e paginação intactos.
- **quebra:** se `Toolbar` já estiver em uso aqui, **não execute** — reporte.

## PROVA
`${PAGES}/Espelho/Index.tsx` importa `Toolbar` de `@/Components/shared/Toolbar` · `EspelhoContratoTest` verde · `_saida-14.md` com `th` medido (px + cor + contraste).
