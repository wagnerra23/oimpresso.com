---
slug: runbook-onda-cowork
title: "RUNBOOK — Ondas de Cópia Cowork → Inertia (playbook canônico reusável)"
type: runbook
status: canon
date: 2026-05-17
related_adrs: [0094, 0104, 0107, 0109, 0114, 0141, 0143, 0168]
related_skills: [mwart-process, mwart-comparative, cowork-prototype-replication, charter-first, preflight-modulo, wagner-protocol-enforce, smoke-prod-evidence, brief-update, migracao-blade-react]
related_runbooks:
  - memory/requisitos/_DesignSystem/RUNBOOK-replicar-prototipo-cowork.md
  - memory/requisitos/_DesignSystem/RUNBOOK-charters-s4-ativacao.md
canonical_case_study: Sells/Index Onda 1 (PR #1032 + #1034) 2026-05-17
---

# RUNBOOK — Ondas Cowork → Inertia (playbook canônico)

> **Origem:** Wagner 2026-05-17 — *"ficou bom não copiou tudo mas ficou muito melhor do que das outras vezes, ainda tem detalhes que não foram colocados. como deveria ser as próximas ondas para garantir a aplicação do novo Design?"*

Aplicar este RUNBOOK em TODA cópia visual KB-9.75 (ou qualquer score Cowork) do prototype pra Inertia/React. Estrutura comprovada com Sells/Index Onda 1 (PR #1032 — Cowork 5.6 → score visual prod) + PR #1034 (gap legacy recuperado via smoke Brave).

---

## Conceito — Onda

Uma **Onda** é o conjunto fechado de artefatos que entregam **1 refino KB-9.75** (ou 1 página migrada inteira) de forma cobrável e mergeável. Não confundir com sprint ou cycle — é uma unidade técnica.

| Tipo de Onda | Escopo | PR único? |
|---|---|---|
| **Onda Visual Base** (R1 Fundação ou cópia integral) | Cópia visual do prototype → Page Inertia + Backend deltas | Sim |
| **Onda Refino Drawer** (R2 IA) | Painel/tab dentro do drawer existente | Sim |
| **Onda Refino Inline** (R3 Curadoria) | Comentários inline + audit + linkify + troubleshooter | Sim |
| **Onda Refino Distribuição** (R4) | Transcript A4 + apresentar + WhatsApp + arte | Sim (ou 2 se >500 LOC) |
| **Onda Polish/Dados Reais** | Sparkline backend + handlers reais + KPI 4º real | Sim |
| **Onda Tests + Smoke Automatizado** | Pest browser + visual-regression CI + cron daily | Sim |

**Cada Onda = 1 PR** (override `commit-discipline ≤300 linhas` autorizado com label `design-literal-copy` se necessário).

---

## Sequência canônica (12 fases obrigatórias)

Aplica a TODA Onda. Skip de qualquer fase = violação PROTOCOLO ([ADR 0168](../../decisions/0168-protocolo-wagner-sempre-tier-A-irrevogavel.md)).

### F0 — Pré-Onda (governance)

- [ ] **Visual-comparison.md** existe pra a Onda — se não, criar primeiro com 15 dimensões + plug-points (skill `mwart-comparative` V4)
- [ ] **Screenshot Wagner aprovou** OU autoriza explicitamente o caminho ("sim", "manda", "copia")
- [ ] **Branch nova** a partir de `origin/main`: `claude/<modulo>-onda<N>-<refino-slug>` (ex: `claude/sells-onda2-r2-ia-drawer`)
- [ ] **`wagner-understand`** agent spawn opcional se pedido cru (decodifica + lista pegadinhas)

### F1 — Pre-flight (R3 do PROTOCOLO)

- [ ] Ler `memory/requisitos/<X>/SPEC.md` — US-* relacionadas
- [ ] Ler `memory/requisitos/<X>/RUNBOOK*.md` — receita técnica
- [ ] Ler `memory/requisitos/<X>/CAPTERRA*.md` — benchmark mercado (se existir)
- [ ] Ler `<Tela>.charter.md` ao lado do `.tsx` — Mission/Goals/Non-Goals/UX targets/Anti-hooks
- [ ] `decisions-search since:<últimos 30d>` filtrado por módulo
- [ ] Skill `como-integrar` spawn se feature parcial (evita duplicação)
- [ ] Identificar **plug-points** exatos (Controller:linha, Service:método, Componente.tsx:linha)

### F2 — Backend deltas

- [ ] Lista campos derivados necessários no JSON do endpoint (`<Modulo>Controller::inertiaList` ou `sheetData`)
- [ ] Computa inline em `map()` callback PHP (não criar Service novo só pra isso)
- [ ] Preserva contratos legacy (US-* antigas continuam funcionando)
- [ ] Subqueries com alias isolados pra evitar conflito (`sps_t`, `tsl_n`, `tp_i`)
- [ ] `Inertia::defer` em props caras (skill `inertia-defer-default` Tier B)
- [ ] **Multi-tenant Tier 0** (R4): `business_id` global scope preservado

### F3 — CSS scoped

- [ ] Identificar classes novas necessárias (`.vd-*`, `.os-*`, etc) lidas verbatim de `prototipo-ui/prototipos/<modulo>/styles.css`
- [ ] **Onda Visual Base**: copiar `styles.css` verbatim → `resources/css/<modulo>-cowork.css` + scope script
- [ ] **Onda Refino**: extrair só as classes do refino → adicionar ao final de `<modulo>-cowork.css` OU criar `<modulo>-cowork-<refino>.css` import em `inertia.css`
- [ ] Globais conflitantes (`body`/`html`/`.app`) desativados
- [ ] **Sem cor crua Tailwind** no TSX — só tokens semânticos ou classes scoped

### F4 — Frontend rewrite/extend

- [ ] **R2 cópia literal** — replicar JSX estrutura do prototype 1:1 (não slice por aspecto)
- [ ] Wrapper `.<modulo>-cowork` no root div
- [ ] Reusar shadcn primitives quando aplicável (`<Sheet>`, `<Dialog>`) + classes scoped pro visual
- [ ] Drawer/painel internos via tabs no SaleSheet existente (não criar drawer paralelo)
- [ ] localStorage prefix `oimpresso.<modulo>.<chave>` (ex: `oimpresso.sells.foco`)
- [ ] TypeScript: `noUnusedLocals` + `noUncheckedIndexedAccess` honrados
- [ ] Skill `charter-first` (Tier A) — atualizar charter pra v2/v3 conforme Goals expandem

### F5 — Testes

- [ ] **Pest backend estrutural** — `tests/Feature/<Modulo>/<Modulo><Refino>PayloadTest.php` (cobre campos novos no JSON)
- [ ] **Pest tenancy** — biz=1 vs biz=99 isolado (ADR 0093)
- [ ] **Pest legacy preservados** — se algum quebrar por mudança de classe, marcar `markTestSkipped()` com razão canon + criar test novo focado em Cowork
- [ ] **Pest browser keyboard** (opcional, depende Pest browser estável) — backlog se ainda flaky
- [ ] **TS check** — `npx tsc --noEmit -p tsconfig.json` zero erros no arquivo tocado

### F6 — Charter + visual-comparison status

- [ ] Charter `<Tela>.charter.md` versão bumped (v2 → v3 se aplicável) — Goals atualizados, Non-Goals revisados
- [ ] `visual-comparison.md` status: `approved` + sync log atualizado
- [ ] Pré-flight reading list referenciada no commit body

### F7 — Commit + PR

- [ ] **Conventional commit** `feat(<modulo>): <refino> KB-9.75 — <slug>` com body multilinha listando deltas + Tier 0 honrado
- [ ] **Body lista "NÃO inclui"** transparente — gaps pra próxima Onda (transparência > optimismo)
- [ ] `Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>`
- [ ] `gh pr create` com body detalhado (template no [Anexo B](#anexo-b-template-pr))
- [ ] Label `design-literal-copy` se >300 LOC (override `commit-discipline`)
- [ ] Linkar PR base (Onda anterior) + visual-comparison.md no body

### F8 — CI watch + auto-merge (R11)

- [ ] `gh pr checks <N>` polling até verde ou red
- [ ] CI verde + caminho pré-aprovado → `gh pr merge <N> --squash --delete-branch --admin` (R11 — não pausa esperando "OK mergeia" separado)
- [ ] CI red → reporta + ESPERA decisão Wagner (não bypass)

### F9 — Smoke real pos-deploy (R1)

- [ ] Aguardar deploy Hostinger (auto via git pull pos-merge)
- [ ] `claude-in-chrome` ou `computer-use` navegar `oimpresso.com/<rota>` logado biz=1 (NÃO biz=4 cliente — R6)
- [ ] Screenshot inline + `read_console_messages` filtrar errors
- [ ] Comparar contra prototype HTML rodando local (porta http.server) lado a lado
- [ ] **Reportar evidência inline** — não delegar pra Wagner abrir o browser depois

### F10 — Detectar gaps pós-smoke

- [ ] Catalogar **explicitamente** o que ficou de fora vs prototype (commit body já listou — confirmar)
- [ ] Se gap novo descoberto (não previsto), criar issue OU propor Onda corretiva imediata (foi o caso #1034)
- [ ] Atualizar `memory/requisitos/<X>/<Tela>.review.md` (skill `tela-smoke-pos-merge`)

### F11 — Encerramento

- [ ] **`brief-update`** skill Tier B auto-trigger atualiza `BRIEFING.md` do módulo
- [ ] **`sync-mem`** skill commita docs canon pendentes (memory/) → webhook GitHub propaga pro MCP
- [ ] **`memory-sync`** — ADR/feedback/session log push pro time MCP
- [ ] **Cleanup branch local** — após merge: `git branch -d claude/<...>` + `git push origin --delete claude/<...>`
- [ ] Relatório final pro Wagner com link Brave + estado canon

---

## Critérios de Onda COMPLETA (gate de pronto)

Checklist objetivo:

- [ ] PROTOCOLO 11 regras aplicáveis (R1-R11) honradas — listadas no commit body
- [ ] Visual-comparison.md status=approved
- [ ] Charter atualizado (Goals expandidos, Non-Goals revisados)
- [ ] Pest backend novos passam (mínimo 5-10 testes estruturais)
- [ ] CI 100% verde (todos checks ADR/Charter/RUNBOOK/SPEC/Pest/Frontend/PII/SCOPE/charter-gate/module-grades-gate/mwart-gate/visual-regression)
- [ ] Smoke real Brave executado COM EVIDÊNCIA INLINE no relatório
- [ ] BRIEFING.md módulo atualizado
- [ ] Gaps remanescentes catalogados explicitamente no commit body "NÃO inclui"

Sinal de Onda INCOMPLETA (não merge):

- ❌ Algum item acima ❌
- ❌ Wagner pergunta "e a tal feature X que tava no prototype?" — Onda faltou catalogar gap
- ❌ Smoke detecta layout quebrado / erro JS / componente não renderiza
- ❌ Tier 0 violado em algum passo (R4 multi-tenant, R6 biz=4 em test, R9 auto-mem)

---

## Cadência sustentável (fator 10x ADR 0106)

Estimar Onda em **tempo codável** (do prototype JSX) e **tempo humano-limitado** (smoke real + screenshot):

| Tipo Onda | LOC est. | Codável (Claude IA-pair) | Humano-limitado | Real |
|---|---|---|---|---|
| Visual Base (cópia integral) | ~800-1500 TSX + ~7000 CSS scoped | ~30min | ~15min smoke | **~45-60min** |
| Refino Drawer (R2 IA) | ~300-500 TSX + ~50 LOC backend | ~20min | ~10min smoke | **~30-40min** |
| Refino Inline (R3 Curadoria) | ~400-600 TSX + ~80 LOC backend | ~30min | ~10min smoke | **~40-50min** |
| Refino Distribuição (R4) | ~500-800 TSX + ~30 LOC backend | ~40min | ~10min smoke | **~50-60min** |
| Polish Dados Reais | ~200-400 PHP + ~100 TSX | ~30min | ~10min smoke | **~40min** |
| Tests + Smoke Automatizado | ~300 Pest + cron + CI yaml | ~30min | ~5min validar cron | **~35min** |

**Total módulo médio** (Visual Base + R2 + R3 + R4 + Polish + Tests) = **~4-5h reais por módulo completo** com IA-pair.

**Cycle sustentável:** 1 módulo por dia (~6h efetivos com break) OU 2 Ondas por dia se quebrado entre dias.

---

## Anti-padrões catalogados (lições)

| Anti-padrão | Sintoma | Mitigação |
|---|---|---|
| **Slice depois Wagner aprovar** | Claude propõe "vou só R1 SLA pill primeiro" pós-aprovação screenshot | R2 PROTOCOLO — cópia literal integral em 1 PR |
| **Skip pre-flight** | Edit em `Modules/<X>/` sem ler SPEC/RUNBOOK/charter | R3 PROTOCOLO + skill `preflight-modulo` Tier A |
| **Edit no path errado** | Worktree filha mas Edit absoluto em main repo | R8 PROTOCOLO — paths absolutos do worktree |
| **Auto-mem privada** | Write em `~/.claude/projects/*/memory/` | R9 PROTOCOLO + hook `block-automem.ps1` |
| **Declarar funcionando sem smoke** | "✅ deploy ok" sem `curl -sv` ou screenshot | R1 PROTOCOLO + skill `smoke-prod-evidence` Tier B |
| **Parar no meio do caminho pré-aprovado** | Faz PR mas espera "OK mergeia" separado mesmo com CI verde | R11 PROTOCOLO — executa do começo ao fim |
| **Feature legacy não migrada** | Componente em `_components/` mas não montado no rewrite | Catalogar gaps em commit body "NÃO inclui" + Onda corretiva imediata |
| **CSS global vazando** | Classes do prototype quebram outras telas (sb-cp, main-body) | Script `scope-<modulo>-css.py` prefixa `.<modulo>-cowork ` em todos selectors |

---

## Pattern reusável pra outros módulos

Aplicável a TODO módulo com prototype Cowork em `prototipo-ui/prototipos/<modulo>/`:

| Módulo | Prototype existe? | Status migração |
|---|---|---|
| **Sells/Index** | ✅ `sells-index/` | ✅ Onda 1 prod live (PR #1032+#1034) · Onda 2+ pendente |
| **Compras** | ✅ `compras/` | ⏸ pendente Onda Visual Base |
| **KB** | ✅ `kb/` | ⏸ pendente Onda Visual Base |
| **Financeiro Unificado** | ✅ `financeiro-unificado/` + 4 sub | ⏸ pendente Onda Visual Base (lição F3 rejeitado catalogada) |
| **OS** | ✅ `os/` | ⏸ pendente Onda Visual Base |
| **Clientes** | ✅ `clientes/` | ⏸ pendente Onda Visual Base |
| **Produto Unificado** | ✅ `produto-unificado/` + 3 sub | ⏸ pendente Onda Visual Base |
| **Orçamento** | ✅ `orcamento/` | ⏸ pendente Onda Visual Base |
| **Caixa Unificada** | ✅ `caixa-unificada/` | ⏸ pendente Onda Visual Base |
| **Equipe** | ✅ `equipe/` | ⏸ pendente Onda Visual Base |
| **Boletos** | ✅ `boletos/` | ⏸ pendente Onda Visual Base |
| **Produção Oficina** | ✅ `producao-oficina/` | ⏸ pendente Onda Visual Base (Martinho prod) |
| **Inventario Migração** | ✅ `inventario-migracao/` | ⏸ pendente |
| **Chat** | ✅ `chat/` | ⏸ pendente |

Recomendação de ordem (alto valor):
1. **Sells/Onda 2-6** (completar refino atual antes de iniciar outros módulos — coesão)
2. **Compras** (16 views Blade catalogadas + 38KB Cowork prontas)
3. **KB** (já bench score documentado)
4. **OS** + **Produção Oficina** (Martinho prod focus do cycle)

---

## Anexo A — Estrutura de commit canônico

```
feat(<modulo>): <refino slug> KB-9.75 — <descrição curta>

<Descrição prosa do que esta Onda entrega — 3-5 linhas máx>

DELTAS

Backend (<Controller>.php +N L):
- ...

Frontend (<Tela>.tsx ~N L):
- ...

CSS (resources/css/<modulo>-cowork.css OU inertia.css):
- ...

Tests:
- tests/Feature/<Modulo>/<Test>.php (N testes novos)
- Legacy preservados / skipped com razão canon

Visual-comparison + Charter:
- ...

PROTOCOLO honrado (R1-R11)
- R<N>: <como honrado>

NÃO INCLUI (refinos KB-9.75 — backlog próxima Onda)
- ...

Refs:
 - Visual-source canon ...
 - ADRs ...

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
```

---

## Anexo B — Template PR body

Ver `memory/requisitos/_DesignSystem/PR-TEMPLATE-onda-cowork.md` (a criar se houver demanda de N PRs em batch).

---

## Refs

- ADR 0168 [PROTOCOLO WAGNER SEMPRE](../../decisions/0168-protocolo-wagner-sempre-tier-A-irrevogavel.md) — 11 regras canon
- ADR 0094 [Constituição v2](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — documento mãe
- ADR 0104 [MWART processo canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- ADR 0107 [Visual-comparison gate F3](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- ADR 0114 [Cowork loop formalizado](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- ADR 0141 [Migração Blade React skill](../../decisions/0141-migracao-blade-react-skill.md)
- ADR 0143 [FSM pipeline live prod](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [PROTOCOLO-WAGNER-SEMPRE.md](../../reference/PROTOCOLO-WAGNER-SEMPRE.md)
- [feedback-design-literal-copy-quando-aprovado.md](../../reference/feedback-design-literal-copy-quando-aprovado.md)
- [feedback-modulo-mexeu-registra-sempre.md](../../reference/feedback-modulo-mexeu-registra-sempre.md)
- [prototipo-ui/PROTOCOL.md](../../../prototipo-ui/PROTOCOL.md)
- [prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md](../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md)
- Skill [`cowork-prototype-replication`](../../../.claude/skills/cowork-prototype-replication/SKILL.md)
- Skill [`mwart-comparative`](../../../.claude/skills/mwart-comparative/SKILL.md) Tier A V4
- Skill [`wagner-protocol-enforce`](../../../.claude/skills/wagner-protocol-enforce/SKILL.md) Tier A always-on
- **Case study canônico:** Sells/Index Onda 1 — PR #1032 (cópia visual KB-9.75) + #1034 (filtros recuperação gap) + #1035 (governance PROTOCOLO)
