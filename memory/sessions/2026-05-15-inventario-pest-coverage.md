---
data: 2026-05-15
sessao: practical-varahamihira-f99694
tema: Inventario Pest coverage + spawn 8 agents paralelos
tipo: session-log
---

# Inventario Pest coverage 2026-05-15 + plano paralelo

## Contexto

Wagner pediu (texto): *"Pode fazer um iventario do sitema? testar e criar teste para coordenar tudo temos muito tokens pode automatizar todos os testes aTÉ AMANHA?"*

Escopo confirmado via AskUserQuestion:
- **Opcao 2**: Tudo paralelo agora — inventario + Pest critico hoje
- **Opcao A** (restricao): Pest APENAS (zero produção, zero migration nova) — agents so criam tests/

## Mapa Modules x Pest coverage

**34 Modules** descobertos via `Glob Modules/*/module.json`.

**18 registrados em phpunit.xml** (Feature ou Unit):
ADS (Unit), Admin, Arquivos, Cms, ComunicacaoVisual, Essentials, Financeiro, Jana, KB, NfeBrasil, OficinaAuto, Ponto, ProjectMgmt, RecurringBilling, Repair, TeamMcp, Vestuario, Whatsapp.

**16 SEM registro phpunit.xml** (potencial falsa cobertura ou zero cobertura):

| Modulo | Tests/ existem? | Controllers | Entities/Models | Prioridade |
|---|---|---|---|---|
| **Auditoria** | ✅ 2 tests (Pest.php + AuditoriaModuleTest) MAS nao registrado | 3 | 0 | **P0 FATAL** (falsa cobertura — proibicao CLAUDE.md) |
| **Crm** | ❌ 0 | 21 | 11 | **P0** (PII cliente, alta superficie) |
| **Manufacturing** | ❌ 0 | 6 | 3 | **P0** (FSM canon via OficinaAuto) |
| **NFSe** | ❌ 0 | 3 | 4 | **P0** (fiscal!) |
| **Connector** | ❌ 0 | 30 | 0 | **P0** (integracao externa) |
| **Accounting** | ❌ 0 | 12 | **70** | P1 |
| **Superadmin** | ❌ 0 | 14 | 4 | P1 (governanca) |
| **Officeimpresso** | ❌ 0 | 7 | 2 | P1 (legacy bridge) |
| SRS | ❌ 0 | 8 | 7 | P2 |
| AssetManagement | ❌ 0 | 7 | 4 | P2 |
| Governance | ❌ 0 | 6 | 0 | P2 |
| Spreadsheet | ❌ 0 | 3 | 2 | P3 |
| ProductCatalogue | ❌ 0 | 3 | 0 | P3 |
| ConsultaOs | ❌ 0 | 3 | 0 | P3 |
| Brief | ❌ 0 | 3 | 0 | P3 |
| Woocommerce | ❌ 0 | 4 | 1 | P3 |

## Estrategia ofensiva: 8 agents paralelos (Wave A)

Cada agent recebe 1 modulo isolado. Restricoes Tier 0 IRREVOGAVEIS:
- APENAS Write/Edit em `Modules/<X>/Tests/Feature/**` (zero Controller/Service/Migration)
- biz=1 (ADR 0101) com fallback cross-tenant biz=99 isolation
- business_id global scope obrigatorio
- PT-BR em comentarios + texto
- ZERO git ops (parent consolida)
- Output: lista arquivos criados + 1 linha sumario por arquivo

Cada agent deve criar minimo 2 tests:
1. **MultiTenantIsolationTest** — Tier 0 cross-tenant biz=1 vs biz=99
2. **InstallControllerTest** ou **DataControllerTest** — smoke install/data
3. Bonus: 1 smoke Index/Create route (se Controller principal)

### Distribuicao (8 agents Wave A):

| Agent | Modulo | Foco minimo |
|---|---|---|
| A1 | Auditoria | Registrar phpunit.xml + completar smoke (ja tem 2 tests, gap = registro) |
| A2 | Crm | MultiTenantIsolation + InstallController + smoke 3 Controllers principais (Contact/Lead/Schedule) |
| A3 | Manufacturing | MultiTenantIsolation + RecipeController smoke (FSM canon) |
| A4 | NFSe | MultiTenantIsolation + smoke emissao route (fiscal) |
| A5 | Connector | MultiTenantIsolation + smoke 3 Controllers top (30 total = sample) |
| A6 | Accounting | MultiTenantIsolation + smoke 3 entities top (70 entities = sample) |
| A7 | Superadmin | MultiTenantIsolation + governanca smoke |
| A8 | Officeimpresso | MultiTenantIsolation + bridge legacy smoke |

P2/P3 modulos (SRS, AssetManagement, Governance, Spreadsheet, ProductCatalogue, ConsultaOs, Brief, Woocommerce) ficam Wave B amanha — se Wave A entregar verde.

## Consolidacao parent (Claude main)

Apos agents terminarem:
1. Glob `Modules/*/Tests/Feature/*Test.php` (validar criacao)
2. Edit `phpunit.xml` adicionando 8 paths novos no testsuite Feature
3. `composer dump-autoload`
4. `./vendor/bin/pest --filter MultiTenantIsolation --parallel` (valida verde isoladamente)
5. `./vendor/bin/pest --parallel` (full suite — opcional se primeiro passar)
6. Create branch + commit + PR (1 PR consolidado, ≤300 linhas por convencao mas pode esticar p/ tests — flagging Wagner)

## Estado MCP no momento do disparo

- Cycle: CYCLE-06 Martinho prod + FSM rollout + Jana V2 demo (12d restantes)
- WIP: 3 HITL pending Wagner (CMS-001, FIN-004)
- ADRs recentes: 1388 Cascade Review, 1396 Onda 6, 1455 Screen-Pattern Reuse, 1462 KB Grafo
- Skill `brief-first` Tier A ja chamado via hook SessionStart
- Worktree: practical-varahamihira-f99694 (isolada)
