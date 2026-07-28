---
id: reference-feedback-testes-no-ct100-nao-local
name: Testes rodam no CT 100 (container staging), NUNCA na máquina local / Hostinger
description: Suíte Pest (e qualquer teste pesado / que precise do stack completo — OTel SDK, Meilisearch, serviços) roda no CT 100 via `docker exec oimpresso-staging php artisan test`. A máquina local do Wagner e o Hostinger NÃO têm recursos pra isso. CT 100 (Proxmox docker-host) é o lugar correto — tem CPU/RAM + o stack . PROVISIONADO em 2026-07-28 — a suíte roda contra o MySQL de staging (database oimpresso_staging), agora com schema completo (377 tabelas) e seed biz=1/biz=2 pela receita do CI. Duas ressalvas — skip continua saindo exit 0 (leia assertions, nunca a contagem de falhas), e a database PERSISTE entre runs, então verde aqui NÃO substitui o CI como gate de merge.
type: feedback
authority: canonical
date_captured: 2026-06-01
captured_in_session: T1.b OTel modernization (rodei testes local, Wagner corrigiu)
applies_to: TODA validação de teste antes de push/PR (Pest, suites de módulo, smoke)
severity: alta
related_adr: [0062]
---

# Feedback Wagner — testes rodam no CT 100, não na local

> **Origem:** 2026-06-01. Durante o fix do OTel (T1.b) eu rodei os Pest **na máquina
> local** (Herd php). Wagner corrigiu, textual: *"os testes não devem ser feito
> local, as maquinas não suportariam faça no ct 100 obrigatoriamente la tem recursos
> para isso. é o lugar correto anote na memória para não errar denovo"*.

## A regra (dura)

**Toda execução de teste Pest** — validação antes de push/PR, suite de módulo,
smoke — roda **no CT 100**, não na máquina local do Wagner nem no Hostinger.

- ❌ **NÃO** `php artisan test` na máquina local (Herd) — ela não aguenta a suíte
  (3000+ testes, 40+ módulos). Pode travar/derreter.
- ❌ **NÃO** rodar no Hostinger (shared hosting, sem recursos + runtime errado — ADR 0062).
- ✅ **SIM** no CT 100, container **`oimpresso-staging`** (tem Pest + todos os pacotes
  incl. `require-dev`/OTel SDK), contra o **MySQL de staging** — ver ressalvas abaixo.
- ✅ CI (GitHub Actions) continua sendo o gate canônico de merge — mas a validação
  local-do-dev acontece no CT 100, não na workstation.

> ⚠️ **Como ler o resultado (medido 2026-07-28).** A suíte roda contra o **MySQL**
> `oimpresso-staging-db` / database `oimpresso_staging` — e **sempre rodou**. O
> `<env name="DB_CONNECTION" value="sqlite"/>` do [`phpunit.xml:75`](../../phpunit.xml)
> **não** tem `force="true"`, então não sobrepõe a env `DB_CONNECTION=mysql` do container.
> Sonda dentro do processo de teste: `driver=mysql name=mysql database=oimpresso_staging`.
> Logo o `-e DB_CONNECTION=mysql` é **no-op**.
>
> O que faltava era o **schema** — até 07-27 a database tinha 15 tabelas e nenhuma
> `business`, e tudo do núcleo **pulava**. **Provisionado em 07-28** (377 tabelas · 820
> migrations · `biz=1`+`biz=2`).
>
> **1. Skip continua saindo exit 0** — `"0 failed"` não prova execução; leia *assertions*.
> **2. CT 100 ≠ CI** — lá o DB é fresco por lane, aqui ele **persiste**: teste que assume
> estado vazio, depende de ordem, ou limpa por `delete` se comporta diferente. Verde aqui
> **não substitui** o CI como gate de merge.

## Como rodar (canônico)

```bash
# 1. levar o código pro staging (já tracka main; ou checkout cirúrgico):
ssh root@100.99.207.66   # Tailscale, key-based
cd /opt/oimpresso-staging/code
git fetch origin && git checkout origin/main -- <arquivos>   # ou git pull

# 2. rodar o teste DENTRO do container (recursos + stack completo):
#    -e DB_CONNECTION=mysql e' no-op (a env do container ja e' mysql) — pode omitir.
docker exec oimpresso-staging php artisan test tests/Feature/Otel/OtelServiceProviderTest.php
docker exec oimpresso-staging php artisan test --filter=AlgumFiltro
```

Ao ler a saída, confira **`assertions`**, não `failed`:

```
Tests:  4 skipped (0 assertions)   # ← NÃO validou nada, e o exit code é 0
Tests:  4 passed (17 assertions)   # ← validou
```

## Segurança (por que é OK rodar no CT 100)

- O container é **isolado de prod** (Hostinger é outro host); a database
  `oimpresso_staging` é de teste, semeada com fixture sintética (`CI Biz`), não com
  dado de cliente — nenhum test run toca dado real.
- Usar **`oimpresso-staging`**, não `oimpresso-mcp` (este é o MCP server LIVE que o
  time consome — não carregar com test runs).
- ⚠️ `oimpresso_staging` **não é clone de prod** — desde 07-28 tem o mesmo schema
  baseline + seed das lanes de CI (`biz=1` = fixture "CI Biz", `biz=2` = Tier 0
  cross-tenant), via [`pest-mysql-setup`](../../.github/actions/pest-mysql-setup/action.yml).
  Fixture sintética, não dado de cliente.
- ⚠️ O checkout montado no container (`/opt/oimpresso-staging/code`) fica **atrás do
  `main`** e costuma ter alterações não-commitadas de outra sessão. **Não dê `git pull`
  lá sem combinar** — e lembre que o resultado reflete aquele checkout, não o `main`.

## Por que erramos

A máquina local tinha o vendor (incl. SDK em `require-dev`) então `php artisan test`
*funcionava* pra 1 arquivo — mas isso não escala pra suíte e não é o lugar. O reflexo
certo: validar no CT 100 staging, sempre.

## Errata 2026-07-27 — "MySQL real, biz=1 dogfooding" era falso

A redação original (2026-06-01) afirmava que o CT 100 rodava contra um MySQL real com
`biz=1` dogfooding. **Não rodava, e a afirmação nunca teve recibo.** Medido em
2026-07-27, no próprio container:

| Fonte medida | Resultado |
|---|---|
| `phpunit.xml:75-76` | `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` — o default nunca foi MySQL |
| `information_schema` do `oimpresso-staging-db` | `oimpresso_staging` = **15 tabelas** (`copiloto_*`/`vestuario_*`), **sem `business`** |
| `artisan test …/ImpostosGuardTest.php` | `4 skipped, 0 assertions`, **exit 0** |

O repo **já se contradizia**: [`block-test-fora-ct100.mjs`](../../.claude/hooks/block-test-fora-ct100.mjs)
descrevia corretamente "DB sqlite :memory: isolado", enquanto este doc (e a regra que o
citava em [`proibicoes.md`](../proibicoes.md)) afirmava MySQL real. Pela precedência
canônica, o perdedor foi corrigido.

Fica registrado em vez de apagado: é a classe **LC-08** (afirmar a partir da fonte
errada) — o doc descreveu a *intenção* do CT 100, não o que ele fazia, e a afirmação
atemporal apodreceu calada por ~2 meses.

## Errata 2026-07-28 — a correção de 07-27 também errou: nunca foi sqlite

A errata acima consertou o "biz=1 dogfooding", mas **introduziu outra afirmação falsa**:
que a suíte rodava em **sqlite `:memory:`** por default. Não rodava. Eu li o
`phpunit.xml` em vez de medir o processo — e `<env>` **sem `force="true"` não sobrepõe**
env já existente, que no container é `DB_CONNECTION=mysql`.

Sonda dentro do próprio processo de teste (2026-07-28), com e sem o override:

```
>>> SONDA driver=mysql name=mysql database=oimpresso_staging env='mysql' config=mysql
```

Consequências: (a) o `-e DB_CONNECTION=mysql` do caminho canônico **sempre foi no-op**;
(b) o [`block-test-fora-ct100.mjs`](../../.claude/hooks/block-test-fora-ct100.mjs), que
eu havia declarado "o artefato correto", também estava errado — dizia "DB sqlite
:memory:" e foi corrigido junto. Os **dois** artefatos erravam, em direções opostas.

Classe: **LC-08** de novo — derivar do config estático em vez de perguntar ao runtime.

**Provisionado em 2026-07-28** (a pendência da errata anterior). Receita do CI, sem
inventar um 3º jeito de semear: baseline + `migrate` + seed `biz=1`/`biz=2`.

| Antes (07-27) | Depois (07-28) |
|---|---|
| 15 tabelas, sem `business` | **377 tabelas**, 820 migrations, `business=2` |
| `ImpostosGuardTest` → `4 skipped (0 assertions)` | → **`4 passed (30 assertions)`** |
| `Modules/Financeiro/Tests/Feature` → tudo pulando | **418 passed · 90 failed · 52 skipped (2133 assertions)** |

As 2 linhas que uma sessão paralela tinha em `vestuario_creditos_cliente`/
`vestuario_devolucoes` foram salvas antes (o baseline recria essas 2 tabelas) e
restauradas depois — conferido, sem perda de coluna.

**As 90 falhas não são 90 regressões.** Concentram causas de ambiente: baseline de
junho/2026 defasado do código (`transactions.deleted_at` inexistente), bundle CSS
ausente no checkout, e limpeza por `delete` barrada por regra de domínio
(`fin_titulos não permite delete`) — o CI cria DB fresco por lane, aqui ele persiste.
Triagem dessas falhas segue em aberto.

## Refs

- ADR 0062 (separação runtime Hostinger ≠ CT 100)
- `docker exec oimpresso-staging` (CT 100 = `100.99.207.66`, Tailscale)
- `oimpresso-staging-db` — MariaDB do CT 100; a database `oimpresso_staging` **não** é
  clone de prod (ver Errata acima)
- [`pest-mysql-setup`](../../.github/actions/pest-mysql-setup/action.yml) — onde o MySQL
  real com `biz=1`+`biz=2` de fato roda (9 lanes de CI)
