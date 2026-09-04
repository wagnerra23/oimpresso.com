---
id: resources-js-pages-fiscal-config-casos
casos: Configuração Fiscal · /fiscal/config
irmaos: Config.charter.md (lei) · memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-09-04"
last_run_ci: "0 UC executado nesta corrida — UC-FCFG-05 nasce com teste novo e veredito PENDENTE da lane Pest Fiscal + suíte noturna CT 100; nada foi re-executado localmente (Pest = CT 100, ADR 0062)"
related_us: [US-FISCAL-009]
---

# Casos de Uso & Aceite — Configuração Fiscal

> **Revalidação `last_run` 2026-09-01 — Onda 1 Fiscal (saneamento `fx-*` → DS):** mudança de
> **apresentação apenas** — 5 `fx-btn` → `<Button>` (dois deles `asChild` sobre `<a>`,
> preservando o href) e 2 `<input>` hand-rolled → `<Input>`, o que apagou o `style` inline de
> `padding`/`border`/`radius` que ambos carregavam (a primitiva já dá).
> Conferi os 3 UC: **todos são Tier 0 de backend** — a senha do A1 nunca chega à tela, o
> certificado de outro business não aparece, e o gate `fiscal.config.edit`. **Nenhum toca o
> `.tsx`.** O campo de senha preservou `type="password"`, `autoComplete="off"` e `maxLength`.
>
> **O que NÃO foi migrado, e por quê (declarado, não esquecido):**
> · `fx-cert-*` (grid, card, head, ic, validade, bar, actions) — o CSS estiliza por **seletor
>   descendente** (`.fx-cert-card h3`, `.fx-cert-card .lead`), então trocar o container por
>   `<Card>` perderia esses estilos. É chrome bespoke, sem gêmeo 1:1 no DS.
> · os 3 `fx-callout` — dois carregam **cor condicional de status** (`--ok-soft`/`--bad-soft`)
>   e o `<Alert>` só tem `default`/`destructive`; mapear exigiria decidir tom por token novo.
>   Fica para leva própria, com o antes→depois visível.
> **Nenhum teste re-executado** (Pest = CT 100).

> **Revalidação `last_run` 2026-08-28 — o que foi conferido:** este PR muda a tela em **um único ponto**: o atributo `data-contract="fiscal-config-cert-regime"` no wrapper, âncora do mapa [`fiscal-config.map.json`](../../../../memory/requisitos/Fiscal/fiscal-config.map.json). Conferi o diff do `.tsx` contra a lista de UC deste arquivo — **nenhum UC depende de atributo de DOM**, logo nenhum aceite mudou. **Nenhum teste foi re-executado** nesta revalidação (Pest = CT 100); os vereditos seguem como estavam.

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
| UC-FCFG-04 | estado da contingência e sua DURAÇÃO chegam do servidor | `[must]` | US-NFE-006 | `ConfigControllerTest` | 🧪 |
| UC-FCFG-05 | o card de envio de documentos LÊ o deploy, não demonstra | `[should]` | CU-FISC-16 | `ConfigControllerTest` | 🧪 |

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

## UC-FCFG-04 — O estado da contingência e sua DURAÇÃO chegam do servidor `[must]`

**Dado** um business com a contingência SEFAZ ativa há 3 dias, com motivo declarado
**Quando** a tela `/fiscal/config` é montada
**Então** o payload traz `ativa=true`, `diasAtiva=3`, o motivo e o instante de ativação;
**E** com a contingência desligada, `diasAtiva` é `null` — **nunca `0`**.

- **Âncora de contrato:** `US-NFE-006` + `ADR TECH-0002 (NfeBrasil)` §"Consequências → risco
  operacional", que mitiga *"tenant esquecer de desativar contingência"* com um aviso de **duração**
  (*"Contingência ATIVA há 2 dias — desativar?"*).
- **Regressão que defende:** duas, e a segunda é sutil.
  1. Calcular a duração **no browser** faria o aviso depender do relógio da máquina do operador —
     e é justamente esse número que sustenta a mitigação da ADR.
  2. Exibir `0` quando está **desligada** confundiria "ligada hoje" com "não ligada". São estados
     diferentes; o controle negativo do teste trava isso.
- **Teste:** `Modules/Fiscal/Tests/Feature/ConfigControllerTest.php` — `it('UC-FCFG-04 · o payload da tela carrega o estado da contingência com a duração vinda do SERVIDOR')`
- **Status:** 🧪 teste nasce nesta corrida; veredito pendente.

---

## UC-FCFG-05 — O card de envio de documentos LÊ o deploy, não demonstra `[should]`

**Dado** as duas chaves que governam o envio automático do DANFE — `email_danfe_on_autorizada`
(NF-e 55) e `email_danfe_nfce_on_autorizada` (NFC-e 65), em `Modules/NfeBrasil/Config/config.php`
**Quando** a contadora abre a aba *Certificado e regime*
**Então** o card **Envio de documentos** mostra o estado **real** de cada uma;
**E** invertidas as chaves, o card inverte junto.

- **Regressão que defende:** card de configuração que serve valor fixo. O protótipo desenha este
  card com `contador@example.com.br` de mock (`fiscal-data.jsx:150`); um port literal traria o
  mock pra tela viva e a contadora leria demonstração como configuração. O controle negativo do
  teste (inverter as chaves e reconferir) é o que separa *ler* de *afirmar* — sem ele, o teste
  passaria com o payload hardcoded.
- **Ausência declarada, não preenchida:** a linha **Contador** do protótipo não tem campo
  correspondente no schema (`nfe_business_configs` não tem coluna de e-mail de contador; a única
  ocorrência no repo é uma linha **comentada** em `Modules/Connector/.../BusinessController.php`).
  A tela diz *"não cadastrado — ainda não existe campo"* em vez de inventar endereço. Cadastrar
  esse e-mail é backlog com decisão [W] (abaixo).
- **Escopo dito em texto:** as duas chaves valem por **deploy**, não por empresa. O card afirma
  isso; um selo por-empresa aqui mentiria sobre o alcance da configuração.
- **Teste:** `Modules/Fiscal/Tests/Feature/ConfigControllerTest.php` — `it('UC-FCFG-05 · o card de envio de documentos espelha as flags REAIS do deploy')`
- **Status:** 🧪 teste nasce nesta corrida; veredito pendente da lane.

---

## Backlog de casos (sem id — viram UC quando ganharem contrato + teste)

- **[BACKLOG · ⬜ sem teste] A validade do certificado aparece com três tons de urgência** — vencido, perto de vencer (até 30 dias) e tranquilo. _O cálculo existe no Controller; sem teste dos limiares._
- **[BACKLOG · ⬜ sem teste] O painel mostra regime, série, próximo número e tributação padrão** — leitura consolidada do que o NfeBrasil guarda. _Sem teste; note que esses dados são lidos por consulta direta à tabela, **fora** do escopo automático de business — o escopo é aplicado à mão a partir da sessão (SDD §5.2)._
- **[BACKLOG · ⬜ sem teste · decisão [W]] A aba de séries mostra séries reais** — hoje ela é servida por **dado de demonstração** com uma filial inventada (`CU-FISC-16` do SDD §6.5 · §5.4.1). **Precisa de decisão [W].**
- **[BACKLOG · ⬜ sem contrato · decisão [W]] Trocar o ambiente SEFAZ e enviar certificado a partir desta tela** — os dois formulários existem, mas o charter diz que a tela é read-only. **Sem contrato até [W] resolver a divergência** (ver aviso no topo). Escrever UC aqui seria escolher o vencedor de uma disputa de intenção.
- **[BACKLOG · ⬜ sem campo · decisão [W]] Cadastrar o e-mail do contador** — a linha existe no card
  (`UC-FCFG-05`) declarando a ausência. Dar valor a ela exige **coluna nova** em
  `nfe_business_configs` (migration em PR próprio, nunca junto de UI) e a decisão de se o envio ao
  contador é cópia automática de toda nota ou digest. _Sem contrato até [W] decidir._
- **[BACKLOG · ⬜ sem campo · decisão [W]] Ligar/desligar o envio automático POR EMPRESA** — hoje as
  duas chaves são de deploy (`Modules/NfeBrasil/Config/config.php`), e o card diz isso. Tornar
  por-tenant é coluna nova + tela editável, não ajuste de leitura.
- **[BACKLOG · 🧪 coberto em outra tela] O bloqueio do download de SPED por feature flag** tem contrato em [`Sped.casos.md`](Sped.casos.md) (`UC-FSPED-05`) — a aba "sped" desta tela apenas aponta para lá.

## Como rodar a suíte

1. **Advisory:** `Pest Fiscal` (matrix `modules-pest.yml`) roda `Modules/Fiscal/Tests` em SQLite — estes testes **pulam** lá.
2. **Noturna CT 100:** `phpunit.xml` inclui `./Modules/Fiscal/Tests/Feature`; é onde eles correm contra MySQL real.
3. ⛔ **Nunca local** ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

## Trilha do tempo

- 2026-07-15 · [CC] stub criado no Passo 3 do programa de ondas — **0 UC**.
- 2026-09-04 · [C] Item A5 (PR 1/3 — abas + cards). As 4 abas recebem os rótulos do protótipo
  (`Certificado e regime` · `Séries` · `Ambiente e certificado` · `SPED`) **sem** trocar as chaves
  de URL; a aba `cert` vira **uma** região ancorada `data-contract="fiscal-config-cert-regime"`,
  trazendo pra dentro dela o card de regime/tributação que vivia fora (era o `gap-parcial` do
  [`fiscal-config.map.json`](../../../../memory/requisitos/Fiscal/fiscal-config.map.json)); e nasce
  o 4º card, **Envio de documentos**, com `UC-FCFG-05`. **Nenhum teste re-executado** (Pest = CT 100).
- 2026-07-27 · [CC] `sdd-from-source` (Onda 1 / S2): **3 UC** derivados do §6 do SDD; 2 herdam testes existentes, 1 nasce com teste novo. Os 5 itens de backlog que citavam a feature flag do SPED foram **movidos** para a tela dona (`Sped`) em vez de duplicados. Divergência charter × código registrada, **não** resolvida (intenção é de [W]).
