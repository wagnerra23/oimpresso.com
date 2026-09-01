---
id: resources-js-pages-fiscal-nfe-casos
casos: Notas NF-e / NFC-e · /fiscal/nfe
irmaos: Nfe.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-09-01"
---

# Casos de Uso & Aceite — Notas NF-e / NFC-e

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

> **Por que esta tabela nasceu em 2026-09-01 (e o que ela NÃO fez):** os 8 UC desta tela já eram
> provados por teste desde 2026-07-27 — nenhum deles declarava, porém, **qual CU do SDD §6 atende**.
> Efeito no painel derivado ([`_STATUS-GENERATED.md`](../../../../memory/requisitos/Fiscal/_STATUS-GENERATED.md)):
> `CU-FISC-02/03/08/09/10` apareciam como "sem UC" tendo comportamento provado, e `UC-FNFE-04`/`UC-FNFE-07`
> saíam atribuídos a `Eventos`/`Dfe` — as telas irmãs os citam em prosa (corretamente, dizendo que o
> contrato mora aqui) e o gerador cai no fallback alfabético quando a tela **dona** não os declara em
> tabela. **Nenhuma asserção de teste mudou:** isto é rastreabilidade, não comportamento.

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

## Backlog de casos (sem id — entram quando um teste de COMPORTAMENTO os cobrir)

- **[~~BACKLOG~~ · 🧪 tem teste, NÃO executa · Tier 0] Gate de permissão `fiscal.nfe.view` bloqueia a leitura da lista** — Dado usuário sem `fiscal.nfe.view` nem `superadmin` · Quando faz `GET /fiscal/nfe` · Então 403. **Corrigido em 2026-09-01:** a redação anterior dizia *"nenhum teste o exercita"* e isso era **falso** — o caso existe em [`GatesPermissaoFiscalTest.php:72`](../../../../Modules/Fiscal/Tests/Feature/GatesPermissaoFiscalTest.php), **com controle negativo** em `:79` (superadmin não recebe 403), e ancora `UC-FNFE-08`. O que é verdade é outra coisa, e a distinção importa: o arquivo inteiro **pula** (`:49-55`) em SQLite e sem `nfe_emissoes`, então o caso **não executa** em nenhuma lane de hoje. *Teste ausente* e *teste que não roda* pedem trabalhos diferentes — o primeiro é escrever, o segundo é dar lane ao módulo (o item de maior alavancagem do plano: 15 de 21 arquivos de teste do Fiscal chamam `markTestSkipped`).
- **[BACKLOG · ⬜ sem teste] Lista deferida filtra por tab/status/busca com paginação 50** — `rows` é `Inertia::defer`; sem teste do payload filtrado (`buildRowsPayload`, ordem `emitido_em DESC`).
- **[BACKLOG · ⬜ sem teste] Retransmitir só aceita nota `rejeitada`/`denegada`/`erro_envio`** — Dado nota em outro status · Quando pede retransmissão · Então volta com erro sem chamar o Service. **Não é testável sem banco**: no `AcoesController::retransmitir` a whitelist é checada **depois** do `firstOrFail()`, logo exige `nfe_emissoes` — indisponível nas duas lanes de hoje (ver §recibo). O caso que existia aqui assertava um array literal escrito no próprio teste e foi removido em 2026-07-28 (não defendia nada). _Parcialmente coberto desde 2026-09-01 pelo `UC-FNFE-09`, que prova **estaticamente** que a whitelist do Service é exatamente essa e que ela rejeita — o que falta aqui é o caminho de **runtime**, e ele segue esperando lane com as migrations do NfeBrasil._
- **[BACKLOG · ⬜ sem teste] Drawer: mapa "Jana sugere" por cstat rejeitado, atalhos J/K + Enter, pílula temporal na linha** — comportamento de UI; sem cobertura Feature nem E2E (a tela não aparece em `tests/Browser`).

## Como rodar a suíte
1. **Pest:** lane Fiscal no CT 100 (ADR 0062) — comando no §recibo acima. `AcoesContratoTest` roda em
   qualquer lane (não toca banco); `UC-FNFE-01` exige `nfe_emissoes`.
2. **Cadência:** rodar ao fim de toda mexida na tela. UC ❌ = regressão fiscal.

## Trilha do tempo
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
- 2026-09-01 · [CC] Onda 1 Fiscal (saneamento `fx-*` → DS). `last_run` bumpado para 09-01 em duas
  revalidações — a do flip do token `--fis` e a da troca de primitivas —, cada uma no MESMO commit
  do `.tsx` que a motivou, para pegar a isenção por SHA do G-6 (a via por data reabre o staleness
  no squash-merge seguinte). **Nenhum UC foi criado, alterado ou removido:** a onda é de camada de
  apresentação, e os 8 UC assertam backend. O débito real deste arquivo continua o mesmo e está
  declarado no §Backlog — em especial o gate de permissão `fiscal.nfe.view` (Tier 0, sem teste) e
  o `UC-FNFE-01`, que segue skipando por falta de `nfe_emissoes` na lane.
