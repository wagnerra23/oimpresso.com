---
slug: 2026-05-17-arte-oimpresso-vs-melhores-2026
type: session-log
agent: estado-da-arte
date: 2026-05-17
module: Governance
tags: [estado-da-arte, governance-v4, scoped-scorecards, benchmark, dora, opensf, port-io, backstage, cortex, oimpresso-vs-mercado]
related_adrs: [0160, 0161, 0094, 0093]
pii: false
---

# Estado da arte — oimpresso (76.9/100 médio v3) vs os melhores ERPs/SaaS 2026

**Pergunta Wagner:** quem publica métricas auditáveis comparáveis às 9 dimensões da rubrica governance v3 do oimpresso, onde a gente supera o mercado, onde está atrás, e qual Wave 26 daria salto >5pts.

> **Disclaimer metodológico:** §1 foi pesquisado SEM tocar memory/ pra não contaminar. §2-4 cruzou com ADRs 0160/0161 + scorecards canon. Nenhum número de mercado é projeção minha — todos saem de relatório público nominado (DORA 2024, Cortex Wrapped 2025, Port Series C, OpenSSF BigQuery, OpsLevel cases).

---

## §1 Top 5 benchmarks reais de governance maturity 2025-2026

| # | Framework / player | Escala da rubrica | Número público auditável | O que mede |
|---|---|---|---|---|
| 1 | **DORA Report 2024** (Google Cloud + Accelerate) | 4 tiers (Low/Medium/High/Elite) → 2025 virou percentis (top 15% = ex-Elite) | **19% das equipes** ranqueadas Elite em 2024. Elite = deploy <1d, MTTR <1h, change failure rate ≤5%, lead time <24h. Gap elite→low: 182× deploys, 2.293× MTTR | 4 métricas de delivery (deployment freq, lead time, change failure rate, MTTR/FDRT) |
| 2 | **OpenSSF Scorecard** (Linux Foundation) | 0-10 por check, 18 checks, agregado 0-10 | **Média 5.4/10** num corpus de **1 milhão** dos OSS mais críticos (BigQuery `openssf:scorecardcron.scorecard-v2_latest`, semanal). Correlação +1pt a cada 100× stars GitHub | Security health: branch protection, dependency-update-tool, SAST, signed releases, code review, etc |
| 3 | **Spotify Backstage Soundcheck / Tech Health** | Bronze / Silver / Gold (tracks × levels × certs) | **3.400+ adoções CNCF**, **89% market share** entre orgs que escolheram IDP. Spotify interno: **99% adoção voluntária**. Mas adoption real fora do Spotify: **~10% de plugins ativados** | Production-readiness, security, testing, reliability, ops standards |
| 4 | **Cortex Wrapped 2025** (multi-customer agregado) | Scorecards segmentados por persona (Eng Mgr / Platform / SRE) | **1.285 scorecards ativos** (62% conversão draft→live), **+64% deploy frequency YoY**, **+20% PRs/dev**, **36.581 workflows automatizados executados** | Maturity por componente, mapeado a personas |
| 5 | **Port.io Engineering 360** (Series C $100M dez/25, valuation $800M) | Scorecards + Initiatives + bucket-by-type | "Sharp decrease in services not production-ready over consecutive quarters" (template). Granular: 100% on-call defined, 67% domain configured (Ecosystem team específico) | Production readiness, ownership, compliance — score-as-code declarativo |

**Bônus — SonarQube SQALE/Maintainability:** rating A = technical debt ratio ≤5%. Distribuição enterprise pública **NÃO existe**; SonarSource não publica agregado (busca exaustiva confirma — só doc técnica do cálculo).

**Bônus — GitLab SaaS:** publica SOC 2 Type II (Trust Center 2025) + ISO 27001/27017/27018, mas **não publica score numérico tipo "85/100 maturity"**. Compliance é binário (passou audit / não passou).

**Veredito §1:** nenhum benchmark exatamente comparável aos 9 D's do oimpresso existe. **O mercado mediu DELIVERY (DORA) e SECURITY (OpenSSF) com agregados grandes; mediu MATURITY DE COMPONENTE (Cortex/Backstage/Port) com tiers bronze-prata-ouro, não com 0-100.** A rubrica oimpresso v3/v4 com 9 dimensões × 100pts × 4 buckets é **mais granular que o público** — mais perto de um IDP custom interno (Spotify-style) que de framework de mercado.

---

## §2 Onde oimpresso REALMENTE supera (3 diferenciais defensáveis)

### 2.1 Multi-tenant Tier 0 IRREVOGÁVEL como bloqueador de CI (D1=25pts, universal)
- **Mercado:** SOC 2 e LGPD pedem "logical separation" como controle, sem enforcement automático. GitLab Trust Center prova auditoria anual; nenhum ERP BR (Omie, Bling, Conta Azul, Nibo) publica como Tier 0 com gate técnico.
- **oimpresso:** [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) + Pest cross-tenant biz=1 vs biz=99 + global scope obrigatório + hook bloqueando `withoutGlobalScopes` sem comentário. **Rubrica reserva 25/100pts pra isso em TODO módulo** — quem não tem, não passa do bucket meta.
- **Why this wins:** SaaS mediano trata multi-tenant como prática; oimpresso trata como invariante constitucional. Ninguém do top 5 §1 codifica multi-tenant como dimensão de scorecard com peso 25%.

### 2.2 Append-only legal-by-design (Portaria MTP 671/2021) + LGPD opt-in granular
- **Mercado:** ERPs BR atendem LGPD na superfície (audit log + senha forte). DORA/OpenSSF/Backstage **não medem** conformidade legal de domínio.
- **oimpresso:** triggers MySQL imutabilidade em `ponto_marcacoes` + `Marcacao::anular()` em vez de UPDATE/DELETE + `Contact::canReceiveEmailNotification()` checagem opt-in pré-dispatch + `PiiRedactor` em logs + CI bloqueia PR com PII real.
- **Why this wins:** isso é vantagem regulatória, não técnica. Concorrente que decidir copiar gasta 6-12 meses pra implementar e validar com counsel BR. Codificado nas proibições Tier 0 já.

### 2.3 Score-as-code + Paired Indicators (anti-gaming Jellyfish 2025) + 4 buckets
- **Mercado:** Port/Cortex/Backstage têm scorecard tier (bronze/silver/gold) mas **score único** por componente. Jellyfish publicou paired indicators em 2025 como pesquisa; ninguém canonizou em produto.
- **oimpresso:** [ADR 0160](../decisions/0160-governance-v4-scoped-scorecards-buckets.md) já tem `memory/scorecards/{vestuario,governance,jana,functional_horizontal}.yaml` com paired indicators (velocidade `cap-eada` por par de qualidade) + 4 buckets de meta diferenciada + drift cron.
- **Why this wins:** o que o mercado tem em paper, oimpresso tem em YAML versionado executando. **Mas é vantagem frágil** — Port em 6 meses copia, tem $158M caixa pra isso.

**Honestidade:** o diferencial #3 é estrutural mas atacável. #1 e #2 são moats reais (regulatórios + arquiteturais).

---

## §3 Onde oimpresso está atrás (3 gaps reais e quantificáveis)

### 3.1 D6 Performance + D9.b Observability sem OTel collector ativo prod
- **Mercado top 5 §1:** todos têm OTel ou equivalente. Cortex/Port leem telemetria via integração nativa. DORA exige métricas de delivery REAIS, não declaradas.
- **oimpresso:** `governance.yaml` linha 421 explicita *"precisa todas paired indicators verdes + OTel collector ativo prod CT 100 (popula mcp_observability)"* — placeholder. p99 declarado `<2000ms`, mas SEM evidência coletada. D6.b + D9.b atualmente carregam estimativa, não medição.
- **Tamanho do gap:** 2 dimensões × até ~6-8 pts cada = **12-16 pts potenciais bloqueados** por essa única ausência. É o gap mais barato de fechar com maior swing.

### 3.2 Cliente único biz=4 (ROTA LIVRE) vs SaaS multi-tenant maduro
- **Mercado:** Port escala 100s clientes pagantes Series C. Omie atende 200k+ PMEs BR. Spotify Backstage 99% adoção interna = milhares de times.
- **oimpresso:** ROTA LIVRE 99% volume; D5 Cliente Real só "verde" em Vestuario+Repair+Financeiro. Outros módulos têm sinal nulo (ADR 0105 ativa — não vira drift de governance, mas é fato real).
- **Tamanho do gap:** não é técnico, é **comercial/temporal**. ComVis e OficinaAuto destravam ao entrar primeiros clientes pagos. Não tem Wave que resolve isso — só venda.

### 3.3 DORA metrics formais (deploy freq / lead time / MTTR / change failure rate) — não medidos
- **Mercado:** ÚNICA métrica de delivery com benchmark global publicado (DORA 2024 = 19% Elite).
- **oimpresso:** existe `dashboard-velocity` / `dashboard-burndown` MCP tools (story points cycle), **mas zero** de deploy frequency / MTTR / change failure rate / lead time. Hostinger deploy é git pull manual — não há trigger automatizável que conte.
- **Tamanho do gap:** 4 métricas DORA somariam ~uma sub-dimensão em D9 ou D6. **Não destrava muito ponto na rubrica atual** (rubrica não pesa DORA), mas **é o único vocabulário que o mercado entende** — sem isso, comparação externa fica difícil de defender.

---

## §4 Wave 26 — recomendação cirúrgica (1 ação, salto >5pts média)

### Ação recomendada: **OTel Collector ativo prod CT 100 + popular `mcp_observability` table real**

**Por quê:**

| Critério | Avaliação |
|---|---|
| Impacto na rubrica | **Alto** — destrava D6.b (perf medida) + D9.b (obs ready) em 34 módulos. Estimativa: +4 a +8pts em ~15 módulos, +2 a +4pts em ~19. **Média projetada: 76.9 → 82-84** |
| Esforço IA-pair (ADR 0106 10× humano) | **Baixo-médio** — OpenTelemetry PHP SDK + autoload Laravel é stack documentada. Composer install + ServiceProvider + collector docker no CT 100 (já existe FrankenPHP + Centrifugo lá) + Tempo/Jaeger backend. Estimativa: **6-10h IA-pair** pra V0 funcional ler 3-5 traces de Modules/Jana e Modules/Repair |
| Pré-requisitos bloqueantes | **Nenhum** — CT 100 já tem container infrastructure (ADR 0058 Centrifugo+FrankenPHP). Hostinger fica de fora (ADR 0062 separação runtime) — sampling 5-10% lá não é regressão |
| Diferencial defensável vs mercado | **Médio** — equipara ao tier base de quem tem APM, não cria moat. Mas remove "placeholder" das casas D6.b/D9.b — vira evidência auditável |
| Risco | **Baixo** — instrumentação read-only; sampling 1-3% overhead (Last9 PHP APM 2025 benchmark) |

**O que NÃO recomendo agora:**

- ❌ **DORA dashboard** — gap real (§3.3), mas sem CD automatizado o deploy frequency é manual = manipulável = inútil pra benchmark. Só faz sentido pós-pipeline CI/CD real.
- ❌ **CodeClimate-like coverage** — Pest cobertura já é coletada; integrar terceiro custa caro e dá <2pts na rubrica.
- ❌ **SLO budget tracking** — depende de OTel já ativo. É Wave 27, não 26.
- ❌ **Copiar Port "Initiatives"** — Port tem $158M de capital fresh; tentar imitar produto deles é distração. ScopedScorecards v4 já cobre o essencial.

### Próxima ação concreta hoje:

1. Criar branch `claude/wave-26-otel-collector-ct100`
2. ADR `0162-otel-collector-prod-observability.md` proposta (não-aceita ainda) — escopo: PHP SDK autoload + collector contrib + Tempo backend + 3 services iniciais (Jana, Repair, Sells) + sampling 5%
3. Validar com Wagner: rota tipo `otel/tempo` vai pra CT 100 ou Hostinger sub-rota? (default proposto: CT 100 only, conforme ADR 0062)
4. Skip Hostinger app server até validação CT 100 + 7 dias soak

---

## §5 Sources

### Benchmarks numéricos citados (Fase 1 — pesquisa limpa)
- [DORA Report 2024 — Throughput and Stability (RedMonk analysis)](https://redmonk.com/rstephens/2024/11/26/dora2024/) — 19% Elite, 182× delta
- [DORA Metrics: Octopus Deploy 2024/25 takeaways](https://octopus.com/devops/metrics/dora-metrics/) — Elite tier numbers
- [OpenSSF Scorecard official](https://scorecard.dev/) — 18 checks, 3 themes
- [OpenSSF Scorecard GitHub repo](https://github.com/ossf/scorecard) — BigQuery `openssf:scorecardcron.scorecard-v2_latest`, weekly 1M projects, avg 5.4/10
- [Spotify Backstage Soundcheck plugin](https://backstage.spotify.com/docs//plugins/soundcheck) — bronze/silver/gold tracks×levels
- [DX blog: 2024 DORA report highlights](https://getdx.com/blog/2024-dora-report/)
- [Port Plugin Framework for Backstage — Scorecards](https://docs.backstage-plugin.port.io/features/scorecards)
- [Cortex.io Engineering Maturity Curve](https://www.cortex.io/post/cortex-engineering-maturity-curve)
- [GitLab Trust Center](https://trust.gitlab.com/) — SOC 2 Type II + ISO 27001/27017/27018 2025
- [GitLab SOC 2 Type II expansion 2025](https://about.gitlab.com/blog/how-gitlab-successfully-expanded-our-soc-2-type-ii-trust-services-report-criteria/)
- [SonarQube Server 2025.1 LTA metric definitions](https://docs.sonarsource.com/sonarqube-server/2025.1/user-guide/code-metrics/metrics-definition) — SQALE A = ≤5% debt
- [CNCF Automated Governance Maturity Model — announcement 2025](https://www.cncf.io/blog/2025/05/05/announcing-the-automated-governance-maturity-model/) — Policy/Evaluation/Enforcement/Audit
- [Last9 — 15 PHP APM Tools 2025](https://last9.io/blog/best-php-apm-tools/) — OTel PHP 1-3% overhead
- [base14 Scout — Laravel OpenTelemetry instrumentation](https://docs.base14.io/instrument/apps/auto-instrumentation/laravel/)
- [Brazil LGPD SaaS compliance guide — Complydog](https://complydog.com/blog/brazil-lgpd-complete-data-protection-compliance-guide-saas)

### Cross-reference oimpresso (Fase 2)
- [ADR 0160 — Scoped Scorecards v4 (4 buckets)](../decisions/0160-governance-v4-scoped-scorecards-buckets.md)
- [ADR 0161 — Aposentar hacks 0159 redundantes](../decisions/0161-governance-v4-aposentar-hacks-0159-redundantes.md)
- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../decisions/0093-multi-tenant-isolation-tier-0.md)
- `memory/scorecards/{governance,vestuario,jana,functional_horizontal}.yaml` — score-as-code canônico
- Sessão anterior [`2026-05-16-arte-scorecards-alta-2026-benchmark.md`](2026-05-16-arte-scorecards-alta-2026-benchmark.md) — Port/Cortex/OpsLevel cases
- Sessão anterior [`2026-05-16-arte-domain-specific-scorecards.md`](2026-05-16-arte-domain-specific-scorecards.md) — origem da régua v4
