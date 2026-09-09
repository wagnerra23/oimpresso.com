# ACERTOS E LIÇÕES — o que a produção acertou, e o que eu errei ao supor o contrário

> **Por que este arquivo existe** (pedido [W] 2026-09-09): *"deveria ir acrescentando e informando pro Code o que ele acertou do que você já escreveu, e as novas memórias — isso mantém o Code para não errar novamente."*
> O sistema já catalogava **erro** (`memory/LICOES_CC.md`), **ausência** (placar) e **proibição** (`memory/proibicoes.md`). Faltava o positivo: **o que já está certo no `main` e não se refaz, não se re-pergunta e não se "melhora"**. Sem isso, cada ciclo redescobre a mesma coisa — e às vezes redescobre errado.
> **Estatuto:** é **ponte**, não memória. Destino no `main`: `prototipo-ui/design-docs/`. As lições daqui são **propostas** para `memory/LICOES_CC.md` — a numeração `L-NN` é do [CL] no merge, eu não invento número.
> **Forma:** um bloco por ciclo, **mais novo em cima**. Acrescenta-se; não se reescreve o passado (o erro registrado é o valor).
> **Leitura:** entra no read-order **junto** com `LICOES_CC.md` — o pre-flight injeta erro catalogado; este injeta **acerto** catalogado.

---

## Ciclo 2026-09-09 · Protocolo (§6-bis + §16) no `main` e o cron do shipped-log (árvores `9d1fa67ac8` → `6f529985368c`)

> Escrito pelo **[CL]** a pedido de [W] ("escreva"). O ciclo foi: colar a norma nova do Cowork ([PR #7136](https://github.com/wagnerra23/oimpresso.com/pull/7136), `66/5`), atacá-la pelo próprio §16 que ela introduz, e destravar o único CI vermelho.

### ✅ O que a produção já acertou (não refazer · não re-perguntar · não regredir)

| # | acerto, medido no `main` neste turno | onde | consequência prática |
|---|---|---|---|
| A1 | **A correção de caminho do §12 está certa, e o caminho antigo estava quebrado.** O script canon tem `descobrirIndices` (`:12`), monta paths `…/00-INDICE.md` (`:17`) e lê `.md` **ou** `.json` por ternário `.endsWith(".md")` (`:127`, `:134`) | `design-docs/cowork-inbox/_scripts/placar-indice.mjs` @`08479554` | não re-apontar doc nenhum para `cowork-inbox/_scripts/…` sem o prefixo `prototipo-ui/design-docs/` — aquele path **não existe** |
| A2 | **O CI roda a cópia canon**, não a do espelho | `.github/workflows/design-memory-gate.yml:603` | ao falar do placar, citar a canon. A do espelho não é a que decide nada |
| A3 | **O schema bate palavra a palavra com o §12**: `dono` enum `[CC, CL, W, W+CL, CC->CL]`, `guarda` boolean, thread `required` = `[id, titulo, dono, arquivo, prefixo, provas]`, `decisoes` com `custo`/`afeta`/`destrava` | `design-docs/cowork-inbox/_schema/playbook.schema.json` @`1dcd29ba` | não re-descrever o schema de memória — ele já está descrito certo na norma |
| A4 | **O `cowork-ssot-guard` já tem R4** — *host único na raiz de `cowork/`*, nascido em **2026-09-01 a pedido do próprio lado design** | `scripts/governance/cowork-ssot-guard.mjs:25` | a regra proposta no §6-bis é **R5**. E "R1/R2/R3 já moram lá" está desatualizado |
| A5 | **O mesmo cabeçalho já registra por que uma regra sintática de path NÃO virou R4** — FP medido *antes*: 24 hits, ~5 FP por construção (fixtures precisam da cópia), 19 cópias declaradas — citando as 4 lápides de guard sintático do §5 | idem `:13–:21` | antes de propor guard por path/pasta, ler esse cabeçalho. O projeto já pagou essa medição |
| A6 | **O `shipped-log-generate` é fail-closed e funcionou**: recusou gravar com `coletado(4606) ≠ total_count(4752)`. O docblock já documenta a Search API respondendo **`0` com `rc=0`, corpo bem-formado e `cost: 1`** — erro unidirecional, subestima e nunca inventa (130 leituras) | `scripts/governance/shipped-log-generate.mjs:155–214` | vermelho isolado desse cron **se re-roda**, não se conserta nem se silencia. O docblock avisa em letra maiúscula: *"nunca para remover este consolida"* |
| A7 | **O watchdog G6 lê a última run AGENDADA, não a última run** | `.github/workflows/shipped-log-cron.yml` + watchdog G6 (ADR 0317) | verde por `workflow_dispatch` **não limpa** o alarme — havia um verde às 17:01Z e o watchdog seguia vermelho, corretamente. Destrava-se re-rodando a run com `event=schedule` (feito: `34358072747` → `success`) |
| A8 | **A cópia obsoleta do placar dentro do espelho tem ZERO invocadores** — varredura em `.github`, `scripts`, `package.json`, `.claude`, `prototipo-ui`: nenhuma referência; as únicas menções são dois `_saida-*.md` de threads que **tropeçaram nela e anotaram** | `prototipo-ui/cowork/cowork-inbox/_scripts/` @`ae69d188` | é **ruído inerte**, não bug vivo. E o conserto não é apagar à mão: §10 proíbe editar `cowork/` do lado do git (espelho read-only; some no próximo `--export-from`) |

### ❌ O que eu errei — e a lição com regra colada

**Erro 1 · Busquei num path adivinhado, li o zero como ausência, e quase reportei.**
Rodei o grep de `ds-anchor-check` contra `prototipo-ui/ds-anchor-check.mjs` — path que eu **supus**. Voltou zero, e eu estava a um passo de escrever "não achei os 15 casos". O arquivo existe, em `prototipo-ui/cowork/cowork-inbox/ancora-ds/ds-anchor-check.mjs`, e declara os 15 casos na `:229`.
→ **Lição (proposta):** *claim de ausência exige controle positivo **e** o dono do inventário — nunca um path adivinhado.* Concretamente: `git ls-tree -r --name-only origin/main | grep <termo>` responde "onde está", e é isso que precede qualquer frase com "não existe". Um `git show <ref>:<path>` que volta vazio é indistinguível de arquivo ausente, de path errado e de mangling do MSYS.

**Erro 2 · Meu controle positivo estava miscalibrado, e ele acusou duas sondas que estavam certas.**
Para checar colapso de escape (LC-26) montei um arquivo de controle com `printf 'a\\b\nc\\\\d\n'` e previ 3 barras invertidas. `grep` e `tr` responderam 1 — e eu tratei as sondas como não-confiáveis. O `cat -A` mostrou `a^H$`: o `printf` interpretou `\b` como **backspace**, não como barra + `b`. O controle é que tinha 1 barra. As duas sondas estavam corretas o tempo todo.
→ **Lição (proposta):** *o controle só valida a sonda se você conhece o valor verdadeiro DELE.* Antes de julgar a sonda pelo controle, **inspecione o controle** (`cat -A`, `od -c`). Um controle mal-calibrado tem dois modos e os dois são caros: transforma sonda boa em suspeita (o meu caso) e, invertido, **absolve sonda cega**.

**Erro 3 · Apresentei gravidade antes de contá-la.**
Escrevi no corpo do PR que *"o buraco do §6-bis já está ocupado no `main`"*, com moldura de achado grave, tendo medido só que a cópia **existe** e **diverge**. Só depois contei invocadores: zero. A moldura estava mais forte que a medida.
→ **Lição (proposta):** *gravidade se conta, não se infere da existência.* Antes de chamar duplicata/órfão/divergência de bug vivo, contar **quem invoca** — repo inteiro mais o dono do inventário. "Existe e diverge" e "está em uso" são duas afirmações, e só a segunda justifica urgência.

### 🔁 Reincidência — o que este ciclo prova sobre o método

**O Erro 1 acima é, literalmente, a Lição do Erro 1 do bloco de baixo** — escrita horas antes, no mesmo dia, e que eu **tinha lido** ao revisar o §15 para julgar se a norma nova era melhor. Reincidiu mesmo assim, agora do lado [CL] em vez do [CC]: a classe não é de um agente, é do ato de afirmar ausência.
→ **Consequência de método, e ela é a favor do §16:** o que me pegou não foi ter lido a regra — foi **ter rodado o ataque B** (controle positivo), e eu só rodei porque estava avaliando o §16 e ele obriga. Ou seja: a seção adversarial pegou o erro do próprio avaliador dela, no turno em que estava sendo avaliada. Regra escrita não protegeu; **regra executada protegeu**. É o argumento mais forte que este ciclo produziu para o §16 não ser tratado como cerimônia.

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
