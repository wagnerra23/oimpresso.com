# ⚠️ GABARITO SELADO — NÃO ABRIR ANTES DE PREENCHER A `folha-cega.md`

> **[W]: se você está lendo isto antes de responder a folha, a rodada 1 morreu** — registre
> `cego: false` (o validador rejeita a entry, e está certo em rejeitar) e monte a rodada 2 com
> outro lote. Rotular vendo o veredito é ancoragem, não calibração.
>
> Este arquivo existe por **auditabilidade**: sem ele, "conferi e deu K/11" é palavra do agente.
> Com ele, qualquer um refaz a conta. É o mesmo motivo do ledger ser append-only.

## O que o juiz decidiu (o que sua resposta será comparada contra)

Fonte: comment de refutação do PR #4274 (Fable 5, sessão fresca, tier superior) + o valor
`status:` carimbado no frontmatter de cada `memory/requisitos/<Mod>/BRIEFING.md` em `origin/main`.
Conferidos 1:1 entre as duas fontes em 2026-07-17 (batem — zero divergência).

| # | Módulo | Status que o juiz CONFIRMOU | Evidência que o refutador deu |
|---|---|---|---|
| 1 | Crm | `producao` | 49 Http/31 Database/14 Tests; módulo pago `crm_module` |
| 2 | Financeiro | `producao` | 39 Http/82 Tests; Observers bridge Sells→`fin_titulos` em prod (#4267) |
| 3 | Governance | `producao` | `/governance/audit` LIVE em prod (incidente Radix + hotfix #3411) |
| 4 | Jana | `producao` | 139 Tests/84 Services; `jana:health-check` daily; CYCLE-01 ROTA LIVRE |
| 5 | NfeBrasil | `producao` | emissão/cancelamento SEFAZ via FSM LIVE biz=1 (ADR 0143) |
| 6 | OficinaAuto | `piloto` | `Config/config.php:15` "LIVE prod biz=164"; 91 veículos reais |
| 7 | PaymentGateway | `parcial` | 12 Http/23 Services/47 Tests **mas flags OFF** (#3371/#3364) |
| 8 | RecurringBilling | `producao` | 22 Http/41 Tests; 108 subs + 1311 invoices migradas (#4045) |
| 9 | Repair | `shared-infra` | consumido por OficinaAuto + ComunicacaoVisual + Vestuario |
| 10 | Sells | `producao` | domínio core; FSM LIVE biz=1; ROTA LIVRE 99% do volume |
| 11 | Whatsapp | `producao` | 34 Http/121 Tests; daemon Baileys CT100 em operação |

**Veredito da máquina no lote:** `aprovado` · `itens_verificados: 11` · `erros_confirmados: 0` ·
`error_rate_pct: 0`.

## Distribuição (por que o baseline trivial é 72,7%)

`producao` ×8 · `piloto` ×1 · `parcial` ×1 · `shared-infra` ×1 → responder `producao` em tudo
acerta **8/11 = 72,7%** sem saber nada. **O sinal da rodada está nos itens 6, 7 e 9** — são os
únicos onde o rótulo do [W] pode discordar sem ser chute.

## Os 3 itens onde o juiz pode estar errado (declarado antes de ver a resposta)

Registrado aqui pra não virar racionalização depois — se [W] discordar num destes, o achado
estava previsto; se discordar em outro, é achado novo e mais interessante ainda.

1. **#7 PaymentGateway = `parcial`** — o refutador **hesitou por escrito**: *"Nem producao nem
   em-construcao"*. Escolheu `parcial` num enum onde `em-construcao` também servia. É o item
   mais frouxo do lote.
2. **#6 OficinaAuto = `piloto`** — o refutador flagrou que `why-oimpresso.md` diz "aguardando
   sinal" e está **stale** vs o código (biz=164 LIVE). Se o Martinho já usa em regime real,
   [W] pode chamar de `producao` — e aí o juiz seguiu o carimbo do config, não o negócio.
3. **#9 Repair = `shared-infra`** — status estrutural, não de maturidade. Se [W] pensa em
   Repair como produto em `producao`, o enum é que está errado, não o juiz.

## Nota metodológica (limite honesto desta rodada)

Esta rodada calibra o juiz num lote de **prosa/status**, cujo veredito é barato de conferir.
Ela **NÃO** calibra o juiz em lotes de `anchors` (path/US-id vs código real), que são a maioria
do ledger e onde o custo humano é proibitivo (40-70 itens × reverificação). Logo: o número desta
rodada **não generaliza** pra "o juiz acerta em tudo". É o primeiro denominador, não o último —
e a entry no ledger declara isso na `evidencia`.
