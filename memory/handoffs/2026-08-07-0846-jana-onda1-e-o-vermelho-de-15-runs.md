---
date: "2026-08-07"
time: "08:46 BRT"
slug: "jana-onda1-e-o-vermelho-de-15-runs"
tldr: "Pedido era fundir as telas da Jana; saiu a onda 1 (remover o /ia/painel, hub mock com 0 hits) e dois achados maiores — um teste vermelho na nightly do CT 100 por 15 runs consecutivos que ninguém lia, e um PR obsoleto que induzia ao diagnóstico errado. O [W] viu que a tela não mudou: a onda 1 era invisível por construção, e o gap visual segue nas ondas 2-4. Fechou com o conserto do hook de design que não disparava em verbo de acesso."
cycle: null
prs: [5357, 5363, 5367, 5371, 5008]
decided_by: [W]
related_adrs:
  - "0282-protocolo-v2-colapso-ratificacao"
  - "0315-design-sync-claude-design-vs-cowork-charter"
  - "0264-governanca-executavel-trio-dominio-e2e"
  - "0269-deploy-automatico-build-no-runner"
---

# Handoff — Jana onda 1 e o vermelho de 15 runs

## Estado MCP no momento do fechamento

Consultado agora (`cycles-active` + `my-work`):

- **`cycles-active`** → *"Nenhum cycle ATIVO em COPI"*.
- **`my-work` (@wagner)** → **8 tasks**, todas em `REVIEW`: `US-COPI-123` (p0 — remover `startMockStream` da rota live `/ia/dashboard`) · `US-TR-309` · `US-TR-310` · `US-PG-008` · `US-PROD-027` · `US-INFRA-023` · `US-TR-305` · `US-TR-306`.

> ⚠️ A `US-COPI-123` toca **a mesma tela** que as ondas 2-4 abaixo (`/ia/dashboard`) — quem pegar a fusão deve reconciliar com ela antes, não em paralelo.

## O que foi entregue

| PR | Entrega | Prova |
|---|---|---|
| #5357 | Remove o `/ia/painel` | smoke `301 → /ia/dashboard` hop a hop + visual logado, console limpo |
| #5363 | Caso `010` do Wave14 + arquivo na lane | lane `success` com **26 assertions** contadas |
| #5367 | Higiene: 4 baselines, `infra-contract`, receita do outage, session log | 9 gates locais `rc=0` |
| #5371 | Gatilho do hook de design + lápide §5 | selftest 16/16 · **em voo** |
| #5008 | Fechado — superado pelo #5202 | 10/10 lanes já tinham o summary |

## O achado que vale mais que a entrega

**A nightly do CT 100 roda e ninguém lê.** Medido em `/opt/oimpresso-fullsuite/runs/*/junit-shard-*.xml`, parseado por testcase:

```
24/07 → 06/08   12 casos | 2 falhas (009 + 010)   ← todos os dias
07/08           11 casos | 1 falha  (010)
```

**15 runs consecutivos** com o mesmo vermelho, zero ação. O `009` só sumiu porque o #5357 removeu o `PainelController` — única mudança em duas semanas, e por acidente.

**Denominador:** 142 testes Feature do Jana, **39** em lane de PR, **103 fora**. Vermelho não observado é indistinguível de teste que não roda.

## O gap que continua aberto — e é decisão [W]

**Um item só: ler o summary de assertions e alarmar.** O corpus já é produzido em toda run desde o #5202 (12 lanes); falta o consumidor.

Tem critério de aceitação pronto: **precisa disparar no histórico do `Wave14`** (15 runs vermelhos). Um alarme que não dispararia ali não serve.

A pausa do #5202 é deliberada e correta — pararam no corpus pra **medir o FP antes de armar**. Hoje há mais dado que em 03/08.

## A fusão da Jana — o que o [W] viu e não estava lá

A onda 1 foi **invisível por construção**: removeu tela com 0 hits. O [W] abriu o Chrome e cobrou. Comparando o protótipo que ele mandou com a produção:

| | Protótipo | Produção |
|---|---|---|
| Abas | `Painel · Conversa (4) · Memória` | `Dashboard · Copiloto · Memórias · Jana Pro · Cockpit` |
| **METAS ATIVAS** | 5 cards + período + "Nova meta" | **ausente** (mas `buildMetasPayload()` já existe no controller) |
| Análises | + Concentração · Churn ouro · Frota | só Inadimplência · Faturamento |

**As peças existem todas no Design System** (`DesignSync`, projeto "Office Impresso — Design System"): `TabBar` (com `count`), `PeriodBar`, `KpiCard`, `Progress`, `Chart`, template `pt-05-dashboard`. O `jana-merge.jsx` do [CC] **nunca chegou ao repo** e deixou de ser necessário.

**Ondas registradas na `US-COPI-148`, com as 4 correções ao pedido do [CC]:**

1. **Onda 2 — abas.** Estender `JanaAreaHeader`+`JanaSubNav` (já existem, servem as 4 telas). Os ghosts vêm do **`DataController` (PHP)**, não do React. ⛔ Não criar `JanaTabs.tsx` (LC-19).
2. **Onda 3 — METAS.** As metas **já vêm do controller e não renderizam** — medir se é bug ou não-plugado antes de assumir.
3. **Onda 4 — `Cockpit.tsx`.** São **3** arquivos de cockpit, não 2, e `JanaCockpitV2` serve a tab Insights de `/sells` (`Sells/Index.tsx:55`) — não pode ser apagado. Remover o `Cockpit.tsx` exige tirar junto `ChatController@cockpit` (`:666`).

**Resíduo da onda 1:** `JanaAreaHeader.tsx` mantém `'painel'` no tipo e um `case` sem consumidor — sai na onda 2.

## Armadilhas atravessadas (economizam tempo a quem repetir)

- **Outage do Actions (06/08 15:22 UTC):** PR nasce com **zero** workflow e a fila drena sem ele. `close`+`reopen` recriou 119 checks. Receita em [`deploy-recovery-patterns.md` §12](../reference/deploy-recovery-patterns.md). Durante a janela, `cancel`/`force-cancel` devolvem HTTP 500.
- **Rebase divergindo do remoto:** force-push é barrado pelo hook (corretamente). O caminho é **merge de reconciliação** — usado 2×, push volta a ser fast-forward.
- **`cat >>` no `proibicoes.md`:** o arquivo **não termina no §5** (tem `## Sempre fazer` depois) — lápide anexada no fim cai fora da seção e o auto-feed segue acusando.

## Erros meus, todos LC-08 (nenhum chegou ao `main`)

Flag tirada de string de mensagem (`--apply` × `--json`) · wrapper com `$?` lendo o `tail` · padrões `Wave14` curto/longo misturados · afirmar que rotas de módulo vivem em `Http/routes.php` (são **5**; `Routes/*` tem **50**) · recomendar sem medir o entorno · e o principal: **declarar que o protótipo não existe varrendo só o git**, com o `DesignSync` à mão. Lápide §5 de 08-07 + `LC-08` 54 → 55.

## Quatro mecanismos me pegaram — vale saber que funcionam

`memory-schema-preflight` (2×: frontmatter de `reference` num `session`; e o `tldr` deste handoff passando de 500 chars) · `dup-detector` (colisão que me levou ao #5008 obsoleto) · `licoes-code-two-strikes` (recibo pendurado no ledger). Nenhum é o gate que eu apontaria como o mais importante; os quatro renderam. O que têm em comum: medem **saída contra fonte**, não releem código.
