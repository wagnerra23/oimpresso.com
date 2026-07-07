# ADR UI-0019 · Sidebar light DEFINITIVO — supersede (consolida) UI-0009 + UI-0014; dark-sidebar de protótipo rejeitado permanentemente

- **Status**: accepted
- **Data**: 2026-07-07
- **Aprovado em**: 2026-07-07 — Wagner explícito, palavras textuais: *"sidebar permanece light, foi superado e isso ficou como está hoje. revogue as anteriores."*
- **Decisores**: Wagner (decisão final), Claude Code (executor)
- **Categoria**: ui · shell · governança
- **Supersede (consolida)**: [UI-0009](0009-cockpit-sidebar-light-padrao.md) — sidebar light padrão · [UI-0014](0014-sidebar-light-mantida-v2-parcial.md) — light mantida (v2 parcial)
- **Rejeita permanentemente**: sidebar dark de QUALQUER protótipo Cowork (incl. o handoff vigente `prototipo-ui/cowork/financeiro-page.jsx`, que renderiza shell dark com sidebar escura)
- **Refs**:
  - [UI-0013](0013-constituicao-ui-v2-camadas.md) — Constituição UI v2 (permanece; só o item sidebar-dark segue não-adotado)
  - [financeiro-unificado-visual-comparison.md §Round 2026-07-07](../../../Financeiro/financeiro-unificado-visual-comparison.md) — inventário por região que disparou a re-decisão (Região 7 Tema/Shell)

## Contexto

As decisões anteriores mantinham a sidebar light, mas com caráter aberto/provisório:

- **UI-0009** (2026-05-04): light "padrão" — escolha do Wagner sobre a versão dark inicial do Cockpit.
- **UI-0014** (2026-05-24): light "mantida" ao adotar a Constituição UI v2 **parcialmente** — o título e o corpo deixavam a porta aberta ("sem dark sempre", adoção parcial, possibilidade de re-visita).

Em 2026-07-07, o inventário por região prod×protótipo (199 comparações) expôs de novo a divergência: o protótipo Cowork do Financeiro renderiza **shell dark com sidebar escura**, enquanto a produção mantém a sidebar **light mesmo com o conteúdo em dark-mode** (medido ao vivo: body `oklch(0.137 …)` escuro, sidebar clara). Apresentada a divergência, Wagner encerrou a questão em definitivo.

## Decisão

1. **A sidebar do oimpresso é LIGHT, em caráter DEFINITIVO** — o comportamento vigente em produção em 2026-07-07 (sidebar clara no tema light E no tema dark do conteúdo) é o canon do shell.
2. **Esta ADR passa a ser a referência única** sobre o tema. UI-0009 e UI-0014 ficam superseded (históricas, append-only — não editar).
3. **Nenhum protótipo Cowork é fonte para o tema da sidebar.** Handoff que traga shell/sidebar dark: a região "Shell/Tema" é marcada `PROD_A_FRENTE`/decisão-vigente no inventário e **não gera gap nem task**.
4. Re-abrir o tema exige **nova ADR com `supersede: [UI-0019]` + aprovação explícita do Wagner** — proibido re-propor por iniciativa de agente (classe "ideia avaliada e descartada", `memory/proibicoes.md`).

## Consequências

- Agentes de design/aplicação de protótipo param de tratar a sidebar dark do Cowork como divergência a corrigir — some da fila de gaps.
- O TweaksPanel/personalização de accent do shell não é afetado (ADR 0190 primary roxo 295 segue).
- `CLAUDE.md` §Constituição UI v2 atualizado no mesmo PR para apontar UI-0019 como vigente.
