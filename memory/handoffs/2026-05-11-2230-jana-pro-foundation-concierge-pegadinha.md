# Handoff 2026-05-11 22:30 — JANA Pro Sprint A foundation + modo Concierge MVP + pegadinha junction NTFS

**Sessão:** noite Wagner solo · Claude Code Max
**Duração:** ~5h ininterruptas
**PRs:** 5 (#597, #598, #600, #602, #603) + colateral #604 do Wagner em paralelo
**ADRs novas:** 2 (0140 + 0141)
**Decisões estratégicas:** 3 (JANA Pro como SaaS, pattern Camada B v2, caminho B Concierge)

---

## Estado MCP no momento do fechamento

**Cycle ativo:** `CYCLE-05` — *Inter PJ prod + WhatsApp governança* (2026-05-11 → 2026-05-23, 8% decorrido, 11 dias restantes).

**Goals trackados:**
- 🔲 Inter PJ Banking em prod com canary 7d (US-RB-048/046/047)
- 🔲 WhatsApp FICHA v2 + AUDIT-LOG.md shell (US-WA-051/052)

**Tasks ativas Wagner (4 DOING):**
- US-RB-048 `p0` RUNBOOK Inter PJ Banking
- US-WA-040 `p2` Múltiplos números por business
- US-COPI-096 `p2` Setup Horizon CT-only
- US-COPI-100 `p2` NarrarSaudeEcosistemaJob hourly

**Observação crítica:** JANA Pro (US-COPI-201/202) **NÃO está nas tasks ativas do cycle CYCLE-05**. Foi feature spawned spontaneamente nesta sessão (Wagner: "quero profissionalizar isso e vender como produto"). Foundation criada, mas Sprint A US-COPI-203/204/205 ficam **fora do cycle atual** até Wagner promover ou abrir cycle dedicado JANA Pro.

**ADRs aceitas no intervalo (since 2026-05-11 17:30):**
- **0140** — JANA Pro produto SaaS R$ 149-499/mês (esta sessão)
- **0141** — Agents tool use pattern "Claude Code" — Camada B v2 (esta sessão)

---

## Cronologia narrativa

### Fase 1 (~17h-19h BRT) — Continuação do CYCLE-05 prep

Sessão começou continuando trabalho prévio:
- PR #598 US-WA-072 N+1 lastMsg denormalize merged + migration Hostinger rodada (11/17 convs backfilled)
- Validação biz=1 prod ok

### Fase 2 (~19h-20h) — Wagner pivot estratégico

Wagner pediu validação real do `BriefDiarioService::snapshot()` em prod biz=1. Output JSON expôs sinais detectáveis (NFe 100% rejeição, cliente Antonella LTV R$ 88k sumido há 322 dias, ticket Claudete Winter "entrego por 38 mil"). Wagner constatou que vale virar produto pago.

**Decisão estratégica:** monetizar como SaaS upsell sobre oimpresso → **ADR 0140 aceita** + Product Plan 32 US × 4 sprints × 90 dias.

### Fase 3 (~20h-21h) — Refactor cognição "estilo Claude Code"

Wagner: *"eu gostaria, de não usar o vizra. ele é incompatível com versão 13 e não estou usando mais, gostaria que o pensamento seja o claude code"*

Vizra ADK já rejeitada por ADR 0048 (incompat L13/PHP 8.4). Mudança real: agents single-shot (BriefingAgent, ChatCopilotoAgent legacy) não suportam decisão dinâmica de qual dado buscar.

**Decisão arquitetural:** novos agents implementam `Laravel\Ai\Contracts\HasTools` (nativo `laravel/ai` ^0.6.3) — LLM decide quais tools chamar, igual a Claude Code com Read/Bash/Grep → **ADR 0141 aceita**.

PR #600 implementa `BriefDiarioAgent` + 5 Tools wrappers do BriefDiarioService + 5/5 Pest PASS + Tier 0 mecânico ($businessId no constructor, LLM nunca pode trocar tenant). Foundation merged dormente até provider LLM ligar.

### Fase 4 (~21h-22h) — Decisão financeira "sem $ pra API"

Wagner: *"nesse modelo eu posso fazer o modulo2 da jana e ter tudo, se ficar claro que é o claude code que vai fazer o serviço."*

Avisei honestamente: **Claude Code Max não pode ser backend de SaaS pro cliente** (ToS + arquitetura + rate limits). Mas propus 3 caminhos viáveis sem $:
- **A** Groq Llama 3.3 70B grátis via `laravel/ai`
- **B** Concierge MVP — Wagner opera manual via Claude Code Max (uso pessoal legítimo)
- **C** Ollama self-host CT 100

Wagner escolheu **caminho B** ("eu gostaria de fazer como combinamos, o claude code e eu executo manualmente quando tiver trabalhando").

PR #602 cria:
- Skill `.claude/skills/jana-brief-concierge/` v0.1.0 — Tier B auto-trigger por padrão JSON snapshot, gera narrativa markdown ~300 palavras
- RUNBOOK `memory/requisitos/Copiloto/RUNBOOK-jana-pro-concierge.md` — playbook 5 passos diário pra Wagner seguir

**Skill é espelho dev do agent** — quando provider LLM ligar (caminho A futuro), output do agent automatizado fica idêntico à skill manual. Zero retrabalho de prompt.

### Fase 5 (~22h-22h30) — Acidente + pegadinha + recovery

**Acidente operacional:** Criei junction NTFS `<worktree>\vendor -> D:\oimpresso.com\vendor` pra rodar Pest no worktree sem reinstalar 162 packages. Cleanup com `git worktree remove --force` seguiu a junction e **esvaziou `vendor/` do repo principal (318MB → 0B em segundos)**.

Recovery: `composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix` (~3-5min).

**Vazamento credencial:** No início da Fase 4, fiz `grep AI_PROVIDER|OPENAI` no `.env` que retornou a `OPENAI_API_KEY` em texto plano no chat. Avisei Wagner imediatamente — ação pendente: rotacionar key.

PR #603 documenta pegadinha:
- `memory/requisitos/Infra/PEGADINHA-junction-vendor-worktree-windows.md` — histórico + 4 opções evitação + sinais de aviso + por que NTFS faz isso
- `memory/proibicoes.md` §Ambiente — 1 linha proibição + link

**Atinge time todo no Windows** (Wagner, Maiara, Felipe, Luiz, Eliana). Linux/macOS imune.

---

## PRs desta sessão

| PR | Conteúdo | Status |
|----|----------|--------|
| **#597** | feat(jana-pro): BriefDiarioService 5 sources + admin preview (US-COPI-201) | ✅ merged |
| **#598** | feat(omnichannel): denormalize last_message_preview/direction (US-WA-072) | ✅ merged + migration prod |
| **#600** | feat(jana-pro): BriefDiarioAgent estilo Claude Code (US-COPI-202, ADR 0141) | ✅ merged dormente |
| **#602** | feat(jana-pro): skill jana-brief-concierge + RUNBOOK modo Concierge MVP | ✅ merged |
| **#603** | docs(infra): armadilha 'worktree remove --force apaga vendor via junction' | ✅ merged |
| #604 | fix(purchase): migrar show pra Inertia — Wagner em paralelo | colateral, fora do escopo |

**Total adicionado:** ~1500 linhas (PHP + tests + skill + ADRs + RUNBOOK + pegadinha).
**Custo direto sessão:** R$ 0 (Max subscription). Custo API LLM produto: R$ 0 (Concierge mode ativo).

---

## Decisões importantes consolidadas

### 1. JANA Pro é produto SaaS, não feature interna (ADR 0140)
- Pricing: R$ 0 Free / R$ 149 Pro / R$ 499 Enterprise
- Modelo: upsell sobre oimpresso (cliente paga oimpresso E paga JANA Pro)
- Cliente piloto: cliente novo qualificado OU 1 dos 7 OfficeImpresso saudáveis quando convertidos
- ROTA LIVRE NÃO é alvo Sprint A (Wagner não quer cobrar Larissa por feature)

### 2. Pattern Camada B v2 — agents pensam estilo Claude Code (ADR 0141)
- Toda classe `Modules/Jana/Ai/Agents/*` nova com dados dinâmicos DEVE implementar `HasTools`
- Tools em `Modules/Jana/Ai/Tools/<Agent>/<Tool>Tool.php` — subpasta por agent
- `$businessId` no constructor da Tool, nunca lido do LLM (Tier 0 mecânico)
- BriefingAgent/ChatCopilotoAgent legacy (single-shot) ficam permitidos pra casos onde contexto cabe em ~2k tokens

### 3. Modo Concierge MVP até ter caixa (caminho B)
- Wagner opera manual via `/copiloto/admin/jana-pro/preview?business_id=N` + Claude Code Max + WhatsApp/email
- Skill `jana-brief-concierge` automatiza geração de narrativa (Wagner cola JSON, recebe markdown)
- Trigger pra migrar automatizado: ≥5 clientes Pro pagantes OU >60min/dia em briefs OU cliente reclamar atraso
- Roteiro de migração documentado (6 passos: gerar GROQ_API_KEY + adicionar `#[Provider('groq')]` + Sprint A US-COPI-203/204)

### 4. ToS Anthropic — Claude Code Max é dev pessoal, NÃO backend SaaS
- Wagner usando Claude Code pra ajudar a redigir brief = uso legítimo (igual usar VS Code pra escrever email cliente)
- Cliente final acessando Claude Code indireto via tua conta = ToS violation
- Camada B v2 (HasTools) prepara migração pra Anthropic API/Groq quando Wagner ligar provider

### 5. Pegadinha junction NTFS é proibição Tier 0 (Windows)
- `git worktree remove --force` com junction de `vendor/` ativa = esvazia main vendor
- Recovery: composer install ~3-5min
- Evitação preferida: composer install próprio no worktree (mais seguro que junction)
- Alternativa aceitável: junction + cleanup manual `Remove-Item <wt>\vendor -Force` ANTES do worktree remove

---

## Lições aprendidas (acumuladas pra próximas sessões)

### 1. Snapshot real expõe sinais que justificam produto
Apenas rodar `BriefDiarioService::snapshot()` em prod biz=1 (dev) já mostrou:
- 100% taxa rejeição NFe (sinal fiscal crítico)
- Cliente Antonella LTV R$ 88k sumido 322d (oportunidade reativação real)
- Ticket Claudete Winter "entrego por 38 mil" (sinal comercial detectável)

Pipeline funciona antes mesmo do LLM entrar. Reforça princípio: **dados primeiro, IA depois**.

### 2. Wagner valoriza honestidade técnica sobre otimismo
Quando ele perguntou se Claude Code poderia ser backend SaaS, eu poderia ter ficado vago. Em vez disso, expliquei os 3 problemas (ToS, arquitetura, rate limit) e propus alternativas viáveis. Resultado: ele escolheu caminho B confiante, sem expectativa errada.

### 3. Pattern "espelho dev → agent operacional" é poderoso
Skill `ticket-triage` (v0.1.0) criada antes desta sessão virou exatamente isso pra triage. Skill `jana-brief-concierge` segue mesmo pattern: spec executável agora, agent operacional depois quando ligar provider. **Zero retrabalho de prompt entre os dois modos.**

### 4. CI drift pré-existente (ADR frontmatter + check-scope) admin-mergeable
PR #600, #602, #603 tiveram check-scope OU ADR frontmatter falhando por ADRs alheias (0122-0127 sem frontmatter completo). Auto-mem `feedback_branch_protection_admin_merge` confirma: Wagner owner + enforce_admins=false → `gh api --method PUT ...pulls/N/merge -f merge_method=squash` legítimo.

### 5. Junction NTFS é pegadinha real, não suposição
Antes dessa sessão eu nunca tinha caído. Agora é proibição Tier 0 documentada. Todo time Windows pode cair. Documentação preventiva > recuperação reativa.

---

## Próximos passos quando Wagner retomar

### Caminhos abertos (Wagner escolhe)

1. **Operar modo Concierge** — usar `/copiloto/admin/jana-pro/preview` + skill `jana-brief-concierge` pros primeiros clientes interessados (sem custo extra)
2. **Migrar pra automatizado** — quando trigger bater (≥5 pagantes / >60min/dia), executar roteiro 6 passos do RUNBOOK
3. **Voltar pro CYCLE-05** — US-RB-048 (Inter PJ Banking RUNBOOK) é p0 do cycle ativo; JANA Pro Sprint A continua dormente até promoção
4. **Rotacionar OPENAI_API_KEY** — pendência de higiene após vazamento no chat desta sessão

### Sprint A JANA Pro tasks remanescentes (fora cycle ativo)

- US-COPI-203 — BriefDiarioJob schedule Horizon CT 100 8h BRT (só faz sentido com provider ligado)
- US-COPI-204 — Persistência `mcp_briefs` + namespace memória `analises.brief_diario`
- US-COPI-205 — Dashboard `/copiloto/admin/jana-pro` Inertia (history viewer)

Tempo estimado: ~12h IA-pair quando Wagner promover.

---

## Referências

- [ADR 0140](../decisions/0140-jana-pro-produto-comercial-saas.md) — JANA Pro produto SaaS
- [ADR 0141](../decisions/0141-agents-tool-use-pattern-claude-code.md) — Pattern Claude Code Camada B v2
- [`.claude/skills/jana-brief-concierge/SKILL.md`](../../.claude/skills/jana-brief-concierge/SKILL.md)
- [`memory/requisitos/Copiloto/RUNBOOK-jana-pro-concierge.md`](../requisitos/Copiloto/RUNBOOK-jana-pro-concierge.md)
- [`memory/requisitos/Copiloto/JANA-PRO-PRODUCT-PLAN.md`](../requisitos/Copiloto/JANA-PRO-PRODUCT-PLAN.md)
- [`memory/requisitos/Infra/PEGADINHA-junction-vendor-worktree-windows.md`](../requisitos/Infra/PEGADINHA-junction-vendor-worktree-windows.md)
- `Modules/Jana/Services/BriefDiarioService.php` — fonte canônica 5 sources
- `Modules/Jana/Ai/Agents/BriefDiarioAgent.php` — agent dormente até provider ligar
- `Modules/Jana/Ai/Tools/BriefDiario/*.php` — 5 tools Tier 0 mecânico
