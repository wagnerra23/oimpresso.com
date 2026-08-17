---
id: resources-js-pages-jana-index-casos
casos: Painel da Jana · metas + cockpit · /ia
irmaos: Index.charter.md (lei) · prototipo-ui/contrato/jana-painel.contract.json (copy/ordem)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-08-17"
---

# Casos de uso — `/ia` (Painel da Jana)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> **Ordem de fonte** (how-trabalhar §Pedido de tela): derivados de `Index.charter.md` v7
> (Mission/Goals/Non-Goals/Anti-hooks/UX targets) + `prototipo-ui/contrato/jana-painel.contract.json`
> (copy literal e ordem) + `memory/requisitos/Jana/SPEC.md` (US-COPI-010/011/012/146/148) +
> [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) (Tier 0).
> **Nunca do `.tsx`** — caso derivado do código é tautológico (§5 2026-06-05).
>
> Persona-alvo: dono/gestor (Wagner, Larissa @ ROTA LIVRE, monitor 1280px).
> **Honestidade de escopo:** o estado REAL de 100% dos tenants hoje é **0 metas cadastradas**
> (medido 2026-08-09) — por isso o empty state é UC de primeira classe, não borda.

## UC-PAINEL-01 — Rota `/ia` abre o Painel (200 + componente)
Status: 🧪 (`Modules/Jana/Tests/Feature/PainelContratoTest.php` — Pest escrito, cita o UC; aguarda run verde na lane MySQL)
Usuário autenticado do business abre `GET /ia`. O grupo `/ia` garante auth; o Controller
renderiza o componente Inertia `Jana/Index`. Âncora: SPEC US-COPI-148 (fusão numa tela única,
rota viva `GET /ia`, `jana.index`; `/ia/dashboard` → 301).
**Pronto quando:** GET `/ia` autenticado → 200 e `assertInertia(component 'Jana/Index')`.

## UC-PAINEL-02 — Contrato de props do Painel
Status: 🧪 (`PainelContratoTest` — `missing(coworkAggregates)` + as 4 eager; aguarda run verde)
A tela recebe `metas`, `sellKpis`, `insightsAggregates`, `janaContext` de forma **eager**, e
`coworkAggregates` de forma **deferida**. A separação não é acidente: o HOTFIX [W] de 2026-05-25
(pós-PR #1547) fixou que `metas` NÃO pode ser deferida porque a Page lê `metas.length` direto.
Âncora: `Index.charter.md` §Goals + o comentário canon no `IndexController::index()`.
**Pronto quando:** as 4 props eager chegam no first render e `coworkAggregates` NÃO está entre elas.

## UC-PAINEL-03 — Escopo `business_id` (Tier 0)
Status: 🧪 (`PainelContratoTest` — `?business_id=999` ignorado; aguarda run verde)
As metas listadas são do business da sessão (ou repo-wide `business_id IS NULL`). Um business
vizinho **nunca** vê meta alheia. Âncora: [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
+ `buildMetasPayload()` (`where business_id = :biz OR business_id IS NULL`).
**Pronto quando:** com meta em `biz=A` e sessão em `biz=B`, o payload de B não contém a meta de A.

## UC-PAINEL-04 — Empty state declara ausência (o estado real de hoje)
Status: 🧪 (`PainelContratoTest` — copy lida do contrato, não do `.tsx`; aguarda run verde)
Sem metas cadastradas, o Painel diz **"Nenhuma meta cadastrada ainda"** e oferece
**"Conversar com a Jana"** — nunca uma lista vazia muda nem zeros. Âncora: contrato
`painel-metas-vazio` (copy literal + estados `vazio|com-metas|aguardando-apuracao`) +
charter §UX targets (`EmptyState` shared component).
**Pronto quando:** 0 metas → a copy das duas strings aparece, na âncora `data-contract="painel-metas-vazio"`.

## UC-PAINEL-05 — Meta sem apuração não vira zero
Status: 🧪 (`PainelContratoTest` — idem; aguarda run verde)
Meta cadastrada e ainda **não apurada** mostra **"Aguardando apuração…"**. Âncora: contrato
`painel-meta-apurando`, cujo `_papel` é literal — *"não pode mostrar zero como se fosse resultado"*.
**Pronto quando:** meta sem `MetaApuracao` → a copy aparece e nenhum valor numérico a substitui.

## UC-PAINEL-06 — Sparkline sem série declara ausência
Status: 🧪 (`PainelContratoTest` — idem; aguarda run verde)
Meta sem série temporal mostra **"Sem histórico"** em vez de desenhar uma linha no zero.
Âncora: contrato `painel-meta-sem-historico` — *"ausência de dado se declara, não se desenha como zero"*.
**Pronto quando:** meta com 0 apurações → a copy aparece e nenhum gráfico é renderizado.

## UC-PAINEL-07 — Farol é do servidor, não do frontend
Status: 🧪 (`PainelContratoTest` cita este UC — payload traz `farol` + o `Index.tsx` não recalcula; as fronteiras −5%/−15% já são cobertas por `Modules/Jana/Tests/Feature/FarolServerSideTest.php`, que **não** cita o UC e por isso não conta pro G-2 — os dois se somam, não se substituem)
O farol verde/amarelo/vermelho/cinza vem de `ApuracaoService::farol($meta)`; a Page só consome.
`cinza` cobre os quatro casos de SEM BASE pra julgar e **não** degrada pra vermelho.
Âncora: charter §Goals + §Anti-hooks (*"⛔ Cálculo de farol no frontend"*).
**Pronto quando:** as fronteiras −5% e −15% caem no lado certo e `Index.tsx` não contém a regra.

## UC-PAINEL-08 — Enquanto o cockpit não chega, a tela NÃO mostra zero
Status: 🧪 (`PainelContratoTest` — 4 asserções + 2 controles negativos; bite provado: remover o skeleton do Faturamento reprova o caso. Aguarda run verde na lane MySQL **e** o screenshot F1.5)
`coworkAggregates` é deferida (`IndexController:47`). Até resolver, o Painel deve declarar
**carregando** — não pintar `R$ 0`, `null` e sparkline vazia como se fossem resultado.
Âncora: é a MESMA regra dos UC-05/06, escrita no contrato para metas e válida para o cockpit
(*"não pode mostrar zero como se fosse resultado"* · *"ausência se declara, não se desenha como zero"*),
somada ao protótipo, que já resolve isso com `JmPainelSkeleton` (`jana-merge.jsx:683`, usado em `:839 :851 :865`)
e 6 classes `.jm-sk-*` em `jana-merge.css`.
**Pronto quando:** com `coworkAggregates` ausente no first render, o Painel mostra estado de
carregamento (skeleton) e **nenhum** `R$ 0`/`0%` derivado de `?? 0`; e ao chegar a prop, os valores reais aparecem.

## UC-PAINEL-09 — Ordem das seções do contrato
Status: 🧪 (`PainelContratoTest` — 5 âncoras + ordem como subsequência; aguarda run verde)
As 5 âncoras `data-contract` existem no `Index.tsx` e a ordem declarada
`[painel-cta-conversar, painel-metas-header, painel-metas-vazio]` é subsequência da ordem de arquivo.
Âncora: o próprio contrato de tela ([ADR 0286](../../../../memory/decisions/0286-contrato-de-tela.md)).
**Pronto quando:** `npm run contrato:check -- prototipo-ui/contrato/jana-painel.contract.json` sai 0.

## UC-PAINEL-10 — A análise "Frota" não existe nesta tela
Status: 🧪 (`PainelContratoTest` — varre `Pages/Jana/**/*.tsx` pulando comentário; aguarda run verde)
O Painel do **núcleo** (ROTA LIVRE, vestuário) não constrói a análise Frota do protótipo.
Âncora: charter §Non-Goals + [ADR 0265](../../../../memory/decisions/0265-oficina-reparo-erradica-locacao.md).
⚠️ O Non-Goal governa **a tela**, não o retrato da âncora: o `jana-merge.jsx` desenha o cockpit do
Martinho (`biz=164`), onde frota É o negócio, e **podar a fonte de design é proibido** (decisão [W]
2026-08-13 + §5 do mesmo dia).
**Pronto quando:** `git grep -niE "frota|ca[çc]amba|locad" -- resources/js/Pages/Jana Modules/Jana` não
retorna código de UI.

---

## UC-PAINEL-08 — como foi consertado (2026-08-17)

`_components/JanaCockpitSkeleton.tsx` (novo, ancorado em `jana-merge.jsx` §`JmPainelSkeleton`)
+ `carregandoCockpit = coworkAggregates === undefined` no `JanaCockpit`. Os `?? 0` **ficaram** —
são eles que impedem o `TypeError` e mantêm válida a entrada na `DEFER_GUARD_ONLY_ALLOWLIST`;
o que mudou é o **render**. Escopo medido: só os **2** KPIs que dependem da prop deferida
(Faturamento mês · PIX hoje) trocam de card. `Inadimplência total` e `Ticket médio` vêm de
`insightsAggregates` (eager) e **não** podem sumir — há controle negativo pra isso no teste.

De quebra, a série ganhou o terceiro estado que faltava: antes, `sparkline.length === 0` dizia
*"Carregando sparkline…"* — então um business **sem vendas** ficava "carregando" pra sempre, e
um carregando de verdade era indistinguível de vazio. Agora: carregando → skeleton; vazio real →
**"Sem histórico"** (a mesma copy que o contrato já usa em `painel-meta-sem-historico`).

**Por que nenhum gate tinha pego.** `InertiaDeferredFrontendGuardTest` (repo-wide, roda no
`ui-architecture-gate`) tem `Jana/Index` na `DEFER_GUARD_ONLY_ALLOWLIST`, com razão **verdadeira**:
o `_components/JanaCockpit.tsx` guarda com `?.`/`?? 0`/`?? []` (`:205 :206 :224`), então não há
`TypeError` nem tela branca. O guard mede **"quebra?"** — e a resposta é não. Ninguém mede
**"declara carregando?"**. São dois contratos diferentes, e o segundo não tinha dono até este UC.

**A allowlist não deve ser mexida por causa disto** — ela é verdadeira para o que aquele gate afirma.
O dono do estado-de-carregamento é este UC + o teste que o citar.
