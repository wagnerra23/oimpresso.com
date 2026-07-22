# Prompt/Skill Management UI — estado-da-arte 2026

> **Assunto:** UIs de gestão de prompts/skills/templates IA — versionamento, governance, history, rationale, testes
> **Data:** 2026-05-05
> **Autor:** Claude (pesquisa) · Wagner (validação)
> **Concorrentes incluídos:** Langfuse, LangSmith, Humanloop, Vellum, PromptLayer, Portkey, Agenta, Helicone, Anthropic Console, Anthropic Skills (`anthropics/skills`)
> **Decisão que vai sair daqui:** Construir UI Inertia/React própria pra `.claude/skills/` (versionada DB↔git) inspirada no melhor das 10 ferramentas, sem virar dependência runtime
> **Companion docs:** [`memory/research/qa-eval-ia-estado-arte-capterra-2026-04-28.md`](./qa-eval-ia-estado-arte-capterra-2026-04-28.md) (eval/QA — adjacente, complementa) · [`memory/research/copiloto-runtime-memory-vs-mem0-langgraph-letta-zep-capterra-2026-04-26.md`](./copiloto-runtime-memory-vs-mem0-langgraph-letta-zep-capterra-2026-04-26.md) (memória runtime — adjacente)

---

## 1. TL;DR (5 frases)

1. **Hoje:** Wagner pediu UI pra gerir `.claude/skills/<slug>/SKILL.md` versionada com governance, history, rationale e testes inline — e existe categoria inteira pra isso (prompt management) com 10+ ferramentas maduras em 2026.
2. **Diferencial real:** o que Wagner pediu (rationale estruturado em 4 campos + approval workflow obrigatório + testes contra inputs reais multi-tenant + bridge filesystem-DB-git) **não é coberto integralmente por NENHUMA das 10 ferramentas** — fica à frente do mainstream.
3. **Onde perdemos contra grupo SaaS** (Humanloop, LangSmith, Vellum): UI mais polida, integrações prontas com 20+ providers, RBAC enterprise sem dor.
4. **Onde perdemos contra OSS self-host** (Langfuse, Agenta): playground multi-variant, eval pipelines automáticos, comunidade contribuindo plugins.
5. **O dilema:** usar Langfuse direto (rápido, +1 daemon JS/TS no CT 100, RBAC pago, modela 1 prompt = string), ou construir UI Inertia copiando padrões (dobro do trabalho, mas integra nativo Laravel + multi-tenant LGPD + folder-per-skill estilo Anthropic) — recomendação é construir.

---

## 2. Concorrentes incluídos

| Nome | URL | Tier de mercado | Observação relevante |
|---|---|---|---|
| **Langfuse** | [langfuse.com](https://langfuse.com/docs/prompt-management/features/prompt-version-control) | Líder OSS | MIT, paridade self-host total. Webhook GitHub bidirecional desde 2025. |
| **LangSmith Prompt Hub** | [docs.langchain.com](https://docs.langchain.com/langsmith/manage-prompts) | Líder SaaS | LangChain-coupled, diff view two-pane melhor do mercado |
| **Humanloop** | [humanloop.com](https://humanloop.com/platform/prompt-management) | Enterprise SaaS | Foco regulado (saúde, finance), governance forte, caro |
| **Vellum** | [vellum.ai](https://www.vellum.ai/products/prompt-engineering) | Desafiante SaaS (YC) | Test Suites + workflow visual + scenarios |
| **PromptLayer** | [promptlayer.com](https://www.promptlayer.com/) | Nicho SaaS | Proxy-based versioning + free tier |
| **Portkey** | [portkey.ai](https://www.braintrust.dev/articles/best-prompt-management-tools-2026) | Gateway OSS | MIT, multi-provider gateway com prompt mgmt embutido |
| **Agenta** | [agenta.ai](https://agenta.ai/blog/prompt-versioning-guide) | Desafiante OSS | MIT self-host completo, branching nativo, playground 2.0 |
| **Helicone** | helicone.ai | OSS observability | Apache, foco em telemetria não prompt mgmt |
| **Anthropic Console** | console.anthropic.com | First-party | Workbench testes, sem sync repo, version save recente |
| **Anthropic Skills repo** | [github.com/anthropics/skills](https://github.com/anthropics/skills) | First-party MIT | Modelo conceitual git-first (SoT), sem UI — gap que oimpresso preenche |

**Grupos:**
- **Vertical (prompt management dedicado):** Langfuse, LangSmith, Humanloop, Vellum, PromptLayer, Agenta
- **Benchmark (gateway/observability adjacente):** Portkey, Helicone, Anthropic Console
- **Modelo conceitual:** Anthropic Skills (`anthropics/skills`) — não é tool, é padrão

---

## 3. Matriz Feature-by-Feature

**Legenda:** ✅ tem completo · 🟡 tem básico · ❌ não tem · ❓ não consegui confirmar

### Categoria 1 — Versionamento

| Feature | oimpresso | Langfuse | LangSmith | Humanloop | Vellum | PromptLayer | Portkey | Agenta | Anthropic Skills |
|---|---|---|---|---|---|---|---|---|---|
| Versions imutáveis | 🟡 (git) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ (git) |
| Auto-increment version | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | 🟡 (manual via PR) |
| Labels móveis (prod/staging/dev) | ❌ | ✅ | ✅ | ✅ | 🟡 | 🟡 | ✅ | ✅ | ❌ |
| Branching | ❌ | 🟡 | ❌ | 🟡 | ❌ | ❌ | ❌ | ✅ (variants) | ✅ (git branches) |
| Rollback 1-click | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | 🟡 (revert PR) |

### Categoria 2 — Sync DB ↔ Git

| Feature | oimpresso | Langfuse | LangSmith | Humanloop | Vellum | PromptLayer | Portkey | Agenta | Anthropic Skills |
|---|---|---|---|---|---|---|---|---|---|
| Sync bidirecional DB↔git | ❌ | ✅ (webhook) | ❌ | ✅ (CI/CD) | ❌ | 🟡 | 🟡 | 🟡 | ✅ (git é SoT) |
| 1 prompt = 1 arquivo no repo | ❌ | 🟡 (1 arquivo único) | ❌ | ❓ | ❌ | ❌ | ❌ | ❌ | ✅ (folder por skill) |
| 1 skill = folder com SKILL.md + scripts/refs | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| PR review obrigatório pré-publish | ❌ | 🟡 (issue aberta) | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ (GitHub PR) |
| Webhook on-merge atualiza DB | ❌ (mas tem padrão p/ memória) | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | N/A |

### Categoria 3 — Diff & History

| Feature | oimpresso | Langfuse | LangSmith | Humanloop | Vellum | PromptLayer | Portkey | Agenta | Anthropic Skills |
|---|---|---|---|---|---|---|---|---|---|
| Diff visual entre versões | 🟡 (git diff) | ✅ | ✅ | ✅ | ✅ | ✅ | 🟡 | 🟡 | ✅ (git) |
| Diff two-pane / unified toggle | ❌ | 🟡 | ✅ | ❓ | ❓ | 🟡 | ❌ | ❌ | ✅ (git) |
| Diff semântico (frontmatter vs body) | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| History navegável UI | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | 🟡 (GitHub) |

### Categoria 4 — Rationale

| Feature | oimpresso | Langfuse | LangSmith | Humanloop | Vellum | PromptLayer | Portkey | Agenta | Anthropic Skills |
|---|---|---|---|---|---|---|---|---|---|
| Commit message livre | 🟡 (git) | ✅ | ✅ | 🟡 | 🟡 | ✅ (notes) | 🟡 | ✅ | ✅ (git) |
| Rationale estruturado (problema/hipótese/métrica/rollback) | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Link versão ↔ ADR/issue | ❌ | 🟡 | 🟡 | 🟡 | ❌ | ❌ | ❌ | ❌ | 🟡 |

### Categoria 5 — Testes inline

| Feature | oimpresso | Langfuse | LangSmith | Humanloop | Vellum | PromptLayer | Portkey | Agenta | Anthropic Skills |
|---|---|---|---|---|---|---|---|---|---|
| Playground inline | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Side-by-side multi-variant | ❌ | ✅ | ✅ | ✅ | 🟡 | 🟡 | ❌ | ✅ | ❌ |
| Test datasets (CSV/inline) | ❌ | ✅ | ✅ | ✅ | ✅ (Test Suites) | 🟡 | ❌ | ✅ | ❌ |
| Run contra **inputs reais multi-tenant** com PII redactor | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

### Categoria 6 — Governance

| Feature | oimpresso | Langfuse | LangSmith | Humanloop | Vellum | PromptLayer | Portkey | Agenta | Anthropic Skills |
|---|---|---|---|---|---|---|---|---|---|
| RBAC org/projeto | ✅ (Spatie) | 🟡 (project-level só Enterprise) | ✅ | ✅ | ✅ | 🟡 (Enterprise) | ✅ | 🟡 | ✅ (GitHub) |
| Audit trail completo | ✅ (mcp_audit_log) | ✅ | ✅ | ✅ | ✅ | 🟡 | ✅ | 🟡 | ✅ (git log) |
| Approval workflow obrigatório | ❌ | ❌ ([#11284 aberta](https://github.com/orgs/langfuse/discussions/11284)) | ❌ | ✅ | 🟡 | 🟡 (Enterprise) | ❌ | ❌ | ✅ (GitHub PR review) |
| Audit "qual escopo afetado" (multi-tenant) | ✅ (business_id) | ❌ | ❌ | 🟡 | ❌ | ❌ | ❌ | ❌ | ❌ |
| LGPD-compliant self-host | ✅ | ✅ (self-host) | ❌ | ❌ | ❌ | ❌ | ✅ (self-host) | ✅ | ✅ |

**Total:** 31 features cobertas em 6 categorias × 9 ferramentas + oimpresso = matriz densa.

---

## 4. Notas estimadas (escala G2 1-5)

| Critério | oimpresso (futuro) | Langfuse | LangSmith | Humanloop | Vellum | Agenta |
|---|---|---|---|---|---|---|
| Facilidade de uso | 4 (estimado) | 4 (estimado, devs reportam curva) | 4.5 ([G2](https://www.g2.com/products/langsmith)) | 4 (estimado) | 4.2 (estimado) | 3.5 (estimado, "polish lacking" admitido [docs](https://agenta.ai/blog/prompt-versioning-guide)) |
| Suporte | 5 (Wagner direto) | 4 (Discord ativo) | 4 (LangChain enterprise) | 5 (enterprise SaaS) | 4 (YC backed) | 3 (early-stage) |
| Custo-benefício | 5 (zero recurring, self-host) | 4 (free OSS, paid Enterprise) | 3 (SaaS pricing) | 2 (enterprise pricing) | 3 (SaaS pricing) | 5 (free OSS) |
| Específico pro nicho (skills com folder + frontmatter + multi-tenant LGPD) | 5 (construído pra isso) | 2 (1 prompt = string) | 2 | 3 | 2 | 2 |

---

## 5. Top 3 GAPS críticos (oimpresso vs estado-da-arte)

### GAP 1 — Sem playground multi-variant pra testar skill antes de aceitar
**O que falta:** rodar mesma input contra 2-3 versões da skill side-by-side comparando outputs. Langfuse, LangSmith, Vellum, Agenta têm. Sem isso, dev edita SKILL.md e só descobre regressão quando harness ativa em prod.
**Esforço estimado:** **Médio** (3-5 dias) — backend já existe (chamada `laravel/ai`), UI é novo
**Impacto se não fechar:** todo edit de skill vira aposta. Regressão silenciosa = bug em prod.

### GAP 2 — Sem rollback 1-click via labels móveis (estilo Langfuse)
**O que falta:** label `production` apontando pra version_id. Mover label = "deploy" sem precisar abrir PR de revert. Padrão em todas 8 ferramentas vertical. Hoje rollback exige git revert + PR + merge + webhook propagar.
**Esforço estimado:** **Baixo** (1-2 dias) — 1 tabela `skill_labels` + UI button
**Impacto se não fechar:** rollback leva ~5min em vez de 5s. Em incidente prod, custa.

### GAP 3 — Sem diff visual semântico (frontmatter vs body)
**O que falta:** mudar `description:` no frontmatter é alto-impacto (afeta auto-activation do harness — pode parar de matchar). Mudar exemplo no body é baixo-impacto. Hoje text-diff trata igual. Sinalização visual obrigatória.
**Esforço estimado:** **Médio** (2-3 dias) — parser YAML + Monaco diff customizado
**Impacto se não fechar:** PRs aprovados sem ver que `description` mudou → skill perdida. Mais grave que parece.

---

## 6. Top 3 VANTAGENS reais

### V1 — Folder-per-skill com scripts/refs (modelo Anthropic Skills)
**Por que é vantagem:** ninguém entre as 9 ferramentas modela isso. Todas tratam prompt como string. Anthropic publicou em dez/2025 e oimpresso já adotou (`.claude/skills/<slug>/SKILL.md`).
**Como capitalizar:** UI nativa do oimpresso entende a estrutura. Categoria nova: editar não só o `SKILL.md` mas o folder inteiro como bundle.
**Risco de erodir:** Langfuse pode adicionar suporte a folder em ~12m se padrão Anthropic dominar. Janela de vantagem ~1 ano.

### V2 — Multi-tenant `business_id` global scope nativo
**Por que é vantagem:** todas 9 ferramentas single-tenant ou top-level org. Skill scoped por business_id (ex: skill custom só pra ROTA LIVRE biz=4) ninguém faz. ADR 0073 pode prever `business_id NULL = global`.
**Como capitalizar:** Larissa (biz=4) edita skill que só ela vê, sem poluir global. Diferencial pra ERP multi-empresa.
**Risco de erodir:** ferramentas SaaS implementam workspace-per-tenant em ~24m. Defensável a médio prazo.

### V3 — Approval workflow obrigatório com 4 campos rationale
**Por que é vantagem:** [Langfuse #11284 aberta](https://github.com/orgs/langfuse/discussions/11284) há meses sem prioridade. Humanloop tem mas é SaaS enterprise caro. Rationale estruturado (problema/hipótese/métrica/rollback estilo Anthropic) NINGUÉM tem.
**Como capitalizar:** posicionar como "prompt management para times regulados" — LGPD/SOX/HIPAA equivalentes BR.
**Risco de erodir:** Langfuse pode merger em 6m se usuários pressionarem. Diferencial de ~6m a 1 ano.

---

## 7. Posicionamento sugerido (3 caminhos)

| Caminho | Tese curta | Veredito |
|---|---|---|
| **A** — Usar Langfuse direto self-host | Subir Langfuse no CT 100, integrar via webhook GitHub. Zero código, máxima velocidade. | ❌ — +1 daemon JS/TS no CT 100, project RBAC só Enterprise, modela 1 prompt = string (não folder), webhook é 1 arquivo único no repo, JS SDK runtime indesejado. |
| **B** — Construir UI Inertia/React própria copiando padrões | Imitar Langfuse (versions+labels+webhook), LangSmith (diff two-pane), Anthropic Skills (folder-per-skill+git PR). Adiciona o que ninguém tem (rationale 4-campos + approval + multi-tenant). | ✅ — alinha 100% com ADR 0061 git-first + ADR 0053 webhook + Spatie + multi-tenant. ~7 dias úteis pra V1. Diferencial real. |
| **C** — Híbrido: Langfuse pra prompts simples + UI custom só pra skills folder | 2 sistemas, 2 fontes. | ❌ — drift garantido entre 2 sistemas. Pior dos mundos. |

**Recomendado: B.**
**Frase de posicionamento:** "Skill management git-first, multi-tenant, com governança que time regulado precisa." (uso interno; vira positioning de oimpresso pra clientes que pedirem CMS de prompts depois.)

---

## 8. Math da decisão (não R$ — tempo + qualidade)

ADR 0022 (R$ [redacted Tier 0]mi/ano) não se aplica — ferramenta interna, não vende sozinha. Math diferente:

- **Skills hoje:** 16 SKILL.md em git. Time edita via VS Code + PR. Wagner aprova manualmente.
- **Sem UI:**
  - Onboarding dev novo: ~30min lendo CLAUDE.md + sync-skills + descobrindo o que existe
  - Edit + review: ~10min PR pra mudança trivial (typo/exemplo) — overhead alto
  - Test antes merge: 0% (nada padronizado) — regressões só descobertas em prod
- **Com UI V1 (caminho B):**
  - Onboarding: 5min navegando `/ads/admin/skills` lista
  - Edit + review: ~3min editor inline + rationale 4 campos forçados + 1-click test
  - Test antes merge: 100% (test runner com inputs reais multi-tenant + PII redactor obrigatório no aprove)
- **Custo construir V1:** ~7 dias úteis (Sprint A 5d backend + Sprint B 3d UI = comprime pra 7d com paralelo)
- **Payback:** se time editar skills 5×/semana × 7min economizados = 35min/semana × 4 = 140min/mês × 12 = 28h/ano por dev ativo. 5 devs = 140h/ano.
- **Mas o ganho real é qualidade:** zero regressão silenciosa em produção (skill com `description` quebrada = harness para de matchar). Dificultar de medir mas alto impacto.

**Assunção não validada:** time vai usar a UI ou continuar editando direto via VS Code? Se segundo, payback some. Sprint B precisa entregar UI **mais rápida** que abrir VS Code + git pull + edit + commit + PR — caso contrário time ignora.

---

## 9. Recomendação concreta

### 3 features prioritárias pra construir nos próximos 6 meses (em ordem)

1. **UI base com versionamento + labels móveis + diff two-pane** — caminho B telas 1-2-3 (lista, detalhe, editor). **3 sprints (15 dias úteis)**, mas V0 utilizável em 7d.
2. **Test runner contra inputs reais multi-tenant + PII redactor** — tela 4. Resolve GAP 1 + V3 simultâneo. **2 sprints.**
3. **Approval workflow obrigatório com rationale 4-campos** — tela 5. Resolve V3 (diferencial absoluto vs mercado). **1 sprint.**

Máximo 3. Total: 6 sprints (~3 meses). Coerente com a janela "6 meses" do template.

### O que NÃO fazer agora

- ❌ Adotar Langfuse como dependency (caminho A) — analisado, contraindica.
- ❌ Branching de variants estilo Agenta — labels sobre versions é mais simples e bate com git-flow oimpresso.
- ❌ UI de criação de skill via UI sem PR git — quebra ADR 0061 (git-first SoT). Edição **sempre** vira PR.
- ❌ Workflow visual estilo Vellum (drag-drop) — overkill, pode entrar em V3+.
- ❌ Eval automático estilo LangSmith evals — categoria adjacente (cobre comparativo `memory/research/qa-eval-ia-estado-arte-capterra-2026-04-28.md`), não confundir.

### Métrica de fé (90 dias)

> Se em 90 dias UI V1 (telas 1+2+3 + test runner básico) estiver em prod **e Wagner+Felipe+Maíra editarem skill via UI ≥ 5×/semana** (medido via `mcp_audit_log` por user+route), confirma a tese. Senão, **pivota pra V0 read-only** (catálogo só pra busca/leitura) e mantém edição via VS Code+git.

---

## 10. Sources

- [Langfuse — prompt version control](https://langfuse.com/docs/prompt-management/features/prompt-version-control)
- [Langfuse — GitHub Integration](https://langfuse.com/docs/prompt-management/features/github-integration)
- [Langfuse — commit messages changelog](https://langfuse.com/changelog/2025-01-28-prompt-commit-messages)
- [Langfuse — Playground](https://langfuse.com/docs/prompt-management/features/playground)
- [Langfuse — Discussion #11284 approval workflow](https://github.com/orgs/langfuse/discussions/11284)
- [LangSmith — Diff View changelog](https://changelog.langchain.com/announcements/diff-view-in-langsmith-s-prompt-hub)
- [LangSmith — manage prompts](https://docs.langchain.com/langsmith/manage-prompts)
- [Humanloop — prompt management](https://humanloop.com/platform/prompt-management)
- [Vellum — Test Suites](https://www.vellum.ai/blog/introducing-vellum-test-suites)
- [Vellum — Prompts/Playground](https://www.vellum.ai/products/prompt-engineering)
- [PromptLayer](https://www.promptlayer.com/)
- [Agenta — versioning guide](https://agenta.ai/blog/prompt-versioning-guide)
- [Agenta — Playground 2.0](https://agenta.ai/blog/prompt-playground)
- [Anthropic Skills repo](https://github.com/anthropics/skills)
- [Braintrust — best prompt management tools 2026](https://www.braintrust.dev/articles/best-prompt-management-tools-2026)
- [Confident AI — top 5 prompt mgmt 2026](https://www.confident-ai.com/knowledge-base/best-ai-prompt-management-tools-with-llm-observability-2026)
- [Agenta — top open-source platforms 2026](https://agenta.ai/blog/top-open-source-prompt-management-platforms)
