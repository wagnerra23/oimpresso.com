---
date: "2026-09-21"
topic: "Auditoria da hipótese de remover baselines de tolerância"
authors: [C]
prs: []
---

# Auditoria dos baselines de tolerância

## TL;DR

Wagner opinou que baseline nem deveria existir e pediu conferência. A objeção foi confirmada para baseline de tolerância: o verde de vários requireds significa apenas “não aumentou em relação à dívida”. Medição no `origin/main` e8de1317: PHPStan ignora 6.677 ocorrências, UI lint tolera 7.594, ESLint 2.339, Stylelint 440, typecheck 333, a11y 246, layout 2.450; o baseline multi-tenant grandfathera 66 Models. Há ainda centenas de isenções de governança.

O `baseline-tamper-guard` cobre parte dos schemas, aceita `BASELINE-ABSORB`/PR isolado e não constava nos requireds vivos consultados. Visual contém 104 snapshots; para telas do Cowork, devem ser recibos e não autoridade — a comparação forte é protótipo canônico e aplicação renderizados no mesmo run.

Não se recomenda apagar arquivos cegamente: alguns são contratos/inventários/snapshots/fixtures e perder o consumidor remove detecção. Direção recomendada: zero tolerância em código tocado; zerar a dívida em ondas; zero grandfather Tier 0; comparação visual direta com o protótipo; renomear inventários; só remover cada baseline após substituir o gate por critério absoluto. Mudança de enforcement exige ADR nova. Relatório de prova: [`memory/audits/2026-09-21-baselines-de-tolerancia.md`](../audits/2026-09-21-baselines-de-tolerancia.md).

Nenhum baseline foi alterado, absorvido ou regenerado. Nenhum Pest/PHPStan local. Sem commit/push/merge. MCP do projeto indisponível nesta sessão; estado vivo não inventado.
