---
slug: 0359-errata-0358-numero-0101-nao-virou-inequivoco
number: 359
title: "Errata à 0358 — remover a ADR resolveu a colisão NO DISCO, mas o número 0101 não virou referência inequívoca"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: errata
decided_by: [W]
decided_at: "2026-07-29"
accepted_via: "Correção do próprio autor, medida depois do merge do #5028. Não muda decisão nenhuma — corrige uma afirmação factual falsa no corpo da 0358, que o append-only impede editar no lugar (Constituição Art. 3)."
module: governance
quarter: 2026-Q3
tags: [governanca, adr, errata, colisao, alias-map, append-only]
supersedes: []
superseded_by: []
related:
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
  - 0274-referencia-adr-por-slug-alias-map-13-colisoes
  - 0316-esquecimento-real-adr-morta-tombstone-git-auditoria
pii: false
---

# ADR 0359 — Errata à 0358: o número `0101` não virou referência inequívoca

> Errata pontual. **Não reverte nem emenda a decisão da [0358](0358-doutrina-de-teste-tenant-98-supersede-0101.md)** — o tenant canônico de teste segue **biz=98** e a `0101-tests` segue esquecida. Corrige **uma frase factual errada** no corpo dela.

## O que a 0358 afirma

Na seção *Consequências*:

> ✅ A **colisão de número 0101** (duas ADRs com o mesmo número) deixa de existir — resolvida
> fisicamente, não remendada por alias-map. Sobra a `0101-sistema-charter-capterra-governanca-escopo`,
> e **"ADR 0101" volta a ser referência inequívoca**.

A primeira metade está certa. **A última cláusula é falsa.**

## O que foi medido (2026-07-29, depois do merge do [#5028](https://github.com/wagnerra23/oimpresso.com/pull/5028))

**115 charters** citam `101` **numérico** em `related_adrs`, e a leitura do contexto mostra que a maioria queria dizer a ADR **de tests** — o trio típico é `93 · 94 · 101 · 104` (multi-tenant · constituição · tests · MWART). Exemplo em `resources/js/Pages/RecurringBilling/Planos/Index.charter.md`:

```yaml
related_adrs: [93, 94, 101, 104, 107, 110, 114, 143]
```

Com a `0101-tests` removida do disco, esse `101` passa a resolver na **sobrevivente** (`0101-sistema-charter-capterra-governanca-escopo`), que trata de governança de escopo — **outro assunto**. Ou seja: a referência deixou de ser *ambígua* e passou a ser *silenciosamente errada*, que é pior.

## A afirmação correta

A colisão foi resolvida **no disco e no índice gerado** (`collisions_grandfathered` 14 → 13). A **referência numérica legada não foi consertada** e continua exigindo desambiguação. Duas fontes resolvem, e as duas continuam vivas de propósito:

1. **[`governance/adr-alias-map.json`](../../governance/adr-alias-map.json)** — a entrada `0101` lista os dois slugs com `hint`. É **append-only** ([ADR 0274](0274-referencia-adr-por-slug-alias-map-13-colisoes.md)): entrada existente nunca sai. Não foi tocada, e é exatamente para este caso que ela serve.
2. **[`governance/adr-tombstones.json`](../../governance/adr-tombstones.json)** — a lápide da `0101-tests` aponta `superseded_by: 0358`.

## Por que os 115 não foram reescritos

Big-bang de legado: tocar 115 charters acorda os gates diff-aware que hoje os grandfatheram ([§5 2026-07-12](../proibicoes.md)). Isso não é hipótese — aconteceu **neste mesmo trabalho**: relinkar 11 charters acordou o `charter related_us join`, e 3 deles não tinham `related_us`. O caminho é **forward-only + oportunístico**: o charter corrige o número quando trabalho real já o tocar.

## Lição registrada

A afirmação errada nasceu de inferência estrutural — *"removi um dos dois, logo o número ficou único"* — sem medir **quem cita o número e querendo dizer o quê**. É a classe **LC-08** (afirmar a partir da fonte errada): o oráculo certo não era o diretório de ADRs, era o corpus que **referencia** o número. Contar levou um comando.
