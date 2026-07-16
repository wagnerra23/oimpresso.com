---
page: /nfse/{id}
component: resources/js/Pages/Nfse/Show.tsx
related_prototype: n/a (tela de Detalhe bespoke — layout InfoRow/Card; não segue um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: NFSe
related_us: [US-NFSE-006]
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /nfse/{id} (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters (telas sem contrato). Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/NFSe/Http/Controllers/NfseController@show` + `@cancelar` (POST, motivo) + `@pdf`. Gates `nfse.view` (ver) e `nfse.cancel` (cancelar). US-NFSE-006.
>
> **Nota de Padrão de Tela:** é uma tela de detalhe, mas o layout usa `InfoRow`/`Card` bespoke (não `<dl>`/StatCard/FsmActionPanel), então NÃO declara PT-03 — declaração seria count-pump reprovado por `pt:conformance:check`. Se um dia migrar pro padrão de Detalhe canônico, atualizar `related_prototype` pra PT-03.

---

## Mission

Detalhe de uma NFS-e emitida: mostra número, status, tomador, valores (serviços + ISS), competência, código LC 116 e descrição, com link pra venda de origem quando houver. Concentra as ações de pós-emissão — baixar PDF, cancelar (com motivo) e reemitir quando a nota falhou. É onde o operador resolve o destino de uma nota específica.

---

## Goals — Features (faz)

- Exibe os dados da nota (`InfoRow`/`Card`): número, `StatusBadge`, tomador (nome/CNPJ/CPF/e-mail), valor dos serviços, valor do ISS, competência, LC 116, descrição, data de criação
- Link pra venda de origem (`venda`) quando a nota veio de uma `Transaction`
- Baixar PDF (`pdf_url`) quando disponível
- Cancelar a nota via `AlertDialog` com campo de motivo obrigatório (`POST /nfse/{id}/cancelar`)
- Reemitir quando `status === 'erro'` (`podeReemitir`)
- AppShellV2 + PageHeader shared, flash de sucesso/erro

---

## Non-Goals — Features (NÃO faz)

- ❌ NÃO edita os dados da nota (NFS-e autorizada é imutável; correção = cancela + reemite)
- ❌ NÃO cancela sem motivo — o `AlertDialog` exige o texto antes de confirmar
- ❌ NÃO reemite nota que não está em `erro` (só o caminho de falha reabre emissão)
- ❌ NÃO mostra XML/RPS bruto por padrão (foco nos dados legíveis + PDF)
- ❌ NÃO acessa nota de outro tenant — `NfseEmissao` scopeada por `business_id` (Tier 0)
- ❌ NÃO gera o PDF sob demanda aqui (só serve o `pdf_url` já produzido pela emissão)

---

## UX targets

- p95 < 1500ms no GET do detalhe
- Cabe em 1280px (ROTA LIVRE)
- Cancelamento com confirmação explícita (`AlertDialog`) — ação destrutiva não acontece por clique único
- Estados de erro/sucesso via flash legível (tokens DS)

---

## Automation hooks (faz)

- `cancelar` dispara o fluxo de cancelamento no backend (registro permanece — número segue usado oficialmente, CONFAZ SINIEF 07/2005)
- Reemissão reabre a emissão assíncrona quando a nota falhou

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO cancela automaticamente por status/tempo — sempre ação humana com motivo
- ❌ NÃO faz `forceDelete` da nota (append-only fiscal — cancelada permanece no banco)
- ❌ NÃO notifica o tomador automaticamente sem checar opt-in (LGPD) — fora do escopo desta tela
- ❌ NÃO reemite em loop automático em caso de erro recorrente

---

## Pendências antes de `status: live`

- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot, não tabela) — estados autorizada / erro / cancelada
- [ ] Confirmar se a tela deve migrar pro Padrão de Detalhe canônico (PT-03) ou seguir bespoke
