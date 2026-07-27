---
date: "2026-07-27"
time: "17:55 BRT"
slug: uc-orfaos-forja-impostos
tldr: "UC órfãos do casos-gate 28→22 (débito 220→214) em 2 PRs mergeadas. Nenhum fechado escrevendo teste novo — 3 por citar teste que JÁ provava, 3 por não serem contrato. 75% do resto está travado num módulo sem lane de CI."
prs: [4879, 4882]
decided_by: [W]
related_adrs: [0264-governanca-executavel-trio-dominio-e2e]
next_steps:
  - "Destravar Modules/TeamMcp (26 arquivos de teste, 7 rodam em CI) — libera 21 dos 22 órfãos restantes"
  - "Provisionar schema UltimatePOS no oimpresso-staging OU corrigir a doc que promete o que não existe"
  - "NÃO fechar UC-KBV2-09 — ele documenta por que não tem teste próprio"
---

# Handoff — UC órfãos: Forja + Impostos

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → 10 tasks, todas em REVIEW (US-TR-305..311, US-PG-008, US-PROD-025/027, US-FIN-023) — **nenhuma relacionada** a este trabalho
- `decisions-search "casos de uso órfãos rastreabilidade UC teste G-2"` → 0282, 0187, 0293, 0239 (nenhuma nova sobre o tema; a mãe é a **0264**)
- Handoffs irmãos de hoje: `0905-sdd-produto-fechado`, `1135-produto-3-achados-tier0`, `1445-orfaos-ligados-elo-hitl`
- `main` em `77230897f8`

**Nenhuma task MCP criada** — deliberado. O resto do débito é rastreado pela **catraca** (`scripts/casos-coverage-baseline.json` = 214) e por 3 chips de sessão. Abrir US duplicaria um número que a máquina já deriva, no artefato que a precedência trata como o elo mais fraco.

## O que aconteceu

Ponto de partida medido: `orphan_ucs: 28` de 181 UC declarados (G-2, ADR 0264) — passando por grandfather no baseline F1 (`ok: true` = "não regrediu", não "limpo").

A triagem foi o trabalho, não a escrita de testes. **Nenhum dos 6 fechados virou teste novo:**

| Caminho | UC | Por quê |
|---|---|---|
| Citar teste que JÁ provava | FORJA-12, FORJA-13, IMP-05 | o teste cobria o critério; faltava o id no **título**, de onde o `casos-results-collect` extrai o veredito |
| Rebaixar (não é contrato) | FORJA-06, FORJA-11 | critério era "gate X verde" (régua paralela) e "nota de fidelidade" |
| Remover (não existe mais) | FORJA-04 | ficou **infalsificável** |

O UC-FORJA-04 exigia que o topnav fosse "o da Forja, **não o da Equipe**". `Modules/TeamMcp/Resources/menus/` não existe mais — na fusão de 2026-06-16 o topnav do TeamMcp foi deletado e o grupo `'Forja'` virou o único que casa `/team-mcp/*`. A colisão morreu por deleção, não pelo guard: um UC que só pode passar não defende nada.

**Quase escrevi duplicata.** No Impostos cheguei a criar dois GUARDs (discriminação com/sem NF por delta + teto de 5) antes de achar o `UC-IMP-10`, que nasceu em 2026-07-06 justamente pra "refinar UC-IMP-05", já provado pelo `ImpostosContractTest C3` — e melhor que a minha versão (assere o item pelo `numero`, com controle negativo). Descartados antes do commit; o conserto virou 1 linha.

Os 3 seguem `🧪`, não `✅`: o ✅ vem do manifesto derivado do JUnit do CI. Escrever à mão seria o campo auto-declarado que o §5 rejeita.

## Achados (o que a triagem desenterrou)

1. **`ForjaRoutesSmokeTest` nunca rodou uma vez.** Não está em lane nenhuma; rodado à mão no CT 100 deu **7 failed / 5 passed** — `Route [forja.saude] not defined` + 6× `DatasetArgumentsMismatch` (dataset associativo entrega 1 arg, o `it()` declara 2). O happy-path jamais executou.
2. **Fatos stale no charter/casos.** `route:list` provou **5** rotas (não 6) e **9** itens de topnav; a permissão virou `jana.mcp.usage.all` no #4853 e sobrevive como `copiloto.*` em **42 arquivos** — `git grep -l ... -- '*.php'` = **0**.
3. **O CT 100 não valida teste MySQL.** `oimpresso-staging` tem **15 tabelas** (`copiloto_*`/`vestuario_*`), sem schema UltimatePOS; `Business::first()` estoura. Tudo que exige banco real **pula em silêncio** — e skip parece verde. Contradiz o que `proibicoes.md` promete.

## Números

| | orphan_ucs | ucs_declared | débito |
|---|---:|---:|---:|
| início | 28 | 181 | 220 |
| **`main` agora** | **22** | 188 | **214** |

Baseline regravado só para baixo nas duas; `--check-baseline-shrink` verde (−5 e −1). `exec_backed` 32 → 35 no primeiro `casos-results-publish` (os 3 UC estão em lanes colhidas).

## Por que parou em 22

**21 dos 22 restantes são do `Modules/TeamMcp`** — que tem **26 arquivos de teste e 7 rodando em CI**. Não está no matrix do `modules-pest`, que nem emite JUnit. Escrever testes lá agora produziria testes **mudos**: fechar o órfão no papel sem defesa real, o padrão presence-gate que o ledger registra como LC-11.

## Lições catalogadas

- **`--theirs` num rebase é invertido** — devolveu a MINHA versão do baseline, não a do `main`. Peguei ao ver 219 onde o #4879 tinha gravado 215. O certo é `git checkout origin/main -- <arquivo>`.
- **Conflito em arquivo derivado não se resolve escolhendo lado** — regenerar (`--write-baseline`) sobre o estado real da árvore.
- **`block-destructive` barrou `--force-with-lease` e `reset --hard`, e estava certo** — refiz por merge a partir do estado remoto, que aceita push normal.
- **Tocar legado acorda gate diff-aware** (§5 2026-07-12, ao vivo): corrigir o charter da Forja acordou o `charter-us-lint`, que dormia por grandfather. Deixei o advisory vermelho em vez de inventar `related_us` — **nenhuma US cobre essa tela** (SPEC do TeamMcp tem US-TEAM-001..007, todas sobre token/actor/audit). Âncora fabricada parece canon.

## Pointers

- PRs [#4879](https://github.com/wagnerra23/oimpresso.com/pull/4879) · [#4882](https://github.com/wagnerra23/oimpresso.com/pull/4882) (bodies têm a triagem completa e os recibos)
- [Cockpit.casos.md](../../resources/js/Pages/team-mcp/Forja/Cockpit.casos.md) §Conformance · §Comportamento que deixou de existir · §Dívida conhecida
- Comentário do advisory: [#4879 issuecomment-5095472024](https://github.com/wagnerra23/oimpresso.com/pull/4879#issuecomment-5095472024)
- Régua viva: `node scripts/casos-coverage-guard.mjs --json`
