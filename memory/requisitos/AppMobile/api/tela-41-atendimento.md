# Tela 41 · Atendimento WhatsApp (P5) ✅ — tela nova

> ✅ **Rota no ERP** desde o PR 18 do playbook `propostas-app` (`AtendimentoController`). Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6). Origem: handoff `propostas-app` do Design, conferido em 2026-10-05.

## Já existe ✅ (web, `Modules/Whatsapp`)
- Rotas: `/inbox`, `/inbox/{id}/send`, `/inbox/{id}/send-media`, `/inbox/conversations`, `/inbox/contacts/search`, `/inbox/{id}/contact/create-from-phone`, macros e IA (`/inbox/{id}/ai/suggest-reply`).
- Consentimento: `contacts.whatsapp_consent` (+ `whatsapp_opt_in_at`), já devolvido em `GET /api/app/pessoas/{id}/cadastro` (§4.1).

## Rotas ✅ — token e `business_id` do usuário do token, explícito (ADR 0093)
Código: `app/Http/Controllers/Api/App/AtendimentoController.php` · rotas: `routes/api/app/atendimento.php`.

`GET /api/app/atendimento?pagina=N` →
```json
{ "canal": { "ligado": true, "numero": "+55 47 3321-0400" },
  "conversas": [ { "id": 77, "contato": { "id": 1, "nome": "Ótica Visão Clara" }, "ultima": "Pode mandar o orçamento?",
      "quando": "2026-10-05T12:31:00Z", "nao_lidas": 2, "consentimento": true, "janela_aberta_ate": "2026-10-06T12:31:00Z" } ],
  "pagina": 1, "tem_mais": false }
```

- 30 por página, mais recente primeiro; só conversas abertas ou aguardando atendente (como a Caixa da web).
- `canal`: o 1º canal WhatsApp **ativo** (`channels.status = active`) que o usuário enxerga; `numero` = `display_identifier`.
  Sem canal: `{ ligado: false, numero: null }` e lista vazia — o app não mostra a área em Mais.
- `contato.id` vem `null` quando a conversa ainda não está ligada a um cliente; o `nome` cai para o nome guardado na conversa ou o telefone.
- `consentimento` = `whatsapp_consent = true` (nulo conta como não). `janela_aberta_ate` = última mensagem do cliente + 24 h, ou `null` se já fechou.
- A área `atendimento` entra em `areas` (§6) com acesso **e** canal ligado.

`GET /api/app/atendimento/{id}` → `{ conversa, mensagens: [{ id, de: "cliente"|"eu"|"sistema", texto, midia, quando, status }] }`
- As 100 últimas, em ordem de envio. Notas internas da equipe não vêm.
- `de`: entrada = `cliente`; saída de pessoa = `eu`; saída do robô ou do sistema = `sistema`.
- `midia`: `{ tipo, nome }` para imagem, áudio, vídeo, documento e figurinha (sem URL na v1); `null` no texto.

`POST /api/app/atendimento/{id}/mensagens` (`Idempotency-Key` obrigatória, throttle 20/min):
```json
{ "texto": "Seu pedido #4812 está em Produção.", "modelo": null, "anexo": null }
```
→ `201 { mensagem: {…mesmo formato…} }`. `modelo` e `anexo` precisam vir `null` na v1 (senão `422 validacao`).

`POST /api/app/atendimento/{id}/atalho` (`Idempotency-Key` obrigatória, throttle 20/min):
```json
{ "tipo": "orcamento"|"status_pedido"|"link_arte", "ref_id": 2231 }
```
O ERP monta o texto (valor e link vêm dele). O app nunca digita valor. O registro precisa ser **do cliente da conversa**:
- `orcamento`: cotação (`transactions` venda, `draft` + `sub_status = quotation`) → "Orçamento #N: total <valor do ERP, formato pt-BR>."
- `status_pedido`: venda final → "Seu pedido #N está em <etapa>." (nome da etapa cadastrada; sem etapa, "está registrado").
- `link_arte`: ordem de produção da Comunicação Visual com `arte_url` → "Arte do pedido <código>: <link>".
- Conversa sem cliente ligado → `422 validacao`; registro inexistente, de outro cliente ou de outra empresa → `404 nao_encontrado`.

O envio (texto e atalho) usa o mesmo despacho da web: `Modules\Whatsapp\Services\Inbox\EnvioDeTextoNoCanal`, extraído de `InboxController::send`.

| Código | Quando |
|---|---|
| `403 sem_permissao` | sem `whatsapp.access` (leitura) / `whatsapp.send` (envio), ou módulo fora do plano; atalho com o ajuste desligado |
| `404 nao_encontrado` | conversa de outra empresa ou de canal sem acesso ativo (`channel_user_access`, salvo `whatsapp.view-all-phones`) |
| `409 em_andamento` | mesma `Idempotency-Key` ainda sendo enviada |
| `422 validacao` | corpo inválido ou sem `Idempotency-Key` (`{ erro, campos }`) |
| `422 idempotencia_conflito` | mesma chave com corpo diferente |
| `422 sem_consentimento` | envio ativo, fora da janela de 24 h, para contato com `whatsapp_consent` ≠ true |
| `422 janela_fechada` | canal oficial da Meta, fora das 24 h (modelo aprovado só pela web) |
| `422 bloqueado` | canal que ainda não envia pelo app (hoje só WhatsApp Web: Baileys/Whatsmeow) |
| `502 falha_envio` | o daemon recusou ou ficou fora; a mensagem fica gravada como falha |
| `503 sem_configuracao` | canal desligado |

- Mesma chave + mesmo corpo → mesma resposta (`200` no lugar do `201`), sem enviar de novo. A chave é reservada **antes** do envio, que sai do servidor.

## LGPD
Só há envio ativo com `whatsapp_consent = true` (Art. 7º, I). Responder dentro da janela que o cliente abriu é permitido. O consentimento é conferido no servidor antes de gravar a mensagem.

## Fora da v1
Áudio, figurinhas, anexo, modelo aprovado e transferir entre filas.

## Ajuste da empresa
`app_atendimento_atalhos` (padrão `true`), lido por `AjustesDaEmpresa::valor()`. Com `false`, `POST /atalho` → `403`. O consentimento LGPD **não** é ajuste.

## Teste
`Modules/Whatsapp/Tests/Feature/AppAtendimentoApiContratoTest.php` (lane `whatsapp-pest`), tenant 98 × 2, com o daemon falso (`Http::fake`).
