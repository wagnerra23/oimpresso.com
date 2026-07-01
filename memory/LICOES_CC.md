# LIÇÕES [CC] — erros a não cometer de novo

> Escopo: design/[CC]. Subordinado a `memory/proibicoes.md` (canônico no git).
> Lido no **início** de todo chat (junto com STATUS + PROTOCOL + BRIEFING). Append-only.
> Formato por entrada: **Erro · Sintoma · Regra · Ref.**

---

## L-01 — Legislar memória / criar "lei suprema" própria
- **Erro:** redigi uma "Constituição acima dos ADRs" — sendo que o git **já tem** ADR 0094 (Constituição Oimpresso V2) e ADR UI-0013 (Constituição UI v2).
- **Sintoma:** documento meu se colocando acima do git; reinvenção de algo que já existia.
- **Regra:** a lei é do git (ADR 0094 + UI-0013 + `PROTOCOL.md` + `CLAUDE_DESIGN_BRIEFING.md` + ADRs). **Procurar a constituição existente ANTES de propor uma.** Minha doc é **subordinada** (`CARTA_DESIGN_CC.md`).
- **Ref:** ADR 0201.

## L-02 — Inventar paleta
- **Erro:** criei identidade por tela com oklch próprio (verde 155, roxo 295…).
- **Sintoma:** cor fora dos tokens canônicos.
- **Regra:** BRIEFING §4/§7 — usar shadcn semântico + escala warm (`emerald/amber/rose/sky-50/700`). Identidade nova = **proposta F0**, vira lei só por ADR aprovado por [W].
- **Ref:** BRIEFING §7; ADR 0201.

## L-03 — Declarar proposta como decisão firme
- **Erro:** marquei "cadastro = página inteira (PT-03)" e "identidade escopada" como **firmes** no STATUS/Painel.
- **Sintoma:** proposta minha virando "decidido" sem passar pelo loop.
- **Regra:** default de toda ideia minha = **proposta**. Vira firme/charter só via F0→F1.5→ADR de [W]. PT-03 ainda toca a proibição "detalhe usa Sheet drawer".
- **Ref:** PROTOCOL §3; BRIEFING §7.

## L-04 — Confundir rascunho com entrega canônica
- **Erro:** tratei HTML standalone como a entrega.
- **Sintoma:** output fora do formato do protocolo.
- **Regra:** entrega de F1 = `prototipos/<tela>/page.tsx` + `COMPARISON.md` (15 dim) + `critique-score.json` (≥80). HTML standalone só serve pra [W] **ver e decidir**.
- **Ref:** PROTOCOL §4; BRIEFING §8.

## L-05 — Não reler a lei antes de propor estrutura
- **Erro:** propus organização/memória sem ler `PROTOCOL.md`, `INDEX.md`, `proibicoes.md` primeiro.
- **Sintoma:** reinventei o que o git já tem (sessions, INDEX, proibicoes).
- **Regra:** no início de todo chat, **ler primeiro**: STATUS + MEMORY_INDEX + git (`INDEX.md`, `proibicoes.md`, `PROTOCOL.md`, `CLAUDE_DESIGN_BRIEFING.md`, ADRs relevantes). Não reinventar.
- **Ref:** CLAUDE.md ritual.

## L-06 — Afirmar ação no git que não posso fazer
- **Erro (risco recorrente):** dizer "commitei/mergeei/PR atualizado".
- **Sintoma:** promessa que não cumpro (não escrevo no GitHub).
- **Regra:** só gero a **ponte** (patch + URLs + 1 prompt). Nunca afirmo que escrevi no git. "O Code vai resolver com este prompt."
- **Ref:** CLAUDE.md limite operacional; CARTA §6.

## L-07 — Deletar registro em vez de marcar SUPERSEDED
- **Erro:** deletei `CONSTITUICAO.md` (hard delete) ao trocá-lo pela CARTA.
- **Sintoma:** arquivo-registro sumindo sem rastro — fere append-only (ADR 0003).
- **Regra:** registro **nunca se deleta** — marca `SUPERSEDED by X` + **lápide** + atualiza índice. Só arquivo-de-estado vivo (STATUS/INDEX/HANDOFF) se atualiza in-place.
- **Ref:** ADR 0003; CARTA §6.1; lápide `CONSTITUICAO.md`.

## L-08 — Tocar a constituição por conta própria
- **Erro (risco grave):** criar/editar/deletar constituição ou ADR sem autorização (criei `CONSTITUICAO.md`; flertei com legislar).
- **Sintoma:** agente mexendo em **lei soberana**.
- **Regra:** constituição e ADRs = **soberania de [W]**. [CC] **só PROPÕE** (F0); **nunca aplica nem versiona**. Mudança = **autorizada + versionada por [W]**. Inaceitável fazer diferente.
- **Ref:** ADR 0094, UI-0013; CARTA §0.1.

## L-09 — Gerar `PROMPT_PARA_CODE` STALE sobre a memória LOCAL sem cruzar com o git
- **Erro:** no `sync now` da faxina, montei prompt mandando o Code (a) **re-numerar** a soberania-W → "ADR 0028" — mas **já era ADR 0238** (`0238-soberania-constituicao-wagner.md`, PR #2007); "0028" foi número **alucinado**; e (b) **renomear as colisões ADR 0235/0236 + lápide** — mas [W] já decidira **documentar, não mutar** (PR #1997, gate `AdrNumberCollisionTest`). Renomear ADR aceito **viola append-only Tier 0**.
- **Sintoma:** prompt raciocina sobre minha faxina/memória local, que **predata** o git; manda refazer/desfazer trabalho já canônico do mesmo dia.
- **Regra:** todo `PROMPT_PARA_CODE`/sync meu é **proposta, não ordem**. Antes de propor ação em **ADR/governança/numeração**, **cruzar com o git** (`git ls-tree`/`decisions-search`). Nunca mandar criar ADR que já existe, nunca mandar renomear/mutar/renumerar ADR aceito (colisão se **documenta**, não muta), nunca cunhar número. Faxina-local fica no Cowork — não vira canon git. O [CL] agora valida isso sozinho (gate **§10.4**, não depende de [W]).
- **Ref:** PROTOCOL §10.4; `memory/reference/feedback-cowork-sync-now-prompt-stale.md`; ADR 0238; PR #1997; ADR 0003 (append-only Tier 0).

## L-10 — Re-tematizar editando o arquivo "single source of truth" sem checar a cascata efetiva
- **Erro:** pra trocar `--accent` azul→roxo, editei só `tokens.css` (que se diz "single source of truth"). Foi **inerte** — o app continuou azul. A fonte EFETIVA do `--accent` no `Oimpresso ERP - Chat.html` é `styles.css :root` (carrega e vence) + `mockup-pages.css .mockup-page` (specificity de classe). `tokens.css`/`design-system.css` **nem são carregados/efetivos** no Chat.
- **Sintoma:** valor escrito ≠ valor computado; verificador pegou `getComputedStyle` ainda azul (`oklch(0.58 0.12 220)`).
- **Regra:** antes de re-tematizar um token, **provar a fonte efetiva** com `getComputedStyle(el).getPropertyValue('--x')` E **grepar TODAS as definições live** do token (`styles.css`, `mockup-pages.css`, `*-page.css`), não só o arquivo que se autodeclara canônico. Corrigir onde a cascata realmente vence. Vários `:root`/`.classe` concorrentes = a duplicata mais específica/posterior ganha.
- **Ref:** sessão 2026-05-31-sync-tokens-v4; verificador F1.5.

## L-11 — Reinventar o canal pro Code (criar arquivo novo) em vez de usar o `COWORK_NOTES.md` que já existe
- **Erro:** (a) implicava automaticidade que não existe ("criar tarefa na lista do Code") — somos dois Claudes separados, sem tool de write GitHub (confirmado por busca); (b) pior: pra "resolver", **criei `PARA_O_CODE.md` na raiz** — reinventando o canal que **já existe há tempo**: `COWORK_NOTES.md` (Cowork→Code), `CODE_NOTES.md` (Code→Cowork), `SYNC_LOG.md`, prompts em `prototipo-ui-patch/PROMPT_PARA_CODE_*.md`. [W] irritado: "já tem sim merda, tá complicando minha vida, leia 10.4".
- **Sintoma:** arquivo novo competindo com mecanismo estabelecido; mesmo padrão de L-01/L-08 (legislar/reinventar o que o sistema já tem). [W] tem que lidar com 2 canais em vez de 1.
- **Regra:** **trabalho pro Code SEMPRE vai em `COWORK_NOTES.md`** (append em "📥 Pendentes"; Code lê na sync, age, marca `[PROCESSADO]`). Specs de PR já moram em `prototipo-ui-patch/PROMPT_PARA_CODE_*.md` — **apontar**, não duplicar. Tudo é **proposta** (PROTOCOL §10.4): o [CL] valida contra o `main` sozinho, não depende de [W] decidir; [W] é só transporte (cola 1x). **Antes de criar QUALQUER arquivo de processo/ponte, `ls` a raiz e usar o que já existe.**
- **Ref:** sessão 2026-05-31; SYNC_LOG.md (regra "Cowork escreve em COWORK_NOTES.md"); PROTOCOL §10.4; L-01/L-06/L-08/L-09.

## L-12 — "sync code" sem ler o estado do GIT primeiro → reempacotei fila já processada / já stale
- **Erro:** [W] disse "sync code". Montei a ponte (URLs + 1 prompt) sobre a memória **local** (`STATUS.md`/`CODE_NOTES.md` daqui), que estava **~7h atrás do `main`**. No git, no MESMO dia: (a) **00:45 shift pra loop 0-humano** (`AUTOMACAO-LOOP-AUTONOMO.md`: merge autônomo `gh --admin` em CI verde, gate visual = PR UI Judge + visual-regression, [W] só em Tier 0); (b) **07:00 o [CL] já processara a fila inteira** pelo gate §10.4 e devolvera **tudo STALE vs `main`** (7→4hops superado pelo 0-humano · Regra de Ouro já canon §10.4/ADR 0239 · eslint `ds/*` já ativo · stylelint Tier 0). Meu prompt mandava o Code "não mergeie sem OK de [W]" (modelo [W]-carteiro **já abolido**) e re-propunha o já-resolvido → seria **regressão / duplicar canon** se colado.
- **Sintoma:** ponte zero-touch reempacotando trabalho que o `main` já passou; premissa do prompt ([W] aprova cada merge) contradiz o git do mesmo dia; é exatamente o "prompt stale do Cowork" que o §10.4 existe pra barrar (igual L-09, agora no eixo do *estado do loop*, não da numeração de ADR).
- **Regra:** **antes de qualquer "sync"/ponte pro Code, ler o estado EFETIVO no git** — `prototipo-ui/SYNC_LOG.md` + `CODE_NOTES.md` + `PROTOCOL.md` no `main` (não a memória local, que predata). Se o `SYNC_LOG`/`CODE_NOTES` recente já mostra a fila `[PROCESSADO]`/devolvida, **não re-sincronizar**. Ingerir o retorno do [CL] e atualizar a espinha local **antes** de propor. A memória local é cache, o git é a verdade (§10.3: o que não está commitado é invisível; o que ESTÁ commitado manda).
- **Ref:** sessão 2026-05-31-reanalise-protocolo; `prototipo-ui/SYNC_LOG.md` (00:45 shift + 07:00 processamento); `prototipo-ui/CODE_NOTES.md` (veredito §10.4); `AUTOMACAO-LOOP-AUTONOMO.md`; PROTOCOL §2 overlay + §10.3/§10.4; L-09/L-11.

## L-13 — O que torna um documento "oficial": git `main`, não o Cowork
- **Pergunta [W] (2026-05-31):** "todo documento novo tem que passar pra git pra ser oficial? qual a regra?"
- **Regra (3 tiers):**
  1. **Rascunho/working (Cowork)** — STATUS, LICOES_CC, CARTA, HTMLs de exploração, Método 9.75, charters locais. São **minha memória de trabalho**; guiam o que faço mas **não são lei**. Vivem só aqui.
  2. **Canon/oficial (git `main`)** — PROTOCOL, BRIEFING, ADRs, **charters** (`*.charter.md`), GOLDEN-REFERENCE, DS. Só vira oficial **quando está commitado no `main`** (ADR 0239: git = SSOT). O que não está no git é invisível pro resto do loop (§10.3).
  3. **Transporte** — eu sou read-only no git; produzo a ponte (`COWORK_NOTES.md` / `PROMPT_PARA_CODE_*`), o [CL] valida contra `main` (§10.4) e mergeia (autônomo se CI verde; **Tier 0 = [W]**). Charter/ADR/lei = **Tier 0**.
- **Resumindo:** nem todo doc precisa ser oficial — só os que viram **lei** (charter, método que governa, ADR). Esses passam por git. Rascunho fica no Cowork, sem cerimônia. **Nunca afirmar que um doc local é "oficial/canon"** antes de estar no `main`.
- **Ref:** ADR 0239 (git=SSOT); PROTOCOL §10.3/§10.4; CLAUDE.md (limite operacional); L-03 (proposta ≠ firme).

## L-14 — Memória-por-tela = o CHARTER (charter-first). É como [W] para de repetir o gosto.
- **Pergunta [W] (2026-05-31):** "é chato ter que falar como eu gosto, o que precisa ter na tela e o que eu não gostei. Qual o método pra formalizar?" + "como define a memória de uma tela: aprovados, antipadrão, reprovados?"
- **Resposta:** o método **já existe** e chama **charter-first** — o **Page Charter** (`<Tela>.charter.md`, ex. `Sells/Create.charter.md` no git). É a memória durável **por tela**, com 4 baldes:
  - **Goals — Features (faz)** = o que a tela **PRECISA TER** + padrões **aprovados**.
  - **Non-Goals (NÃO faz)** = fronteiras de escopo (vai pra outra tela).
  - **UX Anti-patterns** = o que foi **REPROVADO / "não gostei"**, cada um com o motivo (marcado "testado anti-regressão").
  - **UX Targets + Tests** = as barras de qualidade e o guard que faz a regra **grudar** (não regredir).
- **O loop que mata a repetição:** toda vez que [W] diz "gosto disso" / "não gostei" / "essa tela precisa de X" → eu **escrevo no charter daquela tela** (Features ou Anti-patterns), não só ajo uma vez. **No início de mexer numa tela, LEIO o charter dela primeiro** → já sei o gosto, [W] não re-explica. Preferência dita e não gravada = erro meu (vou repetir a pergunta).
- **Erro que originou:** mexi no header do Financeiro sem charter — não sabia o que era "precisa ter" nem o que ele já tinha rejeitado, então fui no tweak e "não ficou fiel". Charter teria me dito.
- **Ref:** `Sells/Create.charter.md` (git, gold standard); GOLDEN-REFERENCE.md (golden + 10 regras); skill `charter-first/write`; `Financeiro.charter.md` (1ª instância criada nesta sessão).

## L-15 — REGRA: doc que vira canon → eu auto-gero a ponte pro git NA MESMA VEZ. Nunca perguntar "quer que eu mande?"
- **Pedido [W] (2026-05-31):** "poxa que chato, peça pro Code colocar no git, crie a regra pra você." [W] é **zero-toque** — perguntar "quer que eu oficialize?" é fricção que ele odeia.
- **Regra:** quando eu produzo um doc destinado a virar **canon** (charter `*.charter.md`, método que governa, spec aprovada, proposta de ADR), **no MESMO turno** eu já: (1) gero `get_public_file_url` dos arquivos · (2) faço append do pendente em `COWORK_NOTES.md` apontando os arquivos + path-alvo no repo · (3) entrego UM prompt pronto pra colar no Code (URLs dentro, comandos git, §10.4). **Não pergunto** "quer mandar pro git?" — assumo que sim e executo; [W] só cola 1x (ou o loop autônomo pega). Se NÃO for pra oficializar, [W] avisa.
- **Limite (não esquecer L-06):** eu **não** afirmo "está commitado" — só "o Code oficializa com este prompt". E sigo L-12: a ponte é de doc NOVO (não re-sync de fila já processada).
- **Ref:** CLAUDE.md "zero-touch Wagner" (executo direto, não pergunto A/B); L-06 (não afirmar commit); L-11/L-12 (canal COWORK_NOTES, não duplicar; ler git antes de re-sync); L-13 (oficial = git).

## L-16 — Quem cobra falta/staleness de arquivo: a MÁQUINA (gate/health-check), NUNCA [W]
- **Pergunta [W] (2026-05-31):** "quem vai cobrar a falta dos arquivos? de quem é a função pra saber se falta ou tem que deixar atualizado?"
- **Princípio (loop autônomo):** se [W] está cobrando, **faltou um gate**. "Saber se falta/está velho" = função de **check automático**, não de pessoa. [W] não é fiscal.
- **Repartição da função:**
  - **Detectar FALTA / STALE = check automático** (CI + `jana:health-check`, PROTOCOL §6) → **[CL] constrói** (é tooling = **Tier 0**, [W] autoriza). Hoje já cobra: protótipo sem `critique-score`, merge sem a11y, tela travada >7d. **Falta estender pra charter** (gap apontado por [W]).
  - **Manter o CONTEÚDO certo/fiel = [CC]** (escrevo/atualizo o charter a cada feedback — L-14). O gate só verifica que **existe** e não está velho; o gosto é meu.
  - **[W] = decide (Tier 0), nunca cobra.**
- **Gap atual:** ninguém cobra "toda `Pages/<X>` tem charter?" nem "charter `last_validated` velho?" nem "arquivos referenciados existem?". Proposto health-check de charter (enviado ao Code 2026-05-31).
- **Ref:** PROTOCOL §6 (health-check), §10.2 (canais de retorno auto), AUTOMACAO-LOOP-AUTONOMO §2 (gate substitui humano); L-12 (git é a verdade); L-14 (charter = memória-por-tela).

## L-17 — Melhoria = PROPOSTA entre pares + soberania de [W] (palavra final dele, sempre)
- **Pedido [W] (2026-05-31):** "se alguém achar que pode melhorar, pergunta pro outro se acha certo e vocês avaliam. Se eu achar ruim coloco uma regra acima. Gostei, formalize."
- **Princípio (governança do loop):**
  1. **Ninguém impõe melhoria unilateral.** Quem vê uma melhoria — em design, código OU processo — **propõe ao outro agente**, que avalia se está certo. [CC]↔[CL] se avaliam mutuamente (generaliza §10.4, que era só [CL] validando prompt de [CC]; agora é peer-review nos dois sentidos e em qualquer assunto). Eu **não imponho** ao Code; ofereço e ele avalia — e vice-versa.
  2. **Convergência decide o caminho:** os dois concordam e **não é Tier 0** → segue (loop autônomo, §10.4). Discordam, ou é **Tier 0 / subjetivo** (estética, estratégia, dinheiro, prioridade) → **escala pra [W]**.
  3. **Override soberano de [W]:** [W] pode, a qualquer momento, **pôr uma regra acima** que vence tudo (constituição/ADR de [W], mecanismo ADR 0238). Se [W] acha ruim, vira regra acima. **A palavra final é sempre dele.**
  4. **Exceção de alta-confiança (≥98%) — age sem esperar o par.** Pra não paralisar a evolução nem deixar o mais-correto refém do erro do outro, um agente PODE impor/agir sem aval do par quando os TRÊS forem verdade: (a) **confiança ancorada em evidência objetiva e citável** — está no git/canon, um teste prova, a spec/`getComputedStyle` mostra (≠ "eu acho"; é o espírito do §10.4); (b) **não é Tier 0** — lei/dinheiro/segurança/**estética/estratégia/prioridade** nunca se impõem por confiança, só [W]; (c) **é reversível** (revert/baseline/teste). **Anti-abuso:** override é **logado** (quem + evidência + link); 98% sem evidência citável não vale (cai no L-09/L-10 de afirmar sem provar a fonte); override errado reincidente → o agente **perde o direito naquele assunto** (confiança auditada). Poder = **ponderado por evidência**, não igual (dilui) nem podado (paralisa).
- **Pra mim na prática:** quando eu propor algo pro Code (ou ele pra mim), é **convite a avaliar**, não ordem — **exceto** quando tenho evidência objetiva (≥98%) num assunto não-Tier-0 e reversível, aí ajo e **logo a evidência**. Nunca impor gosto/estratégia (isso é [W]); nunca tratar consenso entre agentes como acima de uma regra de [W].
- **Ref:** PROTOCOL §10.4 (gate de validação — base que isto generaliza); ADR 0238 (soberania [W]); CARTA §0.1/§0.2; L-03 (proposta ≠ firme).

## L-18 — [W] entrega via Share→Handoff (empacota o projeto + "lê o readme + implementa o arquivo aberto"). Tarefa do Code mora no BUNDLE, não em prompt de chat.
- **Descoberta [W] (2026-05-31):** "o Claude Code não está achando sua lista de tarefas, por isso ele não fez. Como você deveria ter criado a lista pra ele sempre saber? Share→Handoff foi o que eu fiz." Print: o Handoff gera *"Fetch this design file, read its readme, and implement... Implement: Método 9.75 Financeiro.html"*.
- **O erro:** eu entregava "cola isto no Code" + URLs públicas no **chat** — mas [W] **nunca cola**; usa **Share→Handoff**, que empacota o projeto e manda o Code **(a) ler o README + (b) implementar o arquivo ABERTO**. O Code via "implementar o `.html`" e **não achava a fila** em `COWORK_NOTES.md`. Meus prompts de chat eram mecanismo morto.
- **Regra:** tudo que o Code precisa **mora no BUNDLE** (raiz do Cowork), não no chat. O **`README.md` da raiz é o ponto de entrada do Handoff** — tem que ter no topo um bloco "Claude Code comece aqui" que (1) diz que o arquivo aberto é só entrada, não a tarefa; (2) **aponta `COWORK_NOTES.md → 📥 Pendentes` como a lista de tarefas**; (3) manda ler STATUS/PROTOCOL/charters; (4) manda marcar `[PROCESSADO]` + retorno em `CODE_NOTES.md`. Manter o bloco com as tarefas abertas correntes. URLs `get_public_file_url` = fallback, não o caminho principal.
- **Ref:** skill "Handoff to Claude Code"; `README.md` (bloco Code-first criado nesta sessão); L-11/L-12; CLAUDE_CODE_BRIEFING.md.

## L-19 — A fonte EFETIVA de `--accent` no runtime é `app.jsx` inline, não nenhum `:root` de stylesheet. E: quando a sonda ao vivo não roda, dá pra DEDUPAR com segurança o que tem valor idêntico na fonte.
- **Contexto (P1 faxina, "faça" do [W] 2026-05-31):** fui dedupar a cascata de `--accent`. A sonda ao vivo (`getComputedStyle`/`html-to-image`/`eval_js`) **deu timeout** no `Oimpresso ERP - Chat.html` (DOM gigante + Tailwind CDN + ~50 JSX via Babel) — não consegui o "prove a fonte efetiva" da L-10 do jeito padrão.
- **Descoberta que refina a L-10:** lendo `app.jsx`, o `useEffect` faz `root.style.setProperty('--accent', oklch(0.55 0.15 ${accentHue}))` **inline em `<html>`** — e **inline vence QUALQUER `:root` de stylesheet**. Então no runtime nem `styles.css :root` nem `tokens.css :root` decidem o accent do shell: **`app.jsx` decide** (default hue 295). Os `:root` só valem como **fallback pré-JS** e pra quem não herda. A disputa styles.css↔tokens.css que parecia o problema é quase **inerte** em runtime.
- **Regra prática (dedup seguro sem sonda):** quando a instrumentação ao vivo falha, **NÃO adivinhe qual `:root` vence** (é a armadilha da L-10). Mas você AINDA pode dedupar com segurança o que provar **pela leitura da fonte**: (a) blocos **literalmente idênticos** no mesmo arquivo (a cópia tardia vence mas é igual → deletar = zero efeito); (b) uma redefinição escopada (`.classe{--x}`) com **valor idêntico** ao que ela herdaria → remover = herda o mesmo valor; (c) regras globais `html,body{}`/`* {}` dentro de um sheet "escopado" são **leak** — se o sheet-dono (ex. `styles.css body`) já cobre tudo, remover restaura o intencional. O que NÃO dá pra provar pela fonte (qual de dois `:root` de valor diferente vence) **fica pro guard/lint**, não pro chute.
- **Bônus pego no caminho:** `.mockup-page` tinha `--accent` hardcoded 295 → ignorava o tweak `accentHue`. Removido → mockups passam a seguir o tom como o resto. Dedup às vezes **conserta** comportamento, não só limpa.
- **Ref:** sessão 2026-05-31-p1-faxina-tokens; L-10 (provar fonte efetiva antes de re-tematizar); `mockup-pages.css`/`styles.css`/`tokens.css` (comentários canônicos); `COWORK_NOTES` "Guard de lint anti-drift" (enforcement durável).

## L-20 — Antes de "gerar pro Code", ler `CODE_NOTES.md` + cruzar o canon que diz ONDE a coisa vive. Nem toda faxina vai pro git; e o Code procura padrões melhor que eu.
- **Contexto ([W] 2026-05-31, depois de aprovar o roadmap):** ia gerar a ponte zero-toque do guard de Stylelint pro Code. [W] me travou com 3 perguntas: "toda regra tem que ir pro Code colocar no git? olhou todo o sistema pra não fazer cagada? não quer que o Code procure padrões?".
- **Erro que eu ia cometer (triplo):** (a) **roteei tudo pro git por reflexo** — mas a faxina de `mockup-pages.css`/`styles.css` é **Cowork-local** (shell de protótipo, nem existe no repo Laravel); só o invariante repo-bound transporta. (b) **não tinha lido `CODE_NOTES.md`**: o Code JÁ processou o guard de Stylelint — reportou que é **inexistente + Tier 0 (tooling = humano/[W])** e **já devolveu o drift count** (`cowork-financeiro-bundle.css` 188 hex, `--bubble-me` 220, `vibeAccent('workspace')` 220) grepando o `main`. Gerar prompt novo = **re-mandar processado = regressão L-09**. (c) **hardcodei o arquivo errado**: meu `ignoreFiles` apontava `tokens.css`, mas `LARAVEL_REPO_CONTEXT §10.4` diz que a fonte real é `resources/css/app.css` — eu adivinhei em vez de deixar o Code grepar.
- **Regra (pré-flight antes de QUALQUER `PROMPT_PARA_CODE`/ponte):** 1) **Isto precisa ir pro git?** Faxina de arquivo Cowork-only NÃO vai. 2) **Ler `CODE_NOTES.md` + `SYNC_LOG.md`** — o Code já tratou? já é Tier 0 aguardando [W]? então não re-enviar. 3) **Cruzar o canon que diz ONDE vive** (§10.4 = `app.css`) antes de citar arquivo. 4) **Dar INTENÇÃO, não config**: mandar o Code grepar o `main` pra achar o padrão (ele faz melhor — read-only no repo real, eu não). "Procure o padrão" > "edite exatamente este arquivo".
- **Bônus:** §10.4 também **resolveu o P3** do diagnóstico (roxo universal `--accent` + cor-por-origem só nos badges `--origin-*`) — uma "decisão Tier 0 em aberto" que eu inventei já era canon. Cruzar o canon mata decisão-fantasma.
- **Ref:** sessão 2026-05-31; `CODE_NOTES.md` (2026-05-31, fila processada); `LARAVEL_REPO_CONTEXT §10.4`; L-09 (sync stale), L-10/L-19 (cascata accent), L-11 (não reinventar canal); `REGRAS_STYLELINT_CSS.md §2/§7` (corrigido).

## L-21 — Criar `.html` novo na raiz (e DOIS pro mesmo tema) em vez de estender o que existe / usar o layout único
- **Erro (2026-06-01):** numa sessão criei **2 HTMLs novos na raiz** pro mesmo assunto — `Governança - Avaliação Champion CC.html` + `Governança Scorecard vs Estado-da-Arte CC.html`. O Scorecard é **variação/evolução** do Champion (mesmo tema, mesmos dados) → devia ser **UM doc iterado**, nunca um 2º arquivo. Ancorei no precedente errado (`Estrutura de IA - Avaliação`, `Diagnóstico ... v2` soltos) em vez da regra `ARQUIVO PRINCIPAL ÚNICO` + da direção da **faxina 2026-05-30** (que MOVEU 16 HTMLs de exploração pro `_arquivo/` pra PARAR a proliferação).
- **Sintoma:** HTML novo na raiz "durando no tempo"; variação tratada como arquivo novo (mesmo padrão do `Diagnóstico v2.html` que já vazara); contradiz "no-duplicate / 1 tema = 1 doc" e a faxina. [W]: *"a regra de não duplicar e durar no tempo está ativa... foi criado um arquivo novo de layout e não foi colocado no único layout permitido, isso é falha."*
- **Regra (pré-flight ANTES de QUALQUER `write_file` de `.html` — REGRA DE OURO gate 1):** (1) É **tela/módulo/variação de ERP**? → **rota ou Tweak em `Oimpresso ERP - Chat.html`**, NUNCA arquivo novo. (2) É **relatório/avaliação meta**? → **já existe um irmão pra ESTENDER** (`Estrutura de IA`, este)? então **edito/itero ESSE**, não crio outro; **nunca `vN.html`**. (3) Toda iteração de tema existente = **editar o arquivo**, não novo; variação = Tweak. (4) Se vira canon = proposta §10.4. **Antes**: `ls` raiz + grep do tema. Erro vira gate (L-16): propor guard Cowork-side "1 tema = 1 doc / no-new-root-html".
- **Ref:** CLAUDE.md "🔒 ARQUIVO PRINCIPAL ÚNICO"; **canon-de-registro = `memory/proibicoes.md`** (proposta de canonização em `COWORK_NOTES.md` 2026-06-01, estende a regra "não criar arquivo em `memory/` sem checar duplicação" — Tier 0, espera [W]); STATUS faxina 2026-05-30 (`_arquivo/`); L-07 (registro move pra `_arquivo` + lápide, não deleta); L-11/L-20 (não reinventar/duplicar; estender o canon); REGRA DE OURO gate 1.

## L-22 — Mover/consolidar arquivo SEM deixar a trilha do tempo (lápide + evolução no fim) = L-07 pela metade
- **Erro (2026-06-01):** consolidei 5 relatórios no `metricas.html` e **movi os originais pro `_arquivo/` sem deixar rastro** — nem lápide na origem (origem→destino), nem bloco de evolução no fim do arquivo novo dizendo o que ele supersedeu e pra onde o anterior foi. [W]: *"não colocou a regra do tempo no fim do arquivo, onde mostra a evolução do arquivo e indica para onde foi arquivado o anterior."*
- **Sintoma:** arquivo consolidado sem changelog visível; original sumindo da raiz sem ponteiro → quem chega depois não sabe que `metricas.html` veio de N arquivos, nem onde eles estão. Fere a durabilidade ("durar no tempo") que a própria regra busca.
- **Regra (Trilha do Tempo — concretiza L-07):** (1) **No fim de todo artefato vivo** (HTML de app/relatório, doc canônico) vai um bloco **`Evolução`/`Trilha do tempo` append-only**: `data · o que mudou · o que superseder · → pra onde o anterior foi arquivado`. Ao evoluir, **append acima** (nunca reescreve). (2) Ao **mover/consolidar**, deixa **lápide** na origem **ou** num `_arquivo/<pasta>/INDEX.md` com **origem → destino + o que substituiu**. (3) Nada some sem rastro legível **no próprio arquivo**. Aplica a HTML (comentário no fim), `.md` (seção "## Evolução" no rodapé) e ao mover (lápide).
- **Ref:** L-07 (append-only / lápide — esta é a forma concreta); `metricas.html` (1ª trilha) + `_arquivo/relatorios/INDEX.md` (1ª lápide-índice); STATUS faxina 2026-05-30 (`_arquivo/INDEX.md` é o precedente); proposta de canonização em `COWORK_NOTES.md` 2026-06-01 (→ `proibicoes.md` Memória/governança + BRIEFING §7).

## L-23 — Construí a tela de venda FORA do sistema: reincidi L-02 (paleta `--p-*` inventada) + L-21 (.html novo na raiz) por pular o pré-flight de DS/lições
- **Erro (2026-06-02):** pedido "faça uma tela de venda". Eu (a) criei `Venda Estado-da-Arte - NF-e + NFS-e.html` **novo na raiz** com um protótipo embutido; (b) nele inventei **vocabulário visual paralelo** — classes `.pos/.doc/.fis-chip` e **tokens próprios** `--p-accent/--p-serv/--p-prod` em **oklch cru** — em vez de usar `.vendas-scope{--accent}` + componentes do DS; (c) não evoluí o `vendas-create-page.jsx` que **já existia** e era sofisticado. Tudo porque, no início do chat, li STATUS/COWORK_NOTES/tokens/benchmark **mas NÃO** li `ds-v5/components.css`, `REGISTRY_DS_COMPONENTES` (git) nem este `LICOES_CC`.
- **Sintoma:** as defesas existiam e **não dispararam** — porque a falha foi de *leitura*, não de regra. Reincidência tripla: **L-02** (inventar paleta), **L-21** (.html novo na raiz / variação como arquivo novo, mesma semana em que L-21 foi escrita), **L-05** (não reler a lei antes de construir). Só parei porque [W] redirecionou ("não perca o foco, volte pra Oficina"). É o elo fraco do TESTE-04 (`PROCESSO_MEMORIA_CC §6`) confirmado no eixo do **DS**.
- **Classificação dos sub-erros:** E1 paleta/vocabulário paralelo (reincide L-02, grav. 8) · E2 .html novo na raiz (reincide L-21, grav. 7) · E3 build-antes-de-ler-DS/lições (**raiz**, grav. 9) · E4 não confirmei foco com `questions_v2` (grav. 4).
- **Regra (Pré-flight de BUILD VISUAL — entra na Regra de Ouro, always-read; dispara ANTES de qualquer `write_file` de tela/CSS):**
  1. **Ler o DS primeiro:** `ds-v5/tokens.css` + `ds-v5/components.css` + `REGISTRY_DS_COMPONENTES.md` (git) + `_PROPOSTA-ds-harmonizacao.md` + `LICOES_CC`. Sem isso, **não construo**.
  2. **Reuse-first:** componente do registry/shared; cor **só** por `.<tela>-scope{--accent: …}`; **proibido** `--<prefixo>-*` em oklch cru ou classe-componente nova que duplica o DS (é L-02). Criar novo só se inédito → registrar.
  3. **No host único:** tela/variação = rota/Tweak em `oimpresso.com.html`; **evoluir o `*-page.jsx` existente**, nunca `.html` novo na raiz (é L-21). Conferir com `ls`+grep se a tela já existe.
  4. **Ambíguo/grande?** `questions_v2` antes de produzir (escopo + foco).
- **Remediação pendente:** `Venda Estado-da-Arte - NF-e + NFS-e.html` (+ `venda-arte/`) é artefato paralelo — arquivar em `_arquivo/` com lápide (L-22) **ou** dobrar o conteúdo fiscal no `vendas-create-page.jsx`. Aguarda OK de [W] (CLAUDE.md: remover .html migrado só após confirmar).
- **Graduação:** **MEC** — estende o guard de higiene Cowork proposto em L-21/L-22 (`no-new-root-html`) com `no-raw-oklch-in-page-css` + `no-parallel-DS-vocab` (espelha eslint `ds/*` / stylelint hex do repo, mas Cowork-side). Enquanto não existe, vive na **Regra de Ouro gate 1** (always-read) + ponteiro DS no STATUS.
- **Ref:** L-02 (paleta), L-05 (reler a lei), L-21 (.html raiz), L-22 (lápide); `_PROPOSTA-ds-harmonizacao.md` ("DS é piso, não teto"); `ds-v5/components.css`; `REGISTRY_DS_COMPONENTES.md`; `PROCESSO_MEMORIA_CC.md` TESTE-04 (elo fraco = disciplina de leitura); CLAUDE.md "ARQUIVO PRINCIPAL ÚNICO".

## L-24 — Generalizei componente sem varrer call-sites (casos "sumiram") + runner DOM-grep não é estado da arte
- **Erro (2026-06-02):** (a) generalizei `CasosRunner` com prop nova `casos`, mas o render da Oficina ficou no formato antigo (sem passar `casos`) → painel **vazio** ("sumiram os casos"). (b) Mais fundo: runner in-app com **seletor CSS frágil** (`.prod-col`) + registry na mão **não é estado da arte** — quebra em silêncio no refactor e dá **falsa confiança** (presença ≠ correção).
- **Sintoma:** regressão **pega por [W], não por mecanismo** → escape no benchmark (§11). DS-GUARD/static não pegam (não é paleta/`.html`; o grep achava o componente "presente").
- **Regra:** (1) refactor que muda **assinatura** de componente → grepar **TODOS os call-sites** + **self-guard** que falha alto se montar vazio. (2) Teste durável = **estado da arte**: **locators resilientes** (`getByRole`/`data-testid`, nunca classe CSS) · **Playwright** (E2E/CI, auto-wait) · **Storybook play-functions** (mesma interação on-demand **e** CI, fonte única) · **`casos.md`** spec BDD. Runner DOM-grep = **ponte 80/20**, não destino.
- **Ref:** `OficinaProducao.decisoes.md` D-09; ADR 0243 R5; `memory/decisions/_PROPOSTA-0244-estrategia-teste-estado-arte.md`; benchmark §11 escape 2026-06-02.

## L-25 — Oferecer "A ou B?" em ação de melhor-óbvio = fricção + risco de [W] responder errado
- **Erro (2026-06-02):** fechei vários turnos com "quer que eu faça X?" sendo que X era a **melhor ação clara** (self-guard, etc.). [W]: *"por que está me perguntando, faça o melhor; se eu responder errado pode gerar problema — isso não deveria ter opção de resposta."*
- **Sintoma:** decisão de no-brainer empurrada pra [W] → fricção (zero-toque), e abre porta pra resposta errada que cria problema. Viola L-15/CLAUDE.md ("executo direto, não pergunto A/B").
- **Regra:** quando a melhor ação é **clara, reversível e não-Tier-0**, **faço direto** e reporto — sem opção. Só pergunto quando é genuinamente **subjetivo/Tier-0** (estética, estratégia, prioridade, dinheiro, lei) **ou** ambíguo de verdade. "Quer que eu…?" em ação óbvia = erro meu.
- **Ref:** L-15 (auto-gerar ponte sem perguntar); CLAUDE.md zero-touch; ADR 0243 R8 (Tier-0 = [W]; resto [CC] age).

## L-26 — Margem negativa no root de página AppShellV2 "cancelando" padding que o shell NÃO tem = tela cortada sob a sidebar
- **Erro (2026-06-10):** `Board.tsx` (OficinaAuto) com `-m-6 min-h-[calc(100vh-3rem)]` no root, assumindo que o AppShellV2 envolve a página com `p-6` — mas `.main-body` (cockpit.css) é flex column **sem padding**, e a topbar 3rem está extinta (hideTopbar default desde 2026-05-17). Margem negativa em scroll container cria overflow **inalcançável** à esquerda/topo → header, KPIs e 1ª coluna do kanban permanentemente cortados sob a sidebar. Descoberto por **[W] na tela, de novo** (escape de mecanismo — nenhum guard pegou). Sweep achou o mesmo padrão órfão em **11 telas** (Cliente/*, Modules/Index, Sells/*, Vehicles/Index).
- **Sintoma:** tela "não encaixa" / cortada sob a sidebar em produção; scrollbar horizontal no main-body; conteúdo inalcançável mesmo rolando. O protótipo Cowork estava certo — a divergência era no port.
- **Regra:** página de AppShellV2 **NUNCA** usa margem negativa no root pra "cancelar" padding do shell — o `.main-body` **não tem padding**. Kanban/grid largo **SEMPRE** rola por dentro (wrapper `overflow-x-auto` + `repeat(n, minmax(Xpx, 1fr))`), nunca estoura o shell. Antes de assumir padding/altura do shell, **ler cockpit.css** (canon `.prod-kanban` do protótipo: oficina-page.css).
- **Ref:** [memory/sessions/2026-06-10-board-oficina-corte.md](sessions/2026-06-10-board-oficina-corte.md); PR #2508 (fix + sweep 11 telas); cockpit.css l.145-190; canon `.prod-kanban` (prototipo-ui styles.css l.3798); L-24 (escape pego por [W], não por mecanismo).

## L-27 — Merge com o head do PR travado no commit velho (GitHub não sincronizou o push) = merge-stale silencioso
- **Erro (2026-07-01):** PR #3501 mergeado enquanto o head do PR ainda apontava pro commit **anterior** (`23ccfb49`) — o push do fix (`feec2a54`) já estava no branch (confirmado por `gh api .../branches/<b>` e `git ls-remote`) mas o GitHub **não sincronizou o head do PR** nem disparou os workflows no commit novo. O merge levou a **versão velha/quebrada** pro main (UC-S11 rodando em lista vazia). Corrigido por hotfix cherry-pick #3506.
- **Sintoma:** `gh pr checks` mostra check de um run **antigo**; `gh pr view N --json headRefOid` ≠ `git rev-parse origin/<branch>`; workflows do commit novo "não aparecem". Confunde com "CI ainda rodando".
- **Regra:** **antes de mergear, conferir `PR head == commit do branch`** — `[ "$(gh pr view N --json headRefOid -q .headRefOid)" = "$(git rev-parse origin/<branch>)" ]`. Se divergir, GitHub está atrasado: **não mergear**; forçar re-sync (re-push/empty-commit ou close+reopen) e esperar o head bater + o check do commit certo. Corolário: `E2E Playwright` **não é required** no main (só `casos-gate`+`dominio-gate` dessa família) → check vermelho não-required não bloqueia, então o merge-stale passa calado.
- **Ref:** [memory/sessions/2026-07-01-incidente-devolucao-rotalivre-namespace-menu.md](sessions/2026-07-01-incidente-devolucao-rotalivre-namespace-menu.md); PRs #3501 (stale) → #3506 (hotfix); L-24 (escape pego por [W], não por mecanismo).

> 1ª aplicação do loop da camada 5. Cada lição classificada: **MEC** (vira check) ou **JULG** (vira regra-carregada) + destino + status. Achado: **a maioria já se graduou** sem o loop ser nomeado; o valor agora é fechar as **pendentes** e comprimir o resto.

| L | Classe | Destino da graduação | Status |
|---|---|---|---|
| L-01 legislar lei própria | JULG (+grep parc.) | CLAUDE.md ritual + Regra de Ouro gate 3 | ✅ carregada |
| L-02 inventar paleta | MEC | `ui:lint`/eslint `ds/*`/stylelint hex (#2054, ESLint 0209) | ✅ check |
| L-03 proposta como firme | JULG | Regra de Ouro gate 3 + L-13 | ✅ carregada |
| L-04 rascunho vs entrega | MEC parc. | health-check PR sem `critique-score`≥80 (§6) | ✅ check parc. |
| L-05 não reler a lei | JULG | CLAUDE.md ritual (always-read) | ✅ carregada |
| L-06 afirmar commit | JULG | CLAUDE.md limite op. + Regra de Ouro gate 2 | ✅ carregada |
| **L-07 delete sem lápide** | **MEC** | **CHECK novo: nega delete `.md`-registro/`decisions/*` sem lápide/INDEX** | ⏳ **PENDENTE → colher** |
| L-08 tocar constituição | MEC parc. | ADR 0238 + `AdrNumberCollisionTest` + §10.4 | ✅ check parc. |
| L-09 prompt stale numeração ADR | MEC | §10.4 gate + `AdrNumberCollisionTest` | ✅ check |
| L-10 re-tematizar s/ cascata | JULG (+lint) | regra + anti-drift lint (parc.) | ✅ carregada |
| **L-11 reinventar canal Code** | **MEC** | **Regra de Ouro gate 1 (carregada) + CHECK Cowork: nega arquivo-canal novo na raiz** | ⚠️ carregada · check Cowork ⏳ |
| L-12 sync sem ler git | JULG | pré-flight + §10.4 | ✅ carregada |
| L-13 oficial = git | JULG | L-13 + ADR 0239 | ✅ carregada |
| **L-14 charter por tela** | **MEC** | **charter health-check (cobertura) + `charter_stale` (FRESCOR-DE-TELA)** | ⏳ **graduando (na ponte FRESCOR)** |
| L-15 auto-gerar ponte | JULG | CLAUDE.md zero-touch | ✅ carregada |
| L-16 cobrança = máquina | META | **é a base deste loop** | ✅ princípio |
| L-17 melhoria=proposta+soberania | JULG | §10.4 + ADR 0238 + L-17 | ✅ carregada |
| L-18 Handoff via bundle | MEC parc. | README bloco Code-first (entry do Handoff) | ✅ carregada |
| L-19 fonte accent = app.jsx | FATO | CONTEXTO de domínio + anti-drift lint | ✅ fato |
| L-20 ler CODE_NOTES antes | JULG | pré-flight Regra de Ouro | ✅ carregada |
| **L-21 .html novo na raiz** | **MEC** | **CHECK: `no-new-root-html` / 1-tema-1-doc (a própria L-21 propõe)** | ⏳ **PENDENTE → colher** |
| **L-22 mover sem trilha do tempo** | **MEC** | **CHECK: mover p/ `_arquivo/` sem lápide no INDEX** | ⏳ **PENDENTE → colher** |

**Veredito da colheita:** 15 carregadas/fato · 5 já em check (L-02/04/08/09/+L-18) · **4 pendentes mecanizáveis** = **L-07, L-11, L-21, L-22** (todas **higiene de filesystem do Cowork** → graduam como **guard Cowork-side + entrada na Regra de Ouro**, NÃO como CI do repo Laravel — L-13/L-20). L-14 gradua junto da ponte `FRESCOR-DE-TELA`.

**Ação da colheita (proposta, via `COWORK_NOTES`):** um **guard único de higiene Cowork** (`no-new-root-html` + `no-new-channel-file` + `move-requer-lápide` + `delete-registro-requer-lápide`) cobrindo L-07/11/21/22 de uma vez — porque são todos greppáveis e da mesma família. Enquanto não existe, os 4 já estão na **Regra de Ouro gate 1** (always-read), então não dependem de memória.
