---
page: /fiscal/dfe
component: resources/js/Pages/Fiscal/Dfe.tsx
page_id: fiscal-dfe
url: /fiscal/dfe
module: Fiscal
status: draft
created: 2026-05-20
owner: wagner
related_adrs: [0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0101-tests-business-id-1-nunca-cliente, 0104-processo-mwart-canonico-unico-caminho, 0116-pivot-gold-manifestacao-destinatario-emenda-0115]
prototypes:
  - "prototipo-ui/.../fiscal-data.jsx DFE_PENDENTE/DFE_HISTORICO"
---

# Charter — `Fiscal/Dfe`

## Mission

Lista de NF-e emitidas **CONTRA o CNPJ Oimpresso** (manifesto destinatário SEFAZ), com filtros por status + pílula temporal do prazo legal 90d. Prepara terreno pra UI de ação manifestar em PR futuro.

## Goals (DoD PR #3)

1. Lista paginada `NfeDfeRecebido` via HasBusinessScope (ADR 0093)
2. 5 chips status (pendentes/confirmadas/desconhecidas/não-realizadas/todas)
3. Busca por chave 44d + CNPJ + nome emitente
4. Pílula temporal de prazo (`prazo_confirmacao_em - now`) com 3 níveis urgência (crit <7d, warn <30d, ok)
5. Permissão `fiscal.dfe.manage`
6. Inertia::defer em rows

## Non-Goals (PR #3)

- ❌ Ações manifestar (Confirmar/Desconhecer/Não-realizada/Ciência) — UI prepara, mas dispatch real em PR #4 mutações
- ❌ Drawer detalhe com itens da NF-e (NfeDfeItem) — só tabela flat
- ❌ XML viewer da NF-e recebida
- ❌ Bulk manifestar — backlog

## Anti-hooks

- 🚫 NfeDfeRecebido tem `HasBusinessScope` — não usar `withoutGlobalScopes`
- 🚫 `nome_emitente` e `cnpj_emitente` SÃO PII de terceiros — exibidos OK (legalmente devemos saber quem emitiu contra nós), mas sem agregação em log
- 🚫 Prazo 90d hard-coded — fonte de verdade é `prazo_confirmacao_em` no Model (calculado por SEFAZ)
