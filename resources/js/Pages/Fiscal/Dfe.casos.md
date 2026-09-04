---
id: resources-js-pages-fiscal-dfe-casos
casos: Manifesto DF-e · /fiscal/dfe
irmaos: Dfe.charter.md (lei) · memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-09-04"
last_run_ci: "1 UC executado (UC-FDFE-06, vitest/jsdom: 4 casos verdes + 3 mutações provando que morde). Os UC-FDFE-01..05 NÃO foram re-executados nesta corrida — são Pest e Pest não roda local (ADR 0062); o veredito deles segue pendente da lane Pest Fiscal (advisory) + suíte noturna CT 100, como já estava. O bump do last_run é pelo G-6: tocar este casos.md acordou o frescor (o Dfe.tsx é de 2026-09-02, mais novo que o last_run anterior de 09-01), e a data é honesta sobre O QUE foi revalidado — não uma alegação de que os 5 outros rodaram."
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

| `fiscal-debitos-conhecidos.test.tsx` (novo) | `Fiscal Débitos Gate` (vitest/jsdom) | ❌ **não** — advisory |

> Nenhum teste desta tela está na lane **required** (`PHP / Pest (NfeBrasil · MySQL)`). O ratchet-up é proposta ao [W] (SDD §8.3).

## Rastreabilidade

| UC | O que defende | Prio | CU (SDD §6) | Teste que o cita | Status |
|---|---|---|---|---|---|
| UC-FDFE-01 | isolamento da listagem | `[must]` `[T0]` | CU-FISC-12 | `DfeControllerTest` | 🧪 |
| UC-FDFE-02 | o que conta como pendente | `[must]` | CU-FISC-07 | `DfeControllerTest` | 🧪 |
| UC-FDFE-03 | só as 4 ações SEFAZ | `[must]` | CU-FISC-07 | `AcoesControllerTest` | 🧪 |
| UC-FDFE-04 | quando a justificativa é exigida | `[must]` | CU-FISC-07 | `AcoesControllerTest` | 🧪 |
| UC-FDFE-05 | gate de permissão da tela | `[must]` `[T0]` | CU-FISC-13 | `GatesPermissaoFiscalTest` | 🧪 |
| UC-FDFE-06 | a tela avisa que depende de decisão [W] | `[should]` | **—** (ver nota) | `fiscal-debitos-conhecidos.test.tsx` | 🧪 |

> **Por que o `UC-FDFE-06` tem `—` na coluna CU:** os CU do SDD §6 descrevem o que a tela **faz
> pelo usuário fiscal** (listar DF-e, manifestar, filtrar). Este caso é de **transparência sobre a
> própria tela** — meta-comportamento que nenhum CU cobre, porque não existia quando o SDD foi
> escrito. Inventar um CU plausível aqui seria fabricar âncora, que é o oposto do que este bloco faz.

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

## UC-FDFE-06 — A tela avisa que depende de decisão [W] `[should]`

**Dado** que esta tela tem, em `## Backlog` abaixo, item cujo marcador diz `decisão [W]`
**Quando** a contadora abre `/fiscal/dfe`
**Então** ela lê "Decisão [W] pendente" com esse item — em vez de tomar o estado provisório
(a aba Histórico servida por dado de demonstração) pelo definitivo.

- **O que defende:** que o aviso saia da **mesma fonte** do bloco de dívida — os bullets
  `[BACKLOG]` cujo marcador contém literalmente `decisão [W]`, marcados `decisao: true` por
  [`fiscal-debitos-derive.mjs`](../../../../scripts/governance/fiscal-debitos-derive.mjs).
  Nada é escrito à mão. São **4** hoje, e três são exatamente as três instâncias que o
  protótipo desenha (DF-e Histórico · Config Séries · Config Ambiente); a quarta (as 4
  superfícies de demonstração do Cockpit) o protótipo ainda não pintou — a derivação a achou.
- **Um item, um bloco:** o `DebitosConhecidos` filtra `!decisao`, então nenhum item aparece nos
  dois. O caso do `Config` (5 débitos, 2 decisões → 3 + 2, interseção vazia, soma = 5) é o que
  guarda isso.
- **Estado vazio:** nó **ausente**. Tela sem decisão em aberto não desenha o lugar onde decisões
  apareceriam — isso leria como "nada pendente por ora", que é afirmação, não ausência.
- **Delta consciente vs o protótipo:** ali o bloco vive **dentro da aba** a que se refere; aqui
  resolve **por tela**, porque os `.casos.md` não carregam o vínculo com a aba e derivá-lo do
  texto seria adivinhação. E a copy perde o "no vivo" do protótipo — em produção esse dêitico
  não tem referente, o leitor já está no vivo.
- **Mordida provada** (3 mutações, restore byte-idêntico → working tree limpo): remover o render
  do `FxShell` → **3 vermelhos**; tirar o `!decisao` do bloco de dívida → item duplicado, **2
  vermelhos** (cai o caso da interseção vazia e o da soma dos dois blocos); trocar o nó ausente
  por contêiner vazio → **1 vermelho**.
- **Teste:** `tests/js/fiscal-debitos-conhecidos.test.tsx` — 4 casos citando `UC-FDFE-06`.
- **Status:** 🧪 lane **advisory**; veredito pendente.

---

## Backlog de casos (sem id — viram UC quando ganharem contrato + teste)

- **[BACKLOG · ⬜ sem teste] O prazo aparece com três níveis de urgência, vindo do prazo que a SEFAZ calculou** — Dado uma nota recebida com prazo definido · Quando a contadora lê a linha · Então vê quantos dias restam, sinalizado como crítico, atenção ou tranquilo. _O charter é explícito: a fonte de verdade é o prazo gravado pela SEFAZ, **não** um "90 dias" fixo no código. O cálculo existe; nenhum teste valida os níveis._
- **[BACKLOG · ⬜ sem teste] Os chips filtram por estado de manifestação** — pendentes (pendente + ciência), confirmadas, desconhecidas, não realizadas, todas. _Existe no Controller; sem teste do resultado._
- **[BACKLOG · ⬜ sem teste] A busca aceita chave, CNPJ do emitente e nome do emitente** — inclusive digitando o CNPJ com pontuação. _Sem teste do resultado._
- **[BACKLOG · ⬜ sem teste · decisão [W]] A aba Histórico mostra manifestações reais** — hoje ela é servida por **dado de demonstração** com ator e observação inventados (`CU-FISC-16` do SDD §6.5 · §5.4.1). A consulta real está declarada como pendência no próprio código. **Precisa de decisão [W]** sobre marcar procedência, esconder atrás de flag ou declarar Non-Goal.

## Como rodar a suíte

1. **Advisory:** `Pest Fiscal` (matrix `modules-pest.yml`) roda `Modules/Fiscal/Tests` em SQLite — os testes que exigem schema MySQL **pulam**.
2. **Noturna CT 100:** `phpunit.xml` inclui `./Modules/Fiscal/Tests/Feature`; é onde eles realmente correm contra MySQL.
3. ⛔ **Nunca local** ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

## Trilha do tempo

- 2026-09-04 · [C] Onda 5 Cowork (decisão pendente): **+1 UC** (`UC-FDFE-06`). O bloco é montado
  uma vez no `FxShell` e resolvido por `route` — as sete telas o herdam, e esta é a dona do
  contrato por ser o exemplo literal do protótipo. **Nenhum bullet de `## Backlog` virou UC**:
  eles seguem sendo a FONTE, e promovê-los mudaria o denominador que o gerador lê.

- 2026-07-15 · [CC] stub criado no Passo 3 do programa de ondas — **0 UC**.
- 2026-07-27 · [CC] `sdd-from-source` (Onda 1 / S2): **5 UC** derivados do §6 do SDD; 4 herdam testes existentes, 1 nasce com teste novo. Nota de escopo mantida: os testes de ação provam **contrato de entrada** (whitelist, regra de justificativa), não a persistência ponta-a-ponta.
