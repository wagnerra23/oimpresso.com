---
sessao: "_saida-06"
thread: "06 · Widget nasce h3 e o ícone warn sai anônimo — pedido no PRIMITIVO"
dono: "[CL]"
data: 2026-09-28
prefixo_tocado: resources/js/Components/ui/card.tsx · tests/js/card-anatomia.test.tsx · governance/design/component-registry.json
base_lida: wagnerra23/oimpresso.com@main (o card.tsx tinha 4.002 B — já com badge/note/flush da thread 01; a leitura registrada na thread, 1.987 B @733033864088, é anterior a ela)
---
# _saida-06

> ⚠️ **Esta thread NÃO está no bloco json do `00-INDICE.md`.** Não tem `prefixo`, `nao_toca` nem
> `provas` declarados, então o placar não a enxerga. Foi executada tomando o próprio
> `06-widget-nivel-titulo.md` como contrato (achados, alvo, guarda e "o que NÃO fazer"). Se o
> Cowork quiser que ela conte no placar, a entrada precisa nascer lá — o índice é do Cowork e
> não se edita no espelho.

## 1 · Achado 1 (nível de título) — FEITO, e o `main` era diferente do protótipo

- **Medido no `main`:** o `CardTitle` não emite `h3`, como o `Widget` do bundle — emite um
  **`<div>`**. O título do painel nem entra no esqueleto de cabeçalhos da página. O pedido vale
  do mesmo jeito e na forma que ele propõe.
- **Feito:** `CardTitle` ganha `as?: "h2" | "h3"`. Omitido = `<div>` de sempre (a guarda de
  default que já existia em `card-anatomia.test.tsx` segue exigindo o markup de 733033864088 e
  passa sem alteração). `h2` = painel sob o `h1` do PageHeader; `h3` = painel dentro de painel.
- **Testes** (estendi a guarda existente, que tem gate próprio, em vez de criar outra): `as=h2` e
  `as=h3` viram cabeçalho do nível certo, com as mesmas classes e `data-slot`; sem `as` não há
  cabeçalho; com `badge`, a contagem fica dentro do cabeçalho. **17 passed** (13 + 4).
- **Bite-test, nos dois sentidos:** default trocado para `h2` → 2 vermelhos (a guarda); `as`
  ignorado → 3 vermelhos (os testes novos). Restaurado com hash conferido.
- **Visual — medido com o CSS servido em produção**, dentro de `.cockpit`, light e dark: `div`,
  `h2` e `h3` saem **idênticos** (13,5px · 600 · margem 0 · mesma largura e altura). O reset do
  Tailwind neutraliza o cabeçalho.
  - ⚠️ A 1ª leitura deu `h3` com 27px de altura. Era **defeito da sonda**: o `.cockpit` é o grid
    do shell (`260px 340px 0px`) e cada card caiu numa coluna; o do `h3` ficou com 48px. Com os
    cards num bloco comum, os três empatam.
- **Cuidado para quem adotar `as="h3"`:** três regras globais escopadas mudam o `h3` se a tela o
  puser dentro delas — `.cockpit .vw-card h3` (uppercase, 700), `.sells-cowork .vc-card-h h3` e
  `.fin-cowork h1,h2,h3 { text-wrap: balance }`. Só age em opt-in; nenhuma tela passa `as` hoje.

## 2 · Achado 2 (ícone `warn` anônimo) — NÃO FEITO: não há dono-primitivo no `main`

A thread mandava achar o dono por busca antes de consertar. Achado:

- O traçado do ícone (`M12 9v4M12 17h.01M10.3 3.9…`) está em `_ds_bundle.js:730`, no
  `ALERT_TONES.warn` do **`Alert` do DS do Cowork** — é fonte do lado de lá.
- No `main`, esse traçado **não existe** (0 arquivos; controle positivo: `lucide-react` casa em 18
  arquivos de `ui/`+`shared/`, então a busca funciona). O `ui/alert.tsx` de produção **não emite
  ícone**: o ícone vem do consumidor, como filho (25 consumidores de `ui/alert`).
- **Medido:** o `lucide-react` 0.460 **não** põe `aria-hidden` no `<svg>` por padrão. Logo o
  defeito **pode** existir em produção, por consumidor e não por primitivo. **Não medi** quantos
  dos 25 consumidores passam ícone `lucide` puro e quantos passam por um wrapper que já marca
  `aria-hidden` — é a contagem que falta antes de qualquer conserto. Consertar é trabalho tela a
  tela (ou uma decisão de marcar `aria-hidden` no `Alert` para o `svg` filho), fora do que esta
  thread autorizou. Fica para decisão [W].
- O conserto do lado do protótipo (`aria-hidden` no ícone do `Alert` do DS) é do Cowork.

## 3 · Família (a thread pede abrir as 3 juntas)

As outras duas pendências citadas — cor crua no `Avatar` dentro de `<h1>` e `TabBar` sem
`role="tab"` — não foram tocadas: não têm arquivo `NN-*.md` próprio nesta pasta nem no índice.

## 4 · Placar

**entregue 1 de 2 achados (nível de título no `CardTitle`) · ausente o ícone `warn`, porque no
`main` não existe primitivo que o emita — o defeito equivalente é por consumidor, decisão [W].**
