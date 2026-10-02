---
date: "2026-10-02"
time: "08:22 BRT"
slug: bugs-pessoas-d8-fechado
tldr: "Os dois defeitos do D8 do MAPA-DE-DADOS-v1 (app Mobile) estão no main: view_own na lista de Pessoas (#8469, de outra frente; meu #8476 fechado como duplicata) e ticket médio/saldo sempre 0 (#8479, antes→depois aprovado por [W]). Ledger LC-19 (#8484) e mapa (#8501) mergeados. A API de Pessoas do app é o #8497 da coordenação, conferido contra o D8."
prs: [8479, 8484, 8501]
decided_by: [W]
next_steps:
  - "Coordenação (sessão 'Coordenar app das lojas'): mergear o #8497 e depois abrir o PR do serviço único de saldo/ticket médio, com o troco (is_return) e o rascunho no valor_aberto como resíduos."
---

# BUGS PESSOAS — D8 do mapa de dados do app Mobile

## O que foi feito

| Item | PR | Estado |
|---|---|---|
| Lista de Pessoas ignorava `customer.view_own`/`supplier.view_own` | [#8469](https://github.com/wagnerra23/oimpresso.com/pull/8469) (outra frente) | mergeado 2026-10-01 19:25Z |
| Meu PR para o mesmo defeito | [#8476](https://github.com/wagnerra23/oimpresso.com/pull/8476) | fechado como duplicata |
| Ticket médio, total, nº de vendas e saldo sempre 0 (`transactions.total_paid` inexistente → `catch` zerava tudo) | [#8479](https://github.com/wagnerra23/oimpresso.com/pull/8479) | mergeado 2026-10-01 20:35Z (`bc41f10c15`) |
| Registro do erro de duplicata (LC-19 + lápide §5) | [#8484](https://github.com/wagnerra23/oimpresso.com/pull/8484) | mergeado (`1cf8067fcb`) |
| Mapa marca o D8 como fechado | [#8501](https://github.com/wagnerra23/oimpresso.com/pull/8501) | mergeado 2026-10-02 11:11Z (`22aaf8be3f`) |

**#8479:** prova vermelha no run 36915193199 (`4 failed`, `Failed asserting that 0 is identical to 3`) e verde no run 36917546995 (`224 passed, 0 failed`). Os 5 testes rodaram, incluindo o caso cross-tenant. A regra mestre de valor foi cumprida: dupla prova (à mão e por SQL direto) e antes→depois aprovado por [W]. O teste `Modules/Crm/Tests/Feature/ClienteStatsTicketMedioTest.php` foi ligado na lane `verticais-pest`.

**Consumidores do conserto:** `scoreRisco` (aba IA do drawer) e os dados das 3 rotas de IA do cliente. A pontuação de risco tem cache de 24h por pessoa.

## Para a próxima sessão

- A API de Pessoas do app é o [#8497](https://github.com/wagnerra23/oimpresso.com/pull/8497) da coordenação. As fórmulas dele batem com as do #8479. Não abrir uma segunda API.
- O serviço único de saldo e ticket médio (hoje em `PessoasController` e `ClienteIaController`) **fica com a coordenação**, depois do #8497. Se outra sessão quiser pegar, avisar a coordenação antes.
- Resíduos sem dono ainda: a lista web conta rascunho no `valor_aberto`, e nenhuma fórmula desconta pagamento `is_return`.

## Lição

Comecei o defeito 1 sem rodar `dup-detector --path`, e o #8469 estava aberto havia 23 minutos. Registrado como rec na LC-19 (#8484). No pedido seguinte do [W] (construir a API), a mesma sonda achou o #8497 antes de eu escrever qualquer linha.

## Estado MCP no momento do fechamento

- `cycles-active`: nenhum cycle ativo em COPI.
- `my-work`: nenhuma task ativa.
- `decisions-search "app mobile lojas pessoas"`: nenhuma ADR nova sobre o tema (as decisões do app vivem em `docs/lojas-app/DECISOES.md`).
