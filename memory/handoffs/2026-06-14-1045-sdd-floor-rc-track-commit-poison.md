---
date: "2026-06-14"
slug: sdd-floor-rc-track-commit-poison
tldr: "Floor do nightly caiu 1928→1308 (era-sqlite já quarentenado no main). Trilha RC-3..22 fechou os root-causes individuais; o maior cluster restante (PaymentGateway ~150) era commit-poison cross-file — DT incompleto. RC-21 (28 arq) + RC-22 (4 arq NfeBrasil) fecham. Próximo gate: medir nightly antes da cauda (~20 testes espalhados)."
hour: "10:45 BRT"
topic: "SDD floor burn-down — trilha RC (root-causes individuais) + descoberta do commit-poison cross-file no PaymentGateway/NfeBrasil. 12 PRs na sessão. Floor 1308 medido (run 020001), categorizado em 9 buckets. era-sqlite confirmado já neutralizado no main."
duration: "~2h (continuação)"
authors: [W, C]
related_adrs: ["0101-testes-mysql-real-nao-sqlite", "0093-multi-tenant-isolation-tier-0", "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes"]
---

# Handoff — SDD floor: trilha RC fechada + commit-poison cross-file descoberto

> **TL;DR:** O floor do nightly full-suite caiu **1928 → 1570 → 1308** (medido, run `20260614-020001`). Os ~19 corrompedores **era-sqlite já estão quarentenados no main** (Onda 2 #2684 + Onda 3 #2709) — lever puxado. A trilha **RC-3..22** fechou os root-causes individuais. A descoberta da sessão: o maior cluster restante (**PaymentGateway ~150 falhas**) NÃO era falta de DT simples — era **commit-poison cross-file** (testes sem `DatabaseTransactions` commitam credencial no `beforeEach`, e como o nightly recria o DB **1× por run** e não por teste, a linha persiste e colide via índice único com TODOS os testes seguintes — inclusive os que já tinham DT). RC-21 (28 arq) + RC-22 (4 arq) fecham. **Próximo gate: medir o nightly antes de tocar a cauda** (~20 testes espalhados) — lição "previsão-como-fato é veneno".

## Floor medido (run 20260614-020001)
- **1.308 falhas** (668 fail + 640 error) de 10.452 testes · **1.536 skipped** (quarentena funcionando).
- Trajetória: 1928 (handoff 06-13) → 1570 (run 174707) → **1308** (run 020001). Run 020001 é ANTES dos merges RC desta sessão pegarem — próximo nightly cai mais.
- ⚠️ Os "4.756" de leitura crua eram double-count dos agregadores `phpunit.xml`+`Feature` (somam os filhos). Floor real = folhas com failure/error.

### Categorização dos 1308 (por assinatura de erro)
| bucket | qtd | natureza |
|---|---:|---|
| assertion | 396 | mix: snapshot superseded (quarentena) + Pest-API + bug real |
| outros | 345 | precisa inspeção (mock/expectations) |
| php-error | 141 | prováveis bugs de produto reais (Undefined/TypeError) |
| duplicate-key | 126 | commit-poison → RC-21/22 (PG 150-aprox dominante + NfeBrasil 17) |
| auth-403 | 104 | **ruído**: ~13 cache-stale reais (7 já no RC-21); resto = cascata PG + testes que esperam 403 |
| schema-missing-column | 68 | drift ou resíduo |
| http-500 | 61 | controller errors |
| class-not-found | 45 | autoload/namespace |
| schema-missing-table | 21 | resíduo cascata |
| fk-constraint | 1 | — |

## Descoberta-chave: commit-poison cross-file (mecanismo)
- Índice único `pg_cred_biz_gw_amb_unique` em `(business_id, gateway_key, ambiente)`.
- Nightly recria o DB **1× por run** (`DROP/CREATE DATABASE` no `ct100-fullsuite.sh`) + schema dump + migrate + seed (biz=1/biz=2). NÃO por teste.
- Teste SEM `DatabaseTransactions` → `create()` da credencial no `beforeEach` **commita** → linha persiste pelo resto do run → colide com o `create()` de todo teste seguinte do mesmo `gateway_key+biz+ambiente`.
- Por isso `SicoobApiDriverTest` (que JÁ tinha DT) falhava: `SicoobApiWebhookTest` (sem DT) commitava antes.
- **RC-3 foi necessário-mas-insuficiente**: DT em 5 arq não resolve enquanto 28 ainda commitam. Tem que ser DT em TODOS de uma vez.
- Fix canônico: `DatabaseTransactions` dá o mesmo isolamento per-teste que o sqlite `:memory:` recriado dava na era antiga (ADR 0101). Zero código de produto.

## era-sqlite: lever JÁ puxado (confirmado nesta sessão)
- Re-derivei os CORE-droppers (`Schema::drop(business|users|activity_log|permissions|roles|contacts)`): **19 arquivos, todos com skip-guard em MySQL** (`if driverName !== 'sqlite' markTestSkipped` + afterEach guardado). Os 7 RecurringBilling guardam via `config('database.default') !== 'sqlite'`.
- Landaram em #2684 (Onda 2 massa) + #2709 (Onda 3 Jobs/Inter). **Não há corrompedor era-sqlite ativo no main.**
- A previsão "pós-quarentena → ~150-300" NÃO bateu (sobrou 1308). Confirma de novo: medir, não prever.

## PRs da sessão (todos auto-merge squash)
- **Mergeados:** #2712 (RC-7/8 Jana DT + BrasilApi) · #2714 (RC-10/11) · #2716 (RC-15) · #2717 (RC-16 NFSe OtelHelper+DT) · #2718 (RC-17 cliente permissions) · #2719 (RC-18 Financeiro quarentena) · #2722 (RC-19 Pest4 toContain + Wave28 skip) · #2723 (RC-20 oficina permission-cache) · #2724 (**RC-21 PaymentGateway DT 28 arq**).
- **Na fila (CI verde, sem falha):** #2725 (RC-22 NfeBrasil DT 4 arq).
- PII gate desbloqueado em vários com `// pii-allowlist` em CNPJ fixture fake.

## Próximos passos (retomar)
1. **MEDIR primeiro** — esperar o nightly pós-RC-21/22. Esperado: cluster PaymentGateway (~150) cai junto + NfeBrasil (~17). Se cair proporcional → mecanismo confirmado, escalar à cauda. Se NÃO → mecanismo errado, reavaliar.
2. **Cauda mecânica (~20, só após medir):** mesma receita DT/`forgetCachedPermissions` em — Admin duplicate-key (4), Sells/CriarOsPorVendaTest (4, CONFIRMAR se não é idempotência de produto), Financeiro (2), Whatsapp/OficinaAuto auth-403 cache-stale real (~6), KB/ComunicacaoVisual/Cliente (1 cada).
3. **Cauda longa (~1078, triagem real):** assertion 396 + outros 345 + php-error 141 — mix de mais-quarentena, Pest-API, e bugs de produto reais. NÃO é mecânico — exige re-triage por arquivo (Lane D do plano-mãe). php-error (141) é o bucket mais provável de esconder bug real.

## Comandos de retomada
- Floor + categorias: `ssh root@100.99.207.66` → parse `/opt/oimpresso-fullsuite/runs/<latest>/junit.xml` (folhas com failure/error, descontar agregadores).
- Re-derivar commit-poison: arquivos sem `DatabaseTransactions` que fazem `::create(` em `beforeEach` num módulo com índice único.
- Plano-mãe: `memory/sessions/2026-06-13-prompts-burndown-f2b-pos-triage.md` + triage `2026-06-13-sdd-f2b-triage-q2.md`.

## Lições
- **commit-poison cross-file** é um modo de falha distinto do era-sqlite: não dropa schema, mas envenena via INSERT commitado + índice único + DB-por-run. Sintoma = duplicate-key (1062) em massa num módulo. Fix = DT em TODOS os que commitam, simultaneamente (parcial não resolve).
- **Bucket por keyword engana**: "auth-403=104" tinha só ~13 reais; o resto era cascata de outro root-cause ou testes que esperam 403. Sempre re-confirmar a causa real por arquivo antes de aplicar receita.
- **Pest 4 `toContain($v, "msg")`** trata o 2º arg como valor adicional a checar, não mensagem de falha — gera falso-negativo (corrigido no RC-19).
