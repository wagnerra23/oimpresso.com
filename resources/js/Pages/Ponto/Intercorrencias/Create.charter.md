---
page: /ponto/intercorrencias/create
component: resources/js/Pages/Ponto/Intercorrencias/Create.tsx
related_prototype: n/a (herda PT-02 Formulário; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_us: [US-PONT-001]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/intercorrencias/create (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/IntercorrenciaController@create` (form) + `@store` (rota `ponto.intercorrencias.store`) + endpoint IA `@aiClassify` (rota `ponto.intercorrencias.ai-classify`, throttle 10/min). Middleware `ponto.access`.

---

## Mission
Formulário de registro de intercorrência (ausência, consulta médica, esquecimento de marcação, hora extra autorizada, etc.) que afeta a apuração. Além do form estruturado, oferece um campo de texto livre que a IA (opcional) classifica em tipo/prioridade e reescreve a justificativa formalmente, pré-preenchendo o form. A intercorrência nasce como rascunho e depois é submetida ao RH.

---

## Goals — Features (faz)
- Form estruturado: colaborador, tipo, data, prioridade, dia-todo ou intervalo, justificativa, flags "impacta apuração" e "descontar banco de horas".
- Campo IA em texto livre → `POST /ponto/intercorrencias-ai/classify` (fetch com CSRF) → preenche o form + toast com % de confiança e origem cache.
- Estado da IA visível: badge "IA desligada no servidor" quando `ai_enabled=false`, alerts de sucesso/erro.
- Submit via `useForm` → `store`; em sucesso redireciona ao `Show` como rascunho.
- Lista de colaboradores ativos (com `controla_ponto`) vinda do controller.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não aprova a intercorrência — só cria rascunho; aprovação é do RH em Aprovações.
- ❌ Não aplica efeito na apuração/banco de horas no momento da criação (as flags são intenção, aplicadas depois do fluxo de aprovação).
- ❌ Não exige IA — o form funciona sem `ai_enabled`; a IA é assistiva.
- ❌ Não cria intercorrência pra colaborador de outro tenant — lista é scopada por `business_id`.

---

## UX targets
- p95 < 1500ms (admin) ; classificação IA é assíncrona com estado de loading ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 + PageHeader canon (ADR 0182).

---

## Automation hooks (faz)
- Endpoint IA dedicado (`aiClassify`) chama `IntercorrenciaAIClassifier` (OpenAI) com throttle 10/min e cache — retorna JSON que popula o form.
- `store` delega a `IntercorrenciaService::criar` (gera código, estado inicial rascunho).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ A IA não submete nem persiste nada sozinha — só sugere; o usuário confirma/ajusta antes de salvar.
- ❌ Salvar não dispara aprovação nem notifica o RH (submeter é ação separada no `Show`).
- ❌ Não impacta apuração/banco de horas automaticamente ao criar.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) com IA ligada e desligada
- [ ] Confirmar tratamento de PII na descrição livre enviada à IA (LGPD)
