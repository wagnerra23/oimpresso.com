---
slug: oimpresso-architecture
title: "Arquitetura e Escopo do Oimpresso ERP — estado atual e estado-alvo"
type: architecture
authority: canonical
lifecycle: ativo
version: 1.0.0
maintained_by: wagner
last_updated: 2026-05-05
charter_adr: 0080
related:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
pii: false
---

# Arquitetura e Escopo do Oimpresso ERP

> **Versão 1.0.0 — 2026-05-05**
> **Hierarquia:** subordinada à [Constituição](CONSTITUTION.md) v1.1.0 (Artigo 7 — Module Charter)
> 👋 **Visão humana rápida:** [GUIA-DO-SISTEMA.md](../GUIA-DO-SISTEMA.md) (produto + como usar numa página). Este doc é o detalhe técnico arc42. **Contagens/renames abaixo são snapshot 2026-05-05** — estado vivo em [modulos/INDEX.md](../modulos/INDEX.md).

Documento operacional que mapeia (a) os 30 módulos atuais, (b) o estado-alvo após renomeações + depreciações aprovadas, (c) trust level por módulo, (d) plano de execução por fase.

---

## §1. Vista geral em 1 slide

```
┌──────────────────── L1 CONSTITUTION (10 artigos) ────────────────────┐
│                                                                       │
│  ┌──────────── Cross-cutting ────────────┐                            │
│  │  ADRs (memory/decisions/)              │                            │
│  │  Skills (.claude/skills/)              │                            │
│  └─────────────────────────────────────────┘                          │
│                                                                       │
│  ┌──────────── L2 SRS (System Rules Spec) ─────────┐                 │
│  │  memory/governance/srs/* (append-only)           │                 │
│  └───────────────────────────────────────────────────┘                │
│                                                                       │
│  L3 Trust Tiers     L4 Identity Mesh     L5 Module Charter            │
│  (TRUST-TIERS.md)   (mcp_actors)         (Modules/<X>/SCOPE.md)       │
│                                                                       │
│  L6 Policy Gating (mcp_governance_rules + ActionGate)                 │
│                                                                       │
│  L7 Audit (mcp_audit_log + trigger append-only + dashboard)           │
│                                                                       │
└───────────────────────────────────────────────────────────────────────┘

                  Modules/ (30 atuais → 27 após Fase 3)

  KERNEL L0          GOVERNANCE L1         PRODUCT L2        VERTICAL L3
  ─────────────      ─────────────         ─────────────     ─────────────
  Connector          ADS (decisions)        Jana ←Jana    Financeiro
  Superadmin         TeamMcp                Notas ←Essentials NfeBrasil
  schema raiz        Governance(NEW)        KB                NFSe
                     SRS ←MemCofre          Project ←ProjMgmt RecurringBilling
                                            Ponto ←PontoWr2   Officeimpresso
                                            ConsultaOs        Repair
                                                              Manufacturing
                                                              Grow
                                                              Crm
  CONTENT L4
  ─────────────
  Cms (landing)
```

---

## §1-bis. Vista de runtime (C4-Container — onde roda + fluxo)

```mermaid
C4Container
    title Oimpresso ERP — Container view (runtime + fluxo de conhecimento)
    Person(dev, "Time dev", "Wagner/Felipe/Maiara via Claude Code")
    Person(cliente, "Cliente", "Larissa biz=4 etc")
    System_Ext(github, "GitHub origin/main", "fonte de verdade do código + memory/")

    System_Boundary(hostinger, "Hostinger — ERP web (sem daemons, ADR 0062)") {
      Container(erp, "ERP Laravel 13.6", "PHP 8.4 + Inertia/React", "36 módulos · multi-tenant business_id Tier 0")
      ContainerDb(mysql, "MySQL u906587222", "MySQL", "dados de negócio + mcp_*")
    }
    System_Boundary(ct100, "CT 100 — daemons/IA (tailscale)") {
      Container(mcp, "MCP server", "FrankenPHP+Octane", "decisions-search · kb-answer · memoria-search")
      Container(meili, "Meilisearch", "hybrid + embedder qwen3_local")
      Container(ollama, "ollama-embedder", "qwen3-embedding:0.6b")
      Container(daemons, "Centrifugo · WhatsApp · Langfuse · BGE", "daemons")
    }

    Rel(cliente, erp, "usa ERP + Jana chat")
    Rel(dev, mcp, "consulta conhecimento (MCP tools)")
    Rel(github, mcp, "webhook push → sync memory/ + grava SHA de main")
    Rel(github, hostinger, "deploy manual (git pull)")
    Rel(mcp, meili, "hybrid retrieval")
    Rel(meili, ollama, "embeddings")
    Rel(mcp, mysql, "DB compartilhado")
    Rel(erp, mysql, "")
```

> Detalhe de acesso/deploy: [reference/INFRA-ACESSO-CANON.md](../reference/INFRA-ACESSO-CANON.md). Reconcile/drift do estado: `governance:audit` (DriftCheckers).

---

## §2. Módulos — estado e destino

> **Contagem/lista VIVA (não duplicar aqui = não apodrece):** o nº de módulos vive em [modulos/INDEX.md](../modulos/INDEX.md) (auto-gerado por `php artisan module:specs`; hoje **44 detectados / 36 ativos**, não os "30" do mapa histórico abaixo). Responsabilidade de cada um: `Modules/<X>/SCOPE.md`. A tabela abaixo é o mapa curado de estado→destino.

| Módulo | Estado | Categoria | Trust-alvo | Decisão |
|---|---|---|---|---|
| **Jana** | active=1, 16 controllers, drift | IA | L2 PRODUCT | **Renomear → `Jana`** |
| **Essentials** | active=1, 19 controllers, herdado UltimatePOS HRM | UltimatePOS | L3 VERTICAL | **Manter Essentials L3** + criar **`Notas` (novo, L2)** com extração gradual |
| **PontoWr2** | active=1, 12 controllers, ponto eletrônico | Vertical BR | L3 VERTICAL | **Renomear → `Ponto`** |
| **ProjectMgmt** | active=1, 8 controllers Jira-style | Governance | L2 PRODUCT | **Renomear → `Project`** (após delete legado) |
| **Project** | active=1, 9 controllers, UltimatePOS legado | UltimatePOS | — | **Extrair info útil → DELETE** |
| **MemCofre** | active=1, 8 controllers, evidências | Governance | L1 GOVERNANCE | **Repurpose → `SRS` (System Rules Spec)** |
| **Writebot** | active=1, 2 controllers (boilerplate) | IA | — | **DELETE (vazio)** |
| **ADS** | active=0, 19 controllers | IA | L1 GOVERNANCE | Mantém. Receber drift de Jana |
| **KB** | active=1, 3 controllers (boilerplate) | Knowledge | L2 PRODUCT | Mantém. Receber MemoriaController + FontesController da Jana |
| **TeamMcp** | active=1, 5 controllers | Governance | L1 GOVERNANCE | Mantém. Receber tokens/scopes/audit/webhook da Jana + ProjectsController do ADS |
| **Connector** | active=1, 30 controllers, POS APIs | UltimatePOS | L0 KERNEL | Mantém — só Wagner toca |
| **Superadmin** | active=1, 14 controllers | UltimatePOS | L0 KERNEL | Mantém — só Wagner toca |
| **Crm** | active=1, 21 controllers | UltimatePOS | L3 VERTICAL | Mantém |
| **Manufacturing** | active=1, 6 controllers | UltimatePOS | L3 VERTICAL | Mantém |
| **Repair** | active=1, 9 controllers (canônico ref.) | Vertical | L3 VERTICAL | Mantém — referência canônica de imitação |
| **AssetManagement** | active=1, 7 controllers | UltimatePOS | L3 VERTICAL | Mantém |
| **Cms** | active=1, 5 controllers, landing/blog | Content | L4 CONTENT | Mantém |
| **Connector** | (já listado) | | | |
| **Accounting** | active=1, 12 controllers | UltimatePOS | L3 VERTICAL | Mantém — paralelo a Financeiro |
| **Financeiro** | active=0, 10 controllers, BR contas-a-pagar/receber | Vertical BR | L3 VERTICAL | Ativar quando pronto |
| **NFSe** | active=0, 3 controllers | Vertical BR | L3 VERTICAL | Spec-ready, ativar com NFe Brasil |
| **NfeBrasil** | active=0, 3 controllers | Vertical BR | L3 VERTICAL | Spec-ready |
| **Officeimpresso** | active=1, 7 controllers, licenciamento Office Impresso | Vertical | L3 VERTICAL | Mantém |
| **Grow** | active=0, 142 controllers, sistema produção Office Impresso | Vertical | L3 VERTICAL | **Auditar — 142 controllers é gigante; provável legado pra deprecar parcial** |
| **IProduction** | active=1, 2 controllers | UltimatePOS | L3 VERTICAL | Mantém |
| **ProductCatalogue** | active=1, 3 controllers | UltimatePOS | L4 CONTENT | Mantém |
| **RecurringBilling** | active=0, 3 controllers, BR Pix Automático | Vertical BR | L3 VERTICAL | Spec-ready |
| **Spreadsheet** | active=1, 3 controllers | UltimatePOS | L4 CONTENT | Mantém — uso interno |
| **Woocommerce** | active=1, 4 controllers | Integration | L3 VERTICAL | Mantém |
| **ConsultaOs** | active=0, 3 controllers, portal público | Public | L4 CONTENT | Ativar quando pronto |
| **Jana (NEW)** | a criar via rename de Copiloto | IA | L2 PRODUCT | Fase 3 |
| **Notas (NEW)** | a criar | Personal | L2 PRODUCT | Fase 3 — extração gradual de Essentials |
| **Governance (NEW)** | a criar | Governance | L1 GOVERNANCE | Fase 5 — ActionGate + UI |

**Total:** 30 atuais → após Fase 3: **27 ativos** (deletes: Writebot, Project legado) + **3 novos** (Jana via rename, Notas, Governance) + **2 renames preservando id de DB** (Ponto via rename de PontoWr2; Project via rename de ProjectMgmt) + **1 repurpose** (SRS via repurpose de MemCofre)

---

## §3. Renomeações aprovadas pelo Wagner (2026-05-05)

| De | Pra | Tipo | Por quê | Cuidados |
|---|---|---|---|---|
| `Modules/Copiloto` | `Modules/Jana` | rename | Nome canônico da IA do business | namespace, URLs `/copiloto/*`→`/jana/*` (com 301 redirects), permissões `copiloto.*`→`jana.*`, tabelas `copiloto_*` mantém prefixo legacy ou rename via migration cuidadosa |
| `Modules/Essentials` (parte) | `Modules/Notas` (novo) | extração | Notas pessoais + arquivo cliente + KB pessoal **fora** do HRM herdado | Essentials L3 mantém código UltimatePOS HRM; Notas L2 absorve gradualmente: Notes, Personal Tasks, Cliente Archive |
| `Modules/PontoWr2` | `Modules/Ponto` | rename | Tirar `WR2` (cliente externo) do nome do módulo | rename + URLs + namespace + tabelas `ponto_*` mantém prefixo |
| `Modules/ProjectMgmt` | `Modules/Project` | rename | Único `Project` (após delete legado) | DEPENDE: extrair Project legado primeiro |
| `Modules/MemCofre` | `Modules/SRS` | repurpose | Era cofre de evidências; vira System Rules Spec — regras imutáveis pra IA programar | rename + redefinir entities (`Doc*` → SRS entries) + adicionar trigger MySQL append-only |

> **Status (2026-05-06):** renames executados **PHP-only** (pasta+namespace; URLs/permissions/tabelas mantidas legacy) — [ADR 0088](../decisions/0088-module-rename-php-only.md) + erratum §4 v1.2 do [MODULE-DRIFT-MIGRATION-PLAN](MODULE-DRIFT-MIGRATION-PLAN.md). Os nomes antigos na coluna "De" são registro histórico do plano, não referência viva.

**Anti-padrões a evitar:**
- ❌ Rename **sem 301 redirect** quebra bookmarks/integrações
- ❌ Rename de **tabelas DB** sem migration testada quebra prod
- ❌ Mudar **permission slugs** sem migrar `permissions` table desliga acessos
- ❌ Renomear sem atualizar **`module.json` + ServiceProvider + namespace** quebra autoload

---

## §4. Depreciações aprovadas pelo Wagner

### `Modules/Writebot` — DELETE direto

- Estado: 2 controllers (DataController + InstallController boilerplate). Vazio funcional.
- Risco: zero. Nada referencia Writebot além do listing automático de módulos.
- Ação: `git rm -rf Modules/Writebot/` + remover de `modules_statuses.json` + remover permissions órfãs.
- ETA: 30min

### `Modules/Project` (legado UltimatePOS) — extrair info útil → DELETE

- Estado: 9 controllers (Project, ProjectTimeLog, Task, TaskComment, Invoice, Report, Activity, Data, Install).
- **Wagner:** "tem informações lá que gostaria de manter, mais a maioria é lixo"
- Plano:
  1. Auditar dados existentes em DB (queries SQL): há rows em `projects`, `project_tasks`, `project_time_logs`, `project_invoices`?
  2. Wagner identifica quais valem preservar (provavelmente histórico de invoice/timesheet de clientes específicos)
  3. Migrar dados úteis pra módulo correto (Financeiro pra invoices, Notas pra histórico de cliente?)
  4. `git rm -rf Modules/Project/` + drop tables vazias / arquivar com prefixo `_archived_`
- ETA: 4h (auditoria + extração + delete)

---

## §5. Trust level por módulo (estado-alvo, após Fase 3)

### L0 KERNEL — só Wagner toca

| Módulo | Por quê |
|---|---|
| `Modules/Connector` | API POS UltimatePOS — coração dos POS clientes |
| `Modules/Superadmin` | Pacotes + subscription multi-tenant |
| Schema raiz (migrations destrutivas, drop table) | Recovery impossível se errar |
| `mcp_audit_log` | Modificação = forense quebrado |

### L1 GOVERNANCE — Wagner + ADR aprovado

| Módulo | Por quê |
|---|---|
| `Modules/ADS` | Decision flow (Brain A/B, Policy, Confidence) — coração da autonomia futura |
| `Modules/TeamMcp` | Tokens, scopes, audit governance |
| `Modules/Governance (NEW)` | ActionGate + UI consolidada (Fase 5) |
| `Modules/SRS (ex-MemCofre)` | Regras imutáveis pra IA programar |

### L2 PRODUCT — operador aprovado + IA pareada com Skills

| Módulo | Por quê |
|---|---|
| `Modules/Jana (ex-Jana)` | Chat IA do business — feature visível, dev frequente |
| `Modules/Notas (NEW)` | Notas/tarefas/arquivo pessoal Wagner — alta freq de mudança |
| `Modules/KB` | Knowledge browser — leitura ampla, edit moderada |
| `Modules/Project (ex-ProjectMgmt)` | Tasks/cycles Jira-style — operacional do time |
| `Modules/Ponto (ex-PontoWr2)` | Ponto eletrônico — alta freq mas com guardrails legais |
| `Modules/ConsultaOs` | Portal cliente — leitura pública mas precisa cuidado L2 pra config |

### L3 VERTICAL — especialista (dev) + IA pareada

| Módulos | Por quê |
|---|---|
| Financeiro, NfeBrasil, NFSe, RecurringBilling | Compliance fiscal brasileiro — especialista necessário |
| Officeimpresso, Grow, IProduction | Verticais Office Impresso — especialista interno |
| Repair, Manufacturing, AssetManagement | UltimatePOS verticais — especialista UltimatePOS |
| Crm, Accounting, Essentials | UltimatePOS core — herdado, especialista UltimatePOS |
| Woocommerce | Integration — especialista WooCommerce |

### L4 CONTENT — editores

| Módulos | Por quê |
|---|---|
| `Modules/Cms` | Landing/blog — copy editing, baixo risco arquitetural |
| `Modules/Spreadsheet` | Uso interno — risco baixo |
| `Modules/ProductCatalogue` | Catálogo público — copy + media |

---

## §6. Princípios de organização modular

1. **1 conceito = 1 módulo.** Se não souber qual módulo, é porque o conceito não tem dono. Cria SCOPE.md ou ADR antes de código.
2. **SCOPE.md é lei.** Controller fora do SCOPE.md = drift. Pre-commit hook bloqueia.
3. **Trust level antes de feature.** Decidir trust_required antes de codar evita reescrever permissões depois.
4. **Renomear preservando 301 redirects.** Bookmarks de cliente + integrações não podem quebrar.
5. **DELETE de módulo legado é cerimônia.** Audit dados, extrair, marcar tabelas `_archived_`, deletar código.
6. **Rename de tabela DB é última opção.** Prefixos legacy (`copiloto_*` mantém após Copiloto→Jana) custam memória, mas zero risk.
7. **Active flag em module.json não é trust level.** Active=0 = não monta routes; trust = quem pode editar código. São ortogonais.

---

## §7. Plano de execução por fase

| Fase | Trabalho | Tempo | Bloqueador? |
|---|---|---|---|
| **3.1** | DELETE Writebot | 30min | nenhum |
| **3.2** | TRUST-TIERS.md (operacional, este sprint) | 1h | nenhum |
| **3.3** | SCOPE.md em 6 críticos: Jana(Jana), ADS, KB, SRS(MemCofre), TeamMcp, Project(ProjMgmt) | 4h | nenhum (faz com nomes atuais; rename na 3.7) |
| **3.4** | SCOPE.md no resto (24 módulos) | 8h | delegável a outros agentes/devs |
| **3.5** | mcp_modules table + tool MCP `modules-fetch` | 2h | depende 3.3 |
| **3.6** | Pre-commit hook drift detection (warn-only) | 1h | depende 3.5 |
| **3.7** | Renames: Copiloto→Jana, PontoWr2→Ponto, MemCofre→SRS | 6h | depende 3.3 (SCOPE.md já escrito) |
| **3.8** | Project legado: audit dados + extrair + DELETE | 4h | bloqueia 3.9 |
| **3.9** | Rename: ProjectMgmt → Project | 1h | depende 3.8 |
| **3.10** | Notas (NEW): scaffold módulo + extração gradual de Essentials | 6h | depende 3.7 (Jana feita pra reuso de pattern) |

**Total Fase 3:** ~33h distribuídos. P0 (audit cascata + triggers MySQL) e P1 (skills manifest) ficam em paralelo.

---

## §8. Skills carregadas por trust level

Skills auto-load por description match. Trust level da skill define quem pode editar a skill (não quem usa).

| Trust | Skill carregada por |
|---|---|
| L0 | Wagner em Connector/Superadmin/migrations destrutivas |
| L1 | Wagner em ADRs/SRS/Trust Tiers |
| L2 | Wagner+IA em Jana/Notas/KB/Project/Ponto |
| L3 | Especialista+IA em verticais |
| L4 | Editor em Cms/Spreadsheet/Catalogue |

A meta-skill `meta-skill-roi-erp-autonomo` é trust L1 — só Wagner edita.

---

## §9. Estado de implementação

| Item | Estado |
|---|---|
| ARCHITECTURE.md (este doc) | ✅ v1.0.0 |
| Renomeações decididas | ✅ aprovadas Wagner 2026-05-05 |
| Renomeações executadas | ⏸️ Fase 3.7 |
| Depreciações decididas | ✅ aprovadas Wagner |
| Writebot deletado | ⏸️ Fase 3.1 |
| Project legado extraído + deletado | ⏸️ Fase 3.8 |
| 6 SCOPE.md críticos | ⏸️ Fase 3.3 (1/6 esta sessão: ADS) |
| 30 SCOPE.md totais | ⏸️ Fase 3.4 |

---

## Histórico de versões

- **v1.0.0** (2026-05-05) — Vista inicial. 30 módulos mapeados, 5 renomeações + 2 depreciações + 1 repurpose decididos.
