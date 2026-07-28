---
id: sessions-2026-07-28-sdd-nfebrasil-emissao-fiscal
date: "2026-07-28"
topic: "SDD do NfeBrasil — emissão fiscal e manifestação (Onda 5 do passo 5)"
authors: [C]
type: session
module: NfeBrasil
owner: W
related_docs:
  - requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md
  - requisitos/_Governanca/programa-ondas/passo-5-sdd-por-modulo.md
related_adrs:
  - 0351-sdd-from-source
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0093-multi-tenant-isolation-tier-0
  - 0062-separacao-runtime-hostinger-ct100
---

# Sessão 2026-07-28 — SDD do NfeBrasil (Onda 5 · passo 5)

Chip do agent [`sdd-from-source`](../../.claude/agents/sdd-from-source.md) sobre **NfeBrasil**
(6 telas · 34 US · 47 arquivos Pest · **sem SDD**). Alvo pedido: *"a tela de emissão (confirme
qual medindo, não deduza)"*.

## 1. A resposta ao alvo: a tela de emissão NÃO EXISTE

Medido (`grep -rn "Inertia::render(" Modules/NfeBrasil --include=*.php` → **7** chamadas / **6**
componentes; `RegraForm` é renderizado 2× em `create` e `edit`): **nenhum** dos 6 componentes é um
formulário de emissão. A emissão acontece em dois caminhos **sem tela própria**:

- **manual** — `POST /nfe-brasil/transactions/{tx}/emitir`, disparado do **Sells**
  (`Pages/Sells/_components/{FiscalSection,VdNfeEmitModal}.tsx`);
- **automática** — listener `SellCreatedOrModified` → `EmitirNfceJob`, com gate
  `nfe_business_configs.auto_emission_enabled`.

A tela-âncora escolhida foi **`Transactions/NfceStatus`** — o **resultado** da emissão, e a única
tela do módulo que o operador abre por causa de uma nota específica. Registrado no
[SDD §1.1](../requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md).

## 2. Colisão de sessões paralelas — medida ANTES de escrever

`git log origin/main --since=2.days -- Modules/NfeBrasil resources/js/Pages/NfeBrasil`
([ADR 0119](../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md)) mostrou que uma sessão
irmã fechou **2 das 6 telas** em `origin/main` no dia anterior: `Tributacao/Index`
(`UC-NFTR-01..06`, [#4880](https://github.com/wagnerra23/oimpresso.com/pull/4880)) e
`Tributacao/ConfigDefault` (`UC-NFCD-01..06`,
[#4876](https://github.com/wagnerra23/oimpresso.com/pull/4876)).

**Nenhum dos dois foi tocado.** Os prefixos `NFTR`/`NFCD` ficaram reservados; os novos usam
`NFST`/`NFMA`/`NFRF`/`NFIM`. Varredura contada do namespace:
`git grep -ohE "\b(CU|UC)-NF[A-Z]*-[0-9]+" origin/main` → **12** ids, todos das duas telas, e
**zero** `CU-NFE-*` (o namespace de CU do SDD nasceu livre).

> ⚠️ Esta worktree está **37 commits atrás** de `origin/main` (e 21 à frente, com as ondas
> anteriores). Consequência medida: os ids `UC-NFTR-04` e `UC-NFTR-06`, citados em prosa no
> `RegraForm.casos.md` como referência cruzada, aparecem como "sem teste" na porta viva **local** —
> os testes deles vivem em `origin/main`. A porta é advisory e conta qualquer menção; o gate
> **required** (`casos-coverage-guard` G-2) só declara UC de **heading `## UC-XX`**, então isso
> **não** é dívida de gate. Resolve no merge.

## 3. Artefatos

| Arquivo | O que é |
|---|---|
| `memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md` | **novo** — §0–§11, F1..F10, **13 CU** |
| `resources/js/Pages/NfeBrasil/Transactions/NfceStatus.casos.md` | **novo** — `UC-NFST-01..05` |
| `resources/js/Pages/NfeBrasil/Manifestacao/Index.casos.md` | **novo** — `UC-NFMA-01..06` |
| `resources/js/Pages/NfeBrasil/Tributacao/RegraForm.casos.md` | **novo** — `UC-NFRF-01..04` |
| `resources/js/Pages/NfeBrasil/Tributacao/ImportCsv.casos.md` | **novo** — `UC-NFIM-01..04` |
| `Modules/NfeBrasil/Tests/Feature/NfeStatusContratoTest.php` | **novo** — 5 casos |
| `Modules/NfeBrasil/Tests/Feature/ManifestacaoContratoTest.php` | **novo** — 6 casos |
| `Modules/NfeBrasil/Tests/Feature/TributacaoGatesContratoTest.php` | **novo** — 8 casos, **2 vermelhos por desenho** |
| `Modules/NfeBrasil/Tests/Feature/ManifestacaoServiceTest.php` | +1 linha `@covers-us US-NFE-050` |
| `memory/requisitos/NfeBrasil/SPEC.md` | 2 `Testado em:` (US-NFE-050, US-NFE-052) |
| `memory/requisitos/NfeBrasil/SUPERFICIE.md` | regenerado (`module-surface --write`): 156 → **163** |
| `memory/requisitos/NfeBrasil/BRIEFING.md` | redestilação **parcial**, declarada no `distilled_by` |
| `memory/requisitos/NfeBrasil/_STATUS-GENERATED.md` | **novo** — `requisitos-status.mjs --write` (34 US · 13 CU · 21 UC) |
| `memory/sessions/2026-07-28-sdd-nfebrasil-emissao-fiscal.md` | este log |

> ⚠️ **Observação de mecanismo — a worktree é COMPARTILHADA.** `git status --porcelain` no fim da
> corrida listou ~70 arquivos modificados/novos, dos quais **13 são meus**; o resto pertence a
> chips irmãos rodando **na mesma branch e na mesma worktree** (RecurringBilling, Cliente, Compras,
> Financeiro, Fiscal, OficinaAuto, Ponto, Sells, TeamMcp), incluindo edições em
> `scripts/governance/{requisitos-status.mjs,module-surface.mjs,gates-registry.json}` — que o
> **meu** chip tinha proibição explícita de tocar (e não tocou). A regra de isolamento do
> [passo 5](../requisitos/_Governanca/programa-ondas/passo-5-sdd-por-modulo.md) diz *"zero overlap
> entre sessões"* assumindo **1 sessão = 1 worktree**; aqui são N sessões numa worktree só. Não
> houve colisão de arquivo (as áreas são disjuntas por módulo), mas **o `git add` da consolidação
> tem que ser seletivo** — um `git add -A` misturaria 9 chips num commit. Zero git ops feitas por
> esta sessão, conforme o chip.

**Não tocado, de propósito:** a allowlist do `nfebrasil-pest.yml` (catraca por prova verde;
required com `enforce_admins`), `scripts/**`, `governance/*.json`, `.github/**`,
`memory/requisitos/Fiscal/**`, `proibicoes.md`, `LICOES_CODE.md`, `08-handoff.md`, os 2
`casos.md` de `Tributacao` já em `main`, e qualquer `.tsx`/Controller.

## 4. Achados (varredura CONTADA)

| # | Achado | Como foi medido | Estado |
|---|---|---|---|
| A1 | **3 das 5 mutações de tributação sem gate de permissão** — inclusive `toggleAutoEmission`, que liga a emissão automática de documento fiscal do tenant | `grep -n "can(\|abort" TributacaoController.php` → **0** ocorrências; `destroy`/`toggleAutoEmission`/`aplicarTemplate` recebem `Request` (não FormRequest); o rota-group não tem middleware de permissão | 🔴 `UC-NFRF-04` failing-first |
| A2 | **Import CSV resolve o tenant duas vezes** — `preview` guarda linhas em `session('nfe_import_csv_linhas')` sem carimbar o business; `aplicar` lê `session('business.id')` naquele instante | leitura do par `ImportRegrasController@preview`/`@aplicar` | 🔴 `UC-NFIM-04` failing-first |
| A3 | **Link morto na tela de status** — o `.tsx` aponta `/nfe-brasil/transactions/{tx}/danfe`; `grep -rn "danfe" Modules/NfeBrasil/Routes/*.php routes/*.php` → **2 linhas, ambas `emissoes/{id}/danfe-pdf`**. O próprio `.tsx` se autodenuncia: *"rota `/danfe` assumida"* | ⬜ `[BACKLOG]` + `CU-NFE-13` |
| A4 | **A tela de status só enxerga NFC-e 65** — venda com NF-e 55 fica eternamente "Aguardando emissão"; e o `NfeStatusControllerTest` **fixa esse comportamento como correto** (`it('modelo 55 (NFe) é ignorado…')`) — catraca defendendo o defeito | leitura de `NfeStatusController@show` + docblock de `NfeEmissaoController@listar` (*"Substituiu o GET nfe-status que retornava só modelo 65"*) | ⬜ `[BACKLOG]` |
| A5 | **O link "Detalhes" do Sells passa o id errado** — `FiscalSection.tsx` monta `/nfe-brasil/transactions/${em.id}/status` com `em.id` = **id da emissão**; a rota espera `{tx}` = id da transaction. No mesmo bloco o link do DANFE usa `em.id` corretamente (aquela rota **é** por emissão) | `grep` contado de `nfe-brasil/transactions` em `.tsx` (sem corte) + `serializeEmissao` | ⬜ fora da área (Sells) |
| A6 | **Duas chaves de sessão para o mesmo tenant** — `ManifestacaoController` usa `session('user.business_id')`; os outros 5 usam `session('business.id')`. O `ScopeByBusiness` lê a primeira | leitura dos 6 Controllers | 🟡 contornado (os testes novos semeiam as duas) |
| A7 | **Assimetria de guard na manifestação** — `buildKpisPayload` tem `where('business_id')` explícito; `buildItensPayload` (a listagem) **não** | leitura de `ManifestacaoController` | 🟡 `UC-NFMA-06` mede as duas metades juntas |
| A8 | **2 charters prometem 23 testes que não existem** — `find Modules/NfeBrasil/Tests -iname "*Charter*"` → **0**; `Tests/Charters/` não existe. `NfceStatus` promete 10, `Manifestacao/Index` promete 13 | ✅ corrigido como fato (Fase 2.6) |
| A9 | **3 fatos errados no `NfceStatus.charter.md`** — classe (`NfceStatusController` × `NfeStatusController`), rota (`/nfce/status` × `/api/…/nfe-status`) e `NfeService::consultarStatusEmissao`, método que **não existe** (`grep` no repo: **1** ocorrência, a própria linha do charter) | ✅ corrigido como fato |
| A10 | **Charter de Manifestação se contradiz sobre o próprio status** — frontmatter `status: draft`, corpo *"live em 2026-05-10, Non-Goals aprovados por Wagner"* | ⛔ **não tocado** — promoção é [W] |
| A11 | **Non-Goals do `NfceStatus` conflitam com o `.tsx`** — charter declara `❌ Reemissão` e `❌ Download DANFE`; a tela implementa os dois | ⛔ **não tocado** — intenção é [W] |
| A12 | **`oimpresso-staging` (CT 100) não tem as tabelas do NfeBrasil** (achado da sessão irmã, confirmado) — a suíte SKIPa inteira lá e fica **verde igual**. Ao ler resultado: conferir a contagem no JUnit, não o check | 🟡 reportado |

**Dos 12, dois viraram teste vermelho** (A1, A2), dois viraram correção factual de charter (A8, A9),
dois foram **deliberadamente não corrigidos** por serem intenção (A10, A11) e o resto virou
`[BACKLOG]`/`CU ⬜` com decisão de [W] nomeada.

## 5. Orçamento da corrida

| Item | Valor |
|---|---|
| Arquivos **lidos** (integral ou parcial) | **31** — 6 Controllers · 3 Services · 3 Models · 4 charters · 2 `casos.md` de `main` · SPEC · BRIEFING · SUPERFICIE · rotas · 4 FormRequests · 3 testes existentes · hook `useNfceStatus` · `FiscalSection.tsx` · workflow da lane · **o SDD do Fiscal inteiro (626 linhas)** |
| Varreduras `grep`/`git grep` **contadas** | **11** (nenhuma com `head_limit` em pergunta de completude) |
| Arquivos **escritos** | 8 novos + 4 editados |
| CU criados | **13** (`CU-NFE-01..13`) |
| UC **ancorados** (com id + teste que o cita) | **19** |
| `[BACKLOG]` sem id | **18** |
| Achados | **12** (2 viraram vermelho, 2 viraram correção factual, 2 escalados como intenção) |
| Portas rodadas | `requisitos-status.mjs` · `anchor-lint --check` · `casos-coverage-guard` · `screen-coverage-map` · `module-surface --write/--check` |

### Antes → depois (porta viva `requisitos-status.mjs NfeBrasil`)

| Elo | Antes | Depois |
|---|---:|---:|
| US no SPEC | 34 | 34 |
| **CU no SDD** | **0** | **13** |
| Telas `.tsx` | 6 | 6 |
| Telas com `casos.md` | **0** (nesta worktree; 2 em `main`) | **4** (6 pós-merge) |
| **UC declarados** | **0** | **21**¹ |
| UC com teste que os cita | 0 | **19**¹ |
| `anchor-lint` — US implementada **sem teste que a cobre** | **9** | **7** |
| `anchor_coverage` | 100% | 100% |
| `casos:check` débito | — | **−42 vs baseline, 0 violação nova** |
| `SUPERFICIE.md` | 156 arquivos | 163, `--check` OK |

¹ os 2 de diferença são `UC-NFTR-04`/`UC-NFTR-06` citados em prosa como referência cruzada — ver §2.

### O que reusou vs re-varreu (Fase 1.4)

Esta foi a **1ª tela do módulo** neste chip, então quase tudo foi varredura nova. O que **reusou**:

- **O SDD do Fiscal inteiro** — foi a leitura mais cara e a que mais rendeu: fechou 9 comportamentos
  por **ponteiro** em vez de CU novo (§6.0), e a fronteira §5.5 saiu quase pronta do §5.4 de lá.
- **Os 2 `casos.md` da sessão irmã** — reusei o formato, o vocabulário de recibo, o padrão
  "controle positivo em todo caso de isolamento" e o achado das **duas chaves de sessão**
  (`business.id` × `user.business_id`), que teria custado uma rodada vermelha pra descobrir sozinho.
- A resolução da Blade (§1.1 do agent) foi **barata neste módulo**: não há Blade homônima — o
  legado é uma tela **React** desligada por `redirect(302)`, declarado no próprio `web.php`.

O que **re-varreu obrigatoriamente**: os 6 Controllers, os consumidores das rotas de emissão
(`grep` sem corte), a allowlist da lane e os 4 charters.

### Gargalo

A **leitura do SDD do Fiscal** (626 linhas) foi o maior item isolado — e foi o melhor investimento
da corrida: sem ela eu teria escrito 9 CU duplicados (cancelamento, CC-e, inutilização,
retransmissão, timeline, certificado, SPED, os 4 eventos de manifestação, PII), que é exatamente a
dívida que o chip mandava evitar. **Recomendação para a próxima onda:** quando dois módulos se
tocam, ler o SDD do irmão **antes** da Camada 1, não durante — a fronteira muda o que vale a pena
varrer.

## 6. Lições de mecanismo

1. **"Comece pela tela X" pode não ter referente — e medir isso É o primeiro achado.** O chip pedia
   "a tela de emissão"; ela não existe. A instrução *"confirme qual medindo, não deduza"* foi o que
   impediu de carimbar `NfceStatus` como "a tela de emissão" e escrever um SDD sobre uma ficção.
2. **A definição do agent manda "CU sem UC é lacuna", e a porta viva concorda — mas os motivos são
   de naturezas diferentes.** Dos 8 CU sem UC, 4 são **ponteiro** para contrato já escrito
   (duplicar seria dívida), 1 é fluxo **sem tela** (fora da área), 2 são cobertura parcial e 1
   espera decisão. Sem separar isso, a lista parece placar de falha. Criei o §6.0-bis pra declarar
   as três naturezas — sugiro que o formato canônico do SDD passe a prever essa seção.
3. **`assertRedirect()` é vacuamente verdadeiro quando a validação falha** — Laravel também
   redireciona no 422 de FormRequest. Dois casos meus passariam por *não-execução* (a família da
   lápide [proibicoes §5](../proibicoes.md) 2026-07-24) até eu adicionar
   `assertSessionHasNoErrors()` + a pré-condição de que a sessão tem as linhas parseadas.
4. **Teste que fixa o defeito como correto é uma catraca contra o conserto** (A4): o
   `NfeStatusControllerTest` tem `it('modelo 55 é ignorado pelo endpoint NFC-e')`. Quem for
   corrigir a tela precisa **apagar um teste verde** — e apagar teste verde parece regressão.
   Registrei no `[BACKLOG]` para que a decisão [W] já venha com a instrução de aposentar o caso.
5. **A allowlist da lane required é a coisa certa a NÃO tocar, e por dois motivos, não um.** Além
   do "sem prova verde não entra", aqui há o segundo: **2 dos meus testes nascem vermelhos por
   desenho**. Adicioná-los a uma lane com `enforce_admins` bloquearia o merge de todos até o gate
   do Controller existir. Vale a pena o prompt do chip dizer isso explicitamente — a formulação
   atual só cobre o 1º motivo.

## 7. O que precisa de [W]

| # | Decisão | Onde está descrito |
|---|---|---|
| D1 | **Gatear `destroy` / `toggleAutoEmission` / `aplicarTemplate`** (A1) — segurança, não feature | [SDD §5.4.1](../requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md) · R1 |
| D2 | **Fronteira de tenant do import CSV** (A2) — 3 saídas honestas listadas | [SDD §5.3 F8](../requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md) · `UC-NFIM-04` |
| D3 | **`CU-NFE-13`**: link morto do DANFE + modelo 55 invisível + id errado no Sells (A3/A4/A5) | SDD §5.4.3 |
| D4 | **Promover ou não o charter de Manifestação** `draft → live` (A10) | SDD §5.4.5 |
| D5 | **Non-Goals do `NfceStatus`** — aprovar (e mudar o código) ou reconciliar (A11) | SDD §6.6 nota ¹ |
| D6 | **Ratchet-up da lane required** com os 3 testes novos, depois de verde provado | SDD §8.3 |
| D7 | **Criar ou não `ANTI-REGRESSAO-nfe-legacy.md`** — a fonte 4 (Delphi) **não existe** neste módulo, e o agent é proibido de inventá-la | SDD §0.1 |

## 8. Fonte 4 (Delphi / Office Comercial) — **gap declarado**

`find memory -iname "*ANTI-REGRESSAO*"` → **2** arquivos, **ambos do Produto**. A triangulação
deste módulo foi de **3 fontes**, com contrato de paridade mais fraco. Onde o SDD afirma paridade,
ela vem de SPEC/charter/ADR — **nunca** de suposição sobre o legado. Nada foi inventado.
