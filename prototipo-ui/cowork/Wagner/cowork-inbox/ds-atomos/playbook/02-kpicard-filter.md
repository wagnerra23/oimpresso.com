---
sessao: "02"
titulo: shared/KpiCard.tsx — variant="filter" aditivo (o tile clicável do protótipo)
dono: "[CL]"
base: 2b4a3ec3b48a
prefixo: resources/js/Components/shared/KpiCard.tsx
nao_toca: resources/js/Pages/** · ui/** · shared/KpiGrid.tsx
depende: —
---
# 02 · `shared/KpiCard.tsx` — a variante de filtro, sem tocar no tom semântico

## A · IDENTIDADE (ancoragem dupla)
- **alvo (layout, read-only):** `prototipo-ui/cowork/ponto-ui.jsx` :: `Kpi` (ramo `onClick`) e `ponto-page.jsx` :: os 6 tiles de `.pt-kpis`.
- **âncora (código):** `resources/js/Components/shared/KpiCard.tsx` — **11.135 B**, sha `670b3f645b9b`. Símbolos: `KpiCard` (:81), `kpiCardVariants` (:29), `iconContainerVariants` (:52).
- **Leia o arquivo todo** — ele carrega ADR 0110 e uma errata de 2026-08-27 em comentário; **essa história manda mais que este pedido** em qualquer conflito de tipografia.
- **NÃO ler:** `Pages/Ponto/Dashboard/Index.tsx` (20.313 B) · `_components/*` — oráculo.

## B · NÃO INVENTAR
- `cva` já está no arquivo — **estenda as variants**, não escreva classe solta.
- Tons do filtro: **`primary` · `amber` · `rose` · `emerald` · `violet`** (é o vocabulário do bundle `variant="filter"`, look Clientes/CRM). São **outra dimensão** — não renomeie nem remapeie `tone`.
- Zero cor crua; use os tokens/utilitárias que o arquivo já usa.

## C · ALVO MEDIDO (dark, T1 estável)
O ramo clicável de produção **já acerta a semântica** (`<button type="button">` + `aria-pressed={selected}` + anel `border-primary ring-1 ring-primary/40`) — **não** regrida nada disso. O que difere é a **forma do tile**:

| eixo | alvo |
|---|---|
| caixa | pad **12px** · radius **8px** (`rounded-lg`, **não** `xl`) · border 1px `--border` · bg `--surface` · gap **12px** |
| altura | **85px** com label de 2 linhas + valor + sub |
| ícone | placa quadrada, tom por cor do filtro |
| valor | **18px/600**, `--text`, `tabular-nums` |
| label | **13.3px/400 em accent** `oklch(0.70 0.15 295)` — contraste medido **4,85** ✓ |
| grid | `gap:10px`, trilha ~**194px** (`KpiGrid` já resolve; não duplicar) |

⛔ **CONFLITO DECLARADO (`D-KPI-LABEL`, fila de [W]):** o label acima briga com o canon que este mesmo arquivo documenta (ADR 0110: KPI label = `text-[11px] font-semibold uppercase tracking-widest` muted). **Sob `variant="filter"`** entregue o alvo; **fora dela**, não toque no label. Se isso te parecer contradição, é: está na fila, e o aditivo é justamente o que permite as duas conviverem até [W] decidir.

## D · COMO VALIDAR
1. `variant="filter"` renderiza os números do quadro acima (`getComputedStyle`, dark).
2. **Guarda:** `KpiCard` **sem** `variant` mantém `tone default/success/warning/danger/info` e markup byte-a-byte do de `670b3f645b9b` — snapshot de `Pages/Backup/Index.tsx` e `Pages/Financeiro/Unificado/Index.tsx` **sem diff**.
3. `onClick` + `selected`: `aria-pressed` reflete, `focus-visible` com anel de accent, teclado ↵/espaço aciona.
4. Reversível: clicar no selecionado desliga (invariante 2 — a lógica é da tela, mas o componente **não** pode impedir).
5. PLACAR no corpo do PR.

## 4-ter · EXECUÇÃO
- **ARQUIVOS A EDITAR:** `resources/js/Components/shared/KpiCard.tsx` — **só este**.
- **REUSAR:** `cva`/`VariantProps` já importados · `Icon` de `@/Components/Icon` · `cn`.
- **CRIAR:** nada.
- **NÃO TOCAR:** `KpiGrid.tsx` (o grid já é dele) · nenhuma Page.
- **PASSO A PASSO:** 1) ler o arquivo inteiro, inclusive os comentários de ADR · 2) somar `variant?: 'default'|'filter'` e `filterTone?` às variants do `cva` · 3) `defaultVariants` **inalterado** · 4) sob `filter`, aplicar caixa/valor/label do alvo · 5) snapshot de guarda nos 2 consumidores fora do Ponto.
- **DADO:** nenhum.
- **PARAR SE:** o encaixe exigir mudar `tone`, `valueClass` fora da variante, ou o `Delta`. Aí **pare** — o eixo do `Delta` já tem errata aberta no próprio arquivo e não é deste PR.

## PRÉ / PÓS
- **antes:** `shared/KpiCard.tsx` sem `variant`; `Backup/Index.tsx` e `Financeiro/Unificado/Index.tsx` **presentes** (guarda).
- **depois:** `variant="filter"` disponível; os dois consumidores sem diff visual.
- **quebra:** se `variant` já existir no arquivo, **não execute** — reporte.

## PROVA
`shared/KpiCard.tsx` aceita `variant='filter'` · snapshot dos 2 consumidores verde · `_saida-02.md`.
