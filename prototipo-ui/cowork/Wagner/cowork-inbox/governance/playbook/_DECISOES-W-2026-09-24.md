# _DECISOES-W-2026-09-24 — Governança: a vista "Notas dos módulos" sai do design

> **Estatuto:** pedido de edição DO COWORK (protótipo + contrato + charter + casos da Governança).
> O Code não edita o espelho: isso derruba o check required "espelho — mexeu depois de verificar".
> A mudança nasce no Cowork e desce no próximo handoff.

## Resposta de [W]

| id | pergunta | resposta | fonte |
|---|---|---|---|
| G-NOTAS | A vista "Notas dos módulos" continua no protótipo e no contrato, já que o código a removeu? | **Sai.** | [W] 2026-09-24, no chat do gerente de ondas ("pode sair") |

## Por quê

A rubrica module-grade foi aposentada pela **ADR 0399**. A aba `module-grades` saiu do
`Modules/Governance/Http/Controllers/DataController.php` no **#7283**. O design ainda desenha a
vista, então o contrato cobra uma tela que o produto não tem mais.

Medido em `origin/main` em 2026-09-24:

| artefato | onde a vista aparece |
|---|---|
| `governance.contract.json` | `tabs` (5º rótulo), seções `notas-kpis`, `notas-faixas`, `notas-tabela`, `notas-gate-ci`, e o Non-Goal "Edição de nota, peso de rubrica ou disparo de avaliação na tela de notas" |
| `Index.charter.md` | frontmatter `page:` (lista das vistas), a linha da tabela de vistas `/governance/module-grades`, **R8**, a menção em **R11**, e o link para a ADR 0155 |
| `Index.casos.md` | frontmatter `page:` e a seção `## Notas dos módulos` com os UCs dela |
| `Felipe/governance-page.jsx` | `VIEWS` (`{ id: "notas", label: "Notas dos módulos" }`) e o render da vista |

## Edição pedida

1. Protótipo: tirar a vista `notas` do `VIEWS` e o render dela. As 4 vistas que ficam: Painel · Políticas · Auditoria · Drift.
2. Contrato: `tabs` com 4 rótulos; apagar as 4 seções `notas-*` e o Non-Goal sobre a tela de notas.
3. Charter: tirar a vista da lista `page:` e da tabela; R8 sai; R11 perde a menção. O link da ADR 0155 vira ponteiro para a ADR 0399 (aposentadoria).
4. Casos: tirar a vista do `page:` e a seção `## Notas dos módulos`. Os UCs dela saem de propósito, não por esquecimento: não há tela para prová-los.

## O que depende disto

Refazer o **#7138** (rótulos PT-BR das abas), fechado em 2026-09-24 por conflito: o contrato novo
da sub-nav nasce com as 4 abas vivas.
