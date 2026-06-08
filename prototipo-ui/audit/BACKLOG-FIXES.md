# BACKLOG de fixes — derivado da worklist (regras mecanizadas)

> **GERADO** por `backlog.mjs` — proposta pra Wagner aprovar antes de virar task MCP (publication-policy). Não edita à mão.
> A alavancagem abaixo é **só das 7 regras mecanizadas** — as julgadas (R5/R8/R10) entram na Fase 2.

## A pergunta "pode melhorar?" em número

- **239** telas pontuadas · **17** abaixo de 70 na métrica mecanizada (dívida de conformidade-DS).
- **92** telas falham **1 única regra** mecanizada → 1 PR cirúrgico fecha cada.
- A maior alavancagem é **lote por regra** (codemod 1× aplica em N telas) — ver tabela abaixo.

> ⚠️ **Honestidade:** a nota mecanizada subir não é a nota do **board** (UX holística) subir — o board pesa dimensões (Speed-to-task, hierarquia, affordance) que o mecanizado ignora. Fix mecanizado é **necessário, não suficiente**: telas-stub (ex `Jana/Brief`, `Repair/JobSheet`) precisam da Fase 2 + trabalho de UX, não só codemod. Os lotes abaixo fecham a **conformidade-DS**, que é metade do caminho pro ≥70 do board.

## Lotes por regra (codemod 1× aplica em N telas — ordenado por alcance)

| Regra | O que é | Telas | Esforço | Risco | PR sugerido (fix) |
|---|---|--:|:--:|---|---|
| **R2** | elemento nativo → DS | 141 | M | visual · gate | <select>/<input>/<textarea>/<table> → @/Components/ui |
| **R7** | status bg-fill | 80 | M | visual · heurística | bg-*-100 → dot+texto (confirmar AP7 com agente) |
| **R1** | cor crua → token | 40 | M | visual · gate | codemod hex/oklch/rgb → token DS (roxo 295) |
| **R6** | emoji | 21 | S | visual leve | emoji → ícone lucide |
| **R4** | ícone não-lucide / svg inline | 18 | S | visual leve | svg/lib → lucide-react |
| **R9** | <main> aninhado | 13 | S | ✅ estrutural | <main> → <div role="region"> |
| **R3** | localStorage sem prefixo | 1 | S | ✅ INVISÍVEL | prefixar `oimpresso.<mod>.*` |

## Quick-wins (1 regra só — PR cirúrgico)

| Tela | Nota | Regra única | ds/* |
|---|--:|:--:|--:|
| `Purchase/Create` | 77 | R2 | 11 |
| `Purchase/Edit` | 80 | R2 | 8 |
| `RecurringBilling/Planos/Create` | 80 | R2 | 8 |
| `RecurringBilling/Planos/Edit` | 80 | R2 | 8 |
| `Repair/DeviceModels/Create` | 81 | R2 | 7 |
| `Repair/DeviceModels/Edit` | 81 | R2 | 7 |
| `Financeiro/Extrato/Index` | 82 | R2 | 6 |
| `Repair/JobSheet/Edit` | 82 | R2 | 6 |
| `Ponto/Intercorrencias/Create` | 83 | R2 | 5 |
| `RecurringBilling/Configuracoes/Index` | 83 | R7 | 9 |
| `Atendimento/Channels/Show` | 84 | R2 | 4 |
| `MemCofre/Modulo` | 84 | R2 | 4 |
| `Ponto/BancoHoras/Show` | 84 | R2 | 4 |
| `Admin/FeatureFlags/Show` | 85 | R2 | 3 |
| `Ponto/Aprovacoes/Index` | 85 | R2 | 3 |
| `Atendimento/Macros/Index` | 86 | R2 | 2 |
| `Atendimento/Macros/Variants` | 86 | R2 | 2 |
| `OficinaAuto/ServiceOrders/Create` | 86 | R2 | 2 |
| `Site/Login` | 86 | R2 | 2 |
| `ads/Admin/Confidence` | 87 | R2 | 1 |
| `ads/Admin/Patterns` | 87 | R2 | 1 |
| `Compras/components/VisibilidadeColunas` | 87 | R2 | 1 |
| `Fiscal/Eventos` | 87 | R2 | 1 |
| `MemCofre/Chat` | 87 | R2 | 1 |
| `MemCofre/Memoria` | 87 | R2 | 1 |
| `OficinaAuto/ServiceOrders/Show` | 87 | R1 | 1 |
| `Ponto/BancoHoras/Index` | 87 | R2 | 1 |
| `Ponto/Escalas/Form` | 87 | R2 | 1 |
| `Ponto/Espelho/Show` | 87 | R2 | 1 |
| `Repair/JobSheet/AddParts` | 87 | R2 | 1 |
| `Repair/JobSheet/Index` | 87 | R2 | 1 |
| `Repair/Status/Index` | 87 | R2 | 1 |
| `Site/Blogs` | 87 | R2 | 1 |
| `Site/Register` | 87 | R2 | 1 |
| `Admin/FeatureFlags/Index` | 88 | R2 | 0 |
| `Admin/Index` | 88 | R1 | 0 |
| `ads/Admin/Skills/Index` | 88 | R2 | 0 |
| `ads/Admin/Skills/Show` | 88 | R2 | 0 |
| `Atendimento/Csat/Index` | 88 | R2 | 0 |
| `Atendimento/Inbox/Index` | 88 | R1 | 0 |
| `Auditoria/Detail` | 88 | R2 | 0 |
| `Auditoria/Index` | 88 | R2 | 0 |
| `Cliente/Show` | 88 | R7 | 4 |
| `Compras/components/Drawer` | 88 | R2 | 0 |
| `Compras/components/GradeMatrixInput` | 88 | R2 | 0 |
| `Essentials/Documents/Index` | 88 | R2 | 0 |
| `Essentials/Holidays/Index` | 88 | R2 | 0 |
| `Essentials/Todo/Index` | 88 | R2 | 0 |
| `Essentials/Todo/Show` | 88 | R2 | 0 |
| `Financeiro/AssinaturaAtualizar` | 88 | R2 | 0 |
| `Financeiro/ContasBancarias/components/ConfigurarBoletoSheet` | 88 | R1 | 0 |
| `Fiscal/Nfe` | 88 | R2 | 0 |
| `Fiscal/Nfse` | 88 | R2 | 0 |
| `governance/ModuleGrades/Show` | 88 | R7 | 4 |
| `Jana/components/JanaAreaHeader` | 88 | R1 | 0 |
| `Manufacturing/Index` | 88 | R2 | 0 |
| `MemCofre/Dashboard` | 88 | R2 | 0 |
| `NfeBrasil/Transactions/NfceStatus` | 88 | R1 | 0 |
| `Ponto/Colaboradores/Index` | 88 | R2 | 0 |
| `Ponto/Configuracoes/Reps` | 88 | R2 | 0 |
| `Ponto/Escalas/Index` | 88 | R2 | 0 |
| `Ponto/Espelho/Index` | 88 | R2 | 0 |
| `Ponto/Importacoes/Index` | 88 | R2 | 0 |
| `Ponto/Intercorrencias/Index` | 88 | R2 | 0 |
| `Sells/Quotations` | 88 | R2 | 0 |
| `Sells/Subscriptions` | 88 | R2 | 0 |
| `superadmin/Usuario360/Index` | 88 | R2 | 0 |
| `Tarefas/Index` | 88 | R2 | 0 |
| `_Showcase/Components` | 88 | R2 | 0 |
| `Whatsapp/Templates/Index` | 89 | R7 | 3 |
| `ads/Admin/Tools` | 90 | R7 | 2 |
| `ads/Admin/Decisoes` | 91 | R7 | 1 |
| `Cliente/Map` | 91 | R9 | 1 |
| `Produto/Edit` | 91 | R9 | 1 |
| `ProjectMgmt/Activity/Index` | 91 | R7 | 1 |
| `Repair/JobSheet/Show` | 91 | R7 | 1 |
| `Vestuario/Etiquetas/Index` | 91 | R7 | 1 |
| `Admin/ScreenReviewDashboard` | 92 | R7 | 0 |
| `ads/Admin/Projects` | 92 | R7 | 0 |
| `ads/Admin/Skills/Edit` | 92 | R7 | 0 |
| `ads/Admin/Skills/Review` | 92 | R7 | 0 |
| `Financeiro/Advisor/Dashboard` | 92 | R9 | 0 |
| `kb/Graph` | 92 | R7 | 0 |
| `OficinaAuto/AprovacaoPublica` | 92 | R7 | 0 |
| `ads/Admin/DecisaoShow` | 94 | R6 | 2 |
| `Financeiro/Advisor/Login` | 94 | R4 | 2 |
| `Jana/Dashboard` | 94 | R4 | 2 |
| `ProjectMgmt/MyWork/Index` | 94 | R6 | 2 |
| `Site/Pricing` | 95 | R6 | 1 |
| `ads/Admin/Graph` | 96 | R6 | 0 |
| `ProjectMgmt/Burndown/Index` | 96 | R4 | 0 |
| `Repair/Dashboard/Index` | 96 | R4 | 0 |

## Telas <70 na métrica mecanizada (dívida de conformidade-DS — atacar primeiro)

| Tela | Nota mec | Regras a fechar | ds/* |
|---|--:|---|--:|
| `Financeiro/Unificado/Index` | 46 | R1 R2 R4 R6 R7 | 14 |
| `Admin/GovernanceV4Dashboard` | 55 | R1 R2 R4 R7 | 9 |
| `RecurringBilling/Index` | 55 | R1 R2 R4 R7 | 9 |
| `Cliente/Index` | 56 | R1 R2 R7 | 12 |
| `Jana/Cockpit` | 56 | R1 R2 R4 R6 R7 | 4 |
| `Sells/Edit` | 56 | R1 R2 R7 | 12 |
| `Financeiro/Caixa/Index` | 58 | R1 R2 R6 R7 | 6 |
| `RecurringBilling/Faturas/Index` | 58 | R1 R2 R7 | 10 |
| `Financeiro/Fluxo/Index` | 61 | R1 R2 R7 | 7 |
| `RecurringBilling/Planos/Index` | 61 | R1 R2 R7 | 7 |
| `Financeiro/Conciliacao/Index` | 63 | R1 R2 R6 R7 | 1 |
| `Jana/Admin/Qualidade/Index` | 66 | R1 R2 R4 R6 | 2 |
| `Repair/Index` | 66 | R1 R2 R7 | 2 |
| `Compras/Index` | 67 | R1 R2 R9 | 1 |
| `Financeiro/ContasBancarias/Index` | 68 | R1 R2 R7 | 0 |
| `Financeiro/ContasReceber/Index` | 68 | R1 R2 R7 | 0 |
| `Financeiro/Dre/Index` | 68 | R1 R2 | 8 |

## Sequência recomendada (receita 1× → lote)

1. **Lotes seguros primeiro** (✅ R3 localStorage + R9 `<main>`) — invisíveis/estruturais, fecham sem gate visual.
2. **R6 emoji + R4 ícone** — visual leve, codemod → lucide.
3. **R1 cor crua** — maior alcance, mas visual → passa pelo gate (screenshot Wagner).
4. **R7 status-fill** — heurística ampla, agente Fase 2 confirma AP7 real antes do codemod.
5. **R2 nativo → DS** — por tela (mapear props), casa com a Matriz `ds/*` já existente.

> Cruzar com [PLANO-DESIGN-TELAS-2026-05-31](../../memory/governance/scorecards/PLANO-DESIGN-TELAS-2026-05-31.md): os lotes acima são a **versão mecanizada/medida** dos 9 padrões P1-P9 daquele plano (P1 cor = R1, P5 nativo = R2, P6 emoji = R6...). Telas <70 aqui que NÃO estavam nas 44 do board = candidatas novas (conformidade que a nota holística mascarava).