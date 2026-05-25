---
slug: 0191-microsoft-clarity-session-replay-lgpd
number: 191
title: "Microsoft Clarity como ferramenta canon de session replay + heatmap LGPD-compliant — 1 projeto global + custom tag business_id"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-25"
accepted_at: "2026-05-25"
accepted_via: "Wagner aprovou em sessão `frosty-greider-83ab2f` 2026-05-25 — comando exato: 'Aceito — marca aceito + commit + começa PR 1 (banner)'. Decisão precedida por 4 escolhas explícitas via AskUserQuestion: (1) multi-tenant = 1 projeto + custom tag business_id, (2) consent banner = PR separado prerequisito, (3) mask PII = mask all default + unmask seguro, (4) carregar Clarity = só após autenticar."
module: null
quarter: 2026-Q2
tags: [observability, ux-analytics, lgpd, session-replay, heatmap, microsoft-clarity, multi-tenant, consent]
supersedes: []
supersedes_partially: []
superseded_by: []
related: ["0061-conhecimento-canonico-git-mcp-zero-automem", "0062-separacao-runtime-hostinger-ct100", "0093-multi-tenant-isolation-tier-0", "0094-constituicao-v2-7-camadas-8-principios"]
pii: true
review_triggers:
  - Larissa biz=4 reportar invasão de privacidade após ver session replay
  - ANPD publicar regulação específica sobre session replay tools
  - Microsoft Clarity mudar política de retenção/processamento de dados PII
  - Custo de retenção subir (hoje free unlimited — se virar pago, repensar)
  - Métrica de adoção interna < 1 acesso/semana ao dashboard Clarity em 90d
---

# ADR 0191 — Microsoft Clarity como ferramenta canon de session replay + heatmap LGPD-compliant

## Contexto

Sessão 2026-05-25 (`frosty-greider-83ab2f`). Pesquisa de ferramentas IA-UX 2026 mapeou:

| Tier | Ferramenta | Trade-off |
|---|---|---|
| 1 | Baymard UX-Ray 2.0 | 95% precisão, 207 heurísticas — caro (enterprise) |
| 2 | **Microsoft Clarity** | Free unlimited, IA built-in (Smart Alerts, rage clicks, dead clicks, quick backs) |
| 2 | UserZoom 2026 | ML detecta padrões — caro |
| 2 | Hotjar/VWO/Smartlook | Camada IA 2025-2026 — pago |

Oimpresso hoje (auditoria sessão 2026-05-25):
- **Zero analytics/heatmap/session-replay instalado** (só `recaptcha` no login)
- **Zero cookie consent banner** (violação latente LGPD art. 7º/8º se instalar tracking)
- 200 clientes ativos, multi-tenant `business_id` ([ADR 0093](0093-multi-tenant-isolation-tier-0.md))
- Wagner sem dados comportamentais reais → decisões UX baseadas em intuição
- Dossier: [`memory/sessions/2026-05-25-como-integrar-microsoft-clarity.md`](../sessions/2026-05-25-como-integrar-microsoft-clarity.md)

Necessidade: instrumentação comportamental real (rage clicks em Sells/Create? dead clicks em PageHeaderTabs novos? quick backs em fluxos canon recém-mergeados?) sem custo e sem violar Tier 0 multi-tenant ou LGPD.

## Decisão

**Microsoft Clarity é a ferramenta canon de session replay + heatmap + behavioral analytics do oimpresso**, com 5 regras Tier 0:

1. **1 projeto global Microsoft Clarity + custom tag `business_id`** (não 200 projetos):
   - `clarity('set', 'business_id', $bizId)` em todo pageview autenticado
   - Wagner filtra biz no dashboard nativo Clarity
   - Tags adicionais sugeridas: `user_type`, `module`, `pricing_plan`

2. **Cookie consent banner LGPD vai em PR SEPARADO PREREQUISITO** (não junto):
   - Banner cumpre LGPD art. 7º/8º (opt-in explícito antes de tracking)
   - Clarity só carrega após `consent === true` na session
   - Sem banner aceito → snippet Clarity NÃO inicializa

3. **Mascaramento PII = "mask all default + unmask seguro"**:
   - Clarity inicializa em modo `mask all text inputs`
   - `data-clarity-unmask` em campos comprovadamente não-sensíveis (qtd produto, nome produto público, etc)
   - Default-safe: campo novo nasce mascarado, vazamento PII só por escolha explícita
   - Inverte ônus: errar pra menos = perde insight; errar pra mais = vaza CPF/CNPJ/telefone na Azure

4. **Snippet só carrega após autenticar** (Auth::check() === true):
   - Login/cadastro público fica fora (sem `business_id` tag faria session órfã)
   - Foco: comportamento DENTRO do UltimatePOS (núcleo do produto)
   - Trade-off: perde funil de cadastro/login — aceito (não é gargalo conhecido)

5. **Filtra superadmin + user_oimpresso** (não polui dataset):
   - `if (auth()->user()->user_type in ['superadmin', 'user_oimpresso']) return;`
   - Wagner debugando, suporte interno, demo: NÃO gravar
   - Dataset Clarity = só comportamento real de cliente pagante

## Justificativa

**Por que Microsoft Clarity e não Hotjar/VWO/Smartlook/Baymard:**

1. **Custo zero unlimited** — 200 clientes × sessões ilimitadas = $0/mês. Hotjar/VWO/Smartlook caem em tier pago rápido com 200 sites
2. **IA built-in** — Smart Alerts (rage clicks/dead clicks/quick backs/excessive scrolling) sem config extra
3. **Microsoft enterprise compliance** — GDPR/CCPA/SOC2/HIPAA cobertos; LGPD se trata como GDPR equivalente
4. **Integração JS pura client-side** — não toca Hostinger backend ([ADR 0062](0062-separacao-runtime-hostinger-ct100.md) preservado)
5. **Não compete com observability técnica** — OTel GenAI (custos LLM), Centrifugo (realtime), Pest (test), Horizon (queues) continuam canon. Clarity é UX layer separado

**Por que 1 projeto global + custom tag e não 200 projetos:**

- 200 projetos = 200 dashboards = inviável operacionalmente
- Custom tag `business_id` permite filtro nativo no dashboard único
- Microsoft Clarity oficialmente suporta esse pattern (multi-tenant SaaS é caso de uso documentado)
- Wagner ainda enxerga "biz=4 tem rage click no PageHeader" filtrando

**Por que consent banner PR separado:**

- 1 PR = 1 intent (skill `commit-discipline` Tier A)
- Banner é prerequisito legal — sem ele, Clarity é violação latente
- Banner serve a OUTRAS necessidades futuras (Google Analytics? Pixel Facebook se Wagner decidir marketing? cookies de preferência?)
- PR Clarity fica enxuto (~150 linhas) — facilita review

**Por que mask-all default e não mask-nada-+-mascarar-PII:**

- Mask-nada + mascarar manual = checklist de 7+ arquivos hoje, mas QUALQUER form novo nasce vazando até alguém lembrar
- Mask-all default = inverte ônus, segurança by-default
- Cobertura PII no oimpresso é GRANDE (Crm/Cliente, Sells/CustomerSearch, Compras/Fornecedor, RH/Funcionário, Financeiro/Boleto)
- Risco LGPD > valor de ver texto digitado nos campos

**Por que só após autenticar:**

- `business_id` é o filtro principal — sessão pre-auth não tem
- Login/cadastro NÃO é gargalo conhecido (não há sinal cliente reportando)
- Cumpre [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — sem sinal, não instrumenta

**Por que filtrar superadmin/user_oimpresso:**

- Dataset comportamental real ≠ Wagner debugando às 3am
- Sessões internas inflam contadores (rage click do Wagner ≠ rage click do cliente)
- Suporte interno acessando conta de cliente também não vale (não é UX real do cliente)

**Quando faz sentido reabrir esta ADR:**

- Larissa biz=4 reportar invasão de privacidade após ver session replay (UX education ou rollback)
- ANPD publicar regulação específica sobre session replay tools
- Microsoft Clarity mudar política de retenção/processamento PII
- Custo deixar de ser zero (hoje free unlimited)
- Adoção interna < 1 acesso/semana ao dashboard em 90d (sinal: Wagner não está usando, valor = 0)

## Consequências

**Positivas:**
- **Dados comportamentais reais** dos 200 clientes substituindo intuição
- **Rage clicks/dead clicks/quick backs** rankeados por IA — priorização objetiva de bugs UX
- **Heatmaps por tela** validando decisões PageHeader canon recém-mergeadas (PRs #1453-1462)
- **Custo zero** indefinido
- **Multi-tenant Tier 0 preservado** via tag `business_id`
- **LGPD-compliant by-design** (consent + mask-all)
- **Roadmap UX baseado em sinal** ([ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md))

**Negativas / Trade-offs:**
- **2 PRs em vez de 1** (banner + Clarity) — overhead de coordenação
- **30kb JS async** adicional por pageview autenticado — performance impact mínimo mas mensurável
- **Dependência Microsoft Azure** — se Clarity descontinuar, perdemos histórico (mitigação: dados podem ser exportados via API)
- **Visibilidade pra concorrentes via job postings** — Microsoft pode usar dados agregados anonimizados pra benchmarks (TOS clarity.microsoft.com)
- **Wagner trade-off:** sem captura de funil login/cadastro (decisão consciente — não é gargalo)

**Riscos mitigados:**
- LGPD violação → consent banner prerequisito + mask-all default
- Vazamento PII → mask-all (CPF/CNPJ/email/telefone nunca capturados sem unmask explícito)
- Dataset poluído por interno → filtro superadmin/user_oimpresso
- Multi-tenant cross-leak → custom tag `business_id` (dashboard isola)
- Performance → carregamento async + só pós-auth (não bloqueia first paint público)
- Rollback fácil → 2 PRs separados, revertir Clarity sem perder banner consent (que serve a outras features)

## Plano de execução (após ADR aceita)

**Wagner manual (5min):**
1. Criar projeto em https://clarity.microsoft.com
2. Pegar `project_id`
3. Adicionar `CLARITY_PROJECT_ID=xxx` em `.env` produção Hostinger

**PR 1 — Cookie consent banner LGPD** (~1h30 IA-pair):
- Componente `<ConsentBanner>` em `resources/js/Components/shared/`
- Store consent em `localStorage` + cookie HttpOnly
- HandleInertiaRequests share `consent.analytics_accepted`
- Skill `multi-tenant-patterns` aplicada (per business config se Wagner quiser opt-out por biz futuro)
- Pest test happy-path

**PR 2 — Microsoft Clarity integration** (~1h30 IA-pair):
- `config/services.php` bloco `clarity`
- `.env.example` `CLARITY_PROJECT_ID=` + `CLARITY_ENABLED=false`
- Partial `resources/views/layouts/partials/clarity.blade.php` (snippet oficial Microsoft com guards)
- Include em `inertia.blade.php` + `app.blade.php`
- HandleInertiaRequests share `clarity.{enabled, project_id, business_id, user_type}` gated por Auth + consent + !superadmin
- 7+ campos PII: adicionar `data-clarity-unmask` em inputs NÃO-sensíveis (lista no dossier)
- Pest test env-gated (não carrega em test/local)
- Smoke browser MCP Chrome biz=4 staging — confirmar snippet só carrega após consent + auth

**Pós-merge (Wagner valida em 7d com Larissa):**
- Dashboard Clarity recebe sessões biz=4
- Wagner navega Smart Alerts → top 3 problemas reais
- Se Larissa OK → rollout 200 clientes (toggle `CLARITY_ENABLED=true` em prod)
- Se Larissa rejeitar → rollback `CLARITY_ENABLED=false` (zero impacto)

## Referências

- [ADR 0061](0061-conhecimento-canonico-git-mcp-zero-automem.md) — Conhecimento canônico (ADR forma decisão observable)
- [ADR 0062](0062-separacao-runtime-hostinger-ct100.md) — Hostinger ≠ CT 100 (Clarity é client-side, não viola)
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 (preservado via custom tag)
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (princípio 6 multi-tenant)
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal (Clarity gera sinal qualificado)
- [feedback-nunca-publicar-credenciais.md](../reference/feedback-nunca-publicar-credenciais.md) — base do mask-all default
- [Dossier `como-integrar-microsoft-clarity.md`](../sessions/2026-05-25-como-integrar-microsoft-clarity.md) — análise técnica completa
- Microsoft Clarity docs: https://learn.microsoft.com/en-us/clarity/
- Clarity privacy/LGPD compliance: https://clarity.microsoft.com/terms
