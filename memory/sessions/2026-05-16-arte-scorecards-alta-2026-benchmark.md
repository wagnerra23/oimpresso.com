# Estado da arte — Scoped Scorecards em 2026: quem está em ALTA e quem entrega DESEMPENHO REAL

**Data:** 2026-05-16
**Agent:** `estado-da-arte`
**Continua sessão anterior:** identificou 5 players (AWS Lenses / Datadog / Backstage TechInsights / Port / OpsLevel) + Cortex (case ambíguo).
**Pergunta Wagner:** qual técnica está em alta agora + quem entregou número real + tendência 2026 emergente + recomendação cirúrgica oimpresso.

---

## §1 Top 3 técnicas em ALTA em 2026 (sinais concretos, não opinião)

| # | Player | Sinal de alta concreto | O que isso significa |
|---|---|---|---|
| **1** | **Port.io** | **US$100M Series C dez/2025 a US$800M valuation** (lead: General Atlantic; Accel, Bessemer, Team8 participam). Total levantado: US$158M. Demo no palco BackstageCon KubeCon EU 2026: portal inteiro em **5 minutos** | Capital fresco + posicionamento "agentic AI hub" — está caçando Backstage. Scorecards + Initiatives são produto core, não plugin |
| **2** | **Backstage TechInsights / Soundcheck** (Spotify) | **3.400+ adopters CNCF** globalmente, **89% market share** entre orgs que escolheram um IDP. Spotify lançou modelo de assinatura paga de plugins pela primeira vez em 2025 | Dominante por inércia + ecossistema. Mas dados de campo: **adoption real média ~10%** nas orgs que tentam (Spotify mesmo reporta 99% interno — sinal de DIY-not-for-everyone) |
| **3** | **Cortex.io** | **Cortex Wrapped 2025**: 2.075 scorecards criados, 1.285 (62%) saíram do draft pra uso ativo. **25.109 queries Cortex MCP** desde julho/2025 (AI on developer portal). Gartner Market Guide 2025: Representative Vendor | Não é o que mais cresce em capital, mas o que mais publica DADOS de uso. Aposta forte em MCP/AI nativa |

**Quem está esfriando ou plateau:**

- **OpsLevel** — só US$20M total (Series A 2022 ainda), zero novo round em 2025-2026. Produto sólido, cases reais (abaixo), mas sem turbo de capital.
- **OpenSSF Scorecard** (OSS) — 5.1k GitHub stars, plateau. Útil pra security health de OSS específico, **não substitui** scorecard de plataforma interna.
- **Cortex como empresa** — Gartner manteve só como "Representative Vendor" (não Leader) em 2025; sem nova rodada anunciada; sinais mistos em Glassdoor (sessão anterior já marcou como case ambíguo).

**Veredito §1:** **Port.io está claramente comprando o mercado.** Backstage tem o footprint, Cortex tem os dados, mas o capital + narrativa agentic AI do Port é o que vai dominar 2026-2027.

---

## §2 Champions de DESEMPENHO REAL (números auditáveis, não marketing genérico)

### Case 1 — OpsLevel @ logistics tech (anônimo, publicado pela OpsLevel)
- **Métrica:** adoção Snyk coverage **22% → 89%** entre times
- **Mecanismo:** Scorecards + visibilidade + nudges Slack automáticos
- **Tempo:** não especificado (provavelmente 1-2 quarters)
- **Auditabilidade:** ⚠️ média — case study oficial, sem nome de cliente

### Case 2 — OpsLevel @ mid-market SaaS (anônimo)
- **Métrica:** compliance standards (alerting, SLO, on-call) **40% → 85%**
- **Tempo:** **8 semanas**
- **Auditabilidade:** ⚠️ média — número específico + janela apertada = crível, mas anônimo

### Case 3 — Cortex Wrapped 2025 (agregado, multi-customer)
- **Deploy frequency: +64%** ano sobre ano nos clientes Cortex
- **PRs por dev: +20%**
- **Scorecards ativos: 1.285** (62% conversão draft → active)
- **Workflows automatizados executados: 36.581** em 2025
- **Auditabilidade:** ✅ alta — Cortex publica anualmente "Wrapped" estilo Spotify, agregado real de clientes

### Case 4 — Spotify (interno) com Backstage Soundcheck/TechInsights
- **Métrica:** **99% portal adoption rate voluntário** (sem mandato)
- **Auditabilidade:** ✅ alta — Spotify é a casa que construiu Backstage. **Caveat enorme**: cultura DIY + 10 anos investindo = não replicável fora; benchmark de TETO, não de chão.

### Case 5 — Port.io @ Engineering 360 dashboards (multi-customer)
- **Métrica narrativa:** "sharp decrease in services not production-ready over consecutive quarters" (Port docs)
- **Métrica granular publicada:** "100% on-call defined, 67% domain configured" (Ecosystem team específico)
- **Auditabilidade:** 🟡 baixa-média — Port publica padrão/template mais que case fechado com cliente nominal

### O que NÃO encontrei (apesar de procurar)
- ❌ Caso público com cliente nominal (tipo "Cisco com OpsLevel atingiu 97% maturity em X meses"). Os 3 nomes-âncora da pergunta (Cisco/Plaid/Roblox) não retornam case study oficial com OpsLevel. Sessão anterior provavelmente especulou ou viu logo em homepage genérica.
- ❌ Meta 95%+ atingida em ERP modular publicamente documentada. Mercado IDP é dominado por SaaS-platform/microservices, não ERP vertical.

**Veredito §2:** **Cortex Wrapped 2025 é o dado mais sólido publicamente.** +64% deploy frequency é número de mercado real (não 1 cliente cherry-picked). OpsLevel publica os deltas mais brutais (22→89%, 40→85%) mas cliente anônimo. Spotify 99% é teto cultural — **não plagiar como meta inicial**.

---

## §3 Tendência 2026 emergente — pra onde está indo

### Tendência #1 — **AI-driven scorecards (LLM gera/atualiza score lendo código + telemetria)** 🔥 EMERGINDO RÁPIDO

Sinais 2025-2026:
- **Cortex MCP:** 25.109 queries em ~6 meses (jul-dez 2025). Devs perguntam ao Cortex AI em vez de clicar dashboard.
- **Port "Define scorecards with AI" + MCP guide** já documentado: usuário descreve em linguagem natural, Port gera scorecard rule.
- **Braintrust** (LLM eval space, paralelo): evals automáticas a cada commit comparando contra baseline + bloqueando merge se qualidade cair. Mesma técnica vai cruzar pra scorecards de plataforma.
- **Port narrativa Series C ($100M):** capital alocado pra virar "agentic AI hub" — scorecards lidos/atualizados por agentes, não humanos.

Status: **fase early adopter**, mas **com capital pesado por trás**. Em 12-18 meses (até meados 2027) será mesa-de-jantar.

### Tendência #2 — **Score-as-code (YAML/JSON em git, GitOps)** ✅ JÁ COMMODITY

- Cortex (`.cortex/scorecards/*.yaml`), Port (GitOps via repo conectado), Harness IDP, todos suportam.
- Não é mais diferencial — é higiene mínima. Quem não tem, está atrás.
- **Implicação pro oimpresso:** se for construir, **nasça em YAML versionado dia 1**. Custo zero, ganho composto.

### Tendência #3 — **Drift detection cross-realidade automático** 🟡 EMERGINDO

- SmartBear Swagger 2026: contract testing com drift detection contínuo (API behavior vs OpenAPI spec).
- Arize/MLOps tooling: drift detection padrão.
- Cortex/Port ainda emitindo "scorecard scores", **não** "scorecard score vs SHA real do código" — gap.
- Em 2026-2027 vai virar feature padrão: scorecard reconhece quando o código divergiu do que ela mede e auto-alerta.

### Tendência #4 — **DORA 5 (CFR adicionado oficialmente out/2025)** ✅ JÁ ESTABILIZOU

- CD Foundation oficializou 5ª métrica (Reliability/Operational Performance) em outubro 2025.
- DORA largou bucket "Elite" — agora é percentile distribution (top 15% etc).
- Scorecards modernos puxam DORA 5 nativamente — mas é OUTPUT, não FRAMEWORK de scorecard em si.

### O que **NÃO** está acontecendo apesar do buzz
- ❌ "Vibe Score" ou "AI Maturity Index" como produto consolidado — só posts de blog, sem player dominante.
- ❌ Backstage virando IA-native — comunidade reportada como "work in progress" (TechTarget 2025), Spotify investe mais em Portal SaaS que em revigorar OSS core.

---

## §4 Recomendação pro oimpresso — pick ONE, não 5

### Cenário e restrição

Oimpresso ≠ B2B SaaS com 200 microservices. É **ERP modular vertical Laravel** com:
- 12-15 módulos (`Modules/<Vertical>` + núcleo)
- 1 cliente piloto real validando (ROTA LIVRE, biz=4)
- Time 5 pessoas
- Sem tração de capital pra comprar Port/OpsLevel ($$$)
- Pré-existente: `php artisan jana:health-check` com 5 checks SQL ([ADR 0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §5) — **é o embrião primitivo de scorecard que já existe**

### Recomendação: copiar **Cortex score-as-code + Port initiatives pattern**, NÃO comprar ferramenta externa

**Mecanismo concreto:**

1. **`memory/scorecards/<modulo>.yaml`** — 1 arquivo YAML por módulo (`Vestuario.yaml`, `Financeiro.yaml`, `Repair.yaml`, etc), score-as-code estilo Cortex. Versionado em git, PR review obrigatório pra mudar rule.
2. **`php artisan oimpresso:scorecard <modulo>`** estende `jana:health-check` — lê YAML, executa checks SQL/AST/regex, gera score 0-100 + breakdown por dimensão.
3. **4 buckets canônicos** (herdam do CAPTERRA-FICHA pattern já existente do `capterra-senior`): P0 multi-tenant, P1 UX/charter, P2 testes/cobertura, P3 docs/BRIEFING atualizado.
4. **Cron daily 07h BRT** gera snapshot em `mcp_scorecard_runs` (timestamp + sha + score). Drift = diff entre snapshots ao longo do tempo.
5. **Inicial deliberadamente SEM AI** — score-as-code primeiro. AI vem em V2 quando tiver 30 dias de baseline pra LLM avaliar (anti-Goodhart: meta inicial é MEDIR, não otimizar).
6. **Anti-gaming desde dia 1:** paired indicators no YAML (toda métrica de velocidade exige métrica de qualidade pareada — pattern Jellyfish 2025).

**O que NÃO fazer (calibração baseada no que o mercado entregou):**
- ❌ Mirar "99% maturity" inicial — só Spotify atingiu, com 10 anos + cultura DIY. Calibre meta inicial em **70% baseline + ganho composto trimestral** (mais alinhado com case OpsLevel logistics: 22→89% em ~2 quarters).
- ❌ Adotar Backstage só pra ter — 89% market share esconde 10% adoption real médio. Custo de operação > valor pra time de 5.
- ❌ Esperar Port/Cortex barato — pricing escala por user/seat; pra ERP modular vertical é overhead.
- ❌ AI-driven scorecard direto — sem 30 dias de baseline, LLM vai alucinar score.

### Próxima ação concreta pra HOJE (Wagner aprovando):

**Criar 1 arquivo experimental: `memory/scorecards/vestuario.yaml`** (módulo em prod, melhor calibração possível). 4 dimensões × 3-5 rules cada (~15 rules total). 1h de trabalho IA-pair. Output: YAML + saída esperada do score se rodasse hoje contra biz=4. Sem implementar comando artisan ainda — valida o **formato** primeiro, evita parecer Cortex e descobrir que oimpresso precisa de campos custom.

Se Wagner aprovar o YAML em 1 sessão de review, segue pra `php artisan oimpresso:scorecard` no PR seguinte.

---

## §5 Sources

### Funding / Market signals
- [Port nets $100M to turn its developer portal into an agentic AI hub — SiliconANGLE (dez/2025)](https://siliconangle.com/2025/12/11/port-nets-100m-turn-developer-portal-agentic-ai-hub/)
- [Port raises $100M at $800M valuation to take on Spotify's Backstage — TechCrunch (dez/2025)](https://techcrunch.com/2025/12/11/port-raises-100m-at-800m-valuation-to-take-on-spotifys-backstage/)
- [OpsLevel Crunchbase profile (Series A $15M, total $20M)](https://www.crunchbase.com/organization/opslevel)
- [Cortex recognized as Representative Vendor in 2025 Gartner Market Guide for IDPs](https://www.cortex.io/post/cortex-recognized-again-as-a-representative-vendor-in-the-2025-gartner-market-guide-for-internal-developer-portals)

### Case studies / números reais
- [Cortex Wrapped 2025: The Year of AI Excellence (2.075 scorecards, +64% deploy freq, 25.109 MCP queries)](https://www.cortex.io/post/cortex-wrapped-2025-the-year-of-ai-excellence)
- [OpsLevel best practices for internal developer portals (cases 22→89% Snyk, 40→85% compliance em 8wk)](https://www.opslevel.com/resources/best-practices-for-internal-developer-portals-how-to-build-one-that-scales)
- [Spotify Backstage 99% adoption + market 10% médio — TechTarget](https://www.techtarget.com/searchitoperations/news/366558592/Behind-the-scenes-Spotify-Backstage-a-work-in-progress)
- [State of Internal Developer Portals 2025 — Port](https://www.port.io/state-of-internal-developer-portals)
- [Backstage vs Port 2026 — Luca Berton (89% market share Backstage)](https://lucaberton.com/blog/backstage-vs-port-2026/)
- [Top 4 Backstage Alternatives for 2025 — Port (adoption 10% médio, Gartner 2025)](https://www.port.io/blog/top-backstage-alternatives)

### Tendência AI-driven + score-as-code
- [Define scorecards with AI — Port docs (MCP integration)](https://docs.port.io/guides/all/build-port-scorecards-with-mcp/)
- [Scorecards as code — Cortex docs](https://docs.cortex.io/standardize/scorecards/scorecards-as-code)
- [Why Scorecards are critical to your developer portal — Cortex](https://www.cortex.io/post/why-scorecards-are-critical-to-your-developer-portal)
- [What KubeCon EU 2026 tells about the state of AI and Platform Engineering — Port](https://www.port.io/blog/what-kubecon-eu-2026-tells-about-the-state-of-ai-and-platform-engineering)
- [SmartBear Swagger update targets API drift problem — The New Stack](https://thenewstack.io/smartbear-swagger-ai-api-management/)
- [DORA 4 metrics become 5 (CFR oficial out/2025) — CD Foundation](https://cd.foundation/blog/2025/10/16/dora-5-metrics/)

### Anti-gaming
- [Goodhart's Law in Software Engineering — Jellyfish (paired indicators)](https://jellyfish.co/blog/goodharts-law-in-software-engineering-and-how-to-avoid-gaming-your-metrics/)
- [Gaming the System: Goodhart's Law in AI Leaderboard Controversy](https://blog.collinear.ai/p/gaming-the-system-goodharts-law-exemplified-in-ai-leaderboard-controversy)

### Context
- [OpenSSF Scorecard GitHub (5.1k stars)](https://github.com/ossf/scorecard)
- [Service scorecard examples — DX getdx.com](https://getdx.com/blog/service-scorecard-operational-maturity-models/)
- [Tech Insights — Spotify for Backstage docs](https://backstage.spotify.com/docs/plugins/soundcheck/core-concepts/tech-insights)
