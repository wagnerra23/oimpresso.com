# Patch de cor — Consulta de Produtos (`/products/unificado`)

Complemento ao handoff em execução. **Só cor.** Não mexer em layout, conteúdo, ordem de coluna,
menu, permissão ou comportamento.

> **Revisão 2 — 24/08/2026. Substitui integralmente a revisão 1**, cujos valores (6% de fundo, 22%
> de borda, glyph 600, `rounded-xl`, `ring-primary/40`, 12px) foram escritos **por inferência da
> prosa do guia do DS**, não por leitura dos componentes. Estavam errados. Os valores abaixo foram
> **medidos no bundle**, com arquivo e linha.

## Regra zero — o design system sempre ganha, e você nunca decide sozinho

1. **O DS ganha de tudo**: deste patch, do README do handoff, do protótipo, de auditoria, de
   régua de acessibilidade, de bom senso. Sem exceção e sem grau.
2. **Não pressupor, não assumir, não inferir.** Se o valor não estiver citado aqui com arquivo e
   linha, ele **não está decidido** — abra o componente e leia. Não existe "deve ser parecido com".
3. **Ao encontrar divergência, contradição ou valor que pareça errado: pare e pergunte.**
   Não corrigir, não substituir, não adaptar, não "melhorar", nem em nome de acessibilidade,
   contraste, coerência ou de outra regra deste documento. **Divergência não autoriza ação** —
   autoriza pergunta. Aplicar o valor do DS como está e relatar a tensão é o comportamento certo;
   trocar o valor por conta própria é defeito de entrega, mesmo quando a troca é tecnicamente melhor.
4. **Conferir sempre de onde vem.** Todo valor tem procedência declarada (abaixo). Valor sem
   procedência não se usa.

### Protocolo de divergência (o que fazer, exatamente)

Quando o DS contradiz este documento, outra regra do projeto, ou o que parece correto:

1. **Aplique o valor do DS**, como está.
2. **Relate na entrega**, em uma linha: o que o DS manda, o que a outra fonte pedia, e o número
   medido que mostra a tensão.
3. **Não altere nada** enquanto a Wagner não responder. Exceção só existe **assinada por ela e
   registrada** em `pauta-design-system.md`. Não há exceção implícita, nem por urgência, nem por
   norma externa.

## Procedência — como ler cada valor

Toda linha traz uma marca:

- **[DS]** — vem do componente do design system. Fonte citada com arquivo e linha; a expressão é
  literal, transcrita, não parafraseada. **Não recalcular, não arredondar.**
- **[TPL]** — vindo de **template canônico** do DS (`templates/pt-01-lista/…`), que fixa o que está
  *entre* os componentes: contêiner, moldura, sombra, gap. Ganha de [TELA] sempre. Antes de decidir
  qualquer coisa como [TELA], abrir o template do mesmo tipo de tela.
- **[TELA]** — decidido nesta tela por não existir **nem em componente nem em template**.
  Contestável, e o motivo está dito.
- **[RUNTIME]** — observado num navegador rodando. **Nunca é regra.** Ver §7.

Medido em `_ds_bundle.js` do pacote (`design/_ds/wagner-office-impresso-design-system-49a36f76-…/`), 24/08/2026.

---

## 1 · Selo da coluna Disponível — `StatusBadge`  **[DS]**

A tela **não desenha** o selo: chama `StatusBadge`. A definição manda.

**Forma** — `StatusBadge.jsx`, linhas 6484-6497, ramo **não-mono** (rótulo de texto):

| Propriedade | Valor medido |
|---|---|
| `border-radius` | **9999** — pílula totalmente redonda. (4px é só o ramo `mono`) |
| `padding` | `2px 10px` |
| `gap` | 5px |
| `font-size` | `var(--fs-2)` = **11,5px** |
| `font-weight` | **500** |
| `line-height` | 1.5 |
| `border` | 1px sólido no tom |
| Ponto | 6×6, `border-radius` 9999, `background: currentColor` |

**Tons** — linhas 6357-6373, família `fresc-*`, e `outline` na 6310:

| Estado da tela | Tom do DS | Fundo | Borda | Texto e ponto |
|---|---|---|---|---|
| Disponível | `fresc-hot` | `--color-success` a **16%** | a **30%** | `--color-success` |
| Abaixo do mínimo | `fresc-warm` | `--color-warning` a **16%** | a **30%** | `--color-warning` |
| Sem saldo | `fresc-cold` | `--color-destructive` a **16%** | a **30%** | **`oklch(0.74 0.14 18)`** |
| Não estocável | `outline` | transparente | `--color-border` | `--color-foreground`, **sem ponto** |

Duas coisas que a medição resolveu:

1. **16% / 30%, não 6% / 22%.** A revisão 1 afirmava ser "a mesma receita do guia para `Alert` e
   `StatusBadge`". Não é: o `StatusBadge` usa 16/30. Vale o componente.
2. **O texto claro de "Sem saldo" não é desvio — é do DS.** Em `fresc-cold` o próprio componente
   troca o tom cheio por `oklch(0.74 0.14 18)`, exatamente para o contraste que 11,5px exige.
   Usar `--color-destructive` cheio ali é que seria divergência.

*Confere se:* o selo é pílula redonda, 11,5px, e o texto de "Sem saldo" é o rosa claro do DS.

---

## 2 · Placa do ícone do KPI — `KpiFilterCard`  **[DS]**

Também existe no DS; também manda. `KpiFilterCard.jsx`, mapa `TONE` nas linhas 4866-4872.

| KPI | Fundo da placa | Glyph |
|---|---|---|
| Abaixo do mínimo | `oklch(0.72 0.15 70)` a **18%** | `oklch(0.80 0.13 70)` |
| Sem saldo | `oklch(0.65 0.20 20)` a **18%** | `oklch(0.78 0.16 20)` |
| Sem venda 90d | `oklch(0.60 0.18 295)` a **18%** | `oklch(0.80 0.14 295)` |
| Margem baixa | `oklch(0.72 0.15 70)` a **18%** | `oklch(0.80 0.13 70)` |

**A placa não tem borda** (linhas 4890-4899): 36×36, `border-radius` 8, `display:grid`,
`place-items:center`. A revisão 1 pedia borda a 22% — não existe.

**O card** (linhas 4873-4888): `background: --color-card`, `border: 1px solid --color-border`,
`border-radius` **8** (não 12), `padding` 12, `gap` 12, `box-shadow: 0 1px 2px rgba(0,0,0,.05)`.
**Selecionado:** borda `--color-primary` + `box-shadow: 0 0 0 1px var(--color-primary)` — anel de
1px cheio, **não** `ring-primary/40`.

**Rótulo:** `--fs-1`, 600, maiúsculas, `letter-spacing .06em`, `--color-muted-foreground`.
**Valor:** `--fs-6`, 600, `tabular-nums`, `--color-foreground`.

> **Tensão medida, para decisão sua — não resolver por conta.** O mapa `TONE` foi escrito para o
> cockpit escuro: os glyphs estão em lightness 0,78-0,80. Sobre a placa clara desta tela eles ficam
> pálidos. Aplicar o valor do DS como está é o correto agora; se quiser corrigir, o caminho é ADR
> nas fundações, não override na tela. **Não escurecer por conta própria.**

---

## 3 · Aba ativa — `TabBar`  **[DS]**  ·  e o token certo do acento

`TabBar.jsx`, linhas 6636-6672:

| Parte | Ativa | Inativa |
|---|---|---|
| Botão | altura 36, `padding 0 14px`, 13px / **600** em `--text` | 13px / 500 em `--text-dim` |
| Fundo | `--accent-soft` a **50%** | transparente |
| Sublinhado | **2px** no acento, `margin-bottom: -1px` | 2px transparente |
| Pílula do contador | acento **cheio**, texto `--accent-fg`, mono 10,5px / 600, raio 99, `min-width` 18, `padding 0 6px` | `--bg-2` / `--text-dim` |
| Hover (só inativa) | — | `--border-2` a 60%, texto `--text` |

**O acento é roxo, hue 295 — e o token importa.** `--accent` é reescrito em runtime pelo seletor
de matiz do shell (`AppShellV2.tsx`: `useState(accentHue)` → `localStorage` → `['--accent']`).
Portanto **usar `--color-primary`**, que nenhum código reescreve. Onde a tela precisar da versão
suave, derivar: `color-mix(in oklch, var(--color-primary) 12%, transparent)`.

*Confere se:* a aba em tela tem pílula roxa; mudar o seletor de matiz do shell **não** muda a cor
da aba desta tela.

---

## 3.1 · Moldura da tabela e dos blocos de estado  **[TPL]**

O `DataTable` do DS é um `<table>` nu — **sem borda e sem raio** (`DataTable.jsx` L2929-2936). Quem
dá a moldura é o contêiner, e o valor está no template de índice canônico
(`templates/pt-01-lista/Pt01Lista.dc.html`, slot 4):

```
border: 1px solid var(--border)
border-radius: var(--radius-lg)
box-shadow: 0 1px 2px rgba(0,0,0,.04)
background: var(--surface)
```

Mesma moldura nos três blocos que ocupam o lugar da tabela: tabela, `Skeleton` de carregamento e
`EmptyState`. Na tela de produtos o contêiner leva `overflow-x:auto` em vez de `overflow:hidden`,
porque aqui a rolagem horizontal é do wrapper e não interna ao componente — o raio continua
recortando.

*Confere se:* os cantos da tabela são arredondados e há uma sombra de 1px sob o bloco.

## 3.2 · Densidade — contrato de tokens do shell  **[TPL]**

Densidade no DS **não é prop de componente**: é um par de tokens escrito no elemento `.cockpit`,
mais duas regras CSS. Fonte: `templates/pt-01-lista` (`<style>` do `<helmet>` + `applyTheme`).

```css
.cockpit table td { padding-top: var(--d-td-y) !important; padding-bottom: var(--d-td-y) !important; }
.cockpit table th { padding-top: var(--d-th-y) !important; padding-bottom: var(--d-th-y) !important; }
```

| Token | compacto | confortável |
|---|---|---|
| `--d-td-y` / `--d-th-y` | 6px / 6px | 10px / 9px |
| `--d-fontsz` | 13px | 13,5px |
| `--d-cpad-x` / `--d-cpad-y` | 14px / 12px | 22px / 16px |
| `--d-tb-y` | 7px | 11px |
| `--fs-1..9` | 10 · 11 · 11,5 · 12,5 · 13,5 · 15,5 · 19 · 23 · 31 | 10,5 · 11,5 · 12,5 · 13,5 · 15 · 18 · 22 · 28 · 38 |

**Contrato fechado: seis tokens, não oito.** O PT-01 também escreve `--d-sidebar` (208/236) e
`--d-navpy` (5/7), e **esta tela não os escreve — nem deve.** Ninguém os consome: a sidebar é o
`AppSidebar` do DS, que crava `width: 260, flex: 'none'` (`AppSidebar.jsx` L1827-1830) e não tem prop
de largura. São resíduo de quando o template desenhava a própria sidebar. **Não acrescentar os dois**
— escrever token que ninguém lê é ruído que a próxima auditoria vai medir como divergência. Largura de
sidebar é do componente. A lista das seis linhas acima é **fechada**.

⚠ **`hint-size` não é largura.** O `hint-size="222px,100%"` que o PT-01 passa ao `AppSidebar` é
placeholder de streaming (o host é `display: contents`); o componente monta em 260px. Ler `hint-size`
como medida foi a origem de um diff errado — ver `README.md` §15.3, correção da #19.

Todo padding horizontal de slot da tela usa `var(--d-cpad-x)`, nunca px cravado — é isso que faz a
densidade valer para a página inteira e não só para a tabela.

**O colapso de conteúdo em modo denso** (esconder `unidade · categoria` e "N locais") é decisão de
produto **[TELA]**, separada e cumulativa: o padding vem dos tokens, a informação escondida é
escolha da tela.

*Confere se:* alternar "Linhas confortáveis" muda a altura das linhas **e** o padding lateral da
página, não só o conteúdo da célula.

## 4 · Linha da tabela  **[TELA]**

Sem equivalente no DS — decisões desta tela, contestáveis:

| Elemento | Valor | Por quê aqui |
|---|---|---|
| Margem sob o piso | número em `--color-destructive`, sem pílula | selo aqui competiria com o da coluna Disponível |
| Chip de observação comum | texto `--text-dim`, fundo transparente, borda `--border` | não é status; não deve parecer selo |
| Chip de observação crítica | texto e borda `destructive`, fundo a 16% (a mesma fração do `StatusBadge`) | consistência com o selo |
| Marcador "N de N com saldo" | com furo `--color-destructive`; sem furo `--text-mute` | — |
| "+N reservado" / "N locais" | `--text-mute`; "N locais" com borda inferior tracejada | — |

### O que NÃO está aqui, porque é [DS]

Três coisas que eu listava como decisão da tela e o `DataTable` já desenha
(`DataTable.jsx` L3009-3020) — **não reimplementar**:

| Elemento | Valor do componente |
|---|---|
| Trilho da linha urgente | `box-shadow: inset 3px 0 0 var(--color-destructive)` quando `state:'urgent'` |
| Linha selecionada | fundo `var(--accent-soft)` em cada `td`, via `state:'selected'` ou `selectedIds` |
| Hover de linha | fundo `var(--bg-2)` em cada `td` |
| Linha arquivada | `opacity .55` + `filter: saturate(.7)`, via `state:'archived'` |

Basta passar `state` na linha. Mesma regra para `columns: [{mono: true, align: 'right'}]` — o
componente aplica `font-mono` e `tabular-nums`; estilizar a célula à mão duplica.

---

## 5 · Tema escuro  **[DS]**

Os tons do `StatusBadge` e do `KpiFilterCard` **já resolvem os dois temas** — as frações (16/30 e
18) e os literais OKLCH são os mesmos, e os tokens têm override. **Não escrever regra de dark mode
para estes elementos.** Só os itens **[TELA]** da §4 precisam de conferência nos dois temas.

---

## 6 · Proibições — e o que elas NÃO proíbem

A regra é sobre **origem do valor**, não sobre a forma como ele está escrito.

| Ato | Permitido? |
|---|---|
| **Transcrever** literal OKLCH que está no componente do DS, copiado caractere por caractere | **Sim, obrigatório.** É citação. Não é "cor cravada" |
| **Inventar, calcular, derivar ou converter** um literal de cor | **Não.** É isso que a AP1 e esta seção proíbem |
| **Substituir** literal do DS por token que pareça equivalente | **Não.** Mesmo que o token seja "mais correto" — ver Regra zero item 3 |
| Cor nova, sem equivalente no DS, marcada **[TELA]** | Sim, via token ou `color-mix` sobre token, com justificativa |
| Token novo | **Não.** |

**Leia com atenção, porque a versão anterior desta seção se contradizia:** ela dizia "nenhum OKLCH
literal" na primeira linha e "copiar como estão" na segunda. Um agente leu a primeira metade,
concluiu que o literal do `fresc-cold` era proibido, e o substituiu por `--color-destructive`.
Foi **falha deste documento**, não dele. O literal transcrito do DS é a única coisa que vale ali.

**Não substituir componente do DS.** Onde o DS contradiz este documento, **o DS vence** — e o caso
vai para `contexto/pauta-design-system.md`.

---

## 7 · Por que a revisão 1 errou (para não repetir)

Dois erros de método, um só padrão: **inferi em vez de medir.**

1. **Contei o agregado, não o componente.** "9 usos de `--radius-md` no bundle" é sobre o bundle
   inteiro; o selo é uma linha do `StatusBadge`. A tela não desenha o selo — ela chama o componente,
   e a definição dele é a única fonte.
2. **Li runtime como se fosse regra.** `--accent` medido em produção estava em hue 220 e eu tratei
   como "a cor da empresa". É a preferência de quem usou aquele navegador, gravada em
   `localStorage`. Valor observado num navegador **nunca** é normativo: é [RUNTIME].

3. **Escrevi uma proibição que contradizia a própria citação.** O §6 dizia "nenhum OKLCH literal"
   e o §1 mandava transcrever os literais do `StatusBadge`. Regra contraditória não é regra: é
   convite a escolher. Um agente escolheu a metade errada e substituiu o literal do `fresc-cold`.

A regra que fecha a porta: **valor de cor, tamanho ou raio de um elemento que o DS renderiza não se
escreve — se cita, com arquivo e linha.** E a que fecha a segunda: **contradição interna deste
documento é defeito dele; ao encontrar uma, perguntar, nunca escolher um dos lados.**

---

## 8 · Caso resolvido — o `fg` de "Sem saldo" (e o contraste da família `fresc-*`)

**Pergunta recebida:** `fresc-cold` tem `fg: 'oklch(0.74 0.14 18)'` cravado, enquanto os irmãos usam
token (`--color-success`, `--color-warning`). Sobre fundo claro dá **1,94:1**. O §6 e a AP1 pareciam
proibir cor cravada. O agente aplicou `--color-destructive` no lugar e perguntou.

**Resposta — [DS], sem margem:** o literal **vale e é o valor a aplicar**. Transcrever o que está no
componente é citação, não cor cravada (§6). `StatusBadge.jsx` L6360, aplicar como está:

```
'fresc-cold': fg: oklch(0.74 0.14 18)
```

A substituição por `--color-destructive` está **revertida**. Ela foi feita por iniciativa própria a
partir de uma regra deste documento — exatamente o que a Regra zero item 3 proíbe.

**O achado é verdadeiro e vale muito — e continua sendo um achado, não uma licença para agir.**
A medição está registrada como defeito de origem do DS em
`contexto/pauta-design-system.md` → "Família `fresc-*` reprova contraste em tema claro":

| Estado | `fg` | Contraste sobre a placa clara | Régua |
|---|---|---|---|
| Disponível | `--color-success` | 2,86:1 | 4,5:1 |
| Abaixo do mínimo | `--color-warning` | 2,36:1 | 4,5:1 |
| Sem saldo | `oklch(0.74 0.14 18)` | 1,94:1 | 4,5:1 |

Os **três** reprovam, inclusive os que usam token. Ou seja: o problema **não é o literal** — é que
a família `fresc-*` inteira foi autorada para o cockpit escuro. Trocar um dos três por token não
resolveria nada e esconderia o defeito real atrás de uma correção cosmética.

**Enquanto a Wagner não decidir, a tela fica conforme o DS** — os três tons como o componente manda,
contraste reprovado e registrado. A decisão é dela e tem duas saídas possíveis, ambas por ADR nas
fundações, nunca por override nesta tela:

- **(a)** dar à família `fresc-*` um par claro/escuro no DS (corrige todas as telas de uma vez);
- **(b)** exceção declarada e assinada para esta tela, registrada na pauta.

**Não implementar (a) nem (b) sem a assinatura dela.**


---

## 15 · Sidebar em hue 295 — ADR UI-0028  **[DS-repo · exceção assinada]**

**Procedência.** [DS-repo] = transcrito de `resources/css/cockpit.css` L193-200 do `main`
(`wagnerra23/oimpresso.com`, árvore `f026223995a3`, lido em 01/09/2026), que por sua vez copiou os
valores de `prototipo-ui/cowork/styles.css` por decisão da **ADR UI-0028** (accepted 28/08/2026).
Não é [DS]: o pacote espelhado neste handoff (`design/_ds/…/colors_and_type.css` L308-315) ainda
serve **hue 240**. A divergência é **da origem** e a própria UI-0028 a assume como dívida
(*"O Design System passa a divergir de produção nos `--sb-*`"*), remetendo a ADR própria do DS.

**Exceção assinada.** A regra zero deste documento manda aplicar o DS e só perguntar. A troca abaixo
existe porque a **Maiara pediu explicitamente em 01/09/2026** ("aplicar essas modificações na tela").
Registrada em `pauta-design-system.md`. Sem esse pedido, o valor correto seria o do espelho (240).

**Está** (espelho do DS, `colors_and_type.css` L308-315, escopo `.cockpit`):

| token | valor |
|---|---|
| `--sb-bg` | `oklch(0.18 0.006 240)` |
| `--sb-bg-2` | `oklch(0.16 0.006 240)` |
| `--sb-border` | `oklch(0.28 0.008 240)` |
| `--sb-text` | `oklch(0.78 0.005 90)` |
| `--sb-text-dim` | `oklch(0.58 0.005 90)` |
| `--sb-text-hi` | `oklch(0.96 0.005 90)` |
| `--sb-hover` | `oklch(0.27 0.008 240)` |
| `--sb-active` | `oklch(0.32 0.008 240)` |

**Deve ficar** (8 valores transcritos, escopo `.cockpit`, nada convertido nem arredondado):

| token | valor |
|---|---|
| `--sb-bg` | `oklch(0.21 0.025 295)` |
| `--sb-bg-2` | `oklch(0.18 0.025 295)` |
| `--sb-border` | `oklch(0.30 0.03 295)` |
| `--sb-text` | `oklch(0.80 0.008 295)` |
| `--sb-text-dim` | `oklch(0.55 0 0)` |
| `--sb-text-hi` | `oklch(0.97 0.004 295)` |
| `--sb-hover` | `oklch(0.28 0.035 295)` |
| `--sb-active` | `oklch(0.34 0.05 295)` |

**Proibições explícitas.** Não acrescentar `--sb-scroll` nem `--sb-bullet-out`: a **D-3 da UI-0028**
os deixa em hue 240 de propósito, porque o protótipo não os declara — inventar valor para eles é
alucinar fonte, e o resíduo (scrollbar/bullet em 240 ao lado de um shell em 295) é **declarado**.
Não substituir o `AppSidebar` do DS nem redesenhá-lo: a tela só reescreve os 8 tokens que ele
consome. Não estender esta troca a `--accent*`, `--color-primary` ou aos tokens do app (`--bg`,
`--surface`, `--text`…) — a UI-0028 delimita a supersessão ao **prefixo `--sb-`**; o dark do app
segue na UI-0027. Não completar a lista com tokens de sidebar que não estejam acima.

**Como conferir.** No protótipo, com a sidebar visível:
`getComputedStyle(document.querySelector('.cockpit')).getPropertyValue('--sb-bg').trim()` devolve
`oklch(0.21 0.025 295)`, e `--sb-scroll` continua **não declarado** no escopo da tela.

**No repo (implementação).** Nada a fazer: `cockpit.css` L193-200 do `main` **já está** em 295. Este
item existe para o protótipo parar de mostrar 240 enquanto a produção mostra 295 — a direção do
conserto é o espelho do DS adotar o valor, nunca a produção voltar.

---

## 16 · Âmbar por vertical está revogado — ADR 0386  **[DS-repo · nada a mudar, proibição a manter]**

ADR 0386 (aceita 31/08/2026) revoga a decisão 4 da ADR 0244: **não existe `--accent` de cor própria
para vertical nenhuma**; `oklch(0.55 0.15 295)` é a única identidade de chrome (botão, foco, link,
estado ativo, primary). O gate da ADR 0263 (invariante `--accent*` em hue 250-330) passa a valer sem
ressalva de módulo.

**Estado desta tela: já conforme.** `aplicaTema()` escreve `--accent`, `--accent-2`, `--accent-soft`
e `--color-primary` a partir de `const hue = 295`, sem escopo por módulo. **Nada muda.**

**O que fica proibido:** criar seletor de escopo por vertical (`.oficina-scope`, `.grafica-scope`) que
redefina `--accent*`. **O que NÃO fica proibido:** os tons semânticos — status, `--origin-*`,
`--stage-*` — continuam variando de propósito; a 0386 os declara fora do seu alcance.

**Como conferir.** `grep -nE '\-\-accent[a-z0-9-]*\s*:' ` nos arquivos da tela não encontra matiz fora
da faixa 250-330.

---

## 17 · Precedência mudou: o protótipo é soberano na FORMA — ADR UI-0029  **[governança]**

Ratificada em 31/08/2026. Muda **como se resolve conflito**, não um valor:

- Divergência entre ADR UI e protótipo Cowork ⇒ **a ADR está errada**; corrige-se a ADR, não o
  protótipo. Divergência é **defeito, não pauta** — não se devolve "qual dos dois vale?".
- **Escopo: forma** — layout, hierarquia, espaçamento, cor, tipografia, ícone, rótulo, afordância,
  estado. **Não alcança** visibilidade (permissão, pacote por business, módulo instalado) nem dado:
  esses seguem com código/ADR, e **Tier 0 fica intocado** (`business_id`, append-only legal, regra de
  valor/estoque, LGPD).
- **Cadeia de precedência bifurcada.** Forma: `protótipo > teste > casos > charter > SPEC`.
  Comportamento/visibilidade/dado: a cadeia original, **sem** o protótipo. Teste que fixa forma
  antiga **perde** e se **reescreve** no mesmo PR — desabilitar o teste é fuga, e é o sinal de que
  alguém aplicou a regra errado.
- **Residual declarado pela própria ADR: a regra não é enforçável hoje** (o lado vivo não renderiza
  sem sessão logada; não existe gatilho "protótipo mudou ⇒ confere a tela"). Vale culturalmente,
  verificação manual e só no eixo estrutural (DOM). **Não criar gate** — comparador alimentado por
  protótipo sem DS produz divergência falsa.

**Como conferir.** Ao achar divergência de forma entre esta tela e o protótipo Cowork, o registro da
entrega cita UI-0029 e trata a ADR/teste divergente como perdedor, sem abrir pergunta.
