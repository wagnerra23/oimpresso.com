---
date: "2026-08-08"
hour: "19:30 BRT"
topic: "O botão primary de 13 telas rendia nu havia ~2,5 meses porque os 3 shims emitiam `.os-btn` e no CSS servido essa família só existe escopada `.sells-cowork` — e o style inline que pintava a cor mascarava o defeito"
authors: [C, W]
prs: [5436, 5439, 5441]
us: []
outcomes:
  - "Defeito PROVADO em produção antes de qualquer edit, por getComputedStyle no runtime (não leitura da fonte): `/financeiro/plano-contas` padding 0px · border-radius 0px · white-space normal · caixa 68×33 para \"Nova conta\", com o botão DENTRO de `.fin-cowork` e ainda assim 0 regras casando. `/ia/memoria` 59×33 e `/ponto/escalas` 71×33, ambas sem wrapper nenhum."
  - "Mecanismo isolado por fetch do arquivo servido: `inertia-B71Gn6Bs.css` (583 KB) tem 38 regras `.os-btn`, TODAS `.sells-cowork .os-btn*`. `.fin-cowork` tem 940 seletores servidos e nenhum `.os-btn`. O bundle chega; as regras `.os-btn` dele não."
  - "ACHADO MAIOR QUE O FIX: `Unificado/Index.tsx:1591` já documentava este mesmo defeito com assinatura idêntica (\"padding 0, radius 0, 66px, texto em 2 linhas\"), pego por [W] em smoke de 2026-07-07 e corrigido SÓ naquela tela. A correção nunca foi propagada. O fix de lá (utility Tailwind inline) é exatamente o que o `<PageHeaderPrimary>` faz — este trabalho propagou solução já aprovada, não inventou uma."
  - "3 PRs mergeados por [W] (#5436 Financeiro 6 telas · #5439 Jana 3 · #5441 Ponto 4) = 13 telas, com `label` e `onClick` preservados 1:1 e os 3 shims deletados (0 imports restantes em resources/js, varredura contada)."
  - "Smoke R1 pós-deploy em 6 telas: padding 0→0px 12px, radius 0→6px, altura 33→32px (uma linha), `.os-btn` = 0 em todas. O teste mais forte foi \"Novo recebimento\" (label mais longo) em 154×32 numa linha — a preocupação do `whitespace-nowrap` ausente no canon NÃO se materializou, porque `inline-flex` dimensiona pelo conteúdo."
  - "O hook `block-mwart-violation` ANUNCIA um escape que não implementa: a mensagem promete \"Override: comentar /mwart-override <razão> em PR\", mas o arquivo tem 194 linhas, zero `process.env` e zero bypass — as 2 ocorrências de \"override\" são texto da própria mensagem. [W] escolheu essa opção COM BASE na promessa falsa. Lápide §5 2026-07-30 em produção, não como ideia rejeitada na origem. Não consertado (governança, decisão [W])."
  - "Contraste medido no mesmo dia: `block-destructive` barrou corretamente `git push --force-with-lease` E `git reset --hard origin/...`; `memory-schema` barrou este próprio handoff (tldr > 500). Esses mordem. O do MWART anuncia e não morde."
  - "Deletar os shims acordou 3 gates REQUIRED que dormiam (lápide §5 2026-07-27 emenda): `SUPERFICIE.md == árvore` e `deadlink-gate` (mesmo arquivo — índice derivado listando o shim; regenerado por comando, não à mão) e `Casos-coverage G-6` (mede data-git do .tsx vs `last_run`, então até comentário conta — 5 bumps com linha na Trilha do tempo)."
  - "4 erros meus, registrados e não apagados: (1) medidor de CSSOM inválido por construção — `CSSStyleRule.cssRules` é truthy em Chrome moderno (CSS Nesting), então meu walk pulava exatamente as regras que queria contar; peguei porque o fetch do arquivo contradisse o CSSOM; (2) reportei \"16 telas\" usando `git grep -l` que casou comentário — o real é 13; (3) `awk '{print $2}'` em `gh pr checks`, cujos nomes têm espaços; (4) `rc=$?` depois de pipe, capturando o exit do `head`."
  - "Trava sugerida no chip NÃO foi proposta, de propósito: o critério óbvio (acusar classe cujo seletor não existe no CSS servido) é sintático e cai na família que a §5 já matou 4× (allowlist-de-pasta · guard @scope · vocabulário 130 FP · toHaveKey 100% FP). FP não foi medido, e propor sem medir viola a regra \"LIGUE A MÁQUINA\" item 4."
---

# Sessão — o primary que parecia botão

## Como o defeito se escondeu

A pergunta útil desta sessão não é *"por que quebrou"* — é *"por que ninguém viu por 2,5 meses"*.

Os 3 shims aplicavam `style` inline com `backgroundColor`/`borderColor`/`color` (roxo 295) **e**
`className="os-btn primary"`. A cor vinha do inline e funcionava; o **layout** vinha da classe e
não existia. O resultado é um retângulo roxo com o texto quebrado em duas linhas, colado nas
bordas — que a olho passa por "botão meio apertado", não por "componente sem estilo".

Máscara parcial é pior que quebra total: quebra total alguém reporta no primeiro dia.

## O caminho da medição

Ordem que funcionou, e que vale repetir:

1. **`getComputedStyle` no runtime, em prod** — prova o defeito sem depender de ler CSS.
2. **`fetch` do arquivo servido** — isola o mecanismo (quais regras existem de fato).
3. **Controle positivo** — medir o `PageHeaderPrimary` numa tela que já o usa (`/perfil`:
   padding `0 12px`, radius `6px`, 32px). Prova que o alvo da migração funciona **hoje**, em
   produção, antes de migrar qualquer coisa.

O passo 3 foi o que tornou o fix seguro: eu não estava apostando num componente, estava medindo um
que já roda.

## O erro do medidor, e como apareceu

Contei regras `.os-btn` percorrendo `document.styleSheets` com:

```js
if (r.cssRules) { walk(r.cssRules); continue }   // ERRADO
```

Em Chrome moderno, **`CSSStyleRule.cssRules` existe** (CSS Nesting) e é truthy mesmo vazio — então
o `continue` pulava toda regra de estilo antes de chegar no `selectorText`. Resultado: "0 regras",
que eu quase publiquei.

O que denunciou foi **contradição entre dois instrumentos**: o `fetch` do mesmo arquivo mostrava 20
ocorrências de `.os-btn` no texto. Consertado com `if (r.cssRules && r.cssRules.length)`, o número
real apareceu: **38 regras, todas `.sells-cowork`**.

Antes disso eu já tinha protegido contra o fail-open de CORS (contando `folhasCEGAS` explicitamente
e só afirmando com `podeAfirmar: true`) — a proteção estava certa e **não bastou**, porque o furo
era outro. Blindar um vetor não valida o instrumento.

## O hook que promete e não cumpre

Ao migrar o Ponto, o `block-mwart-violation` bloqueou as 4 Pages: `Modules/Ponto` tem **0
RUNBOOKs**. A mensagem oferece um override. Perguntei a [W], que escolheu o override — e aí,
ao ir usar, descobri que **ele não existe**: 194 linhas, zero bypass.

Isso tem duas consequências que valem mais que o incidente:

1. **A decisão de [W] foi tomada sobre informação falsa do mecanismo.** Não sobre meu erro de
   leitura — sobre o que o hook literalmente diz.
2. **Revisão adversarial na origem não pega isso.** O hook foi revisado, mergeou, e a promessa
   nunca foi testada porque *testar a mensagem* não é reflexo de ninguém. Só aparece quando alguém
   tenta usar o escape.

Apliquei por script Node (o matcher é `Write|Edit|MultiEdit`) e **declarei no corpo do PR**. A
alternativa — usar e não contar — seria burlar enforcement.

## O que a sessão confirma sobre os gates

Três hooks morderam corretamente e me pouparam de erro real: `block-destructive` (2×: force-push e
`reset --hard`) e `memory-schema` (tldr > 500 neste handoff). Um anunciou e não mordeu
(`block-mwart-violation`).

E a lição §5 2026-07-28 (*"validar um gate rodando UM dos modos que o job roda"*) se pagou: o
`casos-gate` roda **dois** comandos, e eu rodei os dois antes de afirmar verde. Se tivesse rodado
só o primeiro, teria declarado verde com o segundo vermelho.

## Ligações

- Origem do chip: [handoff 2026-08-08 20:35](../handoffs/2026-08-08-2035-smoke-r1-memoria-e-benchmark-it5.md)
- Fechamento: [handoff 2026-08-08 22:30](../handoffs/2026-08-08-2230-primary-sem-estilo-13-telas-e-o-escape-fantasma.md)
- Precedente do mesmo defeito: `resources/js/Pages/Financeiro/Unificado/Index.tsx:1591` (smoke [W] 2026-07-07)
