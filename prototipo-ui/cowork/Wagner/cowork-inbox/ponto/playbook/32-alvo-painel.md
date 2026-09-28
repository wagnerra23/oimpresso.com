<!-- SESSÃO FRIA · abra esta thread sozinha. Prompt de abertura: `_SESSAO-FRIA.md` (linha "ALVO"). Não leia as outras threads. -->

# 32 · ALVO `ponto--dashboard--index` — medir o protótipo antes da forma (trava a 13)

> **Por que existe:** a 13 roda `/onda Ponto/Dashboard/Index <secao>`; o `pedido.mjs` procura o alvo por `acharAlvo()` (casa o caminho da Page ignorando separadores) e sai **exit 2 NÃO MEDI** sem ele. Hoje o Ponto tem **0 alvos** (árvore `438b6992ed4b`).
> **Slug = caminho da Page** (`Ponto/Dashboard/Index` → `ponto--dashboard--index`). Nome de aba (`ponto--painel`) não é achado.
> **Dono:** [CL] — `governance/design/targets/**` é do `scripts/design-sync/alvo.mjs`. Eu entrego a semente.

## Entrega (1 PR · 3 arquivos)
1. `governance/design/targets/ponto--dashboard--index.secoes.json` — a semente abaixo, conferida com `alvo:mapa`.
2. `governance/design/targets/ponto--dashboard--index.alvo.json` — saída do `alvo:medir`, nunca à mão.
3. Uma linha na tabela "Alvos exportados" do `README.md` da pasta.

## Comando
```bash
npm run alvo:mapa  -- http://127.0.0.1:5550/ --rota ponto
npm run alvo:medir -- http://127.0.0.1:5550/ --tela ponto--dashboard--index --rota ponto \
  --secoes governance/design/targets/ponto--dashboard--index.secoes.json --quieto-ms 2000
```
`--rota` grava `oimpresso.route` antes do load (`alvo.mjs:55`). Viewport 1280×900, dark. Duas medidas → bytes idênticos.

## Semente das seções (com `dado` — sem ele o `pedido.mjs` REPROVA)
```json
{
  "_": "semente [CC] 2026-09-28 — âncoras do protótipo (ponto-page.jsx Painel) = ids do ponto-painel.contract.json e do Dashboard/Index.tsx vivo (:138 · :240 · :333 · :365). dado lido em DashboardController.php@main 0c23a1349c08.",
  "header":    { "seletor": ".ponto-root > .cli-ph > header", "dado": "shell — PageHeader; server_time (eager) para o 'Atualizado às'" },
  "tabs":      { "seletor": ".ponto-root > nav.ds-tabbar", "dado": "shell — PontoSubNav (shell.menu, ADR 0182); contagens vêm de kpis.aprovacoes_pendentes" },
  "nota":      { "seletor": "[data-contract=\"painel-nota-fechamento\"]", "dado": "kpis.aprovacoes_pendentes + kpis.divergencias_mes (ApuracaoDia ESTADO_DIVERGENCIA, whereMonth)" },
  "kpis":      { "seletor": "[data-contract=\"painel-kpis\"]", "dado": "DashboardController::buildKpis — colaboradores_ativos · presentes_agora · atrasos_hoje · faltas_hoje · he_mes_minutos · aprovacoes_pendentes · aprovacoes_urgentes · ultima_marcacao (defer)" },
  "fila":      { "seletor": "[data-contract=\"painel-fila-aprovacoes\"]", "dado": "DashboardController::buildAprovacoes — top 5 Intercorrencia::pendentes(), com dia_todo/intervalo_inicio/intervalo_fim (data_fim sempre null)" },
  "atividade": { "seletor": "[data-contract=\"painel-atividade\"]", "dado": "DashboardController::buildAtividadeRecente — 20 Marcacao de hoje com nsr · tipo · momento · origem · rep" }
}
```
Fora da semente, de propósito: `presenca_agora` (PresenceStrip) e `serie_7dias` existem **só na produção** — a 13 as preserva (guarda), o alvo não as cobra.

## PARAR SE
- a seção `header` sair ausente → o build de 28/09 (`cli-pagehead.jsx?v=cp14`, classe `.cli-ph`) ainda não desceu pro espelho. Importe primeiro; não troque o seletor por um estrutural.
- o `alvo:mapa` não achar algum seletor → corrigir com o que o mapa devolveu e declarar no `_saida`.
- as duas medidas divergirem → carga em fases: `--aguardar-sumir` com o esqueleto que o mapa mostrar.

## Prova
`ponto--dashboard--index.alvo.json` com a chave `secoes` (§7). Fechar com `_saida-32.md`.
