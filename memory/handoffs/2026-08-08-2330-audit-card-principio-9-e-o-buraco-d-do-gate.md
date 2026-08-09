---
date: "2026-08-08"
time: "23:30"
slug: audit-card-principio-9-e-o-buraco-d-do-gate
tldr: "O princípio do Audit Card (LGPD Art. 20), declarado pela ADR 0145 e preservado pela 0363 mas NUNCA redigido, virou ADR 0372 aceita + bullet no Artigo 4 da Constituição (v1.2.0 com cascade audit §10.4). 4 PRs em main. No caminho, a medição derrubou minha própria claim: o gate da Constituição não é insatisfazível — tem buraco por onde o #2413 deletou o arquivo inteiro em silêncio (detect casa ^M/^A, não ^D/^R). Declarado, não consertado."
decided_by: [W]
cycle: null
prs: [5452, 5454, 5459, 5460]
us: []
next_steps:
  - "Decidir se o detect da Constituição no governance-gate.yml ganha ^D/^R (2 linhas espelhando o padrão da ADR 0316; é gate REQUIRED, então PR próprio e ato de governança)"
  - "Fiação do Art. 20 (registro em mcp_audit_log + identificação ao titular + canal de revisão) nasce JUNTO com a primeira decisão automatizada real — nunca antes"
  - "Gaps G2/G3 do cascade audit: L2 SRS nunca implementada; tabela 'Estado de implementação' da Constituição ainda na v1.0.0 (ambos anteriores a este amendment)"
related_adrs:
  - 0372-audit-card-decisao-automatizada-titular-emenda-0094
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0363-governance-incorpora-ads-nucleo-sem-receptor
  - 0316-esquecimento-real-adr-morta-tombstone-git-auditoria
---

# Audit Card vira lei escrita — e o gate da Constituição mostra um buraco

## Estado MCP no momento do fechamento

Consultado em 2026-08-08 ~23:30 (checklist obrigatório da [how-trabalhar §Ao terminar](../how-trabalhar.md)):

- **`cycles-active`** → *"Nenhum cycle ATIVO em COPI"* — este trabalho não roda dentro de cycle.
- **`my-work` (@wagner)** → **10 tasks, todas em REVIEW**, nenhuma relacionada a este trabalho
  (US-TR-305/306/309/310/311 · US-PG-008 · US-PROD-027 · US-INFRA-023/048 · US-KB-002). Confirma o
  padrão já registrado em handoffs irmãos de hoje: **este trabalho não tinha task**.
- **`sessions-recent`** → 3 session logs de hoje (`deadlock-required-promocao-0370`,
  `fatia-d-jana-memoria-metodo`, `primary-os-btn-13-telas`), **nenhum** sobre Audit Card — sem
  duplicação; o desta sessão é [`2026-08-08-audit-card-principio-9-constituicao-v1.2`](../sessions/2026-08-08-audit-card-principio-9-constituicao-v1.2.md).
- **ADRs desde o último handoff** → a **0372** é a única nova, e é desta sessão.

## O que entrou em `main`

| PR | o quê |
|---|---|
| [#5454](https://github.com/wagnerra23/oimpresso.com/pull/5454) | `doc-id-index` regenerado (7 ADRs + 6 docs fora do índice; diff aditivo provado) |
| [#5452](https://github.com/wagnerra23/oimpresso.com/pull/5452) | ADR 0372 nasce |
| [#5460](https://github.com/wagnerra23/oimpresso.com/pull/5460) | flip `proposto → aceito` (1 linha, label `adr-metadata-normalization`) |
| [#5459](https://github.com/wagnerra23/oimpresso.com/pull/5459) | Constituição **v1.2.0** + `audit-2026-08-08-v1.2.md` |

Verificado na fonte, não no watcher (`git grep` em `origin/main`): `version: 1.2.0` ·
`charter_adr: 0094` · bullet `LGPD Art. 20 (decisão automatizada)` no Artigo 4 · rodapé `1.2.0` ·
audit existe · ADR 0372 `status: aceito`.

## Por que isso importava

A [0145](../decisions/0145-ia-administradora-pivot-ads-fsm-piloto-cobradora.md) declarou
`amends: [0094]` em maio; a [0363](../decisions/0363-governance-incorpora-ads-nucleo-sem-receptor.md)
§Herança escreveu que o princípio *"sobrevive à supersessão"* em julho. **A emenda nunca foi
redigida** — a lei existia só por referência em duas ADRs, uma delas `superseded`. Um agente lendo a
Constituição para saber o que é Tier 0 não a encontrava.

Agora o Artigo 4, que enumerava só **Art. 7º** e **Art. 18**, enumera o **Art. 20**.

## O que ficou aberto, e é decisão [W]

**O buraco `^D`/`^R` no detect da Constituição.** Medido: o job casa `^M` e `^A` sobre
`memory/governance/CONSTITUTION.md` — **não** casa delete nem rename. Prova histórica: o **#2413
deletou o arquivo inteiro (414 deleções, 2026-06-08) e o gate ficou mudo**; ele só voltou pelo commit
de restauração `8cd20a34863`. É o mesmo furo que a [ADR 0316](../decisions/0316-esquecimento-real-adr-morta-tombstone-git-auditoria.md)
já fechou **para ADRs** (`adr-tombstones.json`, "furo G5") sem espelhar para a Constituição.

O fix são 2 linhas espelhando um padrão já validado — mas alterar o `detect` de um gate **required**
é ato de governança com PR próprio, fora do escopo autorizado desta sessão. Declarado no §5
(`2026-08-08`) para não virar "descoberta" futura.

## Duas coisas minhas que registro sem enfeitar

1. **Publiquei duas vezes que "por ~15 meses qualquer PR travaria"** — e as duas metades caíram na
   medição: a duração real é **85 dias** (aritmética de cabeça que viajou pro corpo de um PR), e o
   diagnóstico estava **invertido** (gate com buraco, não gate apertado). Lápide no §5, LC-08 → **62**.
2. **Anunciei uma trava de ordem que não existia.** Disse ter deixado o #5459 sem auto-merge de
   propósito; ele já estava armado desde 22:53:45Z por `wagnerra23`. Se o CI dele tivesse fechado
   antes do flip, a Constituição teria gravado ratificação que ainda não havia. Não aconteceu por
   margem de tempo — sorte, não desenho.

## Overrides declarados

Três usos de `OIMPRESSO_MEMORY_OVERRIDE=1` sob autorização explícita de [W] (*"contorne e resolva
isso eu autorizo"*, *"pode fazer tudo sim"*): regra **G** (CONSTITUTION.md) e regra **B** (ADR
existente em disco, 2×). Cada um declarado no commit **e** no corpo do PR — o follow-up que o hook
exige. Não foi usado `Bash` para escapar do matcher `Write|Edit|MultiEdit`: seria evasão, não override.

## Pra quem pegar daqui

O princípio 9 é **prospectivo**: a exposição hoje é zero (`rg client_visible|audit_card_url` → rc=1,
com controle positivo). O dever se cumpre **junto** com a primeira decisão automatizada que atinja
titular — nunca depois dela, e nunca antes num corpus vazio (tela sobre corpus vazio é carimbo, e a
`US-COPI-127` está reancorada justamente por isso).
