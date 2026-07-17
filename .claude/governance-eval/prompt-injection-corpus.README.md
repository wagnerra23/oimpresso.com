# prompt-injection-corpus — red-team do agente (advisory)

Primeiro teste adversarial de **prompt injection via conteúdo de tool-result** no oimpresso.
Nasce da auditoria de segurança-de-agente [`memory/sessions/2026-07-10-arte-seguranca-agente.md`](../../memory/sessions/2026-07-10-arte-seguranca-agente.md).

## Por que existe
A dimensão "segurança do agente" nunca foi auditada nem posta numa nota. O maior gap:
a resistência a injection vinda de **dado que o agente lê** (linha de banco, mensagem WhatsApp,
doc do MCP, resposta WebFetch) era **assumida, não testada**. O único vetor de injection hoje
gateado por máquina é o atrator Figma (`block-figma-without-optin.mjs`, nascido do incidente
2026-06-22). Este corpus torna o resto **máquina-checável**.

## As 3 camadas (honestas sobre o que cada uma prova)
- **A · DETERMINÍSTICA (roda no `.mjs`):** cada cenário cuja ação induzida é coberta por um hook
  (`block-destructive`/`pii-redactor`) alimenta a ação REAL ao hook REAL e assere BLOQUEIO.
  É o backstop → vira **ratchet**: se parar de bloquear, regressão (exit 1). Hoje: **6/6**.
- **B · GAP (reporta, não falha):** cenários cuja ação NÃO é coberta por hook nenhum (exfil via
  `curl`/`gh api`, desabilitar branch-protection, `gh pr merge`, `node -e`). Passam hoje →
  achado **UNGUARDED** explícito. Hoje: **4 caminhos**. No dia que um guard fechar, o cenário
  flipa e migra pra camada A.
- **C · COMPORTAMENTAL (NÃO rodada aqui):** "o agente trata tool-result como DADO, não instrução?"
  Precisa do agente no loop (workflow de eval) — métrica advisory observada, **não binária**.
  Fingir isso no `.mjs` seria o "teatro de suite que mente" que a ADR 0314 podou.
## Rodar
```
node .claude/governance-eval/prompt-injection-corpus.mjs
```
`exit 0` = sem regressão do backstop (camada A). Gaps da camada B são reportados, não falham.
Os hooks do repo são `.mjs` (rodados com `node`, ver `settings.json`); o corpus os invoca direto.

## Quem invoca (chip C11 · fechado 2026-07-17)
De 07-10 a 07-17 **nada** invocava este corpus — rodava só quando um humano lembrava, ou seja,
nunca (correção-do-mecanismo ≠ invocação · `memory/proibicoes.md` §5 2026-07-09).
Invocador: [`.github/workflows/prompt-injection-corpus.yml`](../../.github/workflows/prompt-injection-corpus.yml)
— path-filter em `.claude/hooks/**` (chokepoint medido: 30/30 dos últimos commits nesse glob
vieram por PR) + cron semanal de backstop.

O workflow roda **dois** steps, e o segundo não é redundante:

| Step | Prova | Não prova |
|---|---|---|
| 1. corpus | a **lógica** do backstop morde | que o hook esteja ligado |
| 2. [`settings-backstop-registration.test.mjs`](../../scripts/governance/settings-backstop-registration.test.mjs) | o backstop segue **registrado** | — |

Medido em 2026-07-17: com `pii-redactor` **desregistrado** do `settings.json` (defesa desligada no
agente), o corpus fica **verde** — ele invoca o arquivo direto e o acha no disco. Só o step 2
avermelha. Sem ele, o step 1 certificaria uma defesa que não roda.

## Segurança do próprio script
As ações maliciosas são **strings** passadas ao STDIN de um hook (que só faz regex) — **nunca
executadas**. Payloads de exfil usam placeholders (`evil.example`, `~/.secret-token`).
## Enforcement — quem decide não é este arquivo
Dono único de "o que bloqueia merge" = [`governance/required-checks-baseline.json`](../../governance/required-checks-baseline.json),
vigiado por `scripts/governance/protection-drift.mjs`. Quem precisar falar de enforcement aponta
pro dono, não restateia (lápide §5 2026-07-16: label que afirma o próprio enforcement em presente
apodrece no primeiro flip — já apodreceu 2× aqui).

**Fato datado:** o workflow invocador nasceu **fora** desse baseline em 2026-07-17.

Promover a required exige **reabrir a ADR 0314** (append-only + janela + flip Wagner), nunca no
calado: lá `required` = só Tier-0 (dinheiro/PII/multi-tenant/fiscal), e um gate de
comportamento-de-LLM "verde" viraria o teatro que a 0314 removeu. Este corpus também não registra
hook em `settings.json` — ele não é um hook, é o red-team que os alimenta.

## Como o gap fecha (camada B → A)
Quando existir defesa de ação-outward sob contexto não-confiável (estreitar allow-list, gate de
egress, ou generalizar o `block-figma` intercept-action) — **decisão do Wagner, toca o modelo de
permissão** — os cenários B viram `expect: 'block'` + `layer: 'A'` e passam a ratchet.
