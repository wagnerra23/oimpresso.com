---
sessao: "_saida-04"
thread: "04 · shared/DataTable.tsx — density=\"dense\" aditivo (D-GRADE: servidor)"
dono: "[CL]"
data: 2026-09-28
prefixo_tocado: resources/js/Components/shared/DataTable.tsx · tests/js/datatable-density.test.tsx
base_lida: wagnerra23/oimpresso.com@main (o DataTable.tsx tinha os mesmos 19.800 B do base 07036a68a049 e 0 ocorrências de `density` na abertura)
---
# _saida-04

## 1 · Feito

- `shared/DataTable.tsx`: prop `density?: 'default' | 'dense'`, default `'default'`. Um mapa
  `CLASSE_DENSIDADE` escolhe a classe de `th`, `td`, `tbody` e do hover do `tr`; o ramo
  `default` é **a string de hoje, literal**. `CLASSE_ALINHAMENTO`, `CLASSE_ESTADO`, `meta`,
  `caption`, `scope`, `onRowClick` e a paginação não mudaram — a densidade soma, não substitui.
- Nenhum controller tocado: a tabela já paginava no servidor, e é isso que a D-GRADE pede.
- Zero CSS novo: todas as classes do `dense` **já existiam** no CSS buildado (o hash do
  `inertia-*.css` saiu igual; conferido por busca literal com controle `.p-3`).

## 2 · Medido — `getComputedStyle`, CSS buildado, `.cockpit` dark, 1280px

| parte | alvo (§Alvo do índice) | `dense` medido | default medido (inalterado) |
|---|---|---|---|
| `th` fonte · peso | 11px · 600 | **11px · 600** | 12px · 500 |
| `th` caixa · espaçamento | uppercase · .07em | **uppercase · 0,77px** (= .07em × 11) | none · normal |
| `th` padding | 8 × 10 | **8 × 10** | 12 × 12 |
| `td` fonte · padding | 12,5px · 7 × 10 | **12,5px · 7 × 10** | 14px · 12 × 12 |
| divisória | `--border` a 60% | **1px, alpha 0,6** | 1px, alpha 1 |
| hover | accent (roxo) 5% | `hover:bg-primary/5` | `hover:bg-accent/30` |
| contraste do `th` no dark | ≥ 4,5 | **6,95** | 6,95 |

## 3 · Guarda — as 11 telas consumidoras

- **Medição única:** o `DataTable.tsx` do `main` e o novo renderizados lado a lado em 5 arranjos
  (colunas simples · ordenável + largura + alinhamento + mono + paginação · `rowState` +
  `onRowClick` · lista vazia · sem busca + `minTableWidth` + wrapper próprio) × {sem `density`,
  `density="default"`} = **10 comparações, 0 diferentes**. Controle positivo: `dense` difere.
  (Script descartável, fora do commit.)
- **Permanente** no teste: sem `density`, `th`/`td`/`tbody`/`tr` não carregam nenhuma marca do
  `dense` e mantêm as classes de sempre.

## 4 · Validação (bloco D)

| # | item | resultado |
|---|---|---|
| 1 | sem `density`, markup idêntico | ✅ 10/10 idênticos + guarda permanente |
| 2 | `dense` com os números do bloco C | ✅ classes no teste; valores no computado (§2) |
| 3 | `caption`, `scope`, `meta.width/align/mono`, `rowState`, `onRowClick`, paginação no `dense` | ✅ |
| 4 | bite: tirar a classe do `th` no `dense` | ✅ 1 vermelho (o teste do `th`), falha de asserção; restaurado com hash conferido |
| 5 | `npm run test -- datatable-density` | ✅ **8 passed** |

Também: `tsc` escopado no teste limpo (`tests/` não entra no `tsconfig.json`) · o `DataTable.tsx`
não está entre os 306 erros pré-existentes do `typecheck` · eslint sem warning novo (os 3 já
existiam) · `component-registry-check --check --strict` ✅ · `ds-canon-color-guard` ✅ ·
`reuse:gate` ✅ · `components:check` ✅.

## 5 · Não feito — e por quê

1. **Linha de 65px:** é resultado do conteúdo da célula, não do primitivo. Com texto +
   sub-linha a linha densa mede **50px**; o protótipo põe a sub-linha como `<small>` de 10,5px em
   bloco (`ponto-page.css:66`), e isso é da tela. Nada a mudar no `DataTable`.
2. **Diferenças do protótipo fora do bloco C** (não inventei): `td` com `vertical-align: middle`
   (o primitivo mantém `align-top`) e `th` `sticky` com fundo `--surface`. Se virarem alvo, é
   thread nova.
3. ~~**`governance/design/component-registry.json`** fica parcialmente desatualizado~~ —
   **resolvido no mesmo PR**, a pedido do [W] (2026-09-28): as duas entradas do `DataTable`
   registram a `density`, a de "DataTablePro" separa densidade (existe) de sticky e resize
   (faltam), e o `props` lista as 16 props reais, conferidas contra a interface do componente.
   Ficou no mesmo PR para registro e código chegarem juntos ao `main`.
4. **Adoção:** nenhuma tela liga `dense` aqui. É uma tela por PR.

## 6 · Placar

**entregue 1 de 1 prop (`density`) · 5 de 5 números do alvo medidos no computado · ausente a
linha de 65px, por ser resultado do conteúdo da célula (tela), não do primitivo.**
