---
id: requisitos-superadmin-dashboard-gap
tela: superadmin/Dashboard/Index (/superadmin)
prototipo: prototipo-ui/cowork/superadmin-page.jsx
tela_viva: Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — superadmin/Dashboard/Index

> Protótipo = porte do Blade legado (superadmin-page.jsx:1-9), anterior às telas React (SA-O1..O3, 2026-08). Charter: `Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.charter.md` (Non-Goals/Anti-hooks respeitados, nunca reabertos).

**Veredito:** MOCKUP-STALE nos blocos de número inventado (funil, churn, receita por pacote, "O que fazer primeiro" — Anti-hook do charter + `UC-SADASH-05`), VIVO-À-FRENTE em período/skeleton/vazio/nota do MRR, PARIDADE nas 4 seções do contrato, fila "Vencendo" já registrada como SA-O1b — **2 itens a decidir** (subtítulo com contagens no header; colunas extras dos cadastros recentes).

| Parte | Estado no vivo | Ação |
|---|---|---|
| PageHeader — título | `PageHeader title="Superadmin" moduleNav` (Dashboard/Index.tsx:119); mockup `PageHead titulo="Superadmin"` (superadmin-page.jsx:609) | Nada — paridade |
| PageHeader — subtítulo com contagens (negócios · pacotes ativos · MRR) | `description="Visão geral da plataforma"` fixa (Dashboard/Index.tsx:119); nenhuma contagem no header; o contrato (`prototipo-ui/contrato/superadmin-dashboard.contract.json`) trava só as 4 seções `periodo/kpis/tendencia/recentes`, não o header | **Decidir.** O mockup (superadmin-page.jsx:609) escreve no subtítulo total de negócios, pacotes ativos e MRR computados das listas; o vivo (Dashboard/Index.tsx:119) tem descrição estática, embora o MRR já chegue nas props (:152-154). Construir ou rejeitar por escrito. |
| PageHeader — ações "Comunicador" / "Ver negócios" | Sem botões de ação no header (Dashboard/Index.tsx:119); a navegação entre telas do módulo é o `moduleNav` do PageHeader (`resources/js/Components/shared/PageHeader.tsx:46-50`) | Nada — mock/harness do protótipo (os 2 botões trocam a view do mock via `window.__selectRoute`, superadmin-page.jsx:611-612 — a "view comunicador" nem é destas telas) |
| Segmented de período + nota da janela (`superadmin.dashboard.periodo`) | 4 botões Hoje/Semana/Mês/Ano com `aria-pressed` (Dashboard/Index.tsx:121-141); troca por partial reload `router.reload({ only: [...] })` (:112-115); `janela.rotulo` vem do servidor (:140) | Nada — vivo à frente (recalcula KPIs sem full reload :112-115 e a janela é real :140; no mockup a nota "encerra em 18/08/2026" é literal fixo, superadmin-page.jsx:621) |
| KPI "Novas assinaturas" | `KpisDoPeriodo` → `brl(new_subscriptions)` + nota "soma do valor contratado na janela" (Dashboard/Index.tsx:179-185), fonte `SuperadminDashboardService::statsForPeriod` (:106); mockup :625 | Nada — paridade (a dívida de FONTE deste card — soma o licenciamento legado zerado — já está registrada no charter §Pendências como mudança de VALOR sob REGRA MESTRE, decisão [W]) |
| KPI "Novos cadastros" | `KpisDoPeriodo` → `new_registrations` + nota "cadastro próprio + criados pelo superadmin" (Dashboard/Index.tsx:186); mockup :626 | Nada — paridade |
| KPI "Sem assinatura" | `KpiSemAssinatura` tom `danger` + nota "cadastrou e não assinou" (Dashboard/Index.tsx:191-194), fonte `countNotSubscribedBusinesses` (:48); mockup :627 | Nada — paridade |
| KPI "Receita recorrente (MRR)" | `KpiMrr` com valor de `calcularMrr` (`SuperadminDashboardService.php:170`) e nota que explica o zero / conta ativas e canceladas em 30 d / denuncia fonte indisponível (Dashboard/Index.tsx:197-213) | Nada — vivo à frente (a nota do mockup "+8,4% contra o mês anterior" é string fixa, superadmin-page.jsx:628 = mock; o MRR saiu da lista de Anti-hooks em 2026-08-20 com aprovação [W], charter §Anti-hooks) |
| Bloco "Trial vira assinatura" (funil) | Não renderizado; comentário :12-14 declara de propósito; `SuperadminDashboardContratoTest.php:188` assevera `missing('funil')` | Nada — Non-Goal do charter (Anti-hook "Não inventa número que o banco não tem" · `UC-SADASH-05`; RUNBOOK-dashboard §1 "❌ sem query — SA-O1b") |
| Bloco "Churn" (taxa + motivos) | Não renderizado; o insumo `canceladas` já chega e aparece na nota do MRR (Dashboard/Index.tsx:200, :210); `SuperadminDashboardContratoTest.php:189` assevera `missing('churn')` | Nada — Non-Goal do charter (Anti-hook "Não inventa número que o banco não tem" · `UC-SADASH-05`; charter §Anti-hooks "o que falta é o bloco, não a fonte"; motivos dependem de `rb_subscriptions.churn_reason`, RUNBOOK §1) |
| Bloco "Receita por pacote" | Não renderizado; `SuperadminDashboardContratoTest.php:190` assevera `missing('receitaPorPacote')` | Nada — Non-Goal do charter (Anti-hook "Não inventa número que o banco não tem" · `UC-SADASH-05`; RUNBOOK-dashboard §1 "❌ sem query — SA-O1b") |
| Fila "Vencendo ou vencido" (com Cobrar / Converter / Ver assinatura) | Não existe bloco de vencimento na tela (Dashboard/Index.tsx inteiro, 300 ln) | Nada — decisão já registrada (RUNBOOK-dashboard §1 "🟡 derivável de `findOverdueApproved()` — SA-O1b"; charter §Pendências "em aberto"; os botões do mockup navegam via `window.__selectRoute`, superadmin-page.jsx:696) |
| Tendência mensal (`superadmin.dashboard.tendencia`) | Card com barras, último mês destacado, meta "últimos N meses · valor em <mês>" (Dashboard/Index.tsx:216-253), fonte `buildMonthlyRevenueChart` (:63); copy "Tendência mensal de assinaturas" travada pelo contrato (:225) | Nada — vivo à frente (série contínua com mês zerado — charter Goals SA-O1b; estado vazio :232-235; o mockup :706-716 usa título "de vendas" e `ds().Chart` com fallback nas mesmas barras. A dívida de fonte "barras em zero" já está registrada no charter §Pendências como REGRA MESTRE, decisão [W]) |
| Bloco "O que fazer primeiro" | Não existe lista de pendências na tela | Nada — mock/harness do protótipo (3 itens com nomes de negócio, datas e valor fixos + `window.__selectRoute`, superadmin-page.jsx:721-727; RUNBOOK-dashboard §1 registra "❌ sem query — depende do funil e da fila", sem onda) |
| Cadastros recentes — tabela (`superadmin.dashboard.recentes`) | 5 linhas com Negócio (+ "negócio #id"), Assinatura (rótulo PT-BR via `rotuloAssinatura`), Cadastro (Dashboard/Index.tsx:269-291; `SuperadminController.php:129-146`); mockup :739-748 | Nada — paridade |
| Cadastros recentes — colunas Dono · Pacote · MRR · cidade | Payload traz só `id/nome/criado/assinatura` (`SuperadminController.php:144-146`); a tabela tem 3 colunas (Dashboard/Index.tsx:271-275) | **Decidir.** O mockup (superadmin-page.jsx:739, :743-748) desenha 6 colunas — Dono, Pacote, MRR por negócio e cidade além das 3 do vivo (Dashboard/Index.tsx:271-287). O charter Goals já lista "coluna dono" como alvo do F1 não entregue, sem onda; Pacote/MRR/cidade nem isso. Construir ou rejeitar por escrito. |
| Cadastros recentes — header ("Ver todos" × contagem) | Header com contagem `N negócios` (Dashboard/Index.tsx:261-264); ir pra lista completa é o `moduleNav` do PageHeader (:119) | Nada — paridade (rótulo adaptado: o link "Ver todos" do mockup é `window.__selectRoute`, superadmin-page.jsx:735 — harness) |
| Estado carregando (KPIs · tendência · recentes) | `KpiEsqueleto` ×4 + skeletons dos 2 cards sob `<Deferred>` (Dashboard/Index.tsx:97-107, :144-166) | Nada — vivo à frente (mockup renderiza mocks direto, sem skeleton — superadmin-page.jsx:599-759) |
| Estado vazio (tendência · recentes) | "Nenhuma assinatura registrada nos últimos 12 meses." (Dashboard/Index.tsx:232-235) · "Nenhum negócio cadastrado ainda." (:266-267) | Nada — vivo à frente (mockup não tem `Vazio` nesta view — superadmin-page.jsx:599-759) |

## Recibos de ausência
- `grep -nE 'negócios ·|pacotes ativos|description=.*MRR' Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx` → 0   (subtítulo sem contagens)
- `grep -nE 'Comunicador|Ver negócios|__selectRoute' Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx` → 0   (sem botões de ação no header)
- `grep -nE 'contra o mês|\+[0-9]' Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx` → 0   (sem variação % fixa no MRR)
- `grep -nE 'Trial vira|FUNIL|Funil|>[^<]*funil' Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx` → 0   (sem bloco funil; "funil" só no comentário :12)
- `grep -nE 'Churn|CHURN|>[^<]*churn' Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx` → 0   (sem bloco churn; "churn" só no comentário :12)
- `grep -nE 'Receita por pacote|receitaPorPacote|sa-rpp' Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx` → 0   (sem bloco receita por pacote)
- `grep -nE 'Vencendo|vencid|atraso|Cobrar|Converter' Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx` → 0   (sem fila de vencimento)
- `grep -nE 'fazer primeiro|sa-pend' Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx` → 0   (sem bloco "O que fazer primeiro")
- `grep -nE 'Dono|dono|cidade|>Pacote<|n\.pacote|n\.mrr' Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx` → 0   (recentes sem Dono/Pacote/MRR/cidade)
- `grep -nE 'Ver todos|href=|<Link' Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx` → 0   (sem link "Ver todos" no card)
- `sed -n '599,759p' prototipo-ui/cowork/superadmin-page.jsx | grep -cE 'SkelTable|Vazio|Skeleton|EmptyState'` → 0   (mockup sem skeleton/vazio nesta view)
