---
id: resources-js-pages-nfe-brasil-manifestacao-index-casos
casos: Manifestação do Destinatário — DF-e recebidos, 4 eventos SEFAZ, lote e sync NSU · /nfe-brasil/manifestacao
irmaos: Index.charter.md (lei) · SDD-emissao-fiscal-v1.0.md (§5.3 F9/F10 · §6.3)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: manifestar é obrigação fiscal com prazo legal — e o que esta tela faz em LOTE não existe em nenhuma outra.
owner: wagner
last_run: "2026-07-30"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (NfeBrasil · MySQL)"
---

# Casos de Uso & Aceite — Manifestação do Destinatário

> **Âncora — e o que este arquivo DELIBERADAMENTE não cobre.** O contrato dos **4 eventos SEFAZ**
> (whitelist fechada, quais exigem justificativa, o que conta como "pendente de manifestação") já
> está escrito e enforçado do outro lado da fronteira, em
> [**`CU-FISC-07`**](../../../../../memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md) — porque a
> tela `/fiscal/dfe` faz a mesma manifestação chamando o **mesmo** `ManifestacaoService`.
> Reescrever aqui criaria **dois vereditos para o mesmo comportamento**, que é dívida, não
> cobertura ([SDD §5.5](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md)).
>
> Este arquivo cobre **só o que existe apenas nesta tela**: confirmação em **lote**, **sync NSU** sob
> demanda, os **JSON lazy** dos painéis laterais, e o append-only/idempotência do evento.
> Fonte: SDD §5.3 (F9 · F10) e §6.3 (`CU-NFE-08` · `CU-NFE-09` · `CU-NFE-10` · `CU-NFE-11`), que
> derivam de [**US-NFE-052**](../../../../../memory/requisitos/NfeBrasil/SPEC.md) +
> [**US-NFE-050**](../../../../../memory/requisitos/NfeBrasil/SPEC.md) +
> [**ADR 0116**](../../../../../memory/decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md)
> + o [charter](Index.charter.md). O Controller e o Service foram lidos só para **confirmar**.
>
> ⚠️ **O charter se contradiz sobre o próprio status:** frontmatter `status: draft`, corpo
> *"**Status:** live em 2026-05-10 … Non-Goals + Anti-hooks aprovados por Wagner em 2026-05-10"*.
> Os dois não podem estar certos, e `draft → live` é **promoção** (muda o que vira `[must]`), não
> fato — logo **não foi corrigido**: é decisão [W]. Por conservadorismo, os `[must]` abaixo derivam
> de **ADR/SPEC/Service**, nunca de um `❌` do charter sozinho.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e o veredito é da lane ·
> ⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Contrato | Teste | Status |
|----|-------------|------|----------|-------|--------|
| UC-NFMA-01 | Lote confirma só o que é meu e está pendente | must `[T0]` `[fiscal]` | ADR 0093 · SDD `CU-NFE-08` | `ManifestacaoContratoTest` | 🧪 |
| UC-NFMA-02 | Lote vazio não dispara SEFAZ | must `[fiscal]` | SDD `CU-NFE-08` item 3 | `ManifestacaoContratoTest` | 🧪 |
| UC-NFMA-03 | Sync sob demanda enfileira com o meu tenant, não executa no request | must `[T0]` | SDD `CU-NFE-09` · ADR 0093 | `ManifestacaoContratoTest` | 🧪 |
| UC-NFMA-04 | Sem a permissão de gerenciar, nenhuma mutação passa | must `[T0]` | charter §Goals (permissões) · SDD `CU-NFE-09` | `ManifestacaoContratoTest` | 🧪 |
| UC-NFMA-05 | Os painéis laterais não abrem DF-e de outro tenant | must `[T0]` | ADR 0093 · SDD `CU-NFE-11` | `ManifestacaoContratoTest` | 🧪 |
| UC-NFMA-06 | A lista e os KPIs contam só o meu business | must `[T0]` | ADR 0093 · charter §Anti-hooks | `ManifestacaoContratoTest` | 🧪 |

> **Recibo:** ver §Recibo de execução no rodapé.

---

## UC-NFMA-01 · Lote confirma só o que é meu e está pendente · `must` `[T0]` `[fiscal]`

- **Persona:** responsável fiscal com 40 NF-e de fornecedor na caixa, confirmando tudo de uma vez
  no fim do dia. Cada confirmação é um **evento registrado na SEFAZ** — confirmar a nota de outro
  CNPJ, ou confirmar duas vezes, é fato fiscal, não erro de tela.
- **Aceite:** Dado um lote com (a) uma DF-e **pendente minha**, (b) uma DF-e **já confirmada
  minha** e (c) uma DF-e **pendente de outro business** · Quando confirmo o lote · Então **só a
  (a)** é manifestada: a (b) não gera segundo evento e a (c) permanece **intocada no banco** —
  mesmo status, sem evento novo. A resposta reporta a contagem do que foi feito.
- **Teste:** [`ManifestacaoContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/ManifestacaoContratoTest.php)
  — `UC-NFMA-01 · bulk confirma só a pendente do próprio business e não toca as demais`.
- **Contrato:** [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) +
  SDD [`CU-NFE-08`](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md) itens 1–2
  + `ManifestacaoService` (`JUSTIFICATIVA_MIN_CHARS`, idempotência por evento `autorizado` do mesmo
  tipo) + charter §Goals (*"Bulk-confirmar via checkbox"*).
- **Regressão que defende:** o filtro do lote é uma tripla no `where` —
  `business_id` **+** `id` **+** `status_manifestacao = 'pendente'`. Cada uma protege de uma coisa
  diferente: tirar a 1ª manifesta a nota do vizinho; tirar a 3ª gera evento duplicado (cstat 573).
  E é exatamente o tipo de `where` que um refactor de "simplificar a query do bulk" enxuga. O caso
  carrega os **três** cenários juntos porque só assim se distingue "ignorou por não ser meu" de
  "ignorou por não estar pendente" — com um cenário só, o verde não diz qual guard sobreviveu.
- **Nota de método:** o assert é sobre **estado no banco depois** (a (c) continua `pendente`, sem
  linha nova em `nfe_dfe_eventos`), não sobre a mensagem de flash: a contagem em texto é
  apresentação e pode mudar; o que a nota alheia sofreu, não.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFMA-02 · Lote vazio não dispara SEFAZ · `must` `[fiscal]`

- **Persona:** operador que clicou em "Confirmar selecionadas" sem ter selecionado nada. O certo é
  avisar; o errado é o sistema interpretar "nenhum id" como "todos".
- **Aceite:** Dado um POST de lote **sem ids** (ou com lista vazia) · Quando processo · Então volto
  com **erro** e **nenhuma** DF-e pendente do meu business muda de status.
- **Teste:** [`ManifestacaoContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/ManifestacaoContratoTest.php)
  — `UC-NFMA-02 · bulk sem ids devolve erro e não manifesta nada`.
- **Contrato:** SDD [`CU-NFE-08`](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md)
  item 3 + charter §UX Anti-patterns (*"❌ Bulk-action sem confirmação"*).
- **Regressão que defende:** o guard é `if (! is_array($ids) || count($ids) === 0)`. Sem ele, o
  `foreach` sobre lista vazia é inofensivo — mas a variante perigosa é o "conserto" que trata lista
  vazia como *"então aplica no filtro atual"*, padrão comum em telas de lote. O caso semeia uma
  DF-e pendente **de propósito**: sem ela, "nada mudou" seria verdade por não haver nada — verde
  por não-execução ([proibicoes §5](../../../../../memory/proibicoes.md) 2026-07-24).
- **Status: 🧪** — ver §Recibo.

---

## UC-NFMA-03 · Sync sob demanda enfileira com o meu tenant, não executa no request · `must` `[T0]`

- **Persona:** responsável fiscal que sabe que chegou nota nova e não quer esperar o schedule. A
  SEFAZ pode levar 30s+; se a busca rodasse no request, a tela travaria — e o pior: em fila
  **não existe `session()`**, então o tenant tem que viajar dentro do job.
- **Aceite:** Dado que eu peço "sincronizar agora" · Então (1) o job de busca é **despachado** (não
  executado inline), (2) ele carrega o `business_id` **do meu** tenant, e (3) a resposta volta
  imediatamente com aviso de sucesso.
- **Teste:** [`ManifestacaoContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/ManifestacaoContratoTest.php)
  — `UC-NFMA-03 · sync-now despacha o job de busca carregando o business do tenant`.
- **Contrato:** SDD [`CU-NFE-09`](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md)
  + [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) (*"Job
  assíncrono SEMPRE passa `$businessId` no constructor — `session()` não funciona em fila"*) +
  charter §Automation Hooks + §UX Targets (*"Sync NSU manual < 4000ms (async via Job)"*).
- **Regressão que defende:** este é o caso em que um bug de multi-tenant **não aparece na tela**:
  um job despachado sem `businessId` (ou com o `business_id` errado) roda no worker, grava DF-e no
  tenant errado e ninguém vê até a manifestação sair no CNPJ de outro. O caso assere a **carga do
  job**, não só que ele foi enfileirado — "foi despachado" é presença; o que importa é **com qual
  tenant**.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFMA-04 · Sem a permissão de gerenciar, nenhuma mutação passa · `must` `[T0]`

- **Persona:** um usuário do tenant com acesso de **leitura** fiscal (`nfe.manifestacao.view`) —
  típico de auxiliar administrativo. Ele pode **ver** a caixa de DF-e; não pode **responder à SEFAZ
  em nome da empresa**.
- **Aceite:** Dado um usuário com `view` e **sem** `manage` · Quando tenta confirmar em lote ou
  sincronizar · Então **403** nos dois, e **nada** muda. Controle positivo no mesmo caso: com
  `manage`, os dois passam — senão o 403 poderia vir de qualquer outra coisa (rota, CSRF, sessão).
- **Teste:** [`ManifestacaoContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/ManifestacaoContratoTest.php)
  — `UC-NFMA-04 · usuário com view mas sem manage recebe 403 nas mutações`.
- **Contrato:** charter §Goals (*"Permissões granular: `view` (index + JSON) vs `manage`
  (mutações)"*) + [SPEC US-NFE-052](../../../../../memory/requisitos/NfeBrasil/SPEC.md) + o docblock
  do `ManifestacaoController` (*"Permissão: `nfe.manifestacao.view` (index) + `nfe.manifestacao.manage`
  (mutations)"*) — **três** fontes concordando.
- **Regressão que defende:** o gate é um `abort_unless($this->canManage(), 403)` **repetido em cada
  método** — `aplicarEvento`, `bulkConfirmar`, `syncNow`. Repetição é frágil por construção: método
  novo nasce sem o guard e ninguém percebe. Este é o **único** Controller de mutação do módulo que
  gateia de verdade (SDD §5.4.1 mede que `TributacaoController` tem **0** ocorrências de
  `can(`/`abort`) — travá-lo é impedir que a boa prática regrida para a média do módulo.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFMA-05 · Os painéis laterais não abrem DF-e de outro tenant · `must` `[T0]`

- **Persona:** qualquer tenant. Os painéis `LinkedItens` e `LinkedHistorico` buscam por **id
  numérico** via JSON — é a superfície mais fácil de enumerar do módulo (trocar o número na URL).
  O que eles devolvem são **itens da nota** (NCM, CFOP, quantidade, valor) e o **histórico de
  eventos**: o retrato comercial de uma compra alheia.
- **Aceite:** Dada uma DF-e de outro business · Quando peço os itens ou os eventos dela · Então
  **404** nos dois. Controle positivo: nos meus, **200** com o conteúdo.
- **Teste:** [`ManifestacaoContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/ManifestacaoContratoTest.php)
  — `UC-NFMA-05 · itens e eventos de DFe de outro business dão 404, e os do próprio devolvem 200`.
- **Contrato:** [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) +
  charter §Automation Anti-hooks (*"❌ Não acessa DFe de outro `business_id`"*) + SDD
  [`CU-NFE-11`](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md) item 2.
- **Regressão que defende:** o guard é `where('business_id')->where('id')->firstOrFail()`.
  A tentação de refactor é `NfeDfeRecebido::findOrFail($id)` "porque o global scope resolve" — e
  resolve **em runtime autenticado**, mas some a defesa em profundidade e passa a depender de uma
  única camada. O caso roda com `actingAs` e semeia as **duas** chaves de sessão do módulo
  (`business.id` e `user.business_id` — SDD §5.4.2), então mede as duas camadas de verdade em vez
  de acidentalmente medir só uma.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFMA-06 · A lista e os KPIs contam só o meu business · `must` `[T0]`

- **Persona:** qualquer tenant abrindo a tela. Ver a caixa de entrada fiscal do vizinho revela
  fornecedor, valor e volume de compra — dado comercial, além de fiscal.
- **Aceite:** Dada 1 DF-e pendente minha e 1 DF-e pendente de outro business · Quando a tela é
  montada · Então a lista traz **exatamente** a minha (controle positivo) e o KPI de pendentes
  conta **1**, não 2.
- **Teste:** [`ManifestacaoContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/ManifestacaoContratoTest.php)
  — `UC-NFMA-06 · listagem e KPI de pendentes contam só o próprio business`.
- **Contrato:** idem UC-NFMA-05 + charter §Goals (*"KPIs no topo … queries scoped por
  `business_id`"*) + §UX Anti-patterns (*"❌ Mostrar DFe de outro tenant na lista"*).
- **Regressão que defende:** aqui há uma **assimetria real, medida** (SDD §5.4.2):
  `buildKpisPayload` tem `where('business_id', $businessId)` explícito, e `buildItensPayload`
  **não** — a listagem confia **só** no global scope. Como o `ScopeByBusiness` faz early-return em
  `! auth()->check()`, qualquer teste sem `actingAs` deixaria a listagem passar verde com o scope
  desligado. Este caso é o que impede a listagem de virar a metade descoberta do par: a asserção
  cobre **lista e KPI juntos**, que é onde a assimetria mora.
- **Status: 🧪** — ver §Recibo.

---

## Backlog — sem UC até ganhar teste ou contrato

> Prosa honesta, sem gate. Vira UC quando ganhar teste que o cite (G-2) — ou, no caso dos dois
> últimos, quando [W] decidir.

- `[BACKLOG]` **Idempotência do evento é do schema, não só do código.** O `ManifestacaoService`
  devolve o evento existente sem tocar a SEFAZ, e o UNIQUE
  `(business_id, dfe_recebido_id, tipo, nseq_evento)` é a rede embaixo. `ManifestacaoServiceTest`
  cobre o caminho do Service; **falta** o caso que prova a constraint no MySQL real. Vira UC quando
  houver teste que tente a inserção duplicada direto na tabela.
- `[BACKLOG]` **`cienciar` é aceito em DF-e já manifestada; os outros três não.** É o
  `if ($dfe->status_manifestacao !== 'pendente' && $metodo !== 'cienciar')`. A regra parece
  intencional (ciência é registrável a qualquer momento), mas **não está escrita em fonte nenhuma
  além do próprio código** — logo seria caso derivado da implementação. Vira UC quando a NT
  2014.002 ou o charter disserem isso explicitamente.
- `[BACKLOG]` **AuditLog nas mutações de manifestação.** O charter §Automation Hooks pede
  `activity('nfe.manifestacao')->log()` e aponta a **US-NFE-062** como a entrega. Medido: as
  mutações de manifestação **não** chamam `activity(...)` hoje (as de tributação chamam). Deixo na
  US aberta em vez de duplicar o pedido aqui — UC não é canal de pedido
  ([proibicoes §5](../../../../../memory/proibicoes.md) 2026-07-16).
- `[BACKLOG]` **A tela não usa `Inertia::defer`, contra a skill `inertia-defer-default`.** O código
  declara o motivo: *"ROLLBACK Wave L/W7 PR #963: `Inertia::defer` quebrava Pages (initial render
  undefined)"*. É desvio **consciente**, não esquecimento — mas o charter promete p95 < 1500ms com
  `paginate(50)` + 3 counts + nsuState **todos eager**. Medir isso é trabalho de perf, não de Pest.
- `[BACKLOG]` **Atalhos `J/K/C/D/R`, persistência de filtro em `localStorage`, confirmação
  destrutiva.** Todos comportamento de **cliente** — exigem Playwright/Pest Browser. A tela não tem
  spec e2e hoje.
- `[BACKLOG]` **Duas telas manifestam a mesma DF-e** (`/nfe-brasil/manifestacao` e `/fiscal/dfe`),
  com whitelists de ação e regras de prazo escritas **em lugares diferentes**. O SDD do Fiscal já
  registra a fronteira; consolidar (ou declarar qual é a oficial) é **decisão [W]** — ver
  [SDD §5.5](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md).

---

## Correções factuais aplicadas ao charter nesta corrida

> Fase 2.6: **fato sim, intenção não.** Nenhum Non-Goal, Anti-hook, Goal, persona ou escopo tocado.
> O `status: draft` × *"live"* do frontmatter **não** foi mexido — é promoção, decisão [W].

| O que o charter dizia | O que é (varredura contada) |
|---|---|
| §Métricas vivas: `Tests/Charters/ManifestacaoCharterTest.php` (13 `it`) | o diretório `Tests/Charters/` **não existe** (`find Modules/NfeBrasil/Tests -iname "*Charter*"` → 0); os testes reais são `Tests/Feature/ManifestacaoControllerTest.php` e `ManifestacaoServiceTest.php` |
| `ManifestacaoService::aplicarEvento(id, type)` | a assinatura real é `aplicarEvento(NfeDfeRecebido $dfe, string $tipo, string $justificativa = '')` e o método é **privado** — a API pública são `cienciar`/`confirmar`/`desconhecer`/`naoRealizada` |
| `confirmar → evento 220 SEFAZ` (e `desconhecer → 220`) | os códigos são distintos e constantes no Model: `210210` ciência · `210200` confirmação · `210220` desconhecimento · `210240` não realizada |

---

## Recibo de execução

| Quando | Onde | Resultado |
|---|---|---|
| _pendente_ | lane `PHP / Pest (NfeBrasil · MySQL)` — **required, `enforce_admins`** | a preencher com o run id |

> ⚠️ **Este arquivo de teste NÃO foi adicionado à allowlist da lane** — mesma razão do
> `NfceStatus.casos.md`: a allowlist é **catraca por prova verde** e esta corrida não roda teste
> ([ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)). Proposta
> de ratchet-up em [SDD §8.3](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md).
>
> ⚠️ **Conferir a contagem de testes passados no JUnit, não só o check verde** — a suíte SKIPa em
> SQLite e o `oimpresso-staging` do CT 100 **não tem as tabelas do NfeBrasil**, então lá ela pula
> inteira e fica verde igual (gate mudo).
>
> Os `Status: 🧪` só sobem para ✅ quando o manifesto do G-7 for regravado a partir do JUnit.
