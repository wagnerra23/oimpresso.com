---
id: resources-js-pages-fiscal-config-casos
casos: Configuração Fiscal · /fiscal/config
irmaos: Config.charter.md (lei) · memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-09-04"
last_run_ci: "0 UC executado nesta corrida — UC-FCFG-05, UC-FCFG-06 e UC-FCFG-07 nascem com testes novos, veredito PENDENTE da lane Pest Fiscal + suíte noturna CT 100; nada foi re-executado localmente (Pest = CT 100, ADR 0062)"
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

> ✅ **Divergência FECHADA em 2026-09-02 — o aviso abaixo é registro do que era verdade até lá.**
> O charter foi reconciliado naquela data: saiu o Non-Goal *"edição inline"* na parte de upload de
> certificado e saiu o anti-hook *"esta tela é read-only por design"*, ambos porque o `main` os
> refutava (a tela posta desde 2026-05-27). Com a intenção resolvida, o caminho de mutação deixou
> de ser "contrato em disputa" e ganhou contrato: **`UC-FCFG-06`** (o gate das duas ações de risco).
> O parágrafo original fica preservado porque a data importa — ele explica por que os UC de mutação
> demoraram a existir.
>
> ⚠️ **Divergência aberta entre charter e código — decisão [W] (SDD §5.4.3).** _(registro de 2026-07-27, superado acima)_ O charter declara
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
| UC-FCFG-06 | trocar ambiente / trocar certificado exige gate PRÓPRIO, no servidor | `[must]` `[T0]` | CU-FISC-13 | `GatesPermissaoFiscalTest` | 🧪 |
| UC-FCFG-07 | a troca de ambiente exige destino digitado + motivo, e vira evento | `[must]` | CU-FISC-13 | `TrocaAmbienteCerimoniaTest` | 🧪 |

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

## UC-FCFG-06 — Trocar ambiente e substituir certificado exigem gate próprio, recusado no SERVIDOR `[must]` `[T0]`

**Dado** um usuário com `fiscal.config.edit` — ou seja, alguém que legitimamente abre e edita esta
tela — mas **sem** `fiscal.config.ambiente`
**Quando** ele pede a troca do ambiente SEFAZ, ou o envio de um certificado novo
**Então** o **servidor** recusa com 403;
**E** a tela mostra o estado da permissão em texto, com os campos travados e o motivo dito —
nunca um botão cinza sem explicação.

- **Regressão que defende, e ela era real:** até 2026-09-04 o `updateAmbiente` do
  `CertificadoController` **não tinha gate nenhum** — a rota carrega só
  `web/auth/SetSessionData/language/timezone/AdminSidebarMenu`, e o método recebia `Request` puro,
  sem FormRequest. Qualquer usuário autenticado com business em sessão conseguia inverter
  produção↔homologação. Uma empresa emitindo em homologação sem saber passa dias produzindo nota
  **sem valor fiscal**.
- **Por que separado de `fiscal.config.edit`:** editar o e-mail do contador e trocar o ambiente de
  emissão não são o mesmo risco — a segunda muda o valor fiscal de **toda** nota emitida depois
  dela. É por isso que o teste usa um usuário **com** `config.edit` e **sem** `config.ambiente`:
  um caso com "usuário sem permissão nenhuma" não distinguiria os dois gates.
- **A tela não é a barreira:** o `podeTrocarAmbiente` do payload é espelho, pra dizer o motivo.
  Quem recusa é `CertificadoController::garantirGateAmbiente`. O teste ataca o **POST direto**,
  não a tela.
- **Fail-secure:** sem a permissão concedida, 403. Superadmin passa sempre.
- **Teste:** `Modules/Fiscal/Tests/Feature/GatesPermissaoFiscalTest.php` — `it('UC-FCFG-06 · POST ambiente aborta 403 com fiscal.config.edit mas sem fiscal.config.ambiente')`, `it('UC-FCFG-06 · POST upload de certificado aborta 403 sem fiscal.config.ambiente')` e o controle positivo **sem mutação** (posta o ambiente que já está gravado; o controller sai cedo).
- **Status:** 🧪 testes nascem nesta corrida; veredito pendente da lane.

---

## UC-FCFG-07 — A troca de ambiente exige destino digitado à mão e motivo, e deixa evento `[must]`

**Dado** [W] com o gate concedido e a empresa emitindo em PRODUÇÃO
**Quando** ele pede a troca para HOMOLOGAÇÃO
**Então** só confirma se digitar o **nome do destino** à mão **e** escrever um motivo de **15+
caracteres**;
**E** confirmação que não bate deixa o ambiente **inalterado** e diz isso;
**E** a troca aceita grava evento de auditoria com autor, horário, `antes → depois` **e o motivo**.

- **Regressão que defende:** troca por reflexo. Antes disto a troca era um radio + "Salvar
  ambiente" — dois cliques. Empresa que passa a emitir em homologação sem perceber produz dias de
  nota **sem valor fiscal**, e depois ninguém sabe dizer por quê.
- **Por que DUAS provas, e não uma confirmação:** *"sim"* / *"ok"* não seguram uma ação que muda o
  valor fiscal de toda nota seguinte. O nome do destino escrito à mão obriga a ler **para onde** se
  está indo; o motivo obriga a ter um.
- **Por que o motivo entra no EVENTO:** sem ele a trilha responde *quem* e *quando*, mas não
  *por quê* — e é o *por quê* que alguém precisa quando for explicar a nota de terça.
- **Tolerância deliberada:** a confirmação é insensível a caixa e acento. A fricção que importa é
  ter de **escrever a palavra**, não acertar o cedilha — exigir o acento puniria teclado, não
  desatenção. O servidor normaliza com mapa explícito (nunca `iconv`, que depende de locale).
- **Sem troca, sem cerimônia:** postar o ambiente que já está gravado sai cedo, sem exigir motivo.
  Não há o que confirmar quando nada muda.
- **Teste:** `Modules/Fiscal/Tests/Feature/TrocaAmbienteCerimoniaTest.php` — 3 casos negativos
  (sem cerimônia · confirmação `"sim"` · motivo curto), **cada um reconferindo o valor no banco**,
  mais o **controle positivo** que prova a troca real com `antes → depois` e o evento. Sem esse
  positivo, os três negativos passariam num endpoint quebrado que nunca troca nada.
- **Status:** 🧪 testes nascem nesta corrida; veredito pendente da lane.

---

## Backlog de casos (sem id — viram UC quando ganharem contrato + teste)

- **[BACKLOG · ⬜ sem teste] A validade do certificado aparece com três tons de urgência** — vencido, perto de vencer (até 30 dias) e tranquilo. _O cálculo existe no Controller; sem teste dos limiares._
- **[BACKLOG · ⬜ sem teste] O painel mostra regime, série, próximo número e tributação padrão** — leitura consolidada do que o NfeBrasil guarda. _Sem teste; note que esses dados são lidos por consulta direta à tabela, **fora** do escopo automático de business — o escopo é aplicado à mão a partir da sessão (SDD §5.2)._
- **[BACKLOG · ⬜ sem teste · decisão [W]] A aba de séries mostra séries reais** — hoje ela é servida por **dado de demonstração** com uma filial inventada (`CU-FISC-16` do SDD §6.5 · §5.4.1). **Precisa de decisão [W].**
- ~~**[BACKLOG · ⬜ sem contrato · decisão [W]] Trocar o ambiente SEFAZ e enviar certificado a partir desta tela**~~ → **ganhou contrato em 2026-09-04**: o charter foi reconciliado em 09-02 (a tela é editável) e o **gate** das duas ações virou `UC-FCFG-06`. A **cerimônia** da troca — destino digitado à mão + motivo de 15+ caracteres + evento de auditoria com o motivo — é a PR 3/3 do item A5, e ganha id próprio lá.
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
- 2026-09-04 · [C] Item A5 (PR 3/3 — cerimônia da troca). A troca de ambiente deixa de ser radio
  + botão e passa a exigir o destino digitado à mão + motivo de 15+, validados NO SERVIDOR; o
  evento de auditoria passa a carregar o motivo. `UC-FCFG-07`. **Nenhum teste re-executado.**
- 2026-09-04 · [C] Item A5 (PR 2/3 — gate `fiscal.config.ambiente`). Nasce a permissão, DECLARADA
  em `DataController::user_permissions` (Camada 3) e provisionada — mas **nunca atribuída** por
  comando: conceder é ato de [W]. O enforcement é de SERVIDOR, em
  `CertificadoController::garantirGateAmbiente`, e cobre as duas ações de risco. Fecha um buraco
  real: o `updateAmbiente` não tinha gate nenhum. `UC-FCFG-06`. **Nenhum teste re-executado.**
- 2026-09-04 · [C] Item A5 (PR 1/3 — abas + cards). As 4 abas recebem os rótulos do protótipo
  (`Certificado e regime` · `Séries` · `Ambiente e certificado` · `SPED`) **sem** trocar as chaves
  de URL; a aba `cert` vira **uma** região ancorada `data-contract="fiscal-config-cert-regime"`,
  trazendo pra dentro dela o card de regime/tributação que vivia fora (era o `gap-parcial` do
  [`fiscal-config.map.json`](../../../../memory/requisitos/Fiscal/fiscal-config.map.json)); e nasce
  o 4º card, **Envio de documentos**, com `UC-FCFG-05`. **Nenhum teste re-executado** (Pest = CT 100).
- 2026-07-27 · [CC] `sdd-from-source` (Onda 1 / S2): **3 UC** derivados do §6 do SDD; 2 herdam testes existentes, 1 nasce com teste novo. Os 5 itens de backlog que citavam a feature flag do SPED foram **movidos** para a tela dona (`Sped`) em vez de duplicados. Divergência charter × código registrada, **não** resolvida (intenção é de [W]).
