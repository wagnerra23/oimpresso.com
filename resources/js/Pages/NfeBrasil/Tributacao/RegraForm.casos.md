---
id: resources-js-pages-nfe-brasil-tributacao-regra-form-casos
casos: Regra tributária por NCM — quem pode mexer, e em que tenant grava · /nfe-brasil/tributacao/regras/{create,edit}
irmaos: RegraForm.charter.md (lei) · SDD-emissao-fiscal-v1.0.md (§5.3 F7 · §5.4.1 · §6.2)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: esta regra decide a alíquota que sai na nota — errar aqui é multa fiscal, não bug de tela.
owner: wagner
last_run: "2026-07-28"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (NfeBrasil · MySQL)"
---

# Casos de Uso & Aceite — Regra tributária por NCM (Níveis 2/3 da cascade)

> **Âncora:** os UC derivam do [**SDD do módulo**](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md)
> §6.2 (`CU-NFE-06`), §5.3 (F6/F7) e §5.4.1, que derivam de
> [**ADR arq/0006**](../../../../../memory/requisitos/NfeBrasil/adr/arq/0006-cascade-defaults-ncm-produto.md)
> (cascade em 4 níveis — esta tela edita os **Níveis 2 e 3**),
> [**US-NFE-010**](../../../../../memory/requisitos/NfeBrasil/SPEC.md) e do
> [**charter**](RegraForm.charter.md), que declara textualmente *"permissão `nfe.tributacao.manage`
> validada no FormRequest"*. O Controller foi lido só para **confirmar**.
>
> **O que este arquivo NÃO cobre:** a **ordem de precedência** exibida na listagem é `UC-NFTR-06` e
> o isolamento de `update`/`destroy` já é `UC-NFTR-04` (ambos em
> [`Tributacao/Index.casos.md`](Index.casos.md), em `origin/main`). O Nível 4 é `UC-NFCD-*`.
> Não reabertos — dois vereditos para o mesmo comportamento é dívida.
>
> ⚠️ **O charter é `status: draft`** — §Pendências diz *"[ ] Wagner aprova Non-Goals + Anti-hooks"*.
> Logo nenhum `❌` do charter virou `[must]` sozinho aqui: os `[must]` derivam de **ADR/SPEC + o
> texto do charter sobre permissão**, que é afirmação de arquitetura, não Non-Goal.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e o veredito é da lane ·
> ⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Contrato | Teste | Status |
|----|-------------|------|----------|-------|--------|
| UC-NFRF-01 | Criar/editar regra exige a permissão fiscal | must `[T0]` `[V0]` | SDD `CU-NFE-06` · charter §Backend · US-NFE-010 | `TributacaoGatesContratoTest` | 🧪 |
| UC-NFRF-02 | A regra nasce no meu tenant, nunca no de quem a rota apontar | must `[T0]` `[V0]` | ADR 0093 · SDD `CU-NFE-11` · charter §Non-Goals | `TributacaoGatesContratoTest` | 🧪 |
| UC-NFRF-03 | Abrir a edição de regra alheia é 404 — e não vaza a alíquota dela | must `[T0]` | ADR 0093 · SDD `CU-NFE-11` | `TributacaoGatesContratoTest` | 🧪 |
| UC-NFRF-04 | Apagar regra exige a permissão fiscal | must `[T0]` `[V0]` | SDD `CU-NFE-06` · US-NFE-010 · SDD §5.4.1 | `TributacaoGatesContratoTest` | ❌ **falha esperada** |

> **Recibo:** ver §Recibo de execução no rodapé. O `❌` do UC-NFRF-04 é **o achado**, não um
> conserto pendente de disfarce — ver a nota lá.

---

## UC-NFRF-01 · Criar/editar regra exige a permissão fiscal · `must` `[T0]` `[V0]`

- **Persona:** um usuário de caixa autenticado no tenant. Ele não deve conseguir alterar a alíquota
  de ICMS que vai sair impressa na próxima nota — essa é decisão do responsável fiscal, e o efeito
  dela é dinheiro recolhido a menor (ou a maior) perante o fisco.
- **Aceite:** Dado um usuário **sem** `nfe.tributacao.manage` · Quando ele tenta criar
  (`POST /regras`) ou editar (`PUT /regras/{id}`) uma regra · Então **403** nos dois, e **nada** é
  escrito em `nfe_fiscal_rules`. Controle positivo no mesmo caso: **com** a permissão, a criação e
  a edição passam e o dado muda — senão o 403 poderia vir do payload, da rota ou do CSRF.
- **Teste:** [`TributacaoGatesContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoGatesContratoTest.php)
  — `UC-NFRF-01 · criar e editar regra exigem nfe.tributacao.manage`.
- **Contrato:** o §Backend do [charter](RegraForm.charter.md) (*"permissão `nfe.tributacao.manage`
  validada no FormRequest"*) + [US-NFE-010](../../../../../memory/requisitos/NfeBrasil/SPEC.md) +
  o docblock de `TributacaoController` — **três** fontes afirmando a mesma permissão.
- **Regressão que defende:** o gate **não está no Controller** — está no `authorize()` do
  `UpsertRegraTributariaRequest`. Isso significa que ele desaparece silenciosamente se alguém
  trocar a type-hint do método por `Request` (para "adicionar um campo sem mexer no FormRequest"),
  que é precisamente o estado em que **três outras mutações do mesmo Controller já estão**
  (`destroy`, `toggleAutoEmission`, `aplicarTemplate` — SDD §5.4.1, varredura contada: `can(`/`abort`
  no arquivo = **0**). Este caso trava as duas que ainda gateiam antes que regridam para a média.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFRF-02 · A regra nasce no meu tenant, nunca no de quem a rota apontar · `must` `[T0]` `[V0]`

- **Persona:** qualquer tenant. Uma regra NCM gravada no business errado muda a tributação de
  **outra empresa** — e o sintoma só aparece na nota emitida por ela, dias depois.
- **Aceite:** Dado que eu envio o formulário com um `business_id` embutido no corpo apontando para
  **outro** business · Quando a regra é criada · Então ela pertence ao **meu** business, e o outro
  **não** ganha regra nenhuma.
- **Teste:** [`TributacaoGatesContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoGatesContratoTest.php)
  — `UC-NFRF-02 · business_id vindo no corpo é ignorado — a regra nasce no tenant da sessão`.
- **Contrato:** [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) +
  charter §Non-Goals (*"❌ Não grava em outro tenant — `store` injeta `business_id` da sessão"*) —
  este item **é** verificável como fato (a afirmação é sobre o mecanismo, não sobre intenção de
  produto) + SDD [`CU-NFE-11`](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md).
- **Regressão que defende:** a proteção é o `array_merge($request->validated(), ['business_id' => $businessId])`
  — o `business_id` da **sessão** sobrescreve o que veio do cliente **porque vem depois no
  merge**. Inverter a ordem dos dois argumentos (refactor visualmente neutro, e o tipo de coisa que
  um formatador ou um "deixa mais legível" faz) transforma um campo controlado pelo cliente na
  fonte do tenant. O `validated()` não protege sozinho: basta alguém adicionar `business_id` às
  regras do FormRequest um dia.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFRF-03 · Abrir a edição de regra alheia é 404 — e não vaza a alíquota dela · `must` `[T0]`

- **Persona:** qualquer tenant trocando o id na URL. O payload da tela de edição carrega NCM, CFOP,
  CST/CSOSN e **as quatro alíquotas** — a estrutura de custo tributário do concorrente.
- **Aceite:** Dada uma regra de outro business · Quando abro `/regras/{id}/edit` · Então **404**, e
  o corpo da resposta **não contém** os valores daquela regra. Controle positivo: na minha regra,
  **200** com os valores.
- **Teste:** [`TributacaoGatesContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoGatesContratoTest.php)
  — `UC-NFRF-03 · edit de regra de outro business dá 404 e não vaza os valores dela`.
- **Contrato:** [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) +
  charter §Non-Goals (*"`edit`/`update` escopam por `business_id` … `firstOrFail` bloqueia acesso a
  regra de outro negócio"*).
- **Regressão que defende:** já existe cobertura do 404 do `edit` em `TributacaoControllerTest` —
  mas ela instancia o Controller **sem usuário autenticado**, e nesse estado o `ScopeByBusiness`
  faz early-return: o que passa verde lá é só o `where` manual. Este caso roda por **HTTP com
  `actingAs`** e semeia as **duas** chaves de sessão do módulo (SDD §5.4.2), então mede as duas
  camadas. E assere o **conteúdo** da resposta, não só o status: um 404 renderizado com o payload
  já montado ainda vazaria.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFRF-04 · Apagar regra exige a permissão fiscal · `must` `[T0]` `[V0]` — ❌ **falha esperada**

- **Persona:** o mesmo usuário de caixa do UC-NFRF-01. Apagar uma regra NCM não deixa buraco
  visível: o item simplesmente **desce para o Nível 4** da cascade e passa a ser emitido com a
  tributação default — nota errada, com aparência de nota certa, até a autuação.
- **Aceite:** Dado um usuário **sem** `nfe.tributacao.manage` · Quando ele faz
  `DELETE /nfe-brasil/tributacao/regras/{id}` · Então **403**, e a regra continua ativa
  (`deleted_at` nulo — `NfeFiscalRule` usa `SoftDeletes`, regra fiscal não some do histórico).
- **Teste:** [`TributacaoGatesContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoGatesContratoTest.php)
  — `UC-NFRF-04 · apagar regra sem nfe.tributacao.manage deve dar 403`.
- **Contrato:** [US-NFE-010](../../../../../memory/requisitos/NfeBrasil/SPEC.md) (DoD de permissão)
  + o docblock de `TributacaoController` (*"Permissão: `nfe.tributacao.manage`"* — afirmado para a
  classe inteira) + SDD [§5.4.1](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md).
- **Por que nasce ❌ e por que isso é o certo:** medido — `TributacaoController@destroy` recebe
  `Illuminate\Http\Request` (não um FormRequest) e o arquivo tem **0** ocorrências de `can(`/`abort`;
  o grupo de rotas não tem middleware de permissão. Ou seja: **qualquer usuário autenticado do
  tenant apaga uma regra tributária hoje.** O teste é *failing-first* — o `❌` **é o achado**, com o
  vermelho da lane como recibo, e a correção é decisão [W], não conserto silencioso
  ([proibicoes §Precedência](../../../../../memory/proibicoes.md)). O agent não editou Controller.
- **Escopo do achado (contado):** 3 das 5 mutações do Controller estão assim — `destroy`,
  `toggleAutoEmission` e `aplicarTemplate`. Só `destroy` vira UC **aqui**, porque é a única cujo
  contrato o charter **desta tela** sustenta; as outras duas pertencem à `Tributacao/Index`
  (arquivo de outra sessão, não tocado) e estão registradas no SDD §5.4.1 + §9 R1.
- **Status: ❌ esperado** — ver §Recibo. Se vier **verde**, é sinal de que o gate foi adicionado
  entre esta escrita e a corrida: reconciliar o SDD §5.4.1 no mesmo PR.

---

## Backlog — sem UC até ganhar teste ou contrato

- `[BACKLOG]` **Os GETs da tela são ungated.** `@create` e `@edit` renderizam sem checar permissão
  (o `edit` ao menos escopa por business). O charter afirma a permissão para
  `create|store|edit|update` em bloco; o código só a aplica nos dois que escrevem. Não virou UC
  separado porque a saída (gatear o GET? assumir que ver o formulário vazio é inofensivo?) é
  decisão — e o `edit` já é coberto contra vazamento por `UC-NFRF-03`.
- `[BACKLOG]` **Unicidade da chave (NCM + UF origem + UF destino).** O próprio charter lista em
  §Pendências: *"[ ] Confirmar validação de unicidade da chave no FormRequest"*. Sem ela, duas
  regras iguais competem no Nível 2 e a que vence depende da ordenação — que é justamente o que
  `UC-NFTR-06` mede na listagem. Vira UC quando a regra existir.
- `[BACKLOG]` **Exclusividade CSOSN × CST.** A tela limpa o campo não usado no submit conforme o
  regime; se as duas colunas vierem preenchidas, o motor tributário tem dois caminhos. É
  comportamento de **cliente** (o `.tsx` faz a limpeza) — precisaria de e2e, e a tela não tem spec.
- `[BACKLOG]` **AuditLog.** `store`/`update`/`destroy` chamam `activity('nfe.tributacao')`, sem
  teste. Escopo declarado da **US-NFE-062**, aberta — deixo lá em vez de duplicar aqui.

---

## Recibo de execução

| Quando | Onde | Resultado |
|---|---|---|
| _pendente_ | lane `PHP / Pest (NfeBrasil · MySQL)` — **required, `enforce_admins`** | a preencher com o run id |

> ⚠️ **Este arquivo de teste NÃO foi adicionado à allowlist da lane** — e aqui a razão é dupla:
> (1) a allowlist é **catraca por prova verde** e esta corrida não roda teste
> ([ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md));
> (2) o `UC-NFRF-04` nasce **vermelho por desenho** — adicioná-lo a uma lane required com
> `enforce_admins` **bloquearia o merge de todos** até o gate do Controller existir. O ratchet-up
> está proposto no [SDD §8.3](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md),
> condicionado à correção.
>
> ⚠️ **Conferir a contagem de testes passados no JUnit, não só o check verde** — a suíte SKIPa em
> SQLite e o `oimpresso-staging` do CT 100 não tem as tabelas do NfeBrasil.
