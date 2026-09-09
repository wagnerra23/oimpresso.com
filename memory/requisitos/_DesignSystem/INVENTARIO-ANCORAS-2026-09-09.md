---
id: requisitos-design-system-inventario-ancoras-2026-09-09
slug: inventario-ancoras-shell-2026-09-09
title: "INVENTÁRIO — quais telas sem âncora declarada têm, de fato, protótipo no shell"
type: inventario
module: _DesignSystem
status: ativo
owner: wagner
date: "2026-09-09"
medido_em: "origin/main @ 043106f9c9"
related_adrs: [0299, 0282, 0374, 0315]
related: [INDEX-DESIGN-MEMORIAS.md, PROTOCOLO-COMPARACAO-RUNTIME.md]
---

# INVENTÁRIO — telas sem âncora declarada × protótipo real no shell

> ⚠️ **Isto é um retrato DATADO (2026-09-09), não um mapa vivo.** Os números foram medidos
> contra `origin/main` em `043106f9c9`. O main anda ~68 commits/dia — reler este arquivo daqui a
> uma semana e tratar as contagens como estado atual é o erro que a
> [lápide §5 2026-09-03](../../proibicoes.md) registra ("lápide que declara um GAP tem prazo de
> validade implícito"). **Para o estado de hoje, rode as portas vivas** listadas em §6.
>
> **O que este documento acrescenta e nenhuma máquina produz:** o veredito por tela veio de
> *abrir o protótipo e ler o que ele desenha*. Casar tela com protótipo por semelhança de nome é
> guard sintático — a família com 7 lápides medidas no §5. Nome é pista; o cabeçalho e o corpo
> do `.jsx` são a prova.

---

## 1 · A pergunta, e por que ela não é trivial

A classificação automática dizia **110 telas sem âncora declarada**. Isso não significa "sem
protótipo" — significa "o charter não aponta o arquivo". O classificador cai em "sem fonte"
**por construção**, não por medição: se o charter é silencioso, ele não tem o que resolver.

A pergunta deste inventário: **dessas telas, quais realmente têm fonte no shell, e quais não têm.**

## 2 · Medição reproduzida

O shell canônico é `prototipo-ui/cowork/oimpresso.com.html`. Refs que ele carrega:

```bash
grep -oE '(src|href)="[^"]+"' prototipo-ui/cowork/oimpresso.com.html \
  | sed 's/^\(src\|href\)="//; s/"$//' | sed 's/?.*$//' | sort -u
```

**281 refs** — 199 `.jsx` (53 deles `-page.jsx`), 68 `.css`, 10 `.js`, 4 de fonte externa.
Todos os 199 `.jsx` existem no disco. O `sed 's/?.*$//'` importa: o shell declara os arquivos
como `data-src="venda-blade.jsx?v=vb2"`, e um padrão que não tire o `?v=` perde o arquivo.

Contra **226 charters de tela**, classificando pelo **campo real do frontmatter** (não pela
convenção de nome):

| bucket | n | o que é |
|---|---:|---|
| A — `related_prototype` resolve no shell | 80 | ancorada |
| B — `related_prototype` não resolve direto | 2 | um é indireção legítima, um é `.html` fora do shell |
| C — `bundle_source` / `visual_source` no shell | 32 | fonte declarada em **outro** campo |
| **E — `n/a` sem fonte alguma** | **107** | **o alvo deste inventário** |
| **F — charter sem declaração nenhuma** | **5** | **o alvo deste inventário** |

**Denominador real: 112**, não 110. A lista de 110 fora montada pela convenção
`modulo.toLowerCase() + '-page.jsx'`, e ela erra em massa (§3).

Reconciliação com a porta oficial (`npm run design:coverage`): ela reporta **5 silenciosas** —
bate exatamente com o bucket F — e **36 "n/a com fonte candidata"**, que é o balde por convenção
de nome, um corte diferente do meu C.

## 3 · Por que a convenção de nome erra em massa

O protótipo nomeia por **domínio, em português**; o charter, por **módulo, em inglês**:

| charter | candidato pela convenção | arquivo que de fato desenha |
|---|---|---|
| `Produto/*` | `produto-page.jsx` ✗ | `produtos-page.jsx` (lista) · `produto-blade.jsx` + `produto-blade-forms.jsx` (o resto) |
| `Sells/Drafts`·`Quotations`·`Subscriptions` | `sells-page.jsx` ✗ | **`venda-blade.jsx`** |
| `Purchase/*` | `purchase-page.jsx` ✗ | `compras-page.jsx` |
| `Essentials/*` | `essentials-page.jsx` ✗ | `essenciais-page.jsx` + `essenciais-extras.jsx` |
| `StockAdjustment`·`StockTransfer` | `stockadjustment-page.jsx` ✗ | `estoque-page.jsx` + `estoque-forms.jsx` |

Os nomes que resolvem as sub-telas **não têm parentesco algum** com o nome do módulo. Fosse por
semelhança, o inventário acertaria o arquivo errado em quase todas.

## 4 · Cinco lugares onde uma tela declara sua fonte — e a máquina lê dois

Este é o achado estrutural. A resolução de âncora (`prototipo-ui/ancora.mjs`) declara no docblock
que a regra dura é `âncora ∈ { related_prototype, -page.jsx do bundle via charter }`. Na prática
existem **cinco** lugares onde a fonte está escrita:

| # | onde | quantos | o `ancora.mjs` lê? |
|---|---|---:|---|
| 1 | `related_prototype:` (frontmatter do charter) | 226 | sim |
| 2 | `bundle_source:` (frontmatter) | 40 | sim, **mas ver o defeito abaixo** |
| 3 | `visual_source:` (frontmatter) | 9 | sim, mesma ressalva |
| 4 | **`blueprint_cowork:`** (dentro de `mwart_pattern_reuse`) | **38** | **não — zero ocorrências no código** |
| 5 | **`canon_reference:`** (nos `*-visual-comparison.md`) | **31 arquivos** | **não — zero ocorrências** |

### 4.1 · Defeito 1 — precedência invertida (15 charters)

[`ancora.mjs:486`](../../../prototipo-ui/ancora.mjs) faz:

```js
const source = fm.related_prototype || doBundle || mockupJsx(fm.component) || null;
```

`"n/a (herda PT-01 Lista…)"` é uma string **truthy**. Quando o charter tem `related_prototype: n/a`
**e** `bundle_source`, o `||` nunca alcança o bundle. O `--list` reporta `isNa: true` e o
`design-coverage`, que consome esse `--list`, herda a cegueira.

A porta **per-tela** (`resolveAncora`, L266-271) prefere o campo estruturado e resolve certo — é
por isso que o passo 2 do chip manda usar a porta per-tela, não a listagem geral. Contraste medido:

```
$ node prototipo-ui/ancora.mjs Produto/Index --staging prototipo-ui/cowork
  âncora ✓: [-page.jsx (bundle · bundle_source)] produtos-page.jsx     ← resolve
$ (o mesmo charter no --list)  →  isNa: true, via: related_prototype   ← esconde
```

**15 charters** afetados: os 7 de `Essentials`/`Produto`/`ComunicacaoVisual`, os 4 de Estoque,
`Manufacturing/Index`, 3 de `Repair` e `Vestuario/Etiquetas/Index`.

O fix de 2026-08-28 registrado no próprio arquivo (L476-482) tratou o caso `related_prototype`
**ausente**; o caso `n/a` **presente** ficou.

### 4.2 · Defeito 2 — a 4ª chave aponta um documento de outro assunto (4 charters)

Os 4 charters de Estoque declaram, em `mwart_pattern_reuse.blueprint_cowork`:

```
prototipo-ui/prototipos/inventario-migracao/visual-source.html
```

Esse arquivo existe, tem 30 KB, e é **um relatório técnico de migração de código**:

```
<title>Inventário · Oimpresso ERP — Migração Blade → React</title>
```

Medido com controle positivo e negativo (`grep -oi … | wc -l`, todos `rc=0`):

| termo | ocorrências |
|---|---:|
| `Blade` | 67 |
| `React` | 36 |
| `Inventário` | 8 |
| **`estoque`** | **0** |
| **`SKU`** · **`saldo`** · **`quantidade`** · **`ajuste`** | **0** |

O casamento foi por **homônimo**: "inventário" de código × "inventário" de estoque. Um
`existsSync` diria "está tudo certo" nos quatro. (`F1.html` e `visual-source.html` são o mesmo
blob, md5 `a5b334ce1e48898ad0f3639e0df78311`.)

Outros 3 dos 38 apontam caminho inexistente (`cowork/cms/cms-page.jsx`, `cowork/connector/connector-page.jsx`
— subpasta que não existe; o arquivo real está na raiz).

### 4.3 · Defeito 3 — heurística por nome dentro da máquina canônica

[`ancora.mjs:276-277`](../../../prototipo-ui/ancora.mjs) tem, para quando não há campo:

```js
cand = stFiles.find((f) => /-page\.jsx$/i.test(f) && basename(f).toLowerCase().startsWith(wanted));
if (cand) via = 'heurística startsWith(dir)';
```

É casamento por nome — o critério que a regra dura proíbe. **Três inventariantes independentes
o pegaram errando**, em famílias diferentes.

**Taxa de erro medida** (bloco Financeiro/Repair/governance, onde foi contada): a heurística
resolveu âncora para 11 telas; abrindo os 11 arquivos, **8 são falsas** — 73%. Os 3 acertos
foram coincidência de nomenclatura. Os erros:

| tela | heurística deu | o que o arquivo realmente desenha |
|---|---|---|
| `Financeiro/Configuracoes/Contador` | `configuracoes-page.jsx` | *"Configuração da empresa (BusinessController) — 16 abas"*; `contador`/`advisor` → `rc=1` |
| `Financeiro/Relatorios/Index` | `relatorios-page.jsx` | o módulo Relatórios do **core**, *"importado dos blades `resources/views/report/*`"* |
| `governance/{Custos,DsRollout,QualidadeIa}` | `governance-page.jsx` | *"Cinco vistas: painel · politicas · auditoria · drift · notas"* — nenhuma delas |
| `Ponto/Welcome` | `ponto-page.jsx` | 13 abas de área dentro da página; o padrão hub foi **substituído** |
| `kb/Graph` | `kb-page.jsx` | tri-pane sem grafo; `reactflow`/`nodes`/`edges` = 0 |
| `Financeiro/AssinaturaAtualizar` | `financeiro-page.jsx` | `FIN_SUB` não tem view de assinatura |

Nas outras famílias, os mesmos falsos: `Produto/{Create,Edit,BulkEdit,Show,StockHistory}` →
`produtos-page.jsx`, e `Cliente/Show` → `clientes-page.jsx`. Em todos, o arquivo apontado existe
e é âncora legítima **de outra tela**.

Verificação independente em `produtos-page.jsx` (controle positivo + negativo):
`ProdListPage` = 3 · `BulkBar` = 4 · **`FormProduto` = 0** · **`stock_history` = 0**. Ele desenha
só `/products/unificado`. Ironia útil: a heurística **não** dispara para `Produto/Unificado/Index`,
que é a única tela que aquele arquivo de fato desenha.

O rótulo `via: 'heurística startsWith(dir)'` está no output — quem lê o veredito sem ler o rótulo
aceita um casamento por nome com aparência de resolução.

### 4.4 · Defeito 4 — colisão de atalho (1 em 226)

`ancora.mjs Nfse/Index` resolve o charter de **`Fiscal/Nfse`**, e devolve `âncora ✓` com selo de
frescor — resposta confiante e errada. Só o caminho `.tsx` completo dá o charter certo.

Medido nos 226 atalhos: **1 colisão**. É real, e é isolada — registrado sem inflar.

### 4.5 · Defeito 5 — âncora que resolve para um `<Placeholder>` (3 charters)

Pior que âncora ausente, porque **passa em qualquer gate de presença**. As 3 telas de
`RecurringBilling/{Planos,Faturas,Configuracoes}/Index` declaram `visual_source:
cobranca-recorrente-page.jsx` — e as tabs correspondentes ali são stubs de 3 elementos:

```js
// cobranca-recorrente-page.jsx:331
// ── views placeholder (honestas — não fingir pronto) ──
{tab === "planos" && <Placeholder title="Planos" desc="… Espelha /recurring-billing/planos do git." />}
```

O protótipo é honesto (`"não fingir pronto"`); quem não é honesto é a cadeia, que conta isso
como fonte declarada.

> **Tratado em 2026-09-09** ([PR #7099](https://github.com/wagnerra23/oimpresso.com/pull/7099)):
> os 3 `visual_source` ganharam a ressalva **por anotação**, não foram removidos — remover
> derrubaria a catraca `design-coverage` (`declared` só sobe) e promover ancoraria a tela nela
> mesma (porte reverso). Trocar por "sem fonte" segue decisão [W]. A ausência de outra fonte foi
> medida pelas 3 pernas (repo · projeto Cowork por ID · ledger do espelho, compare 2026-09-08 =
> `sync`) — o `<Placeholder>` é o estado do design **vivo**, não um retrato velho. Efeito medido
> junto: `detect-handoff.mjs:52` casa `visual_source` ancorado em fim de linha e `map[k]=v`
> sobrescreve, então o arquivo roteava para `Planos/Index` (o stub); passou a rotear para
> `RecurringBilling/Index` (Assinaturas), a tela que ele de fato desenha.

### 4.6 · Defeito 6 — cosmético (37 charters)

Para charter sob `Modules/<X>/Resources/js/Pages/`, o `ancora.mjs` imprime `tela viva: —` mesmo
com o `.tsx` existindo. **A âncora resolve normalmente** — só o campo informativo fica vazio.
Impacto real: baixo.

## 5 · O veredito por tela

Vereditos obtidos **abrindo os protótipos**, com `arquivo:linha` como recibo. **128 telas
verificadas** (as 112 do denominador + 16 que já tinham fonte em outra chave, conferidas junto).

| veredito | n | o que significa |
|---|---:|---|
| **TEM FONTE** limpa | 13 | candidata a promover — 4 delas já declaradas em `visual_source` |
| **TEM FONTE (REVERSO)** | 30 | registrar, **não promover** — ancoraria a tela nela mesma |
| **PARCIAL** | 22 | o protótipo cobre o domínio, não a sub-tela |
| **SEM FONTE** | 39 | com recibo de varredura + controle positivo |
| **FORA DO SHELL POR DESENHO** | 12 | telas públicas — ausência **não é dívida** |
| **NÃO É TELA** | 3 | charters de componente |
| **JÁ DECLARADA por outra chave** | 9 | a cadeia é que não enxerga (§4.1) |

> O grosso do que "tem fonte" é **porte reverso**. Isso não é acidente: o espelho recebeu levas
> de porte do vivo (`sync git 2026-08-17`, `leva 1 do espelho`) que trouxeram o código de volta
> como desenho. Promover essas âncoras fabricaria um loop.

### 5.1 · Candidatas limpas a promover âncora

Fonte real no shell **e** sem porte reverso. São as únicas em que promover é ganho sem dívida:

| tela | fonte | recibo |
|---|---|---|
| `Purchase/Index` | `compras-page.jsx:326` | *"Migrado de Compras.html"* — fonte de design, não do código |
| `Purchase/Show` | `compras-page.jsx:539` | `DrawerView`, abas Resumo·Itens·Documentos·Pagamentos·Histórico |
| `StockAdjustment/Index` | `estoque-page.jsx:208` | `AbaAjustes` |
| `StockAdjustment/Create` | `estoque-forms.jsx:157` | `FormAjuste` |
| `StockTransfer/Index` | `estoque-page.jsx:318` | `AbaTransferencias` |
| `StockTransfer/Create` | `estoque-forms.jsx:253` | `FormTransferencia` |
| `Tarefas/Index` | `tasks.jsx` | o `.tsx:5` já declara `layout: tasks.jsx canon Cowork 2026-04-27` |
| `team-mcp/CcSessions/Index` | `forja-changelog.jsx:11` | `canon_reference` **aprovada por [W] em 2026-06-16** |
| `team-mcp/Scorecard/Index` | `forja-saude.jsx:25` | idem; e *"a Page nunca existiu"* prova a direção Design→Code |
| `OficinaAuto/ServiceOrders/Board` | `oficina-page.jsx` | o **código vivo** cita a fonte: `Board.tsx:3` *"Port do protótipo Cowork… confirmado por [W] 2026-06-02"* — já em `visual_source` |
| `OficinaAuto/ServiceOrders/Show` | `oficina-os-page.jsx:101` | `blade_source: N/A (módulo novo)` — design-first — já em `visual_source` |
| `Atendimento/CaixaUnificada/Index` | `inbox-page.jsx` | *"FONTE VISUAL CANÔNICA pro repo"* — já em `visual_source` |
| `Financeiro/PlanoContas/Index` | `financeiro-telas-extras.jsx:595` | `TelaPContas`; ⚠️ ver ressalva de bidirecionalidade abaixo |

⚠️ Duas ressalvas que acompanham a lista:

- **Estoque:** `estoque-forms.jsx` cita o main no cabeçalho, mas o que veio de lá são **regras**
  (`R-ADJ-003`, `INV-2`, `UC-EST-05`), não layout — e `estoque-contagem.jsx` se declara
  *"ESCOPO NOVO… não existe no Blade nem no React vivo"*. Reconciliar regra com o vivo não é
  portar desenho do vivo. Ao promover, **trocar** o `blueprint_cowork` errado (§4.2) pelo
  `bundle_source`, que já está certo.

  > **ERRATA 2026-09-09 (PR de correção do §4.2) — as 4 de Estoque NÃO foram promovidas.**
  > A leitura acima sobre `estoque-forms.jsx` (regra ≠ layout) está correta, mas duas coisas
  > foram medidas depois e mudam o veredito **destas quatro**:
  >
  > 1. **A promoção tem custo, não é "sem dívida".** [`pt-conformance.mjs:56`](../../../scripts/governance/pt-conformance.mjs)
  >    lê o PT **de `related_prototype`** (`claimedPT(fmGet(fm,'related_prototype'))`). Trocar
  >    `n/a (herda PT-0X)` pelo path tira as 4 da única checagem **falsificável** que elas têm —
  >    o script existe declaradamente contra COUNT-PUMP. Medido aplicando a promoção e rodando:
  >    **91 → 87** declarações de PT.
  > 2. **O ganho na catraca é zero.** `design-coverage` ficou **idêntico** nos dois estados
  >    (`declared 221` · `parityLinked 66`): `declared` conta `hasSource`, e o `n/a` já é `true`;
  >    `parityLinked` lê `related_visual_comparison:`, não este campo. O único delta real é o
  >    `--list` sair de `isNa:true` para `isNa:false` — que é o **Defeito 1 (§4.1)**, um bug de
  >    precedência no resolvedor que atinge **15 charters**. Consertar 4 por edição de dado
  >    deixa 11 e troca o conserto do `||` por backfill.
  >
  > Some-se que [§5 2026-08-28 (c)](../../proibicoes.md) é Tier 0 e diz que o `n/a` **coexiste**
  > com a âncora de bundle por desenho. E o `bundle_source` das duas **Create** aponta
  > `estoque-page.jsx`, não o `estoque-forms.jsx:157`/`:253` que a tabela §5.1 nomeia como fonte
  > delas — logo "o `bundle_source`, que já está certo" vale para as 2 Index, não para as 4.
  >
  > O `blueprint_cowork` errado do §4.2 **foi removido** (nos 4 charters e nos 8 irmãos:
  > `RUNBOOK-stock-*.md` e `stock-*-visual-comparison.md`, que carregavam o mesmo ponteiro).
  > Promover segue possível — mas como decisão [W] que aceite o custo em (1), não como
  > "ganho sem dívida".
- **team-mcp:** a `canon_reference` cita `forja-page.jsx`, que foi decomposto em `forja-*.jsx`
  na Onda 2. A promoção precisa apontar o arquivo **atual**, não o citado.
- **Financeiro/PlanoContas:** `financeiro-page.jsx` e `financeiro-telas-extras.jsx` **não**
  declaram porte reverso (nasceram design-first — *"unified ledger app… Persona: Eliana [E]"*),
  mas o corpo está cheio de reconciliação com o vivo (`:33` *"paridade @main"*, `:1971`
  *"paridade vivo — Archive em Index.tsx"*, `financeiro-telas-extras.jsx:53` *"Sync git
  2026-08-17"*). **Bidirecional**: não é reverso na origem, nem fonte pristina hoje. Decisão de [W].
- **Quatro das nove já estão declaradas** por `visual_source` (as 3 últimas + CaixaUnificada) —
  para elas não há o que promover, e sim conferir se a cadeia enxerga (§4.1).

### 5.2 · Têm fonte, mas é PORTE REVERSO — registrar, não promover

O protótipo declara, no próprio cabeçalho, que nasceu **do código vivo**. Ancorar a tela nele é
ancorá-la em si mesma — [§5 2026-06-05](../../proibicoes.md) ("derivar do código") e a regra
par-a-par de [§5 2026-08-28](../../proibicoes.md).

| grupo | fonte | frase que prova |
|---|---|---|
| `Produto/{Index,Create,Edit,Show,BulkEdit,StockHistory}` | `produto-blade.jsx` · `produto-blade-forms.jsx` | *"importado dos blades `resources/views/product/*`… cada bloco aqui é a tradução 1:1 de um blade"* |
| `Produto/Unificado/Index` | `produtos-page.jsx` | *"Porte do main lido em 2026-08-25: **Pages/Produto/Unificado/Index.tsx**"* — porte do React vivo |
| `Essentials/*` (8 telas) | `essenciais-page.jsx` · `essenciais-extras.jsx` · `hrm-page.jsx` · `hrm-extras.jsx` | *"Importado do blade do main: Modules/Essentials/Resources/views/*"* |
| `Sells/{Drafts,Edit,Quotations,Subscriptions}` | `venda-blade.jsx` · `venda-blade-caixa.jsx` | *"⚠️ Produção passou o blade: … renderizam `Sells/Drafts.tsx` e `Sells/Quotations.tsx`… Esta tela ESPELHA o vivo"* |
| `ComunicacaoVisual/Index` | `comunicacao-visual-page.jsx` | *"Espelho do vivo `resources/js/Pages/ComunicacaoVisual/Index.tsx`"* — já auto-rotulado no `bundle_source` |
| `User/Perfil` | `perfil-page.jsx` | *"Redesign fiel da tela legada `resources/views/user/profile.blade.php`"* |
| `Suporte/Visao` | `suporte-page.jsx` | *"Espelho do vivo `resources/js/Pages/Suporte/{Empresas,Visao}.tsx`"* |
| `Jana/Pro` | `jana-pro.jsx` | *"Espelho do vivo `resources/js/Pages/Jana/Pro.tsx`"*; a fonte original sumiu do Cowork |
| `team-mcp/Tasks/Index` | `forja-tarefas.jsx` | *"cópia fiel do conceito de produção `Tasks/Index.tsx` @main"* — **direção invertida**: a `canon_reference` registra que a fonte original (jun/2026) era Design→Code |
| `governance/ModuleGrades/Show` | `governance-telas.jsx` | *"Espelha as telas vivas de `resources/js/Pages/governance/`"*; e ele cobre `ModuleGrades/**Index**`, não o `Show` |

**Caso especial — `Essentials/Settings/Index`:** além de reverso, o inventário de paridade
(`EXPORT-HRM-2026-09-04.md:165`) classifica o protótipo como **à frente**: o vivo já superou o
desenho. Ancorar seria apontar para trás.

### 5.3 · SEM FONTE — e o recibo de por quê

O protótipo **declara o próprio limite** em dois casos, o que é a melhor evidência possível de
ausência (não é "não achei", é "a fonte diz que não desenha"):

- `configuracoes-page.jsx:324` — *"Alíquota, CFOP, NCM e CST ficam em **NF-e Brasil** — aqui só
  os números que aparecem impressos."*
- `prefs-page.jsx:121` — *"Certificado A1 e regras de NCM ficam em **NF-e Brasil**."*
- `compras-page.jsx:408` — *"Nova compra abre o formulário em modo foco — **fora deste protótipo**."*
- `oficina-forms.jsx:7` — *"FORA DE ESCOPO: **Veículos CRUD**, Caçambas, **Aprovação pública UI**,
  Show full-page."*

Sem fonte no shell, por bloco:

| tela | recibo |
|---|---|
| `NfeBrasil/Tributacao/{Index,ConfigDefault,RegraForm,ImportCsv}` | a própria fonte aponta para fora (acima); zero formulário de regra NCM no espelho |
| `Nfse/Emitir` | o botão "Emitir" do cockpit só navega (`fiscal-page.jsx:96`); a emissão vive dentro da venda |
| `Whatsapp/Settings` | `embedded signup`/`waba`/`business manager` → `rc=1` no `prototipo-ui/` inteiro; o inventário declara origem externa (Stripe/Linear) |
| `Atendimento/Csat/Index` | `csat` → `rc=1` no espelho (controle positivo: 54 ocorrências em `CsatFlowTest.php`, a sonda funciona) |
| `Atendimento/JanaTemplates` | falso-positivo por nome: `inbox-page.jsx:157 JANA_TEMPLATES` são **prompts de IA**, não o binding HSM↔evento da tela viva |
| `Atendimento/Macros/Variants` | os 3 hits de `weight` são `fontWeight` |
| `Atendimento/Metricas/Index` | `kpi`/`dashboard`/`chart` nos 5 `inbox-*.jsx` → `rc=1` |
| `TransactionPayment/{Edit,Show}` | o drawer mais próximo detalha **título** (`fin_titulos`), não linha de pagamento |
| `RecurringBilling/Planos/{Create,Edit}` | a aba "Planos" é um `<Placeholder>` de 3 elementos (`cobranca-recorrente-page.jsx:369`) |
| `Settings/PaymentGateways/CnabRetorno` | o batch Cowork do módulo declara 3 telas, e essa não está |
| `Purchase/Edit` | excluído explicitamente pela fonte |
| `Essentials/Knowledge/{Create,Edit}` | `kb_type`/`share_with` → 0 hits |
| `OficinaAuto/Vehicles/{Index,Create,Edit,Show}` | excluído explicitamente; o hero de veículo do `oficina-os-page.jsx` é contexto **dentro da OS** |
| `ads/Admin/{Projects,ProjectShow}` · `KB ads/Admin/Graph` | a chave `projects` do mockup é **ProjectMgmt** (gantt/timesheet), outro domínio |
| `Auditoria/Detail` | o mockup não tem drill-down/diff/revert |

### 5.4 · Não deveriam ter âncora no shell

Telas públicas, fora do `AppShellV2`. Ausência aqui **não é dívida** — e classificá-las como
"sem fonte" inflaria o gap com trabalho que não existe:

`Cms Site/{Home,Blogs,BlogPost,Page}` · `Superadmin Site/Pricing` · `Site/{Login,Register}` ·
`ConsultaOs/Index` · `OficinaAuto/AprovacaoPublica` · `Whatsapp/FeedbackPublico`.

Recibo do último: `Modules/Whatsapp/Routes/web.php:54` usa middleware `signed`, sem `auth`.

### 5.5 · Não são telas

`kb/_components/{NodeReader,PathsDialog,TroubleshooterDialog}` são **charters de componente** —
os próprios charters dizem `drawer concept — sem .tsx de página dedicada`. A fonte existe, é limpa
e já está nomeada **dentro do código** (`NodeReader.tsx:43`: *"Port consolidado de
`kb-page.jsx::ArticleReader` (Cowork)"*). Não falta âncora: falta a declaração estar num campo
que alguma máquina leia.

### 5.6 · O inventário que já existe em código e ninguém lê

O melhor mapa de "o que o protótipo NÃO desenha" **já está escrito**, dos dois lados, e nenhuma
máquina o consome:

- **No protótipo:** `data.jsx:581` `FIN_SUBNAV_OVERFLOW` lista 9 destinos, e
  `financeiro-legado.jsx:2` os nomeia como *"destino sem rota"*.
- **No código vivo:** `financeiroMenu.ts:67` — `// — abaixo NÃO estão no protótipo: overflow ⋯ —`.
- **Mapa reverso completo do Repair:** `repair-page.jsx` tem 12 marcadores de seção
  `// ══ X (blade.php) ══`, cada um nomeando o blade de origem.
- **O código declara a própria proveniência:** `Board.tsx:3` *"Port do protótipo Cowork
  'oficina-page.jsx'… confirmado por [W] 2026-06-02"*; `NodeReader.tsx:43` *"Port consolidado de
  `kb-page.jsx::ArticleReader`"*.

Isso é mais confiável que qualquer heurística — e é o material natural para fechar §4.

## 6 · Portas vivas (o estado de hoje, não o deste arquivo)

```bash
node prototipo-ui/ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork   # âncora de UMA tela
npm run design:coverage                                                  # cobertura + baldes
node scripts/governance/cowork-mirror-freshness.mjs --compare --check    # o espelho está fresco?
```

⚠️ Use **sempre o caminho `.tsx` completo** no `ancora.mjs`, nunca o atalho `Mod/Tela` — §4.4.

## 7 · Limites declarados deste inventário

1. **A claim de ausência vale para o shell, não para o Cowork vivo.** O
   `cowork-mirror-freshness --sla` reporta o espelho completo e fresco (273/273, 0 stale), **mas**
   sinaliza 98 arquivos que existem no Cowork e não no espelho. Os nomeados na saída não incluem
   nenhum `-page.jsx`, e a lista completa não é versionada. Logo: firme sobre o shell,
   **inconclusiva sobre o vivo**.
2. **"Porte reverso" não é mecanizável por regex.** Um detector de marcadores marcou 62 dos 199
   arquivos; ao separar marcador forte de fraco, 40 exigiram leitura humana — e a separação
   automática ela mesma errou nos dois sentidos (`forja-page.jsx` diz *"Tela = projeção do git"*,
   que significa que a Forja **lê** o git como dado, direção oposta). **Os vereditos de §5 vêm de
   leitura, não do regex.** Nenhum número de "quantos são reversos" deve ser citado deste arquivo.
3. **Contagens envelhecem.** 341 commits nos 5 dias que antecederam esta medição. A divergência
   entre a classificação de 2026-09-05 (110) e esta (112) é esperada e não indica erro de nenhuma
   das duas.
4. **A camada `mockup-bodies.js`** (16 telas em HTML cru, fora do DS v6) foi tratada como
   candidato fraco, não como fonte. Onde ela era o único candidato (`Auditoria/Index`), o veredito
   ficou PARCIAL, não TEM FONTE.

## 8 · Continuação por módulo (chips)

[W] 2026-09-09: *"pode corrigir por modulos e chips em sessões frescas"*. A correção sai
**par-a-par, por módulo**, em sessões próprias — nunca em leva. Este documento é a entrada delas.

| chip | escopo | por quê separado |
|---|---|---|
| Estoque | trocar o `blueprint_cowork` errado (§4.2) + promover as 4 âncoras | tem bug de dado **e** promoção |
| Purchase | promover `compras-page.jsx` em Index/Show | fonte limpa, 2 telas |
| Forja/team-mcp | promover CcSessions + Scorecard apontando `forja-*.jsx` **atual** | a `canon_reference` cita arquivo decomposto |
| `ancora.mjs` — precedência | o `n/a` truthy que esconde 15 `bundle_source` (§4.1) | conserto de máquina, não de charter |
| `ancora.mjs` — chaves 4 e 5 | `blueprint_cowork` (38) + `canon_reference` (31) + colisão `Nfse/Index` | idem |
| heurística `startsWith` | 73% de erro medido (§4.3) — decidir entre remover ou rotular como não-âncora | **decisão de [W]**: mexer na regra dura |

## 9 · O que este inventário NÃO faz

Não promove âncora nenhuma. Promoção é **par-a-par, com o cabeçalho do protótipo como evidência**,
e carimbo em leva é proibido ([§5 2026-08-28](../../proibicoes.md)). As 9 telas de §5.1 são
candidatas; a decisão é de [W]. As de §5.2 ficam registradas justamente para que ninguém as promova
sem ver que são porte reverso.
