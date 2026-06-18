---
slug: 0279-sdd-medir-governar-floor-nightly
number: 279
title: "Fechar o elo MEDIR→GOVERNAR do floor (transporte CT100 → scorecard, Opção A)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: meta
decided_by: [W]
decided_at: "2026-06-15"
module: governance
tags: [governanca, sdd, scorecard, floor, nightly, ct100]
supersedes: []
superseded_by: []
related:
  - 0062-separacao-runtime-hostinger-ct100
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0278-arquitetura-rede-ia-duravel-anti-vazamento
---

> **Numerado e aceito por [W] em 2026-06-15** (ADR 0238: numerar = aceitar) na
> **Opção A** (cron git-push `[skip ci]` de `governance/nightly-floor.json`; repo = SSOT).
> O **read-side** (PR-1) está rastreado em **US-GOV-023** (D2); o **write-side** (PR-2, CT100) e
> o detalhe de credencial/shape do push seguem como execução em aberto no ledger SDD §1.

# ADR 0279 — fechar o elo MEDIR→GOVERNAR do floor (transporte CT100 → scorecard)

> **Origem:** avaliação adversarial SDD 2026-06-15 (composto 61.9). Risco sistêmico #1:
> *"mede mas não governa"*. [W] numera se aceitar (ADR 0238).

## Problema (causa-raiz, verificada no código)
- `scripts/governance/sdd-scorecard.mjs:112` **hardcoda** `full_suite_pass_rate = notYet(...)`
  com o comentário *"nenhum run full-repo MySQL jamais foi salvo"* — **factualmente falso**:
  o CT100 salvou ~15 runs. A métrica-mãe do programa fica `not_yet_measured` pra sempre.
- `scripts/tests/ct100-fullsuite.sh` produz `junit.xml` + `summary.json` + `run.log` em
  `/opt/oimpresso-fullsuite/runs/<ts>/` **no CT100** e **para por aí** — nenhum `git/curl/scp`
  leva o número de volta. O scorecard roda no Hostinger/CI e lê **arquivos do repo** (é assim
  que `ghost_count`/`front_door` chegam) → não enxerga o CT100 ([ADR 0062] separa os runtimes).

**Conclusão:** falta um TRANSPORTE do floor do CT100 pra uma fonte que o scorecard leia.
Sem ele, nenhuma promoção da Semana 4-6 (R1) tem dado pra atingir critério.

## Definição de "floor" (regra do próprio SPEC US-GOV-018, não inventar)
Floor = **interseção dos conjuntos de arquivos-que-falham entre ≥2 runs** com seed fixo
(o número de 1 run é não-determinístico, banda ~856). Logo o artefato precisa **acumular**
os últimos N conjuntos de falha, não só o último número.

## Opção A — nightly commita `governance/nightly-floor.json` (RECOMENDADA)
1. `ct100-fullsuite.sh` (passo novo 8/8), após o run: distila `summary.json` → extrai o
   conjunto de arquivos-que-falham + counts (passed/failed/errors/skipped) + `sha` + `ts`;
   acumula numa janela (últimos 2-3 runs); computa `floor = interseção`; escreve
   `governance/nightly-floor.json` `{ floor_count, floor_files_hash, runs:[{sha,ts,failed,errors,skipped}], computed_at, intersection_of }`.
2. Commita no repo via push dedicado **`[skip ci]`** (não dispara a suíte de PR; é dado, não código).
3. `sdd-scorecard.mjs`: lê `governance/nightly-floor.json` p/ `full_suite_pass_rate`+floor;
   **fallback `notYet` se ausente** (zero-risco até a 1ª escrita). Mata o hardcode/comentário falso.
4. Ratchet **arma** o floor após 3 medições válidas (mecânica existente, ADR 0275 §3).

- **Prós:** repo = SSOT (consistente com ghost/front_door); scorecard fica determinístico;
  ratchet protege regressão; auditável no git.
- **Contras/decisões:** a nightly precisa de credencial de push (já clona via `REPO_URL`);
  ruído de 1 commit/dia (mitigado por `[skip ci]` + path dedicado + branch ou commit-bot).

## Opção B — nightly faz POST num endpoint MCP → `mcp_sdd_scorecard_history`
A nightly `curl`a o floor pro MCP; o `SddScorecardSnapshotCommand` (G7) já lê a history table.
- **Prós:** sem commits de cron; alinha com G7/G8 que já leem DB.
- **Contras:** precisa endpoint novo + auth do CT100; o `sdd-scorecard.mjs` (node, sem DB) não
  lê DB → a leitura migraria pro command PHP (muda quem é a fonte única).

## Opção C — GitHub artifact + workflow agendado
A nightly sobe artifact; um workflow lê e atualiza. Rejeitada: a nightly é cron CT100, **não**
GH Actions (não há workflow versionado) — reintroduz a dependência que o ADR 0062 evita.

## Decisão que é do [W] (a única)
**Transporte: Opção A (cron git-push `[skip ci]`) vs Opção B (POST → MCP DB).**
Recomendo **A** — repo=SSOT é consistente com como as outras métricas vivas já entram, e mantém
o `sdd-scorecard.mjs` como fonte única em node. B é melhor se você quiser zero-commit-de-cron.

## Fatiamento (após [W] escolher)
- **PR-1 (read, seguro, testável local):** `sdd-scorecard.mjs` lê `governance/nightly-floor.json`
  com fallback `notYet` + meta-teste 2 lados (com-arquivo→measured, sem→notYet) + mata o comentário falso.
- **PR-2 (write, CT100):** passo no `ct100-fullsuite.sh` que acumula+computa+commita. Validado na
  nightly real (não dá pra testar local — CT100 only).
- **PR-3:** após 3 medições válidas, **armar** o ratchet do floor.

## Não-objetivos
- NÃO promover nada a required (Semana 4-6) — fora de escopo; depende disto + ≥2 medições honestas.
- NÃO unificar `anchor_coverage` (3 valores) — risco #4 separado, proposta própria.

## Emenda 2026-06-18 — realidade da implementação (append-only)
O texto da Opção A acima assumia `git push [skip ci]` do `nightly-floor.json` **direto** (repo=SSOT,
implícito no `main`). Na implementação isso colidiu com **dois fatos verificados**:
1. **`main` é protegido** (`enforce_admins:true` + 18 required checks) → **nenhum** push direto ao `main`
   é aceito, nem de admin. O floor não pode aterrissar no `main` por push de cron.
2. **Abrir/auto-mergear PR** precisaria de um **token de conta** (PAT/App) — não mintável por automação.

**Resolução (mantém a Opção A — repo como fonte, sem tocar a proteção do `main`):**
- O floor é publicado numa **branch órfã dedicada `governance/nightly-floor`** (só o arquivo, sem
  histórico → imune a push de clone shallow), via **deploy key WRITE** em `/root/.ssh/oimpresso_floor_deploy`
  no CT100 (gerada no CT100, chave privada nunca sai de lá; revogável em Settings→Deploy keys).
- O **scorecard MATERIALIZA** o arquivo no CWD em CI (`git fetch origin governance/nightly-floor` +
  `git show FETCH_HEAD:governance/nightly-floor.json`) antes de medir; o read-side (`measureFullSuiteFloor`)
  é inalterado (lê o arquivo no working tree). `governance/nightly-floor.json` é **gitignored** no `main`.
- Write-side: `scripts/tests/floor-compute.mjs` (interseção ≥2 runs válidos) + step `[floor]` no
  `ct100-fullsuite.sh`. <2 runs válidos → `floor_count: null` → read-side `not_yet_measured` (nunca mente 0).
- Fatiamento real: PR-1 read (#2958) · PR-2 write-side + transporte (este) · PR-3 armar ratchet após 3 medições.
