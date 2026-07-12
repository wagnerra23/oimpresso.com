---
page: /memcofre/ingest
component: resources/js/Pages/MemCofre/Ingest.tsx
related_prototype: n/a (herda PT-02 Formulário; segue o Padrão de Tela de formulário)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: MemCofre
related_us: [US-DOCVAULT-002]
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /memcofre/ingest (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/SRS/Http/Controllers/IngestController@show` (GET, rota `memcofre.ingest`) + `@store` (POST, rota `memcofre.ingest.store`), prefixo `/memcofre`, stack admin UltimatePOS + `throttle:60,1`. Módulo `Modules/SRS` ("Cofre de Memórias") — ferramenta interna Wagner de uso raro, em deprecação segundo o BRIEFING. Formulário implementado de verdade (grava `DocSource`/`DocEvidence`).

---

## Mission
Registrar material bruto (print, chat, log de erro, arquivo, texto, URL) como fonte da verdade funcional. O usuário escolhe o tipo — que decide dinamicamente os campos exigidos (upload / URL / texto) — dá um título e contexto, e opcionalmente já anota uma evidência inicial que cai no Inbox pra triagem.

---

## Goals — Features (faz)
- Formulário `useForm` com tipo de fonte dinâmico: `screenshot`/`file` → upload; `url` → campo URL; `chat`/`text`/`error` → textarea.
- Campos: tipo, módulo-alvo (opcional), título (obrigatório), descrição/contexto, corpo/arquivo/URL conforme o tipo.
- Upload multipart (`forceFormData`) com barra de progresso; MIME/whitelist validados no `StoreIngestRequest`.
- Opt-in "Criar evidência inicial" (Switch): tipo + conteúdo da evidência, gravados como `DocEvidence` status `pending`.
- No submit grava `DocSource` (+ evidência opcional) escopado por `business_id` e redireciona pro Dashboard com flash de sucesso.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não classifica nem aplica a evidência aqui — só registra; a triagem é no Inbox. _Inferência pendente de Wagner._
- ❌ Não extrai evidências por IA no momento do upload — a evidência inicial é digitada à mão pelo usuário (opt-in). _Inferência pendente de Wagner._
- ❌ Não edita fontes já existentes (tela é só de criação).
- ❌ Não grava em business alheio (escopo `business_id` no `store`).

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 (breadcrumb "Cofre › Nova evidência").

---

## Automation hooks (faz)
- Persistência de arquivo no disco configurado (`memcofre.upload.disk`) com caminho datado.
- Criação condicional de `DocEvidence` inicial quando o opt-in está ligado e há conteúdo.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não cria evidência sem o opt-in explícito marcado.
- ❌ Não chama LLM/IA automaticamente no ingest (custo $ — a IA fica no Chat, atrás de `memcofre.ai.enabled`).
- ❌ Não publica/notifica ninguém ao registrar a fonte.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) — incl. troca de tipo mostrando/escondendo campos
- [ ] Confirmar limites de upload (tamanho/MIME) contra o `StoreIngestRequest` no smoke
