---
date: "2026-08-07"
time: "17:41 BRT"
slug: "produto-unificado-500-e-a-lane-que-escondia-14"
tldr: "Levantar o que faltava pra ligar a tela nova de Produto achou o 500 que nunca deixou ela abrir; a cobrança do [F] por prova achou um 2º defeito que o BRIEFING atribuía a falta de dado (whereNull num campo NOT NULL). Provar exigiu a lane de Estoque, required e vermelha no main — e arrumá-la revelou o achado maior: a allowlist inline escondia 14 testes que não rodavam em lane nenhuma. Tela consertada em produção; teste aguarda aprovação. R1 não cumprido: ninguém viu a tela renderizar."
cycle: null
prs: [5383, 5387, 5378]
decided_by: [W]
related_adrs:
  - "0093-multi-tenant-isolation-tier-0"
  - "0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314"
  - "0104-processo-mwart-canonico-unico-caminho"
  - "0256-knowledge-survival-meia-vida-catraca-sentinela"
---

# Handoff — o 500 do /products/unificado e a lane que escondia 14 testes

## TL;DR

A tela `/products/unificado` existia inteira e **nunca abriu**: dois defeitos no mesmo método. O conserto e a arrumação da lane **mergearam**; o teste que os prova aguarda aprovação do [W] (toca `.github/`). **Ninguém viu a tela renderizar** — cai no login e eu não autentico. O gap de permissão (G-05) está documentado no PR e é decisão [W].

## Estado MCP no momento do fechamento

Consultado agora:

- **`cycles-active`** → *"Nenhum cycle ATIVO em COPI"*.
- **`my-work`** → *"Sem tasks ativas pra @maiara-01"*.
- **`tasks-list module:Produto`** (no início) → 4 ativas: `US-PROD-027` e `US-PROD-025` em `review`, `US-PROD-024` e `US-PROD-026` em `todo`. **Nenhuma cobre a tela unificada** — o trabalho desta sessão não tem US, e o gap de permissão (G-05) segue sem task.
- **PRs abertos no início** → 10, e **nenhum** tocava `Produto`, `Category`, `tests/Feature/Produto/` ou a lane (varredura por arquivo em cada um). Sem duplicação.

## O que foi pedido, e o que apareceu

Pedido do [F]: *"levanta o que falta pra LIGAR a tela nova de Produto em React, sem tocar na Blade que está em produção"*.

A tela existia inteira desde o commit de origem — rota, controller, 453 linhas de React, charter — e **nunca abriu uma vez**. Dois defeitos empilhados no mesmo método `categorias()`:

1. **`Category::withCount('products')` sobre relação inexistente.** `App\Category` não declara `products()` — varredura contada do arquivo dá 5 métodos, nenhum é esse, e `git log -S "function products"` volta vazio em clone completo. Como `categorias` é closure do render inicial, estourava em **qualquer** sub-tela, não só em `?tela=categorias`.

2. **`whereNull('parent_id')` não casa linha nenhuma.** A coluna é `int(11) NOT NULL`; a raiz em UltimatePOS é `parent_id = 0`, declarado em **três** lugares independentes de `App\Category`. Mesmo com o 500 resolvido, a sub-tela voltaria **vazia** com o banco cheio.

O segundo **corrige o registro do módulo**: o `BRIEFING.md` atribui a lista vazia a *"ausência de dado"*. Pelo menos parte era o filtro morto.

## O que fez o 2º defeito aparecer

O [F] cobrou: *"isso é opinião sua ou é derivado?"* — e mandou fazer o PR com teste de contrato. Escrever o teste **é o que revelou o segundo defeito**: o caso C4 exige que a categoria-pai apareça, e com `whereNull` nenhuma aparecia.

Os dois ficaram provados por **bisect em 3 runs**, não por leitura:

| commit | run | resultado |
|---|---|---|
| só o teste | [31176489473](https://github.com/wagnerra23/oimpresso.com/actions/runs/31176489473) | 🔴 `BadMethodCallException: App\Category::products()` em `ProdutoUnificadoController.php(140)` ← `(60)` |
| mata só o 500 | [31177121503](https://github.com/wagnerra23/oimpresso.com/actions/runs/31177121503) | 🔴 exceção some, cai nos **asserts**: `0 is greater than 0` + `A categoria RAIZ (parent_id = 0) não apareceu` |
| os dois | [31178967276](https://github.com/wagnerra23/oimpresso.com/actions/runs/31178967276) | ✅ `4 passed · 0 skipped · 23 assertions` |

O run do meio é o que torna o 2º defeito **fato**. Sem ele, o verde final cobriria os dois de uma vez e o achado ficaria como inferência.

## O ACHADO MAIOR — e não estava no pedido

Provar o teste exigiu pô-lo em `tests/Feature/Produto/`, que está no gatilho da lane `PHP / Pest (Estoque · MySQL)` desde 15/07. A lane é **required** desde 05/08 ([ADR 0369](../decisions/0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314.md)) e **vermelha no `main` nos últimos 5 runs** — ela passa nos outros PRs por **skip-as-pass**, então o vermelho só aparece pra quem toca esses paths.

Ao inventariar pra arrumar, o número que importava não era o dos 6 failing-first:

| medida | valor |
|---|---|
| arquivos de teste na árvore | **40** |
| nomeados pela allowlist inline | **25** |
| que rodavam em lane **NENHUMA** | **14** (`Wave2*`) |

Varredura contada do basename de cada um em `.github/`, `scripts/` e `package.json`: **zero ocorrências**. Eles não passavam — **nunca foram executados**. A allowlist inline é o mecanismo que produz esse terceiro estado escuro, e é exatamente o bug que o cabeçalho do `financeiro-pest-quarantine.list` descreve tendo substituído.

**Conserto**: mesmo mecanismo do irmão `financeiro-pest.yml` — a única lane com quarentena, e a única das 4 lanes MySQL do lote da 0369 que estava **verde** no `main`. `run-set = árvore MENOS quarentena`, partição **total**, arquivo novo entra **rodando**. A lista nasce conservadora (21 entradas: 6 failing-first com o UC de cada um · 14 nunca-rodaram · 1 com lane própria), com motivo escrito por linha, anti-apodrecimento (falha se um path listado sumir) e a quarentena **impressa em todo run**.

Recibo: `Árvore: 40 · quarentena: 21 · RODANDO: 19` → `51 passed · 0 failed · 0 skipped · 91 assertions`. Depois do teste entrar: `41 · 21 · 20` → `55 passed · 114 assertions`.

## O que o CI achou, e estava certo

- **`dup-detector`** flagou que o PR da quarentena e o do teste editavam o mesmo `estoque-pest.yml` **em direções opostas** — um removia a allowlist, o outro somava linha a ela. Era **conflito real**, confirmado depois por `git merge-tree`. Efeito colateral bom: com a árvore-menos-quarentena, o teste entra **sozinho**, e a resolução foi **apagar** minha linha.
- **`PHPStan ratchet`** pegou `Access to an undefined property App\Category::$count` no meu código. A fonte que dupliquei faz o mesmo acesso e só passa por estar **grandfathered** no baseline. Consertei com `getAttribute('count')` — código novo não herda isenção, e afrouxar baseline pra acomodar código novo é o inverso da catraca.

## Erros meus nesta sessão

- **Afirmei 4× que a consulta duplicada "já roda em produção".** É **falso** — o comentário do próprio helper diz que a lista React é inalcançável (a sidebar usa `<a href>` puro → cai no Blade). Está escrita e revisada, **nunca exercitada**. Errata assinada nos dois PRs. **Risco não medido que sobra**: `leftJoin` + `GROUP BY` sobre `products` em base grande.
- **Propus quarentena quando o [F] só queria ver a tela.** Resolvi o problema errado e segurei a entrega. A separação em 2 PRs — que era a resposta certa — eu devia ter proposto quando ele perguntou se o teste era necessário.
- **Reportei "#5387 precisa do aprove do Wagner" depois de ele já ter aprovado e mergeado.** O diagnóstico estava certo (`.github/` tem dono no CODEOWNERS), o tempo não. Medi um estado e apresentei como atual.
- **`gh api --jq` com `--arg`** falhou e eu quase li o "AUSENTE" resultante como medição — o erro de execução travestido de evidência negativa, que este mesmo §5 cataloga.
- **Parseei `required-checks-baseline.json` pela chave do topo** quando os contexts moram em `classic_protection.contexts` / `rulesets.contexts`, e o retorno foi `0 required`. Refiz: **41**.

Todos LC-08. Nenhum chegou ao `main`.

## Estado ao fechar

| PR | conteúdo | estado |
|---|---|---|
| [#5383](https://github.com/wagnerra23/oimpresso.com/pull/5383) | o conserto (1 arquivo, 0 Blade) | **MERGED** 14:55Z · deploy `success` 6× depois |
| [#5387](https://github.com/wagnerra23/oimpresso.com/pull/5387) | a lane vira árvore-menos-quarentena | **MERGED** 17:11Z |
| [#5378](https://github.com/wagnerra23/oimpresso.com/pull/5378) | o teste de contrato (4 casos) | **OPEN** · 0 falhas · aguarda aprovação (toca `.github/`, CODEOWNERS = [W]) |

**A tela está consertada em produção.** ⚠️ **R1 NÃO cumprido**: abri `/products/unificado` no Chrome e caiu no **login** — não autentico com senha, nem salva. **Ninguém viu a tela renderizar.** Deploy verde prova que o código subiu, não que a página desenha.

## Pro próximo (o [F] avisou que continua em sessão nova — contexto saturado)

1. **Ver a tela.** Logar e abrir `https://oimpresso.com/products/unificado` — **precisa colar a URL**, não há link no menu.
2. **G-05, decisão [W] pendente e documentada no #5383**: a rota não tem `can:` e o controller não tem `__construct` (medido: 0). Expõe `cost` e `margin` por produto a qualquer autenticado que abra a URL, **sem** passar por `view_purchase_price`. **Não é** vazamento entre empresas (o C3 do teste prova o scope). É o mesmo defeito que `UC-PSHOW-01` e `UC-PIDX-03` já provam nas telas irmãs. Sem task MCP.
3. **Não pôr link no menu sem decisão** — o menu é por permissão, não por empresa: o link apareceria pra ROTA LIVRE também.
4. **A dívida da lane está enumerada, não resolvida** — 5 frentes na quarentena, e as 2 de escrita cross-tenant (`UC-PQCK-02`, `UC-PBOM-02`) são as únicas alcançáveis por request direto mesmo sem tela, e as mais baratas. Os 14 `Wave2*` precisam ser rodados **um a um** antes de sair.
5. **Placeholder que a tela mostra** (US-PROD-024, ⚠️ Tier 0 estoque/valor): KPIs `populares`/`margem_media`/`sem_giro` zerados, `stockQty` renderiza literalmente **`null un`**, insumos zerados, multiplicador cosmético.
6. **Resíduo declarado no código**: a contagem **não escopa o lado `products`** por `business_id` — herdado do helper duplicado. Divergir faria as duas cópias driftarem; unificar é o `TODO` no docblock.
