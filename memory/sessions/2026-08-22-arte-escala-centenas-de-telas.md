---
id: sessions-2026-08-22-arte-escala-centenas-de-telas
---

# Estado da arte — consistência visual com CENTENAS de telas (o problema de escala)

- **Data:** 2026-08-22
- **Agente:** `estado-da-arte`
- **Eixo:** escala e manutenção no tempo. **Fora de escopo** (outros agentes): ferramenta design→código, visual regression/medição de fidelidade, contrato de dados.
- **Irmão do mesmo dia (NÃO tocado):** [`2026-08-22-arte-fidelidade-prototipo-producao.md`](2026-08-22-arte-fidelidade-prototipo-producao.md) — eixo "1 tela". Este aqui é o eixo "280 telas".
- **Nota de honestidade metodológica:** o clone desta sessão é **raso** (`git rev-parse --is-shallow-repository` = true) → nenhuma data derivada de `git log` é citada. As contagens da Fase 2 vêm de `git ls-files`/`git grep` com pathspec `:(glob)` **na branch de trabalho**, com o comando ao lado de cada número (§5 2026-07-28). **Não rodei as portas vivas** (`screen-coverage:report`, `casos:report`) — restrição de execução deste agente. Onde cito o número de uma porta, ele vem do **baseline persistido** ou do **comentário datado do próprio script**, e está rotulado como tal.

---

## 1 · PESQUISA — quem tem centenas de telas e como resolve

| Player | Como não deriva (mecanismo concreto) | Evidência + data | Grau |
|---|---|---|---|
| **SAP Fiori Elements** (S/4HANA) | O app **não é desenhado**: metadata OData/CDS + anotações → o framework **renderiza** um de ~5-6 *floorplans* (List Report, Object Page, Overview Page, Analytical List Page, Worklist). A cauda usa *Flexible Programming Model* / *building blocks*. | SAP: *"SAP uses Fiori elements to create approximately **80%** of the apps for S/4HANA Cloud"* e *"SAP needed Fiori elements **to ensure consistency among apps as it scaled from 10s to 100s to the 1000s** that exist today"* — [SAP Community · Fiori Elements FAQ](https://pages.community.sap.com/topics/fiori-elements/faq) (tópico vivo, revisão FAQ 2026) | **vendor documenta** (80% é auto-reporte, não auditoria) |
| **Odoo** | **11 tipos de view** (List, Form, Kanban, Calendar, Pivot, Graph, Gantt, Map, Cohort, Activity, Search) declarados em XML (`arch`); o web client OWL renderiza. Um ERP com **80+ apps oficiais** e **40.000+ módulos** de comunidade cabe nesses 11. Fuga = `widget` / componente OWL dentro da view. | [Odoo 19 · docs frontend/views](https://www.odoo.com/documentation/19.0/developer/reference/frontend/javascript_reference.html) + [Odoo apps store](https://apps.odoo.com/apps/modules/browse) | **vendor documenta** + verificável no código-fonte |
| **Microsoft Power Apps / Dynamics 365** | Split explícito e nomeado: **model-driven** = UI **gerada** de metadata do Dataverse (forms/views/dashboards configurados, não desenhados) vs **canvas** = pixel livre. Escape: *custom pages* / PCF / canvas embutido no form. | [MS Learn · model-driven app overview](https://learn.microsoft.com/en-us/power-apps/maker/model-driven-apps/model-driven-app-overview) | **vendor documenta**; não achei % público de model-driven vs canvas |
| **Salesforce** | Record page = **template de página** (Lightning App Builder) + **Dynamic Forms**: campos/seções vêm de metadata com regras de visibilidade, substituindo N page layouts por 1 página condicional. Admin configura, ninguém codifica a tela. | [Trailhead · Dynamic Forms](https://trailhead.salesforce.com/content/learn/modules/lightning_app_builder/get-started-with-dynamic-forms-lab); Winter '23 ampliou objetos suportados | **vendor documenta** |
| **ServiceNow** | UI Builder + Next Experience: quase todo produto novo (CSM/ITSM/HR/SPM/FSM Workspace) é montado declarativamente sobre ~100 componentes prontos, não codificado. | [ServiceNow · UI Builder](https://www.servicenow.com/products/ui-builder.html) | **vendor documenta** |
| **TOTVS (BR)** | **PO UI** (open source, Angular, 70+ componentes) tem *page templates* explícitos — `po-page-list`, `po-page-default`, `po-page-edit`, `po-page-detail` — **e** os `po-page-dynamic-*`, que montam a tela a partir de um **contrato de serviço** (metadata), não de JSX escrito à mão. | [po-ui.io](https://po-ui.io/) + [TOTVS Developers](https://medium.com/totvsdevelopers/quer-acelerar-o-desenvolvimento-de-aplica%C3%A7%C3%B5es-angular-conhe%C3%A7a-o-po-ui-d94f139db03c) | **vendor documenta** |

**Bling / Omie / Conta Azul:** *não achei evidência pública de engenharia* — sem design system publicado, sem post técnico com número. Não inventei paridade.

**O padrão que emerge, e é unânime:** ninguém que passou de ~100 telas mantém consistência **revisando telas**. Todos mudaram a **unidade de trabalho** de "tela" para "declaração + renderizador". A consistência deixa de ser resultado de disciplina e passa a ser **propriedade estrutural**: você não pode desenhar errado porque não está desenhando.

### 1b · A tese do "padrão de tela" é convergente — com um número

| Produto | Nº de templates/arquétipos | Cobertura declarada |
|---|---|---|
| SAP Fiori Elements | ~5-6 floorplans | **~80%** dos apps S/4HANA Cloud |
| Odoo | **11** view types | 80+ apps + 40k módulos comunitários |
| oimpresso (UI-0013) | **5-7 PTs** (6 existem: PT-01,02,03,04,05,07) | medido abaixo |

A camada "Padrão de Tela" da Constituição UI v2 **é** o desenho que o mercado convergiu. O acerto conceitual não está em discussão. A diferença é **onde o padrão morde** (§2).

### 1c · A cauda que não cabe — como os melhores tratam

Todos têm cauda e **todos a nomeiam**, em vez de fingir que não existe:
- SAP: os ~20% viram *freestyle SAPUI5*; e há o meio-termo (building blocks) pra não cair no zero.
- Odoo: `widget`/componente OWL **dentro** da view — a fuga é local, a moldura permanece.
- Salesforce/ServiceNow/Power Apps: *custom page* / componente próprio embutido no template.

O princípio comum: **a fuga é um slot, não um bypass**. Você foge do conteúdo de uma região, não do frame. Isso é o que impede a cauda de virar 100% em 3 anos.

### 1d · Metadata-driven: o contra-argumento honesto

Não achei postmortem público de time que tenha abandonado geração de telas por arrependimento — o que achei foi a formulação técnica do limite, e ela é útil: *"the escape hatch is not a flaw in the methodology; it is the methodology's load-bearing flexibility"* ([arXiv 2606.07828](https://arxiv.org/abs/2606.07828), 2026 — interpretador compartilhado ~12.5k linhas + camada de fuga por plataforma, cujo tamanho mede quanto o genérico não expressa).

Traduzindo pra régua operacional: **o tamanho da camada de fuga é a métrica de saúde do modelo declarativo.** Se ela cresce, o modelo está errado; se some, o modelo está apertado demais. Não é "geração boa vs ruim" — é medir a fuga.

### 1e · Deriva no tempo e adoção — onde a evidência é fraca

Aqui preciso ser direto: **o mercado publica método, quase nunca número**.

- **Preply** (2025, Into Design Systems): *Visual Coverage* — algoritmo no browser que conta **pixels renderizados** por componente do DS, ponderado por importância; **>300.000 medições/dia**. O número publicado é de *instrumentação*, não de cobertura. ([Into Design Systems 2025](https://www.intodesignsystems.com/agenda/tackling-preply-s-design-system-impact-using-visual-coverage))
- **Mews**, **Productboard** (ESLint/stylelint → Looker, adoção + componentes deprecados + aderência tipográfica): metodologia pública, **percentuais não**.
- **Zeroheight** (survey, 294 respondentes): "adoção" é a métrica nº1 declarada. É survey, não medição de produto.
- **Shopify Polaris / Atlassian**: codemod é o mecanismo canônico de deprecação (`@shopify/polaris-migrator`, `@atlaskit/codemod-cli`) — *"the bulk of migrations are automated"*. **Nenhum % público** de quanto foi migrado.
- **Atlassian**: "strong adoption and instrumentation in place" — frase de marketing, sem número.

**Conclusão da dimensão 4:** o estado-da-arte de conter deriva é (a) medir adoção **do código em produção**, não do Figma; (b) **codemod** como caminho default de deprecação; (c) **catraca/lint** que impede piorar sem exigir limpar o legado. Quem publica número publica o **instrumento**, não o **placar**.

### 1f · Alguém desistiu da fidelidade tela-a-tela?

**Não achei relato de time nomeado com número dizendo "tentamos e desistimos".** O que existe é o deslocamento estrutural, e ele é a resposta indireta: SAP não desistiu de fidelidade — SAP **removeu a pergunta**, ao passar de 10s→1000s de apps gerando 80% deles. Quando a tela é renderizada de metadata, "a tela está fiel ao protótipo?" deixa de ser uma pergunta por tela e vira uma pergunta por **floorplan** (5-6, não 1000). Essa é a única redução de custo comprovada em escala que a pesquisa devolveu.

---

## 2 · COMPARA — o que o oimpresso tem hoje

### 2.1 Os números (medidos nesta sessão, comando ao lado)

| Medida | Valor | Como medi |
|---|---|---|
| `.tsx` em `Pages/**` | **385** | `git ls-files ':(glob)resources/js/Pages/**/*.tsx'` |
| desses, `_components/**` (não são telas) | **175** | idem `+/_components/` |
| **telas-arquivo** (385 − 175) | **~210** | subtração |
| charters ao lado de `.tsx` (Pages) | **171** | `git ls-files ':(glob)resources/js/Pages/**/*.charter.md'` |
| charters na 2ª raiz (`Modules/**`) | **45** | idem |
| `*.casos.md` | **160** | `git ls-files ':(glob)**/*.casos.md'` |
| `*-visual-comparison.md` | **80** | `git ls-files` |
| `RUNBOOK-*.md` em requisitos | **172** | `git ls-files` |

**Sobre os denominadores divergentes (144 · 203 · 235 · 280):** eles não se contradizem — **medem coisas diferentes** e alguns são **catracas** (pisos que só sobem quando regravados), não retratos. Baselines persistidos hoje: `design-coverage-baseline.json` = `{declared: 93, totalCharters: 147}`; `screen-coverage-baseline.json` = `{total: 203, charter: 203, e2e: 14, a11y: 3, scorecard: 191}`. O 280 citado em `proibicoes.md` (datado 2026-07-27) é do `casos:report`, outro universo. **Não reconciliei porque não podia rodar as portas** — e reconciliar lendo arquivo seria justamente o LC-08.

### 2.2 A medida que responde a pergunta do [W] — quanto do produto é "template" vs "desenhado"

Contei a distribuição de `related_prototype:` nos 173 charters que têm o campo:

```
git grep -h -E "^related_prototype:" -- ':(glob)resources/js/Pages/**/*.charter.md' ':(glob)Modules/**/*.charter.md'
```

| Classe | Nº | % | Leitura |
|---|---|---|---|
| **declara `PT-0X`** (herda Padrão de Tela) | **91** | 52,6% | cabe no template |
| **aponta protótipo bespoke** (`.html`/`.jsx`) | **54** | 31,2% | **tela desenhada individualmente** |
| **`n/a` explícito — "não segue um dos 5 PT"** | **28** | 16,2% | cauda **declarada e justificada** |

Isso é o dado central deste documento. Comparando com o benchmark: **SAP roda ~80% no template; o oimpresso roda ~53%.** E — mais importante que o percentual — **31% do produto tem uma tela desenhada uma a uma**, que é exatamente o modo de trabalho que nenhum player de escala comparável sustenta.

Crédito honesto: os 28 `n/a` estão escritos com a razão (*"grafo bespoke ReactFlow"*, *"landing pública de marketing"*, *"feed cronológico agrupado por dia"*). Isso é **melhor** que o padrão de mercado, onde a cauda é silêncio. A cauda aqui é auditável.

### 2.3 Dimensão a dimensão

| Dimensão | Estado-da-arte (§1) | oimpresso hoje | Distância |
|---|---|---|---|
| **Existe camada "padrão de tela" formal** | Fiori floorplans · Odoo 11 views · PO UI page templates | **UI-0013**, 4 camadas, herança explícita, 6 PTs documentados em `padroes-tela/` | **nenhuma** — conceito idêntico, e formalizado antes de vários |
| **Declaração de PT é falsificável** | Não precisa ser: o framework **renderiza**, então declarar e ser são a mesma coisa | `pt-conformance.mjs` verifica assinatura estrutural do `.tsx` contra o PT declarado (PT-02 exige `<form>`, PT-05 exige dnd-kit) | **curta, e é um recorte próprio** — quem gera não precisa disso; quem escreve à mão, precisa, e quase ninguém tem |
| **A tela NASCE do padrão** | SAP/Odoo/Salesforce/ServiceNow: **sempre** | `criar-tela.mjs <Mod/Tela> <PT-0X>` carimba `.tsx` + charter + casos + stub e2e, e passa no `pt-conformance` **por construção** — mas só pra tela **nova**; as ~210 existentes nasceram à mão | **média** — o gerador existe e está certo; a base não veio dele |
| **% do produto no template** | ~80% (SAP, auto-reporte) | **52,6%** (91/173 charters) | **média** |
| **Cauda nomeada e justificada** | SAP: "freestyle"; Odoo: widget | 28 `n/a` **com razão escrita** | **nenhuma / à frente** |
| **A fuga é slot, não bypass** | Fuga é local (widget dentro da view) | 54 protótipos bespoke = **tela inteira** fora do frame | **longa** — é a diferença estrutural mais cara |
| **Adoção medida do código em produção** | Preply (pixels) · Mews/Productboard (lint→dashboard); % raramente público | `design-coverage` (93/147 declared, catraca), `screen-coverage` (203 telas), `component-registry-check --roles`, `screen-grades-ratchet` (**required**) | **nenhuma** — instrumentação comparável ou melhor; e aqui os números são *auditáveis*, não slide |
| **Catraca que impede piorar sem exigir limpar legado** | Prática padrão (ratchet/lint) | `layout-primitives-guard`: **386 arquivos / 2.374 violações** de flex-solto congeladas, só não pode subir. Idem stylelint/eslint ratchet (**required**) | **nenhuma** |
| **Codemod como caminho de deprecação** | Polaris/Atlaskit: *"bulk of migrations are automated"* | **não achei** codemod de migração de componente/DS no repo (achei geradores e verificadores; `doc-auto-relink`/`ghost-fix` são de doc, não de UI) | **média** |
| **Enforcement do PT** | Estrutural (não dá pra violar) | `pt-conformance` = **advisory + path-filtered** (`name: DS pt-conformance (advisory)`); **não** está entre os 45 required | **média** — a lei existe, o guarda não prende |

### 2.4 Onde o oimpresso bate ou supera o mercado (com data)

1. **Declaração de PT falsificável por gate** (`pt-conformance`, com `lib/pt-signatures.mjs` como fonte única compartilhada entre gerador e verificador, de forma que scaffold passa por construção). Nenhum dos players pesquisados precisa disso — mas nenhum framework de admin de mercado (react-admin, Refine, Filament) oferece isso pra código escrito à mão. **Não é claim de exclusividade** (não varri o universo); é: *na pesquisa de 2026-08-22 não encontrei equivalente publicado*.
2. **Cauda declarada com razão** — os 28 `n/a` justificados. O mercado documenta que a cauda existe; não documenta *quais* telas e *por quê*.
3. **Catracas com dívida congelada e visível** (2.374 violações de layout nomeadas por arquivo). Mews/Productboard descrevem a prática; o número aqui está no repo.

---

## 3 · AVALIA — o que falta, rankeado

| # | Gap | Impacto | Esforço (IA-pair, ADR 0106) | Pré-req bloqueante? |
|---|---|---|---|---|
| 1 | **Publicar o placar de conformidade PT** (91/173 = 52,6%) como número derivado e versionado, e virar catraca de `declara_PT` (só sobe) | **alto** — hoje o dado existe mas ninguém o vê; sem placar não há gestão da deriva | ~2-3h (estender `design-coverage` com o eixo `pt_declared`, não criar medidor novo — §5 2026-07-09) | **não** |
| 2 | **`criar-tela.mjs` obrigatório pra tela nova** (hoje é opcional; a base de 210 nasceu à mão) | **alto** — trava a entrada; sem isso o 52,6% cai com o tempo | ~1-2h (hook/gate na criação de `.tsx` novo em `Pages/`, forward-only) | **não** — o gerador já existe e é testado |
| 3 | **Transformar os 54 bespoke em "slot dentro de PT"** — a fuga vira região, não tela | **alto** | **alto**: ~15-25h, e é onda, não PR. Precisa triagem: quantos dos 54 são genuinamente fora-do-frame vs herança histórica | **sim** — depende de #1 pra saber quais, e de decisão [W] sobre o que é legítimo |
| 4 | **Promover `pt-conformance` a required** | médio | ~1h de flip + pré-reqs (tirar `paths:`, tirar "(advisory)" do nome — incidente 2026-08-08 · LC-10) | **sim** — ADR 0336 exige mordida provada; e hoje é path-filtered, required+paths = deadlock |
| 5 | **Codemod de deprecação de componente** (o caminho que Polaris/Atlaskit usam) | médio | ~4-6h por família | **sim** — só faz sentido depois de `component-registry --roles` apontar papel-duplicado com N≥2 |
| 6 | **Reconciliar os denominadores** (144/203/235/280) num só lugar derivado | baixo-médio | ~1h (rodar as 3 portas e publicar a decomposição; **não** criar um 4º medidor) | **não** |
| 7 | **PT-06 formalizado** (hoje 1 tela só do arquétipo, por isso `claimedPT` usa `[1-57]`) | baixo | ~1h quando surgir a 2ª | **sim** — regra própria: ≥2 telas |

### Recomendação

**Comece pelo #1 — publicar o placar de conformidade PT.** Alto impacto, ~2-3h, sem pré-req bloqueante, e é o único item que **muda a pergunta**: hoje o [W] pergunta "dá pra reproduzir fiel o protótipo?" tela a tela; com o placar ele passa a perguntar "quantos por cento do produto herda padrão, e essa curva sobe ou desce?" — que é a pergunta que os 6 players de referência de fato administram.

**Próxima ação hoje:** rodar `node scripts/qa/design-coverage.mjs` e `node scripts/governance/pt-conformance.mjs --json` (as portas vivas que eu não podia executar), conferir se o `91 / 173` que derivei por `git grep` bate com o que os medidores dizem, e — batendo — abrir 1 PR que adiciona o eixo `pt_declared` ao `design-coverage-baseline.json` como catraca. Não criar medidor novo: estender o dono.

---

## O que isso responde à pergunta do [W]

- **Em 1 tela, fidelidade é um problema de execução. Em 280, é um problema de arquitetura** — e nenhum produto de escala comparável resolve revisando telas.
- **Os 6 players pesquisados fizeram a mesma coisa:** mudaram a unidade de "tela desenhada" para "declaração renderizada". SAP diz textualmente que criou o Fiori Elements *pra manter consistência ao escalar de 10s→100s→1000s de apps*, e gera ~80% deles assim.
- **A tese "5-7 Padrões de Tela" do UI-0013 está certa e é convergente** — Fiori tem ~5-6 floorplans, Odoo tem 11 view types pra um ERP inteiro. O conceito não precisa mudar.
- **O que difere é onde o padrão morde:** lá a tela **nasce** do padrão (não dá pra violar); aqui ela é escrita à mão e o padrão é **verificado depois**, por um gate **advisory**.
- **O placar de hoje: 52,6% das telas com charter declaram um PT; 31,2% apontam protótipo bespoke; 16,2% declaram cauda justificada.** Contra ~80% de cobertura de template no benchmark. O 31,2% é a conta que não escala.
- **Onde o oimpresso está à frente:** a declaração de PT é **falsificável** por gate (quem gera não precisa disso, quem escreve à mão quase nunca tem) e a cauda é **nomeada com razão**, não silêncio. Ambos verificáveis no repo.
- **Sobre deriva no tempo, a evidência de mercado é fraca em números** — todos publicam o instrumento (Preply: 300k medições/dia por pixel; Polaris/Atlaskit: codemod; ratchet+lint) e quase ninguém publica o placar. O oimpresso já tem instrumentos equivalentes (`design-coverage`, `screen-grades-ratchet` required, layout-ratchet com 2.374 violações congeladas) — falta **publicar o placar do eixo que importa**, que é o PT.
- **Resposta curta ao [W]:** *dá pra reproduzir fiel em 280 telas — mas não desenhando 280.* Dá fazendo com que a tela nasça do padrão e a fuga seja um slot medido, não uma tela inteira. Comece publicando quanto do produto já herda padrão; sem esse número, qualquer decisão sobre fidelidade é opinião.
