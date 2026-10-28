---
id: resources-js-pages-financeiro-pagamento-publico-casos
casos: Pagamento público do título · /pagar/{token}
irmaos: PagamentoPublico.charter.md (lei) · PagamentoPublico.tsx (código) · ../../../../memory/requisitos/Financeiro/SPEC.md (US-FIN-081)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: o link é o único ponto em que alguém de fora paga um título sem login; o que ele mostra, o valor que cobra e a baixa que gera não podem mudar sem querer.
owner: wagner
last_run: "2026-10-27"
last_run_ci: "pendente — a lane financeiro-pest do #9961 ainda não terminou; Status fica ⬜ até o run"
related_us: [US-FIN-081]
---

# Casos de Uso & Aceite — Pagamento público do título (`/pagar/{token}`)

> Os UC derivam da US-FIN-081 do [SPEC](../../../../memory/requisitos/Financeiro/SPEC.md) e do charter,
> **não** do `.tsx` ([proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05). O desenho repete o
> aceite público do orçamento (US-COMVIS-023).

## Rastreabilidade

| UC | O que defende | Peso | Âncora | Teste |
|---|---|---|---|---|
| UC-FINPAG-01 | link válido abre a página com o valor em aberto, sem documento, sem indexar | must | US-FIN-081 | `LinkPagamentoTituloContratoTest` |
| UC-FINPAG-02 | token desconhecido e flag desligada dão 404 | must `[T0]` | US-FIN-081 | `LinkPagamentoTituloContratoTest` |
| UC-FINPAG-03 | uma empresa não gera link de título de outra | must `[T0]` | US-FIN-081 | `LinkPagamentoTituloContratoTest` |
| UC-FINPAG-04 | pagar com Pix cobra o valor em aberto e não duplica cobrança pagável | must `[V0]` | US-FIN-081 | `LinkPagamentoTituloContratoTest` |
| UC-FINPAG-05 | o aviso do banco dá baixa pelo valor pago; flag desligada não baixa | must `[V0]` | US-FIN-081 | `LinkPagamentoTituloContratoTest` |

## UC-FINPAG-01 · Link válido abre a página com o valor em aberto, sem documento, sem indexar · `must`
- **Persona:** cliente final (sem conta no sistema) abrindo o link que a empresa mandou.
- **Aceite:** Dado um link aberto de um título a receber de 150,00 · Quando o cliente abre `/pagar/{token}` · Então a página `Financeiro/PagamentoPublico` mostra o número e o valor em aberto 150,00 com `situacao: aberto`, sem documento nem id do cliente, e a resposta traz `X-Robots-Tag: noindex, nofollow` e `Referrer-Policy: no-referrer`.
- **Teste:** `Modules/Financeiro/Tests/Feature/LinkPagamentoTituloContratoTest.php` — `UC-FINPAG-01`.
- **Regressão que defende:** charter Objetivo 1 (conferir o valor gravado) e Non-Goal "mostrar documento, e-mail ou dado interno".
- **Status: ⬜** — não verificado: o teste nasce neste PR e o veredito sai da lane `PHP / Pest (Financeiro · MySQL)`.

---

## UC-FINPAG-02 · Token desconhecido e flag desligada dão 404 · `must` `[T0]`
- **Persona:** cliente final (sem conta no sistema) abrindo o link que a empresa mandou.
- **Aceite:** Dado um token de 48 caracteres que não está no banco, ou a flag `FINANCEIRO_LINK_PAGAMENTO_ENABLED` desligada · Quando alguém abre ou posta no link · Então a resposta é 404, sem dizer por quê.
- **Teste:** `Modules/Financeiro/Tests/Feature/LinkPagamentoTituloContratoTest.php` — `UC-FINPAG-02`.
- **Regressão que defende:** US-FIN-081 (404 sem motivo; tudo atrás da flag desligada).
- **Status: ⬜** — não verificado: o teste nasce neste PR e o veredito sai da lane `PHP / Pest (Financeiro · MySQL)`.

---

## UC-FINPAG-03 · Uma empresa não gera link de título de outra · `must` `[T0]`
- **Persona:** cliente final (sem conta no sistema) abrindo o link que a empresa mandou.
- **Aceite:** Dado um título da empresa 99 · Quando a empresa 98 pede o link dele (serviço ou rota logada) · Então a resposta é "não encontrado" e nenhum link é gravado.
- **Teste:** `Modules/Financeiro/Tests/Feature/LinkPagamentoTituloContratoTest.php` — `UC-FINPAG-03`.
- **Regressão que defende:** Tier 0 multi-tenant (ADR 0093): o link sempre pertence à empresa do título.
- **Status: ⬜** — não verificado: o teste nasce neste PR e o veredito sai da lane `PHP / Pest (Financeiro · MySQL)`.

---

## UC-FINPAG-04 · Pagar com Pix cobra o valor em aberto e não duplica cobrança pagável · `must` `[V0]`
- **Persona:** cliente final (sem conta no sistema) abrindo o link que a empresa mandou.
- **Aceite:** Dado um título com 150,00 em aberto e sem cobrança · Quando o cliente escolhe Pix · Então sai uma cobrança de 15000 centavos com `origem_type = fin_titulo` e o id do título, na empresa do link; Quando ele escolhe de novo · Então a página mostra o mesmo Pix e nada novo é emitido.
- **Teste:** `Modules/Financeiro/Tests/Feature/LinkPagamentoTituloContratoTest.php` — `UC-FINPAG-04`.
- **Regressão que defende:** charter Non-Goal "recalcular valor" e regra mestre de valor: a cobrança é o `valor_aberto` gravado, uma vez só.
- **Status: ⬜** — não verificado: o teste nasce neste PR e o veredito sai da lane `PHP / Pest (Financeiro · MySQL)`.

---

## UC-FINPAG-05 · O aviso do banco dá baixa pelo valor pago; flag desligada não baixa · `must` `[V0]`
- **Persona:** cliente final (sem conta no sistema) abrindo o link que a empresa mandou.
- **Aceite:** Dado um título de 150,00 com link · Quando o banco avisa 15000 centavos pagos · Então sai uma baixa de 150,00 em nome de quem gerou o link, o aberto vai a 0,00 e o título fica quitado; Quando avisa 5000 · Então a baixa é 50,00 e o aberto 100,00; Quando a flag está desligada · Então nada muda no título, como antes desta US.
- **Teste:** `Modules/Financeiro/Tests/Feature/LinkPagamentoTituloContratoTest.php` — `UC-FINPAG-05`.
- **Regressão que defende:** US-FIN-081 (baixa pelo webhook) e o comportamento anterior do listener fora da empresa dona do SaaS.
- **Status: ⬜** — não verificado: o teste nasce neste PR e o veredito sai da lane `PHP / Pest (Financeiro · MySQL)`.
