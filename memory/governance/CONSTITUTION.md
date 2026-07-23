---
id: governance-constitution
slug: oimpresso-constitution
title: "Constituição do Oimpresso ERP"
type: constitution
authority: supreme
lifecycle: ativo
version: 1.1.0
ratified_by: [W]
ratified_at: 2026-05-05
charter_adr: 0079
last_amendment: 2026-05-05
amendments:
  - version: 1.1.0
    at: 2026-05-05
    by: [W]
    type: minor
    description: "Adicionada §10.4 — Cascade Review obrigatória. Toda mudança em camada superior exige auditoria documentada das camadas abaixo."
    adr: 0079
related:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0070-jira-style-task-management-current-md-removed
pii: false
review_triggers:
  - "Mudança regulatória em LGPD ou Portaria 671/2021 que invalide artigo 4"
  - "Time crescer >10 pessoas, exigindo refinamento de Trust Tiers (artigo 5)"
  - "Auditoria externa indicar gap em audit (artigo 9)"
  - "Anthropic/AWS/Cedar publicarem pattern superior pra governança que mereça adoção formal"
---

# Constituição do Oimpresso ERP

> **Versão 1.0.0 — ratificada em 2026-05-05 por Wagner Rocha**
> **Status:** ativo / supremo
> **ADR de origem:** [0079](../decisions/0079-constituicao-oimpresso-7-camadas-governanca.md)
> **Mudanças:** somente via Wagner + ADR formal + version bump (semver). Ver §10.

---

## Preâmbulo

O Oimpresso é um ERP gráfico brasileiro construído sobre UltimatePOS, multi-tenant por `business_id`, atendendo clientes em conformidade com LGPD e Portaria MTP 671/2021, com objetivo de virar **ERP autônomo de R$ [redacted Tier 0]M em 24 meses** (2026-05-05 → 2028-05-05).

Esta Constituição governa **todo código, dado, ação e identidade** dentro do oimpresso — humanos e IAs. Ela é **suprema**. Tudo abaixo deriva dela.

São 10 artigos. Cada artigo só muda mediante:
1. Decisão explícita do Wagner
2. ADR formal documentando o porquê
3. Version bump (semver) desta Constituição
4. Aviso público pro time

Não há cláusula pétrea explícita — qualquer artigo pode evoluir. Mas a soberania do Wagner (artigo 1) é a fundação que torna toda a outra mudança possível.

---

## Artigo 1 — Soberania

**Princípio.** Wagner Rocha é o root do sistema. Nenhuma ação automática, nenhuma decisão de IA, nenhuma capability concedida supera decisão explícita do Wagner.

**Por quê.** Toda autoridade abaixo é capability **delegada explicitamente**. Em ambientes onde IAs e humanos coexistem com poder de mudar código e dados, é essencial que exista um root identificável, presente, e revogável.

**Implementação.**
- Wagner é o único actor com `trust_level: L0` (kernel).
- Toda action automática (job, hook, comando agendado) é revogável por Wagner em <1h.
- Wagner pode auditar e revogar qualquer token, capability, ou ação a qualquer momento.

**Verificação.**
- `mcp_actors` registra Wagner como L0 com `parent_actor: null`.
- Toda ação L0 é logada em `mcp_audit_log`.
- Pre-commit hook impede que código toque escopo L0 sem assinatura Wagner.

**Quem viola = como.** IA escrever código que altera trust hierarchy sem ADR. Job que toma ação irreversível sem revogabilidade. Capability concedida que não pode ser desfeita.

---

## Artigo 2 — Multi-tenancy é invariante

**Princípio.** O campo `business_id` é invariante absoluto em todo dado de negócio. Vazamento entre tenants é incidente P0 imediato.

**Por quê.** O oimpresso é multi-tenant nativo (UltimatePOS). 56 businesses convivem no mesmo banco. Vazamento de dados entre clientes destrói relação comercial e expõe Oimpresso a passivo legal (LGPD Art. 7º). Multi-tenancy não é feature — é existência.

**Implementação obrigatória.**
- Toda Eloquent Model com `business_id` MUST ter global scope tenant.
- Toda query SQL crua MUST conter `WHERE business_id = ?` ou justificativa explícita (ADR).
- Jobs em fila MUST persistir `business_id` no payload e restaurá-lo no `handle()`.
- Comandos artisan cross-business MUST exigir flag `--business=` explícita.
- APIs que recebem `business_id` MUST validar contra sessão/token do caller.

**Verificação.**
- Skill `multi-tenant-patterns` ativa em qualquer task tocando código com `business_id`.
- Suite de testes Pest cobre cenários de cross-tenant query.
- Audit log destaca queries que retornaram dados de business diferente do contexto.

**Quem viola = como.** Query sem scope. Job sem persistir tenant. CLI sem `--business=`. API que confia em request sem validar.

---

## Artigo 3 — Imutabilidade onde a lei ou o negócio exige

**Princípio.** Categorias específicas de dados são **append-only** — UPDATE/DELETE é proibido. Apenas INSERT é permitido. Correções acontecem por nova row marcando `superseded_by`.

**Por quê.** Compliance legal (Portaria 671/2021 exige imutabilidade de marcações de ponto), auditoria (trail forense só funciona se log não pode ser reescrito), governança (ADRs/SRS imutáveis evitam reescrita silenciosa do passado).

**Implementação obrigatória.**
- **Imutáveis por trigger MySQL:** `ponto_marcacoes`, `mcp_audit_log`, `memory/governance/srs/*`.
- **Imutáveis por convenção (ADR/PR review):** `memory/decisions/*`, `memory/sessions/*`, `mcp_skill_versions`.
- Correção em row imutável = nova row `version+1` com `supersedes: <old_id>` e `superseded_by` na antiga (ponteiro lógico, não DELETE).

**Verificação.**
- Trigger MySQL `BEFORE UPDATE/DELETE` em tabelas append-only com `SIGNAL SQLSTATE`.
- ADR template enforça imutabilidade — nova ADR supersedes ao invés de editar.
- Pre-commit hook detecta edição em ADR existente e exige flag `--amend-charter` + ADR justificando.

**Quem viola = como.** UPDATE em `ponto_marcacoes`. DELETE em `mcp_audit_log`. Edição inline em ADR existente sem supersede.

---

## Artigo 4 — Compliance regulatório é restrição inegociável

**Princípio.** As seguintes leis/regulações são tratadas como **restrições absolutas** — não há feature, performance ou conveniência que justifique violar:

- **LGPD (Lei 13.709/2018)** — proteção de dados pessoais brasileiros
- **Portaria MTP 671/2021** — registro de ponto eletrônico
- **NF-e / NFC-e (Convênios SINIEF + SPED)** — emissão fiscal eletrônica
- **NFSe (LC 214/2025)** — emissão de NFSe via Sistema Nacional

**Por quê.** Violação = passivo legal direto + perda de credibilidade + impossibilidade de operar verticais regulatórias (PontoWr2, Financeiro/NFe, NFSe).

**Implementação obrigatória.**
- **LGPD Art. 7º:** consentimento, finalidade, transparência. Toda coleta de PII exige declaração de finalidade.
- **LGPD Art. 18:** titular pode acessar/corrigir/apagar seus dados. Implementado via `/copiloto/memoria` opt-out + endpoint LGPD.
- **Portaria 671/2021:** marcações de ponto append-only com hash SHA256 (artigo 3 reforça).
- **NF-e:** emissão via `eduardokum/sped-nfe` com retenção de 5 anos.
- **PII redactor BR:** regex CPF/CNPJ/email/tel ativo em logs e prompts de IA (US-COPI-043).

**Verificação.**
- Skill `multi-tenant-patterns` referencia compliance.
- ADRs taggeados `lgpd`, `portaria-671`, `nfe`, `nfse` listam cobertura por verticais.
- Audit pode disparar relatório LGPD por business_id sob demanda do titular.

**Quem viola = como.** Log com PII raw. Endpoint que retorna dados de business sem consentimento. Marcação de ponto editada. NF-e sem retenção.

---

## Artigo 5 — Trust Tiers (hierarquia de capabilities)

**Princípio.** Toda action pertence a uma camada de confiança L0-L4. Default é deny. Capability é concedida explicitamente ao actor.

**Os 5 tiers:**

| Tier | Quem | Pode tocar | Exemplos de ação |
|---|---|---|---|
| **L0 KERNEL** | Wagner exclusivamente | qualquer coisa | mudar Constituição, schema raiz, migrations destrutivas, revogar tokens |
| **L1 GOVERNANCE** | Wagner + ADR aprovado | tabelas governance, policies, scopes, módulos críticos | editar SRS, criar ADR, ajustar Trust Tiers |
| **L2 OPERATOR** | funcionário aprovado + IA pareada com Skills carregadas | módulos product/vertical aprovados, código com guardrails de Skills | criar/editar features dentro do módulo, deploy via PR |
| **L3 AI-AGENT** | IAs externas conectando via MCP | leitura ampla; escrita restrita a sandboxes ou módulos específicos declarados | sugerir, indexar, anotar; escrita só em scopes declarados |
| **L4 PUBLIC** | clientes finais via APIs read-only | dados públicos do próprio business | consultar OS, baixar boleto, ver portal |

**Por quê.** Sem hierarquia, qualquer actor com acesso a qualquer endpoint vira root. Tier-based caps permite escalar pra time + IAs sem perder controle.

**Implementação obrigatória.**
- Cada actor em `mcp_actors` declara `trust_level`.
- Cada módulo em `Modules/<X>/SCOPE.md` declara `trust_required` mínimo.
- ActionGate middleware (L6) verifica `actor.trust_level >= module.trust_required`.
- Detalhes operacionais: `memory/governance/TRUST-TIERS.md` (a criar).

**Verificação.**
- `mcp_audit_log` registra trust check em toda ação.
- Wagner revisa promotions/demotions trimestralmente.

**Quem viola = como.** Endpoint que aceita action L1+ sem trust check. Token compartilhado entre actors. Promotion silenciosa de capability.

---

## Artigo 6 — Identity Mesh (todo actor com manifest)

**Princípio.** Todo actor — humano ou IA — tem identidade declarada via **manifest**. Sem manifest = sem ação. Default-deny.

**Manifest mínimo:**

```yaml
actor: <slug-único>
type: human | ai_agent
trust_level: L0|L1|L2|L3|L4
parent_actor: <slug ou null>
modules_write: [<lista permitida>] ou [*]
modules_read: [*] ou [<lista>]
modules_blocked: [<exclusões explícitas>]
skills_required: [<skills que IA precisa carregar antes>]
actions_blocked: [<lista>]
audit_required: true|false
created_by: <slug do criador>
revoked_at: null | <timestamp>
```

**Por quê.** Em era de IA conectando via MCP, "quem fez essa ação?" precisa ter resposta verificável. Manifest é a verdade declarada; audit é a verdade observada.

**Implementação obrigatória.**
- `mcp_actors` é a tabela canônica.
- Action sem actor identificável = REJECT.
- IA conectando via MCP precisa ser registrada em `mcp_actors` com manifest antes de receber token.
- Detalhes operacionais: `memory/governance/IDENTITY-MESH.md` (a criar).

**Verificação.**
- ActionGate consulta `mcp_actors` em toda request.
- `mcp_audit_log.actor_slug` é NOT NULL.

**Quem viola = como.** Token bind a `user_id` raw sem manifest. IA externa agindo via API key não-rastreável. Service account sem actor declarado.

---

## Artigo 7 — Module Charter (todo módulo tem fronteira documentada)

**Princípio.** Todo módulo em `Modules/<X>/` tem `SCOPE.md` declarando: o que contém, o que NÃO contém, qual `trust_required`, quem é owner. Controller fora de scope é drift bloqueado em pre-commit.

**Por quê.** 30 módulos já existem. Drift entre módulos é fato observado (controllers em Jana que pertenciam a KB/TeamMcp). Sem fronteira documentada e enforced, novos conceitos caem em qualquer pasta.

**Implementação obrigatória.**
- `Modules/<X>/SCOPE.md` com frontmatter:
  ```yaml
  ---
  module: <Nome>
  purpose: "<1-2 frases descrevendo missão do módulo>"
  contains:
    - <controllers / features dentro do escopo>
  not_contains:
    - <exclusões com link pra módulo correto>
  trust_required: L0|L1|L2|L3
  owner: <slug actor>
  permission_prefix: <prefix>.*
  charter_adr: <NNNN>
  ---
  ```
- Cache em `mcp_modules` via webhook git → DB (mesmo pattern dos ADRs).
- Pre-commit hook: novo `Http/Controllers/*.php` em módulo X deve estar coberto pelo `contains[]` declarado.

**Verificação.**
- Tool MCP `modules-fetch <X>` retorna scope.
- Pre-commit warn (configurável pra bloqueio) em drift.
- Drift detection cron diário compara declared × actual.

**Quem viola = como.** Controller em pasta arbitrária sem ADR. Módulo sem SCOPE.md aceitando código novo. Cross-module dependency sem declaração.

---

## Artigo 8 — Policy Gating (toda ação L2+ passa por gate)

**Princípio.** Toda ação que altera estado em trust L2 ou superior passa por **ActionGate**, que avalia em runtime: actor permitido? policy ativa? ação dentro do scope? Resultado: ALLOW / REQUIRE_REVIEW / BLOCK + log.

**Por quê.** Capabilities declaradas (artigo 6) e fronteiras de módulo (artigo 7) precisam ser **enforced**, não apenas documentadas. ActionGate é o ponto único onde política vira realidade.

**Implementação obrigatória.**
- Tabela `mcp_governance_rules` define policies executáveis com `condition`, `action`, `enabled`, `category`.
- Middleware ActionGate (a construir, Modules/Governance/) intercepta requests L2+.
- Decisão fica em `mcp_dual_brain_decisions` (já existe) + log em `mcp_audit_log`.
- Cobertura ZERO em L0 (Wagner age sem gate, mas tudo logado).
- Cobertura PARCIAL em L1 (Wagner aprovação manual via UI Governance).
- Cobertura TOTAL em L2+ (gate runtime obrigatório).

**Verificação.**
- Pest tests de mutação verificam que policies bloqueiam o que devem.
- Audit log inspeciona ALLOW/REVIEW/BLOCK ratio por categoria.

**Quem viola = como.** Endpoint que ignora ActionGate. Service que age por bypass. Background job sem policy check.

---

## Artigo 9 — Auditoria mandatória

**Princípio.** Toda ação L1+ deixa trilha em `mcp_audit_log`. Modificação ou deleção do audit log é incidente P0.

**Por quê.** Sem audit, governança é teatro. Compliance LGPD/Portaria/Fiscal exige trilha forense. Auditor externo precisa ver o que aconteceu.

**Implementação obrigatória.**
- `mcp_audit_log` schema mínimo: `actor_slug`, `action`, `target` (módulo/entidade), `trust_level`, `policy_outcome`, `before_state` (json), `after_state` (json), `created_at`, `request_id`.
- Trigger MySQL `BEFORE UPDATE/DELETE` em `mcp_audit_log` com `SIGNAL SQLSTATE 'AUDIT_IMMUTABLE'`.
- Retention mínima: 5 anos (compliance).
- UI `/governance/audit` (a construir) permite filtragem por actor / módulo / período / outcome.

**Verificação.**
- Quarterly review do audit log: anomalias, ações L0/L1 fora do horário, sequências suspeitas.
- Backup independente do log (pasta separada, retention diferente).

**Quem viola = como.** Endpoint que muda dado L1+ sem chamar audit logger. Trigger desabilitado. UI/API expondo audit pra read sem proteção.

---

## Artigo 10 — Evolução constitucional

**Princípio.** Esta Constituição evolui. Mas mudança em qualquer artigo requer:

1. **Wagner explicitamente** — não pode ser por inferência, automação, ou consenso de IAs
2. **ADR formal** registrando contexto, decisão, justificativa, consequências
3. **Version bump (semver)** no frontmatter desta Constituição
4. **Aviso público pro time** (commit em main + entry em handoff)
5. **Plano de rollback** documentado caso a mudança quebre algo

**Tipos de mudança:**

| Tipo | Exemplo | Version bump |
|---|---|---|
| **MAJOR (X.0.0)** | Remover ou substituir artigo inteiro | 1.0 → 2.0 |
| **MINOR (1.X.0)** | Adicionar novo artigo, expandir escopo | 1.0 → 1.1 |
| **PATCH (1.0.X)** | Esclarecer redação, adicionar exemplo, fix link | 1.0 → 1.0.1 |

**Por quê.** Constituição é DNA — muda devagar e com cuidado. Estabilidade > novidade. Mas oimpresso evolui em direção a ERP autônomo de R$ [redacted Tier 0]M em 24 meses; pode ser que artigos novos sejam necessários (ex: artigo sobre IAs autônomas tomando decisões financeiras).

**Implementação obrigatória.**
- Frontmatter desta Constituição tem `version`, `last_amendment`, `amendments[]`.
- ADRs taggeados `constitution-amendment` ficam linkados aqui.
- Mudança versionada em git com commit message `constitution(amendment): vX.Y.Z — <descrição curta>`.

**Verificação.**
- Quarterly review constitucional: artigos ainda fazem sentido? Algum precisa amendment?
- Diff entre versões fica em git history.
- ADRs com tag `constitution-amendment` listáveis em `decisions-search query:"constitution"`.

**Quem viola = como.** Editar Constituição inline sem ADR. Bypass do version bump. Mudança que altera comportamento sem aviso.

### §10.4 — Cascade Review (Auditoria em cascata) — adicionada em v1.1.0

**Princípio adicional.** Quando uma camada N é modificada, **todas as camadas abaixo (N+1, N+2, ..., L7) devem ser auditadas e potencialmente atualizadas** para garantir que ainda satisfazem a nova versão da camada superior.

**Mapa de cascata:**

| Mudança em | Cascata obrigatória |
|---|---|
| **L1 Constitution** | audit em L2 SRS + L3 Trust Tiers + L4 Identity Mesh + L5 Module Charter + L6 Policies + L7 Audit |
| **L2 SRS** (entry append-only) | audit em L3-L7 + Skills que referenciam o slug SRS |
| **L3 Trust Tiers** | audit em L4 (manifests precisam mapear pros novos tiers) + L5 (SCOPE.md `trust_required` revisado) + L6 |
| **L4 Identity Mesh** | audit em actors existentes (manifests ainda válidos?) + L6 (policies referenciando actors) |
| **L5 Module Charter** | audit em controllers do módulo (ainda dentro do scope?) + L6 |
| **L6 Policy Gating** | audit em policies ativas (ainda satisfazem a regra modificada?) |
| **L7 Audit** | sem cascata abaixo (é o nível folha) |

**Implementação obrigatória.**
- Toda ADR que modifique camada N deve incluir seção **"Cascade Review"** documentando:
  - Quais camadas abaixo foram auditadas
  - Quais artefatos precisaram de update (e onde foi feito)
  - Quais artefatos passaram intactos (e por quê)
  - Quais ADRs derivadas são necessárias para fechar gaps
- Audit report fica em `memory/governance/audit-YYYY-MM-DD-vX.Y.md` referenciado da ADR de mudança.
- Sem audit report documentado, o amendment **não é considerado ratificado** — fica em status `proposto-pendente-audit` até completar.

**Verificação.**
- Pre-merge gate (CI): PR mexendo em `memory/governance/CONSTITUTION.md` ou `memory/governance/srs/*` requer arquivo `memory/governance/audit-*.md` no mesmo PR.
- Quarterly review constitucional checa se cascade reviews foram completos.

**Por que.** Sem cascata explícita, mudanças em camadas altas viram "letra morta" — a regra muda mas a prática nas camadas abaixo continua refletindo a regra antiga. Defesa em profundidade só funciona se camadas estão alinhadas. Cascade review é o mecanismo de alinhamento.

**Quem viola = como.** Amendment em Constituição sem audit report. SRS atualizado sem revisar Skills que referenciam o slug. Trust Tier mudado sem auditar manifests existentes.

---

## Aplicação prática — fluxo de uma ação genérica

```
Actor decide fazer ação X
       │
       ▼
[Artigo 6] Manifest do actor existe?  ──── não ──> REJECT
       │ sim
       ▼
[Artigo 5] actor.trust_level >= action.required ?  ──── não ──> REJECT
       │ sim
       ▼
[Artigo 7] action está dentro do SCOPE.md do módulo?  ──── não ──> WARN/REJECT
       │ sim
       ▼
[Artigo 8] ActionGate avalia policies em mcp_governance_rules
       │
       ├── ALLOW          → executa
       ├── REQUIRE_REVIEW → fila pro Wagner aprovar
       └── BLOCK          → REJECT
       │
       ▼
[Artigo 2/3/4] Validações invariáveis (multi-tenancy, imutabilidade, compliance)
       │
       ▼
[Artigo 9] Log em mcp_audit_log (actor, ação, before/after, outcome)
       │
       ▼
Ação executada (ou bloqueada com motivo registrado)
```

---

## Estado de implementação (versão 1.0.0)

| Artigo | Status conceitual | Status operacional |
|---|---|---|
| 1 Soberania | ✅ definido | ⚠️ formalização: `mcp_actors` precisa criar |
| 2 Multi-tenancy | ✅ definido + skill ativa | ✅ já em prod (UltimatePOS) |
| 3 Imutabilidade | ✅ definido | ⚠️ trigger MySQL precisa criar pra `mcp_audit_log` e SRS |
| 4 Compliance | ✅ definido | ⚠️ PII redactor pendente (US-COPI-043) |
| 5 Trust Tiers | ✅ definido | ⏸️ TRUST-TIERS.md a criar (Fase 3) |
| 6 Identity Mesh | ✅ definido | ⏸️ IDENTITY-MESH.md + tabela mcp_actors (Fase 4) |
| 7 Module Charter | ✅ definido | ⏸️ SCOPE.md em 30 módulos (Fase 3) |
| 8 Policy Gating | ✅ definido | ⏸️ ActionGate middleware (Fase 5) |
| 9 Auditoria | ✅ definido | ⚠️ tabela existe; UI dashboard pendente (Fase 5) |
| 10 Evolução | ✅ definido | ✅ aplicado nesta Constituição |

---

## Histórico de versões

- **v1.1.0** (2026-05-05) — Adicionada §10.4 Cascade Review. Wagner ratificou; audit cascata aplicada e documentada em `memory/governance/audit-2026-05-05-v1.1.md`.
- **v1.0.0** (2026-05-05) — Ratificação inicial. 10 artigos. Wagner ratificou via ADR 0079.

---

> **Mantida por:** Wagner Rocha
> **Versão atual:** 1.0.0
> **Próxima revisão programada:** 2026-08-05 (review trimestral) ou antes se review_trigger disparar
