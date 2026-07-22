---
slug: governance-readme
title: "Governança do Oimpresso — mapa das 7 camadas"
type: governance-index
authority: canonical
lifecycle: ativo
maintained_by: wagner
last_updated: 2026-07-22
version: 1.1
related:
  - 0079-constituicao-oimpresso-7-camadas-governanca
pii: false
---

# Governança do Oimpresso — mapa das 7 camadas

<!-- documentation-entrypoint: route:governanca -->

> **Você chegou aqui pela rota governança** do [`README.md` da raiz](../../README.md). Este arquivo é uma porta local da pasta: leia os artefatos referenciados conforme precisar.

---

## Princípio fundamental

A **soberania é do Wagner**. Toda autoridade abaixo é capability **delegada explicitamente** — humano ou IA. Default-deny. Em caso de dúvida, escala pra Wagner.

Esta governança existe pra suportar 4 restrições reais que o oimpresso já carrega:

1. **Multi-tenancy** — `business_id` invariante; vazamento entre tenants destrói o negócio
2. **LGPD + Portaria 671/2021 + NF-e/NFSe** — restrições legais brasileiras inegociáveis
3. **Time pequeno + IAs conectando** — humanos e agentes IA convivem; cada um com capability declarada
4. **Arquitetura modular já existente** — drift entre módulos exige fronteira documentada

---

## As 7 camadas

```
┌─────────────────────────────────────────────────────────────────────┐
│  L1  CONSTITUTION                              (10 artigos supremos) │   ← imutável; só Wagner+ADR muda
│      memory/governance/CONSTITUTION.md                               │
└─────────────────────────────────────────────────────────────────────┘
                                  │ deriva
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│  L2  SRS — System Rules Spec                       (append-only)     │   ← regras detalhadas implementam Constituição
│      memory/governance/srs/NNNN-*.md                                 │
└─────────────────────────────────────────────────────────────────────┘
                                  │ aplica via
              ┌───────────────────┼───────────────────┐
              ▼                   ▼                   ▼
┌──────────────────┐ ┌──────────────────┐ ┌──────────────────────────┐
│ L3 TRUST TIERS   │ │ L4 IDENTITY MESH │ │ L5 MODULE CHARTER         │
│   L0-L4          │ │   mcp_actors     │ │   Modules/<X>/SCOPE.md    │
│   default-deny   │ │   per-actor      │ │   fronteira por módulo    │
│                  │ │   manifest       │ │                           │
│ TRUST-TIERS.md   │ │ IDENTITY-MESH.md │ │ + cache mcp_modules       │
└──────────────────┘ └──────────────────┘ └──────────────────────────┘
              │                   │                   │
              └───────────────────┼───────────────────┘
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│  L6  POLICY GATING                          (mcp_governance_rules)   │   ← gate runtime; ALLOW/REVIEW/BLOCK
│      ActionGate middleware (Modules/Governance/ — futuro)            │
└─────────────────────────────────────────────────────────────────────┘
                                  │ tudo passa
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│  L7  AUDIT TRAIL                            (mcp_audit_log)          │   ← forense; append-only por trigger
│      Dashboard /governance/audit (futuro)                            │
└─────────────────────────────────────────────────────────────────────┘

Atravessam todas as camadas (cross-cutting):
  ADRs    — histórico de mudanças (memory/decisions/)
  Skills  — how-to operacional (.claude/skills/)
```

---

## Onde cada coisa vive

| Camada | Onde vive | Quem cria | Quem lê |
|---|---|---|---|
| **L1 Constitution** | `memory/governance/CONSTITUTION.md` | Só Wagner (com ADR formal) | Tudo e todos, sempre |
| **L2 SRS** | `memory/governance/srs/NNNN-*.md` (append-only) | Wagner via ADR | IAs leem antes de programar; Skills referenciam slugs SRS |
| **L3 Trust Tiers** | `memory/governance/TRUST-TIERS.md` | Wagner | ActionGate middleware |
| **L4 Identity Mesh** | `memory/governance/IDENTITY-MESH.md` (pattern) + tabela `mcp_actors` (instâncias) | Wagner cria actor; actor edita seu manifest até seu cap | ActionGate em toda request |
| **L5 Module Charter** | `Modules/<X>/SCOPE.md` + cache `mcp_modules` | Owner do módulo via PR | Pre-commit hook + UI |
| **L6 Policy Gating** | `mcp_governance_rules` (DB editável) | Wagner via UI Governance | Middleware em toda action |
| **L7 Audit** | `mcp_audit_log` (trigger append-only) | Sistema (auto) | Wagner inspeciona via UI |
| ADRs | `memory/decisions/NNNN-*.md` | Qualquer L2+ propõe; Wagner aprova | Tudo |
| Skills | `.claude/skills/<slug>/SKILL.md` | Wagner (ADS-Skills UI) | IAs auto-load por trigger |

---

## Como navegar

| Pergunta | Onde olhar |
|---|---|
| "Quais são os princípios invariáveis?" | `CONSTITUTION.md` (L1) |
| "Como implemento multi-tenancy?" | SRS por tema (L2) |
| "Posso fazer X sendo Y?" | Trust Tiers + meu manifest (L3+L4) |
| "Onde esse controller pertence?" | SCOPE.md do módulo (L5) |
| "Por que essa ação foi bloqueada?" | mcp_governance_rules (L6) + audit log (L7) |
| "Por que decidi X em Y?" | ADR (cross-cutting) |
| "Como faço Z na prática?" | Skill auto-load (cross-cutting) |

---

## Documentos canônicos da governance (esta pasta)

| Doc | Versão | Função |
|---|---|---|
| [`CONSTITUTION.md`](CONSTITUTION.md) | v1.1.0 | 10 artigos supremos + §10.4 cascade review |
| [`TRUST-TIERS.md`](TRUST-TIERS.md) | v1.0.0 | Operacionalização Art. 5 (L0-L4 + actor manifest) |
| [`ARCHITECTURE.md`](ARCHITECTURE.md) | v1.0.0 | 30 módulos + renomeações + depreciações + plano Fase 3 |
| [`ENFORCEMENT.md`](ENFORCEMENT.md) | v1.0.0 | 8 mecanismos NIST/Cedar/OPA × 7 camadas |
| [`audit-2026-05-05-v1.1.md`](audit-2026-05-05-v1.1.md) | — | Cascade audit cumprindo §10.4 |
| `srs/` | ⏸️ pasta vazia | System Rules Spec append-only (Fase 3+) |
| `IDENTITY-MESH.md` | ⏸️ pendente | Operacionaliza Art. 6 (Fase 4) |

## Estado de implementação (2026-05-05)

| Camada | Status | Próximo passo |
|---|---|---|
| L1 Constitution | ✅ v1.1.0 ratificada (audit cascata aplicada) | Wagner revisa Art 1-10 |
| L2 SRS | ⏸️ pasta vazia | SRS-0001 (multi-tenancy) na Fase 3 |
| L3 Trust Tiers | ✅ TRUST-TIERS.md v1.0.0 | mcp_actors precisa criar pra enforcement |
| L4 Identity Mesh | ⏸️ schema definido em TRUST-TIERS §4 | IDENTITY-MESH.md + mcp_actors (Fase 4) |
| L5 Module Charter | ⚠️ 1/30 SCOPE.md (ADS seed) | 5 críticos restantes na Fase 3.3 |
| L6 Policy Gating | ⚠️ mcp_governance_rules existe, ActionGate ausente | Fase 5 |
| L7 Audit | ⚠️ tabela popula, **trigger imutabilidade ausente (P0)** | Fase 3.x (urgente) + UI Fase 5 |
| Skills (cross-cutting) | ⚠️ 16 skills, mas 15/16 sem manifest (P1) | batch update Fase 3.2 |
| ADRs (cross-cutting) | ✅ 80 ADRs | continua organicamente |
| 8 Mecanismos enforcement | 1/8 implementado | Fases 3.5/3.6/5 |

---

## Princípios da governança (não os artigos — a META-disciplina)

1. **Default-deny.** Sem capability declarada, ação não acontece.
2. **Append-only onde a lei exige.** UPDATE/DELETE em categoria imutável = bug.
3. **Auditoria total.** Toda ação L2+ deixa rastro em `mcp_audit_log`.
4. **Ai lida com IA escrevendo código.** Skills auto-load colocam regra no contexto antes da ação. Governance por contexto, não só middleware.
5. **Evolução por ADR.** Toda mudança em camada L1 ou L2 requer ADR formal antes de virar código.
6. **Reversibilidade.** Decisão errada deve ser revogável em <1h. Sem mudança L1/L2 sem rollback plan.
7. **Wagner é root.** Capability concedida não vira capability adquirida — Wagner pode revogar a qualquer momento.

---

## Como esta governança evolui

- **Mudança em L1 (Constitution):** ADR formal + version bump (semver) + Wagner aprova explicitamente. Aviso pro time.
- **Mudança em L2 (SRS):** ADR + nova entry append-only. Antiga vira `superseded`. Skills referenciando o slug antigo ainda funcionam até atualizadas.
- **Mudança em L3-L5:** ADR + edição direta. Histórico fica em git.
- **Mudança em L6 (policies):** UI de governança permite Wagner editar com audit obrigatório. Toda mudança vira row em `mcp_governance_rule_history`.
- **L7 (audit):** nunca muda. Append-only por trigger MySQL.

---

## Para times que entrarem (Felipe, Maiara, Luiz, Eliana, IAs externas)

1. **Leia `CONSTITUTION.md` antes de qualquer coisa.** É o nível mais alto.
2. **Confirme seu trust_level** com Wagner. Define o que pode tocar.
3. **Antes de mexer em módulo X, leia `Modules/X/SCOPE.md`.** Define fronteira.
4. **Toda dúvida arquitetural — busque ADR antes de propor.** Provavelmente já está decidido.
5. **Skills auto-load pra você.** Quando uma skill carrega no contexto, é regra que precisa seguir, não sugestão.

---

> **Mantido por:** Wagner Rocha
> **Última versão:** 1.0 (2026-05-05)
> **Mudanças:** ver ADRs ≥ 0079 com tag `governance`
