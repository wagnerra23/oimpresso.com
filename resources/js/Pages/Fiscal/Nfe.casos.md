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
| UC-FNFE-03 | o código SEFAZ vira status legível | `[must]` | CU-FISC-02 | `NfeCockpitMultiTenantTest` | 🧪 |
| UC-FNFE-04 | cancelar exige motivo de 15 a 255 chars | `[must]` | CU-FISC-08 | `AcoesContratoTest` | 🧪 |
| UC-FNFE-05 | CC-e: texto 15–1000, sequência 1–20 | `[must]` | CU-FISC-09 | `AcoesContratoTest` | 🧪 |
| UC-FNFE-06 | inutilização valida modelo, faixa e justificativa | `[must]` | CU-FISC-10 | `AcoesContratoTest` | 🧪 |
| UC-FNFE-07 | manifestação: 4 ações, justificativa condicional | `[must]` | CU-FISC-07 | `AcoesContratoTest` | 🧪 |
| UC-FNFE-08 | ⚠️ **id sobrecarregado** — (a) a superfície das ações existe · (b) o gate `fiscal.nfe.view` devolve 403 | `[must]` `[T0]` | CU-FISC-13 (só o gate) · ver nota | `AcoesControllerTest` (5) · `GatesPermissaoFiscalTest` (2) | 🧪 |
| UC-FNFE-09 | retransmitir preserva a nota antiga (nunca deleta) | `[must]` `[reg]` | CU-FISC-11 | `AcoesContratoTest` | 🧪 |
| UC-FNFE-10 | a lista é operável só pelo teclado (linha focável, não botão) | `[must]` | **—** (ver nota) | `fiscal-nfe-teclado.test.tsx` | 🧪 |
| UC-FNFE-11 | nenhum ícone decorativo chega ao leitor de tela | `[must]` | **—** (mesma nota) | `fiscal-nfe-teclado.test.tsx` | 🧪 |
| UC-FNFE-13 | a densidade escolhida acompanha a navegação entre as telas de notas | `[should]` | **—** (mesma nota) | `fiscal-densidade.test.tsx` | 🧪 |

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
Status: 🧪 (`NfeCockpitMultiTenantTest::UC-FNFE-03 · sefazCodes retorna mapa…` — **passa**)
Dado a pílula SEFAZ · Quando lê o mapa de códigos · Então contém ao menos 100/110/220/539/691/778/999,
com 100 = `ok`, 220 = `bad` e 691 = `warn`. Âncora: charter Goal 2 + UX targets (verde autorizada ·
âmbar atenção · vermelho rejeição).
**Pronto quando:** o mapa do Controller traz os 7 códigos e os tons não invertem.

## UC-FNFE-04 — Cancelar exige motivo de 15 a 255 caracteres
Status: 🧪 (`AcoesContratoTest::UC-FNFE-04 · REJEITA <15` / `REJEITA >255` / `ACEITA válido` — **passam**)
Dado o pedido de cancelamento · Quando a justificativa tem menos de 15 ou mais de 255 caracteres ·
Então o Controller recusa com erro no campo `motivo`; com motivo válido, a validação não barra.
Âncora: US-FISCAL-012 + Ajuste SINIEF 07/2005 (justificativa mínima).
**Pronto quando:** a asserção usa a regra **do Controller** — afrouxá-la lá derruba este UC.

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

## UC-FNFE-13 — A densidade escolhida acompanha a navegação entre as telas de notas
Status: 🧪 (`tests/js/fiscal-densidade.test.tsx` — **passa**, 6/6; lane `Fiscal Densidade Gate`)
Dado o operador na lista de NF-e · Quando escolhe **Compacto** e navega para o Cockpit ou para a
NFS-e · Então a tabela de lá já abre compacta — a preferência é dele, não da tela.

Âncora: a fonte de design faz as três telas serem a **mesma função** — `FxNotasPage`, chamada com
`preset` diferente ([`fiscal-page.jsx:346,541-543`](../../../../prototipo-ui/cowork/fiscal-page.jsx)) — e
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

## Backlog de casos (sem id — entram quando um teste de COMPORTAMENTO os cobrir)

- **[~~BACKLOG~~ · 🧪 tem teste, NÃO executa · Tier 0] Gate de permissão `fiscal.nfe.view` bloqueia a leitura da lista** — Dado usuário sem `fiscal.nfe.view` nem `superadmin` · Quando faz `GET /fiscal/nfe` · Então 403. **Corrigido em 2026-09-01:** a redação anterior dizia *"nenhum teste o exercita"* e isso era **falso** — o caso existe em [`GatesPermissaoFiscalTest.php:72`](../../../../Modules/Fiscal/Tests/Feature/GatesPermissaoFiscalTest.php), **com controle negativo** em `:79` (superadmin não recebe 403), e ancora `UC-FNFE-08`. O que é verdade é outra coisa, e a distinção importa: o arquivo inteiro **pula** (`:49-55`) em SQLite e sem `nfe_emissoes`, então o caso **não executa** em nenhuma lane de hoje. *Teste ausente* e *teste que não roda* pedem trabalhos diferentes — o primeiro é escrever, o segundo é dar lane ao módulo (o item de maior alavancagem do plano: 15 de 21 arquivos de teste do Fiscal chamam `markTestSkipped`).
- **[BACKLOG · ⬜ sem teste] Lista deferida filtra por tab/status/busca com paginação 50** — `rows` é `Inertia::defer`; sem teste do payload filtrado (`buildRowsPayload`, ordem `emitido_em DESC`).
- **[BACKLOG · ⬜ sem teste] Retransmitir só aceita nota `rejeitada`/`denegada`/`erro_envio`** — Dado nota em outro status · Quando pede retransmissão · Então volta com erro sem chamar o Service. **Não é testável sem banco**: no `AcoesController::retransmitir` a whitelist é checada **depois** do `firstOrFail()`, logo exige `nfe_emissoes` — indisponível nas duas lanes de hoje (ver §recibo). O caso que existia aqui assertava um array literal escrito no próprio teste e foi removido em 2026-07-28 (não defendia nada). _Parcialmente coberto desde 2026-09-01 pelo `UC-FNFE-09`, que prova **estaticamente** que a whitelist do Service é exatamente essa e que ela rejeita — o que falta aqui é o caminho de **runtime**, e ele segue esperando lane com as migrations do NfeBrasil._
- **[BACKLOG · 🟡 parcialmente coberto] Drawer: mapa "Jana sugere" por cstat rejeitado, pílula temporal na linha** — comportamento de UI, sem cobertura Feature nem E2E (a tela não aparece em `tests/Browser`). **Reduzido em 2026-09-03:** a metade "atalhos J/K + Enter" saiu deste item e virou `UC-FNFE-10`, com teste de render que morde (4 mutações provadas). O que fica aqui é o que segue descoberto: o mapa guiado por `cstat` e a pílula temporal.

## Como rodar a suíte
1. **Pest:** lane Fiscal no CT 100 (ADR 0062) — comando no §recibo acima. `AcoesContratoTest` roda em
   qualquer lane (não toca banco); `UC-FNFE-01` exige `nfe_emissoes`.
2. **Cadência:** rodar ao fim de toda mexida na tela. UC ❌ = regressão fiscal.

## Trilha do tempo
- 2026-09-04 · [C] `UC-FNFE-13` — a densidade vira preferência compartilhada. **O id pula o 12
  de propósito:** ele nasceu `UC-FNFE-12` aqui e no [#6731](https://github.com/wagnerra23/oimpresso.com/pull/6731)
  ao mesmo tempo (duas sessões paralelas na mesma tela). A colisão foi detectada por aviso
  cross-session e conferida por medição — o #6731 foi criado às 11:18:39Z e este às 11:25:52Z,
  então quem renomeia é quem chegou depois. Um id ancorando **dois** contratos é o problema que
  o `UC-FNFE-08` desta mesma tela já carrega e que está declarado como achado acima; não se cria
  o segundo de olhos abertos. O bloco inline do
  Cockpit virou `_components/DensidadeToggle.tsx` (dono único, como no protótipo, onde as três
  telas são uma função só) e NF-e/NFS-e passaram a consumi-lo. Os 11 UC anteriores seguem
  intactos: conferi um a um — nenhum toca densidade, tabela ou storage.
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
