---
slug: oimpresso-enforcement
title: "Enforcement — 8 mecanismos NIST/Cedar/OPA aplicados às 7 camadas"
type: governance-spec
authority: canonical
lifecycle: ativo
version: 1.1.0
maintained_by: wagner
last_updated: 2026-07-19
charter_adr: 0080
related:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
pii: false
---

# Enforcement — Como as 7 camadas são protegidas em runtime

> **Versão 1.1.0 — 2026-07-19** (crosswalk regulatório §7 sobre a base v1.0.0 2026-05-05)
> **Hierarquia:** subordinada à [Constituição](CONSTITUTION.md) v1.1.0

A Constituição (L1) e camadas L2-L7 são **regras**. Sem mecanismos de enforcement, regra é teatro. Este documento mapeia os **8 mecanismos** que tornam o framework operável — convergência canônica de **NIST Zero Trust SP 800-207**, **AWS Cedar**, **OPA (Open Policy Agent)**, **Anthropic Constitutional AI**.

Não inventamos. Reusamos pattern formal da indústria.

---

## §1. Tabela mestre — 8 mecanismos × 7 camadas

| # | Mecanismo | Estado-da-arte de origem | Protege camada | Status oimpresso |
|---|---|---|---|---|
| 1 | **Versioned Constitution** (semver + amendments) | Anthropic Constitutional AI; versioned policies em OPA | L1 | ✅ implementado (CONSTITUTION.md v1.1.0 + amendments[]) |
| 2 | **Pre-merge gate (CI)** | OPA Conftest; AWS Cedar in CI; GitHub Actions | L1, L2, L5, L7 | ⏸️ Fase 5 — falta GitHub Action |
| 3 | **Pre-commit hook** | Husky/Lefthook + OPA local; NIST SP 800-207 endpoint validation | L5 (Module Charter) | ⏸️ Fase 3.6 — drift detection warn-only |
| 4 | **ActionGate middleware (runtime gate)** | OPA decision API; AWS Cedar `is_authorized()`; NIST PEP/PDP | L3, L4, L5, L6 | ⏸️ Fase 5 — Modules/Governance/ + middleware |
| 5 | **Drift detection cron** | Cedar policy bundles + reconciliation; OPA periodic audit | L5 (Module Charter) | ⏸️ Fase 3.5 — depois de mcp_modules cache |
| 6 | **Mutation testing** | NIST policy testing; OPA policy unit tests; Cedar test framework | L6 (Policy Gating) | ⏸️ Fase 5 — Pest tests gerados a partir de mcp_governance_rules |
| 7 | **Quarterly constitutional review** | NIST 800-207 §7 governance review; ISO 27001 management review | L1, L3, L4 | ⏸️ pattern definido, primeira ocorrência 2026-08-05 |
| 8 | **Public audit dashboard** | NIST 800-207 §6 monitoring + observability; Cedar audit logs | L7 (Audit Trail) | ⏸️ Fase 5 — UI `/governance/audit` |

**Status global:** 1/8 implementado, 7/8 distribuídos em ADR 0079 Fases 3-5.

---

## §2. Os 8 mecanismos detalhados

### 1. Versioned Constitution

**O que é.** A Constituição (L1) é versionada por semver. Cada amendment vira novo registro em `amendments[]` no frontmatter. Mudanças em Articles geram MAJOR (X.0.0); adições MINOR (1.X.0); patches PATCH (1.0.X).

**Como aplica.**
- ADR formal → version bump → entry em amendments[]
- Cascade Review §10.4 obrigatória pra MAJOR e MINOR
- `git log memory/governance/CONSTITUTION.md` mostra evolução
- Audit reports vinculam à versão (`audit-YYYY-MM-DD-vX.Y.md`)

**Fonte canônica.** Anthropic Constitutional AI (versioned principles); OPA bundle versions; AWS Cedar policy versioning.

**Status oimpresso.** ✅ Implementado.
- v1.0.0 (2026-05-05 ratificação)
- v1.1.0 (2026-05-05 §10.4 cascade) — atual

---

### 2. Pre-merge gate (CI)

**O que é.** GitHub Action que **bloqueia** PR se mudança em camadas críticas não vier acompanhada de artefatos exigidos.

**Regras propostas (Fase 5):**

| PR mexe em | Exige | Senão |
|---|---|---|
| `memory/governance/CONSTITUTION.md` | ADR linkado + audit cascata | ❌ block |
| `memory/governance/srs/*` | ADR linkado | ❌ block |
| `Modules/<X>/SCOPE.md` | ADR de origem ou patch trivial | ⚠️ warn |
| `Modules/<X>/Http/Controllers/*` (novo) | controller listado em SCOPE.md.contains[] | ❌ block |
| `mcp_audit_log` (qualquer mudança) | — | ❌ **always block** (immutable) |
| `mcp_governance_rules` | ADR linkado + Wagner explícito | ❌ block |

**Fonte canônica.** OPA Conftest pre-merge; AWS Cedar in CI/CD pipelines; NIST SP 800-207 §6.

**Status oimpresso.** ⏸️ Fase 5. Implementação: `.github/workflows/governance-gate.yml` com OPA-style checks via shell scripts.

---

### 3. Pre-commit hook (drift detection local)

**O que é.** Hook git local que **avisa** (ou bloqueia, configurável) quando dev cria controller fora do SCOPE.md do módulo.

**Lógica:**
```bash
# Pre-commit checa arquivos staged
for file in git diff --cached --name-only:
  if file matches Modules/<X>/Http/Controllers/<Y>Controller.php (NOVO):
    read Modules/<X>/SCOPE.md
    if <Y>Controller NÃO está em contains[]:
      WARN "Drift detectado: <Y>Controller não declarado em SCOPE.md"
      WARN "Edite SCOPE.md OU mova pro módulo correto"
      (em modo strict: exit 1)
```

**Fonte canônica.** NIST SP 800-207 endpoint validation; OPA local enforcement em developer machines.

**Status oimpresso.** ⏸️ Fase 3.6. Warn-only inicialmente; bloqueio strict após 4 semanas de calibração.

---

### 4. ActionGate middleware (runtime PEP/PDP)

**O que é.** Middleware Laravel que intercepta toda request L2+ e decide ALLOW/REVIEW/BLOCK consultando: actor manifest (L4) + module charter (L5) + governance rules (L6).

**Pseudo-código:**
```php
// Modules/Governance/Http/Middleware/ActionGate.php (Fase 5)

public function handle(Request $request, Closure $next) {
    $actor = $this->resolveActor($request->user());
    if (!$actor || $actor->revoked_at) abort(403, 'No actor manifest');

    $action = $this->classifyAction($request);
    $module = $this->resolveModule($request->route());

    // L3 Trust check
    if ($actor->trust_level < $module->trust_required) {
        $this->logAndReject($actor, $action, 'TRUST_INSUFFICIENT');
        abort(403);
    }

    // L5 Module Charter check
    if (!in_array($action->controller, $module->scope->contains)) {
        $this->logAndReject($actor, $action, 'OUT_OF_SCOPE');
        abort(403);
    }

    // L6 Policy check
    $outcome = app(PolicyEngine::class)->evaluate($actor, $action);
    if ($outcome === 'BLOCK') abort(403);
    if ($outcome === 'REQUIRE_REVIEW') return $this->queueForReview(...);

    // L7 Audit
    app(AuditLogger::class)->record($actor, $action, $outcome);

    return $next($request);
}
```

**Fonte canônica.** NIST SP 800-207 PEP (Policy Enforcement Point) + PDP (Policy Decision Point); OPA decision API; AWS Cedar `is_authorized()`.

**Status oimpresso.** ⏸️ Fase 5. ActionGate vive em `Modules/Governance/Http/Middleware/`.

---

### 5. Drift detection cron

**O que é.** Job diário que compara estado **declarado** (SCOPE.md.contains[]) vs estado **observado** (filesystem actual). Cria alertas em `mcp_alertas` quando divergência aparece.

**Pseudo-código:**
```php
// Modules/Governance/Jobs/DetectDriftJob.php (Fase 5)

public function handle() {
    foreach (Module::all() as $module) {
        $declared = $module->scope->contains; // de SCOPE.md
        $observed = $this->scanControllers($module->path); // filesystem

        $drift = array_diff($observed, $declared);
        if (count($drift) > 0) {
            McpAlerta::create([
                'category' => 'module_drift',
                'severity' => 'medium',
                'module' => $module->key,
                'detail' => "Controllers em prod não declarados: " . implode(', ', $drift),
            ]);
        }
    }
}
```

**Fonte canônica.** Cedar policy bundles + reconciliation loop; OPA periodic audit jobs.

**Status oimpresso.** ⏸️ Fase 3.5 (depois de `mcp_modules` cache). Roda diariamente 06h SP, alerta vira card no dashboard governance.

---

### 6. Mutation testing das policies

**O que é.** Suite Pest que testa que **policies bloqueiam o que devem** — não só que policies existem. Cada `mcp_governance_rules` row vira test case automaticamente:

```php
// tests/Feature/Governance/PolicyMutationTest.php (Fase 5)

it('bloqueia ação X quando confidence < threshold', function () {
    $action = factory()->make(['confidence' => 0.6]);
    $rule = McpGovernanceRule::find('require_review_low_confidence');

    $outcome = app(PolicyEngine::class)->evaluate($action, $rule);

    expect($outcome)->toBe('REQUIRE_HUMAN_REVIEW');
});
```

Geração automática a partir das rules em DB — sempre que rule muda, test atualiza.

**Fonte canônica.** OPA policy unit testing framework; NIST policy validation §A.6.

**Status oimpresso.** ⏸️ Fase 5. Trabalho dependente de ActionGate operacional.

---

### 7. Quarterly constitutional review

**O que é.** Review trimestral pra:
- Cada artigo da Constituição: ainda válido? Precisa amendment?
- Trust Tiers: alguma demoção/promoção pendente?
- Identity Mesh: actors revogados sendo monitorados?
- Skills: alguma deprecada silenciosamente?
- ADRs: status `proposto` há >90 dias = revisitar
- Audit log: anomalias estatísticas (spike, queda)
- Policies: rules com `triggered_count = 0` por trimestre = candidatas a remoção

**Output:** ADR `quarterly-review-YYYY-Q[N].md` com decisões + rollover de pendências.

**Fonte canônica.** NIST SP 800-207 §7 management review; ISO 27001 §9.3 management review.

**Status oimpresso.** ⏸️ Pattern definido. Primeira ocorrência: **2026-08-05** (3 meses após v1.0.0). Wagner agenda no calendário.

---

### 8. Public audit dashboard

**O que é.** UI `/governance/audit` (Fase 5) lendo `mcp_audit_log`:

- Filtros: actor, módulo, ação, outcome, período
- Highlights: ações L0/L1, BLOCKs nas últimas 24h, mudanças em SCOPE.md, amendments constitucionais
- Drill-down: clicar em ação → ver before/after state, request_id, contexto completo
- Export: relatório LGPD por business_id sob demanda do titular (Art. 4 + Art. 18 LGPD)

**Fonte canônica.** NIST SP 800-207 §6 continuous monitoring; Cedar audit log specification; AWS CloudTrail-style transparency.

**Status oimpresso.** ⏸️ Fase 5. Componentes Inertia/React em `Modules/Governance/Resources/js/Pages/Audit/*`.

---

## §3. Defesa em profundidade — exemplo concreto

Cenário: **IA externa (L3) tentando criar Eloquent Model em `Modules/Connector` (L0)**.

| Camada de defesa | Resultado |
|---|---|
| #4 ActionGate runtime | ❌ BLOCK — `actor.trust_level=L3 < module.trust_required=L0` |
| #3 Pre-commit hook (se PR de dev local) | ❌ Drift warning — controller fora de SCOPE.md.contains |
| #2 Pre-merge gate | ❌ Block — Connector é L0 e PR não tem assinatura Wagner |
| #5 Drift detection cron | ✅ Detecta drift se PR escapou e mergeou |
| #8 Audit dashboard | ✅ Wagner vê no dashboard |
| #1 Versioned Constitution | ✅ Artigo 5 (Trust Tiers) cita o caso |

**6 camadas independentes** capturam o ataque/erro. Pra passar todas, precisa comprometer 6 mecanismos diferentes — improvável.

---

## §4. Como esses 8 mapeiam às camadas L1-L7

```
L1 Constitution
   ├── #1 Versioned Constitution (auto-cumprida)
   └── #7 Quarterly review (ainda faz sentido?)

L2 SRS
   ├── #1 Versioned (semver per slug)
   └── #2 Pre-merge gate (ADR linkado obrigatório)

L3 Trust Tiers
   ├── #4 ActionGate (enforcement)
   └── #7 Quarterly review (promotions/demotions)

L4 Identity Mesh
   ├── #4 ActionGate (manifest check)
   ├── #7 Quarterly review (actors revogados ainda monitorados)
   └── #8 Audit dashboard (actor activity)

L5 Module Charter
   ├── #3 Pre-commit hook (drift local)
   ├── #4 ActionGate (controller in scope)
   └── #5 Drift detection cron (declared vs observed)

L6 Policy Gating
   ├── #4 ActionGate (PEP+PDP)
   ├── #6 Mutation testing (policies bloqueiam o que devem)
   └── #2 Pre-merge gate (mudanças em policies precisam ADR)

L7 Audit
   ├── #8 Public audit dashboard (UI)
   └── trigger MySQL append-only (P0.2 fix obrigatório)
```

Cada camada é protegida por 2-3 mecanismos. Defesa em profundidade real.

---

## §5. Plano de implementação (cobertura ADR 0079 Fases)

| Mecanismo | Quando | ADR derivada |
|---|---|---|
| #1 Versioned Constitution | ✅ feito | ADR 0079 |
| #3 Pre-commit hook | Fase 3.6 | ADR 0082 |
| #5 Drift detection cron | Fase 3.5 | ADR 0082 |
| #4 ActionGate middleware | Fase 5 | ADR 0083 |
| #8 Public audit dashboard | Fase 5 | ADR 0083 |
| #6 Mutation testing | Fase 5 (depois de ActionGate) | ADR 0083 |
| #2 Pre-merge gate | Fase 5 (depois de #3) | ADR 0083 |
| #7 Quarterly review | 2026-08-05 (manual primeira vez, automatiza depois) | ADR pra primeira ocorrência |

**Custo agregado de implementação:** ~24h distribuídos em Fase 5 + 4h Fase 3.5/3.6.

---

## §6. Auditoria deste documento

Mudança neste documento requer cascade review §10.4:

- L4 Identity Mesh — manifest schema referenciado em #4 ActionGate
- L5 Module Charter — SCOPE.md.contains[] referenciado em #3, #4, #5
- L6 Policy Gating — mcp_governance_rules referenciado em #4, #6
- L7 Audit — mcp_audit_log referenciado em #4, #8

---

## §7. Crosswalk regulatório — artefato/gate ↔ EU AI Act · ISO 42001 · NIST AI RMF

> **Adicionado v1.1.0 (2026-07-19).** Mapa de **rastreabilidade**, NÃO certificado de conformidade. Responde à pergunta que uma auditoria externa (cliente pagante, due-diligence B2B) faz — *"onde está a evidência do controle X?"* — apontando pro artefato oimpresso que **já existe**. Não classifica o sistema como high-risk nem afirma conformidade; mapeia **onde o substrato de governança já evidencia um controle** e, com igual peso, **onde não cobre** (§7.3). Auto-flagrado ausente em [session 2026-06-20](../sessions/2026-06-20-ia-os-onda1-batch.md) ("crosswalk formal NIST AI RMF / ISO 42001 — destrava auditoria externa"); este é o preenchimento.

### §7.1 Os três referenciais (e por que estender o ENFORCEMENT em vez de doc paralelo)

| Referencial | O que é | Por que importa aqui |
|---|---|---|
| **EU AI Act** (Reg. UE 2024/1689) | Regulação horizontal de IA; obrigações de sistema high-risk (Cap. III Seção 2, Art. 9-15 + 17) | Anexo III (high-risk) **aplicável 02-ago-2026**; cliente/parceiro EU exige evidência de risco/log/oversight |
| **ISO/IEC 42001:2023** | AIMS — sistema de gestão de IA (PDCA, Cl. 4-10 + Anexo A) | Certificável; base de due-diligence B2B; espelha ISO 27001, que já orientou a Constituição (§2 #7) |
| **NIST AI RMF 1.0** | Framework voluntário — 4 funções GOVERN/MAP/MEASURE/MANAGE | Vocabulário comum de auditoria US; já é fonte parcial do ENFORCEMENT via NIST 800-207 |

O ENFORCEMENT (§1-§6) ancora em **NIST 800-207 (Zero Trust)** — segurança de *acesso*. Este §7 é o eixo complementar de governança do *sistema de IA* (AI Act/42001/AI RMF). Mora aqui, e não em doc novo, porque o dono do tema "como as camadas são protegidas + a qual controle externo isso responde" é este documento — abrir paralelo fragmentaria o juiz único (regra §5 proibições "roadmap paralelo").

### §7.2 Matriz artefato ↔ controle

Cada linha: um artefato/gate oimpresso **verificado existente** (jul/2026) → o controle que ele **evidencia** em cada referencial → maturidade honesta (✅ opera · 🟡 parcial · ⏸️ declarado-não-operacional). Subcategorias NIST são **indicativas** (função + representante), não exaustivas.

| # | Artefato/gate oimpresso (real) | EU AI Act | ISO 42001 | NIST AI RMF | Maturidade |
|---|---|---|---|---|---|
| A | **CONSTITUTION.md** v1.1.0 (semver + `amendments[]`) + [ADRs append-only](../decisions/) | Art. 17 (QMS) | Cl. 5 (liderança) · A.2 (política de IA) | GOVERN 1.1 | ✅ |
| B | **ENFORCEMENT.md** — 8 mecanismos (§1-§6, este doc) | Art. 17 (QMS) | Cl. 8 (operação) · A.6.1 | GOVERN 1.4 · MANAGE 1.1 | 🟡 (1/8 pleno) |
| C | **charters** (238 `.charter.md`) — intenção / non-goals / anti-hooks por tela | Art. 11 (doc. técnica) | A.6.2.7 (doc técnica) | MAP 1.1 · MAP 2.3 | ✅ |
| D | **casos.md** (39) + **casos-gate G-2** (CI *required*) — UC ↔ teste | Art. 9 (gestão de risco) · Art. 15 (exatidão) | Cl. 9.1 · A.6.2.4 (verif. & valid.) | MEASURE 2.3 | ✅ |
| E | **mcp_audit_log** — append-only (triggers imutabilidade) + hash-chain (`2026_06_20`) | **Art. 12 · Art. 19 (logs automáticos)** | Cl. 7.5 · A.6.2.8 (event logs) | MEASURE 3.1 · MANAGE 4.1 | ✅ |
| F | **ActionGate** (PEP/PDP) + **DecisionRouter/PolicyEngine** (ADS) + **HITL 4 níveis** (R10) | **Art. 14 (supervisão humana)** | A.9 (uso responsável) | GOVERN 3.2 · MANAGE 2.2 | 🟡 |
| G | **required-checks-baseline.json** + CI gates (governance-gate, anchor-lint, casos-gate…) | Art. 17 (QMS) | Cl. 8 · Cl. 9 (aval. de desempenho) | MEASURE 1.1 · MANAGE 1.x | ✅ |
| H | **jana:health-check** (5 SQL) + **drift-sentinel** + **ragas-real-eval** + Langfuse/OTel | Art. 15 (robustez) · **Art. 72 (pós-mercado)** | Cl. 9.1 (monitoramento) · A.6.2.6 (operação) | MEASURE 2.x · MANAGE 4.1 | 🟡 (online-eval flag OFF) |
| I | **RetentionPurgeCommand** + **LgpdEsquecerTitularTool** (DSR) + **PiiRedactor** | Art. 10 (governança de dados) | A.7 (dados de IA) | MEASURE 2.10 (privacidade) | ✅ (via LGPD Art. 7/18) |
| J | **business_id global scope** (Tier 0, ADR 0093) + Pest cross-tenant (biz=1 vs biz=99) | Art. 15 (cibersegurança) | A.7 · Cl. 8 (segregação) | MEASURE 2.7 (segurança/resiliência) | ✅ |
| K | **Quarterly constitutional review** (§2 #7) + **module-grade scorecards** (ADR 0230) | Art. 17 (melhoria do QMS) | Cl. 9.3 (análise crítica) · Cl. 10 (melhoria) | GOVERN 1.4 · MANAGE 4.2 | 🟡 (1ª ocorrência 2026-08-05) |

**Leitura.** As linhas **E** e **F** são as mais fortes — log tamper-evident (Art. 12/19) e supervisão humana estruturada (Art. 14) são exatamente onde a auditoria high-risk aperta, e o substrato já opera. As mais fracas (**B, F, H, K**) casam com as fraquezas *medidas* na grade (`spec-governanca` 4,5; drift-eval / online-eval flag OFF) — o mapa não infla: onde a peça é parcial, a coluna diz 🟡.

### §7.3 O que os referenciais exigem e NÃO temos (gaps honestos)

Rastreabilidade só vale com a coluna do que falta. Nenhum destes é implicado por "temos governança" — cada um exige trabalho ou decisão explícita:

- **Classificação high-risk indefinida.** Jana é assistente de negócio; provavelmente **fora** do Anexo III (não é biometria / infra crítica / emprego / serviços essenciais). Este mapa é **prontidão**, não declaração de aplicabilidade. Classificar formalmente = decisão [W] + eventual counsel.
- **Conformity assessment / marcação CE (Art. 43) · registro no banco UE (Art. 49) · FRIA (Art. 27):** não feitos — só exigíveis se classificado high-risk. N/A hoje.
- **Post-market monitoring formal (Art. 72 / ISO Cl. 9.1):** parcial — `drift-sentinel` é tautológico (§5 proibições 2026-07-17) e a eval sobre tráfego real está com flag OFF (decisão [W]/LGPD). Monitoramento existe; o **loop de qualidade em prod** não está fechado.
- **Eval independente / cross-model (NIST MEASURE):** verificação é same-model (Opus×Opus) por design; cross-model só ad-hoc (fraqueza `orq-same-model` 5,0).
- **Governança de dado de treino (Art. 10 / A.7.x):** oimpresso **não treina modelo** (usa Claude hospedado) — a maior parte de Art. 10 é upstream (Anthropic / GPAI). Cobrimos dado de **RAG/memória** (corpus governado), não treino.
- **Certificação ISO 42001:** este é **auto-mapa**, não auditoria de terceira parte. Vale como evidência pré-auditoria, nunca como certificado.

### §7.4 Manter fresco (sem apodrecer) — e por que NÃO é gate

Este mapa aponta pra artefatos vivos e pra um alvo regulatório em vigência escalonada (GPAI ago/2025 · high-risk Anexo III **ago/2026** · Anexo I ago/2027). Regras:

- **Não restatear estado que outro sistema sabe melhor** (§5 proibições 2026-07-17): quais gates são *required* mora em [`governance/required-checks-baseline.json`](../../governance/required-checks-baseline.json) (vigiado por `protection-drift.mjs`); a maturidade de cada peça mora na grade de fraquezas ([`memory/reguas/fraquezas.json`](../reguas/fraquezas.json)). Aqui só o **mapeamento** artefato↔controle.
- **Isto NÃO é presence-gate nem catraca** (§5 proibições) — é documentação de rastreabilidade. Não existe check de CI exigindo "linha preenchida" (seria presence-gate, proibido). A defesa de correção de cada linha é o gate/teste do **próprio artefato** citado, não este doc.
- Re-verificar contra a fonte quando: (a) o Anexo III mudar a classificação de assistentes de ERP; (b) um artefato citado for renomeado/removido; (c) ISO 42001 receber revisão. **Data deste retrato: 2026-07-19.**

---

## Histórico

- **v1.1.0** (2026-07-19) — §7 Crosswalk regulatório: matriz de rastreabilidade artefato/gate ↔ EU AI Act · ISO 42001 · NIST AI RMF (11 linhas de artefatos verificados existentes + gaps honestos + caveats de frescor). Preenche o crosswalk AI-específico auto-flagrado ausente na session 2026-06-20. Documentação sobre mecanismo existente — sem código, sem gate novo.
- **v1.0.0** (2026-05-05) — Mapeamento inicial dos 8 mecanismos NIST/Cedar/OPA às 7 camadas + plano de implementação Fases 3.5/3.6/5.
