---
date: "2026-10-18"
topic: "Refutação GT-G5 r3 do lote do PR #9536 (Fisio: BRIEFING/SPEC/trio F1/etapas + 3 índices gerados) — 226 itens, 4 erros, 1.77%, 0 PII; aprovado no limiar"
authors: ["C"]
prs: [9536]
outcomes:
  - "226 itens verificados em 6 de 19 arquivos (seed 182309c9); 4 refutados, todos em etapas/01-biblioteca.md; error_rate 1.77% (< 2)"
  - "0 hits de PII nas 2864 linhas adicionadas (7 padrões, 7 controles positivos); 1 falso-positivo de telefone cru = id de app na App Store"
  - "Máquina derivada: requisitos-status (Ponto/Fisio), tasks-index, plans-index, doc-id-index, feature-lint, anchor-lint, module-surface — todos rc=0"
---

# Session log 2026-10-18 — Refutação GT-G5 · lote PR #9536 · rodada r3

## TL;DR

Veredito **aprovado**: 226 itens verificados contra `origin/main` (e contra o próprio lote onde a âncora nasce nele), **4 erros confirmados** (error_rate **1.77%**), **0 hits de PII**. Os 4 erros estão concentrados em `memory/requisitos/Fisio/etapas/01-biblioteca.md` (inconsistência 10×11 protocolos; decisão [W] sobre protocolos sem recibo no canon; afirmação sobre o HANDOFF que o HANDOFF não contém; mapeamento B-nn→T-nn que contradiz a própria tabela). Os 5 arquivos restantes da amostra fecharam sem erro.

## Cabeçalho

| campo | valor |
|---|---|
| base `origin/main` | `8e99d6cd762429c8fc3fe9e8b03b283a09ff786e` |
| HEAD (lote) | `182309c92202c6d80d9d118a1366efaae347a8b1` |
| repo raso | `git rev-parse --is-shallow-repository` → **true** (nenhuma data de `git log` usada como recibo) |
| sessão fresca | sim — instância nova, sem contexto do gerador nem das rodadas r1/r2 |
| modelo do refutador | Fable 5.1 (tier máximo disponível) |
| `memory/sessions/*refutacao*` abertos | **nenhum** (ver observação sobre um `git grep` repo-wide que devolveu 1 linha de `…-r2.md` — não aberto, não lido além da linha) |
| corpo do PR / commit message lidos como evidência | não |
| árvore ao final | `git status --short` vazio antes de escrever esta evidência |

## §3 checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (Fable 5.1 = tier máximo; igualdade só vale no teto)
- [x] Amostra: 100% anchors nos arquivos amostrados / ≥30% prosa (6 de 19 = 31.6%; seed `182309c9`, ordenação por `sha1(seed + path)`)
- [x] Cada item verificado contra `origin/main` (`git ls-tree`, `git show`, `git grep <ref>`), nunca contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha + porquê)
- [x] Scan PII no diff (7 padrões + 7 controles positivos) — hits = 0
- [x] `error_rate_pct` calculado = 1.77 (< 2)
- [ ] Entry no ledger — **não é minha** (mandato: não escrever no ledger)

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` → **19 arquivos** (16 `A` em `Fisio/`, 3 `M` gerados: `Ponto/_STATUS-GENERATED.md`, `_BACKLOG-GENERATED.md`, `_processo/PLANS-INDEX-GENERATED.md`). Confere com o enunciado.

Contexto que muda a leitura das âncoras: `git ls-tree -r origin/main -- Modules/Fisio` → **0 arquivos**; `memory/requisitos/Fisio` → **0**; `memory/decisions/0437-*` → **ausente em origin/main** (último ADR lá é `0436-os-por-item-producao-dona-da-os-do-pedido.md`). O módulo, o diretório de requisitos e o ADR 0437 **nascem neste PR** (`git diff --name-status origin/main...HEAD` fora de `memory/requisitos`: 36 arquivos em `Modules/Fisio/` + `memory/decisions/0437-…` + `phpunit.xml` + índices). Logo, âncora do lote para o ADR 0437 ou para `Modules/Fisio/**` foi medida contra HEAD, com a nota "nasce no PR".

### Amostra determinística (seed `182309c9`, ceil(0.3 × 19) = 6)

| ordem | sha1(seed+path)[:12] | arquivo |
|---|---|---|
| 1 | `0c333ac28e41` | `memory/requisitos/Fisio/BRIEFING.md` |
| 2 | `145d3f8229f1` | `memory/requisitos/Fisio/features/f1-constancia/requirements.md` |
| 3 | `1c218cf62e11` | `memory/requisitos/Fisio/etapas/01-biblioteca.md` |
| 4 | `32d31f632461` | `memory/requisitos/Ponto/_STATUS-GENERATED.md` |
| 5 | `455c35962610` | `memory/requisitos/Fisio/features/f1-constancia/tasks.md` |
| 6 | `5be426ffd322` | `memory/requisitos/Fisio/SPEC.md` |

Dentro de cada arquivo: 100% das afirmações verificáveis contra o repo/canon. Em `etapas/01-biblioteca.md`, as afirmações de **mercado externo** (tamanhos de biblioteca de concorrentes, preços de TTS/CDN, diretrizes JOSPT) **não são mensuráveis contra `origin/main`** e ficaram **fora da contagem** — declarado aqui para não inflar o denominador.

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---:|---:|---:|---|
| 1 · Âncora existe | 77 | 77 | 0 | `git ls-tree origin/main -- <path>` (blob) para ADRs/docs/código; para o que nasce no PR, `git ls-tree -r HEAD`; controle positivo `Ponto/SPEC.md` → blob; controle negativo `Fisio/NAO-EXISTE.md` → 0 linhas |
| 2 · Âncora não revogada / frontmatter lido pelo leitor real | 5 | 5 | 0 | schema `briefing.schema.json` (enum `status`, `updated_at`), `spec.schema.json` (required/enum), `feature-lint --check` (lê `us`/`parent_plan` do trio), `requisitos-status.mjs Ponto --check` (regenera chave a chave) |
| 3 · Ação × veredito da prosa / afirmação sobre código | 112 | 108 | **4** | leitura linha a linha × ADR 0437 (HEAD), HANDOFF-F1.md, código `Modules/Fisio/**` (HEAD), `Modules/OficinaAuto` e `Modules/Whatsapp` (origin/main), PT-01 (origin/main) |
| 4 · Célula íntegra | 15 | 15 | 0 | script: 15 tabelas / 260 linhas de dados nos 6 arquivos, contagem de células por linha vs cabeçalho (ignorando `\|` e pipes em code-span) → 0 desalinhadas; 274 code-spans, 0 linhas com crase ímpar; sem `map.json` no lote |
| 5 · Máquina derivada | 10 | 10 | 0 | rc literal de cada `--check` abaixo |
| 6 · PII (padrões) | 7 | 7 | 0 | 7 regex sobre 2864 linhas `+`, cada uma com controle positivo sintético |
| **Total** | **226** | **222** | **4** | error_rate = 4/226 = **1.77%** |

### Detalhe do grupo 1 (77)

- BRIEFING (8): ADR 0437 (nasce no PR; blob em HEAD) · `_Geral/BRIEFING.md` ✓ origin/main · `SCOPE.md` · `SPEC.md` · `HANDOFF-F1.md` · `etapas/` (6 arquivos) · `scripts/memory-schemas/briefing.schema.json` ✓ origin/main · `Modules/Fisio/Resources/js/Pages/Fisio/` (5 `casos.md` em HEAD).
- SPEC (17): ADRs 0437, 0093, 0306, 0368, 0358, 0264, 0273 (6 em origin/main, 0437 no PR) · `HANDOFF-F1.md` · `etapas/` · `etapas/01-biblioteca.md` · `etapas/02-f1-constancia.md` · `features/f1-constancia/` · `Modules/Whatsapp/Services/Drivers/ChannelDriverFactory.php` ✓ origin/main · `FisioModuloVisibilidadeTest` + `FisioModuloContratoTest` (HEAD) · `App\Utils\ModuleUtil` (usado em `Modules/Fisio/Services/FisioAcesso.php`) · padrão OficinaAuto token HMAC + PIN + lockout (`Modules/OficinaAuto/Services/AprovacaoOsService.php`: `pin_hash|locked_until` + `hmac` 4 hits em origin/main).
- requirements (26): `../../SPEC.md`, `../../../../decisions/0437-…`, `../../HANDOFF-F1.md`, `../../etapas/01-…`, `../../etapas/02-…`, `etapas/04-f3-operacao.md`, ADRs 0105/0368/0306/0358 (10) + 16 nomes de teste citados nos AC (`FisioMultiTenantTest`, `FisioModuloVisibilidadeTest`, `FisioModuloContratoTest`, `FisioProntuarioTest`, `FisioPacienteCrudTest`, `FisioProgramaEditorTest`, `FisioPortalAcessoTest`, `FisioCheckinTest`, `FisioPortalSegurancaClinicaTest`, `FisioJogoTest`, `NivelCalculatorTest`, `SequenciaCalculatorTest`, `FisioWhatsappJobTest`, `FisioPainelTest`, `AdesaoCalculatorTest`, `AtencaoDetectorTest`) — 16/16 existem em `Modules/Fisio/Tests/{Feature,Unit}` em HEAD.
- tasks (12): `../../HANDOFF-F1.md` · `../../etapas/` · `Modules/Producao` (molde — `DataController.php` e `InstallController.php` existem em origin/main) · `phpunit.xml` (diff registra `Modules/Fisio/Tests/Feature` e `Tests/Unit`) · `scripts/governance/anchor-lint.mjs` ✓ origin/main · ADRs 0070/0302/0273 · `etapas/01`, `etapas/02` · `FisioBibliotecaTest` (HEAD).
- etapa 01 (13): `../protocolos/11-fibromialgia.md` (lote) · ADR 0035 ✓ · `PT-01-Lista.md` ✓ (atalhos `J/K`, `/`, `Enter` nas linhas 77/106/109) · "DS v6" (214 arquivos em origin/main citam) · campos do HANDOFF citados: `is_custom`, `default_*`, `times_per_day`, `condition_flags` (HANDOFF-F1.md:54,57) · tabelas existentes citadas em §3.10: `fisio_exercises`, `fisio_protocols`, `fisio_patients`, `fisio_program_items` (HANDOFF-F1.md:54–60).
- Ponto `_STATUS` (1): `US-PONTO-017` existe em `origin/main:memory/requisitos/Ponto/SPEC.md:378`.

### Detalhe do grupo 2 (5)

1. BRIEFING frontmatter: `status: em-construcao` ∈ enum do `briefing.schema.json`; `updated_at` no padrão `YYYY-MM-DD`; `module: Fisio` — ✓.
2. SPEC frontmatter: required `module`/`version`/`last_updated` presentes; `status: rascunho` ∈ enum; `additionalProperties: true` (logo `owner: wagner` não reprova) — ✓.
3. `requirements.md` lido por `feature-lint --check`: 12 US reconhecidas, 11 AC — ✓ (rc=0).
4. `tasks.md` lido por `feature-lint --check`: 11 tasks, `us:` por task casa com o SPEC — ✓.
5. `Ponto/_STATUS-GENERATED.md` regenerado por `requisitos-status.mjs Ponto --check` → "em dia" — ✓ (a mudança 16→17 e a linha `US-PONTO-017 | desconhecido` são derivadas: a US em origin/main não tem campo `status:` na linha de owner, mesmo formato de `US-PONTO-016`, que já era `desconhecido`).

### Detalhe do grupo 3 (112 · 4 refutados)

- BRIEFING (10/10 ✓): `status_nota` (ADR aceita 2026-10-18 — frontmatter do ADR: `status: aceito`, `decided_at: "2026-10-18"`); "esqueleto (pacote, permissões, menu condicionado, instalação) no PR" (`DataController::superadmin_package` → `fisio_module`; `user_permissions` → `fisio.access`/`fisio.editar`; `modifyAdminMenu`; `InstallController` + 4 rotas install); "suíte de testes da F1 escrita antes do código" (17 arquivos Pest, 138 `it(`); "ainda não há schema, rotas de negócio nem telas" (0 arquivos em `Modules/Fisio/Database`, grupo de rotas autenticadas vazio em `Routes/web.php`, 0 `.tsx`); Dra. Dejeane responsável (ADR §14 nº 4); §13.1 whatsmeow/risco; §5 Anvisa; §14 abertas; próxima ação com `feature-lint --check` verde (rc=0); path de telas `Modules/Fisio/Resources/js/Pages/Fisio/` (onde os 5 `casos.md` estão).
- SPEC (20/20 ✓): decisão aceita 2026-10-18; Dra. Dejeane; US-001 "4 ajustes do HANDOFF" (HANDOFF-F1.md:30–35 lista exatamente 4: XP por config, justificativa de contraindicação, busca condicionada, WhatsApp pelo `ChannelDriverFactory`); US-002 esqueleto sem rotas de negócio/schema/telas; DoD por `FisioModuloVisibilidadeTest`/`FisioModuloContratoTest`; `ModuleUtil` pelo container (`app(ModuleUtil::class)` em `FisioAcesso.php`); US-003 "nenhuma migration `fisio_*`" (0); testes citados existem; US-004 "38 exercícios" (HANDOFF T-04 = 38; etapa 01 L0 = 38); "sem seed"; US-005 Res. COFFITO 414/2012 (ADR §4); US-006 ADR §13.2 aviso nunca bloqueia; US-007 padrão OficinaAuto token HMAC + PIN + lockout (código em origin/main) e Res. 619/2025 (ADR §4); US-008 ADR §6 + `etapas/02`; US-009 `ChannelDriverFactory` + §13.1; US-010 fila de abandono ≠ alerta clínico (ADR §5/§6); US-011 escalas livres EVA/RMDQ/PSFS/LEFS/SPADI e bloqueadas ODI/NDI/KOOS/QuickDASH/WOMAC (ADR §8, 1:1); US-012 ADR 0273 `verificado@sha7`.
- requirements (22/22 ✓): `screens: []` até Pages; "`casos.md` já escritos e entram com o esqueleto" (5 em HEAD); sinal = ADR 0437 aceita + 3 peças (os 4 concorrentes nomeados aparecem no ADR §2; 6 especificações em `etapas/`); 30–50% (ADR §1); citação textual de 2026-10-17 (ADR §3.1, idêntica); citação "whatsmeow será o driver" (ADR §13 item 1); Dejeane; decisão aberta nº 3 (ADR §14); AC-1…AC-11 (11 itens, cada um casa com ADR §4/§5/§6/§8/§13 e HANDOFF T-07…T-11; AC-2 "403/404" — o middleware `FisioLiberado` devolve 403 segundo `Routes/web.php`); fora de escopo F2/F4 (ADR §5/§9), F3 (ADR §9 + `etapas/04`), questionários pagos (ADR §8).
- tasks (22/22 ✓): `parent_plan: "fisio-f1-constancia"` (= `plan.md`, refletido no PLANS-INDEX); ADR 0302 sem `status:`; numeração deslocada em 1 vs HANDOFF (HANDOFF T-00…T-12, T-02 = esqueleto ✓); "README da suíte usa a numeração do HANDOFF" (`Modules/Fisio/Tests/README.md:3` "T-02 a T-10", `:146` "Questionários (T-11)" ✓); T-01…T-11 ↔ US-FISIO-002…012 (11 pares, todos batem com os títulos do SPEC); T-01 molde Producao + `phpunit.xml`; T-02 "11 tabelas" (11 `fisio_*` distintas no HANDOFF) e escopo `business_id = sessão OU nulo` (HANDOFF:57); T-03 38 exercícios; T-07 `config/fisio.php` com números de `etapas/02` (HANDOFF ajuste 1 confirma `config('fisio.xp.*')`); T-08 teto/intervalo/alerta (HANDOFF T-09); T-11 `anchor-lint … → anchored_ok` (comando roda; hoje 12 `pendente`, 0 `anchored_ok`, coerente com `_pendente_`).
- etapa 01 (35 · **4 refutados**): L3 pesquisa 2026-10-17 (ADR §13) ✓ · L11 meta 6→9 (ADR §13) ✓ · L17 P58 ✓ · L45 "queixa nº 1 contra o MedBridge, ADR §2" ✓ · L64 `is_custom` ✓ · L68 "ADR §2, erro 2" ✓ · L92 L0 = 38 em T-04 ✓ · **L93/L145/L289/L316/L323 "10 protocolos" × tabela com 11 — REFUTADO (R1)** · L97 ADR §4 sem depoimento ✓ · L111 `default_*`/`times_per_day` ✓ · L113 `condition_flags` ✓ · L118 ADR §12 ✓ · L120–129 os 8 flags = HANDOFF:54 ✓ · L131 P10 ✓ · L137 ADR §5 P10 ✓ · L139 P58 ✓ · **L161 "aprovados por [W] em 2026-10-18" — REFUTADO (R2)** · L161 B-09/C-04 existem no arquivo ✓ · L216 F1 `is_custom` ✓ · **L218 "o handoff proíbe guardar vídeo de paciente na F1" — REFUTADO (R3)** · L218 Código de Ética ADR §4 ✓ · L229 ADR §12 ✓ · L230 Res. 414 ✓ · L231 palavras proibidas ADR §5 ✓ ("cura" é acréscimo, não contradiz) · L236 "ADR põe busca na F4" ✓ (§13 tabela `05-f4` "ditado, busca…" + §14 nº 3) · L243 atalhos PT-01 ✓ (`Enter` adaptado de "abre" para "adiciona" — observação) · L184/189 DS v6 ✓ · L249 "mantém `contraindication_flags`, `safety_notes`, `needs_support`" ✓ · L249–257 tabelas existentes ✓ · L263 ADR 0035 + P57 ✓ · L274 ADR §5 ✓ · L280 P57 ✓ · **L304 "B-05 a B-08 detalham T-06; B-09 e B-10 entram em T-07" — REFUTADO (R4)** · L312 B-05 "T-04" ✓ (consistente com o HANDOFF; é o que contradiz L304) · L313 B-06 ✓.
- Ponto `_STATUS` (3/3 ✓): "US no SPEC | 17" (17 `### US-PONTO-` em `origin/main:…/Ponto/SPEC.md`); `US-PONTO-017 | desconhecido` (sem `status:` na linha de owner — mesma derivação de US-016); título idêntico ao SPEC.

## REFUTADOS

### R1 · `memory/requisitos/Fisio/etapas/01-biblioteca.md` · contagem de protocolos (10 × 11)

- **Afirmação:** §3.4 abre com *"Os 10 primeiros protocolos"* (L145); L93 *"os 10 protocolos da §3.4"*; §5 L289 *"Cobertura dos 10 protocolos"*; B-09 L316 *"seed dos protocolos 1–10"*; C-04 L323 *"revisão dos 10 protocolos"*.
- **O que o próprio arquivo diz:** a tabela da §3.4 tem **11 linhas** (L149–L159), a 11ª é Fibromialgia com link para `../protocolos/11-fibromialgia.md` (arquivo do lote), e L161 afirma *"os protocolos **1–11** foram aprovados"*.
- **Porquê é erro do lote:** OMITE/TRUNCA — o 11º protocolo entra na tabela e na "aprovação", mas fica fora do DoD de B-09 (seed 1–10), da métrica de cobertura (§5) e da trilha C-04. Um executor que siga B-09 ao pé da letra não semeia a fibromialgia, que o mesmo arquivo declara aprovada. Contado como **1** item (mesma inconsistência em 5 linhas).

### R2 · `memory/requisitos/Fisio/etapas/01-biblioteca.md:161` · decisão [W] sem recibo no canon

- **Afirmação:** *"os protocolos 1–11 foram aprovados pelo produto por [W] em 2026-10-18. Isso libera a construção (B-09) e o seed em `rascunho`"*.
- **O que o canon diz:** o ADR 0437 (HEAD) registra em `accepted_via` o que o aceite de 2026-10-18 cobre — *"a decisão, a seção Fisio no menu (§3.3) e as emendas da §13"* — e a §14 lista as decisões de [W] com o que foi decidido na data (nº 1, 4, 5). Aprovação de protocolos **não consta** em nenhum dos dois. `git grep -n -iE 'protocolos (1.11|1–11|1-11)|aprovad[oa]s? pelo produto' HEAD -- memory Modules/Fisio` excluindo a própria etapa 01 → **0 recibos** no lote/canon (o único hit fora dela é uma linha de evidência de rodada anterior, não um registro da decisão). O ADR §13 ainda fixa: *"em conflito, vale esta ADR até ser emendada"*.
- **Porquê é erro do lote:** carimba uma decisão do dono com data, sem âncora em ADR/§14/HANDOFF — "Decidir" só vale nos termos da prosa canônica, e a prosa canônica (ADR) não a contém. Se [W] decidiu em chat, o lugar é a §14 do ADR (ou emenda), não só uma etapa.

### R3 · `memory/requisitos/Fisio/etapas/01-biblioteca.md:218` · afirmação sobre o HANDOFF que o HANDOFF não contém

- **Afirmação:** *"O handoff proíbe guardar vídeo de paciente na F1"*.
- **O que o lote diz:** `grep -niE 'v[íi]deo' memory/requisitos/Fisio/HANDOFF-F1.md` → **0 linhas** (controle positivo no mesmo arquivo: `grep -niE 'imagem|is_custom'` → 2 hits, L44 e L57). `grep -rniE 'v[íi]deo d[eo] paciente' memory/requisitos/Fisio/ memory/decisions/0437-*` → 0. O HANDOFF fala de consentimento de **imagem** (L44) e de `is_custom` (L57), não de proibição de vídeo de paciente.
- **Porquê é erro do lote:** INVENTA uma regra atribuída a outro documento do mesmo lote. A regra pode ser desejável (o ADR §4 veda imagem de paciente para autopromoção), mas a atribuição ao "handoff" é falsa, e quem for ao HANDOFF conferir não acha.

### R4 · `memory/requisitos/Fisio/etapas/01-biblioteca.md:304` · mapeamento B-nn → T-nn contradiz a própria tabela e o HANDOFF

- **Afirmação:** *"B-01 a B-04 detalham T-03/T-04; **B-05 a B-08 detalham T-06**; **B-09 e B-10 entram em T-07**"*.
- **O que o próprio arquivo e o HANDOFF dizem:** B-05 é *"Seed L0 (38 exercícios, **T-04**)"* (L312) e L92 também amarra L0 a T-04; no HANDOFF (numeração T-00…T-12 que a etapa usa), T-06 = *"Editor de programa + protocolos-modelo + alerta de contraindicação"* (L90) e T-07 = *"Portal: token + PIN…"* (L91). B-09 (*"Protocolos-modelo: tela com diretriz…"*, L316) é escopo de T-06, não de T-07; B-10 (player do portal) é T-07 ✓.
- **Porquê é erro do lote:** CITA ITEM ERRADO — dois dos três mapeamentos da frase-guia de encaixe estão errados (B-05→T-06 e B-09→T-07), e a frase é o que um executor lê para saber onde cada B-nn se encaixa no plano. Contado como **1** item.

## Observações não contadas

1. **ADR 0437 e `Modules/Fisio` nascem neste PR** — não existem em origin/main. Todas as âncoras do lote para eles foram medidas contra HEAD e marcadas como tal. O ADR L20 diz *"Número a confirmar contra o main (o último visto foi 0436)"*: confirmado, `0436-os-por-item-…` é o último em origin/main; 0437 está livre.
2. **`git grep` repo-wide (R2) devolveu 1 linha de `memory/sessions/2026-10-18-refutacao-gt-g5-lote-9536-r2.md`** (hit em "protocolos 1–11"). O arquivo **não foi aberto nem lido além dessa linha**; o achado R1 já estava formulado antes desse grep (derivado da leitura da §3.4). Registro para o workflow decidir se isso compromete a frescura; declaro `abriu_evidencia_anterior: false` porque não houve abertura.
3. `Modules/Fisio/Tests/README.md:3` cita o handoff como `HANDOFF-fisio-f1-claude-code.md` — esse nome **não existe** em HEAD (0 hits em `git ls-tree -r HEAD`); o arquivo do lote é `memory/requisitos/Fisio/HANDOFF-F1.md`. Fora de `memory/requisitos` e fora da amostra; ponteiro podre a corrigir.
4. `tasks.md`: T-03 (seed da biblioteca) declara `covers: AC-4` (aviso de contraindicação, cuja prova é `FisioProgramaEditorTest`, da T-05) e T-11 (fechar o loop) declara `covers: AC-1` (multi-tenant, provado na T-02). Mapeamento fraco, não factualmente falso — `feature-lint` aceita.
5. `BRIEFING.md` §Regra de manutenção cita `topicos/<slug>.md` — não há diretório `topicos/` em `memory/requisitos/Fisio/` (boilerplate do template).
6. `validate-memory-schema.sh spec` emite 2 warnings: SPEC sem `## Histórico` e sem `## Referências` (recomendados, não erro). O validador Ajv (`scripts/memory-schemas/validate.mjs`) **não rodou aqui** — `node_modules` ausente (deps `gray-matter/ajv`); o CI cobre. Validação de enum/required feita à mão a partir dos schemas.
7. `feature-lint --check` emite 1 aviso advisory para `Fisio/f1-constancia`: `[feature-sem-sdd]` (nenhum SDD ligado). Coerente com `sdd: []` no `requirements.md`.
8. `anchor-lint` no SPEC: 12 US `pendente`, 0 `anchored_ok` — coerente com todas as `**Implementado em:** _pendente_`.
9. HANDOFF-F1.md:28 afirma "17 arquivos Pest (138 `it()`)": medido 17 arquivos e 138 linhas iniciando por `it(` ✓ (fora da amostra).
10. Nome "Dra. Dejeane" (profissional contratada, citada pelo próprio [W] no `accepted_via` do ADR) aparece em BRIEFING/SPEC/requirements — **não** é cliente do CRM; não contado como PII.
11. `design-code-map-check.mjs --check --strict` rc=0 (3 avisos em KB/Produto/RecurringBilling — nenhum toca o lote; o lote não tem `map.json`).

## Scan PII (linhas `+` de `git diff origin/main...HEAD -- memory/requisitos` · 2864 linhas)

| padrão | hits | controle positivo |
|---|---:|---|
| CPF pontuado (`ddd.ddd.ddd-dd`) | 0 | OK |
| CPF cru (11 dígitos isolados) | 0 | OK |
| CNPJ (`dd.ddd.ddd/dddd-dd`) | 0 | OK |
| telefone BR formatado (DDD + 4/5-4) | 0 | OK |
| telefone cru (10–11 dígitos isolados) | 1 → **falso-positivo** (id de app em URL da App Store, `apps.apple.com/app/id…`, linha 1570 do diff) | OK |
| e-mail | 0 | OK |
| valor em reais (símbolo seguido de dígito; literal do padrão omitido de propósito) | 0 — todas as ocorrências do símbolo no lote vêm seguidas de `[redacted Tier 0]` | OK |

**pii_hits = 0 · controles positivos = 7/7.** Nomes de cliente do CRM: nenhum (os nomes citados são concorrentes de mercado, autores de diretrizes e a responsável técnica contratada).

## Comandos reproduzíveis

```bash
# base / HEAD / raso / escopo
git rev-parse HEAD origin/main; git rev-parse --is-shallow-repository
git diff --name-status origin/main...HEAD -- memory/requisitos

# amostra determinística
python3 - <<'EOF'
import hashlib,math
seed="182309c9"; paths=[l.split('\t')[1] for l in open('/dev/stdin')]
EOF
# (ordenar por sha1(seed+path), pegar os 6 primeiros)

# existência em origin/main (controle positivo / negativo)
git ls-tree origin/main -- memory/requisitos/Ponto/SPEC.md          # blob
git ls-tree origin/main -- memory/requisitos/Fisio/NAO-EXISTE.md | wc -l   # 0
git ls-tree -r --name-only origin/main -- Modules/Fisio | wc -l      # 0 (nasce no PR)
git ls-tree --name-only origin/main -- memory/decisions/ | grep -E '^memory/decisions/04[3-9][0-9]' | sort | tail -1   # 0436
git grep -l "class ChannelDriverFactory" origin/main -- Modules
git grep -il -E 'pin_hash|lockout|locked_until' origin/main -- Modules/OficinaAuto
git grep -nE '\bJ/K\b|`/`' origin/main -- memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md
git show origin/main:memory/requisitos/Ponto/SPEC.md | grep -c '^### US-PONTO-'   # 17

# R2 / R3
git grep -n -iE 'protocolos (1.11|1–11|1-11)|aprovad[oa]s? pelo produto' HEAD -- memory Modules/Fisio | grep -v 'etapas/01-biblioteca.md'
grep -niE 'v[íi]deo' memory/requisitos/Fisio/HANDOFF-F1.md; echo rc=$?          # 0 linhas, rc=1
grep -niE 'imagem|is_custom' memory/requisitos/Fisio/HANDOFF-F1.md              # controle positivo: 2 hits

# máquina derivada (rc literal)
node scripts/governance/requisitos-status.mjs Ponto --check      # rc=0 "em dia"
node scripts/governance/requisitos-status.mjs Fisio --check      # rc=0 "em dia"
node scripts/governance/tasks-index-generate.mjs --check         # rc=0 (1095 US · 968 abertas · 127 done)
node scripts/governance/plans-index.mjs --check                  # rc=0 (9 registrados, 27 pendentes)
node scripts/governance/doc-id-index.mjs --check-collisions      # rc=0 (0 colisão em 2995 ids)
node scripts/governance/feature-lint.mjs --check                 # rc=0 (0 erros, 1 aviso feature-sem-sdd)
node scripts/governance/anchor-lint.mjs memory/requisitos/Fisio/SPEC.md   # rc=0 (12 pendente)
node scripts/governance/module-surface.mjs Fisio --check         # rc=0 (36 arquivos, sem drift)
node scripts/governance/design-code-map-check.mjs --check --strict   # rc=0
bash .github/scripts/validate-memory-schema.sh spec memory/requisitos/Fisio/SPEC.md   # rc=0 (2 warnings)

# PII: 7 regex sobre as linhas '+' do diff, cada uma com controle positivo sintético (script no scratchpad da sessão)
git diff origin/main...HEAD -- memory/requisitos | grep '^+' | grep -v '^+++' | wc -l   # 2864
```

```json
{"itens_verificados": 226, "erros_confirmados": 4, "error_rate_pct": 1.77, "pii_hits": 0, "veredito": "aprovado"}
```
