---
id: resources-js-pages-fiscal-eventos-casos
casos: Eventos Fiscais · /fiscal/eventos
irmaos: Eventos.charter.md (lei) · memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-09-03"
last_run_ci: "0 UC executado nesta corrida — Pest roda no CT 100/CI, nunca local (ADR 0062); 3 UC nascem com teste novo no arquivo já listado na allowlist da lane required; veredito pendente das lanes"
related_us: [US-FISCAL-007]
---

# Casos de Uso & Aceite — Eventos Fiscais

> **Revalidação `last_run` 2026-09-01 — Onda 1 Fiscal (saneamento `fx-*` → DS):** mudança de
> **apresentação apenas** — `fx-callout` → `<Alert>`, 5 `fx-chip` → `<Button>`, `fx-filters` →
> `<Inline>`. Conferi os 4 UC um a um: **todos assertam backend** — timeline cross-tenant (T0),
> evento registrado não se edita (append-only), os 7 rótulos SEFAZ em português, e o gate
> `fiscal.access` (T0).
> Um deles pedia conferência de perto: o **UC-FEVT-03 fala de RÓTULOS**, mas são os rótulos dos
> **eventos** (`ev.label`, vindo do Controller e renderizado no `fx-tl-badge`) — não os dos
> chips de filtro que este PR migrou. O `fx-tl-badge` **não foi tocado**.
>
> **O `fx-timeline` foi MANTIDO, e é decisão declarada, não esquecimento:** ele não é container
> genérico — carrega `position: relative` (contexto de posicionamento dos dots
> `.fx-tl-item::before`), o `::before` que desenha a linha vertical, e o chrome do card. Medido:
> **não existe primitiva de timeline no DS** (zero no `REGISTRY_DS_COMPONENTES.md` e zero em
> `Components/`). Migrar exigiria CRIAR um componente novo do DS, que é soberania [W].
> A âncora `data-contract="fiscal-eventos-timeline"` segue intacta (`contrato-de-tela` rc=0).
> **Nenhum teste re-executado** (Pest = CT 100).

> **Revalidação `last_run` 2026-08-28 — o que foi conferido:** este PR muda a tela em **um único ponto**: o atributo `data-contract="fiscal-eventos-timeline"` no wrapper, âncora do mapa [`fiscal-eventos.map.json`](../../../../memory/requisitos/Fiscal/fiscal-eventos.map.json). Conferi o diff do `.tsx` contra a lista de UC deste arquivo — **nenhum UC depende de atributo de DOM**, logo nenhum aceite mudou. **Nenhum teste foi re-executado** nesta revalidação (Pest = CT 100); os vereditos seguem como estavam.

> Persona: **Eliana [E] (contadora)** — auditoria. A timeline é o registro append-only do que foi feito com cada nota.
>
> **Âncora:** `CU-FISC-05`, `CU-FISC-12` e `CU-FISC-13` do
> [SDD §6](../../../../memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md). Os UC derivam do **CU**, nunca do `.tsx`.
>
> **Status:** ✅ provado por teste verde que cita o UC · 🧪 tem teste, **veredito pendente da lane** · ⬜ não verificado · ❌ quebrou.

## Força do veredito

| Teste | Lane | Bloqueia merge? |
|---|---|---|
| `EventosCockpitMultiTenantTest` | `PHP / Pest (NfeBrasil · MySQL)` | ✅ **sim** — required no [baseline](../../../../governance/required-checks-baseline.json) e na allowlist do workflow |
| `GatesPermissaoFiscalTest` (novo) | `Pest Fiscal` (**pula** em SQLite) + suíte noturna CT 100 | ❌ **não** — advisory |

## Rastreabilidade

| UC | O que defende | Prio | CU (SDD §6) | Teste que o cita | Status |
|---|---|---|---|---|---|
| UC-FEVT-01 | isolamento da timeline | `[must]` `[T0]` | CU-FISC-12 | `EventosCockpitMultiTenantTest` | 🧪 |
| UC-FEVT-02 | timeline é append-only | `[must]` `[reg]` | CU-FISC-05 | `EventosCockpitMultiTenantTest` | 🧪 |
| UC-FEVT-03 | os 7 tipos SEFAZ rotulados | `[must]` | CU-FISC-05 | `EventosCockpitMultiTenantTest` | 🧪 |
| UC-FEVT-04 | gate de acesso à timeline | `[must]` `[T0]` | CU-FISC-13 | `GatesPermissaoFiscalTest` | 🧪 |
| UC-FEVT-05 | o CSV não vaza outro business | `[must]` `[T0]` | CU-FISC-12 | `EventosCockpitMultiTenantTest` | 🧪 |
| UC-FEVT-06 | o CSV leva o recorte filtrado | `[must]` | CU-FISC-05 | `EventosCockpitMultiTenantTest` | 🧪 |
| UC-FEVT-07 | a janela do CSV é clampada | `[should]` | CU-FISC-05 | `EventosCockpitMultiTenantTest` | 🧪 |

---

## UC-FEVT-01 — A timeline nunca mostra evento de outro business `[must]` `[T0]`

**Dado** eventos do business ativo e de outro business
**Quando** a timeline carrega
**Então** só os do business ativo aparecem.

- **Regressão que defende:** vazamento cross-tenant Tier 0 ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)) na superfície mais sensível — a trilha de auditoria de quem cancelou e corrigiu o quê.
- **Teste:** `Modules/Fiscal/Tests/Feature/EventosCockpitMultiTenantTest.php` — `it('UC-FEVT-01 · NfeEvento HasBusinessScope esconde cross-tenant — listagem timeline scoped')`
- **Status:** 🧪 lane **required**; veredito pendente.

## UC-FEVT-02 — Evento registrado não se edita `[must]` `[reg]`

**Dado** um evento SEFAZ já registrado
**Quando** a timeline renderiza
**Então** nenhuma linha oferece edição — o registro é append-only, sem carimbo de atualização.

- **Âncora legal:** CONFAZ SINIEF 07/2005 Art. 14 + LGPD Art. 37 (registro de operações). Corrigir um evento é **emitir outro**, nunca reescrever o anterior.
- **Regressão que defende:** transformar auditoria em rascunho editável.
- **Teste:** `EventosCockpitMultiTenantTest` — `it('UC-FEVT-02 · NfeEvento é append-only (UPDATED_AT = null) — eventos não devem ser editados')`
- **Status:** 🧪 lane **required**; veredito pendente.

## UC-FEVT-03 — Os sete eventos SEFAZ chegam rotulados em português `[must]`

**Dado** eventos de carta de correção, cancelamento, contingência e as quatro manifestações do destinatário
**Quando** a contadora filtra por categoria
**Então** cada tipo é reconhecido, agrupado na categoria certa e exibido com rótulo legível.

- **Regressão que defende:** tipo novo (ou renomeado) cair no rótulo genérico e sumir do filtro — a contadora deixa de ver a categoria inteira sem nenhum erro aparecer.
- **Teste:** `EventosCockpitMultiTenantTest` — `it('UC-FEVT-03 · mapa de TIPOS cobre os 7 códigos SEFAZ canônicos esperados pelo cockpit')`
- **Status:** 🧪 lane **required**; veredito pendente.
- **Fronteira declarada:** inutilização de faixa **não** é evento desta timeline — vive em registro próprio. O código documenta isso explicitamente; não é lacuna.

## UC-FEVT-04 — A timeline exige `fiscal.access` `[must]` `[T0]`

**Dado** um usuário sem `fiscal.access` e sem `superadmin`
**Quando** abre `/fiscal/eventos`
**Então** recebe 403.

- **Âncora de contrato:** `R-FISCAL-003` do [SPEC.md](../../../../memory/requisitos/Fiscal/SPEC.md) §3 + guard em `EventosController@index`.
- **Teste:** `Modules/Fiscal/Tests/Feature/GatesPermissaoFiscalTest.php` — `it('UC-FEVT-04 · GET /fiscal/eventos aborta 403 sem fiscal.access nem superadmin')`
- **Status:** 🧪 teste nasce nesta corrida; veredito pendente.

## UC-FEVT-05 — O CSV exportado nunca traz evento de outro business `[must]` `[T0]`

**Dado** eventos do business ativo e de outro business no mesmo período
**Quando** a contadora exporta a timeline em CSV
**Então** o arquivo contém apenas os eventos do business ativo.

- **Regressão que defende:** o mesmo vazamento Tier 0 do UC-FEVT-01, por uma porta nova. Um export é pior que a tela: o arquivo sai do sistema, vai por e-mail ao contador e não deixa rastro de quem viu o quê. O filtro vem do global scope `HasBusinessScope` ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)), nunca de `where` manual no Controller.
- **Teste:** `Modules/Fiscal/Tests/Feature/EventosCockpitMultiTenantTest.php` — `it('UC-FEVT-05 · o CSV exportado nunca traz evento de outro business')`. Tem controle positivo: a linha do próprio tenant **está** no arquivo, senão o "não contém" passaria vácuo com um CSV vazio.
- **Status:** 🧪 lane **required**; veredito pendente.

## UC-FEVT-06 — O CSV leva o recorte que está filtrado, não a timeline inteira `[must]`

**Dado** eventos de tipos diferentes no período
**Quando** a contadora filtra por um tipo e exporta
**Então** o arquivo traz só aquele tipo — e o filtro oposto traz só o outro.

- **Regressão que defende:** export que ignora o filtro entrega um arquivo que não corresponde à tela, e a contadora concilia contra o recorte errado sem nenhum erro aparecer. O contrário — exportar só a página de 50 — é a mesma falha ao contrário: quem filtrou 90 dias espera levar os 90 dias.
- **Teste:** `EventosCockpitMultiTenantTest` — `it('UC-FEVT-06 · o CSV respeita o filtro de tipo ativo …')`. Assertiva dos dois lados (`cancel` e `cce`), o que prova que o recorte é **por tipo** e não "só o primeiro".
- **Status:** 🧪 lane **required**; veredito pendente.

## UC-FEVT-07 — A janela do CSV é clampada nas opções da tela `[should]`

**Dado** um pedido de export com período fora das opções oferecidas
**Quando** o arquivo é gerado
**Então** a janela cai no padrão de 30 dias.

- **Regressão que defende:** a tela oferece 7/30/90, mas o período chega por query string. Sem teto, `?dias=99999` vira varredura de tabela inteira num request síncrono — e o export, ao contrário da tela, não tem paginação para segurar.
- **Fronteira honesta:** o clamp vale **só para o export**. A `index()` segue com `max(1, dias)` sem teto, protegida pelo `paginate(50)` — mudar isso altera comportamento de tela viva e é decisão [W], fora do escopo desta onda.
- **Teste:** `EventosCockpitMultiTenantTest` — `it('UC-FEVT-07 · a janela do CSV é clampada …')`.
- **Status:** 🧪 lane **required**; veredito pendente.

---

## Divergência protótipo × produção — EPEC vs Inutilização (resolvida a favor do vivo)

O protótipo Cowork ([`fiscal-subpages.jsx:33`](../../../../prototipo-ui/cowork/fiscal-subpages.jsx)) oferece um chip **"Inutilização (102)"** onde a tela viva tem **EPEC**. Medido nesta onda, o **vivo está certo** — e a razão é estrutural, não de preferência:

| Evidência | O que diz |
|---|---|
| `nfe_eventos.emissao_id` | FK **NOT NULL** para `nfe_emissoes` (migration `2026_05_06_002002`). Todo evento pertence a uma nota que **existe**. |
| Inutilização, por definição | É sobre faixa de numeração **nunca usada** — não há nota, logo não há `emissao_id` possível. Não cabe nesta tabela por construção. |
| `nfe_inutilizacoes` | Tabela própria (`modelo`/`serie`/`numero_de`/`numero_ate`/`cstat`), sem `emissao_id`. Tem Model, Controller e Service próprios. |
| `102` é **cStat** | Código de *status de retorno*, não `tpEvento`. Os chips desta tela filtram por `tipo` (tpEvento). Misturar 102 com 110110/110111 põe duas dimensões na mesma lista. |
| `110140` (EPEC) | `tpEvento` legítimo de NF-e, gravado em `nfe_eventos`. Permanece. |

**Nada foi trocado**, e a decisão já era canon antes desta medição: o charter declara Non-Goal *"Inutilização (vive em NfeInutilizacao — Model separado, sub-página futura)"*, e o UC-FEVT-03 abaixo já registrava a fronteira. Esta seção acrescenta a **prova estrutural**, que faltava. Trocar o chip exigiria mudar o schema — decisão [W], não conserto de onda.

---

## Backlog de casos (sem id — viram UC quando ganharem contrato + teste)

- **[BACKLOG · ⬜ sem teste] A justificativa exibida é truncada, para não vazar PII do XML** — Dado um evento com justificativa longa · Quando a linha renderiza · Então só um trecho inicial aparece. _Anti-hook do charter (o texto do motivo da SEFAZ pode conter dado pessoal); o corte existe no Controller, sem teste._
- **[BACKLOG · ⬜ sem teste] Filtro por categoria e por janela de tempo** — 7, 30 ou 90 dias, com 30 como padrão. _Existe no Controller; sem teste do resultado._
- **[BACKLOG · ⬜ sem teste] A nota de origem vem junto sem multiplicar consultas** — o charter limita a **um** relacionamento carregado; nenhum teste guarda esse limite.
- **[BACKLOG · 🧪 coberto em outra tela] As ações que GERAM evento** (cancelamento, carta de correção, inutilização, retransmissão, manifestação) têm contrato em [`Nfe.casos.md`](Nfe.casos.md) (`UC-FNFE-04..07`) e [`Dfe.casos.md`](Dfe.casos.md) (`UC-FDFE-03/04`) — o dispatch vive lá, esta tela só **lê** o resultado. Não se duplica UC entre telas irmãs.

## Como rodar a suíte

1. **Lane required:** `PHP / Pest (NfeBrasil · MySQL)` roda `EventosCockpitMultiTenantTest` em todo PR que toque `Modules/Fiscal/Tests/**`.
2. **Advisory + noturna:** `Pest Fiscal` (SQLite, pula) e a suíte noturna CT 100 (MySQL, roda).
3. ⛔ **Nunca local** ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

## Trilha do tempo

- 2026-07-03 · [CC] stub criado no Passo 3 do programa de ondas — **0 UC**.
- 2026-07-27 · [CC] `sdd-from-source` (Onda 1 / S2): **4 UC** derivados do §6 do SDD. Os 8 itens de backlog que citavam ações de mutação foram **movidos** para as telas donas (`Nfe`/`Dfe`) em vez de duplicados — UC não se repete entre telas irmãs.
- 2026-09-03 · [CC] Onda 7 (export CSV): **+3 UC** (05 cross-tenant do arquivo · 06 recorte filtrado · 07 clamp da janela). Os testes foram escritos **dentro do `EventosCockpitMultiTenantTest`**, não num arquivo novo: a allowlist da lane required lista os testes um a um, e arquivo novo nasceria sem execução. Registrada também a **divergência EPEC × Inutilização** do protótipo, resolvida a favor do vivo com prova estrutural (FK `emissao_id` NOT NULL) — nada trocado na tela.
