---
id: resources-js-pages-comunicacao-visual-index-casos
casos: Hub + Calculadora de m² · /comunicacao-visual
irmaos: Index.charter.md (lei) · Index.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a fórmula do m² e o isolamento por business são o que não pode mudar — a tela vai ganhar OS, materiais e apontamento por cima disso, e nenhum deles pode afrouxar o cálculo nem o escopo.
owner: wagner
last_run: "2026-07-28"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane Pest ComunicacaoVisual"
---

# Casos de Uso & Aceite — Hub + Calculadora de m² (`/comunicacao-visual`)

> **Âncora:** os UC derivam dos CU do
> [SDD §6](../../../../memory/requisitos/ComunicacaoVisual/SDD-tela-orcamento-m2-v1.0.md) —
> `CU-CV-02`, `CU-CV-03`, `CU-CV-04`, `CU-CV-06`, `CU-CV-08`, `CU-CV-09` e `CU-CV-10` —
> **nunca do `Index.tsx`**: teste derivado do código é tautológico e trava o desvio em vez de
> pegá-lo ([proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05).
>
> **Por que este arquivo nasce agora:** completa o trio da tela (o charter existe desde
> 2026-05-16, Wave 25; `casos.md` faltava — `node scripts/governance/requisitos-status.mjs ComunicacaoVisual`
> acusava *"Tela `Index` sem `casos.md`"* como a única lacuna nomeada do módulo). É o chip da
> **Onda 4** do [passo 5](../../../../memory/requisitos/_Governanca/programa-ondas/passo-5-sdd-por-modulo.md).
>
> ⚠️ **Módulo EM CONSTRUÇÃO, sem cliente piloto.** 14 das 18 US do
> [SPEC](../../../../memory/requisitos/ComunicacaoVisual/SPEC.md) são **plano** e estão listadas
> no [SDD §6.9](../../../../memory/requisitos/ComunicacaoVisual/SDD-tela-orcamento-m2-v1.0.md)
> **sem CU e sem UC** — de propósito: caso sem código vira UC órfão, o `casos-gate` G-2 pune, e
> isso **bloqueia o merge de quem for implementar** ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-16).

## ⚖️ Força do veredito — leia ANTES de confiar em qualquer status daqui

Três portas distintas, medidas separadamente (2026-07-28):

| Pergunta | Porta medida | Resposta |
|---|---|---|
| roda em algum lugar? | [`phpunit.xml`](../../../../phpunit.xml) + [`shards-plan.mjs`](../../../../scripts/tests/shards-plan.mjs) (`--roots tests,Modules`) | ✅ **sim** — full-suite noturna, MySQL real |
| roda no PR? | [`modules-pest.yml`](../../../../.github/workflows/modules-pest.yml) — matrix de 6 módulos, `DB_CONNECTION=sqlite :memory:` **sem migrate** | ⚠️ **parcialmente** — **6 dos 20** arquivos abortam no `beforeEach` com `markTestSkipped('SQLite-incompatível')` |
| **bloqueia merge?** | [`required-checks-baseline.json`](../../../../governance/required-checks-baseline.json) — Pest **required** = Financeiro, NfeBrasil, Unit | ❌ **não** — `Pest ComunicacaoVisual` é **advisory**: reprova visível, não bloqueia merge |

**Consequência honesta:** os UC marcados **⏭ PR-skip** abaixo (incluindo os `[T0]`) **não são
exercitados no PR** — o verde da lane prova que eles foram *pulados*, não que passam. É a família
"verde por não-execução" ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-24). Quem
morde de fato neste PR é o `Casos-coverage · ratchet` (G-1 trio + G-2 UC↔teste), esse sim required.
A decisão de mudar isso é de [W] — mexer no `modules-pest.yml` afeta **6 módulos**, e este chip
não toca lá ([SDD §10](../../../../memory/requisitos/ComunicacaoVisual/SDD-tela-orcamento-m2-v1.0.md) item 2).

**Legenda:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC, veredito pendente da lane ·
⬜ não verificado · ❌ quebrou · ⏭ PR-skip = o caso existe mas a lane do PR não o executa.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora (SDD §6) | Teste | Status |
|----|-------------|------|-----------------|-------|--------|
| UC-CV-01 | O total oficial é o do servidor, não o da tela | must `[V0]` | `CU-CV-02` itens 1-4 | `OrcamentoCalculatorTest` | 🧪 |
| UC-CV-02 | Medida inválida vira 422 em PT-BR, nunca total silencioso | must `[V0]` | `CU-CV-02` item 5 | `OrcamentoCalculatorTest` | 🧪 |
| UC-CV-03 | O preço/m² tem origem única e explícita | must `[V0]` | `CU-CV-03` itens 1-3, 5-6 | `OrcamentoCalculatorTest` | 🧪 |
| UC-CV-04 | Material de outro business não precifica nada | must `[T0]` | `CU-CV-03` item 4 · `CU-CV-04` | `OrcamentoCalculatorTest` · `MaterialSeederTest` | 🧪 ⏭ PR-skip |
| UC-CV-05 | Orçamento, OS e apontamento de outro business não aparecem | must `[T0]` | `CU-CV-04` itens 1-5 | `MultiTenantTest` · `Tier0GuardTest` · `OrcamentoControllerTest` | 🧪 ⏭ PR-skip |
| UC-CV-06 | Um operador nunca tem dois spools abertos; drift vem do servidor | must | `CU-CV-06` itens 2-6 | `ApontamentoTrackerTest` · `ApontamentoControllerTest` | 🧪 ⏭ PR-skip |
| UC-CV-07 | A calculadora recebe o catálogo do business | must | `CU-CV-09` itens 1-3 | `ContratoTelaOrcamentoTest` | 🧪 **vermelho esperado** ⏭ PR-skip |
| UC-CV-08 | O rastro de auditoria não carrega PII | must `[reg]` | `CU-CV-08` itens 1-5 | `AuditTrailIntegrityTest` · `LgpdComplianceTest` | 🧪 |
| UC-CV-09 | O substrato nasce com os campos fiscais do CNAE 1813 | should | `CU-CV-10` item 1 | `ContratoTelaOrcamentoTest` | 🧪 |
| UC-CV-10 | O hub abre pra quem tem permissão e renderiza a calculadora | must | `CU-CV-01` itens 1-2 | `ContratoTelaOrcamentoTest` | 🧪 ⏭ PR-skip |
| UC-CV-11 | Salvar o orçamento grava os valores do servidor, atomicamente | should | `CU-CV-05` itens 1-4 | `OrcamentoControllerTest` | 🧪 ⏭ PR-skip |
| UC-CV-12 | O catálogo de partida nasce completo, idempotente e isolado | should | `CU-CV-07` itens 1-3 | `MaterialSeederTest` | 🧪 ⏭ PR-skip |

> 🧪 **Nenhum status aqui é afirmação de verde.** Este PR não executou teste algum (CT 100/CI —
> [ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)). "Vermelho
> esperado" é **predição declarada**, derivada de leitura de código com varredura contada; o
> veredito vem da lane ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-15).

---

## UC-CV-01 · O total oficial é o do servidor, não o da tela · `must` `[V0]`

- **Persona:** Larissa-equivalente, no balcão — ela vê um número na tela e promete esse preço ao cliente.
- **Aceite:** Dado um orçamento montado na calculadora · Quando ela clica "Conferir no servidor" ·
  Então o total exibido como oficial é o **recalculado no backend**
  (`area = round(l × a × qtd, 3)` · `subtotal = round(area × preço, 2)` ·
  `total = round(subtotal − desconto + extras + instalação + entrega, 2)`, tudo `HALF_UP`),
  e qualquer `area_m2`/`subtotal`/`total` vindo do cliente é **descartado**.
- **Dupla-confirmação `[V0]`:** o cenário canônico (banner 3 m × 1,5 m, 1 peça) fecha em `area = 4,5`
  e o total confere por **dois caminhos independentes** — recomputo à mão da fórmula **e** soma das
  linhas devolvida pelo Service.
- **Teste:** [`OrcamentoCalculatorTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/OrcamentoCalculatorTest.php)
  — cenários 1, 2, 4 e 5 (banner, vinil com qtd, desconto absoluto, múltiplos itens).
- **Contrato:** `CU-CV-02` do SDD · DoD da `US-COMVIS-001` no [SPEC](../../../../memory/requisitos/ComunicacaoVisual/SPEC.md) ·
  anti-padrão nº 11 do SPEC §10 (*"cálculo m² em frontend sem servidor validar"*).
- **Regressão que defende:** a tela espelha a fórmula em JS pra dar feedback instantâneo. Espelho
  desatualiza. Se alguém "otimizar" mandando o total do cliente pro `store`, o preço vira o que o
  navegador disser — e é exatamente a classe do incidente `num_uf` ×100 de 2026-06-05
  ([REGRA MESTRE valor/estoque](../../../../memory/proibicoes.md)).
- **Status: 🧪** — o teste existe e cita o UC; veredito pendente da lane `Pest ComunicacaoVisual` (advisory).

---

## UC-CV-02 · Medida inválida vira 422 em PT-BR, nunca total silencioso · `must` `[V0]`

- **Persona:** Larissa-equivalente — digitar `0` na altura sem querer não pode virar orçamento de graça.
- **Aceite:** Dado um payload com largura ≤ 0, altura ≤ 0, quantidade < 1 ou zero itens · Quando o
  servidor calcula · Então responde **422** com mensagem em português dizendo qual campo e qual peça —
  **nunca** 200 com total zerado ou negativo.
- **Teste:** [`OrcamentoCalculatorTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/OrcamentoCalculatorTest.php)
  — cenários 6, 6b e 7 (throw em largura ≤ 0, altura ≤ 0, e sem preço resolvível) ·
  [`OrcamentoControllerTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/OrcamentoControllerTest.php)
  (a tradução do throw em 422).
- **Contrato:** `CU-CV-02` item 5 do SDD · `validarPayload` (`gt:0`, `min:1`, `itens min:1`).
- **Regressão que defende:** a validação vive em **dois lugares** — a Form validation do Controller
  **e** o Service (que revalida por segurança). Afrouxar um dos dois sem o outro abre o caminho pra
  um total calculado sobre dimensão inválida.
- **Status: 🧪** — veredito pendente da lane.

---

## UC-CV-03 · O preço/m² tem origem única e explícita · `must` `[V0]`

- **Persona:** Larissa-equivalente — ela precisa saber se o preço que apareceu veio da tabela dela
  ou do que ela digitou; "apareceu sozinho" é o pior dos mundos.
- **Aceite:** Dado um item de orçamento · Quando o servidor resolve o preço · Então a ordem é dura e
  única: **(1)** preço digitado pelo operador vence · **(2)** senão, `Material.preco_venda_m2` do
  próprio business · **(3)** senão, **erro**. Preço digitado `≤ 0` ⇒ erro; material com
  `preco_venda_m2 ≤ 0` ⇒ erro nomeando o material. **Nenhum caminho produz preço implícito.**
- **Teste:** [`OrcamentoCalculatorTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/OrcamentoCalculatorTest.php)
  — cenário 3 (resolve do catálogo sem override) e cenário 7 (throw sem `material_id` e sem preço).
- **Contrato:** `CU-CV-03` do SDD · `OrcamentoCalculator::resolverPreco`.
- **Regressão que defende:** um `?? 0` em qualquer ponto dessa cadeia transforma "não sei o preço"
  em "de graça" — o mesmo defeito de `0` ambíguo já catalogado no ecossistema de preço do Produto
  ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-15).
- **Status: 🧪** — veredito pendente da lane.

---

## UC-CV-04 · Material de outro business não precifica nada · `must` `[T0]`

- **Persona:** Wagner / WR2 SC (biz=1) — duas gráficas concorrentes no mesmo servidor não podem
  precificar com a tabela uma da outra. Tabela de preço **é** segredo comercial.
- **Aceite:** Dado um material do business 99 · Quando um item do business 1 informa aquele
  `material_id` sem preço digitado · Então o cálculo **falha** com *"não encontrado ou não pertence
  a este business"* — a mensagem **não confirma** que o material existe. E, como controle positivo,
  o material **do próprio** business resolve o preço normalmente.
- **Teste:** [`OrcamentoCalculatorTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/OrcamentoCalculatorTest.php)
  — cenário 7b (*"throw quando material_id não existe no business"*) ·
  [`MaterialSeederTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/MaterialSeederTest.php)
  (*"`Material::find()` via global scope não retorna rows de outro business"*).
- **Contrato:** `CU-CV-03` item 4 + `CU-CV-04` do SDD ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** o isolamento aqui é **implícito** — vem do global scope do `Material`,
  não de um `where` no Service. Quem ler o `resolverPreco` isolado não vê filtro de business nenhum;
  um `withoutGlobalScopes()` "pra debugar" vaza tabela de preço entre tenants sem erro visível.
- **Status: 🧪 ⏭ PR-skip** — o teste existe e cita o UC, mas o `MaterialSeederTest` pula em SQLite;
  o veredito real vem da full-suite noturna (MySQL).

---

## UC-CV-05 · Orçamento, OS e apontamento de outro business não aparecem · `must` `[T0]`

- **Persona:** Wagner / WR2 SC (biz=1) — vale para **as 10 entidades** do módulo, não só as 3 do fluxo.
- **Aceite:** Dado dados nos businesses 1 e 99 · Quando a sessão é do business 1 · Então:
  (a) `Material`, `Orcamento`, `OrcamentoItem` e `Os` do 99 **não** aparecem — e os do 1 aparecem
  (controle positivo); (b) as 5 entidades do PCP (`Substrato`, `Acabamento`, `InstalacaoCatalogo`,
  `OrdemProducao`, `Instalacao`) idem; (c) `GET /…/api/orcamentos/{id}` de outro business ⇒ **404**,
  não 403 (403 confirmaria a existência); (d) `GET /…/api/apontamentos/em-andamento` de outra
  sessão ⇒ vazio; (e) criar sem informar `business_id` usa o da **sessão**, não vaza pro errado.
- **Teste:** [`MultiTenantTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/MultiTenantTest.php)
  (4 entidades × par negativo/positivo) ·
  [`Tier0GuardTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/Tier0GuardTest.php)
  (as 5 do PCP + o `creating` que auto-popula) ·
  [`OrcamentoControllerTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/OrcamentoControllerTest.php)
  (o 404 cross-tenant) ·
  [`ApontamentoControllerTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/ApontamentoControllerTest.php)
  (o `em-andamento` vazio).
- **Contrato:** `CU-CV-04` do SDD ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) (Tier 0 IRREVOGÁVEL) ·
  [ADR 0101](../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md) (biz=1, nunca biz=4).
- **Regressão que defende:** 10 Entities, 10 global scopes. Entidade nova nascendo sem o scope é
  o vetor mais banal de vazamento cross-tenant, e o módulo tem **5 entidades sem consumidor**
  ([SDD §5.4.5](../../../../memory/requisitos/ComunicacaoVisual/SDD-tela-orcamento-m2-v1.0.md))
  — justamente as que ninguém exercita clicando.
- **Status: 🧪 ⏭ PR-skip** — ⚠️ **este é o caso mais importante do módulo e é o que a lane do PR
  menos executa**: `MultiTenantTest`, `Tier0GuardTest` e `OrcamentoControllerTest` pulam **inteiros**
  em SQLite. Registrado como dívida **D-7** no SDD §9.

---

## UC-CV-06 · Um operador nunca tem dois spools abertos; drift vem do servidor · `must`

- **Persona:** operador de plotter, celular na mão ao lado da máquina.
- **Aceite:** Dado um operador com um apontamento em andamento · Quando tenta iniciar outro ·
  Então recebe erro **nomeando o apontamento aberto**. E, ao finalizar: `duracao_segundos` e
  `drift_percent = round(((produzido − orçado) / orçado) × 100, 2)` são calculados **no servidor**;
  com `m2_orcado ≤ 0` o drift é **null**, nunca divisão por zero. Finalizar duas vezes ⇒ erro.
  Cancelar zera `m2_produzido` e prefixa `[CANCELADO]` — **não apaga** o registro.
- **Teste:** [`ApontamentoTrackerTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/ApontamentoTrackerTest.php)
  (8 casos: duração, drift, drift null, cancelar, spool duplicado, dupla finalização) ·
  [`ApontamentoControllerTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/ApontamentoControllerTest.php).
- **Contrato:** `CU-CV-06` do SDD · DoD da `US-COMVIS-004` no SPEC ·
  `retention.php` (apontamento é **append-only**, sem `SoftDeletes`).
- **Regressão que defende:** o drift é o insumo do pós-cálculo (`US-COMVIS-005`) — é ele que vai
  dizer se a OS deu margem. Calcular no cliente, ou deixar um segundo spool aberto, corrompe o
  custo real de todas as OS daquele operador. E o append-only do apontamento é exigência legal de
  registro produtivo: ganhar `SoftDeletes` "por consistência" é regressão de compliance.
- **Status: 🧪 ⏭ PR-skip** (parte HTTP) — o `ApontamentoTrackerTest` roda parcialmente; o Controller pula.

---

## UC-CV-07 · A calculadora recebe o catálogo do business · `must`

- **Persona:** Larissa-equivalente — o valor prometido pela tela é *"escolhe o material, o preço
  preenche sozinho"*. Hoje ela digita o preço/m² de cabeça, em **toda** peça.
- **Aceite:** Dado um business com materiais cadastrados · Quando a operadora abre
  `/comunicacao-visual` · Então o **nome** desses materiais chega à página, ela escolhe um e o
  preço/m² é preenchido a partir do catálogo. Controle Tier 0 no mesmo caso: material de **outro**
  business **nunca** aparece no payload.
- **Teste:** [`ContratoTelaOrcamentoTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/ContratoTelaOrcamentoTest.php)
  — *"a página do hub entrega o catálogo de materiais do business à calculadora"* + o controle-negativo
  cross-tenant. Ambos com **pré-condição anti-vácuo** (`assertOk()` antes de olhar as props: sem 200
  o teste teria medido o gate de permissão, não o contrato).
- **Contrato:** `CU-CV-09` do SDD §6 · DoD da `US-COMVIS-002` (*"alimentar US-COMVIS-001 sem
  hard-code"*) · [`BRIEFING.md`](../../../../memory/requisitos/ComunicacaoVisual/BRIEFING.md)
  (*"seletor de material puxa o catálogo do business"*) · o próprio docblock do `Index.tsx`.
- **Regressão que defende:** três documentos **e** o código do componente prometem o seletor de
  material; a única rota que renderiza a página passa **só `bizName`**
  ([SDD §5.4.1](../../../../memory/requisitos/ComunicacaoVisual/SDD-tela-orcamento-m2-v1.0.md),
  varredura contada). O `MaterialSeeder` semeia 5 materiais que a tela nunca vê. Sem este caso, a
  promessa segue escrita em 3 lugares e falsa em produção.
- ⚠️ **Duas correções são válidas** — passar a prop na closure da rota **ou** a tela buscar o
  catálogo por fetch. Por isso o assert é **comportamental** (o nome do material chega ao payload),
  não acoplado ao nome da prop: assert por chave literal reprovaria arbitrariamente uma das duas.
- **Status: 🧪 vermelho esperado ⏭ PR-skip** — **predição**, não veredito: nenhum teste rodou neste
  PR. Se a lane noturna confirmar o vermelho, a correção é decisão de [W] (entra agora ou vira US).

---

## UC-CV-08 · O rastro de auditoria não carrega PII · `must` `[reg]`

- **Persona:** Eliana [E] (jurídico/LGPD) — o log de atividade é o que sobra depois que o dado
  operacional é anonimizado; se o PII estiver **nele**, o `right_to_be_forgotten` não cumpre nada.
- **Aceite:** Dado que orçamento, OS e apontamento mudam de estado · Quando a mudança é registrada ·
  Então a whitelist `logOnly` **exclui** `contato_id` (referência a PII) e `observacoes` (texto livre)
  no `Orcamento`, e `observacoes`/`operador_id` no `Apontamento`; **inclui** o que é de negócio
  (status, totais, datas); `logOnlyDirty` + `dontSubmitEmptyLogs` estão ativos; e `observacoes` passa
  por `PiiRedactor` **antes** de qualquer span OTel do `OrcamentoCalculator`.
- **Teste:** [`AuditTrailIntegrityTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/AuditTrailIntegrityTest.php)
  (8 casos, reflection-only) ·
  [`LgpdComplianceTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/LgpdComplianceTest.php)
  (retenção, append-only, janela de telemetria).
- **Contrato:** `CU-CV-08` do SDD ·
  [`PII-LGPD.md`](../../../../memory/requisitos/ComunicacaoVisual/PII-LGPD.md) ·
  `Config/retention.php` · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) §PII.
- **Regressão que defende:** `logOnly` é uma lista de strings. Adicionar `observacoes` "pra ficar
  mais fácil de depurar" põe texto livre de vendedor — onde nome e telefone de cliente aparecem —
  numa tabela que nenhuma rotina de anonimização varre.
- **Status: 🧪** — roda no PR (reflection, sem DB); veredito pendente da lane.

---

## UC-CV-09 · O substrato nasce com os campos fiscais do CNAE 1813 · `should`

- **Persona:** dono de gráfica em onboarding — ele não tem contador de plantão pra classificar 80 itens.
- **Aceite:** Dado o schema de substratos · Quando um substrato é cadastrado · Então ele carrega
  `ncm`, `cfop_padrao` e `csosn_padrao`, de modo que a emissão de NFC-e/NFe de impresso publicitário
  não dependa de configuração item a item.
- **Teste:** [`ContratoTelaOrcamentoTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/ContratoTelaOrcamentoTest.php)
  — *"o schema de substratos carrega ncm, cfop_padrao e csosn_padrao"*.
- **Classe do teste — declarada:** **guard estrutural** (lê o fonte da migration, não exercita
  runtime). Aqui o nome da coluna **é** o contrato — `ncm`/`cfop_padrao`/`csosn_padrao` são campos
  definidos por SEFAZ/CONFAZ, não chaves arbitrárias de payload — então isto não é o
  "assert acoplado à chave literal" que a [ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md) bane.
- **Contrato:** `CU-CV-10` item 1 do SDD · DoD da `US-COMVIS-006` no SPEC.
- **Regressão que defende:** as 5 tabelas `cv_*` **não têm consumidor** — nenhuma tela, nenhum
  controller. Schema órfão é o que mais some num refactor de migration, porque nada quebra visivelmente.
- **Status: 🧪** — roda no PR (source-scoped); veredito pendente da lane.

---

## UC-CV-10 · O hub abre pra quem tem permissão e renderiza a calculadora · `must`

- **Persona:** Larissa-equivalente — se a rota devolve 403 ou renderiza outro componente, todos os
  outros casos desta tela viram medição de nada.
- **Aceite:** Dado um usuário do business com `comvis.orcamento.view` · Quando pede
  `GET /comunicacao-visual` · Então recebe **200** e o componente Inertia renderizado é
  **`ComunicacaoVisual/Index`** — não uma Blade, não um redirect.
- **Teste:** [`ContratoTelaOrcamentoTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/ContratoTelaOrcamentoTest.php)
  — o `assertOk()` + a checagem de `component` que servem de **pré-condição anti-vácuo** dos
  casos UC-CV-07: sem eles, um 403 faria o teste "passar" sem ter medido contrato nenhum
  ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-24).
- **Contrato:** `CU-CV-01` itens 1-2 do SDD · o gate de permissão do `Routes/web.php`
  (`superadmin ∥ comvis.orcamento.view ∥ comvis.os.view`).
- **Regressão que defende:** o gate está numa **closure de rota**, não num FormRequest nem numa
  policy — é o tipo de código que ninguém revisa e que some num refactor de rotas. E o módulo já
  teve o incidente inverso: em 2026-05-26 o dropdown legado do `DataController` apontava pra URLs
  `/comunicacao-visual/admin/*` inexistentes (404), removido justamente por isso.
- **Status: 🧪 ⏭ PR-skip** — o caso vive no arquivo que pula em SQLite; veredito da full-suite noturna.

---

## UC-CV-11 · Salvar o orçamento grava os valores do servidor, atomicamente · `should`

- **Persona:** Larissa-equivalente (quando a Sprint 2 ligar o botão) — o orçamento salvo tem que
  ser exatamente o que ela mostrou ao cliente.
- **Aceite:** Dado um orçamento válido · Quando `POST /…/api/orcamentos` é chamado · Então responde
  **201** com cabeçalho **e** itens; `area_m2`, `preco_unitario_m2` e `subtotal` gravados são a
  **saída do Service**, nunca números vindos do cliente; cabeçalho e itens são gravados na mesma
  transação (não existe orçamento sem linha); e o `numero` segue `ORC-{ano}-{5 dígitos}`, sequencial
  **por business e por ano-civil**.
- **Teste:** [`OrcamentoControllerTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/OrcamentoControllerTest.php)
  — *"POST /orcamentos persiste no DB e retorna 201 com Orcamento + itens"* e
  *"GET /orcamentos/{id} retorna Orcamento com itens"*.
- **Contrato:** `CU-CV-05` itens 1-4 do SDD · `OrcamentoController@store`/`@gerarNumero`.
- **Regressão que defende:** o `store` **não tem consumidor de UI** hoje (SDD §5.4.2) — código sem
  usuário é código que apodrece sem ninguém perceber. Quando a Sprint 2 ligar o botão "salvar",
  este caso é a única coisa entre a tela e um orçamento gravado com o total do navegador.
- ⚠️ A corrida de numeração concorrente (`CU-CV-05` item 5) **não** está coberta — é `[BACKLOG]`, SDD §9 D-2.
- **Status: 🧪 ⏭ PR-skip** — arquivo pula em SQLite; veredito da full-suite noturna.

---

## UC-CV-12 · O catálogo de partida nasce completo, idempotente e isolado · `should`

- **Persona:** dono de gráfica no primeiro dia — ele não vai cadastrar lona, vinil e ACM na mão
  antes de fazer o primeiro orçamento.
- **Aceite:** Dado um business novo do vertical · Quando o `MaterialSeeder` roda · Então existem
  **5** materiais com preço/m²; rodar de novo continua em **5** (idempotente); e `run(1)` e `run(99)`
  produzem catálogos que não se enxergam.
- **Teste:** [`MaterialSeederTest`](../../../../Modules/ComunicacaoVisual/Tests/Feature/MaterialSeederTest.php)
  — os 4 casos (contagem, idempotência, isolamento por business, global scope no `find`).
- **Contrato:** `CU-CV-07` itens 1-3 do SDD · DoD da `US-COMVIS-002` no SPEC.
- **Regressão que defende:** seeder não-idempotente é o vetor clássico de duplicar catálogo a cada
  deploy — e catálogo duplicado com preços diferentes é ambiguidade de **valor** entrando pela porta
  dos fundos. O isolamento por business no seeder é o mesmo Tier 0 do UC-CV-04, na hora do onboarding.
- ⚠️ **O catálogo semeado não chega à tela** hoje (UC-CV-07 / SDD §5.4.1) — este caso prova que ele
  existe no banco, não que a operadora o vê.
- **Status: 🧪 ⏭ PR-skip** — arquivo pula em SQLite; veredito da full-suite noturna.

---

## Backlog — prosa honesta, sem id (ainda não é UC)

> Cada item aqui tem contrato em **uma fonte só**, ou não tem código. Vira UC quando ganhar
> implementação **e** teste que o cite — nunca antes (G-2 / [proibicoes §5](../../../../memory/proibicoes.md) 2026-07-16).

- `[BACKLOG]` **Salvar o orçamento pela tela e mandar o PDF no WhatsApp.** `POST /…/api/orcamentos`
  existe, está testado e **nenhuma tela o chama** (`git grep "api/orcamentos" -- '*.tsx'` = 0). O
  botão "salvar" não existe; a copy da tela já diz "chega em breve", honestamente. SDD §5.4.2.
- `[BACKLOG]` **Numeração de orçamento com reserva atômica.** `gerarNumero()` lê `MAX(numero)` fora
  da transação; a corrida é barrada pelo `UNIQUE (business_id, numero)` (erro visível, não corrupção),
  mas não há teste do caso concorrente. SDD §5.4.3 / D-2.
- `[BACKLOG]` **Mínimo cobrado por material (`minimo_m2`).** O DoD da US-COMVIS-001 pede; a tabela
  `comvis_materiais` tem `estoque_minimo_m2` (estoque, outra coisa) e **não tem** `minimo_m2`; o
  calculator não aplica piso. SDD §5.4.6.
- `[BACKLOG]` **CRUD de materiais.** Sem ele o catálogo do UC-CV-07 nasce vazio na prática — só
  existe seeder. SDD §6 CU-CV-07 item 4.
- `[BACKLOG]` **Seed da tributária CNAE 1813 + wizard de onboarding.** Os NCM/CFOP do ramo (4911.10,
  4911.99, 3919, 7610, 9405 · 5101/5102/5933/5949) não são populados por nada. SDD §6 CU-CV-10 itens 2-3.
- `[BACKLOG]` **Acabamentos, instalação e entrega como linhas do orçamento.** Hoje são um campo
  "extras" manual único na tela; a Entity `Acabamento` existe e o calculator não a usa.
- `[BACKLOG]` **Migrar o `<select>` nativo da linha de item pro `<Select>` do DS.** Resíduo declarado
  no próprio `.tsx` com `eslint-disable` justificado. ⚠️ ao fazer, usar
  [`<SafeSelectItem>`](../../../../memory/requisitos/_DesignSystem/SAFE-SELECT-ITEM.md) — `value=""`
  derruba o render inteiro ([proibicoes §5](../../../../memory/proibicoes.md) 2026-06-29).
- `[BACKLOG]` **`RUNBOOK-comunicacao-visual-index.md`.** Não existe; o hook MWART bloqueia Edit no
  `.tsx` até criar. SDD §9 D-5.
- `[BACKLOG]` **Dicionário de domínio do vertical.** Sem `memory/dominio/comunicacao-visual.md` o
  módulo fica fora do `dominio-gate` (G-4, required) — os enums do schema não têm fonte única. SDD §9 D-6.
