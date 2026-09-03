---
id: resources-js-pages-financeiro-plano-contas-index-casos
casos: Plano de contas · /financeiro/plano-contas
irmaos: charter ao lado (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-08-31"
---

# Casos de uso — /financeiro/plano-contas

> **Status:** ✅ passa (prova no manifesto) · 🧪 prova existe mas o `it()` ainda **não cita o id** (G-2 — corrigir no mesmo PR) · ⬜ sem prova · ❌ quebrou.
>
> Redigido pelo [CC] em 2026-08-17 a partir do charter da tela + do protótipo Cowork. Regra G-2 ([ADR 0264]) respeitada: comportamento **sem teste** fica em **[BACKLOG] sem id** — `UC-*` órfão quebra o `casos-gate`.

Charter em **draft**. Tela de CONSULTA de cadastro contábil (~47 entries DCASP por business).

> **Âncora dos UC abaixo:** [`SPEC.md` **R-FIN-009**](../../../../../memory/requisitos/Financeiro/SPEC.md) — *"47 contas do plano padrão Receita Federal são seedadas com `business_id` correto"* — mais o §Goals do charter ao lado, e a [ADR 0093] pro eixo Tier 0.
>
> A própria R-FIN-009 declarava, em **Testado em:**, a palavra `_lacuna_` com a nota *"sem teste dedicado de seed do plano de contas; cobertura a criar"*. Os UC desta seção fecham o eixo de **leitura** dessa lacuna (a tela lista o que foi seedado, escopado por tenant); o eixo de **seed** — o `SeedPlanoContasPadrao` disparar no `BusinessCreated` e proteger os códigos — **segue aberto e não foi coberto aqui**, pra não vender cobertura que não existe.
>
> Não há `CU-FIN-*` de plano de contas no [SDD do módulo](../../../../../memory/requisitos/Financeiro/SDD-tela-financeiro-v1.0.md). Ancorar num CU alheio seria âncora falsa — gap declarado, não inventado (mesma postura do `Caixa/Index.casos.md`).

| id | caso | força | âncora | prova | status |
|---|---|---|---|---|---|
| UC-FPC-01 | Lista o plano com o shape que a tela consome, ordenado por código | `must` | R-FIN-009 + charter §Goals | `PlanoContaControllerTest` | ⬜ |
| UC-FPC-02 | Conta de outro negócio nunca aparece | `must` `[T0]` | [ADR 0093] + R-FIN-009 | `PlanoContaControllerTest` | ⬜ |
| UC-FPC-03 | Conta inativa fica fora da lista | `should` | charter §Goals (`ativo = true`) | `PlanoContaControllerTest` | ⬜ |
| UC-FPC-04 | O KPI conta exatamente as linhas listadas | `should` | charter §Goals (FinStatStrip) | `PlanoContaControllerTest` | ⬜ |

> **Por que os quatro nascem `⬜` e não `✅`.** O teste existe, cita cada id no `it()` e roda na lane do Financeiro — mas `✅` significa **prova verde no manifesto**, e o manifesto só aterrissa depois de a lane rodar (`casos-results-publish`). Declarar `✅` antes disso é o que o G-7 chama de `status:unverified`, e o `casos-coverage-guard` reprovou a primeira redação desta tabela justamente por isso. Sobem pra `✅` quando o manifesto trouxer o veredito — não antes, e não pela minha palavra.

## UC-FPC-01 — Listar o plano com o shape que a tela consome `[must]`

**Dado** um business com plano de contas seedado
**Quando** Eliana abre `/financeiro/plano-contas`
**Então** a página é `Financeiro/PlanoContas/Index` e entrega `planos[]` e `stats`
**E** cada conta traz os campos que a tabela renderiza — `codigo`, `nome`, `tipo`, `nivel`, `natureza`, `aceita_lancamento`, `protegido`
**E** a lista sai **ordenada por `codigo`**, que é o que produz a hierarquia visual (`1` → `1.1` → `1.1.01` → `1.1.01.001`); o Controller não usa árvore recursiva.
- **Teste:** `Modules/Financeiro/Tests/Feature/PlanoContaControllerTest.php` — `it('UC-FPC-01 · lista o plano com o shape que a tela consome, ordenado por codigo')`
- **Status: ⬜** (teste escrito e citando o id; aguarda o manifesto)

## UC-FPC-02 — Conta de outro negócio nunca aparece `[must]` `[T0]`

**Dado** dois businesses com plano próprio
**Quando** um usuário do business A abre a tela
**Então** nenhuma conta do business B aparece em `planos[]`, nem contribui pro `stats`.
- **Teste:** `Modules/Financeiro/Tests/Feature/PlanoContaControllerTest.php` — `it('UC-FPC-02 · Tier 0 — conta de outro negocio nunca aparece')`
- **Status: ⬜** (teste escrito e citando o id; aguarda o manifesto)

> Tier 0 IRREVOGÁVEL ([ADR 0093]). Aqui há **duas camadas**, e o UC prova o resultado das duas juntas: o Model `PlanoConta` usa o trait `BusinessScope` (global scope por sessão, com bypass explícito pra superadmin), **e** o `PlanoContaController@index` ainda filtra `->where('business_id', session('user.business_id'))` por conta própria. É por isso que este UC prova o **comportamento da rota** em vez da configuração de qualquer uma delas: se uma camada cair, o teste só fica verde enquanto a outra segurar — e se as duas caírem, ele cai.

## UC-FPC-03 — Conta inativa fica fora da lista `[should]`

**Dado** uma conta do plano com `ativo = false`
**Quando** a tela carrega
**Então** ela não aparece em `planos[]` — a tela é a visão do plano **em uso**, e conta desativada é histórico.
- **Teste:** `Modules/Financeiro/Tests/Feature/PlanoContaControllerTest.php` — `it('UC-FPC-03 · conta inativa fica fora da lista')`
- **Status: ⬜** (teste escrito e citando o id; aguarda o manifesto)

## UC-FPC-04 — O KPI conta exatamente as linhas listadas `[should]`

**Dado** o plano carregado
**Então** `stats.total` é igual ao número de itens em `planos[]`
**E** a soma das contagens por tipo (`receita` + `despesa` + `ativo` + `passivo` + `patrimonio` + `custo`) não excede o total.
- **Teste:** `Modules/Financeiro/Tests/Feature/PlanoContaControllerTest.php` — `it('UC-FPC-04 · o KPI conta exatamente as linhas listadas')`
- **Status: ⬜** (teste escrito e citando o id; aguarda o manifesto)

> O `FinStatStrip` no topo é lido como resumo do que está logo abaixo. Se as duas contagens divergirem, o número mente — e mente sobre o vocabulário que classifica todo lançamento do módulo.

## Backlog de casos (sem id — entram quando tiverem teste)
- **[BACKLOG] Badge de tipo e natureza D/C** — cada conta mostra tipo (receita/despesa/ativo/passivo+patrim.) e natureza débito/crédito.
- **[BACKLOG] Conta protegida sinaliza cadeado e não é editável aqui** — index é read-only (o Controller só tem `index`).
- **[BACKLOG] Filtro por tipo + busca client-side** — sem round-trip ao servidor (`useMemo`).
- **[BACKLOG] Empty state instrui o seed** — business sem plano vê a instrução, não uma tabela vazia muda.
- **[BACKLOG] Primary "Nova conta"** — o charter registra que a rota `create` não foi encontrada no Controller: **confirmar existência antes de virar UC** (senão é botão que mente).
- **[BACKLOG] Seed dispara no `BusinessCreated` e protege os códigos** — a outra metade da R-FIN-009: 47 entries nascem com o business e `1.1.01.001`/`3.1.01.001` não podem ser deletados. Exige teste do `SeedPlanoContasPadrao`, não do Controller.

> Os quatro primeiros são **visuais ou client-side** — a prova deles é E2E/Browser, não Controller. Ficam sem id de propósito: promover sem a prova certa é o que o G-2 existe pra impedir.

## Trilha do tempo
- 2026-08-31 · [CC] **4 casos saem do backlog e ganham id** (`UC-FPC-01..04`) + `PlanoContaControllerTest` que os cita. Motivo: era a única tela **viva** do sistema a uma peça de fechar o ciclo (`ciclo-completo.mjs` — as outras duas da lista estão `deprecated`, e o casos.md delas registra que investir contrato em tela que vai morrer é dívida). Âncora achada na R-FIN-009, que declarava `_lacuna_` e pedia cobertura. Metade da R-FIN-009 (o seed) **fica aberta e declarada** no backlog.
- 2026-08-17 · [CC] criado no espelho Cowork. Achado: a tela é o vocabulário que classifica TODO lançamento (o filtro de plano da Unificada depende dela) e está sem uma única prova.

[ADR 0264]: ../../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
