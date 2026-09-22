# Pedido [CC] → [CL] · Jana — fechar o **ciclo completo** de `/ia` em produção

- 2026-08-13 · autor [CC] (Cowork) · itens marcados **[W]** aguardam decisão; o resto é executável hoje
- **Base de leitura — `main` lido NESTE turno (2026-08-13, 17:13Z), tree `cacf627639cd`:** `Pages/Jana/Index.tsx` · `Index.charter.md` (v5) · tree de `Pages/Jana` · grep de cor crua + `data-contract` no módulo.
- **Espelho local `D:\oimpresso.com` lido no mesmo turno** (working copy, **não** é git): `scripts/governance/ciclo-completo.mjs` · `anchor-content-check.mjs` · `anchor-lint.mjs` · `eslint-baseline.mjs` · `scripts/contrato-de-tela.mjs` · `package.json` · `.claude/settings.json` · `.claude/hooks/{block-ancora-no-olho,nudge-test-contract-anchor}.mjs` · `governance/required-checks-baseline.json` · `gates-registry.json` · `contrato/jana-painel.contract.json` · `PT-04-Dashboard.md` · `config/eslint-baseline.json`.
- **Não escrevo no git.** Nada aqui está commitado. É pedido pronto pra [CL] executar.

---

## 0. O que JÁ landou (e por isso não entra no plano)

O `main` andou entre ontem e hoje: `9a121aea5957` → **`cacf627639cd`**. Conferido no fonte, não no changelog:

- **As 10 correções do meu pedido rev.2 estão aplicadas no `Index.tsx`.** `FAROL_CLASSES` agora é `bg-success`/`bg-warning`/`bg-destructive`; o badge METAS é `<Badge>` sólido (o gradiente violet→fuchsia→pink **sumiu**, não virou gradiente de token); o `ProximaAcaoCard` usa `bg-[color:color-mix(in_oklch,var(--color-primary)_6%,var(--color-card))]` — tint **opaco sobre o card**, que era exatamente o defeito do brief afundando; o KPI strip virou `text-primary` + `bg-primary/10`.
- **As 5 âncoras `data-contract` do contrato foram adicionadas** — o `_nota_ancoras` do contrato ("nenhuma âncora existe hoje; o gate acusa") está **obsoleto**.
- **[W] ratificou em 2026-08-12:** `Index.charter.md` v5 revoga a prescrição de *cor* do "Demo polish (v2)", com o motivo medido — o `Index.tsx` já contava **6 violações `no-restricted-syntax`** no `config/eslint-baseline.json` enquanto o charter chamava aquelas cores de alvo de UX. Os dois não podiam estar certos.

Resta o ciclo, que é outra coisa: a tela está **bonita e conforme**, e **incompleta** pela máquina.

---

## 0-bis. Antes de tocar em código — sincronizar, apagar conflito, baixar o necessário

> **Tudo neste bloco foi reconferido no `main` às 17:40Z**, não no disco. O `main` andou **3× hoje**: `9a121aea` → `cacf6276` → **`44d1c107`**. Re-sincronize na hora de começar; qualquer sha citado aqui já pode ter envelhecido.

### a) Sincronizar (o espelho local **está** desatualizado — provado)

```
git fetch origin
git switch main && git pull --ff-only origin main
git rev-parse --short HEAD        # ≥ 44d1c107
git status --porcelain            # tem que sair VAZIO antes de começar
```

Prova de que isso não é formalidade: `D:\oimpresso.com\resources\js\Pages\Jana\Index.charter.md` local está em **v4**; o `main` está em **v5**. Quem editar o charter a partir do disco **desfaz a ratificação de [W] de 12/08** sem perceber. É a causa-raiz L-42 (cópia local = cache que envelhece) acontecendo agora, neste arquivo.

### b) Conflitos a APAGAR (não é conflito de merge — é texto que mente)

**Varri e não achei conflito textual:** zero `<<<<<<<`/`>>>>>>>` em `prototipo-ui/cowork/**` (jsx/css/html/tsx), zero `.bak`/`.orig`/`.rej`/`- Cópia`/`?v=` em `prototipo-ui/`. Não gaste tempo caçando isso. Os conflitos são **semânticos** — três, **todos reconferidos no `main` agora**, e cada um manda a próxima sessão pro lugar errado:

| # | onde | apagar | por que mente |
|---|---|---|---|
| C-1 | `prototipo-ui/contrato/jana-painel.contract.json` → `_nota_ancoras` | a nota **inteira** | Diz "Nenhuma âncora `data-contract` existe hoje no `Index.tsx` — o gate acusa até serem adicionadas". **As 5 existem** (`:100 :162 :257 :322 :361`) e o gate passa. TODO já cumprido vira trabalho refeito |
| C-2 | mesmo arquivo → `_nota_fonte`, última frase | "o charter agora declara `n/a (herda PT-04 Dashboard; segue o Padrão de Tela)`" | O charter v5 do `main` declara `prototipo-ui/cowork/jana-merge.jsx`. **Os dois estão no `main` e se contradizem** — é exatamente o campo do §2.5. Resolvido o §2.5, o perdedor se corrige no mesmo PR |
| C-3 | `config/eslint-baseline.json:234` | a entrada `"resources/js/Pages/Jana/Index.tsx\|no-restricted-syntax": 6` | Crédito de 6 violações que não existem mais (§1). **Não apagar à mão** — `npm run lint:baseline:write` reescreve o arquivo inteiro; editar o número na unha é a lápide "re-rode o comando, não edite o número" (§5 2026-07-17) |

**NÃO apagar:**
- `prototipo-ui/cowork/jana-merge.jsx` — envenenado na parte Frota (§3), mas é a âncora **válida** do drill-down (`JmDrillDrawer` · `JM_KPI_DRILL`) e está declarado no charter. Apagar quebra o `anchor-content-check`, que é **required**. O veneno se resolve **lendo o Non-Goal**, não deletando o arquivo.
- `prototipo-ui/ds-v6/` vs `prototipo-ui/cowork/ds-v6/` — 3 HTMLs homônimos (`gabarito-vendas` · `receita` · `showcase`) nos dois lugares. **Parece** dupe, mas o `ds-v6` é snapshot congelado mantido de propósito como referência histórica. Consolidar é decisão [W] à parte — **fora deste PR**.
- `prototipo-ui/cowork/chat-jana.jsx` — desancorado do charter em 10/08 (lápide), mas o `chat-jana.css` segue sendo a referência de tint do escuro (§3).

### c) Baixar / abrir antes de começar

Tudo do `main` sincronizado no (a) — **nada aqui se lê do Cowork nem do disco velho**:

**Alvo do PR**
- `resources/js/Pages/Jana/Index.tsx` · `Index.charter.md` (**v5**)
- `resources/js/Pages/Jana/Chat.tsx` (`:90-92`) · `Memoria.tsx` (`:49`)
- `resources/js/Pages/Jana/Index.casos.md` — **criar** (§2.4); reconferido no `main` 17:40Z: só `Memoria.casos.md` e `Pro.casos.md` existem
- `config/eslint-baseline.json`

**Contrato e padrão**
- `prototipo-ui/contrato/jana-painel.contract.json` (+ `contract.schema.json`)
- `memory/requisitos/_DesignSystem/padroes-tela/PT-04-Dashboard.md` (é o `draft` do §4)
- `prototipo-ui/PRE-FLIGHT-TELA.md` · `PROTOCOL.md`

**Lei (ler ANTES de propor guarda/regra nova)**
- `memory/INDEX.md` · `memory/proibicoes.md` · `memory/LICOES_CC.md`
- `memory/dominio/oficina-auto.md` (`:50-55` — os termos e paths do §3)
- ADR **0265** (erradica locação) · **0107** (gate F1.5 screenshot) · **0286** (contrato de tela) · **0209** (ratchet ESLint) · **0336** (promoção de gate por mordida provada)

**Máquinas (só se for mexer em gate)**
- `scripts/governance/ciclo-completo.mjs` · `anchor-content-check.mjs` · `scripts/contrato-de-tela.mjs` · `scripts/eslint-baseline.mjs`
- `.claude/settings.json` (o registro é o que ativa hook) · `.claude/hooks/_HOOKS-INDEX.md` (⚠️ regenerar, nunca editar — §5)

```
npm ci        # o PR mexe em ESLint; baseline gerado com dep diferente dá delta fantasma
```

---

## 1. Veredito das máquinas — medido, não estimado

### `ciclo-completo.mjs` · **1 de 6** · `Jana/Index` = INCOMPLETA

Rodei a lógica do gate à mão contra o `main` (o script exige `pt-conformance --json`, que não roda daqui):

| # | check | hoje | por quê |
|---|---|---|---|
| 1 | `charter` | ✅ | `Index.charter.md` v5 existe |
| 2 | `pt_declarado` | ❌ | `declaredPT()` casa **só** `/PT-0[1-5]/` no `related_prototype`. O valor é `prototipo-ui/cowork/jana-merge.jsx` → **null** |
| 3 | `pt_conforme` | ❌ | cascata do #2: `pt ? !!conforme : false` — com `pt` null reprova **independente** do que o `pt-conformance` disser |
| 4 | `casos` | ❌ | **`Jana/Index.casos.md` não existe no `main`.** `Memoria.casos.md` e `Pro.casos.md` existem; `Index` e `Chat` não |
| 5 | `teste` | ❌ | cascata do #4 |
| 6 | `golden_live` | ❌ | duplamente: `pt` null **e** `PT-04-Dashboard.md` está `status: draft` |

> **O #6 não tem conserto por código.** O golden do PT-04 diz, no próprio frontmatter, que a barra técnica "≥2 dashboards convergirem" **já foi atingida** (verificado 2026-07-11: `Admin/GovernanceV4`, `governance/DsRollout`, `kb/Graph`, `team-mcp/Scorecard`) e que o **único gate restante é aprovação de screenshot do [W]** (F1.5 · ADR 0107). Enquanto o golden for `draft`, `/ia` **não fecha o ciclo** — nem ela, nem nenhuma outra tela PT-04. Ver §4.

### `contrato:check` (`jana-painel.contract.json`) · **PASSA** ✅

Conferido seção por seção contra o `Index.tsx` do `cacf627639cd`:

| seção | âncora | copy |
|---|---|---|
| `painel-meta-sem-historico` | ✅ `:100` | "Sem histórico" ✅ |
| `painel-meta-apurando` | ✅ `:162` | "Aguardando apuração…" ✅ (o `…` é o mesmo caractere) |
| `painel-cta-conversar` | ✅ `:257` | "Conversar agora" ✅ |
| `painel-metas-header` | ✅ `:322` | "Metas ativas" ✅ + "Acompanhamento contínuo" ✅ |
| `painel-metas-vazio` | ✅ `:361` | "Nenhuma meta cadastrada ainda" ✅ + "Conversar com a Jana" ✅ |

`ordem` declarada `[cta, header, vazio]` é subsequência da sequência de arquivo `[sem-historico, apurando, cta, header, vazio]` ✅. **0 falhas.**

Wiring do CI conferido (`contrato-de-tela.yml`): os contratos são descobertos por `git ls-files '*.contract.json' | grep -v EXEMPLO` — o `jana-painel` **entra sozinho**, sem registro manual. O job roda ainda o `--map --check` (fonte existe + âncoras); a `fonte` do contrato é `Pages/Jana/Index.tsx`, que existe ✅. **Não há `continue-on-error`** — o job fica vermelho de verdade. Só não **bloqueia**: o context não está no `required-checks-baseline.json`. E ele é condicional a `steps.detect.outputs.relevant == 'true'`, então num PR que não toque os paths relevantes ele nem roda.

### Ratchet ESLint — **baseline stale, e isso é um buraco aberto** 🔴

`config/eslint-baseline.json:234` ainda declara:

```json
"resources/js/Pages/Jana/Index.tsx|no-restricted-syntax": 6
```

O `Index.tsx` **não tem mais** essas 6 (foram elas que o v5 revogou). E o `eslint-baseline.mjs` **só falha em REGRESSÃO** — o modo VALIDATE percorre as contagens atuais e só empilha em `regressions` quando `atual > base`; entrada de baseline **maior** que o real nunca é olhada, e não existe shrink-check, ao contrário do `casos:baseline:shrink-check`, que o repo já tem pra essa exata classe. Consequência: o `Index.tsx` carrega hoje um **crédito de 6 violações grátis** de `ds/no-raw-palette-color`. Alguém repõe o gradiente violet→fuchsia→pink e o gate **"ESLint · ratchet vs baseline" (REQUIRED desde 2026-07-15) fica verde**. Todo o trabalho de ontem fica sem catraca.

**Conserto (§2 item 1):** `npm run lint:baseline:write` no mesmo PR. Não editar o número à mão.

### Cor crua no módulo — o teste da rev.2 **ainda não passa**

`rg "violet-|fuchsia-|pink-|sky-|emerald-|rose-|amber-" resources/js/Pages/Jana/` → **rc=0**, 4 hits fora do `Index.tsx`:

- `Chat.tsx:90-92` — `bg-emerald-100/amber-100/rose-100` **com** par `dark:`. Fora do token, mas não quebra no escuro.
- `Memoria.tsx:49` — `bg-amber-100 text-amber-800` **sem `dark:` nenhum**. → chip claro-sobre-claro no tema escuro. **É o mesmo bug de ontem, na tela vizinha**, e ninguém reportou porque a aba Memória é menos usada.

---

## 2. Plano executável — 6 passos, nesta ordem

**1. Fechar a catraca antes de tudo** (senão o resto do PR entra sem rede)
```
npm run lint:baseline:write      # zera o crédito de 6 do Index.tsx
git diff config/eslint-baseline.json   # confirmar que SÓ a linha 234 mudou pra baixo
```

**2. `Memoria.tsx:49` → token** — `bg-amber-100 text-amber-800` vira `StatusBadge` (o DS tem `kind` pra isso) ou, no mínimo, tint 6% + borda 22% em `warning`. É o único dos 4 hits que **quebra** no escuro; resolve junto com o #1 porque o baseline vai ser reescrito de qualquer forma.

**3. `Chat.tsx:90-92` → token** — `facil/realista/ambicioso` são status de domínio: `success`/`warning`/`destructive`. Depois destes dois passos o teste `rg … → rc=1` fecha o módulo inteiro.

**4. Criar `Jana/Index.casos.md`** (destrava #4 e #5 do ciclo). O `TESTE_RE` do gate exige ref a `tests/` · `Modules/**/Tests/` · `e2e/` · `*.spec.ts` · `*Test.php` em qualquer linha. Os UCs saem prontos do contrato — cada seção já é um caso com estado declarado:
   - `UC-COPI-PAINEL-01` empty state (**hoje é o estado real de 100% dos tenants** — 0 metas em qualquer business, medido 2026-08-09)
   - `UC-COPI-PAINEL-02` meta sem apuração → "Aguardando apuração…" (nunca zero como resultado)
   - `UC-COPI-PAINEL-03` sparkline sem série → "Sem histórico"
   - `UC-COPI-PAINEL-04` farol vem do servidor; payload sem `farol` degrada pra `cinza` (o `farolDaMeta()` já é isso — teste **de contrato**, o fallback está no charter §Anti-hooks)
   - `UC-COPI-PAINEL-05` escopo `business_id` (Tier 0, ADR 0093)
   > ⚠️ O `nudge-test-contract-anchor` só dispara em `*Test.php` — se o teste for `.spec.ts`, **não há nudge**. A regra vale igual: ancore a assertiva no contrato (contrato de tela / charter / ADR), não no que o componente já faz. Teste tautológico trava o drift em vez de pegá-lo.

**5. `related_prototype` — decisão, não wiring** ⚖️ **[W]**
   O check #2 do ciclo só passa se o campo contiver `PT-0X`. Duas saídas, e elas **não** são equivalentes:
   - **(a) `n/a (herda PT-04 Dashboard; segue o Padrão de Tela)`** — é a forma que o `jana-painel.contract.json` **já afirma** estar no charter (e não está: o `main` v5 diz `jana-merge.jsx`; um dos dois documentos mente hoje). Ganha #2. Seguro pro gate **required** `anchor-content-check`: o `anchorFile()` retorna null em `/^n\/a/` → a âncora é ignorada, não vira MISSING. **Custo:** perde a proveniência declarada do drill-down, que o v3 documentou com recibo (`JmDrillDrawer` `:640` · `JM_KPI_DRILL` `:887`, sha256 `057bd8ae081bfd1c…`).
   - **(b) manter `jana-merge.jsx`** — hoje passa o `anchor-content-check` (arquivo existe, é `.jsx` logo não-SHELL, cita o módulo), mas **#2 e #6 do ciclo reprovam pra sempre** e a tela nunca fica completa.
   Não escolho: é a proveniência da tela. **Qualquer que seja, corrija o perdedor no mesmo PR** — hoje contrato e charter se contradizem, e é o tipo de par que manda a próxima sessão procurar a regra onde ela não está.

**6. Os dois `_pendente_w` do contrato — confirmados ainda abertos no código** ⚖️ **[W]**
   - **Título:** `Dashboard.layout` passa `title="Jana — Dashboard"` e `breadcrumbItems=[{Jana},{Dashboard}]`, mas a aba se chama **Painel** desde a onda 3 da US-COPI-148 e a rota é `/ia`. Sobrou "Dashboard" em 2 lugares — e o componente exportado ainda se chama `Dashboard`. Qual é a palavra? Se for "Painel", entra como seção `painel-titulo` no contrato.
   - **Dois botões "(em breve)":** `title="Configurar Brain B Jana (em breve)"` e `title="Exportar relatório (em breve)"` — presentes, clicáveis, sem `disabled`, sem rota. Mesma classe do rodapé que prometia "próximo brief: amanhã, 8h". Some, vira `disabled` com o motivo, ou entrega? **Enquanto não decidido não entram no contrato** — pinar uma promessa é congelá-la.

---

## 3. As âncoras que o [CL] vai usar — e uma está envenenada

| o que | âncora | estado |
|---|---|---|
| copy + ordem das seções | `prototipo-ui/contrato/jana-painel.contract.json` | ✅ **use esta** — é o contrato ativo, e a copy dele deriva da **tela viva**, não de protótipo |
| paridade de tema escuro | `prototipo-ui/cowork/chat-jana.css` (`.jc-*`) | ✅ referência do tint sobre `--surface` |
| drill-down | `jana-merge.jsx` §`JmDrillDrawer` · §`JM_KPI_DRILL` | ✅ símbolo (não linha) — `grep -n "JmDrillDrawer\|JM_KPI_DRILL"` |
| KPIs / cards do Painel | `jana-merge.jsx` | 🔴 **ENVENENADA — ler o Non-Goal do charter v5 ANTES** |

**O veneno, medido e renderizado em 2026-08-13** (nota do charter v5): montado pelo shell canônico, `jana-merge.jsx` renderiza o KPI **"FROTA UTILIZAÇÃO"**, a meta "Utilização de frota" e o card "Frota" com a linha **`Locadas`** + "91 caçambas avulsas". No fonte: **`frota` 8× · `caçamba` 7×**.

E **nenhuma máquina barra** — medido por mim em `memory/dominio/oficina-auto.md:50-55`, não herdado do charter: `forbidden_ui_terms: ["locacao", "cacamba"]` contra `forbidden_ui_paths` de exatamente **três** entradas — `resources/js/Pages/OficinaAuto`, `Modules/OficinaAuto/Database/Seeders`, `Modules/OficinaAuto/Database/Migrations`. **`prototipo-ui/` não está na lista.** Quem derivar da âncora sem ler o Non-Goal reintroduz a locação erradicada pela ADR 0265, e o CI deixa passar. [W] já vetou duas vezes ("Frota utilização são alucinação, ninguém usa até a presente data").

Idem o §Anti-hooks: o protótipo lista `AnaliseInadimplenciaService`/`AnaliseFaturamentoService` — **nenhuma existe**. A fonte real do drawer é `app/Services/Sells/SellsCockpitAggregator.php`.

---

## 4. Só [W] destrava

1. **Golden PT-04 → `live`** — aprovação de **screenshot** (F1.5 · ADR 0107). É o gate #6, e nenhum código o resolve. Sem ele `/ia` fica INCOMPLETA mesmo com os 5 outros checks verdes, junto com toda tela PT-04 do repo.
2. **`related_prototype`** (§2.5) — proveniência da tela.
3. **Título "Dashboard" vs "Painel"** e os **dois "(em breve)"** (§2.6).
4. *(fora deste pedido, mesma família)* ampliar o `dominio-gate` pra varrer `prototipo-ui/` — exige FP medido antes (ADR 0336) e esbarra na exceção legítima "Caçambas" como razão social de cliente (§5 2026-06-09).

---

## 5. Hooks e máquinas — conferidos

**Hooks: registrados e com prova de mordida.** O registro em `.claude/settings.json` é o que ativa (não a existência do arquivo) — `PreToolUse` traz `block-ancora-no-olho` (:80), `charter-validate` (:122), `nudge-test-contract-anchor` (:146). Todo hook tem par `.test.mjs`, e o **`gate selftest` é REQUIRED desde 2026-07-02** — a prova de que as catracas mordem bloqueia merge, então hook que para de morder não passa em silêncio.

**Relevantes pra este PR:**
- `charter-validate` — vai rodar no passo 5. O schema `charter` é **required desde 2026-07-17**; atenção ao `charter_version` **integer** (o decimal atravessou 3 PRs quando era advisory).
- `block-ancora-no-olho` — guarda só **imagem**; alimenta a allowlist a partir de `related_prototype`/`component` dos charters. Trocar o campo pra `n/a` (§2.5a) **não** quebra nada aqui, porque o veneno da §3 não é imagem: nenhum hook lê o conteúdo do `.jsx`.
- `anchor-content-check` — **required** ("Ancora de design nao-shell (F2/F6)"). Falha só em MISSING/SHELL; `n/a` é isento; `.jsx` existente que cita o módulo = OK. Nas duas saídas do §2.5 este gate fica verde.

**Máquinas: nomes corretos** (`package.json` conferido) — `ciclo:` **não existe** como script; o gate roda por caminho:
```
node scripts/governance/ciclo-completo.mjs              # relatório
node scripts/governance/ciclo-completo.mjs --check      # regressão vs baseline
npm run contrato:check -- prototipo-ui/contrato/jana-painel.contract.json
npm run pt:conformance:check
npm run lint:baseline:check      # depois do --write do passo 1
npm run casos:check
npm run dominio:check
```
`ciclo-completo` e `contrato-de-tela` **ficam vermelhos de verdade** (o `contrato-de-tela.yml` declara no cabeçalho: sem `continue-on-error`, sem mascarar), mas **não bloqueiam** — nenhum dos dois contexts está no `required-checks-baseline.json`. Quem trava merge neste diff é o **ratchet ESLint** e os **schemas charter/ADR**.

⚠️ **Pegadinha registrada no baseline, custou um vermelho antes:** `.claude/hooks/_HOOKS-INDEX.md` **embute a contagem de contexts required**. Se este PR tocar required, o step "Hooks manifest em dia" fica vermelho sem relação aparente com o diff. Conserto é **regenerar** (`node scripts/governance/hooks-manifest-generate.mjs --write`), nunca editar o número à mão.

---

## 6. O que **não** verifiquei

- **Não rodei nada.** Sem shell aqui: `ciclo-completo`, `pt-conformance`, ESLint e `contrato:check` foram avaliados **lendo o código dos gates** e aplicando a regra à mão. Os vereditos do §1 são derivados, não execuções — confira rodando antes de agir.
- **A cadeia do `contrato:check` eu segui só até o `--contract`.** O mesmo job roda `--map --check`, `auditar-intencao-fluxo.mjs` e `adversario-intencao-fluxo.mjs --strict` sobre cada contrato; **os dois últimos eu não abri** — podem ter exigência própria sobre o `jana-painel` que este pedido não cobre.
- **`pt_conforme` (#3) na hipótese (a).** Se o `related_prototype` virar `n/a (herda PT-04…)`, o #2 passa e o #3 deixa de ser cascata — aí vale o que o `pt-conformance` disser da assinatura PT-04 do `Index.tsx`, que **não medi**. Pode virar o novo bloqueio.
- **Contraste AA no escuro** depois das trocas de ontem e dos passos 2-3: os tints 6-9% sobre `--color-card` (0.30) não foram medidos contra WCAG. [CA] fecha na F3.5.
- **O espelho local está STALE** e é bom exemplo do porquê: `D:\oimpresso.com\...\Index.charter.md` é **v4**; o `main` é **v5**. Todos os fatos deste pedido sobre charter e `Index.tsx` vêm do `main`; do local vêm só gates, hooks e baselines (que não mudaram no intervalo, mas eu não provei isso).
- **As 8 blades órfãs** (`metas/*`, `alertas/*`, `fontes/show`, `superadmin/metas`) não entram aqui — estão em `layouts.app` AdminLTE, fora deste sistema de token e fora do ciclo de tela Inertia. São as ondas [CC]-8/10/11 do pedido de 09/08.
