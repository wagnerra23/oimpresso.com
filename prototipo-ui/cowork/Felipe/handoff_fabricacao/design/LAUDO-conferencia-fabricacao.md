# LAUDO de conferência — Fabricação (Manufacturing)

## CONTEXTO

| | |
|---|---|
| **Tela** | Família Fabricação: consulta de receitas, drawer, editor de ingredientes, insumos (impacto reverso), ordens de produção, formulário de ordem, relatório, configurações, ficha PT-07 |
| **Arquivos** | `manufacturing-{data,page,recipe,insumos,producao,print}.jsx` **na raiz do projeto** (a cópia em `design/` foi apagada em 22/09/2026; a página-guia carrega por `../../`) · `design/04-modulos/manufacturing/css/manufacturing.css` |
| **Como rodou** | `design/Fabricacao - Guia de Producao.html` no navegador, viewport **914 × 540 px**, tema **claro**, React 18.3.1 + Babel 7.29.0 |
| **Especificação** | `README.md` (raiz do pacote) · `contexto/SDD-tela-fabricacao-v1.0.md` |
| **Telas irmãs** | `resources/js/Pages/Manufacturing/Index.tsx` (existe no alvo) · Compras · Produtos · Estoque |
| **Design system** | WAGNER Office Impresso — tokens `.cockpit` de `_ds/wagner-office-impresso-design-system-49a36f76-…/colors_and_type.css` |
| **Plataforma alvo** | Cockpit desktop ≥ 1280px (declarado em `Index.charter.md` L28) |
| **Tarefas conferidas** | 8 (§5) |
| **Data** | 2026-09-01 · **Avaliador:** Claude (sessão de handoff) |

> Este documento é **medição datada**. Envelhece. Não é especificação — a especificação é o
> `README.md`.

---

## 1 · VEREDITO

**APROVADO COM RESSALVAS.**

A família entrega o que se propõe: os cinco caminhos funcionam, o custo fecha com a fórmula do
legado, e as três declarações que o produto exige (subconjunto declarado, custo congelado × custo
vivo, regra de negócio derivada e não digitada) estão na tela, não no rodapé de um documento.

As ressalvas são duas de acessibilidade herdadas do DS (achados 1 e 2, com ADR aberta) e uma de
layout da própria tela (achado 3, correção de 3 linhas). Nenhuma bloqueia a reimplementação; as
três precisam entrar no mesmo commit da tela.

---

## 2 · PLACAR

| Eixo | Resultado | Bloqueadores |
|---|---|---|
| **Funcionalidade** | 8 de 8 tarefas do golden path completas · 5 abas + 3 overlays renderizados e operados | 0 |
| **Acessibilidade** | 7 de 10 pares de contraste aprovam · 3 reprovam (2 são defeito de origem do DS) | 0 (2 ADRs abertas) |
| **Harmonia** | 4 cores cruas na tela (scrim/sombra) · 11 no papel · 10 tamanhos de fonte · 9 raios · 5 sombras · 12 de 19 valores de espaçamento fora da grade de 4 | 0 |

---

## 3 · ACHADOS

```
[ALTA] Rótulo, código e unidade em --text-mute reprovam AA nos dois temas
Eixo: acessibilidade
Onde: .mfg-th, .mfg-th.sort, .mfg-kpi-l, .mfg-sku, .mfg-u, .mfg-fld>span, .mfg-fld small,
      .mfg-crumb-meta, .mfg-ing .n small — manufacturing.css
Medido: --text-mute sobre --surface = 3,24:1 (claro) e 3,18:1 (escuro); sobre --bg-2 = 2,92:1
      (claro) e 3,94:1 (escuro). Mínimo 4,5:1 — nenhum dos usos passa de 11,5px, então nenhum é
      texto grande. --text-dim mede 5,42–6,80:1 nos mesmos fundos.
Por que importa: é o texto que nomeia a coluna de dinheiro, o SKU do insumo e a unidade da
      quantidade. SKU lido errado troca o insumo do lote; unidade lida errada troca m² por m linear.
Correção sugerida: --text-dim. É troca de token, não cor nova. ADR 0410 (terceiro registro do
      mesmo token no produto → pertence ao DS, não à tela).
```

```
[ALTA] --accent como cor de TEXTO reprova no tema escuro
Eixo: acessibilidade
Onde: .mfg-link (7 pontes entre módulos), .mfg-tot .big dd (o número principal do quadro de
      custo), .mfg-tab.act, .mfg-chip.act, .mfg-crumb button — manufacturing.css
Medido: --accent sobre --surface = 5,15:1 no claro e 2,64:1 no escuro; sobre --bg-2 = 4,66:1 e
      3,27:1. O bloco .cockpit[data-theme="dark"] reescreve --pos/--neg/--warn mas NÃO --accent
      (colors_and_type.css L328-341).
Por que importa: 2,64:1 é o pior número desta família, e cai exatamente no valor "Custo por
      unidade" — o número pelo qual a tela existe.
Correção sugerida: versão clara do acento no bloco escuro do DS (mesmo critério já usado em
      --pos/--neg/--warn). Enquanto não houver, acento só em fundo/borda/anel no escuro. ADR 0411.
```

```
[MÉDIA] Pílula, chip e contador de aba vazam quando o rótulo tem mais de uma palavra
Eixo: harmonia / funcionalidade
Onde: .mfg-pill (coluna "Maior peso" da aba Insumos, 160px), .mfg-chip (chip de categoria
      "Comunicação visual"), .mfg-tab-n (contador "6 · 1 rasc." da aba Ordens) — manufacturing.css
Medido: a 914px de largura de viewport, os três quebram em duas linhas e o texto sai da borda
      arredondada; a segunda linha cai fora do fundo da pílula. Só três seletores do arquivo
      declaram white-space:nowrap (.mfg-name b, .mfg-cat, .mfg-ing .n) — as pílulas não declaram.
Por que importa: pílula vazada é o tipo de defeito que o cliente vê antes de qualquer outra coisa,
      e acontece justamente no rótulo mais informativo ("22 % do custo").
Correção sugerida: white-space:nowrap em .mfg-pill, .mfg-chip e .mfg-tab-n, e a coluna "Maior
      peso" de 160px para 176px (ou encurtar o rótulo para "22%" e levar "do custo" para o
      cabeçalho da coluna). Correção da TELA, não do DS.
```

```
[MÉDIA] --warn como cor de texto reprova por margem estreita no tema claro
Eixo: acessibilidade
Onde: .mfg-pill.warn (10,5px, faixa de margem 45–54,9%), .mfg-kpi-v.warn (21px 600),
      .mfg-sim b.up — manufacturing.css
Medido: --warn sobre --surface = 4,40:1 (claro); sobre --bg-2 = 3,97:1. Mínimo 4,5:1 para texto
      pequeno. O KPI de 21px 600 passa como texto grande (mínimo 3:1); a pílula de 10,5px não.
      No escuro o mesmo par mede 7,17:1 — passa.
Por que importa: é a faixa "atenção" da margem, a que deve chamar mais atenção e chama menos.
Correção sugerida: na pílula, o tom de texto é o próprio --warn misturado com --text
      (color-mix(in oklch, var(--warn) 62%, var(--text))) — o helper que o produto já usa em
      Venda. Não criar cor nova. Fica para a pauta se a mistura não resolver nos dois temas.
```

```
[BAIXA] Campo numérico em linha tem 26px de altura
Eixo: acessibilidade
Onde: .mfg-inp.num (quantidade de ingrediente no editor e consumo na ordem)
Medido: height 26px, width 82px — abaixo do piso de 44px que a camada transversal aplica em
      pointer:coarse (otimiza-ondas.css L18-20).
Por que importa: no tablet da produção o campo fica pequeno para o dedo.
Correção sugerida: nenhuma — ACEITO e declarado. A persona do editor é teclado+mouse no balcão;
      a persona de tablet (Eliana) usa a via de produção impressa, não o editor. Se o editor for
      para tablet, isto volta a ser achado ALTA.
```

```
[BAIXA] Overlay não aprisiona o foco
Eixo: acessibilidade
Onde: .mfg-drw e .mfg-modal (role="dialog" + aria-label presentes; esc fecha; scrim fecha)
Medido: Tab a partir do último botão do rodapé sai do overlay e volta para a tabela atrás.
Por que importa: leitor de tela e navegação por teclado perdem o contexto do diálogo.
Correção sugerida: no alvo, usar ui/sheet.tsx e ui/alert-dialog.tsx, que já têm focus trap. Não
      escrever trap à mão. README §18 item 4.
```

```
[BAIXA] Markup do "Atualizar preço de venda" é placeholder
Eixo: funcionalidade
Onde: manufacturing-page.jsx, atualizarPrecos() — venda = custo unitário × 2
Medido: fator 2 fixo, sem regra por categoria nem margem-alvo.
Por que importa: é o único lugar onde a tela ESCREVE preço de venda. Um fator errado em massa
      reprecifica o catálogo.
Correção sugerida: não implementar. Pendência 1 do README §18 — precisa de decisão de negócio.
```

---

## 4 · TABELA DE CONTRASTE

WCAG 2.1, sRGB, conversão OKLCH→sRGB dos tokens de `colors_and_type.css` (L274-341).
Transição de cor desligada antes de amostrar (o `<a>` do DS tem `transition: color`; medir no meio
da transição produz falha fantasma).

| Par | Claro | Escuro | Mínimo | |
|---|---|---|---|---|
| `--text` / `--surface` | 17,32:1 | 11,42:1 | 4,5 | ✅ |
| `--text` / `--bg-2` | 15,65:1 | 14,16:1 | 4,5 | ✅ |
| `--text-dim` / `--surface` | 6,00:1 | 5,49:1 | 4,5 | ✅ |
| `--text-dim` / `--bg-2` | 5,42:1 | 6,80:1 | 4,5 | ✅ |
| `--text-mute` / `--surface` | 3,24:1 | 3,18:1 | 4,5 | ❌ |
| `--text-mute` / `--bg-2` | 2,92:1 | 3,94:1 | 4,5 | ❌ |
| `--accent` texto / `--surface` | 5,15:1 | 2,64:1 | 4,5 | ❌ escuro |
| `--accent` texto / `--bg-2` | 4,66:1 | 3,27:1 | 4,5 | ❌ escuro |
| `--pos` texto / `--surface` | 5,67:1 | 6,24:1 | 4,5 | ✅ |
| `--neg` texto / `--surface` | 5,32:1 | 5,12:1 | 4,5 | ✅ |
| `--warn` texto / `--surface` | 4,40:1 | 7,17:1 | 4,5 | ❌ claro |
| `#fff` / `--accent` (botão primário) | 5,15:1 | 5,15:1 | 4,5 | ✅ |

Valores usados — claro: `--surface #ffffff` · `--bg-2 oklch(0.965 0.004 90)` ·
`--text oklch(0.22 0.01 80)` · `--text-dim oklch(0.50 0.01 80)` · `--text-mute oklch(0.65 0.01 80)`
· `--accent oklch(0.55 0.15 295)` · `--pos oklch(0.50 0.12 150)` · `--neg oklch(0.55 0.18 25)` ·
`--warn oklch(0.58 0.12 70)`. Escuro: `--surface oklch(0.30 0.008 240)` ·
`--bg-2 oklch(0.23 0.006 240)` · `--text oklch(0.94 0.005 90)` · `--text-dim oklch(0.72 0.005 90)`
· `--text-mute oklch(0.58 0.005 90)` · `--pos oklch(0.74 0.14 150)` · `--neg oklch(0.72 0.16 25)`
· `--warn oklch(0.80 0.13 75)` · `--accent` **não reescrito**.

---

## 5 · O QUE FOI VERIFICADO NA FUNCIONALIDADE

| # | Tarefa | Resultado |
|---|---|---|
| 1 | Abrir o módulo e ler a consulta de receitas | ✅ 8 receitas, 4 KPIs (custo médio R$ 64,08 · 1 margem magra · 2 desperdício · 5 produções), tabela de 8 colunas com `thead` fixo |
| 2 | Abrir o drawer de uma receita | ✅ 3 grupos com subtotal (R$ 32,48 + R$ 15,12 + R$ 16,56 = R$ 64,16 = "Ingredientes") — **soma fecha**; quadro de custo, nota da recalculação, 5 ações no rodapé |
| 3 | Entrar no editor de ingredientes | ✅ trilha `Receitas / <nome>`, meta `MFG-0002 · 4 ingredientes em 3 grupos`, lateral com desperdício 12% e dica `rende 4,40 m² de 5,00` — **rendimento fecha** |
| 4 | Aba Insumos e simulador | ✅ 22 insumos ordenados por nº de receitas; drawer de `INS-022` com slider em +10% (`R$ 108,00 → R$ 118,80 / L`) e 3 receitas afetadas com custo novo e margem nova |
| 5 | Aba Ordens de produção | ✅ 6 ordens, filtro de local + período + só finalizadas; 5 das 6 com sufixo `fix`; o rascunho (MFG2026/0038) sem `fix` |
| 6 | Nova produção | ✅ referência gerada com o prefixo `MFG2026/`, consumo calculado, dica `receita rende 10,00 m² por lote` |
| 7 | Aba Relatório | ✅ 5 produtos agrupados, ordenado por custo desc, barra de % (38 / 22 / 21 / 14 / 6 = 101% por arredondamento de apresentação — **os valores brutos somam 100%**) |
| 8 | Aba Configurações | ✅ prefixo, 2 switches, versão `v6.2.1`, botão `Atualizar` **desabilitado** sem alteração; cartão de permissões e cartão de integrações |

---

## 6 · NÃO VERIFICADO

- **Impressão real.** O CSS da folha PT-07 foi lido linha a linha, mas a folha **não foi
  renderizada** nesta conferência — nem em PDF, nem em papel. Cotas, marcas de corte, tira CMYK,
  escada de cinza, quebra de página por grupo (`page-break-inside: avoid`) e a variante "via de
  produção" **estão sem medição**.
- Lighthouse, axe, leitor de tela (NVDA/VoiceOver), zoom 200%.
- Navegação completa por teclado no editor (Tab entre linhas de ingrediente, `Enter` na busca de
  insumo foi verificado por leitura de código, não por uso).
- Tema escuro **visualmente** — os números de contraste do escuro são calculados dos tokens, não
  amostrados na tela.
- Viewport de 1280px+ (a plataforma alvo). A conferência rodou a 914px, que é **mais estreito** que
  o alvo: por isso o achado 3 aparece aqui; a 1280px ele pode não aparecer, e continua a ser um
  defeito, porque a tabela de insumos tem largura mínima de 900px e rola.
- Exclusão de receita, impressão em lote e "Atualizar preço de venda do produto" (as três ações
  destrutivas/de escrita) — só leitura de código.

---

## 7 · HARMONIA — NÚMEROS BRUTOS

| Métrica | Valor | Observação |
|---|---|---|
| Cores cruas na tela | **4** | `rgba(0,0,0,.28 / .32 / .40 / .42)` — scrim de overlay e 3 sombras elevadas. Sem token de scrim/sombra no DS → pauta |
| Cores cruas no papel | **11** | `#fff #000 #111 #555 #666 #777 #999 #bbb #ccc #ddd #f0eeeb` — ADR 0413 |
| Tintas de processo | **4** | `#00AEEF #EC008C #FFF200 #231F20` na tira de prova — valor de tinta, não cor de tema |
| Usos de `color-mix` sobre token | **14** | tinta de pílula 7–8%, borda 30%, anel 22–35% |
| Tamanhos de fonte distintos | **10** | 10 · 10,5 · 11 · 11,5 · 12 · 12,5 · 13,5 · 14 · 15 · 16px (+ 21px no valor de KPI, via shorthand `font:`) |
| Raios distintos | **9** | 3 · 5 · 6 · 8 · 10 · 12 · 999px + 2 raios parciais de canto de tabela |
| Sombras distintas | **5** | 3 com `rgba` cru, 2 com `color-mix` de acento |
| Espaçamento: valores distintos | **19** | de 1px a 56px |
| Fora da grade de 4 | **12 de 19** | ímpares: 1 · 3 · 5 · 7 · 9 · 11 · 13px; pares fora de 4: 2 · 6 · 10 · 14 · 18px |
| `!important` | **0** na tela · **2** no papel | os dois no bloco `@media print`, para vencer o `display` do shell |

**Leitura:** 10 tamanhos de fonte é muito para uma família — a rampa do DS tem 9 degraus para o
produto inteiro. A causa é herdada: os literais (12,5px, 11,5px, 10,5px) foram escritos antes de a
rampa `--fs-*` existir. Não é correção desta entrega, é dívida declarada: a reimplementação no
alvo usa as classes de tipografia do Tailwind/DS e o número cai naturalmente.
