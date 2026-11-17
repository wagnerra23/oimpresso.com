---
title: "Runbook · NFSe/Show — detalhe e cancelamento da NFS-e"
module: NFSe
tela: Nfse/Show
owner: W
status: ativo
last_validated: "2026-11-16"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
---

# Runbook · NFSe/Show

> F1 PLAN da tela `resources/js/Pages/Nfse/Show.tsx` (ADR 0104). Escrito em 2026-11-16, no lote 3
> da auditoria do NFSe (PR 3.2). O `RUNBOOK.md` do módulo é histórico (lápide 2026-08-16) e não
> descreve esta tela.

## 1. O que a tela faz

Mostra uma NFS-e (status, tomador, valores, descrição, venda vinculada) e concentra as ações de
pós-emissão: baixar o DANFSe, cancelar com motivo e abrir uma nova emissão quando a nota falhou.
Contrato: [`Show.charter.md`](../../../resources/js/Pages/Nfse/Show.charter.md) e
[`Show.casos.md`](../../../resources/js/Pages/Nfse/Show.casos.md).

## 2. Caminho do dado

| passo | onde |
|---|---|
| GET do detalhe | `NfseController@show` → `Inertia::render('Nfse/Show')` (gate `nfse.view`; a nota resolve pelo escopo de empresa) |
| venda vinculada | só se for da mesma empresa da nota (lote 0, #10972) |
| ambiente | `nfse_provider_configs.ambiente` da empresa, **o configurado hoje** — a nota não grava em que ambiente saiu |
| cancelar | `POST /nfse/{id}/cancelar` → `CancelarNfseRequest` (gate `nfse.cancel`, motivo 15..255) → `NfseEmissaoService::cancelar` (só grava `cancelada` com confirmação do provedor, lote 0 #10987) |
| aviso | sucesso e erro do cancelamento voltam no flash compartilhado e saem como toast (`app.tsx`) |

## 3. Regras que a tela não pode perder

- O formulário de cancelamento usa `useForm`: o botão mostra "Cancelando…" durante o envio, e o
  erro de validação do motivo aparece dentro do diálogo, que não fecha sozinho.
- `?cancelar=1` na URL (o ícone "Cancelar" da lista) abre o diálogo de cancelamento se a nota
  estiver emitida.
- "Nova emissão" de uma nota com erro leva `transaction_id` quando a nota tem venda, para o
  formulário vir preenchido com a venda.
- "Cancelada em" vem do `updated_at` da nota cancelada (estado terminal). O **motivo** do
  cancelamento não é gravado em coluna nenhuma; mostrá-lo depende do evento de cancelamento
  (lote 2, PR 2.4).
- Cor do status: `StatusBadge kind="nfse"`; cancelada tem tom próprio (UC-NFSD-04).

## 4. Como conferir uma mudança

1. Pest na lane `nfse-pest` (MySQL): `tests/Feature/Modules/NFSe/NfseShowTelaTest.php` e
   `tests/Feature/Modules/NFSe/NfseVendaDaPropriaEmpresaTest.php`; contrato DB-less em
   `NfseTelasContratoTest` (lane sqlite do `ci.yml`).
2. Tela: abrir `/nfse/{id}` de uma nota emitida, tentar cancelar com motivo curto (erro no
   diálogo) e conferir os estados emitida / erro / cancelada em 1280px.

## 5. Fora desta tela

Lista (`/nfse`), emissão (`/nfse/emitir`), geração do DANFSe (lote 2, PR 2.1).
