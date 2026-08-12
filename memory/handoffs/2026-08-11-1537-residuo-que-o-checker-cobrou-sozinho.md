---
slug: residuo-que-o-checker-cobrou-sozinho
date: "2026-08-11"
time: "15:37 BRT"
tldr: "Removida 1 das 3 entradas de residuos_irrecuperaveis do ledger de réguas — o próprio reguas-ledger-check cobrou a remoção (mecanismo anti-tapete do #5484 funcionando). rc=0 antes e depois nos 2 modos do job. Os 2 irrecuperáveis do #4820 intactos. Lição do dia: gh pr checks --watch --fail-fast saiu 0 com o CI vermelho."
autor: "[CL] Claude Code"
sessao: angry-varahamihira-8a3d76
prs: [5605]
decided_by: [W]
next_steps:
  - "Fila de reguas-indexar tem 11 achados existia-mas-invisível pendentes (medida, não trabalhada)"
---

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → **10 tasks, todas em REVIEW** (US-TR-309/310/311/305/306, US-PROD-025/027, US-INFRA-023/048, US-KB-002)
- Handoffs irmãos do dia: `1810-scope-sai-de-modules`, `1345-ucs-do-preview-v3`, `1336-fronteira-que-nao-era`, `1245-prototipo-jana-no-git`
- `origin/main` no fechamento: `babb06d6051`

## O que aconteceu

Tarefa de escopo travado: o `reguas-ledger-check --check` imprimia, na própria saída, um pedido de remoção:

> 🟢 resíduo declarado 2026-07-26/diferencial_sistema NÃO ocorre mais — remova de `config.json` `residuos_irrecuperaveis` (exceção paga vira tapete se ficar).

É o mecanismo anti-tapete do [#5484](https://github.com/wagnerra23/oimpresso.com/pull/5484) fazendo o que foi desenhado pra fazer: exceção que deixou de ser necessária é **cobrada**, senão a lista de resíduos vira allowlist que só cresce. Confirmei que a mensagem ainda aparecia (o pedido mandava parar se já tivesse sido feito), removi **só** a entrada apontada, e reconferi.

Rodei os **dois** modos que o job `governance-script-tests.yml` invoca (`:311` `--selftest`, `:318` `--check`) — não só o do pedido, por causa da lápide §5 2026-07-28 (*"validar um gate rodando UM dos modos que o CI roda"*).

| | antes | depois |
|---|---|---|
| `--check` | rc=0 | **rc=0** |
| violações NOVAS | 0 | **0** |
| declaradas | 3 | **2** |
| linha 🟢 do resíduo | presente | **sumiu** |

Os 2 IRRECUPERÁVEIS do [#4820](https://github.com/wagnerra23/oimpresso.com/pull/4820) seguem intactos com `motivo` + `fonte_que_resolveria` (`journal.jsonl` do run `wf_db242261-298`, não versionado): `2026-07-26/refutado_tb` e `2026-07-18/diferencial_sistema`. `retratos.json` e `claims.json` não foram tocados (append-only por convenção do ledger).

Detalhe que vale registrar: o resíduo removido e um dos que ficaram são **da mesma rodada** (2026-07-26), e o `motivo` do removido dizia *"mesma causa da linha acima"*. A remoção **não** é sobre a causa ter sido resolvida — é sobre aquela **contagem específica** ter parado de divergir. Os dois campos são independentes no checker.

## Artefatos gerados

| Arquivo | Δ | Canon |
|---|---|---|
| `memory/reguas/config.json` | −8 linhas (1 entrada) | ledger de réguas ([ADR 0353](../decisions/0353-maquina-evolucao-reguas-looping.md)) |

PR [#5605](https://github.com/wagnerra23/oimpresso.com/pull/5605) — **mergeado por [W]**. 99 pass · 0 fail · 2 skipping (crons agendados).

## Lições catalogadas

**1. `gh pr checks --watch --fail-fast` saiu 0 com o CI vermelho.** A conferência independente logo depois deu `rc=1 · 1 fail · 3 pending`. Se eu tivesse aceitado o `--watch` sozinho, teria reportado verde sobre um `fail` real. **`--watch` não é oráculo de conclusão neste repo** — reconferir com `gh pr checks` puro. É a forma já catalogada em LC-08: o instrumento respondeu uma pergunta *parecida* com a feita e devolveu um número.

**2. Meu próprio grep de triagem produziu falso-positivo.** `grep -E '\bfail\b'` casou `fail-class` **dentro do NOME** do check `memory-health (enforce — fail-class bloqueia)`, que estava `pass`. Estado de check se lê por **coluna** (`awk -F'\t' '$2!="pass"'`), nunca por substring do nome.

**3. O `fail` era flake de infra, e a distinção required×advisory importou.** `BRIEFING (memory/requisitos/*/BRIEFING.md) [grace]` morreu no `actions/checkout` com `server certificate verification failed` (3 retries) — o validador nunca rodou (`No files were found: violations.json`). E **não é required**: conferi a **união** `classic_protection.contexts ∪ rulesets.contexts` (43 contexts, §5 2026-08-08), e o required com "BRIEFING" no nome é outro — `Modulo backend com BRIEFING (cobertura)`, que passou. Resolvido com `gh run rerun --failed`.

## Próximos passos pra retomar

Nada em aberto desta tarefa. Medi (e **não** trabalhei) a fila do Órgão 4:

```bash
node scripts/governance/reguas-indexar.mjs
```

**11 achados "existia-mas-invisível" pendentes** em 2026-08-08 — a maioria é correção de ponteiro em `memory/reguas/fraquezas.json` + indexar máquina que já existe no dono certo (`PAINEL-SISTEMA.md` via `system-map.mjs`, `component-registry.json`), com aviso explícito de **não** abrir gate novo. Um deles é acionável e caro: `custo-denominador-outcome` diz que **`claude-opus-5` falta em `PRECOS_USD_MTOK`, então toda medição de dinheiro sai $0** — mas isso já tem lápide própria no §5 (shipou em [#5485](https://github.com/wagnerra23/oimpresso.com/pull/5485)), então **conferir antes de agir**: o ledger pode estar stale.

## Pointers detalhados

- Mecanismo anti-tapete + `residuos_irrecuperaveis` como DADO: [#5484](https://github.com/wagnerra23/oimpresso.com/pull/5484)
- Reconciliação original 6→3 violações: [#4820](https://github.com/wagnerra23/oimpresso.com/pull/4820)
- Checker: [`scripts/governance/reguas-ledger-check.mjs`](../../scripts/governance/reguas-ledger-check.mjs) (`--check` · `--selftest`)
- Máquina de réguas: [ADR 0353](../decisions/0353-maquina-evolucao-reguas-looping.md)
