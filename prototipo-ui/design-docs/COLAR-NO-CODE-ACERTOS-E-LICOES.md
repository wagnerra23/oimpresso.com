# ACERTOS E LIÇÕES — o que a produção acertou, e o que eu errei ao supor o contrário

> **Por que este arquivo existe** (pedido [W] 2026-09-09): *"deveria ir acrescentando e informando pro Code o que ele acertou do que você já escreveu, e as novas memórias — isso mantém o Code para não errar novamente."*
> O sistema já catalogava **erro** (`memory/LICOES_CC.md`), **ausência** (placar) e **proibição** (`memory/proibicoes.md`). Faltava o positivo: **o que já está certo no `main` e não se refaz, não se re-pergunta e não se "melhora"**. Sem isso, cada ciclo redescobre a mesma coisa — e às vezes redescobre errado.
> **Estatuto:** é **ponte**, não memória. Destino no `main`: `prototipo-ui/design-docs/`. As lições daqui são **propostas** para `memory/LICOES_CC.md` — a numeração `L-NN` é do [CL] no merge, eu não invento número.
> **Forma:** um bloco por ciclo, **mais novo em cima**. Acrescenta-se; não se reescreve o passado (o erro registrado é o valor).
> **Leitura:** entra no read-order **junto** com `LICOES_CC.md` — o pre-flight injeta erro catalogado; este injeta **acerto** catalogado.

---

## Ciclo 2026-09-09 · Ponto — DS nos átomos (árvores `a0db7b0177b8` → `2b4a3ec3b48a`)

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
