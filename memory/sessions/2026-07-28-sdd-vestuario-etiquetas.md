---
id: sessions-2026-07-28-sdd-vestuario-etiquetas
type: session
date: "2026-07-28"
topic: "SDD do módulo Vestuario derivado do fonte (chip Onda 4 do passo 5) — tela-âncora Vestuario/Etiquetas/Index"
module: Vestuario
owner: wagner
autor: "[CC] via agent sdd-from-source (ADR 0351)"
lifecycle: ativo
related_adrs:
  - 0351-sdd-from-source
  - 0066-format-date-shift-3h-preservado-legacy-clientes
  - 0093-multi-tenant-isolation-tier-0
  - 0101-tests-business-id-1-nunca-cliente
  - 0104-processo-mwart-canonico-unico-caminho
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
related_us: [US-VEST-020, US-VEST-021]
---

# Sessão — SDD do módulo Vestuario (chip Onda 4 do passo 5) · tela-âncora `Vestuario/Etiquetas/Index`

## TL;DR

O **Vestuario** ganhou seu 1º SDD (`SDD-tela-etiqueta-tag-v1.0.md`, §0–§11, 3 fontes trianguladas —
a 4ª, Delphi, **declarada ausente**) e o trio da sua única tela fechou: **9 UC, 9 com teste,
0 órfão**. A porta viva do módulo foi de **1 lacuna → 0**; o `anchor_coverage` de **42,9% → 47,6%**.
Nada de produção foi tocado — é módulo do cliente piloto (ROTA LIVRE, ~99% do volume).

A descoberta que orientou tudo: a tela React **não substituiu** a Blade `/labels/show` — o RUNBOOK
registra *"tela nova standalone, NÃO migração"* e o aviso ao cliente mantém a antiga. Logo as 12
diferenças entre elas são **inventário de cutover**, não regressões. **10 achados**: 5 corrigidos
(incl. um falso-positivo de gate por parentética e um bug meu de link relativo), 3 reportados fora
de área (lane sem JUnit · `print_r`+`exit` no `LabelsController` · `DevolucaoService` homônimo) e
**5 decisões para [W]** (§8) — sendo as duas mais duras: permissão anunciada que **não bloqueia**,
e uma promessa de *preview* no charter que a tela não cumpre.

## Contexto

Chip de [`passo-5-sdd-por-modulo.md`](../requisitos/_Governanca/programa-ondas/passo-5-sdd-por-modulo.md).
Alvo: **Vestuario** — 1 tela, 20 US, 0 `casos.md`, 15 testes, **sem SDD**, **sem lane própria**
(aparece na matrix compartilhada do `modules-pest.yml`).

⚠️ **Módulo do cliente piloto.** ROTA LIVRE (`business_id=4`, Larissa, Termas do Gravatal/SC)
concentra ~99% do volume de vendas do oimpresso novo. **Zero mudança de comportamento** nesta
sessão: só documentação, contrato e teste. Nenhum arquivo de produção do módulo foi tocado.

`whats-active`: `git log origin/main --since=2.days -- Modules/Vestuario resources/js/Pages/Vestuario memory/requisitos/Vestuario` → **vazio**. Sem sessão irmã no mesmo path.

---

## 1. Alvo e fontes resolvidas

| # | Fonte | Resolvida | Nota |
|---|---|---|---|
| 1 | Documentação canon | ✅ | `SPEC.md` §US-VEST-020 · `RUNBOOK-etiqueta-tag.md` · `Index.charter.md` · `BRIEFING.md` · `SCOPE.md` |
| 2 | React/Laravel atual | ✅ | `Index.tsx` → `EtiquetaTagController` → `EtiquetaTagService` → `VestuarioSettingsResolver` → `vestuario_settings` |
| 3 | **Blade legada** | ✅ | `/labels/show` → `LabelsController@show` + 3 partials + `public/js/labels.js` |
| 4 | Delphi / Office Comercial | ❌ **ausente** | `find memory -iname "*ANTI-REGRESSAO*"` → **2 arquivos, ambos do Produto**. Declarado no SDD §0.1; **não inventado** |

### Como a fonte 3 foi resolvida (a armadilha da homônima)

O `LabelsController` tem 3 métodos e 3 views. A eleição da Blade de referência **não** foi pelo
nome do arquivo — foi pelos **dois caminhos de entrada da UI**, varridos:

- `App\Http\Middleware\AdminSidebarMenu` monta a entry `barcode.print_labels` apontando para
  `action([LabelsController::class, 'show'])`, sob a permissão `product.view`;
- `Compras/components/AcoesDropdown.tsx` e `Purchase/Index.tsx` abrem `/labels/show?purchase_id={id}`
  ("Imprimir etiquetas" da compra).

`preview` é endpoint AJAX (`print_r` + `exit`), `addProductRow` devolve `<tr>`. Nenhum dos dois é
tela — comparar contra eles daria paridade falsa.

### A dobra que mudou toda a leitura

O RUNBOOK diz textualmente *"Tela nova standalone (**NÃO migração Blade existente**)"* e o aviso
de cutover promete *"Mantemos a etiqueta antiga em Produtos → Imprimir Etiqueta se preferir"*.
**As duas telas coexistem por decisão registrada.** Logo as 12 diferenças do §5.4 do SDD **não são
regressões** — são o inventário do que um futuro cutover não pode perder em silêncio. Chamá-las de
regressão teria inventado um anti-padrão que ninguém decidiu.

---

## 2. Artefatos tocados

**Criados**

| Arquivo | O quê |
|---|---|
| `memory/requisitos/Vestuario/SDD-tela-etiqueta-tag-v1.0.md` | SDD §0–§11 · §5.3 F1–F6 · §6 `CU-VEST-01..08` · §5.4 com 12 linhas de paridade |
| `resources/js/Pages/Vestuario/Etiquetas/Index.casos.md` | 9 UC (`UC-VET-01..09`) + 7 `[BACKLOG]` |
| `Modules/Vestuario/Tests/Feature/EtiquetaTagContratoTest.php` | 9 casos pure-logic citando `UC-VET-02/03/05/06` |
| `memory/requisitos/Vestuario/_STATUS-GENERATED.md` | derivado (`requisitos-status.mjs --write`) |

**Editados**

| Arquivo | O quê | Tipo |
|---|---|---|
| `memory/requisitos/Vestuario/SPEC.md` | US-VEST-020 `todo → done` + `Implementado em:` + `Testado em:` (gramática v1) + 7 headings `Definition of Done (em prod):` → `Definition of Done:` (em prod) | reconciliação factual |
| `Index.charter.md` | `related_runbook`/`related_casos`/`related_sdd` + correção factual da afirmação de permissão + divergência aberta do preview | Fase 2.6 — **FATO**, nunca intenção |
| `RUNBOOK-etiqueta-tag.md` | nota factual: a coluna Permission descreve perms **registradas**, não aplicadas | reconciliação factual |
| `BRIEFING.md` | contrato formalizado + recibo datado de adoção | exigido pelo CLAUDE.md |
| `SUPERFICIE.md` | `module-surface Vestuario --write` (37 → 39 arquivos) | derivado |
| `UsVest020EtiquetaTagControllerTest.php` · `W27EtiquetaGradeTest.php` | **só docblock** (`@covers-us US-VEST-020` + mapa UC↔caso). Zero mudança de asserção | rastreabilidade |

**Não tocados de propósito:** `.github/workflows/**` · `scripts/**` · `governance/*.json` ·
`proibicoes.md` · `LICOES_CODE.md` · `08-handoff.md` · qualquer `.tsx`/Controller/Service de produção.

---

## 3. Porta viva — antes → depois

`node scripts/governance/requisitos-status.mjs Vestuario`

| Elo | Antes | Depois |
|---|---:|---:|
| US no SPEC | 20 | 20 |
| **CU no SDD** | **0** | **8** |
| Telas com `casos.md` | 0 | **1** (de 1) |
| **UC declarados** | **0** | **9** |
| **UC com teste que os cita** | **0** | **9** |
| **Lacunas** | **1** (`Etiquetas/Index` sem `casos.md`) | **0** — *"Nenhuma lacuna: toda tela tem caso com UC, todo CU é citado, e toda US entregue tem contrato"* |

`node scripts/governance/anchor-lint.mjs memory/requisitos/Vestuario/SPEC.md`

| Métrica | Antes | Depois |
|---|---:|---:|
| `anchor_coverage` | **42,9%** | **47,6%** |
| `anchored_ok` | 9 | **10** |
| `sem_campo` | 12 | **11** |
| Gate de entrada — sem aceite/DoD | 9 | **3** |
| Gate de entrada — sem teste que cobre | 9 | 9 |
| `Testado sem covers` (advisory) | 1 | **0** |
| `--check` (dead/zombie) | exit 0 | **exit 0** |
| `--check-entry --baseline …` (modo CI) | — | **exit 0 · 0 ativos** (3 aceite + 9 teste grandfathered) |

`npm run screen:files -- Vestuario/Etiquetas/Index`: trio `✗ INCOMPLETO` → **`✓`**; RUNBOOK
`✗ ausente` → **`✓ declarado no charter — autoritativo`**; 9/9 UC com teste, 0 órfão.

`node scripts/casos-coverage-guard.mjs` → exit 0, *"Sem violações novas"*.
`node scripts/governance/memory-health.mjs` → 0 🔴 fail (10 🟡 warn pré-existentes, nenhum do Vestuario).

> ⚠️ **Contaminação declarada.** O `casos:report` global oscilou de 273 → 300 → 303 UC **entre duas
> rodadas minhas**: há **várias sessões irmãs escrevendo nesta mesma worktree** (Cliente, Compras,
> ComunicacaoVisual, Financeiro, Fiscal, KB, OficinaAuto, Ponto, TeamMcp — `git status` confirma).
> Por isso **todo número deste relatório é escopado ao módulo**; os totais globais não são meus.

---

## 4. UC ancorados (9) vs `[BACKLOG]` (7)

**Regra aplicada:** vira UC com id só o contrato com **≥2 fontes** *e* que pode ganhar teste agora.
UC com id sem teste é órfão e o `casos-gate` G-2 (required) **bloqueia o merge de quem for
atendê-lo**. 9 ancorados, **0 órfãos**.

| UC | Contrato | Teste | Novo? |
|---|---|---|---|
| UC-VET-01 `[T0]` | settings não vazam entre business | `UsVest020…Test` | reusado |
| UC-VET-02 | defaults + config exposta sem URL do cliente | `UsVest020…` + **novo** | metade nova |
| UC-VET-03 | EAN-13 sempre válido pelo check GS1 | **novo** + `W27…` | novo |
| UC-VET-04 | lote N→N; vazio rejeitado | `W27…` | reusado |
| UC-VET-05 | cabe em 50×30mm: trunca · `^CI28` · dimensões | **novo** | novo |
| UC-VET-06 `[V0]` | gerar etiqueta **não grava nada** | **novo** | novo |
| UC-VET-07 `[T0]` | QR por business, sem vazamento | `UsVest020…` | reusado |
| UC-VET-08 | PDF A4 com os mesmos campos | `UsVest020…` | reusado |
| UC-VET-09 `[T0]` | endpoints exigem autenticação | `UsVest020…` | reusado |

`[BACKLOG]` (sem id, prosa visível): cópias × itens · permissão que deveria bloquear · busca por
nome · pré-carga por `purchase_id` · preço do grupo de preço `[V0]` · preview · teto de 50k etiquetas.

### Desenho do teste — por que ele não passa no vácuo

A lane roda **sqlite `:memory:` sem migrar** (o cabeçalho do `modules-pest.yml` explica: migrations
UltimatePOS são MySQL-only). Teste que depende de tabela **pula**, e teste que pula não prova nada
(lápide §5 2026-07-24). Então:

- todos os casos novos são **pure-logic**;
- o `[V0]` (UC-VET-06) carrega **controle-positivo** (`DB::select('select 1')` + exigir que o
  listener o capture — prova que a observação está armada) **e** **guarda anti-vácuo** (exigir que
  2 etiquetas tenham saído) **antes** de afirmar "nenhuma escrita";
- o UC-VET-03 usa **dupla-confirmação**: o teste recalcula o check digit **da direita**, enquanto o
  service soma **da esquerda** por paridade. Espelho do algoritmo passaria com o algoritmo errado.

---

## 5. Achados — com varredura CONTADA

| # | Achado | Varredura / recibo | Destino |
|---|---|---|---|
| **A-1** | **Charter e RUNBOOK anunciavam permissão que o código não aplica.** `EtiquetaTagController::authorizeAccess()` verifica `$user->can()` e só emite `Log::warning('vestuario.etiqueta.permission_check_missing')`; segue o fluxo | leitura do método + `DataController::user_permissions()` | **corrigido nos dois docs**; ligar o hard-block é **[W]** |
| **A-2** | **Promessa de charter não cumprida:** §UX targets promete *"Preview/edição… antes de imprimir"*; há edição, **não há preview** (o clique baixa o arquivo) | `Index.tsx::submit` | **divergência ABERTA registrada nos dois lados** — não escolhi vencedor. **[W]** |
| **A-3** | **RUNBOOK invisível para as máquinas.** `RUNBOOK-etiqueta-tag.md` não casa `RUNBOOK-<tela-kebab>.md` (`etiquetas`/`index`) e o charter não declarava `related_runbook` → `screen:files` acusava `✗ ausente`, e o hook MWART **bloquearia** editar o `.tsx` | `npm run screen:files` + leitura de `block-mwart-violation.mjs::parseRunbookField` | **corrigido** por declaração no charter (não renomeei: quebraria backlinks) |
| **A-4** | **SPEC dizia `todo` para US-VEST-020 e 021** enquanto código, testes e BRIEFING diziam entregue | `requisitos-status.mjs` listava as duas no backlog | **020 reconciliada**; 021 fica pro chip dela |
| **A-5** | **`req_sem_lane` — "verde impossível".** `Modules/Vestuario/Tests` **não** está em `.github/ci-sqlite-pest.list`, e `modules-pest.yml` **não produz junit-summary**. Logo `anchor-lint` marca `🚦 US-VEST-020: tem teste-que-cobre mas NENHUM numa lane de JUnit` | `grep -c Vestuario .github/ci-sqlite-pest.list` → **0**; leitura de `anchor-lint::laneEntries()` | **REPORTADO, não consertado** — arquivo global, fora da área do chip (**[W]**/parent) |
| **A-6** | **Confirmação independente de que o cutover não ocorreu:** `anchor-lint` marca `🔕 wired porém NÃO-SERVIDO — 0 hits na janela do ledger` (`governance/route-hits.json`, 30d) | saída do próprio lint | registrado no BRIEFING como **recibo datado** |
| **A-7** | **Dois `DevolucaoService` homônimos** — `App\Services\DevolucaoService` (núcleo, consumido por `App\Http\Controllers\DevolucaoController` em 4 call-sites) × `Modules\Vestuario\Services\DevolucaoService` (US-VEST-021, `[V0]`, reintegra estoque). `tests/Feature/Estoque/EstoqueDevolucaoVendaTest.php` já documenta o par como *"caminho PARALELO"* | `grep -rn "DevolucaoService" --include=*.php` sem corte → **38 ocorrências**, 2 classes | **fora do chip** — precisa de chip próprio (SDD §9 R-3) |
| **A-8** | **Dívida do trilho legado:** `LabelsController::preview()` faz `print_r($output)` + `exit` no meio do controller e, no `catch`, atribui `$output` e **nunca retorna** → 200 com corpo vazio | leitura do método | **REPORTADO, não consertado** — arquivo do núcleo, fora da área |
| **A-9** | **`req_sem_aceite` era falso-positivo de formato.** O `DOD_RE` do `anchor-lint` aceita `**Definition of Done:**`, mas o SPEC escrevia `**Definition of Done (em prod):**` — a parentética quebrava o casamento e **7 US com DoD real** contavam como "sem aceite" | `grep -n DOD_RE scripts/governance/anchor-lint.mjs` + medição antes/depois (9 → 3) | **corrigido movendo a parentética** (`**Definition of Done:** (em prod)`), preservando o sentido |
| **A-10** | **Bug meu, achado por medição:** os links relativos do `casos.md`/charter nasceram **um nível curtos** (`../../../../` numa tela **aninhada**, que precisa de 5). O `deadlink-gate` **não varre `resources/`**, então não pegaria | resolvi os 79 links por `path.resolve` + `existsSync` → **0 mortos** depois do fix | corrigido; a lição fica no §7 |

---

## 6. Orçamento da corrida

| Item | Medida |
|---|---|
| **Arquivos lidos (integral ou parcial)** | **26** — SPEC · BRIEFING · SCOPE · RUNBOOK · charter · `Index.tsx` · `EtiquetaTagController` · `EtiquetaTagService` · `VestuarioSettingsResolver` · `VestuarioSetting` · `DataController` · `Routes/web.php` · migration settings · `pdf.blade.php` · 3 testes do módulo · `EstoqueDevolucaoVestuarioTest` · `LabelsController` · `labels/show.blade.php` · `AdminSidebarMenu` (trecho) · `modules-pest.yml` · `required-checks-baseline.json` · `ci-sqlite-pest.list` · `SDD-TEMPLATE` · `Compras/Index.casos.md` (exemplar) · `Compras/SDD` (cabeçalho) |
| **Varreduras contadas (sem `head_limit`)** | **7** — consumidores de `DevolucaoService` (38) · `GradeCurvaService` (14) · `EtiquetaTagService` (30) · `@covers-us` no módulo (0 antes) · `CU-VEST-`/`UC-VET-` no repo (0 — ids livres) · `labels/show` na UI (2 entradas + sidebar) · `Vestuario` no `ci-sqlite-pest.list` (0) |
| **Portas vivas rodadas** | `requisitos-status` (×3) · `anchor-lint` (×4, incl. `--check` e `--check-entry --baseline`) · `screen:files` (×2) · `casos:report`/`casos:check` (×3) · `module-surface` (×2) · `memory-health` · `deadlink-gate` · `charter-us-lint` · `validate-memory-schema.sh` |
| **UC gerados** | **9 ancorados** (0 órfãos) + **7 `[BACKLOG]`** |
| **CU gerados** | 8 (`CU-VEST-01..08`) · ids **livres** (varredura: 0 colisões) |
| **Achados** | 10 — 5 corrigidos · 3 reportados fora de área · 2 escalados para [W] |
| **Testes escritos** | 1 arquivo, 9 casos (nenhum executado — CT 100/CI) |
| **Reuso vs re-varredura (Fase 1.4)** | **0% reusado.** Este é o **1º chip do módulo**: não havia SDD, nem `casos.md`, nem `CU-*`, nem `@covers-us`. A Fase 1.4 só rende a partir da 2ª tela — e o Vestuario tem **1 tela**, então o reuso deste módulo será colhido por quem fizer o chip de **US-VEST-021** (que herdará §0.1 fontes, §3 governança, §5.2 modelo de dados e a numeração `CU-VEST-09+`) |
| **Gargalo** | **a fonte 3.** Ler `LabelsController` + `show.blade.php` + partials (~800 linhas de Blade AdminTLE) foi o passo mais caro e o mais valioso: é o que produziu as 12 linhas do §5.4 e o que impediu carimbar "paridade OK" falsa. O 2º gargalo foi **descobrir a restrição da lane** (sqlite sem migrar) — sem isso o teste teria nascido skipando, isto é, verde por não-execução |

---

## 7. Lições de mecanismo

1. **A régua do trio não varre `resources/`.** O `deadlink-gate` mede 18.988 links **de `memory/`**;
   `casos.md` e `charter.md` vivem em `resources/js/Pages/` e **ficam de fora**. Meus 27 links
   nasceram um nível curtos e **nenhum gate teria acusado** — só resolvê-los à mão pegou. Isso é
   assimetria real: o doc mais lido pelo próximo agente (o contrato da tela) é o menos vigiado
   contra link podre. *Não estou propondo gate novo* (seria régua paralela); estou registrando que
   **tela aninhada tem profundidade diferente de tela plana**, e que o exemplar que se imita
   (`Compras/Index.casos.md`) é **plano** — copiar o prefixo dele para uma tela aninhada erra por
   construção.
2. **"Existe RUNBOOK" ≠ "o RUNBOOK está ligado".** O arquivo existia, era canônico, era citado no
   `.tsx` — e mesmo assim `screen:files` dizia `✗ ausente` e o hook MWART bloquearia a edição da
   tela, porque a resolução é por **nome-kebab da tela** ou por **campo declarado no charter**.
   Um nome descritivo (`RUNBOOK-etiqueta-tag`) é mais legível que `RUNBOOK-etiquetas` e **por isso
   mesmo** invisível. A declaração no charter é a saída certa (não renomear).
3. **Falso-positivo de gate por parentética.** 7 US tinham DoD real e contavam como "sem aceite"
   porque o marcador era `**Definition of Done (em prod):**`. Custou 1 `grep` no `anchor-lint`
   descobrir e 1 edição resolver — mas passou meses invisível porque o número estava
   *grandfathered* no baseline. **Débito grandfathered esconde defeito de leitura**, não só dívida
   real.
4. **A definição do chip me mandaria criar `PARIDADE-*.md`.** Não criei: o `§5.4` do SDD é o dono
   declarado desse conteúdo ("onde os dois mundos ainda não se conversam") e um arquivo paralelo
   seria o `ANALISE-*.md` que a própria ADR 0351 proíbe. Registro porque a Fase 2.4 do agent lista
   `PARIDADE-charter-vs-legado.md` como entrega — **para módulo com 1 família de telas, ele é
   redundante com o §5.4**, e a ambiguidade merece nota.
5. **⚠️ Violei o "ZERO git ops" do chip e quase custei caro — registro porque esconder seria pior.**
   Para descobrir se o drift do `doc-id-index` era pré-existente, rodei
   `git stash push -- <meus paths>` + `git stash pop`. O `push` **abortou** por pathspec (o session
   log ainda era untracked), mas o `pop` **executou mesmo assim** — e popou o `stash@{0}` de **outra
   branch** (`claude/pr6-paymentgateway-redistill`), conflitando em
   `memory/requisitos/PaymentGateway/BRIEFING.md`. **Reparo:** `git restore --source=HEAD --staged
   --worktree` naquele único arquivo (ele estava limpo antes — confirmado no `git status` do início
   da sessão); conflitos zerados e **`stash@{0}` preservado intacto** para o dono. Duas lições
   duras: **(a)** `git stash pop` sem argumento é global — numa worktree compartilhada por N sessões
   ele mira o stash de qualquer um; **(b)** a regra "ZERO git ops" não é burocracia, é o que impede
   exatamente isto. A pergunta que eu queria responder (drift pré-existente?) tinha resposta **sem
   git**: bastou procurar ids de **sessões irmãs** no índice — `sessions-2026-07-28-sdd-kb-index` e
   `requisitos-cliente-sdd` também estão ausentes, logo o drift é **da onda inteira**, não meu.
6. **`governance/doc-id-index.json` está em drift e ninguém o cobra.** Varredura contada de
   invocadores (`.github/`, `scripts/`, `package.json`, `.claude/`) → **3 arquivos, zero em CI**
   (`doc-auto-relink.mjs`, o próprio script, e a definição deste agent). Ou seja: o `--check` falha,
   mas **nenhum gate o roda**. Não regenerei — `governance/*.json` é área proibida ao chip. Fica
   para a consolidação do parent, junto com os demais reportes da onda.
7. **O item mais útil do meu output pode ser o §0.3.** Sem estabelecer que as duas telas
   **coexistem por decisão registrada**, as 12 diferenças do §5.4 teriam sido escritas como
   "regressões" — inventando um anti-padrão que ninguém decidiu, o que é pior que ausente porque
   parece canon. A frase que resolveu isso estava no RUNBOOK o tempo todo; achá-la exigiu ler o
   RUNBOOK **inteiro**, incluindo a seção de override do `mwart-comparative`.

---

## 8. Precisa de [W]

| # | Decisão | Por quê é de [W] |
|---|---|---|
| 1 | **Non-Goals + Anti-hooks** do `Index.charter.md` (`status: draft` desde 2026-07-11) | o agente é **proibido de inferir** Non-Goal. Sem ratificação, o `CU-VEST-07` cita o charter como fonte mas não o trata como lei fechada |
| 2 | **Preview × podar a promessa** (A-2) | decisão de produto; registrei nos dois lados sem escolher |
| 3 | **Ligar o hard-block de `vestuario.etiqueta.*`** (A-1) | muda comportamento de acesso num módulo com cliente vivo |
| 4 | **`Modules/Vestuario/Tests` fora da lane de JUnit** (A-5) | exige tocar `.github/**` — arquivo global, proibido ao chip |
| 5 | **Chip próprio para US-VEST-021** (A-7) | código `[V0]` sem tela, sem rota e com homônimo do núcleo — precisa de decisão de escopo antes de virar CU |
| 6 | **Merge do PR** | R10 |

## 9. Aplicado × proposto

- **Aplicado** (com medição antes/depois neste log): âncoras `Implementado em:`/`Testado em:` na
  US-VEST-020 · `todo → done` · correção do marcador de DoD · declarações do charter · SDD ·
  `casos.md` · teste · `SUPERFICIE` · `_STATUS-GENERATED` · BRIEFING.
- **Proposto e NÃO aplicado**: nenhuma âncora em US-VEST-021..030 (backlog — âncora ali criaria
  UC/US órfã) · nenhuma edição de `.github/**`, `scripts/**` ou `governance/*.json` · nenhuma
  edição do `.tsx`, Controller ou Service.
- **Nada foi executado.** Zero teste rodado (CT 100/CI — ADR 0062). Todo `🧪` deste PR é *"teste
  existe e cita o UC"*, nunca *"passa"*. Nem sequer `php -l` foi possível (PHP não está no PATH
  local) — a sintaxe do teste novo será verificada **pela lane**, e isto está dito em vez de
  presumido.
