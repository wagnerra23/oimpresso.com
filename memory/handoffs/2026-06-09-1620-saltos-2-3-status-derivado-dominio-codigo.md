---
date: "2026-06-09"
hour: "16:20 BRT"
slug: saltos-2-3-status-derivado-dominio-codigo
topic: "Passe dedicado dos saltos #2 e #3 da governança executável (ADR 0264): dominio:check além do enum (cobertura de código) + casos:check Status derivado do veredito real do teste"
tldr: "Retomei o passe pausado no handoff 13:57 (opção B). Wagner escolheu #3 primeiro (tratável), depois 'merge + já ataco o #2' (que precisa de desenho). 2 PRs merged --admin. #3 (#2471): dominio:check passou a varrer o CÓDIGO (where/whereIn/comparação-de-campo/validação in:) nos módulos com dicionário — pegou 11 ramificações vivas de order_type==='locacao' (dead-code pós-erradicação do enum) absorvidas no baseline. #2 (#2472, F1): aprovei blueprint com Wagner antes de codar — G-7 deriva o Status:✅ por UC do veredito REAL do teste via manifesto JUnit commitado (gate offline, evita deadlock ADR 0261); pegou 8 UCs ✅ da Oficina sem prova (status:unverified). Os 3 saltos do loop agora fechados (#1 #2469 · #3 · #2). Limpeza do dead-code locacao corre em sessão spawn própria."
duration: "~3h"
authors: [CL, W]
session: frosty-greider-83ab2f
---

# Saltos #2 e #3 da governança executável → main (2 PRs)

> Retomada do passe pausado (handoff 13:57 opção B). Wagner via AskUserQuestion: **#3 primeiro** ("tratável") → **"Merge + já ataco o #2"** → blueprint #2 **aprovado** → **"Aprovado — implementa F1"**. Off-cycle (CYCLE-08 = receita; isto é governança/infra, drift conhecido e autorizado).

## Estado MCP no momento

- **CYCLE-08 Receita Onda A** (32% decorrido, 19d). Goals: pricing público, 5 migrações-demo carteira, MRR 2k, ComVis V1, Agrosys de-riscado. Estes 2 PRs são **OFF-cycle** (maturação dos gates ADR 0264).
- `my-work`: 30 tasks. Nenhuma US ativa tocada (trabalho de governança puro). Relevante adjacente: US-OFICINA-026 (outreach Martinho) — a Oficina é o módulo semente do dicionário.

## O que aconteceu

**Salto #3 — domínio ALÉM do enum (PR #2471, `8b3321eaa`).** O G-4 (`dominio:check`) parava no `enum()` de migration. Mas erradicar o enum não mata a alucinação: ela sobrevive como **código morto** que ramifica num valor extinto. Estendi `domain-dict-guard.mjs` pra varrer o **código de aplicação** (nos módulos com dicionário; exclui `Database/` e `Tests/`): `where`/`whereIn`, comparação de campo `$x->col === 'v'`, validação Laravel `in:`. Nova chave de ratchet `dominio:undeclared-code-value:<mod>:<col>:<v>`. **Pegou 11 ocorrências reais** de `order_type === 'locacao'` (ServiceOrder, Vehicle, ServiceOrderController, AprovacaoOsController, ServiceOrderObserver) — o follow-up de dead-code que o handoff 13:57 só documentava. Mordi e travei 1 FP (operador `like` lido como valor: 3º arg em aspas duplas caía no fallback 2-arg → regex agnóstico a aspas + guarda de operador-palavra).

**Salto #2 — Status derivado do verde (PR #2472, `a568a2abc`, F1).** O G-5 trava a **presença** do `Status:` por UC, mas o valor (✅/🧪/⬜/❌) é declarado por humano e **pode mentir**. Desenhei (blueprint aprovado por Wagner ANTES de codar) a arquitetura **manifesto commitado + gate offline**: runners → reporter JUnit → `test-results/` → `casos:results` (coletor) → `scripts/casos-test-results.json` → **G-7** lê offline e cruza com o Status. Separar produção (lenta) de checagem (rápida) evita o deadlock que o ADR 0261 probe (o `casos-gate` é ratchet rápido required). Violações: `status:lies` (✅ vs teste falhou), `status:unverified` (✅ sem prova), `status:stale-results` (✅ provado, tela mudou depois via git). Só ✅ exige prova; 🧪/⬜/❌ são não-afirmações honestas. **F1 não-bloqueante**: baseline absorveu **8 UCs ✅ da Oficina** como `status:unverified` (declarados verde, zero prova automatizada). UC-06 (🧪) e UC-08 (⬜) corretamente fora. Achado que encaixou: os testes **já põem o UC-id no título** (`test('UC-06 · …')`), então o coletor extrai o veredito do `name` do `<testcase>` sem regex de fonte.

**Reconciliação com o gate Append-only:** apus uma linha no Histórico do ADR 0264 e o gate `Append-only canon` bloqueou (ADR ratificada = append-only no nível de arquivo, Constituição Art. 3 + ADR 0095). Revertido — doc do Salto #3 ficou no header do script + dict `oficina-auto.md` (não-ADR) + corpo do PR. **Nenhum dos saltos precisou de ADR novo**: implementam a intenção do G-4/G-5 que o 0264 já ratificou.

## Artefatos gerados

- **#3:** `scripts/domain-dict-guard.mjs` (+code-scan ~110 linhas) · `scripts/domain-dict-baseline.json` (+1 chave) · `tests/dominioGuard.spec.ts` (+10 meta-testes) · `memory/dominio/oficina-auto.md` (seção cobertura de código) · `.github/workflows/dominio-gate.yml` (comentário/diagnóstico).
- **#2:** `scripts/casos-results-collect.mjs` (coletor, novo ~165 linhas) · `scripts/casos-test-results.json` (manifesto seed vazio) · `scripts/casos-coverage-guard.mjs` (+G-7 ~78 linhas) · `scripts/casos-coverage-baseline.json` (+8) · `tests/casosGuard.spec.ts` (+10 G-7) · `tests/casosResultsCollect.spec.ts` (novo, 7 do coletor) · `playwright.config.ts` (reporter JUnit) · `package.json` (`casos:results`) · `.github/workflows/{casos-gate,casos-meta-gate,e2e-gate}.yml` · `.gitignore` (test-results/).
- **Total:** +27 meta-testes físicos (caixa-preta). CI 100% verde em ambos.

## Persistência

- **git:** 2 PRs squash-merged --admin no main (#2471 → `8b3321eaa`, #2472 → `a568a2abc`). Branches + worktrees limpas.
- **MCP:** webhook propaga em ~2min.
- **BRIEFING:** OficinaAuto não atualizado (gate de processo mudou, capacidade do módulo não — skip consciente).

## Próximos passos pra retomar

```
# Dead-code locacao (#3 ratchet → 0): em sessão spawn própria (Wagner clicou o chip).
#   ao mergear: confirmar que dominio:undeclared-code-value:OficinaAuto:order_type:locacao SAIU do baseline.
# #2 F2: flip status:lies pra required (gh api required_status_checks) após Wagner ver a dívida.
# #2 F3: ratchet status:unverified → 0 conforme e2e-gate rodar verde + npm run casos:results (recoleta manifesto).
# Pendente [W] do handoff 13:57: enforce_admins nos gates casos/dominio (já required, admin ainda fura).
```

## Lições catalogadas

- **Review cruza com a fonte, não lê bonito:** o code-scan do #3 só valeu porque rodei `--report` ANTES do baseline e inspecionei cada ocorrência — foi assim que o FP do `like` apareceu e foi travado por meta-teste de regressão.
- **Append-only é a nível de ARQUIVO:** nem apêndice no Histórico de ADR ratificada passa. Doc de extensão vai pro script/dict/PR, não no ADR.
- **Gate git-dependente + gate rápido required = separar produção de checagem:** o G-7 lê manifesto commitado offline em vez de rodar a suíte no gate required (senão deadlock ADR 0261). Mesma lição do #1 (frescor via git, não wall-clock).
- **Desenhar antes de codar quando a infra pesa:** o #2 ("desenha o") rendeu blueprint com trade-off de 3 arquiteturas (manifesto-offline vs needs+artifact vs runner-stamp) — Wagner cravou antes de 1 linha de código.

## Pointers detalhados (on-demand)

- `scripts/domain-dict-guard.mjs` header (seção "SALTO #3") + `scripts/casos-coverage-guard.mjs` header (G-7).
- PRs #2471 / #2472 (corpo tem a arquitetura + faseamento F1/F2/F3 completos).
- Handoff-mãe: `2026-06-09-1357-governanca-executavel-erradicacao-locacao.md` (origem dos 3 saltos).
