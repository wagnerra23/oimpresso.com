---
id: resources-js-pages-nfse-show-charter
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
> **Errata 2026-11-16 (lote 3 da auditoria NFSe, PRs #11041 · #11044 · este):** os itens marcados *(errata 2026-11-16)* foram medidos contra o código e corrigidos. O texto anterior de cada um fica citado no próprio item.
>
> Backend: `Modules/NFSe/Http/Controllers/NfseController@show` + `@cancelar` (POST, motivo) + `@pdf`. Gates `nfse.view` (ver) e `nfse.cancel` (cancelar). US-NFSE-006.
>
> **Nota de Padrão de Tela:** é uma tela de detalhe, mas o layout usa `InfoRow`/`Card` bespoke (não `<dl>`/StatCard/FsmActionPanel), então NÃO declara PT-03 — declaração seria count-pump reprovado por `pt:conformance:check`. Se um dia migrar pro padrão de Detalhe canônico, atualizar `related_prototype` pra PT-03.

---

## Mission

Detalhe de uma NFS-e emitida: mostra número, status, tomador, valores (serviços + ISS), competência, código LC 116 e descrição, com link pra venda de origem quando houver. Concentra as ações de pós-emissão — baixar PDF, cancelar (com motivo) e reemitir quando a nota falhou. É onde o operador resolve o destino de uma nota específica.

---

## Goals — Features (faz)

- Exibe os dados da nota (`InfoRow`/`Card`): número, `StatusBadge`, tomador (nome/CNPJ/CPF/e-mail), valor dos serviços, valor do ISS, competência, LC 116, descrição, data de criação; "Cancelada em" na nota cancelada; ambiente do provedor da empresa, rotulado "configurado hoje" (a nota não grava o ambiente em que saiu)
- Link pra venda de origem (`venda`) quando a nota veio de uma `Transaction` **da mesma empresa da nota** (lote 0, #10972)
- Baixar PDF (`pdf_url`) quando disponível
- Cancelar a nota via `AlertDialog` com campo de motivo obrigatório (`POST /nfse/{id}/cancelar`, `useForm`): "Cancelando…" durante o envio, erro do motivo dentro do diálogo. O motivo **não é gravado** em coluna nenhuma; mostrá-lo depende do evento de cancelamento (lote 2, PR 2.4)
- "Nova emissão" quando `status === 'erro'` (`podeReemitir`): abre o formulário `/nfse/emitir`, com a venda (`?transaction_id=`) quando a nota tem venda. Não reenvia a nota — é uma nota nova *(errata 2026-11-16: dizia "Reemitir")*
- AppShellV2 + PageHeader shared; aviso de sucesso/erro pelo toast global do `app.tsx` (flash compartilhado) *(errata 2026-11-16: a tela tinha banner próprio)*

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

- `cancelar` pede o cancelamento ao provedor com o certificado e o ambiente da empresa da nota, e só grava `cancelada` com a confirmação dele; sem confirmação a nota continua emitida (lote 0, #10987). O registro permanece — número segue usado oficialmente, CONFAZ SINIEF 07/2005
- *(errata 2026-11-16: removido "Reemissão reabre a emissão assíncrona quando a nota falhou" — nada reabre a emissão automaticamente; "Nova emissão" é ação do operador, ver Goals)*

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO cancela automaticamente por status/tempo — sempre ação humana com motivo
- ❌ NÃO faz `forceDelete` da nota (append-only fiscal — cancelada permanece no banco). Escopo do anti-hook: esta tela não tem ação que apague. O model (`SoftDeletes`) não recusa `delete()`/`forceDelete()` por conta própria — ver lote 1, #11031 *(errata 2026-11-16)*
- ❌ NÃO notifica o tomador automaticamente sem checar opt-in (LGPD) — fora do escopo desta tela
- ❌ NÃO reemite em loop automático em caso de erro recorrente

---

## Pendências antes de `status: live`

- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot, não tabela) — estados autorizada / erro / cancelada
- [ ] Confirmar se a tela deve migrar pro Padrão de Detalhe canônico (PT-03) ou seguir bespoke
