# Handoff 2026-05-17 19:38 — KB scope-aware + observer-weighted (P3 backlog)

**Sessão:** exploração conceitual KB multi-escopo com observer-weighted ranking. Não-implementação — só pesquisa + design + task P3 pra fazer depois.

---

## Estado MCP no momento do fechamento

### Cycle ativo (`cycles-active`)
- **CYCLE-06** "Martinho prod + FSM rollout + Jana V2 demo"
- 2026-05-14 → 2026-05-28 · 21% decorrido · 11 dias restantes
- Goals trackados (4):
  - 🔲 Martinho Caçambas em prod paga (1º cliente OficinaAuto pago — sinal qualificado ADR 0105)
  - 🔲 Inter PJ ao vivo — smoke biz=1 + 1ª cobrança biz=4 (Asaas blueprint)
  - 🔲 FSM rollout 162 vendas legadas biz=1 · alvo 14
  - 🔲 Jana V2 demo navegável apresentável a 1 piloto
- **Drift detectado**: 41/41 commits/PRs (7d) NÃO tocam tasks do cycle ativo (0% alinhados) — pivot estratégico em curso

### Tasks @wagner (`my-work`)
- Total: **30** (7 BLOCKED + 23 TODO)
- BLOCKED top: FIN-4 Atualizar cobrança ROTA LIVRE; 6 US-NFE Gold dormentes
- TODO P0 ativas: US-SELL-009 Cutover ROTA LIVRE, US-MWART-001 Camada 2+3 enforcement, US-INFRA-001 GrowthBook self-hosted

### ADRs relevantes (`decisions-search "KB grafo conhecimento observer scope"`)
- **0150** KB Unificado como Grafo de Conhecimento (módulo IA central) — base canon
- **0061** Conhecimento canônico git/MCP, ZERO auto-mem privada
- ARQ-0007 MemCofre framework program comprehension
- UI-0001 React Flow visualização grafo
- TECH-0003 Sync embeddings via observer + hash

_Nota: `sessions-recent` não disponível neste session — pulei esse passo do protocolo ADR 0130. Glob `memory/handoffs/*` timeoutou também._

---

## O que aconteceu

Wagner perguntou (sequência):

1. **Tese inicial**: "scopos / Empresa / grupo econômico / Por Atividade / Brasil / regiões / Setores — um KB para cada contexto?"
   → Resposta: **NÃO 1 KB por escopo** — 1 grafo unificado com N escopos por fato (Tier 0 `business_id` intransponível + cascata por scope tags)

2. **Como pedir pro Claude Design fazer o melhor + comparar + justificar**
   → Mostrei tabela de 6 referências (Glean/Notion/Linear/Stripe/ChatGPT Projects/Mem.ai) + 3 caminhos (A/B/C)

3. **Caminho 3 escolhido** — `estado-da-arte` agent spawned
   → Tese central decifrada: "recursos do observador mudam pesos da decisão" = **observer-weighted ranking**, fronteira de mercado 2026

4. **Caminho C escolhido** (após estado-da-arte) — `design-arte` agent spawned
   → Capterra design FICHA nota 88/100 + 4 wireframes ASCII

5. **Salvar como P3 baixa prioridade** — Wagner explícito "fazer depois"

---

## Artefatos gerados

| Arquivo | Conteúdo | Tamanho |
|---|---|---|
| [memory/sessions/2026-05-17-arte-kb-scope-observer-weighted.md](../sessions/2026-05-17-arte-kb-scope-observer-weighted.md) | Estado-da-arte 10 players (Glean/Notion/Linear/Stripe/ChatGPT Projects/Mem/Perplexity/Claude Projects/Coda/Granola) + decisão DAG 3-níveis + ranking 2-estágios + schema conceitual `kb_scopes`+`kb_observer_*` + gap list backend top 10 | ~370 linhas |
| [memory/requisitos/Copiloto/CAPTERRA-DESIGN-FICHA.md](../requisitos/Copiloto/CAPTERRA-DESIGN-FICHA.md) | 15 dimensões UX P0-P3 + nota agregada **88/100** + 4 wireframes ASCII (Larissa desktop 1280px / Wagner View-As 1920px / Mobile 375px / Cold-start onboarding) + 7 decisões UI justificadas + top 3 ARRISCADAS reconhecidas + gap list UI top 10 | ~870 linhas |

---

## Tese central catalogada (a parte forte da sessão)

> "Cada pessoa vai querer ver a análise pela perspectiva dela. Os pesos vão variar de acordo do observador, vai depender do recurso que o observador tiver — a informação vai valer a pena ou não. Isso muda os pesos da decisão." — Wagner

**Status no mercado 2026**: nenhum dos 10 players modela "recursos do observador afetam pesos". Glean chega mais perto (role + team graph) mas não modela caixa/capacidade/horizonte. **Diferencial defensável**.

---

## Maturidade detectada

- oimpresso **~45% médio** vs estado-da-arte 2026
- **Supera mercado**: multi-tenant Tier 0 (110%), KB schema granular (85%)
- **Gap longo**: scope nesting (20%), observer-weighted (5%), evidence chips UI (30%)
- **Fronteira de mercado**: "recursos do observador afetam pesos" (mercado todo 5-10%, oimpresso 0%)

---

## Decisão arquitetural proposta (NÃO implementada)

- **DAG 3-níveis** canônicos:
  - Nível 1 (intransponível): `business_id` Tier 0
  - Nível 2 (opcional): `scope` = CNAE OR grupo econômico OR região (DAG, N pais)
  - Nível 3 (universal): `global` sem PII
- **Ranking híbrido 2-estágios**:
  - Estágio 1: scope-cascade retrieval (mais específico ganha, geral fallback)
  - Estágio 2: observer-weighted re-rank pós-retrieval (top-100 → top-3 Larissa, top-10 Wagner)
- **Schema conceitual**: `kb_scopes` + `kb_business_scopes` + `kb_node_scopes` + `kb_observer_profiles` + `kb_observer_intents`
- **Cores UI semânticas**: cyan=business, roxo=scope, cinza=global

---

## Top 3 escolhas ARRISCADAS reconhecidas

1. Microcopy "📌 fora do seu perfil — pode importar" pode soar paternalista pra Larissa (NÃO validado com ela)
2. Mobile colapsa scope mas mantém evidence — heurística estabilidade vs mutabilidade NÃO A/B testada
3. Slash `/perspectiva` descoberta passiva pode nunca acontecer (Granola tem onboarding ativo, oimpresso não)

---

## Persistência (3 canais)

| Canal | Estado |
|---|---|
| Git canônico | ✅ commit `586a3bca0` em branch `claude/jolly-kilby-7b3cd3` pushado origin (worktree filha deletada após push) |
| MCP server task | ✅ **US-COPI-107** P3 backlog (owner: wagner, estimate: 45h, status: todo, project: Jana) |
| SPEC.md Jana | ✅ MCP escreveu direto no repo principal — webhook sync ativo |

**Branch `claude/jolly-kilby-7b3cd3` ainda NÃO mergeada em main.** Wagner pode optar:
- (a) abrir PR e mergear pra propagar pro time MCP completo
- (b) deixar branch viva pra retomar depois
- (c) fazer merge direto

---

## Pré-requisitos pra desbloquear implementação (quando retomar)

1. **ADR proposta** `memory/decisions/proposals/NNNN-observer-weighted-kb-scope-dag.md` consolidando arquitetura + design num único Nygard doc
2. **Eliana revisa LGPD** do `kb_observer_profiles` + `kb_observer_intents` (perfis estruturados de observador + intent ativa com TTL)
3. **Validar microcopy** "fora do seu perfil" com Larissa real (telefonema 5min antes de implementar)
4. **Cycle CYCLE-06 estabilizar** — Martinho prod + FSM rollout + Jana V2 demo são prioridade atual. KB observer-weighted entra CYCLE-07+ ou CYCLE-08+.

---

## Sequência canônica pra implementar (quando aprovado)

backend Gap #1 (`kb_observer_profiles` + `kb_observer_intents` ~6h) → UI Gap #1 (`<ScopeBadge>` ~4h) → backend Gap #2 (re-rank observer-weighted no `KbRagService` ~10h) → UI Gap #2 (`<EvidenceChip>` 3 variants ~6h) → UI #3+#4 (loop fechado thumbs + cold-start onboarding ~7h) → backend Gap #5 DAG scopes (`kb_scopes` + `kb_business_scopes` + `kb_node_scopes` ~12h) → UI #5-#10 incremental.

**Regra inviolável**: UI NÃO pode adiantar backend (`<ScopeBadge>` sem `kb_observer_profiles` não tem o que renderizar).

**Total Larissa MVP funcional**: ~37h IA-pair = ~5 dias úteis fator-10x ([ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)), 10 dias úteis margem 2x.

---

## Próximos passos pra retomar a sessão

1. `tasks-detail US-COPI-107` pra resgatar contexto completo
2. Ler [memory/sessions/2026-05-17-arte-kb-scope-observer-weighted.md](../sessions/2026-05-17-arte-kb-scope-observer-weighted.md) (arquitetura)
3. Ler [memory/requisitos/Copiloto/CAPTERRA-DESIGN-FICHA.md](../requisitos/Copiloto/CAPTERRA-DESIGN-FICHA.md) (design)
4. Decidir destino da branch `claude/jolly-kilby-7b3cd3` (merge / PR / abandonar e branch nova)

---

## Lições catalogadas

- **ESQUECI protocolo fechamento ADR 0130** — Wagner cobrou explicitamente "esta esquecendo das regras de fechamento". Reincidência pode ativar hook P2 dormente. Catalogar comportamental.
- **Agents canônicos em série (estado-da-arte → design-arte)** funcionou bem pra exploração conceitual sem implementação prematura — pattern reusável
- **Tese fronteira-de-mercado validada por pesquisa real** — 10 players + 6 papers acadêmicos confirmaram "recursos do observador afetam pesos" é gap aberto (5-10% maturidade mercado)
- **P3 baixa prioridade preserva contexto sem comprometer cycle ativo** — Wagner não foi forçado a escolher entre Martinho/FSM/Jana V2 e KB observer-weighted; ambos coexistem com prioridades distintas
- **Worktree filha deletada após push** — files referenciados em mensagens anteriores via `.claude/worktrees/jolly-kilby-7b3cd3/...` NÃO existem mais; re-ler do repo principal `D:\oimpresso.com\` se precisar
