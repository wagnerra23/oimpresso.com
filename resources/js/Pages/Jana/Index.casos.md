---
id: resources-js-pages-jana-index-casos
casos: Jana Painel · metas ativas · farol server-side · cockpit deferido · /ia
irmaos: Index.charter.md (lei) · memory/requisitos/Jana/RUNBOOK-index.md (runbook) · prototipo-ui/contrato/jana-painel.contract.json (contrato visual)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-08-17"
---

# Casos de uso — /ia (Painel da Jana)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Derivados do `Index.charter.md` (§Goals/§Anti-hooks) e do `jana-painel.contract.json` — **não**
> do `Index.tsx`. Derivar do código seria tautológico (§5 2026-06-05): passaria verde mesmo com o
> comportamento errado.
>
> ⚠️ **O que a âncora diz sobre FONTE de dado continua não valendo.** O `related_prototype` é
> `prototipo-ui/cowork/jana-merge.jsx` (resolva sempre por `node prototipo-ui/ancora.mjs Jana/Index`,
> nunca no olho). Ele cita 6 `Analise*Service` que **não existem** no repo — a fonte real é
> `app/Services/Sells/SellsCockpitAggregator.php`. Isso é o §Anti-hooks do charter *"não citar no
> drawer fonte/serviço que não existe"*, e segue valendo: nome fictício num drawer chamado "de onde
> vem esse número" é mentira com selo de autoridade.
>
> _**Frota deixou de ser proibida** — [W] removeu o Non-Goal no charter v7 (#5867), textual:
> "frota e caçambas locações remova do charter". O UC que travava isso **saiu deste arquivo** na
> mesma leva. As regras visuais da âncora sempre valeram; agora o domínio dela também não é mais
> exceção. Sem obstáculo de máquina: o `dominio-gate` nunca varreu `Pages/Jana` — seus
> `forbidden_ui_paths` são três, todos de `OficinaAuto`._

## UC-COPI-PAINEL-01 — A rota `/ia` abre o Painel (200 + componente)
Status: 🧪 (`Modules/Jana/Tests/Feature/PainelContratoTest.php` — cita o UC; aguarda run verde na lane MySQL)

Usuário autenticado do business abre `GET /ia`. O grupo `/ia` garante auth; o Controller renderiza
o componente Inertia `Jana/Index`. Âncora: SPEC US-COPI-148 (fusão numa tela única; `/ia/dashboard`
responde 301 pra cá).

**Pronto quando:** GET `/ia` autenticado → 200 e `assertInertia(component 'Jana/Index')`.

## UC-COPI-PAINEL-02 — Contrato de props: 4 eager + 1 deferida
Status: 🧪 (`PainelContratoTest` — `missing(coworkAggregates)` + as 4 eager; aguarda run verde)

A tela recebe `metas`, `sellKpis`, `insightsAggregates` e `janaContext` de forma **eager**, e
`coworkAggregates` de forma **deferida**. A separação não é acidente: o HOTFIX [W] de 2026-05-25
(pós-PR #1547) fixou que `metas` **não** pode ser deferida, porque a Page lê `metas.length` direto.

Âncora: charter §Goals + o comentário canon no `IndexController::index()`.

**Pronto quando:** as 4 props eager chegam no first render e `coworkAggregates` **não** está entre elas.

## UC-COPI-PAINEL-03 — Escopo `business_id`, incluindo o `orWhereNull` intencional (Tier 0)
Status: 🧪 (`PainelContratoTest` — `?business_id=999` ignorado; aguarda run verde)

As metas listadas são do business da sessão **ou** repo-wide (`business_id IS NULL`). Um business
vizinho **nunca** vê meta alheia. O `orWhereNull` do `buildMetasPayload` é **intencional** — um teste
ingênuo o leria como vazamento, e é por isso que ele é citado aqui explicitamente.

Âncora: [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md). Tenant de
teste é o fictício **98** ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — nunca biz=4.

**Pronto quando:** com meta em `biz=A` e sessão em `biz=B`, o payload de B não contém a meta de A.

## UC-COPI-PAINEL-04 — O farol é do SERVIDOR, e "sem base pra julgar" é `cinza`, nunca vermelho
Status: ✅ (`Modules/Jana/Tests/Feature/FarolServerSideTest.php` — 2 casos citam este UC no título)

O Painel pinta cada meta com um farol. Quem decide a cor é `ApuracaoService::farol()`; a Page só
**consome** o campo que chega no payload. Quando não há base pra julgar — sem período, sem apuração,
período que não começou, ou período de duração zero — a resposta é `cinza`, que é o rótulo de *"não
dá pra dizer"*, e **não** vermelho.

Âncora: charter §Goals *"Farol calculado server-side … frontend só consome"* + §Anti-hooks
*"⛔ Cálculo de farol no frontend"*.

O quarto caso é a divergência consciente do port e está travada de propósito: no JS a duração zero
dividia por zero → `NaN`, o `NaN` falhava os dois `>=` e a meta caía em **vermelho**. Dado incoerente
não é "meta indo mal".

**Pronto quando:** os quatro casos devolvem `cinza`; as fronteiras `-5%` e `-15%` seguem verde/amarelo/
vermelho; e `Index.tsx` **não** contém `function calcularFarol` (só o leitor `farolDaMeta`).

## UC-COPI-PAINEL-05 — Empty state declara ausência (o estado real de 100% dos tenants)
Status: 🧪 (`PainelContratoTest` — copy lida do contrato, não do `.tsx`; aguarda run verde)

Sem metas cadastradas, o Painel diz **"Nenhuma meta cadastrada ainda"** e oferece **"Conversar com
a Jana"** — nunca lista vazia muda nem zeros. Medido em 2026-08-09: é o estado real de **100% dos
tenants**, então não é borda, é o caminho principal.

Âncora: contrato `painel-metas-vazio` (copy literal + estados `vazio|com-metas|aguardando-apuracao`)
+ charter §UX targets (`EmptyState` shared component).

**Pronto quando:** 0 metas → as duas strings aparecem sob `data-contract="painel-metas-vazio"`.

## UC-COPI-PAINEL-06 — Meta sem apuração não vira zero
Status: 🧪 (`PainelContratoTest` — idem; aguarda run verde)

Meta cadastrada e ainda **não apurada** mostra **"Aguardando apuração…"**. Âncora: contrato
`painel-meta-apurando`, cujo `_papel` é literal — *"não pode mostrar zero como se fosse resultado"*.

**Pronto quando:** meta sem `MetaApuracao` → a copy aparece e nenhum valor numérico a substitui.

## UC-COPI-PAINEL-07 — Série curta declara ausência em vez de desenhar zero
Status: 🧪 (`PainelContratoTest` — idem; aguarda run verde)

Meta sem série temporal mostra **"Sem histórico"** em vez de desenhar uma linha no zero. Âncora:
contrato `painel-meta-sem-historico` — *"ausência de dado se declara, não se desenha como zero"*.

**Pronto quando:** meta com 0 apurações → a copy aparece e nenhum gráfico é renderizado.

## UC-COPI-PAINEL-08 — Enquanto o cockpit não chega, a tela NÃO mostra zero
Status: 🧪 (`PainelContratoTest` — 4 asserções + 2 controles negativos; bite provado: remover o skeleton do Faturamento reprova o caso. Aguarda run verde **e** o screenshot F1.5)

`coworkAggregates` é deferida (`IndexController:47`). Até resolver, o Painel deve declarar
**carregando** — não pintar `R$ 0`, delta nulo e sparkline vazia como se fossem resultado.

Âncora: é a MESMA regra dos UC-06/07, escrita no contrato para metas e válida para o cockpit
(*"não pode mostrar zero como se fosse resultado"* · *"ausência se declara, não se desenha como
zero"*), somada ao protótipo, que resolve isso com `JmPainelSkeleton` (`jana-merge.jsx`, âncora de
símbolo) e 6 classes `.jm-sk-*`.

**Pronto quando:** com `coworkAggregates` ausente no first render, o Painel mostra estado de
carregamento e **nenhum** `R$ 0`/`0%` derivado de `?? 0`; e ao chegar a prop, os valores reais aparecem.

## UC-COPI-PAINEL-09 — As 5 âncoras do contrato existem, e a ordem é respeitada
Status: 🧪 (`PainelContratoTest` — 5 âncoras + ordem como subsequência; aguarda run verde)

As 5 âncoras `data-contract` existem no `Index.tsx` e a ordem declarada
`[painel-cta-conversar, painel-metas-header, painel-metas-vazio]` é subsequência da ordem de arquivo.

Âncora: o próprio contrato de tela ([ADR 0286](../../../../memory/decisions/0286-contrato-de-tela.md)).

**Pronto quando:** `npm run contrato:check -- prototipo-ui/contrato/jana-painel.contract.json` sai 0.


## Nota do conserto do UC-COPI-PAINEL-08 (2026-08-17)

`_components/JanaCockpitSkeleton.tsx` (novo, ancorado em `jana-merge.jsx` §`JmPainelSkeleton`) +
`carregandoCockpit = coworkAggregates === undefined` no `JanaCockpit`. Os `?? 0` **ficaram** — são
eles que impedem o `TypeError` e mantêm válida a entrada `Jana/Index` na
`DEFER_GUARD_ONLY_ALLOWLIST`; o que mudou é o **render**.

Escopo medido: só os **2** KPIs que dependem da prop deferida (Faturamento mês · PIX hoje) trocam de
card. `Inadimplência total` e `Ticket médio` vêm de `insightsAggregates` (eager) e **não** podem
sumir — há controle negativo no teste.

De quebra, a série ganhou o terceiro estado que faltava: antes, `sparkline.length === 0` dizia
*"Carregando sparkline…"* — então um business **sem vendas** ficava "carregando" pra sempre, e
carregando-de-verdade era indistinguível de vazio. Agora: carregando → skeleton; vazio real →
**"Sem histórico"**.

**Por que nenhum gate tinha pego.** `InertiaDeferredFrontendGuardTest` tem `Jana/Index` na
`DEFER_GUARD_ONLY_ALLOWLIST` com razão **verdadeira**: o `JanaCockpit` guarda com `?.`/`?? 0`/`?? []`,
então não há `TypeError` nem tela branca. O guard mede **"quebra?"** — e a resposta é não. Ninguém
media **"declara carregando?"**. A allowlist **não deve ser mexida** por causa disto: ela é verdadeira
para o que aquele gate afirma.

---

## Decisões pendentes de [W] que travam o ciclo desta tela

Medido em 2026-08-17 com `node scripts/governance/ciclo-completo.mjs`. Com este PR a tela sai de
**1/6** para **3/6** (`casos` e `teste` fecham); os 3 restantes são todos decisão [W]:

- ⚖️ **`related_prototype`** — o check `pt_declarado` só casa `PT-0X`, e o campo vale
  `jana-merge.jsx`. Mantê-lo reprova `pt_declarado` e `golden_live` **para sempre**; trocar por
  `n/a (herda PT-04 Dashboard)` ganha o check e **perde** a proveniência declarada do drill-down
  (`JmDrillDrawer` · `JM_KPI_DRILL`). É proveniência de tela, não wiring — decisão [W].
  _Medido 2026-08-17: a 3ª saída (`jana-merge.jsx (PT-04 Dashboard)`, que satisfaria os dois) **não
  funciona** — o parêntese entra no path e o `ancora.mjs` deixa de ler o arquivo. Era um falso dilema
  aparente; a medição fechou a porta._
- ⚖️ **Golden PT-04 `draft` → `live`** — aprovação de **screenshot** (gate F1.5, [ADR 0107](../../../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md)).
  Nenhum código resolve, e trava **3 telas**, não só esta.
- ⚖️ **"Dashboard" × "Painel"** — a aba se chama Painel e a rota é `/ia`, mas título, breadcrumb e o
  nome do componente exportado ainda dizem Dashboard.
- ⚖️ **Os dois botões "(em breve)"** — Configurar e Exportar são clicáveis, sem `disabled` e sem
  rota. Somem, viram `disabled` com o motivo, ou entregam? Enquanto não decidido **não entram no
  contrato** — pinar uma promessa é congelá-la.
