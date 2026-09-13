---
id: resources-js-pages-financeiro-cobranca-index-casos
casos: Cobrança · /financeiro/cobranca
irmaos: charter ao lado (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-08-17"
---

# Casos de uso — /financeiro/cobranca

> **Status:** ✅ passa (prova no manifesto) · 🧪 prova existe mas o `it()` ainda **não cita o id** (G-2 — corrigir no mesmo PR) · ⬜ sem prova · ❌ quebrou.
>
> Redigido pelo [CC] em 2026-08-17 a partir do charter da tela + do protótipo Cowork. Regra G-2 ([ADR 0264]) respeitada: comportamento **sem teste** fica em **[BACKLOG] sem id** — `UC-*` órfão quebra o `casos-gate`.

Personas: **Eliana [E]** + **Larissa [Cliente Piloto]**. Tela de acompanhamento (read-only); emissão vive em POST dedicado.

## UC-COB-01 — "Quem pagou hoje? O que vence amanhã?"
Status: 🧪 (`CobrancaControllerTest` — shape `cobrancas/kpis/funil/contas/gateways/filtros`)
Quando Eliana abre a tela · Então vê os 4 KPIs (Pago no mês · Vencido · Em aberto · 1 contextual) e a tabela densa por vencimento com pagador, chip composto gateway+tipo, conta destino, nosso nº, valor e status com ícone.

## UC-COB-02 — Funil de 5 etapas espelha o estado real
Status: 🧪 (`CobrancaControllerTest` — funil `aberto/lembrete/cobranca_ativa/vencido_5d/protesto`)
As 5 etapas somam as cobranças do período; "Protesto" é derivação de UI (sem job real — Non-Goal declarado).

## UC-COB-03 — Filtros e busca são deep-linkáveis
Status: 🧪 (`CobrancaControllerTest` — filtro por status/tipo/gateway/conta/origem via querystring)
Trocar tab/chip/dropdown reflete na URL e a volta pela URL reproduz a visão.

## UC-COB-04 — Cobrança paga cria o título no caixa
Status: 🧪 (`OnCobrancaPagaCreateFinanceiroTituloTest`)
Dado cobrança emitida · Quando o gateway confirma o pagamento · Então nasce/quita o título correspondente no Financeiro com a **forma realizada** (read-only na Unificada).

## UC-COB-05 — Boleto/PIX emitido tem artefato conferível no drawer
Status: 🧪 (`BoletoMockEmissaoTest` — emissão mock 21 bancos)
Boleto mostra linha digitável + código de barras; PIX mostra BR Code; cartão mostra last4 — cada tipo com seu drawer condicional.

## UC-COB-06 — Webhook do banco é idempotente
Status: 🧪 (`Onda26InterWebhookIntegrationTest`, `ProcessAsaasPixWebhookListenerTest`)
Re-entrega do mesmo webhook não duplica baixa nem título.

## UC-COB-07 — Tier 0, read-only e sem side-effect no render
Status: 🧪 (`CobrancaControllerTest` — global scope + GET não muta)
Abrir a tela não dispara e-mail/WhatsApp, não chama API de gateway e não enxerga outro business ([ADR 0093]). Redirect 301 de `/financeiro/boletos` preservado.

## Backlog de casos (sem id)
- **[BACKLOG] Export CSV/PDF** — botão existe na UI; backend é backlog (Onda 5). Hoje é botão sem contrato.
- **[BACKLOG] Wizard "Nova cobrança" 4 passos** — grava só no submit final; nenhum passo intermediário emite.
- **[BACKLOG] Remessa/Retorno CNAB 240 (C6)** — sheet existe, driver é backlog.
- **[BACKLOG] Atalhos KB-9.75** (`/` `J/K` `Enter` `Esc` `?`) e persistência dos filtros em `localStorage`.

## Trilha do tempo
- 2026-08-17 · [CC] criado no espelho Cowork (preparo da leva 2). Charter v1 rico, 0 `casos.md` — os `it()` do `CobrancaControllerTest` precisam citar os ids (G-2).

[ADR 0264]: ../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
