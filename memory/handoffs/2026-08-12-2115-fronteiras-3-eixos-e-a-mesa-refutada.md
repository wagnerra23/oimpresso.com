---
date: "2026-08-12"
slug: fronteiras-3-eixos-e-a-mesa-refutada
hour: "21:15 UTC"
topic: "3º eixo de fronteira ganhou catraca; a mesa da norma foi refutada por 7 auditorias e reescrita"
authors: [C, W]
prs: [5702, 5708, 5713, 5716]
us: []
tldr: "O ciclo do MECANISMO fechou (3 eixos com catraca + baselines guardadas). O da NORMA não: a mesa que recomendava `depends_on` mergeou errada e a errata mergeou atrás. 7 de 7 auditorias voltaram com correção material; 2 achados vivos viraram tarefa própria."
outcomes:
  - "PR #5702 mergeado — catraca do eixo tabela (DB::table) + 3 baselines no baseline-tamper-guard"
  - "PR #5708 mergeado — mesa da norma, com o INSTRUMENTO ERRADO"
  - "PR #5713 mergeado — errata: `depends_on` marca acoplamento vivo como curado, provado por experimento"
  - "PR #5716 aberto — eixo tabela enxerga ALTER cross-module + estreia do allowlist_razoes"
  - "LC-22 nova no ledger + lápide §5; 5 erros meus registrados"
---

# Fronteiras — o que está fechado, o que não, e o que espera decisão

## Estado dos PRs

| PR | o quê | estado |
|---|---|---|
| [#5702](https://github.com/wagnerra23/oimpresso.com/pull/5702) | catraca do 3º eixo (tabela) + 3 baselines guardadas | **mergeado** 18:57Z |
| [#5708](https://github.com/wagnerra23/oimpresso.com/pull/5708) | mesa da norma — **instrumento errado** | **mergeado** 19:44Z |
| [#5713](https://github.com/wagnerra23/oimpresso.com/pull/5713) | errata da mesa | **mergeado** 20:57Z |
| [#5716](https://github.com/wagnerra23/oimpresso.com/pull/5716) | calibração: ALTER + `allowlist_razoes` | **aberto**, CI rodando |

## O que o próximo precisa saber, em uma frase

**`depends_on` NÃO é o slot de norma.** Declarar faz a catraca reportar o par como *"JÁ FORAM
CURADOS — remova do JSON"* sem nada ter sido curado. O slot que mantém o par **visível com a
razão** é `allowlist` + `allowlist_razoes` na baseline; `not_contains` serve pra delegação
(*"isto não é meu, é do X"*) e também tira o par da dívida. Adoção medida: `depends_on` 1/32,
`not_contains` 30/32, `allowlist_razoes` 0/0 até hoje (agora 1).

## Decisões abertas de [W] — 8 perguntas fechadas

Todas no §4 do doc `memory/decisions/proposals/2026-08-12-fronteiras-de-modulo-norma-por-par.md`
(já em main, versão corrigida). As duas mais consequentes:

- **P5** — o `ClienteVeiculosController` fica em `Modules/Crm` ou migra pro `app/` no
  DEPRECATION-PLAN? A resposta muda **13 setas de `app/ → OficinaAuto` que ninguém declarou**.
- **P1** — `/connector/api/crm/follow-ups` ainda é chamado por cliente externo? É empírico e o
  instrumento existe (middleware `log.delphi`). O CRM está em depreciação com BLOQUEIO E4 aberto.

## Aberto, sem dono

1. **Defeito ativo (LC-10 em prod):** `Modules/Crm/Routes/web.php:159-161` afirma em presente um
   gate de degradação que o `ClienteVeiculosController` não tem. Só não quebra porque `vehicles`
   viaja no `mysql-schema.sql`. Conserto: aplicar o gate canon (`ContactController:2174-2179` é
   o modelo) + corrigir a afirmação da rota.
2. **`Modules/FieldForce` não existe** e o `Connector` importa dele. Runtime é **403 fail-secure**
   (não 500 — guardas antes do uso). Nunca existiu neste repo: herança do fork UltimatePOS.
   Remover controller + rotas ⚠️ **junto com** `AuthApiTest.php:75-89`, que asserta 401/422 na
   rota e viraria 404. Escopo completo mapeado no §7 do doc.
3. **`NfeBrasil` mantém um 2º emissor NFSe inteiro** escrevendo em `nfse_emissoes` com colunas
   que não existem no baseline (`value_servico`, `item_lc116`, `numero_rps`, `status='pending'`).
   Divergência de **schema versionado**; não medido contra banco vivo.
4. **Branch-detrito:** `claude/19-fronteiras-negocio-3a196c` foi recriada pelo push depois que o
   merge do #5702 a apagou. Sem PR, 3 commits já em main via squash. Não deletei por prudência.

## Em curso em outras sessões (iniciadas por [W])

- **Sync bancário sem filtro de tenant** — `SyncBankStatementsJob` agendado itera conta de todos
  os tenants. Não é vazamento; é violação da regra escrita sem o `// SUPERADMIN:`.
- **`valor_aberto` do importer** — título legado `quitado` entra com o valor cheio em aberto.
  Cai na REGRA MESTRE de valor: exige dupla prova + antes→depois + aprovação [W].

## Se você for mexer no `catalog-graph`

- Os **dois** eixos são avaliados sempre e o veredito é união; cada mensagem nomeia o eixo.
- **Não baixe o limiar de infra de 3→2** — medido em 2026-08-12: cegaria o detector em
  `fin_contas_bancarias` e `subscriptions`. A razão está no `_regra` da baseline.
- O scanner usa **`git grep`**: fixture não rastreada é invisível, e experimento local sem
  `git add` lê como "não há acoplamento".
- **Não escreva regex por heredoc de bash nesta máquina** — as barras invertidas são halvadas e
  a medição sai 0 com rc=0. Use a tool Write, com controle positivo do próprio regex embutido.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**.
- `sessions-recent limit:3` → devolve logs de maio/junho indexados hoje; índice do MCP parece
  atrasado vs disco (mesmo sintoma do handoff de hoje 16:17).
- `whats-active` não consultado nesta rodada.
- Nada registrado em `mcp_tasks`.
