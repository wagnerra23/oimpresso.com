---
name: Testes rodam no CT 100 (container staging), NUNCA na máquina local / Hostinger
description: Suíte Pest (e qualquer teste pesado / que precise do stack completo — OTel SDK, Meilisearch, serviços) roda no CT 100 via `docker exec oimpresso-staging php artisan test`. A máquina local do Wagner e o Hostinger NÃO têm recursos pra isso. CT 100 (Proxmox docker-host) é o lugar correto — tem CPU/RAM + o stack + DB de staging isolado. phpunit.xml força sqlite :memory: → testes nunca tocam o DB de prod.
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
- ✅ **SIM** no CT 100, container **`oimpresso-staging`** (staging dedicado, DB próprio
  `oimpresso-staging-db`, tem Pest + todos os pacotes incl. `require-dev`/OTel SDK).
- ✅ CI (GitHub Actions) continua sendo o gate canônico de merge — mas a validação
  local-do-dev acontece no CT 100, não na workstation.

## Como rodar (canônico)

```bash
# 1. levar o código pro staging (já tracka main; ou checkout cirúrgico):
ssh root@100.99.207.66   # Tailscale, key-based
cd /opt/oimpresso-staging/code
git fetch origin && git checkout origin/main -- <arquivos>   # ou git pull

# 2. rodar o teste DENTRO do container (recursos + stack completo):
docker exec oimpresso-staging php artisan test tests/Feature/Otel/OtelServiceProviderTest.php
docker exec oimpresso-staging php artisan test --filter=AlgumFiltro
```

## Segurança (por que é OK rodar no CT 100)

- `phpunit.xml` força `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:` → os testes
  **nunca tocam o MySQL de prod** (Hostinger). Rodar no container é DB-isolado.
- Usar **`oimpresso-staging`**, não `oimpresso-mcp` (este é o MCP server LIVE que o
  time consome — não carregar com test runs).

## Por que erramos

A máquina local tinha o vendor (incl. SDK em `require-dev`) então `php artisan test`
*funcionava* pra 1 arquivo — mas isso não escala pra suíte e não é o lugar. O reflexo
certo: validar no CT 100 staging, sempre.

## Refs

- ADR 0062 (separação runtime Hostinger ≠ CT 100)
- `docker exec oimpresso-staging` (CT 100 = `100.99.207.66`, Tailscale)
- `phpunit.xml` (sqlite :memory: — isolamento de DB)
