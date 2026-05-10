---
name: module-completeness-audit
mission: "Substituir auditoria manual de gaps de governança intra-módulo (multi-instance, perms UI, charter, runbook, pest, audit, scope, smoke) por checklist 8-dimensões executado antes de fechar US."
description: ATIVAR quando user pedir "auditar completude do módulo X", "antes de fechar US-XXX-NNN tem gap?", "verificar governança do {modulo}", "/module-audit {modulo}", "tem gap em {modulo}?", OU **antes** de qualquer `tasks-update <ID> status:done` cuja US toque módulo do oimpresso. Roda 8 dimensões de governança INTERNA — (1) multi-instance scope, (2) permissions middleware+UI, (3) charter, (4) runbook, (5) pest golden+edge cross-tenant biz=99, (6) audit log em mutações, (7) business_id global scope, (8) browser MCP smoke salvo. Retorna gaps numerados — Wagner decide proceed (criar US-fix em batch) ou pular (registrar exceção em PR). NÃO confundir com `/comparativo` (skill irmã `comparativo-do-modulo` cobre gaps EXTERNOS Capterra/mercado); esta cobre gaps INTERNOS de governança (rota tem can:* mas sem tela de gestão; FK pra business existe mas multi-instance não; charter ausente; etc). Origem caso real US-WA-040 / Whatsapp settings 2026-05-10.
type: skill
status: active
version: 0.1.0
trust_level: L2
owner: wagner
created_at: 2026-05-10
generated_from: feedback_module_completeness_audit_approach
charter_adr: 0094  # Constituição v2 §4 loop fechado por métrica
parent_mission: meta-skill-roi-erp-autonomo
triggers_on:
  - "/module-audit"
  - "/module-audit {modulo}"
  - "auditar completude {modulo}"
  - "audit completude {modulo}"
  - "verificar governança {modulo}"
  - "verificar governanca {modulo}"
  - "tem gap em {modulo}?"
  - "{modulo} tá pronto pra fechar?"
  - "{modulo} esta pronto pra fechar?"
  - "essa US tá completa?"
  - "antes de fechar {US-ID}"
  - "tasks-update {ID} status:done"  # auto-trigger pré-fechamento
does_not_trigger_on:
  - "/comparativo {modulo}" (use comparativo-do-modulo — gaps Capterra/mercado, não governança interna)
  - feedback funcional Wagner sobre tela MWART (use mwart-quality — 9 checks Inertia/React)
  - leitura de SPEC.md (use editor direto)
  - criar módulo novo (use criar-modulo — checklist 8 peças)
  - migrar módulo (use migrar-modulo — drift PHP-only vs URL move)
roi_metric:
  type: error
  baseline: "Wagner detecta gap intra-módulo (multi-instance ausente, perms UI ausente, charter ausente, etc) só APÓS abrir tela em prod ~10-30min depois — round-trip Whatsapp settings 2026-05-10 (US-WA-040) custou 2 gaps que voltam pra backlog."
  target: "Skill flagi 8 dimensões em ~2min ANTES de status:review→done — Wagner cria US-fix em batch antes de fechar a US original."
metrics:
  audits_run: 3
  gaps_detected_total: 11
  gaps_fixed_before_done: 9  # US-NFE-061+062+063 / US-RB-048+049 / US-COPI-101+102+103+104
  gaps_ignored_with_exception: 2  # RB Dim 3 (Charter) + RB Dim 8 (Smoke) — UI ainda não existe
  gaps_deferred_p1_p2: 0  # Wagner re-aprovou batch completo no mesmo dia
  modules_covered: [NfeBrasil, RecurringBilling, Jana]
  false_positives: 0
  # ROI gate atual (audits_run >= 5 + fixed/detected > 0.6): fixed/detected = 9/11 = 0.82 ✅
  # Falta atingir audits_run >= 5 pra revisar P1 hook bloqueador
tier: B
parent_adr: 0095
---

# module-completeness-audit

Skill para detectar gaps **INTERNOS de governança intra-módulo** antes de fechar US — complementar a `/comparativo` que detecta gaps **EXTERNOS** vs Capterra/mercado. As duas skills se compõem: comparativo responde "concorrente faz X e a gente não?", esta responde "a gente fez X mas faltou multi-instance / UI permissões / charter / pest cross-tenant?".

## Origem (caso real — registrar pra não repetir)

**US-WA-040** [doing → review, sprint 4 — 2026-05-10] em `/whatsapp/settings`. Wagner detectou em prod 2 gaps **APÓS** abrir a tela:

- ❌ **Multi-instance scope ausente** — model `WhatsappAccount` é 1-por-business, mas business pode ter **N números** (ex: ROTA LIVRE tem atendimento + financeiro). Não tem FK + index pra `phone_number_id` nas tabelas dependentes; UI não permite trocar entre números.
- ❌ **UI permissões dedicada ausente** — rotas em `Modules/Whatsapp/Routes/web.php` têm middleware `can:whatsapp.manage`, mas **não existe tela de gestão** em `/whatsapp/permissions` nem entrada em `/admin/roles` por módulo. Wagner descobriu tentando dar acesso de leitura pra Maiara sem dar gestão.

`/comparativo Whatsapp` **não pegaria** — esses gaps são INTERNOS, não estão na FICHA Capterra (concorrentes podem nem expor multi-phone), mas são **OBRIGATÓRIOS** pelas decisões da Constituição v2 ([ADR 0094](../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) §5 SoC brutal + §6 Multi-tenant Tier 0 + §4 Loop fechado por métrica).

Custo do round-trip: ~30min Wagner + 2 US-fix novas pra backlog + perda de confiança ("US estava em review e tinha 2 gaps").

## Os 8 dimensões da auditoria

Cada dimensão tem critério `✅ APROVADO / 🟡 PARCIAL / ❌ AUSENTE` com **evidência citada `file:line`**.

| # | Dimensão | Pergunta | Onde olha |
|---|---|---|---|
| 1 | **Multi-instance scope** | A entidade é 1-por-business OU N-por-business? Se N, FK + index + UI batem? | Migrations + Model + Controller + Page Inertia |
| 2 | **Permissions middleware + UI** | Rotas têm `can:*`? E existe UI dedicada de gestão (em `/admin/roles` OU dentro do módulo)? | `Routes/web.php` + `Pages/<Mod>/Permissions*` ou `Pages/<Mod>/Settings*` |
| 3 | **Charter** | `*.charter.md` ao lado da `.tsx` da tela principal? | `resources/js/Pages/<Mod>/<Tela>.charter.md` |
| 4 | **RUNBOOK** | `RUNBOOK-<tela>.md` em `memory/requisitos/<Mod>/`? | `memory/requisitos/<Mod>/RUNBOOK*.md` |
| 5 | **Pest golden + edge cross-tenant** | 1 teste cobre golden path E 1 edge case com `biz=99` (cross-tenant)? | `Modules/<Mod>/Tests/**/*.php` + grep `business_id` + grep `biz_99\|business_id.*99` |
| 6 | **AuditLog em mutações** | Controllers `store`/`update`/`destroy` registram em audit log? | grep `AuditLog::log\|audit(\|->logActivity(` em `Modules/<Mod>/Http/Controllers/*.php` |
| 7 | **Multi-tenant `business_id` global scope** | Models tocam `business_id` + global scope (`BusinessIdScope` ou `BelongsToBusiness`)? | `Modules/<Mod>/Models/*.php` |
| 8 | **Browser MCP smoke salvo** | Smoke test browser MCP existe (screenshot + console clean)? | Glob `memory/sessions/*<mod>*.md` + `memory/requisitos/<Mod>/smoke-*.md` |

## Quando ativa

| Gatilho | Modo |
|---|---|
| `/module-audit {Modulo}` | **Pre-flight** — antes de fechar US ou em momento de checagem |
| "auditar completude do {Modulo}" | Pre-flight |
| "antes de fechar {US-ID}" | Pre-flight com foco na US |
| "{Modulo} tá pronto pra fechar?" | Pre-flight |
| `tasks-update <ID> status:done` (próxima call) | **Auto-trigger** — força audit antes da call passar |
| Wagner pergunta "tem gap em {Modulo}?" | Pre-flight |

## Quando NÃO ativa

- `/comparativo {Modulo}` — usa **`comparativo-do-modulo`** (gaps Capterra/mercado, não governança interna). As duas se complementam; rodar ambas é sinal de US grande.
- Edit em `Pages/<Mod>/<Tela>.tsx` MWART — usa **`mwart-quality`** (9 checks técnicos Inertia/React específicos).
- Criar módulo novo — usa **`criar-modulo`** (checklist 8 peças obrigatórias).
- Edição direta de SPEC.md/charter — usa editor.

## Workflow obrigatório (Modo Pre-flight)

Copia o checklist no thinking e marca conforme avança:

```
- [ ] 1. Receber {Modulo} + {US-ID} se houver (default: cycle ativo my-work)
- [ ] 2. Validar pré-condições:
       - Modules/<Mod>/ existe? (parar se não)
       - SPEC.md existe? (avisar se não, continuar)
- [ ] 3. Read paralelo (1 rodada):
       - SPEC.md
       - Modules/<Mod>/Routes/web.php (+ api.php se houver)
       - Glob Modules/<Mod>/Models/*.php
       - Glob Modules/<Mod>/Http/Controllers/*.php
       - Glob Modules/<Mod>/Database/Migrations/*.php
       - Glob Modules/<Mod>/Tests/**/*.php
       - Glob resources/js/Pages/<Mod>/**/*.tsx
       - Glob resources/js/Pages/<Mod>/**/*.charter.md
       - Glob memory/requisitos/<Mod>/*.md
       - Glob memory/sessions/*<mod>*.md
- [ ] 4. Aplicar 8 checks (cada dimensão cita file:line como evidência)
- [ ] 5. Gerar relatório no formato:
       - "✅ Dim N (Nome) — evidência file:line"
       - "🟡 Dim N (Nome) — file:line — falta {item} — fix sugerido"
       - "❌ Dim N (Nome) — sem evidência — fix sugerido"
- [ ] 6. Apresentar batch ao Wagner: "{N} gaps detectados em {Modulo}. Aprovo criar {M} US-fix? (todas / nenhuma / 1,3 / só ❌)"
- [ ] 7. PARAR aguardando resposta — não criar tasks sem aprovação (publication-policy ADR 0040)
- [ ] 8. Após Wagner aprovar: tasks-create no MCP pra cada gap aprovado, prefix US-{MOD}-FIX-NNN
- [ ] 9. Apender bloco "## Auditoria de completude {YYYY-MM-DD}" em SPEC.md (append-only, datado)
- [ ] 10. NÃO fechar a US original (status:done) até gaps virarem US-fix OU Wagner registrar exceção em PR description
```

## Critério de classificação por dimensão

### Dim 1 · Multi-instance scope

```
✅ APROVADO se:
  - Migration declara FK + index pra entidade-pai (business OU sub-entidade tipo phone_number)
  - Model tem relacionamento explícito (hasMany / belongsTo)
  - Controller filtra pelo escopo correto (não só business_id)
  - UI tem seletor visível (dropdown / tabs / badge)

🟡 PARCIAL se:
  - FK existe mas UI não permite trocar (ex: hardcoded "primeira conta")
  - Model OK mas Controller ignora multi-instance

❌ AUSENTE se:
  - Tabela só tem business_id, sem FK pra entidade-N
  - Wagner indica "deveria ter N por business" mas só tem 1

Gatilho típico: "biz pode ter N números/contas/lojas" — se sim, audit aplica.
```

### Dim 2 · Permissions middleware + UI

```
✅ APROVADO se:
  - Routes/web.php tem `->middleware('can:<mod>.<action>')` em rotas mutáveis
  - Existe Page de gestão visível: Pages/<Mod>/Permissions/Index.tsx OU entrada em /admin/roles que lista permissões do módulo
  - Page tem CRUD de role↔permission

🟡 PARCIAL se:
  - Middleware existe mas UI gestão ausente (rota gated mas Wagner não consegue editar quem tem)
  - UI existe em /admin/roles mas sem agrupamento por módulo

❌ AUSENTE se:
  - Sem middleware can:* (rotas todo-mundo-pode)
  - OU middleware sim, UI não (caso US-WA-040 original)
```

### Dim 3 · Charter

```
✅ APROVADO se:
  - <Tela>.charter.md ao lado de <Tela>.tsx
  - Frontmatter status: live (não draft)
  - Mission + Goals + Non-Goals + Anti-hooks preenchidos

🟡 PARCIAL se:
  - charter.md existe mas status: draft (Wagner ainda não aprovou)
  - Apenas tela principal tem; sub-telas (Edit/Create) sem charter

❌ AUSENTE se:
  - Nenhum *.charter.md em Pages/<Mod>/

Skill irmã: charter-write (criar) + charter-first (ler antes de editar .tsx).
```

### Dim 4 · RUNBOOK

```
✅ APROVADO se:
  - memory/requisitos/<Mod>/RUNBOOK-<tela>.md existe (1 por tela importante)
  - 11 seções obrigatórias preenchidas (skill cockpit-runbook)

🟡 PARCIAL se:
  - RUNBOOK existe pra tela principal mas não pra sub-telas
  - Seções incompletas

❌ AUSENTE se:
  - Nenhum RUNBOOK*.md no módulo

Skill irmã: cockpit-runbook (gera/audita).
```

### Dim 5 · Pest golden + edge cross-tenant

```
✅ APROVADO se:
  - Modules/<Mod>/Tests/Feature/*Test.php cobre golden path do Controller principal (store + index)
  - Pelo menos 1 teste com `actingAs(biz=99 user)` validando que NÃO acessa biz=1 data
  - Teste registrado em phpunit.xml (CI roda)

🟡 PARCIAL se:
  - Golden path coberto mas sem cross-tenant
  - Teste existe mas não está em phpunit.xml (CI ignora — proibição § Código)

❌ AUSENTE se:
  - Sem testes feature do módulo
  - OU testes só em biz=1 (sem biz=99 cross-tenant — violação ADR 0101 refinado)
```

### Dim 6 · AuditLog em mutações

```
✅ APROVADO se:
  - Controllers store/update/destroy chamam AuditLog::log() OU activity log Spatie OU equivalente
  - Mensagem auditada cita: ator, ação, entidade, business_id

🟡 PARCIAL se:
  - Apenas store/destroy logam (update silencioso)
  - Log existe mas sem business_id (não dá pra filtrar por tenant)

❌ AUSENTE se:
  - Nenhum log em mutação (caixa-preta — proibição governança)
```

### Dim 7 · Multi-tenant business_id global scope

```
✅ APROVADO se:
  - Cada Model em Modules/<Mod>/Models/*.php que toca dados de negócio:
    - Tem `business_id` na tabela (migration)
    - Tem global scope (BusinessIdScope ou BelongsToBusiness trait)
  - Jobs em Modules/<Mod>/Jobs/* recebem $businessId no constructor

🟡 PARCIAL se:
  - Tabela tem business_id mas Model sem global scope (vaza dados em queries cruas)
  - Job acessa session() em vez de receber businessId no constructor

❌ AUSENTE — Tier 0 IRREVOGÁVEL VIOLADO:
  - Tabela sem business_id em entidade de negócio
  - PARAR auditoria, escalar Wagner imediato (skill multi-tenant-patterns Tier A)
```

### Dim 8 · Browser MCP smoke salvo

```
✅ APROVADO se:
  - memory/sessions/*<mod>*.md OU memory/requisitos/<Mod>/smoke-*.md tem:
    - Screenshot URL/binary
    - Console messages (clean = sem error/Error/TypeError)
    - Data do smoke (≤30 dias)

🟡 PARCIAL se:
  - Smoke existe mas >30 dias (envelhecido)
  - Screenshot sem console messages

❌ AUSENTE se:
  - Sem evidência de smoke browser MCP (caso típico de US fechada baseada só em "passa nos tests")
```

## Saída final pro Wagner

```
✅ Auditoria de completude — {Modulo} ({YYYY-MM-DD}).

Cobertas: {N} de 8 dimensões
- ✅ APROVADO: {N1}
- 🟡 PARCIAL: {N2}
- ❌ AUSENTE: {N3}

Gaps detectados (priorizados ❌ antes de 🟡):
1. ❌ Dim 1 (Multi-instance) — Modules/Whatsapp/Models/WhatsappAccount.php:8 — só 1 conta por business; biz pode ter N → propor migration `add_phone_number_id_to_*` + UI seletor
2. ❌ Dim 2 (Permissions UI) — Modules/Whatsapp/Routes/web.php:14 tem can:whatsapp.manage mas sem Pages/Whatsapp/Permissions → propor UI dedicada
3. 🟡 Dim 5 (Pest cross-tenant) — Modules/Whatsapp/Tests/Feature/SettingsTest.php cobre biz=1, falta biz=99 → adicionar teste BusinessIdGuardTest
4. ❌ Dim 8 (Smoke MCP) — sem evidência em memory/sessions/*whatsapp*.md → rodar smoke pós-merge

Aprovo criar {M} US-fix? (todas / nenhuma / 1,2 / só ❌)
[ ] todas (4 US-fix)
[ ] só ❌ (3 US-fix — pula 🟡)
[ ] 1,2,4 (3 US-fix — pula Pest)
[ ] nenhuma — registrar exceção em PR description e fechar US original mesmo assim
```

**PARAR aqui.** Aguardar resposta. Não criar tasks sozinha (publication-policy [ADR 0040](../../memory/decisions/0040-policy-publicacao-claude-supervisiona.md)).

## Após aprovação Wagner

1. Pra cada gap aprovado, `tasks-create` com:
   - `module: <Modulo>`
   - `priority: P0` (❌) ou `P1` (🟡)
   - `title: "fix({Modulo}): Dim N — {dimensão}"`
   - `description: "Gap detectado por module-completeness-audit em {data}. Evidência: {file:line}. Fix sugerido: {fix}."`
   - `tags: ["completeness-gap", "from-skill"]`
   - `cycle: current` se houver
   - `parent_us: {US-ID original}` se a audit foi disparada por fechamento

2. Apender em `memory/requisitos/<Mod>/SPEC.md` (append-only, datado):
```markdown
## Auditoria de completude — {YYYY-MM-DD}

Disparada por: {US-ID original ou /module-audit manual}
Resultado: {N1} ✅ / {N2} 🟡 / {N3} ❌

Gaps virando US-fix:
- US-{MOD}-FIX-NNN (P0): Dim N (Nome) — file:line
- ...

Gaps com exceção registrada (Wagner aprovou pular):
- Dim N — razão: {explicação curta} — risco aceito por Wagner em PR #NNN
```

3. Atualizar `metrics:` no frontmatter desta skill:
   - `audits_run += 1`
   - `gaps_detected_total += N`
   - `gaps_fixed_before_done += {aprovados}`
   - `gaps_ignored_with_exception += {pulados com aprovação}`
   - `modules_covered` adiciona `{Modulo}` se ainda não estiver

4. **NÃO fechar a US original** (`tasks-update <ID> status:done`) até:
   - Todas as US-fix criadas estarem `status:doing` ou `done` E
   - Wagner explicitamente confirmar "fecha US-{ID-original}"

## Anti-padrões (NUNCA fazer)

- ❌ Fechar US `status:done` sem rodar auditoria em módulo (= bug volta em prod, círculo Whatsapp 2026-05-10)
- ❌ Confundir com `/comparativo` (skills complementares — gaps mercado vs gaps governança)
- ❌ Marcar dim como ✅ sem evidência `file:line` (só evidência salva da alucinação)
- ❌ Auto-criar US-fix sem aprovação Wagner (publication-policy + ADR 0040)
- ❌ Bloquear `tasks-update status:done` tecnicamente (essa skill é **advise**; bloqueio técnico via hook PowerShell — TODO P1, ver §Próximo passo)
- ❌ Rodar audit em módulo sem `Modules/<Mod>/` no FS (parar e instruir)
- ❌ Sobrescrever bloco anterior em SPEC.md (append-only, datado — pra ter histórico de auditorias)
- ❌ Pular Dim 7 (`business_id` global scope) — Tier 0 IRREVOGÁVEL, escalar Wagner se ❌

## ROI / por que existe

| Caso | Custo sem skill | Custo com skill |
|---|---|---|
| Whatsapp settings (2026-05-10, US-WA-040) | 2 gaps detectados em prod, ~30min Wagner round-trip, 2 US-fix backlog | 2min audit pré-review, 2 US-fix criadas em batch ANTES de status:done |
| US futura módulos novos | Repete cenário Whatsapp (gap silencioso até prod) | Skill flagi 8 dims antes de fechar |
| Modules/ConsultaOs (2026-05-04) | Foi mergeado sem audit; pode ter gaps escondidos | Re-auditar retroativo |

**Loop fechado por métrica** (Constituição v2 §4) — gap detectado **antes** do prod = governança forte, base do ERP autônomo R$ 10M / 24m.

## Skills irmãs (matriz de cobertura)

| Skill | Cobre | Quando usar |
|---|---|---|
| **module-completeness-audit** (esta) | Gaps INTERNOS de governança (8 dimensões) | Antes de fechar US / `/module-audit {Mod}` |
| `comparativo-do-modulo` | Gaps EXTERNOS vs Capterra/mercado | `/comparativo {Mod}` — features ausentes vs concorrentes |
| `mwart-quality` | 9 checks técnicos Inertia/React | Edit em `Pages/<Mod>/<Tela>.tsx` |
| `criar-modulo` | Scaffold Laravel modular novo | `git mv`/`mkdir` em `Modules/<Mod>/` |
| `migrar-modulo` | Drift PHP-only vs URL move | Renomear/extrair Controller existente |
| `commit-discipline` (Tier A) | 1 PR = 1 intent, ≤300 linhas | Sempre |
| `multi-tenant-patterns` (Tier A) | Tier 0 isolation enforcement | Edit em Eloquent Model/Controller/Job |

## Próximo passo (P1, fora desta skill)

**Hook bloqueador `block-incomplete-us-close.ps1`:**

Hoje a skill é **advise** (mostra gaps mas não bloqueia técnico). Pattern atual segue precedente de `mwart-quality` que avisa sem bloquear, e o bloqueio técnico vem via hook PowerShell separado (`block-mwart-violation.ps1`).

Se ROI provado em **5+ audits** (`metrics.audits_run >= 5` + `gaps_fixed_before_done / gaps_detected_total > 0.6`), criar PR específico com:

- `.claude/hooks/block-incomplete-us-close.ps1` — intercepta `mcp__Oimpresso_MCP___Wagner__tasks-update {*} status:done`, verifica se audit rodou nos últimos 7 dias pra `<Modulo>` da US, bloqueia se ❌ pendente sem exceção registrada
- ADR 0NNN documentando aposta + criterio de override (`#module-audit-override <razão>` em PR description)
- Workflow CI `module-audit-gate.yml` análogo a `mwart-gate.yml`

Até lá, skill é advise. Wagner pode ignorar e fechar US mesmo com ❌ — mas tem que registrar exceção em SPEC.md e PR description.

## Histórico de versões

- **v0.1.0** (2026-05-10) — DRAFT inicial. Approach 1+3 aprovado em 2026-05-10 (memory/feedback_module_completeness_audit_approach.md). 8 dimensões definidas a partir do caso US-WA-040. Tier B (auto-trigger por description). Hook bloqueador é P1.

## Referências

- **Caso original:** US-WA-040 / `/whatsapp/settings` 2026-05-10 — 2 gaps detectados em prod (multi-phone scope + UI permissões)
- **Approach aprovado:** `memory/feedback_module_completeness_audit_approach.md`
- **Constituição v2:** [ADR 0094](../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) §4 (loop fechado por métrica) + §5 (SoC brutal) + §6 (multi-tenant Tier 0)
- **Tier B convenção:** [ADR 0095](../../memory/decisions/0095-skills-tiers-convencao-interna.md)
- **Publication policy:** [ADR 0040](../../memory/decisions/0040-policy-publicacao-claude-supervisiona.md) — não criar US sem aprovação humana
- **Skills tiers audit:** `memory/sprints/s3-constituicao/03-skills-audit.md`
- **Test cross-tenant convention:** `memory/feedback_test_biz_99_cross_tenant_convention.md`
- **Skill irmã (gaps mercado):** `.claude/skills/comparativo-do-modulo/SKILL.md`
