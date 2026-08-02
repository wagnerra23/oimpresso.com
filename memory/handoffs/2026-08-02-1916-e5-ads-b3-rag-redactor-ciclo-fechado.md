---
date: "2026-08-02"
time: "19:16 BRT"
slug: e5-ads-b3-rag-redactor-ciclo-fechado
tldr: "E5 do ADS fechado (36.986 linhas arquivadas no CT 100, 5 tabelas dropadas, 6 preservadas, smoke real) + B3 vivo em prod (284 docs do trio no RAG, gap 0) + ADR 0365 ratificada (trio fica colado) + PiiRedactor deixando de comer run id de CI. O achado que pagou o dia veio de desconfiar de um número que não mexia: 3 camadas de teste que o CI nunca executou. Ciclo de aprendizado fechado — 10 de 10 erros com lápide e contador (LC-08 39, LC-13 7, LC-16 e LC-17 novas)."
prs: [5143, 5148, 5157, 5159, 5163, 5167, 5169, 5176, 5179]
decided_by: [W]
related_adrs:
  - 0363-governance-incorpora-ads-nucleo-sem-receptor
  - 0365-trio-de-tela-fica-colocado-reverte-eixo-0364
  - 0364-trio-de-tela-mora-em-memory-emenda-0264
  - 0344-two-strikes-cobre-processo
next_steps:
  - "Reindexar `--only=casos` em prod: os 8 casos.md indexados ANTES do fix do redactor seguem com recibo de CI apagado (chip aberto)"
  - "E7 do ADS — lápide §5 + BRIEFING final (chip aberto)"
  - "Modules/{NfeBrasil,RecurringBilling}/Tests/Unit continuam fora do phpunit.xml (chip aberto)"
  - "Plano B: só B2/B4/B7-cobertura em aberto — todos advisory/ratchet, dono [F], nenhum depende de [W]"
---

# E5 do ADS · B3 no RAG · redactor · ciclo fechado

> Sessão longa, 9 PRs mergeados. O que vale reler não é a lista — são os três achados
> que só apareceram porque alguma medição foi desconfiada.

## O que está no ar

| PR | Intent | Prova |
|---|---|---|
| [#5143](https://github.com/wagnerra23/oimpresso.com/pull/5143) | **E5** — archive 36.986 + drop de 5 tabelas | 102 checks · smoke: 5 ausentes, 6 de pé, 4 rotas 302 |
| [#5157](https://github.com/wagnerra23/oimpresso.com/pull/5157) | auto-sync da âncora doc→código (Swimm traduzido) | selftest 8/8 |
| [#5159](https://github.com/wagnerra23/oimpresso.com/pull/5159) | ambíguas 24→11 · 47 âncoras carimbadas | selftest 9/9 |
| [#5163](https://github.com/wagnerra23/oimpresso.com/pull/5163) | ambíguas 11→3 · `--check` ligado em CI | OK=63 de 66 |
| [#5167](https://github.com/wagnerra23/oimpresso.com/pull/5167) | **B3** — trio no RAG in-place | prod: 284 indexados, `sync_gap` 0 |
| [#5169](https://github.com/wagnerra23/oimpresso.com/pull/5169) | redactor: run id ≠ CPF | lane rodou: 38 passed |
| [#5179](https://github.com/wagnerra23/oimpresso.com/pull/5179) | **ADR 0365** ratificada — trio fica colado | 94 checks |
| [#5148](https://github.com/wagnerra23/oimpresso.com/pull/5148) · [#5176](https://github.com/wagnerra23/oimpresso.com/pull/5176) | ledger | LC-08 39 · LC-13 7 · LC-16 · LC-17 |

## Os três achados que pagaram a sessão

**1. Indexar em produção revelou o que auditoria estática não viu.** O `--only=charter`/`--only=casos`
reportou **32 redactions de PII** em 8 `casos.md`. Nenhuma era PII: eram **run id do GitHub Actions**.
O `\d{11}` do CPF casa qualquer número de 11 dígitos e — liberado o CPF — o regex de telefone casava
os 10 primeiros. Não era vazamento; era o oposto, apagando do índice justamente o recibo de CI que a
regra de evidência exige. Fix por regra de formação: CPF tem dígito verificador, telefone tem DDD que
existe. **O segundo defeito só apareceu porque testei o primeiro** — o fix inicial passou no lint e
falhou no smoke.

**2. Um número que não mexia escondia 3 camadas.** Adicionei 6 casos Pest e o total da lane ficou
**33 antes, 33 depois**. Puxando o fio: (a) `Modules/Jana/Tests/Unit` **nunca esteve no `phpunit.xml`**
— 10 arquivos fora do CI, incluindo o `PiiRedactorTest`, o teste do redactor de PII do sistema;
(b) registrar não bastou, porque a lane **lista arquivo por arquivo** e não usa testsuite;
(c) o `[added] <arquivo>` que eu via no log é listing de arquivos mudados do PR, não execução. Só
rodou depois de ligar na lane certa nos **dois** pontos (`paths:` + comando) — e aí virou **38 passed**.

**3. A lista de DROP do ADS encolheu 3 vezes, sempre pela mesma causa.** Erratas E3, C5 e a minha D1:
`mcp_projects`/`mcp_project_parts`, depois `mcp_decision_links`, depois `mcp_tool_executions` e
`mcp_user_module_access`. As duas últimas alimentam rotas que o smoke registrou **vivas** — dropá-las
converteria 302 em 500. O padrão vale como regra pro que restar: **antes de dropar tabela de módulo em
deprecação, procure o consumidor FORA dele.**

## Erros meus — 10, todos registrados

O ciclo estava 2 de 10 quando [W] pediu a matriz. Fechou em 10 de 10:

| classe | contador | o quê |
|---|---|---|
| LC-08 | 38 → **39** | linha vazia ≠ inexistente · PII auditada depois de commitar · `node -e` mangleado 3× |
| LC-13 | 6 → **7** | registrar no `phpunit.xml` e achar que passou a rodar |
| **LC-16** | **nova (2)** | reescrita textual sem âncora — comeu `:66-82`, duplicou path |
| **LC-17** | **nova (1)** | recuo à mão que virou estado de branch, não regra |

O que sobrevive delas: **toda reescrita automática de doc precisa de teste de identidade** (reverta a
transformação no diff e prove byte-idêntico — foi isso que pegou os dois defeitos do LC-16 antes do
commit); **exceção que se repete a cada run pertence ao script**, não ao `git checkout`; **a prova de
que um teste rodou é o contador subir**.

## Três vermelhos que não eram meus

`Append-only canon` acusou reversão de `status: aceito` na ADR 0333 — que [W] tinha **acabado de
ratificar** (#5171). `Casos-coverage` acusou 20 telas do Ponto porque a sessão paralela aterrissou o
ratchet `191 → 146` às 19:24. Em ambos minha branch estava atrás; resolvi com merge do `main`, sem
tocar conteúdo alheio. **Reflexo pro futuro:** quando um gate acusa algo que você não fez, meça a
distância pro `main` antes de investigar o gate — o repo recebeu mais de 10 merges nesta janela.

## Decisões [W] desta sessão

- **merge do E5** — que, por o deploy rodar `migrate --force` automático, foi também o ato de aplicar
  o DROP. O plano previa um gate ✅[W]+✅[F] entre mergear e aplicar; **esse intervalo não existe** neste
  pipeline. Fica registrado: migration destrutiva futura precisa sair em PR separado ou usar
  `skip_migrate`.
- **`--only` controlado** antes do cron — evitou o cenário de 284 docs de uma vez, que é o gatilho do
  residual conhecido "sync completo falha com deadlock/OOM". A janela existiu por acaso: o primeiro
  deploy falhou no pré-check por SSH timeout, antes de qualquer escrita.
- **Flip da ADR 0365** — o trio fica colado; a 0364 permanece ativa e intacta.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → **8 tasks, todas em REVIEW** (US-COPI-123 `p0` · US-TR-309/310/305/306 · US-PG-008 ·
  US-PROD-027 · US-INFRA-023) — nenhuma tocada nesta sessão
- `decisions-search "trio de tela colocado"` → a **0365 já responde pelo MCP**, com a 0364 ao lado —
  prova de que o webhook sincronizou o canon novo
- Handoff anterior desta linha: [2026-08-02 11:30](2026-08-02-1130-doc-organizar-opcao-b-e-pesquisas.md)
