# PROMPT PARA CLAUDE CODE — Censo de Adoção do DS (Onda 0, só medição)

> Cole isto UMA vez no Claude Code. **Não muda UI.** Só **mede** e gera um relatório.
> Origem: re-baseline do plano de rollout DS (Cowork [CC], conferido @main 87726ae nesta sessão).
> Objetivo: preencher a 1ª coluna do "Ledger de Conformidade DS" com dado real do repo,
> antes de aprovar qualquer onda de porte.

## Contexto (já verificado @main)
O DS **já existe** no repo: `resources/js/Components/ui/` (button, avatar, badge, input,
select, command, sheet, segmented, checkbox, field-state, form-section…), `layout/`
(Box/Stack/Inline/Grid/Text), `shared/` (DataTable, KpiCard, StatusBadge, PageHeader,
EmptyState…), `cockpit/` (Thread, Sidebar), `CommandPalette.tsx`, `foundations.css`.
O gap medido num piloto (`Pages/Produto/Create.tsx`) é **adoção parcial + cor crua**:
usa `@/Components/ui` ✓, mas pinta com `stone-*`/`rose-*` Tailwind cru (não token),
header hand-rolled (existe `shared/PageHeader.tsx`) e checkbox cru (existe `ui/checkbox.tsx`).

## Tarefa — gerar `memory/ds-adocao-censo.md` (e o JSON de apoio)
Para **cada Page** em `resources/js/Pages/**/*.tsx` (ignore `_Showcase`, `Modules` stub),
medir e tabelar, **usando os scripts que já existem** (não criar gate novo):

1. **Componentes DS usados** — contar imports de `@/Components/ui`, `@/Components/shared`,
   `@/Components/layout`, `cockpit/*`. (reaproveite `scripts/components-tree-guard.mjs`.)
2. **Cor crua** — nº de classes Tailwind de família+grau (`(bg|text|border|ring|…)-(stone|slate|rose|emerald|amber|…)-\d{2,3}`)
   e `#hex`/`oklch()` cru. (reaproveite a regra do `ui:lint` R1 / `scripts/conformance-gate.mjs`.)
3. **Nota de identidade** — rode `scripts/design-identity-grade.mjs` e capture a nota por Page (se suportar) ou global.
4. **DS report** — rode `scripts/ds-report.mjs` e anexe o resumo.
5. **Veredito por Page**: `OK` (0 cor crua + usa shared) · `PARCIAL` (usa componente, cor crua) · `BESPOKE` (CSS próprio, ex.: telas com `*-cowork.css` grande).

## Saída esperada
- `memory/ds-adocao-censo.md` — tabela: Page | componentes DS | cor crua (nº) | nota | veredito.
- Um **placar de topo**: quantas Pages `OK` / `PARCIAL` / `BESPOKE` e o **% adotado**.
- Ordenar por "mais cor crua" (as que mais pagam ao tokenizar primeiro).

## Regras
- **Só leitura/medição.** Nenhuma mudança de UI, nenhum componente novo, nenhum gate novo.
- Use os scripts existentes; se algum não suportar por-Page, rode global e diga isso (honesto).
- Commit numa branch `chore/ds-adocao-censo` + abra PR com o `.md` pra [W] revisar.

## Pós-censo (NÃO faça agora — é a próxima decisão de [W])
Com o censo na mão, [W] escolhe a 1ª onda real (sugestão do [CC]: tokenizar a Page com
mais cor crua que já usa componente — ganho máximo, risco mínimo). Aí sim vira onda de porte.
