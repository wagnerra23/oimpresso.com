---
id: resources-js-pages-fiscal-nfe-casos
casos: Notas NF-e / NFC-e · /fiscal/nfe
irmaos: Nfe.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-09-04"
---

# Casos de Uso & Aceite — Notas NF-e / NFC-e

> **Revalidação `last_run` 2026-09-03 — o que foi conferido (Onda 2 Fiscal, teclado na lista):**
> a `<tr>` da lista ganhou `tabIndex`, `aria-label`, `onFocus` e `onKeyDown` (Enter/Space), e o
> `fiscal-cockpit.css` ganhou o anel `:focus-visible` no mesmo token `--fis` que o `.fx-row-focus`
> já usava. **Um UC novo: `UC-FNFE-10`** — e ele é o primeiro desta tela a provar comportamento de
> **UI**, não de backend. Os 8 anteriores seguem intactos: conferi que nenhum deles toca a tabela,
> o foco ou o teclado, logo nenhum aceite mudou. O que **mudou de fato** é o §Backlog: o item
> "atalhos J/K + Enter … sem cobertura Feature nem E2E", aberto desde 2026-07-03, deixa de ser
> integralmente descoberto — a metade de teclado agora tem teste que morde, e o restante dele
> (mapa "Jana sugere", pílula temporal) segue declarado.
> **O que esta revalidação NÃO cobre:** os 8 UC de backend não foram re-executados (Pest = CT 100),
> e o `UC-FNFE-01` continua skipando por falta de `nfe_emissoes` na lane — inalterado por esta onda.

> **Revalidação `last_run` 2026-09-02 — o que foi conferido (conflito semântico do merge):**
> o `update-branch` do #6530 trouxe do `main` a versão do `.tsx` que declarava `chipProps`/
> `chipCount` LOCALMENTE (a que o #6517 mergeou), enquanto esta branch já importava as duas do
> `_lib/chip-filtro`. O git juntou os dois lados **sem marcador de conflito** — o defeito só
> apareceu no `TS2440` (import conflita com declaração local), 2 ocorrências. Removi as cópias
> locais e o import órfão de `cn`, mantendo o dono único no `_lib`.
> **Zero mudança de comportamento:** as funções removidas eram byte-a-byte o que o `_lib` já
> exporta, e nenhum UC toca o `.tsx` — os 8 assertam backend. Nenhum UC criado, alterado ou
> removido. **Nenhum teste re-executado** (Pest = CT 100).

> **Revalidação `last_run` 2026-09-01 — o que foi conferido (Onda 1 Fiscal, troca de primitivas):**
> `fx-btn`/`fx-chip`/`fx-search`/`fx-filters` saíram para as primitivas `Button`/`Input`/`Inline` do
> DS. É **camada de apresentação**; os 8 UC assertam backend e nenhum toca o `.tsx`. Dois pontos onde
> a troca *poderia* ter mexido em comportamento foram checados **no código, não presumidos**:
> **(a)** o handler J/K ignora teclas quando `target.tagName === 'INPUT'` — o `<Input>` do DS
> renderiza um `<input>` real, então a guarda continua valendo e a navegação não dispara ao digitar;
> **(b)** o bloco `useEffect` do J/K está **intacto** no diff (0 linhas `+/-` casando
> `tagName`/`addEventListener`). A âncora `data-contract="fiscal-nfe-filters"` sobreviveu à troca do
> `<div>` pelo `<Inline>` (que faz spread de `...props`), provado pelo `contrato-de-tela`: limpo
> `rc=0`, copy mutada `rc=1`. **Nenhum teste foi re-executado** (Pest = CT 100).
> _O que esta revalidação NÃO cobre: o §Backlog segue igual — o gate `fiscal.nfe.view` continua sem
> teste e o `UC-FNFE-01` continua skipando. A onda não os toca e não os conserta._

> **Revalidação `last_run` 2026-09-01 — o que foi conferido (Onda 1 Fiscal, flip do token `--fis`):**
> este PR muda a tela em **um único ponto**: o comentário de cabeçalho do `.tsx`, que afirmava
> `var(--fis) rosa fiscal` e virou falso quando o token passou a `oklch(0.55 0.15 295)`. A mudança de
> comportamento é **zero** — o CSS é o único consumidor funcional de `var(--fis)` (medido: 38 usos no
> `.css`, 0 no `.tsx`). Conferi os 8 UC deste arquivo um a um: **todos assertam comportamento de
> backend** (`HasBusinessScope`, `isCancelavel`, mapa `sefazCodes`, validações do `AcoesController`,
> superfície de métodos/rota/Services) — **nenhum depende de cor, token ou do `.tsx`**, logo nenhum
> aceite mudou. **Nenhum teste foi re-executado** (Pest = CT 100); os vereditos seguem como estavam.
> _Este bump vai no MESMO commit do `.tsx` de propósito: o G-6 isenta por SHA igual, que é a forma
> que sobrevive ao squash-merge reescrever a data (o caminho por data cai de novo no dia seguinte)._

> **Revalidação `last_run` 2026-08-28 — o que foi conferido:** este PR muda a tela em **um único ponto**: o atributo `data-contract="fiscal-nfe-filters"` no wrapper, âncora do mapa [`fiscal-nfe.map.json`](../../../../memory/requisitos/Fiscal/fiscal-nfe.map.json). Conferi o diff do `.tsx` contra a lista de UC deste arquivo — **nenhum UC depende de atributo de DOM**, logo nenhum aceite mudou. **Nenhum teste foi re-executado** nesta revalidação (Pest = CT 100); os vereditos seguem como estavam.

> Persona: **Eliana (contadora)** + operador fiscal. Cockpit Fiscal (agregador thin sobre NfeBrasil).
> Âncora de contrato: SPEC `US-FISCAL-001` (lista) + `US-FISCAL-012` (cancelar/DF-e) +
> `US-FISCAL-013` (CC-e/inutilizar) + `US-FISCAL-014` (retransmitir) + `Nfe.charter.md`.
> Leis citadas: cancelamento e CC-e pelo **Ajuste SINIEF 07/2005** (janela legal; CC-e com texto de
> 15 a 1000 caracteres e até 20 sequências por NF-e); janela de 24h para NFC-e (modelo 65) e 168h
> para NF-e (modelo 55).
>
> **Status:** ✅ passa (UC-id com veredito `pass` no manifesto) · 🧪 UC citado por teste, veredito
> ainda não capturado no manifesto · ⬜ não verificado · ❌ quebrou.

## O que mudou aqui em 2026-07-27 (e por quê)

A versão anterior deste arquivo dizia que o débito era "**rastreabilidade, não ausência de teste**",
apoiada em "17 casos reais". A medição desmontou as duas metades:

| Afirmação anterior | O que a medição mostrou |
|---|---|
| "17 casos reais defendem o comportamento" | **13 dos 18** casos do `AcoesControllerTest` eram **tautológicos** — montavam um `validator([...], [...])` LOCAL com as regras reescritas à mão, ou assertavam um array literal declarado na linha acima. Não tocavam o `AcoesController`: trocar `min:15` por `min:5` **não os derrubava**. _(11 removidos em 2026-07-28 por decisão [W]; 2 ficaram por ancorarem UCs da tela `Fiscal/Dfe` — ver §Trilha do tempo.)_ |
| "o comportamento de leitura já é defendido" | O caso `isCancelavel` declarava um `$isCancelavel = function (...)` que **re-implementava a fórmula do Controller** e testava esse clone. |
| "os de validação/whitelist rodam sempre" | **Falso.** Medido: `NfeCockpitMultiTenantTest` + `AcoesControllerTest` = **21 skipped, 0 passed** no CT 100 — um `beforeEach` de arquivo skipava tudo quando faltava `nfe_emissoes`, inclusive os casos que nunca consultam banco. |

**O que foi feito:** as regras ganharam um contrato REAL em `AcoesContratoTest` (invoca os métodos do
Controller), o guard de banco desceu para os casos que de fato precisam dele, e o caso-espelho do
`isCancelavel` foi removido em favor do que invoca o método real. Resultado medido no mesmo dia:
**33 passed / 1 skipped** (o único que segue skipando é o que realmente precisa de `nfe_emissoes`).

## Recibo (medido 2026-07-27 — re-rode, não edite os números)

```
tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging \
  php artisan test Modules/Fiscal/Tests/Feature/AcoesContratoTest.php \
    Modules/Fiscal/Tests/Feature/AcoesControllerTest.php \
    Modules/Fiscal/Tests/Feature/NfeCockpitMultiTenantTest.php"
```

| Momento | Resultado |
|---|---|
| Antes | **21 skipped · 0 passed** |
| Depois | **33 passed · 1 skipped** (109 asserções) |
| Mordida provada (contrafactual) | afrouxar `min:15` → `min:5` no `AcoesController::cancelarNfe` deixa `UC-FNFE-04` **vermelho** (1 failed / 13 passed); Controller restaurado sem drift |

**Limite honesto:** o caso Tier 0 de isolamento (`UC-FNFE-01`) precisa de `nfe_emissoes` e **não
executa em nenhuma lane disponível hoje** — a lane de CI é SQLite in-memory e o staging do CT 100
não tem as migrations do NfeBrasil. É lacuna de ambiente, não defeito do teste.

## Rastreabilidade

| UC | O que defende | Prio | CU (SDD §6) | Teste que o cita | Status |
|---|---|---|---|---|---|
| UC-FNFE-01 | a contagem não vaza outro business | `[must]` `[T0]` | CU-FISC-12 | `NfeCockpitMultiTenantTest` | 🧪 |
| UC-FNFE-02 | janela legal 24h NFC-e / 168h NF-e | `[must]` `[reg]` | CU-FISC-03 | `AcoesContratoTest` | 🧪 |
| UC-FNFE-03 | o código SEFAZ vira status legível — e nunca "Status" nu | `[must]` | CU-FISC-02 | `NfeCockpitMultiTenantTest` (4) · `fiscal-nfe-sefaz-pilula.test.tsx` (7) | 🧪 |
| UC-FNFE-04 | cancelar exige motivo de 15 a 255 chars | `[must]` | CU-FISC-08 | `AcoesContratoTest` | 🧪 |
| UC-FNFE-05 | CC-e: texto 15–1000, sequência 1–20 | `[must]` | CU-FISC-09 | `AcoesContratoTest` | 🧪 |
| UC-FNFE-06 | inutilização valida modelo, faixa e justificativa | `[must]` | CU-FISC-10 | `AcoesContratoTest` | 🧪 |
| UC-FNFE-07 | manifestação: 4 ações, justificativa condicional | `[must]` | CU-FISC-07 | `AcoesContratoTest` | 🧪 |
| UC-FNFE-08 | ⚠️ **id sobrecarregado** — (a) a superfície das ações existe · (b) o gate `fiscal.nfe.view` devolve 403 | `[must]` `[T0]` | CU-FISC-13 (só o gate) · ver nota | `AcoesControllerTest` (5) · `GatesPermissaoFiscalTest` (2) | 🧪 |
| UC-FNFE-09 | retransmitir preserva a nota antiga (nunca deleta) | `[must]` `[reg]` | CU-FISC-11 | `AcoesContratoTest` | 🧪 |
| UC-FNFE-10 | a lista é operável só pelo teclado (linha focável, não botão) | `[must]` | **—** (ver nota) | `fiscal-nfe-teclado.test.tsx` | 🧪 |
| UC-FNFE-11 | nenhum ícone decorativo chega ao leitor de tela | `[must]` | **—** (mesma nota) | `fiscal-nfe-teclado.test.tsx` | 🧪 |
| UC-FNFE-12 | a lista não promete o que não tem (separador solto · tecla sem handler) | `[should]` | **—** (mesma nota) | `fiscal-nfe-teclado.test.tsx` (5) | 🧪 |
| UC-FNFE-14 | a densidade escolhida acompanha a navegação entre as telas de notas | `[should]` | **—** (mesma nota) | `fiscal-densidade.test.tsx` | 🧪 |
| UC-FNFE-15 | nota em contingência (tpEmis 4/9) sai com `dhCont` + `xJust` da ativação | `[must]` | **—** (US-NFE-070) | `ContingenciaAgendamentoTest` | 🧪 |
| UC-FNFE-16 | nota normal (tpEmis 1) não leva `dhCont`/`xJust` | `[must]` `[reg]` | **—** (US-NFE-070) | `ContingenciaAgendamentoTest` | 🧪 |
| UC-FNFE-17 | o agendamento só retransmite quem tem nota pendente e SEFAZ no ar | `[must]` | **—** (US-NFE-070) | `ContingenciaAgendamentoTest` | 🧪 |
| UC-FNFE-18 | o job de um business nunca transmite nota de outro | `[must]` `[T0]` | **—** (US-NFE-070) | `ContingenciaAgendamentoTest` | 🧪 |
| UC-FNFE-19 | SVC-AN/SVC-RS: a NF-e sai com tpEmis 6/7 e vai na hora para a SEFAZ Virtual da UF | `[must]` `[fiscal]` | **—** (US-NFE-071) | `ContingenciaModoTest` (7) | 🧪 |
| UC-FNFE-20 | EPEC: o evento 110140 é registrado no Ambiente Nacional e a nota fica guardada | `[must]` `[fiscal]` | **—** (US-NFE-071) | `ContingenciaModoTest` (2) | 🧪 |
| UC-FNFE-30 | EPEC recusado ou AN fora: a nota não se perde e o motivo diz que o DANFE ainda não vale | `[must]` | **—** (US-NFE-071) | `ContingenciaModoTest` (2) | 🧪 |
| UC-FNFE-31 | o EPEC de uma empresa grava evento só nela | `[must]` `[T0]` | **—** (US-NFE-071) | `ContingenciaModoTest` | 🧪 |
| UC-FNFE-21 | fatura paga emite a NF-e sem ninguém clicar (UC-FLX-26) | `[must]` `[fiscal]` | CU-RB-09 itens 1-2 (SDD RecurringBilling) | `EmitirNFeAoReceberPagamentoTest` (4) | 🧪 |
| UC-FNFE-22 | com a chave desligada (o padrão) ou fatura não encontrada, nenhuma nota sai (UC-FLX-26) | `[must]` `[fiscal]` | CU-RB-09 item 2 | `EmitirNFeAoReceberPagamentoTest` (2) | 🧪 |
| UC-FNFE-23 | SEFAZ fora não derruba o pagamento: a emissão volta pra fila (UC-FLX-26) | `[must]` `[fiscal]` | CU-RB-09 item 3 | `EmitirNFeAoReceberPagamentoTest` (3) | 🧪 |

> **Por que esta tabela nasceu em 2026-09-01 (e o que ela NÃO fez):** os 8 UC desta tela já eram
> provados por teste desde 2026-07-27 — nenhum deles declarava, porém, **qual CU do SDD §6 atende**.
> Efeito no painel derivado ([`_STATUS-GENERATED.md`](../../../../memory/requisitos/Fiscal/_STATUS-GENERATED.md)):
> `CU-FISC-02/03/08/09/10` apareciam como "sem UC" tendo comportamento provado, e `UC-FNFE-04`/`UC-FNFE-07`
> saíam atribuídos a `Eventos`/`Dfe` — as telas irmãs os citam em prosa (corretamente, dizendo que o
> contrato mora aqui) e o gerador cai no fallback alfabético quando a tela **dona** não os declara em
> tabela. **Nenhuma asserção de teste mudou:** isto é rastreabilidade, não comportamento.

> **Por que o `UC-FNFE-10` tem `—` na coluna CU (e não um CU plausível):** os 16 CU do SDD §6
> cobrem *o que a pessoa fiscal faz* (conferir status, cancelar, manifestar, inutilizar,
> retransmitir, isolar tenant) — **nenhum** trata de acessibilidade ou de operação por teclado. O
> mais próximo, `CU-FISC-02`, é sobre o status SEFAZ ser **legível**, não sobre a lista ser
> **navegável**. Ancorar ali fecharia a lacuna do painel derivado sem lastro — a classe LC-11 que
> este projeto persegue. Abrir um CU de acessibilidade no SDD é decisão do dono daquele documento,
> não desta onda; até lá o travessão é a resposta honesta. A âncora de contrato do UC existe e está
> declarada na seção dele: o **Goal 5 + §UX targets do charter**.

> **⚠️ `UC-FNFE-08` ancora DOIS contratos diferentes (achado de 2026-09-01).** O mesmo id nomeia
> 5 casos em `AcoesControllerTest` (a superfície das ações) **e** 2 casos em
> `GatesPermissaoFiscalTest:72,79` (o gate `fiscal.nfe.view` devolve 403, com controle negativo em
> `:79` — superadmin **não** recebe 403). São contratos distintos sob um id só: o painel derivado
> conta **um** requisito e o leitor não tem como saber qual dos dois um `❌` reprovou. Separar em
> dois ids mexe no nome de casos em **dois** arquivos de teste — intent próprio, não escopo desta
> onda. Fica registrado aqui para não virar "descoberta" futura.
>
> **Por que a coluna CU diz "só o gate":** a metade-gate atende o `CU-FISC-13` (gate de permissão
> por sub-feature), e é isso que a tabela declara. A metade-superfície não atende CU nenhum —

> rota `fiscal.acoes.nfe.retransmitir` registrada, assinatura `NfeService::retransmitir(int,int)` e os
> Services no NfeBrasil. Isso **não** é o que o `CU-FISC-11` pede: preservação da nota antiga
> (`[reg]` — `forceDelete()` nunca usado, CONFAZ SINIEF 07/2005 Art. 14) e recusa de status fora de
> `{rejeitada, denegada, erro_envio}` (`[must]`). Ancorar aquele CU nesta linha fecharia a lacuna do
> painel **sem lastro de comportamento** — a classe LC-11 (presença ≠ comportamento) que este projeto
> persegue. Quem fecha o `CU-FISC-11` é o **`UC-FNFE-09`**, escrito para os dois itens dele — e com o
> limite declarado ali: a invariante é provada **estaticamente**, o caminho de runtime segue no
> backlog abaixo, porque nenhuma lane de hoje tem `nfe_emissoes`.

## UC-FNFE-01 — A contagem do cockpit nunca mostra nota de outro business (Tier 0)
Status: 🧪 (`NfeCockpitMultiTenantTest::UC-FNFE-01 · global scope HasBusinessScope…` — **skipa** hoje, ver §recibo)
Dado 1 emissão do biz=1 e 2 do biz=99 · Quando a lista conta na sessão do biz=1 · Então vê **1**,
enquanto `withoutGlobalScopes` vê 3. Âncora: ADR 0093 + charter anti-hook "não acessar NfeEmissao sem
global scope". Teste com biz=1 e biz=99 fictício, nunca biz=4 (ADR 0101).
**Pronto quando:** a contagem escopada e a não-escopada divergem exatamente nas notas do outro tenant.

## UC-FNFE-02 — A janela legal de cancelamento é 24h na NFC-e e 168h na NF-e
Status: 🧪 (`AcoesContratoTest::UC-FNFE-02 · isCancelavel do Controller respeita 24h NFC-e vs 168h NF-e` — **passa**)
Dado nota autorizada · Quando a tela calcula se ainda dá pra cancelar · Então NFC-e (modelo 65) é
cancelável só até 24h e NF-e (modelo 55) até 168h da emissão; nota não-autorizada nunca é cancelável.
Âncora: charter Goal 3 + Ajuste SINIEF 07/2005.
**Pronto quando:** 10h/30h no modelo 65 e 48h/200h no modelo 55 dão true/false/true/false, medidos
pelo método **do Controller** (não por uma cópia da fórmula dentro do teste).

## UC-FNFE-03 — O código SEFAZ vira status legível com o tom certo
Status: 🧪 (`NfeCockpitMultiTenantTest::UC-FNFE-03 · …` 4 casos — **passam** · CT 100, 20 assertions ·
`fiscal-nfe-sefaz-pilula.test.tsx` 7 casos — **passam** · lane `Fiscal Teclado Gate`)

Dado a lista de notas · Quando renderiza a pílula SEFAZ · Então **nenhuma** linha mostra rótulo
genérico: o cStat conhecido vira o **texto oficial da SEFAZ**, o desconhecido vira o status de
domínio com o número preservado, e a nota sem cStat vira só o status de domínio.
O tooltip traz o `motivo` que a SEFAZ gravou naquela nota.

**Pronto quando:** o rótulo (`.fx-sefaz .lbl`) nunca é `"Status"` nem vazio, e o texto de cada
código vem da tabela oficial — não de um apelido escrito à mão.

**Âncora de contrato (externa ao código):** Manual de Orientação do Contribuinte / tabela cStat da
SEFAZ, distribuída em `vendor/nfephp-org/sped-nfe/storage/cstat.json` (528 códigos) com o SDK que o
projeto usa pra transmitir. É de lá que o `SefazCstatService` deriva rótulo e tom (`status: "1"` =
aceito), e é contra os textos do MOC — transcritos no teste — que o caso asserta.

### Reescrito em 2026-09-04 — a redação anterior descrevia o defeito, não o contrato

A versão de cima dizia *"contém ao menos 100/110/220/539/691/778/999, com 691 = `warn`"*. Duas
coisas estavam erradas ali, e o teste que a implementava passava verde sobre as duas:

| O que o caso pedia | O que era verdade | Como se soube |
|---|---|---|
| presença de 7 chaves | presença **não** é legibilidade — o mapa tinha 778 e a tela dizia "CST/CFOP inválido" quando o texto oficial é "Informado NCM inexistente" | tabela cStat oficial; e a própria SEFAZ gravou `motivo="Rejeicao: Informado NCM inexistente [nItem:1]"` na nota id=8 de biz=1 |
| `691 = warn` | 691 é **rejeição** (`status: "0"` na tabela oficial) — o âmbar vinha de acreditar que o código era "NCM divergente"; ele é "Chave de Acesso da NF-e diverge da Chave de Acesso do EPEC" | idem |

O tom deixou de ser digitado e passa a ser **derivado** do campo `status` da tabela, com âmbar só
para transitório/indisponibilidade (103/105/108/109) — decisão de UI declarada no serviço. Isso
**cumpre** o UX target do charter (*"vermelho = rejeição"*) melhor que a lista literal cumpria: o
691 estava no âmbar por erro de classificação, não por escolha. Precedência aplicada — *teste verde
> casos > charter > SPEC* — com o charter reconciliado no mesmo PR.

**Mordida provada (contrafactual, os dois lados):** reintroduzir os apelidos à mão no
`SefazCstatService` deixa `UC-FNFE-03` **vermelho no Pest** (`Failed asserting that two strings are
identical`, 1 failed / 4 passed); reintroduzir o `?? { label: 'Status' }` no `Nfe.tsx:287` deixa
**4 dos 7 casos vitest vermelhos** (`expected 'Status' not to be 'Status'`). Restaurar devolve
5/5 e 7/7 — controle positivo rodado nos dois sentidos.

**Limite honesto:** o Pest prova a ORIGEM (o backend traduz pela tabela oficial) e o vitest prova o
CONSUMO (a tela nunca mostra rótulo genérico). Nenhum dos dois sozinho fecha o UC, e o vitest monta
o mapa à mão — se o backend parar de servi-lo, quem cai é o Pest.

## UC-FNFE-04 — Cancelar exige motivo de 15 a 255 caracteres
Status: 🧪 (`AcoesContratoTest::UC-FNFE-04 · REJEITA <15` / `REJEITA >255` / `ACEITA válido` — **passam**)
Dado o pedido de cancelamento · Quando a justificativa tem menos de 15 ou mais de 255 caracteres ·
Então o Controller recusa com erro no campo `motivo`; com motivo válido, a validação não barra.
Âncora: US-FISCAL-012 + Ajuste SINIEF 07/2005 (justificativa mínima).
**Pronto quando:** a asserção usa a regra **do Controller** — afrouxá-la lá derruba este UC.

## UC-FNFE-51 — Cancelar pergunta o escopo: só a nota ou a venda junto · `must`

**Dado** uma NF-e autorizada **Quando** o fiscal cancela **Então** escolhe "só a nota" (para reemitir; o padrão, como
antes) ou "venda junto"; qualquer outro valor não passa da validação. Âncora: R-NFE-044 · decisão [W] 2026-10-26.

Status: 🧪 (`AcoesCancelarEscopoTest` — `UC-FNFE-51 · …`)

## UC-FNFE-52 — "Venda junto" cancela a venda pelo fluxo, só na própria empresa · `must` `[T0]`

**Dado** a nota de uma venda da empresa no fluxo de vendas **Quando** o fiscal escolhe "venda junto" **Então** a venda
recebe a ação `cancelar_venda` com o mesmo motivo (o cascade estorna boleto pendente, libera a reserva de estoque e avisa
o cliente); venda fora do fluxo não é cancelada e a mensagem manda cancelar em Vendas; venda de outra empresa não é
achada. Com a US-SELL-077 (#9911) o cancelamento da venda devolve o estoque e cancela o título sem pagamento.

Status: 🧪 (`AcoesCancelarEscopoTest` — `UC-FNFE-52 · …`, MySQL, tenant 98 × 99)

## UC-FNFE-54 — Venda com dinheiro recebido: nada é cancelado, nem a nota · `must` `[T0]`

**Dado** a nota de uma venda com um pagamento recebido **Quando** o fiscal escolhe "venda junto" **Então** a resposta diz
"Nada foi cancelado." com a pendência do cancelamento da venda ("A venda tem … recebidos…"), a nota segue autorizada e
a SEFAZ nem é chamada. Âncora: R-NFE-044 · US-SELL-077.

Status: 🧪 (`AcoesCancelarEscopoTest` — `UC-FNFE-54 · …`; pula sem o `CancelamentoDaVenda` do #9911, verde com ele no CT 100)

## UC-FNFE-05 — Carta de Correção respeita texto 15–1000 e sequência 1–20
Status: 🧪 (`AcoesContratoTest::UC-FNFE-05 · texto fora de 15–1000` / `n_seq fora de 1–20` / `aceita válido` — **passam**)
Dado uma CC-e · Quando o texto sai da faixa de 15 a 1000 caracteres, ou a sequência sai de 1 a 20 ·
Então recusa apontando o campo; dentro das faixas, passa. Âncora: US-FISCAL-013 + SINIEF 07/2005
Art. 14 (limite de 20 CC-e por NF-e). CC-e corrige texto — **nunca valor**.
**Pronto quando:** 'curto', 1001 chars, e as sequências 0/21/-1/100 falham no campo certo.

## UC-FNFE-06 — Inutilização valida modelo, faixa e justificativa
Status: 🧪 (`AcoesContratoTest::UC-FNFE-06 · modelo fora de 55/65` / `faixa invertida` / `justificativa <15` / `aceita válido` — **passam**)
Dado a inutilização de uma faixa numérica · Quando o modelo não é 55 nem 65, a faixa está invertida
(`numero_ate < numero_de`), ou a justificativa tem menos de 15 caracteres · Então recusa no campo
correspondente; payload válido passa nos dois modelos. Âncora: US-FISCAL-013.
**Pronto quando:** os 3 caminhos de recusa apontam `modelo` / `numero_ate` / `justificativa`.

## UC-FNFE-07 — Manifestação DF-e aceita 4 ações e só exige justificativa em duas
Status: 🧪 (`AcoesContratoTest::UC-FNFE-07 · whitelist` / `EXIGE justificativa` / `NÃO exige` — **passam**)
Dado a manifestação do destinatário · Quando a ação não é `cienciar`/`confirmar`/`desconhecer`/
`nao_realizada` · Então 404. Quando é `desconhecer` ou `nao_realizada` · Então justificativa é
obrigatória (≥15 chars); `cienciar` e `confirmar` não a exigem. Âncora: US-FISCAL-012.
**Pronto quando:** as 4 ações canon passam pela whitelist e a exigência condicional bate.
_(A tela DF-e propriamente dita é a sub-página `Fiscal/Dfe` — aqui só o contrato da ação.)_

## UC-FNFE-08 — A superfície de ações existe: métodos, rota e Services
Status: 🧪 (`AcoesControllerTest::UC-FNFE-08 · …5 métodos públicos` / `…signature int/int → NfeEmissao` / `…route POST registrada` / `NfeCartaCorrecaoService…` / `NfeInutilizacaoService…` — **passam**)
Dado o contrato do módulo · Quando outra camada chama uma ação fiscal · Então o `AcoesController`
expõe `cancelarNfe`/`manifestarDfe`/`cartaCorrecao`/`inutilizar`/`retransmitir`, a rota
`fiscal.acoes.nfe.retransmitir` está registrada, `NfeService::retransmitir(int,int): NfeEmissao` tem a
assinatura canônica, e a lógica de CC-e/inutilização mora nos Services do NfeBrasil (não duplicada no
Fiscal). Âncora: US-FISCAL-013/014.
**Pronto quando:** os 5 métodos, a rota e os 2 Services existem com as assinaturas esperadas.

## UC-FNFE-09 — Retransmitir preserva a nota antiga: nunca deleta, e só 3 status passam
Status: 🧪 (`AcoesContratoTest::UC-FNFE-09 · PRESERVA a nota antiga` + `· whitelist de status vem do Service` — **novos**, ainda sem veredito de lane)
Dado uma nota `rejeitada`/`denegada`/`erro_envio` · Quando o operador retransmite · Então a nota antiga
**continua na tabela** — marcada `status='inutilizada'` com `transaction_id=null` (libera a UNIQUE
biz+tx) e `metadata.original_transaction_id` preservando o vínculo — e uma nova é emitida com número
novo. Nota em qualquer outro status é recusada antes de chamar o Service.
Âncora: CU-FISC-11 do SDD §6.2 (os dois itens) + US-FISCAL-014 + **CONFAZ SINIEF 07/2005 Art. 14**
(documento fiscal é imutável — remoção física é proibida).
**Pronto quando:** o corpo de `NfeService::retransmitirInterno` não contém `forceDelete` / `->delete(`
/ `::destroy(`, contém o `update` de preservação, e declara a whitelist exata dos 3 status usada num
`in_array` que lança `InvalidArgumentException`.

**Limite honesto — o que este UC NÃO prova.** A asserção é **estática**: ela lê o corpo do método de
produção por reflection e verifica a invariante no fonte. Ela **não** exercita o runtime, porque não
dá: tanto `AcoesController::retransmitir` quanto `NfeService::retransmitirInterno` fazem a query
(`firstOrFail()` / `find()`) **antes** de checar o status, e `nfe_emissoes` não existe nem na lane
SQLite do CI nem no staging do CT 100. O que ela garante é o que importa contra regressão silenciosa:
trocar o `update` por um delete, ou mexer na whitelist, **derruba o caso**. A diferença para o
`NfeServiceRetransmitirTest` (que segue no repo) é essa — lá a whitelist é declarada dentro do próprio
teste, então ele continuaria verde se o Service mudasse; é a lápide §5 2026-06-05. O caso de runtime
permanece declarado no backlog abaixo.

## UC-FNFE-10 — A lista é operável só pelo teclado: a linha é focável, e não virou botão
Status: 🧪 (`tests/js/fiscal-nfe-teclado.test.tsx` — 6 casos, **passam**; lane `Fiscal Teclado Gate`)
Dado a lista de notas · Quando o operador percorre as linhas com Tab e pressiona **Enter** ou
**Space** · Então a linha focada abre o drawer **daquela** nota (não a primeira da lista), o Space
**não rola a página**, e o anel de foco do Tab é o **mesmo** cursor do J/K — um anel, não dois.
A `<tr>` permanece com o papel implícito `row`: o alvo é linha **focável**, nunca `role="button"`,
que destruiria a semântica de tabela para leitor de tela.
Âncora: `Nfe.charter.md` **Goal 5** ("atalhos J/K + Enter pra navegar lista e abrir drawer") +
§UX targets ("linha cursor com `outline: 2px solid var(--fis)`") + o item de §Backlog abaixo.
**Pronto quando:** as 4 mutações abaixo derrubam o caso e o restore devolve 6/6.

**Mordida provada (contrafactual medido 2026-09-03 — restore byte-idêntico ao backup = 6/6):**

| Mutação no `Nfe.tsx` | Efeito |
|---|---|
| remover `tabIndex={0}` | o foco cai no `<body>` → *"linha 0 não recebeu foco"*, 1 failed |
| remover `e.preventDefault()` | *"Space NÃO teve o default cancelado — a página rolaria"*, 1 failed |
| remover o `onKeyDown` inteiro | 1 failed (só o Space — ver limite abaixo) |
| remover `onFocus={() => setCursor(idx)}` | o anel para na 2ª linha em vez da 3ª, 1 failed |

**Limite honesto — o que este UC NÃO prova.** A mutação 3 mostrou que o **Enter é servido por dois
caminhos**: o `onKeyDown` da linha e o handler global de `window` (o J/K, que já existia). Com o
cursor sincronizado pelo `onFocus`, remover o handler da linha **não** derruba o caso do Enter — o
global abre a mesma nota. O que o caso prova do Enter é o que o operador **observa** (abre a nota da
linha focada), e isso vale por qualquer um dos dois caminhos; o valor próprio do handler da linha é
não depender do listener de `window`, e o `stopPropagation` é o que evita a abertura dupla quando os
dois veem a tecla. Segundo limite: o jsdom não implementa a travessia por Tab do browser, então
"Tab alcança todas" é medido como "toda linha é de fato focável, na ordem do DOM" — a condição que
a torna possível. A travessia física, o anel pintado e o leitor de tela são olho humano no smoke (R1).

## UC-FNFE-11 — Nenhum ícone decorativo chega ao leitor de tela
Status: 🧪 (`tests/js/fiscal-nfe-teclado.test.tsx` — **passa**; lane `Fiscal Teclado Gate`)
Dado a tela renderizada · Quando um leitor de tela percorre a `.fx-page` · Então **nenhum**
`<svg>` decorativo é anunciado: cada um declara `aria-hidden` (nele ou num ancestral).
Âncora: `fiscal-page.jsx` do Cowork vivo, que em 2026-09-03 passou a envolver o ícone em
`<span className="fx-i" aria-hidden="true">` com a nota *"A3 · ícone decorativo nunca entra na
árvore de acessibilidade (medido: 4 de 4 svg sem aria-hidden)"*.

**Por que o caso mede o DOM e não o `.tsx`:** sonda de 2026-09-03 renderizou `<RefreshCw/>` e leu
os atributos do `<svg>` — `aria-hidden=null`, `role=null`, `focusable=null`. **O lucide não declara
nada sozinho**, então a ausência no fonte não bastaria como prova e a presença também não: só o
DOM renderizado responde.

**Escopo declarado:** a asserção varre a `.fx-page` — o que ESTA tela desenha, incluindo o shell e
a paleta ⌘K que ela monta. Ficaram fora as outras 6 telas do Fiscal, que renderizam os seus
próprios ícones e são de outra onda.

**Mordida provada (contrafactual 2026-09-03):** remover o `aria-hidden` de **um** ícone da subnav
(`<Receipt>` em `_lib/paginas-fiscais.tsx`) derruba o caso apontando exatamente aquele
(`['lucide lucide-receipt']`); restaurado, volta a 7/7.

**Cuidado que o caso NÃO dispensa:** `aria-hidden` em ícone que é o ÚNICO conteúdo de um controle
**remove o nome acessível** dele. Cada um dos 13 foi conferido antes: os 5 da tela e os 7 da subnav
vêm com texto ao lado; o `<X>` da paleta está num botão que já declara `aria-label="Fechar (ESC)"`.

## UC-FNFE-12 — A lista não promete o que não tem
Status: 🧪 (`tests/js/fiscal-nfe-teclado.test.tsx` — 5 casos, **passam**; lane `Fiscal Teclado Gate`)

Dois defeitos da mesma família, achados por [W] no screenshot de `/fiscal/nfe` em produção
(biz=1, 2026-09-04): a tela ocupando espaço com informação que não existe.

**(a) Separador entre dois nadas.** Dado nota sem destinatário e sem documento · Quando renderiza a
célula · Então mostra `—`, nunca `— · —`. O Controller manda `dest: '—'` (fallback declarado no
charter, R2) e o `formatDoc` devolve `'—'` — dois fallbacks individualmente CORRETOS que somavam um
terceiro errado. Em biz=1 é o caso das notas rejeitadas, que não chegaram a ter destinatário gravado.

**(b) Tecla anunciada sem handler.** Dado o rodapé de atalhos · Quando o operador lê as teclas ·
Então toda tecla listada tem handler. `R` ("reconsultar SEFAZ") e `X` ("cancelar") estavam lá
rotuladas `(em breve)` e o `keydown` desta tela trata só `j`/`k`/setas/`Enter` — varredura contada
no `Nfe.tsx`: 0 handlers para as duas.

**Pronto quando:** nenhuma célula de informação começa/termina com `·` nem contém `— ·`, e o
`.fx-cheatsheet` não anuncia tecla que a tela ignora.

**Por que (b) é defeito, se o rótulo era honesto:** a barra de atalhos é **onde o operador aprende
as teclas**. Duas mortas ali ensinam errado — ele aperta, nada acontece, e conclui que a tela
travou. As duas AÇÕES existem e seguem intactas no drawer (US-FISCAL-012/014); o que não existe é a
tecla. Reatalhar é decisão de produto, não conserto: o `X` abre um fluxo que exige motivo de 15–255
chars, então não é uma tecla, é uma porta.

**Mordida provada (contrafactual, os dois lados):** reintroduzir `{n.dest} · {formatDoc(…)}` derruba
2 casos com `expected '— · —' to be '—'` e `expected 'Gráfica Ribeirão · —' to be 'Gráfica Ribeirão'`;
devolver `R`/`X` à barra derruba o terceiro (`not to contain 'em breve'`). Restaurados, 12/12.

**Controle negativo incluído:** um caso dispara `r`/`R`/`x`/`X` na window e assere que o DOM não
muda — prova que remover da barra descreve a realidade, em vez de esconder um atalho vivo. E um
caso de não-regressão: com destinatário E documento, a célula segue mostrando os dois.
## UC-FNFE-14 — A densidade escolhida acompanha a navegação entre as telas de notas
Status: 🧪 (`tests/js/fiscal-densidade.test.tsx` — **passa**, 6/6; lane `Fiscal Densidade Gate`)
Dado o operador na lista de NF-e · Quando escolhe **Compacto** e navega para o Cockpit ou para a
NFS-e · Então a tabela de lá já abre compacta — a preferência é dele, não da tela.

Âncora: a fonte de design faz as três telas serem a **mesma função** — `FxNotasPage`, chamada com
`preset` diferente ([`fiscal-page.jsx:346,541-543`](../../../../prototipo-ui/cowork/Wagner/fiscal-page.jsx)) — e
persiste a escolha em `fxLS("oimpresso.fiscal.densidade")` (`:358,363`). Lá o compartilhamento é
grátis; aqui a produção separou em três arquivos, então a propriedade precisa ser defendida.

**O estado que este caso corrige (medido em `origin/main` d23bc3df34):** o controle existia **só no
Cockpit**, e com `useState<Density>('comfort')` — estado efêmero. A escolha morria ao trocar de tela,
e NF-e/NFS-e não tinham controle nenhum (`fx-density` = 0 nas duas).

**Por que o caso é de RENDER, e não um assert sobre o texto do `.tsx`:** procurar o nome do
componente nas telas provaria que o import foi **escrito** — presença, não comportamento (LC-11),
e ficaria verde no instante em que alguém digitasse a linha. Aqui a NFS-e é montada **depois** de
a NF-e ser desmontada, e a asserção lê a classe que o CSS de fato consome — o que torna o caso
uma prova de travessia, e não de estado compartilhado em memória.

**Mordida provada (contrafactual 2026-09-04):** devolver `useState('comfort')` à NFS-e — o defeito
exato que o Cockpit tinha em `origin/main` — derruba **2** casos, com a mensagem nomeando o
sintoma (*"a NFS-e ignorou a escolha feita na NF-e: expected 'comfort' to be 'compact'"*); divergir
a chave da fonte de design derruba **1**, nomeando as duas chaves. Restaurado, 6/6 verde.

**O que este caso NÃO cobre:** a navegação HTTP real entre as rotas — o jsdom não a faz. O que os
casos provam é a parte que **carrega** a preferência (mesma origem, mesmo storage, a tela nova
lendo o que a anterior gravou); a troca de página com o Inertia no meio é olho humano no smoke (R1).
O Cockpit não é renderizado (recebe ~15 props de payload) e entra por um caso estático de fonte
única — perde-se a prova do repinte dele, não a condição da travessia.

**Por que `—` na coluna CU:** vale aqui a mesma nota do `UC-FNFE-10` — os 16 CU do SDD §6 tratam do
que a pessoa fiscal **faz** (conferir, cancelar, manifestar, inutilizar); nenhum trata de
preferência de exibição. Ancorar num CU plausível fecharia a lacuna do painel sem lastro (LC-11).

## Item do regime normal com base e alíquota em percentual (R-NFE-040)

> **Âncora:** R-NFE-040 do [SPEC do NfeBrasil](../../../../memory/requisitos/NfeBrasil/SPEC.md). Prova:
> `NfeAliquotaPercentualTest` (sem banco, motor falso com alíquotas em fração como a régua guarda).

## UC-FNFE-42 · Regime normal: base cheia e alíquota em percentual · `must`

**Dado** uma regra CST 00 com ICMS 0,18, PIS 0,0165 e COFINS 0,076 **Quando** um item de 1.000,00 vai à nota
**Então** sai vBC 1.000,00, pICMS 18, vICMS 180,00, pPIS 1,65, pCOFINS 7,6 e o total vBC 1.000,00; com base
reduzida pelo benefício (600,00), a base é 600,00.

Status: 🧪

## UC-FNFE-43 · Simples e CST sem ICMS ficam como antes · `must`

**Dado** CSOSN 102, CST 40 ou CST 00 com alíquota zero **Quando** o item vai à nota **Então** a base do ICMS
fica 0 no item e no total.

Status: 🧪

## NF-e em contingência quando a SEFAZ cai (US-NFE-070 · UC-FLX-32)

> **Âncora:** [US-NFE-070](../../../../memory/requisitos/NfeBrasil/SPEC.md) (resto da US-NFE-006) e o
> UC-FLX-32 do catálogo de fluxos (etapa Validada → Transmitida: *"NF-e em contingência quando a SEFAZ
> cai"*). Nesta entrega: `dhCont`/`xJust` no XML e a retransmissão agendada (`nfe:retentar-contingencia`,
> a cada 5 min). SVC-AN/SVC-RS e o evento EPEC 110140 entraram em 2026-10-21 (US-NFE-071,
> UC-FNFE-19/20/30/31); a marcação de contingência no DANFE segue pendente na US.
> Coluna CU `—`: o SDD §6 do Fiscal não tem CU de contingência. Prova: `ContingenciaAgendamentoTest`
> e `ContingenciaModoTest`.

## UC-FNFE-15 · A nota em contingência leva quando e por quê entrou nela · `must`

**Dado** a contingência ligada às 14:05 com o motivo "SEFAZ-RR fora do ar desde 14h" **Quando** sai uma NF-e
(tpEmis 4) e uma NFC-e (tpEmis 9) **Então** as duas trazem `<dhCont>` com 14:05 do dia e `<xJust>SEFAZ-RR fora
do ar desde 14h</xJust>`. Motivo de 10 caracteres ("SEFAZ caiu") vira "Contingencia: SEFAZ caiu" (24), porque a
SEFAZ exige de 15 a 256.

Status: 🧪

## UC-FNFE-16 · A nota normal não muda · `must`

**Dado** a contingência desligada **Quando** sai uma NF-e **Então** o XML tem `<tpEmis>1</tpEmis>` e nenhum
`<dhCont>` nem `<xJust>`.

Status: 🧪

## UC-FNFE-53 · A NFC-e é assinada com o CSC que a empresa cadastrou · `must`

> **Âncora:** R-NFE-045 do [SPEC do NfeBrasil](../../../../memory/requisitos/NfeBrasil/SPEC.md). Prova: `NfceCscConfigTest`.

**Dado** a empresa com CSC e id do CSC cadastrados **Quando** a NFC-e vai ser assinada **Então** a configuração da
`sped-nfe` leva `CSC` e `CSCid` com esses valores; sem CSC cadastrado, leva vazio.

Status: 🧪

## UC-FNFE-17 · A retransmissão só sai quando há nota pendente e a SEFAZ responde · `must`

**Dado** a empresa A com 1 nota em contingência e a B só com nota autorizada **Quando** o agendamento roda e o
ping da SEFAZ de A volta cStat 107 **Então** só A é consultada, 1 job sai para A, nenhum para B, e a UF fica
`verde` em `nfe_sefaz_status`. Com cStat 108 (SEFAZ paralisada) nada é despachado nem gravado.

Status: 🧪

## UC-FNFE-18 · O job de uma empresa nunca transmite nota de outra · `must`

**Dado** uma nota em contingência em A e outra em B **Quando** roda o job de A **Então** só a nota de A é
transmitida e a de B continua `contingencia`.

Status: 🧪

## UC-FNFE-47 · Regime normal emite o grupo IBS/CBS sem precisar ligar nada · `must`

> **Âncora:** [ADR 0442](../../../../memory/decisions/0442-ibscbs-automatico-regime-normal-emenda-0321.md)
> (emenda a 0321) · US-FISCAL-021. Prova: `SerializacaoIbsCbsTest` (XML montado pelo `buildXml`).

**Dado** um emitente CRT 3 com `reforma_tributaria_modo = legacy` e regra com CST 000, cClassTrib 000001, IBS 0,1% e
CBS 8,8% **Quando** sai uma NF-e de 1.000,00 **Então** o item leva o grupo IBS/CBS (vIBSUF 1,00 · vCBS 88,00) e o vNF
continua 1.000,00.

Status: 🧪

## UC-FNFE-48 · Simples segue byte-idêntico · `must`

**Dado** um emitente CRT 1 ou 2 em `legacy` **Quando** sai a NF-e, com ou sem IBS/CBS na regra **Então** o XML é o
mesmo de antes, sem grupo.

Status: 🧪

## UC-FNFE-19 · No modo SVC a NF-e vai na hora para a SEFAZ Virtual da UF · `must`

**Dado** a contingência ligada em **SVC-AN** **Quando** sai uma NF-e **Então** o XML traz `<tpEmis>6</tpEmis>`
com `dhCont`/`xJust`, o lote é enviado com a lib armada em `SVCAN` (é isso que troca o webservice) e a nota
volta autorizada. Em **SVC-RS** é `<tpEmis>7</tpEmis>` e `SVCRS`. A NFC-e não tem SEFAZ Virtual: com SVC
ligado ela segue off-line (tpEmis 9) e não é enviada. Ativar a SVC que não atende a UF do emitente é
recusado (a outra SVC recusaria as notas), e a tabela UF → SVC é conferida contra a da `sped-nfe` nas 27 UFs.

Status: 🧪

## UC-FNFE-20 · No modo EPEC o evento 110140 é registrado antes de a nota ir à SEFAZ · `must`

**Dado** a contingência ligada em **EPEC** **Quando** sai uma NF-e **Então** ela fica guardada (`contingencia`,
tpEmis 4), o lote **não** vai à SEFAZ da UF, o evento 110140 é enviado com a lib armada em `EPEC` e fica
`autorizado` com cStat 136, e o protocolo vai para o metadata da nota. Quem ativou antes de existir a escolha
(modo vazio) segue em EPEC.

Status: 🧪

## UC-FNFE-30 · EPEC recusado ou AN fora do ar não perde a nota · `must`

**Dado** o modo EPEC **Quando** o AN devolve cStat de erro, ou o envio do evento lança exceção **Então** a nota
continua `contingencia` com o XML guardado, o evento 110140 fica `rejeitado` com o cStat ou o erro, e o motivo
da nota diz "O DANFE só vale com o EPEC registrado".

Status: 🧪

## UC-FNFE-40 · Devolução exige a permissão e quantidade válida · `must`

> **Âncora:** R-NFE-039 do [SPEC do NfeBrasil](../../../../memory/requisitos/NfeBrasil/SPEC.md) (rota da
> NF-e de devolução, US-NFE-064). Prova: `AcoesDevolucaoTest`, que invoca o `AcoesController`.

**Dado** uma NF-e autorizada **Quando** quem não tem `fiscal.nfe.acoes` pede a devolução **Então** 403. Sem item,
ou com quantidade 0, negativa ou não numérica, o pedido não passa da validação; 2,5 passa.

Status: 🧪

## UC-FNFE-41 · A devolução só acha nota da própria empresa · `must`

**Dado** uma nota autorizada da empresa A e outra da B **Quando** A pede a devolução da nota de B **Então** não é
achada (404) e nada é emitido; da nota de A, o serviço recebe a empresa A, a nota e as quantidades por item.

Status: 🧪

## UC-FNFE-31 · O EPEC de uma empresa grava evento só nela · `must`

**Dado** a empresa A em EPEC **Quando** sai uma NF-e de A **Então** o único evento da nota é de A, e a empresa B
não ganha evento 110140.

Status: 🧪

---

## A NF-e da venda sai para o cliente, com o DIFAL (US-NFE-072 · R-NFE-036/037)

> **Âncora:** [US-NFE-072](../../../../memory/requisitos/NfeBrasil/SPEC.md) e a regra R-NFE-036 do
> SPEC do NfeBrasil (leiaute 4.00, grupo E do destinatário e `idDest`) e a R-NFE-037 (grupo NA, ICMSUFDest). Mora nesta tela porque a nota
> emitida aparece nesta lista. Coluna CU `—`: o SDD §6 do Fiscal não tem CU de emissão a partir da
> venda. Prova: `NfeDestinatarioDaVendaTest` (sem banco: contato como objeto, motor falso) e
> `NfeDifalNoXmlTest` (XML montado pelo `buildXml`, sem SEFAZ).

## UC-FNFE-32 · Cliente identificado vira o destinatário, e o destino chega ao motor · `must`

**Dado** uma venda de SC para um cliente com CNPJ, IE e endereço no RJ **Quando** sai a NF-e (modelo 55)
**Então** o destinatário leva o CNPJ só com dígitos, a IE, o código IBGE e a UF RJ, com `ind_ie_dest` 1;
cliente sem IE é 9 e não leva IE; o indicador cadastrado (2, isento) prevalece. O motor recebe a UF RJ,
`contribuinte` e a interna do RJ; um item de 1.000,00 sai com CFOP 6102 e o vNF continua 1.000,00.

Status: 🧪

## UC-FNFE-33 · Sem cliente identificado a nota é a de antes · `must`

**Dado** um contato sem CPF/CNPJ, ou a NFC-e **Quando** sai a nota **Então** não há destinatário do cadastro,
o motor é chamado como sempre (mesma UF, CFOP 5102) e o vNF é o total da venda. UF inválida no cadastro
mantém a operação interna.

Status: 🧪

## UC-FNFE-34 · Venda a não contribuinte de outra UF leva o DIFAL na nota · `must`

**Dado** um item de 1.000,00 de SC para não contribuinte no RJ, ICMS interestadual 12%, interna do RJ 20% e
FCP 2% **Quando** sai a NF-e **Então** o item leva o grupo `ICMSUFDest` com base 1.000,00, interna 20,0000,
interestadual 12,00, partilha 100, DIFAL 80,00 e FCP 20,00; o `ICMSTot` soma 80,00 e 20,00; o vNF fica
1.000,00 (R-NFE-037).

Status: 🧪

## UC-FNFE-35 · Contribuinte, ou mesma UF, não leva DIFAL · `must`

**Dado** o mesmo item vendido a um contribuinte no RJ, ou a um cliente de SC **Quando** sai a NF-e **Então**
o item não leva `ICMSUFDest` e o total do grupo fica como antes.

Status: 🧪

## UC-FNFE-36 · Sem a interna do destino a nota é recusada com a saída · `must`

**Dado** a venda a não contribuinte no RJ e a interna do RJ não cadastrada na tabela por UF **Quando** pede a
NF-e **Então** a emissão é recusada com "Alíquota interna de RJ não cadastrada. Peça ao contador para
preencher em Tributação."; com a interna, com cliente contribuinte, na mesma UF ou sem cliente, não recusa.

Status: 🧪

## UC-FNFE-49 · O ICMS-ST vai na nota e soma no valor total · `must`

**Dado** um item de 1.000,00 de SC para contribuinte no RJ, regra CST 10, ICMS 12%, MVA 40% e interna do RJ 18%
**Quando** sai a NF-e **Então** o ICMS10 leva base ST 1.400,00, MVA 40, alíquota ST 18 e ST 132,00; o total leva
vBCST 1.400,00 e vST 132,00; o vNF e o pagamento são 1.132,00 (regra W16 do leiaute). Com CSOSN 202, idem.

Status: 🧪

## UC-FNFE-50 · Regra com MVA e CST sem ST é recusada · `must`

**Dado** a mesma regra com CST 00 **Quando** pede a NF-e **Então** é recusada com "não leva ST — ajuste a regra em
Tributação".

Status: 🧪

## UC-FNFE-55 · Destinatário informado por quem emite vence o cliente da venda · `must`

**Dado** uma NF-e (modelo 55) emitida com um destinatário informado (ex. o comprador de um pedido de
marketplace), com as chaves de `contacts` **Quando** sai a nota **Então** o destinatário é o informado,
com CPF só com dígitos, a UF dele e `ind_ie_dest` 9 quando não há IE; informado sem CPF/CNPJ, a emissão
recusa em vez de sair para o consumidor anônimo. Âncora: R-NFE-046 / US-NFE-075.

Status: 🧪

## UC-FNFE-56 · CFOP informado por quem emite vence o da regra · `must`

**Dado** uma NF-e emitida com um CFOP informado **Quando** os itens são montados **Então** todos saem com
esse CFOP e o resto do item fica igual; CFOP fora de 4 dígitos de 1xxx a 7xxx recusa. O valor do CFOP é
de quem chama. Âncora: R-NFE-046 / US-NFE-075.

Status: 🧪

---

## Boleto pago emite a NF-e (US-RB-044 · UC-FLX-26)

> **Âncora:** [US-RB-044](../../../../memory/requisitos/RecurringBilling/SPEC.md) (*"Listener InvoicePaid
> em NfeBrasil — emissão automática NFe55 + DANFE + e-mail"*, `done`) e o `CU-RB-09` do
> [SDD da cobrança recorrente §6](../../../../memory/requisitos/RecurringBilling/SDD-cobranca-recorrente-v1.0.md)
> — e o UC-FLX-26 do catálogo de fluxos (*"Boleto pago emite a NF-e · Pago → emite a NF-e sem ninguém
> clicar"*, estado `producao`). Código conferido, não usado como fonte:
> `Modules/NfeBrasil/Listeners/EmitirNFeAoReceberPagamento.php` e `NfeService::emitirParaInvoice`.
>
> **Por que mora nesta tela:** o fluxo não tem tela própria, e o SDD da cobrança recorrente declara o
> `CU-RB-09` *"fora da fronteira deste módulo — o listener vive em `Modules/NfeBrasil/`"* (§6, tabela
> dos CU sem `casos.md`). A regra do mesmo SDD é que fluxo sem tela fica no `casos.md` da tela **do
> artefato que ele produz** — a NF-e emitida aparece nesta lista. Criar arquivo paralelo seria tipo
> novo ([ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md) D-B).
>
> **Força do veredito, declarada:** `EmitirNFeAoReceberPagamentoTest` não está na lista da lane
> `nfebrasil-pest` (MySQL); entra pela matriz da `modules-pest` (`vendor/bin/pest Modules/NfeBrasil/Tests`,
> SQLite em memória) e pula inteiro quando a tabela `rb_invoices` não existe. Se ele executa ou pula
> naquela lane não foi medido aqui: o `🧪` diz que o teste cita o UC, não que ele rodou — ler a
> contagem de *assertions* no JUnit antes de subir para ✅.

## UC-FNFE-21 · Fatura paga emite a NF-e sem ninguém clicar · `must`

Status: 🧪 (`EmitirNFeAoReceberPagamentoTest` — `UC-FNFE-21 · …`, 4 casos)

**Dado** a chave `nfebrasil.auto_emission_on_invoice_paid` ligada e a fatura `INV-…` de 199,90 paga
**Quando** a cobrança recorrente dispara `InvoicePaid` **Então** o listener (registrado pelo
`NfeBrasilServiceProvider`, na fila `nfe`, nunca síncrono) acha a fatura pela empresa + número do
documento e pede **uma** emissão a partir dela; autorizada (cStat 100), dispara `NFeAutorizada` — que é o
que leva o DANFE por e-mail. Rejeitada (cStat 225): a emissão fica gravada como rejeitada e `NFeAutorizada`
**não** sai.

## UC-FNFE-22 · Com a chave desligada, ou sem a fatura, nenhuma nota sai · `must`

Status: 🧪 (`EmitirNFeAoReceberPagamentoTest` — `UC-FNFE-22 · …`, 2 casos)

**Dado** a chave desligada (o padrão — `CU-RB-09` item 2) **Quando** a fatura `INV-2026-0001` de 199,90 é
paga **Então** o listener só registra "DISABLED" com o número da fatura e não chama a emissão. **Dado** a
chave ligada e um número de fatura que não existe (`INV-INEXISTENTE-9999`) **Então** registra aviso
"Invoice não encontrada" e também não emite.

## UC-FNFE-23 · SEFAZ fora não derruba o pagamento · `must`

Status: 🧪 (`EmitirNFeAoReceberPagamentoTest` — `UC-FNFE-23 · …`, 3 casos)

**Dado** a emissão falhando com "SEFAZ timeout" **Quando** a fatura paga chega ao listener **Então** o erro
é registrado ("NFe auto-emission falhou") e devolvido à fila, que tenta **3** vezes com **60 s** de
intervalo; esgotadas as tentativas, `failed()` registra o erro com o número da fatura. O pagamento já foi
creditado antes e não é desfeito (`CU-RB-09` item 3).

**Fora destes três (backlog, sem id):**

- `[BACKLOG]` **A mesma fatura paga duas vezes não gera duas NF-e.** O SPEC promete idempotência por
  `transaction_id = invoice.id`; o `emitir()` é idempotente por `transaction_id`
  (`NfeServiceIdempotenciaRetryTest`), mas nenhum teste passa a mesma fatura duas vezes pelo listener.
- `[BACKLOG]` **Fatura de outra empresa com o mesmo número não é emitida.** O listener filtra por
  `business_id` + `numero_documento`; nenhum caso usa duas empresas.
- `[BACKLOG]` **O teste rodar numa lane com `rb_invoices`** (a `nfebrasil-pest`, MySQL) — ver a força do
  veredito acima.
- O e-mail com DANFE + XML é o `EnviarDanfePorEmail` (US-NFE-044, `EnviarDanfePorEmailTest`), fora deste
  bloco.

## XML do mês vai ao contador (US-NFE-073 · R-NFE-038)

> **Âncora:** [US-NFE-073](../../../../memory/requisitos/NfeBrasil/SPEC.md) e a R-NFE-038. Mora nesta
> tela porque o pacote é feito das notas desta lista. Coluna CU `—`: o SDD §6 do Fiscal não tem CU do
> envio ao contador. Prova: `EnviarXmlContadorTest` (MySQL, tenants 98 e 99, storage e e-mail falsos).

## UC-FNFE-37 · O pacote leva as autorizadas e canceladas do mês, só da empresa · `must`

**Dado** a empresa com, em 09/2026, uma NF-e autorizada, uma cancelada e uma rejeitada, mais uma autorizada
em 10/2026 e uma do vizinho **Quando** monta o pacote de 2026-09 **Então** o ZIP tem 2 XML, a cancelada com
"-cancelada" no nome, e nenhuma das outras. Nota do mês sem XML no storage é contada à parte.

Status: 🧪

## UC-FNFE-38 · O contador recebe uma vez por mês · `must`

**Dado** contador cadastrado na empresa e no vizinho **Quando** roda o envio de 2026-09 duas vezes **Então** sai
um e-mail só, para o contador da empresa, com 1 XML; o envio fica registrado; o vizinho sem nota não recebe.

Status: 🧪

## UC-FNFE-39 · Sem contador, em dry-run ou com a chave desligada, nada sai · `must`

**Dado** a empresa sem contador **Quando** roda o envio **Então** nenhum e-mail sai. Em dry-run nada é enviado
nem gravado. O agendamento do dia 5 às 07:00 existe e só passa com `NFEBRASIL_ENVIO_XML_CONTADOR` ligada.

Status: 🧪

## Backlog de casos (sem id — entram quando um teste de COMPORTAMENTO os cobrir)

- **[~~BACKLOG~~ · 🧪 tem teste, NÃO executa · Tier 0] Gate de permissão `fiscal.nfe.view` bloqueia a leitura da lista** — Dado usuário sem `fiscal.nfe.view` nem `superadmin` · Quando faz `GET /fiscal/nfe` · Então 403. **Corrigido em 2026-09-01:** a redação anterior dizia *"nenhum teste o exercita"* e isso era **falso** — o caso existe em [`GatesPermissaoFiscalTest.php:72`](../../../../Modules/Fiscal/Tests/Feature/GatesPermissaoFiscalTest.php), **com controle negativo** em `:79` (superadmin não recebe 403), e ancora `UC-FNFE-08`. O que é verdade é outra coisa, e a distinção importa: o arquivo inteiro **pula** (`:49-55`) em SQLite e sem `nfe_emissoes`, então o caso **não executa** em nenhuma lane de hoje. *Teste ausente* e *teste que não roda* pedem trabalhos diferentes — o primeiro é escrever, o segundo é dar lane ao módulo (o item de maior alavancagem do plano: 15 de 21 arquivos de teste do Fiscal chamam `markTestSkipped`).
- **[BACKLOG · ⬜ sem teste] Lista deferida filtra por tab/status/busca com paginação 50** — `rows` é `Inertia::defer`; sem teste do payload filtrado (`buildRowsPayload`, ordem `emitido_em DESC`).
- **[BACKLOG · ⬜ sem teste] Retransmitir só aceita nota `rejeitada`/`denegada`/`erro_envio`** — Dado nota em outro status · Quando pede retransmissão · Então volta com erro sem chamar o Service. **Não é testável sem banco**: no `AcoesController::retransmitir` a whitelist é checada **depois** do `firstOrFail()`, logo exige `nfe_emissoes` — indisponível nas duas lanes de hoje (ver §recibo). O caso que existia aqui assertava um array literal escrito no próprio teste e foi removido em 2026-07-28 (não defendia nada). _Parcialmente coberto desde 2026-09-01 pelo `UC-FNFE-09`, que prova **estaticamente** que a whitelist do Service é exatamente essa e que ela rejeita — o que falta aqui é o caminho de **runtime**, e ele segue esperando lane com as migrations do NfeBrasil._
- **[BACKLOG · 🟡 parcialmente coberto] Drawer: mapa "Jana sugere" por cstat rejeitado, pílula temporal na linha** — comportamento de UI, sem cobertura Feature nem E2E (a tela não aparece em `tests/Browser`). **Reduzido em 2026-09-03:** a metade "atalhos J/K + Enter" saiu deste item e virou `UC-FNFE-10`, com teste de render que morde (4 mutações provadas). O que fica aqui é o que segue descoberto: o mapa guiado por `cstat` e a pílula temporal.
- **[BACKLOG · ⬜ sem teste] NF-e de devolução total ou parcial** (pedido de 2026-10-23) `[V0]` — Dado
  uma venda com NF-e autorizada e 2 de 3 itens devolvidos, Quando emito a devolução, Então a NF-e de
  devolução referencia a chave da original e leva só os 2 itens; com tudo devolvido, leva a nota
  inteira. Fonte: UC-TRB-13 do protótipo fiscal (proposta) e o processo "Devolução, troca e garantia".
- **[BACKLOG · ⬜ sem teste] Remessa para conserto e o retorno** (pedido de 2026-10-23) `[E0]` — Dado um
  bem enviado para conserto, Quando emito a remessa, Então a nota fica "aguardando retorno" e o bem não
  sai do estoque como venda; ao registrar a volta, a nota de retorno fica ligada à remessa e o saldo
  pendente zera. CFOP de exemplo: 5.915 na remessa e 1.916 na volta; o contador confirma. Fonte:
  UC-TRB-23 do protótipo fiscal (falta).
- **[BACKLOG · ⬜ sem teste] Remessa em garantia** (pedido de 2026-10-23) `[E0]` — Dado a troca de uma
  peça em garantia, Quando emito a nota, Então ela sai sem cobrança, como remessa em garantia, com
  referência à NF-e da venda; a peça com defeito que volta entra com a nota de entrada. CFOP do SPEC de
  Garantia: 5.949 na saída e 1.949 na entrada; o contador confirma. Fonte: SPEC de Garantia §1 e §5
  (planejado).
- **[BACKLOG · ⬜ sem teste] Notas da venda para entrega futura** (pedido de 2026-10-23) `[V0]` `[E0]` —
  Dado uma venda para entrega futura, Então a nota de simples faturamento e cada nota de remessa ficam
  ligadas, e a tela mostra o saldo a remeter; remessa acima do faturado é recusada. Fonte: UC-TRB-24 do
  protótipo fiscal (falta).
- **[BACKLOG · ⬜ sem teste] Emissão recusada antes da SEFAZ por pendência** (pedido de 2026-10-23) —
  Dado uma venda com item sem NCM, natureza fiscal a decidir ou cliente sem os dados do destinatário,
  Quando a nota é pedida, Então nada é enviado à SEFAZ, nenhum número é usado e a resposta lista as
  pendências de cada item e do cliente. Fonte: UC-TRB-05 e UC-TRB-16 do protótipo fiscal e UC-FNFSE-12.
  A lista de pendências traz a saída que o UC-TRB-33 decidido exige ("o bloqueio nunca é beco sem
  saída"): o NCM padrão marcado para revisão.
- **[BACKLOG · ⬜ sem teste] Cancelar a nota e emitir outra na mesma venda** (pedido de 2026-10-23)
  `[V0]` `[E0]` — Dado uma venda com NF-e autorizada que precisa ser corrigida, Quando cancelo a nota
  dentro do prazo, Então valem os efeitos do Cancelar do fluxo de NF-e (o estoque é estornado e o
  título é cancelado se não estiver pago); Quando emito a nota nova e ela é autorizada, Então valem os
  efeitos do Retorno 100 (baixa o estoque, se for a regra, e liga ao título), a nota nova fica ligada à
  mesma venda e a venda mostra as duas, a cancelada com o motivo. Nenhum valor nem estoque fica em dobro
  ou faltando. Na NFS-e a correção é por **substituição**, não por cancelar e emitir: a nova NFS-e entra
  no lugar da anterior, fica vinculada a ela e recalcula o título (fluxo de NFS-e, "Substituir"); o
  cancelamento de NFS-e fica fora da tela da NFS-e (charter). Hoje a emissão só barra nota autorizada
  ou pendente do mesmo modelo (`NfeEmissaoController::emitir`), então emitir de novo depois de cancelar
  já passa; não há caso nem teste que prove o vínculo. Fonte: `fluxos-modelos.casos.md` (NF-e:
  Cancelar e Retorno 100; NFS-e: Substituir). Verificado contra as regras do [W] em 2026-10-23.
- **[BACKLOG · ⬜ sem teste] Nota que deixaria o saldo fiscal negativo avisa** (pedido de 2026-10-27)
  — Dado um produto sem nenhuma entrada com nota, Quando emito uma NF-e com ele, Então a tela avisa
  que o saldo fiscal vai ficar negativo e oferece emitir a nota de entrada ou ligar a compra à nota do
  fornecedor. A venda continua escolhendo com ou sem nota, como hoje; o sistema não decide pela origem
  da compra. Avisar ou bloquear a emissão é decisão do [W]; a proposta é só avisar. Depende do saldo
  fiscal (`Produto/Index.casos.md`) e da compra ligada à nota (`Purchase/Create.casos.md`). Pedido da
  PraMoto.

## Como rodar a suíte
1. **Pest:** lane Fiscal no CT 100 (ADR 0062) — comando no §recibo acima. `AcoesContratoTest` roda em
   qualquer lane (não toca banco); `UC-FNFE-01` exige `nfe_emissoes`.
2. **Cadência:** rodar ao fim de toda mexida na tela. UC ❌ = regressão fiscal.

## Trilha do tempo
- 2026-10-27 · [F+C] Backlog: nota que deixaria o saldo fiscal negativo avisa, pedido da PraMoto, sem id.
- 2026-10-23 · [M+C] Backlog: emissão recusada antes da SEFAZ por pendência de imposto ou de cliente, sem id.
- 2026-10-23 · [M+C] Backlog: NF-e de devolução total ou parcial, remessa para conserto, remessa em garantia e notas da venda para entrega futura, sem id.
- 2026-10-21 · [C] `UC-FNFE-19`, `20`, `30`, `31` — SVC-AN/SVC-RS e evento EPEC (US-NFE-071 ·
  UC-FLX-32). Testes novos em `ContingenciaModoTest`, sem veredito de lane ainda. **Os ids pulam do 20
  pro 30:** o 21..23 já é do fluxo "boleto pago emite a NF-e", e 24..29 ficam livres para quem estiver
  abrindo casos nesta tela em paralelo.
- 2026-10-20 · [C] `UC-FNFE-15..18` — contingência (US-NFE-070 · UC-FLX-32): `dhCont`/`xJust` no XML e
  retransmissão agendada. Testes novos em `ContingenciaAgendamentoTest`, sem veredito de lane ainda.

- 2026-10-20 · [C] `UC-FNFE-21..23` — o fluxo "boleto pago emite a NF-e" (US-RB-044 · `CU-RB-09` ·
  UC-FLX-26) ganha casos aqui. Nenhum teste novo e nenhum assert mudou: os 9 casos existentes de
  `EmitirNFeAoReceberPagamentoTest` passaram a citar o UC no título. **Os ids pulam pro 21 de propósito:**
  o [#9629](https://github.com/wagnerra23/oimpresso.com/pull/9629) (contingência, UC-FLX-32), aberto em
  paralelo, usa `UC-FNFE-15..18` nesta mesma tela. Seguindo a regra de 2026-09-04, este lado escolheu um
  valor que não colide com nenhum estado possível do outro (15..18, e folga até 20) e anuncia no PR.
  O `13` segue vago.
- 2026-09-04 · [C] `UC-FNFE-14` — a densidade vira preferência compartilhada. **O id saltou pro
  14, e o 13 fica vago de propósito — vale registrar o mecanismo, porque ele custou 3 rodadas.**
  Este UC e o do [#6731](https://github.com/wagnerra23/oimpresso.com/pull/6731) nasceram ambos
  `UC-FNFE-12`, no mesmo dia, na mesma tela: duas sessões paralelas, contratos diferentes. Daí:
  **(1)** eu cedi por *ordem de criação* e fui pro `13`; **(2)** a outra sessão cedeu por *custo
  medido* e foi pro `13` também — a colisão mudou de casa; **(3)** os dois voltamos pro `12` ao
  mesmo tempo — a colisão voltou pra casa. Cortesia simétrica não resolve disputa de nome: cada
  passo do outro era invisível até chegar, e os dois calculavam **o mesmo próximo livre**.
  Saída: escolher unilateralmente um valor que não colida com **nenhum** estado possível do outro
  (ele estava no `12`, podia ir pro `13` — logo, `14`), aplicar, e comunicar como fato consumado
  em vez de proposta. O `13` fica vago porque já está queimado nos dois históricos.
  **Regra pra próxima:** em renomeação negociada entre sessões, quem cede **anuncia o destino
  antes de escrever**, e quem recebe o anúncio **não cede de volta**.
- 2026-07-03 · [CC] criado no Passo 3 do programa de ondas. 17 testes mapeados, 0 citavam UC-id.
- 2026-07-27 · [CC] fecha a G-2 com 8 UC (`UC-FNFE-01..08`). Criado `AcoesContratoTest` (contrato REAL
  das regras do Controller, mordida provada); guard de banco movido pros casos que precisam dele
  (21 skipped → 33 passed); removido o caso-espelho do `isCancelavel`. Corrigidas duas afirmações
  falsas da versão anterior deste arquivo ("17 casos reais" e "validação/whitelist rodam sempre").
  Prefixo `UC-FNFE-` em vez do `UC-FISCAL-` planejado: as 6 telas Fiscal compartilhariam o id e a G-2
  casa por substring — colisão viraria cobertura falsa cruzada.
- 2026-07-28 · [CC] poda de **11** casos tautológicos do `AcoesControllerTest` (decisão [W]). Dez
  têm substituto com mordida provada no `AcoesContratoTest`; o 11º (whitelist de status do
  `retransmitir`) não tem — a checagem vive depois do `firstOrFail()` e exige `nfe_emissoes`,
  então virou item de backlog declarado em vez de teste que não defende nada. **Dois** casos
  tautológicos ficaram de propósito: os de manifestação DF-e ancoram `UC-FDFE-03`/`UC-FDFE-04` da
  tela `Fiscal/Dfe` (anotados por outra sessão enquanto este trabalho corria) — removê-los
  orfanaria UC de tela alheia. O comportamento real deles já é provado por `UC-FNFE-07`;
  re-apontar o DF-e pra lá é decisão do dono daquela tela. Nenhum UC perdeu lastro.
- 2026-09-04 · [C] `UC-FNFE-11` criado — acessibilidade de ícone. 13 ícones ganham
  `aria-hidden`: 5 na tela, 7 na fonte única da subnav (`_lib/paginas-fiscais.tsx`, que serve as 7
  telas do Fiscal), 1 no `FxShell`, mais 10 no `CmdKPalette`. Medido antes: o lucide não declara
  `aria-hidden` sozinho (sonda no DOM). Nenhum era o único conteúdo de um controle — conferidos um
  a um. Os 22 `color: white` e os ícones das outras 6 telas ficam fora, declarados.
- 2026-09-03 · [C] Onda 2 Fiscal (teclado na lista). **`UC-FNFE-10` criado** — o primeiro UC desta
  tela que prova comportamento de **UI** em vez de backend, e o primeiro a rodar em lane `vitest`
  (`fiscal-teclado-gate.yml`, criada no mesmo PR porque NENHUMA lane roda `vitest run` sem
  argumento e spec sem lane nunca executa). Metade do item de §Backlog de teclado saiu de
  "⬜ sem teste" para UC com mordida provada. Coluna CU fica `—` de propósito: não existe CU de
  acessibilidade no SDD §6 e inventar um seria LC-11. Os 8 UC de backend: intactos, não
  re-executados.
- 2026-09-01 · [CC] Onda 1 Fiscal (saneamento `fx-*` → DS). `last_run` bumpado para 09-01 em duas
  revalidações — a do flip do token `--fis` e a da troca de primitivas —, cada uma no MESMO commit
  do `.tsx` que a motivou, para pegar a isenção por SHA do G-6 (a via por data reabre o staleness
  no squash-merge seguinte). **Nenhum UC foi criado, alterado ou removido:** a onda é de camada de
  apresentação, e os 8 UC assertam backend. O débito real deste arquivo continua o mesmo e está
  declarado no §Backlog — em especial o gate de permissão `fiscal.nfe.view` (Tier 0, sem teste) e
  o `UC-FNFE-01`, que segue skipando por falta de `nfe_emissoes` na lane.
