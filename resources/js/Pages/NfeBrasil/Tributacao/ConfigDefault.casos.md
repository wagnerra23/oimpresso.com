---
id: resources-js-pages-nfe-brasil-tributacao-config-default-casos
casos: Defaults tributários do business (Nível 4) · /nfe-brasil/tributacao/config-default
irmaos: ConfigDefault.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o que esta tela grava vira imposto na nota — o comportamento é durável mesmo se o form mudar de layout.
owner: wagner
last_run: "2026-07-27"
---

# Casos de Uso & Aceite — Defaults tributários (cascade Nível 4)

> **Âncora:** o módulo NfeBrasil **não tem SDD** (verificado em `origin/main` 2026-07-27 —
> `git ls-tree` não devolve nenhum `SDD-*` sob `memory/requisitos/NfeBrasil/`). A fonte de contrato,
> na ordem canônica de [`how-trabalhar.md`](../../../../../memory/how-trabalhar.md) §"Pedido de tela/feature", é:
>
> 1. [**ADR ARQ-0006**](../../../../../memory/requisitos/NfeBrasil/adr/arq/0006-cascade-defaults-ncm-produto.md) —
>    cascade tributário em 4 níveis. Define o **Nível 4** (`nfe_business_configs.tributacao_default`),
>    a tabela de onboarding por regime e o pattern `aplicarDefaults($defaults, …)`.
> 2. [**US-NFE-010**](../../../../../memory/requisitos/NfeBrasil/SPEC.md) — DoD "UI fase 2 (CRUD básico)"
>    + "Multi-tenant scope `business_id`" + "Permissão Spatie `nfe.tributacao.manage`".
> 3. [**ConfigDefault.charter.md**](ConfigDefault.charter.md) — Automation Hooks/Anti-hooks.
>
> Os UCs derivam do **contrato**, nunca da implementação — teste derivado do código é tautológico e
> trava o desvio em vez de pegá-lo ([`proibicoes.md`](../../../../../memory/proibicoes.md) §5 2026-06-05).
> O `ConfigDefaultController` foi lido só para **confirmar** o comportamento.
>
> **Por que este arquivo nasce agora:** fecha o trio da tela (o charter existe desde 2026-05-16;
> `casos.md` + teste faltavam). A tela é Tier-0 quente pela sentinela
> [`exposicao-tier0.mjs`](../../../../../scripts/qa/exposicao-tier0.mjs) — `exposure_score 9`,
> categorias `dinheiro,pii,fiscal` — e estava em **débito** (sem teste de comportamento).
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Contrato | Teste | Status |
|----|-------------|------|----------|-------|--------|
| UC-NFCD-01 | Config de outro business não vaza nem é sobrescrita | must `[T0]` | ADR 0093 · anti-hook | `ConfigDefaultContratoTest` | 🧪 |
| UC-NFCD-02 | O default persistido carrega a chave `cfop` que o motor Nível 4 lê | must `[fiscal]` | ARQ-0006 §Nível 4 | `ConfigDefaultContratoTest` | 🧪 |
| UC-NFCD-03 | Regime fora do enum dos 4 regimes é rejeitado | must `[fiscal]` | ARQ-0006 §Onboarding | `ConfigDefaultContratoTest` | 🧪 |
| UC-NFCD-04 | Alíquota é decimal ∈ [0,1] — "18" (por "18%") é rejeitado | must `[fiscal]` | charter §Automation Hooks | `ConfigDefaultContratoTest` | 🧪 |
| UC-NFCD-05 | Salvar config não reescreve NFe já emitida | must `[T0]` `[fiscal]` | anti-hook · SINIEF 07/2005 | `ConfigDefaultContratoTest` | 🧪 |
| UC-NFCD-06 | CSOSN e CST são mutuamente exclusivos | must `[fiscal]` | ARQ-0006 §Onboarding | `ConfigDefaultContratoTest` | 🧪 |

> **Recibo:** ver §Recibo de execução no rodapé — status é o **veredito** da corrida, não leitura de código.

---

## UC-NFCD-01 · Config de outro business não vaza nem é sobrescrita · `must` `[T0]`

- **Persona:** qualquer tenant. Esta config **é** o imposto aplicado quando o NCM do produto não tem
  regra — se a config de um business alcançar outro, o vizinho emite nota com a carga tributária errada.
- **Aceite:**
  - Dado config de `business_id=2` gravada · Quando `show()` roda com `business.id=1` · Então o payload
    **não** traz nada de biz=2 (cai no default de tela, `regime='simples'`).
  - Dado config de biz=2 gravada · Quando biz=1 faz `upsert` · Então cria/atualiza **só** a row de biz=1
    e a row de biz=2 permanece byte-a-byte igual.
- **Teste:** [`ConfigDefaultContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/ConfigDefaultContratoTest.php)
  — `UC-NFCD-01 · config de outro business não vaza no show nem é sobrescrita no upsert`.
- **Contrato:** [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) (Tier 0
  irrevogável) + charter §Automation Anti-hooks — *"❌ Não acessa config de outro `business_id`"* +
  US-NFE-010 DoD *"Multi-tenant scope `business_id` em todas queries (R-NFE-001)"*.
- **Regressão que defende:** `NfeBusinessConfig` usa `HasBusinessScope`, mas o controller **também**
  filtra à mão (`where('business_id', $businessId)`) a partir de `session('business.id')`. Nada hoje
  prova que os dois concordam; um refactor que troque a sessão pelo scope global (ou vice-versa) passa
  silencioso. Este é o `it('isolates config by business_id (cross-tenant 404)')` que o charter promete
  em §Métricas vivas e **nunca existiu**.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFCD-02 · O default persistido carrega a chave `cfop` que o motor Nível 4 lê · `must` `[fiscal]`

- **Persona:** Larissa emitindo NFC-e de um produto cujo NCM não tem regra cadastrada — o CFOP da nota
  sai do Nível 4. CFOP errado = natureza da operação errada na nota fiscal.
- **Aceite:** Dado um `upsert` com `cfop_default='6102'` · Quando leio `nfe_business_configs.tributacao_default`
  · Então a chave **`cfop`** existe e vale `'6102'` (não só `cfop_default`).
- **Teste:** [`ConfigDefaultContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/ConfigDefaultContratoTest.php)
  — `UC-NFCD-02 · upsert grava o alias cfop que o motor Nível 4 consome`.
- **Contrato:** [ARQ-0006](../../../../../memory/requisitos/NfeBrasil/adr/arq/0006-cascade-defaults-ncm-produto.md)
  §Decisão — o bloco Nível 4 especifica o JSON de `tributacao_default`, e §Pattern obrigatório mostra
  `aplicarDefaults($defaults, …)` consumindo esse JSON.
- **Regressão que defende:** `cfop_default` é nome de **campo de formulário**; quem o motor lê é
  `tributacao_default['cfop']` (`MotorTributarioService::aplicarDefaults`). O controller grava os dois —
  o alias é **load-bearing** e está a uma linha de ser removido como "duplicata". Se sumir, o motor
  **não quebra**: cai no literal `?? '5102'` e emite CFOP 5102 (operação **dentro** do estado) mesmo numa
  venda interestadual que pedia 6102. Falha silenciosa, com nota autorizada e errada. O outro caminho de
  escrita (`TributacaoTemplateService::aplicar`, a partir dos arquivos de
  `Modules/NfeBrasil/Resources/templates/`) grava `cfop` e **não** grava `cfop_default` — logo `cfop` é a
  chave que os dois caminhos têm em comum, e é ela que precisa de guarda.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFCD-03 · Regime fora do enum dos 4 regimes é rejeitado · `must` `[fiscal]`

- **Persona:** Gestor/Contador. O regime decide se a nota leva **CSOSN** (Simples/MEI) ou **CST**
  (Presumido/Real) — um valor fora do enum deixa a cascata sem código tributário definido.
- **Aceite:** Dado `regime='lucro_arbitrado'` (não existe no domínio da tela) · Quando faço `upsert`
  · Então a requisição é **rejeitada com erro de validação em `regime`** (rota web → redirect + erros
  na sessão, não 422) e **nenhuma** row de config é criada. Controle positivo no mesmo caso: um regime
  válido grava — senão o vermelho poderia vir de o POST nunca alcançar o gravador.
- **Teste:** [`ConfigDefaultContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/ConfigDefaultContratoTest.php)
  — `UC-NFCD-03 · regime fora do enum é rejeitado e não grava config`.
- **Contrato:** [ARQ-0006](../../../../../memory/requisitos/NfeBrasil/adr/arq/0006-cascade-defaults-ncm-produto.md)
  §Onboarding inteligente — a tabela canônica define exatamente 4 regimes (MEI · Simples Nacional ·
  Lucro Presumido · Lucro Real) + charter §UX Anti-patterns *"❌ Aceitar regime ∉ {mei, simples,
  lucro_presumido, lucro_real}"*.
- **Regressão que defende:** o enum vive em **três** lugares que precisam concordar — a coluna
  `nfe_business_configs.regime`, o `Rule::in([...])` do FormRequest e o `REGIMES` do `.tsx`. Sem teste,
  acrescentar um regime no front sem acrescentar no back (ou o inverso) só aparece em produção.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFCD-04 · Alíquota é decimal ∈ [0,1] — "18" (por "18%") é rejeitado · `must` `[fiscal]`

- **Persona:** Larissa/Contador preenchendo ICMS. A tela pede **decimal** (`0.18` = 18%), mas o hábito
  humano é digitar `18`. Sem guarda, `18` vira alíquota de **1800%**.
- **Aceite:** Dado `aliquota_icms=18` · Quando faço `upsert` · Então **rejeitado com erro de validação
  em `aliquota_icms`** e nenhuma config gravada. Dado `aliquota_icms=0.18` · Então grava e persiste
  exatamente `0.18`.
- **Teste:** [`ConfigDefaultContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/ConfigDefaultContratoTest.php)
  — `UC-NFCD-04 · alíquota fora de [0,1] é rejeitada e o decimal válido persiste`.
- **Contrato:** charter §Automation Hooks — *"Validation: ICMS/PIS/COFINS/IPI ∈ [0, 1] (alíquotas
  decimais — 0.18 = 18%)"* + charter §UX Targets *"Decimal: 0.18 = 18%"*.
- **Regressão que defende:** é a única barreira entre um erro de digitação e um valor de imposto
  ×100 na nota. O `max:1` do FormRequest é uma linha — este teste é o que impede que ela seja
  "relaxada" por alguém que interprete o `max` como restritivo demais. Cobre os dois lados (rejeita o
  inválido **e** aceita o válido), pra o teste não passar por não-execução.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFCD-05 · Salvar config não reescreve NFe já emitida · `must` `[T0]` `[fiscal]`

- **Persona:** Contador auditando. A nota autorizada guarda o **snapshot** da tributação aplicada no
  momento da emissão. Mudar o default hoje não pode reescrever o que a SEFAZ já autorizou ontem.
- **Aceite:** Dado uma linha em `nfe_emissoes` do business · Quando faço `upsert` da config com
  alíquotas diferentes · Então a linha de `nfe_emissoes` permanece **inalterada** (mesmos campos,
  mesmo `updated_at`) e **nenhuma** linha é removida.
- **Teste:** [`ConfigDefaultContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/ConfigDefaultContratoTest.php)
  — `UC-NFCD-05 · upsert de config não altera nem apaga nfe_emissoes já gravadas`.
- **Contrato:** charter §Automation Anti-hooks — *"❌ Não dispara re-cálculo retroativo de NFes já
  autorizadas (config muda só futuro — append-only fiscal)"* + *"❌ Não modifica `nfe_emissoes`
  existentes (emissões guardam snapshot da tributação aplicada)"*. Ancora legal: o número de NFe
  autorizada permanece oficialmente usado mesmo após cancelamento (**CONFAZ, Ajuste SINIEF 07/2005,
  Art. 14**) — daí a regra Tier 0 de que `nfe_emissoes` é append-only e `forceDelete()` é proibido
  ([`proibicoes.md`](../../../../../memory/proibicoes.md) §"FSM Pipeline Canônico").
- **Regressão que defende:** hoje o `upsert` só toca `nfe_business_configs`, e é isso que queremos
  travar. O risco realista não é alguém escrever `NfeEmissao::update()` de propósito — é um
  Observer/listener futuro em `NfeBusinessConfig` ("recalcular pendentes") que, sem esta guarda, passa
  no review por parecer inofensivo.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFCD-06 · CSOSN e CST são mutuamente exclusivos · `must` `[fiscal]`

- **Persona:** Gestor no wizard "Aplicar pelo regime". Simples/MEI usa **CSOSN**; Presumido/Real usa
  **CST**. Mandar os dois deixa a nota com dois códigos de situação tributária concorrentes.
- **Aceite:** Dado `csosn='102'` **e** `cst='000'` juntos · Quando faço `upsert` · Então **rejeitado com
  erro de validação em `csosn`** e nenhuma config gravada. Dado **nenhum** dos dois · Então rejeitado
  em `csosn` e `cst` (o par é obrigatório-um). Controle positivo: exatamente um dos dois grava, e o
  outro **não** aparece no JSON persistido.
- **Teste:** [`ConfigDefaultContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/ConfigDefaultContratoTest.php)
  — `UC-NFCD-06 · CSOSN e CST juntos são rejeitados e a ausência dos dois também`.
- **Contrato:** [ARQ-0006](../../../../../memory/requisitos/NfeBrasil/adr/arq/0006-cascade-defaults-ncm-produto.md)
  §Onboarding inteligente — a tabela associa **um** código por regime (MEI/Simples → CSOSN 102;
  Presumido/Real → CST 000), nunca os dois + charter §Goals *"Toggle CSOSN vs CST automático pelo regime"*.
- **Regressão que defende:** a exclusividade mora num `withValidator()` (`$validator->after`), não nas
  `rules()` — é o tipo de bloco que some num refactor de FormRequest sem que nenhuma rule fique
  visivelmente faltando. O `.tsx` limpa o campo oposto no submit, mas isso é conveniência de UI: o
  contrato tem que valer para qualquer cliente do endpoint.
- **Status: 🧪** — ver §Recibo.

---

## Backlog — sem UC até [W] decidir

> Prosa honesta, sem gate. Vira UC quando ganhar teste que o cite (G-2).

- ~~`[BACKLOG]` **Confirmação antes de salvar mudança de regime.**~~ — **RESOLVIDO em 2026-07-28: o Non-Goal SAIU do charter (v3), por decisão [W].**
  Motivo: ele **contradizia** o §UX Anti-patterns do próprio charter (*"❌ Modal pra confirmar save… modal só pra destrutivo"*).
  Os dois itens nasceram na mesma passada de agente em 2026-05-16 e **nenhum** foi aprovado por [W] — não era lei, era rascunho.
  O risco que o item imaginava já está coberto por [`UC-NFCD-05`](#uc-nfcd-05--salvar-config-não-reescreve-nfe-já-emitida--must-t0-fiscal):
  salvar **não** reescreve NFe já emitida; o efeito é só sobre emissões futuras, corrigível voltando na tela.
  **Não virou UC** — não há comportamento novo a defender. Se [W] quiser rede de segurança no futuro, o caminho coerente com
  o charter é **aviso inline** ao trocar o regime (não modal), e aí sim nasce UC com teste.

---

## Recibo de execução

| Quando | Onde | Resultado |
|---|---|---|
| _pendente_ | lane `PHP / Pest (NfeBrasil · MySQL)` | a preencher com o run id da corrida |

> Os `Status: 🧪` acima significam **"o teste cita o UC e passou na corrida registrada aqui"**. Só sobem
> pra ✅ quando o manifesto do G-7 (`scripts/casos-test-results.json`) for regravado a partir do JUnit —
> ✅ é afirmação que exige prova, 🧪 é honestidade sobre o que foi de fato observado.
