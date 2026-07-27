---
id: sessions-2026-07-27-auditoria-camada1-sdd-mordida
type: session
date: "2026-07-27"
topic: "Auditoria da Camada 1 do sdd-from-source — a triangulação das 3 fontes já mordeu? (+ errata da grade SDD×Swimm)"
authors: [C]
module: Produto
owner: W
related_adrs:
  - 0351-sdd-from-source
  - 0352-errata-0351-venue-distiller-citacao-taxonomia
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
pii: false
---

# Auditoria da Camada 1 · `sdd-from-source` — a triangulação mordeu?

> **Pedido [W]** (2026-07-27, sessão `priceless-euclid`): comparar a máquina SDD com o modelo
> [Swimm AI-ready codebase](https://swimm.io/solutions/ai-ready-codebase), dizer quais arquivos
> deveria gerar, por que não funciona, e pontuar. Seguido de **"adversario"** e **"pode fazer tudo"**.
>
> Este log tem **duas metades**: (§1-§3) a auditoria que [W] autorizou — *a Camada 1 já mordeu?* — e
> (§4) a **errata** da grade que eu mesmo apresentei antes de auditar, cujo diagnóstico estava errado.
> O recibo do erro vem primeiro na ordem de leitura porque ele condiciona a confiança no resto.

---

## §1 — A pergunta e por que ela era a certa

A [ADR 0351](../decisions/0351-sdd-from-source.md) organiza o agent em 3 camadas e chama a **Camada 1**
(triangular React + Blade + Delphi) de **FUNDAMENTAL** — *"sem as fontes 2 e 3, o agente documenta um
React que pode já ter perdido features. Isso não é opcional."*

Nenhuma medição tinha sido feita sobre essa alegação. A régua que o projeto exige de gate — **mordida
provada**, [ADR 0336](../decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md) DR-2 —
nunca foi apontada pro SDD. A pergunta operacional:

> A Camada 1 achou algo que a Camada 3 (conferir charter × código) **não teria achado sozinha**?

Ela discrimina duas decisões de orçamento muito diferentes: escalar **SDD** (escrever docs) ou escalar
**destilado de legado** (`ANTI-REGRESSAO-*`, que é a pré-condição da Camada 1 e existe em 1 módulo).

**Minha hipótese entrando:** *"todo o valor veio da Camada 3; a Camada 1 não tem evidência de mordida."*
**Resultado: REFUTADA.** Ver §3.

## §2 — Método (e seu limite honesto)

Universo: os **9 commits** que citam `sdd-from-source` em `origin/main`, de
[#4766](https://github.com/wagnerra23/oimpresso.com/pull/4766) a
[#4823](https://github.com/wagnerra23/oimpresso.com/pull/4823) (24/07 → 26/07).
Varredura **contada, sem corte**: `git log --grep="sdd-from-source" -i` → 9 de 9 lidos.

Fonte do veredito: o **corpo** de cada commit (`git log --format=%b`) + o **diff de produção**
(`git show -- app/**/*.php`), nunca o título. Repo **completo** (`--is-shallow-repository=false`,
5.7k commits) — a lápide §5 2026-07-24 (data em clone raso) não se aplica aqui.

**Limite declarado:** classifiquei a origem de cada achado pelo que o **autor do commit declarou**.
Isso é auto-declaração, não prova independente — não rodei os testes (CT 100, [ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md))
nem re-derivei os achados do zero. Vale como **rastro**, não como contrafactual: ninguém pode provar que a
Camada 3 sozinha *jamais* acharia o item. O que os corpos permitem afirmar é mais modesto e ainda
decisivo — **quais achados só são enunciáveis comparando o React com a Blade e o Delphi**.

## §3 — Os achados, por camada de origem

| # | PR | Achado | Origem | Peso |
|---|---|---|---|---|
| 1 | [#4769](https://github.com/wagnerra23/oimpresso.com/pull/4769) | `update()` cross-tenant devolvia **500 em vez de 404** (`first()` → null → `\Error`, que `catch (\Exception)` não pega). `ProductController` corrigido | **C3** charter × código | **Tier 0** ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)) |
| 2 | [#4782](https://github.com/wagnerra23/oimpresso.com/pull/4782) / [#4780](https://github.com/wagnerra23/oimpresso.com/pull/4780) | `Edit.tsx` manda **18 chaves**, `update()` lê **33+**; o writer trata **ausência como zero** (`enable_stock`, `not_for_selling`, `enable_sr_no`, `sub_unit_ids`) e `single_variation_id` ausente → `\Error` → 500. **Ligar a tela React zeraria estoque no 1º save** | **C1 triangulação** (declarado no corpo: *"4 candidatos que o humano não tinha, todos da triangulação React × Blade × Delphi"*) | **Tier 0 estoque `[V0]`** — bloqueador de migração |
| 3 | [#4808](https://github.com/wagnerra23/oimpresso.com/pull/4808) | `business_id` **ambíguo** no `leftJoin` de categorias → `SQLSTATE 23000` → 500 na lista React. `ProductController` corrigido | **C1** (exposto por UC-PIDX-01/02/03/06) | 500 imediato ao ligar |
| 4 | #4808 | Preço de **compra vaza**: a Blade gateia com `@can` (47 linhas / 15 views), o Delphi faz **sumir** (`AR-PROD-015`), o branch Inertia consulta 3 permissões — **nenhuma de preço** | **C1 pura** — só existe comparando as 3 | dado sensível |
| 5 | #4808 | Menu de Ações: Blade **10 por linha**, React **1** — **5 sem Non-Goal declarado** | **C1 pura** | regressão silenciosa de migração |
| 6 | #4808 | `App\Product` **não tem global scope** (`addGlobalScope` = 0). O §3.1 do próprio SDD **afirmava que tinha** | C1/C3 — contradiz doc canon | premissa falsa em canon |
| 7 | #4808 | `limit(200)` sem paginação, com KPI contando o catálogo inteiro | C1 | correção |
| 8 | [#4823](https://github.com/wagnerra23/oimpresso.com/pull/4823) | `default_sell_price_inc_tax` **não existe** (nem coluna nem accessor). **4 telas leem** → null → preço de venda 0. Corrige a premissa do `CU-PROD-14` que o **próprio agent** escrevera | C1/C3 — **auto-refutação** | **Tier 0 valor** |
| 9 | #4823 | `bulkUpdate` grava `variation_group_prices` **sem guard de tenant** — mesmo buraco do [#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300), fechado no irmão e aberto aqui | C1/C3 | **Tier 0** |
| 10 | #4823 | A tela submete pra `/products/mass-update` — **rota inexistente** (0 em `routes/`) | C3 factual | quebrado |
| 11 | [#4807](https://github.com/wagnerra23/oimpresso.com/pull/4807) | 8 asserts afirmavam `file_exists` de RUNBOOK **inexistente** — **vermelho real no nightly** do CT 100, somando ao floor | C3 factual | teste falso |
| 12 | #4823 | **Anti-gaming no painel**: o predicado era `src.includes(id)` — escrever o id em **qualquer parágrafo** fechava a lacuna sem contrato. Achado pelo próprio agent, que **testou e reportou** (*"fiz, vi fechar, desfiz"*) | meta | **presence-gate vivo** (LC-11) |
| 13 | [#4770](https://github.com/wagnerra23/oimpresso.com/pull/4770) | Errata [ADR 0352](../decisions/0352-errata-0351-venue-distiller-citacao-taxonomia.md) derrubou **3 alegações da própria 0351** (Camada 1.3 não funciona; citação "taxonomia 0345" falsa; "40% da visão" sem fonte) | meta | honestidade |

### Veredito

**A Camada 1 mordeu — e mordeu onde só ela podia morder.**

Dos 11 achados de código, **pelo menos 6 têm origem na triangulação**, incluindo o mais grave (#2,
bloqueador de migração que zeraria estoque) e os dois que **nenhuma conferência charter × código
alcançaria por construção** (#4 preço de compra, #5 menu 10→1) — porque o charter descreve o **React**;
só a Blade e o Delphi sabem o que a tela **fazia antes**. É exatamente a regressão-silenciosa que a
0351 previu, citando o menu sumido no [#1032](https://github.com/wagnerra23/oimpresso.com/pull/1032).

Dois sinais de qualidade do método, que valem registro:

- o [#4782](https://github.com/wagnerra23/oimpresso.com/pull/4782) foi desenhado como **grupo de
  controle** (agent rodado na tela cujo `casos.md` fora feito à mão, sem sobrescrever, prefixo
  `_b1-controle-`, comparação UC a UC) — e o veredito publicado foi **desfavorável ao agent** em parte
  (*"perdeu 2 UCs que o humano derivou; errou 1 status — auto-acusação de LC-08 dele"*);
- o [#4823](https://github.com/wagnerra23/oimpresso.com/pull/4823) mediu o **falso-positivo do próprio
  fix** antes de fechar (a 1ª versão do regex anti-gaming acusou 3 CU legítimos porque o corpus usa
  blockquote, não bullet) — *"o padrão vem do que o projeto escreve, não do que eu imaginei"*.

### O que isso muda na decisão de escalar

O gargalo **não é** escrever SDD, nem a Camada 3. É a **pré-condição da Camada 1**: o destilado do
legado. Medido hoje, sem corte:

```
git ls-files "memory/requisitos/*/ANTI-REGRESSAO*"  →  2 arquivos, 1 módulo (Produto)
```

A triangulação das 3 fontes é **executável em 1 módulo de ~40**. Onde ela é executável, rodou e pagou.
O investimento marginal que destrava os outros é **destilar o legado dos módulos em migração MWART** —
não escrever mais SDD. Nada disso é decisão minha; fica registrado para [W] com o número ao lado.

## §4 — Errata: a grade que apresentei antes de auditar estava errada

Antes desta auditoria eu entreguei a [W] uma grade da máquina SDD (nota **47/100**) com 7 falhas.
Quatro defeitos meus, medidos depois:

**(a) Denominador inventado.** Pontuei cobertura como `1 SDD / 40 módulos = 2,5%`. **Nenhuma decisão
estabelece que 40 módulos precisam de SDD.** O denominador executável — onde a pré-condição da Camada 1
existe — é **1**. Cobertura real: **1/1**. Pontuei a máquina errada: a dívida é do destilado de legado.
É o vício de denominador que o §5 já registra em 2026-07-17 (*"confundir 'tela sem baseline' com 'tela
que deveria ter baseline'"*).

**(b) Métrica de outro universo lida como veredito.** Chamei de *"o achado que fecha o diagnóstico"* o
fato de o `anchor-lint` do Produto seguir em **11,1%** depois de 4 PRs de trio. O `anchor-lint` mede **US
do SPEC**; o trio produz **`casos.md` por tela**. Fui ver as 9 US: `US-PROD-020..027` são **roadmap não
implementado** (duas delas — 025 e 027 — estavam **em voo no brief do dia**); só a `028` está entregue,
e é **a única com âncora**. O campo vazio está **correto**. O que sobra é dívida de forma: as 8
deveriam trazer `**Implementado em:** _pendente_` — **8 linhas de higiene**, não máquina quebrada.

**(c) Nota agregada proibida.** O `47/100` somou **capacidade do mecanismo** (verificação, ancoragem,
sync) com **quantidade de artefato existente** (cobertura), que é função de decisão de [W] + tempo. São
incomensuráveis, e o teste de nocividade da lápide §5 2026-07-17 (C9) se aplica: **maximizar o número
significa gerar 39 SDDs**, ou seja, big-bang de legado — proibido pelo §5 2026-07-12. **Nota retirada**;
as dimensões separadas seguem válidas.

**(d) Premissa aceita sem contestar.** [W] perguntou *"por que não funciona"* e eu fui listar por que
não funciona. A [ADR 0351](../decisions/0351-sdd-from-source.md) é de **24/07**; o
[SDD-TEMPLATE](../requisitos/_DesignSystem/SDD-TEMPLATE.md), de **26/07 — um dia antes da pergunta**.
Medir cobertura de uma máquina de 3 dias e chamar de falha é tratar **latência como fracasso**.

**Bônus:** recomendei a [W] a pergunta *"o piloto pagou?"* alegando indício em **títulos** de commit.
A resposta estava a um `git show` de distância — nos **corpos**. Deveria ter medido antes de recomendar.

### O que da grade sobrevive à auditoria

| Falha | Veredito | Por quê |
|---|---|---|
| **Zero sync da âncora** | ✅ **de pé** | estrutural, independente de tempo. `verificado@<sha>` não é regravado por ninguém; o lint denuncia o cadáver (`anchored_dead`), nada reconcilia. É o delta real vs. o auto-sync do Swimm |
| **Dois denominadores de tela** (`casos:report` 280 × `screen-coverage` 235) | ✅ **de pé** | fato medido; toda % de cobertura de tela é ambígua enquanto as duas portas não declararem o que contam |
| Badge `⚙️/🖐` ausente no exemplar | 🟡 enfraquece | verdade (0 no SDD do Produto), mas o template que o exige nasceu **ontem** — pendência de 1 dia |
| Distiller desligado (Camada 1.3) | 🟡 enfraquece | é **escopo declarado** na [ADR 0352](../decisions/0352-errata-0351-venue-distiller-citacao-taxonomia.md), não máquina quebrada. Meu *"religou desligado"* foi retórica |
| UC órfão trava merge (G-2) | ❌ cai | é trade-off consciente e documentado (honestidade > volume) |
| Âncora parada em 11,1% | ❌ cai | erro de fonte meu — item (b) |
| "Nada puxa o SDD a existir" | ❌ cai | com denominador 1/1, não há o que puxar |

**Placar: 2 de pé, 2 enfraquecidas, 3 derrubadas.** A máquina está bem melhor do que a minha grade
pintou, e o defeito real dela é mais estreito: **a âncora não se reconcilia sozinha**, e **as portas não
concordam sobre o denominador**.

## §5 — Rastro

- **Ledger:** `LC-08` incrementado 12 → 13 ([`LICOES_CODE.md`](../LICOES_CODE.md)), recibo `07-27`.
- **Lápide:** [`proibicoes.md`](../proibicoes.md) §5 — *"Grade de máquina com denominador INVENTADO…"*.
- **Não virou gate, de propósito:** *"o denominador é o executável"* é predicado **semântico** —
  qualquer sonda sintática cairia na família já morta (allowlist-de-pasta 2026-06-30, guard `@scope`
  2026-07-09, vocabulário 130 FP 2026-07-16). A defesa aqui é o §5 + a ordem de leitura, não YAML.
- **Colateral:** meu run local de `sdd-scorecard.mjs` degradou `governance/sdd-scorecard.json`
  (16 inserções / 89 deleções — sem `--junit` e sem full-suite, métricas caem pra `not_yet_measured`).
  **Revertido, não commitado** — seria regredir um baseline required pra "passar", que é teatro (§5).
  Fica o aviso: **rodar o scorecard fora do CT 100 suja o artefato**; conferir `git status` depois.
