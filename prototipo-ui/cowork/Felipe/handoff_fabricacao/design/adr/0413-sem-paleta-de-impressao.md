# ADR 0413 — O cockpit não publica paleta de impressão

**Data:** 2026-09-01 · **Status:** proposto (aguarda decisão) · **Camada:** 1 · Fundações
**Origem:** folha de prova PT-07 da família Fabricação (ficha técnica de produção)

## Contexto

A ficha técnica é o entregável físico desta família: sai na bancada, é assinada e volta. São duas
variantes — com custo (orçamento) e via de produção (sem valor de compra).

Papel não tem tema. Os tokens do `.cockpit` são todos de tela e trocam de valor por
`[data-theme]`: usar `var(--text)` numa folha impressa entrega tinta clara quando o usuário está no
tema escuro, e `var(--surface)` pinta o fundo de cinza — no papel isso é toner gasto e leitura
pior.

Medido em `04-modulos/manufacturing/css/manufacturing.css`, bloco `@media print` (linhas 231-264):
**11 valores literais de cinza**, todos escolhidos como densidade de tinta, não como cor de marca:

| Valor | Papel na folha |
|---|---|
| `#fff` | fundo do papel |
| `#111` | texto, marcas de corte, mira de registro, régua das cotas, linha de assinatura |
| `#000` | realce da cota destacada (mais denso que o texto) |
| `#555` | texto secundário, rodapé |
| `#666` | eyebrow, `.dim`, observação |
| `#777` | rótulo de cota, carimbo, `.eq` |
| `#999` | borda inferior da faixa de grupo |
| `#ccc` | divisor de linha da tabela |
| `#bbb` | borda superior do rodapé da folha |
| `#ddd` | contorno da escada de cinza |
| `#f0eeeb` | fundo da faixa de grupo |

Além disso, quatro valores que **não são cinza e não são cor de tema**: `#00AEEF` `#EC008C`
`#FFF200` `#231F20` na tira do rodapé — as quatro **tintas de processo** (ciano, magenta, amarelo,
preto). São o mesmo conjunto do primitivo `ProofStrip kind="cmyk"` do DS, e existem na folha como
controle de prova: se a tira sai lavada, a impressora está descalibrada e o resto da folha também
não é confiável.

O guia do DS classifica cor crua em arquivo de módulo como anti-padrão **AP1**. Nenhuma das 15
ocorrências acima pode ser trocada por token, porque o token que descreveria "tinta preta 100% no
papel" não existe.

## Decisão

**Na tela (aplicado, declarado):** o bloco `@media print` usa cinzas literais e as quatro tintas de
processo. Está isolado no fim do arquivo de módulo, com comentário, e **não vaza para a tela** — as
regras só existem dentro de `@media print`.

**No DS (proposto):** publicar um conjunto mínimo de tokens de papel, aplicáveis dentro de
`@media print`, na linha do grupo *Print-craft* que o DS já tem (`ProofFrame`, `Dimension`,
`ProofStrip`, `RegistrationMark`). Valores e nomes são decisão do DS; **não inventar aqui**. O que
esta folha consumiria: papel, tinta cheia, tinta de texto secundário, tinta de rótulo, divisor
fino, fundo de faixa, e as 4 tintas de processo.

## Consequências

- **Se aprovado:** a folha PT-07 desta família e a do módulo Oficina (que já imprime OS) passam a
  falar a mesma língua, e AP1 volta a ser verificável varrendo o CSS inteiro sem exceção.
- **Se não aprovado:** cada módulo que imprime repete a sua escala de cinza — hoje são dois
  (Manufacturing e Oficina). No terceiro, isto deixa de ser exceção e passa a ser padrão não
  escrito.
- Enquanto não houver decisão, **toda folha nova declara suas cores cruas** no LAUDO da tela, como
  esta.

## Referências

- `04-modulos/manufacturing/css/manufacturing.css` L231-264
- `manufacturing-print.jsx` (tira CMYK no rodapé da folha)
- Guia do DS · grupo *Print-craft* e anti-padrão AP1
- `contexto/pauta-design-system.md`
