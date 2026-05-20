# RUNBOOK — Fiscal/Eventos (sub-página 5)

> **Tela:** `/fiscal/eventos`
> **Page:** [`resources/js/Pages/Fiscal/Eventos.tsx`](../../../resources/js/Pages/Fiscal/Eventos.tsx)
> **Charter:** `Eventos.charter.md`
> **Controller:** [`EventosController`](../../../Modules/Fiscal/Http/Controllers/EventosController.php)
> **Permissão:** `fiscal.access`
> **PR origem:** PR #2 Wave consolidada

## 1. Objetivo

Timeline append-only de eventos SEFAZ aplicados a notas — CC-e + Cancelamento + EPEC + Manifestação destinatário. Auditoria LGPD Art. 37 + revisão fiscal.

## 2. Estrutura visual (timeline vertical)

```
FxShell
├── Hero (Eventos + crumb count autorizados + select período 7/30/90d)
├── SubNav (highlighted fiscal_eventos)
├── Body
│   └── fx-timeline (linha vertical + bullet colorido por kind)
│       ├── Badge (CC-e/Cancelamento/EPEC/Manifesto · cor)
│       ├── Link pra emissão (NF-e NNNN → /fiscal/nfe?focus=N)
│       ├── cstat evento
│       └── Justificativa truncada (200 chars)
└── Cheatsheet
```

## 3. Tipos SEFAZ (tpEvento → kind)

| tpEvento | Kind | Label PT-BR | Cor |
|---|---|---|---|
| 110110 | `cce` | Carta de correção | ok (verde) |
| 110111 | `cancel` | Cancelamento | bad (vermelho) |
| 110140 | `epec` | EPEC (contingência) | warn (âmbar) |
| 210200 | `manifest` | Manifesto · Confirmação | fis (rosa) |
| 210210 | `manifest` | Manifesto · Ciência | fis |
| 210220 | `manifest` | Manifesto · Desconhecimento | fis |
| 210240 | `manifest` | Manifesto · Não realizada | fis |

**Não em NfeEvento** (Models separados):
- Inutilização (110000 — NfeInutilizacao)
- Carta de Correção retroativa (legacy — não usar)

## 4. Permissão

`fiscal.access` — gate único (eventos são audit, todos com acesso ao módulo veem).

## 5. Multi-tenant + append-only

- HasBusinessScope (ADR 0093)
- `NfeEvento::UPDATED_AT = null` — append-only by design (CONFAZ SINIEF 07/2005 Art. 14)

## 6. Riscos

- **R1**: `payload_json` pode ser grande + ter PII — NÃO exibido (Controller só passa `justificativa` truncada)
- **R2**: Eager `with('emissao')` adiciona JOIN — 1 query extra, OK pra paginate 50

## 7. Smoke biz=1

```bash
curl -sv "https://oimpresso.com/fiscal/eventos" -H "Cookie: ..." 2>&1 | grep '^< HTTP'
# Esperado: < HTTP/2 200
```

---

**Atualizado:** 2026-05-20 — criação inicial PR #2 Wave consolidada
