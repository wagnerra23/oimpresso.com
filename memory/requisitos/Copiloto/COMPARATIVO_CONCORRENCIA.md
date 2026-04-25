# Copiloto — Comparativo Concorrência (estilo Capterra)

**Última atualização:** 2026-04-25 | **Próx. revisão:** 2026-07-25

## Sobre o módulo

| Campo | Valor |
|---|---|
| **Best for** | "Gestor PME que quer chat IA propondo metas + monitorando" |
| **Setor** | BI + AI assistant + business intelligence pra PME |
| **Stage** | Spec-ready completo (ARQ + UI ADRs), scaffold mínimo |
| **Persona** | Wagner (dono PME) + Larissa-financeiro (operadora) |
| **JTBD** | "Conversar com IA sobre meu negócio e receber metas concretas com plano" |

## Cards comparados

### 🟢 Copiloto (oimpresso)
- ⭐ **Score:** 0/100 (não implementado)
- 💰 **Preço planejado:** Free 50 msgs/mês / R$ 79 Pro / R$ 199 Enterprise
- 🎯 **Best for:** PME que já usa UPos + quer insights tipo conversational
- ✨ **Diferencial planejado:** Sabe contexto da tela atual + dados do tenant + propõe metas
- ☁️ Cloud (Vizra ADK + Prisma OU LaravelAI)

### 🔴 ChatGPT Custom GPTs
- ⭐ **Capterra:** 4,7/5 (~5000 reviews global)
- 💰 US$ 20/mês ChatGPT Plus
- 🎯 **Best for:** Profissional knowledge worker
- ✨ **Diferencial:** Modelo state-of-art GPT-4
- ❌ **Falha BR:** sem contexto do meu ERP, sem integração POS
- ☁️ Cloud OpenAI

### 🔴 Microsoft Copilot for Business
- ⭐ **Capterra:** 4,3/5 (~3000 reviews)
- 💰 US$ 30/mês por user
- 🎯 **Best for:** Empresa que usa Microsoft 365
- ✨ **Diferencial:** Integração Word/Excel/Teams nativa
- ❌ **Falha BR:** caro, EN-only nas funções avançadas
- ☁️ Cloud Microsoft

### 🟡 Tabnine / GitHub Copilot
- (não competidores diretos — coding assistants)

### 🟡 Tiny IA / Bling Copilot
- ⭐ **Capterra:** N/A (lançamento recente 2026)
- 💰 Bundle Tiny/Bling
- 🎯 **Best for:** Quem já é cliente
- ✨ **Diferencial:** Integrado ERP-próprio
- ❌ **Falha:** features ainda pobres, principalmente FAQ-bot

## Matriz de features

| Feature | 🟢 Nós | ChatGPT | MS Copilot | Tiny IA | Bling | Importância |
|---|---|---|---|---|---|---|
| Chat conversacional | ❌ planejado | ✅ | ✅ | ✅ | ⚠ | **P0** |
| Sabe a tela atual | ✅ planejado | ❌ | ⚠ Office | ❌ | ❌ | **diferencial** |
| Sabe dados do tenant | ✅ planejado | ❌ | ⚠ via plugin | ✅ | ⚠ | **diferencial** |
| Propõe metas | ✅ planejado | ⚠ se perguntado | ❌ | ❌ | ❌ | **diferencial** |
| Monitora desvio de meta | ✅ planejado | ❌ | ❌ | ❌ | ❌ | **diferencial** |
| Multi-tenancy híbrido (biz_id null) | ✅ ADR ARQ-0001 | N/A | N/A | ⚠ | ⚠ | P1 |
| Integração POS UPos | ✅ planejado | ❌ | ❌ | ❌ | ❌ | **P0** |
| Mascara CPF/CNPJ antes do prov | ✅ planejado | ❌ | ⚠ DLP | ❌ | ❌ | **diferencial** |
| API custom (drivers) | ✅ ADR TECH-0001 | API REST | Graph API | ❌ | ❌ | P1 |

## Score (Capterra-style)

| Critério | 🟢 Nós | ChatGPT | MS Copilot | Tiny IA |
|---|---|---|---|---|
| Easy of use | 0 | **9** | 8 | 7 |
| Inteligência (LLM) | 0 | **10** | 9 | 6 |
| Contexto do negócio | 0 | 2 | 6 | **8** |
| Privacidade (LGPD/PII) | 0 | 4 | 7 | **8** |
| Integração ERP BR | 0 | 0 | 0 | **8** |
| Preço BR | 0 | 5 (US$) | 4 (US$) | **9** |
| **Total /60** | **0** | **30** | **34** | **46** |
| **Score /100** | **0** | **50** | **57** | **77** |

## Estratégia

### Posicionamento (planejado)
> _"O assistente IA que conhece seu negócio porque está dentro do seu ERP."_

### Track imitar (acelerada)
- **MVP:** Chat básico + briefing tenant (1 sessão)
- **Onda 2:** Sugerir metas com schema JSON (LaravelAI ou OpenAI direto)
- **Onda 3:** Apurar metas (SqlDriver + ApurarMetaJob — ADR TECH-0001)

### Track diferenciar (aposta)
- **Sabe a tela atual** (FAB com `?context=rota_origem`)
- **Multi-tenancy híbrido** (business_id NULL = plataforma, vê todos)
- **Mascara PII antes do provedor** (LGPD compliance — competitivo BR)
- **Integração nativa Financeiro/PontoWr2/Officeimpresso** (dados unificados)

### Preço
- Free 50 mensagens/mês (entrada)
- Pro R$ 79 (vs ChatGPT Plus US$ 20 ≈ R$ 100; mais barato + contexto BR)
- Enterprise R$ 199 ilimitado + custom drivers

### Métricas
- Cobertura de telas com `?context`: **100% em 6 meses**
- Adoção: **30% dos tenants UPos** abrem chat 1×/semana
- NPS pós-launch: meta **8/10**

## Refs

- ADR ARQ-0001 a 0002 + TECH-0001 a 0002 + UI-0001 (Copiloto)
- memory/requisitos/Copiloto/SPEC.md + ARCHITECTURE.md + RUNBOOK.md
- ChatGPT, MS Copilot for Business — sites oficiais
