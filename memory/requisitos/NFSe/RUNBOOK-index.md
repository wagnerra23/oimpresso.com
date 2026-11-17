---
title: "Runbook · NFSe/Index — lista de NFS-e"
module: NFSe
tela: Nfse/Index
owner: W
status: ativo
last_validated: "2026-11-16"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
---

# Runbook · NFSe/Index

> F1 PLAN da tela `resources/js/Pages/Nfse/Index.tsx` (ADR 0104). Escrito em 2026-11-16, no lote 3
> da auditoria do NFSe (PR 3.1). O `RUNBOOK.md` do módulo é histórico (lápide 2026-08-16) e não
> descreve esta tela.

## 1. O que a tela faz

Lista as NFS-e da empresa com filtro por status, competência (mês) e busca por tomador ou número.
Contrato: [`Index.charter.md`](../../../resources/js/Pages/Nfse/Index.charter.md) e
[`Index.casos.md`](../../../resources/js/Pages/Nfse/Index.casos.md).

## 2. Caminho do dado

| passo | onde |
|---|---|
| GET da lista | `NfseController@index` → `Inertia::render('Nfse/Index')` (gate `nfse.view`) |
| validação dos filtros | `Modules/NFSe/Http/Requests/IndexNfseRequest.php` — status ∈ `IndexNfseRequest::STATUS`, competência `AAAA-MM`, busca até 120 caracteres |
| consulta | `NfseController::buildNotasPayload` — `NfseEmissao` (escopo de empresa pelo `NfseBusinessScope`), só as colunas da tabela, 25 por página |
| entrega | `notas` vai **deferida**: a tela abre com o esqueleto e a lista chega no 2º pedido (`<Deferred data="notas">`) |

## 3. Regras que a tela não pode perder

- **Competência é mês.** O `<input type="month">` manda `AAAA-MM`; a regra aceita só isso. Até
  2026-11-16 a regra pedia `Y-m-d` e todo filtro de competência voltava com erro.
- **Um vocabulário de status.** `IndexNfseRequest::STATUS`, o `StatusBadge kind="nfse"` e o filtro
  da tela usam os 5 valores que a coluna aceita: rascunho, processando, emitida, cancelada, erro.
- **Sem XML na lista.** `xml_envio` e `xml_retorno` ficam fora da prop (charter Non-Goal).
- **Aviso de sucesso/erro** sai pelo toast global do `app.tsx` (flash compartilhado). A tela não
  tem banner próprio.

## 4. Como conferir uma mudança

1. Pest na lane `nfse-pest` (MySQL): `tests/Feature/Modules/NFSe/NfseIndexTelaTest.php`
   (UC-NFSL-04/05) e `tests/Feature/Modules/NFSe/NfseTelasContratoTest.php` (UC-NFSL-01..03,
   lane sqlite do `ci.yml`).
2. Tela: abrir `/nfse` em 1280px, filtrar uma competência e conferir que a lista volta filtrada.

## 5. Fora desta tela

Emitir (`/nfse/emitir`), detalhe e cancelamento (`/nfse/{id}`), configuração do provedor.
A lista do Fiscal (`/fiscal/nfse`) é outra tela, de outro módulo.
