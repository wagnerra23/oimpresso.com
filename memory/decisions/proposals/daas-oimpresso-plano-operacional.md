---
title: DaaS oimpresso.com/insights — plano operacional Data-as-a-Service
status: proposed (Wagner valida — exige revisão jurídica antes de qualquer cláusula virar produção)
date: 2026-05-09
author: Claude Opus 4.7 (sub-agent VP product + counsel B2B)
type: feature wish ADR-eligible (mãe candidata: ADR canon próximo nº disponível)
relates:
  - feature-financial-snapshot-multi-cliente.md (Tier 1-3 base)
  - OFFICEIMPRESSO-FIREBIRD-SCHEMA.md (fonte de dados)
  - .claude/skills/officeimpresso-financial-snapshot/SKILL.md (automação)
  - ADR 0093 (multi-tenant Tier 0 IRREVOGÁVEL)
  - ADR 0105 (cliente como sinal qualificado)
  - ADR 0094 (Constituição v2 — princípios duros 6 e 8)
prerequisites:
  - revisão jurídica externa (DPO + counsel LGPD especializado)
  - opt-in escrito ASSINADO de cada cliente OfficeImpresso ANTES de qualquer ETL
  - DPA modelo Brasil registrado em RDC + RDP
---

# DaaS oimpresso.com/insights — plano operacional

> **Data-as-a-Service como linha de negócio formal.** Monetizar — com consentimento explícito + LGPD-first — os dados anonimizados e agregados dos 37 clientes OfficeImpresso (132k clientes finais, R$ [redacted Tier 0]M GMV agregado).
>
> **REGRA DURA: nada nesta spec executa sem revisão jurídica + opt-in assinado.** LGPD não é checkbox — é fundação.

---

## 0. TL;DR executivo

| Eixo | Decisão |
|---|---|
| **Produto** | `oimpresso.com/insights` — DaaS com 5 tiers (Free, Snapshot R$ [redacted Tier 0] Pro R$ [redacted Tier 0] Insights Pro R$ [redacted Tier 0] Data Partner R$ [redacted Tier 0]) + one-shot reports R$ [redacted Tier 0]k-15k |
| **Wedge** | OfficeImpresso (37 clientes) → leitor legacy de Firebird vira lead-magnet → migração pro oimpresso.com novo |
| **ARR projetado 24m** | ~R$ [redacted Tier 0]k recorrente + R$ [redacted Tier 0]k one-shot = R$ [redacted Tier 0]k total (~5% da meta R$ [redacted Tier 0]M; combinado com migração ~10%) |
| **Sequência** | Tier 2 (Snapshot R$ [redacted Tier 0]) primeiro — já tem ETL provado + 5 pilotos saudáveis. Tiers 3-5 só após 5 pagantes validarem |
| **Risco #1** | LGPD — penalidade ANPD até 2% faturamento ou R$ [redacted Tier 0]M (Lei 13.709 Art. 52). Mitigação: opt-in explícito + DPO + auditoria |
| **GO/NO-GO** | 5 clientes pilotos pagantes em 60d = construir Tier 3+. Sem sinal em 90d = arquivar como ADR feature-wish |

---

## 1. LGPD compliance (CRÍTICO — toda seção depois desta presume isto FEITO)

> ⚠️ Wagner é advogado mas NÃO é DPO. **Exigir parecer externo de counsel LGPD especializado antes de produção.** ANPD vem fiscalizando ERP-multi-tenant em 2025-2026.

### 1.1 Cláusula contratual mandatória (anexo ao contrato OfficeImpresso atual)

Texto-base (precisa revisão jurídica):

> **CLÁUSULA X — TRATAMENTO DE DADOS PARA INSIGHTS AGREGADOS**
>
> X.1 O CLIENTE autoriza, em caráter revogável a qualquer tempo, o TRATAMENTO dos dados constantes em sua base OfficeImpresso para finalidade exclusiva de:
> (a) geração de insights e dashboards entregues ao próprio CLIENTE (Snapshot/Pro);
> (b) agregação ANONIMIZADA com dados de outros clientes para benchmark setorial e relatórios de mercado (Insights Pro/Data Partner);
> (c) revenda de relatórios setoriais ANONIMIZADOS a fornecedores e órgãos públicos.
>
> X.2 É VEDADA a comercialização ou compartilhamento de dados IDENTIFICÁVEIS com terceiros sem novo consentimento específico.
>
> X.3 O CLIENTE pode revogar consentimento via email ou painel `oimpresso.com/insights/consent` a qualquer momento, com efeito em até 7 dias úteis. Revogação parcial (ex: aceitar Snapshot mas recusar agregação) é permitida.
>
> X.4 Base legal LGPD Art. 7º, V (cumprimento de obrigação contratual) para Tier 1-2 e Art. 7º, IX (legítimo interesse com transparência) para Tier 3-5, sempre com possibilidade de oposição (Art. 18, §2º).
>
> X.5 A WR Sistemas atua como CONTROLADORA dos dados agregados anonimizados e como OPERADORA dos dados identificáveis do CLIENTE.

> 🚨 **NÃO USAR EM PRODUÇÃO sem assinatura de counsel LGPD.** Texto acima é DRAFT pra discussão.

### 1.2 Termo de uso de dados (consent flow)

Fluxo dual-consent:
1. **Onboarding novo cliente OfficeImpresso/oimpresso**: opt-in granular no contrato
2. **Cliente atual (37 ativos)**: campanha de re-consent obrigatória ANTES do Tier 3
   - Email + WhatsApp explicando: "Vamos lançar relatórios setoriais. Seus dados, anonimizados, podem participar?"
   - Default = OPT-OUT. Só processa quem assinar.
   - Aceite registrado em tabela `insights_consents` com IP, timestamp, hash do termo, versão do termo

### 1.3 DPA (Data Processing Agreement) modelo Brasil

Anexo separado ao contrato (1ª revisão, exige counsel):
- **Finalidades específicas** (lista fechada — não "qualquer fim comercial")
- **Categorias de dados**: financeiros (FINANCEIRO), comerciais (VENDA, NOTA_FISCAL), cadastrais agregados (PESSOAS sem PII)
- **Retenção**: 5 anos pós-revogação (cumprimento fiscal Art. 195 CTN), depois deleção criptográfica
- **Sub-operadores autorizados**: Hostinger (hospedagem), CT 100 Proxmox interno (processamento), AWS S3 BR (backup) — lista fechada com direito de objeção
- **Direitos do titular** (LGPD Art. 18): acesso, correção, anonimização, portabilidade, eliminação, revogação, oposição
- **Notificação de incidente**: 24h após detecção (mesmo prazo ANPD recomenda)
- **Auditoria**: cliente pode auditar 1×/ano com aviso 30d

### 1.4 Pipeline de anonimização (técnico)

**Camada 1 — Pseudonimização** (ETL Firebird → MySQL staging):
- `RAZAOSOCIAL` → `sha256(razaosocial + business_id + salt)[:16]` — chave estável por cliente final mas não-reversível externamente
- `CNPJCPF` → `sha256` + descarte original em DB agregado
- `EMAIL`, `FONE` → eliminados completamente em camada agregada
- `ENDERECO` → mantém só `CIDADE` + `UF` (sem CEP completo)

**Camada 2 — k-anonymity (k=5)** (agregação cross-cliente):
- Toda dimensão de relatório agregado precisa ter **mín 5 entidades** no bucket
- Ex: "ticket médio gráficas SP" só publicável se ≥5 gráficas SP no dataset
- Bucket com k<5 → suprimir ou agregar até atingir k=5

**Camada 3 — Differential privacy (Tier 5 / relatórios públicos)**:
- Ruído Laplaciano em totais publicados (ε=1.0 default, configurável)
- Top-N clientes/fornecedores por GMV NUNCA publicados nominalmente — só faixas

**Auditoria**: tabela `insights_anonymization_audit` registra cada export com hash do dataset + parâmetros k/ε aplicados. Imutável (append-only, trigger MySQL).

### 1.5 Opt-out a qualquer momento

- Painel self-service em `/insights/consent` (autenticado)
- Botão "Revogar consentimento agregação" + "Revogar tudo (incluindo dashboard próprio)"
- Efeito imediato em consentimento + 7 dias úteis para purga retroativa de agregados
- Auditoria: log imutável de cada revogação

### 1.6 Revisão jurídica obrigatória (gate de produção)

**Antes de qualquer linha de Tier 3+:**
1. Counsel LGPD externo revisa cláusula contratual + DPA + termo opt-in
2. DPO designado (interno ou terceirizado — Wagner candidato natural mas precisa formalizar)
3. RIPD (Relatório de Impacto à Proteção de Dados) elaborado e arquivado
4. Registro de operações de tratamento atualizado (Art. 37 LGPD)
5. Política de privacidade pública atualizada em `/privacidade-insights`

**Sem esses 5 itens checkados, Tier 3+ não vai pra prod.** Tier 2 (cliente vê só dados próprios) tem barra mais baixa mas ainda exige cláusula 1.1.

---

## 2. Tier comercial expandido

Estende `feature-financial-snapshot-multi-cliente.md` Tiers 1-3.

### Tier 4 — Insights Pro (R$ [redacted Tier 0]/m)

**LGPD**: cliente vê dados próprios + benchmarks anonimizados k=5. Opt-in seção 1.2.

- Tudo do Tier 2 (Pro R$ [redacted Tier 0]) +
- **Benchmark setorial anônimo**: "sua margem está 12pp abaixo da média de gráficas SP/médio porte"
- **Forecast 90d** receita esperada (regressão simples sazonal — não ML pesado)
- **Recomendações acionáveis**: top 3 ações por ROI estimado
- Multi-banco (até 3 bases, ex: cliente com 3 unidades)
- Suporte WhatsApp + 1 sessão Wagner trimestral

### Tier 5 — Data Partner (R$ [redacted Tier 0]/m)

**LGPD**: exige DPA específica + auditoria 1×/ano. Opt-in seção 1.2 + assinatura formal.

- Tudo do Tier 4 +
- **API REST** com auth OAuth2 (Sanctum) — leitura de agregados anonimizados
- **Multi-cliente**: parceiro vê N clientes do próprio portfólio (ex: contabilidade com 10 clientes oimpresso)
- **White-label** (subdomain custom, logo, tema)
- **SLA 99.5%** + suporte dedicado
- Limite: queries API rate-limited 1000/dia, sem export bulk não-anonimizado

### One-shot reports (R$ [redacted Tier 0]-15.000)

**LGPD**: agregados k≥10 + differential privacy ε=1.0. Buyer assina NDA + uso restrito.

| Tipo | Preço | Audiência |
|---|---:|---|
| Snapshot setorial 1 página | R$ [redacted Tier 0] | leads, fornecedores curiosos |
| Relatório anual gráficas SP | R$ [redacted Tier 0] | fornecedores médios (Avery BR, 3M BR) |
| Relatório setorial nacional + entrevistas | R$ [redacted Tier 0] | grandes fornecedores, gov, investidores |

Vendido com NDA padrão + cláusula "uso restrito ao comprador". Não pode redistribuir.

### Tabela consolidada

| Tier | Preço/m | Quem | Features-core | Limite | LGPD-base |
|---|---:|---|---|---|---|
| Free | R$ [redacted Tier 0] | leads | 1 KPI mensal anônimo | 1 banco demo | sem dado real |
| Snapshot | R$ [redacted Tier 0] | cliente atual | dashboard básico próprio | 1 banco | Art. 7º V (contrato) |
| Pro | R$ [redacted Tier 0] | cliente médio | + benchmark + alerts | 1 banco | Art. 7º V + IX |
| Insights Pro | R$ [redacted Tier 0] | cliente grande | + forecast + multi-banco | 3 bancos | Art. 7º IX + opt-in |
| Data Partner | R$ [redacted Tier 0] | parceiro/fornecedor | API + white-label | unlimited (rate limit) | DPA específica |

---

## 3. Infraestrutura técnica

### 3.1 Stack canônica (alinhada ADR 0035 + 0062)

- **ETL**: `Modules/Insights/` (módulo nWidart Laravel)
  - Job `RunInsightsEtlJob` queued, lê Firebird via Python helper (firebird-driver) ou php-fdb
  - Origem: cada cliente OfficeImpresso (Firebird LAN) → tunnel SSH/Tailscale → CT 100
  - Destino: MySQL Hostinger tabela `insights_aggregates` (anonimizado, k≥5)
- **Dashboard**: React/Inertia em `oimpresso.com/insights/*`
  - Pages: `Insights/Dashboard.tsx`, `Insights/Benchmark.tsx`, `Insights/Reports.tsx`
  - Charters MWART obrigatórios (ADR 0104)
  - Charts: `chart.js` (já no projeto) — sem dependência nova
- **API REST**: Laravel routes + Sanctum OAuth2
  - Scribe (já 70% pronta — confirmar com Felipe) gera docs
  - Endpoints: `/api/insights/v1/aggregates`, `/api/insights/v1/benchmark`, `/api/insights/v1/forecast`
- **Cron mensal**: Laravel scheduler `app/Console/Kernel.php`
  - `insights:etl` → 1º do mês 02h BRT (carga off-peak cliente)
  - `insights:rebuild-benchmarks` → 1º do mês 04h BRT
  - `insights:purge-revoked` → diário 06h BRT (verifica `insights_consents` revogados)
- **Storage histórico**: AWS S3 região BR (`sa-east-1`) — 5 anos retenção (Art. 195 CTN)
  - Buckets: `oimpresso-insights-raw` (encrypted KMS, restricted) + `oimpresso-insights-aggregated` (encrypted, internal)
- **Multi-role**:
  - `cliente` — vê só próprios dados (Snapshot/Pro/Insights Pro)
  - `partner` — vê N clientes do portfólio (Data Partner — escopado por `partner_business_ids[]`)
  - `admin` — Wagner + DPO designado, acesso completo + audit trail
  - `external_buyer` — one-shot reports apenas (download PDF, sem dashboard)

### 3.2 Tabelas novas (migration `2026_05_09_create_insights_tables.php`)

```sql
-- Consentimentos (LGPD-critical)
CREATE TABLE insights_consents (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  tier_consent ENUM('snapshot','pro','insights_pro','data_partner') NOT NULL,
  granted_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  ip_address VARCHAR(45),
  consent_term_version VARCHAR(20),
  consent_term_hash CHAR(64),  -- SHA256 do termo aceito
  signed_by_user_id INT UNSIGNED,  -- usuário que assinou
  INDEX idx_biz_tier (business_id, tier_consent),
  INDEX idx_revoked (revoked_at)
);

-- Agregados anonimizados (cache materializado)
CREATE TABLE insights_aggregates (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  metric_key VARCHAR(100),  -- ex: 'monthly_revenue.graficas_sp.medio_porte'
  bucket_key VARCHAR(200),  -- ex: '2026-04|UF=SP|porte=M'
  k_anonymity_count INT,    -- número de entidades no bucket (deve >=5)
  value_p50 DECIMAL(15,2),
  value_p25 DECIMAL(15,2),
  value_p75 DECIMAL(15,2),
  value_mean DECIMAL(15,2),
  computed_at DATETIME,
  expires_at DATETIME,
  INDEX idx_metric_bucket (metric_key, bucket_key)
);

-- Audit trail anonymization
CREATE TABLE insights_anonymization_audit (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  export_id CHAR(36) UNIQUE,  -- UUID
  metric_keys TEXT,
  k_param INT,
  epsilon_param DECIMAL(5,2),
  bucket_count INT,
  rows_suppressed INT,  -- buckets com k<5 que foram dropped
  exported_at DATETIME,
  exported_by_user_id INT,
  -- TRIGGER MySQL: BEFORE UPDATE/DELETE → SIGNAL SQLSTATE '45000' (imutável)
  INDEX idx_exported_at (exported_at)
);

-- Conexões legacy (LAN bancos clientes)
CREATE TABLE insights_legacy_connections (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  alias VARCHAR(100),
  host VARCHAR(255),
  port INT DEFAULT 3050,
  schema_fingerprint CHAR(16),
  last_etl_at DATETIME,
  last_etl_status ENUM('ok','error','no_consent'),
  -- credenciais via Vaultwarden ref, NÃO no DB
  vaultwarden_item_id VARCHAR(36),
  INDEX idx_biz (business_id)
);
```

**business_id global scope obrigatório** (Tier 0 IRREVOGÁVEL — ADR 0093) em `insights_consents` e `insights_legacy_connections`. `insights_aggregates` é cross-tenant por design (já anonimizado) — usa role `admin`/`partner` pra controlar leitura.

### 3.3 Hosting separation (ADR 0062)

- **Hostinger**: app web `/insights` + tabelas + cron Laravel scheduler
- **CT 100 Proxmox**: Python ETL workers (firebird-driver), Tailscale tunnel pros bancos cliente, geração PDF (browserless), MCP exposed
- ⛔ NUNCA rodar daemons ETL no Hostinger. ⛔ NUNCA expor MCP tools no Hostinger.

### 3.4 Observabilidade

- OTel GenAI traces pra ETL job (já padrão Modules/Copiloto)
- `jana:health-check` ganha check `insights_etl_freshness_24h` (alerta se ETL falhou >24h)
- `jana:health-check` ganha check `insights_consent_drift` (alerta se aggregate inclui business sem consent ativo)

---

## 4. Roadmap de construção (90 dias = 13 sprints semanais)

> Fator 10x recalibrado ADR 0106 aplicado. Tarefas humano-limitadas (jurídico, opt-in, canary) mantém relógio do mundo real.

### Sprint 1-2 (semanas 1-2) — LEGAL + OPT-IN base

**Humano-bound, não acelera.**

- [ ] Counsel LGPD externo contratado (Wagner indica)
- [ ] Cláusula 1.1 + DPA 1.3 + termo opt-in 1.2 redigidos por counsel
- [ ] Versionamento dos termos em `memory/legal/insights/v1/`
- [ ] Email + WhatsApp pros 37 clientes OfficeImpresso atuais com cláusula nova (anexo de contrato)
- [ ] Painel `/insights/consent` MVP (read-only — só mostra status, opt-in vem em sprint 3)
- [ ] DPO designado e formalizado em RDP

**Critério saída sprint 2**: cláusula assinada por counsel + 5 clientes pilotos contatados.

### Sprint 3-4 (semanas 3-4) — ETL + agregação base

- [ ] Módulo `Modules/Insights/` scaffold (skill `criar-modulo`)
- [ ] Migration tabelas seção 3.2
- [ ] Service `FirebirdConnector` (PHP shell-out Python OU php-fdb — decisão Felipe)
- [ ] Job `RunInsightsEtlJob` queued + cron scheduler
- [ ] Pipeline anonimização camadas 1 + 2 (k=5) implementado
- [ ] Pest 5+ tests: isolamento tenant, k-anonymity enforcement, opt-out purge
- [ ] 3 dos 5 pilotos com ETL rodando e consent assinado

### Sprint 5-6 (semanas 5-6) — Dashboard MVP

- [ ] Charter `Insights/Dashboard.charter.md` aprovado por Wagner (MWART F0)
- [ ] Visual-comparison.md aprovado SCREENSHOT (MWART F1.5)
- [ ] Pages: `Dashboard.tsx` + `Connections.tsx` + `ConsentPanel.tsx`
- [ ] 4 KPIs base: receita 12m, MRR atual, A receber vencidas, top 10 clientes
- [ ] Multi-tenant Tier 0 enforcement (skill `multi-tenant-patterns`)
- [ ] 5 pilotos vendo dashboard próprio

**Critério saída sprint 6**: 5 pilotos logando ≥3×/semana no dashboard.

### Sprint 7-8 (semanas 7-8) — API REST + auth

- [ ] Sanctum OAuth2 setup
- [ ] Rotas `/api/insights/v1/*` + Scribe docs
- [ ] Rate limiting (1000 req/dia/partner)
- [ ] Audit log toda chamada API (`insights_api_audit`)
- [ ] 1 parceiro beta testando API (provavelmente Asaas — já tem relação)

### Sprint 9-10 (semanas 9-10) — Relatório PDF + agendamento

- [ ] Geração PDF via browserless CT 100 (template Inertia → puppeteer)
- [ ] Agendamento cron mensal envia PDF email
- [ ] Template visual aprovado por Wagner (skill `mwart-comparative` aplicada)
- [ ] One-shot report manual flow (Wagner aprova export → audit registra)

### Sprint 11-12 (semanas 11-12) — Launch privado

- [ ] 5 pilotos viram **pagantes Tier 2** (R$ [redacted Tier 0]/m × 5 = R$ [redacted Tier 0]/m base)
- [ ] Pricing-lock anual oferecido (R$ [redacted Tier 0]/ano)
- [ ] Webinar gravado "Como leio meu negócio sem abrir o sistema" (pré-fase 2 GTM)
- [ ] Página `/insights` pública com demo dados sintéticos

### Sprint 13 (semana 13) — Launch público

- [ ] Anúncio LinkedIn + email base oimpresso
- [ ] Free tier ativo (1 KPI mensal anônimo)
- [ ] Métricas baseline registradas (`jana:health-check insights_*`)
- [ ] Retro 90d → decide construir Tier 4-5 ou parar

**Critério saída**: 5 pagantes ativos. Sem isso → arquivar como ADR feature-wish.

---

## 5. GTM (go-to-market)

> LGPD aplicada em CADA fase: nada vai pra fora sem consent + anonimização adequada ao tier.

### Fase 1 — Pilotos grátis 30d (sprint 11)

5 clientes saudáveis identificados via análise piloto (sinal qualificado ADR 0105):
- **Vargas** · **Extreme** · **Gold** · **Zoom** · **Fixar** (top 5 por receita 12m + recência <30d)

Oferta: 30d grátis Tier 2, depois R$ [redacted Tier 0]/m com pricing-lock anual R$ [redacted Tier 0] Sem cartão upfront.

**LGPD**: opt-in já assinado em sprint 1-2.

### Fase 2 — Webinar lançamento (sprint 13 + 30d)

Tema: *"Como leio meu negócio sem abrir o sistema — case real anonimizado"*
- Mostra dashboard de cliente piloto (com permissão escrita + dados mascarados extra)
- Atrai churned OfficeImpresso (lista de 12 ex-clientes nos últimos 24m)
- CTA: "trial 30d grátis"

**LGPD**: case usado tem opt-in formal específico pro evento + termo de imagem.

### Fase 3 — Parcerias fornecedores (mês 4-6)

Fornecedores B2B do setor (gráfica/com.visual):
- **Avery BR** (etiquetas) · **3M BR** (vinis/comunicação visual) · **Heytex** (lonas) · **Roland DG BR** (impressoras)

Pitch: *"Quer saber qual % das gráficas SP/MG/RJ usa lona impermeável? Relatório anual setorial R$ [redacted Tier 0]"*

**LGPD**: relatório agregado k≥10 + differential privacy. Buyer assina NDA + uso restrito.

### Fase 4 — Relatório anual setorial (mês 6+)

Produto editorial recorrente:
- **Q4/2026**: 1º "Estado da Comunicação Visual BR — gráficas rápidas pequeno/médio porte" (R$ [redacted Tier 0] unit)
- Vendido a 5-10 fornecedores + assoc. setoriais (ABIGRAF, ABICOM)
- Receita esperada: 5 vendas × R$ [redacted Tier 0] = R$ [redacted Tier 0]/relatório (~R$ [redacted Tier 0]k/ano se 4 relatórios)

**LGPD**: agregação cross-cliente k≥10 + ε=1.0 + revisão DPO + revisão counsel pré-publicação.

### Fase 5 — API B2B parceiros estratégicos (mês 9+)

Hipóteses de parceiros (validar antes de construir):
- **Asaas** — pagamentos: pode pagar pra ver agregados de inadimplência setorial → calibrar score crédito
- **Seguradoras B2B** (Porto Seguro Empresas, Tokio Marine) — perfil de risco gráfica/com.visual
- **Fintechs B2B** (Cora, Conta Simples) — qualificação de lead PJ

Tier 5 Data Partner R$ [redacted Tier 0]/m + revenue share opcional em volume alto.

**LGPD**: DPA específica por parceiro + auditoria 1×/ano + counsel revisa cada novo contrato.

---

## 6. Pricing strategy detalhado

| Tier | Preço/m | Quem | Features | Limite | LGPD |
|---|---:|---|---|---|---|
| Free | R$ [redacted Tier 0] | leads | 1 KPI mensal genérico anônimo | 1 banco demo | sem dado real |
| Snapshot | R$ [redacted Tier 0] | cliente atual OfficeImpresso/oimpresso | dashboard básico próprio | 1 banco | Art. 7º V |
| Pro | R$ [redacted Tier 0] | cliente médio | + benchmark anônimo + alerts WhatsApp | 1 banco | Art. 7º V + IX |
| Insights Pro | R$ [redacted Tier 0] | cliente grande/multi-unidade | + forecast + recomendações + multi-banco (3) | 3 bancos | Art. 7º IX + opt-in escrito |
| Data Partner | R$ [redacted Tier 0] | parceiro estratégico | API REST + white-label + multi-cliente portfolio | 1000 req API/dia | DPA específica + audit anual |

**One-shot reports**: R$ [redacted Tier 0]k (snapshot 1pg) → R$ [redacted Tier 0]k (anual setorial regional) → R$ [redacted Tier 0]k (nacional + entrevistas).

**Setup**: zero default. R$ [redacted Tier 0] só pra integração customizada (banco em local incomum, charset diferente, etc).

**Anual paga 10**: Tier 2 R$ [redacted Tier 0]/ano · Tier 3 R$ [redacted Tier 0]/ano · Tier 4 R$ [redacted Tier 0]/ano · Tier 5 R$ [redacted Tier 0]/ano.

---

## 7. Modelo de receita projetado 24m

### Cenário base realista (após pilotos validarem)

| Linha | Volume | Preço/m | ARR |
|---|---:|---:|---:|
| Snapshot R$ [redacted Tier 0]/m | 30 clientes | R$ [redacted Tier 0] | R$ [redacted Tier 0] |
| Pro R$ [redacted Tier 0]/m | 10 clientes | R$ [redacted Tier 0] | R$ [redacted Tier 0] |
| Insights Pro R$ [redacted Tier 0]/m | 5 clientes | R$ [redacted Tier 0] | R$ [redacted Tier 0] |
| Data Partner R$ [redacted Tier 0]/m | 2 parceiros | R$ [redacted Tier 0] | R$ [redacted Tier 0] |
| **Subtotal recorrente** |  |  | **R$ [redacted Tier 0]** |
| Relatórios setoriais | 4 relatórios/ano × R$ [redacted Tier 0] médio | — | R$ [redacted Tier 0] |
| **Total ARR DaaS 24m** |  |  | **~R$ [redacted Tier 0]** |

### Bonus implícito — conversão pra oimpresso.com novo

- Cada cliente Tier 3+ que migrar pro oimpresso.com = +R$ [redacted Tier 0]-1.499/m receita ERP completo
- Estimativa: 5 migrações em 24m × R$ [redacted Tier 0] médio × 12m = **R$ [redacted Tier 0] adicional**

**Total combinado realista 24m**: ~R$ [redacted Tier 0]k ARR (~5% da meta R$ [redacted Tier 0]M; combinado migração ~R$ [redacted Tier 0]k = ~5%)

### Cenário otimista (sinal forte fase 3-5 vinga)

- 50 Snapshot + 25 Pro + 12 Insights Pro + 6 Data Partner + 8 relatórios/ano
- ~R$ [redacted Tier 0]k ARR (~9% da meta)

### Cenário conservador (só pilotos saudáveis viram pagantes, Tier 4-5 não vinga)

- 10 Snapshot + 3 Pro + 1 Insights Pro + 0 partner + 1 relatório
- ~R$ [redacted Tier 0]k ARR (~0.7% da meta)

> Realista é base. Otimista exige mercado fornecedor maduro pra DaaS B2B (não-óbvio em 2026 BR).

---

## 8. Riscos + mitigações

### Risco 1 — Cliente sentir invadido ("vão me espionar")

**Probabilidade**: alta (50%+ resistência inicial em base legacy).
**Mitigação**:
- Opt-in EXPLÍCITO + DEFAULT OPT-OUT (nunca presumir)
- Transparência radical: cliente vê EXATAMENTE quais dados saem do banco dele (audit log self-service)
- Valor primeiro: dashboard próprio (Tier 2) entrega ROI antes de pedir agregação (Tier 3+)
- Comunicação vertical no opt-in: "agregamos para benchmark, NUNCA vendemos seus números nominais"

### Risco 2 — Penalidade ANPD/LGPD

**Probabilidade**: baixa se compliance feito; alta se atalhar.
**Penalidade**: até **2% faturamento ou R$ [redacted Tier 0]M** por infração (Art. 52 LGPD).
**Mitigação**:
- DPO formalizado + RIPD documentado
- Counsel LGPD revisa CADA novo termo/cláusula
- Audit trail imutável (`insights_anonymization_audit`)
- Auditoria interna trimestral + externa anual
- Política de incidente: 24h notificação ANPD + cliente
- Seguro responsabilidade civil cyber (ex: Chubb, Tokio) — R$ [redacted Tier 0]-10k/ano por R$ [redacted Tier 0]M cobertura

### Risco 3 — Concorrente copiar (Mubisys/Zênite/Calcgraf)

**Probabilidade**: alta em 12-18m.
**Mitigação**:
- **Barreira é o ACESSO HISTÓRICO de 26 anos de Delphi WR Comercial** — não replicável
- Concorrentes não têm leitor Firebird legacy + relacionamento cliente legacy
- Network effect: quanto mais clientes opt-in, melhor benchmark (k-anonymity vira moat)
- Brand: Wagner é o "cara que sabe ler ERP de gráfica" — moat reputacional

### Risco 4 — Custo de manutenção (drift schema, suporte multi-cliente)

**Probabilidade**: média (cada banco cliente pode ter ligeira variação Delphi).
**Mitigação**:
- `schema_fingerprint` na tabela `insights_legacy_connections` detecta drift
- ETL falha graciosamente (job retry + alerta health-check) — não corrompe agregado
- Começar SaaS (1 codebase) → escalar custo marginal (~R$ [redacted Tier 0]/cliente/m em infra)

### Risco 5 — LGPD: revogação em massa pós-launch

**Probabilidade**: média (cliente vê valor, raramente revoga; mas 1 incidente externo pode causar onda).
**Mitigação**:
- Job `insights:purge-revoked` diário rebuilda agregados sem cliente revogado
- Comunicação proativa: trimestral, "veja o que entregamos com seus dados anonimizados"
- Se >20% revogar em 30d → halt automático + post-mortem + counsel review

### Risco 6 — Dependência de tunnel SSH / Tailscale pros bancos cliente

**Probabilidade**: alta (cada cliente roda Firebird LAN; conexão flaky).
**Mitigação**:
- Tailscale (já em produção CT 100) preferido sobre autossh
- ETL job tem retry exponential backoff
- Se cliente offline >7 dias → email avisa + dashboard mostra "dados desatualizados"
- Opção fallback: cliente exporta dump Firebird manual semanalmente (suporte humano)

### Risco 7 — Dispersão de foco (oimpresso ERP vs Insights vs migração)

**Probabilidade**: alta (Wagner já com WIP=2).
**Mitigação**:
- DaaS é caminho pra migração, NÃO competidor — mesmo time, mesmo CTO
- Felipe owner técnico Insights · Wagner aprovação + counsel · Maiara suporte cliente piloto
- Sprint review semanal mantém scope claro
- Critério kill: 90d sem 5 pagantes = arquivar como ADR feature-wish (ADR 0105)

---

## 9. Critério de "GO/NO-GO" formal

### GO pra construir Tier 4-5 (sprint 8+)
- ≥5 clientes pagantes Tier 2 ativos no fim sprint 12
- ≥1 parceiro/fornecedor com LOI assinada pra Tier 5 ou relatório
- LGPD compliance auditado por counsel sem ressalva crítica
- NPS dos pilotos ≥30

### NO-GO (arquivar como ADR feature-wish)
- <3 pagantes em sprint 12 = sinal fraco
- Counsel LGPD com ressalva crítica não-resolvida em 30d
- Cliente piloto reporta problema de dados (vazamento, drift) com severidade alta

### Pivot
- Se Tier 2 vinga mas Tier 4-5 não: ficar em SaaS B2C-de-cliente (sem agregação cross-cliente). Ainda viável ~R$ [redacted Tier 0]k/ano + lead-magnet pra migração.

---

## 10. Próximos passos (Wagner aprovação)

- [ ] Aprovar conceito + LGPD-first abordagem? (S/N)
- [ ] Aprovar contratar counsel LGPD externo (orçamento R$ [redacted Tier 0]-15k one-shot + retainer)?
- [ ] Apontar 5 clientes pilotos definitivos? (default sugerido: Vargas/Extreme/Gold/Zoom/Fixar)
- [ ] Aprovar DPO designado (Wagner candidato natural)?
- [ ] Aprovar roadmap 90d ou ajustar?
- [ ] Formalizar como ADR canon (próximo nº ~0121-0125) ou manter feature-wish?
- [ ] OK pra Felipe começar sprint 1-2 (legal + opt-in) em paralelo a outros WIPs?

---

## 11. Referências canon

- ADR 0035 (Stack IA canônica) · ADR 0048 (Vizra rejeitada) · ADR 0053 (MCP server)
- ADR 0062 (Hostinger ≠ CT 100) · ADR 0070 (Jira-style tasks)
- ADR 0093 (Multi-tenant Tier 0 IRREVOGÁVEL) · ADR 0094 (Constituição v2)
- ADR 0104 (MWART processo canônico) · ADR 0105 (Cliente como sinal qualificado)
- ADR 0106 (Recalibração velocidade fator 10x)
- LGPD Lei 13.709/2018 — especialmente Art. 7º, 18, 37, 52
- `OFFICEIMPRESSO-FIREBIRD-SCHEMA.md` (fonte dados)
- `feature-financial-snapshot-multi-cliente.md` (Tier 1-3 base)
- `.claude/skills/officeimpresso-financial-snapshot/SKILL.md` (automação)

---

**Status**: PROPOSED — aguarda Wagner + counsel LGPD externo antes de virar ADR canon.
