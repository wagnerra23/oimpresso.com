---
date: "2026-07-03"
time: "17:30 BRT"
slug: dente-calculo-fiscal-motor-tributario
tldr: "Dente de cálculo da CAMADA FISCAL (motor tributário Fiscal/NfeBrasil/NFSe). TEST-ONLY. Cobertura REAL: o cascade do MotorTributarioService já era defendido, mas o arredondamento de centavo (ICMS fmt() + ISS valorIss) estava indefeso, ISS sem teste. 2 PRs MERGED [W]: #3735 (17 testes verde CT100) + #3749 (promove a gate required na lane NfeBrasil·MySQL)."
prs: [3735, 3749]
decided_by: [W]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0062-separacao-runtime-hostinger-ct100, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes]
next_steps:
  - "Nada obrigatório: dente entregue + wired como gate vivo. Fiscal segue defendido no arredondamento."
  - "Se abrir dente do ISS-cascade/base-reduzida/ICMS-ST: hoje o motor NÃO calcula ST/redução (mva/fcp são pass-through) — testar seria feature, não defesa. Só sob US nova."
---

# Dente de cálculo — CAMADA FISCAL (motor tributário)

Aplica o padrão da Onda 1.4 (`onda-1-sells/1.4-dente-calculo.md`) ao coração fiscal. **1 dente cobre 3 módulos** (Fiscal/NfeBrasil/NFSe compartilham o `MotorTributarioService`) — abrir 3 colidiria no mesmo código.

## Estado MCP no momento do fechamento

- **cycles-active**: nenhum cycle ATIVO em COPI (off-cycle).
- **my-work**: 30 tasks ativas. Relacionadas ao tema: `FORJA-136` (Financeiro — pilar Fiscal, parado em 5,5), `US-FISCAL-018` (cockpit Fiscal Larissa biz=4). Nenhuma consumida — este trabalho é o dente do programa-ondas, não uma US do backlog.
- **origin/main**: HEAD `56db5237df`. Meus 2 commits landed: `7a29016dcf` (#3735) + `9630029e26` (#3749).

## O que aconteceu

Pedido: dente de cálculo da camada fiscal, mirando o que resta **indefeso** no motor (o Financeiro #3710 já cobriu `updateGroupTaxAmount`).

**Verificação de cobertura REAL** (o agente backend marcou o motor como "tem teste" — conferi de verdade):
- `MotorTributarioServiceTest` é **real**, mas defende só o **cascade de seleção** (níveis 1→4, CST/CSOSN, cache, multi-tenant). Todos os asserts usam números redondos (100×0,18=18,0) que **nunca exercem o `fmt()`** → o **arredondamento de centavo estava indefeso**.
- `SpedMotorTributarioIntegrationTest` usa **mock** do motor → não defende cálculo.
- `NfseEmissaoPayload::valorIss()` (ISS) tinha **zero teste**.

**#3735 — o dente** (`tests/Feature/Calculo/CalculoTributarioTest.php`, 17 testes / 32 asserts, verde CT100 MySQL biz=1):
1. **Golden ICMS fracionário** — 12,34×0,18=2,2212 → 2,22 (RED se `fmt` deixar a 3ª casa).
2. **Property SEFAZ** sobre faixa — imposto ≤2 casas + desvio ≤ meio centavo (mata o vetor de escala `num_uf` ×10⁵).
3. **Golden ISS** — 333,33×0,05=16,6665 → 16,67.
4. **Caracterização ISS retido** — `issRetido=true → valorIss()=0,0` (LC 116/2003 Art. 6º; mudar = US separada REGRA MESTRE).
5. **Discriminação RED inline** — reproduz o strip-do-ponto (incidente 2026-06-05) e prova que o motor atual não converge.

Contrato ancorado em fonte **externa** (layout SEFAZ + ICMS/ISS=base×alíquota), não no código → evita o teste tautológico (proibicoes §5).

**#3749 — promoção a gate vivo**: o dente vivia só no CT100/nightly (como os irmãos em `tests/Feature/Calculo/`). Entrou na allowlist da lane **NfeBrasil·MySQL** (armada/required, ADR 0275) + registrei os paths de trigger (`Modules/NFSe/DTO/NfseEmissaoPayload.php` + o próprio teste). Agora se alguém quebrar `MotorTributarioService::fmt` ou `NfseEmissaoPayload::valorIss`, a lane **morde** o PR.

## Artefatos gerados

- `tests/Feature/Calculo/CalculoTributarioTest.php` (~347 linhas, novo) — #3735.
- `.github/workflows/nfebrasil-pest.yml` (+11 linhas: allowlist + paths + comentário ratchet) — #3749.

## Persistência

- **git**: 2 PRs squash-merged em `main` (`7a29016`, `9630029`); branches deletados; worktrees limpos.
- **MCP**: propaga via webhook GitHub→MCP.
- **BRIEFING**: não aplicável (TEST-ONLY, sem mudança de capacidade de módulo).

## Evidência (não narração)

- CT100 (staging MySQL real biz=1): **17 passed, 32 assertions, 14.9s** (#3735).
- Lane NfeBrasil·MySQL no CI (fresh migrate + seed biz=1/biz=2): log mostra `PASS Tests\Feature\Calculo\CalculoTributarioTest` — os 17 rodaram; lane total **89 passed / 203 assertions** (#3749). CI dos 2 PRs: verde, 0 fail.

## Lições catalogadas

- **REGRA MESTRE / TEST-ONLY respeitada**: a tentativa de mutar `fmt()` no staging pra provar RED foi **corretamente bloqueada** pelo classifier — não contornei; RED provado pelo teste de discriminação inline (mesma abordagem não-destrutiva do dente-irmão de Sells).
- **Seguro na lane MySQL**: `DatabaseTransactions` (não `RefreshDatabase` — não envenena o seed) + `nfe_fiscal_rules.business_id` é int indexado **sem FK** → zero dependência de seed; UNIQUE (biz,ncm,uf_o,uf_d) não colide (rollback por método).
- **Promover a gate = passo de ratchet separado** (não bundlei no PR do teste) — espelha o commit #3728 (Compras) que fez exatamente isso pra um teste.
- **MSYS mangling reincidiu**: `git show origin/main:<path>` voltou vazio (`:`→`;`, `/`→`\`) — usei worktree fresco em vez de `git show`.

## Pointers detalhados

- Padrão do dente: `memory/requisitos/_Governanca/programa-ondas/onda-1-sells/1.4-dente-calculo.md`.
- Handoffs irmãos (mesma série, mesmo dia): Financeiro `2026-07-03-1044-fin-dente-calculo.md`, RecurringBilling `2026-07-03-1215-dente-calculo-recurringbilling.md`, Produto `2026-07-03-1703-dente-calculo-produto.md`.
- Código sob defesa: `Modules/NfeBrasil/Services/MotorTributarioService.php` (`fmt`, `aplicarRegra`, `aplicarDefaults`), `Modules/NFSe/DTO/NfseEmissaoPayload.php` (`valorIss`).
