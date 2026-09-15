---
sessao: "15"
titulo: Paridade de FORMA — Aprovações (Aprovacoes/Index.tsx)
dono: "[CL]"
base: 2b4a3ec3b48a
prefixo: resources/js/Pages/Ponto/Aprovacoes/Index.tsx
nao_toca: ${PAGES}/Intercorrencias/** · Modules/Ponto/** · shared/BulkActionBar.tsx
depende: ds-atomos 01 · ds-atomos 03
---
# 15 · Aprovações — fila, seleção em lote e o motivo

## A · IDENTIDADE (ancoragem dupla)
- **alvo (layout, read-only):** `prototipo-ui/cowork/ponto-telas.jsx` — aba **Aprovações**, T1 estável **912 nós**, **1280px**.
- **âncora (código):** `resources/js/Pages/Ponto/Aprovacoes/Index.tsx` — **23.762 B**, sha `6dc451b3c541`. Símbolo: `AprovacoesIndex` (:119). Imports já presentes: `KpiGrid` · `KpiCard` · `PageFilters` · `StatusBadge` · `EmptyState` · `BulkActionBar` · `Textarea` · `Label`.
- **oráculo:** `Index.charter.md` (3.237 B) · `AprovacaoTest`.
- **NÃO ler:** `Intercorrencias/**` (48 KB somados).
- **persona:** Eliana (financeiro/RH, tabelas densas) e Wagner (aprovador).

## B · NÃO INVENTAR
- **Zero CSS novo, zero utilitária de cor/espaço** onde os átomos já cobrem. Zero hex, zero `oklch()` literal.
- **Reusar os átomos que a tela já importa** — eles já têm `aria`/`data-slot`; não reimplemente nenhum.
- **Copy:** literal do protótipo, PT-BR, sentence case. Enum legal (`REP-P`, `NF-e`, `NSR`) mantém a caixa canônica.
- **Dado:** Model/Service/coluna real. Sem fonte ⇒ `—` + linha no PR. Nenhum número inventado.
- **O alvo dos átomos NÃO se repete aqui** — está em `cowork-inbox/ds-atomos/playbook/00-INDICE.md` (§Alvo). Cite por nome, não copie: regra copiada envelhece em paralelo.

## ⛔ O QUE ESTA THREAD NÃO É
Não é "deixar a tela igual ao protótipo **em inventário de seção**". A produção tem seções que o meu protótipo **não** tem, e elas **ficam** — remover é regressão, não paridade. O que se aplica é **forma**: anatomia do átomo, densidade, tipografia, tom e ordem **dentro** das seções que existem nos dois lados.

## C · ALVO MEDIDO (dark, T1 912 nós, 1280px)
Três seções, nesta ordem:

| # | seção | nós | forma |
|---:|---|---:|---|
| 1 | barra de filtros | 29 | `Toolbar` (ds-atomos 03). **A produção usa `PageFilters`** (chips de filtro ativo + grid) — **peça diferente, e ela fica**: o `Toolbar` entra **onde hoje não há moldura nenhuma**, não substituindo o `PageFilters`. Se as duas colidirem na tela, **pare e reporte** |
| 2 | card «Fila de aprovações» | 81 | `Card` **flush** + **badge** de contagem · tabela densa (mesmos números da 14) · `StatusBadge kind="intercorrencia"` no estado e `kind="prioridade"` no urgente |
| 3 | `pt-legal` | 3 | linha legal com artigo literal |

**Seleção em lote:** o `BulkActionBar` de produção aceita `children` — o campo **"motivo do lote"** cabe **dentro** dela. Foi medido em 2026-09-09 e **mata a W12** ("o DS não tem slot pro motivo" era falso). Aprovar/rejeitar em lote **sem motivo** é o que não pode.
**Invariante 1 do módulo:** toda escrita é **PROPOSTA** — aprovar grava proposta + atividade, nunca aplica direto.

## D · COMO VALIDAR
1. 3 seções na ordem; contagem = alvo.
2. `BulkActionBar` aparece só com `selectedCount > 0`, com o motivo dentro e `aria-live` anunciando "N itens selecionados".
3. Clique no check da linha **não** abre o detalhe (`stopPropagation` declarado — invariante 3).
4. `esc` fecha **um** nível.
5. **Guarda:** `PageFilters` segue em uso onde já estava · `AprovacaoTest` verde.
6. Screenshot prod · dark · 1280px · PLACAR no PR.

## 4-ter · EXECUÇÃO
- **ARQUIVOS A EDITAR:** `${PAGES}/Aprovacoes/Index.tsx` — **só este**.
- **REUSAR:** `BulkActionBar` (com `children`) · `Card` `badge`/`flush` · `Toolbar` · `StatusBadge` · `EmptyState` · `Textarea`+`Label` do motivo.
- **CRIAR:** nada. **Não** reimplemente barra de lote — ela existe.
- **NÃO TOCAR:** `shared/BulkActionBar.tsx` (é da ds-atomos, não desta) · `Intercorrencias/**` · o controller.
- **PASSO A PASSO:** 1) medir antes · 2) card → `flush`+`badge` e tabela nos números da 14 · 3) motivo dentro do `BulkActionBar` · 4) conferir `stopPropagation` no check · 5) Pest.
- **PARAR SE:** `Toolbar` e `PageFilters` brigarem por lugar · aprovar em lote não tiver endpoint que aceite motivo (**afordância falsa** — LC-15: não desenhe o campo).

## PRÉ / PÓS
- **antes:** `PageFilters` **presente** (guarda) · `BulkActionBar` presente sem campo de motivo.
- **depois:** motivo dentro da barra de lote · card `flush`+`badge` · tabela nos números do alvo · `PageFilters` intacto.
- **quebra:** se o motivo já estiver na barra, **não execute** — reporte.

## PROVA
`${PAGES}/Aprovacoes/Index.tsx` tem `Textarea` dentro de `BulkActionBar` · `PageFilters` ainda importado (guarda) · `AprovacaoTest` verde · `_saida-15.md`.
