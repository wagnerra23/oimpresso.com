---
module: Relatorios
status: parcial
status_nota: "relatórios do ReportController migrando um por PR para Inertia atrás de ?tela=nova; a Blade segue padrão até a F5 ([W])"
updated_at: "2026-10-07"
owner: W
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# BRIEFING — Relatórios · 🟡 PARCIAL

> **O que é:** os relatórios gerais (`/reports/*`, `App\Http\Controllers\ReportController`) — não é módulo nWidart.
> Não confundir com `/financeiro/relatorios` nem `/ponto/relatorios`, que são de outros módulos.

**Estado:** migração Blade → Inertia em curso (thread `sistema/playbook/07`, decisão D2 de [W]: uma tela com
grupos, como o protótipo `relatorios-page.jsx`). Cada relatório migrado:

- responde a Page `Relatorios/<Nome>/Index` só com `?tela=nova`; sem o parâmetro, a Blade de sempre;
- lê a **mesma** consulta/endpoint da Blade — a tela não calcula valor;
- tem um `RUNBOOK-<relatorio>.md` nesta pasta + charter e casos ao lado da Page + teste em
  `tests/Feature/Relatorios/` (valor por dois ou três caminhos e isolamento 98 × 99).

**Quais já migraram:** a lista viva são os `RUNBOOK-*.md` desta pasta e as Pages em `resources/js/Pages/Relatorios/`.

**Falta:** o hub com os grupos e os demais relatórios; a F5 (Blade sai) de cada um é decisão [W].
