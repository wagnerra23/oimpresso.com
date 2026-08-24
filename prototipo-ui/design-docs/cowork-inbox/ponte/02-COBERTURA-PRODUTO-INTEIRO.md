---
sessao: "02"
titulo: Cobertura do produto inteiro — mapa medido, módulo a módulo
autor: "[CC]"
criado: 2026-08-23
base: wagnerra23/oimpresso.com@main (tree d1ccdff91be9) — varredura de `**/Pages/**/*.{charter,casos}.md`, 331 arquivos, lida 2026-08-23T15:31Z
metodo: contagem manual sobre a árvore retornada; ±2 arquivos de margem. O que não medi, digo que não medi.
---

# Sim, dá pra cobrir o produto inteiro — e aqui está o tamanho real dele

> ## ⚠️ ERRATA 2026-08-23 (pós-medição)
> Os números deste mapa (216 charters · 114 casos · 11 contratos) **seguem válidos** — foram lidos do `main`. O que muda é a **conclusão**: eu tratava a cobertura como programa de meses, secundário à ponte. A medição mostra que é **o gargalo primário** — `readiness` dá **29 de 54**, e o que falta nas 25 é exatamente `casos.md`-com-UC + scorecard. S9 sobe de "programa" para **P1**. Ver `06-CORRECAO-MEDIDA.md`.

> A ponte cobria 7 frentes. O produto tem **217 telas com charter no `main`**. Esta é a lista que faltava.

## Os três números que mudam o plano

| Medida | Valor | Leitura |
|---|---|---|
| Telas com `.charter.md` | **216** | a lei existe para quase tudo |
| Telas com `.casos.md` | **114** | **53%** — metade do produto não tem UC verificável |
| Telas com contrato de tela | **11** | **5%** — a catraca de copy cobre um vigésimo |

**Conclusão honesta:** o gargalo do produto inteiro **não é charter** (216 já existem, trabalho feito). É **casos.md** (103 telas sem) e **contrato** (206 telas sem). A ponte que desenhei ataca 13 telas; o produto pede 103.

---

## Mapa por módulo — ✅ trio · ⚠️ só charter · 🔴 anomalia

### Frente A — completos (charter + casos em 100% das telas)

| Módulo | Telas | Nota |
|---|---|---|
| **Cliente** (CRM) | 7 | Create·Edit·Import·Index·Ledger·Map·Show — o mais maduro do produto |
| **Financeiro** | 21 | 19 telas + Unificado (Index·Novo). Cobertura completa de casos |
| **Fiscal** | 7 | Cockpit·Config·Dfe·Eventos·Nfe·Nfse·Sped |
| **NfeBrasil** | 6 | Manifestacao·NfceStatus·Tributacao(4) |
| **OficinaAuto** | 9 | AprovacaoPublica + ServiceOrders(4) + Vehicles(4) |
| **Produto** | 8 | 7 + Unificado. `SellingPrices.charter` tem 57 KB — o maior do repo |
| **RecurringBilling** | 6 | Index·Configuracoes·Faturas·Planos(3) |
| **Jana** | 4 | Chat·Index·Memoria·Pro |
| **kb** | 3 | Graph·Index·Index.v2 (+3 `_components` só charter) |
| **Ponto** | 13 de 22 | Aprovacoes·BancoHoras(2)·Escalas/Form·Espelho(2)·Importacoes(3)·Intercorrencias(3)·Relatorios |
| Menores ✅ | 10 | Backup·Compras·ComunicacaoVisual·Modules·Suporte(2)·User/Perfil·Vestuario/Etiquetas·Officeimpresso/Logs(2)·PaymentGateway(2) |
| **Superadmin** | 4 | Dashboard·Negocios·Assinaturas·Pacotes |

### Frente B — charter sem casos (a dívida real: **103 telas**)

| Módulo | Telas sem casos | Peso |
|---|---|---|
| **Forja** | 12 de 20 | ads/Admin(4)·Activity·Backlog·Board/DetailSheet·Burndown·MyWork·Roadmap/Index·CcSessions·Tasks·Team |
| **Repair** | **13 de 13** | Index·Show·Dashboard·DeviceModels(3)·JobSheet(5)·ProducaoOficina·Status — **módulo inteiro sem um UC** |
| **Essentials** | **13 de 13** | Documents·Holidays·Knowledge(4)·Messages·Reminders·Settings·Todo(4) — idem |
| **governance** | 8 de 9 | Audit·Custos·Dashboard·DriftAlerts·Policies·QualidadeIa·ModuleGrades(2). Só DsRollout tem casos |
| **Whatsapp/Atendimento** | **8 de 8** | CaixaUnificada·Channels(2)·Csat·Macros(2)·Metricas·JanaTemplates — **e a CaixaUnificada tem contrato ATIVO** |
| **Ponto** | 9 de 22 | Welcome·Colaboradores(2)·Configuracoes(2)·Dashboard·Escalas/Index + os 5 que nem charter têm (S2) |
| **Sells** (PDV) | 5 de 9 | Drafts·Edit·Quotations·Subscriptions·Caixa |
| **Purchase** | 4 | Create·Edit·Index·Show |
| **Cms/Site** | 4 | Home·Page·Blogs·BlogPost |
| **Nfse** | 3 | Index·Emitir·Show |
| **TransactionPayment** | 3 | Index·Edit·Show |
| **kb/_components** | 3 | NodeReader·PathsDialog·TroubleshooterDialog |
| **Auditoria** | 2 | Index·Detail |
| **Superadmin/Usuario360** | 2 | Index·Show |
| **StockAdjustment** | 2 | Index·Create |
| **StockTransfer** | 2 | Index·Create |
| **Site** | 2 | Login·Register |
| Avulsas | 6 | Home/Index·Manufacturing·ConsultaOs·Tarefas·Whatsapp/Settings·Whatsapp/Templates·KB/Graph·Superadmin/Site/Pricing |

### Frente C — anomalias medidas

| # | Anomalia | Onde |
|---|---|---|
| A.01 | `Estoque/Movimentacao.casos.md` (13 KB) **sem charter** — casos órfão, o inverso do gap comum | `resources/js/Pages/Estoque/` |
| A.02 | `Manufacturing/Index.charter.md` existe, mas o módulo Produção/OP não tem mais nada | 1 tela |
| A.03 | `kb/Index` **e** `kb/Index.v2` coexistem com trio completo cada — qual é a viva? | 2 trios |
| A.04 | `Sells/Create` e `Sells/CreateV3` idem | 2 trios |
| A.05 | `Financeiro/Unificado/Novo` tem charter+casos e **nenhum `.tsx`** | já era 6.12 |
| A.06 | `Repair` e `Essentials` — 26 telas, zero casos, zero contrato | maior buraco único |

---

## Revalidação de cada etapa da ponte, contra este mapa

| Etapa | Veredito revalidado | O que muda |
|---|---|---|
| **S1** Build mecânico | ✅ **inalterada** | 5 arquivos, escopo fechado, não depende do produto |
| **S2** Trio órfão (13) | ✅ **confirmada e agora medida** | O `main` tem `Ponto/Welcome`, `Colaboradores/Index`, `Configuracoes/Index`, `Escalas/Index` **só com charter** — bate exatamente com a lista de casos faltantes de S2. Ponto tem 22 telas; 13 completas, 9 pendentes |
| **S3** Curadoria | ⚠️ **cresce** | 3.23 (12 subpastas) pode revelar F1 de módulos que nem entraram nesta conta |
| **S4** Diff resíduo | ✅ inalterada | |
| **S5** Contratos | ⚠️ **subdimensionada** | Eu tratava "14 seções recortadas" como a dívida. A dívida real é **206 telas sem contrato nenhum** |
| **S6** Implantação (11 telas) | ⚠️ **é 5% do produto** | Continua correta como *piloto*; deixa de se chamar "a ponte" e passa a ser "a onda 1 da ponte" |
| **S7** Pós-merge | ✅ inalterada | |
| **S8** Encerramento | ⚠️ **critério muda** | Guard verde com allowlist vazia fecha a *esteira*, não a *cobertura*. São dois fins diferentes |

---

## S9 · Cobertura do produto inteiro (novo bloco — 12 processos)

Estratégia: **não** escrever 103 casos.md à mão. Cobrir por **onda de módulo**, priorizando por risco × uso real.

| # | Processo | Critério | Estado |
|---|---|---|---|
| 9.01 | Fechar **Ponto** (9 telas) — módulo com cliente vivo e lei CLT | risco legal | ⬜ |
| 9.02 | Fechar **Whatsapp/Atendimento** (8) — CaixaUnificada tem contrato ATIVO sem casos | contrato sem UC é gate falso | ⬜ |
| 9.03 | Fechar **Sells/PDV** (5) — é o caixa da Larissa | uso diário | ⬜ |
| 9.04 | Fechar **Repair** (13) — módulo inteiro nu, e tem persona (Técnico, tablet) | maior buraco | ⬜ |
| 9.05 | Fechar **Essentials** (13) — 13 telas nuas, uso baixo | maior buraco, menor risco | ⬜ |
| 9.06 | Fechar **Forja** (12) — ferramenta interna | risco baixo, [W] decide se vale | ⛔ |
| 9.07 | Fechar **governance** (8) — é o dono dos required-checks | meta-risco: o guardião sem lei | ⬜ |
| 9.08 | Fechar **Purchase + Stock*** (8) — Compras/Estoque | + resolver A.01 | ⬜ |
| 9.09 | Resolver duplicatas A.03/A.04 (kb v1/v2, Sells Create/V3) — qual morre? | [W] | ⛔ |
| 9.10 | Charter para `Estoque/Movimentacao` (A.01) | casos órfão | ⬜ |
| 9.11 | Contrato de tela para as telas que já têm trio ✅ — **114 candidatas**, começar pelas 21 do Financeiro | onde há UC, cabe contrato | ⬜ |
| 9.12 | Meta declarada por [W]: cobertura de casos aceitável é 100% ou "só o que tem cliente"? | define se S9 acaba | ⛔ |

**Ordem sugerida:** 9.01 → 9.02 → 9.03 → 9.07 → 9.08 → 9.04 → 9.10 → 9.11 → (9.05 e 9.06 por último, se [W] quiser).

---

## Contagem final revalidada

| Bloco | Processos |
|---|---|
| S1–S8 (a ponte) | 124 + 12 telas |
| **S9 (o produto)** | **12 processos → 103 telas de casos + até 206 contratos** |
| **Total** | **136 processos · 217 telas mapeadas** |

## A frase honesta

Dá pra cobrir o produto inteiro, sim — mas não é a mesma tarefa. **A ponte é finita** (124 processos, semanas). **A cobertura é um programa** (103 telas de casos, meses), e só faz sentido se 9.12 for respondida: cobertura total, ou só onde há cliente. Eu recomendo **só onde há cliente** — Ponto, Atendimento, Sells, Repair, Financeiro — e deixar Essentials e Forja explicitamente descobertos, declarados no `memory/`, em vez de fingir dívida que ninguém vai pagar.
