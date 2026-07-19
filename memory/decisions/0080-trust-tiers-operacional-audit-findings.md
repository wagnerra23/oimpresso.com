---
slug: 0080-trust-tiers-operacional-audit-findings
number: 80
title: "Trust Tiers operacional + Architecture & Scope + audit findings v1.1.0"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-05"
module: governance
quarter: 2026-Q2
tags: [governance, trust-tiers, architecture, audit, p0]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0065-permission-registry-contract
  - 0077-mcp-resolver-owner-via-mcp-handle
pii: false
review_triggers:
  - "Time crescer >10 pessoas — refinamento dos tiers necessário"
  - "Incidente de drift recorrente que SCOPE.md + pre-commit não capturou"
  - "Demoção emergencial em incidente"
---

# ADR 0080 — Trust Tiers operacional + Architecture & Scope + audit findings

## Contexto

Constituição v1.1.0 ratificada hoje (ADR 0079) com regra cascade review §10.4. Cumprindo a regra, audit cascata foi executado (`memory/governance/audit-2026-05-05-v1.1.md`). Findings principais:

**4 P0 blockers:**
1. `ponto_marcacoes` sem trigger MySQL append-only (legal risk Portaria 671)
2. `mcp_audit_log` sem trigger MySQL append-only (forense quebrado)
3. `memory/governance/audit-*.md` exigido por §10.4 — RESOLVIDO criando o doc
4. Tabela `mcp_actors` não existe (Identity Mesh Art. 6 não operável)

**14 P1 items:** 15/16 skills sem manifest, 0/29 módulos com SCOPE.md, camadas L2-L6 não implementadas, ADR 0077 (mcp_handle) ainda proposto.

Wagner (mensagens em sessão):
- *"aprovado inicialmente, caso dedar tem que revisar todos niveis abaixo que auditar a regras abaixo"*
- *"pode continuar e revise todos os documentos abaixo para saber se estão dentro dos parametros"*
- *"Arquitetura e escopo"*
- *"modulos depreciados e ja aprovados as modificação propostas anteriormente"*

Síntese das 4 mensagens: aprovou Constituição, exige cascade review, quer endereçar arquitetura + scope, lembra que renomeações + depreciações dos módulos estão aprovadas.

## Decisão

Esta ADR consolida 4 decisões operacionais derivadas:

### 1. Trust Tiers v1.0.0 ratificados

[`memory/governance/TRUST-TIERS.md`](../governance/TRUST-TIERS.md) v1.0.0 operacionaliza Artigo 5. Define:

- 5 tiers (L0 KERNEL, L1 GOVERNANCE, L2 OPERATOR, L3 VERTICAL, L4 CONTENT) com capabilities específicas
- Mapeamento `permission_registry.risk` → Trust Tier (ADR 0065 alinhada)
- Schema do actor manifest YAML (referência cruzada com Artigo 6)
- Regras de promotion/demotion (Wagner aprova explicitamente)
- Auto-onboarding de IA externa (default L3 conservador)

### 2. Architecture & Scope v1.0.0 ratificada

[`memory/governance/ARCHITECTURE.md`](../governance/ARCHITECTURE.md) v1.0.0 mapeia:

- **30 módulos atuais** com categoria + trust-alvo + decisão
- **Renomeações aprovadas pelo Wagner em sessão (2026-05-05):**
  - `Modules/Copiloto` → `Modules/Jana` (rename, nome canônico da IA)
  - `Modules/Essentials` (parte) → `Modules/Notas` (extração gradual; Essentials L3 mantém HRM herdado)
  - `Modules/PontoWr2` → `Modules/Ponto` (rename, tirar legacy WR2)
  - `Modules/ProjectMgmt` → `Modules/Project` (após delete legado)
  - `Modules/MemCofre` → `Modules/SRS` (repurpose: System Rules Spec)
- **Depreciações aprovadas:**
  - `Modules/Writebot` — DELETE direto (vazio funcional)
  - `Modules/Project` (legado) — extrair info útil → DELETE
- **Plano de execução em 10 sub-fases (Fase 3.1 a 3.10)** totalizando ~33h

### 3. Drift identificado e mitigação

Audit detectou 9 controllers em módulo errado (5 em Copiloto, 4 em ADS). Todos documentados como `drift_alerts[]` no SCOPE.md de cada módulo afetado. Migração ocorre em Fase 3.7 (após renames).

[`Modules/ADS/SCOPE.md`](../../Modules/ADS/SCOPE.md) v1.0.0 criado nesta ADR como **seed pattern** pros outros 29 módulos. Inclui:
- `purpose` em 1-2 frases
- `contains[]` (controllers/features dentro do scope)
- `not_contains[]` (com ponteiro pro módulo correto)
- `trust_required: L1`
- `permission_prefix`, `url_prefixes`, `db_tables_owned`
- `drift_alerts[]` listando controllers a migrar
- frontmatter `charter_adr: 0080` linka aqui

### 4. Audit findings com plano de fix

P0 e P1 listados em [`audit-2026-05-05-v1.1.md`](../governance/audit-2026-05-05-v1.1.md) com ETAs propostos. Esta ADR **aceita** o plano e autoriza:

- **P0.1 + P0.2** (triggers MySQL) — ADR 0084 (futura) cria as migrations
- **P0.4** (mcp_actors) — ADR 0081 (futura) cria schema + IDENTITY-MESH.md (também resolve ADR 0077)
- **P1.1** (skills manifest) — script batch atualiza 15 SKILL.md frontmatter (Fase 3.2 paralela)
- **P1.2** (SCOPE.md) — Fase 3.3 começou nesta ADR (1 de 6 críticos: ADS)
- **P1.3** (camadas L2-L6) — distribuídas em ADR 0079 Fases 3-5

## Justificativa

**Por que consolidar 4 decisões em 1 ADR.** As 4 são interdependentes: Trust Tiers operacional depende de Architecture (módulos por tier); Architecture depende de Audit (drift atual); Audit findings dependem de Trust Tiers (priorização por tier). Separá-las exigiria 4 ADRs com cross-references repetidas. Uma ADR consolidada documenta a relação naturalmente.

**Por que ADS como seed SCOPE.md.** ADS é o módulo mais sensível (decision flow + skills governance) e mais drift (4 controllers errados). Se SCOPE.md funciona aqui, funciona pros outros 5 críticos. Maior área de risco vira maior área de aprendizado do template.

**Por que renomeações são aprovadas mas executadas só em Fase 3.7.** Documentar a decisão hoje ancora futuro; executar hoje quebraria URLs ativas + workflows do time. Fase 3.7 vem após SCOPE.md (3.3) — ordem importa porque rename mexe em namespace + URLs + permissions.

**Por que deletar Writebot agora vs Project depois.** Writebot é vazio (zero risk). Project legado tem dados (risk de perder histórico). Sequência: delete Writebot rápido → audit Project legado → extrair → delete.

**Por que MemCofre vira SRS por repurpose, não por novo módulo.** MemCofre tem infra de imutabilidade + entidades Doc* + DB tables. Repurpose preserva código existente; criar SRS novo duplicaria. Trade-off aceitável: nome vira "SRS" (técnico) em vez de "MemCofre" (semântico Wagner usava antes).

**Reabrir esta decisão se:** auditoria descobrir drift adicional não capturado, time crescer >10 pessoas exigindo tiers refinados, ou incidente de credencial comprometida exigir demoção emergencial.

## Cascade Review (cumprindo §10.4)

**Mudança aplicada:** este ADR cria L3 Trust Tiers (TRUST-TIERS.md) + L5 Module Charter parcial (1 SCOPE.md) + atualiza Architecture mapping.

**Cascata obrigatória:** L4 Identity Mesh + L6 Policy Gating + L7 Audit + Skills + ADRs cross-cutting.

| Camada | Auditada? | Resultado | Ação derivada |
|---|---|---|---|
| L4 Identity Mesh | ✅ sim | Schema mcp_actors ainda ausente. Manifest schema definido em TRUST-TIERS.md §4. | ADR 0081 (Fase 4) cria tabela e popula com 5 actors do time |
| L5 Module Charter | ✅ sim | 1/29 SCOPE.md (ADS). Pattern definido. | Fase 3.3 cria 5 críticos restantes; 3.4 cria os 24 demais |
| L6 Policy Gating | ✅ sim | mcp_governance_rules + permission_registry mapeiam pra tiers via ADR 0065 + TRUST-TIERS §3 | ActionGate middleware (Fase 5) implementa enforcement |
| L7 Audit | ✅ sim | mcp_audit_log existe; trigger ausente (P0.2) | ADR 0084 (Fase 5) cria trigger + UI |
| Skills (cross-cutting) | ✅ sim | 15/16 sem manifest (P1.1) | Fase 3.2 batch update |
| ADRs (cross-cutting) | ✅ sim | ADR 0077 absorvida em ADR 0081 (Identity Mesh resolve mcp_handle bug) | atualizar ADR 0077 status |

**Conclusão cascada:** sem novos gaps descobertos. Plano consolidado em ADR 0079 Fases 3-5.

## Consequências

**Positivas:**

- **Wagner pode operar como root informado.** Trust Tiers explícitos + actor manifest tornam claro quem pode fazer o quê. UI de governança (Fase 5) materializa.
- **Drift detectável.** SCOPE.md + pre-commit hook (Fase 3.6) capturam controllers em módulo errado antes do merge.
- **Renomeações documentadas.** Wagner não vai esquecer Copiloto→Jana porque ARCHITECTURE.md lembra, com fase de execução clara.
- **Compliance audit-friendly.** Auditor externo lê audit-2026-05-05-v1.1.md + Constituição v1.1.0 + plano de P0 fixes. Trail forense organizado.

**Negativas / Trade-offs:**

- **4 P0 blockers exigem fix em ≤7 dias.** Triggers MySQL e mcp_actors criação não podem esperar.
- **15 skills precisam batch update de frontmatter.** Não bloqueia uso, mas precisa fazer.
- **SCOPE.md em 30 módulos = ~12h de trabalho.** Distribuído entre Fase 3.3 (críticos) e 3.4 (resto, delegável).
- **Renames em Fase 3.7 trazem risk operacional.** URLs antigas → 301 redirects. Permission slugs → migration. Tabelas DB mantém prefix legacy.

**Riscos mitigados:**

- **Drift novo entrar.** Pre-commit hook (Fase 3.6) + cascade review (§10.4) previnem.
- **Renames quebrarem prod.** SCOPE.md já escrito antes do rename; URLs antigas preservadas via 301.
- **Auditoria fiscal questionar imutabilidade.** Triggers MySQL (P0.1, P0.2) fecham em ≤2 dias.
- **Tokens MCP sem rastreabilidade.** mcp_actors (P0.4) cria registro canônico antes de IAs externas conectarem em escala.

## Próximas ADRs derivadas

- **ADR 0081** — Identity Mesh: schema `mcp_actors` + IDENTITY-MESH.md + migração de tokens (resolve ADR 0077)
- **ADR 0082** — Module Charter: SCOPE.md template canônico + cache `mcp_modules` + tool MCP `modules-fetch` + drift hook
- **ADR 0083** — ActionGate middleware + UI Governance consolidada (`/governance` dashboard)
- **ADR 0084** — Audit append-only triggers (`ponto_marcacoes`, `mcp_audit_log`) + retention 5y + UI dashboard
- **ADR 0085** — Renames executados (Copiloto→Jana, PontoWr2→Ponto, MemCofre→SRS, ProjectMgmt→Project) — uma ADR por rename ou consolidada

## Referências

- [Constituição v1.1.0](../governance/CONSTITUTION.md)
- [Audit Cascade v1.1](../governance/audit-2026-05-05-v1.1.md)
- [Trust Tiers v1.0.0](../governance/TRUST-TIERS.md)
- [Architecture & Scope v1.0.0](../governance/ARCHITECTURE.md)
- [SCOPE.md ADS (seed)](../../Modules/ADS/SCOPE.md)
- [ADR 0079 — Constituição](0079-constituicao-oimpresso-7-camadas-governanca.md)
- [ADR 0065 — Permission Registry](0065-permission-registry-contract.md)
- [ADR 0077 — MCP resolver mcp_handle](0077-mcp-resolver-owner-via-mcp-handle.md)
