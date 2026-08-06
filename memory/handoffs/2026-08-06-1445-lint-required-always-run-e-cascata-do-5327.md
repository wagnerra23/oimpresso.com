---
id: handoff-2026-08-06-1445-lint-required-always-run
data: "2026-08-06"
autor: "[CC]"
tipo: handoff
status: ativo
---

# Handoff — lint anti-deadlock + a cascata de regressões do #5327

## O que fechou

**[#5332](https://github.com/wagnerra23/oimpresso.com/pull/5332) — MERGEADO.** `scripts/governance/required-always-run.mjs`: para cada context required do `required-checks-baseline.json`, resolve o workflow/job produtor (`name:` literal → job id → expansão de `matrix`) e reprova se o gatilho `pull_request` estiver filtrado por `paths:`/`paths-ignore:`.

O buraco que fecha: context required com workflow path-filtered **não fica vermelho — não nasce**. O PR trava em `Expected — waiting for status`, e como a trava é do *branch*, pega o repo inteiro. Precedente: 2026-07-02, `main` BLOCKED com 54/54 verdes. O `protection-drift` confere nome/baseline/mojibake e **não** confere alcançabilidade.

- FP medido ANTES: **40 contexts · 40 always-run · 0 filtrados · 0 não-resolvidos**.
- **Bite no incidente real:** a branch do #5069 → **2 filtrados, exit 1**.
- Advisory (ADR 0275), `promote_by: 2026-09-05`, `terminal` + `anchor` no registry (ADR 0298). Sem `paths:` de propósito.

**[#5335](https://github.com/wagnerra23/oimpresso.com/pull/5335)** — US-REPA-002 (3 testes do `Wave18RepairSaturationTest` com `base_path()` fora do bootstrap). 83 pass, 0 fail no último retrato.

## O que ficou aberto — e é a parte que importa pro próximo

**[#5327](https://github.com/wagnerra23/oimpresso.com/pull/5327)** (rebase do #5069) segue **BLOCKED** por GT-G5. Duas refutações independentes reprovaram:

| rodada | veredito | erros |
|---|---|---|
| R3 | reprovado | 2/97 (2,06%) — E1 e E2, **consertados** ([`7d78340bbee`](https://github.com/wagnerra23/oimpresso.com/pull/5327)) |
| R4 | reprovado | 3/97 (3,09%) — E3, E4, E5, **pendentes** |

### Pendente (conserto definido no parecer da R4, §6 não permite que o refutador aplique)

- **E3 (grave).** `module-surface.yml` L69 põe `system-map --check` **bloqueante** dentro do context REQUIRED, sem `continue-on-error` — enquanto o dono (`system-map.yml`) o mantém advisory de propósito (*"stale = avisa, não bloqueia"*). Bite-test reproduzido por mim: base regenerada `exit 0` → **um session log alheio** `exit 1` → removido `exit 0`. O verde é snapshot; trava no primeiro session log que outra sessão mergear. Conserto: `continue-on-error: true` **ou** mover pra job próprio fora do required. Promover de verdade exige ADR + janela + flip [W].
- **E4.** `Modules/Financeiro/SCOPE.md` aponta `not_contains` pra `Modules/ProjectMgmt` e `Modules/TeamMcp` — nenhum existe (renomeado pra `Forja`). Conserto: restaurar os 2 ponteiros.
- **E5 (menor).** Mesmo arquivo: `purpose` truncado + `migracao_ui` removido. Conserto: restaurar, **preservando** o `depends_on: [Sells, Compras]` novo (legítimo).

**Rodada 5 exige sessão fresca**, independente de R1–R4, contra head PINADO.

## A classe de defeito que dominou a sessão

O lote do #5069 foi gerado de **base velha**, e o git honra as "deleções" resultantes **sem marcar conflito**. Apareceu em 3 arquivos e nenhum deu conflito:

- `governance-script-tests.yml` — 16 steps do main sumiram (9/9 tokens medidos), incl. rebaixar `feature-lint --check` → `feature-lint` (sem a flag **nunca** sai exit≠0: gate de teatro que o main matou no #5275).
- `module-surface.yml` — revertia o header da ADR 0370 e afirmava "advisory" em presente sobre job required (**LC-10**). O comportamento estava certo; quem regredia era a doc — justamente a que impede repor o `paths:`.
- `system-map.mjs` — perdeu o export `semComentarioHtml` + o frontmatter do rail `/documentacao` (sem o `id`, o item **404**).

**Nem merge nem CI sinalizam.** Só aparece rodando o que foi apagado, ou comparando com o main (`git diff --numstat origin/main HEAD`, procurando deleções grandes).

Restam **4 arquivos** com deleção grande não-verificados: `GenerateModuleSpecsCommand.php` (173), `module-surface.mjs` (103), `documentation-loop.mjs` (64), `nfebrasil.php` (21). Podem ser reescrita intencional — o `documentation-loop` tem 389 adições contra 64 deleções, o que sugere trabalho real. **Verificar item a item, não presumir.**

## Erros meus, registrados

- **LC-08 ×2.** (a) Rodei `--raiz <sandbox>` — flag inexistente — e o script mediu o cwd errado e saiu **0**; quase publiquei "o #5069 não tem o problema" tendo auditado a árvore errada. Fix: flag desconhecida agora aborta com exit 2. (b) Li três `exit code 1` no log do Governance Gate e conclui "3 causas", sem ver que dois steps tinham `continue-on-error: true`. Só o `ledger-check` derruba. **Classificar por STEP, nunca por texto de log.**
- **LC-13.** Selftest do lint deu **12/12 com o instrumento cego**: fixtures 100% LF, workflows do repo em CRLF, e o `$` do JS em `/m` não casa antes de `\r\n`. Lápide em `proibicoes.md` §5. Junto: **`\Z` não existe em JS** (vira literal "Z"; a âncora é `$(?![\s\S])`).
- **E3 é meu.** Ao restaurar o `module-surface.yml`, chamei o `system-map --check` de "adição legítima" porque estava no diff do lote — sem medir que promove política de um check advisory.

## Achado estrutural consertado no caminho

`requisitos-status.mjs` **nunca emitia `authority: generated`** — campo que o `distiller_freshness` usa pra ignorar docs gerados. Sem ele, regenerar um índice derivado marcava a BRIEFING do módulo como stale por falso e **avermelhava um gate required**. Armadilha armada pro próximo. Consertado no gerador (não no baseline), bite-test `1 → 0`.

## Lição operacional que repetiu 3×

**Derivado que lê `git log` (`gitLastDate`, `distiller_freshness`, contagem de handoffs) se regenera DEPOIS do commit do merge, nunca durante a resolução do conflito.** Medir antes do commit dá o número de um histórico que ainda não existe. Custou um ciclo de CI cada vez.

## Estado MCP no momento do fechamento

- `cycles-active`: **nenhum cycle ATIVO em COPI**.
- `my-work`: 30 tasks — 1 DOING (US-INFRA-048), 13 REVIEW, 10 BLOCKED, 6 TODO.
- Handoffs anteriores: `2026-08-05-1835-hooks-condicionais-observaveis.md`.

## Próxima ação

1. Aplicar E3/E4/E5 no #5327 (a sessão da R4 estava ativa na branch até o fim desta sessão — **conferir `isRunning` antes de tocar**).
2. Disparar rodada 5 em sessão fresca contra head pinado.
3. Varrer os 4 arquivos com deleção grande restantes.
