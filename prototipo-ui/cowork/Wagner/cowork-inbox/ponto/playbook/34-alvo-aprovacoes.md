<!-- SESSÃO FRIA · abra esta thread sozinha. Prompt de abertura: `_SESSAO-FRIA.md` (linha "ALVO"). Não leia as outras threads. -->

# 34 · ALVO `ponto--aprovacoes--index` — trava a 15

> Slug = `Ponto/Aprovacoes/Index`. Sem o alvo, a 15 sai `exit 2`.
> **Achado que muda a 15 (W15):** o "motivo do lote" (Textarea na barra de seleção) **não tem endpoint** — `AprovacaoController` só tem `aprovarEmLote` (`ids` uuid). Rejeitar exige motivo e é **um por vez** (`rejeitar`, `motivo required|max:500`).

## Entrega (1 PR · 2 arquivos + linha no README)
`ponto--aprovacoes--index.secoes.json` (conferido) · `ponto--aprovacoes--index.alvo.json` (medido).

## Comando
```bash
npm run alvo:mapa  -- http://127.0.0.1:5550/ --rota pt-aprovacoes
npm run alvo:medir -- http://127.0.0.1:5550/ --tela ponto--aprovacoes--index --rota pt-aprovacoes \
  --secoes governance/design/targets/ponto--aprovacoes--index.secoes.json --quieto-ms 2000
```

## Semente
```json
{
  "_": "semente [CC] 2026-09-28 — data-contract de ponto-telas.jsx (Aprovacoes :60 e :82). dado lido em AprovacaoController.php@main 0c23a1349c08.",
  "header": { "seletor": ".ponto-root > .cli-ph > header", "dado": "shell — PageHeader" },
  "tabs":   { "seletor": ".ponto-root > nav.ds-tabbar", "dado": "shell — PontoSubNav (ADR 0182)" },
  "kpis":   { "seletor": "[data-contract=\"aprovacoes-kpis-estado\"]", "dado": "AprovacaoController::buildContagensEstado — RASCUNHO · PENDENTE · APROVADA · REJEITADA · APLICADA · CANCELADA (defer)" },
  "barra":  { "seletor": "[data-contract=\"aprovacoes-kpis-estado\"] + div", "dado": "filtros eager estado (default PENDENTE) · tipo (8 do enum 'tipos') · prioridade" },
  "fila":   { "seletor": "[data-contract=\"aprovacoes-fila-de-aprovacoes\"]", "dado": "AprovacaoController::buildAprovacoesPagina — paginate(20), ordem URGENTE→NORMAL→created_at desc; impacta_apuracao · descontar_banco_horas · solicitante" }
}
```
A barra de lote não entra: só existe com seleção (medir num 2º alvo com `--clicar` no checkbox, se a 15 precisar depois de W15).
`aprovacoes-fila-de-aprovacoes` é o mesmo id que a 17 grava em `Aprovacoes/Index.tsx` — âncora dupla.

## PARAR SE
- `header` ausente → o build de 28/09 (`.cli-ph`) ainda não desceu. Importe primeiro.
- `aprovacoes-kpis-estado + div` não for a barra → trocar pelo que o mapa devolver e declarar.

## Prova
`ponto--aprovacoes--index.alvo.json` com `secoes`. Fechar com `_saida-34.md`.
