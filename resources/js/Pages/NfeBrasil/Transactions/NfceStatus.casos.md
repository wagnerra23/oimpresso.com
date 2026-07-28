---
id: resources-js-pages-nfe-brasil-transactions-nfce-status-casos
casos: Status fiscal pós-venda — o desfecho SEFAZ da nota de uma venda · /nfe-brasil/transactions/{tx}/status
irmaos: NfceStatus.charter.md (lei) · SDD-emissao-fiscal-v1.0.md (§5.3 F3/F4 · §6.1)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a única tela do módulo que a operadora abre por causa de UMA nota — o que ela mostra decide se a venda sai do balcão ou não.
owner: wagner
last_run: "2026-07-28"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (NfeBrasil · MySQL)"
---

# Casos de Uso & Aceite — Status fiscal pós-venda (NFC-e)

> **Âncora:** os UC derivam do [**SDD do módulo**](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md)
> §5.3 (F3 · F4) e §6.1 (`CU-NFE-01` · `CU-NFE-02` · `CU-NFE-13`), que por sua vez derivam de
> [**US-NFE-002**](../../../../../memory/requisitos/NfeBrasil/SPEC.md) e do
> [**charter**](NfceStatus.charter.md). O `NfeStatusController` e o hook `useNfceStatus` foram
> lidos só para **confirmar** o comportamento — caso derivado da implementação é tautológico
> ([proibicoes §5](../../../../../memory/proibicoes.md) 2026-06-05).
>
> ⚠️ **O charter desta tela é `status: draft`** e o próprio texto dele diz *"Non-Goals + Anti-hooks
> aguardam aprovação Wagner antes de promover pra `status: live`"*. Por isso **nenhum `❌` do
> charter virou `[must]` aqui** — diferente da tela irmã `Tributacao/Index`, cujo charter é `live`.
> Promover é decisão [W].
>
> **Por que este arquivo nasce agora:** o módulo tinha **0 `casos.md`** (medido por
> `node scripts/governance/requisitos-status.mjs NfeBrasil`, 2026-07-28) e **nenhum SDD**. Esta é a
> tela-âncora da Onda 5 — não porque seja a "tela de emissão" (essa **não existe**, §SDD 1.1), mas
> porque é a que mais perto está dela.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e o veredito é da lane ·
> ⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Contrato | Teste | Status |
|----|-------------|------|----------|-------|--------|
| UC-NFST-01 | Venda sem nota mostra "ainda não emitida" — não erro | must | SDD `CU-NFE-02` · charter §Mission | `NfeStatusContratoTest` | 🧪 |
| UC-NFST-02 | O desfecho da nota de outro business nunca aparece | must `[T0]` | ADR 0093 · charter §Anti-hooks | `NfeStatusContratoTest` | 🧪 |
| UC-NFST-03 | A consulta só se declara terminal nos 3 estados terminais | must | SDD `CU-NFE-02` · charter §UX Targets | `NfeStatusContratoTest` | 🧪 |
| UC-NFST-04 | Depois de uma retentativa, vale a emissão mais recente | must | SDD `CU-NFE-02` · docblock CONFAZ | `NfeStatusContratoTest` | 🧪 |
| UC-NFST-05 | O payload da consulta não carrega segredo nem XML | must `[T0]` | PII-LGPD-FISCAL · charter §Anti-hooks | `NfeStatusContratoTest` | 🧪 |

> **Recibo:** ver §Recibo de execução no rodapé — status é o **veredito** da corrida, não leitura
> de código.

---

## UC-NFST-01 · Venda sem nota mostra "ainda não emitida" — não erro · `must`

- **Persona:** Larissa acabou de finalizar a venda e abriu a tela antes de o job de emissão rodar.
  A resposta certa é *"estamos processando"*, não uma tela de erro — erro na frente do cliente vira
  ligação para o suporte.
- **Aceite:** Dado uma venda do meu business **sem** nenhuma emissão · Quando a tela consulta o
  status · Então a resposta é **200**, com estado "não emitida" e uma mensagem legível — e
  **nunca** 404 nem 500.
- **Teste:** [`NfeStatusContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/NfeStatusContratoTest.php)
  — `UC-NFST-01 · venda sem emissão responde 200 com estado não-emitida e mensagem`.
- **Contrato:** SDD [`CU-NFE-02`](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md)
  item 2 + charter §Mission (*"polling até cStat final"*) + o docblock de
  `NfeStatusController@show`, que declara a resposta *"ainda emitindo / não emitida"* como caso
  normal do contrato.
- **Regressão que defende:** o caminho "sem emissão" é um `if (! $emissao) return response()->json([...])`.
  Trocar por `firstOrFail()` — refactor de aparência inocente, e o tipo de coisa que "limpa" um
  null-check — transforma o caso mais comum da tela (os 2–10 primeiros segundos de **toda** venda)
  num **404**, e o hook trata `!res.ok` como desistência silenciosa: a tela ficaria eternamente em
  "Aguardando emissão" sem nunca mais consultar.
- **Nota de método:** o assert é sobre o **comportamento** (*"a resposta é 200 e traz um estado
  vazio identificável + uma mensagem"*), não sobre o nome literal da chave — assert acoplado a
  chave de payload reprova arbitrariamente um dos dois fixes válidos.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFST-02 · O desfecho da nota de outro business nunca aparece · `must` `[T0]`

- **Persona:** qualquer tenant. A chave de acesso de 44 dígitos, o valor da nota e o CNPJ do
  destinatário são dado fiscal de terceiro — vazá-los não é bug de UI, é incidente.
- **Aceite:** Dada uma emissão **autorizada** pertencente a outro business, amarrada a um
  `transaction_id` qualquer · Quando eu (business 1) consulto o status daquele `transaction_id` ·
  Então **nada** da nota alheia aparece: nem chave, nem número, nem valor, nem status. Controle
  positivo no mesmo caso: com a emissão do **meu** business no mesmo id, os dados **aparecem** —
  senão o verde viria de a resposta estar vazia por ausência de dado.
- **Teste:** [`NfeStatusContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/NfeStatusContratoTest.php)
  — `UC-NFST-02 · consulta não devolve emissão de outro business, e devolve a do próprio`.
- **Contrato:** [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) +
  charter §Automation Anti-hooks (*"❌ Não acessa Transaction de outro `business_id`"*) + SDD
  [`CU-NFE-11`](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md).
- **Regressão que defende:** o guard é um `where('business_id', $businessId)` **explícito** no
  Controller — e é a **única** camada aqui, porque o teste roda com `withSession` e o
  `ScopeByBusiness` global faz early-return quando `! auth()->check()`. Este caso usa **`actingAs`
  + as duas chaves de sessão** (`business.id`, lida pelo Controller, e `user.business_id`, lida
  pelo scope — as duas coexistem no módulo, SDD §5.4.2), então as duas camadas valem de verdade.
  Um refactor que remova o `where` explícito confiando "no scope" passa no teste antigo e vaza aqui.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFST-03 · A consulta só se declara terminal nos 3 estados terminais · `must`

- **Persona:** Larissa esperando. "Terminal" é o que **desliga o polling**: marcado cedo demais, a
  tela congela num "Processando" que nunca mais atualiza; marcado tarde demais, a tela martela a
  SEFAZ por 1 minuto depois de a resposta já ter chegado.
- **Aceite:** Dada uma emissão em cada estado · Quando a tela consulta · Então o indicador de
  desfecho final é verdadeiro **exatamente** em `autorizada`, `rejeitada` e `denegada`, e falso em
  `pendente`.
- **Teste:** [`NfeStatusContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/NfeStatusContratoTest.php)
  — `UC-NFST-03 · o indicador terminal é verdadeiro só em autorizada, rejeitada e denegada`.
- **Contrato:** SDD [`CU-NFE-02`](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md)
  item 3 + charter §UX Targets (*"Polling para após cStat final"*) + charter §UX Anti-patterns
  (*"❌ Loop polling infinito sem cap"*) + o docblock do Controller (*"Quando status === 'autorizada'
  OU 'rejeitada' OU 'denegada', UI para o polling — esses são estados terminais"*).
- **Regressão que defende:** o conjunto terminal está escrito **duas vezes** no repo — no
  `NfeStatusController@show` (`in_array(..., ['autorizada','rejeitada','denegada'])`) e de novo no
  `NfeEmissaoController::serializeEmissao`, onde inclui **também `cancelada`**. Duas listas para o
  mesmo conceito drifam em silêncio; este caso trava a do endpoint que a tela realmente consome.
  Um `cancelada` entrando aqui faria a tela parar de acompanhar uma nota que ainda pode mudar.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFST-04 · Depois de uma retentativa, vale a emissão mais recente · `must`

- **Persona:** Wagner reemitiu uma nota que a SEFAZ rejeitou. A tela precisa mostrar o desfecho da
  **segunda** tentativa; mostrar a primeira faria ele reemitir de novo uma nota que já autorizou —
  e número fiscal consumido não volta.
- **Aceite:** Dadas duas emissões para a mesma venda (a primeira `rejeitada`, a segunda
  `autorizada`, com números diferentes) · Quando a tela consulta · Então vem a **segunda** —
  identificada pelo número, não só pelo status.
- **Teste:** [`NfeStatusContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/NfeStatusContratoTest.php)
  — `UC-NFST-04 · com retentativa, a consulta devolve a emissão mais recente`.
- **Contrato:** SDD [`CU-NFE-02`](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md)
  item 4 + SDD §5.3 F5 (preservação de numeração, CONFAZ Ajuste SINIEF 07/2005 Art. 14) + comentário
  do Controller (*"se houver múltiplas (retentativas), pega a mais recente"*).
- **Regressão que defende:** o `orderByDesc('id')` é a única coisa que decide qual das N emissões a
  tela mostra. Sem ele o MySQL devolve em ordem de chave física — que **coincide** com a ordem certa
  na maioria dos casos, e por isso um teste sem duas linhas passa verde. A asserção compara o
  **número da nota**, não só o status: com dois `autorizada`, comparar status não distinguiria nada.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFST-05 · O payload da consulta não carrega segredo nem XML · `must` `[T0]`

- **Persona:** qualquer um com o DevTools aberto. Esta resposta viaja a cada 2 segundos, em toda
  venda, para o browser do balcão. É o payload mais repetido do módulo.
- **Aceite:** Dada uma emissão autorizada, com certificado ativo e XML guardado · Quando a tela
  consulta · Então **nenhum** valor do payload é a senha do certificado nem o conteúdo do XML.
- **Teste:** [`NfeStatusContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/NfeStatusContratoTest.php)
  — `UC-NFST-05 · nenhum segredo de certificado nem conteúdo de XML viaja no payload de status`.
- **Contrato:** [PII-LGPD-FISCAL.md](../../../../../memory/requisitos/NfeBrasil/PII-LGPD-FISCAL.md)
  + charter §Automation Anti-hooks (*"❌ Não loga PII"*) + `$hidden` em `NfeCertificado` + SDD
  [`CU-NFE-12`](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md).
- **Regressão que defende:** hoje o Controller monta o array campo a campo, o que é seguro **por
  acidente do estilo**. O dia em que alguém trocar por `$emissao->toArray()` ou
  `->load('certificado')` — refactor comum quando a tela pede "mais um campo" — o payload passa a
  carregar o que o Model tiver. O assert é sobre o **valor** (nenhum valor da resposta contém o
  segredo semeado nem o corpo do XML), não sobre o nome da chave: renomear a chave não pode fazer o
  vazamento passar.
- **Status: 🧪** — ver §Recibo.

---

## Backlog — sem UC até ganhar contrato

> Prosa honesta, sem gate. Vira UC quando **duas fontes** disserem qual é o comportamento certo
> ([proibicoes §5](../../../../../memory/proibicoes.md) 2026-07-16 — UC sem contrato nasce órfão e
> trava o merge de quem for atendê-lo).

- `[BACKLOG]` **A tela só enxerga NFC-e (modelo 65).** `NfeStatusController@show` filtra
  `where('modelo', 65)`; uma venda que emitiu **NF-e 55** recebe *"NFC-e ainda não foi emitida pra
  essa venda"* e a tela mostra "Aguardando emissão" para uma nota **autorizada**. O
  `NfeEmissaoController@listar` (`/api/transactions/{tx}/emissoes`) existe exatamente para os dois
  modelos e seu docblock diz *"Substituiu o GET nfe-status que retornava só modelo 65"* — mas a
  tela continua no endpoint antigo. ⚠️ **E o teste existente `NfeStatusControllerTest` fixa esse
  comportamento como correto** (`it('modelo 55 (NFe) é ignorado pelo endpoint NFC-e')`) — ou seja,
  há uma catraca defendendo o que o SDD chama de defeito. **Decisão [W]:** migrar a tela para
  `/emissoes` (e aposentar aquele caso) ou declarar o recorte NFC-e como Non-Goal.
- `[BACKLOG]` **O botão "Baixar DANFE" aponta para rota inexistente.** O `.tsx` monta
  `/nfe-brasil/transactions/{tx}/danfe`; varredura contada nas rotas
  (`grep -rn "danfe" Modules/NfeBrasil/Routes/*.php routes/*.php`) devolve **2 linhas, ambas de
  `emissoes/{id}/danfe-pdf`**. O próprio arquivo se autodenuncia no rodapé (*"rota `/danfe`
  assumida; confirmar nome real no controller"*). Corrigir exige a `emissao_id` na tela — hoje ela
  só recebe `transaction_id`. É mudança de `.tsx` + payload → **decisão [W]**.
- `[BACKLOG]` **O link "Detalhes" do Sells passa o id errado.** `Pages/Sells/_components/FiscalSection.tsx`
  monta `/nfe-brasil/transactions/${em.id}/status` com `em.id` = **id da emissão**, enquanto a rota
  espera `{tx}` = id da **transaction**. No mesmo bloco, o link do DANFE usa `em.id` corretamente
  (aquela rota **é** por emissão). Fora da área deste chip (`Pages/Sells/**`) — reportado, não
  tocado.
- `[BACKLOG]` **Non-Goals do charter conflitam com a tela.** O charter declara `❌ Reemissão NFC-e`
  e `❌ Download direto DANFE`; o `.tsx` implementa os dois. Como o charter é `draft` (Non-Goals
  *"aguardam aprovação Wagner"*), os ❌ **não** viraram `[must]`. Aprovar ou reconciliar é [W].
- `[BACKLOG]` **Cap de polling e cadência.** O charter promete 2s ± 200ms e parada após 30 tentativas;
  o hook implementa. É comportamento de **cliente** — precisaria de Playwright/Pest Browser, não de
  Pest HTTP. Existe `tests/Browser/NfeBrasil/NfceStatusTest.php`, declarado *characterization* do
  estado S0 (fotografa o que há, não defende contrato).

---

## Correções factuais aplicadas ao charter nesta corrida

> Fase 2.6 do [`sdd-from-source`](../../../../.claude/agents/sdd-from-source.md): **fato sim,
> intenção não.** Nenhum Non-Goal, Anti-hook, Goal, persona ou escopo foi tocado.

| O que o charter dizia | O que é (varredura contada) |
|---|---|
| `NfceStatusController::show` | a classe é `NfeStatusController`; o método da **página** é `showPage` |
| `GET /nfe-brasil/transactions/{tx}/nfce/status` | a rota real é `GET /nfe-brasil/api/transactions/{tx}/nfe-status` |
| `NfeService::consultarStatusEmissao(transaction_id)` | **não existe** — `grep` no repo inteiro devolve **1** ocorrência, a própria linha do charter |
| §Métricas vivas: `Tests/Charters/NfceStatusCharterTest.php` (10 `it`) | o diretório `Tests/Charters/` **não existe**; o teste real é `Tests/Feature/NfeStatusControllerTest.php` |

---

## Recibo de execução

| Quando | Onde | Resultado |
|---|---|---|
| _pendente_ | lane `PHP / Pest (NfeBrasil · MySQL)` — **required, `enforce_admins`** | a preencher com o run id |

> ⚠️ **Este arquivo de teste NÃO foi adicionado à allowlist da lane.** A allowlist do
> `nfebrasil-pest.yml` é uma **catraca por prova verde** (o cabeçalho do workflow declara:
> *"cresce por ratchet conforme prova verde"*); esta corrida **não roda teste**
> ([ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)), logo não
> tem a prova que a catraca exige — e arquivo não provado ali **bloqueia o merge de todos**. A
> proposta de ratchet-up está no [SDD §8.3](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md).
> **Isso não é "verde impossível":** `phpunit.xml` + `scripts/tests/shards-plan.mjs` já fazem
> `Modules/NfeBrasil/Tests` rodar na suíte noturna do CT 100, e `modules-pest.yml` roda no PR (em
> SQLite, onde os casos MySQL-only pulam).
>
> ⚠️ **Ao ler o resultado, conferir a contagem de testes passados no JUnit, não só o check verde** —
> suíte que SKIPa fica verde igual (gate mudo, [proibicoes §5](../../../../../memory/proibicoes.md)
> 2026-07-27). O `oimpresso-staging` do CT 100 **não tem as tabelas do NfeBrasil**, então lá a
> suíte pula inteira.
>
> Os `Status: 🧪` só sobem para ✅ quando o manifesto do G-7 (`scripts/casos-test-results.json`)
> for regravado a partir do JUnit — ✅ é afirmação que exige prova.
