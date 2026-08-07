---
date: "2026-08-07"
topic: "Onda 1 da fusão de telas da Jana virou achado maior: o vermelho de 15 dias na nightly do CT 100 que ninguém lia"
authors: [C, W]
prs: [5357, 5363]
us: [US-COPI-148, US-COPI-106]
outcomes:
  - "O /ia/painel (hub de 3 links + buildMockPayload, 0 hits no ledger, link /ia/chat quebrado) foi removido com smoke real: cadeia 301 → 302 → 200 provada hop a hop e confirmada logada no browser."
  - "O caso 010 do Wave14GovernanceV3Test afirmava o OPOSTO do código e estava vermelho na nightly por 15 runs consecutivos (24/07→07/08) sem ninguém agir — corrigido e posto numa lane de PR, que passou com 26 assertions contadas."
  - "Denominador medido: 142 testes Feature do Jana, 39 em lane de PR, 103 fora. A nightly os executa e ninguém lê o resultado — vermelho não observado é indistinguível de teste que não roda."
  - "4 pontos do pedido do [CC] caíram na conferência contra o repo vivo: o JanaTabs proposto já existe (JanaAreaHeader+JanaSubNav, e as abas vêm do backend), o cockpit são 3 arquivos e um serve /sells, apagar o Cockpit.tsx deixa render órfão, e a US-JANA-PAINEL-001 nunca existiu no SPEC."
  - "PR aberto durante o outage do GitHub Actions nasce SEM workflow nenhum e a fila drena sem ele; close+reopen recriou 119 checks. Receita catalogada em deploy-recovery-patterns.md §12."
---

# Sessão 2026-08-07 — Jana onda 1 + o vermelho que ninguém lia

Pedido de entrada: doc `JANA-FUSAO-2026-08-06` do [CC], propondo fundir as telas do módulo Jana numa tela única `/ia` com abas. O doc avisava que foi medido num **espelho local, não em `origin/main`**, e pedia confirmação dos caminhos.

## O que a conferência mudou no plano

Confirmar contra o repo vivo derrubou 4 pontos do pedido:

1. **`JanaTabs.tsx` não precisa ser criado** — as abas já existem como `JanaAreaHeader` + `JanaSubNav`, servindo as 4 telas. E não são hardcoded no React: vêm do `shell.menu`, declarado pelo `DataController` (PHP). A onda 2 é backend, não frontend.
2. **O cockpit não está duplicado 2×, são 3 arquivos** — e `components/JanaCockpitV2.tsx` serve a tab Insights de `/sells` (`Sells/Index.tsx:55`). Não é duplicata da Jana e não pode ser apagado.
3. **Apagar `Cockpit.tsx` deixa render órfão** — `ChatController@cockpit` (`:666`) faz `Inertia::render('Jana/Cockpit')`.
4. **`US-JANA-PAINEL-001` nunca existiu no SPEC** — id fantasma, vivo só no charter, no teste e no `SCOPE.md`, de onde vazava pro `catalog.json` (derivado).

## Onda 1 — remoção do `/ia/painel` (#5357)

O Painel era hub de 3 links + `buildMockPayload()`. Medido antes de apagar: **0 hits** no `governance/route-hits.json` contra 4 do `/ia/dashboard` na mesma janela, e o link `/ia/chat` do próprio hub apontava pra rota inexistente. A capacidade tem receptor vivo — `/ia/dashboard` entrega brief · KPIs · análises · ações via `_components/JanaCockpit`, com dado real do `SellsCockpitAggregator`.

**Smoke real pós-deploy**, cadeia hop a hop:

```
/ia/painel     →  301  Location: /ia/dashboard
/ia/dashboard  →  302  Location: /login
/login         →  200
```

Confirmado também **logado** no browser: `/ia/painel` cai no dashboard renderizado (brief "118 vendas · 70 pendentes", 4 KPIs, análises), console sem erros. A barra de abas mostra `Dashboard | Copiloto | Memórias | Jana Pro | Cockpit` — **nunca houve aba "Painel"**, então apagar não deixou buraco de navegação.

## O achado maior — a nightly roda, produz vermelho, e ninguém lê

Ao fechar a dívida "o `Wave14GovernanceV3Test` não está em lane de PR", a medição no CT 100 (`/opt/oimpresso-fullsuite/runs/*/junit-shard-*.xml`, parseados por testcase) mostrou:

```
24/07 → 06/08   12 casos | 2 falhas (009 + 010)   ← todos os dias
07/08 (hoje)    11 casos | 1 falha  (010)
```

**15 runs consecutivos** com o mesmo vermelho e zero ação. O `009` só sumiu porque o #5357 removeu o `PainelController` — foi a única mudança nesse quadro em duas semanas, e por acidente.

O caso `010` exigia `'metas' => Inertia::defer(` num controller que traz, desde 2026-05-25, `HOTFIX: metas SEM Inertia::defer porque Dashboard.tsx lê metas.length direto`. **O código estava certo; o teste é que mentia.** Corrigido em #5363 para defender a decisão, e o arquivo entrou na lane — que passou com **26 assertions contadas** do Wave14 (a prova de execução que a LC-13 exige, e não `0 failed`).

**Denominador honesto:** 142 testes Feature do Jana, **39** em lane de PR, **103 fora**. A nightly os executa; ninguém lê.

## Incidente atravessado: outage do GitHub Actions

O #5357 nasceu **sem workflow nenhum** (`total_count: 0` no head_sha) durante o outage de 2026-08-06 15:22 UTC. A fila do repo caiu de 82 → 4 e o PR seguiu com 0 checks — prova de que o evento `pull_request opened` se perdeu, não que estava na fila. `close`+`reopen` recriou 119 checks. Receita em [`deploy-recovery-patterns.md` §12](../reference/deploy-recovery-patterns.md).

Durante a janela, `cancel` e `force-cancel` devolviam **HTTP 500** — por isso os 4 runs zumbis de 2026-05-15 sobrevivem há meses.

## Erros meus nesta sessão (registrados, não apagados)

- **Flag derivada de string de mensagem**: usei `--apply` no `screen-coverage-map.mjs` tirado de um `console.log` interno; a real é `--json`. Rodou sem efeito e eu quase li "não mudou nada" como "está certo".
- **Wrapper mascarando exit code**: montei `run(){ ... | tail }` onde `$?` lia o `tail`, e cantei `rc=0` num gate cujo texto dizia "PR bloqueado".
- **Padrões misturados**: usei `Wave14` (curto) pra localizar e `Wave14GovernanceV3Test` (longo) pra contar, e li como se fossem o mesmo — concluí 2 shards quando era 1.
- **Afirmação errada sobre o `infra-contract`**: disse que "as rotas de módulo vivem em `Http/routes.php`". Medido: `Routes/*` = **50 arquivos** (padrão dominante), `Http/routes.php` = **5**. O gap era real mas 10× menor do que pintei.
- **Recomendação sem medir o entorno**: recomendei "matar o Painel primeiro = baixo risco" com base no doc do [CC]; medido, o Painel tinha 20 pontos de acoplamento, 2 testes e 5 artefatos derivados.

Todos são LC-08 (afirmar/derivar da fonte errada) e nenhum chegou a `main`.

## Higiene fechada junto

- **Baselines com tela morta**: `Jana/Regras/Index.tsx` (removida em 2026-08-04 pelo #5270) saiu de 4 baselines.
- **`infra-contract-required` cego**: passou a casar `Modules/**/Http/routes.php` — os 5 módulos que escapavam (57 commits/90d). Descoberto no caminho que ele **não é required** apesar do nome do arquivo (LC-10).

## Fica aberto

| Item | Estado |
|---|---|
| Alarme sobre o resultado da nightly | decisão [W] — o #5008 (draft, 9 dias parado) faz o 1º passo (emitir summary); falta ler e alarmar |
| Ondas 2-4 da fusão | `US-COPI-148`, com as 4 correções acima |
| Resíduo da onda 1 | `JanaAreaHeader.tsx` mantém `'painel'` no tipo e um `case` sem consumidor — sai na onda 2, que edita o arquivo |
