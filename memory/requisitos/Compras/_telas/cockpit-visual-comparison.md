---
tela: Compras/Index
modulo: Compras
tipo: LIST
generated_at: 2026-09-07
generated_by: "[C] design-diff medido no runtime (PROTOCOLO-COMPARACAO-RUNTIME D1-D9)"
status: medido
prod_url: https://oimpresso.com/compras
design_ancora: prototipo-ui/cowork/Wagner/compras-page.jsx
roles_override: governance/design/targets/roles/Compras--Index.json
contrato: governance/design/contracts/compras-cockpit.contract.json
---

# Visual Comparison — cockpit `Compras/Index`

> **Medido, não olhado.** Os dois lados foram renderizados e sondados com a MESMA sonda
> (`design-diff --probe`), no MESMO tema (dark) e no mesmo navegador, e o veredito saiu do
> `design-diff --compare`. Nenhum item abaixo veio de leitura de código ou de screenshot.

## Como foi medido (reprodutível)

| | |
|---|---|
| lado design | espelho `prototipo-ui/cowork` servido em `http://127.0.0.1:5599/oimpresso.com.html`, `localStorage['oimpresso.route']='compras'` |
| lado produção | `https://oimpresso.com/compras`, sessão logada |
| estabilidade | esperado `__oiLazyDone` + 3 leituras iguais de `querySelectorAll('*').length` (design 1199 nós; prod 779) |
| papéis | `governance/design/targets/roles/Compras--Index.json` — medidos no DOM dos dois lados |

**Por que o override de papéis existe:** os defaults heurísticos do `design-diff-lote`
produziram **dois falsos positivos** nesta tela. Ambos foram verificados e refutados **antes**
de virar trabalho — e é por isso que eles estão registrados aqui, não corrigidos no código.

## Achados

### ✅ REAL — faltam 2 colunas na produção

| | colunas |
|---|---|
| protótipo | Ação · Compra · Fornecedor · Data · Estágio · **Itens** · Total · A pagar · **NF-e** (9) |
| produção | Ação · Compra · Fornecedor · Data · Estágio · Total · A pagar (7) |

O charter da tela declara as duas que faltam (§Goals: *"colunas Compra / Fornecedor / Data /
Estágio (pill colorida) / Itens / Total / A pagar / NF-e"*), então a produção diverge do
protótipo **e** do próprio charter.

**Não é preferência de coluna oculta:** medido no navegador, `localStorage` não tem chave de
visibilidade para esta tela (`storage: []`), e "Itens"/"NF-e" não aparecem em lugar nenhum do
texto renderizado.

⚠️ **Exige backend, não é onda de frontend:** a `interface Row` do `Index.tsx` não tem campo de
contagem de itens nem de nota fiscal. Fechar isto é adicionar dado ao payload do
`ComprasController`/`ComprasService` — onda própria, com decisão [W] sobre qual dado a coluna
NF-e mostra (número, chave, ou status de emissão).

### ❌ REFUTADO — título 16px × 22px NÃO é decisão de design

O comparador acusou `[D4] título font-size: prod=16px · design=22px → DIVERGE (bug)`.
**Verificado e refutado.** Os dois lados declaram a MESMA regra:

```
.compras-root .hd h1 { font-size: 16px }   ← idêntica em cowork-compras-bundle.css e compras-page.css
```

No protótipo o `<h1>` está **fora** do container `.hd` (medido: `closest('.hd')` = null, pai sem
classe), então a regra não o alcança e ele cai no padrão do agente de usuário — 22px por
acidente de markup, não por escolha. Aplicar 22px na produção seria **copiar um defeito do
protótipo**.

Classificação: **DERIVA do lado do design** (ninguém escolheu). Vai como achado de volta ao
Cowork, não como correção na produção.

### ❌ REFUTADO — "filtro em 3 linhas" é artefato de seletor

O comparador acusou `[D2] filtro linhas: prod=3 · design=1`. Medido no DOM: a zona
`[data-contract="compras-abas"]` da produção tem 6 filhos — as 4 abas (todas no mesmo `top`,
uma linha só), mais um espaçador `.sp` e o bloco `.filters`, que têm `top` diferente por
alinhamento vertical. O seletor do design (`.jm-tabs > *`) pegava **só as abas**.

Conjuntos diferentes, não quebra de linha. O override já corrige o seletor da produção para
`[data-contract="compras-abas"] > a`.

### 🟡 A INVESTIGAR — cor própria em células

`col1.cor: prod=herda a da linha · design=cor própria` (DIVERGE bug) e o inverso em col0/col6
(DIVERGE fonte, produção à frente). Não investigado nesta rodada; fica declarado em vez de
suposto.

### ⬜ SEM-DADO — as 4 regiões `data-contract`

`compras-cabecalho`, `compras-abas`, `compras-kpis`, `compras-tabela` existem **só na produção**
(instrumentadas no #6806). O protótipo não tem âncoras `data-contract`, então a dimensão D9
não compara. Não é defeito de nenhum dos lados; é instrumentação que só um lado tem.

## Placar

| | |
|---|---|
| divergências acusadas pela máquina | 4 |
| **reais após verificação** | **1** (colunas) |
| refutadas (artefato de seletor ou acidente do protótipo) | 2 |
| a investigar | 1 |
| shell "a classificar" | 36 — fora do escopo desta tela (é fundação, e o comparador não distingue DECIDIDA/DERIVA/DESIGN-ANDOU sozinho) |

**A lição que este placar carrega:** 3 de 4 acusações não sobreviveram à verificação. Comparação
medida é o começo do trabalho, não o veredito — cada divergência precisa da pergunta "o seletor
está medindo a mesma coisa dos dois lados?" antes de virar código.

---

## Atualização — 2026-09-07, mesmo dia (o achado das colunas foi atendido)

> **O corpo acima fica intacto de propósito.** Ele é o retrato do que foi MEDIDO às 20:00 de
> 2026-09-07, e continua verdadeiro como registro daquela hora. Esta seção diz o que aconteceu
> depois — quem for citar o achado das colunas como estado do mundo deve ler daqui.

| quando | o quê |
|---|---|
| 20:00 | esta medição ([#6951](https://github.com/wagnerra23/oimpresso.com/pull/6951)) |
| 21:24 | [#6955](https://github.com/wagnerra23/oimpresso.com/pull/6955) leva as 2 colunas ao cockpit — 84 min depois |
| depois | este PR corrige a **fonte** da coluna NF-e |

**A decisão [W] que o §Achados pedia não era necessária.** O texto acima diz *"com decisão [W]
sobre qual dado a coluna NF-e mostra (número, chave, ou status de emissão)"*. Estava respondido na
própria fonte de design, e eu não a tinha lido: `compras-page.jsx:508` renderiza
`{p.xmlChave ? "✓ XML" : "—"}` — **status de presença**, com a chave de 44 dígitos aparecendo só no
drawer (`:679`). Escalar o que a fonte já respondia é a classe LC-28. Registrado no #6955.

**O que este PR corrigiu.** O #6955 ligou a coluna a `transactions.document` porque *"já vinha na
query do core"* — critério de disponibilidade, não de significado. Medido depois:

| campo | o que é | de onde vem |
|---|---|---|
| `transactions.document` | anexo genérico de arquivo, baixável, podendo ser imagem | `PurchaseController` → `uploadFile($request, 'document', 'documents')` |
| `transactions.chave_entrada` | chave de acesso de 44 dígitos da NF-e de entrada | `PurchaseXmlController:341`, e nomeia o XML em `xml_entrada/<cnpj>/<chave>.xml` |

Lido como NF-e, `document` faz a tela afirmar **"✓ XML" para um JPEG anexado** e **"—" para uma
compra com nota de verdade**. O drawer já errava assim antes do #6955 — `Drawer.tsx:489` exibia o
nome do arquivo sob o rótulo *"chave de acesso"*, sustentado por um comentário que afirmava
*"UPos guarda chave NF-e em document quando há"*. Os dois passaram a ler `chave_entrada`.

**Ressalva honesta sobre o valor prático.** O único escritor de `chave_entrada` é o
`PurchaseXmlController`, que o canon do projeto já registrava como *"completo porém ÓRFÃO sem
rota"* ([`INDICE-INVISIVEIS-2026-07.md`](../../../reguas/INDICE-INVISIVEIS-2026-07.md)) —
confirmado aqui por varredura repo-inteiro com controle positivo: zero referência em `routes/`,
enquanto o mesmo padrão acha `PurchaseController::class` em `routes/web.php:783`. Ou seja: a
coluna nasce **honesta e quase sempre vazia**, e acende quando a bridge DF-e→compra
(**US-COM-003**, Wave 6) ligar `nfe_dfe_recebidos.chave_44` à transação. Não medi a base de
produção — se linhas migradas do legado carregam a chave, é pergunta de prod, não de repo.

**O placar acima NÃO foi recontado.** Recontar exige os dois renders com a mesma sonda
(`design-diff --probe` → `--compare`), e o lado produção só muda depois do deploy. Enquanto a
re-medição não roda, o número de divergências desta tela segue sendo o de 20:00.
