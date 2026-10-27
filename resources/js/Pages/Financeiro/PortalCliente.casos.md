---
id: resources-js-pages-financeiro-portal-cliente-casos
casos: Portal do cliente · /portal-cliente/{token}
irmaos: PortalCliente.charter.md (lei) · PortalCliente.tsx (código) · ../../../../memory/requisitos/Financeiro/SPEC.md (US-FIN-082)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: o portal mostra a alguém de fora tudo o que ele deve a uma empresa; vazar título de outro cliente ou de outra empresa é o defeito mais caro que esta tela pode ter.
owner: wagner
last_run: "2026-10-27"
last_run_ci: "0 UC executado na lane — trio nasce neste PR; o veredito sai da lane financeiro-pest do PR"
related_us: [US-FIN-082]
---

# Casos de Uso & Aceite — Portal do cliente (`/portal-cliente/{token}`)

> Os UC derivam da US-FIN-082 do [SPEC](../../../../memory/requisitos/Financeiro/SPEC.md) e do charter,
> **não** do `.tsx` ([proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05). Pagar reusa o link de
> pagamento da US-FIN-081 ([PagamentoPublico.casos.md](PagamentoPublico.casos.md)).

## Rastreabilidade

| UC | O que defende | Peso | Âncora | Teste |
|---|---|---|---|---|
| UC-FINPOR-01 | o portal lista só os títulos do cliente, com total em aberto e histórico | must | US-FIN-082 | `PortalClienteContratoTest` |
| UC-FINPOR-02 | token desconhecido e flag desligada dão 404 | must `[T0]` | US-FIN-082 | `PortalClienteContratoTest` |
| UC-FINPOR-03 | o token de um cliente não paga título de outro | must `[T0]` | US-FIN-082 | `PortalClienteContratoTest` |
| UC-FINPOR-04 | uma empresa não gera nem mostra portal de outra | must `[T0]` | US-FIN-082 | `PortalClienteContratoTest` |
| UC-FINPOR-05 | pagar pelo portal cobra o valor em aberto pelo link de pagamento | must `[V0]` | US-FIN-082 | `PortalClienteContratoTest` |

## UC-FINPOR-01 · O portal lista só os títulos do cliente · `must`

**Dado** o cliente A com um título de 100,00 em aberto, um parcial com 40,00 em aberto e um pago, e o
cliente B com um título de 999,00 **Quando** A abre o portal **Então** vê os 2 em aberto, total
140,00, e o pago no histórico; nada do cliente B; a resposta traz `noindex` e `no-referrer`.

Status: 🧪

## UC-FINPOR-02 · Token desconhecido e flag desligada dão 404 · `must` `[T0]`

**Dado** um token que não está no banco, ou `FINANCEIRO_PORTAL_CLIENTE_ENABLED` desligada **Quando**
alguém abre ou posta no portal **Então** a resposta é 404.

Status: 🧪

## UC-FINPOR-03 · O token de um cliente não paga título de outro · `must` `[T0]`

**Dado** o portal do cliente A **Quando** alguém posta o id de um título do cliente B **Então** volta
com o erro "não está mais disponível", nenhuma cobrança é emitida e nenhum link é gravado.

Status: 🧪

## UC-FINPOR-04 · Uma empresa não gera nem mostra portal de outra · `must` `[T0]`

**Dado** um cliente da empresa 99 **Quando** a empresa 98 pede o portal dele **Então** "não
encontrado"; e um título da 99 com o mesmo `contact_id` do cliente A não aparece no portal da 98.

Status: 🧪

## UC-FINPOR-05 · Pagar pelo portal cobra o valor em aberto · `must` `[V0]`

**Dado** o título de 100,00 do cliente A **Quando** ele escolhe Pix no portal **Então** sai uma
cobrança de 10000 centavos com `origem_type = fin_titulo`, a página mostra o Pix, e o link que o
operador já tinha mandado para esse título continua abrindo.

Status: 🧪
