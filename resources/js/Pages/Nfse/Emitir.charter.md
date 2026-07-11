---
page: /nfse/emitir
component: resources/js/Pages/Nfse/Emitir.tsx
related_prototype: n/a (herda PT-02 Formulário; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: NFSe
related_us: [US-NFSE-009]
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /nfse/emitir (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters (telas sem contrato). Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/NFSe/Http/Controllers/NfseController@create` (GET, form) + `@store` (POST → `NfseEmissaoService::montarPayload` + `despacharEmissaoAsync`). Gate `nfse.emit`. Pode pré-preencher a partir de uma venda (`?transaction_id=`). US-NFSE-009.

---

## Mission

Formulário de emissão de uma NFS-e: o operador informa competência, tomador (nome + CNPJ/CPF + e-mail), descrição do serviço, código LC 116, valor dos serviços, alíquota de ISS e retenção — a tela calcula ISS e valor líquido em tempo real e envia pra processamento assíncrono. Quando aberta a partir de uma venda, herda os dados do contato e do total. É onde a NFS-e nasce; o acompanhamento vive na listagem `/nfse`.

---

## Goals — Features (faz)

- Formulário `useForm` (Inertia) com campos: competência, tomador (nome/CNPJ/CPF/e-mail), descrição, código LC 116, valor dos serviços, alíquota ISS, `iss_retido`
- Cálculo local em tempo real de ISS (`valor × alíquota`) e valor líquido (líquido = valor − ISS quando retido)
- Pré-preenchimento a partir de uma venda (`venda` prop, via `?transaction_id=`) — contato, total e descrição default
- Defaults do provider (`config`): código LC 116, alíquota, ambiente (produção/homologação)
- Alerta quando o módulo não está configurado (`semConfig`) ou o certificado está inválido/expirado (`certAlerta`)
- Submit `POST /nfse/emitir` → redireciona pra listagem com flash de "enviada para processamento"
- Erros de validação server-side por campo (`errors`), AppShellV2 + PageHeader shared

---

## Non-Goals — Features (NÃO faz)

- ❌ NÃO emite de forma síncrona — dispara job assíncrono (`despacharEmissaoAsync`); status vem depois na listagem
- ❌ NÃO configura certificado / provider / município (isso é setup do módulo, não desta tela)
- ❌ NÃO edita nota já emitida (NFS-e autorizada é imutável)
- ❌ NÃO calcula tributos além de ISS (sem IR/PIS/COFINS retidos nesta tela)
- ❌ NÃO permite emitir sem certificado válido / módulo configurado (bloqueia com alerta)
- ❌ NÃO cria/edita o cadastro do tomador (usa o que veio da venda ou digitado; não persiste contato)

---

## UX targets

- p95 < 1500ms no GET do formulário
- Cabe em 1280px (ROTA LIVRE)
- Cálculo de ISS/líquido instantâneo (client-side), sem round-trip
- Alerta de certificado/config antes do operador perder tempo preenchendo

---

## Automation hooks (faz)

- `store` monta DTO no `NfseEmissaoService` e despacha job (Controller thin: HTTP/auth/validate)
- Job assíncrono SEMPRE recebe `business_id` no payload (fila não tem `session()` — Tier 0)
- Pré-preenchimento automático dos campos quando `transaction_id` resolve uma `Transaction` do tenant

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO reenvia/retenta emissão automaticamente em erro (reemissão é ação explícita no detalhe)
- ❌ NÃO salva rascunho automático do formulário
- ❌ NÃO dispara emissão sem submit explícito do operador

---

## Pendências antes de `status: live`

- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot, não tabela) — caminho vazio e caminho vindo-de-venda
- [ ] Confirmar cópia do cálculo ISS × parser pt-BR (regra-mestre valor — dupla conferência antes de qualquer mudança de cálculo)
