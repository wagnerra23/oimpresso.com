---
name: module-completeness-audit
mission: "Substituir auditoria humana de governança interna de módulo por checklist objetivo que bloqueia US `status:review→done` sem cobertura, fechando o loop métrica da Constituição v2 §4."
description: ATIVAR antes de marcar US como `done` (`tasks-update task_id:US-XXX-NNN status:done` ou `tasks-update from:review to:done`), OU quando user pedir "auditar completude de {modulo}", "{modulo} está pronto?", "checklist de governança {modulo}", "/module-completeness-audit {modulo}", "está pronto pra fechar?", "vai poder marcar done?". Roda 8-10 verificações objetivas (multi-instance scope, permissions UI, charter, RUNBOOK, Pest cobertura, AuditLog write, business_id scope, browser MCP smoke salvo) cruzando código + memory/requisitos + Charter + browser MCP screenshots. Bloqueia transição se faltar item. Complementa skill `comparativo-do-modulo` (que detecta gaps mercado) — esta detecta gaps governança interna.
type: process-skill
status: active
version: 1.1.0
trust_level: L2
owner: wagner
created_at: 2026-05-10
updated_at: 2026-06-05
charter_adr: 0094
parent_mission: "Toda skill substitui trabalho humano repetitivo com ROI provado, rumo ao ERP autônomo de R$ [redacted Tier 0]M em 24 meses."
triggers_on:
  - "tasks-update {task_id} status:done"
  - "tasks-update from:review to:done"
  - "marcar {task_id} done"
  - "fechar US {task_id}"
  - "/module-completeness-audit {modulo}"
  - "/audit-completude {modulo}"
  - "{modulo} está pronto?"
  - "checklist de governança {modulo}"
  - "está pronto pra fechar?"
  - "auditar completude {modulo}"
does_not_trigger_on:
  - tasks-update status:doing|review|blocked (só na transição final → done)
  - comparar módulo com mercado (use `comparativo-do-modulo`)
  - criar módulo novo (use `criar-modulo`)
  - migração Blade→Inertia (use `mwart-process` + `mwart-quality`)
  - revisar PR (use `/review` ou `/ultrareview`)
roi_metric:
  type: time + quality
  baseline: "Wagner audita manualmente cada US no review→done (~15min) — frequente caso 'esqueci de cobrir Pest / Charter ausente / sem AuditLog' detectado em prod (ex: /whatsapp/settings em 2026-05-10 com falta de multi-phone scope + UI permissões)"
  target: "Reduz pra ~30s automatizado + bloqueia transição com checklist red. Detecta 80%+ gaps governança antes de mergeado. ROI: evita 1 retrabalho/semana (≈2h)."
metrics:
  audits_executados: 0
  audits_aprovados: 0
  audits_bloqueados: 0
  gaps_detectados_total: 0
  modulos_cobertos: []
artefatos_governados:
  - "memory/requisitos/{Modulo}/AUDIT-LOG.md (apêndice de cada audit run)"
  - "mcp_tasks via tasks-comment (resultado registrado na própria US)"
  - "mcp_tasks via tasks-update (BLOQUEIA transição se reprovado)"
tier: B
parent_adr: 0094
related_adrs: [0089, 0093, 0095, 0101, 0104, 0110]
---

# module-completeness-audit (v1.0)

Skill que **bloqueia** a transição `review → done` de uma US se o módulo não cobrir o checklist objetivo **pontuado** de 9 dimensões de governança interna (score estilo Bateria §9 do [`PROCESSO_MEMORIA_CC`](../../../prototipo-ui/PROCESSO_MEMORIA_CC.md): corte **≥90**, qualquer check **duro** ❌ = **INVÁLIDO**). É o gate pré-adoção (Peça 2 do anti-regressão do código, Opção B).

Diferente de [`comparativo-do-modulo`](../comparativo-do-modulo/SKILL.md) (que mede gap **vs mercado**), esta mede gap **vs nossa própria Constituição v2** ([ADR 0094](../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)).

## Origem (2026-05-10)

Wagner detectou em prod (`/whatsapp/settings` Hostinger) **2 gaps que escaparam do review→done** das 9 US Whatsapp do CYCLE-04:

1. Falta tela multi-instance scope (1 phone por business hoje, US-WA-040 cobre só parcialmente)
2. Falta tela permissões UI (middleware `can:whatsapp.*` existe, mas sem UI dedicada — cai no Roles UltimatePOS padrão)

Pergunta dele: "como automatizar essas verificações? deveria ter uma maneira de isso sempre ser feito". Esta skill é a resposta aprovada (approach 1+3 — ver auto-mem `feedback_module_completeness_audit_approach.md`).

## Quando ativar

**Hard trigger (BLOQUEADOR):**
- Antes de chamar tool MCP `tasks-update` com `status: done` (ou `from: review, to: done`)
- Antes de mergear PR cuja branch contém commits que fecham US (`Refs: US-XXX-NNN`)

**Soft trigger (sob demanda):**
- `/module-completeness-audit {Modulo}` — roda audit do módulo inteiro
- "Modules/X está pronto pra fechar?" — Wagner pergunta livre
- "checklist de governança Modules/X" — review proativo

**Não ativar:**
- `tasks-update status:doing|review|blocked` (só na transição final pra done)
- Comparar com mercado (use `comparativo-do-modulo`)
- Criar módulo novo (use `criar-modulo`)

## Os 9 itens do checklist (pontuados)

Cada item é **binário** (✅/❌) com evidência citada em arquivo. Item 🟡 (parcial) conta como ❌ até evidência completa. **4 itens são DUROS** (#5, #7, #8, #9) — qualquer duro ❌ = **INVÁLIDO** (não fecha US), independente do score. Pontuação na §"Veredito" abaixo.

### 1. Multi-instance scope decidido e documentado

**Pergunta:** Esta entidade é 1-per-business OU N-per-business? E o schema reflete isso?

**Aprovado se:**
- `memory/requisitos/{Modulo}/SPEC.md` declara explicitamente em `## Modelagem` ou `## Decisões`: "1 X por business" OU "N X por business"
- Migration tem `UNIQUE(business_id)` (caso 1-per) ou índice em `(business_id, scope_key)` (caso N-per)
- US correspondente cita decisão na descrição

**Reprovado se:**
- Schema permite N mas SPEC só fala em 1 (caso /whatsapp/settings em 2026-05-10)
- US fala em "número primário" sem decidir multi-phone

### 2. Permissões: middleware E UI

**Pergunta:** Toda rota tem `can:*` + existe UI pra atribuir/visualizar essa permissão?

**Aprovado se:**
- `Modules/{Modulo}/Routes/web.php` toda rota mutadora tem `->middleware('can:{modulo}.{ação}')`
- Permissão registrada em `Modules/{Modulo}/Database/Seeders/{Modulo}PermissionsSeeder.php`
- UI existe: tela em `/admin/roles` reconhece + (opcional) tela dentro do módulo `/modulo/permissions` pra escopo per-resource

**Reprovado se:**
- Middleware `can:*` existe mas seed da permission não foi rodado
- Permission existe mas sem UI (Wagner não consegue dar acesso por usuário sem editar DB direto)
- Falta scope per-resource quando módulo tem N entidades por business (ex: per-phone-number scope no Whatsapp)

### 3. Charter `.charter.md` ao lado do `.tsx`

**Pergunta:** Toda Page Inertia tem charter declarando invariantes UX/Business?

**Aprovado se:**
- Cada `resources/js/Pages/{Modulo}/*.tsx` tem `*.charter.md` ao lado
- Charter tem frontmatter `mission:`, `non_goals:`, `automation_hooks:`, `anti_hooks:`
- `status: live` ratificado por Wagner

**Reprovado se:**
- `.tsx` existe sem `.charter.md` (skill `charter-first` Tier A dormente vai bloquear quando S4 ativar)
- Charter `status: draft` em US done

### 4. RUNBOOK do módulo

**Pergunta:** Existe `RUNBOOK-*.md` em `memory/requisitos/{Modulo}/` cobrindo tela/feature?

**Aprovado se:**
- `memory/requisitos/{Modulo}/RUNBOOK-{tela-kebab}.md` existe pra cada tela MWART
- RUNBOOK segue 11 seções obrigatórias (skill `cockpit-runbook`)
- Snippets PT-BR executáveis + clickable links

**Reprovado se:**
- Tela MWART sem RUNBOOK (já bloqueado por hook `block-mwart-violation.ps1` em runtime, mas validar)
- RUNBOOK incompleto (faltando seção)

### 5. Pest cobre golden path + edge case

**Pergunta:** Tem teste pra (a) caso feliz, (b) caso anti-tenancy, (c) caso edge?

**Aprovado se:**
- Cada Controller `store/update/destroy` tem test em `Modules/{Modulo}/Tests/Feature/`
- Pelo menos 1 test de **isolamento multi-tenant** (biz=1 vs biz=99 — convenção `feedback_test_biz_99_cross_tenant_convention.md`)
- 1 test de **autorização** (user sem `can:*` recebe 403)
- Comando local: `vendor\bin\pest Modules/{Modulo}` PASSA ✅

**Reprovado se:**
- `Modules/{Modulo}/Tests/` não registrado em `phpunit.xml` (ADR 0070 — tests fantasma sem CI)
- Doc-comment `/** @test */` (PHPUnit 12 desabilita silenciosamente — ver `08-handoff.md`)

### 6. AuditLog write em mutações sensíveis

**Pergunta:** Mutações que afetam config, permission ou dados regulados gravam audit log?

**Aprovado se:**
- Cada Controller que muda `WhatsappBusinessConfig` / Roles / dados fiscais chama `AuditLog::write(...)`
- Tabela `audit_logs` tem registros pra US do escopo (smoke local: rodar mutação + verificar `audit_logs.where('actor_id', auth()->id())`)

**Reprovado se:**
- Update em config sem audit (Wagner não consegue rastrear "quem mudou meu Z-API token")
- AuditLog grava só em `dev` mas não em `prod` (.env ENV-gated)

### 7. Multi-tenant `business_id` global scope (Tier 0)

**Pergunta:** Eloquent Model tem global scope? Job assíncrono passa `$businessId` no constructor?

**Aprovado se:**
- Cada Model em `Modules/{Modulo}/Entities/` (ou `Models/`) que toca dados de negócio tem trait `BelongsToBusiness` (ou equivalente — verificar [ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
- Cada Job em `Modules/{Modulo}/Jobs/` tem `$businessId` no constructor (NUNCA `session()`)
- Migration tem `business_id` indexado + FK
- 0 ocorrências de `withoutGlobalScopes()` sem comentário `// SUPERADMIN: <razão>`

**Reprovado se:**
- Job lê `session('user.business_id')` (vai dar `null` em fila)
- Model sem global scope (vaza dados cross-tenant)

### 8. Browser MCP smoke salvo

**Pergunta:** Existe screenshot salvo provando que tela renderiza em `oimpresso.test` (local) E `oimpresso.com` (prod) pelo menos 1× depois do merge?

**Aprovado se:**
- `memory/requisitos/{Modulo}/smoke/{tela}-{YYYY-MM-DD}.png` (screenshot Chrome MCP)
- Screenshot mostra header + 1 fluxo principal + ausência de errors (console limpo)
- Tirado em ambiente prod (não só local)

**Reprovado se:**
- US done sem smoke (Wagner descobre quebrado pelo cliente)
- Smoke só em local — não validou prod (ex: caso Whatsapp 2026-05-10 onde local mostrava inbox vazia mas prod tinha mensagens reais)

### 9. Teste ancorado em CONTRATO, não no código (DURO — novo v1.1)

**Pergunta:** Cada teste novo/alterado deriva de um contrato **externo** (SPEC / ADR / proibicoes / charter / casos) citado — e não da implementação?

**Aprovado se:**
- O teste cita no cabeçalho/doc-comment a fonte do contrato (ex: `@see ADR 0143` + a regra em português que ele prova)
- A asserção corresponde à **regra do contrato**, não ao que a classe já faz
- Invariante de negócio escrita a partir da proibição/SPEC, não copiada do comportamento atual

**Reprovado se:**
- Invariante/asserção extraída do código (tautológico) — passa ✅ mesmo se o comportamento estiver errado vs a intenção, **travando o drift** em vez de pegá-lo. Ver [§"Ideias avaliadas e DESCARTADAS"](../../../memory/proibicoes.md) entrada 2026-06-05 "Teste tautológico".
- Teste sem nenhuma âncora de contrato citada.

**Por que DURO:** teste tautológico é **pior que não ter teste** (catraca o desvio). NÚCLEO invariante 4 do método: *"Casos = contrato de não-regressão"*. Caso real: `FsmAuthorizationFlagPropertyTest` (2026-06-05) derivou invariantes da classe, não do contrato ADR 0143.

## Veredito — pontuação (Bateria §9 portada)

> Porta a régua do [`PROCESSO_MEMORIA_CC`](../../../prototipo-ui/PROCESSO_MEMORIA_CC.md) §9 pro mundo de código. **Não é bateria nova** — é a mesma lógica, sistema separado (Opção B).

- **Peso:** cada check = 1 ponto. Os **4 duros (#5, #7, #8, #9) contam dobrado.** Total possível = **13**.
- **Corte duro:** qualquer check DURO ❌ → **INVÁLIDO** (não fecha US), independente do score.
- **Senão, pelo score%** (pontos ÷ 13):
  - **≥90%** → ✅ APROVADO (pode marcar done)
  - **75–89%** → 🟡 CONDICIONAL (corrige os ❌ e re-roda; não fecha ainda)
  - **<75%** → ❌ INVÁLIDO
- Score registrado no `AUDIT-LOG.md` junto do resultado (§7) — alimenta a métrica de drift (Peça 3).

## Os 7 passos do ciclo

### 1. Detectar trigger

```
SE tool call = `tasks-update` com `status: done` ou `from: review, to: done`:
  → resgatar `task_id`, ler `tasks-detail` pra pegar `module`
  → rodar audit ANTES de executar o update
SE user pediu "/module-completeness-audit {Modulo}":
  → rodar audit do módulo inteiro (todos os items, não só task-specific)
SE user perguntou "está pronto?":
  → confirmar qual módulo + rodar audit
```

### 2. Validar pré-condições

```
- Modules/{Modulo}/ existe?
- memory/requisitos/{Modulo}/SPEC.md existe?
- task_id (se trigger por tasks-update) está em status `review`?
SE algo faltar: parar, instruir Wagner.
```

### 3. Rodar os 8 checks (paralelo onde possível)

Para cada check:
- Ler arquivos relevantes (Glob + Read)
- Aplicar critério ✅/❌
- Coletar evidência (citar arquivo:linha)

```
Check 1 → Read SPEC.md + Glob Database/Migrations/ → grep UNIQUE(business_id)
Check 2 → Read Routes/web.php + Glob PermissionsSeeder + check UI roles
Check 3 → Glob resources/js/Pages/{Modulo}/*.tsx + check sibling .charter.md
Check 4 → Glob memory/requisitos/{Modulo}/RUNBOOK-*.md
Check 5 → Read phpunit.xml + Glob Modules/{Modulo}/Tests/ + Bash `vendor\bin\pest Modules/{Modulo} --no-coverage --stop-on-failure`
Check 6 → Grep AuditLog::write em Modules/{Modulo}/Http/Controllers/
Check 7 → Grep BelongsToBusiness + grep withoutGlobalScopes
Check 8 → Glob memory/requisitos/{Modulo}/smoke/*.png (data > merge da US)
Check 9 → grep doc-comment (@see/ADR/SPEC/proibicoes/charter) em cada *Test.php novo/alterado; conferir asserção vs regra do CONTRATO (não vs a classe)
```

### 4. Compilar resultado

Tabela com 9 linhas + linha de **Veredito** (score/13 + duros ❌ se houver):

```markdown
| # | Check | Status | Evidência | Próximo passo |
|---|-------|--------|-----------|----------------|
| 1 | Multi-instance scope | ✅ | SPEC.md:42 declara "1 config per business" + UNIQUE(business_id) em 2026_01_15_create_whatsapp_business_configs | — |
| 2 | Permissions UI | ❌ | Routes/web.php tem can:whatsapp.* mas sem PermissionsSeeder + sem UI per-phone | Criar US-WA-041 + seed |
| ... |
```

### 5. Apresentar resultado pro Wagner

```
🔍 Audit completude — Modules/{Modulo} ({task_id ou "módulo inteiro"})

✅ Aprovados: {N}/9  ·  Score: {pontos}/13 ({pct}%)
❌ Reprovados: {M}  {duros ❌: [#5/#7/#8/#9] se houver}
Veredito: {APROVADO ≥90 / CONDICIONAL 75-89 / INVÁLIDO <75 OU qualquer duro ❌}

Reprovados:
- #2 Permissions UI: {evidência curta}
- #6 AuditLog: {evidência curta}

Decisão:
- BLOQUEIO transição review→done desta US
- Sugiro criar 2 US novas pros gaps. Aprova? (s/n/lista IDs)
```

### 6. Decisão Wagner → ação

**Se Wagner aprovar override** ("override #2", "force done", "vai assim mesmo"):
- Registrar override em `memory/requisitos/{Modulo}/AUDIT-LOG.md` com justificativa
- Permitir transição (registra ADR `lifecycle: historical` se >2 overrides na mesma US)
- Apender comment na US via `tasks-comment`

**Se Wagner aprovar criar US gaps**:
- `tasks-create` no MCP pra cada gap (priority herda do score do check)
- Apender no SPEC.md
- Bloquear transição da US original até gaps fecharem

**Se Wagner pedir fix antes**:
- Listar próximos passos concretos
- NÃO transicionar US

### 7. Apender em AUDIT-LOG.md

```markdown
## {YYYY-MM-DD HH:MM} — {Modulo} — {task_id ou "full module"}

- Checks: ✅{N} ❌{M}
- Reprovados: [#2 Permissions UI, #6 AuditLog]
- Decisão Wagner: {override / criou US / pediu fix}
- US criadas: [US-XXX-NNN, ...]
- Próxima ação: {fix antes / aguarda gaps / done liberado}
```

Atualizar `metrics:` no frontmatter desta skill.

## Saída final pro Wagner

```
🔍 Audit completude — Modules/{Modulo}

Resultado: {APROVADO ✅ / BLOQUEADO ❌}

[se aprovado]
✅ 9/9 checks · score 13/13 (100%). Pode marcar US-XXX-NNN como done.

[se bloqueado]
❌ {M} reprovados. Bloqueio transição review→done.
Detalhes: AUDIT-LOG.md:linha
{N} US novas criadas pros gaps: [...]

Próximo passo: {Wagner decide}
```

## Reprovações (não fazer)

- ❌ Marcar US done sem rodar audit (silenciar a skill)
- ❌ Override sem justificativa (vira débito sem rastreio)
- ❌ Inventar evidência (cada ✅ precisa citar arquivo:linha)
- ❌ Rodar audit sem SPEC.md ou sem `Modules/{X}/` existir
- ❌ Bloquear PR sem dar plano concreto de fix
- ❌ Substituir `comparativo-do-modulo` (skills complementares — esta = governança, aquela = mercado)

## Métricas a popular

A cada execução, atualizar `metrics:` no frontmatter:
- `audits_executados` += 1
- `audits_aprovados` += 1 (se passou todos)
- `audits_bloqueados` += 1 (se reprovou ≥1)
- `gaps_detectados_total` += N
- `modulos_cobertos` adicionar `{Modulo}` se primeiro audit

## Roadmap (Fase 2)

- **CI gate** (`audit-gate.yml` workflow): falha PR se branch fecha US sem audit ✅
- **Pest custom assertion** `assertModuleAuditPasses({Modulo})` — testa em CI
- **Tela admin** `/admin/audit-log` renderiza histórico AUDIT-LOG.md cross-módulos
- **Integração com `comparativo-do-modulo`**: roda os 2 em batch quando user pede "fechar sprint"

## Não fazer (descartado pelo Wagner em 2026-05-10)

- ❌ **Cron mensal** rodando audit sozinho — pouco ROI até ter histórico do (1) `/comparativo`. Reavaliar quando esta skill tiver 5+ módulos cobertos.
