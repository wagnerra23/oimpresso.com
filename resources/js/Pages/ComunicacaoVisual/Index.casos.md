---
id: resources-js-pages-comunicacao-visual-index-casos
casos: Hub + Calculadora de m² · /comunicacao-visual
irmaos: Index.charter.md (lei) · Index.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a fórmula do m² e o isolamento por business são o que não pode mudar — a tela vai ganhar OS, materiais e apontamento por cima disso, e nenhum deles pode afrouxar o cálculo nem o escopo.
owner: wagner
last_run: "2026-10-27"
last_run_ci: "2026-10-27 — GH run 40495854452 (verticais-pest, MySQL, PR #9994): OsPosCalculoContratoTest 5 passed (UC-CVPOS-01..05); os outros UCs da tela não foram rodados nesta data; a mudança do Index.tsx pelo #9860 (link de aceite, 2026-10-27) não foi revalidada aqui. ---- 2026-10-26 — a tela ganhou o cartão Roteiro da produção (UC-CVOS-11, #9900), depois composto com Stack; vitest local tests/js/cv-roteiro-padrao.test.tsx 2 passed; nenhum outro UC tocado. ---- 2026-10-21 — CT 100 MySQL, cópia isolada do branch a8ada7ef4: OrcamentoSalvarReabrirContratoTest 6 passed (39 assertions) + ViraOs/CustoPrevisto/CustoMaquina 16 passed; sem run id de CI. ---- 2026-10-13 (PR contratos de tela, modulos-faltantes thread 01) — REVALIDACAO ANALITICA, suite NAO executada. A mudanca na tela sao so atributos data-contract (e um div sem classe em volta do PageHeader); nenhum UC afirma ancora, nenhum calculo tocado. ---- REGISTRO ANTERIOR: last_run 2026-09-25. 2026-09-25 — GH run 36124963011 (verticais-pest, workflow_dispatch, MySQL, main 2e3f8adb4): MigrationsTest, Tier0GuardTest e MultiTenantTest PASS (lane vermelha só por Cms). CT 100 MySQL (worktree isolado em origin/main 1743f005a, sem run id): 10 arquivos, 58 passed / 10 failed (200 assertions) — ver §Revalidação 2026-09-25. Nenhum UC no manifesto G-7, logo nenhum ✅."
---

# Casos de Uso & Aceite — Hub + Calculadora de m² (`/comunicacao-visual`)

> **Âncora:** os UC derivam dos CU do
> [SDD §6](../../../../memory/requisitos/ComunicacaoVisual/SDD-tela-orcamento-m2-v1.0.md) —
> `CU-CV-02`, `CU-CV-03`, `CU-CV-04`, `CU-CV-06`, `CU-CV-08`, `CU-CV-09` e `CU-CV-10` —
> **nunca do `Index.tsx`**: teste derivado do código é tautológico e trava o desvio em vez de
> pegá-lo ([proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05).
>
> **Por que este arquivo nasce agora:** completa o trio da tela (o charter existe desde
> 2026-05-16, Wave 25; `casos.md` faltava — `node scripts/governance/requisitos-status.mjs ComunicacaoVisual`
> acusava *"Tela `Index` sem `casos.md`"* como a única lacuna nomeada do módulo). É o chip da
> **Onda 4** do [passo 5](../../../../memory/requisitos/_Governanca/programa-ondas/passo-5-sdd-por-modulo.md).
>
> ⚠️ **Módulo EM CONSTRUÇÃO, sem cliente piloto.** 14 das 18 US do
> [SPEC](../../../../memory/requisitos/ComunicacaoVisual/SPEC.md) são **plano** e estão listadas
> no [SDD §6.9](../../../../memory/requisitos/ComunicacaoVisual/SDD-tela-orcamento-m2-v1.0.md)
> **sem CU e sem UC** — de propósito: caso sem código vira UC órfão, o `casos-gate` G-2 pune, e
> isso **bloqueia o merge de quem for implementar** ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-16).

## ⚖️ Força do veredito — leia ANTES de confiar em qualquer status daqui

Três portas distintas, medidas separadamente (2026-07-28):

| Pergunta | Porta medida | Resposta |
|---|---|---|
| roda em algum lugar? | [`phpunit.xml`](../../../../phpunit.xml) + [`shards-plan.mjs`](../../../../scripts/tests/shards-plan.mjs) (`--roots tests,Modules`) | ✅ **sim** — full-suite noturna, MySQL real |
| roda no PR? | [`modules-pest.yml`](../../../../.github/workflows/modules-pest.yml) — matrix de 6 módulos, `DB_CONNECTION=sqlite :memory:` **sem migrate** | ⚠️ **parcialmente** — **6 dos 20** arquivos abortam no `beforeEach` com `markTestSkipped('SQLite-incompatível')` |
| **bloqueia merge?** | [`required-checks-baseline.json`](../../../../governance/required-checks-baseline.json) — Pest **required** = Financeiro, NfeBrasil, Unit | ❌ **não** — `Pest ComunicacaoVisual` é **advisory**: reprova visível, não bloqueia merge |

**Consequência honesta:** os UC marcados **⏭ PR-skip** abaixo (incluindo os `[T0]`) **não são
exercitados no PR** — o verde da lane prova que eles foram *pulados*, não que passam. É a família
"verde por não-execução" ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-24). Quem
morde de fato neste PR é o `Casos-coverage · ratchet` (G-1 trio + G-2 UC↔teste), esse sim required.
A decisão de mudar isso é de [W] — mexer no `modules-pest.yml` afeta **6 módulos**, e este chip
não toca lá ([SDD §10](../../../../memory/requisitos/ComunicacaoVisual/SDD-tela-orcamento-m2-v1.0.md) item 2).

**Legenda:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC, veredito pendente da lane ·
⬜ não verificado · ❌ quebrou · ⏭ PR-skip = o caso existe mas a lane do PR não o executa.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora (SDD §6) | Teste | Status |
|----|-------------|------|-----------------|-------|--------|
| UC-CV-01 | O total oficial é o do servidor, não o da tela | must `[V0]` | `CU-CV-02` itens 1-4 | `OrcamentoCalculatorTest` | 🧪 |
| UC-CV-02 | Medida inválida vira 422 em PT-BR, nunca total silencioso | must `[V0]` | `CU-CV-02` item 5 | `OrcamentoCalculatorTest` | 🧪 |
| UC-CV-03 | O preço/m² tem origem única e explícita | must `[V0]` | `CU-CV-03` itens 1-3, 5-6 | `OrcamentoCalculatorTest` | 🧪 |
| UC-CV-04 | Material de outro business não precifica nada | must `[T0]` | `CU-CV-03` item 4 · `CU-CV-04` | `OrcamentoCalculatorTest` · `MaterialSeederTest` | 🧪 ⏭ PR-skip |
| UC-CV-05 | Orçamento, OS e apontamento de outro business não aparecem | must `[T0]` | `CU-CV-04` itens 1-5 | `MultiTenantTest` · `Tier0GuardTest` · `OrcamentoControllerTest` | 🧪 ⏭ PR-skip |
| UC-CV-06 | Um operador nunca tem dois spools abertos; drift vem do servidor | must | `CU-CV-06` itens 2-6 | `ApontamentoTrackerTest` · `ApontamentoControllerTest` | 🧪 ⏭ PR-skip |
| UC-CV-07 | A calculadora recebe o catálogo do business | must | `CU-CV-09` itens 1-3 | `ContratoTelaOrcamentoTest` | 🧪 verde no CT 100 (2026-09-23) ⏭ PR-skip |
| UC-CV-08 | O rastro de auditoria não carrega PII | must `[reg]` | `CU-CV-08` itens 1-5 | `AuditTrailIntegrityTest` · `LgpdComplianceTest` | 🧪 |
| UC-CV-09 | O substrato nasce com os campos fiscais do CNAE 1813 | should | `CU-CV-10` item 1 | `ContratoTelaOrcamentoTest` | 🧪 |
| UC-CV-10 | O hub abre pra quem tem permissão e renderiza a calculadora | must | `CU-CV-01` itens 1-2 | `ContratoTelaOrcamentoTest` | 🧪 ⏭ PR-skip |
| UC-CV-11 | Salvar o orçamento grava os valores do servidor, atomicamente | should | `CU-CV-05` itens 1-4 | `OrcamentoControllerTest` | 🧪 ⏭ PR-skip |
| UC-CV-12 | O catálogo de partida nasce completo, idempotente e isolado | should | `CU-CV-07` itens 1-3 | `MaterialSeederTest` | 🧪 ⏭ PR-skip |
| UC-CVPRC-01 | Peça menor que o mínimo do material é cobrada pelo mínimo | must `[V0]` | `CU-CV-02` · DoD US-COMVIS-001 (mínimo) | `OrcamentoMinimoAcabamentoContratoTest` | 🧪 |
| UC-CVPRC-02 | Acabamento do catálogo entra no total pela base do tipo dele | must `[V0]` `[T0]` | `CU-CV-02` · DoD US-COMVIS-001 (acabamentos) · SPEC §12.1 | `OrcamentoMinimoAcabamentoContratoTest` | 🧪 |
| UC-CVPRC-03 | Perda % do material soma na área cobrada | must `[V0]` | Decisão [W] 2026-10-26 · US-COMVIS-001 | `OrcamentoPerdaEmendaContratoTest` | 🧪 |
| UC-CVPRC-04 | Peça maior que a bobina leva emenda cobrada por metro de solda | must `[V0]` `[T0]` | Decisão [W] 2026-10-26 · US-COMVIS-001 | `OrcamentoPerdaEmendaContratoTest` | 🧪 |
| UC-CVINST-01 | Agenda equipe e veículo na OS e a agenda da janela lista a instalação | must | US-COMVIS-007 · UC-FLX-21 | `InstalacaoAgendaContratoTest` | 🧪 |
| UC-CVINST-02 | Alguém da equipe ocupado na janela bloqueia, dizendo com quem e quando | must | US-COMVIS-007 · UC-FLX-21 | `InstalacaoAgendaContratoTest` | 🧪 |
| UC-CVINST-03 | O mesmo veículo na janela bloqueia, mesmo escrito de outro jeito | must | US-COMVIS-007 · UC-FLX-21 | `InstalacaoAgendaContratoTest` | 🧪 |
| UC-CVINST-04 | Horário encostado e instalação cancelada não bloqueiam | must | US-COMVIS-007 · UC-FLX-21 | `InstalacaoAgendaContratoTest` | 🧪 |
| UC-CVINST-05 | Equipe de outra empresa é recusada; a agenda da outra empresa nunca conflita | must `[T0]` | US-COMVIS-007 · UC-FLX-21 | `InstalacaoAgendaContratoTest` | 🧪 |
| UC-CVINST-06 | OS de outra empresa devolve 404 | must `[T0]` | US-COMVIS-007 · UC-FLX-21 | `InstalacaoAgendaContratoTest` | 🧪 |
| UC-CVINST-07 | Sem permissão não agenda nem lista | must | US-COMVIS-007 · UC-FLX-21 | `InstalacaoAgendaContratoTest` | 🧪 |
| UC-CVINST-08 | Agendar avisa o cliente por WhatsApp com OS, dia e hora | must `[LGPD]` | US-COMVIS-007 · UC-FLX-21 | `InstalacaoAvisoClienteContratoTest` | 🧪 |
| UC-CVINST-09 | Cliente que recusou WhatsApp, sem celular, sem cliente ou sem canal ativo não é avisado | must `[LGPD]` | US-COMVIS-007 · UC-FLX-21 | `InstalacaoAvisoClienteContratoTest` | 🧪 |
| UC-CVINST-10 | Contato e número de outra empresa nunca entram no aviso | must `[T0]` | US-COMVIS-007 · UC-FLX-21 | `InstalacaoAvisoClienteContratoTest` | 🧪 |
| UC-CVOS-01 | Aprovar cria a OS com os itens e as medidas do orçamento | must | US-COMVIS-020 · UC-FLX-01 | `OrcamentoViraOsContratoTest` | 🧪 |
| UC-CVOS-02 | A OS vale o total do orçamento e nenhum valor do orçamento muda | must `[V0]` | US-COMVIS-020 · UC-FLX-01 | `OrcamentoViraOsContratoTest` | 🧪 |
| UC-CVOS-03 | Aprovar de novo não duplica OS, itens nem arquivos | must | US-COMVIS-020 · UC-FLX-01 | `OrcamentoViraOsContratoTest` | 🧪 |
| UC-CVOS-04 | Os arquivos do orçamento passam para a OS, só os da empresa | must `[T0]` | US-COMVIS-020 · UC-FLX-01 | `OrcamentoViraOsContratoTest` | 🧪 |
| UC-CVOS-05 | Outra empresa não aprova nem gera OS | must `[T0]` | US-COMVIS-020 · UC-FLX-01 | `OrcamentoViraOsContratoTest` | 🧪 |
| UC-CVOS-06 | Orçamento recusado não vira OS | must | US-COMVIS-020 · UC-FLX-01 | `OrcamentoViraOsContratoTest` | 🧪 |
| UC-CVOS-07 | Só quem pode criar orçamento aprova | must | US-COMVIS-020 · UC-FLX-01 | `OrcamentoViraOsContratoTest` | 🧪 |
| UC-CVOS-08 | O orçamento aprovado reabre com o número da OS | must | US-COMVIS-020 · UC-FLX-01 | `OrcamentoViraOsContratoTest` | 🧪 verde no CT 100 (2026-10-21) |
| UC-CVOS-09 | A OS aprovada nasce com o roteiro padrão e aparece no quadro do PCP | must | US-COMVIS-003 · decisão [W] 2026-10-26 | `OsRoteiroPadraoContratoTest` | 🧪 |
| UC-CVOS-10 | O padrão só aceita roteiro ativo da própria empresa | must `[T0]` | US-COMVIS-003 · decisão [W] 2026-10-26 | `OsRoteiroPadraoContratoTest` | 🧪 |
| UC-CVOS-11 | O hub mostra e troca o roteiro padrão para quem cuida do catálogo | should | US-COMVIS-003 · decisão [W] 2026-10-26 | `tests/js/cv-roteiro-padrao.test.tsx` | 🧪 |
| UC-CVPOS-01 | Custo orçado, custo real e as duas margens da OS saem da conta feita à mão | must `[V0]` | US-COMVIS-005 | `OsPosCalculoContratoTest` | 🧪 run 40495854452 |
| UC-CVPOS-02 | Trecho aberto não entra; etapa sem m² conta a hora e não o material | must `[V0]` | US-COMVIS-005 | `OsPosCalculoContratoTest` | 🧪 run 40495854452 |
| UC-CVPOS-03 | Peça sem custo deixa o orçado vazio; nenhuma com custo deixa o real vazio | must | US-COMVIS-005 | `OsPosCalculoContratoTest` | 🧪 run 40495854452 |
| UC-CVPOS-04 | OS, hora e m² de outra empresa nunca entram | must `[T0]` | US-COMVIS-005 | `OsPosCalculoContratoTest` | 🧪 run 40495854452 |
| UC-CVPOS-05 | Flag desligada é 404; sem quem vê custo é 403 | must | US-COMVIS-005 | `OsPosCalculoContratoTest` | 🧪 run 40495854452 |
| UC-CVMRG-01 | Cada peça e o orçamento mostram custo e margem | must `[V0]` | US-COMVIS-022 · UC-FLX-03 | `OrcamentoMargemContratoTest` | 🧪 verde no CT 100 (2026-10-21) |
| UC-CVMRG-02 | Preço, subtotal e total são os mesmos com e sem margem | must `[V0]` | US-COMVIS-022 · UC-FLX-03 | `OrcamentoMargemContratoTest` | 🧪 verde no CT 100 (2026-10-21) |
| UC-CVMRG-03 | Peça sem custo fica sem margem; o orçamento diz quantas têm | must | US-COMVIS-022 · UC-FLX-03 | `OrcamentoMargemContratoTest` | 🧪 verde no CT 100 (2026-10-21) |
| UC-CVMRG-04 | Custo de material de outra empresa nunca entra | must `[T0]` | US-COMVIS-022 · UC-FLX-03 | `OrcamentoMargemContratoTest` | 🧪 verde no CT 100 (2026-10-21) |
| UC-CVMRG-05 | Só quem gerencia materiais vê custo e margem | must | US-COMVIS-022 · UC-FLX-03 | `OrcamentoMargemContratoTest` | 🧪 verde no CT 100 (2026-10-21) |
| UC-CVMRG-06 | O custo mostrado é o que o orçamento grava | must `[V0]` | US-COMVIS-022 · UC-FLX-03 | `OrcamentoMargemContratoTest` | 🧪 verde no CT 100 (2026-10-21) |
| UC-CVARQ-01 | Anexar grava o arquivo no orçamento e aprovar o leva para a OS | must | US-COMVIS-020 · UC-FLX-01 | `OrcamentoArquivosContratoTest` | 🧪 verde no CT 100 (2026-10-21) |
| UC-CVARQ-02 | Formato que a gráfica não usa é recusado | must | US-COMVIS-020 · UC-FLX-01 | `OrcamentoArquivosContratoTest` | 🧪 verde no CT 100 (2026-10-21) |
| UC-CVARQ-03 | Depois de virar OS o orçamento não recebe nem perde arquivo | must | US-COMVIS-020 · UC-FLX-01 | `OrcamentoArquivosContratoTest` | 🧪 verde no CT 100 (2026-10-21) |
| UC-CVARQ-04 | Outra empresa não anexa nem remove | must `[T0]` | US-COMVIS-020 · UC-FLX-01 | `OrcamentoArquivosContratoTest` | 🧪 verde no CT 100 (2026-10-21) |
| UC-CVARQ-05 | Sem permissão de criar não anexa nem remove | must | US-COMVIS-020 · UC-FLX-01 | `OrcamentoArquivosContratoTest` | 🧪 verde no CT 100 (2026-10-21) |
| UC-CVARQ-06 | Remover tira o arquivo e ele não vai para a OS | must | US-COMVIS-020 · UC-FLX-01 | `OrcamentoArquivosContratoTest` | 🧪 verde no CT 100 (2026-10-21) |
| UC-CVSAL-01 | Salvar grava os valores do servidor, não os da tela | must `[V0]` | US-COMVIS-021 | `OrcamentoSalvarReabrirContratoTest` | 🧪 verde no CT 100 (2026-10-21) |
| UC-CVSAL-02 | Reabrir devolve as peças e os ajustes, e aparece na lista | must | US-COMVIS-021 | `OrcamentoSalvarReabrirContratoTest` | 🧪 verde no CT 100 (2026-10-21) |
| UC-CVSAL-03 | Regravar o reaberto mantém o número e troca as peças | must `[V0]` | US-COMVIS-021 | `OrcamentoSalvarReabrirContratoTest` | 🧪 verde no CT 100 (2026-10-21) |
| UC-CVSAL-04 | Orçamento que virou OS ou foi recusado não regrava | must | US-COMVIS-021 | `OrcamentoSalvarReabrirContratoTest` | 🧪 verde no CT 100 (2026-10-21) |
| UC-CVSAL-05 | Outra empresa não reabre, não lista nem regrava | must `[T0]` | US-COMVIS-021 | `OrcamentoSalvarReabrirContratoTest` | 🧪 verde no CT 100 (2026-10-21) |
| UC-CVSAL-06 | Sem permissão de criar não salva nem regrava | must | US-COMVIS-021 | `OrcamentoSalvarReabrirContratoTest` | 🧪 verde no CT 100 (2026-10-21) |

> 🧪 **Nenhum status aqui é afirmação de verde.** O PR que criou este arquivo não executou teste algum (CT 100/CI —
> [ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)). "Vermelho
> esperado" é **predição declarada**, derivada de leitura de código com varredura contada; o
> veredito vem da lane ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-15).

## Revalidação 2026-09-25 (G-6 — a tela mudou depois do `last_run` 2026-09-23)

**O que mudou no `Index.tsx`:** #7854 (header canon + R2/R3/R6) e #7868 (R4 — a **prévia**
client-side passou a arredondar área a 3 e subtotal a 2 **por item**, `HALF_UP`, e o total ficou
**sem clamp** em zero, com aviso quando negativo). As duas são só apresentação; nenhuma toca
servidor, rota ou permissão.

**Contrato conferido contra o #7868:** UC-CV-01 continua descrevendo a tela — o total oficial
segue sendo o do servidor e a fórmula escrita aqui (`round` por item, `HALF_UP`, total sem piso)
é exatamente a que a prévia passou a espelhar; antes do #7868 a prévia é que divergia do caso.
UC-CV-02 (422) e UC-CV-03 (origem do preço) vivem no servidor e não foram tocados.

**Execução (MySQL real), resultado por UC — ✅ só com prova no manifesto G-7, que não tem UC-CV:**

| UC | Teste medido | Resultado |
|----|-------------|-----------|
| UC-CV-01 · 02 · 03 · 04 | `OrcamentoCalculatorTest` (CT 100) | 9/9 passed — cenários 1-7b |
| UC-CV-02 · 05 · 11 | `OrcamentoControllerTest` (CT 100) | 3/4 — **1 falha**: `POST /calcular` asserta `toBe(270.0)` e recebe o inteiro `270` (tipo JSON, não valor); código servidor sem mudança desde 2026-08-12 — não medido se já falhava antes; fora deste escopo |
| UC-CV-04 · 12 | `MaterialSeederTest` (CT 100) | 4/4 passed |
| UC-CV-05 | `MultiTenantTest` · `Tier0GuardTest` (CT 100 **e** run 36124963011) | todos passed |
| UC-CV-06 | `ApontamentoTrackerTest` · `ApontamentoControllerTest` (CT 100) | 5/8 · 0/4 — **não é veredito**: o `MultiTenantTest` deixa um apontamento aberto do operador 1 no banco persistente e o número da OS de teste colide em `varchar(20)` (`uq_comvis_os_business_numero`); poluição de ordem, não o contrato |
| UC-CV-07 · 10 | `ContratoTelaOrcamentoTest` (CT 100) | **não medido**: 500 por `ViteManifestNotFoundException` no worktree de teste (sem build do front); vale o verde datado de 2026-09-23 |
| UC-CV-08 | `AuditTrailIntegrityTest` · `LgpdComplianceTest` (CT 100) | 8/8 · 8/8 passed |
| UC-CV-09 | `ContratoTelaOrcamentoTest` (CT 100) | passed |

Por isso os status seguem 🧪: nenhum UC tem entrada no manifesto `scripts/casos-test-results.json`,
e marcar ✅ sem ela é `status:unverified` (G-7).

---

## UC-CV-01 · O total oficial é o do servidor, não o da tela · `must` `[V0]`

- **Persona:** Larissa-equivalente, no balcão — ela vê um número na tela e promete esse preço ao cliente.
- **Aceite:** Dado um orçamento montado na calculadora · Quando ela clica "Conferir no servidor" ·
  Então o total exibido como oficial é o **recalculado no backend**
  (`area = round(l × a × qtd, 3)` · `subtotal = round(area × preço, 2)` ·
  `total = round(subtotal − desconto + extras + instalação + entrega, 2)`, tudo `HALF_UP`),
  e qualquer `area_m2`/`subtotal`/`total` vindo do cliente é **descartado**.
- **Dupla-confirmação `[V0]`:** o cenário canônico (banner 3 m × 1,5 m, 1 peça) fecha em `area = 4,5`
  e o total confere por **dois caminhos independentes** — recomputo à mão da fórmula **e** soma das
  linhas devolvida pelo Service.
- **Teste:** [`OrcamentoCalculatorTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/OrcamentoCalculatorTest.php)
  — cenários 1, 2, 4 e 5 (banner, vinil com qtd, desconto absoluto, múltiplos itens).
- **Contrato:** `CU-CV-02` do SDD · DoD da `US-COMVIS-001` no [SPEC](../../../../memory/requisitos/ComunicacaoVisual/SPEC.md) ·
  anti-padrão nº 11 do SPEC §10 (*"cálculo m² em frontend sem servidor validar"*).
- **Regressão que defende:** a tela espelha a fórmula em JS pra dar feedback instantâneo. Espelho
  desatualiza. Se alguém "otimizar" mandando o total do cliente pro `store`, o preço vira o que o
  navegador disser — e é exatamente a classe do incidente `num_uf` ×100 de 2026-06-05
  ([REGRA MESTRE valor/estoque](../../../../memory/proibicoes.md)).
- **Status: 🧪** — o teste existe e cita o UC; veredito pendente da lane `Pest ComunicacaoVisual` (advisory).

---

## UC-CV-02 · Medida inválida vira 422 em PT-BR, nunca total silencioso · `must` `[V0]`

- **Persona:** Larissa-equivalente — digitar `0` na altura sem querer não pode virar orçamento de graça.
- **Aceite:** Dado um payload com largura ≤ 0, altura ≤ 0, quantidade < 1 ou zero itens · Quando o
  servidor calcula · Então responde **422** com mensagem em português dizendo qual campo e qual peça —
  **nunca** 200 com total zerado ou negativo.
- **Teste:** [`OrcamentoCalculatorTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/OrcamentoCalculatorTest.php)
  — cenários 6, 6b e 7 (throw em largura ≤ 0, altura ≤ 0, e sem preço resolvível) ·
  [`OrcamentoControllerTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/OrcamentoControllerTest.php)
  (a tradução do throw em 422).
- **Contrato:** `CU-CV-02` item 5 do SDD · `validarPayload` (`gt:0`, `min:1`, `itens min:1`).
- **Regressão que defende:** a validação vive em **dois lugares** — a Form validation do Controller
  **e** o Service (que revalida por segurança). Afrouxar um dos dois sem o outro abre o caminho pra
  um total calculado sobre dimensão inválida.
- **Status: 🧪** — veredito pendente da lane.

---

## UC-CV-03 · O preço/m² tem origem única e explícita · `must` `[V0]`

- **Persona:** Larissa-equivalente — ela precisa saber se o preço que apareceu veio da tabela dela
  ou do que ela digitou; "apareceu sozinho" é o pior dos mundos.
- **Aceite:** Dado um item de orçamento · Quando o servidor resolve o preço · Então a ordem é dura e
  única: **(1)** preço digitado pelo operador vence · **(2)** senão, `Material.preco_venda_m2` do
  próprio business · **(3)** senão, **erro**. Preço digitado `≤ 0` ⇒ erro; material com
  `preco_venda_m2 ≤ 0` ⇒ erro nomeando o material. **Nenhum caminho produz preço implícito.**
- **Teste:** [`OrcamentoCalculatorTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/OrcamentoCalculatorTest.php)
  — cenário 3 (resolve do catálogo sem override) e cenário 7 (throw sem `material_id` e sem preço).
- **Contrato:** `CU-CV-03` do SDD · `OrcamentoCalculator::resolverPreco`.
- **Regressão que defende:** um `?? 0` em qualquer ponto dessa cadeia transforma "não sei o preço"
  em "de graça" — o mesmo defeito de `0` ambíguo já catalogado no ecossistema de preço do Produto
  ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-15).
- **Status: 🧪** — veredito pendente da lane.

---

## UC-CV-04 · Material de outro business não precifica nada · `must` `[T0]`

- **Persona:** Wagner / WR2 SC (biz=1) — duas gráficas concorrentes no mesmo servidor não podem
  precificar com a tabela uma da outra. Tabela de preço **é** segredo comercial.
- **Aceite:** Dado um material do business 99 · Quando um item do business 1 informa aquele
  `material_id` sem preço digitado · Então o cálculo **falha** com *"não encontrado ou não pertence
  a este business"* — a mensagem **não confirma** que o material existe. E, como controle positivo,
  o material **do próprio** business resolve o preço normalmente.
- **Teste:** [`OrcamentoCalculatorTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/OrcamentoCalculatorTest.php)
  — cenário 7b (*"throw quando material_id não existe no business"*) ·
  [`MaterialSeederTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/MaterialSeederTest.php)
  (*"`Material::find()` via global scope não retorna rows de outro business"*).
- **Contrato:** `CU-CV-03` item 4 + `CU-CV-04` do SDD ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** o isolamento aqui é **implícito** — vem do global scope do `Material`,
  não de um `where` no Service. Quem ler o `resolverPreco` isolado não vê filtro de business nenhum;
  um `withoutGlobalScopes()` "pra debugar" vaza tabela de preço entre tenants sem erro visível.
- **Status: 🧪 ⏭ PR-skip** — o teste existe e cita o UC, mas o `MaterialSeederTest` pula em SQLite;
  o veredito real vem da full-suite noturna (MySQL).

---

## UC-CV-05 · Orçamento, OS e apontamento de outro business não aparecem · `must` `[T0]`

- **Persona:** Wagner / WR2 SC (biz=1) — vale para **as 10 entidades** do módulo, não só as 3 do fluxo.
- **Aceite:** Dado dados nos businesses 1 e 99 · Quando a sessão é do business 1 · Então:
  (a) `Material`, `Orcamento`, `OrcamentoItem` e `Os` do 99 **não** aparecem — e os do 1 aparecem
  (controle positivo); (b) as 5 entidades do PCP (`Substrato`, `Acabamento`, `InstalacaoCatalogo`,
  `OrdemProducao`, `Instalacao`) idem; (c) `GET /…/api/orcamentos/{id}` de outro business ⇒ **404**,
  não 403 (403 confirmaria a existência); (d) `GET /…/api/apontamentos/em-andamento` de outra
  sessão ⇒ vazio; (e) criar sem informar `business_id` usa o da **sessão**, não vaza pro errado.
- **Teste:** [`MultiTenantTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/MultiTenantTest.php)
  (4 entidades × par negativo/positivo) ·
  [`Tier0GuardTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/Tier0GuardTest.php)
  (as 5 do PCP + o `creating` que auto-popula) ·
  [`OrcamentoControllerTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/OrcamentoControllerTest.php)
  (o 404 cross-tenant) ·
  [`ApontamentoControllerTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/ApontamentoControllerTest.php)
  (o `em-andamento` vazio).
- **Contrato:** `CU-CV-04` do SDD ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) (Tier 0 IRREVOGÁVEL) ·
  [ADR 0101](../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md) (biz=1, nunca biz=4).
- **Regressão que defende:** 10 Entities, 10 global scopes. Entidade nova nascendo sem o scope é
  o vetor mais banal de vazamento cross-tenant, e o módulo tem **5 entidades sem consumidor**
  ([SDD §5.4.5](../../../../memory/requisitos/ComunicacaoVisual/SDD-tela-orcamento-m2-v1.0.md))
  — justamente as que ninguém exercita clicando.
- **Status: 🧪 ⏭ PR-skip** — ⚠️ **este é o caso mais importante do módulo e é o que a lane do PR
  menos executa**: `MultiTenantTest`, `Tier0GuardTest` e `OrcamentoControllerTest` pulam **inteiros**
  em SQLite. Registrado como dívida **D-7** no SDD §9.

---

## UC-CV-06 · Um operador nunca tem dois spools abertos; drift vem do servidor · `must`

- **Persona:** operador de plotter, celular na mão ao lado da máquina.
- **Aceite:** Dado um operador com um apontamento em andamento · Quando tenta iniciar outro ·
  Então recebe erro **nomeando o apontamento aberto**. E, ao finalizar: `duracao_segundos` e
  `drift_percent = round(((produzido − orçado) / orçado) × 100, 2)` são calculados **no servidor**;
  com `m2_orcado ≤ 0` o drift é **null**, nunca divisão por zero. Finalizar duas vezes ⇒ erro.
  Cancelar zera `m2_produzido` e prefixa `[CANCELADO]` — **não apaga** o registro.
- **Teste:** [`ApontamentoTrackerTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/ApontamentoTrackerTest.php)
  (8 casos: duração, drift, drift null, cancelar, spool duplicado, dupla finalização) ·
  [`ApontamentoControllerTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/ApontamentoControllerTest.php).
- **Contrato:** `CU-CV-06` do SDD · DoD da `US-COMVIS-004` no SPEC ·
  `retention.php` (apontamento é **append-only**, sem `SoftDeletes`).
- **Regressão que defende:** o drift é o insumo do pós-cálculo (`US-COMVIS-005`) — é ele que vai
  dizer se a OS deu margem. Calcular no cliente, ou deixar um segundo spool aberto, corrompe o
  custo real de todas as OS daquele operador. E o append-only do apontamento é exigência legal de
  registro produtivo: ganhar `SoftDeletes` "por consistência" é regressão de compliance.
- **Status: 🧪 ⏭ PR-skip** (parte HTTP) — o `ApontamentoTrackerTest` roda parcialmente; o Controller pula.

---

## UC-CV-07 · A calculadora recebe o catálogo do business · `must`

- **Persona:** Larissa-equivalente — o valor prometido pela tela é *"escolhe o material, o preço
  preenche sozinho"*. Hoje ela digita o preço/m² de cabeça, em **toda** peça.
- **Aceite:** Dado um business com materiais cadastrados · Quando a operadora abre
  `/comunicacao-visual` · Então o **nome** desses materiais chega à página, ela escolhe um e o
  preço/m² é preenchido a partir do catálogo. Controle Tier 0 no mesmo caso: material de **outro**
  business **nunca** aparece no payload.
- **Teste:** [`ContratoTelaOrcamentoTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/ContratoTelaOrcamentoTest.php)
  — *"a página do hub entrega o catálogo de materiais do business à calculadora"* + o controle-negativo
  cross-tenant. Ambos com **pré-condição anti-vácuo** (`assertOk()` antes de olhar as props: sem 200
  o teste teria medido o gate de permissão, não o contrato).
- **Contrato:** `CU-CV-09` do SDD §6 · DoD da `US-COMVIS-002` (*"alimentar US-COMVIS-001 sem
  hard-code"*) · [`BRIEFING.md`](../../../../memory/requisitos/ComunicacaoVisual/BRIEFING.md)
  (*"seletor de material puxa o catálogo do business"*) · o próprio docblock do `Index.tsx`.
- **Regressão que defende:** três documentos **e** o código do componente prometem o seletor de
  material; a única rota que renderiza a página passa **só `bizName`**
  ([SDD §5.4.1](../../../../memory/requisitos/ComunicacaoVisual/SDD-tela-orcamento-m2-v1.0.md),
  varredura contada). O `MaterialSeeder` semeia 5 materiais que a tela nunca vê. Sem este caso, a
  promessa segue escrita em 3 lugares e falsa em produção.
- ⚠️ **Duas correções são válidas** — passar a prop na closure da rota **ou** a tela buscar o
  catálogo por fetch. Por isso o assert é **comportamental** (o nome do material chega ao payload),
  não acoplado ao nome da prop: assert por chave literal reprovaria arbitrariamente uma das duas.
- **Status: 🧪 verde no CT 100 ⏭ PR-skip** — medido em 2026-09-23 no PR que passou o catálogo na
  rota: `3 passed (10 assertions)` num worktree isolado no `oimpresso-staging` (MySQL real), e o
  mutante (rota de volta à versão do `main`) derruba **só** o caso positivo, com a mensagem do
  contrato. Não é ✅: a lane do PR roda SQLite e pula; o veredito que conta é o da full-suite noturna.
  Até 2026-09-23 este caso era **vermelho esperado** (a rota passava só `bizName`).

---

## UC-CV-08 · O rastro de auditoria não carrega PII · `must` `[reg]`

- **Persona:** Eliana [E] (jurídico/LGPD) — o log de atividade é o que sobra depois que o dado
  operacional é anonimizado; se o PII estiver **nele**, o `right_to_be_forgotten` não cumpre nada.
- **Aceite:** Dado que orçamento, OS e apontamento mudam de estado · Quando a mudança é registrada ·
  Então a whitelist `logOnly` **exclui** `contato_id` (referência a PII) e `observacoes` (texto livre)
  no `Orcamento`, e `observacoes`/`operador_id` no `Apontamento`; **inclui** o que é de negócio
  (status, totais, datas); `logOnlyDirty` + `dontSubmitEmptyLogs` estão ativos; e `observacoes` passa
  por `PiiRedactor` **antes** de qualquer span OTel do `OrcamentoCalculator`.
- **Teste:** [`AuditTrailIntegrityTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/AuditTrailIntegrityTest.php)
  (8 casos, reflection-only) ·
  [`LgpdComplianceTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/LgpdComplianceTest.php)
  (retenção, append-only, janela de telemetria).
- **Contrato:** `CU-CV-08` do SDD ·
  [`PII-LGPD.md`](../../../../memory/requisitos/ComunicacaoVisual/PII-LGPD.md) ·
  `Config/retention.php` · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) §PII.
- **Regressão que defende:** `logOnly` é uma lista de strings. Adicionar `observacoes` "pra ficar
  mais fácil de depurar" põe texto livre de vendedor — onde nome e telefone de cliente aparecem —
  numa tabela que nenhuma rotina de anonimização varre.
- **Status: 🧪** — roda no PR (reflection, sem DB); veredito pendente da lane.

---

## UC-CV-09 · O substrato nasce com os campos fiscais do CNAE 1813 · `should`

- **Persona:** dono de gráfica em onboarding — ele não tem contador de plantão pra classificar 80 itens.
- **Aceite:** Dado o schema de substratos · Quando um substrato é cadastrado · Então ele carrega
  `ncm`, `cfop_padrao` e `csosn_padrao`, de modo que a emissão de NFC-e/NFe de impresso publicitário
  não dependa de configuração item a item.
- **Teste:** [`ContratoTelaOrcamentoTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/ContratoTelaOrcamentoTest.php)
  — *"o schema de substratos carrega ncm, cfop_padrao e csosn_padrao"*.
- **Classe do teste — declarada:** **guard estrutural** (lê o fonte da migration, não exercita
  runtime). Aqui o nome da coluna **é** o contrato — `ncm`/`cfop_padrao`/`csosn_padrao` são campos
  definidos por SEFAZ/CONFAZ, não chaves arbitrárias de payload — então isto não é o
  "assert acoplado à chave literal" que a [ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md) bane.
- **Contrato:** `CU-CV-10` item 1 do SDD · DoD da `US-COMVIS-006` no SPEC.
- **Regressão que defende:** as 5 tabelas `cv_*` **não têm consumidor** — nenhuma tela, nenhum
  controller. Schema órfão é o que mais some num refactor de migration, porque nada quebra visivelmente.
- **Status: 🧪** — roda no PR (source-scoped); veredito pendente da lane.

---

## UC-CV-10 · O hub abre pra quem tem permissão e renderiza a calculadora · `must`

- **Persona:** Larissa-equivalente — se a rota devolve 403 ou renderiza outro componente, todos os
  outros casos desta tela viram medição de nada.
- **Aceite:** Dado um usuário do business com `comvis.orcamento.view` · Quando pede
  `GET /comunicacao-visual` · Então recebe **200** e o componente Inertia renderizado é
  **`ComunicacaoVisual/Index`** — não uma Blade, não um redirect.
- **Teste:** [`ContratoTelaOrcamentoTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/ContratoTelaOrcamentoTest.php)
  — o `assertOk()` + a checagem de `component` que servem de **pré-condição anti-vácuo** dos
  casos UC-CV-07: sem eles, um 403 faria o teste "passar" sem ter medido contrato nenhum
  ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-24).
- **Contrato:** `CU-CV-01` itens 1-2 do SDD · o gate de permissão do `Routes/web.php`
  (`superadmin ∥ comvis.orcamento.view ∥ comvis.os.view`).
- **Regressão que defende:** o gate está numa **closure de rota**, não num FormRequest nem numa
  policy — é o tipo de código que ninguém revisa e que some num refactor de rotas. E o módulo já
  teve o incidente inverso: em 2026-05-26 o dropdown legado do `DataController` apontava pra URLs
  `/comunicacao-visual/admin/*` inexistentes (404), removido justamente por isso.
- **Status: 🧪 ⏭ PR-skip** — o caso vive no arquivo que pula em SQLite; veredito da full-suite noturna.

---

## UC-CV-11 · Salvar o orçamento grava os valores do servidor, atomicamente · `should`

- **Persona:** Larissa-equivalente (quando a Sprint 2 ligar o botão) — o orçamento salvo tem que
  ser exatamente o que ela mostrou ao cliente.
- **Aceite:** Dado um orçamento válido · Quando `POST /…/api/orcamentos` é chamado · Então responde
  **201** com cabeçalho **e** itens; `area_m2`, `preco_unitario_m2` e `subtotal` gravados são a
  **saída do Service**, nunca números vindos do cliente; cabeçalho e itens são gravados na mesma
  transação (não existe orçamento sem linha); e o `numero` segue `ORC-{ano}-{5 dígitos}`, sequencial
  **por business e por ano-civil**.
- **Teste:** [`OrcamentoControllerTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/OrcamentoControllerTest.php)
  — *"POST /orcamentos persiste no DB e retorna 201 com Orcamento + itens"* e
  *"GET /orcamentos/{id} retorna Orcamento com itens"*.
- **Contrato:** `CU-CV-05` itens 1-4 do SDD · `OrcamentoController@store`/`@gerarNumero`.
- **Regressão que defende:** o `store` **não tem consumidor de UI** hoje (SDD §5.4.2) — código sem
  usuário é código que apodrece sem ninguém perceber. Quando a Sprint 2 ligar o botão "salvar",
  este caso é a única coisa entre a tela e um orçamento gravado com o total do navegador.
- ⚠️ A corrida de numeração concorrente (`CU-CV-05` item 5) **não** está coberta — é `[BACKLOG]`, SDD §9 D-2.
- **Status: 🧪 ⏭ PR-skip** — arquivo pula em SQLite; veredito da full-suite noturna.

---

## UC-CV-12 · O catálogo de partida nasce completo, idempotente e isolado · `should`

- **Persona:** dono de gráfica no primeiro dia — ele não vai cadastrar lona, vinil e ACM na mão
  antes de fazer o primeiro orçamento.
- **Aceite:** Dado um business novo do vertical · Quando o `MaterialSeeder` roda · Então existem
  **5** materiais com preço/m²; rodar de novo continua em **5** (idempotente); e `run(1)` e `run(99)`
  produzem catálogos que não se enxergam.
- **Teste:** [`MaterialSeederTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/MaterialSeederTest.php)
  — os 4 casos (contagem, idempotência, isolamento por business, global scope no `find`).
- **Contrato:** `CU-CV-07` itens 1-3 do SDD · DoD da `US-COMVIS-002` no SPEC.
- **Regressão que defende:** seeder não-idempotente é o vetor clássico de duplicar catálogo a cada
  deploy — e catálogo duplicado com preços diferentes é ambiguidade de **valor** entrando pela porta
  dos fundos. O isolamento por business no seeder é o mesmo Tier 0 do UC-CV-04, na hora do onboarding.
- ⚠️ Este caso prova que o catálogo **existe no banco**, não que a operadora o vê — quem prova a
  tela é o UC-CV-07 (até 2026-09-23 a rota não entregava o catálogo; SDD §5.4.1).
- **Status: 🧪 ⏭ PR-skip** — arquivo pula em SQLite; veredito da full-suite noturna.

---

## UC-CVPRC-01 · Peça menor que o mínimo do material é cobrada pelo mínimo · `must` `[V0]`

- **Persona:** vendedor de gráfica orçando plaquinha de 30 × 40 cm — a gráfica não corta, imprime e
  acaba uma peça por menos que o mínimo dela.
- **Aceite:** Dado um material com mínimo de 0,5 m² por peça · Quando o item tem peças menores · Então
  cada peça é cobrada como 0,5 m² (`area_cobrada_m2`), a área física (`area_m2`, base do custo) não
  muda, e peça maior que o mínimo segue pela própria área. Material sem mínimo, ou de outra empresa,
  não aplica mínimo nenhum: o orçamento sai com os mesmos números de antes.
- **Teste:** [`OrcamentoMinimoAcabamentoContratoTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/OrcamentoMinimoAcabamentoContratoTest.php)
  — cada valor é conta à mão, refeita por um segundo caminho sem banco.
- **Contrato:** DoD da `US-COMVIS-001` no SPEC (*"mínimo cobrado configurável (`material.minimo_m2`)"*)
  · `CU-CV-02` do SDD (o servidor é a fonte do valor) · SDD §5.4.6 / D-8.
- **Status: 🧪** — verde no CT 100 em 2026-10-26 (cópia isolada do branch); veredito da lane pendente.

---

## UC-CVPRC-02 · Acabamento do catálogo entra no total pela base do tipo dele · `must` `[V0]` `[T0]`

- **Persona:** vendedor que soma bainha, ilhós, laminação e arte num banner sem abrir planilha.
- **Aceite:** Dado acabamentos cadastrados da empresa · Quando o item os escolhe · Então cada um vale
  base × preço, com a base pelo tipo (SPEC §12.1): `m_linear` = metros informados ou o perímetro da
  peça × quantidade; `m2` = área física; `unitario` = quantidade informada ou nº de peças; `fixo` = 1.
  A soma vai para `acabamentos` e entra no total; o subtotal do material não muda. Acabamento de outra
  empresa, inativo ou sem empresa conhecida é recusado. Gravar guarda o acabamento e o preço do dia.
- **Teste:** [`OrcamentoMinimoAcabamentoContratoTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/OrcamentoMinimoAcabamentoContratoTest.php)
- **Contrato:** DoD da `US-COMVIS-001` (*"acabamentos[]"*, *"extras = sum(acabamentos.preco)"*) ·
  SPEC §12.1 (`cv_acabamentos.tipo`) · `CU-CV-02` do SDD.
- **Status: 🧪** — verde no CT 100 em 2026-10-26 (cópia isolada do branch); veredito da lane pendente.

---

## UC-CVPRC-03 · Perda % do material soma na área cobrada · `must` `[V0]`

- **Persona:** vendedor de gráfica orçando lona, que perde material no corte e na margem de refile.
- **Aceite:** Dado um material com perda de 10% · Quando o item é calculado · Então a área cobrada é
  `max(L × A, mínimo) × 1,10 × quantidade` (3 × 1,5 → 4,95 m²) e a área física não muda. Com mínimo, a
  perda incide sobre o mínimo.
- **Teste:** [`OrcamentoPerdaEmendaContratoTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/OrcamentoPerdaEmendaContratoTest.php)
- **Contrato:** decisão [W] 2026-10-26 no DoD da US-COMVIS-001.
- **Status: 🧪** — verde no CT 100 em 2026-10-26 (cópia isolada do branch); veredito da lane pendente.

---

## UC-CVPRC-04 · Peça maior que a bobina leva emenda cobrada por metro de solda · `must` `[V0]` `[T0]`

- **Persona:** vendedor orçando fachada de 5 × 3 m numa lona de bobina 1,60 m.
- **Aceite:** Dado um material com largura de bobina e preço do metro de solda · Quando os DOIS lados da
  peça passam da bobina · Então entra a linha "Emenda (solda)" = (N − 1) × maior lado × preço × quantidade,
  com N = tiras do menor lado (5 × 3 → 1 emenda de 5 m). Peça que cabe girada, material sem preço de
  solda, ou de outra empresa, não leva emenda. Reabrir o orçamento não devolve a emenda como acabamento
  escolhido (ela é recalculada).
- **Teste:** [`OrcamentoPerdaEmendaContratoTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/OrcamentoPerdaEmendaContratoTest.php)
- **Contrato:** decisão [W] 2026-10-26 no DoD da US-COMVIS-001.
- **Status: 🧪** — verde no CT 100 em 2026-10-26 (cópia isolada do branch); veredito da lane pendente.

---

## Agenda da instalação (US-COMVIS-007 · UC-FLX-21)

> **Âncora:** UC-FLX-21 do catálogo de fluxos (*"Agenda equipe e veículo; bloqueia se não houver
> janela"*) e o aceite de equipe + veículo + bloqueio da
> [US-COMVIS-007](../../../../memory/requisitos/ComunicacaoVisual/SPEC.md). Nesta entrega são os
> endpoints `POST /comunicacao-visual/api/os/{os}/instalacao` e
> `GET /comunicacao-visual/api/instalacoes?de=&ate=` (janela de até 92 dias); não há tela de
> instalação nem de OS, por isso os casos moram no hub (a agenda visual é follow-up). Veículo é
> texto (placa/apelido): a empresa não tem cadastro de frota. Sobreposição é
> `início < fim da outra E fim > início da outra`, só contra instalações `agendada`/`em_execucao`
> da mesma empresa. Prova: `InstalacaoAgendaContratoTest` (tenant 98 contra 99,
> [ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).
> Sem impacto em valor ou estoque.

## UC-CVINST-01 · Agenda equipe e veículo na OS e a agenda da janela lista a instalação · `must`

**Dado** uma OS da empresa na etapa instalação **Quando** o coordenador agenda 21/10 09:00–11:00 com dois
instaladores e o veículo "Fiorino ABC1D23" **Então** responde 201 e a instalação fica `agendada`, presa à OS,
com início, fim, equipe e veículo; a agenda do dia lista a instalação com o número da OS e o nome de quem vai,
e não mostra a instalação de outra empresa no mesmo dia. Janela pedida maior que 92 dias → 422.

Status: 🧪

## UC-CVINST-02 · Alguém da equipe ocupado na janela bloqueia, dizendo com quem e quando · `must`

**Dado** o instalador A agendado 09:00–11:00 **Quando** outra OS pede 10:00–12:00 com A (veículo diferente)
**Então** responde 422 com o nome de A e o horário da instalação que conflita, e nada é gravado.

Status: 🧪

## UC-CVINST-03 · O mesmo veículo na janela bloqueia, mesmo escrito de outro jeito · `must`

**Dado** "Fiorino ABC1D23" agendado 09:00–11:00 **Quando** outra OS pede 10:30–12:00 com outra pessoa e o
veículo " fiorino abc-1d23 " **Então** responde 422 citando o veículo, e nada é gravado.

Status: 🧪

## UC-CVINST-04 · Horário encostado e instalação cancelada não bloqueiam · `must`

**Dado** A e o veículo agendados 09:00–11:00 **Quando** outra OS pede 11:00–13:00 ou 07:00–09:00 com os
mesmos A e veículo **Então** agenda (201); e depois de a instalação das 09:00 ser cancelada, 09:30–10:30
também agenda.

Status: 🧪

## UC-CVINST-05 · Equipe de outra empresa é recusada; a agenda da outra empresa nunca conflita · `must` `[T0]`

**Quando** a equipe inclui um usuário da empresa 99 **Então** responde 422 e nada é gravado. **Dado** a 99 com
o mesmo veículo e o mesmo id de pessoa na mesma janela **Quando** a 98 agenda **Então** agenda (201).

Status: 🧪

## UC-CVINST-06 · OS de outra empresa devolve 404 · `must` `[T0]`

**Quando** a 98 tenta agendar instalação na OS da 99 **Então** a OS não é encontrada (404) e nenhuma
instalação nasce nela.

Status: 🧪

## UC-CVINST-07 · Sem permissão não agenda nem lista · `must`

**Quando** quem só tem `comvis.os.view` agenda **Então** 403 e nada é gravado; sem permissão nenhuma, listar
dá 403; com `comvis.os.view` a agenda lista (200); com `comvis.os.update_status` agenda (201).

Status: 🧪

## UC-CVINST-08 · Agendar avisa o cliente por WhatsApp com OS, dia e hora · `must` `[LGPD]`

**Dado** a OS cujo orçamento tem cliente com celular, e a empresa com número de saída do WhatsApp **Quando**
o coordenador agenda 21/10 09:00 **Então** sai na fila uma mensagem para o celular do cliente, pelo número da
empresa, com o número da OS e "21/10/2026 às 09:00", sem o nome de quem instala nem o veículo. Agenda recusada
por falta de janela não avisa.

Status: 🧪

## UC-CVINST-09 · Cliente que recusou WhatsApp, sem celular, sem cliente ou sem canal ativo não é avisado · `must` `[LGPD]`

**Quando** o cliente marcou que não aceita WhatsApp (`whatsapp_consent = false`), não tem celular, o orçamento é
balcão (sem cliente) ou o número da empresa está caído **Então** a instalação é agendada e nenhuma mensagem
sai. Controle: com o canal de volta, o mesmo cliente recebe.

Status: 🧪

## UC-CVINST-10 · Contato e número de outra empresa nunca entram no aviso · `must` `[T0]`

**Dado** um orçamento da 98 que aponta para um contato da 99 **Então** ninguém é avisado. **Dado** a 98 sem
número de saída e a 99 com número **Então** o número da 99 não é usado.

Status: 🧪

## Orçamento aprovado vira OS (US-COMVIS-003 · UC-FLX-01)

> **Âncora:** UC-FLX-01 do catálogo de fluxos (*"Aprovado: cria a OS com itens, medidas e arquivos
> do orçamento"*) e o aceite da [US-COMVIS-020](../../../../memory/requisitos/ComunicacaoVisual/SPEC.md)
> (entrada da OS da US-COMVIS-003). Nesta entrega é o endpoint
> `POST /comunicacao-visual/api/orcamentos/{id}/aprovar`; a tela ainda não tem o botão (nenhuma tela
> salva nem lista orçamentos — ver Backlog). Não há tela de OS ainda, por isso os casos moram no hub.
> Prova: `OrcamentoViraOsContratoTest` (tenant 98 contra 99, [ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).
> VALOR: regra mestre — a aprovação não recalcula preço.

## UC-CVOS-01 · Aprovar cria a OS com os itens e as medidas do orçamento · `must`

**Dado** o orçamento enviado com Banner 3,000 × 1,500 × 1 (4,500 m²) e Adesivo 2,000 × 1,000 × 3 (6,000 m²)
**Quando** ele é aprovado **Então** o orçamento fica `aprovado` e nasce uma OS da empresa, na etapa `arte`,
número `OS-AAAA-NNNNN`, com dois itens que repetem descrição, largura, altura, quantidade, área e observação.

Status: 🧪

## UC-CVOS-02 · A OS vale o total do orçamento e nenhum valor do orçamento muda · `must` `[V0]`

**Dado** subtotal 510,00 (270,00 + 240,00) e instalação 200,00 **Quando** o orçamento é aprovado **Então** a
OS vale 710,00 (conta à mão e pelo calculator), e subtotal, total, desconto, extras, instalação e o subtotal
de cada item do orçamento ficam iguais aos de antes.

Status: 🧪

## UC-CVOS-03 · Aprovar de novo não duplica OS, itens nem arquivos · `must`

**Dado** o orçamento já aprovado **Quando** a aprovação chega de novo **Então** volta a mesma OS (200, não
201), com os mesmos dois itens e o mesmo arquivo.

Status: 🧪

## UC-CVOS-04 · Os arquivos do orçamento passam para a OS, só os da empresa · `must` `[T0]`

**Dado** dois arquivos anexados ao orçamento e um anexo de outra empresa com o mesmo id de orçamento
**Quando** ele é aprovado **Então** a OS aponta para os dois arquivos da empresa (mesmo arquivo em disco) e
nunca para o da outra.

Status: 🧪

## UC-CVOS-05 · Outra empresa não aprova nem gera OS · `must` `[T0]`

**Quando** a empresa 99 tenta aprovar o orçamento da 98 **Então** o orçamento não é encontrado (404 na rota),
nenhuma OS nasce e o orçamento continua `enviado`.

Status: 🧪

## UC-CVOS-06 · Orçamento recusado não vira OS · `must`

**Dado** o orçamento recusado **Quando** a aprovação chega **Então** é recusada (422), nenhuma OS nasce e o
status continua `recusado`.

Status: 🧪

## UC-CVOS-07 · Só quem pode criar orçamento aprova · `must`

**Quando** um usuário sem `comvis.orcamento.create` aprova **Então** recebe 403 e nada é criado; com a
permissão, a OS nasce (201).

Status: 🧪

## UC-CVOS-08 · O orçamento aprovado reabre com o número da OS · `must`

**Dado** o orçamento enviado, ainda sem OS (o reaberto vem com `os` nulo) **Quando** ele é aprovado
**Então** reaberto ele vem `aprovado` com o id e o número da OS que gerou, a lista dos salvos mostra o
mesmo número, e a outra empresa não reabre o orçamento.

Na tela: o rodapé troca "Salvar" por "Aprovar e criar OS" quando o orçamento está gravado igual à
tela; a confirmação é um segundo clique na mesma linha. Mudança não salva não aprova.

Status: 🧪

## UC-CVOS-09 · A OS aprovada nasce com o roteiro padrão e aparece no quadro do PCP · `must`

**Dado** que a empresa escolheu um roteiro padrão **Quando** um orçamento vira OS (pela tela ou pelo
cliente no link) **Então** a OS ganha uma etapa do PCP por etapa não opcional do roteiro, em ordem, e
aparece em `/producao/quadro`. As etapas ficam no nome de quem aprovou; sem usuário (aceite pelo link),
no do dono da empresa. Sem padrão, a OS nasce como antes, sem etapas.

Status: 🧪

## UC-CVOS-10 · O padrão só aceita roteiro ativo da própria empresa · `must` `[T0]`

**Dado** quem cuida do catálogo **Quando** escolhe o roteiro padrão **Então** roteiro de outra empresa ou
inativo dá 422 e nada muda; um roteiro ativo da empresa vira o padrão; vazio tira o padrão. Um roteiro
de outra empresa gravado à mão no ajuste não gera etapa nenhuma.

Status: 🧪

## UC-CVOS-11 · O hub mostra e troca o roteiro padrão para quem cuida do catálogo · `should`

**Dado** quem cuida do catálogo (`comvis.material.manage`) **Quando** abre o hub **Então** o cartão
"Roteiro da produção" mostra o roteiro padrão atual e os roteiros ativos; "Salvar roteiro" só libera
quando a escolha muda; sem roteiro ativo, diz para cadastrar na tela de Roteiros da produção.

Teste: `tests/js/cv-roteiro-padrao.test.tsx`

Status: 🧪

## Pós-cálculo da OS (US-COMVIS-005)

> Âncora: SPEC US-COMVIS-005 (custo orçado × real ao fechar a OS). `GET …/api/os/{os}/pos-calculo`
> (`OsPosCalculoController` → `PosCalculoDaOs`), atrás de `COMVIS_POS_CALCULO_ENABLED` (desligada),
> só para quem vê custo (superadmin ou `comvis.material.manage`). Só lê: nenhum valor gravado muda.
> As horas e o m² impresso vêm do Producao pelo contrato `App\Contracts\Producao\CustoRealDaOs`
> (a conta das horas é a do `CustoRealService`). Sem tela neste PR; terceiros entram na fatia seguinte.

Conta do arranjo (preço da OS 300,00; peças A 6,000 m² e B 1,440 m², ambas a 8,50/m²; A com 18,00 de máquina prevista):

| | Material | Máquina | Pessoa | Total | Margem |
|---|---|---|---|---|---|
| Orçado | 63,24 | 18,00 | — | 81,24 | 218,76 (72,9%) |
| Real | 8,190 m² × 63,24 ÷ 7,440 = 69,615 → 69,62 | 2h30 × 9,00 = 22,50 | 1h × 15,00 = 15,00 | 107,12 | 192,88 (64,3%) |

2º caminho do material: as duas peças custam 8,50/m², 8,190 × 8,50 = 69,615 → 69,62. Arredonda uma vez, meio para cima.

## UC-CVPOS-01 · Custo orçado, custo real e as duas margens da OS saem da conta feita à mão · `must` `[V0]`
- **Persona:** dono da gráfica (quem cuida do catálogo, `comvis.material.manage`) conferindo se a OS deu a margem que o orçamento prometeu.
- **Aceite:** Dado o arranjo acima (preço 300,00; peças A e B a 8,50/m²; 8,190 m² impressos; 2h30 de máquina a 9,00 e 1h de pessoa a 15,00) · Quando pede o pós-cálculo · Então vêm orçado 63,24 + 18,00 = 81,24 e real 69,62 + 22,50 + 15,00 = 107,12, margem orçada 218,76 (72,9%) e real 192,88 (64,3%). O material real é o m² impresso nas etapas × o custo médio do m² das peças no dia do orçamento.
- **Teste:** `Modules/ComunicacaoVisual/Tests/Feature/OsPosCalculoContratoTest.php` — `UC-CVPOS-01 · custo orçado, custo real e as duas margens saem da conta feita à mão [V0]`.
- **Regressão que defende:** SPEC US-COMVIS-005 (custo orçado × real ao fechar a OS) e a regra mestre de valor: conta em inteiros, arredonda uma vez, meio para cima; 2º caminho à mão (8,190 × 8,50 = 69,615 → 69,62).
- **Status: 🧪** — passou na lane `verticais-pest` (MySQL), run 40495854452, 2026-10-27 (5 de 5 UC-CVPOS verdes); ✅ quando `casos:results` regravar o manifesto.

## UC-CVPOS-02 · Trecho aberto não entra; etapa sem m² conta a hora e não o material · `must` `[V0]`
- **Persona:** o mesmo dono olhando uma OS ainda em produção.
- **Aceite:** Dado um trecho de máquina fechado com 7,440 m², um de pessoa sem m² e um de máquina ainda aberto · Quando pede o pós-cálculo · Então o real é 63,24 + 9,00 + 15,00 = 87,24: o trecho aberto não conta.
- **Teste:** `Modules/ComunicacaoVisual/Tests/Feature/OsPosCalculoContratoTest.php` — `UC-CVPOS-02 · trecho aberto não entra; etapa concluída sem m² conta a hora e não o material [V0]`.
- **Regressão que defende:** a regra do `CustoRealService` (só apontamentos FECHADOS) aplicada à OS da CV; hora em andamento contada viraria custo inventado.
- **Status: 🧪** — passou na lane `verticais-pest` (MySQL), run 40495854452, 2026-10-27 (5 de 5 UC-CVPOS verdes); ✅ quando `casos:results` regravar o manifesto.

## UC-CVPOS-03 · Peça sem custo deixa o orçado vazio; nenhuma com custo deixa o real vazio · `must`
- **Persona:** o mesmo dono, numa OS cujo orçamento tem peça sem material cadastrado.
- **Aceite:** Dado a peça A sem custo previsto · Quando pede o pós-cálculo · Então orçado e margem orçada vêm vazios ("1 de 2" peças com custo) e o material usa o custo médio só da peça B (2,880 × 12,24 ÷ 1,440 = 24,48). Sem nenhuma peça com custo, material, total e margem real vêm vazios.
- **Teste:** `Modules/ComunicacaoVisual/Tests/Feature/OsPosCalculoContratoTest.php` — `UC-CVPOS-03 · peça sem custo deixa o orçado vazio; nenhuma com custo deixa o real vazio`.
- **Regressão que defende:** a mesma regra da margem do orçamento (US-COMVIS-022, UC-CVMRG-03): margem calculada com buraco engana.
- **Status: 🧪** — passou na lane `verticais-pest` (MySQL), run 40495854452, 2026-10-27 (5 de 5 UC-CVPOS verdes); ✅ quando `casos:results` regravar o manifesto.

## UC-CVPOS-04 · OS, hora e m² de outra empresa nunca entram · `must` `[T0]`
- **Persona:** dono da empresa 98, com a empresa 99 apontando no mesmo id de OS.
- **Aceite:** Dado a empresa 99 apontando hora e 50 m² no mesmo id da OS da 98 · Quando a 98 pede o pós-cálculo · Então máquina fica 0,00 e m² impresso 0,000; a OS da 99 pedida pela 98 devolve 404.
- **Teste:** `Modules/ComunicacaoVisual/Tests/Feature/OsPosCalculoContratoTest.php` — `UC-CVPOS-04 · OS, apontamento e hora de outra empresa nunca entram [T0]`.
- **Regressão que defende:** **Tier 0** (ADR 0093): o contrato `CustoRealDaOs` e as leituras da CV filtram pelo `business_id` recebido.
- **Status: 🧪** — passou na lane `verticais-pest` (MySQL), run 40495854452, 2026-10-27 (5 de 5 UC-CVPOS verdes); ✅ quando `casos:results` regravar o manifesto.

## UC-CVPOS-05 · Flag desligada é 404; sem quem vê custo é 403 · `must`
- **Persona:** qualquer usuário logado, com a flag `COMVIS_POS_CALCULO_ENABLED` desligada ou ligada.
- **Aceite:** Dado a flag desligada · Quando alguém pede · Então 404. Ligada, quem só vê orçamento recebe 403 e quem cuida do catálogo recebe 200.
- **Teste:** `Modules/ComunicacaoVisual/Tests/Feature/OsPosCalculoContratoTest.php` — `UC-CVPOS-05 · com a flag desligada é 404; sem quem vê custo é 403`.
- **Regressão que defende:** feature nova nasce desligada; custo e margem só para quem vê custo, a mesma regra da calculadora (UC-FLX-03).
- **Status: 🧪** — passou na lane `verticais-pest` (MySQL), run 40495854452, 2026-10-27 (5 de 5 UC-CVPOS verdes); ✅ quando `casos:results` regravar o manifesto.

## Custo e margem por peça (US-COMVIS-022 · UC-FLX-03)

> Âncora: UC-FLX-03 (margem por item no orçamento) e SPEC US-COMVIS-022. `POST …/api/calcular`
> passa a devolver, só para quem tem `comvis.material.manage` (ou superadmin), `custo`, `margem` e
> `margem_pct` por item e um bloco `margem` do orçamento (`MargemDoOrcamento`). O custo vem de
> `OrcamentoGravador::custosPrevistos`, a regra que o orçamento grava (US-COMVIS-019). Preço,
> subtotal e total não mudam. Execução: CT 100 MySQL, branch `65bccea49` — 6 passed, e os irmãos
> de custo previsto seguem verdes (44 passed, 227 assertions); mutação que tira o desconto da
> receita derruba UC-CVMRG-01 e 05.

Conta do arranjo (Lona custo 8,50/m², venda 30,00/m²):

| Peça | Área | Preço | Custo | Margem |
|---|---|---|---|---|
| A 2,000 × 1,500 × 2 | 6,000 m² | 180,00 | 51,00 | 129,00 (71,7%) |
| B 0,800 × 0,600 × 3 | 1,440 m² | 43,20 | 12,24 | 30,96 (71,7%) |
| Orçamento | — | 223,20 − 20,00 de desconto = 203,20 | 63,24 | 139,96 (68,9%) |

Extras (50,00) somam no total (253,20) e ficam fora da margem: não têm custo cadastrado.

## UC-CVMRG-01 · Cada peça e o orçamento mostram custo e margem · `must` `[V0]`

**Dado** o arranjo acima **Quando** o orçamento é conferido por quem vê custo **Então** cada peça traz
o custo, a margem em R$ e em % da tabela, e o orçamento traz custo 63,24, receita 203,20 e margem
139,96 (68,9%) — conferidos à mão e pela fórmula sem banco.

Status: 🧪

## UC-CVMRG-02 · Preço, subtotal e total são os mesmos com e sem margem · `must` `[V0]`

**Dado** o mesmo orçamento **Quando** calculado com e sem a margem **Então** total (253,20),
subtotal e o preço e o subtotal de cada peça são idênticos.

Status: 🧪

## UC-CVMRG-03 · Peça sem custo fica sem margem; o orçamento diz quantas têm · `must`

**Dado** mais uma peça de material com custo zero e uma sem material **Quando** conferido **Então**
essas duas vêm sem custo e sem margem, e o orçamento diz "2 de 4" sem margem total — margem com
peça sem custo enganaria.

Status: 🧪

## UC-CVMRG-04 · Custo de material de outra empresa nunca entra · `must` `[T0]`

**Dado** uma peça com material da empresa 99 (custo 3,00) **Quando** a 98 confere **Então** a peça
vem sem custo; a mesma peça lida pela 99 tem custo 3,00.

Status: 🧪

## UC-CVMRG-05 · Só quem gerencia materiais vê custo e margem · `must`

**Dado** um usuário de balcão (`comvis.orcamento.create`) e um que também tem
`comvis.material.manage` **Quando** os dois conferem **Então** só o segundo recebe custo e margem, e o
total é o mesmo para os dois.

Status: 🧪

## UC-CVMRG-06 · O custo mostrado é o que o orçamento grava · `must` `[V0]`

**Dado** o mesmo cálculo **Quando** ele é mostrado e gravado **Então** o `custo_previsto` de cada
item gravado é o custo mostrado na tela.

Status: 🧪

## Arquivos do orçamento (US-COMVIS-020 · UC-FLX-01)

> Âncora: SPEC US-COMVIS-020 — os arquivos anexados ao orçamento passam para a OS. Até 2026-10-21
> nada gravava `media` no orçamento: a cópia existia sem nada para copiar. Agora
> `POST …/api/orcamentos/{id}/arquivos` (multipart `arquivos[]`) e `DELETE …/arquivos/{media}`,
> só antes da OS existir. Execução: CT 100 MySQL, branch `7fc1d0862`, disco falso — 6 passed;
> mutação que tira a trava "já virou OS" derruba o UC-CVARQ-03.

## UC-CVARQ-01 · Anexar grava o arquivo no orçamento e aprovar o leva para a OS · `must`

**Dado** o orçamento enviado **Quando** são anexados `arte-fachada.pdf` e `foto-local.jpg` **Então**
há dois registros `media` da empresa no orçamento, o reaberto os lista pelo nome, e ao aprovar a OS
recebe os mesmos dois arquivos.

Status: 🧪

## UC-CVARQ-02 · Formato que a gráfica não usa é recusado · `must`

**Dado** um `.exe` **Quando** ele é anexado **Então** a validação recusa (formatos aceitos: pdf, jpg,
jpeg, png, svg, ai, eps, cdr, tif, tiff, psd) e nada é gravado.

Status: 🧪

## UC-CVARQ-03 · Depois de virar OS o orçamento não recebe nem perde arquivo · `must`

**Dado** o orçamento aprovado, com OS **Quando** se tenta anexar ou remover **Então** as duas
respostas são 422 e o arquivo do orçamento continua lá — o da OS é outra linha.

Status: 🧪

## UC-CVARQ-04 · Outra empresa não anexa nem remove · `must` `[T0]`

**Dado** o orçamento da 98 com um arquivo **Quando** a 99 tenta anexar ou remover **Então** as duas
dão 404 e o arquivo continua.

Status: 🧪

## UC-CVARQ-05 · Sem permissão de criar não anexa nem remove · `must`

**Dado** um usuário só com `comvis.orcamento.view` **Quando** ele anexa ou remove **Então** 403, e nada muda.

Status: 🧪

## UC-CVARQ-06 · Remover tira o arquivo e ele não vai para a OS · `must`

**Dado** dois arquivos anexados **Quando** um é removido e o orçamento é aprovado **Então** a lista
fica só com o outro e a OS recebe um arquivo.

Status: 🧪

## Salvar e reabrir o orçamento (US-COMVIS-021)

> Âncora: SPEC US-COMVIS-021 e o UC-FLX-01 — aprovar precisa de um orçamento salvo. A tela grava com
> `POST /comunicacao-visual/api/orcamentos`, lista os salvos com `GET …/api/orcamentos` (e, na rota do
> hub, a prop deferida `recentes`), reabre por `/comunicacao-visual?orcamento={id}` e regrava o
> reaberto com `PUT …/api/orcamentos/{id}`. O servidor recalcula tudo nas duas gravações.
>
> Execução: CT 100, MySQL, cópia isolada do branch `a8ada7ef4` (2026-10-21) — 6 passed (39 assertions)
> junto com os irmãos ViraOs/CustoPrevisto (22 passed, 139 assertions). Mutação: tirar o
> `business_id` explícito do `paraTela` derruba o UC-CVSAL-05. Sem entrada no manifesto G-7, logo 🧪.

## UC-CVSAL-01 · Salvar grava os valores do servidor, não os da tela · `must` `[V0]`

**Dado** Banner 3,000 × 1,500 × 1 a 60,00/m² e Adesivo 2,000 × 1,000 × 3 a 40,00/m², instalação 200,00,
e um `total`/`subtotal` qualquer vindo do cliente **Quando** o orçamento é salvo **Então** ele nasce
`rascunho`, número `ORC-AAAA-NNNNN`, na empresa da sessão, com total 710,00 e subtotal 510,00 —
conferidos à mão e pelo `OrcamentoCalculator` — e os dois itens.

Status: 🧪

## UC-CVSAL-02 · Reabrir devolve as peças e os ajustes, e aparece na lista · `must`

**Dado** o orçamento salvo **Quando** ele é reaberto **Então** volta com as mesmas peças (descrição,
largura, altura, quantidade, preço por m²), com a instalação de 200,00 (que não tem campo na tela e não
pode sumir ao regravar) e com o total 710,00; e está na lista dos salvos da empresa.

Status: 🧪

## UC-CVSAL-03 · Regravar o reaberto mantém o número e troca as peças · `must` `[V0]`

**Dado** o orçamento salvo **Quando** ele é regravado com uma Placa 2,000 × 1,000 × 1 a 50,00/m² e a
mesma instalação **Então** o número é o mesmo (um orçamento só com ele), o total passa a 300,00 —
conferido à mão e pelo calculator — e o único item é a Placa.

Status: 🧪

## UC-CVSAL-04 · Orçamento que virou OS ou foi recusado não regrava · `must`

**Dado** um orçamento aprovado (com OS) e um recusado **Quando** se tenta regravar qualquer um
**Então** a resposta é 422 e total e itens ficam como estavam.

Status: 🧪

## UC-CVSAL-05 · Outra empresa não reabre, não lista nem regrava · `must` `[T0]`

**Dado** o orçamento da empresa 98 **Quando** a 99 tenta reabrir, listar ou regravar **Então** reabrir
devolve nada, a lista da 99 não o traz, regravar dá 404 e o total da 98 não muda.

Status: 🧪

## UC-CVSAL-06 · Sem permissão de criar não salva nem regrava · `must`

**Dado** um usuário só com `comvis.orcamento.view` **Quando** ele tenta salvar ou regravar **Então** a
resposta é 403 e nenhum orçamento é criado; listar continua permitido.

Status: 🧪

---

## Aceite do orçamento pelo link (US-COMVIS-023)

> Contrato: a US-COMVIS-023 do SPEC. Prova: `OrcamentoAceiteLinkContratoTest` (lane `verticais-pest`).
> A página pública ainda não existe; estes casos cobrem o serviço e a rota que gera o link.

## UC-CVACE-01 · Gerar o link guarda só o hash e vale para a empresa do orçamento · `must` `[T0]`

**Dado** um orçamento salvo em rascunho **Quando** o operador gera o link **Então** sai um token de
48 caracteres, o banco guarda só o sha256 dele, o orçamento passa a `enviado` e a leitura pelo token
mostra itens e total, sem custo. Gerar de novo fecha o link anterior. Orçamento de outra empresa não
gera link (404), nem pelo serviço nem pelo controller.

Status: 🧪

## UC-CVACE-02 · O cliente decide uma vez: aceitar vira OS com o total gravado, ajuste volta a rascunho · `must` `[V0]`

**Dado** um link aberto **Quando** o cliente aceita **Então** o orçamento vira OS pelo mesmo caminho da
tela, com `valor_total` igual ao `total` gravado, e o link não decide de novo. **Quando** pede ajuste
com um comentário **Então** o orçamento volta a rascunho, sem OS; sem comentário, recusa. Link vencido,
validade passada ou orçamento já aprovado não decidem.

Status: 🧪

## UC-CVACE-03 · A calculadora gera o link e entrega pronto para o WhatsApp · `should`

**Dado** um orçamento gravado, igual à tela e ainda não aprovado **Quando** o vendedor clica "Enviar no
WhatsApp" **Então** a tela chama `POST /comunicacao-visual/api/orcamentos/{id}/link`, mostra o link e
oferece "Copiar link" e "Abrir no WhatsApp" (`wa.me` com o número do orçamento e o link na mensagem).
Orçamento já aprovado não oferece o link (o serviço recusaria).

Teste: `tests/js/cv-aceite-link.test.tsx`

Status: 🧪

---

## Backlog — prosa honesta, sem id (ainda não é UC)

> Cada item aqui tem contrato em **uma fonte só**, ou não tem código. Vira UC quando ganhar
> implementação **e** teste que o cite — nunca antes (G-2 / [proibicoes §5](../../../../memory/proibicoes.md) 2026-07-16).

- `[BACKLOG]` **Mandar o PDF do orçamento salvo no WhatsApp.** Salvar e reabrir entraram em
  2026-10-21 (UC-CVSAL-01..06); o PDF e o envio não existem. SDD §5.4.2.
- `[BACKLOG]` **Numeração de orçamento com reserva atômica.** `gerarNumero()` lê `MAX(numero)` fora
  da transação; a corrida é barrada pelo `UNIQUE (business_id, numero)` (erro visível, não corrupção),
  mas não há teste do caso concorrente. SDD §5.4.3 / D-2.
- `[BACKLOG]` **CRUD de materiais.** Sem ele o catálogo do UC-CV-07 nasce vazio na prática — só
  existe seeder. SDD §6 CU-CV-07 item 4.
- `[BACKLOG]` **Seed da tributária CNAE 1813 + wizard de onboarding.** Os NCM/CFOP do ramo (4911.10,
  4911.99, 3919, 7610, 9405 · 5101/5102/5933/5949) não são populados por nada. SDD §6 CU-CV-10 itens 2-3.
- `[BACKLOG]` **Escolher o acabamento na calculadora; instalação e entrega como linhas.** O cálculo
  já soma acabamentos do catálogo (UC-CVPRC-02, 2026-10-26), mas a tela ainda só tem o campo
  "extras" manual, e não há cadastro de acabamentos nem de mínimo do material.
- `[BACKLOG]` **Migrar o `<select>` nativo da linha de item pro `<Select>` do DS.** Resíduo declarado
  no próprio `.tsx` com `eslint-disable` justificado. ⚠️ ao fazer, usar
  [`<SafeSelectItem>`](../../../../memory/requisitos/_DesignSystem/SAFE-SELECT-ITEM.md) — `value=""`
  derruba o render inteiro ([proibicoes §5](../../../../memory/proibicoes.md) 2026-06-29).
- `[BACKLOG]` **`RUNBOOK-comunicacao-visual-index.md`.** Não existe; o hook MWART bloqueia Edit no
  `.tsx` até criar. SDD §9 D-5.
- `[BACKLOG]` **Dicionário de domínio do vertical.** Sem `memory/dominio/comunicacao-visual.md` o
  módulo fica fora do `dominio-gate` (G-4, required) — os enums do schema não têm fonte única. SDD §9 D-6.
