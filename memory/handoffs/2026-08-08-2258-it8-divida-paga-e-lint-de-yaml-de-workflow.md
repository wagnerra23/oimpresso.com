---
date: "2026-08-08"
time: "22:58 UTC"
slug: it8-divida-paga-e-lint-de-yaml-de-workflow
tldr: "Delta do handoff das 21:53: [W] mandou fazer os 3 itens pendentes. IT8 fechou pagando a dívida (não congelando), e a medição derrubou minha própria hipótese sobre o órfão 'vivo'. O lint de YAML de workflow foi armado no dono existente, com contrafactual provado. O §12 passo 3 NÃO foi feito — o candidato que eu registrei não cobria o caso."
prs: [5457]
decided_by: [W]
next_steps:
  - "§12 passo 3 (promover gate a required) segue [W]: ADR 0336 DR-2 pede mordida provada, e há 1 dívida sustentada, não 2 mordidas distintas"
  - "Resíduo do IT8: a Opção A do espelhar-domínio (companion cockpit_domains.css) é Tier 0 aguardando [W] desde 2026-07-10"
---

# IT8: dívida paga · lint de YAML de workflow · e o item que a medição vetou

> **Delta** do [handoff das 21:53](2026-08-08-2153-teste-07-auditoria-15-e-a-lane-revivida.md), que fechou a auditoria e deixou 3 itens pra [W]. Ele respondeu **"faça"**. Este registra o que saiu — e o que **não** saiu, com o número que explica.

## Estado MCP no momento do fechamento

- `cycles-active`: **nenhum cycle ATIVO em COPI**.
- `my-work`: **5** tasks em REVIEW (caiu de 8 desde as 21:53) — nenhuma é deste trabalho.
- `decisions-search`: nenhuma ADR nova; o trabalho é execução de decisão já registrada.

## O que aconteceu

**1. IT8 verde — dívida PAGA, não congelada.** Os 3 órfãos de 2026-07-10 foram arquivados em `prototipo-ui/_arquivo/handoffs-processados/` com lápide (L-07/L-22). `handoff:check` **rc=1 → rc=0**, órfãos **3 → 0**, **sem tocar no baseline** — congelar está barrado na §5.

**A medição derrubou a minha própria hipótese, e essa é a parte que importa.** Eu tinha reportado **três vezes** que `DS-DOMINIO-RETIRAR-DSV6` (o único sem retorno em `CODE_NOTES`) era tarefa **viva** e devia ser enfileirado. Antes de enfileirar, medi o SSOT: os tokens que ele pede **já estão** em `resources/css/tokens/semantic.tokens.json` (canal 38 · kind 26 · kpi-feature 17 · origin 32 · sla 61 · stage 23), e os valores dele **regrediriam o canon** — `origin CRM bg` é `oklch(0.92 0.06 220)`, o prompt propõe `oklch(0.93 0.07 245)`. Enfileirar seria a regressão L-09/L-12. **Órfão sem retorno não é automaticamente tarefa viva** — pode ser tarefa que o mundo resolveu por outro caminho. Ficou escrito na lápide.

**2. Lint do `name:` que mata o workflow inteiro.** Estende `required-always-run.mjs` — que já pergunta *"o context required NASCE?"* e cujo caso **extremo** é o workflow que não parseia (não nasce **nada**). **Textual de propósito:** `js-yaml` não é dependência declarada e a lane não roda `npm ci`; vale a razão escrita pelo próprio autor do script.

**3. §12 passo 3 — NÃO feito.** Ver abaixo.

## Artefatos gerados

| PR | commit em `main` | conteúdo |
|---|---|---|
| [#5457](https://github.com/wagnerra23/oimpresso.com/pull/5457) | `3755baed9eb` (merge 22:55:22Z, **104 verdes / 0 falhas**) | 3 prompts arquivados + `_arquivo/handoffs-processados/INDEX.md` (lápide) + bloco histórico abaixo da linha d'água em `COWORK_NOTES.md` + o lint em `required-always-run.mjs` |

## Persistência

- **git**: #5457 mergeado; verificado **no `main`**, não no worktree.
- **MCP**: nada a atualizar — trabalho sem task associada.
- **BRIEFING**: não aplicável (nenhum `Modules/<X>` tocado).

## Verificação (contra `main`)

```
handoff:check (IT8)                 rc=0   órfãos 0/0
required-always-run (com o lint)    rc=0
integrity-check (§15)               rc=0
```

**FP medido ANTES de armar** (121 workflows): escopo `name:` → **0 hits**. O escopo largo daria 17, **todos legítimos** (comentário depois do valor, flow mapping) — por isso o escopo é `name:`, onde mora prosa livre.

**Contrafactual contra o incidente real:**

```
antes do #5424    0 achados
NO #5424          1 achado (linha 53)   ← teria barrado o merge
depois do fix     0 achados
```

**12 asserts**: bite (a linha real verbatim + CRLF) · 5 controles negativos · 2 E2E.

## Lições catalogadas

- **A hipótese que eu repeti 3× estava errada, e só a medição pegou.** "Órfão sem retorno ⇒ tarefa viva" é uma inferência sobre o gate, não um fato sobre o mundo. O fato veio de abrir o SSOT. Mesma família do LC-08: derivar em vez de medir — desta vez barrado antes de virar ação, o que é a diferença entre lição e incidente.
- **O candidato que eu mesmo registrei não cobria o caso.** No handoff das 21:53 nomeei "estender o eixo 1 do `cron-watchdog` pra olhar `conclusion`" como o par candidato do vão. Medido: `handoff-integrity.yml` tem **zero `schedule:`** e o `cron-watchdog` só enxerga runs **agendadas** — nunca teria coberto o IT8. Registrar candidato sem checar o alcance dele é a mesma doença, um nível acima.
- **Limite honesto do lint, escrito no código:** pega esta família (dois-pontos-espaço em `name:` não citado), **não** "todo YAML inválido" — indentação torta, tab e aspas abertas passam. E se o workflow quebrado for o que hospeda o lint, ele não roda pra se denunciar.

## Por que o §12 passo 3 não foi feito

Não é omissão — é medição:

1. o candidato registrado **não alcança** o caso (`handoff-integrity` não é agendado);
2. a população de sequência vermelha em `main` hoje é **só** o `governance-script-tests`, já consertado ⇒ um vigia **nasceria parado**, o anti-padrão `foundation-ratchet`;
3. promover gate a required exige **mordida provada** (ADR 0336 DR-2) e há **1 dívida sustentada de 29 dias**, não 2 mordidas distintas — e o §5 2026-07-01 barra promover não-Tier-0 sem reabrir a política.

**É decisão [W], e agora com o número na mesa.**

## Próximos passos pra retomar

```
git fetch origin main && node scripts/handoff-integrity-guard.mjs && node scripts/governance/required-always-run.mjs
```

Os dois devem sair **0**. Se o `handoff:check` voltar a 1, é órfão **novo** — cheque se pousou (`CODE_NOTES.md`) ou se ficou stale **antes** de enfileirar, e nunca rode `baseline:write` (§5).

## Pointers

- Auditoria que originou os 3 itens: [handoff 21:53](2026-08-08-2153-teste-07-auditoria-15-e-a-lane-revivida.md)
- Lápide dos 3 órfãos: [`_arquivo/handoffs-processados/INDEX.md`](../../prototipo-ui/_arquivo/handoffs-processados/INDEX.md)
- Método: [`PROCESSO_MEMORIA_CC.md`](../../prototipo-ui/PROCESSO_MEMORIA_CC.md) §5 · §6 TESTE-07 · §12 · §16
