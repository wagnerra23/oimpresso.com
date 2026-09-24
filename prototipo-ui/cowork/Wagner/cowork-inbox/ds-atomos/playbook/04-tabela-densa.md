---
sessao: "04"
titulo: shared/DataTable.tsx — prop aditiva density="dense" (tabela densa do protótipo)
dono: "[CL]"
base: 07036a68a049
prefixo: resources/js/Components/shared/DataTable.tsx · tests/js/datatable-density.test.tsx (criar)
nao_toca: resources/js/Pages/** · Components/ui/** · qualquer controller · DataGrid/DataTablePro do espelho
depende: 01 (card — feito) · D-GRADE respondida ([W] 2026-09-24: SERVIDOR)
---
# 04 · Tabela densa — no primitivo, não na tela

## A · IDENTIDADE (ancoragem dupla)
- **alvo (layout, read-only):** `prototipo-ui/cowork/Wagner/ponto-page.css` · `table.pt-tbl` (e a §Alvo do `00-INDICE.md` desta pasta — **única fonte dos números**).
- **âncora (código):** `resources/js/Components/shared/DataTable.tsx` — **19.800 B** @`07036a68a049`, lido inteiro pelo [CC] em 2026-09-24. Já pagina **no servidor** (`manualPagination: true`, `PaginatorShape` do Inertia) — por isso a D-GRADE cabe aqui sem mexer em controller.
- **NÃO ler:** `Pages/**` (as 11 telas consumidoras não mudam nesta thread).

## B · NÃO INVENTAR
- **ADITIVO OU NADA.** Prop nova `density?: 'default' | 'dense'`, default **`'default'`** = markup/classe **byte-idêntico** ao de hoje. Nenhuma das 11 telas muda de pixel.
- **Não** criar `ui/table.tsx` (não existe; D-GRADE mediu) nem migrar para `DataGrid` no cliente — **D-GRADE: servidor**.
- Zero CSS novo: utilitárias Tailwind + tokens que o repo já tem.
- **Copy:** nenhuma.

## C · ALVO MEDIDO (protótipo, dark, 1280px — ver §Alvo do índice)
| parte | alvo | classe sugerida no `dense` (conferir por `getComputedStyle`, não pela classe) |
|---|---|---|
| `th` | **11px · uppercase · letter-spacing .07em · 600 · cor `--text-dim`** · pad **8px 10px** | `px-2.5 py-2 text-[11px] uppercase tracking-[.07em] font-semibold text-muted-foreground` |
| `td` | **12.5px** · pad **7px 10px** | `px-2.5 py-[7px] text-[12.5px]` |
| divisória | border-bottom **`--border` a 60%** | `divide-border/60` no `<tbody>` |
| hover | **accent 5%** (accent = roxo da marca) | `hover:bg-primary/5` — **não** `bg-accent/30` (no shadcn `accent` é neutro) |
| linha com sub-linha | **65px** | resultado, não classe: medir com um `<small>` na célula |

**Contraste (o alvo não é sagrado):** o `th` já foi corrigido no protótipo de `--text-mute` (3,18 ✗) para `--text-dim` (5,49 ✓). No `main`, confirmar que `text-muted-foreground` no `thead` dá **≥ 4,5:1** sobre o fundo do cabeçalho **no tema dark** — se não der, é defeito a registrar, não motivo para trocar token nesta thread.

## D · COMO VALIDAR
1. `density` ausente → snapshot do `<table>` **idêntico** ao de antes (teste de guarda com 1 tabela de 2 linhas).
2. `density="dense"` → `th` e `td` com os números do bloco C, lidos por `getComputedStyle`.
3. `caption` (sr-only), `scope="col"`, `meta.width/align/mono`, `rowState`, `onRowClick` e a paginação **continuam** funcionando no `dense` (mesmo teste, com as duas densidades).
4. **Bite-test:** remova a classe do `th` no `dense` e o teste tem de ficar vermelho.
5. `npm run test -- datatable-density` verde · PLACAR no corpo do PR.

## 4-ter · EXECUÇÃO
- **ARQUIVOS A EDITAR:** `shared/DataTable.tsx` (1 prop + 2 mapas de classe) · **criar** `tests/js/datatable-density.test.tsx`.
- **REUSAR:** `cn` se precisar compor · os mapas `CLASSE_ALINHAMENTO`/`CLASSE_ESTADO` já existentes (a densidade soma, não substitui).
- **NÃO TOCAR:** as 11 telas · `ui/**` · controllers.
- **PASSO A PASSO:** 1) `const DENSO = density === 'dense'` · 2) classe do `th`/`td`/`tbody` escolhida por `DENSO`, com o ramo `false` sendo **a string de hoje, literal** · 3) teste de guarda + teste do denso + bite · 4) docblock da prop citando D-GRADE e esta thread.
- **DADO:** nenhum.
- **PARAR SE:** o default mudar 1 caractere de classe; ou aparecer vontade de ligar `dense` em alguma tela (é outra thread, 1 tela por PR).

## PRÉ / PÓS
- **antes:** `DataTable.tsx` sem `density` (conferir: `grep -c "density" … = 0`).
- **depois:** `density?: 'default' | 'dense'` presente · teste novo verde · 11 telas sem diff.
- **quebra:** se `density` já existir no arquivo, **não execute** — reporte e pare.

## PROVA
`DataTable.tsx` contém `density` · `tests/js/datatable-density.test.tsx` existe · `_saida-04.md`.
