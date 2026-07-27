---
date: "2026-07-27"
topic: "Chip S5 (Onda 2 · passo 5) — SDD do Financeiro do zero: Unificado (âncora) + Caixa, contrato [V0] da baixa"
authors: [C]
module: Financeiro
agente: sdd-from-source
outcomes:
  - "SDD-tela-financeiro-v1.0.md criado do zero (o módulo tinha 0 CU) — F1-F10 + CU-FIN-01..23"
  - "9 UC ancorados novos (UC-FUNI-01..04 [V0]/[T0] + UC-FCX-01..05), 0 órfãos"
  - "anchor-lint 93,1% -> 100%; sem_campo 4 -> 0; anchored_dead 0"
  - "8 achados com varredura contada — incl. régua dupla de UC-id e 56 de 80 testes fora de lane"
us: [US-FIN-003, US-FIN-013, US-FIN-031, US-FIN-038, US-FIN-064]
related_adrs:
  - 0351-sdd-from-source
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
---

# Chip Onda 2 · passo 5 — Financeiro (`Financeiro/Unificado` como âncora)

Primeiro run do ramo **"SDD não existe → cria §0–§10"** num módulo grande (21 telas · 58 US · 0 CU).

## 1. Alvo e fontes resolvidas

| # | Fonte | Estado |
|---|---|---|
| 1 | Documentação canon | ✅ `SPEC.md` (58 US) · 21 charters · `RUNBOOK-unificado.md` · `ARCHITECTURE.md` |
| 2 | React/Laravel vivo | ✅ 21 `.tsx` · 23 controllers · 10 services · 80 arquivos de teste |
| 3 | Blade legada | ✅ **do core UltimatePOS**, não do módulo — `cash_register/`, `account/`, `transaction_payment/`, `expense/` |
| 4 | Delphi / Office Comercial | ❌ **não existe** (`find memory -iname "*ANTI-REGRESSAO*"` = 2 arquivos, ambos do Produto) — **gap declarado** |

**Armadilha da Blade homônima, checada:** `Modules/Financeiro/Resources/views/` tem 3 blades e
**nenhuma** é tela de operação. A Blade que a operadora abre pro Caixa é
`resources/views/cash_register/index.blade.php` (core) — alcançada pelo próprio React via
`links.cash_register_legacy`. Comparar contra a blade do módulo teria dado paridade OK **falsa**.

## 2. Artefatos tocados

| Arquivo | Ação |
|---|---|
| `memory/requisitos/Financeiro/SDD-tela-financeiro-v1.0.md` | **criado** — §0–§10, F1–F10, CU-FIN-01..23 |
| `resources/js/Pages/Financeiro/Unificado/Index.casos.md` | +4 UC estritos (`UC-FUNI-01..04`) + tabela de rastreabilidade + trilha |
| `resources/js/Pages/Financeiro/Caixa/Index.casos.md` | **criado** — `UC-FCX-01..05` + 4 `[BACKLOG]` de paridade |
| `Modules/Financeiro/Tests/Feature/BaixaConservacaoValorContratoTest.php` | **criado** — contrato `[V0]` da baixa, anti-vácuo |
| `Modules/Financeiro/Tests/Feature/CaixaControllerTest.php` | `it()` passam a citar `UC-FCX-*`; docblock. **Zero corpo alterado** |
| `Modules/Financeiro/Tests/Feature/UnificadoBaixaDialogGuardTest.php` | `@covers-us US-FIN-003` + nota do G3 stale |
| `.github/workflows/financeiro-pest.yml` | allowlist +2 arquivos |
| `memory/requisitos/Financeiro/SPEC.md` | âncora de `US-FIN-003` reconciliada + `_pendente_` nas 4 US sem campo |
| `resources/js/Pages/Financeiro/ContasPagar/Index.casos.md` | correção factual do bullet D1 |

## 3. Veredito (Camada 3) — medido, não afirmado

| Porta | Antes | Depois |
|---|---|---|
| `requisitos-status` · CU no SDD | 0 | **16** |
| `requisitos-status` · telas com `casos.md` | 5 | **6** |
| `requisitos-status` · UC declarados | 17 | **26** |
| `requisitos-status` · UC com teste que os cita | 15 | **24** |
| `requisitos-status` · telas sem `casos.md` | 16 | **15** |
| `requisitos-status` · `casos.md` sem UC | 3 | **1** |
| `requisitos-status` · US entregue sem contrato | 13 | **11** |
| `anchor-lint` · coverage | 93,1% | **100%** |
| `anchor-lint` · `sem_campo` | 4 | **0** |
| `anchor-lint` · `anchored_dead` | 0 | **0** |
| `anchor-lint --check` | — | **exit 0** |
| `casos-coverage-guard` | — | **exit 0**, zero UC novo órfão |
| `deadlink-gate --check` | — | meu SDD limpo (ver achado 8) |

⚠️ **Nenhum UC nasce ✅.** Todos os 9 novos são 🧪 — não rodei teste (CT 100/CI · ADR 0062).
O veredito é da lane `PHP / Pest (Financeiro · MySQL)`, que **é required**
(`governance/required-checks-baseline.json`) → reprovar **bloqueia merge**.

## 4. Achados (com varredura CONTADA)

1. **A régua estrita não enxerga `UC-F0N`** — `requisitos-status.mjs:85` usa
   `UC-[A-Z0-9]{2,10}-\d{2,3}` (exige 2º hífen); o `casos-gate` usa `uc-regex.mjs`, mais frouxo.
   Os **5 UC reais** do Unificado (`UC-F01..F05`) eram invisíveis à porta → ela imprimia
   *"casos.md existe mas não declara nenhum UC"* e acusava `US-FIN-031`/`US-FIN-038` de
   "entregue sem contrato" **sendo falso**. **Não renomeei**: `UC-F01..03` são citados por
   `tests/Feature/TravaSegunda/RetencaoLoopE2ETest.php`, **fora da área do chip**. Resolvido por
   tabela de rastreabilidade + `> **Âncora:**`. *Dois medidores da mesma coisa com réguas
   diferentes é o defeito estrutural — reporto, não conserto (`scripts/` é área proibida).*
2. **56 de 80 testes do módulo não rodam em lane nenhuma** (24 na allowlist da `financeiro-pest`).
   Cobertura que nunca produz veredito — "verde impossível" no sentido do `anchor-lint`.
   Fechei 1 (`CaixaControllerTest`); os de Conciliação/DRE/Fluxo/Extrato seguem fora (§5).
3. **Divergência de contrato `status='parcial'` × SPLIT** — os `casos.md` de CP/CR e o **DoD do
   `US-FIN-003`** descrevem a baixa parcial como *"`valor_aberto=70` e `status=parcial`"*. O código
   **não usa `status='parcial'` desde 2026-06-04** (decisão [W], comentário literal em
   `UnificadoController@baixar`): faz **SPLIT** (filho quitado + pai reduzido, `titulo_pai_id`).
   Um teste escrito a partir do contrato antigo nasceria vermelho **por motivo errado**.
   Corrigi o **perdedor** (precedência código-provado > casos).
4. **Âncora do `US-FIN-003` estava stale** — dizia *"`_pendente_`, `ContasReceber/Show.tsx` modal
   não construída"*. A baixa está **viva desde 2026-06-03**, noutra tela (`FinBaixaSheet` do
   Unificado); a `ContasReceber/Show.tsx` **nunca existiu** e a própria ContasReceber está
   `deprecated`. Reconciliado.
5. **`UnificadoBaixaDialogGuardTest` G3: descrição stale, corpo certo** — `it()` diz
   *"reduz valor_aberto e marca parcial"*, o corpo asserte o split. Anotado, título não alterado
   (evita misturar escopo com renomeio que mexe no manifesto por-UC).
6. **Paridade do Caixa: abrir/fechar turno não existe no React.** Blade core tem `create` +
   `close_register_modal` + `register_details`; o React é read-only e **linka o legado**. Não é
   regressão silenciosa — é gap consciente. **Virar Non-Goal ou US é [W]** (§6).
7. **Teste do Caixa ancorava num US inexistente** — o docblock citava `US-FIN-CAIXA`, que não
   existe no SPEC. Removido; **não** substituí por um US alheio (seria âncora falsa).
8. **Fora da minha área, reportado e não consertado:** `deadlink-gate --check` está vermelho por
   `memory/requisitos/OficinaAuto/SDD-tela-ordem-servico-v1.0.md` → link morto
   `_telas/importer-frota-legada.casos.md`. É de outra sessão da onda.

## 5. Telas cobertas × deixadas

**Cobertas com contrato REAL (UC + teste que o cita + lane):** `Unificado/Index` (+4 UC estritos,
totalizando 8 com os legados) · `Caixa/Index` (5 UC).

**Deixadas, com motivo declarado:**

| Tela(s) | Motivo |
|---|---|
| `ContasReceber/Index` · `ContasPagar/Index` | `deprecated` (#3718) + `US-FIN-064` prevê o redirect. Contrato em tela que vai morrer é dívida. Só correção factual. |
| `Conciliacao` · `Dre` · `Fluxo` · `Extrato` | os testes **existem** mas estão **fora da lane**; declarar UC citando teste que nunca roda produz exatamente o "verde impossível" que o `anchor-lint` denuncia. Entrar na allowlist exige run prévio no CT 100 — **não posso rodar**. Virou `R2` do roadmap do SDD. |
| `Cobranca` · `ContasBancarias` · `Categorias` · `PlanoContas` · `Configuracoes/Contador` · `Advisor/*` · `AssinaturaAtualizar` · `Unificado/Novo` · `Dashboard` | orçamento; e `Unificado/Novo`/`AssinaturaAtualizar`/`Advisor/Login` têm US de reforma abertas (065/066/067) que vão mudar a tela — contrato agora seria refeito. |

## 6. Decisões que tomei sozinho (e as que escalei)

**Decidi (não perguntei):**

- **Apliquei** as âncoras no SPEC (não propus): `US-FIN-003` reconciliada + `_pendente_` nas 4 US
  `todo` sem campo. `anchor-lint --check` = **exit 0**, `dead=0` → **nada a reverter**.
- **Onde o dente D1 mora.** O `casos.md` de ContasPagar pedia o teste de baixa parcial *ali*.
  Coloquei em **`Unificado`**, porque é onde a baixa **realmente executa** — e a tela pedinte está
  deprecada. Contrato na tela errada não defende nada.
- **Assert de invariante, não de campo.** O contrato antigo pedia `status=='parcial'` (hoje falso).
  Assertei `Σ(filhos)+pai.valor_aberto == original` — verdadeiro mesmo se a estrutura mudar.
- **Anti-vácuo obrigatório.** `cvExigeQueTenhaBaixado()` roda **antes** de toda invariante: sem
  linha em `fin_titulo_baixas`, o caso **falha** em vez de passar por não-execução.
- **`CaixaControllerTest` entrou na allowlist SEM run prévio.** A lane é required. Se avermelhar,
  o ❌ é o achado e a decisão é de [W] — está escrito no comentário do YAML, não escondido.
- **Não renomeei `UC-F01..05`** (tocaria arquivo fora da área) nem editei `scripts/`.
- **Não inventei fonte 4** nem `@covers-us` de US alheia.

**Escalei a [W] (soberania, não dúvida):**

1. **Non-Goal do Caixa** — "abrir/fechar turno fica no legado" é Non-Goal ou vira US? O agent é
   proibido de preencher Non-Goal/Anti-hook do charter.
2. **Merge do PR** (R10) e o veredito da lane.
3. **A régua dupla de UC-id** (achado 1) — a correção mora em `scripts/`, área proibida ao chip.

## 7. Orçamento da corrida

| Métrica | Valor |
|---|---:|
| Tool calls | **~76** |
| Arquivos lidos (Read/sed/grep alvo) | ~28 |
| Varreduras contadas (sem `head_limit` no que decidia) | 9 |
| UC ancorados criados | **9** (4 `UC-FUNI` + 5 `UC-FCX`) |
| `[BACKLOG]` sem id criados | 4 |
| CU criados | **23** (16 na contagem da porta; os demais são `⬜`/`❌` declarados) |
| Achados | **8** |
| Telas cobertas / deixadas | 2 / 19 |
| Testes novos | 1 arquivo · 4 casos |
| Reuso vs re-varredura | **1ª tela do módulo = zero reuso** (não havia SDD, §5.3 nem `AR-*`). A 2ª tela (`Caixa`) custou **~8 tool calls** contra ~40 da 1ª — reusou §5 inteiro, personas, governança e o mapa de controllers; **re-varreu só** a Blade de referência e o teste dela (a Fase 1.4 diz que isso nunca se reusa, e a Blade do Caixa provou o porquê). |
| **Gargalo** | **Camada 1 do módulo** (~40 calls): 58 US, 23 controllers, 80 testes e a descoberta de que 70% não rodam. O 2º gargalo foi **reconciliar réguas** (achado 1) — 6 calls só pra entender por que a porta dizia "0 UC" numa tela com 5 UC reais. |

## 8. Lições de mecanismo (o que na definição do agent atrapalhou)

1. **"Não escreva `scripts/`" × "a porta viva é a régua"** — a porta subconta por um detalhe de
   regex e o chip é proibido de corrigir. A saída honesta (declarar âncora) é correta, mas o
   próximo módulo com id fora do formato vai reincidir. **Sugestão:** o contrato do chip deveria
   dizer qual é o **formato canônico de UC-id** (`UC-<PREFIXO>-NN`, 2 hífens) já no prompt — barato
   e evita o gasto de descobrir.
2. **A Fase 2.2 manda `casos.md` ao lado do `.tsx` e a Fase 2.1 manda o §5.3 no SDD do módulo —
   mas nada diz o que fazer quando a tela dona do comportamento está `deprecated`.** Gastei
   decisão própria pra concluir que o contrato segue o **executor**, não a tela pedinte. Vale virar
   regra: *"UC mora na tela onde o fluxo EXECUTA; tela deprecada só recebe correção factual."*
3. **"Adicionar o teste à allowlist é parte do chip" × "não afirme verde sem execução"** estão em
   tensão real: a allowlist é ratchet de verdes e o chip não pode rodar teste. Resolvi assumindo o
   vermelho como achado, mas a definição deveria dizer isso explicitamente em vez de deixar cada
   run reinventar a justificativa.
4. **O gasto de descobrir "esse teste roda?"** foi alto e é recorrente. Um número derivado
   (`testes do módulo` × `na allowlist`) por módulo economizaria a varredura manual — mas isso é
   máquina nova, e criar máquina exige FP medido antes (§5 2026-07-26); fica só como observação,
   não como proposta de gate.
