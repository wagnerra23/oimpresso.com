---
sessao: "03"
titulo: a11y — sinal não-cor na divergência + mobile-fit do dia a dia
dono: "[CL]"
base: e86130722de1
prefixo: resources/js/Pages/Ponto/Espelho/Index.tsx · Espelho/Show.tsx · _components/MonthHeatmap.tsx
nao_toca: prototipo-ui/contrato/ponto-espelho.contract.json (copy/ordem são lei) · Modules/Ponto/** · os 4 data-contract do Show fora do escopo
depende: thread 01 feita (rede antes de tocar UI) — vaga 2
---
# 03 · a11y não-cor + mobile-fit

## Medido nesta sha
`grep` em `resources/js/Pages/Ponto/**`: **0 `sr-only` · 0 `aria-live`** (os 9 hits são `data-contract`). O dia em `DIVERGENCIA` no `MonthHeatmap` e nas linhas do `Show` é sinalizado **só por cor** — falha WCAG 1.4.1 (Use of Color). Score a11y 74 e mobile_fit 74 vêm da medição de 27–28/08 (não remedidos).

## Alvo (o que muda)
- **MonthHeatmap.tsx:** cada célula em estado ≠ normal ganha texto para leitor de tela (`<span className="sr-only">Divergência</span>` / "Falta" / "Feriado") **e** um sinal visual não-cor (glifo ou borda/traço — reusar o padrão que `StatusBadge kind="intercorrencia"` já usa no DS, nunca ícone anônimo).
- **Espelho/Show.tsx (`espelho-apuracao-diaria`):** a linha em divergência recebe o mesmo sinal + `aria-label` na célula de estado. Ações que escrevem (Anular) já existem — **não mexer no comportamento**.
- **Mobile-fit (Técnico Repair, touch ≥44 px):** alvos de toque da grade do mês ≥ 24 px (A7) e sem overflow horizontal em 390 px. Só CSS/classe; nenhuma mudança de dado.

## Não inventar
- Componentes: `@/Components/ui/*` · `shared/StatusBadge` · tokens do DS (`--status-*`); zero cor crua.
- Copy: "Divergência", "Falta", "Feriado" — as mesmas palavras do contrato `ponto-espelho`.

## Passo a passo
1. `gh pr list --state open` × os 3 arquivos.
2. Medir antes: axe/pa11y nas 2 rotas (dark, 1280) → nº de violações no `_saida`.
3. Aplicar no `MonthHeatmap` → `Show` → `Index`.
4. Medir depois; e2e da thread 01 verde (é o que garante "não mudei layout").
5. `_saida-03.md`.

## PARAR SE
- Precisar mudar copy/ordem do contrato → parar (lei [W]).
- O sinal não-cor exigir componente novo no DS → pedido de DS, não bespoke aqui.

## Prova
- `MonthHeatmap.tsx` contém `sr-only` (hoje 0) · `_saida-03.md` com violações antes/depois · e2e verde
