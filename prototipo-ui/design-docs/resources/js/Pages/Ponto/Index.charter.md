---
id: resources-js-pages-ponto-index-charter
page: /ponto
component: resources/js/Pages/Ponto/Index.tsx
owner: wagner
status: draft
status_detail: F1 protótipo — aguarda [W]
last_validated: "2026-08-20"
parent_module: Ponto
states: [default, loading, vazio]
related_prototype: prototipo-ui/cowork/ponto/ponto-page.jsx
contrato: prototipo-ui/contrato/ponto-painel.contract.json
tier: A
charter_version: 1
---

# Page Charter — /ponto (Painel do Ponto)

> **Origem:** Modules/Ponto/Resources/views/dashboard/index.blade.php
> Importado no F1 [CC] em 2026-08-20 para `prototipo-ui/cowork/ponto/`; a copy literal e a ordem das
> seções ficam no contrato (`prototipo-ui/contrato/ponto-painel.contract.json`), quando existe.
>
> **Persona:** Wagner (escritório, 1440px) e Eliana (RH) — leem o dia antes de agir.

---

## Mission (1 frase)

Dizer em ate 5s o que trava o fechamento do mês: quantas intercorrências esperam decisão, quantos dias estão em divergência, e quem está presente/atrasado/faltando hoje.

---

## Goals — faz

- 6 KPIs do DashboardController (colaboradores ativos, presentes agora, atrasos, faltas, HE do mês, aprovações pendentes) — todos clicáveis pro destino
- Nota de fechamento com a consequência ("o AFD sai com a jornada errada"), não só o número
- Fila de aprovações (as pendentes) com atalho pra fila completa
- Atividade recente: últimas marcações do dia com tipo, NSR e origem
- `Inertia::defer` em kpis, aprovações e atividade

## Non-Goals — NÃO faz

> Anti-alucinação. Cada item vira GUARD test.

- ❌ **NÃO decide intercorrência** aqui — o Painel conta e leva pra Aprovações
- ❌ **NÃO recalcula** nada: reapurar é ação explícita no cabeçalho do módulo
- ❌ **NÃO mostra zero como resultado** quando não há apuração — é empty state
- ❌ **NÃO duplica estado**: KPI, badge da aba e fila leem a mesma fonte

---

## Anti-hooks (sinais de drift)

- ⚠️ KPI que não bate com a aba de origem — estado duplicado (aconteceu 2× no F1)
- ⚠️ "Presentes agora" derivado de cache velho — o número é de tempo real ou não existe
- ⚠️ Aparecer gráfico decorativo sem dado atrás

---

## Invariantes do módulo (valem aqui também)

- Marcação é **append-only** (Portaria MTP 671/2021 Art. 85): correção é anulação + nova marcação
- `business_id` escopa toda query (ADR 0093, Tier 0)
- Nenhum número inventado: sem dado, empty state que explica por quê
- Nenhuma cor fora dos tokens do DS vivo; PT-BR em toda UI cliente-facing
