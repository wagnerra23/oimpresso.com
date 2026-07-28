---
id: reference-feedback-testes-no-ct100-nao-local
name: Testes rodam no CT 100 (container staging), NUNCA na máquina local / Hostinger
description: Suíte Pest (e qualquer teste pesado / que precise do stack completo — OTel SDK, Meilisearch, serviços) roda no CT 100 via `docker exec oimpresso-staging php artisan test`. A máquina local do Wagner e o Hostinger NÃO têm recursos pra isso. CT 100 (Proxmox docker-host) é o lugar correto — tem CPU/RAM + o stack . ATENÇÃO (medido 2026-07-27) — o CT 100 NÃO tem o schema do núcleo; a suíte roda em sqlite em memória e todo teste que exige `business` PULA com exit 0. Quem executa contra MySQL real com biz=1+biz=2 são as 9 lanes de CI que usam a action pest-mysql-setup.
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
  incl. `require-dev`/OTel SDK) — em **sqlite `:memory:`**, ver a ressalva abaixo.
- ✅ CI (GitHub Actions) continua sendo o gate canônico de merge — mas a validação
  local-do-dev acontece no CT 100, não na workstation.

> ⚠️ **Ressalva que muda como ler o resultado (medido 2026-07-27).** O CT 100 **não
> tem o schema do núcleo**. A suíte roda em **sqlite `:memory:`**
> ([`phpunit.xml:75`](../../phpunit.xml)) e o override `-e DB_CONNECTION=mysql` aponta
> pra database `oimpresso_staging`, que nessa data tinha **15 tabelas**
> (só `copiloto_*`/`vestuario_*`) e **nenhuma `business`**. Nos dois caminhos, teste que
> exige o núcleo **pula** — e **skip sai exit 0**. Portanto **"0 failed" aqui não prova
> execução**: leia a contagem de *assertions*. Só **85** dos **1443** arquivos de teste
> usam `RefreshDatabase` (que constrói o próprio schema no sqlite); **562** têm guarda
> `markTestSkipped` por schema ausente.

## Como rodar (canônico)

```bash
# 1. levar o código pro staging (já tracka main; ou checkout cirúrgico):
ssh root@100.99.207.66   # Tailscale, key-based
cd /opt/oimpresso-staging/code
git fetch origin && git checkout origin/main -- <arquivos>   # ou git pull

# 2. rodar o teste DENTRO do container (recursos + stack completo):
#    NÃO passe -e DB_CONNECTION=mysql — aponta pra uma database sem o núcleo (skip silencioso).
docker exec oimpresso-staging php artisan test tests/Feature/Otel/OtelServiceProviderTest.php
docker exec oimpresso-staging php artisan test --filter=AlgumFiltro
```

Ao ler a saída, confira **`assertions`**, não `failed`:

```
Tests:  4 skipped (0 assertions)   # ← NÃO validou nada, e o exit code é 0
Tests:  4 passed (17 assertions)   # ← validou
```

## Segurança (por que é OK rodar no CT 100)

- O container é **isolado de prod** (Hostinger é outro host) e a suíte roda em
  **sqlite `:memory:`**, então nenhum test run toca dado real.
- Usar **`oimpresso-staging`**, não `oimpresso-mcp` (este é o MCP server LIVE que o
  time consome — não carregar com test runs).
- ⚠️ A database `oimpresso_staging` do MariaDB do CT 100 **não é** um clone de prod
  com `biz=1`. Bugs reais de schema/integração (`businesses`→`business` UPos, CSRF 419,
  FK sintética) só afloram nas **9 lanes de CI** que usam
  [`pest-mysql-setup`](../../.github/actions/pest-mysql-setup/action.yml) — MySQL real,
  schema baseline + seed `biz=1` (fixture "CI Biz") e `biz=2` (Tier 0 cross-tenant).

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

**Pendente (precisa de janela combinada):** provisionar o núcleo em `oimpresso_staging`
pela receita do CI. Não foi feito aqui porque o baseline
[`database/schema/mysql-schema.sql`](../../database/schema/mysql-schema.sql) recria 3 das
tabelas vivas de lá, e havia sessão paralela com dados de 2026-07-27 nelas.

## Refs

- ADR 0062 (separação runtime Hostinger ≠ CT 100)
- `docker exec oimpresso-staging` (CT 100 = `100.99.207.66`, Tailscale)
- `oimpresso-staging-db` — MariaDB do CT 100; a database `oimpresso_staging` **não** é
  clone de prod (ver Errata acima)
- [`pest-mysql-setup`](../../.github/actions/pest-mysql-setup/action.yml) — onde o MySQL
  real com `biz=1`+`biz=2` de fato roda (9 lanes de CI)
