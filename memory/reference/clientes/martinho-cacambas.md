---
slug: martinho-cacambas
business_id: 164
razao_social: MARTINHO CAÇAMBAS LTDA
cnpj: TBD-perguntar-wagner
status: piloto-ativo
vertical_principal: oficina-auto-locacao-cacamba
sub_vertical: caçamba-avulsa-entulho-obra
cnae: 4581-4/00
cidade_uf: TBD-perguntar-wagner/SC
distancia_km_wagner: 20
inicio_relacionamento: 2026-05-13
canary_inicio: 2026-05-19
faturamento_anual_brl: 6281171.55
funcionarios_total: 20
champions_oimpresso:
  - slug: lara
    role: estoque
  - slug: dani
    role: financeiro
decisor_principal: jair
decisor_secundario: kamila (esposa do Jair · quem manda depois)
hierarquia_decisao:
  - jair (dono majoritário · #1)
  - kamila (esposa Jair · #2 — propos dual-system)
  - lara (filha do Martinho · champion estoque)
  - dani (financeiro · champion)
sistema_anterior: Office Comercial Delphi (WR Sistemas legacy)
concorrentes_avaliados_pausados:
  - Highsoft
pricing_mensal_brl: 830
arquitetura_migracao: dual-sync-delphi-master-oimpresso-viewer
perfil_legacy: ../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md
timezone: America/Sao_Paulo
ultima_atualizacao: 2026-05-14
proxima_revisao: 2026-05-19
---

# MARTINHO CAÇAMBAS LTDA (business_id = 164)

Cliente piloto Modules/OficinaAuto sub-vertical 3 — **Locação caçamba avulsa entulho/obra** (CNAE 4581-4/00). Dono majoritário **Jair** endossou 14/maio noite · **Kamila (esposa Jair)** é #2 decisora · propôs dual-system. Champions duplo **Lara (filha do Martinho · estoque)** + Dani (financeiro). Co-design presencial 20km viável. Highsoft (concorrente) pausado. Arquitetura dual-system Delphi master + oimpresso viewer com sync near-realtime.

> ⚠️ **Hierarquia decisão (Wagner reforçou 2026-05-14 noite):** Jair (#1 dono majoritário · marido Kamila) > **Kamila (#2 esposa do Jair · quem manda depois)** > Lara (filha do Martinho · estoque) + Dani (financeiro). Kamila NÃO é filha do Jair · NÃO é irmã da Lara · Lara NÃO é filha do Jair.

## 1. Identificação

| Campo | Valor |
|---|---|
| Razão social | MARTINHO CAÇAMBAS LTDA |
| Hash legacy | `Cliente_731814` |
| Banco Firebird | `D:\DadosClientes\MartinhoCacamba\Dados\BANCO.FDB` (alias `MartinhoServidor`) |
| LAN Firebird | `192.168.0.55:3050` (acesso via servidor-crm Wagner) |
| Distância escritório | ~20km Wagner-Martinho · co-design presencial viável |
| Onboarding | 2026-05-13 (reunião 10h Wagner+Martinho) → 2026-05-14 noite (Felipe presencial + endosso Jair) |
| Status comercial | piloto ativo · canary inicia semana 19/maio |
| business_id oimpresso | 164 |
| Timezone | America/Sao_Paulo |

## 2. Stakeholders

| Nome | Papel | user biz=164 | sistema usado | papel canary |
|---|---|---|---|---|
| **[Jair](../funcionarios/martinho-cacambas/jair.md)** | **Dono majoritário · #1 decisor** | (sem user no oimpresso ainda) | Aprovou estratégia 14/maio noite | **decisor-principal** |
| **[Kamila](../funcionarios/martinho-cacambas/kamila.md)** | **Esposa do Jair · #2 decisora (quem manda depois do Jair)** · operação POS+vendas | id=292 `kamila-164` | **Continua Delphi** (operação) · pausou avaliação Highsoft · propôs dual-system | continua-legacy-com-poder-decisao-2 |
| [Martinho](../funcionarios/martinho-cacambas/martinho.md) | Sócio · dá nome empresa · **pai da Lara** | (sem user no oimpresso ainda) | Aprovou piloto 13/maio | decisor-secundario |
| **[Lara](../funcionarios/martinho-cacambas/lara.md)** | **Filha do Martinho · responsável ESTOQUE** | (criar pré-canary) | Entra oimpresso semana 19/maio | **champion-oimpresso** |
| **[Dani / DANIELLI](../funcionarios/martinho-cacambas/dani.md)** | **Financeiro** | id=297 `danielli-164` | Entra oimpresso semana 19/maio | **champion-oimpresso** |
| [Rodrigo da Silva](../funcionarios/martinho-cacambas/rodrigo.md) | Vendedor (Google Form Checklist Mecânica) | id=294 `rodrigo da silva-164` | Delphi | continua-legacy |
| [Eduardo](../funcionarios/martinho-cacambas/eduardo.md) | Vendedor | id=298 `eduardo-164` | Delphi | continua-legacy |
| Andre / Evandro / Luiza Correa / Junior / Teste | Outros operadores Delphi | id=290..299 | Delphi | continua-legacy |
| Leonardo · Leoni · Arthur · Ramon | 4 mecânicos | (sem user — não opera sistema) | Google Form Checklist Mecânica 8 páginas | nao-opera-sistema |

**Total ~20 funcionários no escritório · 4 vendedores ativos + 4 mecânicos.**

> User `wagner-dev@oimpresso.com` (id=574, senha `WagnerDev2026!`) criado por Wagner pra dev — **deletar pré-canary**.

## 3. Saúde financeira (snapshot 12m · 2026-05-11)

| Métrica | Valor |
|---|---:|
| Receita 12m | **R$ 6.281.171,55** (~R$ 632k/mês médio) |
| Despesa 12m | R$ 4.957.121,01 |
| Resultado 12m | R$ 1.324.050,54 (margem ~21%) |
| **A receber vencidas** | **R$ 4.819.643,60 (76.7% inadimplência)** — fóssil 2015-2017 |
| A pagar vencidas | R$ 3.356.491,39 |
| Lançamentos 12m | 4.656 |
| Ticket médio | R$ 1.349,05 (mediana R$ 738) |
| Top 10 clientes (% receita) | apenas 15% — base pulverizada |

**Cleanup write-off aplicado prod biz=164 (2026-05-14):** 748 títulos receber vencidos >3 anos R$ 844.660 flagados `metadata.is_write_off_candidate=true` · Dani filtra UI.

Snapshot detalhado: [memory/research/clientes-legacy-officeimpresso/05-martinho-cacambas/03-financeiro-2026-05-11.md](../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/03-financeiro-2026-05-11.md).

## 4. Sistema atual

**Office Comercial Delphi (WR Sistemas legacy)** — versão `v1404`. Cliente usa há anos. Banco Firebird LAN. Volume: 44.709 vendas históricas.

**Sistemas concorrentes avaliados e pausados:**
- **Highsoft** — Kamila estava avaliando · pausou após endosso Jair pra oimpresso (14/maio noite).

**Pain-points reportados (Delphi atual):**
- Sem visão financeira consolidada AR+AP (Dani sente)
- Sem gestão estoque integrada (Lara sente)
- Sem WhatsApp Inbox multi-atendente
- Sem NFSe automática a partir de boleto pago
- Sem PWA mecânico campo (4 mecânicos usam Google Form 8 páginas)

## 5. Arquitetura migração — Dual-system

**Delphi master + oimpresso viewer com sync near-realtime** (daemon polling 5min). Ver [ADR proposal dual-sync](../../decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md).

| Componente | Quem usa | Sistema |
|---|---|---|
| Operação dia-a-dia (vendas balcão · POS) | Kamila + 4 vendedores | **Delphi** (continua) |
| Estoque | Lara | **oimpresso** (entra 19/maio) |
| Financeiro AR+AP | Dani | **oimpresso** (entra 19/maio) |
| Vendas no campo | Rodrigo · Eduardo | Delphi (continua) |
| Checklist mecânico | 4 mecânicos | Google Form (continua · futuro PWA Fase 4) |

**Cronograma:**
- Canary 7d tela por tela com Lara+Dani validando · cutover gradual feature por feature quando confiança crescer
- Wagner co-design presencial 20km regular (sem hospedagem)
- Daemon Fase 1 MVP (chunks paginados + retry exponencial + checkpoint per chunk) em desenvolvimento BG

## 6. Pricing + comercial

| Item | Valor |
|---|---|
| **Baseline mensal** | **R$ 830/m** (paridade preço Delphi · não altera preço) |
| Upsell WhatsApp Inbox multi-atendente | R$ 200-300/m |
| Upsell Jana IA ilimitada (brief diário + cobrança automática) | a definir |
| Upsell NFSe automática a partir de boleto pago | a definir |
| Upsell PWA mecânico campo (Fase 4) | a definir |

Decisão Wagner: sem subir preço baseline ("muito difícil"). Ganho via upsells de módulos novos.

Refs: [proposal pricing-recalibracao R$ 830-850](../../decisions/proposals/pricing-recalibracao-ticket-real-830-850.md).

## 7. Sensibilidades operacionais — NÃO MEXER SEM AVISAR

1. **Vocabulário caçamba: m³ (volume 3D)** — NÃO m² (isso é ComVis). Sub-tamanhos típicos: 3m³ reforma pequena · 5m³ reforma grande · 7m³ obra média.

2. **Vendas em massa sem placa:** 45.971 vendas (98.4%) NÃO têm vehicle_id — são "venda de internet" + "conserto bomba freio" + serviço avulso. Só 91 têm placa (locação caçamba específica).

3. **76.7% inadimplência fóssil:** Dani vai ver dashboard ao ligar → ataque cardíaco se não filtrar write-off. Cleanup tools US-005 OBRIGATÓRIO antes Dani entrar `/financeiro/boletos`. Heurística atual: `tipo='receber' AND status IN ('aberto','parcial') AND vencimento < NOW-1095d` → 748 títulos R$ 844k flagados.

4. **Schema PLACA Martinho v1404:** **FK numérica** (1, 2, 3...) → `EQUIPAMENTO_VEICULO.CODIGO` (NÃO string `ABC-1234`). Diferente de Vargas que tem multi-placa cavalo+reboque (PLACA + PLACA2 + CHASSI2).

5. **Firebird LAN cai em batch grande:** import-financeiro 98k rows caiu mid-query 14/maio. Daemon Fase 1 resolve com chunks + retry.

6. **Co-design presencial:** Lara/Dani PEDIRAM ver Wagner desenvolvendo. Approach "made together" — NÃO entregar versão final pronta sem feedback contínuo delas.

## 8. Estado prod biz=164 (14/maio 18h)

| Tabela | Rows | Status |
|---|---:|---|
| contacts | 18.845 | ✅ completo |
| transactions (sell) | 43.995 | ✅ all-time |
| vehicles | 91 | ✅ |
| service_orders | 91 | ✅ |
| products | 1.838 / 4.378 | 🟡 42% (re-rodar c/ fix cinto-suspensório) |
| variation_location_details (qty≠0) | 4.279 / 4.581 | 🟡 93% |
| fin_titulos | 5.546 / 98.533 | 🔴 6% (Firebird connection caiu · daemon Fase 1 retoma) |
| fin_titulos write-off flagged | 748 (R$ 844k) | ✅ |
| purchase_lines | 1 / 16k | 🔴 0% (aguarda import-contacts-from-nfe.py fornecedores) |

Sidebar customizada por business_id (26 Pest) — estoque visível pra Lara.

## 9. Histórico de marcos

### 2026-05-11 — Análise legacy + qualificação técnica
Wagner rodou pesquisa Heatmap UI v2 + sinais Firebird via skill `officeimpresso-financial-snapshot`. Confirmou: oficina simples, bom piloto.

### 2026-05-13 10h — Reunião Wagner + Martinho
Martinho topou testar oimpresso · "promessa de migrar tudo". Decisões P0 fechadas (paridade R$ 830/m · escopo Fase 1 · cutover canary 7d · champion filha+Dani · `vehicles` permanece nome).

### 2026-05-13 13h — Import vehicles prod biz=164
`import-vehicles.py --target prod --confirm` aplicou 91 caçambas + 91 service_orders biz=164. biz=164 MARTINHO CAÇAMBAS LTDA criado em prod Hostinger.

### 2026-05-14 manhã — Wave A prod biz=164
18.845 contacts + 44k transactions + 91 vehicles + fin_titulos parcial 5.546 + products 1.838 importados.

### 2026-05-14 14:30 — Cross-business bug ROTA LIVRE (biz=4) afetado
3 importers (estoque + produtos + compras) tinham SELECT/UPDATE em `variation_location_details` SEM JOIN+WHERE business_id explícito. UPDATE batch Martinho contaminou 5 VLDs ROTA LIVRE (CARDIGAN M/G · JAQUETA P/M · BLUSA P/G — total 3 unidades). Recovery via backup `~/.cagefs/tmp/oimpresso-dump-20260513-195514.sql.gz` (13/maio 19:55 BRT) + 3 UPDATEs surgical. Larissa não viu nada. Mitigação aplicada: cinto-suspensório `INNER JOIN products + WHERE business_id` em 3 importers + `rowcount==0 → skip`. Detalhes: [feedback-importer-cross-business-bug.md](../feedback-importer-cross-business-bug.md).

### 2026-05-14 noite — Firebird connection shutdown em batch 98k fin_titulos
Cursor único Firebird LAN caiu durante import-financeiro · só 5.546/98.533 importados. Mitigação: daemon Fase 1 MVP (`a13a132de0c4217f1`) implementa chunks paginados + retry exponencial + checkpoint per chunk. Detalhes: [feedback-firebird-batch-instavel.md](../feedback-firebird-batch-instavel.md).

### 2026-05-14 15:51 — Insight estratégico dual-system (Kamila)
WhatsApp Kamila: *"continuar usando o sistema antigo e colocar o sistema novo para a lara e a dani usar... ele consegue puchar as informações em tempo real do sistema antigo"* — origem da arquitetura dual-system Delphi master + oimpresso viewer.

### 2026-05-14 ~16:30 — Felipe presencial Martinho · Jair endossou
Felipe foi presencial Martinho. **JAIR (dono majoritário) endossou** + Kamila pausou avaliação **Highsoft** (concorrente) · co-design 20km viável confirmado.

### 2026-05-19 (planejado) — Canary inicia
Lara + Dani entram oimpresso · co-design presencial Wagner 20km · operação Kamila + vendedores continua Delphi (dual-system).

## 10. Refs

- [ADR 0105 — Cliente como sinal qualificado](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)
- [ADR 0121 — Modular especializado por vertical](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)
- [ADR 0137 — Modules/OficinaAuto qualificada](../../decisions/0137-modules-oficinaauto-qualificada.md)
- [ADR proposal dual-sync Delphi+oimpresso](../../decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md)
- [ADR proposal pricing R$ 830-850](../../decisions/proposals/pricing-recalibracao-ticket-real-830-850.md)
- [Perfil Martinho legacy (anonimizado)](../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md)
- [Snapshot financeiro 2026-05-11](../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/03-financeiro-2026-05-11.md)
- [CHECKLIST pós-reunião](../../requisitos/OficinaAuto/demo-martinho-2026-05-13/CHECKLIST-POS-REUNIAO.md)
- [Session log 2026-05-14](../../sessions/2026-05-14-martinho-canary-prep-massive.md)
- [Handoff 2026-05-14 18:00](../../handoffs/2026-05-14-1800-martinho-canary-prep-jair-endossou.md)
- [feedback-importer-cross-business-bug.md](../feedback-importer-cross-business-bug.md)
- [feedback-firebird-batch-instavel.md](../feedback-firebird-batch-instavel.md)
- [dominios-verticais-oimpresso.md](../dominios-verticais-oimpresso.md) — OficinaAuto sub-vertical 3 vocabulário m³
- Funcionários: [jair](../funcionarios/martinho-cacambas/jair.md) · [martinho](../funcionarios/martinho-cacambas/martinho.md) · [kamila](../funcionarios/martinho-cacambas/kamila.md) · [lara](../funcionarios/martinho-cacambas/lara.md) · [dani](../funcionarios/martinho-cacambas/dani.md) · [rodrigo](../funcionarios/martinho-cacambas/rodrigo.md) · [eduardo](../funcionarios/martinho-cacambas/eduardo.md)
