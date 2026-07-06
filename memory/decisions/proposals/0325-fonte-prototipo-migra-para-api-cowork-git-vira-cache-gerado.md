---
slug: 0325-fonte-prototipo-migra-para-api-cowork-git-vira-cache-gerado
number: 325
title: "Fonte do protótipo migra pra API Cowork (claude.ai/design): git deixa de ser fonte e vira cache GERADO (read-only), não cópia hand-editada"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-06"
module: design-system
tags: [design, governanca, cowork, design-sync, fonte-da-verdade, espelho, cache-gerado, tier-0, migracao]
supersedes: []
superseded_by: []
supersedes_partially:
  - 0299-figma-nao-e-fonte-de-design
  - 0315-design-sync-claude-design-vs-cowork-charter
related:
  - 0239-governanca-design-system-git-ssot-regressao-ia
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0324-sentinela-frescor-espelho-cowork-designsync-read
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
pii: false
---

> **Proposta por [CL] (Claude Code) em 2026-07-06.** Ratificação = merge por [W].
> **Gatilho (Wagner, verbatim):** *"vai apagar todas as copias dos prototipos e deixar apenas a do link da api nova"* + *"tem muitos conflitos de várias tentativas de várias decisões erradas… apagar os lixos antigos"*.
> **Reversão parcial (Tier 0):** inverte a claim "claude.ai/design NÃO é fonte / o espelho git É a fonte" das ADRs [0299](../0299-figma-nao-e-fonte-de-design.md) §1 e [0315](../0315-design-sync-claude-design-vs-cowork-charter.md) Eixo A — **só pro protótipo de tela**. NÃO mexe no bloqueio do Figma (0299), no gate de ESCRITA do DesignSync (0315 Eixo B), nem no git-SSOT dos **tokens do Design System** ([0239](../0239-governanca-design-system-git-ssot-regressao-ia.md) segue intacta).

# ADR 0325 — A fonte do protótipo migra pra API Cowork; o git vira cache gerado

## Contexto (verificado em `origin/main`)

O `prototipo-ui/cowork/` é um **espelho hand-editado** do projeto vivo do Cowork (claude.ai/design). O dia 2026-07-06 mostrou que esse espelho é a **fábrica de deriva** do projeto: âncora apontando pro shell, âncora pra arquivo-fantasma que sumiu num refactor, "protótipo de bubble velha" (cópia do repo virou design antigo). Toda a camada de máquina de design existe pra **policiar a deriva do espelho**: `ancora.mjs` (proveniência), `anchor-content-check` (correção), `cowork-mirror-freshness` (frescor, [0324](0324-sentinela-frescor-espelho-cowork-designsync-read.md)), `cowork-ssot-guard` (arquitetura), `reconcile-triplet` (paridade). Cinco gates pra um problema que só existe **porque há uma cópia editável à mão**.

A Anthropic shippou a integração **DesignSync** (a "API nova"): dá pra LER o design vivo direto do Cowork (`list_files`/`get_file`). Wagner: *pare de guardar cópia; a fonte é a API.* A intenção é certa — **eliminar a classe de bug "espelho apodrece"**. Mas a forma literal ("apagar todas as cópias, viver só da API") tem um furo **provado**:

> **A API nova NÃO roda headless.** [ADR 0315 §Furos, tentativa 2](../0315-design-sync-claude-design-vs-cowork-charter.md): `DesignSync` exige `/design-login`, *"which requires an interactive terminal and is not available in this environment."* Sem cópia local, **CI, gates e qualquer agente sem login perdem o design inteiro** — e ainda se perde histórico git, diff, review em PR e leitura offline.

## Decisão

**A API Cowork (claude.ai/design) passa a ser a FONTE-DE-RECORD do protótipo de tela. O `prototipo-ui/cowork/` deixa de ser fonte e passa a ser um CACHE GERADO, read-only, nunca editado à mão.**

É o **mesmo padrão dos tokens do DS** ([0239](../0239-governanca-design-system-git-ssot-regressao-ia.md): `*.tokens.json` é fonte → CSS é saída gerada). O protótipo no git vira **saída**, não entrada:

1. **Fonte-de-record = projeto vivo no Cowork** (id do projeto + caminho da tela). É lá que o design nasce e muda.
2. **`prototipo-ui/cowork/` = cache gerado.** Regenerado por um **dispatch logado** (agente com `/design-login` roda `DesignSync.get_file` por arquivo → escreve o cache). Marcado `GENERATED — não editar à mão; fonte = projeto <id>`. Como é **saída**, a deriva "cópia hand-editada apodrece" fica **impossível por construção** (ninguém edita; sempre regenera).
3. **`cowork-mirror-freshness` ([0324](0324-sentinela-frescor-espelho-cowork-designsync-read.md)) vira o gate de integridade do cache** — `md5(cache) == md5(vivo)`. STALE = regenerar. (Antes eu propus ele como conferência; agora ele é a catraca que garante que o cache = fonte.)
4. **CI/offline continuam lendo o cache** (é git, versionado, diffável, review em PR). O login só é exigido no **dispatch de regeneração**, não em cada job.

### O que muda na prática

| Peça | Antes (espelho hand-editado) | Depois (cache gerado) |
|---|---|---|
| `related_prototype` no charter | caminho do arquivo no repo | **referência à fonte viva** (projeto + tela); o path do cache é derivado |
| `ancora.mjs` | resolve contra o repo | resolve contra a fonte viva; o cache é o material local |
| `cowork-ssot-guard` | "cowork/ é a fonte única, build-only" | "cowork/ é **cache gerado**; hand-edit é violação" (a regra vira anti-hand-edit) |
| `cowork-mirror-freshness` | conferência advisory | **gate de integridade do cache** (STALE ⇒ regenerar) |
| deriva de espelho | 5 gates policiando | **eliminada na origem** (cache não se edita) |

## Alternativas consideradas

- **Zero-cópia (o literal "apagar tudo, só a API")** — ❌ **rejeitado**: quebra headless (0315), perde histórico/diff/review/offline do design. A API sozinha é frágil de propósito onde hoje é robusto. A intenção (API = verdade) é atingida pelo cache gerado **sem** o custo.
- **Manter o espelho hand-editado (status quo)** — ❌ é a fábrica de deriva que o Wagner mandou matar.
- **Cache gerado (esta decisão)** — ✅ API é a verdade, git é saída; deriva morre por construção; CI/offline vivem.

## Plano de migração (faseado — NADA de `rm` de protótipo antes de ratificar)

1. **Ratificar esta ADR** + obter o **UUID pleno do projeto vivo** ("Oimpresso ERP Conunicação Visual", `019dcfd3…` — hoje não aparece em `list_projects` porque o filtro é "graváveis"; Gap 1 da [0324](0324-sentinela-frescor-espelho-cowork-designsync-read.md)).
2. **Dispatch de regeneração** (agente logado): `DesignSync.list_files` + `get_file` → reescreve `prototipo-ui/cowork/` a partir do vivo. Provado: o caminho de leitura já funciona (`get_file` devolveu `Button.jsx` real).
3. **Marcar o cache como gerado**: header/README `GENERATED — fonte = projeto <id>; regenerar via <comando>`. Adaptar `cowork-ssot-guard` pra proibir hand-edit (em vez de policiar "fonte única").
4. **`mirror-freshness` vira o gate de integridade** do cache (dispatch logado; advisory por [0314](0314-poda-gates-onda-2-lei-fusoes.md)).
5. **Reapontar `related_prototype`** dos charters pra referência viva (projeto+tela); ajustar `ancora.mjs`.
6. **Só então** podar máquina que virou redundante (parte do `reconcile-triplet`/`cowork-ssot-guard`) — via as ADRs de esquecimento/revisão ([0316](0316-esquecimento-real-adr-morta-tombstone-git-auditoria.md)/[0317](0317-maquina-revisao-adr-quando-rever-gatilhos.md)), não `rm` cego.

## Não-goals

- ❌ **Não apaga** as cópias de protótipo neste PR — a migração vem antes (fase 2-3). Deletar antes = design inacessível headless.
- ❌ **Não toca no git-SSOT dos tokens do DS** ([0239](../0239-governanca-design-system-git-ssot-regressao-ia.md)) — tokens/componentes seguem fonte-git.
- ❌ **Não reabre o Figma** ([0299](../0299-figma-nao-e-fonte-de-design.md) block segue) nem a **escrita** do DesignSync ([0315](../0315-design-sync-claude-design-vs-cowork-charter.md) Eixo B segue gateado) — só a LEITURA regenera o cache; `nuvem → git` deixa de ser proibido **apenas** pra regeneração automática do cache (não pra edição humana).
- ❌ **Não é zero-cópia** — o cache gerado permanece no git (é o que salva o headless).

## Gaps residuais honestos

1. **UUID do projeto vivo pendente** (Gap 1 da 0324) — sem ele, a regeneração não roda. Wagner fornece ou sessão logada lista.
2. **Regeneração depende de `/design-login`** — é dispatch periódico logado, não CI. Entre regenerações o cache pode ficar atrás do vivo; `mirror-freshness` sinaliza (não é instantâneo).
3. **Perde-se autoria humana no cache** — se alguém "ajustava" o espelho à mão hoje, esse fluxo acaba (tem que mudar no Cowork e regenerar). É o objetivo, mas é uma mudança de hábito.
4. **A referência viva no charter precisa de um formato estável** (projeto+tela) que sobreviva a rename no Cowork — a definir na fase 5.

## Consequências

✅ Elimina a classe "espelho apodrece" na origem (âncora-podre/fantasma/bubble-velha do dia 2026-07-06). ✅ A API vira a verdade (o que o Wagner pediu) sem perder headless/histórico/diff/review. ✅ Simplifica: parte da máquina de policiar deriva vira redundante. ✅ `mirror-freshness` ganha propósito duro (integridade do cache).

⚠️ Regeneração exige login (dispatch, não CI). ⚠️ Muda o hábito de editar o espelho à mão. ⚠️ Reversão Tier 0 — exige ratificação [W] item-a-item e supersede-parcial registrado (feito no frontmatter). ⚠️ Depende do UUID do projeto vivo.

## Validação (o que já está provado vs pendente)

- ✅ Leitura da API funciona (`DesignSync.get_file` → conteúdo real). ✅ `mirror-freshness` (0324) já compara md5 repo↔vivo. ✅ 42 relatórios meta já saíram do espelho (commit `99e57a75`).
- ⏳ Regeneração end-to-end pendente do UUID do projeto vivo (fase 1-2). ⏳ Reaponte de charters + adaptação do ssot-guard pendentes de ratificação.

## Notas

- Sequência de decisões de fonte-de-design: 0114 (loop Cowork) → 0239 (git-SSOT tokens) → 0299 (Figma não-fonte) → 0315 (DesignSync não-fonte, write gated) → 0324 (frescor do espelho) → **0325 (fonte do protótipo migra pra API; git vira cache gerado)**.
- O `mirror-freshness` (0324) não morre com esta ADR — ele **muda de papel**: de "conferência advisory" pra "gate de integridade do cache gerado". As duas ADRs se compõem.
