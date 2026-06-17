---
title: "[AH] Red-team adversarial — loop handoff zero-paste (proposta handoff-loop-zero-paste)"
type: session
date: 2026-06-17
topic: "[AH] red-team do loop handoff zero-paste — furos verificados + auto-merge bloqueado"
authors: [C]
related: [0114-prototipo-ui-cowork-loop-formalizado, 0093-multi-tenant-isolation-tier-0, 0261, 0269, 0271]
target: memory/decisions/proposals/handoff-loop-zero-paste.md
veredito: NÃO LIGAR AUTO-MERGE — 3 furos letais + infra inteira a-construir
---

# [AH] ADVERSÁRIO — como o loop handoff zero-paste falha em produção

Papel: red-team. Meta única = fazer o loop **falhar em prod** (vazar tenant, mergear ruim verde,
ser fraudado, colapsar). Cada ataque ancorado em `arquivo:linha`/gate real. Não assumi boa-fé.

> **Resumo executivo:** a proposta é uma camada de segurança construída sobre **infra que não
> existe** (sig/ingest/ack/files_json = 0 linhas no git canônico) e **gates que não mordem o que ela
> auto-mergeia** (.tsx). Os dois gates que a proposta promete promover a required (E2) **fazem
> SKIP-AS-PASS em mudança de `.tsx`** — não rodam um único check. O score "determinístico" (E1) é
> regex binário que ignora se a tela renderiza, vaza dado ou tem XSS. Auto-merge na main **dispara
> deploy de produção sozinho** (`deploy.yml`). Veredito: **não ligar auto-merge.**

---

## TOP 10 MODOS DE FALHA (rankeados severidade×probabilidade)

### #1 — Vazamento cross-tenant via `.tsx` presentacional (sev 5 × prob 4 = 20) 🔴 LETAL
**Ataque:** Um handoff "presentacional" toca `resources/js/Pages/Cliente/Index.tsx` — uma lista
multi-tenant (biz=4 Larissa é tenant real). O arquivo está cheio de partial-reloads que dirigem
dados do servidor: `router.reload({ only: ['customers'], ... })` em
`Cliente/Index.tsx:423,484,631-632,788-789,1376-1385,2187`. O atacante (ou [CC] descuidado) **adiciona
um parâmetro de query** ao reload (`data: { scope: 'all' }`, `business_id: x`, `with_all: true`, ou
um filtro novo) ou troca `only:[]` pra pedir uma prop que o controller computa sem o mesmo escopo.
Resultado: o backend devolve dado de outro tenant — **sem tocar um único `.php`**.
**Evidência:** `Cliente/Index.tsx:411-423` ("search server-side via router.reload"),
`:460` ("escopo business_id Tier 0 ADR 0093"), `:703` (kpis scoped business_id). O escopo Tier 0
vive no controller, mas **o que o `.tsx` PEDE** controla a query.
**Por que os gates não pegam:** `multi-tenant-gate.yml:57-63` — o step `detect` só marca `relevant=true`
pra `Modules/.*/Http/Controllers/`, 2 middlewares e o próprio teste. `.tsx` **nunca** entra →
`relevant=false` → **"SKIP-AS-PASS (gate verde sem rodar)"** (linha 62). `tier0-guards-advisory.yml`
é varredura **estática de PHP** (`withoutGlobalScopes` em `.php`) — não olha `.tsx`. Promover os dois
a required (E2) **não muda nada**: continuam verdes sem rodar.
**Defesa mínima que mata:** `.tsx` é **inelegível a auto-merge se o diff tocar qualquer
`router.*`/`useForm`/`router.reload`/`Link href`/`only:`/`data:`/parâmetro de request**. Auto-merge só
pra `.tsx` cujo diff é provadamente **render-only** (JSX/className/copy), validado por um lint
dedicado que falha na presença de qualquer chamada de transporte. Sem isso, E2 é teatro.

### #2 — `files_json` scope-guard NÃO EXISTE; o `scope-guard.yml` atual não serve (sev 5 × prob 4 = 20) 🔴 LETAL
**Ataque:** A proposta diz (tabela §1) "scope-guard ✅ — estender pra ler files_json". Isso vende
como "já existe, só estender" o **núcleo inteiro da contenção de escopo**. Na verdade o
`scope-guard.yml` existente é **outra coisa**: compara controllers `.php` contra `SCOPE.md.contains[]`
do módulo (anti-drift de Constituição Art. 7), `bin/check-scope.php`. Ele **só roda em
`Modules/**/Http/Controllers/**.php` e `SCOPE.md`** (`scope-guard.yml:11-18`) e **não tem nada** a ver
com um manifesto `files_json` por-handoff nem com `.tsx`.
**Evidência:** `git grep files_json` no canon = **zero** ocorrências em Modules/app/.github/bin/scripts.
`scope-guard.yml:11-18` (paths) + `bin/check-scope.php:91` (regex `Modules/.../Controllers/...Controller.php`).
A contenção "aplica só em `files_json`" (§2 R-papéis, §5 proibição) é **100% a-construir**.
**Defesa mínima:** reconhecer que o scope-guard por-handoff é **código novo a escrever e testar com
fixture adversarial** (PR que toca arquivo fora do manifesto DEVE reprovar), não "extensão". Até
existir + ter meta-teste no `gate-selftest`, auto-merge é cego a drift de escopo.

### #3 — Auto-merge na main = deploy de produção automático, sem olho humano (sev 5 × prob 3 = 15) 🔴 LETAL
**Ataque:** `deploy.yml` publica em prod **a cada push na main** (`deploy.yml:12-19`), e o
`paths-ignore` **NÃO inclui `resources/js/**`** (ignora só `memory/**`, `**.md`, `prototipo-ui/**`,
`cowork-inbox/**`). Logo um `.tsx` auto-mergeado **dispara deploy de produção sozinho** —
build + ssh + swap + opcache reset + `migrate --force` (`deploy.yml:251-254`). Auto-merge + auto-deploy
= handoff malicioso/quebrado em prod servindo cliente real (biz=4 Larissa) em minutos, **zero humano**.
**Evidência:** `deploy.yml:13-19` (trigger push main, paths-ignore sem js), `:254` (migrate --force),
`:346-370` (maintenance off + opcache). Precedente vivo: `cowork-inbox.yml:56-62` já faz
`gh pr create` + `gh pr merge --squash` automático com `contents: write`.
**Defesa mínima:** auto-merge de handoff **não** vai direto pra main que deploya. Buffer obrigatório:
ou (a) acumula em branch de staging com deploy só manual, ou (b) `paths`-gate o deploy pra exigir
selo humano em qualquer `.tsx` que veio de handoff. "Reverter via git" (E4) **não desfaz** o que já
foi servido (ver #7).

### #4 — Score "determinístico" (E1) é gamável e cego a correção (sev 4 × prob 4 = 16) 🔴
**Ataque:** O `UiDeterministicScorer.php` (existe: `Modules/Jana/Ai/UiDeterministicScorer.php`) é
**regex binário sobre as linhas `+` do diff**, 6 dims, cada uma 10-se-zero-hits / 4-se-hit
(`:215-224`). Mede só: cor crua, elemento nativo, prefixo localStorage, ícone lucide, emoji,
status-fill. **Não mede** se a tela renderiza, se a prop vaza dado, XSS, lógica, ou se o visual está
quebrado. Goodhart trivial: escrevo um `.tsx` que **vaza tenant** e **tira 10 em todas as 6 dims** —
basta não usar `#hex`/`<input>`/emoji. O score sobe sem a tela melhorar; o determinismo **vira o
alvo**, não a qualidade. Pior: `dimAtalhos` (`:235-242`) **nunca penaliza** (sempre 10).
**Evidência:** `UiDeterministicScorer.php:80-110` (score), `:137-194` (os 6 regex), `:215-224`
(binário 10/4). É cópia dos regex de `prototipo-ui/audit/score-mechanized.mjs` (sync por
`scorer-sync-gate.yml`). A própria docstring admite: as 3 dims que importam (hierarquia, slot PT-01,
voz PT-BR) "ficam no juiz LLM" (`:65-69`) — e o LLM é **advisory** por E1.
**Defesa mínima:** o score determinístico **não é gate de qualidade** — é detector de regressão de
token. Não pode ser o "gate duro" que destrava auto-merge. O que destrava tem que incluir prova de
render + ausência das classes de #1/#5/#6, não regex de cor.

### #5 — XSS via `dangerouslySetInnerHTML` em prop não-sanitizada (sev 4 × prob 3 = 12) 🔴
**Ataque:** 28 Pages já usam `dangerouslySetInnerHTML`. O padrão seguro depende de sanitização
**server-side em PHP** — ex. `Site/BlogPost.tsx:77-81` (`HTMLPurifier` via `SiteContentService`). Um
handoff presentacional adiciona `dangerouslySetInnerHTML={{ __html: prop }}` numa prop **não
sanitizada** (nota de cliente, corpo de mensagem WhatsApp, descrição) — stored XSS, **sem `.php`**,
e o scorer dá 10 (não há regex pra dangerouslySetInnerHTML).
**Evidência:** `Site/BlogPost.tsx:80`; 28 arquivos com `dangerouslySetInnerHTML` (Whatsapp, Cliente,
Financeiro, Auditoria, kb, Essentials…). Scorer não tem regra pra isso (`UiDeterministicScorer.php:137-194`).
**Defesa mínima:** lint que **bloqueia** qualquer `dangerouslySetInnerHTML` introduzido por handoff
(allowlist explícita por revisão humana). Inelegível a auto-merge.

### #6 — a11y/visual: gates que a proposta cita NÃO cobrem a tela auto-mergeada (sev 3 × prob 4 = 12) 🔴
**Ataque:** E3 auto-mergeia `.tsx` de **Pages**. Mas `a11y-axe-gate.yml` é **path-scoped a
`resources/js/Components/ui/**`** — não dispara em `Pages/<Mod>/<Tela>.tsx`. E o jsdom "NÃO vê
contraste/foco" (comentário do próprio workflow). `visual-regression` pixel-diff é **ADVISORY**
(`visual-regression.yml:233 continue-on-error: true`) e cobre só 6 telas-núcleo. A proposta lista
"a11y AA ✅" como rede — **falso pro escopo real** do auto-merge.
**Evidência:** `a11y-axe-gate.yml` paths `resources/js/Components/ui/**`; `visual-regression.yml:223-258`
(advisory). Memória: visual-regression mergeou vermelho 2× em 24h (#2544/#2548).
**Defesa mínima:** não citar a11y/visual como rede de auto-merge enquanto não cobrirem Pages e não
forem required+estáveis. Hoje uma tela auto-mergeada passa **sem nenhum check de a11y rodar**.

### #7 — "Reverter via git" não desfaz dano já servido a cliente (sev 4 × prob 3 = 12) 🔴
**Ataque:** E4 promete `git revert` como backstop. Mas entre o auto-merge→auto-deploy e o [W] ler o
digest, a tela quebrada/vazadora **já serviu** biz=4 (Larissa). Se vazou tenant, o dado **já saiu**;
revert não recolhe. Se XSS, o payload **já executou** no browser de quem abriu. Append-only preserva
o histórico do código — **não desfaz o efeito colateral em prod**.
**Evidência:** `deploy.yml` auto-deploy (#3) + ausência de qualquer quarentena. A proposta §7 admite:
"reverter é via git revert, não bloqueio prévio".
**Defesa mínima:** o controle tem que ser **prévio** pras classes irreversíveis (tenant leak, XSS,
PII). Revert serve pra regressão visual, não pra vazamento.

### #8 — Digest diário depende do humano LER (E4 reintroduz o fio humano que a proposta queria cortar) (sev 3 × prob 4 = 12)
**Ataque:** A premissa da proposta é "[W] sai do meio". E4 devolve [W] como leitor obrigatório do
digest. Solo founder, 1 aprovador. Se [W] não lê por 1 semana (viagem, foco em vendas, doença),
**todos** os merges ruins daquela semana ficam em prod sem detecção. O "novo ponto de controle"
(calibrar gate + ler digest) é **o mesmo modo de falha** que a proposta diagnostica em §0
("gate na memória do humano") — só mudou de lugar.
**Evidência:** proposta §0.3 + §2 E4 + §7. Memória do projeto: "[W] único aprovador".
**Defesa mínima:** digest tem que ter **SLA de leitura forçado** — se não há ACK do digest em 24-48h,
o auto-merge **pausa** automaticamente (fail-closed), não continua acumulando.

### #9 — Replay / reuso de handoff assinado + supply-chain do pipeline Cowork (sev 4 × prob 2 = 8)
**Ataque:** O `sig` prova **autenticidade, não qualidade nem frescor**. Sem nonce/timestamp/
expiração + binding ao conteúdo+escopo, um handoff antigo assinado pode ser **re-injetado** (replay)
pra reverter uma tela. Pior vetor: o SECRET vive "no pipeline de export do Cowork" (§5, §6) — um
ambiente **fora do git, fora do controle de [W]**. Se o pipeline Cowork for comprometido,
`sig` válida + conteúdo malicioso = auto-merge confia cegamente. O `ingest` "rejeita sem sig" (§5)
não ajuda contra sig **legítima** de conteúdo ruim.
**Evidência:** infra `sig`/`ingest`/`ack` = **0 linhas no git** (`git grep handoff_secret|handoff.pending|
handoff.ack` → vazio). `McpTokenIssuer.php` emite/revoga/rotaciona token MCP (Tier 0 segredo, bom),
mas **não há nada** ligando isso a verificação de assinatura de handoff. Tudo a-construir.
**Defesa mínima:** assinatura precisa de (a) nonce + expiração curta + bind ao SHA do conteúdo E ao
`files_json`, (b) registro de nonces consumidos (anti-replay), (c) o SECRET nunca no Cowork — só no
servidor MCP, e o Cowork **solicita assinatura** via token escopado revogável (`McpTokenIssuer.rotate`).
Sem isso, `sig` é selo decorativo.

### #10 — Append-only vira reversão silenciosa de decisão ✅; race entre handoffs (sev 3 × prob 3 = 9)
**Ataque:** §5 diz "revisão = nova `version`; anterior vira `superseded`". Mas um handoff **novo**,
válido e assinado, pode silenciosamente **desfazer** uma decisão `✅` anterior (ex.: reintroduzir o
layout que [W] rejeitou) — append-only registra, mas **não impede** a regressão de mérito. E dois
handoffs concorrentes no mesmo `.tsx` (ou no mesmo `COWORK_NOTES.md`, canal único de R2) = **race**:
o segundo a mergear sobrescreve/conflita sem ninguém arbitrar. PROTOCOL §8 já proíbe ">2 telas em
F3" e "editar SYNC_LOG não-append" justamente por isso — a proposta não trata concorrência.
**Evidência:** proposta §5 (append-only) sem regra de conflito; PROTOCOL.md:127-128 (anti-padrões de
concorrência); R2 elege `COWORK_NOTES.md` como canal **único** (ponto de contenção serializada).
**Defesa mínima:** lock por arquivo-alvo (um handoff aberto por `.tsx` por vez) + diff do handoff novo
contra a última `✅` precisa de sinal explícito quando **reverte** região antes aprovada.

---

## VEREDITO POR EMENDA E1-E4

**E1 (gate duro = determinístico; LLM advisory) — É-INSUFICIENTE.**
Corrige um furo real (LLM-judge flaky como bloqueador era pior). MAS o substituto
(`UiDeterministicScorer`) é regex binário gamável (#4) que **não prova qualidade nem segurança**.
Tirar o LLM e pôr regex de cor como "gate duro" troca um juiz ruim por um juiz **cego**. O score
serve como anti-regressão de token, não como destravador de auto-merge.

**E2 (tier0/multi-tenant required antes de auto-merge) — É-INSUFICIENTE (a mais perigosa).**
Dá **falsa sensação de rede**. Os dois gates **não rodam em `.tsx`**: `multi-tenant-gate.yml:57-63`
faz SKIP-AS-PASS quando nenhum controller muda; `tier0-guards` é scan estático de PHP. Promovê-los a
required deixa o badge verde sem cobrir o escopo exato que E3 auto-mergeia (#1). É pior que não ter:
parece protegido. Só vira MITIGA-PARCIAL **se** acompanhada de um gate Tier-0 que efetivamente
inspecione transporte de dados em `.tsx` (props/only/query) — que não existe.

**E3 (auto-merge faseado, só Camada-4 `.tsx`/`.css`) — MITIGA-PARCIAL.**
A restrição a presentacional + nunca-`.php`/Shell/Fundações é a decisão mais sensata da proposta.
MAS "presentacional" está **mal definido**: um `.tsx` "Camada-4" pode vazar tenant (#1), injetar XSS
(#5), mudar store/contexto React global. A allowlist `.tsx`/`.css` **não é suficiente** — precisa de
allowlist por **conteúdo do diff** (render-only), não por extensão.

**E4 (vigia pós-merge + digest) — É-INSUFICIENTE.**
Reintroduz o humano que a proposta queria cortar (#8) e o controle é **posterior** ao dano
irreversível (#7). Vira MITIGA-PARCIAL só com SLA de leitura fail-closed (pausa auto-merge sem ACK).

---

## OS 3 FUROS QUE SOZINHOS JUSTIFICAM NÃO LIGAR AUTO-MERGE

1. **Os gates Tier-0 não mordem `.tsx` (E2 é teatro).** `multi-tenant-gate.yml:57-63` faz
   SKIP-AS-PASS em mudança de `.tsx`; `tier0-guards` só lê PHP. Um `.tsx` que altera `router.reload`/
   query (`Cliente/Index.tsx:423-789`) pode vazar tenant com **todos os required verdes**. Pior bug
   do projeto, sem rede. (Furo #1+#2)

2. **A infra de contenção inteira não existe.** `sig`/`ingest`/`pending`/`ack`/`files_json` = **0
   linhas no git canônico**. A proposta vende "scope-guard ✅ estender" mas o scope-guard real é de
   controller `.php` vs `SCOPE.md`, sem relação. Auto-merge ligado hoje confiaria num escopo que
   **nenhum código verifica**. (Furo #2+#9)

3. **Auto-merge = deploy de produção imediato e irreversível-de-fato.** `deploy.yml:12-19`
   auto-deploya `.tsx` na main; "git revert" (#7) não recolhe tenant vazado nem XSS executado. O
   único backstop (digest E4) depende de [W] ler a tempo (#8). (Furo #3+#7)

---

## ALEGAÇÕES DA PROPOSTA QUE NÃO SE SUSTENTAM CONTRA O REPO

- **§1 tabela: "Escopo (scope-guard) ✅ — estender pra ler files_json".** Enganoso. O
  `scope-guard.yml` existente compara controllers `.php` contra `SCOPE.md` (`scope-guard.yml:11-18`,
  `bin/check-scope.php:91`). Não lê `files_json` (zero no git), não toca `.tsx`. É **a-construir**, não
  "estender".
- **§1 tabela: "Multi-tenant / Tier-0 ⚠️ existe mas advisory" + E2 "promover a required" como rede.**
  Tecnicamente o `multi-tenant-gate` já é always-run, mas **SKIP-AS-PASS em `.tsx`** (`:57-63`).
  Promover a required **não cobre** o escopo auto-mergeado. A rede prometida não existe pra `.tsx`.
- **§1/§6 "a11y AA ✅" como check do que se auto-mergeia.** `a11y-axe-gate.yml` é path-scoped a
  `Components/ui/**` e jsdom não vê contraste/foco. Pages auto-mergeadas **não** acionam a11y.
- **§1 "Score de qualidade UI ✅ determinístico" como gate duro de merge.** Existe
  (`UiDeterministicScorer.php`) mas é regex binário cego a render/segurança/lógica (#4). Não é prova
  de qualidade que justifique remover o humano.
- **Correção de registro da própria proposta (§0) usada como prova de R1.** A proposta admite que a
  auditoria "n=1" errou o arquivo (`ContextSidebarV4` vs `Index.tsx:437/:545`). Isso **fortalece o
  ataque, não a proposta**: se [CC] errou o arquivo numa auditoria supervisionada, o mesmo skim em
  **altitude** vai errar o escopo de um handoff auto-mergeado — exatamente o vetor #1 (citar/tocar o
  arquivo errado). A proposta pede pra confiar no auditor que ela mesma mostrou ser falível.

---

## RECOMENDAÇÃO [AH]

Fase 0 (auto-PR + 1-clique de [W]) é defensável — [W] ainda clica. **Não passar da Fase 0 (não ligar
auto-merge)** até, no mínimo:
1. lint render-only que torna `.tsx` com transporte de dados (router/useForm/only/query/
   dangerouslySetInnerHTML) **inelegível** a auto-merge (mata #1, #5);
2. scope-guard por-handoff `files_json` real, com fixture adversarial no `gate-selftest` (mata #2);
3. assinatura com nonce+expiração+bind-ao-conteúdo+escopo, SECRET fora do Cowork (mata #9);
4. quarentena entre auto-merge e deploy.yml de prod (mata #3/#7);
5. digest com SLA fail-closed (mata #8).
Sem os 5, "[W] sai do meio" = "ninguém protege o pior bug do projeto".
