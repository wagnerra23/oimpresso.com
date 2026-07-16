# Architecture Decision Records (ADRs)

> ⚠️ **Este README NÃO é a lista de ADRs** — está obsoleto (parou no ADR 0023, era "Ponto WR2"). A lista canônica e atual é **GERADA**: [`_INDEX-GENERATED.md`](_INDEX-GENERATED.md) (modelo Log4brains, ADR 0258 / PR #2391 · `node scripts/governance/adr-index-generate.mjs`). Este arquivo serve só como doc de "o que é / como criar uma ADR".

Este diretório contém os registros formais de decisões arquiteturais importantes do projeto Ponto WR2.

## O que é uma ADR

Uma **Architecture Decision Record** é um documento curto que captura uma decisão arquitetural significativa, seu contexto e suas consequências. Seguimos o formato do Michael Nygard:

- **Título:** ADR NNNN — título descritivo
- **Status:** Proposta | Aceita | Substituída por NNNN | Obsoleta
- **Contexto:** O problema e as forças que levaram à decisão
- **Decisão:** O que decidimos fazer
- **Consequências:** O que melhora, o que piora, o que muda

## Quando criar uma ADR

Crie uma nova ADR quando:

- A decisão afeta a arquitetura (estrutura, tecnologia, integrações)
- A decisão é difícil/custosa de reverter
- Pessoas novas no time vão perguntar "por que está assim?"
- Substitui uma decisão anterior

**Não crie ADR** para: escolhas de implementação local, naming, formatação, bugs.

## Como numerar

Sequencial, 4 dígitos, zero à esquerda: `0001`, `0002`, ...

Nome do arquivo: `NNNN-titulo-curto-em-kebab.md`

## Como ratificar uma ADR proposta (flip `proposto → aceito`)

A ratificação é ato soberano do Wagner (R10) — o **merge do PR de flip é o ato**. Mecânica canônica (convenção [ADR 0257](./0257-adr-status-lifecycle-kind-modelo-canonico.md), validada no [PR #4027](https://github.com/wagnerra23/oimpresso.com/pull/4027)):

1. **Base fresca**: branch a partir de `origin/main`; confirmar via `git show origin/main:<adr>` que o status ainda é `proposto` (sessão paralela pode ter flipado).
2. **Flip in-place**: mudar SÓ a linha `status: proposto → aceito` no frontmatter. NUNCA mover de pasta, NUNCA criar arquivo novo, corpo INTACTO (append-only).
3. **Índice**: `node scripts/governance/adr-index-generate.mjs --write` e commitar o `_INDEX-GENERATED.md` junto; `--check` deve sair exit 0.
4. **Label OBRIGATÓRIA no PR: `adr-metadata-normalization`** — sem ela o gate required `Append-only canon` FALHA mesmo com diff perfeito (a exceção 0257 do `governance-gate.yml` só aplica sob essa label e valida que o diff toca apenas `status`/`lifecycle`/`kind`/`authority`/`supersedes*`/`superseded_by`). Se esquecer: `gh pr edit <PR> --add-label adr-metadata-normalization` + `gh run rerun <run-id> --failed` (o step lê a label em runtime).
5. **Corpo do PR**: deixar explícito que "o merge deste PR = ratificação formal pelo Wagner (R10); a ADR só entra na busca default do `decisions-search` (`scopePorStatusAtivo`) após o merge". Quem mergeia (ou manda mergear) é o Wagner.

## Índice de ADRs

| # | Título | Status |
|---|---|---|
| [0001](./0001-estender-ultimatepos-opcao-c.md) | Estender UltimatePOS em vez de build ou fork | ✅ Aceita |
| [0002](./0002-nwidart-laravel-modules.md) | Usar nWidart/laravel-modules | ✅ Aceita |
| [0003](./0003-marcacoes-append-only.md) | Marcações append-only via triggers MySQL + app layer | ✅ Aceita |
| [0004](./0004-bridge-colaborador-config.md) | Tabela bridge `ponto_colaborador_config` | ✅ Aceita |
| [0005](./0005-uuid-vs-bigint.md) | UUID para auditáveis, BigInt para lookups | ✅ Aceita |
| [0006](./0006-multi-tenancy-logica.md) | Multi-tenancy lógica via business_id | ✅ Aceita |
| [0007](./0007-banco-horas-ledger.md) | Banco de horas como ledger append-only | ✅ Aceita |
| [0008](./0008-sidebar-unica-tabs-horizontais.md) | Sidebar com 1 item + tabs horizontais | ✅ Aceita |
| [0009](./0009-prototipos-html-puro.md) | Protótipos em HTML+Tailwind+Chart.js (não React) | ✅ Aceita |
| [0010](./0010-sistema-memoria-projeto.md) | Sistema de memória do projeto (CLAUDE.md + /memory/) | ✅ Aceita |
| [0011](./0011-alinhamento-padrao-jana.md) | Alinhamento com padrãa Jana (UltimatePOS) | ✅ Aceita |
| [0023](./0023-inertia-v3-upgrade.md) | Upgrade Inertia.js v2 → v3 (faseado) | ✅ Aceita |
| 0024–0235 | _(índice cronológico defasado — ver índice temático completo abaixo)_ | — |
| [0246](./0246-sessao-2026-05-30-ds-harmonizacao.md) | Harmonização DS sem perder qualidade + caminho v4.2 (Cowork, proposto como `0200`) | ✅ Aceita |
| [0247](./0247-ratificacao-constituicao-design.md) | Carta de Design [CC] subordinada ao protocolo do git (Cowork, proposto como `0201`) | ✅ Aceita |

---

> ⚠️ **Este índice cronológico está defasado** (parou em 2026-04-24 / ADR 0023). O índice **completo e atual**,
> organizado por tema (T1–T9, ADRs 0001–0247), está em [`../INDEX_TEMATICO.md`](../INDEX_TEMATICO.md).
> O lifecycle (aceita / superseded / proposta) é resolvido pela tool MCP `decisions-search` +
> [`_INDEX-LIFECYCLE.md`](./_INDEX-LIFECYCLE.md). Regra de numeração: ADR 0028 (monotônica).

**Última atualização:** 2026-07-09 — seção "Como ratificar uma ADR proposta" (label `adr-metadata-normalization` obrigatória no PR de flip, lição do #4027).
