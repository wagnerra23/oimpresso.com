---
id: resources-js-pages-fiscal-nfe-casos
casos: Notas NF-e / NFC-e · /fiscal/nfe
irmaos: Nfe.charter.md (lei) · memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-27"
last_run_ci: "0 UC executado nesta corrida — os UC herdam testes que JÁ existem; veredito pendente das lanes PHP / Pest (NfeBrasil · MySQL) e Pest Fiscal"
related_us: [US-FISCAL-001, US-FISCAL-012, US-FISCAL-013, US-FISCAL-014]
---

# Casos de Uso & Aceite — Notas NF-e / NFC-e

> Persona: **Eliana [E] (contadora)** — conferência fiscal · **Wagner [W]** — ação (cancelar/CC-e/inutilizar/retransmitir).
>
> **Âncora:** `CU-FISC-02`, `CU-FISC-03`, `CU-FISC-08`, `CU-FISC-09`, `CU-FISC-10`, `CU-FISC-11`,
> `CU-FISC-12` e `CU-FISC-13` do [SDD §6](../../../../memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md).
> Os UC abaixo **derivam do SDD/CU**, nunca do `.tsx` (senão viram tautologia — [proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05).
>
> **Status:** ✅ provado por teste verde que cita o UC · 🧪 tem teste, **veredito pendente da lane** ·
> ⬜ não verificado · ❌ quebrou. **Nenhum UC nasce ✅** — quem dá veredito é a lane, não a leitura (G-7 · [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Força do veredito — qual lane, e se ela **bloqueia merge**

| Teste | Lane | Bloqueia merge? |
|---|---|---|
| `NfeCockpitMultiTenantTest` | `PHP / Pest (NfeBrasil · MySQL)` | ✅ **sim** — está no [`required-checks-baseline.json`](../../../../governance/required-checks-baseline.json) **e** na allowlist do workflow |
| `AcoesControllerTest` | `Pest Fiscal` (modules-pest, SQLite) + suíte noturna CT 100 (MySQL) | ❌ **não** — `Pest Fiscal` não está no baseline: reprova visível, **advisory** |
| `GatesPermissaoFiscalTest` (novo) | idem acima; **SKIPa em SQLite** (schema exige MySQL) → roda de fato só no CT 100 | ❌ **não** — ratchet-up para a lane required é proposta ao [W] (SDD §8.3) |

## Rastreabilidade

| UC | O que defende | Prio | CU (SDD §6) | Teste que o cita | Status |
|---|---|---|---|---|---|
| UC-FNFE-01 | isolamento da contagem | `[must]` `[T0]` | CU-FISC-12 | `NfeCockpitMultiTenantTest` | 🧪 |
| UC-FNFE-02 | janela legal 24h/168h | `[must]` `[reg]` | CU-FISC-03 | `NfeCockpitMultiTenantTest` | 🧪 |
| UC-FNFE-03 | tradução do código SEFAZ | `[must]` | CU-FISC-02 | `NfeCockpitMultiTenantTest` | 🧪 |
| UC-FNFE-04 | motivo de cancelamento | `[must]` | CU-FISC-08 | `AcoesControllerTest` | 🧪 |
| UC-FNFE-05 | limites da CC-e | `[must]` | CU-FISC-09 | `AcoesControllerTest` | 🧪 |
| UC-FNFE-06 | faixa de inutilização | `[must]` | CU-FISC-10 | `AcoesControllerTest` | 🧪 |
| UC-FNFE-07 | retransmitir sem apagar | `[must]` `[reg]` | CU-FISC-11 | `AcoesControllerTest` | 🧪 |
| UC-FNFE-08 | gate de permissão da tela | `[must]` `[T0]` | CU-FISC-13 | `GatesPermissaoFiscalTest` | 🧪 |

---

## UC-FNFE-01 — A contagem do cockpit nunca conta nota de outro business `[must]` `[T0]`

**Persona:** Eliana [E]
**Dado** que existem notas do business dela e notas de outro business com a mesma marca de teste
**Quando** a contagem do cockpit roda na sessão dela
**Então** só as notas do business dela entram na conta — e uma leitura sem o escopo global enxerga todas, provando que o filtro é o responsável.

- **Regressão que defende:** vazamento cross-tenant Tier 0 ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)). O guard já falhou uma vez por motivo de *teste* (sessão sem `actingAs` fazia o escopo no-opar e contar 3 em vez de 1) — o teste hoje autentica de verdade.
- **Teste:** `Modules/Fiscal/Tests/Feature/NfeCockpitMultiTenantTest.php` — `it('UC-FNFE-01 · global scope HasBusinessScope esconde emissões cross-tenant na contagem do cockpit')`
- **Status:** 🧪 tem teste na lane **required** `PHP / Pest (NfeBrasil · MySQL)`; veredito da lane, não desta leitura.

## UC-FNFE-02 — A janela legal de cancelamento respeita 24h (NFC-e) e 168h (NF-e) `[must]` `[reg]`

**Persona:** Wagner [W]
**Dado** uma nota autorizada
**Quando** o cockpit decide se ela ainda pode ser cancelada
**Então** NFC-e (modelo 65) só é cancelável até 24h e NF-e (modelo 55) até 168h da emissão; fora disso, não.

- **Âncora legal:** CONFAZ Ajuste SINIEF 07/2005 Art. 14 — é lei, não preferência de UI. `R-FISCAL-002` do [SPEC.md](../../../../memory/requisitos/Fiscal/SPEC.md).
- **Regressão que defende:** oferecer cancelamento fora do prazo (o operador tenta, a SEFAZ recusa) ou esconder cancelamento ainda válido (perde a janela).
- **Teste:** `NfeCockpitMultiTenantTest` — `it('UC-FNFE-02 · isCancelavel respeita janela legal 24h NFC-e (modelo 65) vs 168h NF-e (modelo 55)')`
- **Status:** 🧪 lane **required**; veredito pendente.

## UC-FNFE-03 — O código SEFAZ chega traduzido em tom e rótulo `[must]`

**Persona:** Eliana [E]
**Dado** uma nota com `cstat` de autorização, rejeição ou processamento
**Quando** a linha é renderizada
**Então** o código vira tom + rótulo + dica legíveis — autorização em tom de sucesso, duplicidade em tom de erro, divergência de NCM em tom de atenção.

- **Regressão que defende:** contadora lendo número cru de SEFAZ e não sabendo se precisa agir.
- **Teste:** `NfeCockpitMultiTenantTest` — `it('UC-FNFE-03 · sefazCodes retorna mapa com pelo menos 100, 110, 220, 539, 691, 778, 999')`
- **Status:** 🧪 lane **required**; veredito pendente.

## UC-FNFE-04 — Cancelar exige justificativa de no mínimo 15 caracteres `[must]`

**Persona:** Wagner [W]
**Dado** um pedido de cancelamento de NF-e
**Quando** o motivo tem menos de 15 caracteres
**Então** a ação é recusada; com 15 ou mais, é aceita e segue para o Service do NfeBrasil.

- **Âncora legal:** CONFAZ SINIEF 07/2005 Art. 14 (justificativa mínima).
- **Regressão que defende:** cancelamento sem motivo auditável — o evento 110111 vai para a SEFAZ com a justificativa dentro.
- **Teste:** `Modules/Fiscal/Tests/Feature/AcoesControllerTest.php` — `it('UC-FNFE-04 · cancelarNfe rejeita motivo < 15 chars (regra CONFAZ SINIEF 07/2005)')` e `it('UC-FNFE-04 · cancelarNfe aceita motivo válido ≥15 chars')`
- **Status:** 🧪 lane **advisory** (`Pest Fiscal`) + suíte noturna CT 100.

## UC-FNFE-05 — A Carta de Correção respeita o texto de 15–1000 e a sequência de 1–20 `[must]`

**Persona:** Wagner [W]
**Dado** uma NF-e autorizada
**Quando** aplica CC-e com texto fora de 15–1000 caracteres, ou com número de sequência fora de 1–20
**Então** a ação é recusada; dentro dos limites, é aceita.

- **Âncora legal:** CONFAZ Art. 14 + limite SEFAZ de 20 CC-e por NF-e.
- **Regressão que defende:** CC-e rejeitada pela SEFAZ por payload fora de norma (gasta a sequência sem corrigir nada).
- **Teste:** `AcoesControllerTest` — `it('UC-FNFE-05 · cartaCorrecao rejeita texto correção <15 chars (CONFAZ Art. 14)')`, `it('UC-FNFE-05 · cartaCorrecao rejeita texto correção >1000 chars (limite SEFAZ)')`, `it('UC-FNFE-05 · cartaCorrecao rejeita n_seq_evento fora de 1-20 (CONFAZ Art. 14)')`, `it('UC-FNFE-05 · cartaCorrecao aceita texto válido (15-1000) + seq 1-20')`
- **Status:** 🧪 advisory + noturna.

## UC-FNFE-06 — Inutilizar faixa valida modelo, coerência da faixa e justificativa `[must]`

**Persona:** Wagner [W]
**Dado** um buraco no sequencial fiscal
**Quando** a faixa é enviada com modelo fora de {55, 65}, com fim menor que início, ou com justificativa curta demais
**Então** a ação é recusada por campo; com payload coerente, é aceita.

- **Regressão que defende:** buraco de numeração não fechado vira multa anual; faixa invertida inutilizaria o intervalo errado.
- **Teste:** `AcoesControllerTest` — `it('UC-FNFE-06 · inutilizar valida modelo (whitelist 55/65)')`, `it('UC-FNFE-06 · inutilizar rejeita faixa inválida (numero_ate < numero_de)')`, `it('UC-FNFE-06 · inutilizar rejeita justificativa <15 chars (regra SEFAZ)')`, `it('UC-FNFE-06 · inutilizar aceita payload válido (modelo 55/65, faixa 1..N, just 15-255)')`
- **Status:** 🧪 advisory + noturna.

## UC-FNFE-07 — Retransmitir só vale para nota em erro, e nunca apaga a antiga `[must]` `[reg]`

**Persona:** Wagner [W]
**Dado** uma NF-e rejeitada, denegada ou com erro de envio
**Quando** o operador retransmite
**Então** a operação é permitida **apenas** nesses três estados, e a emissão antiga é **preservada** (marcada como inutilizada, com o vínculo de venda liberado) enquanto uma nova nasce com número novo.

- **Âncora legal:** CONFAZ SINIEF 07/2005 Art. 14 — documento fiscal é imutável; `forceDelete()` é proibido.
- **Regressão que defende:** apagar registro fiscal para "limpar" o erro. Nota autorizada nunca entra aqui (corrige-se por CC-e); cancelada também não (o número já foi usado oficialmente).
- **Teste:** `AcoesControllerTest` — `it('UC-FNFE-07 · retransmitir contrato: status válidos = rejeitada/denegada/erro_envio')`, `it('UC-FNFE-07 · retransmitir contrato: NfeService::retransmitir signature int/int → NfeEmissao')`, `it('UC-FNFE-07 · retransmitir route POST registrada (acoes.nfe.retransmitir)')`
- **Status:** 🧪 advisory + noturna.
- ⚠️ **Limite honesto:** os três testes provam **contrato** (estados aceitos, assinatura, rota registrada), não a persistência ponta-a-ponta da preservação. O `[reg]` "nunca `forceDelete()`" ainda **não** tem teste de comportamento — ver `[BACKLOG]` abaixo.

## UC-FNFE-08 — A tela exige `fiscal.nfe.view` `[must]` `[T0]`

**Persona:** qualquer usuário autenticado sem a permissão
**Dado** um usuário sem `fiscal.nfe.view` e sem `superadmin`
**Quando** ele abre `/fiscal/nfe`
**Então** recebe 403 — nenhuma nota vaza para quem não tem o gate da sub-feature.

- **Âncora de contrato:** `R-FISCAL-003` do [SPEC.md](../../../../memory/requisitos/Fiscal/SPEC.md) §3 (Gherkin) + o guard declarado em `NfeCockpitController@index`.
- **Regressão que defende:** promover a permissão genérica `fiscal.access` a "vê tudo do fiscal" — o desenho é gate **por sub-feature**.
- **Teste:** `Modules/Fiscal/Tests/Feature/GatesPermissaoFiscalTest.php` — `it('UC-FNFE-08 · GET /fiscal/nfe aborta 403 sem fiscal.nfe.view nem superadmin')`
- **Status:** 🧪 teste nasce nesta corrida; **veredito pendente** (SKIPa em SQLite; roda no CT 100).

---

## Backlog de casos (sem id — viram UC quando ganharem contrato + teste)

- **[BACKLOG · ⬜ sem teste] A lista deferida filtra por aba, status e busca com 50 por página** — Dado emissões · Quando aplica aba (55/65), chip de status ou busca por número/chave/motivo · Então a lista deferida devolve no máximo 50 linhas por página, da mais recente para a mais antiga. _Existe no Controller; nenhum teste exercita o payload filtrado._
- **[BACKLOG · ⬜ sem teste] Retransmitir preserva de fato a emissão antiga** — Dado uma nota rejeitada · Quando retransmite · Então a emissão antiga continua existindo no banco (marcada como inutilizada, sem vínculo de venda) e a nova nasce com número diferente. _Hoje só há contrato de assinatura/rota; a preservação em si não é exercitada. É o `[reg]` mais caro do módulo._
- **[BACKLOG · ⬜ sem teste] A pílula de prazo e o cálculo do servidor concordam** — Dado uma nota perto do limite da janela · Quando servidor e navegador estão em fusos diferentes · Então os dois mostram a mesma decisão. _Risco R3 do charter: o servidor usa o relógio do app e o front usa o do browser; a correção proposta (mandar o instante do servidor) não foi feita._
- **[BACKLOG · ⬜ sem teste] Destinatário sem nome mostra marcador neutro** — Dado nota antiga sem nome de destinatário no metadado · Quando a linha renderiza · Então aparece um marcador neutro, não vazio nem "null". _Non-Goal declarado do PR #1 (sem JOIN com contatos); fallback existe no código._

## Como rodar a suíte

1. **Pest MySQL (lane required):** `PHP / Pest (NfeBrasil · MySQL)` roda `NfeCockpitMultiTenantTest` a cada PR que toque `Modules/Fiscal/Tests/**` ou `Modules/NfeBrasil/**`.
2. **Pest SQLite (advisory):** `Pest Fiscal` (matrix `modules-pest.yml`) roda `Modules/Fiscal/Tests` inteiro; os testes que exigem schema MySQL **pulam** — "verde" ali não prova comportamento de banco.
3. **Suíte noturna CT 100:** `phpunit.xml` inclui `./Modules/Fiscal/Tests/Feature` e o `shards-plan.mjs` a enumera — é onde os testes MySQL-only realmente correm.
4. ⛔ **Nunca rodar local** ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

## Trilha do tempo

- 2026-07-03 · [CC] stub criado no Passo 3 do programa de ondas — **0 UC**, só backlog com citação de teste.
- 2026-07-27 · [CC] `sdd-from-source` (Onda 1 / S2 do passo 5): **8 UC** derivados do §6 do SDD recém-criado; os 7 primeiros herdam testes que já existiam (o débito era rastreabilidade, não ausência de teste), o 8º nasce com teste novo. Declarada a **força do veredito por lane** e o limite honesto do UC-FNFE-07.
