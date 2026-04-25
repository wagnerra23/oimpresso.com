# Matriz Comparativa estilo Capterra/G2 — oimpresso vs Concorrentes BR (2026-04-25)

> **Fonte:** research executado por agente Claude em 2026-04-25, com WebSearch + WebFetch dos sites oficiais e reviews públicas (App Store, GetApp, Capterra/G2 onde havia dados).
> **Objetivo:** subsidiar decisão de roadmap pra atingir meta R$5mi/ano (ADR 0022).
> **Companion:** [`site_marketing_concorrentes_comunicacao_visual_2026_04_25.md`](site_marketing_concorrentes_comunicacao_visual_2026_04_25.md) (research anterior, foco em copy/visual)

## TL;DR (5 frases)

1. **oimpresso é hoje um ERP genérico (UltimatePOS-fork) com 25 módulos**, fortíssimo em fiscal BR, financeiro/CNAB e PDV — mas NÃO é um ERP vertical de gráfica/comunicação visual no sentido em que **Mubisys, Zênite, Visua, Calcgraf e Calcme** são (FPV, cálculo por m², PCP de impressão, OP com etapas).
2. Diferencial real está na **stack moderna** (Laravel 13.6 + Inertia v3 + React + Tailwind v4) e em **dois módulos que nenhum concorrente vertical entrega**: **Copiloto** (chat IA contextual) e **MemCofre** (cofre de memórias) — porém Copiloto está em construção e ainda não é vendável.
3. Contra os **genéricos (Bling, Omie)** o oimpresso perde em **escala/marca/integrações marketplace** — Bling tem 250+ integrações nativas + app mobile, Omie tem IA fiscal e antecipação de recebíveis em produção.
4. Contra os **verticais gráficos**, faltam: **cálculo por m², FPV, apontamento de máquina, PCP gráfico** — só tem POS adaptado, insuficiente pra escalar ROTA LIVRE e prospectar novas gráficas.
5. Estado atual: **comercialmente um ERP genérico bonito sem cliente** (56 cadastros / 7 ativos / 99% volume num cliente só). Pra R$5mi/ano em 24 meses, precisa **escolher entre virar vertical de comunicação visual OU virar plataforma IA-first sobre UltimatePOS**.

---

## Matriz Feature-by-Feature

Legenda: ✅ Tem completo · 🟡 Tem básico/limitado · ❌ Não tem · ❓ Não confirmado

### Categoria 1 — Específico do nicho gráfico/comunicação visual

| Feature | oimpresso | Mubisys | Zênite (GE) | Alfa Networks | Visua | Calcgraf (NetCalc) | Calcme | Bling | Omie |
|---|---|---|---|---|---|---|---|---|---|
| Cálculo automático por m² | ❌ | ✅ | ✅ | 🟡 | ✅ (FPV) | ✅ (2M orçam./mês) | ✅ | ❌ | ❌ |
| OP com etapas | 🟡 (Manufacturing/IProduction genéricos) | ✅ (PCP+setor.) | ✅ (PCP tempo real) | 🟡 (OS) | ✅ (workflow instalação) | ✅ (PCP+ficha) | ✅ (kanban) | 🟡 | 🟡 |
| Apontamento autom. máquina | ❌ | ❓ | ✅ (coleta direto) | ❌ | 🟡 (monitor web) | ❓ | ❌ | ❌ | ❌ |
| Tabela preço por substrato/acabamento | ❌ | ✅ | ✅ | ❓ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Fluxo orçamento → contrato → OP → entrega | 🟡 (sem etapa contrato) | ✅ (proposta WhatsApp + OS) | ✅ (end-to-end) | 🟡 | ✅ | ✅ | ✅ (Assiname p/ assinatura) | 🟡 | 🟡 |
| 3D / pré-visualização projeto | ❌ | ❓ | ❓ | ❌ | ❌ | 🟡 (GCad) | ✅ (Calcme3D) | ❌ | ❌ |

### Categoria 2 — Fiscal BR

| Feature | oimpresso | Mubisys | Zênite | Alfa | Visua | Calcgraf | Calcme | Bling | Omie |
|---|---|---|---|---|---|---|---|---|---|
| NF-e | ✅ (NfeBrasil) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| NFC-e | ✅ | ❓ | 🟡 | ✅ (SAT/NFC-e) | ❓ | ❓ | ✅ | ✅ | ✅ |
| NFS-e | ✅ | ❓ | 🟡 | ✅ | ❓ | ✅ | ✅ | ✅ | ✅ |
| CT-e | ❌ | ❓ | ❓ | ❌ | ❓ | 🟡 | ✅ | 🟡 (via integração) | 🟡 (via integração) |
| MDF-e | ❌ | ❓ | ❓ | ❌ | ❓ | ✅ | ✅ | 🟡 | ✅ (nativo) |
| SPED contábil/fiscal | 🟡 (Accounting genérico) | ❓ | ✅ | ❓ | ❓ | ✅ | ❓ | ✅ | ✅ |
| Simples/MEI/Lucro Real | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ (com IA fiscal) |

### Categoria 3 — Operacional core

| Feature | oimpresso | Mubisys | Zênite | Alfa | Visua | Calcgraf | Calcme | Bling | Omie |
|---|---|---|---|---|---|---|---|---|---|
| PDV (POS) com leitor | ✅ (UltimatePOS) | ✅ | ✅ | ✅ | 🟡 | ❓ | 🟡 | ✅ | ✅ |
| Multi-loja/Multi-empresa | ✅ (multi-tenant) | ✅ | ✅ | ❓ | ❓ | ❓ | ❓ | ✅ | ✅ |
| Estoque (giro/lote/validade) | ✅ | ✅ | ✅ | ✅ | ✅ (lote+mult.un.) | ✅ (WMS) | ✅ | ✅ | ✅ |
| Compras + cotação | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| CRM com funil | 🟡 (Crm básico) | ✅ (funil+comissões) | ✅ | ❌ | ❌ | ✅ | ✅ (Chatme integrado) | 🟡 | ✅ |

### Categoria 4 — Financeiro

| Feature | oimpresso | Mubisys | Zênite | Alfa | Visua | Calcgraf | Calcme | Bling | Omie |
|---|---|---|---|---|---|---|---|---|---|
| Contas pagar/receber | ✅ (Financeiro Onda 1) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Boletos CNAB | ✅ (CnabDirect mock + 21 bancos planejados) | ✅ | ✅ | ❓ | 🟡 | ✅ | ✅ | ✅ | ✅ |
| Conciliação bancária | 🟡 (planejado) | ✅ | ❓ | ❓ | ❓ | ❓ | 🟡 | ✅ | ✅ (automática) |
| Antecipação recebíveis | ❌ | ❓ | ❓ | ❌ | ❌ | ❌ | ✅ (Calcpay) | ❓ | ✅ |
| Pix integrado | 🟡 (planejado RecurringBilling) | ❓ | ❓ | ❓ | ❓ | ❓ | ✅ | ✅ | ✅ |
| Conta digital PJ embutida | ❌ | ❓ | ❓ | ❌ | ❌ | ❌ | 🟡 (Calcpay) | ✅ (Bling Conta) | ✅ (Omie.Cash) |

### Categoria 5 — Diferencial moderno

| Feature | oimpresso | Mubisys | Zênite | Alfa | Visua | Calcgraf | Calcme | Bling | Omie |
|---|---|---|---|---|---|---|---|---|---|
| App mobile próprio (não responsive) | ❌ | ✅ (iOS+Android) | 🟡 | ❌ | 🟡 | 🟡 | ✅ | ✅ | ✅ |
| API/integrações nativas | 🟡 (Connector) | ❓ | 🟡 | ❌ | ❌ | 🟡 (G-Link) | 🟡 | ✅ (250+) | ✅ (Omie.Hub) |
| BI/Dashboards customizáveis | 🟡 (FusionCharts+Spreadsheet) | ❓ | ✅ | ❌ | 🟡 | ✅ | ✅ | ✅ | ✅ |
| **IA / chat assistente** | 🟡 (**Copiloto + LaravelAI em construção**) | ❌ | ❌ | ❌ | ❌ | ❌ | 🟡 (Chatme=WhatsApp, não IA) | 🟡 (extensões) | ✅ (IA fiscal nativa) |
| Marketplace/E-commerce nativo | 🟡 (Woocommerce sync) | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ (250+) | ✅ (Omie.Hub) |
| Importação de outros sistemas | 🟡 (UltimatePOS importer) | ❓ | ❓ | ❓ | ❓ | ❓ | ❓ | ✅ | ✅ |
| Assinatura digital contrato | ❌ | ❓ | ❓ | ❌ | ❌ | ❌ | ✅ (Assiname) | ❌ | 🟡 |
| **Cofre de memórias / KB** | ✅ (**MemCofre — único no mercado**) | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

### Categoria 6 — Operação SaaS

| Feature | oimpresso | Mubisys | Zênite | Alfa | Visua | Calcgraf | Calcme | Bling | Omie |
|---|---|---|---|---|---|---|---|---|---|
| Onboarding guiado | 🟡 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ (academy) | ✅ (academy) |
| Suporte chat 24/7 | ❌ | 🟡 | ✅ (30+ anos) | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ (premium) |
| Suporte WhatsApp | 🟡 (manual) | ✅ | ✅ | ❓ | ❓ | ❓ | ✅ | 🟡 | ✅ |
| Treinamento estruturado | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| LGPD compliant explícito | ❓ | ❓ | ❓ | ❓ | ❓ | ❓ | ❓ | ✅ | ✅ |
| Backup/exportação dados | 🟡 | ❓ | ❓ | ❓ | ❓ | ❓ | ❓ | ✅ | ✅ |

---

## Notas estimadas (escala G2 1-5)

| Critério | oimpresso (estimado) | Mubisys | Zênite | Visua | Calcgraf | Calcme | Bling | Omie |
|---|---|---|---|---|---|---|---|---|
| Facilidade de uso | 3.5 (UI moderna mas não polida pra public) | 4.5 (4.4 App Store) | 3.5 (legacy mas funcional) | 4.0 | 3.5 | 4.0 | 4.5 | 4.3 |
| Suporte | 2.5 (sem time formal) | 4.5 | 4.5 (30+ anos) | 4.5 | 4.0 | 4.0 | 4.3 | 4.4 |
| Custo-benefício | ❓ (sem tabela pública) | 4.5 (300+ reviews) | 3.8 (premium) | 4.0 | 3.8 | 4.0 | 4.7 (R$55/mês) | 4.2 |
| Específico pro nicho gráfico | **1.5** (não é vertical) | **4.9** (referência) | **4.7** | **4.5** | **4.6** | **4.5** | 1.5 | 1.5 |

> **Mubisys 4.9/5 com 300+ reviews é o ponto de referência do nicho.** Bling/Omie têm milhares de reviews mas em categoria genérica — não competem em "específico pro nicho".

---

## Top 3 GAPS críticos do oimpresso

### GAP 1 — Cálculo por m² + FPV (Formação de Preço de Venda) gráfica
**O que falta:** Tabela de preço dimensional (m², metro linear, lote), cálculo automático considerando substrato + acabamento + instalação + ICMS por estado + comissão. Visua cunhou "FPV"; Calcgraf processa **2 milhões de orçamentos/mês**. Sem isso, o oimpresso **não consegue prospectar gráfica** — o vendedor abre Excel paralelo.
**Esforço:** Alto (módulo novo `PricingFpv` 4-6 semanas + parametrização por substrato/máquina).

### GAP 2 — App mobile próprio + suporte estruturado
**O que falta:** Mubisys/Bling/Omie/Calcme têm app iOS/Android nativo. oimpresso é só responsive. Pra **vendedor externo orçar in-loco**, é deal-breaker. Soma-se: nada de chat 24/7, academy, treinamento estruturado.
**Esforço:** Muito alto (app mobile = 3-4 meses; suporte = contratar pessoa).

### GAP 3 — CT-e/MDF-e + conciliação bancária + antecipação
**O que falta:** Pra gráficas que entregam (banner, fachada, ACM), CT-e e MDF-e são obrigatórios desde 2026 (ajustes SINIEF abril/2026). oimpresso não tem nenhum dos dois. Conciliação bancária e antecipação faltam.
**Esforço:** Médio (CT-e/MDF-e via sped-nfe já planejado; conciliação OFX 2-3 semanas).

---

## Top 3 VANTAGENS reais

### V1 — Stack moderna real
Laravel 13.6 + PHP 8.4 + Inertia v3 + React + Tailwind v4 + Pest + GH Actions. Concorrentes verticais são todos legacy (PHP/Delphi/Java antigos, jQuery/Bootstrap 3-4). Velocidade de iteração permite lançar features em semanas.

### V2 — Copiloto (chat IA contextual) + MemCofre — únicos no mercado vertical
Nenhum vertical tem IA real. Omie tem "IA fiscal" (limitado a classificação tributária). Chatme do Calcme é só WhatsApp, não IA. Copiloto bem entregue (sabe tela atual, dados do user, sugere meta) é diferencial **defensável por 12-18 meses**. MemCofre como knowledge base por business é único.

### V3 — Multi-tenant + extensibilidade UltimatePOS
25 módulos plugáveis (BaseModuleInstallController + ModuleUtil) permitem **vender o core e ativar verticais por business_id**. Bling/Omie monolíticos. Mubisys/Zênite vendem pacote fechado. oimpresso pode ser "ERP modular" — vende só Ponto + Financeiro pra gráfica pequena, full pra grande.

---

## Posicionamento sugerido

3 caminhos viáveis. Recomendado: **B**.

| Caminho | Tese | Veredito |
|---|---|---|
| **A** — "Mubisys mais barato" | Copiar feature-set, cobrar 30% menos | ❌ Mubisys tem 14k usuários + 30 anos. Você é desafiante sem narrativa. |
| **B** — "ERP gráfico com IA + cofre de memória" | Único vertical de CV com IA contextual + MemCofre | ✅ Diferencial defensável; ticket premium |
| **C** — "ERP genérico moderno BR" | Competir com Bling/Omie | ❌ Não ganha de R$55/mês do Bling sem queimar caixa |

**Posicionamento recomendado:** **"O ERP de comunicação visual com IA que substitui seu Mubisys/Zênite — e nunca esquece um cliente."**

---

## Math da meta R$5mi/ano (24 meses)

R$5mi/ano = **R$417k/mês de MRR**.

Combinando com revenue thesis dos módulos (ARQ-0004 Financeiro):
- **Cenário B (recomendado em 11-metas-negocio.md):** 250 clientes × R$1.668/mês
- **Cenário D Misto:** 50 ent × R$5k + 120 médios × R$1,5k + 200 peq × R$440 = R$418k/mês

Pra ticket médio R$497/mês (entre Mubisys e Bling premium): **838 clientes ativos**. Com churn 3%/mês e CAC payback 6 meses, funil de **~50 leads qualificados/mês**.

**Realidade hoje:** 7 clientes ativos, ROTA LIVRE concentra 99%. Gap de 800+ clientes em 24 meses = **33 clientes novos/mês líquidos**. Wagner sozinho não escala — **pressupõe contratação a partir de R$50k MRR** (memória 11-metas-negocio.md).

---

## 3 features prioritárias pra construir nos próximos 6 meses

### #1 — PricingFpv (cálculo por m² + FPV gráfica)
Mata GAP 1. Desbloqueia prospect de qualquer gráfica/CV. Sem isso o resto não importa. **3-4 sprints.**

### #2 — Copiloto v1 production-ready
Sair de "em construção" pra vendável. Foco em 3 use-cases:
- "qual foi o orçamento dessa cliente ano passado"
- "qual minha margem média em ACM esse mês"
- "lembra a Larissa quando o CT-e dela vencer"

É a narrativa de diferenciação. **2-3 sprints.**

### #3 — CT-e + MDF-e + conciliação bancária OFX
Mata GAP 3 fiscal+financeiro de uma vez. Sem isso, gráfica que entrega não compra. **3 sprints (sped-nfe já planejado em ADR fiscal).**

### O que NÃO fazer agora
- App mobile nativo (deal-breaker mas adiável 12 meses se Copiloto compensar)
- Marketplace nativo (nunca vai ganhar do Bling)
- SPED contábil completo (deixa pra quem migrar de Mubisys)

### Métrica de fé
Se em 90 dias do PricingFpv + Copiloto v1 estiverem em produção e a ROTA LIVRE virar case com vídeo + 5 prospects qualificados convertidos via indicação, **confirma a tese**. Senão, pivota pra Caminho A ou C.

---

## Sources

- [Mubisys](https://www.mubisys.com) · [Zênite/ZSL](https://www.zsl.com.br) · [Alfa Networks](https://www.alfanetworks.com.br) · [Visua](https://www.visua.com.br) · [Calcgraf](https://www.calcgraf.com.br) · [Calcme](https://www.calcme.com.br) · [Bling](https://www.bling.com.br) · [Omie](https://www.omie.com.br)
- [Mubisys App Store reviews](https://apps.apple.com/br/app/mubisys/id1623941063)
- [SINIEF ajustes CT-e/MDF-e abril 2026](https://notagateway.com.br/blog/pacote-de-ajustes-sinief-de-abril-de-2026-traz-varias-mudancas-em-ct-e-mdf-e-nf-e-e-nfc-e/)
- [Visua FPV — Grandes Formatos](https://www.grandesformatos.com/visua-gestao-inteligente-para-comunicacao-visual/)
- [Bling integração LOG CT-e](https://blog.bling.com.br/bling-lanca-integracao-com-logcte-para-emissao-de-mdf-e/)
- [Omie MDF-e nativo](https://ajuda.omie.com.br/pt-BR/articles/9898339-configurando-a-emissao-de-mdf-e-e-alterando-o-ambiente-de-emissao)
- [Omie vs Bling GetApp](https://www.getapp.com.br/compare/2063952/2063982/omie/vs/bling)
