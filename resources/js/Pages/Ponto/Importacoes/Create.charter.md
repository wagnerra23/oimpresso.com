---
page: /ponto/importacoes/novo
component: resources/js/Pages/Ponto/Importacoes/Create.tsx
related_prototype: n/a (herda PT-02 Formulário; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_us: [US-PONT-010]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/importacoes/novo (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/ImportacaoController@create` (form) + `@store` (rota `ponto.importacoes.store`, middleware `ponto.access`). Upload de arquivo AFD/AFDT do REP pra virar marcações.

---

## Mission
Formulário de envio de arquivo AFD (Arquivo Fonte de Dados) ou AFDT gerado pelo relógio de ponto (REP), conforme Portaria MTP 671/2021. O operador escolhe o tipo e sobe o `.txt`; o backend calcula SHA-256, bloqueia duplicados e enfileira o processamento assíncrono que cria as marcações com hash encadeado.

---

## Goals — Features (faz)
- Seleção de tipo (AFD / AFDT) e upload de arquivo `.txt` (`useForm` + `forceFormData`).
- Barra de progresso do upload (`form.progress.percentage`).
- Bloco explicativo do fluxo (SHA-256 → dedup → job assíncrono → acompanhamento).
- POST pra `ponto.importacoes.store`; em sucesso redireciona ao `Show` da importação criada.
- Toast de sucesso/erro; validação client-side mínima (arquivo obrigatório).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não processa o arquivo no request — o parsing roda em job (`ProcessarImportacaoAfdJob`), não síncrono.
- ❌ Não aceita re-envio de arquivo já importado — dedup por SHA-256 no `store` rejeita hash repetido.
- ❌ Não cria marcações diretamente pela tela — quem cria é o job (append-only, Portaria 671/2021).
- ❌ Não mistura tenants — o arquivo é armazenado e escopado por `business_id` da sessão.

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 + PageHeader canon (ADR 0182).

---

## Automation hooks (faz)
- `store` dispara `ProcessarImportacaoAfdJob::dispatch($businessId, $importacao->id)` — job recebe `$businessId` no constructor (Tier 0 ADR 0093, sem session no worker).
- Cálculo de SHA-256 no upload + dedup automático antes de persistir.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ O submit não processa/materializa marcações — só enfileira.
- ❌ Não sobrescreve importação existente com mesmo hash (falha com erro, não substitui).
- ❌ Não notifica ninguém ao enfileirar (o acompanhamento é manual, na tela do item).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) do upload + barra de progresso
- [ ] Confirmar limite de tamanho de arquivo aceito (`ImportacaoAfdRequest`)
