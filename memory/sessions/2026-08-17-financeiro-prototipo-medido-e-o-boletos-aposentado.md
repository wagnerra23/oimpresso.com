# Sessão 2026-08-17 — o protótipo do Financeiro já estava aplicado

> Handoff: [`2026-08-17-1615-financeiro-prototipo-ja-aplicado-boletos-aposentado.md`](../handoffs/2026-08-17-1615-financeiro-prototipo-ja-aplicado-boletos-aposentado.md)

## Pedido

[W]: *"pode aplicar o prototipo do modulo financeiro. informe quais telas, quais sessões por tela e seus componentes"*.

## O inventário que ele pediu

**21 charters** no Financeiro; **7 com `related_prototype` real**, 14 declaram `n/a` (herdam PT ou
nasceram do DS). No espelho, **17 arquivos** `financeiro-*`/`boletos-*`/`cobranca-*`.

| Tela | Protótipo | Vivo | Seções |
|---|---|---|---|
| Unificado | `financeiro-page.jsx` 2002 ln | **3090 ln** + 25 comp. | Hero+KPI · Ageing · PeriodBar · FilterBar · Tabela · FooterBar · Drawer · CmdK · Tweaks |
| Fluxo | `TelaFluxo` ~156 | **562** | Margem mínima · Pior dia previsto · Próximos eventos |
| Conciliação | `TelaConciliacao` ~121 | **351** | Total no extrato · Conciliados · Pendente revisão · match sugerido |
| DRE | `TelaDRE` ~128 | **535** | Demonstração · Margem operacional · Top categorias · export Excel |
| Impostos | `TelaImpostos` ~159 | **270** | Próxima obrigação · A recolher · Calendário · Guias · "Lançar a pagar" |
| PlanoContas | `TelaPContas` ~76 | **215** | Código/Conta/Tipo/Saldo mês · Importar |
| Cobrança | `cobranca-page.jsx` 998 | 536 + 7 comp. | — |

Drawer do Unificado: 2 abas (**Detalhes** / **IA**), com 3 lentes em Detalhes — Conciliação ·
Fiscal · Cobrança. Módulos vizinhos no espelho (não são Financeiro): `pg-cobranca-page.jsx` →
PaymentGateway, `cobranca-recorrente-page.jsx` → RecurringBilling,
`pg-sells-cobranca-preview.jsx` → Sells.

**32 dos 34 componentes já estão no vivo**, vários renomeados — e o código documenta a herança:
`DrawerLens` diz *"Referência F1: LensSection em financeiro-page.jsx do protótipo Cowork"*.

## O veredito que inverteu o pedido

O vivo está **à frente** em todas as 7. Aplicar seria regredir — anti-padrão explícito do protocolo.

## As 3 frentes que ofereci, e por que as 3 caíram

Ver a lápide `§5` (mergeada em #5872) para o registro canônico. Resumo dos recibos:

1. **âncoras** — `anchor-content-check` (required) `EXIT 0`, `podre: 0 · ✓ ok: 15`. O cego era o
   `ancora.mjs`, meu instrumento de diagnóstico, e por **desenho** (2 BITE tests protegem o
   comportamento). Testei `anchorRelPath()` nos 7 valores: **6/7 resolvem e o arquivo existe**.
2. **Boletos** — `Routes/web.php` diz tudo: *"deletado em hotfix 2026-05-19"*, `Route::redirect(...301)`,
   e *"Cobrança … substitui /financeiro/boletos · Cowork F1.5 score 96/100 aprovado [W] · ADR 0144 + 0170"*.
3. **régua D+0/D+3/D+8** — o protótipo declara embaixo dela que a automação **não existe**.

## O que virou PR

**#5871** — o resíduo da deleção de maio. `index()` inalcançável (única rota é o `POST cancelar`)
renderizando page deletada + 5 privados que só ele chamava. **259 → 63 ln**. A entrada saiu da
`ORPHAN_RENDER_ALLOWLIST`, cuja ação declarada era exatamente *"Limpar dead code: task separada"*.

**#5872** — a lápide `§5` + LC-08 98→99.

## Pergunta pós-merge: "o design já copiou as funções da produção?"

**Sim — foto de 31/05, e arquivada.** `_arquivo/repo-mirror/` tem 77 arquivos (Unificado + 22
componentes + `_lib/forma-pagamento.ts`, Dre, Fluxo, Conciliação…), **0 dos 114 paths fora de
`_arquivo/`**. Charter copiado: **v13 / 2026-05-31**. Charter vivo: **v22 / 2026-07-13**.

Delta = 9 versões + 3 US: **US-FIN-029** (3 lentes — no mirror está no *Backlog*, no vivo está
implementada), **US-FIN-031** (bulk ≤500), **US-FIN-038** (pill "Conta indefinida").

Direção do "igualar" **não escolhida** — Design←Produção é escrita no DesignSync, gated (ADR 0315).

## Erros meus de método (todos pegos por controle positivo, antes de virar afirmação)

- **pathspec do git**: `Modules/*/Http/Controllers/` devolveu **0** para "boleto" — e o
  `BoletoController` existia. `:(glob)Modules/*/Http/Controllers/**` devolve 18. Só peguei porque
  rodei um controle positivo com padrão que eu sabia existir (§5 2026-07-28).
- **`$?` depois de pipe**: li `EXIT=0` num selftest que saía **1** — o `$?` era do `tail` (§5 2026-08-13).
- **`TZ` no Git Bash**: `TZ=America/Sao_Paulo date` devolveu = UTC; conferido com `Intl` → 16:11 BRT.
- **`/tmp` diverge** entre bash MSYS e node no Windows (node resolveu `D:\tmp`).
- **`gh api /rate_limit`** → MSYS reescreveu para path de filesystem; sem a barra inicial funciona.

## Contexto de infra

GitHub instável ~40min: `503` derrubou `gh pr create` (3×), `gh pr merge`, `gh pr checks`,
`gh run rerun`. O `module-grades-gate` do #5872 ficou vermelho **3 reruns** — sempre com veredito
`✅ all clear` e morrendo no `POST` do comentário. Gate **required** derrubado por falha de
entrega, não de cálculo.

MCP inalcançável a sessão inteira (4ª seguida) — fallback filesystem.
