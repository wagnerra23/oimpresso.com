---
id: resources-js-pages-fiscal-dfe-casos
casos: Manifesto DF-e · /fiscal/dfe
irmaos: Dfe.charter.md (lei) · memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-27"
last_run_ci: "0 UC executado nesta corrida — 4 UC herdam testes que JÁ existem e 1 nasce com teste novo; veredito pendente da lane Pest Fiscal + suíte noturna CT 100"
related_us: [US-FISCAL-008, US-FISCAL-012]
---

# Casos de Uso & Aceite — Manifesto DF-e

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
- **Teste:** `Modules/Fiscal/Tests/Feature/AcoesControllerTest.php` — `it('UC-FDFE-03 · manifestarDfe whitelist exatamente 4 ações canon SEFAZ')`
- **Status:** 🧪 advisory + noturna.

## UC-FDFE-04 — Desconhecer e "não realizada" exigem justificativa; ciência e confirmação não `[must]`

**Dado** a ação escolhida
**Quando** é desconhecer ou declarar operação não realizada
**Então** a justificativa é obrigatória (mínimo 15 caracteres); nas outras duas, não é pedida.

- **Por que importa:** as duas ações que **negam** a operação são as que geram disputa com o fornecedor — a justificativa é a defesa documental do business.
- **Teste:** `AcoesControllerTest` — `it('UC-FDFE-04 · manifestarDfe desconhecer/nao_realizada exigem justificativa, cienciar/confirmar não')`
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

- 2026-07-15 · [CC] stub criado no Passo 3 do programa de ondas — **0 UC**.
- 2026-07-27 · [CC] `sdd-from-source` (Onda 1 / S2): **5 UC** derivados do §6 do SDD; 4 herdam testes existentes, 1 nasce com teste novo. Nota de escopo mantida: os testes de ação provam **contrato de entrada** (whitelist, regra de justificativa), não a persistência ponta-a-ponta.
