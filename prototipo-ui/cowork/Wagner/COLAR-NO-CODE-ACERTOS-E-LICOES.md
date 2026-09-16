# ACERTOS E LIÇÕES — o que a produção acertou, e o que eu errei ao supor o contrário

> **Por que este arquivo existe** (pedido [W] 2026-09-09): *"deveria ir acrescentando e informando pro Code o que ele acertou do que você já escreveu, e as novas memórias — isso mantém o Code para não errar novamente."*
> O sistema já catalogava **erro** (`memory/LICOES_CC.md`), **ausência** (placar) e **proibição** (`memory/proibicoes.md`). Faltava o positivo: **o que já está certo no `main` e não se refaz, não se re-pergunta e não se "melhora"**. Sem isso, cada ciclo redescobre a mesma coisa — e às vezes redescobre errado.
> **Estatuto:** é **ponte**, não memória. Destino no `main`: `prototipo-ui/design-docs/`. As lições daqui são **propostas** para `memory/LICOES_CC.md` — a numeração `L-NN` é do [CL] no merge, eu não invento número.
> **Forma:** um bloco por ciclo, **mais novo em cima**. Acrescenta-se; não se reescreve o passado (o erro registrado é o valor).
> **Leitura:** entra no read-order **junto** com `LICOES_CC.md` — o pre-flight injeta erro catalogado; este injeta **acerto** catalogado.

---

## Ciclo 2026-09-14 · Transporte (zip), âncora do Ponto e o `<main>` que era MEU (árvores `73182439581f` → `420b061817e0` → `32af4af112a4`)

### ✅ O que a produção já acertou (não refazer · não re-perguntar · não regredir)

| # | acerto, medido no `main` | onde | consequência prática |
|---|---|---|---|
| A9 | **O `<main>` do AP9 EXISTE em produção** — `<main className="main-body">` no shell | `resources/js/Layouts/AppShellV2.tsx:755` (lido 2026-09-14) | **o pedido não tem objeto.** Eu declarei *"0 `<main>` no documento → host, sem dono"* em **8+ pacotes** (relatórios · repair · estoque 1.1 · estoque 1.2 · visão-geral · CRM · jana · o `handoff-sidebar`): o host defeituoso era **o meu** (`app.jsx:940`, `<div className="main">`), não o shell do `main`. **Parar de emitir essa linha como dívida de produção** |
| A10 | **O `map.json` do Ponto existe, e é por REGIÃO** — 3 pares gap+map (`dashboard-index`, `espelho-index`, `espelho-show`), `gerado_em: 2026-09-14`, com `prototipo_sha` por **conteúdo** (ADR 0324) e `vivo.ancora` = `data-contract` | `memory/requisitos/Ponto/*.map.json` | é o mecanismo que a decisão `D-SIMBOLO` estava inventando. **Não pedir contrato por tela para essas 3** — ler o map. As 4 seções do painel estão em **paridade** medida |
| A11 | **O `_doc` do map deconflita os 3 eixos** (tela por região × componente/`component-registry.json` × charter) e declara que **range de linha do lado vivo é informativo/frágil** | `dashboard-index.map.json` `_doc` | âncora verificável é `data-contract` no `.tsx`, não número de linha. Eu tratava o charter como se fosse os três eixos |
| A12 | **O que trava o Ponto está declarado como decisão de [W], não como falta de âncora** — 3 partes `decidir-w` (escala tipográfica · header/ações · gráfico 7 dias + painel de atenção) e o próprio map diz *"produção evoluiu além da âncora: ou a âncora incorpora, ou eles saem — não assumir que extra = errado"* | idem | não abrir thread de layout no Ponto antes dessas decisões. *"Sem elas, construir o header é inventar lei"* |

### ❌ O que eu errei — e a lição com regra colada

**Erro 1 · A minha hipótese-padrão falhou pela 3ª vez seguida.**
"Produção está atrás" já havia caído no Fiscal (03/09) e no Ponto (09/09). Caiu de novo aqui, e pior: eu **exportei minha própria dívida como órfã de outro** durante semanas — o `<main>` ausente era do meu `app.jsx`.
→ **Lição (proposta):** *antes de declarar defeito "do host/do shell/sem dono", medir o host de produção no turno.* "Sem dono" é uma afirmação sobre o `main`, e afirmação sobre o `main` exige leitura no turno (regra do Erro 2 do ciclo 09/09, agora aplicada a **landmark** e não só a componente).

**Erro 2 · Eu disse 2 ciclos que "não inspeciono o protótipo deste lado". Inspeciono.**
Sondei o protótipo servido e fechei a bateria A1–A12 com T1 estável (755=755, após `__oiLazyDone`). Por causa dessa frase, dois pacotes saíram com o bloco 2 (a11y do alvo) marcado como bloqueado **sem tentativa**.
→ **Lição (proposta):** *"não consigo medir" é uma afirmação sobre a minha própria ferramenta e precisa de teste, igual a qualquer outra.* Uma tentativa custa 1 chamada; declarar impossibilidade custou 2 pacotes incompletos.

**Erro 3 · Sonda de foco medida em repouso acusou 41 defeitos inexistentes.**
`outlineStyle === 'none'` no estado de repouso → "41 elementos sem anel". Focando um: `outline solid 2px` + `box-shadow oklch(0.32 0.06 295) 0 0 0 3px`, `:focus-visible` verdadeiro.
→ **Lição (proposta):** *propriedade que só existe num estado se mede naquele estado.* O caso de sanidade do §5-bis passa a incluir **estado**, não só valor: focar/hover antes de julgar anel, e nunca reportar contagem de defeito de foco medida em repouso.

**Erro 4 · Troquei a abertura da tag e não o fechamento.**
`<div className="main">` → `<main>` sem mexer no `</div>`: `Expected corresponding JSX closing tag for <main>` e a página caiu inteira. Pego no console no mesmo turno, consertado e remedido.
→ **Lição (proposta):** *troca de tag é par, não linha.* Toda edição de elemento confere o fechamento no mesmo `str_replace` — e a prova é o console limpo + remedição, não a leitura do diff.

**Erro 5 · Hipótese de gitignore usada como explicação.**
Afirmei que `sync/` não estava na árvore porque seria gitignored. Li o `.gitignore` inteiro: **`sync/` não aparece**, e a entrada do export local declara que o repo guarda *"o manifesto do bundle"*. A explicação confortável escondia um furo real.
→ **Lição (proposta):** *explicação plausível não substitui leitura do arquivo que a confirmaria* — especialmente quando a explicação dissolve o problema.

---


### ✅ O que a produção já acertou (não refazer · não re-perguntar · não regredir)

| # | acerto, medido no `main` | onde | consequência prática |
|---|---|---|---|
| A1 | **O Ponto já é React inteiro e já compõe o DS** — 21 Pages `.tsx` + 21 charters, 24 `Inertia::render`, 2 contratos vigentes. Imports: `ui/{button,card,input,label,textarea,badge,alert,skeleton,switch}` + `shared/{KpiGrid,KpiCard,StatusBadge,EmptyState,PageFilters,BulkActionBar}` | `resources/js/Pages/Ponto/**` | o pedido "fazer o Ponto usar o DS" **não tem objeto**. O outlier era o protótipo |
| A2 | **`ui/input.tsx` e `ui/textarea.tsx` fazem `{...props}`** no elemento nativo | `Components/ui/input.tsx` @`98ba5af0d2a0` | `min`/`max`/`step`/`maxLength`/`accept` **passam**. Não existe lacuna de passthrough em produção |
| A3 | **`PontoSubNav` existe e é correto**: lê `shell.menu` (ADR 0182), tablist ARIA, `maxVisible={5}` + `⋯ Mais` + primary, hue 295, e **degrada silenciosamente** se o módulo não estiver instalado | `Pages/Ponto/_shared/PontoSubNav.tsx` @`68dd263ce16f` | não desenhar sub-nav nova. A divergência (13 abas planas no protótipo) é do **protótipo** — W9 |
| A4 | **`shared/BulkActionBar` aceita `children`** | `shared/BulkActionBar.tsx` @`9e306af2aaf4` | "o DS não tem slot pro motivo do lote" era **falso**. O campo cabe dentro. Mata a W12 |
| A5 | **`shared/KpiCard` já acerta a semântica do KPI-filtro**: `<button type="button">` + `aria-pressed={selected}` + anel `border-primary ring-1 ring-primary/40` + `focus-visible` | `shared/KpiCard.tsx` @`670b3f645b9b` | só a **forma** do tile difere. Não reescrever o comportamento |
| A6 | **O componente documenta o próprio raciocínio melhor que o protótipo** — `KpiCard.tsx` carrega ADR 0110 (tipografia do KPI), a medição de 2026-08-24 que reprovou `truncate` no rótulo (4 de 6 cortados a 1280), e uma **errata adversarial** que admite que a conclusão anterior foi interpolada com N=1 | `shared/KpiCard.tsx` :100–:160 | em conflito de tipografia, **o comentário do arquivo manda mais que o meu pedido**. Está escrito nas threads |
| A7 | **`PageFilters` é outra peça, e está certa** (chips de filtro ativo + grid + "Limpar tudo") — não é um `Toolbar` malfeito | `shared/PageFilters.tsx` @`309d6e537180` | `shared/Toolbar` entra **onde não há moldura**, não substituindo. Prova de guarda nas threads 14/15 |
| A8 | **Precedente do mesmo acerto, 6 dias antes:** no Fiscal, `Cockpit.tsx`/`Nfe.tsx` já usavam `Button`/`Input`/`Select`/`Checkbox` do DS via `_lib/botao-fiscal.ts`, **com o motivo escrito no próprio `_lib`** (armadilha layered × unlayered do `cowork-fields.css`) | `Pages/Fiscal/_lib/` | eu diagnostiquei "PR-A1 pendente" e estava **em boa parte entregue** (registrado no `github.md` de 03/09) |

### ❌ O que eu errei — e a lição com regra colada

**Erro 1 · Falso negativo de busca virou fato dito a [W].**
Procurei `from "@/Components/(ui|shared)/` (aspas **duplas**) em `Pages/Ponto/` → **"No matches"**. O repo usa aspas **simples**. Concluí e reportei: *"o Ponto não usa o DS"*. Errado em 21 arquivos.
→ **Lição (proposta):** *"zero resultado" não é evidência de ausência até rodar um **controle positivo**.* A regra já existe no protocolo para sondas de DOM (§5-bis: "toda sonda nova roda um caso de sanidade de valor conhecido antes de qualquer veredito") — **passa a valer para busca de código**. Concretamente: antes de afirmar ausência, buscar um termo que **tem** de aparecer (`^import .*from`) e conferir que o padrão de estilo do repo (aspas, alias, extensão) casa. Mesma família do L-42: eu confiei num retrato em vez de medir.

**Erro 2 · Afirmei lacuna do DS sem ler o arquivo real.**
Disse a [W] que o `Input` do DS não repassa atributo nativo e que era **lacuna pro Code**. O que eu tinha lido era o **bundle compilado do espelho** (`_ds/…/_ds_bundle.js`, lista de props fechada, sem spread). Produção faz `{...props}`.
→ **Lição (proposta):** *o espelho (`_ds/`) não é evidência sobre o `main`.* Toda frase da forma "o DS não tem X" exige o `.tsx` real no turno, ou a frase é **"o bundle do espelho não tem X"** — que é outra afirmação, com outro dono.

**Erro 3 · Estendi uma autorização ao vizinho de bullet.**
[W] disse "apague a ProvaViva". Apaguei também `AssinaturaAtualizar.tsx`, que ele **não** tinha olhado. Restaurada por reimportação do `main` (12.168 B), sem transcrição.
→ **Lição (proposta):** *"apague X" autoriza X.* Diagnóstico igual não é autorização igual. Item vizinho no mesmo bullet, na mesma tabela ou com o mesmo defeito **continua precisando de "sim"**.

**Erro 4 · Emiti alvo medido em cima do lixo da própria migração.**
A migração `.pt-toolbar → PtBarra` arrastou 7 `<span className="pt-sp">` do CSS antigo, convivendo com o spacer do `Toolbar` (82px de gap fantasma). O bloco C da thread `ds-atomos/03` foi medido **naquela barra** e mentiu em 3 números ("7 filhos, todos `flex:none`, `nowrap`" → são 6, e o regime é `0 1 auto` / `1 1 260px` / `1 1 0%`).
→ **Lição (proposta):** *migração mecânica preserva lixo que só fazia sentido no regime antigo — medir depois da limpeza, nunca antes.* E: **regime de flex se lê, não se supõe** (`getComputedStyle(...).flex`, nunca a intenção do CSS).

**Erro 5 · Medi na janela do preview e chamei de alvo.**
A barra dava 158px/5 faixas a 599px. A 1280px (persona Larissa) é **1215×71px, uma faixa**.
→ **Lição (proposta):** *toda medida cita a largura.* Número dependente de largura sem largura declarada é provisório, não alvo. (Já havia registro do mesmo vício em `handoff-crm/PEDIDO-CODE.md` e `COLAR-NO-CODE-repair-ondas.md`, ambos medidos a 841px — **terceira reincidência**, agora com regra.)

### 🔁 Reincidência — o que este ciclo prova sobre o método
**"Produção está atrás do protótipo" é a minha hipótese-padrão, e ela falhou 2 de 2 vezes** em que foi testada contra leitura (Fiscal 03/09 · Ponto 09/09). Nos dois casos a produção estava **à frente** em pelo menos um eixo (a11y, átomos do DS, motivo escrito no código).
→ **Consequência de método:** o passo 0 do §13.7 ("RELER a árvore no turno") **não basta se a leitura for enviesada pela hipótese**. Antes de escrever "o módulo X não tem Y", a ordem é: (1) controle positivo na busca · (2) ler 1 arquivo real do módulo · (3) só então afirmar. Custo medido: 3 chamadas. Custo de errar: um pacote inteiro emitido no eixo errado — que foi o que aconteceu **antes** de eu corrigir, neste mesmo turno.

---

<!-- Próximo ciclo: acrescente um bloco ACIMA desta linha, no mesmo formato (✅ acertos com sha · ❌ erros com regra colada · 🔁 reincidência). Não reescreva blocos antigos. -->
