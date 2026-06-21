---
date: "2026-06-13"
hour: "19:23 BRT"
topic: "Auditoria adversarial SDD F2b + limpeza do backlog US-GOV-019 + descoberta da corrupção de redação Tier-0 nos testes (13 PRs)"
duration: "~3h"
authors: [W, C]
---

# SDD F2b — audit adversarial + backlog US-GOV-019 + corrupção de redação

## Estado MCP no momento
- **Cycle CYCLE-08** (Receita Onda A) · 15d restantes · trabalho desta sessão é **off-cycle** (governança/SDD, não goals de receita) — drift conhecido.
- **my-work**: 30 tasks (4 review, 6 blocked-dormente NFE Gold, 20 todo). US-GOV-019/020 vivem no SPEC Governance, não saíram como task individual ativa.
- Sessão paralela `Parallel turbo stages` rodando no MESMO worktree (`frosty-greider-83ab2f`) — dona do working tree (cluster Whatsapp não-commitado) + frente de floor/harness.

## O que aconteceu
Arco em 4 atos disparado por "pode continuar em paralelo?" → "o que falta do SDD?" → "põe um adversário" → "segue":
1. **Fila de merge zerada**: #2646/47/49/52 (quick-wins US-GOV-018 re-triage) mergeados. #2657 (Frente C) confirmado CLOSED de propósito.
2. **Auditoria adversarial (4 agents paralelos read-only)**: veredito dos fixes mergeados (5 reais sólidos, #2652 parcial por blind spot do guard, harness parcial) + mapa do que falta + #2664 resolvido + floor caracterizado.
3. **Descoberta sistêmica**: investigando por que `Pest Vestuario` falhava, achamos que um processo de **redação Tier-0 corrompeu assertions de teste** (trocou `R$ 89,90` pelo token `[redacted Tier 0]` DENTRO do `.php`). Mecanismo: `fd96258ae` restaurou 14.969 arquivos de um snapshot JÁ redactado → re-propagou. **Raio real pequeno**: ~115 ocorrências mas maioria cosmética; só ~3 arquivos de corrupção real fora Vestuario. Floor impacto ~7 assertions → **confirma que o lever grande do floor são os ~19 corruptores era-sqlite**, não isto.
4. **Backlog US-GOV-019 disjunto limpo** + higiene Tier-0.

## Artefatos gerados (13 PRs)
- **Mergeados**: #2646/47/49/52 (quick-wins), #2664 (charters + fix guard vocab OficinaAuto), #2672 (CSAT DispatchCsatJob), #2673 (Vestuario DataController), #2674 (Vestuario corrupção+biz4→1), #2675 (Copiloto+RecurringBilling corrupção), #2677 (DESIGN.md link), #2679 (WithoutGlobalScopes 3 arquivos), #2680 (@test→#[Test] 8 arquivos + pii-allowlist), #2681 (BusinessIdGuard hardening).
- **Auto-merge armado**: #2678 (NFSe spanBiz cancelar + fix PHPStan `$extras` array).
- **Doc**: `memory/sessions/2026-06-13-auditoria-adversarial-sdd-f2b-floor.md` (auditoria completa, vereditos, plano, achados).

## Persistência
- git: este handoff + índice + session-doc commitados (só MEUS 3 arquivos — working tree da sessão paralela intacto).
- MCP: webhook GitHub→MCP propaga em ~2min após push.
- SPEC Governance: **NÃO atualizado** (checkbox bug #1 US-GOV-019 segue `[ ]` apesar de #2648; SPEC US-GOV-020 stale dizendo floor 1870/resolvido).

## Próximos passos pra retomar
1. **Floor < 1928** (lever real = ~19 corruptores era-sqlite) — **coordenar com a sessão `Parallel turbo stages`** (colisão harness/Whatsapp) antes de pegar o piloto.
2. **US-GOV-020 (p0)**: re-landar branch `sdd/fc-trigger-definer-privilege` (grants Frente C + **revert A.2 net-harmful que segue ATIVO no main**) vs aceitar grants como provisionamento. Atualizar SPEC stale.
3. **WithoutGlobalScopes — ~89 violações irmãs** (PaymentGateway/OficinaAuto/KB/NfeBrasil/Superadmin/ComVis) — guard nem está plugado no CI ainda.
4. **91 quarentena** (mecanismo pronto, 0 aplicados) + **Lane D** (bloqueada até re-run limpo).
5. **NumUf unclear #11**: 2 valores de dataset ambíguos — git archaeology funda OU quarentena.

## Lições catalogadas
- **Redação Tier-0 over-broad corrompe SOURCE de teste**: o redator de valores monetários, ao rodar sobre fixtures, troca preços reais por `[redacted Tier 0]` no `.php` → ExpectationFailed garantida. Vetor de reintrodução = restore de snapshot redactado (`fd96258ae`). Defesa: redator deve allowlistar paths de teste OU não tocar literais de fixture.
- **Agent isolation:worktree com path absoluto `D:\` VAZA pro working tree principal** (CSAT agent editou InboxController no tree compartilhado, percebeu, reverteu via cherry-pick + reset). Verificar SEMPRE git status pós-agent quando há sessão paralela no mesmo worktree.
- **Guards Tier-0 "armados mas não plugados"**: `WithoutGlobalScopesCommentGuardTest` e `BusinessIdGuardTest` não rodam em nenhum workflow CI → passam "verde" por não existirem no gate. Endurecer guard ≠ enforcement.
- **#2673 mergeou via override admin com `Pest Vestuario` vermelho** — o swarm usa admin-merge; `Pest Vestuario` é check não-required (advisory) por isso passou. #2674 limpou o vermelho.
- **PHPStan ratchet**: erro `int<0,max> given` (Eloquent magic prop) não casa regex baselined `int given` → vira "regressão". Fix = passar array correto pro `$extras`, não regenerar baseline.

## Pointers detalhados
- Auditoria completa + vereditos + plano: `memory/sessions/2026-06-13-auditoria-adversarial-sdd-f2b-floor.md`
- Plano-mãe SDD F2b: `memory/sessions/2026-06-13-prompts-burndown-f2b-pos-triage.md` (Lanes 0/A/B/C/D)
- Floor handoff: `memory/handoffs/2026-06-13-1730-sdd-floor-frente-c-era-sqlite.md`
- US-GOV-019/020: `memory/requisitos/Governance/SPEC.md`
<!-- schema-allowlist: salvo de feat/governance-ds-rollout-ledger (branch shallow-orfanada 2026-06-20); output de subagente/legacy, schema estrito de secao nao se aplica -->
