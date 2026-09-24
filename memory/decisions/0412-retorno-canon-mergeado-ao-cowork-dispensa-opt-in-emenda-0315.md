---
slug: 0412-retorno-canon-mergeado-ao-cowork-dispensa-opt-in-emenda-0315
number: 412
title: "Retorno de canon já mergeado ao projeto Cowork de telas dispensa opt-in (emenda à 0315)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-24"
module: design-system
tags: [design, governanca, hooks, design-sync, cowork, retorno, espelho]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0315-design-sync-claude-design-vs-cowork-charter
  - 0404-ultimo-importado-e-autoridade-do-espelho
  - 0406-o-que-ultimo-importado-decide-emenda-0404
  - 0374-emenda-0315-espelho-cowork-e-rota-prevista
pii: false
---

# ADR 0412 — retorno de canon já mergeado ao Cowork dispensa opt-in

> **Emenda à [ADR 0315](0315-design-sync-claude-design-vs-cowork-charter.md).** A política da
> 0315 continua inteira: claude.ai/design não é fonte de design, e a escrita pelo `DesignSync`
> exige opt-in explícito. Esta emenda abre **uma** exceção, estreita e medida pela máquina.
> Proposta por [CL]; o merge de [W] é a ratificação.

## Decisão do dono

[W], 2026-09-24, textual: **"eu imagino que isso já deveria estar sempre gravado e autorizado"**.

## O problema

A [ADR 0406](0406-o-que-ultimo-importado-decide-emenda-0404.md) D5 manda que o que o Code precisa
mudar no conteúdo do espelho `prototipo-ui/cowork/<dono>/` vá **para a origem** — o projeto Cowork
de telas. O [PROTOCOL §10.6](../reference/prototipo-ui/PROTOCOL.md) permite subir à origem o que
**já está aceito no `main`**, por `finalize_plan` + `write_files` com lista explícita de paths.

O hook `block-design-sync-without-optin` barrava essa escrita sem opt-in por prompt. Na sessão de
2026-09-24 ([PR #7852](https://github.com/wagnerra23/oimpresso.com/pull/7852)) [W] precisou
autorizar **três vezes** para uma escrita que o protocolo já prescreve: "autorizo gravar no
Cowork" e "design-sync, vão os 8 arquivos" não armaram o predicado; só "/design-sync" armou.

E a mensagem de bloqueio mentia sobre a saída: dizia *diga "design-sync" explícito no chat*, e
"design-sync" sozinho **não** passa no predicado — a saída anunciada não era a implementada
(classe LC-15 de [LICOES_CODE](../LICOES_CODE.md)).

O [#7888](https://github.com/wagnerra23/oimpresso.com/pull/7888), no mesmo dia, já isentava o
retorno por **formato** (projeto de telas, só `cowork-inbox/`, sem deleção, por `localPath`). O
formato não prova que o conteúdo é canon: um recibo escrito e ainda não revisado passava igual.

## Decisão

**D1 — isento sem opt-in: subir ao projeto Cowork de TELAS conteúdo já mergeado.** A escrita
dispensa opt-in quando, cumulativamente:

| critério | como o hook mede |
|---|---|
| projeto de **telas** (nunca o Design System) | `projectId` igual ao id do painel |
| todo path sob `cowork-inbox/`, literal, sem curinga nem `..` | formato do plano |
| nenhuma deleção | `deletes` vazio; `delete_files` nunca isenta |
| `localDir` é o espelho `prototipo-ui/cowork/<dono>` de um repo git | `git rev-parse --show-toplevel` + caminho relativo |
| cada arquivo tem blob **idêntico** ao de `origin/main` | `git hash-object --path` × `git ls-tree origin/main` |
| no upload, o arquivo é o mesmo que foi medido e não mudou | plano medido gravado no `finalize_plan` (TTL 15 min); `write_files` exige `localPath === path` e recompara o blob |

**D2 — qualquer outro caso continua exigindo opt-in**, incluindo tela `*.jsx`/CSS mesmo que
mergeada (subir tela à origem é decisão [W], `fora_do_canal` do `pendentes-cowork.mjs`),
arquivo só commitado no branch, e qualquer falha de medição (sem repo, sem ref, git ausente):
não medir nunca vira isenção.

**D3 — a mensagem anuncia exatamente o que o predicado aceita.** As frases de opt-in exibidas
saem de `OPT_IN_EXEMPLOS`, e um teste de contrato roda cada uma no `isDesignSyncOptInPrompt`. A
mensagem também diz por que a escrita não ficou isenta (motivo medido).

## Consequências

- O fluxo `pendentes-cowork.mjs --plano` → `finalize_plan` → `write_files` → `--registrar-envio`
  roda sem pedir autorização ao [W], **depois** do merge do PR que traz o recibo.
- Subir **antes** do merge (o que o #7888 permitia) volta a pedir opt-in. É o que o §10.6 já dizia.
- ⚠️ O hook lê o ref **local** `origin/main`, sem rede. Ref velho corta pros dois lados: arquivo
  mergeado depois do último fetch cai no opt-in (seguro), e um arquivo local igual a uma versão
  anterior que ainda está no ref velho passa — é canon já mergeado, mas pode não ser o último.
  Rodar `git fetch origin main` antes do `--plano`.
- ⚠️ O hook só vale a partir da próxima sessão (sem hot-reload), como toda mudança de hook.
- O conteúdo é medido pelo hook, não pelo `DesignSync`: se a ferramenta mudar a semântica de
  `localDir`/`localPath`, a isenção precisa ser re-medida.

## Provas

`node .claude/hooks/block-design-sync-without-optin.test.mjs` monta um repo git descartável com o
espelho e prova: canon mergeado passa (plano e upload); conteúdo diferente de `origin/main`,
arquivo novo, arquivo só commitado no branch, disco editado entre plano e upload, `localPath`
diferente do `path`, path fora do plano, `localDir` fora do espelho, projeto DS, tela `.jsx` e
`localDir` fora de repo git são bloqueados.
