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

## UC-EXT-07 · OFX de banco sem API entra no extrato da conta com o sinal certo
- **Persona:** financeiro de uma empresa com conta no Bradesco, Caixa, BB, C6 ou Cora (bancos sem integração de extrato; medido em produção em 2026-10-27).
- **Aceite:** Dado o OFX exportado do internet banking (SGML ou XML, vírgula ou ponto decimal) · Quando é importado na conta · Então cada transação vira lançamento do extrato com valor positivo e tipo C/D pelo sinal do `TRNAMT`, e a Conciliação lê o mesmo valor com sinal; reimportar o arquivo não duplica. Dado uma conta do business 99 · Quando o 98 tenta importar nela · Então nada é gravado. Com `FINANCEIRO_EXTRATO_OFX_CONTA` desligada, o comando não grava.
- **Teste:** `Modules/Financeiro/Tests/Feature/ExtratoOfxContaContratoTest.php` — `UC-EXT-07 · OFX entra no extrato da conta com o sinal certo, lido igual pela Conciliação, sem duplicar` · `UC-EXT-07 · Tier 0: conta de outro business não recebe o OFX` · `UC-EXT-07 · comando com a flag desligada não grava; ligada, grava e o --dry só conta`.
- **Regressão que defende:** SPEC US-FIN-084 (sinal do extrato é valor exibido e conciliado — regra mestre de valor) e ADR 0093 (conta de outro business).
- **Status: ⬜** — o teste roda na lane `PHP / Pest (Financeiro · MySQL)`, que ainda não executou neste PR.

## UC-EXT-08 · O sync do Inter grava pelo mesmo caminho do OFX
- **Persona:** financeiro com conta Inter sincronizada pelo cron das 07:00 (US-RB-046).
- **Aceite:** Dado uma linha de extrato vinda da API do banco · Quando é gravada · Então fica com valor positivo e tipo C/D, `business_id` da própria conta, `origem=api` e `external_id=api:<id>` (formato da ADR 0236), e a Conciliação lê o débito como negativo.
- **Teste:** `Modules/Financeiro/Tests/Feature/ExtratoOfxContaContratoTest.php` — `UC-EXT-08 · linha de API grava pelo mesmo caminho: positivo + C/D, business da conta, external_id api:`.
- **Regressão que defende:** SPEC US-FIN-084 (gravação comum a todos os drivers) e ADR 0236 (chave unificada do extrato).
- **Status: ⬜** — o teste roda na lane `PHP / Pest (Financeiro · MySQL)`, que ainda não executou neste PR.

## Backlog de casos (sem id)
- **[BACKLOG] Nenhum side-effect no render** — não chama gateway, não dispara sync, não muta saldo (cron é o gatilho).
- **[BACKLOG] Guard do defer** — o hotfix #3605 (tela branca por defer sem guard) merece uma prova, não só um smoke.

## Trilha do tempo
- 2026-08-17 · [CC] criado no espelho Cowork; achada a âncora podre do charter.
- 2026-10-27 · [CC] UC-EXT-07/08 — OFX por conta na tabela canônica e gravação comum a todos os drivers (US-FIN-084).

[ADR 0264]: ../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
