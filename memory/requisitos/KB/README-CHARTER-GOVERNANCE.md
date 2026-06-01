---
doc: README — Pacote Charter Governance no KB
module: KB
status: índice do pacote
adr: 0243-charter-governance-kb
created: 2026-06-01
owner: wagner
---

# 📦 Pacote — Charter Governance no KB

> Pacote completo da decisão "charters viram nós governados do KB" (Wagner 2026-06-01, "faça o pacote completo… isso vai ajudar a tornar o sistema autônomo"). Este README é o **mapa**: o que é cada arquivo, onde tudo mora, quais processos usar e em que ordem executar.

## 1. Os arquivos do pacote (e onde moram)

**Decisão** — `memory/decisions/proposals/`
| Arquivo | Papel |
|---|---|
| [0243-charter-governance-kb.md](../../decisions/proposals/0243-charter-governance-kb.md) | **ADR proposta** — a decisão formal + resposta de autonomia. Promover → `memory/decisions/0243-*.md` quando Wagner aprovar (número provisório). |

**Conhecimento/planejamento** — `memory/requisitos/KB/` (módulo dono)
| Arquivo | Papel |
|---|---|
| [CONCEITO-CHARTER-GOVERNANCE-V1.md](CONCEITO-CHARTER-GOVERNANCE-V1.md) | Visão + estado-da-arte mundial 2026 (Backstage/Guru/Oxide/MADR/Gloaguen) + §12 autonomia/Champion |
| [SPEC-CHARTER-GOVERNANCE.md](SPEC-CHARTER-GOVERNANCE.md) | Backlog US-CHTR-NNN faseado F1→F4 + DoD + regras Gherkin (parseável → dogfooding do Module Charter) |
| [SCHEMA-CHARTER-GOVERNANCE-DELTA.md](SCHEMA-CHARTER-GOVERNANCE-DELTA.md) | Deltas D1–D10 → 2 migrations aditivas + observers + edges + permissions (pronto pra virar código) |
| [INTERFACE-CHARTER-KB.md](INTERFACE-CHARTER-KB.md) | A interface (tri-pane reuso + painel de governança) — a parte que Wagner pediu |
| [SCHEMA-DB-V1.md](SCHEMA-DB-V1.md) | (base existente — fundação do grafo KB) |

**Código** (na execução F1+) — `Modules/KB/` + `resources/js/Pages/kb/`
- Entities/Observers/Migrations/Services/Controllers em `Modules/KB/`
- Telas em `resources/js/Pages/kb/Charters/` (+ `*.charter.md` ao lado — dogfooding)
- Charters (núcleo) continuam `*.charter.md` no git, bridged pro KB (ADR 0061)

## 2. Processos escolhidos (e por quê)

| Processo / skill | Quando | Por quê |
|---|---|---|
| `preflight-modulo` (BLOQUEADOR) | antes de tocar `Modules/KB` | regra Tier 0 — ler SPEC/RUNBOOK/charter/ADRs antes |
| `multi-tenant-patterns` (Tier A) | toda entity/migration/service | `business_id` scope — vazar tenant é o pior bug (ADR 0093) |
| `commit-discipline` (Tier A) | todo PR | 1 PR = 1 intent, ≤300 linhas → execução faseada, não big-bang |
| `mwart-process` + `mwart-comparative` | telas (`/kb/charters`) | caminho único Blade→Inertia + gate visual Wagner aprova screenshot |
| `charter-write` | gerar drafts de charter | lê código → draft; Wagner revisa Non-Goals/Anti-hooks |
| `inertia-defer-default` | controllers com props caras | SPA-feel (lista + preview) |
| Pest GUARD + `module-completeness-audit` | antes de fechar US | invariantes Tier 0 (R-CHTR-001..004) + governança interna |
| Loop Cowork↔Code (ADR 0114) | UI | design supervisionado, Wagner aprova screenshot |

## 3. Ordem de execução (com gates Wagner)

```
F0  Aprovar ADR 0243  ───────────────────────────── ⛔ GATE: Wagner valida ângulo + confirma número
                                                       (e escolhe canal "publicar=PR": gh PR vs task MCP)
F1  Page charter governado (US-CHTR-001/002/003)
      migration D3 · status workflow · sugestão · aprovação ── ⛔ GATE: Pest verde + cross-tenant
F2  Module Charter read-only (US-CHTR-010/011/012)
      salva RequirementsFileReader · bridge · tela tri-pane ── ⛔ GATE: screenshot Wagner (mwart)
F3  Enforcement (US-CHTR-020/021/022)
      publicar=PR · charter-lint CI · Pest GUARD Non-Goals ─── ⛔ GATE: CI gate ativo + baseline
F4  Maturidade/autonomia (US-CHTR-030/031/032)
      cadência re-verificação · scorecard bronze→champion · trilha
```

**Cada fase = 1+ PRs ≤300 linhas.** F1 e F2 são independentes o suficiente pra paralelizar se Wagner quiser.

## 4. Como começar (quando Wagner der o "vai" no F1)

1. `preflight-modulo` em `Modules/KB` (ler este pacote + SCHEMA-DB-V1 + charter existente).
2. PR 1 (F1.a): migration D3 (`kb_comments` +kind/status) + Model + Pest cross-tenant. ≤300 linhas.
3. PR 2 (F1.b): `KbCharterController` (sugestão/aprovação) + permissions D5 + Pest invariantes.
4. PR 3 (F1.c): UI aba Governança no preview (sugestão/aprovação) — gate screenshot.
5. … (F2 inicia em paralelo: migration D8 + RequirementsFileReader migrado + bridge module-charter).

## 5. Definição de "pronto" do pacote (meta)

O sistema está **Champion** (no sentido do projeto) quando: charters governados em produção + CI gate ativo + ratchet travando regressão + **métrica provando que telas com charter governado sobem de nível mais rápido que sem** (loop fechado por métrica — princípio #4 da Constituição). Aí o charter deixou de ser doc e virou **motor de autonomia**.

---

**Status atual:** F0 aguardando Wagner. Pacote de decisão+planejamento+interface **completo**. Execução de código (F1+) segue `preflight` + `commit-discipline` faseado.
