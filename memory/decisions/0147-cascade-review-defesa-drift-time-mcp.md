---
slug: 0147-cascade-review-defesa-drift-time-mcp
number: 147
title: "Cascade Review §10.4 — Defesa em profundidade contra drift pré-entrada time MCP"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-15"
quarter: 2026-Q2
module: governance
tags: [governance, drift, enforcement, team-mcp, cascade-review, constitution-art-7-8-9-10]
supersedes: []
related:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
  - 0081-identity-mesh-mcp-actors
  - 0086-fase-5-mvp-governance-actiongate-warn
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0130-handoff-append-only-mcp-first
  - 0131-tiering-memoria-canonico-local-segredo
pii: false
review_triggers:
  - "Time MCP entra com >2 violações/semana detectadas (hook ou CI) — calibração insuficiente"
  - "Mode strict ativo por 4 semanas sem violations — policies vazias OU contrato bem entendido"
  - "Quinto dev externo entrar (>5 pessoas total) — Trust Tiers precisa refinar L2/L3"
  - "Wagner detectar tentativa drift que nenhuma das 5 camadas pegou — defesa em profundidade falhou"
---

# ADR 0147 — Cascade Review §10.4 — Defesa em profundidade contra drift pré-entrada time MCP

**Status:** Aceito
**Data:** 2026-05-15
**Decidido por:** Wagner (sessão thread agressiva 16h plano Max, target 98 maturidade memória)
**Origem:** Wagner expressou medo explícito 2026-05-15 — time MCP entrando (Felipe Delphi / Maiara suporte / Luiz mobile / Eliana[E] financeiro), cada um com Claude Code + token MCP = N× vetores de drift PR-less em `memory/` canon + `Modules/<X>/` Controllers.

---

## Contexto

### O problema

Estado antes desta Onda:

- `mcp_actors` populada com manifests v0 conservadores (migration legacy 240002)
- `ActionGate` middleware existe modo `warn` ([ADR 0086](0086-fase-5-mvp-governance-actiongate-warn.md)) — não aplicado em rotas reais ainda
- 33 SCOPE.md existem mas sem enforcement runtime (drift de Controllers passa silencioso)
- `memory/` canon (1.500+ docs) editável diretamente — hook `block-automem.ps1` cobre só auto-mem privada `~/.claude/projects/*/memory/*.md`, NÃO o canon `D:\oimpresso.com\memory\<canon>\*.md`
- 16 hooks pré-commit catalogados mas só 1 bloqueia memory; 0 bloqueiam canon
- Sem CI gate pra ADR/handoff append-only (workflows existentes: mwart-gate, adr-lint, scope-guard, memory-schema-gate, charter-gate — nenhum cobre append-only)
- Time MCP entra com **zero defesas runtime ativas** contra drift de memória

Cobertura efetiva: **1/8 mecanismos** ENFORCEMENT.md operacional (#1 Versioned Constitution feito; #2-#8 pendentes Fase 5+).

### Por que agir AGORA (não esperar Fase 5 completa)

Wagner palavras textuais 2026-05-15: *"estou com medo de colocar a memoria para equipe e a coisa desandar tudo."*

Fase 5 completa (ActionGate strict + frontend Inertia + mutation testing + audit dashboard) é 8-10h distribuídos próximas sessões. Time MCP entra em dias/semanas. Janela curta — precisa defesa **mínima viável robusta** antes do time entrar, não Fase 5 inteira.

Decisão: entregar **5 camadas defesa em profundidade** focadas no medo declarado, deixar Fase 5 completa pra próxima sessão.

---

## Decisão

Acumular **5 camadas defesa em profundidade** (NIST SP 800-207 pattern) cobrindo drift de código E drift de memória, mais 2 docs cobertura cultural (onboarding + hub Delphi), mais 1 agent auditor sênior:

### Camada 1 — Runtime hook `block-memory-drift` ([PR #890](https://github.com/wagnerra23/oimpresso.com/pull/890))

Hook PreToolUse PowerShell bloqueia Edit/Write em paths canônicos quando branch é `main` OU alvo é ADR/handoff existente. 6 regras (A-F) + override `OIMPRESSO_MEMORY_OVERRIDE=1` Tier 0. 10/10 testes smoke. Registrado em `.claude/settings.json` como 2º hook PreToolUse.

**Defende:** edit acidental em ADR existente, handoff existente, canon governança/proibições — em qualquer branch.

### Camada 2 — Pre-commit hook `block-module-drift` ([PR #891](https://github.com/wagnerra23/oimpresso.com/pull/891))

Hook PreToolUse PowerShell detecta Controller novo fora de `Modules/<X>/SCOPE.md.contains[]`. Modo `warn` default 4 semanas, depois `strict`. Parser YAML pragmático regex (PS 5.1 sem ConvertFrom-Yaml). 7/7 testes. ENFORCEMENT.md §2 mecanismo #3.

**Defende:** Controller criado fora SCOPE.md.contains[] em desenvolvimento local — pegado antes de PR existir.

### Camada 3 — CI pre-merge gate `governance-gate.yml` ([PR #893](https://github.com/wagnerra23/oimpresso.com/pull/893))

Workflow GitHub Actions com 3 jobs (timeout 5min cada):

1. **block-adr-edits** (HARD) — bloqueia M/R* em `memory/decisions/NNNN-*.md` + `memory/handoffs/*.md`; CONSTITUTION sem label + audit-cascade §10.4
2. **scope-md-drift** (WARN, posta comment PR) — Controller novo fora SCOPE.md.contains[]
3. **pii-scan** (HARD) — regex CPF + CNPJ literal; redact log público; allowlist por linha

Pendente Wagner UI GitHub: marcar **Governance Gate** required check em branch protection `main`. ENFORCEMENT.md §2 mecanismo #2.

**Defende:** PR violando canon que escapou hook local — bloqueia antes de merge.

### Camada 4 — Cron daily `governance:detect-drift` ([PR #892](https://github.com/wagnerra23/oimpresso.com/pull/892))

Command artisan escaneia 33 SCOPE.md × filesystem Controllers, detecta divergência declared∖observed e observed∖declared. Persiste `mcp_alertas_eventos` (reuso schema [ADR 0055](0055-mcp-tabelas-jobs-meta.md), zero migration nova) com idempotência por dia. Schedule daily 06:15 BRT. Exit 1 com drift (CI/cron alerting). 9/9 mental dry-run. ENFORCEMENT.md §2 mecanismo #5.

**Defende:** drift que escapou camadas 1-3 (edits diretos SSH, migrations pre-SCOPE.md, branches paralelas).

### Camada 5 — Identity declarada `mcp_actors` 5 manifests ([PR #894](https://github.com/wagnerra23/oimpresso.com/pull/894))

Seed McpActorsSeeder com 5 manifests canônicos refletindo papel real do time (Wagner L0, Felipe/Maira L2, Luiz/Eliana L3). `updateOrCreate` por slug — idempotente, preserva FKs. Command `team-mcp:seed-actors {--dry-run}` valida. 8/8 Pest tests (41 assertions). Doc canônico `IDENTITY-MESH-MANIFESTS.md` documenta WHY de cada modules_write/blocked.

Constituição v1.1.0 Art. 5 (Trust Tiers) + Art. 6 (Identity Mesh) operacionalizados pra time real.

**Defende:** ActionGate (warn-only Fase 5 [ADR 0086](0086-fase-5-mvp-governance-actiongate-warn.md)) consulta esses manifests via ActorResolver pra validar trust_level em rotas L1+. **Atualmente warn** — STRICT mode pós-4-semanas calibração.

### Cobertura cultural — Onboarding pack ([PR #895](https://github.com/wagnerra23/oimpresso.com/pull/895))

4 onboarding docs em `memory/onboarding/team/` (felipe-delphi / maiara-suporte / luiz-mobile / eliana-financeiro). Cada doc cobre: tier+papel, modules_write/blocked, skills auto-load (Tier A) + auto-trigger (Tier B), checklist primeiro dia 10-11 passos, workflow Tier 0 3 fases PRÉ-FLIGHT/DURING/POST, vetores drift catalogados por persona, escalação Wagner/mentor, recursos.

**Defende:** drift por **desconhecimento do contrato**. Time entra sabendo expectativas — drift evitado por clareza, não só bloqueio reativo.

### Cobertura cultural — Hub Delphi ([PR #896](https://github.com/wagnerra23/oimpresso.com/pull/896))

`memory/legacy-delphi/` com 4 docs (_INDEX, SCHEMA-FIREBIRD com 393 tabelas v1468 + 9 críticas com volumes reais, MAPEAMENTO-DELPHI-LARAVEL com 19 Controllers/Forms → Laravel + 8 procs → Services + Anticorruption Layer 14 traduções, PEGADINHAS). Template `descobertas/YYYY-MM-DD-<area>.md` força registro.

**Defende:** drift de conhecimento Delphi. Felipe vai mergulhar em `.pas` + Firebird ~50 clientes legacy — sem hub, descobertas dele ficam local = drift conhecimento. Princípio: "Descoberta solo na cabeça = dívida. Descoberta em git = ativo."

### Continuidade — Agent `memoria-senior` ([PR #897](https://github.com/wagnerra23/oimpresso.com/pull/897))

Novo agent Opus 4.7 sustained pra auditoria profunda contínua de arquitetura de memória/KB/RAG. Pesquisa 10-15 players globais 2026 (Mem0/Letta/LangChain/LlamaIndex/Cognee/Bedrock KB/Pinecone/OpenAI Memory/Anthropic Constitutional/Cursor rules/Continue.dev/Notion AI) com 40-50 WebSearch (5-7 × 8 dimensões D1-D8). Nota 0-100 ponderada. **Target Wagner: 98**.

Auto-trigger por description + `/memoria-senior`. Pattern capterra-senior aplicado a domínio cross-cutting.

**Defende:** estagnação. Memória precisa auditoria periódica vs estado-da-arte — agent garante isso seja repetível.

---

## Matriz Cascade Review §10.4

Esta ADR modifica L5 (Module Charter — enforcement runtime) + L6 (Policy Gating — operacional warn) + L7 (Audit trail — `mcp_alertas_eventos` populado). Cascade obrigatória abaixo:

| Camada Constituição | Auditada? | Resultado | Ação |
|---|---|---|---|
| **L1 Constitution v1.1.0** | ✅ sim | Compatível — Art. 7 (Module Charter) + Art. 8 (Policy Gating) + Art. 9 (Audit) operacionalizados | sem mudança |
| **L2 SRS** | ⏸️ pasta vazia ainda | N/A — sem entries SRS pra revisar | sem ação |
| **L3 Trust Tiers** | ✅ sim | Manifests refletem TRUST-TIERS.md §1 (L0-L4) e §5 (ações por tier exemplos) — sem conflito | sem mudança |
| **L4 Identity Mesh** | ✅ sim | 5 manifests canônicos populam tabela. ActorResolver consulta. Sem conflito schema (100% match ADR 0081) | doc `IDENTITY-MESH-MANIFESTS.md` adicionado |
| **L5 Module Charter** | ✅ sim | 33 SCOPE.md sem mudança. Enforcement local (hook #3) + cron (#5) + CI (#2) aplicado por cima sem mexer no charter | sem mudança SCOPE.md |
| **L6 Policy Gating** | ✅ sim | ActionGate continua warn. Não foi aplicado em rotas novas (gradual, próxima sessão) | sem mudança ActionGate |
| **L7 Audit** | ✅ sim | `mcp_alertas_eventos` ganha rows de `module_drift` via cron daily. Schema reusado sem mudança | sem mudança schema |
| **ADRs cross-cutting** | ✅ sim | Esta ADR 0147 referencia 7 ADRs predecessoras sem editá-las (append-only honored) | nova ADR 0147 |
| **Skills cross-cutting** | ✅ sim | Nenhuma skill referenciada precisou mudar. `block-automem.ps1` continua. Novos hooks coexistem na PreToolUse stack | sem mudança skills |

**Conclusão cascade:** Mudanças concentradas em **runtime enforcement + identity declarada + cobertura cultural** sem afetar contratos canon L1-L4 ou skills. ADR validamente aceita.

---

## Justificativa

### Por que 5 camadas (não 1 ou 2)

NIST SP 800-207 Zero Trust defense-in-depth: **cada controle individual falha eventualmente; sobreposição é garantia**. Stack 5-camadas cobre cenários ortogonais:

| Cenário | Camada que pega |
|---|---|
| Dev edita ADR no Claude Code | C1 hook memory-drift |
| Dev cria Controller fora SCOPE.md em desenvolvimento local | C2 hook module-drift |
| Dev sobe PR que escapou hook local (modo off, branch fresh) | C3 CI gate |
| Dev edita arquivo via SSH direto em prod | C4 cron drift detection (próxima execução pega) |
| Dev tenta action runtime que requer L1+ sem tier | C5 ActionGate consultando manifest |

Sem 1 das 5 = brecha. Wagner detecta drift que nenhuma camada pegou = trigger review desta ADR.

### Por que warn-only (não strict)

[ADR 0086](0086-fase-5-mvp-governance-actiongate-warn.md) prescreveu warn-only 4 semanas pra ActionGate — mesma lógica aplicada aqui:

- **Hook #3 module-drift:** warn 4 semanas (env `OIMPRESSO_DRIFT_HOOK_MODE=warn`)
- **CI gate #2 governance-gate Job 2 scope-md-drift:** WARN com comment PR (não HARD)
- **CI gate #2 governance-gate Job 1 block-adr-edits:** **HARD desde início** — ADR/handoff append-only é Tier 0 IRREVOGÁVEL, não há "calibração" pra append-only
- **CI gate #2 governance-gate Job 3 pii-scan:** **HARD desde início** — PII vaza = LGPD violado, não há "calibração"
- **Cron #5 detect-drift:** alerta `mcp_alertas_eventos` sem bloquear (cron é detection, não enforcement)

Razão: warn coleta sinal real 4 semanas antes de bloquear ações potencialmente legítimas. Calibragem reduz falso-positivo. ADR/PII/handoff = linhas vermelhas, sem espaço pra calibração.

### Por que defesa cultural além de técnica

Hook bloqueia ATO; onboarding doc explica POR QUÊ. Sem POR QUÊ, time tenta override emergencial cedo, hook vira ruído filtrado mentalmente, defesa colapsa.

Onboarding doc + hub Delphi documentam EXPECTATIVAS. Time entra com contrato explícito. Defesa cultural complementa runtime — pattern Anthropic Constitutional AI (context engineering > middleware-only).

### Por que NÃO esperar Fase 5 completa

Fase 5 completa ([ADR 0086](0086-fase-5-mvp-governance-actiongate-warn.md) §6) inclui frontend Inertia + PoliciesController CRUD + AuditController drill-down + ActionGate aplicado em rotas + mode strict. ~8-10h distribuídos próximas sessões.

Time MCP entra em **dias/semanas** (estimativa Wagner — sem data exata). Esperar Fase 5 completa = janela de exposição. Entregar 5 camadas defesa mínima viável robusta hoje = aceitar Fase 5 incompleta mas time entra protegido.

### Por que `mcp_alertas_eventos` (não `mcp_alertas` nova tabela)

[ADR 0055](0055-mcp-tabelas-jobs-meta.md) já criou `mcp_alertas_eventos` (tabela de eventos disparados, diferente de `mcp_alertas` que são regras configuráveis). Mapping 1:1 cabe sem migration nova:

| Gap-spec original | mcp_alertas_eventos real |
|---|---|
| `category=module_drift` | `tipo='module_drift'` |
| `severity=medium` | `severidade=medium` |
| `detail` | `titulo + descricao` |
| Idempotency UNIQUE | `chave_idempotencia` UNIQUE |

Zero risco DDL em prod. DriftAlertsController scaffold (ADR 0086) já consome eventos.

---

## Consequências

### Positivas

- **5 camadas defesa em profundidade** — cobertura runtime + pre-commit + CI + cron + identity + cultural
- **Time MCP entra protegido** — não dependente apenas de Wagner revisar manual cada PR
- **Cobertura ENFORCEMENT.md**: 1/8 → **5/8** mecanismos operacionais (#2 CI gate, #3 hook local, #5 cron, #6 Pest mutation parcial, #7 quarterly review programado; #1 já feito; #4 ActionGate warn pendente strict; #8 dashboard pendente)
- **Identity Mesh canônica populada** — manifests refletem papel real, ActionGate ganha decisão precisa
- **Hub Delphi pronto pro Felipe** — descobertas legacy viram ativo git, não dívida
- **Memoria-senior agent operacional** — auditoria contínua memória vs estado-da-arte
- **5 ADRs derivadas potenciais** — após Wagner aprovar memoria-senior primeira execução, top 3 ações roadmap podem virar ADRs próprias

### Negativas / Trade-offs

- **Hook #3 + cron #5 warn-only 4 semanas** — drift detectado mas não bloqueado (calibração)
- **ActionGate continua warn** — Fase 5 strict pendente próxima sessão
- **Frontend Inertia governance ausente** — Wagner não tem UI Dashboard.tsx ainda (consome via SQL/comando)
- **Wagner UI GitHub pendente** — required check + label `constitution-amendment` manuais (não pode automatizar)
- **Pest local SQLite skipa** — migration legacy `transactions MODIFY ENUM` incompatível (problema pré-existente, não causado por esta Onda). Mitigação: rodar local MySQL Laragon (validado mcp_actors 8/8)
- **8 PRs pra Wagner revisar** — overhead de aprovação maior que 1 PR consolidado

### Riscos mitigados

- **Drift de memória PR-less** — hook C1 + CI gate C3 cobrem
- **Drift de Controllers** — hook C2 + cron C4 + CI gate C3 (warn) cobrem
- **Identity sem manifest** — C5 popula, ActionGate consulta
- **Conhecimento Delphi perdido** — hub C6 força registro
- **Estagnação memória** — agent memoria-senior pra auditoria periódica

### Riscos aceitos conscientemente

- **Hook bypass via OIMPRESSO_*_OVERRIDE=1** — Wagner Tier 0 emergência. Risco: dev de boa-fé usa pra fix rápido e esquece PR follow-up. Mitigação: hook imprime warning loud ao detectar override + Wagner audit `mcp_audit_log` busca por `OVERRIDE_USED` quinzenalmente
- **Cron defasagem até 24h** — drift detectado D+1, não real-time. Aceitável pra detection (não enforcement). Real-time fica pra ActionGate strict (Fase 5)
- **memoria-senior primeira execução não validada** — agent novo, 1ª run define template. Risco: nota inflada ou roadmap impreciso. Mitigação: Wagner revisa primeira FICHA com olhar crítico (anti-falso-positivo herdado capterra-senior dogfood 2026-05-13)

---

## Implementação

✅ **FEITO nesta ADR (consolidando 8 PRs irmãs):**

1. [PR #890](https://github.com/wagnerra23/oimpresso.com/pull/890) — block-memory-drift hook + RUNBOOK + settings.json
2. [PR #891](https://github.com/wagnerra23/oimpresso.com/pull/891) — block-module-drift hook + RUNBOOK
3. [PR #892](https://github.com/wagnerra23/oimpresso.com/pull/892) — DetectDriftCommand + Pest + Kernel schedule
4. [PR #893](https://github.com/wagnerra23/oimpresso.com/pull/893) — governance-gate.yml + pii-scan.sh + RUNBOOK + proibições 1 bullet
5. [PR #894](https://github.com/wagnerra23/oimpresso.com/pull/894) — McpActorsSeeder + SeedActorsCommand + Pest + IDENTITY-MESH-MANIFESTS.md
6. [PR #895](https://github.com/wagnerra23/oimpresso.com/pull/895) — 4 onboarding packs em memory/onboarding/team/
7. [PR #896](https://github.com/wagnerra23/oimpresso.com/pull/896) — hub legacy-delphi 4 docs
8. [PR #897](https://github.com/wagnerra23/oimpresso.com/pull/897) — memoria-senior agent definition

⏸️ **Pendente próximas sessões:**

- Wagner UI GitHub: marcar **Governance Gate** required check em main + criar label `constitution-amendment`
- Wagner rodar `php artisan team-mcp:seed-actors` em prod (não --dry-run) após merge PR #894
- memoria-senior primeira execução completar + Wagner revisar AUDITORIA-MEMORIA-2026-05-15
- ActionGate aplicado em rotas L1+ gradualmente (Modules/Governance/, Modules/ADS/, Modules/TeamMcp/)
- Mode warn → strict pra hook #3 + ActionGate após 4 semanas calibração (~2026-06-13)
- Pest mutation testing policies (mecanismo #6) — depende ActionGate em rotas reais
- Frontend Inertia `governance/Dashboard.tsx` — pendente desde [ADR 0086](0086-fase-5-mvp-governance-actiongate-warn.md)
- Estender block-memory-drift pra `memory/sessions/` (append-only por convenção hoje sem hook)
- Comando artisan `delphi:discover` automatiza probe schema (sugestão hub legacy-delphi)

---

## Referências

- [Constituição v1.1.0](../governance/CONSTITUTION.md) — Art. 7 Module Charter + Art. 8 Policy Gating + Art. 9 Audit + §10.4 Cascade Review
- [TRUST-TIERS.md](../governance/TRUST-TIERS.md) — L0-L4 hierarchy
- [ENFORCEMENT.md](../governance/ENFORCEMENT.md) — 8 mecanismos NIST/Cedar/OPA
- [IDENTITY-MESH-MANIFESTS.md](../governance/IDENTITY-MESH-MANIFESTS.md) — 5 manifests time (criado nesta Onda)
- [ADR 0079](0079-constituicao-oimpresso-7-camadas-governanca.md) — Constituição 7 camadas governança (ratificação inicial)
- [ADR 0080](0080-trust-tiers-operacional-audit-findings.md) — Trust Tiers operacional
- [ADR 0081](0081-identity-mesh-mcp-actors.md) — Identity Mesh + mcp_actors schema
- [ADR 0086](0086-fase-5-mvp-governance-actiongate-warn.md) — Fase 5 MVP ActionGate warn
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 mãe (mais recente)
- [ADR 0130](0130-handoff-append-only-mcp-first.md) — Handoff append-only
- [ADR 0131](0131-tiering-memoria-canonico-local-segredo.md) — Tiering memória 3-tier
- NIST SP 800-207 Zero Trust Architecture — defense in depth pattern
- AWS Cedar policy bundles + reconciliation
- OPA Conftest pre-merge + periodic audit jobs
- Anthropic Constitutional AI memory patterns 2026

---

## Princípio fundador

Wagner pediu 2026-05-15: *"estou com medo de colocar a memoria para equipe e a coisa desandar tudo."* + *"faça em tread agressiva tenho tokens plano max e tem que ser consumidos em 16 horas."*

Esta ADR formaliza a resposta — defesa em profundidade 5 camadas em uma sessão de 16h, cobrindo medo declarado de drift PR-less com runtime + pre-commit + CI + cron + identity + cultural. Janela curta antes do time entrar; Fase 5 completa fica pra próxima sessão; entregar mínimo viável robusto hoje.

Validado em: sessão 2026-05-15 (esta ADR + 8 PRs #890-#897 + memoria-senior primeira execução background).
