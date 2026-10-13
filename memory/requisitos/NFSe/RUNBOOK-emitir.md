---
title: "Runbook · NFSe/Emitir — formulário de emissão de NFS-e"
module: NFSe
tela: Nfse/Emitir
owner: W
status: ativo
last_validated: "2026-10-13"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
---

# Runbook · NFSe/Emitir

> F1 PLAN da tela `resources/js/Pages/Nfse/Emitir.tsx` (ADR 0104). Escrito em 2026-10-13, quando a
> tela ganhou a marcação de retenção federal (R-NFSE-003). O `RUNBOOK.md` do módulo é histórico
> (lápide 2026-08-16) e não descreve esta tela.

## 1. O que a tela faz

Formulário que monta uma NFS-e e a despacha para emissão assíncrona. Contrato da tela:
[`Emitir.charter.md`](../../../resources/js/Pages/Nfse/Emitir.charter.md) e
[`Emitir.casos.md`](../../../resources/js/Pages/Nfse/Emitir.casos.md).

## 2. Caminho do dado

| passo | onde |
|---|---|
| GET do formulário | `NfseController@create` → `Inertia::render('Nfse/Emitir')` |
| validação | `Modules/NFSe/Http/Requests/StoreNfseRequest.php` (gate `nfse.emit`) |
| montagem do payload | `NfseEmissaoService::montarPayload` — cert, ambiente e IBGE do tenant; retenção federal pelo `Modules/NfeBrasil/Services/RetencoesNfseService.php` quando `retencao_federal_sujeito` vem marcado |
| despacho | `EmitirNfseJob` (fila `nfse`, `business_id` no payload) |
| gravação + DPS | `NfseEmissaoService::emitir` grava `nfse_emissoes`; `SnNfseAdapter::buildDps` monta a DPS |

## 3. Campos do formulário

Competência, tomador (nome, CNPJ, CPF, e-mail), descrição, código LC 116, valor dos serviços,
alíquota ISS, "ISS retido na fonte" e, desde 2026-10-13, "Serviço sujeito à retenção de
PIS/COFINS/CSLL (Lei 10.833/2003, art. 30)". A marcação vem desligada; quem emite decide (decisão
[W] 2026-10-13). O valor retido não é calculado na tela: depende do regime da empresa, que só o
backend lê.

## 4. Como conferir uma mudança

1. Pest: `Modules/NfeBrasil/Tests/Feature/NfseRetencoesDpsTest.php` (lane `nfebrasil-pest`) e
   `tests/Feature/Modules/NFSe/NfseTelasContratoTest.php`.
2. Tela: abrir `/nfse/emitir` em 1280px, conferir as duas marcações e o resumo financeiro.

## 5. Fora desta tela

Certificado, provider e município (setup do módulo); edição de nota emitida; IRRF e INSS.
