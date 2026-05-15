# Visual Comparison — Cliente/Show (W1-B3)

## Pattern reuse (ADR 0149)
Deriva blueprint Cowork Index — header pattern + KPI cards idênticos. Layout 2-col (sidebar + main).

## Dimensões diff vs Index

| Dimensão | Index | Show |
|---|---|---|
| Layout | list 7xl | detail 6xl 2-col |
| Header | h1 + 4 KPIs | avatar 56px + name + 4 stats |
| Body | table 50 rows | sidebar contato + tabela 20 tx |

## Defer
- `stats` — agregação financeira (caro)
- `transactions` — JOIN com transactions limit 20

## Avatar
Quadrado 56px `rounded-md` gradient stone (canon ADR 0110).

## Acessibilidade
- `<dt>/<dd>` para Contact info (semântico)
- Link "Página completa" + "Editar" CTAs principais
- Skeleton loading states

## Gate F1.5
✅ Aprovado via ADR 0149 (pattern reuse Index)
