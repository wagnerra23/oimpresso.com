# RUNBOOK — Fiscal/Cockpit (sub-página 1)

> **Tela:** `/fiscal`
> **Module:** `Modules/Fiscal` (thin agregador)
> **Page:** [`resources/js/Pages/Fiscal/Cockpit.tsx`](../../../resources/js/Pages/Fiscal/Cockpit.tsx)
> **Charter:** `Cockpit.charter.md`
> **Controller:** [`CockpitController`](../../../Modules/Fiscal/Http/Controllers/CockpitController.php)
> **Permissão:** `fiscal.access`
> **PR origem:** PR #2 Wave consolidada (Cockpit + NFS-e + Eventos)

## 1. Objetivo

Visão consolidada do estado fiscal do mês em <3s: KPIs + sparklines + alertas determinísticos + 6 quick-link cards.

## 2. Estrutura

```
FxShell (wrapper compartilhado)
├── Hero (Cockpit fiscal + env badge crit count)
├── SubNav (sub-páginas 1-7 — 4 ativas, 3 disabled)
├── Body
│   ├── 6 KPIs (emitidas/autorizadas/rejeitadas/faturamento/dfe/cert)
│   ├── Alertas (3 níveis crit/warn/info — render condicional)
│   └── 6 Quick-link cards (drill-down sub-páginas)
└── Footer cheatsheet sticky
```

## 3. Dados (eager — não-defer)

- **KPIs**: `NfeEmissao::count() / sum()` filtrados por `emitido_em >= startOfMonth()`
- **Sparklines (14d)**: 1 query `GROUP BY DATE(emitido_em), status` + agregação PHP
- **Alertas**:
  - **crit** = rejeições últimos 7d (limit 2) + cert vencendo ≤7d
  - **warn** = cert vencendo 8-60d + DF-e pending >10
  - **info** = DF-e pending 1-10

## 4. Permissões

- `fiscal.access` (já existe via PR #1) — único gate desta tela

## 5. Multi-tenant Tier 0

Todos os 4 Models lidos (NfeEmissao, NfseEmissao, NfeDfeRecebido, NfeCertificado) usam `HasBusinessScope` (ADR 0093). Sem `withoutGlobalScopes` no Controller.

## 6. Quick links (status atual)

| # | Sub-página | URL | Ativo? |
|---|---|---|---|
| 2 | NF-e · NFC-e | `/fiscal/nfe` | ✅ PR #1 |
| 3 | NFS-e | `/fiscal/nfse` | ✅ PR #2 |
| 4 | DF-e manifesto | — | 🔒 backlog |
| 5 | Eventos | `/fiscal/eventos` | ✅ PR #2 |
| 6 | Cert & Cfg | — | 🔒 backlog |
| 7 | SPED & Livros | — | 🔒 backlog |

## 7. Riscos

- **R1**: sparkline query lenta com >100k notas/mês — mitigação: index em `emitido_em` (já existe)
- **R2**: cert sem `valido_ate` populado retorna `null` — UI mostra "sem cert ativo"
- **R3**: DF-e pending pode ter PII no nome_emitente — exibido só count (sem detalhes)

## 8. Smoke pós-merge biz=1

```bash
# 1. Curl literal:
curl -sv "https://oimpresso.com/fiscal" -H "Cookie: laravel_session=..." 2>&1 | grep '^< HTTP'
# Esperado: < HTTP/2 200

# 2. Negativo (sem fiscal.access): < HTTP/2 403
```

---

**Atualizado:** 2026-05-20 — criação inicial PR #2 Wave consolidada Cockpit
