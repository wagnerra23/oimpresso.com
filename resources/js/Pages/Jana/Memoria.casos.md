---
id: resources-js-pages-jana-memoria-casos
casos: Jana Memória · fatos aprendidos · LGPD Art. 18 · /ia/memoria
irmaos: Memoria.charter.md (lei) · memory/requisitos/Jana/RUNBOOK-memoria.md (runbook)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-09-03"
---

# Casos de uso — /ia/memoria (Memória da Jana)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Derivados do `Memoria.charter.md` (Goals/Non-Goals/Anti-hooks) + do protótipo `JmMemoria`
> (`prototipo-ui/cowork/jana-merge.jsx`, lido no DesignSync 2026-08-07) — **não** do `.tsx`.
> Derivar do código seria tautológico (§5 2026-06-05): passaria verde mesmo com o comportamento
> errado, que é exatamente o que estava acontecendo aqui.
>
> **Estado medido ANTES desta leva (2026-08-07):** o charter mandava, desde 2026-05-16, registrar
> "autor/quando/**motivo**" no activitylog e proibia "update direto sem activitylog". O código
> validava só `fato`, o `useForm` mandava só `fato` (0 hits de `motivo`) e **nenhum teste mordia**.
> A lei existia e valia zero — é o caso do charter que não é lei porque ninguém a executa.

> **Revalidação 2026-08-26 — PR #6298 (DS onda 1), head `b011221e50`.** Os 5 UCs são de
> servidor e o diff é só de markup: `<textarea>`→`Textarea`, `<label>` envolvente→`Label` com
> `htmlFor`, e os dois empty states→`EmptyState` canon. O `podeSalvar` continua exigindo `fato`
> **e** `motivo` (`Memoria.tsx:96`) e os dois `aria-label` seguem lá — que é o que o UC-MEM-01
> chama de conveniência da UI.
>
> Recibo: na lane do CI estes 5 casos saíram **5 skipped, 0 asserções** (o seed do CI não tem um
> `Admin#{biz}`, e o bootstrap pula) — skip sai exit 0 e não prova nada. Rodados no CT 100
> (`oimpresso-staging`, `main @ c01ee7615`) em 2026-08-26: **5 de 5 passam**.

## UC-MEM-01 — Editar um fato SEM motivo é rejeitado
Status: 🧪 (MemoriaEdicaoMotivoTest · lane `jana-pest.yml`, MySQL real)
O titular abre a edição de um fato e tenta salvar sem preencher o motivo. O servidor **reprova**
antes de tocar o driver de memória — a UI desabilitar o Salvar é conveniência, não garantia
(contornar a tela não contorna a regra). Âncora: charter Anti-hooks *"⛔ Update direto sem
`activitylog` — quebra audit trail LGPD Art. 18"* + protótipo (`disabled={!rascunho.fato.trim() || !rascunho.motivo.trim()}`).
**Pronto quando:** `PATCH /ia/memoria/{id}` sem `motivo` volta com erro de validação em `motivo`
e **não** grava nada. _(O teste checa também que a resposta é 302 — anti-vácuo: 403/404 fariam o
assert passar por engano.)_

## UC-MEM-02 — Motivo em branco ou curto demais não conta como motivo
Status: 🧪 (MemoriaEdicaoMotivoTest)
`''`, `'   '` e `'ok'` são reprovados. Espaço em branco satisfazer um campo obrigatório é a forma
mais barata de esvaziar a regra sem parecer que se esvaziou.
**Pronto quando:** os três payloads voltam com erro em `motivo` (mínimo 3 caracteres, `string`).

## UC-MEM-03 — Editar COM motivo grava a trilha com autor + motivo
Status: 🧪 (MemoriaEdicaoMotivoTest)
Com motivo preenchido a correção passa e nasce **uma** linha em `activity_log` sob
`log_name = jana_memoria_fato_editado`, com `causer_id` (quem editou) e `properties.motivo`.
Âncora: charter Goals *"Editar fato inline … com `activitylog` registrando autor/quando/motivo"*
+ copy do protótipo *"Toda alteração registra autor e motivo no log de auditoria"*.
**Pronto quando:** a contagem sobe exatamente 1, `causer_id` não é nulo e `properties.motivo`
bate o texto enviado.

## UC-MEM-04 — PII do motivo é redigida antes de persistir
Status: 🧪 (MemoriaEdicaoMotivoTest)
O motivo é prosa digitada pelo titular — pode conter CPF. Passa por `PiiRedactor` antes de ir pro
`activity_log`, que **nunca é purgado** (`Modules/Jana/Config/retention.php`: *"activity_log é
AUDITORIA — NUNCA purgada"*). Âncora: charter Anti-hooks *"⛔ Render texto fato sem `PiiRedactor`
se contém CPF/CNPJ — Tier 0 LGPD"* + a decisão já tomada na entity (`logOnly([...])`, comentário
*"NÃO logga `fato`/`metadata` (PII livre)"*).
**Pronto quando:** motivo com CPF é gravado como `[REDACTED:CPF]` e o número cru não aparece.

## UC-MEM-05 — Esquecer um fato também deixa trilha
Status: 🧪 (MemoriaEdicaoMotivoTest)
Apagar é "alteração" pro Alert da tela. Trilha que registra edição mas não exclusão é trilha
quebrada — daria pra apagar um fato sem deixar rastro, que é o oposto do Art. 18. **Não** exige
motivo: o protótipo confirma o apagar inline, sem campo.
**Pronto quando:** `DELETE /ia/memoria/{id}` acrescenta 1 linha sob `jana_memoria_fato_esquecido`.

---

## Fora do alcance do Pest de Controller (⬜ contrato visual / smoke real)

> Honestidade de escopo: os itens abaixo são do charter e do protótipo, mas são **client-side** —
> Pest de Controller não morde. Ficam pro contrato visual + smoke real (RUNBOOK-memoria.md passo 6).

- ⬜ **Salvar desabilitado** enquanto texto **ou** motivo estiverem vazios (o servidor já é travado por UC-MEM-01).
- ⬜ **Copy literal do Alert** — "Memória da Jana — LGPD Art. 18" + "Você vê, corrige e apaga qualquer fato que a Jana aprendeu sobre o seu negócio. Toda alteração registra autor e motivo no log de auditoria."
- ⬜ **Rodapé da edição** "Toda correção registra autor, horário e motivo."
- ⬜ **Busca** "Buscar em fatos…" e **filtro por categoria** — implementados; a lista agora FILTRA
  em vez de AGRUPAR. Os chips são **derivados do dado**, não da lista literal do protótipo: aquela
  (`preferência/operação/financeiro/…`) é a taxonomia do mock do Martinho, a de produção é outra
  (`CATEGORIA_LABELS`). Traduz-se o comportamento, não a lista (§5 2026-07-16).
- ⬜ **Confirmação inline** do apagar, no lugar do `confirm()` nativo — implementada.
- ⬜ **Os DOIS empty states** ("nada aprendido" × "nenhum fato com esse filtro" + Limpar filtro) —
  implementados com a copy literal. Produção tinha só um, com texto diferente.

## Divergências protótipo × produção — decididas

- ✅ **Escala de relevância — FICA `/10`.** Decisão [W] 2026-08-07: a **produção é a fonte** e o
  protótipo (que desenha 1–5) é que se adapta. Mudar seria migração de `metadata.relevancia` já
  gravado, sem razão de domínio que a justifique. **Não re-propor** sem sinal novo: divergência
  protótipo×prod não é, por si, motivo pra mexer em dado persistido.
- ✅ **`origem` passou a ser renderizada** (decisão [W] 2026-08-07, PR à parte). O dado já vinha no
  payload e não aparecia — o titular via *o que* a Jana aprendeu, mas não *de onde*. Fecha o Goal 4
  do charter (*"Mostrar `origem` do fato (chat / brief auto / inserção manual) — transparência"*).

## Revalidação de 2026-08-17 — por que o `last_run` subiu

O G-6 acusou `stale:` porque o `Memoria.tsx` mudou depois do `last_run` de 08-08. O que mudou:
as 5 pills de categoria (`meta` `preferencia` `restricao` `contexto` `acao_pendente`) e o
fallback saíram de `bg-<cor>-100 text-<cor>-800` — **escala crua sem par `dark:`, ilegível no
tema escuro nas cinco** — para as variantes soft do `<Badge>`, que carregam light+dark no token.

**Interseção com os UCs desta tela: nenhuma.** Os cinco tratam de comportamento de servidor
(motivo obrigatório na edição, trilha em `activity_log`, redação de PII, trilha no esquecer);
o diff não toca controller, service, validação nem payload — só qual `variant` o chip recebe.
Por isso o bump é do `last_run`, e nada de `Status:` mudou: os cinco seguem `🧪`, como estavam.

Registrado porque o §5 de 2026-07-27 já cataloga esta classe: mudança semanticamente inerte
**não é inerte pro gate** — o G-6 mede data de git, não semântica. O `last_run` só sobe com o
motivo escrito ao lado; subir o número calado é o que ele existe pra impedir.

- `[BACKLOG]` a legibilidade das pills no tema escuro virou contrato de fato nesta tela, e hoje
  nenhum UC a cobre — ficaria como UC quando existir teste que a prove (visual-regression da
  aba Memória ou asserção de `variant`). Sem id de propósito: UC sem teste que o cite quebra o
  G-2, e prometer teste que não existe é pior que a ausência.

## Ainda ABERTAS (sem decisão)

- ❓ **Edição parcial** — o protótipo edita `categoria` e `relevância`; a produção só edita o texto.
- ❓ **Naming** — a tela ainda diz "O Copiloto lembra de você" (h1 + título do shell) enquanto o
  resto do módulo já é "Jana" (`RUNBOOK-chat.md`: *"Em texto novo sempre Jana"*). Mexer no título
  toca o breadcrumb do shell, então não é edição de 1 linha.

## Revalidação de 2026-08-18 — por que o `last_run` subiu (2ª vez)

O G-6 acusou `stale:` de novo, agora porque o `Memoria.tsx` mudou depois do `last_run` de 08-17.
**O que mudou:** o `breadcrumbItems` foi removido do `AppShellV2` — **dado morto**, pela mesma razão
registrada no `Index.casos.md` (o shell só renderiza breadcrumb sob `!hideTopbar`, e o default é `true`).

**Interseção com os UCs desta tela: nenhuma.** Os cinco tratam de comportamento de servidor (motivo
obrigatório, trilha em `activity_log`, redação de PII, trilha no esquecer). O que saiu nunca renderizou.
Os cinco seguem `🧪`.

É a segunda revalidação inerte seguida deste arquivo — e as duas pelo mesmo motivo estrutural: o G-6
mede **data de git**. Continua correto que ele meça assim; o preço é esta nota, e o preço é barato.

**E depois, na onda 2 da paridade:**

## Revalidação de 2026-08-18 — o mesmo dia, pela onda 2 da paridade

O G-6 acusou `stale:` porque o `Memoria.tsx` mudou depois do `last_run` de 08-17. O que mudou, na
onda 2 da paridade da área Jana ([#5919](https://github.com/wagnerra23/oimpresso.com/pull/5919)):

- a Page passou a declarar e destruturar a prop `janaContext` (`businessId` · `businessName`);
- o `<JanaAreaHeader active="memoria">` passou a receber `businessName`/`businessId`, props que
  ele **já aceitava** e ninguém mandava.

**Interseção com os UCs desta tela: nenhuma.** Os cinco tratam de comportamento de servidor (motivo
obrigatório na edição, trilha em `activity_log`, redação de PII, trilha no esquecer); o diff não
toca controller, service, validação nem o payload dos fatos — o `businessId` que a tela já usava
no corpo continua vindo como antes, e o `janaContext` só alimenta o header.

Por isso o bump é do `last_run` e **nenhum `Status:` mudou** — os UCs seguem exatamente como
estavam. Registrado porque o §5 de 2026-07-27 cataloga esta classe: mudança semanticamente
inerte **não é inerte pro gate** — o G-6 mede data de git, não semântica. O `last_run` só sobe
com o motivo escrito ao lado; subir o número calado é o que ele existe pra impedir.

**Lição de método desta rodada** (vale pra quem repetir o fluxo): rodar o `casos-coverage-guard`
**antes** de commitar dá verde falso — o G-6 lê a data do `.tsx` pelo **git**, então enquanto a
mudança está só no working tree ela é invisível pro gate. Rode-o **depois** do commit, ou
espere o CI dizer. Foi o que aconteceu aqui: o gate local passou, o do CI reprovou, e o certo
era o do CI.

## Revalidação de 2026-08-25 — por que o `last_run` subiu

O G-6 acusou `stale:` de novo, agora porque este PR foi rebaseado sobre o `main` depois de
**299 commits** — o `Memoria.tsx` mudou nesse intervalo, e a data de git é o que o gate mede.

**O que este PR muda na tela:** o breadcrumb do shell. **Interseção com os UCs: nenhuma** —
medido, não presumido: `grep -i breadcrumb` nos UCs deste arquivo devolve **zero**. Os casos
tratam de filtro, navegação por teclado, ARIA, isolamento Tier 0, leitura pura, PII e
contagem de histórico. Breadcrumb é chrome do shell; não toca nenhum deles.

**Nenhum `Status:` mudou.** O bump é só do `last_run`, com o motivo escrito ao lado — que é a
condição que o §5 de 2026-07-27 impõe: mudança semanticamente inerte **não é inerte pro
gate**, e o `last_run` só sobe acompanhado da razão.

## UC-MEM-06 — o selo de plano lê o PACOTE, não o cliente
Status: 🧪 (`JanaPlanoTierTest` — 3 `it()`, um comportamental e dois de fonte; o teste diz
por que cada um é o que é. Aguarda run verde na lane e o screenshot F1.5.)

Derivado da âncora (`prototipo-ui/cowork/jana-merge.jsx:970` + `chat-jana.jsx:217`) e da decisão
[W] de 2026-08-27 — **não** do `.tsx`. Derivar do código seria tautológico (§5 2026-06-05).

Até 2026-08-27 este selo era o item **BLOQUEADO** da onda 4 (`PARIDADE` §8.1), e o motivo não era
trabalho: **não havia de onde ler o plano**. O `ProController` mandava `'plan' => 'free'` literal;
não existia coluna, tabela nem chave de tier; e no protótipo o `pro` é um toggle de simulação, cuja
legenda diz *"aqui o Pro é simulação pra ver o gating"*. O `useJanaConfig` já recusava gravá-lo
*"porque o servidor não as honra"*.

O que mudou é a FONTE, não o desenho: `jana_pro_module` virou chave de pacote marcável no
Superadmin (sem billing — Asaas real segue Sprint JANA-B, ADR 0140), e o selo lê
`shell`/`jana.pro`, derivado da assinatura.

O caso defende três coisas, e a terceira é a que dói se quebrar:

| o que | por quê |
|---|---|
| o selo mostra `plano Pro` só com `jana_pro_module` no pacote | senão volta a afirmar estado que o sistema não sabe |
| `jana_module` e `jana_pro_module` seguem eixos SEPARADOS | fundi-los repete, dentro do código, o engano que um humano cometeu lendo o painel |
| sem pacote legível o degrade é `Grátis` | afirmar Pro a quem não é promete recurso pago; o inverso só omite |

---

## Revalidação de 2026-08-28 — o `.tsx` mudou de PATH de import, e só isso

O `casos-gate` acusou `stale:` nesta tela. A causa é mecânica: a pasta
`Pages/Jana/components/` (sem underscore) foi para o canon `_components/`, e o G-6 compara a
**data-git do `.tsx`** com o `last_run` — mudança semanticamente inerte **não é inerte pro
gate** (é a lápide §5 2026-07-27: comentário, whitespace e rename contam como "a tela mudou").

**Revalidação de contrato, medida:**

| O que conferi | Como | Resultado |
|---|---|---|
| o tamanho do diff nesta tela | `git diff origin/main...HEAD --numstat -- …/Memoria.tsx` | **2 linhas**, todas de `import` |
| o que mudou nelas | `git diff` das mesmas linhas | só o PATH de `FabJana` e `JanaAreaHeader` — nome, símbolo e uso idênticos |
| o componente mudou de conteúdo? | `git log --stat` do rename | **não** — o git detectou 100%% de similaridade nos dois arquivos |

**Interseção com os UCs: nenhuma.** Um caso de uso descreve comportamento de tela; path de
import não é comportamento. Nenhum `Status:` muda.

**Não rodei a suíte** — CT 100 respondeu 502 durante toda a sessão e Pest local é proibido
(ADR 0062). O bump é por revalidação de CONTRATO, e digo porque o G-6 aceita a data e só o
leitor percebe a diferença.

---

## UC-MEM-07 — Tier 0: fato de outro business não aparece nem é apagável
Status: 🧪 (`Modules/Jana/Tests/Feature/Http/MemoriaPermissaoTest.php` — 2 `it()`. Aguarda run verde
na lane `jana-pest.yml`, onde o alvo entrou no MESMO PR: registrar o arquivo no repo não é a lane
executá-lo, e aquela lane roda allowlist.)

Derivado do charter — Mission *"acesso `business_id` scoped strict — fato cross-tenant = bug Tier 0"*
+ Non-Goal *"⛔ Mostrar fato de outro business"* — e da [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
**Não** do `.tsx` (§5 2026-06-05).

O isolamento não vive no Controller: vive no `HasBusinessScope` do `MemoriaFato`, que filtra por
`session('user.business_id')`. Por isso o caso ataca pelos **dois** verbos — ler e apagar —, e não
só pela listagem: um `DELETE` que cruzasse o tenant apagaria dado alheio sem nunca exibi-lo.

**Pronto quando:** com sessão no tenant 98, `GET /ia/memoria` devolve **200**, mostra o fato próprio
e **não** mostra o texto do fato do tenant 2; e `DELETE /ia/memoria/{id-do-2}` deixa o fato do
vizinho com `deleted_at` **nulo**.
_(Anti-vácuo em duas pernas: o 200 + `assertSee` do fato próprio impedem que um 403/500 faça o
`assertDontSee` passar por engano; e o mesmo `DELETE` é exercido no fato PRÓPRIO, provando que o
verbo funciona — senão uma rota morta satisfaria o isolamento sem provar nada.)_

## UC-MEM-08 — LIMITE MEDIDO: hoje `jana.access` sozinho já edita e apaga
Status: 🧪 (`MemoriaPermissaoTest` — 1 `it()` + 1 controle)

⚠️ **Este caso não abençoa o estado atual — ele o MEDE, pra que pare de ser invisível.** O charter
prometia, desde 2026-05-16, um Anti-hook *"⛔ Permitir edit por user sem permissão
`copiloto.memoria.manage`"*. **Medido em 2026-09-02: a trava nunca existiu, e a key também não** —
o registry tem 22 keys, todas `jana.*`, e o `MemoriaController` não checa permissão nenhuma. A única
defesa é o `can:jana.access` do grupo `/ia`.

Numa tela LGPD isso importa: `jana.access` é a permissão de **entrar no módulo**; ela está separando
quem vê a Jana de quem não vê, não quem pode apagar um fato de quem só pode lê-lo.

**Pronto quando:** um usuário **não-admin** (o `Gate::before` libera qualquer ability pra
`Admin#{biz}` — por isso o teste tem um controle que assere `hasRole(...) === false`) cujo conjunto
de permissões é **exatamente** `['jana.access']` consegue `DELETE /ia/memoria/{id}` com 302 e o fato
fica soft-deleted.

**Quando a trava existir, este caso fica VERMELHO — e esse é o sinal, não o defeito.** A correção
então é trocá-lo pelo `UC-JPERM-07` da [emenda do Cowork](../../../../prototipo-ui/design-docs/cowork-inbox/JANA-CASOS-EMENDA-PERMISSAO-2026-08-27.md)
(*403 sem a permissão / 302 com ela*) e atualizar o `Memoria.charter.md`. O `UC-JPERM-07` segue ⬜ de
propósito: **qual key trava a escrita é decisão [W]** (§Gap de permissão do charter), e escrever o
UC antes da trava quebraria o G-2 do casos-gate — a própria emenda manda *"cada UC entra no mesmo PR
do seu teste, nunca antes"*.

---

## Revalidação de 2026-09-03 — a onda 4 da paridade mexeu na FORMA da linha

O `casos-gate` vai acusar `stale:` porque o `Memoria.tsx` mudou depois do `last_run`. **Desta vez
não é inerte** — a onda 4 mudou a forma da linha do fato e a largura da tela —, então o `last_run`
sobe **com dois UCs novos**, não só com a nota.

**Interseção com os 8 UCs anteriores: nenhuma.** Medido, não presumido: UC-MEM-01..05 são de
servidor (motivo obrigatório, trilha, PII, esquecer), UC-MEM-06 é a fonte do selo de plano e
UC-MEM-07/08 são permissão e Tier 0. O diff não toca controller, validação, rota nem payload —
`git diff --stat` desta leva lista `Memoria.tsx`, o spec novo e a lane. Nenhum `Status:` mudou.

## UC-MEM-09 — as ações da linha do fato se anunciam por TEXTO
Status: 🧪 (`tests/jana-memoria-linha.test.tsx`, 2 `it()` · lane `jana-conversas-gate.yml`, jsdom)

Derivado da âncora `JmMemoria` (`prototipo-ui/cowork/jana-merge.jsx`) — `.jm-fato-acts` são
`<button class="jm-btn ghost">Editar</button>` e `<button class="jm-btn ghost danger">Apagar</button>`,
rótulo visível — e do charter, cuja Mission é o titular **exercer** o Art. 18. **Não** do `.tsx`
(§5 2026-06-05). Até 2026-09-03 a produção usava botão-ícone (`Pencil`/`Trash2`) com `title`: numa
tela LGPD, a ação destrutiva só se identificava no hover.

**Pronto quando:** os botões da linha têm `textContent` "Editar" e "Apagar".

_(O assert é de `textContent`, **não** de nome acessível, e é o que faz o caso morder: um
botão-ícone com `title="Esquecer"` TEM nome acessível e passaria por `getByRole` sem exibir
rótulo nenhum — mediria o que eu escrevi, não o que a tela mostra.)_

## UC-MEM-10 — a linha apresenta o FATO antes da meta que o qualifica
Status: 🧪 (`tests/jana-memoria-linha.test.tsx`, 2 `it()` · mesma lane)

Derivado da âncora: `.jm-fato-bd` é `<p>{f.fato}</p>` e **só depois** `.jm-fato-meta` (categoria ·
origem · desde · relevância, numa linha só, mono 10.5px). A produção trazia invertido — pill,
relevância, origem e data no topo, texto embaixo —, então a linha abria pelo rótulo em vez de
abrir pelo que a Jana aprendeu, que é o objeto do direito de acesso.

**Pronto quando:** no DOM, o texto do fato precede a pill de categoria; e origem, data e
relevância vivem no mesmo container da pill.

_(A busca da pill é escopada à linha de propósito: "Preferência" aparece **duas** vezes na tela —
pill do fato e chip do filtro, que é derivado do dado. Buscar no documento casaria as duas e o
caso passaria por acidente.)_

### ⬜ Por que NÃO há UC de LARGURA, embora a onda 4 a tenha mudado

A onda tirou o `max-w-4xl mx-auto` (896px numa viewport de 2560) — era a única das quatro telas da
área presa numa coluna central, enquanto `Index.tsx`, `Chat.tsx` e a âncora ocupam a largura toda.
**Isso não vira UC aqui**: largura é propriedade **computada**, e jsdom não computa Tailwind.
Assertar a ausência da classe mediria o que eu escrevi, não o que o browser resolveu
(§5 2026-07-16). Quem mede é o `visual-regression` (esta tela está no `visreg-screens.json`) e a
sonda do `design-diff.mjs` — e o veredito de pixel é aprovação [W] no gate F1.5, não deste arquivo.

### Deltas MEDIDOS que a onda 4 aceitou (e não escondeu)

| item | âncora | entregue | por quê |
|---|---|---|---|
| raio da linha | 10px (cru, fora da própria rampa dela) | `rounded="lg"` = **8px** | o token do DS vale mais que o px cru do protótipo |
| padding | `11px 13px` | `p={3}` = **12px** | a escala do `Box` é enumerada por CVA, que recusa px cru em compilação |
| corpo | 13px / 1.5 | `--fs-4` = **13.5px** / 1.45 | a rampa `--fs-1..9` é a âncora única de tipografia (ADR 0253) |
| meta | 10.5px mono | `--fs-1` = **10.5px** mono | exato |
| pill | 10.5px · `2px 8px` · full | `Badge` = **12px** · `2px 8px` · full | padding e raio exatos; o tamanho é do componente do DS |

⚠️ **Fora desta onda, de propósito:** o rastro `editado por … · motivo` que a âncora mostra na meta
(é **backend** — o payload é `MemoriaPersistida::toArray()`, DTO `final readonly` de 8 chaves, e o
Controller não lê `activity_log`; ordem 1 do `Memoria-visual-comparison.md`) e os campos `Categoria`
e `Relevância` da edição inline (o `update` valida só `fato`/`motivo`; ordem 2 do mesmo doc).
Renderizá-los agora seria UI prometendo o que o servidor não cumpre.

## Achado colateral de 2026-09-03 — o empty state estava CERTO

A rodada medida de 2026-09-03 registrou que `jana_memoria_facts` tem **19 linhas** em `business_id=1`
enquanto `/ia/memoria` exibia o empty state, e mandou conferir o filtro antes de tratar como bug.
**Conferido em produção (2026-09-03) — é filtro legítimo, não defeito.**

A listagem não é por business: é `MemoriaFato::doUser($businessId, $userId)->ativos()`, que soma
três filtros — `business_id`, `user_id` e `valid_until` — mais o soft delete do trait. Medido:

| pergunta | resposta |
|---|---|
| linhas em `business_id=1` | **19** |
| distribuição por `user_id` | **`{"1": 19}`** — todas do usuário 1 |
| soft-deleted (`deleted_at` não nulo) | **14** |
| com `valid_until` no passado | **0** |
| `listar(1, 1)` pelo contrato ligado (`MeilisearchDriver`) | **5** |

Ou seja: o usuário 1 vê 5 fatos; **qualquer outro usuário do mesmo business vê o empty state, e
corretamente** — memória da Jana é por titular, não por empresa. O 19 é uma contagem de business
que ignora `user_id` e `deleted_at`; o denominador da tela é outro (§5 2026-07-27: denominador
inventado). Nada a corrigir no controller.
