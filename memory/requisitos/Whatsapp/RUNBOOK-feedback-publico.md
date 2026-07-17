---
title: "RUNBOOK — Feedback Público (canal web_form)"
module: Whatsapp
tela: Whatsapp/FeedbackPublico
owner: W
status: ativo
last_validated: "2026-07-17"
preconditions:
  - "Módulo Whatsapp habilitado no business (modules_statuses.json)"
  - "APP_KEY definida (o HMAC do link assinado depende dela)"
steps:
  - "Gerar o link: php artisan feedback:link {business_id} --detail"
  - "Enviar a URL ao cliente (WhatsApp / e-mail / rodapé de nota)"
  - "Cliente abre, escreve a dor e informa severidade 0-4"
  - "Ler os sinais em /atendimento/feedback (filtro canal=web_form)"
  - "Revogar: gerar link novo, ou rotacionar APP_KEY (invalida TODOS os links assinados)"
related_adrs: [0105-cliente-como-sinal-guiar-sem-mandar, 0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio, 0093-multi-tenant-isolation-tier-0, 0104-processo-mwart-canonico-unico-caminho]
---

# RUNBOOK — Feedback Público (`Pages/Whatsapp/FeedbackPublico.tsx`)

> **F1 PLAN** do MWART (ADR 0104) pra US-INFRA-002. Tela **pública** (sem auth, sem
> AppShellV2, fora do cockpit) — não segue PT-01..05, que assumem shell + sidebar.

## 1. Por que esta tela existe

A ADR 0334 diagnosticou atrofia do **órgão sensor**: *"o aparelho de sentir/rotear sinal do
cliente nunca foi instalado"*. A tabela `clients_feedbacks` existe desde o PR #1711 citando
a ADR 0105, mas a única entrada é `POST /atendimento/feedback/capture` sob
`can:whatsapp.access` — ou seja, **o sinal só existe quando o [W] ouve a Larissa no WhatsApp
e clica "Capturar"**. O [W] é o nervo, manualmente. Se ele não ouvir, o sinal morre.

Esta tela é o canal onde **o cliente reporta direto**, sem intermediário.

Medida que a justifica (2026-07-17): `gov/(gov+neg) = 77%`, ratio 3,4× — 778 PRs de
governança × 228 de negócio em 4 semanas. A ADR 0105 diz *"backlog só recebe item se cliente
paga + reporta OU métrica detecta drift"*, mas **não existia superfície onde o cliente
reportasse**.

## 2. Decisão de design (o que NÃO fizemos)

O SPEC da US-INFRA-002 pedia tabela nova `mcp_client_signals` + `Pages/Feedback/Form.tsx`.
**[W] decidiu 2026-07-17: estender `clients_feedbacks`** — a tabela nova duplicaria dedup
por signature, `relevance_score`, workflow de status, link `mcp_task_id`, dashboard e
sync-pro-git que a existente já tem e já são Tier 0 (padrão que `proibicoes.md` §5
2026-07-09 chama de *"duplica régua consolidada"*).

O campo `canal` (default `whatsapp`) era o plug-point: `web_form` entra ao lado.

| SPEC dizia | Entregue | Por quê |
|---|---|---|
| tabela `mcp_client_signals` | `clients_feedbacks` + 5 colunas nullable | não duplicar régua consolidada |
| `Pages/Feedback/Form.tsx` | `Pages/Whatsapp/FeedbackPublico.tsx` | a tela é do módulo Whatsapp; `Pages/Feedback/` criaria módulo-fantasma em `memory/requisitos/` |
| "token único por biz, expira 30d" | `URL::temporarySignedRoute` | HMAC + expiração nativos; zero tabela de token pra manter/vazar |
| tools MCP `client-signals-*` | **não nesta onda** | o consumo é outro intent; o dashboard `/atendimento/feedback` já lê a tabela |
| `screenshot_url` (upload) | coluna criada, **upload não** | aceitar arquivo em rota pública sem auth é superfície de abuso que não se paga agora; reservado pro APM (US-INFRA-003) |

## 3. Tier 0 — o ponto que NÃO pode regredir (ADR 0093)

A rota **não tem auth**, então `session('user.business_id')` é null e o global scope
`ScopeByBusiness` é **NO-OP** aqui (ele retorna cedo em `!auth()->check()` —
[`Modules/Jana/Scopes/ScopeByBusiness.php:26`](../../../Modules/Jana/Scopes/ScopeByBusiness.php)).

Portanto:

1. **`business_id` vem da URL assinada, nunca do input.** O middleware `signed` valida HMAC
   (APP_KEY) sobre a URL inteira: adulterar `?biz=4` → `?biz=1` **quebra a assinatura → 403**.
   O tenant é criptograficamente amarrado ao link.
2. **Toda query filtra `business_id` à mão.** Não confiar no global scope neste controller.
   O dedup usa `findDuplicateWithin90d($sig, $bizId)`, que já filtra explicitamente
   (verificado em `FeedbackRelevanceService.php:123` — não presumido).

Teste que trava isso: `FeedbackPublicoTest` — signed URL de biz=1 não cria nem enxerga
feedback de biz=99.

## 4. Fluxo

```
[W]  $ php artisan feedback:link 4 --detail
     → https://oimpresso.com/feedback?biz=4&expires=…&signature=…   (30d)
     → manda pro cliente (WhatsApp / e-mail / rodapé de nota)

Larissa  GET  /feedback?biz=4&…   → middleware signed → Pages/Whatsapp/FeedbackPublico
         escreve a dor + diz o quanto dói (0-4)
         POST /feedback?biz=4&…   → mesma URL, mesma assinatura (HMAC cobre URL, não método)

Sistema  dedup 90d por signature ─┬─ hit  → recorrente_count++ (pattern_emergente em 3)
                                  └─ novo → INSERT canal=web_form, status=novo
         Observer → signature + relevance_score
         → aparece em /atendimento/feedback junto do canal whatsapp
```

## 5. Rotas

| Método | Rota | Middleware | Controller |
|---|---|---|---|
| GET | `/feedback` | `signed`, `throttle:30,1` | `Publico\FeedbackFormController@show` |
| POST | `/feedback` | `signed`, `throttle:10,1` | `Publico\FeedbackFormController@store` |

GET e POST compartilham path **e** assinatura — o HMAC do Laravel cobre a URL, não o método,
então o form posta na mesma URL que abriu. Um link só, sem 2º token.

`throttle` espelha o portal público do `ConsultaOs` (anti-abuso em rota sem auth).

## 6. Campos novos em `clients_feedbacks` (todos nullable)

| Coluna | Por quê |
|---|---|
| `reporter_name` | o canal whatsapp resolve a pessoa por `contact_id`; no form público não há contact |
| `url_seen` | onde ele estava. `tela_afetada` é o julgado na triagem; este é o cru |
| `browser_console_dump` | *"cliente sabe ONDE dói, raramente sabe POR QUÊ"* (ADR 0105 §princípio 1) |
| `screenshot_url` | reservado pro APM (US-INFRA-003) — form v1 não faz upload |
| `severity_self_reported` | o quanto dói **segundo o cliente**. Distinto de `severity_nng` (o julgado, que alimenta o `relevance_score`). Guardamos os dois: a triagem ajusta o julgado sem nunca sobrescrever o bruto |

## 7. Operação

```bash
# gerar link (validade padrão 30d)
php artisan feedback:link 4 --detail

# validade custom
php artisan feedback:link 4 --dias=90

# revogar: gere um link novo, ou rotacione APP_KEY (invalida TODOS os links assinados
# do sistema — não só os de feedback. Use com consciência.)
```

Ler os sinais: `/atendimento/feedback` (dashboard existente, filtra por `canal`).

## 8. Pegadinhas

- **Rotacionar `APP_KEY` mata todo link assinado do sistema**, não só os de feedback. É a
  alavanca certa pra revogação em massa, mas não é cirúrgica.
- **Link vazado = qualquer um reporta como aquele business.** Aceito: o dano é sinal-ruído
  num inbox de triagem (não há leitura de dado do cliente na tela — só escrita). Se virar
  problema, o corte é rotacionar o link, não construir login pro cliente.
- **`severity_nng` é semeado por `severity_self_reported`** no insert. Na triagem, ajuste
  `severity_nng` — nunca o self-reported.
- **Não adicionar leitura de dados do business nesta tela.** Ela é write-only por design; um
  `GET` que exponha dado do tenant numa rota pública vira vazamento na hora (o global scope
  não te protege aqui — ver §3).

## 9. Próximos passos (fora desta onda)

- Tools MCP `client-signals-list` / `-triage` (o SPEC pedia; o dashboard já cobre o mínimo)
- Contagem no `brief-fetch` ("client_signals 24h: N pendentes") — pareia com `US-COPI-139`
- Wire ADS (US-INFRA-005): signal → `decide()` → US automática se confidence ≥ HIGH
- `screenshot_url` + `browser_console_dump` preenchidos pelo APM (US-INFRA-003)
