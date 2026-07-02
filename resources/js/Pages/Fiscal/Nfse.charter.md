---
page: /fiscal/nfse
component: resources/js/Pages/Fiscal/Nfse.tsx
page_id: fiscal-nfse
url: /fiscal/nfse
module: Fiscal
status: draft
created: 2026-05-20
owner: wagner
related_us: [US-FISCAL-005]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0101-tests-business-id-1-nunca-cliente, 0104-processo-mwart-canonico-unico-caminho]
prototypes:
  - "prototipo-ui/.../fiscal-page.jsx §10 FiscalNFSePage"
---

# Charter — `Fiscal/Nfse`

## Mission

Lista navegável de **NFS-e emitidas** (Sistema Nacional NT 2024-001 — substitui emissores municipais legacy) com filtros por status + competência + busca, agregada no cockpit Fiscal.

## Goals (DoD PR #2)

1. **Lista paginada** NfseEmissao via HasBusinessScope (ADR 0093) — modelo nacional 56
2. **Filtros chip-row**: Todas, Autorizadas, Rejeitadas, Processando (pending+sent), Canceladas
3. **Seletor competência** (month picker) — default mês corrente, drill-down past months
4. **Busca**: número NFS-e + código verificação + CPF/CNPJ tomador
5. **Inertia::defer** em rows (skill inertia-defer-default)
6. **Permissão** `fiscal.nfse.view`
7. **Pest biz=1**: isolation + permission gate

## Non-Goals (PR #2)

- ❌ Drawer detalhe NFS-e (drawer dedicado vem em PR futuro — por enquanto title hover mostra error_msg)
- ❌ Emissão nova (botão Emitir não existe nessa tela — flow via /sells)
- ❌ Cancelamento UI (varia por município — backlog)
- ❌ Download PDF NFS-e (rota em Modules/NfeBrasil)

## Anti-hooks

- 🚫 Não acessar NfseEmissao sem global scope
- 🚫 Não usar PHP `is_numeric()` na busca — `preg_replace('/\D/', '', $s)` pra CPF/CNPJ
- 🚫 Não JOIN com `transactions` ainda — dados já em NfseEmissao->cpf_cnpj_tomador
- 🚫 Não mostrar `error_msg` completo na tabela (só hover/title) — pode conter PII
