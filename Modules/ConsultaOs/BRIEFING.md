# BRIEFING — Modules/ConsultaOs

> 1-pager executivo do portal publico de consulta de OS.
> Atualizar a cada PR que altere capacidades/diferenciais. Skill `brief-update` Tier B auto-ativa.

## Em uma frase

Portal **publico (sem auth)** onde cliente final acompanha em tempo real o estagio de producao de uma OS via numero compartilhado pelo vendedor — sem expor dados financeiros, PII bruta ou multi-tenant.

## Mercado e posicionamento

| Concorrente | Stack | Onde oimpresso ganha |
|---|---|---|
| **OS-Track** | SaaS BR isolado | Modular junto com Repair/Sells/NFe (sem 3 fornecedores) |
| **MeuPedido** | SaaS generico | Multi-tenant Tier 0 IRREVOGAVEL + payload publico curado (sem leak de margem/custo) |
| **Mensageria SMS/WA manual** | sem painel cliente | UX self-service 24/7 + 404 opaco anti-enumeration |

## Stack e arquitetura

- **Backend:** `Modules/ConsultaOs/` (nWidart) — Laravel 13.6 + PHP 8.4
- **Controllers (3):** `ConsultaOsController` (publico), `DataController` (sidebar admin), `InstallController` (1-click ADR 0024).
- **Service (D4):** `ConsultaOsMockService` orquestra busca + filtro estagio + span OTel.
- **Repository (D4):** `ConsultaOsRepositoryInterface` + `MockConsultaOsRepository` (impl atual). Substituir por `RepairConsultaOsRepository` em US-CONSULTA-001.
- **FormRequest (D8):** `ConsultaPublicaRequest` valida `alpha_num + max:20` + lista controlada de estagios.
- **Frontend:** React 19 + Inertia v3 — pagina `ConsultaOs/Index.tsx` opera client-state + fetch JSON.

## Capacidades canon

✅ **Em prod (mock-only):**
- Acessar portal publico `/consulta-os` (Inertia React).
- Buscar OS por numero (4 OS fake: Acme/Padaria/Clinica/Escola).
- Filtrar por estagio (orcado/aprovacao/producao/acabamento/expedicao/entregue/todos).
- Throttle 30 req/min anti-enumeration.
- Auditoria via PiiRedactor + IP truncado /24 (LGPD pseudonimizacao).
- OTel span `consultaos.busca_publica` (D9 observabilidade).

🟡 **Backlog (US-CONSULTA-001):**
- Substituir `MockConsultaOsRepository` por query real em `transactions` (Repair) — invoice_no + ultimos 4 do telefone (padrao Repair).
- Resolver `business_id` via lookup do protocolo + rate-limit por IP global (defesa em profundidade).
- Canary 7d ROTA LIVRE antes de outros tenants (US-CONSULTA-002).

## Diferenciais

1. **Modular nativo** — ja integrado ao Repair/Sells/NFe; nao requer 3 fornecedores.
2. **Payload curado D7 LGPD** — cliente NUNCA ve `business_id`, `total_final`, `lucro`, `cliente_cpf`, `cliente_cnpj`.
3. **404 opaco** — same response em `not_found` e `stage_mismatch` (anti-enumeration).
4. **Repository pattern D4** — trocar mock → real = 1 linha bind (zero refactor Controller).

## Bloqueadores Wagner

- Decidir mapping definitivo: invoice_no + ultimos 4 telefone vs UUID publico (US-CONSULTA-001).
- Aprovar canary 7d em ROTA LIVRE pre-outros tenants.

## Risks ativos

- 🟡 **Mock-only em prod** — UX validada mas dados nao refletem ordens reais. Mitigation: backlog US-CONSULTA-001 prioridade media (sem cliente reportando dor — ADR 0105).
- 🟢 **Brute-force enumeration** — throttle:30,1 + 404 opaco + validation FormRequest defesa em profundidade.

## Smoke E2E

```bash
php artisan test --filter=CustomerJourneyTest --testsuite=consultaos
```

9 cenarios: acesso portal + busca conhecida + payload sem PII + 404 limpo + filtro estagio + brute-force + throttle + filtro `todos`.

## Cliente piloto

- **Atual:** mock-only — sem cliente real reportando uso ativo (ADR 0105 sinal qualificado).
- **Proximo:** ROTA LIVRE (canary 7d) quando US-CONSULTA-001 entregar query real.

## ADRs centrais

- [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0 IRREVOGAVEL.
- [ADR 0153](../decisions/0153-module-grade-v1.md) / [0155](../decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md) — rubrica governance.

## Ultimo update

**Atualizado:** 2026-05-16 Wave 18 — extract Service/Repository + README/CHANGELOG/BRIEFING.
**Proximo update esperado:** quando US-CONSULTA-001 mergear (trocar mock → real).
