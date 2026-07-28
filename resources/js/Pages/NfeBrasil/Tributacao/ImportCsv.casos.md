---
id: resources-js-pages-nfe-brasil-tributacao-import-csv-casos
casos: Import CSV de regras tributárias — 2 passos, e em qual tenant grava · /nfe-brasil/tributacao/import
irmaos: ImportCsv.charter.md (lei) · SDD-emissao-fiscal-v1.0.md (§5.3 F8 · §6.2)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a única porta que escreve CENTENAS de regras tributárias de uma vez — e ela resolve o tenant DUAS vezes, em momentos diferentes.
owner: wagner
last_run: "2026-07-28"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (NfeBrasil · MySQL)"
---

# Casos de Uso & Aceite — Import CSV de regras tributárias

> **Âncora:** os UC derivam do [**SDD do módulo**](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md)
> §5.3 (F8) e §6.2 (`CU-NFE-07`), que derivam de
> [**US-NFE-010 fase 3**](../../../../../memory/requisitos/NfeBrasil/SPEC.md),
> [**ADR arq/0006**](../../../../../memory/requisitos/NfeBrasil/adr/arq/0006-cascade-defaults-ncm-produto.md)
> (as linhas do CSV são Níveis 2/3 da cascade) e do [**charter**](ImportCsv.charter.md). O
> `ImportRegrasController` e o `ImportRegrasCsvService` foram lidos só para **confirmar**.
>
> **O que este arquivo NÃO cobre:** as **regras de parse linha a linha** (NCM 8 dígitos, UF válida,
> CFOP 4 dígitos, CSOSN×CST mutuamente exclusivos) já têm cobertura dedicada em
> `ImportRegrasCsvServiceTest`. Aqui o recorte é o **fluxo de 2 passos e o tenant** — o que o
> Service sozinho não pode provar, porque quem resolve o tenant é o Controller.
>
> ⚠️ **O charter é `status: draft`.** Os `[must]` abaixo derivam de **ADR 0093 + US-NFE-010 + o
> item de mecanismo do charter** (*"`aplicar` escopa por `business.id` da sessão"*) — que é
> afirmação verificável sobre o funcionamento, não Non-Goal de produto.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e o veredito é da lane ·
> ⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Contrato | Teste | Status |
|----|-------------|------|----------|-------|--------|
| UC-NFIM-01 | O preview confere sem gravar nada | must `[V0]` | charter §Non-Goals (mecanismo) · SDD `CU-NFE-07` | `TributacaoGatesContratoTest` | 🧪 |
| UC-NFIM-02 | Aplicar sem preview não grava nada | must `[V0]` | SDD `CU-NFE-07` item 2 | `TributacaoGatesContratoTest` | 🧪 |
| UC-NFIM-03 | Aplicar exige a permissão fiscal | must `[T0]` `[V0]` | charter §Backend · US-NFE-010 | `TributacaoGatesContratoTest` | 🧪 |
| UC-NFIM-04 | O que foi conferido num tenant não pode ser gravado noutro | must `[T0]` `[V0]` | ADR 0093 · charter §Non-Goals | `TributacaoGatesContratoTest` | ❌ **falha esperada** |

> **Recibo:** ver §Recibo de execução no rodapé. O `❌` do UC-NFIM-04 é **o achado** — ver a nota lá.

---

## UC-NFIM-01 · O preview confere sem gravar nada · `must` `[V0]`

- **Persona:** responsável fiscal subindo 300 linhas de NCM de uma planilha do contador. O passo de
  conferência só vale se ele for **inócuo**: se o preview já gravasse, "conferir antes" seria
  ficção e um CSV errado já teria mudado a tributação da empresa antes de qualquer decisão humana.
- **Aceite:** Dado um CSV com linhas válidas · Quando faço o **preview** · Então vejo a contagem de
  válidas e os erros, e **nenhuma** linha nova aparece em `nfe_fiscal_rules`. Controle positivo no
  mesmo caso: **depois** de aplicar, as linhas aparecem — senão "nada gravado" seria verdade por o
  CSV não ter sido lido.
- **Teste:** [`TributacaoGatesContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoGatesContratoTest.php)
  — `UC-NFIM-01 · preview não grava regra nenhuma; aplicar depois grava`.
- **Contrato:** charter §Non-Goals (*"❌ Não persiste nada no passo de preview — só parseia e
  mostra; a gravação é exclusiva do `aplicar`"*) + §Anti-hooks (*"❌ Preview nunca grava"*) + SDD
  [`CU-NFE-07`](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md) item 1.
- **Regressão que defende:** a separação hoje é só **convenção de método** — o `preview` chama
  `parse()` e o `aplicar` chama `aplicar()`; nada impede um refactor de "economizar um round-trip"
  de mover a gravação para o primeiro passo (a tentação existe porque as linhas já estão parseadas
  ali). O `[V0]` é literal: 300 regras gravadas sem conferência mudam a alíquota de 300 famílias de
  produto de uma vez.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFIM-02 · Aplicar sem preview não grava nada · `must` `[V0]`

- **Persona:** o operador que recarregou a página, ou cujo POST de aplicar chegou depois de a
  sessão ter sido limpa. O certo é dizer *"faça o upload de novo"*; o errado é gravar vazio e
  reportar sucesso.
- **Aceite:** Dado que **não há** linhas guardadas do passo anterior · Quando aplico · Então volto
  com erro de campo pedindo o upload, e o total de regras do meu business **não muda**.
- **Teste:** [`TributacaoGatesContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoGatesContratoTest.php)
  — `UC-NFIM-02 · aplicar sem preview anterior é recusado e não grava`.
- **Contrato:** SDD [`CU-NFE-07`](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md)
  item 2 + charter §Automation hooks (*"Linhas válidas do preview ficam em sessão … e são
  consumidas + limpas no aplicar"*).
- **Regressão que defende:** o guard é `if (empty($linhas)) return …withErrors(...)`. Sem ele,
  `aplicar($businessId, [])` percorre zero linhas, devolve `['criadas'=>0,…]` e a tela mostra
  **"import concluído"** — falso sucesso, que é pior que erro porque o operador vai embora achando
  que importou. O caso semeia uma regra existente antes, para poder afirmar que a contagem **não
  mudou** em vez de afirmar que "está vazio".
- **Status: 🧪** — ver §Recibo.

---

## UC-NFIM-03 · Aplicar exige a permissão fiscal · `must` `[T0]` `[V0]`

- **Persona:** usuário do tenant sem responsabilidade fiscal. Esta é a porta de **maior alcance por
  clique** do módulo: um POST reescreve centenas de regras de uma vez.
- **Aceite:** Dado um usuário **sem** `nfe.tributacao.manage`, e linhas válidas prontas do passo
  anterior · Quando aplica · Então **403**, e nenhuma regra é criada nem atualizada. Controle
  positivo: **com** a permissão, as linhas entram.
- **Teste:** [`TributacaoGatesContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoGatesContratoTest.php)
  — `UC-NFIM-03 · aplicar sem nfe.tributacao.manage dá 403 e não grava`.
- **Contrato:** §Backend do [charter](ImportCsv.charter.md) (*"permissão `nfe.tributacao.manage`"*)
  + [US-NFE-010](../../../../../memory/requisitos/NfeBrasil/SPEC.md) + o
  `if (! $request->user()?->can('nfe.tributacao.manage')) abort(403)` explícito no Controller.
- **Regressão que defende:** aqui o gate é **explícito no Controller**, e é o único assim em toda a
  superfície de tributação (o `preview` gateia pelo FormRequest; `TributacaoController` não gateia
  em 3 de 5 mutações — SDD §5.4.1). Justamente por ser a exceção boa, é a que um "padroniza tudo
  igual ao resto" apaga. Note que este UC e o `UC-NFIM-04` medem coisas **diferentes**: quem pode
  (permissão) × onde grava (tenant).
- **Status: 🧪** — ver §Recibo.

---

## UC-NFIM-04 · O que foi conferido num tenant não pode ser gravado noutro · `must` `[T0]` `[V0]` — ❌ **falha esperada**

- **Persona:** um usuário com acesso a **dois** negócios (contador do grupo, ou o próprio [W], que
  troca de business pelo seletor). Ele confere o CSV do negócio A, é interrompido, troca para o
  negócio B para ver outra coisa, e volta para clicar em "Aplicar".
- **Aceite:** Dado que fiz o preview logado no business A · Quando o tenant da sessão passa a ser o
  business B e eu aplico · Então as regras **não** entram em B. (Qualquer das duas saídas honestas
  serve ao contrato: recusar a aplicação, ou gravar em A — o que **não** pode é gravar em B.)
- **Teste:** [`TributacaoGatesContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoGatesContratoTest.php)
  — `UC-NFIM-04 · linhas conferidas no business A não podem ser gravadas no business B`.
- **Contrato:** [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
  + charter §Non-Goals (*"❌ Não importa para outro tenant … as regras entram só no negócio
  corrente"*) + SDD [§5.3 F8](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md).
- **Por que nasce ❌ e por que isso é o certo:** medido — o fluxo resolve o tenant **duas vezes, em
  requests diferentes**. O `preview` parseia e guarda as linhas em `session('nfe_import_csv_linhas')`
  **sem carimbar o business**; o `aplicar` lê `session('business.id')` **naquele instante** e chama
  `aplicar($businessId, $linhas)`. Nada amarra as linhas ao tenant em que foram conferidas. O
  resultado não é erro nem aviso: são regras tributárias silenciosamente gravadas na empresa errada,
  e o sintoma aparece só na nota emitida por ela. O teste é *failing-first* — o `❌` **é o achado**,
  com o vermelho da lane como recibo ([proibicoes §Precedência](../../../../../memory/proibicoes.md)).
- **A saída é decisão [W], e há pelo menos três:** (a) carimbar o `business_id` junto das linhas na
  sessão e recusar se divergir no aplicar; (b) chavear a chave de sessão por business
  (`nfe_import_csv_linhas.{biz}`); (c) declarar o cenário fora de escopo por Non-Goal. O agent
  **não** escolheu — por isso o aceite acima aceita as duas primeiras e só proíbe o vazamento.
- **Nota de método:** o assert é sobre **onde a regra existe depois** (nenhuma linha do CSV em B),
  não sobre a mensagem nem sobre o status HTTP — status acoplado reprovaria arbitrariamente a saída
  (a), que devolve erro, ou a (b), que devolve sucesso.
- **Status: ❌ esperado** — ver §Recibo. Se vier **verde**, o guard foi adicionado entre esta
  escrita e a corrida: reconciliar o SDD §5.3 F8 e §9 R2 no mesmo PR.

---

## Backlog — sem UC até ganhar teste ou contrato

- `[BACKLOG]` **O GET da tela é ungated.** `ImportRegrasController@show` não checa permissão — só
  as duas mutações checam. Ele entrega apenas a constante `COLUNAS_OBRIGATORIAS`, então não há
  vazamento de dado; o que existe é uma tela de escrita fiscal visível a qualquer usuário do tenant.
  Gatear ou não é decisão.
- `[BACKLOG]` **Linhas parseadas vão inteiras para a sessão, sem limite.** `session()->put('nfe_import_csv_linhas', $resultado['linhas'])`
  guarda todas as linhas válidas — um CSV de 5 MB (o teto do upload declarado no charter) vira um
  payload de sessão proporcional. Não há cap. Vira UC quando houver um limite declarado em alguma
  fonte; hoje seria caso derivado da implementação.
- `[BACKLOG]` **Idempotência do import pela chave natural.** O charter promete *"idempotente pela
  chave (NCM + UF origem + UF destino)"* e o Service implementa por `first()` + `update()`/`create()`.
  `ImportRegrasCsvServiceTest` cobre o Service; falta o caso ponta-a-ponta que reimporta o mesmo CSV
  e prova que a contagem não dobra.
- `[BACKLOG]` **`confirm()` antes de aplicar.** Charter §Anti-hooks pede confirmação explícita e o
  `.tsx` implementa. Comportamento de **cliente** — precisaria de e2e; a tela não tem spec.

---

## Recibo de execução

| Quando | Onde | Resultado |
|---|---|---|
| _pendente_ | lane `PHP / Pest (NfeBrasil · MySQL)` — **required, `enforce_admins`** | a preencher com o run id |

> ⚠️ **Este arquivo de teste NÃO foi adicionado à allowlist da lane** — pelas mesmas duas razões do
> `RegraForm.casos.md`: a allowlist é **catraca por prova verde** (e esta corrida não roda teste,
> [ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)) e o
> `UC-NFIM-04` nasce **vermelho por desenho** — numa lane required com `enforce_admins`, isso
> **bloquearia o merge de todos**. Ratchet-up proposto no
> [SDD §8.3](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md).
>
> ⚠️ **Conferir a contagem de testes passados no JUnit, não só o check verde.**
