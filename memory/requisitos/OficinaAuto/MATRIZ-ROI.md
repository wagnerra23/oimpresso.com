---
module: OficinaAuto
artefato: matriz-roi
status: draft (discovery 2026-05-12, aguarda Wagner)
purpose: cruzar features OficinaAuto V1-V5 vs ROI (esforço × diferencial competitivo × valor cliente × risco) pra ranking objetivo de roadmap pós-FSM
escopo: 22 features avaliadas (V0 done excluído)
calibrado_com: Vargas + Martinho perfis legacy + 8 concorrentes BR + 3 USA (Shop-Ware, AutoFluent, Mitchell1) + research 2026-05-prospeccao-auto
ultima_atualizacao: 2026-05-12
---

# Matriz ROI — Modules/OficinaAuto

> Espelha template `MATRIZ-ROI.md` ComVis (a criar paralelo) — colunas: feature, US-OFICINA-ID, esforço (h IA-pair), diferencial vs concorrência BR/USA, valor pra cliente, risco implementação, score ROI ponderado, decisão fase.
>
> **Como ler:** Score ROI = `(Diferencial × 3) + (Valor × 3) − (Esforço × 1) − (Risco × 2)`. Range realístico -10 a +30. Acima de 15 = fase 1; 10-15 = fase 2; 5-10 = fase 3; abaixo de 5 = backlog.
>
> **Escalas:**
> - Esforço: 1 (≤4h) · 2 (4-8h) · 3 (8-16h) · 4 (16-40h) · 5 (>40h)
> - Diferencial vs mercado: 1 (paridade — todos têm) · 3 (parcial) · 5 (ninguém entrega)
> - Valor pra cliente (Vargas/Martinho): 1 (nice-to-have) · 3 (importante) · 5 (bloqueia adoção)
> - Risco: 1 (trivial) · 3 (médio) · 5 (alto — dep externa, drift, regulatório)

## Concorrentes referência

| Concorrente | Origem | Pricing/m | Forte | Fraco |
|---|---|---|---|---|
| **Ultracar** | BH/MG 31a | R$ 189-494 | 430+ features, blog SEO, base instalada | NFS-e travada (RA reclamação 1 ano cliente), suporte despreparado, sem IA |
| **Oficina Integrada** | Viçosa/MG 23a | R$ 99-339 | "1º 100% online", app Android, NFC-e/NFSe ilim | Reclamação acesso bloqueado pós-pagamento, UI desktop-em-browser, zero IA |
| **Onmotor** | BR | R$ 0-479 | Múltiplos tiers granular, 5d trial | Stack desconhecida, sem mobile destacado, free só 50 OS/m |
| **Oficina Inteligente** | BR <2a | R$ 399-599 | Marketing "120+ recursos", multi-segmento | Sem track record |
| **Manager Full** | BR | R$ 155-300 | **Modelo 3D avarias** (único), NFe+NFSe, busca XML SEFAZ | Stack desconhecida, sem IA conversacional |
| **MotorSW** | BR | n/a | 100% online, intuitivo | Mid-market |
| **vhsys** | BR (multi-vertical) | R$ ~70/m | Consulta placa auto-fill | Não é vertical pura — multi-segmento raso |
| **WSoft** | BR | R$ 79,90 | OS digital + NFe + WhatsApp | Entry-tier; sem IA |
| **NeXT ERP** | BR | R$ 69+ | NFC-e SAT, NFSe | Entry-tier |
| **AutoFluent** | USA | $150-300 | CARFAX integration, timeclock técnico, PO/AP, QuickBooks export | Não-BR; sem NFe/NFSe |
| **Shop-Ware** | USA | $349+ | Digital vehicle inspections (DVI), CRM robusto | Premium, sem fiscal BR |
| **Mitchell1** | USA | $200+ | Database OEM + estimating completo + integração marketing | Foco USA; database OEM colossal indisponível BR |

## Matriz consolidada (22 features avaliadas)

| # | Feature | US-OFICINA | Fase atual | Esforço (h) | Esforço-score | Diferencial vs BR | Diferencial vs USA | Valor Vargas | Valor Martinho | Risco | **Score ROI** | Decisão fase |
|---|---|---|---|---:|:---:|:---:|:---:|:---:|:---:|:---:|---:|---|
| 1 | **Multi-placa cavalo+reboque (PLACA + PLACA2 + CHASSI2)** | 007 schema (em vehicles V0) | Fase 1 | 8 | 3 | 5 (ninguém BR cobre) | 5 (USA pula caminhão pesado) | 5 (bloqueia adoção) | 1 (não usa) | 2 | **27** | Fase 1 |
| 2 | **FSM canônica wire-up (15 stages + audit trail)** | 006 | Fase 1 | 6 | 2 | 3 (Ultracar tem pipeline mas não auditável) | 4 (Shop-Ware pipeline forte) | 5 (governance + audit Wagner pain-point) | 5 (Martinho já usa FSM 2 estados) | 2 | **27** | Fase 1 |
| 3 | **Defeitos múltiplos por OS (JSON array)** | 009 | Fase 1 | 3 | 1 | 3 (Ultracar/Manager Full têm linha-única, não array semântico) | 4 (Mitchell1 tem DVI estruturado) | 5 (Vargas média 3.08 itens/OS) | 3 (caçambas avulsas variam) | 1 | **26** | Fase 1 |
| 4 | **Aprovação OS via WhatsApp link público + PIN** | 014 | Fase 1 | 7 | 2 | 1 (padrão BR: Ultracar/Oficina Integrada/Manager Full têm) | 3 (USA usa email mais) | 5 (cliente Vargas distância retorna oficina) | 4 (caçamba PJ aprova rápido) | 2 | **22** | Fase 1 |
| 5 | **Garantia granular per-item (peça vs serviço)** | 008 | Fase 1 | 5 | 2 | 5 (Manager Full ✅ apenas; outros OS-todo) | 4 (Shop-Ware DVI tem mas raro) | 4 (Vargas mistura pneu 6m + aplicação 180d) | 3 | 2 | **23** | Fase 1 |
| 6 | **Importer Firebird EQUIPAMENTO_VEICULO → oa_vehicles (Martinho 91)** | 002 | Fase 1 | 4 | 1 | n/a (interno migração) | n/a | 5 (destrava migration Martinho) | 5 (caso piloto direto) | 2 | **24** | Fase 1 |
| 7 | **Importer multi-placa Vargas (1.064 veículos)** | 007 | Fase 1 | 8 | 3 | n/a | n/a | 5 (destrava Vargas) | 0 | 3 | **17** | Fase 1 |
| 8 | **Teste estrada + ajuste final loop (stages oficina-específicos)** | 010 | Fase 1 | 4 | 1 | 5 (Ultracar/Of.Integrada não têm test-loop) | 3 (Shop-Ware DVI tem checkpoint final) | 5 (Vargas pós-recapagem exige test-loop) | 2 | 1 | **25** | Fase 1 |
| 9 | **Re-orçamento (escalada supervisor + flag aprovado_apos_aumento)** | 011 | Fase 1 | 4 | 1 | 5 (Manager Full não rastreia re-orçamento) | 4 (USA tem "estimate revision") | 5 (Vargas comum peça extra detectada) | 3 | 2 | **22** | Fase 1 |
| 10 | **Cleanup tools cliente legacy (revisão pendências + conciliação + dedup PESSOAS)** | 005 | Fase 1-2 | 12 | 3 | n/a (one-off migração) | n/a | 5 (R$ 15k+R$400/m pricing piloto provado) | 5 (Martinho 76,7% inadimplência fóssil) | 3 | **23** | Fase 1 ROI imediato |
| 11 | **Consulta CRLV/Renavam por placa (cache 30d)** | 012 | Fase 2 | 6 | 2 | 3 (vhsys faz; Ultracar via 3rd party) | 1 (USA tem CARFAX equivalente) | 3 (Vargas: caminhões já catalogados) | 4 (caçambas avulsas placa-única) | 3 (API custo R$ 0,15/consulta) | **15** | Fase 2 |
| 12 | **Tabela tempária seed (100 serviços comuns)** | 013 | Fase 2 | 5 | 2 | 3 (Tempario.com R$ 79/m standalone; mercado integra ou digita) | 1 (Mitchell1 tem database OEM) | 4 (Vargas precisa pra cálculo MOD recapagem padronizado) | 2 | 2 | **18** | Fase 2 |
| 13 | **Histórico veículo (timeline OS + km percorrido)** | 017 | Fase 2 | 4 | 1 | 1 (todos verticais têm) | 1 (todos USA têm) | 4 (Vargas mesmo caminhão volta a cada 6m) | 3 | 1 | **18** | Fase 2 |
| 14 | **Diagnóstico Jana IA (sintoma → hipóteses + tempário)** | 007-AUTO (anexo) | Fase 2-3 | 16 | 4 | 5 (NINGUÉM BR tem IA conversacional) | 4 (USA Shop-Ware tem ML diagnostic emergente) | 3 (Vargas mecânicos experts já sabem) | 4 (Martinho atendente sem expertise) | 4 (LGPD + responsabilidade civil disclaimer) | **18** | Fase 2-3 |
| 15 | **NFSe modelo 56 split documentos (NFe55 peça + NFSe servico)** | 018 | Fase 2 | 10 | 3 | 5 (Ultracar travado 1 ano cliente RA — wedge) | n/a (não-BR) | 5 (Vargas fiscal correto) | 3 (caçambas PJ exige) | 5 (driver NFSe per-município externo) | **15** | Fase 2 (bloqueado driver) |
| 16 | **PWA mecânico campo (V0: minhas OS + foto + clock-in)** | 015 | Fase 3 | 16 | 4 | 3 (Oficina Integrada Android; Manager Full mobile responsive) | 4 (USA AutoFluent timeclock técnico) | 5 (Vargas galpão grande, mecânico longe PC) | 3 (Martinho rolling caçambas) | 3 (iOS Safari PWA quirks) | **18** | Fase 3 |
| 17 | **Comissão por OS (% mecânico + atendente, escalonado)** | 019 | Fase 3 | 8 | 3 | 1 (padrão BR: Ultracar/Soften/Mubisys têm) | 1 (USA padrão) | 4 (Vargas grande paga comissão) | 3 (Martinho dono+1 mecânico) | 2 | **15** | Fase 3 |
| 18 | **Lembrete garantia pré-vencimento (cron WhatsApp)** | 016 | Fase 3 | 3 | 1 | 3 (Manager Full ✅; outros 🟡) | 1 (USA tem) | 4 (Vargas pós-venda diferenciado) | 3 | 1 (LGPD opt-in obrigatório) | **22** | Fase 3 (alto ROI baixo esforço!) |
| 19 | **Integração FIPE veículo (valor mercado + cap garantia)** | 021 | Fase 3 | 4 | 1 | 5 (NINGUÉM faz nativo BR) | 3 (USA tem KBB equivalent integration) | 3 (caminhão FIPE útil seguro) | 1 (caçamba não-FIPE) | 2 | **19** | Fase 3 |
| 20 | **Importer WR_KANBAN Delphi (pré-arte Vargas estado UI)** | 020 | Fase 3 | 4 | 1 | n/a (interno migração) | n/a | 4 (UX continuidade Vargas — 548 grids salvos) | 4 (Martinho 690 grids) | 2 | **20** | Fase 3 |
| 21 | **Catálogo peças OEM Bosch/Nakata/Fras-le (similares)** | 008-AUTO (anexo) | Fase 5 | 40 | 5 | 3 (Limersoft 🟡 kits; Ultracar 🟡) | 1 (Mitchell1 colossal database) | 1 (Vargas usa banda rodagem específica) | 1 (Martinho não usa peças OEM convencionais) | 5 (parceria legal + manutenção catálogo) | **3** | Backlog/ADR feature-wish |
| 22 | **Boleto B2B (frota PJ) vs PIX PF (split pagamento)** | (futuro) | Fase 3 | 6 | 2 | 1 (padrão BR via UltimatePOS) | 1 (USA via Stripe/Square) | 4 (Vargas transportadora PJ paga boleto 30d) | 4 (Martinho PJ construtora) | 2 | **18** | Fase 3 |

## Top 5 features ROI (Fase 1 — destravar piloto)

| Rank | Feature | Score | US | Justificativa |
|---:|---|---:|---|---|
| 1 | **Multi-placa cavalo+reboque (schema + UI + importer)** | **27** | 007 + V0 ajuste | Bloqueia Vargas 100% — sem isto não consigo migrar caso piloto saudável; ninguém BR cobre como native; baixo risco |
| 1 | **FSM canônica wire-up (15 stages)** | **27** | 006 | Pre-requisito de TUDO — sem isto a OS não tem ciclo de vida governance-aligned; reusa engine LIVE ADR 0143 testado; pain-point Wagner já resolvido em Sells |
| 3 | **Defeitos múltiplos JSON array** | **26** | 009 | Cenário Vargas (3.08 itens/OS média) — pneu+freio+óleo em 1 OS; baixo esforço (3h); alto valor; baixo risco |
| 4 | **Teste estrada + ajuste final loop** | **25** | 010 | Diferencial vertical caminhão pesado (recapagem Vargas exige); 0 concorrentes BR cobrem; KPI emergente "iterações ajuste" |
| 5 | **Importer Firebird Martinho 91 veículos** | **24** | 002 | Destrava piloto Martinho mais simples (3 estados Simples vs 5 Vargas Complexa); baixo esforço (4h); cria pipeline migration testado pra Vargas escalar depois |

## Bottom 3 features (deferir/arquivar)

| Rank | Feature | Score | Razão |
|---:|---|---:|---|
| 20 | **Catálogo OEM peças** | 3 | Vargas/Martinho não usam peças OEM convencional (recapagem banda + caçamba estacionária); custo legal parceria + manutenção desproporcional; Fase 5+ se piloto pagante Pro tier exigir |
| 11 (med) | **CRLV consulta** | 15 | Vargas já tem catálogo + Martinho placa-única simples; útil mas não urgência; cobrável add-on R$ 49/m não-tier-1 |
| 15 (med) | **NFSe split documentos** | 15 (bloqueado) | Score alto mas driver NFSe NÃO existe (10 US backlog SPEC-NFSE-CANCEL ADR 0143). Bloqueado por infra externa — falsa prioridade até driver verde |

## Cobertura competitiva oimpresso pós-Fase 1

**vs Ultracar/Oficina Integrada/Onmotor:** paridade em fluxo (OS + Kanban + aprovação WhatsApp + NFC-e) + 4 diferenciais vencedores:
- ✅ FSM auditável (Wagner pain-point #2 — eles não têm)
- ✅ Multi-placa cavalo+reboque native (eles assumem 1 placa)
- ✅ Garantia granular per-item (eles assumem OS-todo)
- ✅ Teste estrada + ajuste loop (eles cortam após "concluído")

**Aberto pós-Fase 2:**
- ✅ NFSe auto a partir de boleto pago (ataca Ultracar pain RA público)
- ✅ Tempário seed gratis (vs Tempario.com R$ 79/m standalone)
- ✅ Histórico veículo timeline com KPI km

**Aberto pós-Fase 3:**
- ✅ Jana IA diagnóstico (NINGUÉM tem)
- ✅ PWA mecânico (parcial Of.Integrada)
- ✅ FIPE integração native (NINGUÉM tem)
- ✅ Lembrete garantia pré-vencimento (parcial Manager Full)

## Wedge primário pós-Fase 1 (3 frases)

> *"O ERP de oficina BR que sabe que caminhão tem 2 placas, OS tem 3 defeitos, garantia varia por item e teste-estrada não é opcional. Que dispara NFC-e + NFSe automaticamente quando o boleto cai — enquanto Ultracar deixa cliente 1 ano sem NFSe. Que muda de stage só com role autorizada — enquanto concorrentes deixam qualquer um pular do `Recebido` ao `Entregue`."*

## Próximos passos

1. Wagner valida matriz (top 5 / bottom 3 / scores subjetivos)
2. Se aprovado → ADR proposal `oficina-auto-modulo-canonico-fsm-wireup.md` vira `accepted`
3. Felipe destrava US-OFICINA-006 → 002 → 007 → 009 → 010 (Fase 1, ~70h IA-pair = 3 semanas)
4. 1º canary com Martinho (mais simples) → smoke + ajustes → Vargas

## Refs

- [SPEC OficinaAuto §14-§18](SPEC.md) — FSM canon + schema + US
- [ADR proposal FSM wire-up](../../decisions/proposals/drafts/oficina-auto-modulo-canonico-fsm-wireup.md)
- [Research concorrentes 2026-05-prospeccao-auto](../../research/2026-05-prospeccao-auto/) — Ultracar/Of.Integrada/Onmotor/Manager Full
- [_LICOES-CRITICAS Vargas+Martinho](../../research/clientes-legacy-officeimpresso/_LICOES-CRITICAS.md)
- [ADR 0143 FSM canon LIVE](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
