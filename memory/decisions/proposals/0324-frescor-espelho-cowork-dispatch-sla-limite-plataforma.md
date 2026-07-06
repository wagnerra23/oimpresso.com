---
slug: 0324-frescor-espelho-cowork-dispatch-sla-limite-plataforma
number: 324
title: "Modelo operacional de frescor do espelho Cowork — dispatch logado com SLA + ledger, limite de plataforma DesignSync, PR-bot regenerador como V2"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-06"
module: design-system
supersedes: []
superseded_by: []
related:
  - 0315-design-sync-claude-design-vs-cowork-charter
  - 0298-teto-de-governanca-anti-proliferacao-gates
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
pii: false
---

> **Proposta por [CL] em 2026-07-06** (Wagner: "continue" sobre a fila do estado-da-arte). Ratificação = merge por [W].
> Executa as ações **#3+#4+#5** do [estado-da-arte 2026-07-06](../../sessions/2026-07-06-arte-design-code-sync-frescor.md) num doc só (1 tema = 1 doc; a pilha de proposals já está em 91). As ações #1+#2 (identidade canônica: path completo + hash normalizado; correção do §0.2) já mergearam (PR #3882).

# ADR 0324 — Frescor do espelho Cowork: dispatch logado com SLA (agora) · limite de plataforma · PR-bot (V2)

## Contexto

O espelho `prototipo-ui/cowork/` é a cópia local do design vivo (Cowork, projeto `019dcfd3…` — [INDEX §0.2](../../requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md)). O §0.2 manda **"diffar antes de concluir"**; a ferramenta que automatiza o diff existe e usa identidade canônica (`cowork-mirror-freshness.mjs` v2, `sha256(normalizado)` por path completo — #3882). Faltava decidir **como essa rotina OPERA** sem virar teatro:

- A v1 morreu no adversário também por isto: um selftest advisory no CI **não mede frescor** ("a suite mente" — prova a forma, não a correção viva). A [ADR 0298](0298-teto-de-governanca-anti-proliferacao-gates.md) bane "advisory-eterno" que finge proteger.
- O estado-da-arte 2026 detecta drift por **webhook push** (Figma) ou **bot-PR** (Tokens Studio/DTCG) — nunca por polling manual. Nenhum dos dois é possível hoje aqui (ver D3).

## Decisão

### D1 — Frescor = ROTINA DE DISPATCH LOGADO, com ledger datado

Quem mede frescor é um **agente logado** (Claude Code com escopo claude.ai/design), não o CI:

1. `node scripts/governance/cowork-mirror-freshness.mjs --manifest` → lista de âncoras + hash do repo.
2. `DesignSync.get_file` por path no projeto vivo → snapshot `{relPath: contentHash(content)}` (**mesma** normalização — importar `contentHash`/`normalize` do módulo; hashear só conteúdo **persistido em arquivo**, nunca "de memória").
3. `--compare snap.json --check --ledger` → veredito + **registra a rodada** em `scripts/governance/.cowork-freshness-ledger.json` (append-only, commitado).
4. STALE ⇒ re-exportar do Cowork pelo fluxo canônico (nunca editar o espelho à mão) e rodar de novo.

`UNCHECKED` é veredito honesto de rodada parcial — snapshot incompleto NUNCA vira SYNC no silêncio.

### D2 — O CI mede CADÊNCIA, nunca frescor (advisory honesto, 0298-conforme)

Dois steps advisory no `design-memory-gate.yml` (workflow **existente** — zero workflow novo, a torneira da 0298 não abre):

- **selftest** — prova que o comparador classifica certo (46 asserts de contrato, incl. colisão-de-path e CRLF).
- **`--sla`** — lê SÓ o ledger (headless-safe): **NEVER-RAN** / **OVERDUE** (>14d) / **LAST-STALE** (última rodada achou divergência não-resolvida) ⇒ vermelho advisory; **FRESH** ⇒ verde. O step declara no comentário que **não mede frescor** — mede se a rotina anda rodando e se o último resultado ficou limpo. É a "freshness da freshness-check": o que o CI PODE provar sem auth.

**SLA = 14 dias.** Mudança de SLA = editar `SLA_DAYS` + este ADR.

### D3 — Limite de plataforma (registrado pra ninguém re-propor cron/webhook)

Verificado nesta sessão + [0315 §Furos](0315-design-sync-claude-design-vs-cowork-charter.md): a integração DesignSync **não expõe webhook**, **não tem service-account/token headless**, e a auth (`/design-login`) é interativa. Portanto **webhook push e cron-com-secret são IMPOSSÍVEIS hoje** — o teto viável é o dispatch logado (D1). Re-propor cron/webhook só quando a plataforma mudar (aí sim, superar esta ADR).

### D4 — V2: PR-bot regenerador (modelo Tokens Studio) — direção, não implementação

O alvo de longo prazo é o padrão vencedor de 2026: **design vivo é a fonte; o git recebe o espelho GERADO por export→PR** — o diff do PR *é* a detecção de frescor, sem gate especial. Realiza a intenção do Wagner ("viver da API") do jeito que os líderes fazem: as cópias **ficam** (CI/offline/histórico), mas viram **saída regenerável**, não artefato mantido à mão. **Gatilhos pra implementar:** (a) o ledger acumular rodadas provando a cadência; (b) Wagner cravar a pendência do §0.2 ("antigo" = direção a redesenhar?). Até lá, D1-D3 são o modelo vigente.

## Não-goals

- ❌ Não vira gate **required** — advisory de cadência num workflow existente (0298: required = só Tier-0).
- ❌ Não muda a fonte de design (§0.2 intacto: Cowork é a fonte, espelho em sincronia).
- ❌ Não implementa o PR-bot agora (D4 é direção com gatilhos explícitos).
- ❌ Código **não cita** este ADR (proposto) — memory-health Check L: código que roda só referencia §0.2/session; este ADR aponta pro código, nunca o inverso, até ratificação.

## Validação (executada — dispatch REAL desta sessão)

- ✅ Primeira rodada real registrada no ledger (2026-07-06): **1 SYNC** (`financeiro-page.jsx` — hash vivo `e2aa76e7…` == repo, sob identidade normalizada) · **2 UNCHECKED** (conteúdo vivo veio inline no transcript, não-persistido → sem hash fiel; honestidade > fingir SYNC) · **0 STALE**.
- ✅ `--sla` pós-rodada: **FRESH** (há 0d). Com ledger vazio: **NEVER-RAN** exit 1 (provado).
- ✅ Selftest **46 asserts** verdes (identidade + vereditos + ledger/SLA + read-only 0315).
- ✅ memory-health local: 0 🔴 (código não cita ADR proposto).

## Consequências

✅ O "gate de frescor" deixa de ser teatro: o CI afirma só o que prova (cadência+último resultado), o dispatch logado mede o real, e o ledger é evidência datada. ✅ Cron/webhook impossíveis ficam registrados — mata re-proposta cega. ✅ Caminho V2 (bot-PR) fica decidido em direção, com gatilhos.
⚠️ Frescor depende de disciplina de dispatch (mitigado: OVERDUE avermelhará o advisory em ≤14d). ⚠️ Rodadas parciais (UNCHECKED) são possíveis — o ledger as expõe, não as esconde.
