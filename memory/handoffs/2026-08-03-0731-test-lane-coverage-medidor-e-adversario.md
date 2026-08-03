---
date: "2026-08-03"
time: "07:31 BRT"
slug: test-lane-coverage-medidor-e-adversario
tldr: "Alvo declarado (2 suites Tests/Unit fora do CI) estava VAZIO — só .gitkeep. O buraco real era outro e agora tem medidor: 957 testes (67,3%) fora das lanes de PR, derivado em vez de garimpado à mão em 117 workflows. 4 PRs mergeados. Adversário derrubou a tela proposta e 2 premissas minhas."
prs: [5202, 5203, 5208, 5213]
decided_by: [W]
related_adrs: [0264-governanca-executavel-trio-dominio-e2e, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes]
next_steps:
  - "Com corpus acumulado das 14 lanes, medir FP do junit-summary --check-assertions antes de armar (passo 2 do Gate: da LC-13)"
  - "Decidir se os ~200 verdes-com-0-assertions são FP ou achado — exige o corpus"
  - "Mover a exclusão informal '# NAO ligar ainda' do IndexarMemoryGitParaDbTest para quarentena formal"
---

# Handoff — medidor teste×lane + revisão adversarial

## TL;DR

O alvo declarado da investigação estava **vazio** (só `.gitkeep`), mas o buraco real era maior e agora tem máquina: **957 testes (67,3%) fora das lanes de PR**, derivado em vez de garimpado à mão em 117 workflows. 4 PRs mergeados. Um adversário read-only matou a tela que eu ia construir e derrubou 2 premissas que eu tinha afirmado ao [W]. O medidor achou um caso novo no 1º dia — que virou dívida de fixture exposta.

## Estado MCP no momento do fechamento

- `cycles-active`: **nenhum cycle ATIVO** em COPI
- `my-work`: 8 tasks, **todas em REVIEW** (US-COPI-123 p0 · US-TR-309/310/305/306 · US-PG-008 · US-PROD-027 · US-INFRA-023)
- Handoffs irmãos de agosto: 3 (último `2026-08-02-2100-b7-cobertura-travas-de-prova.md`)
- `decisions-search "teste lane CI cobertura"`: 0264 (trio+gates), 0261 (enforcement faseado), 0298 (teto anti-proliferação)

## O que aconteceu

**A tarefa pedida não existia.** Investigar `NfeBrasil/Tests/Unit` e `RecurringBilling/Tests/Unit` "fora do CI" revelou que ambos contêm **só `.gitkeep` de 0 bytes** (scaffold de 2026-04-24) — zero testes, logo zero falsa cobertura. A contagem que os apontava somava `.gitkeep` como teste (inflava também o Financeiro: 4 `.php`, não 5).

**O buraco real era outro:** 5 arquivos de `Modules/Jana/Tests/Unit` sem lane de PR, incluindo `PiiRedactorTest` (19 casos, LGPD) — enquanto o [#5169](https://github.com/wagnerra23/oimpresso.com/pull/5169) alterava `PiiRedactor.php` em +98 linhas e ligava só o teste do caso novo. Agravante: `PiiRedactor.php` **já constava no `paths:`**, então a lane disparava e não rodava o teste dele.

**Achado de mecanismo, vale pro repo inteiro:** nenhuma das 27 invocações de teste nos 117 workflows usa `--testsuite`. Registrar em `phpunit.xml` **não alcança o CI**. A proibição canônica *"não criar `Modules/X/Tests/` sem registrar em phpunit.xml"* é **necessária e insuficiente** — quem decide execução é a lista da lane.

**Revisão adversarial (3 agentes read-only, mandato de refutar medindo):**

| Alvo | Veredito |
|---|---|
| o número | 967 → **957 (67,3%)**; 2 defeitos reais meus, corrigidos |
| o valor | merece, em escopo **4× menor** — 45,8% são dívida vermelha ou verde-vazio, não cobertura perdida |
| a tela | **NÃO CONSTRUIR** — charter escrito e revertido no mesmo branch |

**Duas premissas minhas caíram, ambas ditas ao [W]:** (a) *"merge em main não é deploy automático"* — falso, `deploy.yml` dispara em `push: main` → SSH Hostinger; (b) *"é N=1, two-strikes manda consertar sem codificar"* — errado, LC-13 está em **8 ocorrências**.

**A tela morreu por premissa falsa minha:** afirmei que "o Governance tem sidebar com 5 itens". Não tem — `modifyAdminMenu()` tem `return;` **incondicional** (`DataController.php:61`) e os itens que li estão em bloco `↓ DEAD CODE`; [W] removeu a entry em 2026-05-25. Li a lista sem verificar se o código é alcançável — a mesma classe (artefato que existe e não executa) que este trabalho consertava.

## Artefatos gerados

| PR | Conteúdo | Estado |
|---|---|---|
| [#5202](https://github.com/wagnerra23/oimpresso.com/pull/5202) | `junit-summary` em 12 lanes (2→14) — corpus da LC-13 | merged |
| [#5203](https://github.com/wagnerra23/oimpresso.com/pull/5203) | `scripts/governance/test-lane-coverage.mjs` (~500 ln) + modo `--diff` forward-only | merged |
| [#5208](https://github.com/wagnerra23/oimpresso.com/pull/5208) | 5 `Tests/Unit` da Jana na lane (alvos 5→11) | merged |
| [#5213](https://github.com/wagnerra23/oimpresso.com/pull/5213) | 3 órfãos do `IndexarMemoryGitParaDb` (chip spawnado) | merged |

## Persistência

- **git**: 4 PRs em `main`; ledger `memory/LICOES_CODE.md` com adendo à LC-13 (contador 8, mesclado com edição paralela de outra sessão)
- **MCP**: sem task nova criada (trabalho nasceu de investigação direta, não de US)
- **BRIEFING**: não aplicável — mudança é de CI/governança, não de capacidade de módulo

## Próximos passos pra retomar

```bash
node scripts/governance/test-lane-coverage.mjs            # retrato atual
node scripts/governance/test-lane-coverage.mjs --diff origin/main   # forward-only do PR
```

O passo 2 do plano (medir FP do `--check-assertions`) depende do corpus que as 14 lanes começaram a produzir — **não armar antes de medir**.

## Lições catalogadas

- **O medidor achou caso novo no 1º dia contra código vivo:** `IndexarMemoryGitParaDbTest` (7 casos, classe alterada pelo #5193, zero lanes). Ao ser ligado deu **7 ERRORS** — fixture 3 migrations atrás do schema. Vermelho = dívida aparecendo, confirmado na prática.
- **"Fora do CI de PR" ≠ "nunca roda"** — a nightly CT100 roda `--roots tests,Modules`. Corrigi a semântica do script antes do commit; a 1ª versão ia mentir.
- **Errei 3× lendo comentário como conteúdo** (`grep -c` contando `# NAO ligar ainda`, entre outros) — mesma classe da lápide §5 2026-07-28.
- **Resolução de conflito é ADITIVA** quando os dois lados adicionam teste: 3 conflitos nesta sessão (ledger 2×, lane 1×), nenhum lado descartado.
- **4 hooks me barraram e todos estavam certos** (teste-fora-CT100, force-push, `reset --hard`, `rm`) — nenhum escape usado.

## Pointers detalhados

- Classe de defeito: `memory/LICOES_CODE.md` LC-13 (campo `Gate:` tem o plano de 3 passos)
- Limites do medidor: cabeçalho de `scripts/governance/test-lane-coverage.mjs` (parsing textual, não AST)
- Lápides que mataram a tela: `memory/proibicoes.md` §5 2026-07-25 / 07-23 / 07-27 / 07-17
