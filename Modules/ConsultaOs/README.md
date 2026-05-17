# Modules/ConsultaOs

Portal publico (sem auth) para cliente final acompanhar status de Ordem de Servico (OS) — fluxo orcado → aprovacao → producao → acabamento → expedicao → entregue.

## Status

**Mock-only** — 4 OS fake no `MockConsultaOsRepository`. Migrar pra `transactions` real esta no backlog (US-CONSULTA-001 em `memory/requisitos/ConsultaOs/SPEC.md`).

## Arquitetura (D4 SoC brutal — Wave 18)

```
Routes/web.php (throttle:30,1)
  → ConsultaOsController (validacao + auditoria PiiRedactor)
    → ConsultaOsMockService (orquestra busca + filtro estagio + OTel span)
      → ConsultaOsRepositoryInterface (contrato)
        → MockConsultaOsRepository (impl atual)
        → RepairConsultaOsRepository (TODO US-CONSULTA-001 — transactions real)
```

Trocar fonte = 1 linha em `ConsultaOsServiceProvider::register()`.

## Como cliente usa

1. Vendedor entrega: "Sua OS e 4821, acompanhe em https://oimpresso.com/consulta-os"
2. Cliente acessa portal Inertia React (`/consulta-os`)
3. Digita numero (alpha_num + max:20 via FormRequest) + escolhe estagio (opcional)
4. Recebe JSON: `{found: true, os: {client, contact, stage, items[]}}` ou `404 {found: false}`

Nunca expoe `business_id`, `total_final`, `lucro`, `cliente_cpf`, `cliente_cnpj` (D7 LGPD + ADR 0093).

## Conformidade

- **D8 Security:** `ConsultaPublicaRequest` valida `alpha_num + max:20`; route middleware `throttle:30,1` (30 req/min anti-enumeration); `404` opaco em not_found OU stage_mismatch (nao confirma existencia).
- **D7 LGPD:** auditoria via `PiiRedactor` (cobre CPF/CNPJ/email se cliente cola no campo errado) + IP truncado /24 (ipv4) ou /48 (ipv6) — pseudonimizacao. Retencao 365d via `Config/retention.php`.
- **D9 OTel:** `ConsultaOsMockService` envolve busca em `OtelHelper::span('consultaos.busca_publica')`.

## Smoke E2E

```bash
php artisan test --filter=CustomerJourneyTest --testsuite=consultaos
```

Cobre acesso portal + busca OS conhecida + filtro estagio + 404 limpo + brute-force bloqueado + payload sem PII.

## Refs

- `memory/requisitos/ConsultaOs/SPEC.md` (US-CONSULTA-001 — migrar mock → real)
- [ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0
- [ADR 0155](../../memory/decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md) — rubrica D1-D9
- `Modules/Repair/Routes/web.php` — padrao a imitar (`/repair-status`, `/post-repair-status`)
