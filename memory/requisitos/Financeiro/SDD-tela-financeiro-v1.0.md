---
id: requisitos-financeiro-sdd-tela-financeiro-v1-0
slug: financeiro-sdd
title: "SDD — Família de telas do Financeiro (Visão Unificada + satélites)"
type: sdd
module: Financeiro
status: ativo
owner: wagner
version: 1.0.0
last_updated: 2026-07-27
related_docs:
  - SPEC.md
  - BRIEFING.md
  - ARCHITECTURE.md
  - RUNBOOK-unificado.md
  - RUNBOOK-transaction-payment.md
  - CAPTERRA-INVENTARIO.md
  - SUPERFICIE.md
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0175-fix-observer-conta-bancaria-opcional'
  - '0264-governanca-executavel-trio-dominio-e2e'
  - '0273-anchor-spec-codigo-formato-canonico-fluxo-novo'
  - '0351-sdd-from-source'
related_us:
  - US-FIN-003
  - US-FIN-009
  - US-FIN-013
  - US-FIN-031
  - US-FIN-038
  - US-FIN-064
---

<!-- derivado: re-rodável do fonte — §5 e §6 apontam símbolo + grep que os re-localiza -->

# SDD — Família de telas do Financeiro (domínio Financeiro)

> **Como este documento nasceu.** Derivado pelo agent [`sdd-from-source`](../../../.claude/agents/sdd-from-source.md)
> ([ADR 0351](../../decisions/0351-sdd-from-source.md)) na Onda 2 do
> [passo 5](../_Governanca/programa-ondas/passo-5-sdd-por-modulo.md), 2026-07-27.
> **É o primeiro SDD do Financeiro** — o módulo tinha 21 telas, 58 US e 0 CU.
>
> **Triangulação das fontes (declarada nos dois sentidos — [ADR 0352](../../decisions/0352-errata-0351-venue-distiller-citacao-taxonomia.md)):**
>
> | # | Fonte | Estado | O que rendeu |
> |---|---|---|---|
> | 1 | Documentação canon | ✅ `SPEC.md` (58 US) · 21 `*.charter.md` · `RUNBOOK-unificado.md` · `ARCHITECTURE.md` | a âncora dos CU |
> | 2 | React/Laravel vivo | ✅ 21 `.tsx` · 23 controllers · 10 services · 80 arquivos de teste | o §5 (fluxo) |
> | 3 | Blade legada | ✅ **existe, mas é do core UltimatePOS, não do módulo** — `resources/views/{cash_register,account,transaction_payment,expense}/` | paridade do Caixa (§5.4) |
> | 4 | Delphi / Office Comercial | ❌ **NÃO EXISTE** — `find memory -iname "*ANTI-REGRESSAO*"` = 2 arquivos, **ambos do Produto** (medido 2026-07-27) | **gap declarado**: o contrato de paridade legado do Financeiro é mais fraco que o do Produto. Não inventado. |
>
> ⚠️ **A armadilha da Blade homônima foi checada.** O `Modules/Financeiro/Resources/views/` tem só
> 3 blades (`index`, `layouts/master`, `pdf/dre`) — nenhuma é tela de operação. A Blade que a
> operadora realmente abre para o **Caixa** é `resources/views/cash_register/index.blade.php` (core),
> alcançada pelo próprio React via `links.cash_register_legacy`
> (`resources/js/Pages/Financeiro/Caixa/Index.tsx`). Comparar contra a blade do módulo teria dado
> **"paridade OK" falsa**.

---

## 0. Base empírica

### 0.1 O que o módulo é (e o que não é)

O Financeiro **não é um rewrite Blade→React**. É uma **camada nova** (`fin_*`) construída
**ao lado** do núcleo UltimatePOS (`transactions`, `transaction_payments`, `accounts`,
`cash_registers`), ligada por **bridge** (Observers/Listeners/Jobs). Consequência de desenho que
atravessa todo o §5: **o mesmo fato financeiro tem duas representações** — a do núcleo
(`transaction_payments`) e a do módulo (`fin_titulos` + `fin_titulo_baixas`) — e a corretude do
módulo depende da bridge não perder nem duplicar evento.

### 0.2 Recibo de estado (medição datada — regra fact-anchor, [proibicoes](../../proibicoes.md) 2026-07-17)

Números abaixo são **medição de 2026-07-27**, não afirmação atemporal. Re-rode a porta, não edite o número.

| Fato | Porta que mediu |
|---|---|
| 21 telas · 5 `casos.md` · 17 UC (na régua estrita) · 58 US | `node scripts/governance/requisitos-status.mjs Financeiro` |
| `anchor_coverage` 93,1% · 18 US "implementada SEM teste que a cobre" | `node scripts/governance/anchor-lint.mjs memory/requisitos/Financeiro/SPEC.md` |
| 80 arquivos de teste em `Modules/Financeiro/Tests` · **24 na allowlist da lane** | `.github/workflows/financeiro-pest.yml` (bloco `Run Pest … ALLOWLIST VERDE`) |

**A consequência do 3º número é o achado central de governança deste SDD:** ~70% dos testes do
módulo **não rodam em lane nenhuma** — são "verde impossível" no sentido do `anchor-lint`. Ver §5.5.

---

## 1. Visão geral

### 1.1 Família de telas

A família é **radial**: a Visão Unificada é o hub; as demais são satélites de um recorte.

| Tela | Rota | Papel | Contrato hoje |
|---|---|---|---|
| **`Unificado/Index`** | `/financeiro/unificado` | **hub** — Pagar/Pagas/Receber/Recebidas numa view só, 3 lentes | `casos.md` com UC |
| `Unificado/Novo` | `/financeiro/unificado/novo` | stub picker (US-FIN-065 quer virar form real) | sem `casos.md` |
| `ContasReceber/Index` · `ContasPagar/Index` | `/financeiro/contas-{receber,pagar}` | **deprecadas** (charter `status: deprecated`, #3718); redirect é a US-FIN-064 `todo` | `casos.md` só com `[BACKLOG]` |
| `Conciliacao/Index` | `/financeiro/conciliacao` | extrato ↔ título (OFX/Pluggy/Inter) | sem `casos.md` |
| `Caixa/Index` | `/financeiro/caixa` | turno de caixa (lê `cash_registers` do core) | `casos.md` novo (v1.0.0) |
| `Extrato/Index` | `/financeiro/extrato` | lançamentos do extrato bancário | sem `casos.md` |
| `Dre/Index` · `Fluxo/Index` · `Relatorios/Index` · `Impostos/Index` | relatórios | leitura/derivação | só Impostos tem UC |
| `Cobranca/Index` · `ContasBancarias/Index` · `Categorias/Index` · `PlanoContas/Index` · `Configuracoes/Contador` | cadastros/apoio | — | sem `casos.md` |
| `Advisor/{Login,Dashboard}` | portal contador | acesso externo (US-FIN-037) | sem `casos.md` |
| `AssinaturaAtualizar` · `ProvaViva` · `Dashboard/Index` | apoio / dormente | `Dashboard` é 301→Unificado | `ProvaViva` tem UC |

### 1.2 O eixo de valor (o que não pode quebrar)

`venda → título a receber → baixa → caixa` e `compra/despesa → título a pagar → baixa → caixa`.
Todo CU marcado `[V0]` mora nesse eixo.

---

## 2. Público-alvo e personas

### P1 · Eliana [E] — financeiro do escritório (persona-dona da família)
Densidade alta, atalhos de teclado, fecha o mês com 200+ títulos. É a persona declarada nos
charters do Unificado, CR e CP. Quer **ver o que pede ação** sem abrir linha por linha.

### P2 · Larissa — ROTA LIVRE (biz=4, vestuário, 99% do volume)
Opera o caixa e o recebimento no balcão. Monitor 1280px. Para ela, "recebi" tem que virar baixa
**e** entrada de caixa **sem digitação dupla** — é literalmente o UC-F02.

### P3 · Wagner [W] — operador-dono e cobaia segura (biz=1)
Dogfooding: todo fluxo novo roda em biz=1 antes de chegar em biz=4 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)).

### P4 · Contador parceiro (externo, portal Advisor — US-FIN-037)
Acesso **compartilhado e limitado**; superfície de risco multi-tenant maior que a das outras personas.

---

## 3. Governança aplicável

### 3.1 Tier 0 — IRREVOGÁVEL

| Trava | Onde morde neste módulo |
|---|---|
| **Multi-tenant** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) | `business_id` em `fin_*`; bulk valida ownership de **todos** os ids antes de qualquer escrita (422 fail-closed) |
| **REGRA MESTRE valor/estoque** ([proibicoes](../../proibicoes.md)) | **todo** CU de baixa/split/bulk/cálculo de saldo é `[V0]`: dupla-confirmação por 2 caminhos + tabela antes→depois + aprovação [W] |
| **PII / valores BRL fora do git** | nenhum valor real de cliente entra neste doc nem nos `casos.md` |
| **Append-only contábil** | `fin_titulos` recusa `forceDelete` (`DomainException`); cancelar é `status='cancelado'`, nunca delete |

### 3.2 Processo de mudança
Charter (lei) → `casos.md` (contrato UC) → teste na lane `financeiro-pest.yml` (defesa).
Precedência quando discordam: **teste verde > casos > charter > SPEC** ([proibicoes](../../proibicoes.md) §Precedência).

---

## 4. Design system aplicável

- **Shell:** `AppShellV2` + `PageHeader` canônico; subnav própria `_shared/FinanceiroSubNav.tsx`.
- **Padrão de tela:** PT-01 Lista para os satélites; o Unificado é **workspace** (lista + drawer 3 camadas), fora do PT-01 puro.
- **Bundle Cowork:** `resources/css/cowork-canon-financeiro-bundle.css` aplicado **inteiro**
  (regra Tier 0 de bundle) e escopado por wrapper `.fin-cowork`.
  ⚠️ Os drawers são `<Sheet>` Radix **portalados pro `<body>`** — por isso o wrapper é re-aplicado
  no `<SheetContent>` e os tokens de domínio são **redeclarados de propósito** no bloco `.fin-cowork`
  ([proibicoes](../../proibicoes.md) §5 2026-07-10). Não "deduplicar".
- **Números** em IBM Plex Mono (KPI, coluna Valor, rodapé).

---

## 5. Arquitetura

### 5.1 Visão em camadas

```
Pages/Financeiro/<Tela>.tsx      (Inertia v3 + React 19)
        │  Inertia::render / router.post
Modules/Financeiro/Http/Controllers/<X>Controller.php     (23 controllers)
        │
Modules/Financeiro/Services/         UnificadoService · TituloService · TituloAutoService
                                      DreService · FluxoCaixaService · FluxoRealizadoService
                                      BoletoOcrService · FinanceiroAuditLogger
        │
Modules/Financeiro/Repositories/TituloRepository.php
        │
Modules/Financeiro/Models/            Titulo · TituloBaixa · TituloAnexo · ContaBancaria
                                      BankStatementLine · PlanoConta · Categoria
        ▲ bridge (Observers/Listeners/Jobs)
Núcleo UltimatePOS: transactions · transaction_payments · accounts · cash_registers
```

### 5.2 Modelo de dados (núcleo)

| Tabela | Papel | Invariante que este SDD defende |
|---|---|---|
| `fin_titulos` | título a pagar/receber | `valor_aberto ≤ valor_total`; `status ∈ {aberto, quitado, cancelado}`; sem hard-delete |
| `fin_titulo_baixas` | baixa (append-only) | `idempotency_key` uuid por baixa; soma das baixas de um título == `valor_total − valor_aberto` |
| `fin_titulos.titulo_pai_id` | **split** da baixa parcial | Σ(filhos) + `valor_aberto` do pai == valor original do pai (**CU-FIN-02**) |
| `fin_caixa_movimentos` | entrada/saída ligada à baixa | toda baixa gera 1 movimento |
| `fin_bank_statement_lines` | linha do extrato | dedupe por hash do arquivo/linha; `reabrir()` volta a `pendente` e zera `titulo_id`/`match_score` |
| `fin_contas_bancarias` | conta | `conta_bancaria_id` na baixa é **opcional** ([ADR 0175](../../decisions/0175-fix-observer-conta-bancaria-opcional.md)) → daí o pill "Conta indefinida" |

### 5.3 Fluxos críticos

> Âncoras são **símbolo + grep**, não `arquivo:NNN` (linha apodrece no primeiro refactor).

#### F1 · Listar a Visão Unificada `[T0]`
`Unificado/Index.tsx` → `GET /financeiro/unificado` → `UnificadoController@index` → `UnificadoService`.
Filtro grosso = **lente** (`caixa|receber|pagar`, clamp default `caixa`) ∩ chips de lifecycle
(interseção vazia = lente inteira, defense-in-depth). Props caras vão por closure/`only:` (partial reload).
Re-localiza: `grep -n "function index" Modules/Financeiro/Http/Controllers/UnificadoController.php`.

#### F2 · Baixar título (o fluxo `[V0]` central) — **SPLIT, não status `parcial`**
`FinBaixaSheet` → `POST /financeiro/unificado/{id}/baixar` → `UnificadoController@baixar`.
Sequência **medida no fonte** (`grep -n "function baixar" Modules/Financeiro/Http/Controllers/UnificadoController.php`):

1. `Titulo::where('business_id', …)->findOrFail($id)` — **Tier 0 no primeiro acesso**, não depois.
2. `status ∈ {quitado, cancelado}` → recusa com flash `error` (append-only contábil).
3. Conta: se informada, **valida no business** (`ContaBancaria::where('business_id',…)->find()`); senão auto-pick (`ativo_para_boleto` → primeira). **Sem conta cadastrada = recusa.**
4. **Clamp do valor:** `max(0.01, min($valor, $aberto))` — nunca baixa mais que o devido, nem menos que 1 centavo.
5. Meio de pagamento: valida contra `Titulo::FORMAS_PAGAMENTO`; inválido cai no default `transferencia`.
6. `restante = round($aberto - $valor, 2)`; `parcial = restante > 0.001`.
7. **Dentro de `DB::transaction`:**
   - **parcial** → cria **título FILHO** (`replicate()` + `fill()`) com `status='quitado'`, `valor_total=$valor`, `valor_aberto=0`, `titulo_pai_id`, número `R-/P-NNNNN` obtido com `lockForUpdate()`; cria a `TituloBaixa` **no filho**; **reduz o pai** para `valor_total = valor_aberto = restante` (o pai **permanece aberto**).
   - **total** → cria a `TituloBaixa` no próprio título; `valor_aberto = 0`; `status = 'quitado'`.

> 🔴 **Divergência de contrato encontrada (fonte 1 × fonte 2).** Os `casos.md` de ContasPagar/ContasReceber
> descrevem a baixa parcial como *"`valor_aberto = 70,00` e `status = parcial`"* — o código **não usa mais**
> `status='parcial'` desde 2026-06-04 (decisão [W], comentário literal no `baixar()`). O **perdedor é o
> `casos.md`**, corrigido nesta onda (§Reconciliação abaixo). E o `it()` do
> `UnificadoBaixaDialogGuardTest` **G3** ainda se chama *"reduz valor_aberto e **marca parcial**"* embora
> o corpo asserte o split correto — **descrição stale, corpo certo**.

#### F3 · Ações em lote `[V0]` `[T0]`
`POST /financeiro/unificado/bulk` `{action, ids[], payload{}}` → `UnificadoController@bulk`.
5 ações: `baixar` · `categoria` · `plano_conta` · `cancelar` · `exportar_csv`. Ownership de **todos**
os ids antes de qualquer escrita (1 alheio ⇒ 422, nada aplica); limite **500**/chamada; audit trail
`Activity bulk_*` com `{action, ids, count, total}`. Cancelar é append-only e **pula quitado**.

#### F4 · Criar título / lançamento
`TituloCreateSheet` → `POST /financeiro/unificado` → `UnificadoController@store`.
Apoios: `GET /unificado/sugerir-valor`, `GET /unificado/buscar-cliente`, `POST /unificado/ocr-boleto`
(OCR do boleto via Vision — US-FIN-029).

#### F5 · Conciliação bancária `[V0]`
`Conciliacao/Index.tsx` → `ConciliacaoController` (`importar`/`match`/`ignorar`/`reabrir`).
Upload OFX **idempotente por hash** (2× o mesmo arquivo não duplica linha); `match_score`
heurístico (valor exato + tolerância de dias + fuzzy da descrição) — **não** a constante 0.85;
`reabrir()` volta `status=pendente` e **zera** `titulo_id`/`match_score`, é idempotente, e de outro
business retorna 404. Toda transição escreve auditoria via `FinanceiroAuditLogger`.

#### F6 · Caixa do turno (leitura do núcleo)
`Caixa/Index.tsx` → `GET /financeiro/caixa` → `CaixaController@index` → `DB::table('cash_registers as cr')`.
Permissão `can:view_cash_register`. Filtro `?status=open|close`; `?limit` com **clamp 10..200**.
Cada linha mostra o vínculo com o `fin_titulo` (ou o CTA "lançar retroativo" quando o caixa fechou
sem título) — é a costura caixa↔financeiro.

#### F7 · DRE / Fluxo / Impostos (derivação)
`DreController@index` → `DreService` (linhas + `margem_operacional` + `top_categorias_receita`;
subtotal "Resultado operacional" com `highlight=true`). `FluxoController@index` → `FluxoCaixaService`
(saldo_hoje, saldo_30d, pior_dia, `margem_minima` configurável, `?dias` com clamp 7..60).

#### F8 · Boleto pelo título
`POST /unificado/{tituloId}/boleto` → `emitirBoletoTitulo` → `PaymentGatewayContract` (Inter).
**Anti-duplo-recebível:** a cobrança nasce com `origem_type='fin_titulo'`+`origem_id`; no pagamento o
listener `OnCobrancaPagaCreateFinanceiroTitulo` dá **baixa** neste título em vez de criar outro
(senão o recebível contaria **em dobro** — `[V0]`).

#### F9 · Bridge núcleo → módulo
`TransactionPaymentObserver` (pagamento do núcleo ⇒ baixa + movimento de caixa) ·
`CriarTituloDeVendaJob` · `BridgeExpenseToTitulosCommand` · `FinanceiroHealthCommand`
(cron diário que detecta buraco de bridge — US-FIN-040).

#### F10 · Anexos · aprovação · comentários
`GET/POST/DELETE /unificado/{id}/anexos[...]` · `solicitar-aprovacao|aprovar|rejeitar`
(permissão Spatie `financeiro.titulo.aprovar`) · `comments` + `audit`.

### 5.4 Paridade com a Blade legada (fonte 3) — onde o React ainda não chegou

⚙️ derivado · medido 2026-07-27.

| Capacidade da Blade core | Blade | React | Veredito |
|---|---|---|---|
| **Abrir** caixa (`btn-modal` → `CashRegisterController@create`) | `cash_register/index.blade.php` | ❌ ausente | **gap consciente** — o React linka `links.cash_register_legacy` |
| **Fechar** caixa (`close_register_modal.blade.php`) | ✅ | ❌ ausente | idem |
| Detalhe do turno (`register_details` / `register_product_details`) | ✅ | ❌ ausente | idem |
| Listar turnos + filtro por status | ✅ (só listagem) | ✅ **superior** (filtro, clamp, entradas/saídas, vínculo `fin_titulo`) | React à frente |
| Baixa de pagamento (`transaction_payment/*`) | ✅ (núcleo) | ✅ via `FinBaixaSheet` + bridge | coexistem por design |

> ⚠️ **Escalado a [W] (soberania):** transformar "abrir/fechar caixa fica no legado" em **Non-Goal**
> explícito do `Caixa/Index.charter.md` é decisão dele — o agent não preenche Non-Goal.

### 5.5 Onde a defesa não chega (dívida central de execução)

**Medido:** 80 arquivos de teste em `Modules/Financeiro/Tests`, **24 na allowlist** da
`financeiro-pest.yml`. Os demais **não rodam em lane nenhuma** — existem, parecem cobertura, e
nunca produzem veredito. Some-se um 2º efeito: a maioria usa `markTestSkipped` quando falta
business/tabela/module-gate — ou seja, mesmo **dentro** da lane um teste pode "passar por
não-execução" (a classe do §5 2026-07-24). Consequência prática para este SDD: **nenhum CU abaixo
nasce ✅ por leitura**; o veredito vem da lane.

---

## 6. Casos de uso

> Marcadores: `[V0]` = REGRA MESTRE valor · `[T0]` = invariante multi-tenant ·
> `[must]`/`[should]` = força. Status: ✅ provado na lane · 🧪 tem contrato+teste, veredito pendente ·
> 🟡 parcial · ⬜ sem contrato. **Nada aqui é ✅ por leitura** (G-7).

### 6.1 Núcleo do dinheiro (`CU-FIN`)

#### CU-FIN-01 — Venda a prazo gera título a receber `[must]` `[V0]` ✅
Toda venda a prazo nasce como `fin_titulos` tipo `receber`, `status=aberto`, valor = total da venda,
vencimento = data + prazo. Âncora: US-FIN-013 · charter Unificado. Defendido por `RetencaoLoopE2ETest`
(UC-F01), **na lane**.

#### CU-FIN-02 — Baixa parcial faz SPLIT e **conserva o valor** `[must]` `[V0]` 🧪
Dado título de R$ V aberto, baixa de R$ B < V ⇒ nasce título FILHO quitado de B (`titulo_pai_id`),
o pai reduz para `V − B` e **permanece aberto**. **Invariante de conservação:**
`Σ(filhos.valor_total) + pai.valor_aberto == V` — e a soma das `fin_titulo_baixas` do filho == B.
Não existe mais `status='parcial'`. Âncora: US-FIN-003 · charter Unificado v12 · decisão [W] 2026-06-04.
UC: `UC-FUNI-01`.

#### CU-FIN-03 — Baixa nunca excede o aberto nem desce de 1 centavo `[must]` `[V0]` 🧪
`clamp(0.01, valor, valor_aberto)`. Pedido acima do aberto **não** vira crédito nem título negativo:
quita exatamente o aberto. Âncora: US-FIN-003 · `baixar()`. UC: `UC-FUNI-02`.

#### CU-FIN-04 — Título quitado/cancelado recusa baixa `[must]` 🧪
Append-only contábil: nenhuma `TituloBaixa` nasce; o usuário recebe flash `error`. Âncora:
R-FIN-008 (SPEC §3) · charter. UC: `UC-FUNI-03`.

#### CU-FIN-05 — Conta bancária de outro business é recusada `[must]` `[T0]` 🧪
`conta_bancaria_id` alheio ⇒ recusa **sem** criar baixa (fail-closed). Âncora:
[ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · R-FIN-001. UC: `UC-FUNI-04`.

#### CU-FIN-06 — Baixa sem conta vinculada é permitida e **sinalizada** `[should]` ✅/🧪
[ADR 0175](../../decisions/0175-fix-observer-conta-bancaria-opcional.md) permite `conta_bancaria_id NULL`; a UI mostra
o pill "Conta indefinida" (CTA pro cadastro). Âncora: US-FIN-038. Defendido por
`UnificadoContaIndefinidaGuardTest` (UC-F05), **na lane**.

#### CU-FIN-07 — Ações em lote respeitam tenant, limite e contabilidade `[must]` `[V0]` `[T0]` 🧪
1 id alheio ⇒ 422 e **nada** aplica; limite 500; baixar em lote soma exata provada por 2 caminhos;
cancelar é append-only e pula quitado; modal apresenta "N títulos totalizando R$ X" **antes**;
audit trail com `{action, ids, count, total}`. Âncora: US-FIN-031. UC: `UC-F04` (legado).

#### CU-FIN-08 — Recebimento baixa o título **e** entra no caixa `[must]` `[V0]` ✅
Sem digitação dupla: `TransactionPayment` ⇒ `fin_titulo_baixas` ⇒ `fin_caixa_movimentos` (entrada).
Âncora: US-FIN-003 · RUNBOOK-transaction-payment. Defendido por `RetencaoLoopE2ETest` (UC-F02).

#### CU-FIN-09 — Boleto do título não duplica o recebível `[must]` `[V0]` ⬜
Cobrança nasce com `origem_type='fin_titulo'`; ao pagar, o listener **baixa** o título existente.
Âncora: US-FIN-010/016 · `emitirBoletoTitulo` docblock. **Sem UC ainda** — ver §6.4.

### 6.2 Conciliação (`CU-FIN-1x`)

#### CU-FIN-10 — Importar OFX é idempotente por hash `[must]` `[V0]` ⬜
2× o mesmo arquivo não duplica linha nem baixa. Âncora: US-FIN-009 DoD.
Teste existe (`ConciliacaoUploadDedupeTest`) mas **fora da allowlist** — veredito impossível hoje.

#### CU-FIN-11 — `match_score` discrimina candidatos `[must]` ⬜
Valor exato + mesmo dia ⇒ score ≈1.0; data afastada ⇒ score estritamente menor; **nunca** a constante
0.85 para candidatos distintos. Âncora: US-FIN-009 DoD (`valor_exato + tolerancia_3_dias + fuzzy ≥80%`).
Teste existe (`ConciliacaoMatchScoreTest`) **fora da allowlist**.

#### CU-FIN-12 — `reabrir()` é reversível, idempotente e tenant-safe `[must]` `[T0]` ⬜
Volta `status=pendente`, zera `titulo_id`/`match_score`, roda 2× sem erro, 404 cross-tenant, e grava
auditoria. Teste existe (`ConciliacaoAuditReabrirTest`) **fora da allowlist**.

### 6.3 Caixa (`CU-FIN-2x`)

#### CU-FIN-20 — Listar turnos de caixa com filtro e clamp `[must]` 🧪
`?status=open|close` filtra; `?limit` fora de 10..200 é **clampado** (não erro). Âncora:
charter `Caixa/Index` · `CaixaController@index`. UC: `UC-FCX-01`, `UC-FCX-02`.

#### CU-FIN-21 — Caixa de outro business nunca aparece `[must]` `[T0]` 🧪
Âncora: [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md). UC: `UC-FCX-03`.

#### CU-FIN-22 — Sem `view_cash_register` a tela é 403 `[must]` 🧪
Âncora: `$this->middleware('can:view_cash_register')`. UC: `UC-FCX-04`.

#### CU-FIN-23 — Abrir/fechar turno `[should]` ❌ **ausente no React** (paridade §5.4)
Fica no legado por ora. **Non-Goal ou US? decisão [W].**

### 6.4 Non-goals e lacunas declaradas (não são regressão silenciosa)

- **`ContasReceber/Index` e `ContasPagar/Index` não recebem CU novo** — estão `deprecated` e a
  US-FIN-064 (`todo`) prevê o redirect pro Unificado. Investir contrato ali seria contrato para
  tela que vai morrer. Os `[BACKLOG]` existentes ficam, **corrigidos** onde descreviam o `status='parcial'`
  que o código não usa mais.
- **DRE/Fluxo/Extrato/Relatórios/Cobrança/cadastros** — sem CU nesta versão (v1.0.0). Motivo declarado:
  os testes existem mas estão fora da lane; declarar UC citando teste que nunca roda produz o
  "verde impossível" que o `anchor-lint` existe pra denunciar.
- **Fonte 4 (Delphi) inexistente** — o contrato de paridade legado deste módulo é estruturalmente
  mais fraco que o do Produto. Não foi inventado.

---

## 7. Requisitos não-funcionais

| NFR | Alvo | Onde é defendido hoje |
|---|---|---|
| **Isolamento multi-tenant** | 0 vazamento | `MultiTenantComprehensiveTest`, `MultiTenantIsolationTest`, `AccountTransactionIdorTest` (na lane) |
| **Corretude de valor** | centavo exato, conservação no split | **lacuna até CU-FIN-02 rodar** |
| **Latência de lista** | partial reload `only:[kpis,lancamentos,pagination,filters,periodLabel]` + closures | charter v-D14 |
| **Auditabilidade** | toda transição escreve `activity_log` / `FinanceiroAuditLogger` | CU-FIN-07, CU-FIN-12 |
| **Idempotência** | `idempotency_key` na baixa; hash do OFX; `event_id` no webhook | R-FIN-005/007/012 |

---

## 10. Roadmap (derivado das lacunas acima, não inventado)

| # | Item | Origem |
|---|---|---|
| R1 | Rodar `CU-FIN-02/03/04/05` na lane e promover 🧪→✅ | esta onda |
| R2 | Entrar `Conciliacao*Test` + `DreControllerTest` + `FluxoControllerTest` na allowlist (exige run no CT 100 antes — ratchet de verdes) | §5.5 |
| R3 | Decidir Non-Goal × US para abrir/fechar caixa no React | §5.4 — **[W]** |
| R4 | US-FIN-064 (redirect CR/CP→Unificado) e então remover o trio das 2 telas | SPEC |
| R5 | CU-FIN-09 (anti-duplo-recebível do boleto) ganhar UC + teste | §6.1 |

---

## Trilha do tempo

- **2026-07-27 · v1.0.0 · [CC]** — SDD criado do zero pelo `sdd-from-source` (Onda 2 do passo 5).
  3 fontes trianguladas (Delphi ausente, declarado). §5.3 com F1–F10 derivados do fonte;
  §6 com CU-FIN-01..23. Achados: divergência `status='parcial'` × split; 56 de 80 testes fora da
  lane; paridade de caixa (abrir/fechar) ausente no React.
