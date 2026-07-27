---
id: resources-js-pages-fiscal-config-casos
casos: Configuração Fiscal · /fiscal/config
irmaos: Config.charter.md (lei) · memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-27"
last_run_ci: "0 UC executado nesta corrida — 2 UC herdam testes que JÁ existem e 1 nasce com teste novo; veredito pendente da lane Pest Fiscal + suíte noturna CT 100"
related_us: [US-FISCAL-009]
---

# Casos de Uso & Aceite — Configuração Fiscal

> Persona: **Wagner [W]** (admin) — confere certificado, regime, série e ambiente sem abrir o módulo NfeBrasil.
>
> **Âncora:** `CU-FISC-06`, `CU-FISC-12`, `CU-FISC-13` e `CU-FISC-14` do
> [SDD §6](../../../../memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md). Os UC derivam do **CU**, nunca do `.tsx`.
>
> **Status:** ✅ provado por teste verde que cita o UC · 🧪 tem teste, **veredito pendente da lane** · ⬜ não verificado · ❌ quebrou.

> ⚠️ **Divergência aberta entre charter e código — decisão [W] (SDD §5.4.3).** O charter declara
> `❌ Edição inline (upload novo cert, mudar regime, editar tributação)` e o anti-hook
> `🚫 esta tela é read-only por design`. Medido em `Config.tsx`: existem **dois formulários de
> mutação** — envio de certificado e troca de ambiente SEFAZ. A **letra** do anti-hook está honrada
> (nenhum controlador de escrita nasceu no Fiscal; os formulários postam para os endpoints do
> NfeBrasil), mas o Non-Goal, não. **Non-Goal é intenção e só [W] altera** — o agente não tocou no
> charter. Nenhum UC abaixo cobre o caminho de mutação, justamente porque o contrato dele está em disputa.

## Força do veredito

| Teste | Lane | Bloqueia merge? |
|---|---|---|
| `ConfigControllerTest` · `GatesPermissaoFiscalTest` | `Pest Fiscal` (**pulam** em SQLite) + suíte noturna CT 100 | ❌ **não** — advisory |

> Nenhum teste desta tela está na lane **required**. O ratchet-up é proposta ao [W] (SDD §8.3).

## Rastreabilidade

| UC | O que defende | Prio | CU (SDD §6) | Teste que o cita | Status |
|---|---|---|---|---|---|
| UC-FCFG-01 | segredo do certificado não viaja | `[must]` `[T0]` | CU-FISC-14 | `ConfigControllerTest` | 🧪 |
| UC-FCFG-02 | certificado de outro business não aparece | `[must]` `[T0]` | CU-FISC-12 | `ConfigControllerTest` | 🧪 |
| UC-FCFG-03 | gate de permissão da tela | `[must]` `[T0]` | CU-FISC-13 | `GatesPermissaoFiscalTest` | 🧪 |

---

## UC-FCFG-01 — A senha do certificado A1 nunca chega à tela `[must]` `[T0]`

**Dado** um certificado A1 cadastrado, cuja senha fica cifrada no banco
**Quando** a tela serializa o certificado para o navegador
**Então** **nenhum valor de senha do certificado viaja no payload** — nem cifrado.

- **Regressão que defende:** vazamento de segredo por serialização automática do modelo. É o caso clássico em que "adicionar um campo ao payload" abre um buraco sem nenhum erro aparecer.
- **Contrato, não chave:** o que se defende é *"nenhum valor de senha aparece"* — não *"a chave se chama X"*. Renomear o campo não pode fazer o vazamento passar.
- **Teste:** `Modules/Fiscal/Tests/Feature/ConfigControllerTest.php` — `it('UC-FCFG-01 · NfeCertificado encrypted_password é hidden — não vaza no payload Inertia')`
- **Status:** 🧪 advisory + noturna.

## UC-FCFG-02 — O certificado de outro business não aparece `[must]` `[T0]`

**Dado** certificados ativos de mais de um business
**Quando** a tela busca o certificado vigente
**Então** só o do business ativo é encontrado.

- **Regressão que defende:** vazamento cross-tenant Tier 0 ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)) — aqui expondo o CNPJ titular e a validade do certificado de outro cliente.
- **Teste:** `ConfigControllerTest` — `it('UC-FCFG-02 · NfeCertificado HasBusinessScope esconde certs de outros tenants')`
- **Status:** 🧪 advisory + noturna.

## UC-FCFG-03 — A tela exige `fiscal.config.edit` `[must]` `[T0]`

**Dado** um usuário sem `fiscal.config.edit` e sem `superadmin`
**Quando** abre `/fiscal/config`
**Então** recebe 403.

- **Âncora de contrato:** `R-FISCAL-003` do [SPEC.md](../../../../memory/requisitos/Fiscal/SPEC.md) §3 + guard em `ConfigController@index`.
- **Regressão que defende:** esta tela mostra CNPJ titular, regime, numeração fiscal e o ambiente SEFAZ em uso — e, hoje, **oferece dois formulários de mutação** (ver aviso acima). O gate é a única barreira.
- **Teste:** `Modules/Fiscal/Tests/Feature/GatesPermissaoFiscalTest.php` — `it('UC-FCFG-03 · GET /fiscal/config aborta 403 sem fiscal.config.edit nem superadmin')`
- **Status:** 🧪 teste nasce nesta corrida; veredito pendente.

---

## Backlog de casos (sem id — viram UC quando ganharem contrato + teste)

- **[BACKLOG · ⬜ sem teste] A validade do certificado aparece com três tons de urgência** — vencido, perto de vencer (até 30 dias) e tranquilo. _O cálculo existe no Controller; sem teste dos limiares._
- **[BACKLOG · ⬜ sem teste] O painel mostra regime, série, próximo número e tributação padrão** — leitura consolidada do que o NfeBrasil guarda. _Sem teste; note que esses dados são lidos por consulta direta à tabela, **fora** do escopo automático de business — o escopo é aplicado à mão a partir da sessão (SDD §5.2)._
- **[BACKLOG · ⬜ sem teste · decisão [W]] A aba de séries mostra séries reais** — hoje ela é servida por **dado de demonstração** com uma filial inventada (`CU-FISC-16` do SDD §6.5 · §5.4.1). **Precisa de decisão [W].**
- **[BACKLOG · ⬜ sem contrato · decisão [W]] Trocar o ambiente SEFAZ e enviar certificado a partir desta tela** — os dois formulários existem, mas o charter diz que a tela é read-only. **Sem contrato até [W] resolver a divergência** (ver aviso no topo). Escrever UC aqui seria escolher o vencedor de uma disputa de intenção.
- **[BACKLOG · 🧪 coberto em outra tela] O bloqueio do download de SPED por feature flag** tem contrato em [`Sped.casos.md`](Sped.casos.md) (`UC-FSPED-05`) — a aba "sped" desta tela apenas aponta para lá.

## Como rodar a suíte

1. **Advisory:** `Pest Fiscal` (matrix `modules-pest.yml`) roda `Modules/Fiscal/Tests` em SQLite — estes testes **pulam** lá.
2. **Noturna CT 100:** `phpunit.xml` inclui `./Modules/Fiscal/Tests/Feature`; é onde eles correm contra MySQL real.
3. ⛔ **Nunca local** ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

## Trilha do tempo

- 2026-07-15 · [CC] stub criado no Passo 3 do programa de ondas — **0 UC**.
- 2026-07-27 · [CC] `sdd-from-source` (Onda 1 / S2): **3 UC** derivados do §6 do SDD; 2 herdam testes existentes, 1 nasce com teste novo. Os 5 itens de backlog que citavam a feature flag do SPED foram **movidos** para a tela dona (`Sped`) em vez de duplicados. Divergência charter × código registrada, **não** resolvida (intenção é de [W]).
