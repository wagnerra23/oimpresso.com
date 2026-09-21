# Como escrever um handoff que um agente de código executa sem errar

Complemento do `MANUAL-HANDOFF-DE-DESIGN` (método Oimpresso). O manual ensina a montar o pacote
para **um dev humano**. Este documento cobre o caso em que quem implementa é um **agente de código**
(Claude Code e afins), que falha de um modo diferente e previsível.

**A diferença que gera todo erro:** um humano em dúvida pergunta; um agente em dúvida **preenche**.
Ele varre o codebase, acha o mais parecido e segue. Toda lacuna do documento vira código plausível
e errado. Portanto: **num handoff para agente, omissão não é neutra — é uma instrução involuntária.**

Cada lei abaixo nasceu de um erro real, nomeado ao lado. Nenhuma é estilística.

---

## Lei 1 · Valor em número, nunca nome de interface

Nome de componente ou de prop descreve **intenção**. Só o número é **contrato**.

| ❌ Não escrever | ✅ Escrever |
|---|---|
| `tone="amber"` no `KpiFilterCard` | fundo `amber-500` a 6% · borda a 22% · glyph `amber-600` |
| "espaçamento confortável" | 12px entre células, 9px no grid de KPI |
| "selo tintado" | fundo 6% · borda 22% · texto 700 · ponto 6px |
| "animação suave" | 180ms, ease padrão |

Nome de prop do protótipo **não existe** no codebase alvo. O agente lê `tone="amber"`, não encontra
onde passar, pinta o que consegue (o glyph) e para.

**Incidente:** placas de ícone dos KPI ficaram brancas com glyph âmbar. O handoff dizia a prop; não
dizia a fração.

**Cláusula de precedência, obrigatória no pacote:** *onde um nome de prop e um valor numérico
descreverem a mesma coisa, o número vence.*

---

## Lei 2 · Toda lista é FECHADA ou declarada ILUSTRATIVA

Nenhuma lista sem rótulo. As duas palavras são de uso literal:

- **Lista fechada** — é o conteúdo inteiro. "Não acrescentar itens encontrados no codebase."
- **Lista ilustrativa** — exemplos; o agente pode estender e deve dizer o que estendeu.

**Incidente:** o menu `⋯` recebeu um grupo "Outras visões" com quatro telas do legado. O handoff
mostrava o `⋯` no diagrama e nunca listou o conteúdo. Não foi invenção: foi preenchimento de lacuna.

---

## Lei 3 · Todo elemento do diagrama tem entrada normativa

Se aparece na ASCII art, no screenshot ou no protótipo e **não** tem seção que o descreva, o agente
inventa aquele pedaço.

**Conferência antes de fechar o pacote:** varra o próprio diagrama elemento por elemento e marque
qual seção governa cada um. Elemento sem seção é buraco — feche ou apague o elemento do diagrama.

---

## Lei 4 · Proibição explícita, sempre

Para um agente, **regra ausente não é proibição**. Escreva as três negativas onde importam:

- "**não acrescentar**" (listas, menus, colunas, campos)
- "**não substituir**" (componente do DS — contornar na tela ou registrar proposta)
- "**não completar**" (com o que existir no codebase)

Toda seção que enumera algo termina com uma delas.

**E toda proibição diz o que ela NÃO proíbe** — ver Lei 15. Proibição sem esse recorte é lida no
sentido mais largo e apaga algo que outra seção manda fazer.

---

## Lei 5 · Derivado ou digitado, marcado item a item

Todo número exibido é uma das duas coisas, e o documento diz qual:

- **derivado** → dar a fórmula (`montáveis = mín(saldo do componente ÷ qtd por kit)`)
- **digitado** → dar o campo de origem

Sem a marca, o agente liga no campo de cadastro de nome parecido.

**Incidente:** o marcador de grade saiu como o texto do cadastro ("Tamnha p-m-g (4)") em vez do
derivado "4 de 6 com saldo".

---

## Lei 6 · Declarar o escopo do espelho

O protótipo roda sobre um espelho do DS, não sobre o DS do produto. Onde os dois divergem, **o
documento traduz** — tabela de três colunas: elemento · nome no alvo · o que o protótipo desenhou
e por quê.

**Incidente:** o bundle espelhado tem 22 glyphs; o produto usa Lucide inteiro. O protótipo desenhava
fallbacks (hambúrguer no `⋯`, sino em "abaixo do mínimo", cadeado em "sem saldo") sem dizer que
eram fallbacks. Cinco ícones divergiram.

**Corolário:** o protótipo **não é** referência de pixel para nada que ele contornou. Diga o que
ele contornou.

---

## Lei 7 · Critério de aceite verificável, por requisito

Requisito sem teste é opinião. Cada um leva uma linha que se roda na tela pronta:

```
Trilho da linha       → abrir /products/unificado: toda linha "Sem saldo" tem barra
                        vermelha de 3px na borda esquerda. Zero linha normal com barra.
Placa do KPI          → inspecionar a placa de "Sem saldo": fundo rose a 6%, não branco.
Menu ⋯                → abrir: exatamente 2 grupos e 7 itens. Nenhum link de navegação.
```

É isso que permite o agente **se autoconferir** antes de entregar — e permite você reprovar em
segundos, sem discussão.

---

## Lei 8 · Uma fonte por fato

O mesmo número em dois lugares do documento divergirá na primeira revisão. Escreva-o uma vez e
referencie a seção. Vale para o documento tanto quanto para a tela.

---

## Lei 9 · Precedência declarada dentro do pacote

Escreva a ordem, no topo: **README > protótipo > auditorias**. O protótipo ilustra; o README
governa; auditoria é medição datada e não é especificação. Sem essa linha, o agente copia pixel de
um protótipo que tinha contorno conhecido.

---

## Lei 10 · Tela que já existe recebe diff, não descrição

Quando a implementação já está de pé, descrever o alvo faz o agente reescrever o que estava certo.
Entregue tabela de três colunas: **está assim · deve ficar · como conferir**. Uma linha por
divergência, com a causa no handoff anotada — é o que impede o mesmo erro na próxima tela.

---

## Lei 11 · Valor de componente do DS não se escreve — se cita

Se o elemento é renderizado por um componente do design system, **a definição dele é a única
fonte**. O documento transcreve, com **arquivo e linha**, e não parafraseia.

| ❌ | ✅ |
|---|---|
| "selo tintado, fundo 6%, borda 22%" | "`StatusBadge.jsx` L6357: fundo 16%, borda 30%" |
| "raio de 6px, como o resto do DS" | "`StatusBadge.jsx` L6486: `c.mono ? 4px : 9999` → pílula" |

**Incidente:** escrevi 6/22/600, `rounded-xl` e `ring-primary/40` inferindo da prosa do guia
("tinta 5–10%"). Medido: o `StatusBadge` usa 16/30 e pílula 9999; o `KpiFilterCard` usa 18% **sem
borda**, card com raio 8 e anel de 1px cheio. Sete valores errados num documento de uma página —
e a implementação obedeceu, porque o documento tinha número, que é o que a Lei 1 pede. **Número
inventado é pior que nome de prop:** parece medição.

**Corolário — ler o docblock, não só o código.** Afirmei que o DS não tinha chip de anotação neutro
e abri proposta. O docblock do `TagChip` dizia, na terceira linha: *"Unknown tags fall back to
neutral."* Eu havia lido a tabela `TAG_HUE` e parado ali. O comentário do componente existe para
declarar o comportamento que a estrutura de dados não mostra — **é fonte, não decoração**. Segunda
proposta falsa da mesma sessão: as duas nasceram de parar de ler cedo.

**Corolário — não contar agregado.** "9 usos de `--radius-md` no bundle" não diz nada sobre o
selo. Medir **o componente que renderiza aquele elemento**, nunca a frequência no repositório.

## Lei 12 · Valor observado em runtime nunca é normativo

Marcar a procedência de cada valor: **[DS]** citado, **[TELA]** decidido aqui e justificado,
**[RUNTIME]** observado num navegador. O terceiro **não vira regra**, em nenhuma hipótese.

**Incidente:** medi `--accent: oklch(0.55 0.15 220)` na tela em produção e tratei como "a cor da
empresa". Era o seletor de matiz do shell gravado em `localStorage` — preferência de quem usou
aquele navegador. Passei por cima de três fontes que diziam roxo 295 (README §10, bundle, ADR 0401)
e ainda mudei sublinhado e pílula, que estavam certos, para acompanhar o azul.

**Corolário — token reescrito em runtime não serve de contrato.** `--accent` é sobrescrito pelo
shell; `--color-primary` não. Onde um token é mutável, o documento diz qual usar **e por quê**.

## Lei 13 · Tensão medida vira decisão declarada, não correção silenciosa

Quando o valor do DS não serve ao contexto, o documento **declara a tensão com a medição** e diz o
caminho (ADR nas fundações), em vez de escrever o valor "corrigido". Correção silenciosa dentro da
tela é divergência que ninguém audita.

Exemplo real: o mapa `TONE` do `KpiFilterCard` tem glyphs em lightness 0,78-0,80 — escritos para
o cockpit escuro, pálidos sobre placa clara. O certo é aplicar o valor do DS **e** registrar a
tensão na pauta, não escurecer na tela.

## Lei 14 · Divergência autoriza pergunta, nunca ação

O agente encontra uma contradição e **age**: escolhe o lado que lhe parece mais defensável, entrega,
e menciona no relatório. Parece prudente. É o pior resultado possível, porque a tela sai divergente
**com justificativa** — e justificativa é o que impede alguém de reabrir o caso.

Escrever o protocolo, literal, em todo pacote:

&gt; Ao encontrar divergência, contradição ou valor que pareça errado: **aplicar o valor do DS como
&gt; está**, relatar a tensão com o número medido, e **não alterar nada** até a decisão vir. Exceção
&gt; existe só assinada e registrada. Não há exceção implícita — nem por acessibilidade, nem por
&gt; urgência, nem por outra regra do próprio documento.

**Incidente:** `fresc-cold` traz `fg: oklch(0.74 0.14 18)` cravado; sobre fundo claro dá 1,94:1. O
agente leu a proibição de "cor cravada", substituiu por `--color-destructive` e avisou. A medição
dele estava certa e era valiosa — os **três** tons da família reprovam (2,86 / 2,36 / 1,94:1), o
que revela que `fresc-*` foi autorada para o cockpit escuro. Mas a ação transformou um defeito de
DS auditável em uma divergência de tela justificada.

**A hierarquia, escrita sem grau:** o DS ganha deste documento, do protótipo, de auditoria, de régua
de acessibilidade e de bom senso. Sempre. Quem flexibiliza é a Wagner, por escrito.

## Lei 15 · Contradição interna é defeito seu, e o agente vai resolvê-la contra você

Duas regras do mesmo documento que se anulam **não** produzem uma pergunta: produzem uma escolha
silenciosa. O agente adota a que estiver mais perto do problema dele e segue.

**Incidente:** o §6 do patch dizia "nenhum OKLCH literal escrito por você" e o §1 mandava
transcrever os literais do `StatusBadge`. As duas eram verdadeiras na minha cabeça — a primeira
proíbe **inventar**, a segunda obriga **citar** — e eu nunca escrevi essa distinção. O agente leu
"nenhum literal" e apagou uma citação.

**Como fechar:** toda proibição diz o que **não** proíbe. Quando a regra é sobre *origem* do valor
e não sobre sua *forma*, a tabela permitido/proibido é obrigatória: transcrever é citação;
inventar, calcular, converter e substituir são proibidos. E antes de fechar o pacote, ler cada
proibição perguntando "isto pode ser lido como proibindo algo que eu mando fazer em outra seção?".

## Lei 16 · Componente não é a única fonte do DS — o template canônico também é

A Lei 11 diz "meça o componente que renderiza o elemento". Fica implícito nela que **se nenhum
componente renderiza o elemento, a decisão é sua** — e isso é falso. O DS decide também por
**template**: as telas de referência (`templates/pt-01-lista`, `pt-05-dashboard`, …) fixam o que
está entre os componentes — contêiner, moldura, sombra, gap da grade, ordem dos slots.

**Antes de marcar um valor como [TELA], procurar o template do mesmo tipo de tela.** Só é [TELA]
o que não existe nem em componente nem em template.

**Incidente:** envolvi a tabela em `border:1px solid var(--border)` sem raio e sem sombra, e
registrei como decisão da tela. O template PT-01 — a tela de índice canônica, o mesmo tipo de tela —
já dizia `border-radius: var(--radius-lg)` + `box-shadow: 0 1px 2px rgba(0,0,0,.04)` +
`background: var(--surface)`. Não havia decisão a tomar: havia um template que eu não abri. A
usuária viu a diferença na galeria do DS antes de mim.

**Marca de procedência nova:** **[TPL]** — vindo de template canônico, com o caminho do arquivo.
Vale tanto quanto [DS]; ganha de [TELA] sempre.

**Corolário 1 — o template decide MECANISMO, não só valor.** Procurei densidade de tabela no
`DataTable`, não achei prop, e propus uma ao DS como P1. O PT-01 já resolvia: densidade ali é um
**contrato de tokens no shell** (`--d-td-y`, `--d-th-y`, `--d-cpad-x`, `--d-tb-y`, `--d-sidebar` e a
rampa `--fs-1..9` inteira), aplicado por `!important` em `.cockpit table td/th`. Cobre muito mais
que a prop que eu pedi. **Propor ao DS algo que ele já tem é pior que não propor:** gasta a
credibilidade da pauta e some no meio das propostas legítimas.

**Corolário 2 — "o componente não tem" não é conclusão; é meio caminho.** A sequência obrigatória
antes de escrever [TELA] ou abrir proposta na pauta: **(1)** o componente que renderiza o elemento;
**(2)** o template canônico do mesmo tipo de tela — markup, `<style>` do `<helmet>` **e** a lógica
que escreve tokens; **(3)** o guia do DS. Só depois dos três é decisão da tela.

**Corolário 4 — `hint-size` não é medida, e token escrito não é token vigente.** Publiquei "sidebar
248px → 222px" como diff. O 222 veio do `hint-size="222px,100%"` do PT-01 — que é **placeholder de
streaming**, não largura: o host é `display: contents` e o `AppSidebar` crava `width: 260`
(`AppSidebar.jsx` L1827-1830). Medido: 260px. Duas regras derivadas:

- **Ler do lugar que renderiza.** `hint-size`, `defaultValue`, `placeholder` e nome de prop descrevem
  intenção; só o componente montado (ou o componente-fonte) descreve o resultado.
- **Token escrito ≠ token consumido.** O PT-01 escreve `--d-sidebar` e `--d-navpy` e **ninguém os lê**
  — resíduo de quando o template desenhava a própria sidebar. Publicar um deles como contrato entrega
  um número que nada aplica. Antes de citar um token, achar **quem o consome**.

**Corolário 3 — usar as props que o componente já tem.** Estilizar a célula à mão o que
`columns: [{mono: true, align: 'right'}]` faz é reimplementar componente por dentro. Ler a
assinatura antes de estilizar.

## O bloco §0 — colar no topo de todo handoff

```markdown
## 0 · Contrato de leitura (para quem implementa, humano ou agente)

1. **Precedência, sem grau:** **design system > README > protótipo > auditorias.** O DS é a
   autoridade final; o protótipo ilustra; este documento governa o que o DS não decide; auditoria é
   medição datada, não especificação.
2. **Número vence nome.** Nome de componente ou prop citado aqui é do protótipo e descreve
   intenção. Onde houver valor numérico para a mesma coisa, o número é o contrato.
3. **Listas.** "Lista fechada" = conteúdo inteiro, não acrescentar. Sem essa marca, a lista é
   ilustrativa — e o que você acrescentar deve ser relatado na entrega.
4. **Não substituir componente do design system.** Contornar na tela, ou registrar proposta na
   pauta do DS. Recriar componente localmente está descartado.
5. **O design system sempre ganha** — deste documento, do protótipo, das auditorias, da régua de
   acessibilidade, do bom senso. Não pressupor, não assumir, não inferir: valor sem citação de
   arquivo e linha não está decidido.
6. **Divergência autoriza pergunta, nunca ação.** Ao encontrar contradição ou valor que pareça
   errado: aplique o valor do DS como está, relate a tensão com o número medido, e **não altere
   nada** até a decisão vir. Exceção só assinada e registrada. Não corrigir por conta própria —
   nem em nome de acessibilidade, nem de coerência, nem de outra regra deste documento.
7. **Em dúvida, pergunte — não preencha.** Lacuna deste documento é defeito do documento.
   Relate a lacuna junto com a entrega; não a resolva por inferência do codebase.
8. **Entregue com o critério de aceite conferido** (§<n>), item por item.
```

---

## Fechar o pacote — as oito perguntas

Antes de zipar, responda por escrito:

1. Toda cor, medida e duração está em **número**, e todo número de elemento do DS está **citado
   com arquivo e linha** — nenhum inferido de prosa?
2. Toda lista está marcada **fechada** ou **ilustrativa**?
3. Todo elemento do diagrama tem **seção** que o governa?
3b. Todo valor **[TELA]** foi conferido contra o **template canônico** do mesmo tipo de tela — e é
   [TELA] mesmo, não [TPL] não-lido?
4. Todo número exibido está marcado **derivado** (com fórmula) ou **digitado** (com campo)?
5. Todo contorno do protótipo está **declarado** como contorno, e todo valor está marcado
   **[DS] / [TELA] / [RUNTIME]** — nenhum valor de runtime tratado como regra?
6. Todo requisito tem **teste de aceite** de uma linha?
7. Toda proibição diz **o que ela não proíbe**, e nenhuma contradiz outra seção?
8. O **protocolo de divergência** está escrito (aplicar o DS · relatar · não alterar)?

Um "não" é um erro de implementação já contratado.
