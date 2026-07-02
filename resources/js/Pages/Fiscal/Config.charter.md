---
page: /fiscal/config
component: resources/js/Pages/Fiscal/Config.tsx
page_id: fiscal-config
url: /fiscal/config
module: Fiscal
status: draft
created: 2026-05-20
owner: wagner
related_us: [US-FISCAL-009, US-NFE-041]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0101-tests-business-id-1-nunca-cliente, 0104-processo-mwart-canonico-unico-caminho]
prototypes:
  - "prototipo-ui/.../fiscal-data.jsx CONFIG"
---

# Charter — `Fiscal/Config`

## Mission

Visão consolidada do estado **read-only** de cert A1 + regime tributário + tributação default. Edição completa via `Modules/NfeBrasil/.../Configuracao/Certificado.tsx` existente (link no header).

## Goals (DoD PR #3)

1. Status cert A1 (`NfeCertificado::ativos()`) — valido_ate + dias restantes + cnpj titular
2. Regime tributário (`NfeBusinessConfig.regime`)
3. Tributação default cascata (JSON resumido)
4. Pílula temporal de vencimento (crit ≤7d, warn ≤60d)
5. Link "Editar" → `/nfe-brasil/configuracao/certificado` (módulo emissor canon)
6. Permissão `fiscal.config.edit`

## Non-Goals (PR #3)

- ❌ Edição inline (upload novo cert, mudar regime, editar tributação) — vive em NfeBrasil canon
- ❌ Renovação automática de cert (backlog ADR futuro)
- ❌ Histórico de certs (apenas atual ativo)

## Anti-hooks

- 🚫 `encrypted_password` está em `$hidden` no Model — NUNCA expor no payload Inertia
- 🚫 Não criar UPDATE Controller — esta tela é read-only por design
- 🚫 `cnpj_titular` exibido OK (admin do business já tem acesso a esse dado)
