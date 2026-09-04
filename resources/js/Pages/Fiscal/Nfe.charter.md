---
id: resources-js-pages-fiscal-nfe-charter
page: /fiscal/nfe
component: resources/js/Pages/Fiscal/Nfe.tsx
related_prototype: prototipo-ui/cowork/fiscal-page.jsx
bundle_source: fiscal-page.jsx
page_id: fiscal-nfe
url: /fiscal/nfe
module: Fiscal
status: draft
created: 2026-05-20
owner: wagner
related_us: [US-FISCAL-001, US-FISCAL-012, US-FISCAL-013, US-FISCAL-014]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0104-processo-mwart-canonico-unico-caminho, 0114-prototipo-ui-cowork-loop-formalizado, 0143-fsm-pipeline-live-prod-marco-2026-05-12]
prototypes: [prototipo-ui/cowork/fiscal-page.jsx]
---

# Charter — `Fiscal/Nfe`

> **Tipo:** charter canônico Tier A skill `charter-first` — Wagner aprova Non-Goals + Anti-hooks ANTES de marcar `status: live`.

## Mission

Dar à pessoa fiscal (Eliana contadora + Wagner operador) a **lista navegável de NF-e/NFC-e emitidas** com **status SEFAZ legível**, **janela legal de cancelamento visível**, e **detalhe acionável via drawer** — substituindo a UI atual fragmentada de `Pages/NfeBrasil/Transactions/NfceStatus` por visão consolidada multi-modelo (55 + 65).

## Goals (Definition of Done PR #1)

1. **Lista paginada** de NfeEmissao (HasBusinessScope ativo — multi-tenant Tier 0) por modelo (55, 65) + filtros (Todas, Autorizadas, Rejeitadas, Janela 24h, Processando).
2. **SEFAZ pill** colorida por tom (ok/warn/bad) com código + label + hint hover — espelha SEFAZ_CODES do design.
3. **Pílula temporal de cancelamento** (24h NFC-e / 168h NF-e) — visível na linha e drawer.
4. **Drawer slide-in com detalhe** (status, destinatário, operação, mapa SEFAZ guiado "Jana sugere" quando cstat rejeitado).
5. **Atalhos J/K + Enter** pra navegar lista e abrir drawer.
6. **Inertia::defer** em rows (skill `inertia-defer-default`) — tabela carrega só quando solicitada.
7. **Pest biz=1** (ADR 0101): isolation cross-tenant + permission gate `fiscal.nfe.view`.

## Non-Goals (Wagner aprova explicitamente)

> ⚠️ **Reconciliado em 2026-07-27.** O Non-Goal "❌ Ações de mutação (cancelar, retransmitir, CC-e,
> inutilizar) — botões existem desabilitados" **estava stale**: as quatro ações foram entregues em
> `US-FISCAL-012` (cancelar + manifestar DF-e), `US-FISCAL-013` (CC-e + inutilizar) e
> `US-FISCAL-014` (retransmitir), e o `NotaDrawer.tsx` já renderiza os botões habilitados
> (`disabled={busy}`, não `disabled` fixo). Mantê-lo instruiria uma sessão futura a desligar código
> correto. Precedência aplicada: *teste verde > casos > charter > SPEC* (proibicoes.md). **Nenhum
> Non-Goal novo foi inventado** — só saiu o que o código refutava; os demais seguem como [W] aprovou.
>
> Pelo mesmo motivo saiu "❌ ⌘K palette completa com busca cross-fiscal": entregue em
> `US-FISCAL-015` e montada no shell desta tela (`FxShell.tsx:144 (verificado@e2c8397)` renderiza `<CmdKPalette />`).

- ❌ **Download de XML e DANFE** pelo drawer — botões seguem desabilitados ("PR seguinte").
- ❌ **NFS-e** na mesma tela (sub-página 3 separada do design).
- ❌ **Manifesto DF-e** (sub-página 4 separada — só o contrato da ação vive aqui).
- ❌ **Emissão nova** (botão "Emitir" desabilitado — entra com EmitirSheet em PR próprio).
- ❌ **Sparklines, alertas, KPIs** (são do Cockpit sub-página 1 — PR #2).
- ❌ **Importar XML** entrada de fornecedor (depende endpoint NfeBrasil ainda não exposto).
- ❌ **Dest_name/CNPJ via JOIN com transactions/contacts** — primeiro PR lê de `metadata` JSON; PR seguinte adiciona join (perf sob carga real).

## Anti-hooks (regras de proteção — bloqueiam regressão)

- 🚫 **Não acessar NfeEmissao sem global scope** — toda query usa `HasBusinessScope` (ADR 0093). Pest cross-tenant biz=1 vs biz=99 quebra se vazar.
- 🚫 **Não usar `withoutGlobalScopes`** no Controller — superadmin acessa via session do business escolhido.
- 🚫 **Não cachear sefazCodes do lado servidor por business** — mapa estático global, cache ok.
- 🚫 **Não disparar polling SEFAZ no `index()`** — leitura pura; reconsulta vem por ação explícita.
- 🚫 **Não mostrar PII real** (CPF/CNPJ completo) sem masking — `formatDoc` aplica truncamento quando necessário.
- 🚫 **Não emitir botão habilitado sem permission gate** — `fiscal.nfe.acoes` obrigatório quando ativar mutations.

## UX targets

- **Densidade:** linhas ~48px, fonte 12.5px corpo / 13.5px número da nota (mono).
- **Cor de status:** verde = aceito pela SEFAZ · âmbar = transitório ou SEFAZ indisponível (103/105/108/109) · vermelho = rejeição. A classificação por código deixou de ser digitada aqui e passa a ser **derivada** do campo `status` da tabela oficial — ver §Reconciliação 2026-09-04.
- **Pílula 24h:** verde >12h, âmbar 6-12h, vermelho <6h (urgência cresce).
- **Drawer:** largura 480px desktop, full-width mobile, ESC fecha, click-outside fecha.
- **Foco visual:** linha cursor (J/K) com `outline: 2px solid var(--fis)`.

## Contrato de teclado — destilado (2026-09-03, Onda 2)

> **Fato, não intenção.** Mission, Goals, Non-Goals, Anti-hooks e UX targets acima não foram
> tocados — este bloco só destila o que a tela **passou a garantir** e quem prova. A intenção que
> ele serve já era do [W]: **Goal 5** ("atalhos J/K + Enter pra navegar lista e abrir drawer") e o
> §UX targets ("linha cursor com `outline: 2px solid var(--fis)`").

| O que a lista garante | Como se prova |
|---|---|
| Toda linha é **focável** e alcançável na ordem do DOM (`tabIndex={0}`) | `document.activeElement` após `.focus()` real — sem o atributo, o foco cai no `<body>` |
| **Enter** abre o drawer da linha **focada**, não o da primeira | o `<h2>` do drawer traz o número da nota da 3ª linha |
| **Space** abre **e cancela o default** — a página não rola | `fireEvent` devolve `false` só quando houve `preventDefault` |
| Tab e J/K compartilham **um** cursor (um anel, não dois) | focar a 2ª + `j` global acende a 3ª; sem o `onFocus`, acenderia a 2ª |
| A linha **continua sendo linha** — nada de `role="button"` | `getAttribute('role')` é `null` nas três linhas |
| O anel de foco usa o **token** `--fis`, e o do UA é substituído, nunca suprimido | `:focus-visible` em `fiscal-cockpit.css`; zero `outline: none` no arquivo |

**Quem defende:** [`tests/js/fiscal-nfe-teclado.test.tsx`](../../../../tests/js/fiscal-nfe-teclado.test.tsx)
(6 casos) na lane advisory `fiscal-teclado-gate.yml`. Contrato por UC em
[`Nfe.casos.md`](Nfe.casos.md) → **`UC-FNFE-10`**, com as 4 mutações da mordida tabeladas lá.

**Limite declarado:** o Enter é servido por **dois** caminhos (o `onKeyDown` da linha e o handler
global de `window`) e o teste não os distingue — ele prova o que o operador observa. E o jsdom não
implementa a travessia por Tab, então "Tab alcança todas" é medido como focabilidade real de cada
linha. Anel pintado e leitor de tela seguem sendo olho humano no smoke (R1).

## Automation hooks (futuros — não-bloqueantes PR #1)

- `Modules/Jana` consome `sefazCodes` + receitas SEFAZ_ACTIONS pra responder dúvidas em chat ("o que significa rejeição 539?").
- Telemetria: `viewed_fiscal_nfe` event quando carrega lista (cycle goal "Eliana usa cockpit").
- Hook futuro pós-cancel: emit `FscalNotaCancelled` event consumido por Whatsapp/Email (já há `CancelarVendaCascade` em FSM ADR 0143).

## Riscos conhecidos

- **R1:** lista carrega lenta se business tem >10k notas — mitigação: defer + paginate 50 + index em `emitido_em DESC`.
- **R2:** metadata->dest_name pode estar vazio em notas antigas pré-Sprint 3 ARQ-019 — fallback "—".
- **R3:** janela 24h vs UTC vs America/Sao_Paulo — Controller usa `now()` (timezone do app); pílula JS usa `Date.now()` (browser timezone). Risco baixo porque comparação é minutos antes da deadline, não horas; futuro: passar `nowMs` server-rendered pra precisão.

---

## Reconciliação factual — 2026-07-27 (`sdd-from-source`, Fase 2.6)

**Só FATO foi corrigido. Nenhuma intenção (Mission, Goals, Non-Goals, Anti-hooks, UX targets) foi tocada** — intenção é de [W].

| O que dizia | O que é | Evidência |
|---|---|---|
| `prototypes: prototipo-ui/Oimpresso ERP - Chat.html` · `prototipo-ui/fiscal-page.css` | **nenhum dos dois existe**, e não há protótipo fiscal algum no repositório | `ls "prototipo-ui/Oimpresso ERP - Chat.html"` e `ls prototipo-ui/fiscal-page.css` → *No such file or directory*; `find prototipo-ui -maxdepth 2 -iname "*fiscal*"` → **0 resultados** |

O campo `related_prototype` desta tela **já** declarava `n/a (herda PT-01 Lista; segue o Padrão de Tela)` — a lista `prototypes:` era o resíduo que contradizia. Os dois campos agora concordam.
Consequência que **não** é dívida: sem protótipo, esta tela não é ancorável por `proto-baseline`; é o caso "nasce do Design System" (SDD §4).

Contexto completo: [`memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md`](../../../../memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md) §4 · contrato de teste em [`Nfe.casos.md`](Nfe.casos.md).

### Nova fonte recebida — 2026-08-28

O diagnóstico acima permanece como fato de 2026-07-27. Depois dele, o bundle transacional
versionou `prototipo-ui/cowork/fiscal-page.jsx`; o frontmatter passou a apontar para essa fonte.
Receber a fonte não altera o veredito de aplicação: ele é derivado pelo Design Sync.

---

## Reconciliação factual — 2026-09-04 (pílula SEFAZ)

**Só FATO foi corrigido. Mission, Goals, Non-Goals, Anti-hooks seguem intocados** — intenção é de [W].

Achado de produção reportado por [W] com screenshot: em `biz=1` a lista mostrava **`781 Status`** e
**`— Status`**. `"Status"` não é rótulo — era o fallback do `Nfe.tsx:286` vazando para a tela.
Medindo o mapa que o Controller servia contra a tabela cStat oficial (528 códigos, distribuída em
`vendor/nfephp-org/sped-nfe/storage/cstat.json` com o SDK que este projeto usa pra transmitir),
apareceu o problema maior: **5 dos 12 códigos estavam traduzidos errado**.

| O que dizia (UX targets + mapa à mão) | O que é (tabela oficial da SEFAZ) | Evidência |
|---|---|---|
| `691` é âmbar, "NCM divergente" | `691` = **"Chave de Acesso da NF-e diverge da Chave de Acesso do EPEC"**, `status: "0"` = rejeição | tabela cStat oficial |
| `778` = "CST/CFOP inválido" | `778` = **"Informado NCM inexistente"** | a SEFAZ gravou `motivo="Rejeicao: Informado NCM inexistente [nItem:1]"` na nota id=8 de biz=1 |
| `220` = "Duplicidade" | `220` = **"Prazo de Cancelamento superior ao previsto na Legislação"** | tabela cStat oficial |
| `104` = "Autorizada (NFC-e)" | `104` = **"Lote processado"** | idem |
| `999` = "Processando" | `999` = **"Erro não catalogado"** | idem |

O âmbar do `691` no §UX targets caiu junto com a premissa que o sustentava: ele estava lá por se
acreditar que o código era um erro de cadastro corrigível. Derivar o tom do campo `status` da tabela
**cumpre** a regra que [W] escreveu (*"vermelho = rejeição"*) melhor do que a lista literal cumpria.

Também deixou de ser verdade que o Goal 2 se satisfaz "espelhando SEFAZ_CODES do design": o
`prototipo-ui/cowork/fiscal-data.jsx` tem 8 códigos e declara-se derivado do `Cockpit.tsx` — é porte
reverso do código, não fonte fiscal. A fonte é a tabela da SEFAZ.

**O que a tela garante agora** (contrato por UC em [`Nfe.casos.md`](Nfe.casos.md) → `UC-FNFE-03`):

| Garantia | Como se prova |
|---|---|
| cStat conhecido mostra o **texto oficial** da SEFAZ | `NfeCockpitMultiTenantTest` asserta contra textos do MOC transcritos, não contra o próprio mapa |
| o rótulo **nunca** é `"Status"` nem vazio | `fiscal-nfe-sefaz-pilula.test.tsx` mede `.fx-sefaz .lbl` nas 2 linhas reais de biz=1 |
| nota **sem** cStat mostra o status de domínio ("Inutilizada") | idem — antes renderizava `— Status` |
| código fora da tabela **não** é inventado: some do mapa e a tela mostra status + número | `mapaPara([424242])` → `[]` (controle negativo) |
| o tooltip traz o `motivo` daquela nota, com o `[nItem:N]` | idem |

### Dívida declarada, NÃO consertada aqui — e a primeira redação dela estava ERRADA

⚠️ **Correção de precisão (mesma sessão, apontada pela sessão irmã do #6719).** Esta seção dizia
que o `_lib/sefaz-codes.ts` *"serve o `Cockpit.tsx` e o `EventosDrawer.tsx`"*. **Falso, e falso de
um jeito caro:** quem ler isso vai consertar o `sefaz-codes.ts` achando que conserta a lista do
cockpit — e **não conserta**. Varredura contada (`from '../_lib/sefaz-codes'`, 17.193 arquivos):
**2 importadores, nenhum é o `Cockpit.tsx`**.

O mesmo defeito existe em **três** lugares independentes, e cada um pede trabalho próprio:

| # | Onde | Serve | Estado |
|---|---|---|---|
| 1 | `NfeCockpitController::sefazCodes()` | lista `/fiscal/nfe` | **consertado aqui** — deriva da tabela oficial |
| 2 | `Cockpit.tsx:159` `STATUS_LABEL` (8 códigos) + fallback `` `Status ${n.status}` `` na linha 612 | **lista `/fiscal`** | **6 de 8 errados** — pior proporção que o #1 |
| 3 | `_lib/sefaz-codes.ts` (16 códigos) | `NotaDrawerV2` (que o Cockpit monta) + `EventosDrawer` | apelidos errados em `220`/`691`/`778` |

Ou seja: a lista do cockpit e o drawer dele passam por **caminhos diferentes**. O `781 Status` que
este PR conserta em `/fiscal/nfe` tem um irmão vivo em `/fiscal` — pelo #2, não pelo #3.

⚠️ **Um `STATUS_LABEL` no módulo NÃO é necessariamente um mapa de cStat.** A varredura acha **cinco**
`STATUS_LABEL` em `Pages/Fiscal`, e só os dois de cima entram nesta tabela. Os outros dois falam
**outro vocabulário** e não têm o defeito:

| Onde | Chaves | É cStat? |
|---|---|---|
| `Nfse.tsx:69` | `authorized`/`rejected`/`pending`/`sent`/`cancelled` | **não** — status de domínio da NFS-e (municipal) |
| `_components/NFSeDrawer.tsx:48` | idem | **não** — mesmo vocabulário |

A distinção não é preciosismo: quem for unificar as fontes precisa saber que a NFS-e **não tem
cStat** (é prefeitura, não SEFAZ) e que forçá-la no mesmo dicionário seria o erro oposto.

Nenhum dos dois foi tocado: são outras telas, outro intent, e há sessões ativas no Cockpit agora.
Fica medido para quem pegar.

