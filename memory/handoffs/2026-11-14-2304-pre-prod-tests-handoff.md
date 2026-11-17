---
date: "2026-11-14"
time: "2304 BRT"
slug: "pre-prod-tests"
tldr: "Gates required do CI verdes, mas casos-gate vermelho no main, nightly full-suite CT 100 parada desde 06/out e 14 arquivos multi-tenant/LGPD vermelhos no último snapshot. 1 flaky real (Producao UC-PCOSF-03). Só reporte, nada corrigido."
decided_by: []
cycle: null
prs: []
us: []
next_steps:
  - "Reativar a nightly full-suite no CT 100 e ler o 1º snapshot novo"
  - "Triar os 14 arquivos multi-tenant/LGPD vermelhos (ambiente x bug real)"
  - "Revalidar Configuracoes/Empresa/Index.casos.md (last_run) pra destravar o casos-gate no main"
  - "Decisões [W]: lane NFSe, desmascarar continue-on-error dos E2E/a11y, flaky Producao"
related_adrs: []
---

# Handoff — testes pré-produção (2026-11-14 23:04)

**Relatório completo:** [`memory/sessions/2026-11-14-2304-pre-prod-tests.md`](../sessions/2026-11-14-2304-pre-prod-tests.md)
**Escopo:** só reporte. Nada corrigido, nada mergeado, nada rodado contra prod. Sem PHP na máquina → Pest/PHPStan via evidência CI + nightly CT 100; lint/tsc rodados local.

## TL;DR
Gates required do CI verdes; **nightly full-suite CT 100 parada desde 2026-10-06** e o último snapshot tinha 731 testes vermelhos, incluindo 14 arquivos de isolamento multi-tenant/LGPD.

## Bloqueadores (🔴)
1. Nightly CT 100 sem publicar há 39 dias (`governance/nightly-floor` @ `20261006-020001`) — verificar cron/`.lock` (RUNBOOK-ct100-fullsuite).
2. 14 arquivos multi-tenant/LGPD vermelhos na nightly (Connector, Forja, NFSe, NfeBrasil, Vestuario, Crm…) — triagem ambiente × bug.
3. `Modules/NFSe/Tests` (10 arquivos) fora de qualquer lane de CI.
4. `casos-gate` vermelho no main desde ~05/nov — `stale:Configuracoes/Empresa/Index.casos.md` (tsx mudou em #10342 após `last_run` 04/nov). Reproduzido em `20040dde`.
5. 93 `oimpresso.missingTenantScope` absorvidos no baseline PHPStan.

## Flaky
- `Modules\Producao\Tests\Feature\ProducaoFichaOsContratoTest` › UC-PCOSF-03 — falhou attempt 1 / passou rerun no mesmo SHA em 2 de 18 runs reais da lane Verticais (PRs alheios).

## Próximo passo sugerido
1. Reativar nightly e ler o 1º snapshot novo.
2. Triagem dos 14 arquivos Tier 0.
3. Decisões [W]: lane NFSe · desmascarar `continue-on-error` dos E2E/a11y (Vestuario + NfeBrasil Tributação vermelhos escondidos) · PHPStan 5→7.

## Pegadinhas pra próxima sessão
- Verde das lanes por módulo ≈ skip-as-pass (60/60 runs em Financeiro/Jana/Ponto/PG/Officeimpresso/Arquivos não executaram teste). Olhar o step `Skip-as-pass`, não a conclusão.
- `tsc` local acusa 3 regressões falsas por Wayfinder stale gitignored (`resources/js/routes/`, `actions/`).
- Cobertura não existe em lugar nenhum (`coverage_pct: null`).
- Worktree `nostalgic-bartik-c99b10` estava vazio e o checkout raiz `D:/oimpresso.com` está em `532ba51fbb` (02/out, **2.192 commits atrás**). Lint/tsc/casos foram medidos num worktree descartável de `origin/main` `20040dde` com `npm ci` limpo.
- Pixel-diff visual não executou em nenhum dos 60 runs amostrados — não há tabela tela×diff%.

## Estado MCP no momento do fechamento

> Snapshot real das tools MCP (consultadas em 2026-11-17, ao fechar o PR #11092).

### cycles-active
```
Nenhum cycle ATIVO em COPI.
```

### my-work
```
Sem tasks ativas pra @wr23.
```

### sessions-recent limit:3
```
- session-2026-08-22-arte-agentes-ia-ui-guardrails (indexed 2026-11-17)
- session-2026-08-22-arte-escala-centenas-de-telas (indexed 2026-11-17)
- session-2026-08-22-arte-fidelidade-prototipo-producao (indexed 2026-11-17)
```

### decisions-search "nightly full-suite CT100 testes"
```
0358-doutrina-de-teste-tenant-98-supersede-0101
0410-ratificacao-zero-baseline-no-funil-design
0235-staging-ct100-clone-anonimizado
(nenhuma ADR nova sobre a nightly parada — o RUNBOOK-ct100-fullsuite segue sendo a referência)
```
