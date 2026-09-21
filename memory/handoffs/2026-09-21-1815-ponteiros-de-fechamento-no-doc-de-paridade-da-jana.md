---
date: "2026-09-21"
time: "18:15 UTC"
slug: ponteiros-de-fechamento-no-doc-de-paridade-da-jana
tldr: "As tabelas de veredito do Index-visual-comparison.md da Jana guardavam o ANTES sem nota de fechamento, e tres sessoes irmas foram mandadas consertar codigo ja correto. 13 linhas ganharam ponteiro na propria linha, append-only provado. E registrei no ledger que inventei a HORA de uma medicao dentro da nota que existe para marcar medicao caduca."
prs: [7642, 7650]
decided_by: [W]
related_adrs: [0344-two-strikes-cobre-processo]
next_steps:
  - "Decisao [W] aberta: header/brief/corpo da rodada 09-07 seguem NAO REAVALIADOS (exigem sonda no DOM)"
  - "Decisao [W] aberta: densidade da grade de METAS diverge (3col/gap16 x auto-fit/gap10) - divida nova, medida no #7639"
  - "tabs re-conferido e SEGUE ABERTO: o gap-0.5 (2px) do container role=tablist nao foi tocado pelo density=compact"
---

# Ponteiros de fechamento no doc de paridade da Jana — e a hora que eu inventei

> **Par do [`1730`](2026-09-21-1730-grade-analises-3-colunas-e-a-tabela-que-mentia.md)**, da sessão
> que consertou a grade. Ele conta o **conserto de código** e diagnostica a causa; este conta o
> **conserto do documento** que produziu o retrabalho. Leia os dois se o tema for o Painel da Jana.

## Estado MCP no momento do fechamento

| consulta | resultado |
|---|---|
| `cycles-active` | **nenhum cycle ATIVO** em COPI |
| `whats-active` | rodado 2× (23 sessões vivas na 1ª; 10 na 2ª). Uma tocava o **mesmo arquivo**; 3 PRs irmãos abertos nele |
| handoffs irmãos de hoje | **8** (este é o 9º) · `memory/handoffs/2026-09-*` = 68 |
| `decisions-search` | 0388 (réplica primeiro) · 0290 (fidelity lock recusado) · 0397 · UI-0028 — nenhuma contradiz o feito aqui |

## O que aconteceu

**O problema não era a medição — era a falta do ponteiro.** As tabelas de veredito do
`memory/requisitos/Jana/Index-visual-comparison.md` preservam o **ANTES** de cada rodada (correto:
é fato datado). Só que os fechamentos posteriores foram registrados em **notas abaixo das tabelas**,
e quem lê a tabela não desce até a nota. Resultado medido hoje: **três sessões irmãs** receberam
ordem de consertar itens daquele doc, mediram antes de editar, e acharam o item **já correto em
produção**. Obedecer teria reescrito código correto e derrubado teste.

O caso mais duro é auto-evidente: na rodada de 09-07, o `h2 ações` recebeu `✅` do conserto de
09-18 e o `h2 análises` — **duas linhas ao lado, mesmo componente, mesmo PR** — não. O documento se
contradizia a duas linhas de distância.

O [#7642](https://github.com/wagnerra23/oimpresso.com/pull/7642) acrescentou o ponteiro **na própria
linha** de 13 itens, sempre preservando o veredito antigo integral:

| tabela | linhas | resultado |
|---|---|---|
| §KPIs (rodada 09-03) | **7** | `✅ FECHADO 2026-09-03 (#6662)` — merge às 17:44Z do **mesmo dia** da medição; ficou **18 dias** sem ponteiro |
| §por seção (rodada 09-07) | 4 | `análises (grade)` #7638 · `h2 análises` #7555 · `ações gap` **reclassificado** (propriedade inerte) · `tabs` **segue aberto** |
| §Header+abas (09-03) | 1 | `subtítulo` #6655 (`font-mono`) |
| §Metas (09-03 **e** 09-07) | 1 + 2 notas | deixou de ser `⬜ NÃO COMPARÁVEL` |

**Append-only provado, não afirmado.** Minha 1ª tentativa **substituiu** o veredito, comendo
`(é o "feio"…)`, `tinta sólida × 5%` e `peso 700×600`. Revertida e refeita em modo append, com
teste de identidade: **0 linhas antigas sem sobrevivência · 13/13 preservaram a contagem de
células** (tabelas markdown intactas). As linhas foram extraídas do próprio arquivo, não
redigitadas.

**Ordem de fonte respeitada** (da mais barata à mais cara, sem sonda no DOM): `casos.md` + o teste
que trava → componente → `git log -S` em repo comprovadamente não-raso. Cada ponteiro tem PR **e**
data de merge medida (`gh pr view --json mergedAt`).

## O erro que eu mesmo cometi, e o registro dele

O [#7650](https://github.com/wagnerra23/oimpresso.com/pull/7650) registra no ledger que, na
nota-recibo dos KPIs, eu escrevi que os números antigos *"eram verdade **às 09h** daquele dia"*.
**A rodada de 2026-09-03 não registra hora nenhuma.** Reconstruí de plausibilidade.

O incremento que a lápide acrescenta à **LC-08** não é "afirmei sem medir" (a classe já tinha 113
recibos): é **como passou**. A frase estava cercada de três números medidos de verdade
(`17:44:08Z` de `mergedAt`, 18 dias calculados, 13 campos contados) e **herdou a credibilidade da
vizinhança, sem disfarce ativo** — o §5 2026-07-17 cobre o disfarce deliberado (*"citar a tool ao
lado"*), e aqui não citei tool alguma para ela. O leitor calibra confiança no **bloco**, não na
frase.

**Agravante exato:** a nota existe para marcar retrato vencido, e o PR inteiro se justifica por 3
sessões terem sido enganadas por retrato vencido. Mesmo formato da emenda §5 2026-07-30
(*"cometi, ao registrar a classe, a própria classe"*).

**Alcance medido — não chegou ao `main`:** viveu **2min22s** no head (`a32254815` 17:20:05Z →
errata `7097e6eb1` 17:22:27Z) e **59s** com o PR aberto. **Quem pegou foi releitura própria do
texto publicado, não máquina** — dado, não desculpa.

## Artefatos gerados

| arquivo | diff | papel |
|---|---|---|
| `memory/requisitos/Jana/Index-visual-comparison.md` | +94/−13 | 13 ponteiros + 4 notas-recibo |
| `memory/licoes-rejeitadas.md` | +58 | **fonte** da lápide (append-only Tier 0) |
| `memory/LICOES_CODE.md` | +1 | recibo na LC-08 |
| `memory/proibicoes.md` | +27 | **derivado** por `sec5-derive.mjs --write` |

Os 3 arquivos do ledger são **append puro** (0 removidas em cada).

## Persistência

- **git:** 2 PRs mergeados pelo [W] — `#7642` (`0e8a3b029d2`, squash) e `#7650` (`bb9ed039340`).
- **CI:** #7642 → 84 success · 2 skipped · 1 `cancelled` **superseded por success 70s depois**;
  #7650 → **113 success · 3 skipped · 0 falhas**.
- **ledger:** contador da LC-08 **114 → 115**, DERIVADO (`base:1` + 114 recibos) — número não
  tocado à mão, e a lápide **não escreve o ordinal** (convenção 2026-09-15).
- **conferido em `origin/main`, não na minha árvore:** os 3 arquivos presentes ·
  `sec5-derive --check` **OK** (221 limites, 0 perdidos) rodado contra `origin/main` ·
  `licoes-code-two-strikes --reconcile` → `recibos_resolvem 156/156` · `pendurados []` ·
  `surface_S3 0`.
- **BRIEFING:** não tocado — nenhuma capacidade de módulo mudou (doc + ledger only).

## Próximos passos pra retomar

```bash
node scripts/governance/sec5-derive.mjs --check && node .claude/hooks/licoes-code-two-strikes.mjs --reconcile
```

Para o doc da Jana, a porta viva é o próprio arquivo: as linhas abertas agora **dizem que estão
abertas**, e as não-reavaliadas dizem isso numa tabela dentro da nota. Nada a "descobrir".

## Lições catalogadas

| lição | onde |
|---|---|
| Inventei a hora de uma medição dentro da nota que marca medição caduca; **número verdadeiro ao lado não autentica o vizinho** | §5 2026-09-21, *hora-inventada-na-nota-de-caducidade* · recibo na **LC-08** |
| Substituí veredito antigo em vez de apendar — pego pelo teste de identidade **antes** do commit | corrigido na hora; não virou lápide (não chegou a artefato publicado) |
| `density="compact"` fechou fonte/padding da aba e **não** o `gap` do container — dois eixos, uma tabela com ✅ e outra com ❌ | registrado na própria linha `tabs` do doc |
| O gate `memory-schema` **mordeu ao escrever este handoff**: `related_adrs` não aceita slug com ponto (`…claude-4.8-aware`) — a ADR 0224 ficou citada só no corpo | recibo do próprio mecanismo funcionando |

**Sem gate novo.** O óbvio da LC-08 está medido-e-reprovado (130 FP · ~64% FP), a forma sintática
reprovaria os legítimos do próprio arquivo, e o predicado é semântico (ADR 0224 —
`memory/decisions/0224-hooks-block-vs-advisory-claude-4.8-aware.md`). Por
[ADR 0344](../decisions/0344-two-strikes-cobre-processo.md): não chegou a prod.

## Pointers detalhados

- Conserto de código do mesmo tema + diagnóstico da causa: [`1730`](2026-09-21-1730-grade-analises-3-colunas-e-a-tabela-que-mentia.md)
- Medição do **depois** dos KPIs (13 campos): `resources/js/Pages/Jana/Index.casos.md`, seção "Medição de runtime — mesma sonda nos dois lados (2026-09-03)"
- Lápide completa: `memory/licoes-rejeitadas.md`, cabeçalho `2026-09-21 — Inventei a HORA…`
