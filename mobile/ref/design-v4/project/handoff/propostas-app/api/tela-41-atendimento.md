# Tela 41 · Atendimento WhatsApp (P5) ⬜ — tela nova

> Proposta. Vira `api/tela-41-atendimento.md` no ERP.

## Já existe ✅ (web, `Modules/Whatsapp`)
- Rotas: `/inbox`, `/inbox/{id}/send`, `/inbox/{id}/send-media`, `/inbox/conversations`, `/inbox/contacts/search`, `/inbox/{id}/contact/create-from-phone`, macros e IA (`/inbox/{id}/ai/suggest-reply`).
- Consentimento: `contacts.whatsapp_consent` (+ `whatsapp_opt_in_at`), já devolvido em `GET /api/app/pessoas/{id}/cadastro` (§4.1).

## Novo ⬜ — por cima do `InboxController`, com token e `business_id` explícito (ADR 0093)
`GET /api/app/atendimento` →
```json
{ "canal": { "ligado": true, "numero": "+55 47 3321-0400" },
  "conversas": [ { "id": 77, "contato": { "id": 1, "nome": "Ótica Visão Clara" }, "ultima": "Pode mandar o orçamento?",
      "quando": "2026-10-05T12:31:00Z", "nao_lidas": 2, "consentimento": true, "janela_aberta_ate": "2026-10-06T12:31:00Z" } ],
  "pagina": 1, "tem_mais": false }
```

- `canal.ligado: false` → o app não mostra a área em Mais. A área `atendimento` só entra em `areas` (§6) com o canal ligado e permissão.

`GET /api/app/atendimento/{id}` → `{ conversa, mensagens: [{ id, de: "cliente"|"eu"|"sistema", texto, midia?, quando, status }] }`

`POST /api/app/atendimento/{id}/mensagens` (`Idempotency-Key`):
```json
{ "texto": "Seu pedido #4812 está em Produção.", "modelo": null, "anexo": null }
```

`POST /api/app/atendimento/{id}/atalho`:
```json
{ "tipo": "orcamento"|"status_pedido"|"link_arte", "ref_id": 2231 }
```
O ERP monta o texto (valor e link vêm dele). O app nunca digita valor.

| Código | Quando |
|---|---|
| `403 sem_permissao` | sem a permissão do inbox web |
| `422 sem_consentimento` | envio ativo, fora da janela de 24 h, para contato com `whatsapp_consent` ≠ true |
| `422 janela_fechada` | fora das 24 h sem modelo aprovado |
| `503 sem_configuracao` | canal desligado |

## LGPD
Só há envio ativo com `whatsapp_consent = true` (Art. 7º, I). Responder dentro da janela que o cliente abriu é permitido.

## Fora da v1
Áudio, figurinhas e transferir entre filas.

## Ajuste da empresa
`app_atendimento_atalhos` (padrão `true`). Com `false`, `POST /atalho` → `403`. O consentimento LGPD **não** é ajuste.
