---
slug: 0316-esquecimento-real-adr-morta-tombstone-git-auditoria
number: 316
title: "Esquecimento real de ADR morta — git rm + tombstone ledger + git history como auditoria (emenda 0270-D1)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-01"
accepted_via: "Wagner 2026-07-01, no chat: 'os adr invalidos, que devem ser retirados eles ficam poluindo a memoria isso nao e legal' + 'sei que tem o index mais nao funciona, tem que ter esquecimento real essa merda' + 'resolva essa merda poxa, verifique o que funciona melhor'. Autorizacao explicita pra ir alem do lifecycle-hide (0270-D1) e remover de verdade a ADR morta."
module: governance
tags: [governanca, adr, esquecimento, tombstone, append-only, ciclo-de-vida, decaimento, memoria]
supersedes: []
superseded_by: []
related:
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0258-processo-adr-estado-arte-indice-gerado-supersede-atomico
  - 0274-referencia-adr-por-slug-alias-map-13-colisoes
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
pii: false
---

# ADR 0316 — Esquecimento real de ADR morta

> Emenda a [ADR 0270](0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento.md) §D-1 (tipo "Decisão"). NÃO supersede a 0270 — fecha o furo que ela deixou aberto pro tipo ADR.

## Contexto

A [ADR 0270](0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento.md) decidiu que **"aplicar append-only a conhecimento é proibido — esquecer é feature, não perda"** e tipou a informação em 4 classes. Mas classificou **ADR** como *"append-only com lifecycle; decai de relevância, NÃO some"* — ou seja, a ADR morta continua **fisicamente** em `memory/decisions/`.

Resultado medido (2026-07-01): 320 ADRs no disco; **29 mortas** (`lifecycle: substituido`/`arquivado`) que já não aparecem no `decisions-search` (filtrado por status) MAS continuam poluindo `ls`, `grep`, o índice gerado bruto e a leitura humana do diretório. Wagner (palavras textuais acima): o índice/lifecycle-hide *"não funciona"* — quer **esquecimento real**.

Verificação adversarial (workflow `adr-depoluir-cadeias-mortas`, 35 candidatos): **0 ADRs "mortas-marcadas-vivas"** — todas as que citam supersede no título são emendas/erratas que mantêm a base viva. Logo o alvo de esquecimento é **exclusivamente** o conjunto já-verificado-morto (`substituido`/`arquivado`), sem risco de enterrar decisão viva.

### O destravamento

O que o append-only protege é o **"por que um dia foi assim"** (auditoria). Isso vive no **histórico do git** — não no arquivo parado na árvore de trabalho. Então dá pra ter esquecimento real na *memória de trabalho* **preservando** a auditoria.

## Decisão

**ADR verificada morta (`lifecycle: substituido` OU `arquivado`) é REMOVIDA de `memory/decisions/` via `git rm`.** Some do `ls`, `grep`, `Glob`, do índice gerado, do ingest do MCP (`decisions-search` soft-deleta slug ausente) e do recall da Jana. Esquecimento real.

Auditoria preservada por **duas** fontes, nenhuma na árvore quente:

1. **Git history** — conteúdo integral da ADR, pra sempre (`git log --follow -- <path>`, `git show <sha>:<path>`). É a auditoria primária.
2. **Tombstone ledger** — [`governance/adr-tombstones.json`](../../governance/adr-tombstones.json), **append-only**, 1 entrada por ADR esquecida: `{ number, slug, died_reason, superseded_by, last_sha, forgotten_in_pr }`. Machine-readable, 1 linha — ledger, não doc; não polui. Resolve referência legada ("ADR 0028") e o link `supersedes` das vivas.

### Invariantes (Tier 0)

- **Só esquece ADR já-morta.** `git rm` de ADR com `lifecycle: ativo` (ou status `aceito`/`proposto`) é **proibido** — precisa rebaixar primeiro (supersede atômico, ADR 0258).
- **Número NUNCA reusado** (o `next-id.mjs` já pula buracos; o tombstone registra o número como consumido-e-esquecido).
- **`git rm` + entrada no tombstone no MESMO PR** (diff verificável).
- **Lei/auditoria imortal segue intocada** (0270-D1 tipo "Lei": marcação de ponto, `mcp_audit_log`, NFe — nunca entram aqui).
- **Recuperável:** nada é destruído — `git show` traz de volta; re-adicionar exige remover do tombstone (append-only ⇒ decisão consciente).

### Mecânica (o que muda no código)

- **`scripts/governance/adr-index-generate.mjs` fica tombstone-aware:** `supersedes: [T]` cujo `T` foi esquecido (consta no tombstone) **NÃO** é mais "dangling / ADR não existe" — é supersessão de ADR legitimamente esquecida. Sem isso o `--check` (gate duro, 0258) quebraria ao deletar (0258→0028, 0048→0035, 0178→0136, etc). O índice ganha uma linha "Esquecidas (N · ver tombstone + git)".
- **Gate append-only (`governance-gate.yml` block-adr-edits):** hoje NÃO bloqueia `^D` (delete) — só `^M`/`^R`. Este ADR torna isso **intencional e governado**: delete de ADR é permitido, condicionado ao invariante "só morta + tombstone no mesmo PR", enforçado por validador do tombstone (o `git rm` sem entrada no ledger, ou de ADR viva, falha).

## Consequências

- ✅ `memory/decisions/` passa a ter **só ADR viva** — o "poluída" some do diretório, grep, índice e recall.
- ✅ Auditoria intacta (git history + tombstone). Nada perdido; "por que" recuperável.
- ✅ Acumulação para: dali em diante, ADR que morre **sai** em vez de empilhar.
- ✅ Alinha com docs-as-code de verdade (o VCS é o arquivo morto — Log4brains/AWS).
- ⚠️ Custo: gerador tombstone-aware + ledger + validador (PR-A) + rodar o esquecimento das 29 (PR-B). Referências em prosa (links markdown) pra ADR esquecida viram links quebrados — resolvidos pelo tombstone/git; um sweep opcional pode reescrevê-los pra apontar a sucessora.
- ⚠️ Não conserta o **volume** (320 ADRs, swarm de erratas) — isso é destilação (0270-D2), escopo separado. Esquecimento ataca o morto; destilação ataca o vivo-fragmentado.

## Alternativas consideradas

- **Lifecycle-hide (0270-D1 atual):** rejeitada por Wagner — o arquivo fica no disco/grep/índice; "não funciona".
- **Mover pra `memory/decisions/_archive/`:** mais suave (sai do glob/índice) mas o arquivo continua no repo (grep acha) — não é esquecimento real. Descartado a favor do `git rm` + git history.
- **Hard-delete sem tombstone:** perde a resolução de referência legada e o link das vivas. Rejeitado — o ledger de 1 linha custa quase nada e preserva a navegabilidade.

## Refs

- [ADR 0270](0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento.md) §D-1 (tipo Decisão) — o furo que este fecha
- [ADR 0258](0258-processo-adr-estado-arte-indice-gerado-supersede-atomico.md) — supersede atômico + índice gerado (o `--check` que fica tombstone-aware)
- [ADR 0274](0274-referencia-adr-por-slug-alias-map-13-colisoes.md) — referência por slug (tombstone é o análogo pra ADR esquecida)
- `scripts/governance/adr-index-generate.mjs` · `governance/adr-tombstones.json` · workflow `adr-depoluir-cadeias-mortas` (2026-07-01, provou 0 falsos-mortos)
