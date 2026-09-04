---
id: resources-js-pages-fiscal-dfe-casos
casos: Manifesto DF-e · /fiscal/dfe
irmaos: Dfe.charter.md (lei) · memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-09-04"
last_run_ci: "2 UC executados e VERDES no CT 100 (MySQL) — UC-FDFE-06/07, 3 passed / 9 assertions, com bite-test (mutar 1 das 4 chamadas → 1 failed). Os UC-01..05 seguem 🧪, veredito pendente da lane Pest Fiscal + suíte noturna."
related_us: [US-FISCAL-008, US-FISCAL-012]
---

# Casos de Uso & Aceite — Manifesto DF-e

> **Revalidação `last_run` 2026-09-01 — Onda 1 Fiscal (saneamento `fx-*` → DS):** mudança de
> **apresentação apenas** — `fx-callout` → `<Alert>`, 5 `fx-chip` → `<Button>`, `fx-search` +
> `<input type="search">` → `<Input>`, `fx-filters` → `<Inline>`, 7 `fx-btn` → `<Button>`.
> Os 4 botões de ação da linha perderam o `style` inline (`padding` + `color: var(--bad)` /
> `var(--warn)`) para `size="icon-xs"` + token (`text-destructive-fg` / `text-warning-fg`), e
> **ganharam `aria-label`** — eram ícone puro, nomeados só pelo `title`.
> Conferi os 5 UC um a um: **todos assertam backend** — escopo cross-tenant (T0), a regra de
> "pendente inclui quem só teve ciência", a whitelist das 4 manifestações da SEFAZ, a exigência
> condicional de justificativa e o gate `fiscal.dfe.manage` (T0). **Nenhum toca o `.tsx`.**
> A âncora `data-contract="fiscal-dfe-filters"` sobreviveu à troca do `<div>` pelo `<Inline>`
> (`contrato-de-tela` rc=0, com as 4 copies literais). **Nenhum teste re-executado** (Pest = CT 100).

> **Revalidação `last_run` 2026-08-28 — o que foi conferido:** este PR muda a tela em **um único ponto**: o atributo `data-contract="fiscal-dfe-filters"` no wrapper, âncora do mapa [`fiscal-dfe.map.json`](../../../../memory/requisitos/Fiscal/fiscal-dfe.map.json). Conferi o diff do `.tsx` contra a lista de UC deste arquivo — **nenhum UC depende de atributo de DOM**, logo nenhum aceite mudou. **Nenhum teste foi re-executado** nesta revalidação (Pest = CT 100); os vereditos seguem como estavam.

> Persona: **Eliana [E] (contadora)** — manifesta as NF-e que terceiros emitiram **contra** o CNPJ, dentro do prazo legal.
>
> **Âncora:** `CU-FISC-07`, `CU-FISC-12` e `CU-FISC-13` do
> [SDD §6](../../../../memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md). Os UC derivam do **CU**, nunca do `.tsx`.
>
> **Status:** ✅ provado por teste verde que cita o UC · 🧪 tem teste, **veredito pendente da lane** · ⬜ não verificado · ❌ quebrou.

## Força do veredito

| Teste | Lane | Bloqueia merge? |
|---|---|---|
| `DfeControllerTest` · `AcoesControllerTest` · `GatesPermissaoFiscalTest` | `Pest Fiscal` (SQLite — os que tocam banco **pulam**) + suíte noturna CT 100 (MySQL) | ❌ **não** — `Pest Fiscal` não está no [baseline](../../../../governance/required-checks-baseline.json): reprova visível, **advisory** |

> Nenhum teste desta tela está na lane **required** (`PHP / Pest (NfeBrasil · MySQL)`). O ratchet-up é proposta ao [W] (SDD §8.3).

## Rastreabilidade

| UC | O que defende | Prio | CU (SDD §6) | Teste que o cita | Status |
|---|---|---|---|---|---|
| UC-FDFE-01 | isolamento da listagem | `[must]` `[T0]` | CU-FISC-12 | `DfeControllerTest` | 🧪 |
| UC-FDFE-02 | o que conta como pendente | `[must]` | CU-FISC-07 | `DfeControllerTest` | 🧪 |
| UC-FDFE-03 | só as 4 ações SEFAZ | `[must]` | CU-FISC-07 | `AcoesControllerTest` | 🧪 |
| UC-FDFE-04 | quando a justificativa é exigida | `[must]` | CU-FISC-07 | `AcoesControllerTest` | 🧪 |
| UC-FDFE-05 | gate de permissão da tela | `[must]` `[T0]` | CU-FISC-13 | `GatesPermissaoFiscalTest` | 🧪 |
| UC-FDFE-06 | a manifestação chega ao motor | `[must]` | CU-FISC-07 | `AcoesDfeManifestacaoTest` | 🧪 |
| UC-FDFE-07 | isolamento **da ação**, não só da lista | `[must]` `[T0]` | CU-FISC-12 | `AcoesDfeManifestacaoTest` | 🧪 |

---

## UC-FDFE-01 — A lista nunca mostra nota recebida por outro business `[must]` `[T0]`

**Dado** notas recebidas do business ativo e de outro business
**Quando** a lista de DF-e carrega
**Então** só as do business ativo aparecem.

- **Regressão que defende:** vazamento cross-tenant Tier 0 ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)) — aqui expondo **fornecedor de terceiro**, que é PII de outra empresa.
- **Teste:** `Modules/Fiscal/Tests/Feature/DfeControllerTest.php` — `it('UC-FDFE-01 · NfeDfeRecebido HasBusinessScope esconde cross-tenant da listagem DF-e')`
- **Status:** 🧪 advisory + noturna.

## UC-FDFE-02 — "Pendente de manifestação" inclui a nota que só teve ciência dada `[must]`

**Dado** uma nota recebida
**Quando** o estado dela é *pendente* ou *ciência*
**Então** ela conta como pendente de manifestação; nota já confirmada, não.

- **Por que importa:** dar ciência **não** encerra a obrigação — só suspende o prazo. Tratar ciência como resolvida esconde nota que ainda precisa de confirmação e faz o valor pendente da tela mentir.
- **Teste:** `DfeControllerTest` — `it('UC-FDFE-02 · isPendenteManifestacao retorna true pra status PENDENTE e CIENCIA')` e `it('UC-FDFE-02 · STATUS constants estão definidas — Controller depende delas pra filtros')`
- **Status:** 🧪 advisory + noturna.

## UC-FDFE-03 — Só existem quatro manifestações, e elas são as da SEFAZ `[must]`

**Dado** uma nota recebida
**Quando** a contadora escolhe o que fazer
**Então** as únicas opções aceitas são dar ciência, confirmar a operação, desconhecer a operação e declarar que a operação não foi realizada. Qualquer outro verbo é recusado.

- **Regressão que defende:** inventar ação intermediária ("aprovar", "arquivar") que a SEFAZ não conhece — o evento sai errado e o prazo continua correndo. A whitelist é dupla: a rota restringe e o Controller re-checa.
- **Teste:** `Modules/Fiscal/Tests/Feature/AcoesContratoTest.php` — `it('UC-FNFE-07 · UC-FDFE-03 · manifestarDfe REJEITA ação fora da whitelist canon SEFAZ')`. _Reapontado em 2026-07-28: o teste anterior vivia no `AcoesControllerTest` e era tautológico — assertava um array literal escrito na própria linha acima, sem tocar o Controller. O atual invoca `AcoesController::manifestarDfe` e verifica o 404._
- **Status:** 🧪 advisory + noturna.

## UC-FDFE-04 — Desconhecer e "não realizada" exigem justificativa; ciência e confirmação não `[must]`

**Dado** a ação escolhida
**Quando** é desconhecer ou declarar operação não realizada
**Então** a justificativa é obrigatória (mínimo 15 caracteres); nas outras duas, não é pedida.

- **Por que importa:** as duas ações que **negam** a operação são as que geram disputa com o fornecedor — a justificativa é a defesa documental do business.
- **Teste:** `Modules/Fiscal/Tests/Feature/AcoesContratoTest.php` — `it('UC-FNFE-07 · UC-FDFE-04 · manifestarDfe EXIGE justificativa em desconhecer/nao_realizada')` + `it('UC-FNFE-07 · UC-FDFE-04 · manifestarDfe NÃO exige justificativa em cienciar/confirmar')`. _Reapontado em 2026-07-28: o anterior assertava `in_array($acao, ['desconhecer','nao_realizada'])` sobre arrays locais — nunca chamava o Controller. O atual dispara `ValidationException` no campo `justificativa` de verdade._
- **Status:** 🧪 advisory + noturna.

## UC-FDFE-05 — A tela exige `fiscal.dfe.manage` `[must]` `[T0]`

**Dado** um usuário sem `fiscal.dfe.manage` e sem `superadmin`
**Quando** abre `/fiscal/dfe`
**Então** recebe 403.

- **Âncora de contrato:** `R-FISCAL-003` do [SPEC.md](../../../../memory/requisitos/Fiscal/SPEC.md) §3 + guard em `DfeController@index`.
- **Regressão que defende:** a tela expõe razão social e CNPJ de **fornecedores** — quem não gerencia DF-e não precisa dessa lista.
- **Teste:** `Modules/Fiscal/Tests/Feature/GatesPermissaoFiscalTest.php` — `it('UC-FDFE-05 · GET /fiscal/dfe aborta 403 sem fiscal.dfe.manage nem superadmin')`
- **Status:** 🧪 teste nasce nesta corrida; veredito pendente.

---

## UC-FDFE-06 — Clicar em manifestar faz a manifestação chegar ao motor `[must]`

**Dado** uma DF-e pendente do próprio business
**Quando** a contadora aciona uma das 4 manifestações
**Então** o motor de manifestação recebe **aquela nota** e **aquela ação** — não um identificador solto —
**e** a justificativa, quando a ação exige uma, chega junto.

- **Âncora de contrato:** `CU-FISC-07` do [SDD §6](../../../../memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md) — a manifestação é o ato que o CU descreve; um clique que não alcança o motor não cumpre o CU.
- **Regressão que defende:** entre a Wave 4 e 2026-09-04, as 4 ações da tela **não manifestavam nada**. O adaptador chamava `ManifestacaoService::cienciar($businessId, $recebido)` — dois `int` — num método cuja assinatura é `cienciar(NfeDfeRecebido $dfe)`. O `TypeError` caía no `catch (\Throwable)` do próprio método e virava a flash `"Manifestação falhou: …"`. Os UC-FDFE-03/04 seguiam verdes porque medem a camada de cima (whitelist e validação) e um `TypeError` engolido também não é `ValidationException`.
- **Por que o aceite para na fronteira do motor:** o efeito ponta-a-ponta (`status_manifestacao` virando `ciencia`) **não** é asserível hoje — os dois `buildConfig` do `NfeBrasil` leem `business.state`, coluna que não existe no schema canônico, no staging nem em produção (133 colunas nos três, medido 2026-09-04). Ver o backlog abaixo.
- **Teste:** `Modules/Fiscal/Tests/Feature/AcoesDfeManifestacaoTest.php` — `it('UC-FDFE-06 · manifestarDfe ENTREGA a DF-e carregada ao service (nao um id solto)')` e `it('UC-FDFE-06 · manifestarDfe repassa a justificativa de desconhecer ao service')`
- **Status:** 🧪 rodado VERDE no CT 100 (MySQL) em 2026-09-04 — `3 passed (9 assertions)`, com bite-test (repor **uma** das 4 chamadas antigas → `1 failed`). Fica 🧪 e não ✅ porque o G-7 lê o **manifesto commitado**, e o CT 100 não o alimenta: o ✅ chega quando a lane publicar (`casos-results-publish`).

---

## UC-FDFE-07 — Manifestar DF-e de outro business é 404, não erro genérico `[must]` `[T0]`

**Dado** uma DF-e que pertence a outro business
**Quando** alguém aciona a manifestação dela pelo endpoint
**Então** recebe 404, o motor **não é sequer invocado**, e o registro alheio fica intacto.

- **Âncora de contrato:** `CU-FISC-12` do SDD §6 + [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** o UC-FDFE-01 isola a **listagem**; nada isolava a **ação**. O adaptador nunca carregava o registro — passava o id adiante —, então não havia ponto onde o `business_id` fosse conferido. O isolamento dependia inteiramente do motor, que recebia o id por um caminho que nem chegava a executar.
- **Teste:** `Modules/Fiscal/Tests/Feature/AcoesDfeManifestacaoTest.php` — `it('UC-FDFE-07 · manifestarDfe NAO alcanca DF-e de outro business (Tier 0 · ADR 0093)')`
- **Status:** 🧪 rodado VERDE no CT 100 (MySQL) em 2026-09-04 — tenant fictício 98 × 99 ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)), nunca `biz=4`. 🧪 e não ✅ pelo mesmo motivo do UC-06: a prova do G-7 é o manifesto, não a prosa.

---

## Backlog de casos (sem id — viram UC quando ganharem contrato + teste)

- **[BACKLOG · ❌ quebrado · decisão [W]] Nenhuma manifestação chega à SEFAZ, por nenhum caminho** — `ManifestacaoService::buildConfig()` e `DistribuicaoDfeService::buildConfig()` fazem `select(['name','tax_number_1','state'])` em `business`. A coluna `state` **não existe**: 133 colunas no schema canônico (`database/schema/mysql-schema.sql`), 133 no staging CT 100 e 133 em produção Hostinger, sem `state` nos três — e nenhuma das 43 migrations que tocam `business` a cria. Toda manifestação morre em `SQLSTATE[42S22] Unknown column 'state'`, incluindo o `ManifestacaoController::bulkConfirmar` do próprio NfeBrasil. Os dois sites já carregam `?? 'SP'`, e o docblock diz *"UF default 35 (SP) — manifestação é nacional, mas Tools exige cUF"* — o default parece ter sido sempre a intenção, e a coluna no `select` o acidente. **É motor fiscal: conserto é decisão [W]**, e são 2 sites (a lápide §5 2026-08-02 é explícita sobre corrigir só um de N).

- **[BACKLOG · ⬜ sem teste] O prazo aparece com três níveis de urgência, vindo do prazo que a SEFAZ calculou** — Dado uma nota recebida com prazo definido · Quando a contadora lê a linha · Então vê quantos dias restam, sinalizado como crítico, atenção ou tranquilo. _O charter é explícito: a fonte de verdade é o prazo gravado pela SEFAZ, **não** um "90 dias" fixo no código. O cálculo existe; nenhum teste valida os níveis._
- **[BACKLOG · ⬜ sem teste] Os chips filtram por estado de manifestação** — pendentes (pendente + ciência), confirmadas, desconhecidas, não realizadas, todas. _Existe no Controller; sem teste do resultado._
- **[BACKLOG · ⬜ sem teste] A busca aceita chave, CNPJ do emitente e nome do emitente** — inclusive digitando o CNPJ com pontuação. _Sem teste do resultado._
- **[BACKLOG · ⬜ sem teste · decisão [W]] A aba Histórico mostra manifestações reais** — hoje ela é servida por **dado de demonstração** com ator e observação inventados (`CU-FISC-16` do SDD §6.5 · §5.4.1). A consulta real está declarada como pendência no próprio código. **Precisa de decisão [W]** sobre marcar procedência, esconder atrás de flag ou declarar Non-Goal.

## Como rodar a suíte

1. **Advisory:** `Pest Fiscal` (matrix `modules-pest.yml`) roda `Modules/Fiscal/Tests` em SQLite — os testes que exigem schema MySQL **pulam**.
2. **Noturna CT 100:** `phpunit.xml` inclui `./Modules/Fiscal/Tests/Feature`; é onde eles realmente correm contra MySQL.
3. ⛔ **Nunca local** ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

## Trilha do tempo

- 2026-07-15 · [CC] stub criado no Passo 3 do programa de ondas — **0 UC**.
- 2026-07-27 · [CC] `sdd-from-source` (Onda 1 / S2): **5 UC** derivados do §6 do SDD; 4 herdam testes existentes, 1 nasce com teste novo. Nota de escopo mantida: os testes de ação provam **contrato de entrada** (whitelist, regra de justificativa), não a persistência ponta-a-ponta.
- 2026-09-04 · [C] **+2 UC (06/07), os primeiros verdes desta tela.** A "nota de escopo" de 2026-07-27 delimitava exatamente o buraco por onde passou um defeito de produção: as 4 ações não manifestavam nada (`TypeError` engolido pelo `catch`). Fechado no adaptador, com bite-test. Descoberto no caminho, e **não** consertado por ser motor fiscal: `business.state` não existe em lugar nenhum — está no backlog acima como decisão [W].
