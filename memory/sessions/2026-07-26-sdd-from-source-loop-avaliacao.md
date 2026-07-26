---
id: sessions-2026-07-26-sdd-from-source-loop-avaliacao
type: session
date: "2026-07-26"
topic: "Loop de avaliação da máquina de SDD — 3 corridas do agent sdd-from-source + 3 avaliações céticas independentes (6,9 → 7,4; não chegou a 9)"
authors: [W, C]
module: Produto
owner: W
prs: [4807, 4808, 4809]
us: [US-PROD-029, US-PROD-030, US-PROD-031, US-PROD-032]
related_adrs:
  - 0351-sdd-from-source
  - 0352-errata-0351-venue-distiller-citacao-taxonomia
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
pii: false
---

# Loop de avaliação da máquina de SDD — 3 corridas + 2 avaliações céticas

> **Pedido do [W]:** *"pesquise em grade os concorrentes, ancorar nos melhores, pontue e descreva por quê"* → *"teste e repita até a máquina ficar nota 9"*.
> **Resultado honesto: NÃO chegou a 9.** Ficou em **6,7** na 2ª avaliação cética — pior que os 7,56 da 1ª. Este log existe pra registrar *por que* piorou, que é o achado mais útil da sessão.

## O que rodou

| Corrida | Alvo | Definição usada | Custo (tokens · tools · duração) |
|---|---|---|---|
| **B0** baseline | `Produto/Show` | v1 (a de 24/07) | 289.180 · 90 · ~20,6 min |
| **B1** | `Produto/Index` | v2 (corrigida com as 10 lições do B0) | 286.902 · 70 · ~18,4 min |
| Avaliador cético #1 | output do B0 | — | 234.373 · 41 · ~8,5 min |
| Avaliador cético #2 | B0 corrigido + B1 | — | 294.810 · 52 · ~10,1 min |

**Custo por tela documentada: ~287k tokens / ~20 min.** É o número que faltava pra decidir escala — a 240 telas sem `casos.md`, documentar tudo nesta profundidade é da ordem de **69M tokens**. A conclusão prática não é "não escala": é que **a profundidade tem que ser escolhida por tela**, e o critério de escolha ainda não existe.

## Reuso medido (Fase 1.4) — a 3ª tela do módulo custou menos que a 1ª

**Reusou (não re-derivou):** §5.3 do SDD (fluxos F1-F7 já mapeados) · §6.1 `CU-PROD-01..14` como âncora direta · `ANTI-REGRESSAO` (43 KB) por **1 grep temático** em vez de leitura integral · o `Show.casos.md` + `ProdutoShowContratoTest` como **molde** — sobretudo a mecânica de prop deferida (`Inertia::defer` + partial reload + `X-Inertia-Version`) e o padrão anti-vácuo. Esta foi a maior economia isolada.

**Não reusou, e a definição está certa nisso:** a resolução do branch legado (no `Show` era uma modal em outra rota; no `Index` era **o outro branch do mesmo método**) · a varredura de consumidores do fluxo · a verificação factual do charter (5 verificações independentes, nenhuma reusável).

**Gargalo:** (1) verificar os fatos do charter um a um; (2) ler o `index()` inteiro — 310 das 374 linhas são o branch DataTables, e pular teria custado o achado principal.

## Por que a nota CAIU de 7,56 → 6,7 (a lição que vale a sessão)

**Escrever a regra na definição do agente não fez o agente segui-la.** Entre B0 e B1 foram adicionadas regras explícitas; na corrida seguinte:

| Regra adicionada antes de B1 | O que B1 fez |
|---|---|
| 🔓 assert prova **comportamento**, não chave literal (com a coluna ❌ e o motivo) | usou `not->toHaveKey('cost'/'margin'/'price')` — **literalmente a coluna ❌** |
| 🔗 âncora estável > `:NNN` | line-refs subiram **26 → 41** |
| 🔴 "roda?" se responde com `phpunit.xml` + `shards-plan`, não `grep` em `.github/` | respondeu com `grep` em `.github/` e concluiu "não roda em lane nenhuma" — **falso** |
| ⚖️ declarar a força do veredito no `casos.md` | 0 ocorrências de "advisory/required" nos dois `casos.md` |
| 📝 persistir orçamento em session log | nenhum session log escrito (este é o primeiro) |

**Isto é `L-24 / presença ≠ correção` aplicado a prompt de agente:** instrução em prompt é *advisory por natureza* — ela compete com o resto do contexto e perde. O que morde é gate. As regras que B1 **cumpriu** foram as que tinham consequência verificável e local (alocação de id de CU, string canônica de `last_run_ci`, `F<n>` no §5.3, critério de parada); as que ele violou foram as que exigiam disciplina difusa ao longo de todo o arquivo.

**Corolário pro projeto:** não adianta engordar a definição do agente. O caminho é ou (a) um lint que rode sobre o output do agente antes do PR, ou (b) aceitar que a corrida precisa de revisão adversarial — que foi exatamente o que pegou os defeitos aqui.

## O que as corridas acharam (valor real entregue, independente da nota)

| # | Achado | Verificado por |
|---|---|---|
| 1 | **`App\Product` não tem global scope** — `grep -c addGlobalScope app/Product.php` → **0**. O isolamento do catálogo é `where` manual repetido. **O SDD §3.1 afirma que tem** (linha 168), e o §6.1 já registrava a verdade desde 15/07: o SDD **contradiz a si mesmo** num eixo Tier 0 | medição direta |
| 2 | **Ficha e lista entregam preço de compra a quem o Blade e o Delphi escondem.** Blade gateia com `@can` (47 linhas / 15 arquivos de view); Delphi faz o campo **sumir** (`AR-PROD-015`); o branch Inertia consulta 3 permissões, **nenhuma de preço** | `grep` contado, conferido 2× |
| 3 | **7 asserts apontavam pra `memory/requisitos/Inventory/`**, que não existe — e como `phpunit.xml` inclui `tests/Feature` recursivamente, eram **vermelhos reais** no nightly, não "latentes". Corrigidos nesta sessão | `ls` + `phpunit.xml` |
| 4 | `limit(200)` sem paginação enquanto o KPI conta o catálogo inteiro — o produto do fim do alfabeto some sem aviso | `grep` de `paginate`/`page` em `Index.tsx` → 0 |
| 5 | Menu de Ações: Blade tem **10 por linha**, React tem **1**; 5 sem Non-Goal declarado. É a classe de regressão que motivou a [ADR 0351](../decisions/0351-sdd-from-source.md) | `ProductController:200-262` |
| 6 | **`anchor-lint` não enxerga a lane `estoque-pest`** (`inLane()` só conhece `.github/ci-sqlite-pest.list` + 3 dirs de `Modules/`) → toda âncora apontando pros testes de contrato do Produto nasce marcada "verde impossível" | `anchor-lint.mjs:441-447` |
| 7 | A lane `PHP / Pest (Estoque · MySQL)` **não é required** — os achados acima reprovam de forma visível mas **não bloqueiam merge** | `governance/required-checks-baseline.json` |

## Erros meus nesta sessão (registro honesto)

1. **Aritmética inconsistente na 1ª nota** — publiquei 7,1 usando D8 com peso 2 no cálculo enquanto a tabela o marcava `P0` (peso 4). Correto: 6,9.
2. **Afirmei enforcement inexistente (LC-10)** — escrevi na definição *"`owner:` por UC é obrigatório (G-5 cobra)"*. O G-5 lê o **frontmatter**. Corrigido.
3. **Rodar `sdd-scorecard.mjs --help` reescreveu `governance/sdd-scorecard.json`** (−87 linhas: `full_suite_pass_rate` de `measured: 345` → `not_yet_measured`), porque o script escreve por padrão e local não tem os inputs do CT100. Revertido com `git checkout`. **O script não recusa rodar sem os inputs** — se isso entrasse num commit, a catraca required GT-G3 perderia dados em silêncio.
4. **Quase escrevi assert com método inexistente** (`EstoqueFixture::locationName()`) e **mensagem em `toContain`**, que é variádico em Pest (viraria um segundo needle). Pegos por verificação antes de fechar.

## Estado ao fim da sessão

- `casos:check` → **verde**, débito **−5 vs baseline**, baseline **não adulterado**, 13 UC novos e **0 órfão**.
- Máquina em **6,7/10**. Os 6 itens acionáveis pra chegar a 9 estão na avaliação #2; 5 deles foram aplicados nesta sessão (asserts sentinela, paths, força do veredito, LC-08 reescrito, este log). Falta re-avaliar.
- **Nada commitado** — R10.
