---
id: resources-js-pages-fiscal-eventos-charter
page: /fiscal/eventos
component: resources/js/Pages/Fiscal/Eventos.tsx
related_prototype: prototipo-ui/cowork/fiscal-subpages.jsx
bundle_source: fiscal-page.jsx
page_id: fiscal-eventos
url: /fiscal/eventos
module: Fiscal
status: draft
created: 2026-05-20
owner: wagner
related_us: [US-FISCAL-007]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0358-doutrina-de-teste-tenant-98-supersede-0101, 0104-processo-mwart-canonico-unico-caminho]
prototypes: [prototipo-ui/cowork/fiscal-subpages.jsx]
---

# Charter — `Fiscal/Eventos`

## Mission

**Timeline append-only** de eventos SEFAZ aplicados a notas — CC-e + Cancelamento + EPEC + Manifestação destinatário. Acesso rápido pra auditoria LGPD Art. 37 + revisão fiscal.

## Goals (DoD PR #2)

1. **Lista timeline** NfeEvento via HasBusinessScope (ADR 0093)
2. **Filtros por tipo**: Todos, CC-e (110110), Cancelamento (110111), EPEC (110140), Manifesto (210200/210/220/240)
3. **Seletor período**: 7d / 30d / 90d (default 30d)
4. **Eager join** com NfeEmissao (numero, modelo, chave) — link clickável pra Fiscal/Nfe?focus=N
5. **Inertia::defer** em rows
6. **Permissão** `fiscal.access`
7. **Pest biz=1**: isolation + filtro por kind

## Non-Goals (PR #2)

- ❌ Drill-down drawer pro evento (apenas timeline horizontal — payload_json fica em audit log)
- ❌ Emitir novo evento (CC-e/Cancelar) — flow via /fiscal/nfe drawer (PR #4 mutations)
- ❌ Inutilização (vive em NfeInutilizacao — Model separado, sub-página futura)
- ~~❌ Export CSV (backlog)~~ → **entregue na Onda 7 (2026-09-03)**. Era adiamento declarado (`(backlog)`), não anti-hook; o contrato do que foi entregue está em §Contrato do export CSV abaixo.

## Contrato do export CSV (Onda 7 · 2026-09-03)

`GET /fiscal/eventos/export` — mesmo gate (`fiscal.access`), `throttle:6,1`.

| Regra | Valor | Onde é defendida |
|---|---|---|
| Escopo do arquivo | o **conjunto filtrado** (tipo + período) — não a página de 50, não a tabela inteira | `UC-FEVT-06` |
| Isolamento | global scope `HasBusinessScope`, nunca `where` manual | `UC-FEVT-05` `[T0]` |
| Janela | clampada em `{7, 30, 90}`; fora disso → 30 | `UC-FEVT-07` |
| Teto de volume | `EXPORT_MAX_LINHAS = 10000`, streaming em lotes de 500; ao estourar, última linha avisa | — |
| Formato | BOM UTF-8 + separador `;` (Excel pt-BR abre sem wizard) | `UC-FEVT-05` |
| Colunas | `Quando · Tipo · Sequência · Documento · Justificativa · Autor · cstat` (as 7 do protótipo) | — |
| PII | justificativa truncada em 200 chars, igual à tela — o anti-hook abaixo vale para o CSV | — |

**Duas colunas saem `—` por ausência de fonte, não por esquecimento:**
- **Sequência** — `nfe_eventos` não tem coluna; `n_seq_evento` só existe dentro de `payload_json` e só a CC-e o grava. Lemos **essa chave escalar** (não-PII), o que não fere o anti-hook do `payload_json` completo. Nos demais tipos, `—`.
- **Autor** — não existe `user_id` em `nfe_eventos`, e nenhum dos dois produtores grava causer. Preencher via `activity_log` mentiria a autoria: evento vindo de job/webhook SEFAZ não tem usuário.

**Fronteira mantida:** o CSV **lê** a timeline; não a altera. O append-only, a ordem cronológica e os 5 chips seguem intactos.

## Anti-hooks

- 🚫 Eventos são **append-only** (NfeEvento::UPDATED_AT = null) — UI não permite edit
- 🚫 Não mostrar `payload_json` completo na timeline (pode ter PII em xMotivo do XML)
- 🚫 Justificativa truncada em 200 chars no Controller (já feito) — frontend não re-expande
- 🚫 Eager `with('emissao')` é OK (1 join) — não fazer 4-5 joins extras
