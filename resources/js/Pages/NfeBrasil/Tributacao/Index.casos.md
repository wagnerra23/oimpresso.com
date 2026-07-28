---
id: resources-js-pages-nfe-brasil-tributacao-index-casos
casos: Tributação — regras NCM + templates + gate de emissão automática · /nfe-brasil/tributacao
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: esta tela liga a emissão automática de NFe e edita a cascade tributária — o comportamento é durável mesmo se o layout mudar.
owner: wagner
last_run: "2026-07-27"
---

# Casos de Uso & Aceite — Tributação (regras NCM · templates · gate de emissão automática)

> **Âncora:** o módulo NfeBrasil **não tem SDD** (verificado em `origin/main` 2026-07-27 —
> `git ls-tree` não devolve nenhum `SDD-*` sob `memory/requisitos/NfeBrasil/`). A fonte de contrato,
> na ordem canônica de [`how-trabalhar.md`](../../../../../memory/how-trabalhar.md) §"Pedido de tela/feature", é:
>
> 1. [**ADR ARQ-0006**](../../../../../memory/requisitos/NfeBrasil/adr/arq/0006-cascade-defaults-ncm-produto.md) —
>    cascade em 4 níveis. Esta tela edita os **Níveis 2 e 3** (regra NCM exata × NCM com UF destino "todas")
>    e é a porta do **Nível 4**.
> 2. [**US-NFE-010**](../../../../../memory/requisitos/NfeBrasil/SPEC.md) — DoD "UI fase 2 (CRUD básico)" +
>    "Multi-tenant scope `business_id`" + [**US-NFE-TPL-001**] (templates L1).
> 3. [**Index.charter.md**](Index.charter.md) — **`status: live`, com Non-Goals e Anti-hooks
>    aprovados por [W] em 2026-05-10**. Diferente da tela irmã `ConfigDefault` (cujo charter é `draft`
>    e cujos Non-Goals nunca foram ratificados), aqui os itens ❌ **são lei** e podem virar `[must]`.
>
> Os UCs derivam do **contrato**, nunca da implementação — teste derivado do código é tautológico
> ([`proibicoes.md`](../../../../../memory/proibicoes.md) §5 2026-06-05). O `TributacaoController` e o
> `TributacaoTemplateService` foram lidos só para **confirmar**.
>
> **Por que este arquivo nasce agora:** fecha o trio da tela (charter desde 2026-05-10; `casos.md` +
> teste de contrato faltavam). Tier-0 quente pela sentinela
> [`exposicao-tier0.mjs`](../../../../../scripts/qa/exposicao-tier0.mjs) — `exposure_score 9`,
> categorias `dinheiro,pii,fiscal` — e em **débito**.
>
> **O que esta tela liga.** O switch "Emissão automática" é o **gate per-business** que faz vendas
> finalizadas emitirem NFC-e/NFe **sozinhas**. É o controle de maior consequência do módulo: ligado no
> tenant errado, ele emite documento fiscal em nome de quem não pediu. Daí `UC-NFTR-01` e `UC-NFTR-02`
> serem os primeiros.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Contrato | Teste | Status |
|----|-------------|------|----------|-------|--------|
| UC-NFTR-01 | Gate de emissão automática é per-business | must `[T0]` `[fiscal]` | ADR 0093 · charter §Goals | `TributacaoIndexContratoTest` | 🧪 |
| UC-NFTR-02 | Toggle sem config é recusado e não cria config | must `[fiscal]` | charter §UX Anti-patterns | `TributacaoIndexContratoTest` | 🧪 |
| UC-NFTR-03 | Aplicar template substitui a config e **preserva** as regras NCM | must `[fiscal]` | charter §Automation Hooks | `TributacaoIndexContratoTest` | 🧪 |
| UC-NFTR-04 | Update/destroy de regra de outro business → 404, e a regra alheia sobrevive | must `[T0]` | ADR 0093 · anti-hook | `TributacaoIndexContratoTest` | 🧪 |
| UC-NFTR-05 | Listagem não traz regra de outro tenant (com auth real) | must `[T0]` | ADR 0093 · anti-hook | `TributacaoIndexContratoTest` | 🧪 |
| UC-NFTR-06 | Regras ordenadas por NCM → UF origem → UF destino (NULL primeiro) | must | ARQ-0006 §Níveis 2/3 | `TributacaoControllerTest` | 🧪 |

> **Recibo:** ver §Recibo de execução no rodapé — status é o **veredito** da corrida, não leitura de código.

---

## UC-NFTR-01 · Gate de emissão automática é per-business · `must` `[T0]` `[fiscal]`

- **Persona:** Wagner habilitando emissão automática no próprio tenant depois de validar o smoke fiscal
  em homologação. Nenhum outro tenant pode ser arrastado junto: emitir NFe em nome de terceiro é dano
  fiscal, não bug de UI.
- **Aceite:** Dado config de biz=1 e config de biz=2, ambas com `auto_emission_enabled = false` · Quando
  biz=1 faz `POST /nfe-brasil/tributacao/auto-emission/toggle` com `enabled=true` · Então biz=1 fica
  `true` **e biz=2 continua `false`**. E o inverso (desligar) também não atravessa.
- **Teste:** [`TributacaoIndexContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoIndexContratoTest.php)
  — `UC-NFTR-01 · toggle de emissão automática não atravessa para outro business`.
- **Contrato:** charter §Goals — *"Switch 'Emissão automática NFC-e' — gate per-business (ADR 0093 Tier 0)"*
  + charter §Automation Anti-hooks *"❌ Não acessa regras de outro `business_id`"* +
  [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** o toggle resolve o tenant por `session('business.id')` e faz
  `$config->update(...)`. Um refactor que troque o `first()` escopado por um `NfeBusinessConfig::first()`
  (ou que confie só no global scope sem usuário autenticado — o `ScopeByBusiness` faz early-return em
  `! auth()->check()`) liga a emissão automática do **vizinho** sem nenhum sintoma visível. O teste usa
  auth real, então exercita o scope global **e** o filtro manual juntos.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFTR-07 · Ligar o toggle é CONFIGURAR, nunca EMITIR · `must` `[T0]` `[fiscal]`

- **Regra de domínio ([W] 2026-07-28, textual):** *"As notas não podem sair automáticas em
  todos os clientes. Não é assim que funciona. O cliente escolhe se quer emitir ou não. E
  **tem configuração por empresa** se isso é automático."*
- **Persona:** a empresa decide, no seu próprio tenant, que quer NFC-e automática depois de
  validar o smoke fiscal. Nada é emitido nesse instante — a escolha fica gravada em
  `nfe_business_configs.auto_emission_enabled` e só passa a valer **quando houver venda
  finalizada**, via `EmitirNfceAoFinalizarVenda` → `EmitirNfceJob`.
- **Aceite:** Dado config do business com `auto_emission_enabled = false` · Quando faz
  `POST /nfe-brasil/tributacao/auto-emission/toggle` com `enabled=true` · Então a flag fica
  `true` (**pré-condição anti-vácuo**: prova que a operação aconteceu) **e nenhum**
  `EmitirNfceJob`/`EmitirNFSeJob` é despachado.
- **Por que este caso existe:** o charter já declarava a regra em §Automation Anti-hooks
  (*"Não dispara Job de emissão quando toggleAutoEmission=true"*) e prometia que *"cada item
  vira Pest GUARD test"* — mas **não havia guard**. Regra escrita e indefesa: um agente
  futuro lendo só o controller poderia "otimizar" emitindo no toggle, e nada quebraria.
- **Teste:** [`TributacaoIndexContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoIndexContratoTest.php)
  — `UC-NFTR-07 · ligar a emissão automática grava a escolha da empresa e NÃO despacha emissão`.
- **Status:** 🧪 existe teste que cita o UC — veredito é da lane (CT 100/CI, ADR 0062).

## UC-NFTR-02 · Toggle sem config é recusado e não cria config · `must` `[fiscal]`

- **Persona:** Larissa recém-configurada, ainda sem tributação default. Ligar a emissão automática antes
  de existir Nível 4 faria o motor cair em `TributacaoNaoConfiguradaException` na hora da venda — erro no
  balcão, com cliente na frente.
- **Aceite:** Dado **nenhuma** config para o business · Quando faço `POST …/auto-emission/toggle` com
  `enabled=true` · Então volto com **flash de erro** (mensagem manda aplicar um template antes) e
  **nenhuma** row de `nfe_business_configs` é criada — o toggle não pode "criar config no caminho".
- **Teste:** [`TributacaoIndexContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoIndexContratoTest.php)
  — `UC-NFTR-02 · toggle sem config existente é recusado e não cria config`.
- **Contrato:** charter §UX Anti-patterns — *"❌ Toggle auto-emission antes de existir config (canon =
  mostra erro flash + redireciona)"* + [ARQ-0006](../../../../../memory/requisitos/NfeBrasil/adr/arq/0006-cascade-defaults-ncm-produto.md)
  §Decisão, ramo *"❌ ERROR — exige tenant cadastrar default mínimo"*.
- **Regressão que defende:** a guarda é um `if (! $config) return redirect()->with('error', …)`. Trocar
  o `first()` por `firstOrCreate()` — refactor de aparência inocente, e o tipo de coisa que "conserta" um
  null-check — criaria uma config **vazia** e ligaria a emissão automática em cima dela. A asserção
  cobre os dois lados: a recusa **e** a ausência da row.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFTR-03 · Aplicar template substitui a config e **preserva** as regras NCM · `must` `[fiscal]`

- **Persona:** gráfica que já refinou 3 regras NCM específicas e resolve aplicar um template setorial
  pra corrigir o Nível 4. As regras que ela cadastrou à mão (Níveis 2/3) não podem ser varridas junto.
- **Aceite:** Dado 2 regras NCM do business e uma config existente · Quando aplico um template
  (`POST …/templates/{slug}/aplicar`) · Então a config passa a refletir o template **e** as 2 regras NCM
  continuam lá, intactas. Re-aplicar o mesmo template é **idempotente no sentido semântico** — regime e
  `tributacao_default` inalterados, sem row duplicada. (A comparação é sobre o JSON **decodificado**, não
  sobre a string: a coluna é `json` e o MySQL normaliza a ordem das chaves, então comparar bytes mediria
  o formato de armazenamento em vez do contrato.)
- **Teste:** [`TributacaoIndexContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoIndexContratoTest.php)
  — `UC-NFTR-03 · aplicar template substitui a config e preserva as regras NCM`.
- **Contrato:** charter §Automation Hooks — *"`aplicarTemplate` (cria/substitui config default; **regras
  NCM permanecem**)"* + charter §Goals *"Confirmação destrutiva ao aplicar template se já existe config"*.
- **Regressão que defende:** "aplicar template" é a única ação **destrutiva por design** da tela (troca o
  regime + a tributação default inteira). A fronteira do estrago — parar em `nfe_business_configs` e não
  encostar em `nfe_fiscal_rules` — hoje é só uma escolha do service, sem teste. O passo de idempotência
  vem junto porque é o que impede o "aplicar de novo" de virar um segundo write silencioso.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFTR-04 · Update/destroy de regra de outro business → 404, e a regra alheia sobrevive · `must` `[T0]`

- **Persona:** qualquer tenant. Uma regra NCM define a carga tributária de um produto — apagar ou editar
  a do vizinho corrompe a nota dele silenciosamente, e só aparece numa autuação.
- **Aceite:** Dado uma regra pertencente a biz=2 · Quando biz=1 faz `PUT /…/regras/{id}` ou
  `DELETE /…/regras/{id}` · Então **404** em ambos, **e** a regra de biz=2 continua no banco com os
  mesmos valores (não basta o 404: o que importa é que nada foi escrito antes dele). Controle positivo:
  as mesmas rotas funcionam na regra do próprio business — e "removida" significa `deleted_at` preenchido
  (`NfeFiscalRule` usa `SoftDeletes`; regra fiscal não some do histórico), não linha ausente.
- **Teste:** [`TributacaoIndexContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoIndexContratoTest.php)
  — `UC-NFTR-04 · update e destroy de regra de outro business dão 404 e não tocam a regra alheia`.
- **Contrato:** [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) +
  charter §Automation Anti-hooks *"❌ Não acessa regras de outro `business_id`"* + US-NFE-010 DoD
  *"Multi-tenant isolation (business A não vê regras de B)"*.
- **Regressão que defende:** o `TributacaoControllerTest` existente cobre o 404 do **`edit`** — e só dele.
  `update` e `destroy` (os dois que **escrevem**) estão descobertos, que é exatamente a metade que causa
  dano. Este UC fecha o par escrita/remoção e afirma a sobrevivência do dado, não só o status HTTP.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFTR-05 · Listagem não traz regra de outro tenant (com auth real) · `must` `[T0]`

- **Persona:** qualquer tenant abrindo `/nfe-brasil/tributacao`. Ver a tabela de NCM/alíquotas do vizinho
  é vazamento de dado comercial, além de fiscal.
- **Aceite:** Dado 1 regra de biz=1 e 1 regra de biz=2 · Quando biz=1 abre a tela e o payload de `regras`
  é resolvido · Então vem **exatamente** a regra de biz=1 (controle positivo) e **nenhuma** de biz=2.
- **Teste:** [`TributacaoIndexContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoIndexContratoTest.php)
  — `UC-NFTR-05 · listagem traz a regra do próprio business e nenhuma do vizinho`.
- **Contrato:** idem UC-NFTR-04.
- **Regressão que defende:** o `TributacaoControllerTest` já tem um caso de isolamento, mas ele instancia
  o controller **sem usuário autenticado** — e o `ScopeByBusiness` faz early-return em
  `! auth()->check()`. Ou seja: lá o global scope **no-opa**, e o que passa verde é só o `where` manual do
  controller. É a mesma causa que derrubou `NfeBrasilMultiTenantIsolationTest` e `Wave25NfeSaturationTest`
  em 2026-06-24 (documentada na allowlist de `nfebrasil-pest.yml`). Este UC roda **com `actingAs` e as duas
  chaves de sessão** (`business.id`, lida pelo controller, e `user.business_id`, lida pelo scope), então as
  **duas** camadas valem. Traz controle positivo, pra o verde não vir de a lista estar vazia por ausência
  de dado.
- **Nota de método:** o caso chama o controller e resolve a closure de `Inertia::defer`, em vez de bater na
  rota. Não é preguiça: arrancar prop deferida por partial reload exigiria casar `X-Inertia-Version`
  (mismatch → 409), e essa fragilidade não tem relação com o que o caso mede. O que ativa o global scope é a
  **autenticação**, não o transporte HTTP — e ela está presente.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFTR-06 · Regras ordenadas por NCM → UF origem → UF destino (NULL primeiro) · `must`

- **Persona:** Contador conferindo a cascade. A ordem **é** a semântica: dentro do mesmo NCM, a regra mais
  específica (Nível 2, com UF destino) e a genérica (Nível 3, UF destino "todas") precisam aparecer numa
  ordem estável, senão a leitura da precedência fica ambígua.
- **Aceite:** Dadas 3 regras (NCM `22021000` genérica; NCM `49019900` genérica; NCM `49019900` para `RJ`)
  · Quando a listagem é montada · Então a ordem é `22021000` → `49019900`(UF destino NULL) →
  `49019900`(RJ).
- **Teste:** [`TributacaoControllerTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoControllerTest.php)
  — `UC-NFTR-06 · regras retornam ordenadas por NCM, UF origem, UF destino (NULL primeiro)`.
- **Contrato:** [ARQ-0006](../../../../../memory/requisitos/NfeBrasil/adr/arq/0006-cascade-defaults-ncm-produto.md)
  §Decisão — Níveis 2 e 3 + charter §Goals *"Listagem regras NCM ordenadas por NCM → uf_origem →
  uf_destino (NULL last via `IS NULL DESC`)"*.
- **Onde é provado — e o limite honesto:** este é o **único** UC desta tela cujo teste **não** está na
  lane MySQL. Ele vive no `TributacaoControllerTest`, que roda na lane sqlite (`modules-pest.yml`, que
  executa `Modules/NfeBrasil/Tests` inteiro). Para *ordenação* isso é prova legítima — `ORDER BY` +
  `IS NULL DESC` são portáveis e não dependem do schema real. Não subi esse arquivo para a allowlist
  MySQL porque não consegui provar que ele passa lá (ver §Recibo), e ratchet sem prova é o anti-padrão
  que a própria allowlist proíbe.
- **Status: 🧪** — ver §Recibo.

---

## Backlog — sem UC até ganhar teste

> Prosa honesta, sem gate. Vira UC quando ganhar teste que o cite (G-2).

- `[BACKLOG]` **`regras` e `templates` são deferidos; `config` é eager.** O charter promete
  `p95 first-paint < 1500ms` e o controller aplica `Inertia::defer` nas duas props caras (Wave 25 D3).
  Nada prova que continuam deferidas — um refactor que volte a eager degrada a tela sem sinal. Vira UC
  quando houver um caso que asserte a ausência das props no render inicial e a presença no partial reload.
- `[BACKLOG]` **AuditLog nas mutações de regra.** `store`/`update`/`destroy` e `toggleAutoEmission` já
  chamam `activity('nfe.tributacao')->log(...)`, mas nenhum teste prova. É o escopo declarado da
  **US-NFE-062** (P1), ainda aberta — deixo lá em vez de duplicar aqui.
- `[BACKLOG]` **Confirmação destrutiva ao aplicar template sobre config existente.** O charter §Goals
  pede `window.confirm` explícito e o `.tsx` implementa. É comportamento de **cliente** — precisaria de
  e2e Playwright, não de Pest; a tela não tem spec e2e hoje (`screen:files` → *"nenhum teste Browser cita
  o path"*).

---

## Recibo de execução

| Quando | Onde | Resultado |
|---|---|---|
| _pendente_ | lane `PHP / Pest (NfeBrasil · MySQL)` — UC-NFTR-01..05 | a preencher com o run id |
| _pendente_ | lane `modules-pest` (sqlite) — UC-NFTR-06 | a preencher com o run id |

> ⚠️ **Não foi possível pré-provar no CT 100.** O container `oimpresso-staging` **não tem as tabelas do
> NfeBrasil** (`nfe_business_configs` / `nfe_fiscal_rules` / `nfe_emissoes` ausentes — medido via
> `Schema::hasTable` em 2026-07-27): a suite SKIPa inteira lá. A lane de CI é o único lugar que a executa.
> Ao ler o resultado, conferir a **contagem de testes passados** no JUnit, não só o check verde — suite
> que SKIPa também fica verde (gate mudo, `proibicoes.md` §5 2026-07-27).
>
> Os `Status: 🧪` só sobem pra ✅ quando o manifesto do G-7 (`scripts/casos-test-results.json`) for
> regravado a partir do JUnit — ✅ é afirmação que exige prova.
