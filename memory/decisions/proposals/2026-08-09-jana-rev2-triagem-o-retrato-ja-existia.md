---
title: "Jana rev2 — triagem: metade já está em prod, e o retrato que a Onda 0 queria construir já existe"
status: proposta
date: "2026-08-09"
owners: [W]
parent_module: Jana
related_adrs: [93, 104, 256, 264, 275, 315, 366]
related_specs:
  - memory/requisitos/Jana/SPEC.md (US-COPI-031, 040, 060, 061)
related_charters:
  - resources/js/Pages/Jana/Index.charter.md
---

# Jana rev2 — triagem

> Triagem do pedido [CC] `JANA-ONDAS-PR-2026-08-09 · revisão 2`. Continua a
> [reconciliação da rev1](2026-08-09-jana-ondas-5-a-12-delta-e-donos.md) (mergeada, [#5495](https://github.com/wagnerra23/oimpresso.com/pull/5495)).
>
> A rev2 foi escrita contra o tree de **código** anterior aos merges de 2026-08-09 — o próprio
> pedido admite que *"o main andou durante a sessão"*. Por isso repete como pendente o que já
> está em produção. Esta triagem separa: **feito · stale · novo-e-verdadeiro · novo-e-falso**.

## 1. Já está em produção (a rev2 lista como onda 5)

Tudo no [#5496](https://github.com/wagnerra23/oimpresso.com/pull/5496), mergeado, deployado, com smoke real em prod:

| Item da rev2 | Estado |
|---|---|
| `S-1` `reapurar` não despacha | ✅ despacha, com `businessId` explícito e `Meta` carregada pelo global scope |
| `S-2` `alertas/config` não persiste | ✅ form `disabled` + rota devolve *"nada foi alterado"* |
| `S-3` "STUB spec-ready" na tela | ✅ 3 views com empty state honesto |
| `B-7` empty state do superadmin | ✅ string removida |
| cláusula de saída da onda 5 (*"teste que varre as views"*) | ✅ `JanaViewsSemAndaimeTest`, ligado na lane, rodou e passou |

**`B-7` repete uma claim já corrigida:** a rev2 diz *"vaza a meta de faturamento da plataforma"*. **Não vaza** — o número está redigido no git desde o `filter-repo` de 2026-06-08. Era string quebrada, e saiu.

**Stale na tabela §0:** alertas não tem mais *"stub declarado na tela"*.

## 2. O que a rev2 não sabe (medido em 2026-08-09)

- **Nenhuma meta existe.** 0 em `biz=1`, **0 cross-business** (tela superadmin: *"Nenhum cliente configurou metas ainda"*), 0 hits em 30d. As ondas 8/10/11 migrariam telas de uma feature que **ninguém jamais cadastrou**.
- **Ondas 8/10/11 já têm dono:** [`PLAN-MWART-metas.md`](../../requisitos/Jana/PLAN-MWART-metas.md) + 10 US. Reconciliado no #5495.
- **Onda 8 está travada:** não existe `RUNBOOK-metas.md`; o hook `block-mwart-violation` barra o 1º `Edit`, e o `/mwart-override` que ele anuncia **não existe no código** (§5 2026-08-08).
- **Decisões §6 #2 e #5 já respondidas no canon:** metas é FOCO (US-COPI-148); fonte é editor com preview (DoD da US-COPI-040).

## 3. Novo e VERDADEIRO

### `A-1` — o Blade se escondia ✅ **consertado**

`module-surface.mjs:243` tinha `listar: false`: o índice imprimia `## Views (Blade) — 9` sem dizer quais. Corrigido em [#5502](https://github.com/wagnerra23/oimpresso.com/pull/5502) — 1 linha à mão, 23 `SUPERFICIE.md` regenerados pelo dono.

### `P-2` — a âncora de design está contaminada ✅ **confirmado, e pior**

A âncora da `Jana/Index`, resolvida por `ancora.mjs`, é **`chat-jana.jsx`** (não o `jana-merge.jsx` que a rev2 cita). Ela tem **8 ocorrências** de Frota/caçambas: *"Frota utilização 33%"* · *"91 caçambas avulsas · PARADAS"* · *"8 caçambas paradas há >7d"* · *"Caçambas paradas"*.

**"Caçamba" tem lápide própria** no §5 (2026-06-09), [W] textual: *"pode apagar aluguel de caçamba e fundamentar para não voltar mais, eu não uso é alucinação"* — e ali está registrado que "Caçambas" é **nome comercial do cliente Martinho**, nunca conceito de domínio. Somado a Frota, morta por [W] em 2026-08-07.

⛔ **Não consertado, de propósito.** `prototipo-ui/cowork/` é **espelho** do Cowork vivo, e o cabeçalho do próprio [`cowork-mirror-freshness.mjs`](../../../scripts/governance/cowork-mirror-freshness.mjs) declara: *"o espelho não apodrece SOZINHO — ninguém o edita à toa"*. Editá-lo à mão criaria exatamente o drift que a ferramenta existe pra medir. O conserto pertence à **fonte**, e escrita lá é gated ([ADR 0315](../0315-design-sync-claude-design-vs-cowork-charter.md)) — o projeto vivo do Cowork nem aparece na lista gravável do `DesignSync`. **Decisão [W].**

### §2 do pedido — a cadeia de alcance ✅ **confirmada**

`Index.tsx:176` → `href={\`/ia/metas/${meta.id}\`}` é o **único link vivo** pro Blade. E o `Index.charter.md` **delega de propósito**: §Goals *"click em meta → drilldown `/copiloto/metas/{id}`"*, §Anti-goals *"⛔ edição inline (vai em `/copiloto/metas/{id}/edit`)"*. Não é resíduo esquecido — é continuação declarada do fluxo, servida no shell errado.

## 4. Novo e FALSO

### `A-3` — *"`migracao:report` está morto"* ❌ **refutado**

A rev2 diz que o parser *"só conhece `--write`/`--check`/`--all` → `alvos` vazio → `exit(2)`"*. Medido: [`module-surface.mjs:43`](../../../scripts/governance/module-surface.mjs) tem `const MIGRACAO = args.includes('--migracao')`, e **rodar devolve `rc=0` com 50 linhas**.

### `P-1` — *"protótipo cita serviços inexistentes, nunca aplicado ao build"* ❌ **refutado**

Os serviços **são** fictícios (zero em PHP — varrido). Mas as únicas menções no repo são **comentários que já documentam isso**: [`JanaDrillDrawer.tsx:6`](../../../resources/js/Pages/Jana/_components/JanaDrillDrawer.tsx) — *"o protótipo lista fontes FICTÍCIAS … Medido em 2026-08-07: nenhuma dessas classes"* — e `Index.charter.md:84`. **A correção foi aplicada em 2026-08-07**, ao código e ao charter.

E o arquivo citado como "build F1 de referência", `prototipo-ui/cowork/jana-merge.jsx`, **não existe em nenhum dos dois donos** do inventário de design: nem no git, nem no projeto `DesignSync` (listei os dois).

> ## ⚠️ ERRATA — 2026-08-11: o parágrafo acima é FALSO, e custou caro
>
> **O `jana-merge.jsx` existia.** Vivia no projeto Cowork `019dcfd3-6ef2-7ee6-8512-b1b0e5544e58`
> ("Oimpresso ERP Conunicação Visual.") e hoje está versionado em
> [`prototipo-ui/cowork/jana-merge.jsx`](../../../prototipo-ui/cowork/jana-merge.jsx) (943 ln, `SYNC` com o vivo).
>
> **Por que o "listei os dois" não valeu:** o `DesignSync{list_projects}` enumera **só projetos
> do tipo design-system**. O protótipo do ERP vive num projeto **REGULAR**
> (`get_project` → `type: PROJECT_TYPE_PROJECT`) e **não aparece** naquela lista. A varredura
> foi feita — e não cobria o universo.
>
> **O agravante:** a proposal de **2026-08-07**, também mergeada, já dizia o contrário e
> antecipava esta exata confusão: *"ele NÃO ESTÁ NO GIT e ESTÁ NO DESIGNSYNC — as duas coisas
> são verdade e não conflitam."* Um `git grep jana-merge` devolve **21 sites** no repo (charter,
> 2 `.tsx` de produção, workflow, `gates-registry`, RUNBOOK, testes). O oráculo custava um comando.
>
> **O que esta frase causou:** a lápide §5 de 2026-08-10 foi construída sobre ela e proibiu, por
> tabela, o protótipo CERTO (regra `biz=NNN`, revogada por emenda em 08-11). Canon negou canon,
> a lápide herdou a negação, e a sessão seguinte herdou a lápide.
>
> **Nenhum gate pega documento contradizendo documento** — por isso esta errata é append, não
> edição: apagar a frase esconderia como o erro se propagou. Ver §5 `proibicoes.md` 2026-08-11
> (3 lápides) e `LICOES_CODE.md` LC-08.

## 5. O achado que responde a decisão §6 #0

A Onda 0 propõe 4 PRs pra construir um "retrato dos 37 módulos" e decidir se a Jana é a prioridade. **O retrato já existe, roda e já ordenou** — `npm run migracao:report`:

| Módulo | Endpoints servindo Blade |
|---|---|
| **Crm** | **47** |
| Essentials | 38 |
| Repair | 28 |
| Superadmin | 25 |
| AssetManagement | 21 |
| Officeimpresso | 16 |
| Manufacturing | 13 |
| Cms | 12 |
| **Jana** | **8** (9º) |

O comando ainda traz uma coluna `decisão`, lida do campo curado `migracao_ui:` do `SCOPE.md` de cada módulo — mais rico que o proposto.

**A decisão #0 tem resposta, e é "não":** o Crm tem ~6× mais dívida de Blade que a Jana.

## 6. Recomendação

1. **`A-1`** — feito (#5502).
2. **Ondas 6-12 na Jana: não abrir** enquanto o retrato disser que a Jana é a 9ª. Isso não é opinião — é o output do comando que a própria Onda 0 pediu.
3. **Onda 0 (PRs 0.2-0.4): não construir.** O que ela quer já existe. `C-10` do próprio pedido manda estender, não recriar.
4. **`P-2`** — decisão [W]: a âncora contaminada se conserta na fonte, não no espelho.
5. **A pergunta que vale mais que todas as ondas** segue sendo a do [plano de teste](2026-08-09-jana-plano-de-teste-de-uso-decisao-w.md): a capacidade da Jana serve? Sem isso, migrar tela é escolher a forma antes da função.

## 7. Limite honesto

Não conferi as fatias `F`–`L` do FASE2 (o doc não está no repo). Não medi se o espelho Cowork está fresco vs o vivo — só que ele **contém** o conteúdo contaminado. A leitura de `route-hits.json` tem a ressalva do próprio arquivo: 0 hits = *"wired-porém-não-servido"*, e a janela fechou em 2026-07-25.
