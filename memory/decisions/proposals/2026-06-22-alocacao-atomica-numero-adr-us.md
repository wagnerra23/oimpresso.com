---
proposal_id: alocacao-atomica-numero-adr-us
status: accepted
created: 2026-06-22
proposed_by: claude-code
decided_by: wagner
decided_at: 2026-06-22
realized_by: 0304-alocacao-numero-ciente-trabalho-em-voo
parent_adr: 0094
related_adrs: [0028, 0180, 0257, 0271, 0298, 0303, 0304]
type: governanca-mecanismo
origem: sessão 2026-06-22 (re-montagem do tijolo anchor-fidelity → 3 colisões de número numa só sessão)
---

# Proposta · Alocação de número (ADR/US) ciente de trabalho em voo — prevenir na fonte

> **Status:** 🟢 **ACCEPTED 2026-06-22** — aprovada por Wagner ("aprovado merge"); realizada na [ADR 0304](../0304-alocacao-numero-ciente-trabalho-em-voo.md).
> Origem: ao re-montar o tijolo anchor-fidelity (PR #3240), **3 colisões de número numa única sessão** — ADR 0297→0302→0303 (0297-excecao e doneness-lint #3239 tomaram 0297/0302 em paralelo) e US-GOV-043→044 (043 já era o charter_refs da onda-0). Wagner: *"verdade, precisa"*.

## Contexto

A colisão de número é **crônica**, não pontual. `_INDEX-LIFECYCLE.md` registra **14 colisões de ADR** (`0101, 0102, 0119, 0126, 0141, 0170, 0178, 0180, 0195, 0216, 0235, 0236, 0246, 0294`). A [ADR 0028](../0028-adrs-numeracao-monotonica.md) exige número único — *"não cumprido em 14 casos"*.

A causa-raiz é uma só: **a alocação é cega**. Quem cria uma ADR/US escolhe "próximo livre" lendo a `main` canônica, sem enxergar:
- branches não-mergeadas que já reservaram um número (ex: onda-0 usava 0297 + US-GOV-043);
- PRs abertos em paralelo que acabaram de tomar o número (ex: doneness-lint #3239 pegou 0302 enquanto eu rebaseava).

Hoje a defesa é **só reativa e só pra ADR**:
- `memory-health.mjs` **Check A** (gate required) falha em colisão de ADR não-registrada — mas só **depois** que o número já foi commitado.
- **US-IDs não têm detecção nenhuma** (não há equivalente do Check A pra `US-*`).

E o agravante: pela regra **append-only** ([ADR 0257](../0257-adr-status-lifecycle-kind-modelo-canonico.md)), uma vez commitada, a colisão **não pode ser renumerada** — vira permanente, só "registrada" (é por isso que há 14 e não 0). Logo, **detectar tarde não basta**: a alavanca real está na **alocação** (escolher certo antes de commitar). Esta sessão só não virou a 15ª colisão porque renumerei 3× manualmente antes do CI.

## Decisão proposta

Duas peças, ambas **forward-looking** (prevenir novas; as 14 existentes seguem registradas — append-only):

### 1. `scripts/governance/next-id.mjs` — alocador ciente de trabalho em voo

CLI determinística, Node puro + `gh`:

```
node scripts/governance/next-id.mjs adr            # → próximo número de ADR livre
node scripts/governance/next-id.mjs us <MODULE>    # → próximo US-<MODULE>-NNN livre
```

Calcula o próximo livre considerando **três fontes**, não só a canônica:
1. **Canonical** — maior número commitado na `main` (ADRs em `memory/decisions/`, US no `SPEC.md` do módulo).
2. **PRs abertos** — `gh pr list --state open --json headRefName` + varredura dos arquivos tocados (ADR files / SPEC.md) de cada PR pra colher números já reivindicados.
3. **Branches locais** — refs não-mergeadas com ADR/US novos.

Retorna o menor número acima de **todos**. Wired em:
- skill **`pre-adr-introspect`** (passo "número do ADR" passa a chamar `next-id adr`);
- fluxo de criação de ADR + o guidance do `tasks-create` (MCP) no CLAUDE.md "Como propor mudança".

**Honesto sobre o limite:** é **redução, não eliminação** — há uma janela de corrida (duas sessões rodando no mesmo minuto). O resíduo continua coberto pela rede reativa (peça 2). Mas mata ~95% dos casos, que são "branch parada há dias com número reivindicado".

### 2. Estender o Check A do `memory-health` a US-IDs (sem gate novo)

`checkAdrCollisions()` ganha um irmão `checkUsCollisions()` **dentro do mesmo `memory-health.mjs`** — varre todos os `SPEC.md`, falha em `US-<MOD>-NNN` duplicado. **Deliberadamente NÃO é um gate novo** — respeita o **teto de governança anti-proliferação** ([ADR 0298](../0298-teto-de-governanca-anti-proliferacao-gates.md)). Mesma catraca required, cobertura simétrica ao ADR. Fixture good/bad no `gate-selftest` (GT-G6, ADR 0256).

## Consequências

- ✅ Colisão deixa de nascer na fonte (alocador vê PRs/branches) → estanca o crescimento dos 14.
- ✅ Fecha a lacuna de detecção de US-ID, simétrica ao ADR, **sem proliferar gate** (ADR 0298-compliant).
- ✅ Determinístico, Node + `gh`, sem DB/PHP — encaixa no padrão das catracas.
- ⚠️ `next-id` depende de `gh` autenticado (ok no ambiente desktop/CI; degrada pra "canonical-only" com aviso se `gh` faltar).
- ⚠️ Janela de corrida residual permanece — coberta pela peça 2 no CI. Não se promete atomicidade total sem um ledger (ver Alternativas).
- ⚠️ As 14 colisões existentes **não** são corrigidas aqui (append-only) — seguem registradas em `_INDEX-LIFECYCLE.md`.

## Alternativas consideradas

1. **Ledger de reserva** (arquivo canônico onde se reserva o número antes de trabalhar; reservas paralelas = conflito git visível). Prevenção mais forte (atômica de fato), mas **alta fricção** (commit de reserva antes de começar) — pesado pro ritmo do time. Rejeitado por ora; fica como evolução se o alocador+Check A não bastarem.
2. **IDs não-sequenciais (ULID/timestamp/hash)** — elimina colisão por construção, mas quebra a convenção humana "ADR 0297" e **toda referência existente**. Rejeitado (disruptivo demais).
3. **Só melhorar a detecção (sem alocador)** — não resolve: a detecção já existe pra ADR e mesmo assim há 14, porque append-only torna a colisão permanente uma vez commitada. O gargalo é a **alocação**, não a detecção.

## Referências

- [ADR 0028](../0028-adrs-numeracao-monotonica.md) — numeração única (a regra que esta proposta operacionaliza) · [ADR 0180](../0180-drift-numero-adr-0178-conflito-paralelo.md) — registro de colisão · [ADR 0257](../0257-adr-status-lifecycle-kind-modelo-canonico.md) — append-only/lifecycle (por que renumerar tarde é proibido)
- [ADR 0298](../0298-teto-de-governanca-anti-proliferacao-gates.md) — teto anti-proliferação (por que a detecção de US entra no Check A, não num gate novo) · [ADR 0271](../0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) — advisory→required
- `scripts/governance/memory-health.mjs` (Check A) · `scripts/governance/adr-index-generate.mjs` (contagem de colisão) · `_INDEX-LIFECYCLE.md` (`numbering_collisions`)
- Origem: sessão 2026-06-22 — PR #3240 (anchor-fidelity, ADR 0303) gerou 3 colisões (0297→0303, 0302/doneness, US-GOV-043→044)
