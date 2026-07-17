---
date: "2026-07-16"
time: "19:30"
slug: produto-preco-especial-f1-charter-v38
tldr: "[F] pediu a aba "Preço especial" do cadastro ("a melhor usabilidade ganha; o legado Delphi não entra"). Saiu F1 navegável + charter v2→v3 + 2 pesquisas (24 plataformas). Achado: Bling/Tiny/ContaAzul/Microvix têm ZERO faixa de quantidade. [F] cortou 8x, todas procedentes - 2 revogaram o charter. Meus 2 padrões de erro foram pro §5: importar solução sem checar a premissa (3x) e medir a propriedade errada chamando de verificado."
decided_by: [F]
prs: []
us: [US-PROD-022, US-PROD-023, US-PROD-027]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0104-processo-mwart-canonico-unico-caminho, 0107-emendation-0104-visual-comparison-gate-f3]
next_steps:
  - "[F]/[W] decidir o markup na aba Base — (a) bidirecional (= paridade com prod, product.js:313/:338) ou (b) read-only (depende da aba Custos, que NÃO existe em git). [V0]"
  - "[F]/[W] decidir o furo do custo zero — o aviso `preço < custo` nunca dispara com custo 0, e custo 0 é 10,4% da base real (53,4% deles já com preço zero). [V0]"
  - "[W] mergear o PR #4321 — 79 checks verdes, MERGEABLE, aberto desde 15/jul. É a evidência que derrubou o 'markup é mestre' do charter"
  - "[W] emendar a ADR ARQ-0001 (multiplicador) — premissa central falsa: price_type='percentage' JÁ é multiplicador desde 2023, e a ADR nunca o cita"
  - "[W] decidir AR-PROD-101 (piso que BLOQUEIA) — diferente do aviso de custo (que AVISA); existe só no browser hoje"
  - "[W] US-PROD-027 está em `review` no MCP mas o PR #4328 foi FECHADO — status subiu por link de commit, não por prontidão. Comentei na task; sugestão review→todo/blocked + reescrever o aceite (aponta pro PDV, fora do cadastro)"
  - "[F] resolver a colisão de nome — o charter declara 'Preço Especial' (AR-PROD-111..116) Non-Goal, e a aba se chama Preço especial"
---

# Produto · aba "Preço especial" — F1 + charter v3

## Onde parou

**Branch `claude/tabela-preco-regra-excecao`, 11 commits, SEM PUSH.** Worktree
`.claude/worktrees/tabela-regra` (criada de `origin/main` fresco — o checkout principal está ~5.200
commits atrás).

Guards limpos em todo commit: `ds-guard` + `integrity-check` IT1..IT7. Protótipo verificado no
browser a cada rodada (DOM medido, zero erro no console).

## O que entregou

| Artefato | Estado |
|---|---|
| [`prototipo-ui/cowork/produto-preco-especial/`](../../prototipo-ui/cowork/produto-preco-especial/) | F1 **navegável** — HTML + CSS + `NOTES.md` |
| [`SellingPrices.charter.md`](../../resources/js/Pages/Produto/SellingPrices.charter.md) | **v2 → v3** |
| [`proibicoes.md` §5](../proibicoes.md) | **+2 entradas** (as lições que se repetiram) |
| [session log](../sessions/2026-07-16-produto-preco-especial-f1.md) | narrativa completa |

## O modelo (contrato v3)

- **Grade** — 1-3 eixos do `variation_templates` (existe desde 2017, CRUD vivo, a tela React não
  usava). 0 eixos → 1 célula (`DUMMY`) · 1 → lista · 2 → matriz · **3+ → linhas combinadas** (Odoo)
- **Base** — mora na **variação**; produto simples = grade de 1 célula
- **Tabela** — **regra %** (célula = exceção) **OU** preço digitado (célula = o preço). O schema já
  suportava: `price_type ∈ {fixed, percentage}`
- **Faixa** — linha esparsa em **qualquer** contexto (inclusive a Base). Guarda o **piso**, exibe
  **de X até Y**. VOLUME/bloco. Substitui, não mescla
- **Único aviso** — preço **abaixo do custo**, por variação afetada. Penhasco do volume **não** avisa

## Achado comercial

**Bling · Tiny/Olist · Conta Azul · Linx Microvix = ZERO faixa de quantidade.** Omie = meia-solução
em "característica do produto", só no PDV. Faixa real só em TOTVS/Sankhya. **Espaço aberto.**

## ⚠️ Correções de canon que valem além desta tela

1. **`AR-PROD-095` "markup é mestre" está ERRADO** — junta 2 colunas. `MARGEM` = `((V/C)−1)×100` ✅
   (5 caminhos, base real 3.668 linhas) · `CALC_PMARKUP` = **fórmula desconhecida**, 0/3.668. O
   certo: **Custo é âncora**, Valor↔Margem **bidirecional**, e o oimpresso **já faz em produção**.
2. **`AR-PROD-097`** = flag **por produto** que o oimpresso **não tem** (só o modo `N`). Base real:
   83,8% `N` · **8,2% `S`** → migrar como está **quebra silencioso pros 8,2%**. Capacidade a preservar.
3. **A aba Custos NÃO existe em git** — 0 `.tsx`, 0 branch. Só na máquina de [F] ou no papel.
4. **ADR ARQ-0001 tem premissa falsa** — `price_type='percentage'` **já é** multiplicador desde 2023;
   a ADR (2026-05) diz que "são modelos diferentes" e **nunca o cita**. Precisa de emenda.
5. **`AR-PROD-101`/MSP** existe **só no browser**; o "piso" é o próprio preço de venda. ≠ custo.
6. **O charter da Variação está STALE** — parkeado (`docs/charter-variacao-precos-parked`, PR
   [#4324](https://github.com/wagnerra23/oimpresso.com/pull/4324) **fechado**) e diz *"os dois modos,
   excludentes"*. **Revogado:** quantidade × variação são **ortogonais**. Aviso deixado no charter vivo.
7. **`US-PROD-027` não é executável como escrita** — PR [#4328](https://github.com/wagnerra23/oimpresso.com/pull/4328)
   **fechado**. O aceite aponta pro **PDV** (fora do cadastro); o caso que **cabe** no cadastro a US
   **exclui** de propósito. Precisa reescrita do aceite antes de virar código.

## O que eu errei (o que foi pro §5)

**8 cortes de [F], todas procedentes.** Duas famílias, e as duas **repetiram**:

1. **Importar solução de outro sistema sem checar se o problema existe aqui** — **3× nesta sessão**:
   delta do Odoo (lá o preço é **composto**, aqui é digitado) · `priceList!` do Shopify (lá **não
   existe** preço base fora de catálogo) · "máx 2 eixos" do Akeneo (lá é eixo de **PIM**). **2 dessas
   viraram lei no charter** — erro que a próxima sessão obedeceria.
2. **Medir a propriedade errada e chamar de verificado** — `.hidden` (atributo) em vez de `display`
   computado → **2 bugs vivos** passaram por 2 rodadas · `offsetTop` (relativo ao offsetParent) →
   quase concluí que o colapso não economizava nada.

> **O denominador:** eu **já tinha a informação e não liguei os pontos** — o `variation_templates`
> que eu mesmo levantei, o `price_type` que li no 1º dia, o contrato que **escrevi e violei**. E o
> que pegou nunca foi o CI: foi **[F] olhando a tela**.

## Estado MCP no momento do fechamento

⚠️ **O MCP esteve FORA a sessão inteira e VOLTOU só no fechamento** — então esta seção tem 2 partes,
e a distinção importa pra quem auditar.

**Durante a sessão (sem MCP).** O hook `SessionStart` reportou `brief-fetch FALLBACK ATIVADO — curl
falhou (exit 28)`; `curl --max-time 8 https://mcp.oimpresso.com/` → **HTTP 000**; Tailscale em
`NoState` (precisa de re-auth manual do [W]). **Todo o trabalho foi feito sem brief** — usei git como
substituto: `git show origin/main:<path>` pros SPEC/charters/ADRs (o checkout local está −5.300),
`git ls-tree` pros session logs/handoffs, `gh pr list` pro estado dos PRs.

**No fechamento (MCP de volta) — snapshot real:**

```
cycles-active          → "Nenhum cycle ATIVO em COPI"   ← off-cycle, confirmado
brief #369             → HITL pending [W]: 2 · Brain B hoje 0% · flags todas 🟢
tasks-detail US-PROD-027 → status: review · owner: wagner · p1 · ~3.0h
```

🔴 **DRIFT ACHADO E REGISTRADO:** a **`US-PROD-027` está em `review`** — mas o PR
[#4328](https://github.com/wagnerra23/oimpresso.com/pull/4328) foi **FECHADO** nesta sessão. O status
subiu porque os 3 commits ficaram linkados por sha, **não** porque está pronta. Quem olhasse o MCP
veria "prestes a entrar". **Comentei na task** com o veredito (não é executável como escrita — o
aceite aponta pro PDV, fora do cadastro; o caso que cabe no cadastro ela exclui) + os 4 achados que
sobrevivem pra quem reescrever. **Sugestão registrada lá: `review` → `todo`/`blocked`, owner [W].**
Não mudei o status — a task é do [W].

⚠️ **O que NÃO consegui, mesmo com o MCP de volta:** não rodei `whats-active` (detecta sessão
paralela tocando o mesmo path — [ADR 0119](../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md)).
Quem retomar **deve** rodar antes de tocar `Pages/Produto/`.

O brief também mostra a `US-PROD-027` como **2ª em voo** ("40h") — número que não bate com o
`estimate: 3h` do SPEC. Não investiguei; registro como observação.

## Como retomar

```
brief-fetch                                    # o meu falhou — rode o seu
whats-active                                   # sessão paralela em Pages/Produto?
git worktree list | grep tabela-regra          # a worktree ainda existe
```

Depois: abrir o protótipo (`cd prototipo-ui && python -m http.server 8903` →
`/prototipos/produto-preco-especial/`) e ler o charter v3 §Modelo de digitação.

**A aba está fechada como F1.** O que trava o F3 é a **`US-PROD-022`** (a regra da tabela não tem
coluna) — e ela é Tier 0, precisa de [W].
