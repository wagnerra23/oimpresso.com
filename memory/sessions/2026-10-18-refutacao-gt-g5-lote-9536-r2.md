---
date: "2026-10-18"
topic: "Refutação GT-G5 rodada r2 do lote PR #9536 (17 arquivos memory/requisitos, módulo Fisio) — REPROVADO: 24 erros em 274 itens (8,8%) + 45 linhas com valor em reais no diff"
authors: ["C"]
prs: [9536]
outcomes:
  - "Lote REPROVADO: error_rate 8,8% (limiar 2%) — 21 afirmações de prosa refutadas (driver WhatsApp stale contra decisão [W] whatsmeow; Prism inexistente; 'na biblioteca? sim' sem seed listado) + 3 derivados em drift (plans-index, doc-id-index, _STATUS-GENERATED do Fisio ausente)"
  - "Scan PII: 45 linhas `+` com valor em reais (86 ocorrências) em 6 arquivos do lote — Tier 0 proibicoes.md; scanner do repo (brl-scan-diff) confirma rc=1 com 43 linhas no lote"
  - "Âncoras: 71 verificadas, 0 refutadas — com a ressalva declarada de que Modules/Fisio e memory/requisitos/Fisio NÃO existem em origin/main (módulo nasce neste PR): existência medida em HEAD, inexistência em origin/main registrada"
---

# Refutação GT-G5 · lote PR #9536 · rodada r2

## TL;DR

**Veredito: REPROVADO.** 274 itens verificados, 24 erros confirmados, error_rate **8,76%** (limiar < 2%). O eixo sistemático é um só e está espalhado em 3 dos 6 arquivos da amostra: a prosa das etapas 02 e 06 segue prescrevendo "Cloud API oficial / número oficial / custo por mensagem" **depois** da decisão [W] registrada (ADR 0437 §13.1 e §14 nº 5: driver `whatsmeow`, um número por clínica) e ignora que `origin/main` já tem `MetaCloudDriver`. Somam-se 10 linhas "na biblioteca? **sim**" do protocolo 11 sem nenhum seed nomeado no PR, 2 citações a `Prism` (pacote ausente em `origin/main`, superseded pela ADR 0035) e 3 máquinas derivadas em drift. PII: **45 linhas** com valor em reais no diff do lote (Tier 0 `proibicoes.md`), 0 CPF/CNPJ/telefone/e-mail.

## Cabeçalho

| campo | valor |
|---|---|
| base `origin/main` | `8e99d6cd762429c8fc3fe9e8b03b283a09ff786e` |
| HEAD (branch `claude/fisio-modulo`) | `b1bb9ddcca669ea1550eab4a490727757231cc74` |
| repo raso | **true** (`git rev-parse --is-shallow-repository`) — nenhuma data de `git log` usada como recibo |
| sessão fresca | sim — instância nova, sem contexto do gerador nem de r1 |
| modelo refutador | Fable 5.1 (tier máximo disponível) |
| `arquivo_existia` | false (a evidência r2 não existia antes desta escrita) |
| `abriu_evidencia_anterior` | **false** — não abri `memory/sessions/*refutacao*` nem `memory/handoffs/` de hoje. ⚠️ Declaração de contaminação parcial: `scripts/governance/brl-scan-diff.mjs --base origin/main` varre o PR inteiro e **imprimiu 4 linhas** do arquivo `memory/sessions/2026-10-18-refutacao-gt-g5-lote-9536-r1.md` (ele está no diff do PR). Não abri o arquivo e não usei essas linhas como evidência — todo item abaixo tem medição própria. O scratchpad desta sessão também continha `plus.txt` e `r1.txt` pré-existentes; não os abri. |
| corpo do PR / commit message | não lidos (claim ≠ prova) |

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (igualdade só no tier máximo — Fable é o tier máximo)
- [x] Amostra: 100% anchors / ≥30% prosa — seed `b1bb9ddc`, ordenação por `sha1(seed + path)`, primeiros `ceil(0,3 × 17) = 6`
- [x] Cada item verificado contra o código real em `origin/main` (e em HEAD onde o artefato nasce neste PR — declarado), não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha + porquê)
- [x] Scan PII no diff — 7 padrões, 7 controles positivos casaram (um deles só na 2ª tentativa; ver §Scan PII)
- [x] `error_rate_pct` calculado: **8,76** (≥ 2 → lote reprovado inteiro)
- [ ] Entry no ledger — **não escrevi no ledger** (mandato: não editar o lote, não commitar, não escrever no ledger)

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` → **17 arquivos** (15 `A` em `memory/requisitos/Fisio/**`, 2 `M`: `Ponto/_STATUS-GENERATED.md`, `_BACKLOG-GENERATED.md`). Confere com o enunciado.

**Amostra determinística (6 de 17):**

| sha1(seed+path)[:12] | arquivo |
|---|---|
| `0fe1dcb67a3f` | `memory/requisitos/Fisio/features/f1-constancia/requirements.md` |
| `19603daf456c` | `memory/requisitos/Fisio/protocolos/11-fibromialgia.md` |
| `205992f7cdde` | `memory/requisitos/_BACKLOG-GENERATED.md` |
| `2abb919c78ae` | `memory/requisitos/Fisio/etapas/02-f1-constancia.md` |
| `37cee9c28132` | `memory/requisitos/Fisio/etapas/05-f4-ia-questionarios.md` |
| `4630e88aaa62` | `memory/requisitos/Fisio/etapas/06-lancamento.md` |

**Fato de moldura, medido antes de tudo:** `git ls-tree -r origin/main --name-only | grep -i fisio` → **0 de 0** hits (rc do grep = 1; controle positivo: o mesmo pipeline em HEAD devolve 51). `Modules/Fisio`, `memory/requisitos/Fisio` e `memory/decisions/0437-*` **nascem neste PR** (último ADR em `origin/main` é 0436). Consequência declarada: âncoras internas ao Fisio não podem existir em `origin/main` por construção — para elas medi existência em HEAD e registrei a ausência em `origin/main`; afirmações sobre **código vivo** (módulo Whatsapp, OficinaAuto, composer) foram medidas em `origin/main`. Em HEAD, `Modules/Fisio` tem controllers, middleware, rotas, 5 `casos.md`, 17 arquivos Pest — e **nenhum** `.tsx`, Entity, migration ou Service além de `FisioAcesso` (os testes importam `Modules\Fisio\Entities\*`, `Services\AdesaoCalculator` etc. que não existem; 12 arquivos de teste têm `markTestSkipped`).

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---:|---:|---:|---|
| 1 · Âncora existe | 71 | 71 | 0 | `git ls-tree origin/main -- <path>` e `git ls-tree HEAD -- <path>` para cada link relativo, path em backtick, classe de teste, chave de pacote, módulo e seção de ADR citada; controle negativo `memory/requisitos/Fisio/NAO-EXISTE.md` → False; controle positivo `memory/requisitos/Ponto/SPEC.md` → True |
| 2 · Âncora não revogada / leitor real | 4 | 4 | 0 | regenerei os derivados com o gerador do repo e comparei (`tasks-index-generate --check`, `requisitos-status Ponto --check`, `feature-lint --check`); ADR 0437 frontmatter `status: aceito` lido no HEAD |
| 3 · Ação × veredito da prosa / afirmação sobre código | 152 | 131 | **21** | cada afirmação cruzada com ADR 0437 (HEAD), HANDOFF-F1 (HEAD), `01-biblioteca.md` (HEAD), código em `origin/main` (`Modules/Whatsapp/Services/Drivers/`, `composer.json`, `Modules/OficinaAuto`) e testes em HEAD |
| 4 · Célula íntegra | 39 | 39 | 0 | script conta pipes por linha vs header em todas as tabelas dos 5 arquivos de prosa (38 tabelas, 0 linhas com contagem divergente, backticks ignorados) + 1 tabela do BACKLOG; sem map.json no lote |
| 5 · Máquina derivada | 8 | 5 | **3** | `--check` rodados, rc literal abaixo |
| **Total** | **274** | **250** | **24** | |

`error_rate_pct = 24 / 274 = 8,76%`.

Afirmações **externas** (artigos, preços de concorrentes, resoluções COFFITO/Anvisa, URLs) **não entram no denominador**: não são mensuráveis contra `origin/main`. Só o que cita canon, código ou artefato do repo foi contado.

### Grupo 1 — detalhe (71 itens, 0 refutados)

- `requirements.md` (26): 5 links relativos (`../../SPEC.md`, `../../../../decisions/0437-…`, `../../HANDOFF-F1.md`, `../../etapas/01-biblioteca.md`, `../../etapas/02-f1-constancia.md`) → HEAD ok / `origin/main` MISSING (módulo novo); 12 US-ids `US-FISIO-001..012` presentes no SPEC (HEAD `SPEC.md:29–106`); `parent_plan: fisio-f1-constancia` ↔ `plan.md` (HEAD); 16 classes de teste citadas em AC-1..AC-10 existem em `Modules/Fisio/Tests/**` (HEAD); `ChannelDriverFactory` → `origin/main:Modules/Whatsapp/Services/Drivers/ChannelDriverFactory.php`; chave `fisio_module` usada em HEAD (`Modules/Fisio/Config/config.php:9`, `Http/Middleware/FisioLiberado.php:11`); `etapas/04-f3-operacao.md` (backtick, relativo à raiz do Fisio) existe em HEAD.
- `11-fibromialgia.md` (3): `etapas/01-biblioteca.md` (HEAD), ADR 0437 §5 e §13 item 3 existem.
- `02-f1-constancia.md` (14): `Public/AprovacaoOsController` (padrão OficinaAuto) → `origin/main:Modules/OficinaAuto/Http/Controllers/Public/AprovacaoOsController.php:18–28` (link + PIN, lockout após 5 tentativas); ADR §1, §2, §5, §6, §7, §13.1, §14 nº 9 existem; handoff T-07..T-10 existem (`HANDOFF-F1.md:91–94`); tabelas `fisio_consents`, `fisio_records`, `fisio_game_ledger`, `fisio_patients`, `fisio_checkins` no modelo de dados do handoff (`:54–64`).
- `05-f4-ia-questionarios.md` (11): etapa 03 §5 existe (`03-f2-camera.md:234`, e a linha 253 com taxas por porte está dentro dela); ADR §7, §8, §14 nº 7, ADR 0368 (`origin/main`); handoff T-06, T-07, T-09, T-11; `fisio_records`; Meilisearch na stack canônica.
- `06-lancamento.md` (15): ADR §13.1, §14 nº 10, §7, §3.1, §10, §5, §3.3; handoff T-04, T-07, T-08, T-09, T-12; `Modules/RecurringBilling` e `Modules/NFSe` em `origin/main` (`module.json` de cada um); rota `/fisio` (`Modules/Fisio/Routes/web.php:15,33` `->prefix('fisio')`).
- `_BACKLOG-GENERATED.md` (2): `scripts/governance/tasks-index-generate.mjs` existe; fonte `Fisio/SPEC.md` existe (HEAD).

### Grupo 2 — detalhe (4 itens, 0 refutados)

1. `_BACKLOG-GENERATED.md` ↔ gerador: `node scripts/governance/tasks-index-generate.mjs --check` → `1095 US lidas · 968 abertas · 127 done`, **rc=0** (gerado = commitado).
2. `Ponto/_STATUS-GENERATED.md` ↔ gerador: `node scripts/governance/requisitos-status.mjs Ponto --check` → `em dia`, **rc=0**. A linha nova `US-PONTO-017` vem do `origin/main:memory/requisitos/Ponto/SPEC.md:378` (o derivado em `origin/main` estava stale; `git grep US-PONTO-017 origin/main -- memory/requisitos/Ponto/_STATUS-GENERATED.md` → rc=1; controle positivo `US-PONTO-016` → 1).
3. Trio `features/f1-constancia/*` ↔ `feature-lint --check`: `ERROS 0 · AVISOS 1` (advisory `feature-sem-sdd`), **rc=0** — bate com a promessa da US-FISIO-001.
4. "ADR 0437 aceita 2026-10-18" (requirements.md:18, 06:…): frontmatter do ADR em HEAD `status: aceito`, `decided_at: "2026-10-18"`, `accepted_via` textual. ✓ como citação; ver observação sobre nascer `aceito` no mesmo PR.

### Grupo 5 — detalhe (8 itens, 3 refutados)

| comando | rc | veredito |
|---|---:|---|
| `node scripts/governance/tasks-index-generate.mjs --check` | 0 | ✓ |
| `node scripts/governance/requisitos-status.mjs Ponto --check` | 0 | ✓ |
| `node scripts/governance/requisitos-status.mjs Fisio --check` | 1 | **✗** `_STATUS-GENERATED.md não existe — rode com --write` (SPEC novo sem cadeia derivada; o loop do umbrella só itera arquivos existentes, logo o CI fica mudo) |
| `node scripts/governance/plans-index.mjs --check` | 1 | **✗** `PLANS-INDEX-GENERATED.md DESATUALIZADO`; probe `--write` + `git diff` mostra o delta = exatamente o `plan.md` do Fisio (`8 → 9 registrados`); restaurado com `git checkout --`. Step do umbrella é `continue-on-error: true` (advisory), mas o drift é do lote |
| `node scripts/governance/doc-id-index.mjs --check-collisions` | 0 | ✓ (`0 colisão em 2994 ids`) |
| `node scripts/governance/doc-id-index.mjs --check` | 1 | **✗** `doc-id-index.json em drift`; `grep -c 'requisitos-fisio' governance/doc-id-index.json` → **0** (os ids novos do lote não estão no índice). O CI só roda `--check-collisions` ("frescor fica no --write") — drift real, cobrança advisory |
| `node scripts/governance/design-code-map-check.mjs --check --strict` | 0 | ✓ (avisos pré-existentes em KB/Produto/RecurringBilling, nada do Fisio) |
| `node scripts/governance/feature-lint.mjs --check` | 0 | ✓ |

## REFUTADOS (24)

### R1 · `etapas/02-f1-constancia.md:55` — "O módulo atual do oimpresso usa Evolution API/whatsmeow … É não oficial"
- **Afirmação:** o módulo Whatsapp atual é conexão não oficial via QR.
- **origin/main diz:** `Modules/Whatsapp/Services/Drivers/` contém `MetaCloudDriver.php` (API oficial), `WhatsmeowDriver.php`, `ZapiDriver.php`, `ChannelDriverFactory.php`; `git grep -l meta_cloud origin/main -- Modules/Whatsapp` → **57 arquivos**; `embedded` → 9 arquivos.
- **Porquê é erro:** afirma sobre o código algo que o código contradiz (grupo 3: "abra o componente e conte"). A caracterização "não oficial" omite o driver oficial já existente.

### R2 · `02-f1-constancia.md:56` — "Saída barata: … trocar só o transporte do Fisio para a Cloud API … Se faltar, chamar a Graph API direto num adapter"
- **origin/main diz:** `MetaCloudDriver.php` já existe (R1). ADR 0437 §13.1 (HEAD, `:204`): *"Nada de integração nova"* e *"Trocar para `meta_cloud` (já existe no módulo, com Embedded Signup) é configuração do canal"*.
- **Porquê é erro:** propõe construir o que já existe e contraria a decisão [W] registrada.

### R3 · `02-f1-constancia.md:104` — "Recomendação: **A no lançamento** (número único 'Fisio · oimpresso', um WABA do oimpresso) … interface única `FisioMessenger`"
- **Canon mais novo (HEAD):** ADR 0437 §13.1 mitigação (a) *"um número por clínica, nunca um número compartilhado entre clínicas"*; §14 nº 5 **decidido 2026-10-18**: *"driver `whatsmeow`, um número por clínica"*; `HANDOFF-F1.md:35` *"O Fisio nunca chama o whatsmeow direto: passa pelo `ChannelDriverFactory`"*.
- **Porquê é erro:** REABRE decisão [W] (opção A é exatamente o número compartilhado vetado) e inventa adapter paralelo ao contrato de driver. O banner da linha 3 condiciona só "categorias, preço por template, aprovação de template, Flows" — não cobre a escolha de número.

### R4 · `02-f1-constancia.md:286` — "Fisio só envia pela Cloud API oficial. O transporte não oficial fica proibido para o Fisio (teste falha se o business usar instância QR)"
- **Canon:** ADR 0437 §13.1 (`whatsmeow` = instância QR é o driver decidido); `SPEC.md:85` US-FISIO-009 *"(driver whatsmeow)"*; `HANDOFF-F1.md:93` T-09.
- **Porquê é erro:** inverte a decisão [W]; um teste que "falha se instância QR" reprovaria o caminho canônico.

### R5 · `02-f1-constancia.md:328` — T-09a "Adapter `FisioMessenger` sobre a **Cloud API oficial** … DoD: Business com instância QR não envia nada do Fisio (teste)"
- **Canon:** idem R3/R4; `HANDOFF-F1.md:35,93`.
- **Porquê é erro:** tarefa executável que instrui o Claude Code a implementar o oposto da decisão registrada; não está sob a ressalva do banner (que lista seções, não tarefas).

### R6 · `etapas/06-lancamento.md:107` — "O que puxa o upgrade é o lembrete automático … Ele custa dinheiro real (a ADR cita cerca de [valor] por mensagem de utilidade …)"
- **Canon (HEAD):** ADR 0437 §2 alavanca 1 (`:47`): *"Com o driver `whatsmeow` (§13.1) **não há custo por mensagem**; na API oficial seria cerca de …"*.
- **Porquê é erro:** afirma custo que o canon diz não existir no driver decidido; a própria linha 98 do mesmo arquivo diz que o plano pago usa `whatsmeow`, *"não é a API oficial"* — contradição interna.

### R7 · `06-lancamento.md:151` — onboarding 7–9 min: "no pago, envia pelo **número oficial**"
- **Canon:** ADR §13.1 / §14 nº 5; `06-lancamento.md:98` (mesmo arquivo: *"driver `whatsmeow` … não é a API oficial"*).
- **Porquê é erro:** INVERTE o veredito do próprio arquivo e do ADR.

### R8 · `06-lancamento.md:175` — "IA via Prism só reescreve a legenda no tom do profissional"
- **origin/main diz:** `composer.json:22` → `"laravel/ai": "^0.10"`; `git grep -ic prism origin/main -- composer.json composer.lock` → rc=1 (0 hits; controle positivo `laravel/ai` → 1). ADR 0035 (`origin/main`, `:48`): *"Laravel AI SDK oficial (`laravel/ai`) como camada A — supersedes Prism PHP"*; `:71` Prism é só *fallback se o SDK perder feature crítica*.
- **Porquê é erro:** prescreve dependência inexistente e não canônica ("Não criar nova tecnologia/dependência sem ADR", proibicoes.md).

### R9 · `06-lancamento.md:239` — risco "grátis caro (WhatsApp oficial) | … o lembrete **oficial** é só no pago"
- **Canon:** ADR §2 `:47` (sem custo por mensagem com whatsmeow); §13.1.
- **Porquê é erro:** o risco parte de premissa que o canon elimina; "lembrete oficial" contradiz a linha 98 do mesmo arquivo.

### R10 · `06-lancamento.md:267` — L-02 "**envio oficial no pago** … DoD: grátis nunca chama a API oficial"
- **Canon:** idem R7/R9.
- **Porquê é erro:** tarefa executável contrária à decisão [W]; DoD testa um caminho que não existe no desenho decidido.

### R11 · `etapas/05-f4-ia-questionarios.md:255` — T-F4-03 "`TranscriptionService` com driver configurável (**Prism quando couber**)"
- **origin/main diz:** idem R8 (Prism ausente; ADR 0035 camada A = `laravel/ai`).
- **Porquê é erro:** nomeia pacote inexistente como driver da tarefa.

### R12–R21 · `protocolos/11-fibromialgia.md` — coluna "na biblioteca?" = **sim** (10 linhas)
- **Linhas:** 30 (Respiração diafragmática), 31 (Gato-camelo), 32 (Rotação de tronco deitado), 33 (Alongamento de isquiotibiais sentado), 34 (Retração escapular), 43 (Sentar e levantar), 44 (Ponte de glúteo), 45 (Elevação de panturrilha), 47 (Mini agachamento na parede), 48 (Apoio unipodal).
- **O que o PR contém:** `HANDOFF-F1.md:88` T-04 *"Seed da biblioteca (38 exercícios)"* e `01-biblioteca.md:92,312` *"L0 (seed do código, T-04) · 38"* — **nenhum arquivo lista os 38 nomes**. `git grep -il` por `Gato-camelo`, `Ponte de gl`, `escapular`, `diafragm`, `isquiotibiais`, `unipodal`, `Sentar e levantar` em `Modules/Fisio`, `memory/requisitos/Fisio` e no ADR 0437 (HEAD): cada termo aparece **só no próprio protocolo 11**, exceto `Gato-camelo` (`FisioProgramaEditorTest.php`) e `diafragm` (`FisioJogoTest.php`), onde são nomes de fixture criados com `uniqid()` (`FisioFixtures.php:149,183`), não seed. `01-biblioteca.md` não contém nenhum desses nomes (grep -ci = 0 para 14 termos; controle positivo `panturrilha` = 1, mas em outro contexto).
- **Porquê é erro:** afirmação de pertencimento a um artefato que não existe no lote nem em `origin/main`; evidência não fecha → REFUTADO por default. Contraste honesto: as 4 linhas "**novo** (gravar)" são consistentes com a seção *"Para a biblioteca"* (`:80`) e foram confirmadas.

### R22 · Grupo 5 — `requisitos-status.mjs Fisio --check` rc=1
- SPEC novo (`Fisio/SPEC.md`) sem `_STATUS-GENERATED.md`; o lote regenerou o do Ponto e não derivou o do próprio módulo. O umbrella só itera arquivos existentes → gate mudo.

### R23 · Grupo 5 — `plans-index.mjs --check` rc=1
- `PLANS-INDEX-GENERATED.md` não regenerado após adicionar `features/f1-constancia/plan.md` (delta medido por `--write`: `8 → 9` registrados, linha nova = o plan do Fisio; árvore restaurada).

### R24 · Grupo 5 — `doc-id-index.mjs --check` rc=1
- `governance/doc-id-index.json` sem os ids novos (`requisitos-fisio-*` → 0 ocorrências). Drift atribuível ao lote; o CI cobra só colisões.

## Observações não contadas

1. **ADR 0437 nasce `aceito` no mesmo PR** que a cria (último ADR em `origin/main` = 0436). O CLAUDE.md prevê ratificação `proposto→aceito` por flip de [W]; aqui o aceite vem por `accepted_via` textual. Fora do lote (memory/requisitos) — registro, não conto.
2. **Etapa 02, banner da linha 3** mitiga parte do stale (seções sobre Meta API "valem só se o canal for trocado"), mas enumera só "categorias, preço por template, aprovação de template, Flows" — R3/R4/R5 ficam fora dessa lista. A etapa 06 **não tem banner nenhum** e carrega o mesmo stale (R6/R7/R9/R10). Também 02 T-09b (catálogo de templates/webhooks) e 06 L129/L241 (reclassificação de template) só fazem sentido com `meta_cloud` — não contei por estarem cobertos pelo banner (02) ou por não contradizerem regra (06).
3. **AC-9 (`requirements.md:43`)** diz "pelo `ChannelDriverFactory` … Prova: `FisioWhatsappJobTest`": o teste (HEAD) mede o efeito por `Bus::fake` sobre `Modules\Whatsapp\Jobs\SendWhatsappMessageJob` (existe em `origin/main`), não exercita o factory; o item "alertar o profissional quando o canal cair" não aparece no teste (grep `cair|caiu|alerta` = 0). Confirmei como prova parcial coerente — fica a nota.
4. **Testes do Fisio em HEAD importam classes inexistentes** (`Modules\Fisio\Entities\*`, `Services\AdesaoCalculator`, `Jobs\*`) e 12 arquivos têm `markTestSkipped` — "Prova: X" em `requirements.md` aponta para testes que hoje pulam (LC-13: skip sai exit 0). Não é afirmação falsa (o arquivo existe), é prova ainda vazia.
5. `01-biblioteca.md:161` diz *"protocolos 1–11 aprovados"* e `:323` *"C-04 revisão dos 10 protocolos"* — 10 × 11 no mesmo arquivo (fora da amostra; o protocolo 11 cita C-04).
6. `requirements.md:50` e `11-fibromialgia.md:3` citam `etapas/04-…`/`etapas/01-…` em backtick relativo à raiz do Fisio, não ao próprio diretório — resolvem em HEAD pela raiz; não são links markdown, não contei como âncora quebrada.
7. `plans-index` gerou célula truncada para o plan do Fisio (`"…rodando verde no  |"`) — defeito do gerador sobre o texto do `## Status vivo`, visível só no probe; não está no lote.
8. Nome "Dra. Dejeane" (responsável técnica contratada) e "Dra. Ana · CREFITO-10 12345-F" (mock de rodapé) não são clientes do CRM — não contados como PII.
9. A persona "[W] em Tubarão", "rede pessoal de [W]" (06 §3.6) — persona interna, não PII.

## Scan PII (linhas `+` de `git diff origin/main...HEAD -- memory/requisitos`, 2.746 linhas)

| padrão | controle positivo | hits |
|---|---|---:|
| CPF pontuado | casou | 0 |
| CPF cru (11 dígitos isolados) | casou | 0 |
| CNPJ | casou | 0 |
| telefone BR (DDD + 4/5-4) | casou | 0 |
| telefone cru (10–11 dígitos) | casou | 1 → **falso-positivo** (id numérico de app na App Store, `02-f1-constancia.md` fontes) |
| e-mail | casou | 0 |
| valor em reais (símbolo seguido de dígito) | casou (na 2ª tentativa — a 1ª falhou porque o símbolo é âncora de fim em regex Python; corrigido com escape; §5 2026-08-01, controle que salva) | **45 linhas · 86 ocorrências** |

**Distribuição das 45 linhas:** `01-biblioteca.md` 6 · `02-f1-constancia.md` 2 · `03-f2-camera.md` 8 · `04-f3-operacao.md` 5 · `05-f4-ia-questionarios.md` 8 · `06-lancamento.md` 16. Corroboração pelo instrumento do repo: `node scripts/governance/brl-scan-diff.mjs --base origin/main` → **rc=1**, 52 linhas no PR inteiro (43 no lote `memory/requisitos`, 5 no ADR 0437, 4 na evidência r1); probe `--stdin` com a linha 77 da etapa 02 → flagrada. Regra: `memory/proibicoes.md` *"NUNCA commitar valores BRL em memory/"* + ADR 0407 (memory/ segue integralmente no eixo). São estimativas de custo e preços de concorrentes, não valor de cliente — mas o eixo canônico do arquivo é o lugar, e o mandato conta o padrão.

`pii_hits = 45` · `pii_controles_positivos_ok = 7`.

## Comandos reproduzíveis

```bash
git rev-parse HEAD origin/main; git rev-parse --is-shallow-repository
git diff --name-status origin/main...HEAD -- memory/requisitos
git ls-tree -r origin/main --name-only | grep -ic fisio            # 0 (controle: HEAD → 51)
git ls-tree origin/main --name-only -- Modules/Whatsapp/Services/Drivers/   # MetaCloudDriver.php, WhatsmeowDriver.php, ZapiDriver.php…
git grep -l meta_cloud origin/main -- Modules/Whatsapp | wc -l       # 57
git show origin/main:composer.json | grep -in 'prism\|laravel/ai'   # só laravel/ai
git grep -n 'supersedes Prism' origin/main -- memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md
git grep -n US-PONTO-017 origin/main -- memory/requisitos/Ponto/SPEC.md
sed -n '204,207p;218,227p;47p' memory/decisions/0437-modulo-fisio-vertical-fisioterapia.md
for e in 'Gato-camelo' 'Ponte de gl' 'escapular' 'diafragm' 'isquiotibiais' 'unipodal' 'Sentar e levantar'; do git grep -il "$e" HEAD -- Modules/Fisio memory/requisitos/Fisio memory/decisions/0437-modulo-fisio-vertical-fisioterapia.md; done
node scripts/governance/tasks-index-generate.mjs --check; echo $?
node scripts/governance/requisitos-status.mjs Ponto --check; echo $?
node scripts/governance/requisitos-status.mjs Fisio --check; echo $?
node scripts/governance/plans-index.mjs --check; echo $?
node scripts/governance/doc-id-index.mjs --check-collisions; echo $?
node scripts/governance/doc-id-index.mjs --check; echo $?
node scripts/governance/design-code-map-check.mjs --check --strict; echo $?
node scripts/governance/feature-lint.mjs --check; echo $?
node scripts/governance/brl-scan-diff.mjs --base origin/main >/dev/null 2>&1; echo $?   # 1
# amostra: python3 — ordenar os 17 paths por sha1("b1bb9ddc"+path), pegar 6
```

Árvore deixada limpa: `git status --short` → só esta evidência. Probe de `plans-index --write` revertido com `git checkout -- memory/requisitos/_processo/PLANS-INDEX-GENERATED.md`.

```json
{"itens_verificados": 274, "erros_confirmados": 24, "error_rate_pct": 8.76, "pii_hits": 45, "veredito": "reprovado"}
```
