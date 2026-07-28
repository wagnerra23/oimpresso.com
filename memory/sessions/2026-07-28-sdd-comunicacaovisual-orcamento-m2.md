---
id: sessions-2026-07-28-sdd-comunicacaovisual-orcamento-m2
type: session
date: "2026-07-28"
topic: "SDD do ComunicacaoVisual derivado do fonte (chip Onda 4 do passo 5) — tela-âncora ComunicacaoVisual/Index, orçamento por m²"
module: ComunicacaoVisual
owner: wagner
autor: "[CC] via agent sdd-from-source (ADR 0351)"
lifecycle: ativo
authors: [C]
related_adrs:
  - 0351-sdd-from-source
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0093-multi-tenant-isolation-tier-0
  - 0105-cliente-como-sinal-guiar-sem-mandar
related_us: [US-COMVIS-001, US-COMVIS-002, US-COMVIS-004, US-COMVIS-006]
---

# Sessão 2026-07-28 — SDD do ComunicacaoVisual (orçamento por m²)

Chip da **Onda 4** do [passo 5](../requisitos/_Governanca/programa-ondas/passo-5-sdd-por-modulo.md),
agent [`sdd-from-source`](../../.claude/agents/sdd-from-source.md) ([ADR 0351](../decisions/0351-sdd-from-source.md)).

**O que este módulo tem de diferente dos irmãos:** é o **primeiro do passo 5 sem cliente em
produção**. Produto/Sells/Vestuario documentam código que a Larissa usa; aqui **14 das 18 US são
plano**, e o exercício foi tanto *documentar o que existe* quanto *não documentar o que não existe*.

---

## 1. As 3 fontes — e o que a triangulação achou

| # | Fonte | Estado | Recibo |
|---|---|---|---|
| 1 | Canon | ✅ | SPEC (18 US) · BRIEFING (honesto, 2026-06-15) · PII-LGPD · charter (Wave 25) · CAPTERRA |
| 2 | React/Laravel | ✅ | `Index.tsx` 531L · 4 Controllers · 2 Services · 10 Entities · 9 migrations · 20 Pest |
| 3 | Blade legada | ❌ **inexistente** | `find resources/views -ipath "*comvis*" -o -ipath "*comunicacao*"` = **0** — o módulo nasceu Inertia. Não é gap: é ausência de fato |
| 4 | Delphi / Office Comercial | ⚠️ **corpus existe, destilação não** | `find memory -iname "*ANTI-REGRESSAO*"` = **2**, ambos do Produto |

**A decisão sobre a fonte 4 — e por que não inventei paridade.** O chip avisava que a fonte 4
"pode existir aqui" (o legado WR Comercial é de gráfica). Existe **corpus** (`memory/legacy-delphi/`,
`memory/dominios/wr-comercial/`, `PLANO-MIGRACAO-6-SAUDAVEIS.md`) e é do ramo certo. Mas
**nenhuma feature foi destilada em contrato de paridade** pra este módulo — e a diferença é
categórica: no Produto a fonte 4 é *não-regressão* (a tela React substituiu uma tela Delphi em uso);
aqui **não houve cutover**, nenhuma das 6-7 gráficas migrou. Destilar agora produziria um
`ANTI-REGRESSAO` que **parece contrato de paridade e não é** — anti-padrão inventado parece canon.
Registrado como dívida **D-4** do SDD: nasce com a 1ª piloto, é pré-requisito do cutover.

---

## 2. Achados (varredura contada, âncora citada)

### A-1 · A rota não entrega o catálogo que a tela declara — `CU-CV-09`, vermelho esperado

`Index.tsx` declara `Props { bizName?, materiais?, podeCriar? }` e monta o `<select>` de material a
partir de `materiais`. **A única rota que renderiza a página passa só `bizName`.**

Varredura contada:
- `git grep "ComunicacaoVisual/Index" -- '*.php'` = **2 hits**: 1 comentário (`DataController:89`) + **1 render real** (`Routes/web.php:25`).
- `git grep "'materiais'" -- Modules/ComunicacaoVisual` = **1 hit**, e é **string de tradução**.
- `git grep -rn "podeCriar"` = **3 hits, todos dentro do próprio `.tsx`** (nenhum produtor).

Cadeia: `materiais=[]` ⇒ `semCatalogo=true` ⇒ `<select>` **disabled** ("Sem catálogo") ⇒
`material_id` sempre `null` ⇒ o ramo (2) do `resolverPreco` **nunca é exercitado em produção** ⇒
a operadora digita o preço/m² à mão em toda peça. O `MaterialSeeder` semeia 5 materiais que a tela
nunca vê.

**≥2 fontes prometem o contrário** → vira UC, não backlog: docblock do `.tsx` · `BRIEFING.md` ·
DoD da `US-COMVIS-002`. Teste failing-first: `ContratoTelaOrcamentoTest` (**UC-CV-07**), com assert
**comportamental** (o *nome* do material chega ao payload) — não acoplado ao nome da prop, porque
há **duas correções válidas** (passar a prop na closure **ou** buscar por fetch) e assert por chave
literal reprovaria uma delas arbitrariamente.

### A-2 · Os `[T0]` do módulo não são exercitados no PR — dívida D-7

**Qual das 3 portas eu medi — as três, separadamente:**

| Pergunta | Porta | Resposta |
|---|---|---|
| roda em algum lugar? | `phpunit.xml` (testsuite `Feature` inclui `./Modules/ComunicacaoVisual/Tests/Feature`) + `scripts/tests/shards-plan.mjs` (`--roots tests,Modules`) | ✅ sim — full-suite noturna, MySQL |
| roda no PR? | `modules-pest.yml` — matrix de 6 módulos, `DB_CONNECTION=sqlite :memory:` **sem migrate** | ⚠️ **6 dos 20** arquivos abortam no `beforeEach` |
| bloqueia merge? | `governance/required-checks-baseline.json` — Pest required = **Financeiro, NfeBrasil, Unit** | ❌ não — advisory |

Os 6 que pulam inteiros (contados): `MultiTenantTest`, `Tier0GuardTest`, `OrcamentoControllerTest`,
`MaterialSeederTest`, `MigrationsTest`, `CustomerJourneyTest`. **São exatamente os que provariam o
isolamento multi-tenant.** O verde da lane no PR prova que foram *pulados* — família "verde por
não-execução" ([proibicoes §5](../proibicoes.md) 2026-07-24). Corrigir exige mexer no
`modules-pest.yml`, **compartilhado com 6 módulos** → reportado, não tocado.

### A-3 · `anchor-lint` diz "verde impossível" — e está CERTO

As 4 US ancoradas ficam com `🚦 tem teste-que-cobre mas NENHUM numa lane de JUnit`. Investiguei a
origem: `inLane()` deriva de `.github/ci-sqlite-pest.list` + workflows que contenham `--log-junit`.
O `modules-pest.yml` roda `vendor/bin/pest … --no-coverage --colors=always` — **sem `--log-junit`**.
Logo o gate-verde nunca poderá ler um verde destas US. **Não é falso-positivo** (diferente do caso
stale de 2026-07-27 já corrigido no script): é diagnóstico correto de uma lane que não emite junit.
Advisory; `--check` sai 0. Fora da minha área → reportado.

### A-4 · Menores, todos com âncora no SDD

- **§5.4.2** `store`/`show` da API de orçamento **não têm consumidor** (`git grep "api/orcamentos" -- '*.tsx'` = **0**). Não é bug — é o TODO "Sprint 2" declarado no `.tsx`. Registrado pra ninguém ler os testes e concluir que "salvar" funciona pela UI.
- **§5.4.3** `gerarNumero()` lê `MAX(numero)` fora da transação e sem lock — mas a migration tem `UNIQUE (business_id, numero)`: a corrida vira **erro visível**, não dois orçamentos com o mesmo número. Dívida, não Tier 0.
- **§5.4.4** `store()` tira `business_id` de `session(...)`, não de `auth()` — **observação, não achado**: não varri o `SetSessionData` a fundo o bastante pra afirmar exploração ([proibicoes §5](../proibicoes.md) 2026-07-15).
- **§5.4.5** 5 entidades do PCP + o `FsmProcessoComunicacaoVisualSeeder` são **schema órfão**: migration + Entity + teste de isolamento, **zero controller/rota/tela**.
- **§5.4.6** o `minimo_m2` do DoD da US-001 **não existe** — a tabela tem `estoque_minimo_m2` (estoque, outra coisa).
- **§5.4.7** não há `memory/dominio/comunicacao-visual.md` → o módulo está **fora do `dominio-gate`** (G-4, required).

---

## 3. Artefatos (todos dentro da área do chip)

| Artefato | Estado |
|---|---|
| `memory/requisitos/ComunicacaoVisual/SDD-tela-orcamento-m2-v1.0.md` | **novo** — §0–§11, 10 CU, 8 dívidas |
| `resources/js/Pages/ComunicacaoVisual/Index.casos.md` | **novo** — 12 UC + 9 `[BACKLOG]` |
| `Modules/ComunicacaoVisual/Tests/Feature/ContratoTelaOrcamentoTest.php` | **novo** — 3 casos (2 comportamentais + 1 guard estrutural) |
| 9 testes existentes do módulo | `@covers-us` + citação dos UC nos docblocks |
| `SPEC.md` | 4 linhas `**Testado em:**` (US-001/002/004/006) |
| `BRIEFING.md` | redestilação **parcial** — 2 afirmações otimistas corrigidas |
| `SUPERFICIE.md` · `_STATUS-GENERATED.md` | regenerados pela máquina |

**Não toquei** (proibido pelo chip): `scripts/**` · `governance/*.json` · `.github/workflows/**` ·
`proibicoes.md` · `LICOES_CODE.md` · `08-handoff.md` · o `charter` (nenhuma correção **factual**
era necessária — o charter diz "stub Sprint 2", que segue verdadeiro; o resto dele é intenção).

---

## 4. Veredito da Camada 3

| Gate | Antes | Depois |
|---|---|---|
| `requisitos-status ComunicacaoVisual` | 0 CU · 0 casos.md · 0 UC · **1 lacuna** | **10 CU · 1 casos.md · 12 UC · 12 com teste · 0 lacuna** |
| `anchor-lint --check` | 100% cov, **4 US sem teste que a cobre** | 100% cov, **0** · exit 0 |
| `anchor-lint --check-covers` | 4 `testado_sem_covers` | **0** · exit 0 |
| `anchor-lint --check-entry` | gate de entrada: 4 | **0** · exit 0 |
| `casos-coverage-guard` | — | exit 0 |
| `screen-coverage-map` | — | exit 0 |
| `module-surface --check` | **DRIFT** | OK (64 arquivos) |

⚖️ **Nenhum status acima é veredito de teste.** Não executei Pest (CT 100/CI — [ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md)).
"Vermelho esperado" no UC-CV-07 é **predição declarada**, derivada de leitura com varredura contada.

### 🚩 Vermelho de terceiro — reportado, não consertado

`node scripts/governance/doc-id-index.mjs --check` → **exit 1**. Atribuição contada: `--write`
produziria **62 inserções**, das quais **1** é minha (o id do SDD novo) — as outras **61** vieram
de chips irmãos rodando **na mesma worktree**. `governance/*.json` está na minha lista de proibidos.
**Para o parent:** rodar `doc-id-index.mjs --write` uma vez na consolidação, junto do
`casos:baseline:write`.

> ℹ️ **Descoberta operacional do dia:** a worktree estava limpa no `git log` do início, mas o
> `git status` ao final mostra **~60 arquivos de outros módulos** (KB, TeamMcp, Vestuario, Cliente,
> Compras) — sessões irmãs escrevendo **em paralelo na mesma árvore**. O isolamento **por módulo**
> funcionou (zero overlap de arquivo), mas os **globais derivados** (`doc-id-index`, baseline do
> casos) acumulam drift de todos. O `git status` de um chip **não** é o diff dele.

---

## 5. Orçamento da corrida

| Dimensão | Número |
|---|---:|
| Arquivos lidos (integral ou parcial) | **31** |
| Varreduras `git grep`/`find` contadas | **11** (renders do Index · `'materiais'` · `podeCriar` · `ANTI-REGRESSAO` · `PARIDADE` · Blade comvis · `office comercial` · `CU-CV`/`UC-CV` · `@covers-us` · skip-sqlite · `cv_substratos`/ncm) |
| Execuções de porta viva / gate | **14** |
| CU criados | **10** (sobre 4 US entregues/parciais) |
| UC criados | **12 ancorados** + **9 `[BACKLOG]`** sem id |
| US que ficaram **sem** CU/UC de propósito | **14** (todas `_pendente_` — §6.9 do SDD) |
| Testes novos | 1 arquivo, 3 casos (1 previsto vermelho) |
| Testes existentes anotados | 9 |
| Achados com varredura contada | **6** (1 previsto quebrado · 1 estrutural de lane · 4 dívidas) |

**Reuso vs re-varredura (Fase 1.4):** **zero reuso** — este é o **1º SDD do módulo**, então toda a
Camada 1 foi paga do zero (não havia §5.3 nem `AR-*` a reusar). O que *barateou* foi outra coisa:
**as fontes 3 e 4 voltaram vazias**, e resolver isso custou 4 varreduras em vez de horas de leitura
de Blade. O corolário pra fila: **módulo sem Blade e sem cutover é chip mais barato que módulo
migrado**, mesmo tendo mais US no SPEC — o custo mora na *triangulação*, não na contagem de US.

**Gargalo — o que consumiu mais:** decidir **o que NÃO documentar**. O SPEC tem 18 US e descreve um
ERP inteiro; a parte cara foi separar, US por US, "tem código" de "tem prosa", e resistir a escrever
CU pra PCP/instalação/NFSe/DAM — o que teria dobrado o SDD e criado 20+ UC órfãos que travariam o
merge de quem for implementar. Segundo gargalo: **medir as 3 portas do veredito** (A-2/A-3) — três
arquivos diferentes, e a resposta certa só apareceu porque a pergunta foi feita três vezes.

---

## 6. Lições de mecanismo (o que na definição do chip atrapalhou)

1. **`Testado em:` é mais estrito do que o aviso deixa claro — e o custo é silencioso.** O chip
   dizia *"leva só o arquivo de teste"*. Eu pus o path **e** a prosa de contrato na mesma linha; o
   `anchor-lint` parseou `SDD §6 CU-CV-02/CU-CV-03` e `Index.casos.md` **como paths de teste** e
   cuspiu 4 `testado_sem_covers` + 1 `anchored_dead` (cobertura 100% → 94,4%). O mesmo aconteceu no
   `Implementado em:`, onde a string `ComunicacaoVisual/Index` (nome de **componente**) virou path
   morto. **Sugestão pra definição do agent:** *"a linha `Testado em:`/`Implementado em:` contém
   APENAS paths; qualquer prosa vai num blockquote na linha seguinte"* — foi o que resolveu, e não
   é óbvio antes de rodar.

2. **A ordem certa é: CU primeiro, rodar a porta, DEPOIS os UC.** Escrevi 9 UC de cabeça e a porta
   viva acusou 3 CU órfãos (`CU-CV-01`/`05`/`07` sem UC). Rodar `requisitos-status` logo após o §6 —
   antes de escrever o `casos.md` — teria dado a lista exata de UC a criar. Cabe na Fase 2.2 como
   passo explícito.

3. **A decisão de `distilled_at` precisa estar no chip, não na cabeça do agente.** O buraco #1 do
   plano manda "redestilar parcialmente e declarar no `distilled_by`". Fui ler o medidor
   (`measureDistillerFreshness`) e ele **não penaliza porta sem carimbo** — `sem_carimbo` = 67 de 78;
   só `stamped && stale` conta. Carimbar põe o módulo no conjunto *stale-able* de uma métrica
   **ARMADA** (GT-G3) **para sempre**: qualquer doc dele tocado >7d depois, por qualquer pessoa,
   vira `stale +1`. Num módulo em construção (docs em rajada, depois silêncio) o custo cai em
   terceiros e o ganho é zero. **Decidi não carimbar** e registrei o porquê no rodapé do BRIEFING.
   Se o desenho quiser o carimbo assim mesmo, isso tem que estar escrito — hoje o chip e o medidor
   apontam pra lados diferentes.

4. **"Módulo sem cliente" merece um ramo próprio na definição.** As Fases 2.3/2.4 assumem cutover
   (âncoras no SPEC, `ANTI-REGRESSAO`, `PARIDADE`). Aqui a resposta certa pras duas últimas é
   *"ainda não, e eis por quê"* — que é trabalho de julgamento, não de preenchimento. Valeria uma
   linha no agent: *"sem cutover, `ANTI-REGRESSAO`/`PARIDADE` não nascem; declare a dívida e o gatilho"*.

5. **Paralelismo na mesma worktree quebra a leitura do próprio diff.** Ver o box do §4. O chip pede
   `whats-active` no início — o que ele **não** diz é que, no fim, `git status` mostra o trabalho de
   todo mundo. Vale a definição mandar o chip **listar explicitamente seus arquivos** na devolutiva
   (foi o que fiz no §3), senão a consolidação do parent vira adivinhação.

---

## 7. O que precisa de [W]

1. **Merge** (R10).
2. **`CU-CV-09` / UC-CV-07 — prioridade de produto:** confirmado o vermelho, a rota passa a entregar
   o catálogo **agora** ou vira US? (o diff é pequeno; a decisão é de produto, não minha).
3. **Dívida D-7 — decisão de escopo:** as suítes DB do ComVis passam a rodar contra MySQL no PR
   (como Financeiro/NfeBrasil), ou o veredito Tier 0 fica **declaradamente noturno**? Mexer no
   `modules-pest.yml` afeta **6 módulos** — fora da área de qualquer chip individual.
4. **`Non-Goals` do charter** — o agente é proibido de inferir; o §6.10 do SDD está `⬜ aguardando [W]`.
5. **Dívida D-1 — schema órfão:** construir o PCP ou marcar as 5 entidades como reservadas? Hoje
   elas custam manutenção e sugerem capacidade inexistente.
