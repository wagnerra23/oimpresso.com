---
status: proposal
title: "Documentação do fonte — modelo canônico por módulo (ratifica o dual-casa já implementado + sub-estrutura)"
proposed_by: Felipe [F] + Claude
proposed_at: 2026-07-31
relates_to:
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0130-handoff-append-only-mcp-first
  - 0094-constituicao-v2-7-camadas-8-principios
---

# PROPOSAL — Documentação do fonte: modelo canônico por módulo

> **Status:** `proposal` — [F] patrocina; ratificação segue o caminho canônico (PR + aprovação
> [W], porque toca `memory/decisions/` + um gate **required** — CODEOWNERS `@wagnerra23`).
> **Origem (2026-07-31):** [F] — *"gostaria de toda documentação junta, nada fora"* +
> *"organize melhor, tudo dentro lá como ficar melhor"*. A dor real: **achar toda a
> documentação de um módulo a partir de um lugar só**.
>
> **Revisado adversarialmente** (`document-relocation-adversary`, 2026-07-31): veredito
> *"sem defeito de fundo, após 3 correções"* — todas incorporadas aqui. As correções mudaram o
> enquadramento: **o núcleo desta decisão já está implementado e rodando**; a ADR o **ratifica**
> em canon + adiciona a sub-estrutura intra-módulo (ver §"Reenquadramento").

## Contexto (MEDIDO 2026-07-31, read-only)

| Família de doc | Onde vive | Longe do fonte? | Imposto por |
|---|---|:--:|---|
| SDD, SPEC, ANTI-REGRESSAO, BRIEFING, RUNBOOK, CAPTERRA*, audits, visual-comparison, `*.proto-baseline.json`, `*.map.json` | `memory/requisitos/<Modulo>/` | ✓ sim | `anchor-lint` (SPEC), schemas |
| **`charter.md` + `casos.md` (trio de tela)** | `resources/js/Pages/<Mod>/<Tela>.*` | ✗ **ao lado do `.tsx`** | **`casos-gate` G-1/G-6 (required)** + **[ADR 0264](../0264-governanca-executavel-trio-dominio-e2e.md)** |

Três fatos que **já estavam decididos** e que esta ADR reconcilia em vez de reabrir:

1. **A doc pesada já mora em `memory/requisitos/<Modulo>/`** — o objetivo "docs em `memory/`" está 90% satisfeito; o `Modules/` (backend) está limpo de doc.
2. **O índice derivado "dual-casa" que responde a dor do [F] JÁ EXISTE e é do [W]:** `scripts/governance/requisitos-status.mjs <Mod> --write` grava `memory/requisitos/<Mod>/_STATUS-GENERATED.md` lendo **as duas casas** — `resources/js/Pages/<Mod>/` **E** `memory/requisitos/<Mod>/_telas/` (docblock *"DUAS casas … [W] 2026-07-26"*). Ou seja: *"o trio de cada tela + status, onde quer que esteja"* já é um comando, com dono.
3. **A árvore-alvo espacial de `memory/` já tem dono:** `estrutura-canon-memoria` **§II.3** (`(A) Produto ERP → memory/requisitos/<Modulo>/`) + **§II.5b** (máquina de realocação `move-with-tombstone`, **implementada 2026-07-22**, que já trata `charter` como referrer sob gate diff-aware).

## Reenquadramento (correção do adversário, eixo 3)

Esta ADR **não** decide um A-vs-B aberto — o B **já está implementado e rodando**. Ela faz duas
coisas genuinamente novas, **apontando** para os donos acima em vez de restatear (§5 2026-07-09):

- **(N1)** Eleva a canon append-only a regra que hoje vive só num docblock de script: *"o trio da tela React fica ao lado do `.tsx`; achabilidade vem do índice derivado dual-casa (`requisitos-status.mjs`)"*.
- **(N2)** Define a **sub-estrutura intra-módulo** de `memory/requisitos/<Modulo>/` (que `estrutura-canon` §II.3 não detalha), **forward-only**.

E delimita a fronteira: `estrutura-canon` é dona do **frontmatter** (Parte I) e do **layout espacial de `memory/` + realocação** (Parte II); esta ADR é dona do **modelo de doc-do-fonte por módulo + a regra do trio (N1) + a sub-estrutura (N2)**.

## Decisão proposta

1. **Casa canônica da documentação do módulo = `memory/requisitos/<Modulo>/`**, sub-estrutura fixa
   (**N2**, forward-only — nasce assim; legado migra **oportunístico**, nunca em lote):

   ```
   memory/requisitos/<Modulo>/
   ├── SPEC.md · BRIEFING.md · SDD-<x>-vN.md · ANTI-REGRESSAO-<x>-legacy.md · RUNBOOK-<x>.md
   ├── _telas/            # por-tela: casos de fluxo-sem-React, visual-comparison, gap, proto-baseline
   ├── adr/{arq,tech,ui}/ # ADRs locais do módulo (onde já existe, ex. Financeiro)
   ├── audits/            # CAPTERRA*, AUDIT*, DISCOVERY*
   └── _STATUS-GENERATED.md   # DERIVADO por requisitos-status.mjs (não editar)
   ```

2. **O trio da tela React (`charter`+`casos`) permanece ao lado do `.tsx`** ([ADR 0264](../0264-governanca-executavel-trio-dominio-e2e.md)/[0256](../0256-knowledge-survival-meia-vida-catraca-sentinela.md)) — **N1, ratifica o já-implementado**.

3. **Achabilidade "de um lugar" = `requisitos-status.mjs --write` (dual-casa, já existe)**, não pasta nova nem índice à mão. *"Duas casas no disco, uma consulta"* ([W] 2026-07-26).

4. **Sem big-bang.** A sub-estrutura é forward-only + oportunística. Todo move oportunístico **deve enumerar os globs de gate diff-aware que o arquivo casa antes de tocar** — a EMENDA §5 2026-07-27 provou que a classe tem **3 eixos** (anchor-lint no SPEC · `casos-gate` G-6 por data-git · charter-join), e que até mudança inerte acorda gate diff-aware.

## Se [W] preferir mover o trio fisicamente pra `memory/` (Opção A — não recomendado)

Vira epic [W]-gated com `supersedes: [264]`. **Custo medido 2026-07-31 (corrigido — era subestimado):**

- **Raio real ≫ "6 ferramentas":** **29 workflows** fazem path-gate em `.charter.md`/`.casos.md`/`resources/js/Pages` (`git grep -lE … .github/workflows | wc -l` = 29) + **≥12 scripts/hooks** derivam o path-irmão (`block-mwart-violation`, `charter-validate`, `detect-ui-drift`, `pt-conformance`, `screen-coverage-map`, `vital-signs`…).
- **A enforcement NÃO sobrevive intacta a um re-path ingênuo** (correção da afirmação anterior): o **G-6 frescor** do `casos-gate` faz `const tsx = file.replace(/\.casos\.md$/, '.tsx'); if (!existsSync(tsx)) continue;` (`casos-coverage-guard.mjs:283,336`) → sem o `.tsx` irmão, **G-6 pula em silêncio** = anti-rot desligado sem sinal. Re-path exigiria re-arquitetar esse pressuposto-irmão, não só trocar o glob.
- **Degrada o anti-apodrecimento** ([ADR 0256](../0256-knowledge-survival-meia-vida-catraca-sentinela.md)): o custo é **mecânico** (o gate depende do irmão), não estético.

Registrado como opção consciente [W], **não recomendado** — o objetivo real do [F] (achar de um lugar) já é servido pelo dual-casa sem pagar nada disso.

## Consequências

- **Positivo:** formaliza em canon uma decisão hoje órfã de docblock; a achabilidade do [F] já está resolvida (comando existente); a sub-estrutura padroniza o que a campanha SDD gera daqui pra frente.
- **Custo aceito:** o trio segue fisicamente em `resources/js/` — "duas casas, uma consulta".

## Riscos Tier 0

- **Append-only:** não edita a 0264; se A, cria nova com `supersedes`.
- **Gate required:** nenhum re-path sem [W] + revisão adversarial (regra "a IA não altera a máquina que a fiscaliza" — CODEOWNERS).
- **Legado:** sub-estrutura forward-only; enumerar globs de gate antes de qualquer move (EMENDA §5 2026-07-27); zero reorg em massa (§5 2026-07-12).

## Ratificação

PR + aprovação **[W]** (CODEOWNERS). Revisão adversarial já feita (verdicto anexo no PR).
