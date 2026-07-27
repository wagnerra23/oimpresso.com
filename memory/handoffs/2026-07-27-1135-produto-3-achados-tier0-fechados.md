---
date: "2026-07-27"
time: "11:35 BRT"
slug: produto-3-achados-tier0-fechados
tldr: "Os 3 achados Tier 0 que o #4823 deixou para [W] foram provados por dois caminhos independentes (estático + lane MySQL real) e apresentados antes de aplicar. Dois fecharam com UC verde: preço de venda 0 em 4 telas e gravação cross-tenant no bulkUpdate. O terceiro (rota fantasma) não fecha sozinho — o cálculo mostrou ganho zero e custo alto, e ficou registrado pra viajar junto com o UC-PBULK-05."
prs: [4832, 4845, 4848]
decided_by: [W]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0104-processo-mwart-canonico-unico-caminho, 0264-governanca-executavel-trio-dominio-e2e]
next_steps:
  - "Fechar o UC-PBULK-05 (payload 2x5 campos) — leva junto a linha do BulkEdit.tsx, e o gate visreg para de cobrar contrato por escopo global"
  - "Decidir o destino da edição em massa: vive, morre ou vira /unificado (pendente em PARIDADE-charter-vs-legado.md)"
  - "UC-PSHOW-01 segue vermelho: ficha entrega custo a quem não tem permissão — é o maior risco entre os 15 restantes"
---

# Handoff 2026-07-27 11:35 — os 3 achados Tier 0 do #4823

## Estado MCP no momento do fechamento

- **`cycles-active`:** nenhum cycle ATIVO em COPI (trabalho off-cycle)
- **`my-work`:** 8 tasks em REVIEW — `US-TR-309/310/311`, `US-PG-008`, `US-PROD-027`, `US-TR-305/306`, `US-PROD-025`. **Nenhuma tocada nesta sessão** (o trabalho veio de achados do #4823, não de task)
- **`decisions-search`:** nenhuma ADR nova no intervalo. Relevantes ao tema: [0290](../decisions/0290-fidelity-lock-v0-recusado.md) (fidelidade visual não se prova por render pareado — a mesma doutrina que sustenta a recusa de baseline falso aqui)
- **Handoff anterior:** [2026-07-27 09:05](2026-07-27-0905-sdd-produto-fechado-cadeia-requisitos.md)

## O que aconteceu

Os itens **8/9/10** do §3 da [auditoria da Camada 1](../sessions/2026-07-27-auditoria-camada1-sdd-mordida.md)
estavam catalogados como *"precisam de [W] — nenhum corrigido aqui"*. Como dois deles tocam **valor** e
**isolamento**, a REGRA MESTRE se aplicava: provar por dois caminhos + apresentar antes→depois **antes**
de aplicar.

**Fecharam (#4832):** `default_sell_price_inc_tax` não existe — 4 telas liam o nome errado e o preço de
venda chegava `0`; e o `bulkUpdate` gravava `variation_group_prices` sem guard de tenant (2ª instância
da família `UC-PTAB-04`). Ambos provados estáticamente **e** por lane real, e ambos verdes depois.
Placar da lane: **17 → 15** vermelhos, com comparação **nominal** confirmando zero regressão.

**Não fechou (#4845 fechado → #4848 registra):** a rota fantasma `/products/mass-update`. O
`visual-regression` (required) barrou, e o cálculo mostrou que fechá-la **sozinha** entrega **zero**
(com a rota certa o writer estoura em `dpp_inc_tax` e reverte o lote — `UC-PBULK-05`) e **custa** um
contrato visreg para tela `POST`-only. Corrigir o payload toca o Controller → `scope: global` → o gate
deixa de cobrar. **A linha viaja junto com o `UC-PBULK-05`.**

**Premissas corrigidas junto** (regra de precedência): `CU-PROD-14` item 5 e `UC-PSHOW-05` descreviam o
campo inexistente como *"venda com imposto"* — a mistura exc × inc que apontam era **hipotética**, e só
agora vira achado vivo.

## Artefatos gerados

| PR | Estado | O que |
|---|---|---|
| [#4832](https://github.com/wagnerra23/oimpresso.com/pull/4832) | **merged** `78d5243b3f` | achados 8 e 9 + premissas + fix do hook MWART (10 arquivos) |
| [#4845](https://github.com/wagnerra23/oimpresso.com/pull/4845) | **closed** | tentativa do achado 10 — barrada pelo gate, motivo no fio |
| [#4848](https://github.com/wagnerra23/oimpresso.com/pull/4848) | **merged** `1cb27ebd9c` | cálculo do achado 10 registrado em casos/charter/RUNBOOK/PARIDADE/SDD |

Colateral: o `block-mwart-violation` lia só `runbook:` enquanto o schema canônico define
**`related_runbook`** (medido: 7 charters usam a do schema, 5 a curta). Regex aceita as duas; selftest
**18 → 22** com 2 controles negativos (fantasma continua bloqueando).

## Persistência

- **git:** 2 PRs mergeados em `main` + este handoff/session log
- **MCP:** propaga via webhook (~2min). Nenhuma task mutada — o trabalho não veio de task
- **BRIEFING:** não atualizado — a capacidade do módulo não mudou (2 correções de defeito, 1 registro)

## Próximos passos pra retomar

```bash
gh pr view 4848 --json body -q .body
```

O caminho natural é **`UC-PBULK-05`**: fecha o payload, leva junto a linha do `.tsx`, e o gate visreg
para de cobrar contrato sozinho (escopo global). Antes disso, vale a decisão pendente do [W] sobre o
**destino da edição em massa** — se ela morrer, o `UC-PBULK-05` e o achado 10 morrem juntos.

## Lições catalogadas — 3× LC-08 na mesma sessão

Todas da mesma classe: **o instrumento respondia uma pergunta parecida com a feita, e devolvia um número.**

1. **`git log --all` sem `git fetch`** → afirmei que o session log da auditoria *"não existe em nenhuma
   ref do git"*. Existia desde as 10:19; meu HEAD era das 09:34. `--all` varre refs **locais**.
   **A afirmação errada foi publicada no #4832.** Corolário: `--all` não é "tudo" — é "tudo que eu baixei".
2. **Hook testado no cwd errado** → `exit 0` no meu teste, bloqueio real na prática (o `PreToolUse` usa
   snapshot da sessão, não o disco).
3. **`grep` capturou o tempo de execução** junto do nome do teste → 2 "regressões novas" que eram os
   mesmos testes com timing diferente. Refeito normalizado: zero regressão. Sem a conferência, teria
   relatado quebra de 2 testes que nunca toquei.

Nenhuma virou defesa mecânica — a classe já tem alarme no ledger e o gate óbvio já foi medido e
reprovado. Ficam como recibo-instância (LC-08 → 13).

## Pointers detalhados

- [Session log desta sessão](../sessions/2026-07-27-produto-3-achados-tier0-fechados.md) — narrativa completa + tabela de provas
- [Auditoria de origem](../sessions/2026-07-27-auditoria-camada1-sdd-mordida.md) §3 itens 8/9/10
- [`BulkEdit.casos.md`](../../resources/js/Pages/Produto/BulkEdit.casos.md) — bloco de cálculo do achado 10
