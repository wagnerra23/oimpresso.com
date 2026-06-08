# Playbook migração Mubisys → oimpresso

> Documento operacional — não marketing. Tom realista, cita gaps abertamente.
> Data: 2026-05-09 · Owner: Wagner [W] + Maiara [M] + Felipe [F] (devops/API) · Revisão: por cliente
> Sinais públicos base: 2 RA Mubisys + claims oficiais (14k usuários, 1.8k empresas, 150+ TB MubiDrive) + AFACOM+ ([fonte](../../research/2026-05-prospeccao/02-concorrentes-zenite-mubisys.md))

---

## Perfil do prospect típico

**Demografia:**
- **Porte:** 10-50 funcionários, 3-15 plotters/impressoras + setor de instalação/marcenaria, gestor + dono separados
- **Faturamento:** R$ [redacted Tier 0]k-2M/mês
- **Segmento:** comunicação visual mid-market (fachadas grandes, sinalização corporativa, pontos de venda em rede), gráfica industrial leve
- **HQ:** SP/RJ/MG/SC concentra (Mubisys é Barueri-SP, base AFACOM+ Goiás)
- **Tempo no Mubisys:** **3+ anos** (esse é o perfil-ouro). Cliente passou da fase deslumbre, sente teto de capacidade. Reclamação RA de fev/2023 é literal: *"Sistema engessado sem possibilidade de integração ou consulta de dados. Bom apenas para empresas gráficas de pequeno porte"*

**Sinais comportamentais:**
- Cresceu 2-3x desde que entrou no Mubisys → operação estourou tamanho que sistema foi pensado
- Sente **falta de API/integração externa** — quer cruzar dado com banco (Asaas/Inter/C6), CRM externo (HubSpot/RD), BI (Metabase/PowerBI). Mubisys engessa
- **Equipe TI/dev interna** OU **contador parceiro** que pediu integração API e ouviu "não dá"
- Tem **operação em campo** (instalação fachada) — usa app mobile Mubisys mas relata "várias operações são PC-only" (RA literal)
- Investiu pesado em **MubiDrive** (150+ TB segundo claim Mubisys — cliente médio ali tem dezenas de GB de arte armazenada)
- **Pricing**: paga proposta enterprise customizada (não é tier público) — sente que está pagando caro

**Não-perfil (pular esses):**
- Cliente Mubisys feliz (1.8k empresas; média não está saindo). Foco em quem **falou em fórum/RA** ou **time interno reclama**
- Cliente que ama Mubisys e tem 50% do volume em MubiDrive → migração de DAM é blocker
- Pequeno porte (Mubisys serve bem) — esse não tem dor

---

## Diagnóstico — perguntas iniciais (call discovery, 60-90min)

> ⚠️ Discovery mais longo que Calcme — cliente é maior, decisão envolve >1 pessoa.

**Situação (S):**
1. Há quanto tempo no Mubisys? Quem foi o decisor original — você ou foi alguém que saiu da empresa?
2. Quantos usuários ativos? Como dividem entre comercial / produção / financeiro / instalação campo?
3. Quantas notas/dia (NFe + NFCe + NFSe + MDFe)? Fatura mensal aproximado?
4. **Quanto vocês têm armazenado no MubiDrive hoje?** (peça número — se >100GB, é fator crítico de migração)

**Problema (P):**
5. Quando vocês precisam **cruzar dado do Mubisys com outra ferramenta** — banco, CRM, BI — como fazem hoje? (escutar: "exporta CSV" / "tem TI que monta planilha" / "não dá, contador faz manual")
6. Algum projeto de integração que vocês quiseram fazer e o Mubisys não permitiu? Quanto custou em tempo/dinheiro essa não-integração?
7. Sua equipe de campo (instalação) — qual % do trabalho deles é resolvido pelo app mobile Mubisys? E o que eles ainda voltam ao escritório pra fazer?
8. Quando o sistema fica fora do ar (SLA Mubisys?), qual é o impacto financeiro/dia?

**Implicação (I):**
9. Vocês cresceram X% nos últimos 2 anos. Se crescerem mais Y% nos próximos 2, o Mubisys aguenta? O que vocês acham que vai estourar primeiro — performance, integração, mobile, fiscal?
10. Quanto vale pra vocês ter **API aberta** pra construir 3-5 automações próprias em cima do ERP? (esse é o ponto que mata Mubisys — RA pública)

**Necessidade (N):**
11. Se eu te mostrasse: (a) API REST documentada com exemplos, (b) IA com memória que responde queries no chat, (c) integração nativa Asaas/Inter/Bancos, (d) governança formal pública (ADRs auditáveis) — isso resolve o que tá te incomodando ou tem outro ângulo?
12. Quem mais precisa estar nessa decisão? (procurar: dono + sócio + TI + contador externo)

> **Frame da conversa:** *"Mubisys foi bom até [tamanho onde vocês estavam quando entraram]. O oimpresso é construído pra te levar pra próxima fase — API-first desde o dia 1, IA conversacional, governança auditável. Não é trocar funcional por funcional — é trocar plataforma pra escalar próximos 5 anos sem teto."*

---

## O que migrar (escopo — volumes maiores que Calcme)

| Dado | Volume típico Mubisys | Como sai | Como entra no oimpresso | Status oimpresso |
|------|----------------------|----------|-------------------------|------------------|
| Cadastro clientes | 2k-20k | **[gap — Mubisys não publica export schema. Validar com cliente se tem export CSV/JSON nativo no plano dele.]** Se enterprise pago, pedir ao Mubisys export oficial via SLA | Import UltimatePOS `/contacts/import` em batches de 500 | ✅ |
| Cadastro produtos/serviços | 500-5k SKUs | Idem (export Mubisys) ou scraping autorizado | Import + variações m² + tabelas preço | 🟡 Conversão schema custom 16-32h dev |
| Tabelas de preço por cliente/canal | 30-200 tabelas | Export ou planilha | Selling Price Group multi-tenant | ✅ |
| **Funil CRM (oportunidades abertas)** | 50-500 leads ativos | Mubisys tem "termômetro acesso" — exportar como pipeline | Modules/CRM oimpresso (Pipeline US-CRM-001) | 🟡 **[gap — Modules/CRM ainda em scaffold. Pipeline visual estilo Kanban precisa polir antes do contrato]** |
| OS abertas (PCP/Produção) | 100-500 ativas | Export OS por status | Modules/Repair (gerencia OS open-ended) ou nova OS oimpresso | 🟡 Modules/Repair existe (PR #363 drag-drop OK) — **falta validar paridade fluxo Mubisys PCP** |
| Histórico vendas (24-36 meses) | 50k-500k registros | **[gap real — Mubisys provavelmente não exporta histórico transacional limpo]**. Opções: (a) carregar saldo abertura por cliente, (b) negociar export SQL com Mubisys (se relação permitir) | Saldo abertura recomendado | 🟡 Idem Calcme |
| Financeiro AR/AP | saldos abertos | Planilha exportada | Lançamentos opening_balance | ✅ |
| Boletos abertos no período | 100-1k | Lista vencimento/valor | Re-emitir Asaas/Inter via Modules/RecurringBilling | 🟡 **Volume alto** — automatizar lote em 1 script |
| Conciliação bancária histórico | 12 meses | Mubisys conciliação própria | Modules/Financeiro reconcile (não migrar histórico, recomeçar) | ✅ — recomeço aceitável |
| Cobertura fiscal (NFe + NFCe + MDFe + NFSe) | XMLs todos | Manter offline (5 anos legal) | Modules/NfeBrasil prod (NFCe entregue biz=1; **NFSe e MDFe gaps**) | 🟡 **[gap — NFSe e MDFe não estão entregues prod. NFCe sim. Validar quais usa o cliente]** |
| **MubiDrive (DAM)** | **10GB-150TB** (varia muito) | **[gap crítico]** — ver opções abaixo | 3 opções abaixo | ❌ **oimpresso não tem DAM nativo** — ponto a discutir antes do contrato |
| App mobile usuários campo | usuários ativos | Cadastrar de novo | PWA Inertia (instalável Android) | 🟡 **[gap — app nativo iOS/Android não existe. PWA cobre 80% dos casos mas não é paridade]** |
| Cert digital A1 | 1-3 certs (multi-empresa) | .pfx export | Upload Modules/NfeBrasil | ✅ |
| Integrações externas (se houver) | API Calls Mubisys → outros | Mapear cada uma | Re-construir via API oimpresso + Webhooks | 🟡 **[gap — API oimpresso existe mas docs públicas precisam polir antes do 1º cliente Mubisys]** |

### Decisão MubiDrive (3 opções, pra discutir ANTES do contrato)

**Opção A — Manter MubiDrive como storage isolado + integrar via API (recomendada se >50GB)**
- Cliente segue pagando Mubisys APENAS pelo MubiDrive (verificar se Mubisys vende DAM standalone — provavelmente não)
- oimpresso linka arquivos via URL/path no campo da venda
- **Vantagem:** zero migração de TB. **Desvantagem:** cliente paga 2 fornecedores; depende boa vontade Mubisys
- **Risco:** Mubisys pode bloquear/cancelar conta se virar concorrência

**Opção B — Migrar pra storage S3-like próprio (Cloudflare R2 / Wasabi / AWS S3)**
- Cliente provisiona bucket próprio, oimpresso integra via SDK
- **Estimativa:** 20-40h dev backend + 8-16h migração inicial dos arquivos (precisa export massivo do MubiDrive — gargalo!)
- **Vantagem:** cliente vira dono dos dados. **Desvantagem:** custo storage cliente paga direto (50-200 R$/mês pra 1TB)
- **Pré-requisito oimpresso:** **[gap — integração S3 estilo DAM com tagging/preview/versão NÃO está construída. ~80h dev pra entregar minimum viable]**

**Opção C — Não migrar arquivos. Cliente assume Drive/Dropbox externo + link manual**
- Mais barato/rápido, perde feature
- Aceitável apenas se cliente tem <10GB no MubiDrive

> **Posição honesta:** se cliente tem >100GB no MubiDrive e DAM é fluxo crítico (artes pesadas baixadas direto pro produção), **adiar migração 3-6 meses até oimpresso construir DAM minimum viable**. Não vender com gap aberto.

---

## Cronograma proposto (cutover por fases, paralelo 21 dias)

> ⚠️ Cliente maior = downtime caro = NÃO faz cutover Big Bang. **Migração faseada por módulo.**

### Fase 0: Discovery + escopo (D-30 a D-21)
- Discovery 1-2 calls + visita presencial (Wagner [W] vai)
- Escopo formal escrito + estimativa horas custom + pricing
- **Cliente assina antes da fase 1**

### Fase 1: Cadastros + Comercial (D-21 a D-7)
- Sandbox oimpresso com `business_id` cliente novo
- Migração clientes + produtos + tabelas preço
- Time comercial faz 5 orçamentos paralelos no Mubisys + oimpresso → comparar resultados
- Treinamento comercial 4h
- **Resultado fase 1:** comercial decide se fluxo cobre. Go/no-go formal Wagner [W] + dono cliente

### Fase 2: Financeiro + Fiscal (D-7 a D-day)
- Migração saldos AR/AP + cert A1
- Setup Asaas/Inter (cliente integra banco)
- **Smoke fiscal homologação** (runbook biz=1) com nota teste cada UF que cliente usa
- D-1: re-extração delta da semana
- D-day **sábado:** virada (8-10h trabalho — mais que Calcme porque volume maior)

### Fase 3: Produção/PCP + Campo (D+1 a D+14)
- Modules/Repair OU módulo PCP custom (depender escopo) entra em uso
- Equipe campo recebe acesso PWA + treinamento 2h específico
- **Calcme readonly 21 dias** (não 7 como Calcme — operação maior precisa mais paralelo)

### Fase 4: Integrações + API (D+14 a D+30)
- TI cliente recebe doc API + sandbox tokens
- Re-construir N integrações que existiam no Mubisys (lista mapeada na fase 0)
- **Webhook setup** pra automações próprias

### Fase 5: Desligar Mubisys (D+30 a D+45)
- Cliente cancela Mubisys (verificar contrato — enterprise pode ter aviso prévio 60-90d!)
- Backup XMLs fiscais
- Pós-mortem 2h Wagner [W] + dono + TI cliente

---

## Riscos identificados + mitigação

| # | Risco | Probabilidade | Impacto | Mitigação |
|---|-------|---------------|---------|-----------|
| 1 | **MubiDrive volume real >>150GB** descoberto tarde | Alta | Crítico | Pergunta 4 do discovery captura. Se >100GB e DAM crítico → **NÃO vender ainda**. Adiar 3-6m |
| 2 | **Contrato Mubisys com aviso prévio 60-90d** (enterprise comum) | Alta | Alto | Validar D-30 antes de assinar contrato oimpresso. Cliente pode pagar 2 fornecedores 60-90d |
| 3 | **Cliente tem N integrações custom** (CRM/BI/banco/Receita) | Alta | Alto | Mapear na fase 0 cada integração. Estimar horas re-build. Cobrar |
| 4 | **NFSe/MDFe não entregues prod oimpresso** mas cliente usa | Média | Crítico | Validar quais notas o cliente emite. **Se NFSe/MDFe são >20% volume → adiar** ou cobrar custom Modules/NfeBrasil-NFSe (US a criar, ~80h dev) |
| 5 | **API oimpresso não documentada publicamente** mas é selling point | Alta | Alto | **Construir docs API pública antes do 1º contrato Mubisys**. Estimativa 16-24h Felipe [F] |
| 6 | **Time campo perde feature mobile nativa** (Mubisys tem app, oimpresso PWA) | Média | Médio | Demo PWA na discovery. Se campo é >50% time → discutir. PWA Android cobre bem; iOS Safari é OK; push notif gap |
| 7 | **Mubisys oferece desconto agressivo pra reter** quando souber da migração | Alta | Médio | Cliente decide na pré-fase 1 (D-21) se segue. oimpresso oferta garantia 90d (não 60 como Calcme) |
| 8 | **Equipe TI cliente esperava SDK/lib oficial** (oimpresso só tem REST) | Média | Médio | Honestidade: "REST + Webhook hoje, SDK roadmap Q3". Aceitável pra maioria |
| 9 | **Volume saldo abertura AR/AP errado** (cliente grande tem milhares lançamentos) | Média | Crítico | Reconciliação contábil dupla. Wagner [W] revisa. Contador cliente assina |
| 10 | **Cliente Larissa-style (1 dono-operador) NÃO é o perfil — Mubisys atende equipe inteira** | N/A | N/A | Reconhecer: Mubisys é decisão coletiva (dono + gestor + operador). Discovery precisa de >1 pessoa |

---

## Comunicação ao cliente final do prospect (B2B mid-market)

**E-mail formal aos 5-20 maiores clientes do prospect (D-7):**

```
Assunto: [Nome Gráfica] — atualização de plataforma

Prezado(a) [Cliente],

A [Nome Gráfica] está atualizando sua plataforma de gestão entre
[DATA_INICIO] e [DATA_FIM]. Para você, a operação continua normal —
seus pedidos, NFe, prazos e contatos seguem com [Nome Conta], no mesmo
WhatsApp/e-mail.

Mudanças que você pode notar:
1. NFe pode chegar com nova identidade visual a partir de [DATA].
2. Boleto: número e link podem mudar a partir de [DATA+7]. Boletos
   antigos seguem válidos até vencimento.
3. Portal do cliente (acesso de download de artes finalizadas) terá
   novo endereço — vamos enviar separadamente.

Em caso de qualquer dúvida, [Nome Conta] está disponível em [whats] /
[email]. Diretoria também à disposição: [email diretor].

Atenciosamente,
[Dono Gráfica]
Diretor — [Nome Gráfica]
```

**Plano comunicação portal cliente (se houver área de download artes):**
- D-7: e-mail aviso + URL nova
- D-day: redirect HTTP 301 do antigo pro novo
- D+30: desligar URL antigo

---

## Pricing migração (Enterprise tier — perfil Mubisys típico)

| Item | Valor | Observação |
|------|-------|------------|
| **Setup Enterprise** | R$ [redacted Tier 0] (one-time) | Inclui migração até **5.000 clientes + 2.000 produtos + saldos AR/AP + 5 integrações simples** |
| Migração custom adicional | R$ [redacted Tier 0]/dia dev | Comum: 5-15 dias adicional |
| **Treinamento 4h síncrono + 2h por papel** (comercial/financeiro/produção/campo) | R$ [redacted Tier 0] (até 4 papéis) | Maiara [M] + Felipe [F] |
| Treinamento extra | R$ [redacted Tier 0]/h | Após D+45 |
| **Mensalidade Enterprise** | **R$ [redacted Tier 0]/mês** | 25 usuários inclusos. R$ [redacted Tier 0]/usuário extra/mês |
| Setup integrações banco (Asaas/Inter/C6) | R$ [redacted Tier 0]/banco | Até 3 bancos |
| Re-construção integração custom (CRM/BI/etc) | R$ [redacted Tier 0]/dia dev | Estimar fase 0 |
| **DAM minimum viable (S3 wrap)** | R$ [redacted Tier 0] (one-time) | Apenas se cliente migra MubiDrive — **avisar gap 6-8 sem desenvolvimento** |
| **Garantia 90d** com cláusula rollback | Incluso | Devolução 100% setup se cliente sair em 90d |
| Cert A1 multi-empresa (até 3 CNPJs) | Incluso | Acima disso, R$ [redacted Tier 0]/CNPJ |

**Comparação com pricing-tiers oficial:** consultar [`memory/sales/2026-05/06-pricing-tiers.md`](06-pricing-tiers.md). Enterprise R$ [redacted Tier 0]/mês alinhado.

---

## SLA pós-migração (90 dias garantia — mais que Calcme)

> **⚠️ Realista pra time de 5.** Não prometer 24x7 nem 99.9% uptime sem infraestrutura HA pronta.

| Item | SLA | Janela |
|------|-----|--------|
| Resposta WhatsApp prioridade | ≤ 2h | Seg-sex 09h-19h. Sáb 09h-13h |
| Bug bloqueador (NFe/PDV/login down) | Workaround em 8h, fix em 24h | 7 dias/semana |
| Bug não-bloqueador | Fix em 5 dias úteis | seg-sex |
| Pergunta uso ("como faz X?") | Resposta + tutorial Jana | ≤ 4h |
| Treinamento extra | Agendar em 5 dias úteis | Maiara [M] |
| **Uptime alvo** | **99.5%** (não 99.9% — honestidade) | Hostinger shared hosting + CT 100. **Se cliente exige 99.9% → custo infra dedicada R$ [redacted Tier 0]-1.500/mês adicional** |
| Tempo de resposta API | <500ms p95 | Caminho feliz |
| **Rollback contratual** | Cliente decide até D+30 → volta Mubisys, devolução 100% setup | Cláusula explícita |
| Pós-D+90 | Mensalidade Enterprise + suporte ticket padrão | Sem prioridade extra |

---

## Vitórias rápidas (week 1-2) pra cliente sentir valor

> **Objetivo:** D+7 cliente já fez 1 demo interna pro próprio time mostrando "olha o que dá pra fazer agora".

### Semana 1 — TI cliente vê API funcionando
1. **API REST documentada (Swagger/OpenAPI público)** + Postman collection. TI cliente faz primeira integração demo (puxar lista clientes via API) em <1h.
   - **Status:** [gap — docs precisam polir antes do 1º contrato; Felipe [F] estima 16-24h]
2. **Webhook setup:** evento "pagamento recebido" dispara HTTP POST pro endpoint do cliente. Demo end-to-end. **Bate dor #1 do top 5 deles** (engessado, sem integração).

### Semana 1 — Jana IA com memória do cliente
3. **Jana configurada com `business_id` cliente:** dono pergunta no chat "qual cliente faturou mais em abril, e qual está em maior risco de churn?" → resposta com SQL auditável. Bate dor #4 setor (margem/relatório).

### Semana 2 — NFe automática a partir de boleto (US-RB-044)
4. **Primeiro lote de boletos pagos → N NFes emitidas em paralelo** sem clique humano. Cliente médio Mubisys emite >100 NFe/dia — economia 30-60min/dia operador financeiro = **R$ [redacted Tier 0]-6k/mês** salário.

### Semana 2 — Visão Unificada Financeiro
5. **Tela `/financeiro/visao-unificada`** — substitui dashboard contador/BI externo. Cliente mostra pro contador parceiro: "agora a gente roda relatório aqui mesmo".

### Semana 3 (bônus) — Governança pública
6. **Mostrar página pública de ADRs** (memory/decisions/) — cliente enterprise valoriza saber **como decisões são tomadas**. Diferencial vs Mubisys que não publica nada.

---

## Gaps oimpresso que **podem travar** essa migração (declarar antes do contrato)

> ⚠️ Mais gaps que Calcme — cliente Mubisys é maior, exige mais features.

| Gap | Impacto cliente Mubisys | Mitigação ofertável | Decisão |
|-----|------------------------|---------------------|---------|
| **DAM nativo equiv MubiDrive** | Alto (cliente médio tem 10-150GB de arte) | 3 opções acima (manter MubiDrive / migrar S3 / Drive externo) | **Construir DAM minimum viable é R$ [redacted Tier 0]k+ dev. Adiar até 2º cliente Mubisys ou cobrar antecipado** |
| **App mobile nativo iOS+Android** | Alto se time campo >5 pessoas | PWA Android OK, iOS Safari OK. Sem push notif nativo | **Ofertar PWA + roadmap app nativo Q4/2026. Honesto.** |
| **NFSe (serviço) e MDFe (transporte)** prod | Crítico se cliente emite — Mubisys cobre todos | Modules/NfeBrasil só tem NFCe entregue biz=1 | **80h dev por nota fiscal. Cobrar como custom OU adiar contrato** |
| **API docs públicas** | Alto (selling point) | Swagger/OpenAPI público | **24h Felipe [F] antes de 1º contrato — pré-requisito** |
| **Integração nativa CRM externo (HubSpot/RD/Pipedrive)** | Médio | Webhook + API REST + cliente constrói integração | **Não construir nativo. Documentar como webhook integration** |
| **BI nativo (dashboards customizáveis pelo cliente)** | Médio | Jana IA + export CSV + integração Metabase via API | **Não construir BI nativo. Apontar Metabase open-source** |
| **Funil CRM com termômetro acesso (Mubisys feature)** | Médio | Modules/CRM ainda em scaffold | **Polir Modules/CRM Pipeline antes de 1º contrato OU cobrar custom** |
| **Comissões nativas vendedor** | Médio (Mubisys publiciza) | UltimatePOS tem comissão básica — polir UI | **Validar paridade. Estimar 16h dev se gap real** |
| **Conciliação bancária histórica importada** | Baixo (recomeçar é aceitável) | Recomeço D-day. Cliente perde 12 meses histórico | **Aceitar gap, comunicar discovery** |
| **Multi-empresa (multi-CNPJ mesmo dono)** publicizado | Médio | oimpresso é multi-tenant Tier 0 — funciona, mas UX precisa polir pra "trocar empresa no topbar" | **Polir UX antes de 1º contrato — 8h dev** |

---

## Ângulo "Mubisys foi bom até X, oimpresso te leva pra próxima fase"

**Pitch escrito (e-mail outreach):**

```
[Nome],

Vi vocês há ~3 anos no Mubisys — sei que era a melhor escolha pra
quem fazia [X] no porte que vocês tinham. Mas vocês cresceram. E o
[Nome] (RA fev/2023) escreveu literalmente em público: "sistema
engessado sem possibilidade de integração ou consulta de dados".

Não é defeito — é teto. Mubisys foi feito pra cobrir [funcional do
funil até NFe] e cobre bem. Quando empresa cresce e quer:
  - cruzar dado com banco/CRM/BI externo (API),
  - pedir pra IA "me lista os 5 clientes em risco" (chat com memória),
  - publicar como decisões são tomadas (governança formal),
o oimpresso é construído nessa segunda fase desde o dia 1.

Se vocês tiverem 45min, posso mostrar a [Nome Cliente Rota Livre],
gráfica que roda em prod, fala com a IA Jana no chat, e tem API
documentada pública.

Sem trial gating. Quer ligação de 15min essa semana?

[Wagner / Office Impresso]
```

---

## Fluxo da venda (resumo executivo pra Wagner)

1. **Lead capture:** monitorar RA Mubisys + grupos LinkedIn de gestores gráficos + AFACOM+ contactos saídos
2. **Outreach:** cold email/LinkedIn DM personalizado citando RA específica + crescimento cliente (LinkedIn público mostra)
3. **Discovery 60-90min** com >1 pessoa do cliente (dono + TI ou dono + gestor)
4. **Visita presencial 1 dia** (Wagner [W] vai) — cliente >R$ [redacted Tier 0]k/mês merece
5. **Demo formal 90min** com Rota Livre + ambiente sandbox cliente
6. **Proposta** com pricing tabela + escopo migração + decisão DAM declarada
7. **Fase 0 paga (R$ [redacted Tier 0]k descontado se contrato fechar)** — discovery + escopo formal
8. **Cutover faseado 4-6 semanas** → **garantia 90d** → cliente fidelizado + case enterprise público

---

## Comparativo Calcme vs Mubisys (rápido pra Wagner)

| Dimensão | Calcme | Mubisys |
|---|---|---|
| Perfil cliente | 1-10 fn, 1 dono-operador | 10-50 fn, dono+gestor |
| Faturamento | R$ [redacted Tier 0]-200k/m | R$ [redacted Tier 0]k-2M/m |
| Volume migração | Baixo (até 1k/500) | Médio-Alto (5k/2k+) |
| Tempo migração | 1-2 semanas | 4-6 semanas faseado |
| Setup price | R$ [redacted Tier 0] | R$ [redacted Tier 0]+custom |
| Mensalidade | R$ [redacted Tier 0]/m (Pro) | R$ [redacted Tier 0]/m (Enterprise) |
| Garantia | 60d | 90d |
| Risco principal | Larissa decora horário | DAM volume + aviso prévio contrato |
| Dor pública | "trial promete + RA suporte" | "engessado sem integração" |
| Wedge oimpresso | NFe automática + Jana bulk | API pública + IA com memória + governança |
| Gaps oimpresso | DAM, mobile nativo, Jana bulk publish | + NFSe/MDFe + API docs + DAM volume |

---

**Última atualização:** 2026-05-09 · próximo review após 1º cliente Mubisys migrado
