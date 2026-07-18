---
slug: 0120-reverse-supersession-metadata-housekeeping
number: 120
title: "Supersession metadata housekeeping — fix 0079 + documenta drift de direção forward"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-09"
quarter: 2026-Q2
tags: [governance, adr, metadata, housekeeping, audit-constituicao]
related_adrs: [0028, 0094, 0095]
amends: []
supersedes: []
parent_adr: 0094
authors: [wagner, opus]
pii: false
---

# ADR 0120 — Supersession metadata housekeeping

> **Status:** ✅ Aceita em 2026-05-09 por Wagner (sessão "consolidação geral pós-Constituição v2", autorização "Tier B pode escolher").
>
> **Categoria:** governance / metadata cleanup
>
> **Escopo reduzido após verificação direta:** o audit `audit-constituicao` (skill criada nesta sessão) reportou inicialmente que **9 ADRs marcadas `substituido` faltavam `superseded_by:` no frontmatter**. Verificação na fonte (Read direto dos 9 arquivos) descobriu que **todos os 9 já têm `superseded_by:` populado** — o audit confundiu direções. O drift real está na **direção forward (`supersedes:` no superseding ADR)**, não reverse.

---

## Contexto

A skill `audit-constituicao` (recém-criada) afirmou que 9 ADRs `substituido` careciam de `superseded_by:`. Verificação direta refutou: 0008, 0010, 0031, 0032, 0033, 0042, 0073, 0075, 0077 **todas têm `superseded_by:` corretamente populado**. O verificador adversarial interno também não pegou esse erro (deu accuracy 10/10 ao audit 3 — overestimated).

**Único drift confirmado:** [ADR 0079](0079-constituicao-oimpresso-7-camadas-governanca.md) (Constituição 10 artigos) tem `lifecycle: ativo` + `superseded_by: []` apesar de [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) declarar explicitamente `supersedes: [0078, 0079]` no frontmatter. Drift triplo (frontmatter 0079 ↔ frontmatter 0094 ↔ `_INDEX-LIFECYCLE.md` Bloco 7).

**Drift adicional descoberto na verificação (fora do escopo desta ADR):** a direção FORWARD tem buracos — alguns superseding ADRs não declaram `supersedes:` em frontmatter:

| ADR superseding | Deveria declarar supersedes de | Atualmente |
|---|---|---|
| 0027 + 0053 | 0010 (parcial cada) | 0027 não tem; 0053 não tem (verificar) |
| 0036 | 0031 + 0033 | NÃO tem `supersedes:` (só `related:`) |
| 0039 | 0008 (parcial) | NÃO tem `supersedes:` (só `related:`); body diz "Substitui parcialmente ADR 0008" |
| 0048 | 0032 | verificar |
| 0058 | 0042 | NÃO tem `supersedes:` (só `related:`) |
| 0076 | 0075 | ✅ tem |
| 0081 | 0077 | ✅ tem |
| 0094 | 0078 + 0079 | ✅ tem |

Essa direção forward é não-trivial pra fixar porque:
1. Algumas ADRs declaram supersession só no body (ex: 0039 body fala "Substitui parcialmente 0008")
2. `related:` ≠ `supersedes:` semanticamente, mas frontmatter histórico misturou
3. Adicionar `supersedes:` retroativamente a ADRs accepted é ato sensível de metadata-only — esta ADR estabelece precedente mas não executa o backfill agora

## Princípio invocado

[ADR 0028](0028-padrao-adr-numeracao-monotonica-formato-nygard.md) define formato Nygard. **Append-only** ([proibições.md](../proibicoes.md)): "ADRs CANON são append-only. NUNCA editar accepted records — criar nova com `supersedes: [N]`".

**Distinção:** "accepted records" = **decisão** (Status / Context / Decision / Consequences corpo). **Metadata derivada de OUTRA ADR** (lifecycle, superseded_by, supersedes) é registro de relação inter-ADR — fechar o link bidirecional já implícito não revisa decisão original.

## Decisão

1. **Fix imediato (escopo desta ADR):** [ADR 0079](0079-constituicao-oimpresso-7-camadas-governanca.md) — única drift cristalina:
   - `lifecycle: ativo` → `lifecycle: substituido`
   - `superseded_by: []` → `superseded_by: ['0094']`
   - **Não tocar no corpo** (Decision/Context/Consequences).

2. **Pendência forward direction (escopo separado, futura ADR):** Adicionar `supersedes:` em 5-6 ADRs (0027, 0036, 0039, 0048, 0053, 0058) quando outra ADR aceita as supersede. Não é feito agora porque:
   - Requer caso a caso (parcial vs total)
   - Algumas só registram supersession no body, não frontmatter (ambíguo)
   - Wagner não autorizou retro-edição em massa de ADRs accepted

3. **Skill `audit-constituicao` melhorada:** o prompt do audit dimensão 3 (ADR lifecycle) deve, em rodadas futuras, **verificar AMBAS as direções** (`supersedes:` no superseding + `superseded_by:` no superseded), e rodar Read direto de spot-check antes de afirmar drift. O verificador adversarial também precisa endurecer.

## Consequências

### Positivas

- 0079 sai do estado inconsistente. Tools MCP (`decisions-search`) podem filtrar corretamente.
- Estabelece precedente de "metadata-only fix com ADR justificadora" — não viola append-only.
- Documenta a pendência forward (com exemplos) pra próxima rodada de housekeeping.
- Lição aprendida: audit pode reportar direção errada — verificador adversarial **precisa** Read direto, não só spot-check do que o audit afirmou.

### Negativas / Trade-offs

- ADR 0120 em si tinha rascunho inicial errado (assumiu o reverso — backfill de `superseded_by:`). Corrigida antes do commit. Lição: ler fonte primária antes de escrever ADR baseada em audit.
- Drift forward direction continua aberto (5-6 ADRs sem `supersedes:`). Risco baixo (info vive no `_INDEX-LIFECYCLE.md`) mas não-zero.

### Métrica de validação

Re-rodar `audit-constituicao` (`/audit-constituicao`) após este merge:
- Dimensão 3 deve mostrar 0079 como `substituido` ✅
- Dimensão 3 deve flagar 5-6 ADRs sem `supersedes:` direção forward (novo achado documentado)

## Referências

- [ADR 0028](0028-padrao-adr-numeracao-monotonica-formato-nygard.md) — formato Nygard
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (supersede 0078, 0079)
- [ADR 0079](0079-constituicao-oimpresso-7-camadas-governanca.md) — alvo do fix imediato
- [_INDEX-LIFECYCLE.md](_INDEX-LIFECYCLE.md) — fonte das relações de supersessão
- [proibições.md](../proibicoes.md) — append-only
- skill `audit-constituicao` (criada 2026-05-09) — origem do diagnóstico (com erros corrigidos por verificação direta)

---

**Aprovado:** 2026-05-09 por Wagner.
**Implementação:** simultânea com este merge (apenas fix em 0079).
