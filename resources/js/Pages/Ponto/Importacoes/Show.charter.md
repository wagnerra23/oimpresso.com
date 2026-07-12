---
page: /ponto/importacoes/{id}
component: resources/js/Pages/Ponto/Importacoes/Show.tsx
related_prototype: n/a (tela de detalhe bespoke — status de processamento de um arquivo AFD; não segue um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_us: [US-PONT-011]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/importacoes/{id} (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/ImportacaoController@show` (rota `ponto.importacoes.show`, middleware `ponto.access`). Detalhe + acompanhamento do processamento de uma importação AFD/AFDT.

---

## Mission
Detalhe de uma importação AFD/AFDT: mostra os metadados do arquivo (nome, tipo, tamanho, hash SHA-256, quem enviou) e o estado do processamento (pendente/processando/concluído/falhou) com contadores de linhas processadas, marcações criadas e ignoradas. Enquanto o job roda, a tela faz auto-refresh pra acompanhar o progresso em tempo quase real.

---

## Goals — Features (faz)
- Dois cards: "Arquivo" (nome, tipo, tamanho, hash, enviado por, criado em) e "Processamento" (estado, contadores, última atualização).
- Badge de estado + rótulo "auto-refresh 3s…" enquanto pendente/processando.
- Alerta de erro com `erro_mensagem` quando o processamento falha.
- Botão "Baixar original" → `/ponto/importacoes/{id}/original` (download do arquivo enviado).
- Voltar pra lista.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não reprocessa nem re-enfileira o job manualmente pela tela.
- ❌ Não edita as marcações criadas — são append-only (Portaria MTP 671/2021).
- ❌ Não deleta a importação nem o arquivo original.
- ❌ Não valida acesso cross-tenant explicitamente no `findOrFail` — depende do global scope do model. *(inferência/risco pendente de Wagner — confirmar scope `business_id`)*

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 + PageHeader canon (ADR 0182).

---

## Automation hooks (faz)
- Polling client-side: `router.reload({ only: ['importacao'] })` a cada 3s enquanto o estado é `ESTADO_PROCESSANDO` ou `ESTADO_PENDENTE`; para sozinho ao concluir/falhar.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ O polling NÃO continua indefinidamente — cessa quando o estado sai de pendente/processando.
- ❌ Nenhuma mutação em GET — a tela e o polling são read-only.
- ❌ Não notifica o usuário por e-mail/WhatsApp ao terminar o processamento.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Confirmar scope `business_id` no `show`/`baixarOriginal` (risco cross-tenant)
- [ ] Smoke visual 1280/1440 (screenshot) nos estados processando/concluído/erro
