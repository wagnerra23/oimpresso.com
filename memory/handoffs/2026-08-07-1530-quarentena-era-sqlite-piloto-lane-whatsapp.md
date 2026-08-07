---
date: "2026-08-07"
time: "15:30 BRT"
slug: quarentena-era-sqlite-piloto-lane-whatsapp
tldr: "Pedido era tirar biz=4 de 1 teste; virou 1 de 142 testes fora da quarentena era-sqlite + a 1a lane MySQL do Whatsapp. O teste convertido PEGOU a diferenca de contrato 6-vs-7 permissions ao rodar contra checkout velho — prova de que nao e tautologico. Achado lateral nao endereçado: 5 testes dropam tabela CORE sem skip."
prs: [5396]
decided_by: [W]
related_adrs:
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
  - 0093-multi-tenant-isolation-tier-0
  - 0062-separacao-runtime-hostinger-ct100
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
next_steps:
  - "Burn-down: 141 restantes (72 Whatsapp na lane nova, 69 em core tests/, Jana, RecurringBilling, NfeBrasil, PaymentGateway, Forja, Repair, KB)"
  - "Decidir os 5 testes que dropam tabela CORE sem skip (risco sobre oimpresso-staging persistente)"
  - "promote_by 2026-08-21: decidir promocao da lane whatsapp-pest a required"
---

## Estado MCP no momento do fechamento

- `cycles-active`: **nenhum cycle ATIVO** em COPI
- `my-work`: 8 tasks, **todas em REVIEW** (US-TR-309/310/305/306, US-PG-008, US-PROD-027, US-INFRA-023/048) — nenhuma tocada aqui
- Handoff irmão do mesmo dia: [`2026-08-07-0846-jana-onda1-e-o-vermelho-de-15-runs`](2026-08-07-0846-jana-onda1-e-o-vermelho-de-15-runs.md)

## O que aconteceu

O pedido era pequeno — tirar `biz=4` do `RegisterWhatsappPermissionsCommandTest` (dívida da US-GOV-059/#5381). [W] escalou duas vezes: *"vai ter que fazer sim"* (tirar `biz=1` também) e *"pode tirar da quarentena, os dois vamos colocar isso tudo para funcionar"*.

**A medição desmentiu o "dois".** `git grep` com controle positivo: **163** testes com `skip` a menos que sqlite; **142** deles também montam schema sintético (`dropIfExists`) — a quarentena real, com o mesmo comentário copiado (*"quarentena Onda 2 SDD floor; burn-down converte depois"*). 73 no Whatsapp.

**Por que a quarentena existia, e não era preguiça:** esses testes dropam `business`/`users`/`permissions`/`roles` e recriam à mão. O `ct100-fullsuite.sh` roda contra DB `*_test` **recriada** (guard aborta se o nome não terminar em `_test`) — ali é inofensivo. O `oimpresso-staging` **persiste** — ali destrói. E o schema sintético **divergia do real**: a `roles` inventada declarava `unique(name,guard_name)`, que o real **não tem**, e `business_id` nullable, quando o real é **NOT NULL + FK→business ON DELETE CASCADE**.

**Piloto de 1 arquivo, deliberado** (big-bang em legado já falhou — §5 2026-07-12). Converter exigiu: helper `rwpEnsureBusiness()` resolvendo a FK circular (`business.owner_id`→`users.id` × `users.business_id`→`business.id`); `DatabaseTransactions` e **não** `RefreshDatabase` (que dá `migrate:fresh` e apaga o seed — cascata de 454 falhas em `TestCase::healCanonicalTenantIfWiped`); e três asserções absolutas reescritas — `Permission::count()->toBe(0)` era **impossível** de satisfazer (78 permissions pré-existentes medidas no staging).

**A prova, e é o melhor pedaço:** rodei no CT 100 duas vezes. Com o checkout do container (07-23, **6** permissions): `5 failed / 21 assertions`, dizendo `6 !== 7`. Com o `DataController` de `main` (**7**): `10 passed / 34 assertions`. O vermelho **não era defeito do teste** — o container é anterior ao #5381, que adicionou `whatsapp.view-all-phones`. **O teste pegou a diferença de contrato.** Banco medido antes e depois: idêntico (biz 97/98 não sobraram, `users rwp_owner_* = 0`).

**A lane era obrigatória, não enfeite:** converter sem ela seria **perda** de cobertura — o teste só rodava na lane sqlite *porque* montava o próprio schema, e o Whatsapp não tem lane MySQL (fora do `modules-pest.yml`). `whatsapp-pest.yml` espelha `kb-pest.yml`, que nasceu do mesmo problema.

## Artefatos gerados

| Arquivo | O quê |
|---|---|
| `Modules/Whatsapp/Tests/Feature/RegisterWhatsappPermissionsCommandTest.php` | convertido pro schema real (383 ln alteradas) |
| `.github/workflows/whatsapp-pest.yml` | **lane nova** (MySQL, advisory, skip-as-pass) — HOME do burn-down |
| `.github/ci-sqlite-pest.list` | arquivo removido da lane sqlite (com o porquê) |
| `scripts/governance/gates-registry.json` | entrada + `promote_by: 2026-08-21` |
| `memory/reference/PAINEL-SISTEMA.md` · `MAQUINAS-INVENTARIO.md` | regenerados (119→120 workflows) |

## Persistência

- **git**: [#5396](https://github.com/wagnerra23/oimpresso.com/pull/5396) MERGED (squash `127446882c8`), 102 pass / 3 skipping / **0 fail**
- **CI**: lane nova passou no PR (SUCCESS) e disparou no push pra `main`
- **MCP**: nenhuma task mutada (as 8 em REVIEW não foram tocadas)

## Lições catalogadas

1. **Dois gates me pegaram e os dois estavam certos** — `maquinas-inventario DRIFT` (workflow novo sem índice regenerado) e `memory-health` (gate advisory novo sem `promote_by`, teto ADR 0298: *"advisory não nasce eterno"*). Nos dois casos re-rodei o gerador; **não editei número à mão** (§5 2026-07-17).
2. **Errei ao perguntar em vez de decidir.** Montei `AskUserQuestion` com menu de escopo; o hook `block-askq-execution-menu` bloqueou, corretamente — [W] já tinha mandado executar. A regra é **recomendar e seguir**.
3. **Reincidi na armadilha do `catch(status===1)`** — meu script cruzando listas usava `rg`, que não está no PATH, e o catch engoliu "comando não existe" como "não achou": os cinco contadores saíram **0**, falsamente. Refiz com `git grep` **+ controle positivo**. É literalmente a lápide §5 2026-07-31/2026-08-01.
4. **`[ -f ]` num diretório** deu falso "AUSENTE" pra `tests/Feature/Form` na validação da lista sqlite — falso alarme meu, não drift do repo.

## Fica aberto (decisão [W])

- **141 quarentenados** — o custo por arquivo agora está medido: helper de FK + asserções reescritas + run no CT 100 antes de entrar na allowlist. Não é mecânico.
- **⚠️ 5 testes dropam tabela CORE sem ter o skip** — `Whatsapp/WhatsmeowWebhookAuthTest` (`business`), `RecurringBilling/Wave21NewSubscription` e `Wave23EditarAssinatura` (`users`), mais 4 em `contacts`. Rodar a suíte inteira contra `oimpresso-staging` apaga tabela core do clone. Latente, mas real — **não endereçado aqui**.

## Pointers

- Teste convertido: docblock explica o antes/depois e por que não há skip de driver
- Lane: cabeçalho de `whatsapp-pest.yml` carrega o racional do burn-down + a catraca
- Sessão irmã do mesmo dia: [`0846-jana-onda1`](2026-08-07-0846-jana-onda1-e-o-vermelho-de-15-runs.md)
