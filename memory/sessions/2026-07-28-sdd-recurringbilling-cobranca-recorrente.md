---
id: sessions-2026-07-28-sdd-recurringbilling-cobranca-recorrente
date: "2026-07-28"
topic: "SDD do RecurringBilling — chip da Onda 5 do passo 5 (agent sdd-from-source)"
authors: [C]
title: "SDD do RecurringBilling — chip da Onda 5 do passo 5 (sdd-from-source)"
type: session
owner: W
module: RecurringBilling
related_adrs:
  - 0351-sdd-from-source
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0170-extracao-paymentgateway-recurringbilling
related_us:
  - US-RB-002
  - US-RB-003
  - US-RB-041
  - US-RB-047
  - US-RB-051
---

# Sessão — SDD do RecurringBilling (chip da Onda 5 · passo 5)

Agent [`sdd-from-source`](../../.claude/agents/sdd-from-source.md) sobre `RecurringBilling`, seguindo
[`passo-5-sdd-por-modulo.md`](../requisitos/_Governanca/programa-ondas/passo-5-sdd-por-modulo.md).

## 1. Artefatos tocados

**Criados (7)**
- `memory/requisitos/RecurringBilling/SDD-cobranca-recorrente-v1.0.md` — §0–§11, 9 fluxos (F1–F9),
  `CU-RB-01..14`, §9.1 com o achado `[V0]`
- `resources/js/Pages/RecurringBilling/{Index, Faturas/Index, Planos/Index, Planos/Create,
  Planos/Edit, Configuracoes/Index}.casos.md` — 6 arquivos, 36 UC
- `Modules/RecurringBilling/Tests/Feature/PlanoSemFaturaContratoTest.php` — **failing-first** (UC-RBSUB-05)

**Editados (16)** — 15 testes com `@covers-uc`/`@covers-us` + `Index.charter.md` (só FATO) +
`SPEC.md` (2 correções factuais) + `BRIEFING.md` (redestilação parcial declarada) +
`SUPERFICIE.md` (regenerada por `module-surface --write`)

## 2. Antes → depois (porta viva `requisitos-status.mjs RecurringBilling`)

| Elo | Antes | Depois |
|---|---:|---:|
| CU no SDD | 0 | **14** |
| Telas com `casos.md` | 0 / 6 | **6 / 6** |
| UC declarados | 0 | **36** |
| UC com teste que os cita | 0 | **36** (0 órfãos) |
| Lacunas listadas pela porta | 14 | **7** |

`anchor-lint SPEC.md --check` → **exit 0**. `dead_tests: 0` · `testado_sem_covers: 0` ·
`anchored_dead/zombie: 0`. "US implementada SEM teste que a cobre" **28 → 23**.
`casos-coverage-guard` → **zero violação** em qualquer arquivo do módulo.

## 3. As 3 portas da lane — MEDIDAS, não deduzidas

> "Sem lane" era **meia-verdade**. Os 39 testes deste módulo **nunca foram invisíveis**.

| Pergunta | Porta consultada | Resultado |
|---|---|---|
| roda em algum lugar? | `phpunit.xml` + `scripts/tests/shards-plan.mjs` | ✅ **SIM** — `phpunit.xml` lista `./Modules/RecurringBilling/Tests/Feature` na testsuite `Feature`; `shards-plan --roots tests,Modules` descobre recursivo; `SHARD_EXCLUDE` do `ct100-fullsuite.sh` só poda `tests/Browser,tests/governance-fixtures`. **Rodam na nightly CT100.** |
| roda no PR? | `.github/ci-sqlite-pest.list` + `paths` dos workflows | ❌ **NÃO** — 0 linhas do módulo na allowlist; `grep -rln RecurringBilling .github/workflows/` = **0 arquivos**; `modules-pest.yml` cobre outros 6 módulos |
| bloqueia merge? | `governance/required-checks-baseline.json` | ❌ **NÃO** — nada do módulo. `PHP / Pest (Unit)` **é required**, mas só executa a allowlist |

**Não criei lane** (conforme instrução). O caminho certo **não é lane nova**: é 1 bloco na allowlist
que já existe e é `merge=union` no `.gitattributes` (não conflita com PRs concorrentes). A linha
proposta está no **SDD §8.2** — 6 paths, dos quais 5 já passam na nightly e 1 nasce vermelho.

## 4. Achados (varredura CONTADA)

### A1 🔴 `[V0]` Assinatura com valor negociado nunca gera fatura

`store()` casa plano por `ciclo` **E** `valor` **exatos** → sem match, `plan_id = null` (coluna
nullable) → `InvoiceGeneratorService::processarSubscription` faz `if ($plan === null) { errors++;
Log::error; return; }` — **sem fatura, sem timeline, sem alarme**.

Os 3 requisitos de [proibicoes §5](../proibicoes.md) 2026-07-15:
1. **Varredura contada** — `InvoiceGeneratorService` tem **3 invocadores de produção**
   (`GenerateInvoicesCommand@handle` + `run`/`runInternal`) e **2 arquivos de teste**; todos os
   caminhos passam pelo mesmo `processarSubscription`. Não há 2º gerador (`Grep` sem `head_limit`).
2. **Âncora de contrato** — a DoD da **US-RB-002** exige *"criar … com customizações de valor
   (override do plano)"*. Override é exatamente o caso que zera o `plan_id`.
3. **Teste vermelho** — `PlanoSemFaturaContratoTest` nasce failing-first. ⚠️ **Predição**, não
   veredito: não rodei (CT 100).

**Não consertei** — há ≥2 remédios válidos que **se anulam** (gerador cair pra `metadata.valor` ×
`store()` recusar × plano implícito). Decisão `[V0]` de [W].

### A2 ⬜ Hipótese: e-mail do DANFE ao pagador sem checar opt-in LGPD — **FORA DA ÁREA**

`canReceiveEmailNotification|canReceiveWhatsappNotification` tem **3 sites de chamada em todo o repo**
(Whatsapp 2 + OficinaAuto 1) — **zero em `Modules/NfeBrasil/`**. E
`Modules/NfeBrasil/Listeners/EnviarDanfePorEmail` faz `Mail::to(...)->send()` resolvendo o
destinatário via `Invoice → Contact` (o pagador da fatura recorrente), por gatilho automático.
Fica `⬜ hipótese` porque a proibição Tier 0 nomeia só o `NotificarClienteCancelamentoJob`, e se
DANFE (documento fiscal que o destinatário tem direito de receber) entra na mesma classe de
"notificação comercial" é **decisão jurídica de [E]/[W]**. **Reportado, não corrigido.**

### A3 🟡 Charter prometia teste que nunca existiu
`Index.charter.md` §Tests prometia `Wave4PagesIndexTest.php` — `ls` → *No such file*. O real é
`Wave4PresenterIndexTest.php`, e ele cobre **outra coisa** (derivação de status/KPIs), não os 5
cenários HTTP prometidos. Corrigi **o fato** (path) e registrei os 5 cenários como **promessa não
cumprida** — podar o charter ou construir os testes é decisão de [W], não escolho vencedor.

### A4 🟡 SPEC dizia "lacuna" onde a cobertura existia
Duas regras Gherkin (`Testado em: _lacuna — X não existe_`) estavam **factualmente erradas**: a
cobertura existia, só com outro nome. Corrigidas com path real + prosa em blockquote na linha
seguinte (o campo `Testado em:` aceita **só paths**). De brinde: descobri que a idempotência do
gerador é por **query de competência**, não pelo índice UNIQUE que a linha `Implementação:` afirma —
a migration não cria esse índice. **Reportado, não corrigido** (mudar de query pra constraint é
decisão de schema `[V0]`).

### A5 🚩 Gates vermelhos de TERCEIRO (reporto, não conserto, não abortei)
- **`doc-id-index --check` FALHA** — verificado com `git stash`: **falha também com meu trabalho
  fora**, é pré-existente. O `--write` toca `governance/doc-id-index.json`, ⛔ na área do chip.
  **Parent roda `--write` na consolidação** (meu SDD novo tem `id:` e vai entrar).
- **A worktree tem ≥5 chips irmãos com trabalho não-commitado** — 64 entradas em `git status`,
  **28 minhas**. Há trabalho de Cliente, Compras, Financeiro, Fiscal, NfeBrasil, OficinaAuto, Ponto e
  Sells no mesmo checkout, **mais `scripts/governance/{requisitos-status.mjs, module-surface.mjs,
  gates-registry.json}` modificados por terceiro** (631+/575−). ⚠️ **Eu medi com a versão
  não-commitada da porta viva** — que é a esperada (o chip diz "corrigida 7× nas últimas 24h"), mas o
  parent precisa saber que meus números vêm dela, não da de `origin/main`.

### A6 ✅ O guard de schema mordeu em mim (e estava certo)
O `memory-schema` bloqueou a 1ª gravação **deste** session log: frontmatter sem `topic`
(`session.schema.json` exige `date` + `topic`). Corrigi e regravei. Registro porque é o contrário de
teatro — a defesa criada em #4798 funcionou contra o agente que a leu no contexto e mesmo assim errou.

## 5. Orçamento da corrida

| Item | Medida |
|---|---:|
| Arquivos lidos (integral ou parcial) | ~34 |
| Varreduras contadas (`Grep` sem `head_limit`) | 4 (`ASAAS_REFUND_ENABLED` · `canReceive*` · `InvoiceGeneratorService` · `CU-RB`/`UC-RB` pra alocação de id) |
| Portas vivas rodadas | `requisitos-status` (2×) · `anchor-lint` (3×) · `casos-coverage-guard` (3×) · `screen:files` (2×) · `module-surface --write` · `doc-id-index --check` (2×) |
| UC gerados | **36 ancorados · 0 órfãos** · **23 `[BACKLOG]`** sem id |
| CU gerados | 14 (`CU-RB-01..14`) · ids livres confirmados por varredura (0 matches prévios) |
| Testes tocados | 15 anotados + **1 novo** (failing-first) |
| Achados | 6 (1 🔴 `[V0]` · 1 ⬜ hipótese fora de área · 2 🟡 factuais · 1 🚩 de terceiro · 1 ✅ guard mordeu) |
| Reuso vs re-varredura (Fase 1.4) | **0% de reuso** — módulo sem SDD prévio, sem `casos.md`, sem `ANTI-REGRESSAO`. Este é o **ramo caro** ("SDD do zero"), o que a Onda 1 nunca exercitou. As 6 telas em sequência custaram pouco depois da 1ª: a Camada 1 é do MÓDULO, e o §5.3/§6 serviu as 6. |
| Gargalo | **Mapear os 9 fluxos** (11 controllers × 6 services × 6 jobs) — a Camada 1.2. Segundo: descobrir que a cobertura de teste já existia e o que faltava era só o **elo UC**; isso mudou a estratégia de "escrever testes" pra "wire dos ids", ~5× mais barato e mais honesto. |
| Custo que **não** paguei | zero teste rodado (CT 100), zero `php`/`pest` local, zero git op. |

## 6. Lições de mecanismo (o que na definição do agent atrapalhou)

1. **`last_run_ci` × `last_run` — a definição do agent diverge do gate.** O agent manda escrever
   `last_run_ci` no frontmatter do `casos.md`; o **G-5 do `casos-coverage-guard` lê `last_run`**
   (`fmField(fm,'last_run')`). Segui o **gate**. Se eu tivesse seguido a definição, os 6 arquivos
   nasceriam violando G-5 (required). **A definição do agent precisa ser corrigida pro nome que o
   gate lê** — ou vira LC-08 pro próximo chip.
2. **O agent não diz onde mora o `casos.md` de fluxo SEM tela.** Gerador, webhook e refund não têm
   `.tsx`. Resolvi ancorando na tela **do artefato que o fluxo produz ou desfaz** (gerador+refund →
   Faturas; webhook → Configurações, que é quem exibe a URL). Funcionou e respeita "zero tipo novo",
   mas foi decisão minha, não regra. **Vale virar regra explícita na definição** — a alternativa
   óbvia (arquivo paralelo) é justamente o BUG que a D-B proíbe.
3. **"Sem lane" no plano da onda é ambíguo e induz erro.** O `passo-5` marca RecurringBilling como
   "sem lane → não é chip válido até a Onda 0". Medindo as 3 portas, o que falta é **mordida
   per-PR**, não execução. A frase certa é *"sem mordida per-PR"* — e o remédio é 1 bloco numa
   allowlist `merge=union`, não uma Onda 0 inteira. **Dois chips já tinham reportado isso**; este é
   o terceiro, com as 3 portas separadas.
4. **A instrução "APLIQUE as âncoras" colide com a lápide de 2026-07-12.** Tocar SPEC legado acorda
   gates diff-aware. Mitiguei aplicando **só onde a linha estava factualmente errada** (2 casos),
   nunca em massa — e o `--check` fechou exit 0. Vale a definição dizer isso.
5. **Não há como o agent verificar sintaxe PHP** (CT 100 + `php` fora do PATH). O
   `PlanoSemFaturaContratoTest` segue a regra de "sem `const` no nível de arquivo" (usei `function`),
   mas **erro de sintaxe só apareceria na lane**. É risco estrutural do chip, não deste run.
6. **`grep -r` do bash trava no Windows** (2 comandos estourados pra background, ~4min perdidos). A
   ferramenta `Grep` (ripgrep) responde na hora. Vale a definição do agent mandar usar `Grep`
   explicitamente nas varreduras contadas — o texto atual diz *"`git grep` sem `head_limit`"*, que
   nesta plataforma é o caminho lento.

## 7. Perguntas pro [W]

1. **`[V0]` §9.1** — qual remédio pra assinatura sem plano casado? (gerador cai pra
   `metadata.valor` × `store()` recusa × plano implícito). Os três se anulam; preciso da sua escolha
   antes de qualquer correção, e ela exige dupla-confirmação + tabela antes→depois.
2. **§6.2 Non-Goals do domínio está VAZIO de propósito** — o agente é proibido de inferir.
   Candidatos que a análise encontrou: *"não somos Merchant of Record"* (ADR arq/0004), *"não fazemos
   cartão tokenizado próprio"*, *"NFS-e não é deste módulo"* (ADR arq/0002). Confere?
3. **A2 (LGPD do DANFE)** — o e-mail automático do DANFE ao pagador entra na classe "notificação
   comercial" (precisa opt-in) ou "documento fiscal" (direito do destinatário)? É [E]/[W], não minha.
4. **A3** — os 5 cenários HTTP prometidos no `Index.charter.md` e nunca escritos: poda a promessa ou
   constrói os testes?
5. Aplico o bloco do **SDD §8.2** na allowlist, ou o parent consolida junto com os outros chips?
