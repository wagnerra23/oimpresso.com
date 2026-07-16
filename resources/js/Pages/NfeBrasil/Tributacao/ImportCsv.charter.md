---
page: /nfe-brasil/tributacao/import
component: resources/js/Pages/NfeBrasil/Tributacao/ImportCsv.tsx
related_prototype: n/a (herda PT-02 Formulário; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: NfeBrasil
related_us: [US-NFE-010]
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /nfe-brasil/tributacao/import (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/NfeBrasil/Http/Controllers/ImportRegrasController@show|preview|aplicar` (rotas `nfe-brasil.tributacao.import.*`, permissão `nfe.tributacao.manage`). Form de upload em 2 passos (preview → aplicar) para importar regras tributárias NCM em massa. US-NFE-010 fase 3.

---

## Mission
Deixar o responsável fiscal cadastrar/atualizar muitas regras tributárias de uma vez via CSV, com um passo de conferência antes de gravar: faz upload, vê o preview (válidas + erros linha a linha) e só então aplica. O import é idempotente pela chave (NCM + UF origem + UF destino), evitando duplicidade.

---

## Goals — Features (faz)
- Passo 1: form de upload de CSV (máx 5 MB, `.csv`/`.txt`) com `useForm` + `forceFormData`, mostrando o cabeçalho esperado (`colunas_obrigatorias`) e exemplo.
- Passo 2: preview vindo do flash (`preview`): contagem de válidas/erros, tabela de erros (linha + motivo) e amostra das primeiras 10 linhas válidas com alíquotas formatadas em %.
- Aplicação idempotente sob `confirm()`, que persiste as linhas válidas guardadas em sessão (sem re-upload) e redireciona pro índice com resumo (criadas/atualizadas/falhas).
- Toasts de sucesso/erro em preview e aplicar; link de voltar pra `/nfe-brasil/tributacao`.
- Explica as regras do formato: `uf_destino` vazio = "todas" (Nível 3), CSOSN ou CST mutuamente exclusivos, alíquotas em decimal.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita regra individualmente (isso é o `RegraForm`).
- ❌ Não persiste nada no passo de preview — só parseia e mostra; a gravação é exclusiva do `aplicar`.
- ❌ Não valida regra de negócio fiscal profunda (correção de alíquota vs NCM real) além do parse/validação do service.
- ❌ Não importa para outro tenant — `aplicar` escopa por `business.id` da sessão (Tier 0, ADR 0093); as regras entram só no negócio corrente.

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 ; container `max-w-5xl`.

---

## Automation hooks (faz)
- `aplicar` grava `activity('nfe.tributacao')` (log de auditoria com business_id + resumo) via Spatie activitylog.
- Linhas válidas do preview ficam em sessão (`nfe_import_csv_linhas`) e são consumidas + limpas no aplicar.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não aplica o import sem `confirm()` explícito do usuário.
- ❌ Preview nunca grava — upload/preview é read-only sobre o arquivo.
- ❌ Não reprocessa nem reenvia sozinho em caso de falha (erro retorna sem mudanças).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) — validar preview com erros e com >10 linhas
- [ ] Confirmar limite de 5 MB e comportamento com CSV malformado (encoding/separador)
