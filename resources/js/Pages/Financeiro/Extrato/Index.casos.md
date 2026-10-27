---
id: resources-js-pages-financeiro-extrato-index-casos
casos: Extrato bancário · /financeiro/extrato/{contaId}
irmaos: charter ao lado (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-08-17"
---

# Casos de uso — /financeiro/extrato/{contaId}

> **Status:** ✅ passa (prova no manifesto) · 🧪 prova existe mas o `it()` ainda **não cita o id** (G-2 — corrigir no mesmo PR) · ⬜ sem prova · ❌ quebrou.
>
> Redigido pelo [CC] em 2026-08-17 a partir do charter da tela + do protótipo Cowork. Regra G-2 ([ADR 0264]) respeitada: comportamento **sem teste** fica em **[BACKLOG] sem id** — `UC-*` órfão quebra o `casos-gate`.

Tela `live`, read-only, extrato sincronizado por cron (Inter PJ Open Finance · US-RB-046).

> ⚠️ **Âncora podre:** o charter cita 8 `it()` de `FinanceiroExtratoCharterTest`, arquivo que **não existe** em `Modules/Financeiro/Tests/` (medido 2026-08-17). Provas reais: `ExtratoControllerTest`, `ExtratoNavRedirectTest`, `BackfillExtratoOfxTest`.

## UC-EXT-01 — Ver saldo e lançamentos de uma conta
Status: 🧪 (`ExtratoControllerTest`)
Quando abre `/financeiro/extrato/{id}` · Então vê banco + conta no header, 4 KPIs (saldo atual, crédito e débito do período, contagem) e a lista com data, valor, C/D, descrição e contraparte.

## [BACKLOG] Saldo sem sync mostra "—", nunca R$ 0,00
Status: ⬜ sem prova — saldo_atualizado_em só aparece como fixture (=> now()); nenhuma asserção do caso nulo → "—". Vira `UC-EXT-02` quando existir teste citando o id (G-2).
Dado `saldo_atualizado_em` nulo · Então a tela mostra "—" e o aviso "Sem sync" (nunca zero, que engana).

## [BACKLOG] Filtro de período é manual e preserva a posição
Status: ⬜ sem prova — preserveScroll/preserveState é visual, sem asserção. Vira `UC-EXT-03` quando existir teste citando o id (G-2).
Mudar as datas não recarrega nada até o clique em Aplicar.

## UC-EXT-04 — Navegação legada cai no lugar certo
Status: 🧪 (`ExtratoNavRedirectTest`)
Rotas antigas de extrato redirecionam pra a tela canônica sem 404.

## UC-EXT-05 — Backfill de OFX é idempotente
Status: 🧪 (`BackfillExtratoOfxTest`)
Reimportar o mesmo OFX não duplica lançamento (`external_id` único).

## UC-EXT-06 — Tier 0: conta de outro tenant é 404
Status: 🧪 (`ExtratoControllerTest` — valida `business_id` do `contaId`)
`{contaId}` de outro business não vaza dado ([ADR 0093]).

## UC-EXT-07 — OFX de banco sem API entra no extrato da conta com o sinal certo
Status: 🧪 (`ExtratoOfxContaContratoTest`)
Dado um banco sem API de extrato (Bradesco, Caixa, BB, C6, Cora — medido 2026-10-27) · Quando o OFX exportado do internet banking é importado na conta · Então cada transação vira linha em `fin_extrato_lancamentos` com valor positivo e tipo C/D pelo sinal do `TRNAMT` (vírgula decimal aceita), e a Conciliação lê o mesmo valor com sinal. Reimportar o arquivo não duplica (FITID). Conta de outro business não recebe nada. Com `FINANCEIRO_EXTRATO_OFX_CONTA` desligada, nada é gravado (US-FIN-082).

## UC-EXT-08 — O sync do Inter grava pelo mesmo caminho do OFX
Status: 🧪 (`ExtratoOfxContaContratoTest`)
O extrato do Inter (`SyncBankStatementsJob`) e o OFX passam pelo mesmo `StatementPersister`: valor positivo + C/D, `business_id` da própria conta, `origem` e `external_id` no formato da ADR 0236 (`api:<id>` / `ofx:<FITID>`) (US-FIN-082).

## Backlog de casos (sem id)
- **[BACKLOG] Nenhum side-effect no render** — não chama gateway, não dispara sync, não muta saldo (cron é o gatilho).
- **[BACKLOG] Guard do defer** — o hotfix #3605 (tela branca por defer sem guard) merece uma prova, não só um smoke.

## Trilha do tempo
- 2026-08-17 · [CC] criado no espelho Cowork; achada a âncora podre do charter.
- 2026-10-27 · [CC] UC-EXT-07/08 — OFX por conta na tabela canônica e gravação comum a todos os drivers (US-FIN-082).

[ADR 0264]: ../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
