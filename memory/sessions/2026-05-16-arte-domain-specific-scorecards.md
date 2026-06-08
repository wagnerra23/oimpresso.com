---
slug: 2026-05-16-arte-domain-specific-scorecards
title: "Estado-da-arte — Domain-Specific Scorecards (régua por tipo de módulo)"
type: session
date: 2026-05-16
authority: research
lifecycle: ativo
audience: [W]
related_adrs: [0153, 0154, 0155, 0156, 0157, 0158, 0159]
related_skills: [module-grades-gate, avaliar-modulo]
question: "Como dar nota a 34 módulos heterogêneos (verticais vs cross-cutting vs IA vs funcionais) sem que régua única vire trofeu inflado ou injusto?"
tags: [governance, rubrica, scorecards, scoped, lenses, port, opslevel, datadog, backstage, aws-wa, anti-gaming]
---

# Estado-da-arte — Domain-Specific Scorecards (régua por tipo de módulo)

> **Pergunta Wagner:** "Quero rubrica que não seja genérica — régua especializada por tipo de módulo, cada bucket com dimensões e pesos próprios. Que técnica é essa? Quem dominou em 2026?"
>
> **Resposta curta:** o nome canônico na indústria é **Scoped Scorecards** (também chamado de **Lenses Pattern** quando vem da família AWS Well-Architected). Top players: **AWS Well-Architected Lenses**, **Datadog Service Scorecards**, **Backstage Tech Insights**, **Port Scorecards**, **OpsLevel Scoped Scorecards**. **Cortex é o case negativo** (régua única undifferentiated — perdeu posição pra OpsLevel/Datadog exatamente por isso). Wagner está intuindo o problema certo.

---

## 1. Pesquisa (Fase 1 — clean, sem contaminar com oimpresso)

### 1.1 Nome canônico da técnica

Não tem 1 nome único formal. Indústria 2025-2026 usa **3 termos quase intercambiáveis**:

| Termo | Origem dominante | Conotação |
|---|---|---|
| **Scoped Scorecards** | OpsLevel (2023+), Datadog (2024+), Port (2024+) | Plataforma de IDP — scorecard com filter por entity type/tag/tier |
| **Lenses (Pattern)** | AWS Well-Architected (2018+, expandido massivamente 2023-2025) | Cada "lens" = perspectiva domínio-específica com pillars/questions próprios sobre o mesmo workload |
| **Quality Profiles** | SonarQube (legacy, 2010+) | Conjunto de regras por linguagem — não cobre exatamente pesos diferentes, é mais "regras-on/off" |

Pra oimpresso o termo mais limpo é **"Scoped Scorecards" + "Lens per Module Kind"** combinados.

> **Por que não tem nome acadêmico único:** literatura formal (ISO/IEC 25010:2023, CMMI, SPACE) define **dimensões de qualidade** universais (9 características no 25010), mas explicitamente delega "weighting depends on stakeholder/domain" pro implementador. Quem materializou o pattern com pesos por bucket foi a indústria de IDP (Internal Developer Portals) 2022-2026.

### 1.2 Top 5 champions (cases reais, sem buzzword)

| # | Player | Mecanismo concreto | Por que é referência |
|---|---|---|---|
| **1** | **AWS Well-Architected Lenses** | 6 pillars core (Operational/Security/Reliability/Performance/Cost/Sustainability) + **N lenses domain-specific** que adicionam pillars/questions próprios (Serverless, ML, SaaS, Financial Services, Games, IoT, HPC, Healthcare, Streaming Media, Hybrid Networking, SAP). Workload pode ter até 20 lenses aplicados simultaneamente. Custom Lenses (user-defined) compartilháveis cross-account. | Padrão de facto na nuvem desde 2018; expandido pra ~15 lenses oficiais 2025-2026. Adotado por consultorias (Accenture, Deloitte) como linguagem cliente. |
| **2** | **Datadog Service Scorecards** | Rules organizadas em **3 níveis hierárquicos** (Level 1 baseline, Level 2 recomendado, Level 3 aspiracional). Rule pode ser **scoped por entity kind + tier + custom tags**. 3 scorecards out-of-the-box (Production Readiness, Observability, Ownership) + custom unlimited. | Dogfooded internamente pela Datadog (case study público "Dogfooding Scorecards"); adoção rápida 2024-2026 porque pluga no service catalog deles. |
| **3** | **Backstage Tech Insights (Spotify, CNCF)** | `TechInsightsScorecardBlueprint` permite filtrar scorecard por `kind: api` vs `kind: service` vs `kind: library` vs `spec.lifecycle: production`. Facts (data) separadas de Checks (rules), permitindo reuso e composição. Roadie + Port + Harness oferecem managed SaaS. | Open-source CNCF, ecossistema plugin maior do mercado; ~3.4k empresas usam Backstage em prod (2025 CNCF survey). |
| **4** | **Port Scorecards (Blueprint-based)** | Cada blueprint (≈ entity type) tem N scorecards próprios. Scorecard tem **levels hierárquicos** (Basic→Bronze→Silver→Gold customizável) + **filter** que decide quais entities são avaliadas. **Notável:** Port explicitamente NÃO suporta pesos por regra — recomenda usar **níveis** ou **scorecards separados** pra modelar prioridade. | Crescimento mais rápido entre IDPs 2024-2026; modelo blueprint é o mais flexível pra modular. |
| **5** | **OpsLevel Scoped Scorecards** | Combina **Rubric global** (standards organização) com **Scoped Scorecards** (per team/group/category). Scoped = scope filter por service type + maturity tier. Marketing positioning explícito vs Cortex: *"Cortex applies same baseline to all services; OpsLevel scopes per service type/maturity"*. | Único player com narrativa pública "régua única é antipattern". Adoção em larges 2024-2026 (Cisco, Roblox, Plaid). |

**Honorable mentions:**

- **CISQ Scorecarding** (Consortium for IT Software Quality, ISO/IEC 5055) — define KPIs Security/Reliability/Performance/Maintainability mas não opina sobre pesos por domínio (delega).
- **OpenSSF Scorecard** (security only) — weighted aggregated score via "risk level as weight" — cada check tem risk Critical/High/Medium/Low que pondera. Modelo de **peso por risco** dentro de UM domínio.
- **Gartner Software Engineering Score** (2024+) — produto fechado de benchmark, dimensões diferentes por org size/domain.

### 1.3 Como cada um aplica buckets/categorias diferentes

**AWS Lenses — exemplo Serverless Lens:**
- Pega os 6 pillars core
- Adiciona perguntas específicas (ex: "How do you optimize cold start latency?")
- Cada pergunta tem improvement plan próprio
- **Score não é numérico global** — é "X de Y best practices atendidas" por pillar, por lens
- Custom Lens permite pillars completamente novos (ex: "Multi-Tenancy" como pillar próprio em SaaS Lens)

**Backstage Tech Insights — exemplo:**
```yaml
- name: api-production-scorecard
  filter:
    kind: api
    spec.lifecycle: production
  checks:
    - has-openapi-spec
    - has-sla-defined
    - has-circuit-breaker
- name: library-scorecard
  filter:
    kind: library
  checks:
    - has-readme
    - semver-compliant
    - has-tests
```
Mesma entity (component) tem checklists radicalmente diferentes dependendo de `kind`.

**Datadog — exemplo Level 1 vs Level 3:**
- Level 1 (basic): "has on-call owner", "has monitor", "has team"
- Level 3 (aspiracional): "has chaos test", "has runbook", "has p99 SLO defined"
- Cada rule pode ser scoped: `kind:service AND tier:critical`

### 1.4 Casos acadêmicos relevantes

- **ISO/IEC 25010:2023** (jan 2023): 9 características universais (Functional Suitability, Performance, Compatibility, Interaction Capability, Reliability, Security, Maintainability, Flexibility, **Safety NOVO**). Norma explicitamente diz: *"weighting characteristics depends on stakeholder needs and product context"* — delega pesos pro implementador.
- **CMMI v3.0** (2023): maturity por **process area**, não por componente. Pattern indireto: cada PA tem práticas próprias, score agregado é "% PAs no nível N".
- **SPACE Framework** (Forsgren, Storey, et al. 2021): produtividade dev em 5 dimensões (Satisfaction, Performance, Activity, Communication, Efficiency). Defende **pesos diferentes por team/role**, não régua única.

---

## 2. Comparação com oimpresso (Fase 2)

### 2.1 Estado atual (módulo-grade-v3 + 4 erratas)

| Aspecto | Estado oimpresso 2026-05-16 | Estado-da-arte (Fase 1) |
|---|---|---|
| **Modelo conceitual** | Régua única 9 dimensões (D1-D9) pesos fixos, normalizada /100 | Lens/Scorecard scoped por entity kind/tier |
| **Pesos** | Fixos pra todos 34 módulos (D1=25, D2=17, D3=12, D4=17, D5=12, D6-D9=35) | Pesos próprios por bucket (Port: níveis em vez de pesos; Datadog: Level 1-2-3; AWS: pillars próprios por lens) |
| **Escape hatch** | 4 hacks no [ADR 0159](../decisions/0159-module-grade-v3-errata-meta-97-realismo.md): `internal_governance_active=15`, `query_failed_jobs=true` default, `fsm_n_a=true` via module.json, CHANGELOG ≤7d reset D3.b | Backstage/Port: filter no scorecard exclui entity — não vira "escape", vira "regra não se aplica desde sempre". Pattern limpo. |
| **Meta numérica** | 97.75/100 hard global pra TODOS módulos | Datadog/OpsLevel: meta **por scorecard**, não global. AWS: % de best practices por pillar por lens. |
| **Anti-gaming** | review_triggers no ADR 0159 (>3 módulos viram cross-cutting/fsm_n_a sem justificativa) | Port: niveis hierárquicos em vez de pesos (mais difícil gamear). Datadog: rules versionadas em código (PR review). |
| **Comparabilidade entre módulos** | Direta — todo mundo bate 9 dim mesma escala | **Quebrada por design** — players não tentam comparar API vs Library; comparam dentro do bucket |

### 2.2 Dimensões — análise honesta

| Dimensão atual | Faz sentido pra todos 34 módulos? | Sintoma de régua única |
|---|---|---|
| **D1 multi-tenant** (25pts) | SIM — Tier 0 IRREVOGÁVEL pra qualquer Module que toca dado. Único universal. | Ok |
| **D2 Pest cov** (17pts) | SIM em geral, mas Modules/Brief (cross-cutting consultivo) tem ~zero código de negócio | Brief precisa de testes diferentes (smoke do cron, não unit) |
| **D3 docs SPEC/RUNBOOK** (12pts) | SIM mas Modules/Connector (REST API) não precisa de RUNBOOK humano — precisa de OpenAPI spec | D3 cobra artefato errado pra alguns kinds |
| **D4 arquitetura + FSM** (17pts) | NÃO pra cross-cutting — daí ADR 0159 inventou `fsm_n_a` | Hack 1 do 0159 |
| **D5 cliente real** (12pts) | NÃO faz sentido pra Governance/Admin/TeamMcp — daí ADR 0159 inventou `internal_governance_active=15` | Hack 2 do 0159 |
| **D6 performance** (10pts) | SIM se módulo tem Inertia controller. NÃO se é Connector REST ou Brief cron | D6.a desperdiça medida em ~10 módulos |
| **D7 LGPD** (10pts) | SIM se módulo toca PII. NÃO se é Governance/Admin interno (não tem PII externa) | D7.b cobra LogsActivity em Models que não existem |
| **D8 security** (8pts) | SIM universalmente — auth/CSRF/throttle | Ok |
| **D9 observability** (7pts) | SIM mas D9.b ready-mode foi forçado a `true` default pra dar pontos (hack 3) | Hack 3 do 0159 |

**Diagnóstico honesto:** dos 9 critérios, **2 são realmente universais** (D1 multi-tenant + D8 security). Os outros 7 fariam mais sentido **per bucket**. O ADR 0159 confirma empiricamente — Wagner já está hackeando a régua única em 4 lugares pra acomodar a heterogeneidade dos 34 módulos.

### 2.3 Distância pro estado-da-arte

| Dimensão | Estado-da-arte | oimpresso hoje | Distância |
|---|---|---|---|
| Filtro/scope por kind | Port blueprint, Backstage `kind:`, Datadog `kind:service AND tier:` | Inexistente — todo Module bate mesma régua | **Longa** (precisa categorizar 34 módulos + criar 3-5 scorecards) |
| Pesos por categoria | AWS pillars per lens, OpsLevel rubric+scope | Pesos fixos D1-D9, hacks via escape no ADR 0159 | **Média** (Service já tem reweight logic, falta dim de "bucket") |
| Levels hierárquicos | Datadog L1/L2/L3, Port Basic/Bronze/Silver/Gold | Score 0-100 absoluto, sem nivelamento | **Média** (modelo de níveis é antitético ao "meta 97.75" atual — Wagner precisa decidir) |
| Score agregado entre rubricas diferentes | AWS: % best practices per lens, não agrega global. Datadog: por scorecard. | Tentativa de média global 97.75 forçando comparação injusta | **Longa** (mudança conceitual — abandonar meta única) |
| Anti-gaming | Port: níveis (mais difícil gamear). Versionamento em código (PR). | review_triggers no ADR (manual humano detecta) | **Curta** — gate CI atual já cobre regressão; falta gate de "bucket gaming" |
| Comparabilidade módulos heterogêneos | **Players desistem disso por design** | Tenta forçar via normalização /100 | Wagner precisa **abandonar essa premissa** |

**Onde oimpresso JÁ bate ou supera o mercado:**
- ✅ **Gate CI anti-regressão** com label de override (`module-grades-allowed-regression`) é mais maduro que Port/Cortex (que rastreiam tendência mas não bloqueiam merge nativo)
- ✅ **Auto-detect heurístico** via Service PHP (vs APIs SaaS) — não depende de vendor lock-in
- ✅ **Multi-tenant Tier 0 como D1=25%** é mais opinionated que qualquer player generalista (eles não sabem que oimpresso é multi-tenant brutal)

---

## 3. Avaliação — gaps rankeados (Fase 3)

| # | Gap | Impacto | Esforço IA-pair (ADR 0106) | Pré-req? |
|---|---|---|---|---|
| 1 | **Categorizar 34 módulos em 4-5 buckets** (Vertical/CrossCutting/Funcional/IA/Infra) via `module.json` campo `governance.bucket` | Alto — destrava todo o resto | ~30min (PR adicionando campo + decisão Wagner por módulo) | Wagner decide taxonomia |
| 2 | **Definir rubrica por bucket** — cada bucket tem subset de D1-D9 ativas + pesos próprios | Alto — resolve o problema raiz Wagner formulou | ~2h (4 YAMLs `governance/buckets/<bucket>.yaml` + ADR 0160) | Gap #1 |
| 3 | **Refatorar `ModuleGradeService`** pra carregar rubrica do bucket do módulo em vez de hardcoded D1-D9 fixos | Alto — substitui código atual | ~3h IA-pair (Service já tem reweight, virar plugin de YAML é mecânica) | Gap #1+#2 |
| 4 | **Abandonar meta global 97.75** — substituir por **meta por bucket** (ex: Verticais ≥85, Cross-cutting ≥90, IA ≥80) | Alto conceitual | ~10min (editar ADR 0159 supersede + novo ADR meta-per-bucket) | Wagner aprova mudança filosófica |
| 5 | **Adicionar dimensão D10 "Lens-specific"** por bucket (ex: Verticais ganham D10=Capterra-gap; IA ganha D10=Hallucination-rate) | Médio — diferencia competição real | ~1h por bucket (4-5 dimensões novas) | Gap #2 |
| 6 | **Anti-gaming bucket assignment** — gate CI bloqueia mudança de `governance.bucket` no module.json sem label `bucket-change-approved` | Médio — previne que módulo escolha bucket conveniente | ~30min (extender workflow `module-grades-gate.yml`) | Gap #1 |
| 7 | **Comparabilidade dentro do bucket** — dashboard mostra ranking só intra-bucket + percentile dentro do bucket | Baixo — UX | ~2h (dashboard Admin) | Gap #2-#3 |
| 8 | **Aposentar 3 dos 4 hacks do ADR 0159** (cross-cutting=15, fsm_n_a, CHANGELOG fresh ficam **redundantes** quando bucket próprio existe) | Médio — limpa débito | ~20min (ADR 0161 retira erratas conforme bucket cobre) | Gap #2-#3 |

### 3.1 Riscos catalogados (Wagner precisa saber)

| Risco | Vetor | Mitigação |
|---|---|---|
| **Gaming via bucket conveniente** | Módulo muda `governance.bucket: vertical` → `cross_cutting` pra fugir de D5 cliente real | Gap #6 (label gate) + review humano Wagner em PR de bucket-change |
| **Explosão de manutenção** | 4-5 rubricas × evolução cada → 5x mais ADRs/edits | Compartilhar D1+D8 universais entre todos buckets (não duplicar) — só varia D2-D7+D9 |
| **Comparabilidade global quebrada** | "Qual módulo é o melhor?" não tem resposta | **É O DESIGN** — players assumem isso. Wagner precisa abraçar. Reportar média **por bucket** + ranking intra-bucket. |
| **Confusão time MCP** (Maiara/Felipe/Luiz/Eliana) | "Em que bucket entra módulo X?" cada PR | Skill `assign-bucket` (Tier B) com decision tree determinístico |
| **Drift de bucket** | Módulo evolui (ex: Connector vira ConnectorPlus com cliente externo) sem revisar bucket | Trigger `review_triggers` no ADR 0160: quando módulo ganha cliente real, revisar bucket em 30d |
| **Inflação de meta** | Bucket próprio = mais fácil bater 100; Wagner volta a sentir "trofeu inflado" daqui 3 meses | Score Datadog-style L1/L2/L3 dentro do bucket — bater Level 3 é raro por design |

### 3.2 Casos análogos (ERP modular)

**Busca específica não retornou case de Laravel modular + nWidart**. Mas pattern **scoped scorecards aplicado a ERP modular** existe em:
- **Odoo Apps** (Python): cada app tem `__manifest__.py` declarando `category` (Accounting/Sales/HR/etc) — usado por loja oficial pra ranking interno por categoria, não por catálogo global.
- **Microsoft Dynamics 365** (cross-cutting LOB): módulos têm `Solution Component Type` com checklists próprios pra publicação no AppSource.
- **Salesforce Managed Packages**: Security Review tem checklist diferente por package type (Aura vs LWC vs Apex-only).

Conclusão: **oimpresso seria pioneiro aplicando o pattern em Laravel modular nWidart**, mas o pattern em si é maduro em ERPs proprietários grandes.

---

## 4. Recomendação concreta

### Comece pelo Gap #1 + #4 combinados (alto impacto, baixo esforço, sem pré-req bloqueante)

**Mínimo viável pra Wagner decidir ADR 0160:**

1. **Definir 4 buckets canônicos** (não 5+ — começar enxuto):

   | Bucket | Membros | Caráter | Score-alvo |
   |---|---|---|---|
   | `vertical_client_facing` | Vestuario, ComunicacaoVisual, OficinaAuto, Officeimpresso, Repair, Crm, Financeiro, RecurringBilling, NfeBrasil, NFSe | Vendável, cliente real, UX-driven | ≥85 |
   | `cross_cutting_infra` | Governance, Auditoria, Admin, Brief, TeamMcp, Superadmin, Connector, Mwart, Mcp | Infra interna, sem cliente vendável, depende-de-tudo | ≥90 |
   | `ai_central` | Jana, KB | LLM/embedding/RAG — dimensões próprias (hallucination, latência LLM, custo token) | ≥80 |
   | `functional_horizontal` | Ponto, ProductCatalogue, ProjectMgmt, Manufacturing, Cms, Spreadsheet, Arquivos, Accounting, AssetManagement, Essentials, ADS, ConsultaOs, SRS, Whatsapp, Woocommerce | Útil cross-vertical, sem dono cliente único | ≥80 |

2. **Decidir 1 dimensão universal compartilhada por todos buckets:** D1 multi-tenant (25pts) + D8 security (8pts) = **33pts core fixos**. Demais 67pts variam por bucket.

3. **Exemplo concreto — bucket `vertical_client_facing` (6 dims, peso próprio):**
   ```yaml
   bucket: vertical_client_facing
   inherits_core: [D1_multi_tenant, D8_security]  # 33pts
   dimensions:
     V1_pest_e2e: 15      # cliente real precisa de E2E, não só unit
     V2_d5_cliente: 15    # D5 ganha peso aqui (vs 12 hoje)
     V3_d6_perf: 12       # UX-crítico
     V4_d7_lgpd: 10       # CPF/CNPJ cliente real
     V5_d3_docs: 8        # SPEC + charter
     V6_capterra_gap: 7   # NOVO: gap vs concorrente top (skill capterra-senior)
   total: 100
   meta: 85
   ```

4. **Próxima ação hoje (Wagner pode aprovar):**
   - Wagner aprova taxonomia dos 4 buckets acima (ou ajusta)
   - Eu (próxima sessão) adiciono campo `governance.bucket` em `module.json` dos 34 módulos via 1 PR + ADR 0160 esqueleto
   - Wagner revisa PR — 1 review pra atribuir bucket de cada módulo
   - Service refactor (Gap #3) entra em PR separado depois que bucket estiver canon

**ROI estimado:** elimina 3 dos 4 hacks do ADR 0159 (que viraram redundantes), destrava meta realista por bucket (97.75 fica como meta agregada artificial só pra alguns buckets), responde a Wagner intuition empírica: "régua única estava errada".

**Custo se NÃO fizer:** Wagner vai continuar inventando escape valves (ADR 0159 já tem 4; próximo erra a ter 6, 8...) — cada uma é débito técnico no Service + dúvida sobre integridade da rubrica.

---

## 5. Riscos & não-recomendações

- ❌ **Não criar 7+ buckets** — comeca com 4. Cortex tentou granular demais e ficou inutilizável. Backstage começou com `kind: component|api|library|website` (4) e segue saudável.
- ❌ **Não abandonar gate CI atual** — `module-grades-gate.yml` é estado-da-arte vs Port/Cortex. Mantém. Só adapta pra ler rubrica do bucket do módulo.
- ❌ **Não tentar manter "meta única global 97.75"** depois de bucketizar — vira contradição lógica (médias ponderadas de escalas heterogêneas não fazem sentido estatístico).
- ❌ **Não fazer "Lens custom" (AWS-style) por enquanto** — overhead. Bucket é nível certo de granularidade pra oimpresso (34 módulos não justifica lens custom per módulo).

---

## 6. Sources (Fase 1, ordem de relevância)

- [AWS Well-Architected — Lens Catalog](https://docs.aws.amazon.com/wellarchitected/latest/userguide/lenses.html)
- [Datadog — Scorecard Configuration (Levels & Scopes)](https://docs.datadoghq.com/service_catalog/scorecards/scorecard_configuration/)
- [Datadog — Dogfooding Scorecards](https://www.datadoghq.com/blog/scorecards-dogfooding/)
- [Backstage Tech Insights — Scorecards per Component Kind](https://github.com/backstage/community-plugins/blob/main/workspaces/tech-insights/plugins/tech-insights/README.md)
- [Port — Scorecards Concepts & Structure (blueprint-based)](https://docs.port.io/scorecards/concepts-and-structure/)
- [Port — How to Use Scorecards (levels Basic→Gold + filters)](https://docs.port.io/promote-scorecards/usage/)
- [OpsLevel — How Scorecards Work: Flexible Model (positioning vs Cortex)](https://www.opslevel.com/resources/how-scorecards-work-in-opslevel-a-truly-flexible-model)
- [OpsLevel — Scoped Scorecards docs](https://docs.opslevel.com/docs/scorecards)
- [OpsLevel vs Cortex (case negativo régua única)](https://www.opslevel.com/resources/opslevel-vs-cortex-whats-the-best-internal-developer-portal)
- [ISO/IEC 25010:2023 — Product Quality Model (9 chars)](https://www.iso.org/standard/78176.html)
- [SonarQube — Quality Profiles (per language)](https://docs.sonarsource.com/sonarqube-server/quality-standards-administration/managing-quality-profiles/understanding-quality-profiles)
- [OpenSSF Scorecard — Weighted Aggregated Score (risk as weight)](https://github.com/ossf/scorecard)
- [CISQ Scorecarding (ISO/IEC 5055)](https://www.it-cisq.org/scorecarding/)
- [Roadie — Tech Insights GA announcement](https://devops.com/roadie-adds-scorecard-tool-to-backstage-saas-platform/)

---

**Fim do estado-da-arte.** Sem recomendação adicional além do §4. Wagner decide se aprova taxonomia dos 4 buckets ou ajusta.
