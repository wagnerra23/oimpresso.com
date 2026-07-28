# Sessão 2026-07-27 — os 3 achados Tier 0 do #4823, medidos e fechados

> Continuação direta da [auditoria da Camada 1](2026-07-27-auditoria-camada1-sdd-mordida.md) §3
> (itens **8/9/10**). Aqueles três estavam catalogados como *"precisam de [W] — nenhum corrigido aqui"*.
> Esta sessão os provou por **dois caminhos independentes**, apresentou o impacto antes de aplicar
> (REGRA MESTRE de valor/estoque) e fechou o que fazia sentido fechar agora.

---

## O que os três eram

| # | Achado | Prova A (estático) | Prova B (lane real, [run 30264246760](https://github.com/wagnerra23/oimpresso.com/actions/runs/30264246760)) |
|---|---|---|---|
| 8 | `default_sell_price_inc_tax` **não existe** | migration declara `default_sell_price` + `sell_price_inc_tax`; `grep` em `database/` = **0**; `App\Variation` sem accessor nem `$appends` → Eloquent devolve `null` | `UC-PBULK-01` ⨯ — *"Nenhum campo da variação carrega o preço de venda corrente (233.11 nem 256.42)"* |
| 9 | `bulkUpdate` grava **sem guard de tenant** | `updateOrCreate(['price_group_id' => $k, …])` cru do request; `VariationGroupPrice` sem global scope (`$guarded = ['id']`) | `UC-PBULK-03` ⨯ — gravou linha na tabela de preço de **outro business** (`price_group_id=2`) |
| 10 | `/products/mass-update` **não existe** | **0** ocorrências em `routes/`; writer real é `POST /products/bulk-update` (`web.php:443`) | `BulkEdit.tsx:138` postava nela; flag `enable_product_bulk_edit` = **`false`** |

O `UC-PBULK-03` falhou no **segundo** assert (`:311`) — a **pré-condição anti-vácuo passou**, provando
que o laço de `group_prices` rodou de verdade. Não foi verde-por-não-execução (a armadilha catalogada
em [proibicoes §5 2026-07-24](../proibicoes.md)).

## Raio de explosão — medido antes de decidir

`Show`/`SellingPrices`/`BulkEdit` têm branch dual e só renderizam React com header `X-Inertia`; o
sidebar (`AdminSidebarMenu.php:157`) usa `<a href>` puro → em prod caía no **Blade**, que lê as colunas
certas. `/products/unificado` é Inertia **direto** (alcançável por URL) mas com **0** links apontando
pra ela. **Defeito real e provado, sem vítima em produção** — por isso coube decisão calma do [W], não
hotfix. Registrar isso importou tanto quanto o fix: sem a medição, a leitura seria "4 telas quebradas
em prod".

## Decisões [W]

1. **`sell_price_inc_tax`** (com imposto) — preserva a intenção do nome quebrado e a paridade com a
   coluna *"venda (inc)"* do Blade/Delphi.
2. **Espelhar o guard do [#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300)** —
   `$allowedPriceGroupIds` + `continue` + `Log::warning`, idêntico ao `saveSellingPrices`.
3. **Repontar a tela**, não criar alias — alias abriria superfície de escrita nova numa feature que o
   upstream declara que vai depreciar.

## O achado 10 não fechou — e o cálculo é o produto

Aplicada a linha, o `visual-regression` (**required**) barrou: *"telas afetadas sem contrato visreg"*.
O cálculo que resolveu:

- **Ganho sozinho = zero.** Com a rota certa o `bulkUpdate` estoura em `dpp_inc_tax` (tela manda 2
  campos, writer lê 5 sem `??`), o `catch` engole, lote reverte — `UC-PBULK-05`, vermelho. O operador
  sai de *"rota inexistente"* para *"algo deu errado"*.
- **Custo sozinho = alto.** A tela é **`POST`-only** (1 rota, `web.php:442`, zero GET) e o harness
  navega por GET (`/_visreg-login?to=`) — o baseline capturaria um redirect: o *verde vazio* que o
  próprio canário existe pra impedir.
- **O obstáculo some sozinho.** Corrigir o payload toca `ProductController@bulkUpdate` → o diff vira
  `scope: global` → o gate deixa de cobrar contrato. **Provado** rodando `classifyChanges` com os dois
  arquivos (`scope: global`, `reason: controller-inertia`), não inferido da leitura.

**Decisão: a linha viaja junto com o `UC-PBULK-05`.** Registrado nos artefatos ([#4848](https://github.com/wagnerra23/oimpresso.com/pull/4848))
justamente pra próxima sessão não refazer o caminho e bater no mesmo muro.

## Achado colateral — o hook lia a chave errada

O `block-mwart-violation` lia só `runbook:`; o **schema canônico** (`charter.schema.json`) define
**`related_runbook`**. Medido: **7** charters usam a do schema, **5** a curta — o resgate por
proveniência ficava cego justamente para quem segue o canon. Regex passa a aceitar as duas; a
validação de **existência** segue idêntica (ampliar leitura ≠ afrouxar gate). Selftest **18 → 22**,
com 2 controles negativos novos: `related_runbook:` **fantasma continua bloqueando**.

## Lições — três instâncias de LC-08 na mesma sessão

**A classe é sempre a mesma: o instrumento respondia uma pergunta *parecida* com a feita, e devolvia
um número.** As três foram pegas por verificação, não por sorte — mas duas quase viraram relato falso.

| # | O que medi | Por que estava errado |
|---|---|---|
| 1 | *"o session log `auditoria-camada1` não existe em nenhuma ref do git"* | rodei `git log --all` **sem `git fetch`**. Meu HEAD era `b6b5fac341` (09:34); o log entrou em `3473cc2d03` (10:19). `--all` varre refs **locais** — eu estava 45 min atrasado. O correto seria *"não existe nas refs que tenho"*. **A afirmação foi publicada no #4832.** |
| 2 | *"o hook corrigido resolve"* | testei `node hook` com o cwd do **worktree**; o `PreToolUse` usa outro contexto (snapshot da sessão). Exit 0 no meu teste, bloqueio real na prática. |
| 3 | *"2 regressões novas no PR"* | meu `grep` capturou o **tempo de execução** junto do nome (`0.25s` × `0.39s`), e `UC-PEDIT-07`/`UC-PIDX-06` apareceram nos dois lados. Refiz normalizado: **zero regressão**. Se eu tivesse confiado, teria relatado quebra de 2 testes que nunca toquei. |

Corolário perene: **"existe a máquina" nunca é resposta suficiente — a pergunta é o que ela mede, e se
é isso mesmo que eu quero saber.** Vale pro `git log` em repo desatualizado tanto quanto pro
`crontab -l` em host gerenciado ([§5 2026-07-17](../proibicoes.md)).

## Sessão paralela

O [#4847](https://github.com/wagnerra23/oimpresso.com/pull/4847) (outra sessão) tocou o **mesmo** SDD
no mesmo intervalo. Verificado pós-merge: minhas edições sobreviveram íntegras em `main` (3 matches de
`Changelog v1.0.5` / `viaja junto com o UC-PBULK-05`). Sem sobrescrita.

## Pointers

- Contratos: [`BulkEdit.casos.md`](../../resources/js/Pages/Produto/BulkEdit.casos.md) (bloco de cálculo do achado 10) · [`Show.casos.md`](../../resources/js/Pages/Produto/Show.casos.md) (`UC-PSHOW-05`)
- SDD: [`SDD-tela-cadastro-produto-v1.0.md`](../requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) §changelog v1.0.5 + §5.3 F5.1 + §6.1 `CU-PROD-14`
- Auditoria de origem: [`2026-07-27-auditoria-camada1-sdd-mordida.md`](2026-07-27-auditoria-camada1-sdd-mordida.md) §3 itens 8/9/10
