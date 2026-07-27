---
date: '2026-07-27'
topic: "SDD de Sells derivado do fonte (Create + Index) — chip Onda 2 do passo 5"
authors: [C]
us:
  - US-SELL-047
related_adrs:
  - '0351-sdd-from-source'
  - '0093-multi-tenant-isolation-tier-0'
  - '0264-governanca-executavel-trio-dominio-e2e'
outcomes:
  - "SDD do módulo Sells criado (§0–§10) — não existia"
  - "2 UC Tier 0 novos (UC-SIDX-01/02) + Pest HTTP+DB real"
  - "lane sells-pest.yml criada (advisory)"
  - "6 achados medidos, 2 deles derrubam premissas do próprio chip"
---

# Sessão — chip Onda 2 do passo 5: SDD de Sells (Create + Index)

Chip do [passo 5](../requisitos/_Governanca/programa-ondas/passo-5-sdd-por-modulo.md).
Alvo: `Sells/Create` + `Sells/Index`. Agent [`sdd-from-source`](../../.claude/agents/sdd-from-source.md).

## 1. Artefatos (5 arquivos — zero fora da área declarada)

| Arquivo | Ação |
|---|---|
| `memory/requisitos/Sells/SDD-tela-venda-v1.0.md` | **novo** — SDD do módulo §0–§10 (não existia) |
| `resources/js/Pages/Sells/Index.casos.md` | +2 UC (`UC-SIDX-01` `[T0]`, `UC-SIDX-02` `[V0][T0]`) |
| `tests/Feature/Sells/SellsIndexTenantContratoTest.php` | **novo** — Pest HTTP+DB real |
| `.github/workflows/sells-pest.yml` | **novo** — a lane que não existia |
| `memory/requisitos/Sells/SPEC.md` | `**Testado em:**` em US-SELL-047 |

## 2. Orçamento da corrida

| Métrica | Valor |
|---|---:|
| Tool calls | ~92 (referência do chip: ~95) |
| Arquivos escritos | 5 |
| Varreduras contadas (sem `head_limit`) | 9 |
| UC ancorados criados | **2** |
| UC `[BACKLOG]` criados | 0 (os 3 pré-existentes ficaram) |
| CU documentados no SDD | 33 (6 com heading `####` — ver §6 do SDD) |
| Telas cobertas | 2 de 8 (Create já tinha contrato; Index ganhou +2 UC) |
| Telas deixadas de fora | **6** (Edit · Show · Caixa · Subscriptions · Quotations · Drafts) |
| Achados novos | 6 |

**Gargalo:** a Camada 1 (triangulação). ~35 das ~92 calls foram medição de fonte —
e valeu: **duas premissas do chip caíram na medição** (achados 1 e 2). O SDD do
zero (§0–§10) foi barato depois disso, porque o módulo já tinha `CASOS-USO-CREATE-VENDA.md`
(15 CU) e `CASOS-USO-PIPELINE-VENDAS.md` (7 CU) — a parte cara do §6 já estava escrita,
faltava **namespace + reconciliação**.

**Fase 1.4 (reuso):** primeira tela do módulo → **nada a reusar**; tudo re-varrido.
O que a 2ª tela (Index) reusou da 1ª (Create): o mapa de rotas, a cadeia
Controller→Util, as personas e o §5.1 inteiro. O que **não** se reusou (por regra):
a resolução da Blade — e foi exatamente aí que o Create escondeu a armadilha (achado #2).

## 3. Achados (todos com varredura contada)

### #1 — A porta viva não enxerga os UC de Sells (falso "0 UC")
`requisitos-status.mjs` extrai UC por `/\b(UC-[A-Z0-9]{2,10}-\d{2,3})\b/` — exige **3
segmentos**. Sells usa `UC-S01` (2 segmentos). Censo contado dos prefixos em todo
`resources/js/Pages/**/*.casos.md`: `UC-S` = 5, e **todos os outros 19 prefixos têm 3
segmentos**. Logo os 5 UC reais de Sells eram reportados como *"casos.md existe mas não
declara nenhum UC"* — a premissa "2 casos.md com 0 UC (stubs)" do chip.
**Não corrigido** (`scripts/` fora da área). Mitigação: UC novo nasce com 3 segmentos;
os antigos ficam (renomear quebraria a citação dos testes que já os defendem — G-2).

### #2 — A Blade de referência é a homônima ERRADA
O chip apontou `sale_pos/create.blade.php`. Medido: o operador abre "Nova venda" →
`/sells/create` → `SellController@create` → `view('sell.create')` = **998 linhas**.
`sale_pos/create.blade.php` = **131 linhas**, servida por `/sale-pos/create`
(`SellPosController@create`). Ambos renderizam o **mesmo** `Sells/Create.tsx` sob a flag
`useV2SellsCreate`. Comparar contra a de 131 linhas daria **"paridade OK" falsa** —
a armadilha exata que a ADR 0351 manda evitar. O `Create.charter.md` confirma na Mission
(*"substitui `sell.create.blade.php` legacy"*).

### #3 — `has_return` deriva de subquery/JOIN **não escopado** por `business_id`
Dois sites: `SellController@inertiaList` (`DB::raw` correlacionada) e
`TransactionUtil::getSellsCurrentFy` (`leftjoin('transactions as SR', …)`). Ambos filtram
só `type='sell_return'` + `return_parent_id`. `App\Transaction` **não tem global scope**
(`grep -n "addGlobalScope" app/Transaction.php` = 0) → nada supre.
**Limite honesto:** `return_parent_id` não é controlado pelo usuário no fluxo normal →
**sem vazamento provado**; o fato medido é a **ausência do escopo** (defesa em profundidade).
Virou contrato (`CU-SELL-32` / `UC-SIDX-01`), **não** conserto — mexer em query de venda é
decisão [W] sob REGRA MESTRE.

### #4 — A suíte Sells é ~100% grep de source (e o SPEC já sabia)
71 dos 72 arquivos de `tests/Feature/Sells` são "Pest estrutural" (`file_get_contents` +
`toContain`); só **1** usa `DatabaseTransactions`. Inclusive o teste que o `UC-S12` cita
(`SellsIndexCoworkPayloadTest`) assere **strings do controller**, não comportamento —
tautológico ([proibicoes §5](../proibicoes.md) 2026-06-05). **Isto não é descoberta minha:**
a `US-SELL-047` já documenta *"0/254 fazem HTTP/render/DB"* e chama de "falso conforto".
O que esta corrida acrescenta é o **primeiro pagamento** dessa dívida.

### #5 — `POST /sells` é endpoint vivo que não faz nada
`SellController@store` tem corpo `//`, mas `Route::resource('sells', …)->except(['show'])`
registra a rota. O writer real é `POST /pos` (`SellPosController@store`). **Reportado.**

### #6 (operacional) — as sessões irmãs compartilham a worktree
`git status` = **101 arquivos** modificados (Compras/Fiscal/Ponto/OficinaAuto). O plano
do passo 5 pressupõe áreas isoladas, e as **áreas** de fato não colidiram (0 arquivo de
Sells tocado por irmão; meus 5 são só meus). Mas os **relatórios globais** (`casos:report`,
`screen-coverage`) ficam contaminados — o delta 213→207 do débito **não é meu sozinho**.
Consequência prática: a consolidação do parent (`casos:baseline:write` 1× após os merges)
segue certa, mas **nenhum chip deve reportar delta global como próprio**.

## 4. As três portas de "roda / é cobrado" (medidas separadamente)

Erro LC-08 é responder uma porta e concluir sobre outra. Para `tests/Feature/Sells` (72 arquivos):

| Pergunta | Porta | Antes | Depois |
|---|---|---|---|
| roda em **algum** lugar? | `phpunit.xml` + `scripts/tests/shards-plan.mjs` | ✅ **sim** (nightly CT 100) | ✅ sim |
| roda **no PR**? | allowlists + `ci-sqlite-pest.list` | ❌ não (`grep -rn "Feature/Sells" .github/` = 0) | ✅ 1 arquivo (allowlist) |
| **bloqueia merge**? | `governance/required-checks-baseline.json` | ❌ não | ❌ **não** (advisory por desenho) |

> **Correção de premissa do chip:** o teste **não** nasceria "verde impossível" — os 72 já
> rodavam no nightly. O que faltava era mordida **no PR**. A distinção muda o remédio.

## 5. Veredito da Camada 3

| Gate | Resultado |
|---|---|
| `anchor-lint SPEC.md --check` | **exit 0** · 0 dead · 0 zombie · coverage 92,2% · dead_tests 0 |
| `casos-gate` G-2 (órfãos) | 28 **inalterado** → os 2 UC novos **não** são órfãos (citados 8× no teste) |
| `requisitos-status Sells` | `Index.casos.md` saiu da lacuna "não declara nenhum UC"; `CU no SDD` 0 → 6 |
| YAML da lane | parseia; job `PHP / Pest (Sells · MySQL)` |
| Pest | 🧪 **sem veredito — não rodei** (CT 100/CI, ADR 0062) |

**Predição declarada (não é fato):** espero `UC-SIDX-01 (B)` **vermelho**, porque a subquery
medida não filtra `sr.business_id`. Se vier vermelho, **ele é o achado** — correção é decisão
[W]. Se `UC-SIDX-01 (A)` (controle positivo) vier vermelho junto, o defeito é do **setup**, não
do produto: é para isso que o par existe.

## 6. O que decidi sozinho vs escalei

**Sozinho (dentro do mandato):** criar o SDD e o namespace `CU-SELL-*`; usar 3 segmentos nos UC
novos; **corrigir o enunciado do `UC-SIDX-02`** ao descobrir que os totalizadores usam query
separada da paginada (a redação original teria produzido **falso-vermelho contra comportamento
correto**); aplicar `**Testado em:**` em US-SELL-047 mantendo `_pendente_` (a US não está feita);
manter a lane **advisory** e fora do baseline; listar teste failing-first na allowlist.

**Escalado para [W] (não decidi):**
1. **Achado #3** — escopar (ou não) `has_return` por `business_id`: mexe em query de venda → REGRA MESTRE.
2. **NG-08** — "tipos de serviço" (`typesOfService` existe sem UI): Non-Goal ou US?
3. **Achado #5** — o que fazer com `POST /sells` morto.
4. **D-4** — corrigir o extrator de UC em `scripts/` (fora da área de qualquer chip).
5. **Achado #6** — desenho de isolamento das sessões paralelas (worktree compartilhada).
6. **Merge** (R10).

## 7. Lições de mecanismo (o que na definição do agent atrapalhou)

1. **O chip trouxe 2 premissas erradas e 1 imprecisa** — Blade homônima (#2), "0 UC" (#1) e
   "verde impossível" (§4). Todas caíram na Camada 1. O agent acertou em exigir medição antes
   de aceitar o alvo; o que faltou foi um passo explícito de **"re-medir as premissas do próprio
   chip e reportar as que caírem"** — fiz por instinto, não por instrução.
2. **A Fase 2.2 manda derivar UC do §6 do SDD — mas quando o SDD não existe**, a ordem real é
   §6 primeiro, UC depois. A definição descreve o ramo "SDD existe"; o ramo "SDD não existe" (o
   de 39 dos 40 módulos) não diz que a Fase 2.1 vira **pré-requisito** da 2.2.
3. **O critério de parada funcionou** — a tentação era despejar 15 UC espelhando os 15 CU.
   Medi que estoque (`EstoqueMovimentacaoVendaTest`, lane estoque) e cálculo (`UC-S02`) **já
   tinham dono** e parei em 2 UC genuinamente descobertos. "Cubra menos com contrato real" foi
   a instrução mais útil do chip.
4. **Faltou no agent uma regra anti-vácuo explícita.** Escrevi o controle positivo por causa da
   lápide de 2026-07-24 (*"verde por não-execução"*), que está no `proibicoes.md`, **não** na
   definição do agent. Um UC `[T0]`/`[V0]` que afirma ausência ("não vaza", "não conta")
   deveria **exigir** o par controle-positivo + contrato por construção.
