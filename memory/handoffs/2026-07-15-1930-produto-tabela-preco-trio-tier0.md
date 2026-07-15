---
date: "2026-07-15"
time: "19:30"
slug: produto-tabela-preco-trio-tier0
tldr: "Print do Claude Design (aba Custos) trazia bug de dinheiro — markup mestre a 2 casas faz R$ 7.000,00 virar 6.999,97. Markup mestre está CERTO (o banco já é DECIMAL(22,4)); a UI é que arredonda. Ao fechar o trio da tela, o UC-PTAB-04 reprovou e provou furo Tier 0: price_group_id cru do request gravava linha cross-tenant. Corrigido failing-first no mesmo PR. 4 PRs merged."
decided_by: [W]
prs: [4299, 4300, 4308, 4319]
us: [US-PROD-020, US-PROD-022, US-PROD-027]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0264-governanca-executavel-trio-dominio-e2e]
next_steps:
  - "US-PROD-027 — travar o acidente do 0-row (TEST-ONLY; não precisa de decisão de ninguém)"
  - "Wagner decide as 4 do §Backlog do SellingPrices.casos.md (302-vs-404 · Labels/Woo sem CU · default 0 da UI · piso AR-PROD-101)"
  - "Design da Variação volta → destrava a branch parked docs/charter-variacao-precos-parked + casos.md de Create/StockHistory"
  - "Revogar o PAT vazado em github.com/settings/tokens (removido do .git/config nesta sessão)"
---

# Produto · tabela de preço — do design ao Tier 0, guiado por corte

## TL;DR

**4 PRs MERGED:** [#4299](https://github.com/wagnerra23/oimpresso.com/pull/4299) dente do markup · [#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300) trio + fix Tier 0 · [#4308](https://github.com/wagnerra23/oimpresso.com/pull/4308) lição §5 · [#4319](https://github.com/wagnerra23/oimpresso.com/pull/4319) SDD v1.0.1.

Um print do Claude Design trazia um **bug de dinheiro** (markup mestre a 2 casas perde 3 centavos). Ao cravar isso em teste, o trio da tela fechou — e o `UC-PTAB-04` **reprovou na 1ª execução**, provando um **furo Tier 0 real** que ninguém sabia que existia. Corrigido no mesmo PR, failing-first.

## Estado MCP no momento do fechamento

```
cycles-active  → Nenhum cycle ATIVO em COPI (off-cycle)
my-work        → Sem tasks ativas pra @maiara-01
tasks-list Produto → 5 ativas (US-PROD-021/023/024/025/026) + US-PROD-027 (nova, neste PR)
                     ⚠️ as 5 dizem "bloqueada por US-PROD-020" — que está `done` desde 14/07 11:53
                     com os 3 aceites `[ ]` ABERTOS (ver §Lições)
handoffs irmãos hoje → 6 (design v2/hook, combobox, PHT accent, status-badge, tabnav) — zero overlap
ADRs 24h       → 0337, 0338, 0339 (design/gates DS — sem interseção com Produto)
```

## O que aconteceu

Maiara desenhava a tela de cadastro de produto no Claude Design e trouxe um print da aba Custos. **O print tinha um bug de dinheiro:** o design definiu "Markup é o campo MESTRE" e renderizou o produto `SG03#` do legado (custo 4.300,00 / valor 7.000,00) como **6.999,97 / lucro 2.699,97**. A margem real é 62,790697674…% — arredondada a 2 casas pra virar o mestre, não reconstitui o preço. **Com markup de 2 casas, R$ 7.000,00 é inexpressável.**

Veredito: **markup como mestre está correto** — `variations.profit_percent` já é `DECIMAL(22,4)`, exatamente o piso medido. O bug é a UI arredondar o mestre. **Regra: grava 4, exibe 2.**

Ao fechar o trio da tela, o `UC-PTAB-04` **reprovou na 1ª execução** e provou um furo Tier 0 real: `saveSellingPrices` escopava por `business_id` **só** o `Product::findOrFail`; o `price_group_id` vinha **cru da chave do array do request**, sem `validate`/`exists`. Produto meu + `price_group` alheio **gravava linha cross-tenant**. Corrigido no mesmo PR (failing-first). Não vazava dado (leituras são escopadas), mas violava a ADR 0093 — e tornava **falso** o `✅ (reusa guard)` do `CU-PROD-10`.

O passe adversarial também mapeou o ecossistema: `getVariationGroupPrice` tem **5 consumidores e 3 não guardam** o retorno (`LabelsController:145`, `WoocommerceUtil:343,733`). Venda interna guarda; saída pro cliente final não. **Não viraram teste — não têm CU** (o `CU-PROD-09` não menciona preço; Woo não tem CU nenhum). Registrados como pendência de contrato.

## Artefatos gerados

| Arquivo | Canon | O quê |
|---|---|---|
| `tests/Feature/Produto/FormacaoPrecoParidadeLegadoTest.php` | +200 | golden `SG03#` + discriminação 2-casas + piso de precisão. Lane **required** `Pest (Unit)` via 1 linha em `.github/ci-sqlite-pest.list` |
| `tests/Feature/Produto/TabelaPrecoContratoTest.php` | +250 | `UC-PTAB-01..04` — matriz persiste · cross-tenant produto · `num_uf` · cross-tenant price_group. Lane `Estoque · MySQL` (escopo estendido a `tests/Feature/Produto/**`) |
| `resources/js/Pages/Produto/SellingPrices.casos.md` | novo | contrato executável; fecha o trio (baseline 316 → **315**) |
| `resources/js/Pages/Produto/SellingPrices.charter.md` | v1→**v2** | fluxo real (tabela nasce fora → produto precifica → vincula a cliente/tipo de venda); `AR-PROD-111..116` vira **Non-Goal declarado** |
| `app/Http/Controllers/ProductController.php` | fix Tier 0 | `$allowedPriceGroupIds` antes do laço + skip + `Log::warning` |
| `memory/proibicoes.md` §5 | +1 entrada | 2026-07-15 — achado/correção lidos no código não são achado |
| `memory/requisitos/Produto/SDD-*.md` | v1.0.0→**v1.0.1** | `CU-PROD-10` ✅ → 🟡 parcial |
| `memory/requisitos/Produto/SPEC.md` | +US-PROD-027 | travar o acidente do 0-row (TEST-ONLY, p1, 3h) |

## Persistência (3 canais)

- **git:** 4 PRs merged + este handoff. Branch parked `docs/charter-variacao-precos-parked` (`41046e1`) — charter da Variação espera a `.tsx`.
- **MCP:** `US-PROD-027` no SPEC (webhook sincroniza no push). ⚠️ O `tasks-create` respondeu "✅ criada e adicionada" mas o MCP roda no CT 100 — **não escreveu no meu disco**. Colei o bloco à mão. *Se tivesse acreditado na mensagem, a task existiria só no chat.*
- **BRIEFING:** não tocado (`Produto/BRIEFING.md` está stale desde 15/06 — diz "SPEC.md não existe"; fora de escopo, ver §Próximos).

## Próximos passos pra retomar

```
/continuar  → depois:  tasks-detail task_id=US-PROD-027
```

**Ordem sugerida:** (1) **US-PROD-027** — não precisa de decisão de ninguém, é a única das pendências que vira segurança sem virar escolha. (2) Wagner decide as 4 do §Backlog do `casos.md`. (3) Design da Variação volta → destrava a branch parked + `casos.md` de Create/StockHistory.

## Lições catalogadas

- **[§5 mergeado]** Achado lido no código é **hipótese**. Vira achado com três, cumulativos: varredura completa **com o número** ("2 de 2", não "achei em 2") · âncora de contrato citada · teste vermelho rodado quando `[V0]`/`[T0]`. Os 3 cortes de Maiara ("quais testes essa solução resolve?" · "casos de uso?" · "verifica melhor") mataram 3 propostas minhas — e a única que sobreviveu foi a única ancorada em CU.
- **Não procurar "a raiz"** quando os defeitos são independentes: unificar numa narrativa esconde que as correções **se anulam** (parar de gravar zeros → mais "sem row" → piora Labels/Woo).
- **Status de task pode mentir.** `US-PROD-020` está `done` desde 14/07 com os **3 aceites `[ ]` abertos** e 5 US ainda listadas como bloqueadas por ela. Mesma família do `✅ (reusa guard)` que o CI derrubou. **Faltam os `casos.md` de Create e StockHistory.**
- **Charter sem `.tsx` não mora no repo** — 3 gates independentes dizem isso (`charter-refs` · `integrity-check` IT2 · `charter related_us join`). Draft **não** é exceção; PR permanentemente vermelho ensina a ignorar vermelho.
- **Ninguém roda teste em máquina.** CI é o gate: lane sqlite via `.github/ci-sqlite-pest.list` (1 linha, `merge=union`) · 6 lanes MySQL por domínio · nightly CT 100. Esta máquina **não tem shell no CT 100** (ACL do tailnet: `wr2backup@` ≠ dono do nó).
- **CODEOWNERS `.github/ @wagnerra23`** — PR que toca CI **exige** review do Wagner. Não é bug; é o desenho. Diagnostiquei por eliminação (protection → contexto ausente → `strict`) quando o padrão estava na cara: os 2 bloqueados tocavam `.github/`, o que mergeou não tocava.

## Pointers detalhados (on-demand)

- Decisões [W] pendentes: §Backlog de [`SellingPrices.casos.md`](../../resources/js/Pages/Produto/SellingPrices.casos.md) — 302-vs-404 · Labels/Woo sem guard **e sem CU** · 0-row inerte por acidente · piso `AR-PROD-101`
- Contrato de paridade do legado: [`ANTI-REGRESSAO-cadastro-produto-legacy.md`](../requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md) (Partes 1-4, ~120 itens) + [`-variacao-legacy.md`](../requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-variacao-legacy.md) (`AR-PROD-170..187`)
- Mapa charter × legado: [`PARIDADE-charter-vs-legado.md`](../requisitos/Produto/PARIDADE-charter-vs-legado.md)
- ⚠️ **Higiene fora de escopo:** PAT do GitHub inválido estava embutido no remote (`.git/config`) — **removido nesta sessão** (auth segue pelo helper do `gh`, testado). **Falta revogar** em github.com/settings/tokens: ele vazou no output. `Produto/BRIEFING.md` stale (15/06).
