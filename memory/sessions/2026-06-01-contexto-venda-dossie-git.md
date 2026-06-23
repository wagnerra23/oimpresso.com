# Dossiê de Contexto — A Tela de Venda (`Sells`) · pesquisa profunda no git

> **Pedido [W] 2026-06-01:** *"procure tudo antes de fazer e grave o que vai precisar… avalie a importância de cada arquivo para poder contextualizar a tela de venda."*
> **Autor:** [CC] · **Fonte:** `wagnerra23/oimpresso.com@main` (read-only via connector) + Cowork local.
> **Função:** mapa de contexto rankeado + conceitualização da venda. É a **pesquisa pré-charter** que alimenta o futuro `Vendas.charter.md`. NÃO é lei — é o que eu li e o peso que dei.
> **Regra:** antes de tocar a venda, reler ESTE dossiê + o charter (quando existir). Não redescobrir o domínio do zero (Método Migration→Tela).

---

## 0. Veredito de uma linha

**Sells = venda comercial, NÃO POS.** O repo já separa em **3 superfícies** e tem o módulo Sells **muito mais maduro** que o protótipo Cowork. O `vendas-create-page.jsx` do Cowork **regrediu pra POS** (bipe de código de barras, "Consumidor Final", NFC-e cupom, F2-imprimir) — é esse o "se perdeu e ficou simples". O modelo de dados do endereço de entrega (P0) **já existe no repo** (`contact_addresses`, US-CRM-078, com `city_code` fiscal); o gap é o Sells **consumir** esse catálogo e **ligar à NF-e** (destinatário ↔ local de entrega).

---

## 1. As 3 superfícies (canon — `Create.charter.md` §Non-Goals)

| Superfície | Rota | DNA | Doc fiscal |
|---|---|---|---|
| **Venda comercial** | `/sells/create` | cliente cadastrado · ciclo de vida FSM · frete · impostos | NF-e 55 / NFS-e 56 |
| **POS / balcão** | `/sale-pos/create` | walk-in · rápido · cupom | NFC-e 65 |
| **Cotação** | `/sells/quotations` | proposta → converte em venda | — (PDF) |

→ Misturar DNA de POS dentro do `/sells/create` é **anti-pattern explícito do charter**.

---

## 2. Modelo conceitual da venda (do código real)

**Ciclo de vida (FSM · ADR 0129/0143).** Campo `status` (`quotation/draft/proforma/final`) + máquina de estados tabular. Trilha **"Venda Com Produção"** (a da gráfica):
```
quote_draft → quote_sent → quote_approved → in_production → ready_for_invoice
→ invoiced → paid → delivered → completed   (+ cancelled / on_hold laterais)
```
Cada transição tem **RBAC por role** (`sale_stage_action_roles`) + **side-effects** (ReservarEstoque, ConsumirEstoque, BaixarFinanceiro, EmitirNFeJob, CancelarVendaCascade). `current_stage_id` só muda via `ExecuteStageActionService` (Observer bloqueia UPDATE direto). **Gate fiscal:** `faturar_os` exige *"cliente tem CNPJ + endereço completo"*; `entregar_ao_cliente` exige NF-e+NFS-e autorizadas.

**Estrutura da tela (`Create.tsx` · Cockpit V2).** Página única longa · header sticky + **pills de seção** (Dados/Produtos/Pagamento/Resumo/Mais opções) + scroll-spy + footer sticky. **4 KPIs gigantes** (Itens/Total/Pago/Status pgto). **Triagem 18 campos → 8 visíveis + 10 colapsados** em "Mais opções".

**Multi-documento por venda** (`transaction_documents` poly): 1 venda → N notas (nfe55 + nfse56 + mdfe58). O caso comvis canônico = banner (NF-e 55) + instalação (NFS-e 56) + transporte se >R$500 (MDF-e 58), tudo em 1 transaction.

**Funções/peças que JÁ EXISTEM** (não inventar — reusar/portar):
`CustomerSearchAutocomplete` + `QuickAddCustomerSheet` · `ProductSearchAutocomplete` + `ProductLineCard` · `PaymentRow` (split + saldo falta/troco/exato) · `FsmActionPanel` · `FiscalSection` (emite 55/65) · `CobrancaDrawer` (boleto/PIX) · `CommissionSplitEditor` · `CriarOsButton` · auto-save draft `{biz}.{user}` · `validacoesFiscaisBr` (sem CNPJ/CPF não emite) · `NumericInputPtBR`.

---

## 3. O P0 — endereço de entrega na venda/NF-e (mapa cirúrgico)

### O que JÁ EXISTE no repo
- **`app/ContactAddress.php` (US-CRM-078)** — catálogo de múltiplos endereços do cliente. Schema `contact_addresses`:
  `label · zip_code · address_line_1 · numero · address_line_2 (compl) · neighborhood · city · state · **city_code (IBGE/cMun)** · is_default · is_shipping` + soft-delete + `HasBusinessScope` (Tier 0).
  - `Contact::addresses()` / `defaultAddress()` (is_default) / `shippingAddress()` (is_shipping).
  - Accessor `one_line` (UI do seletor) + `toInlineArray()` (espelha no inline `contacts` + gera o `shipping_address` texto) + `backfillInline()`.
  - Comentário no model (verbatim): *"o seletor de entrega na venda (Sells) escolhe dele ou digita um avulso ('Outro')."* → **o feature foi DESENHADO pra Sells; só não foi ligado.**
- **Transaction (UltimatePOS)** — bloco frete = 5 campos: `shipping_address` (texto livre) · `shipping_details` · `shipping_charges` · `shipping_status` · `delivered_to`.

### O gap (4 pontos)
1. **Sells não consome o catálogo.** `CustomerSearchAutocomplete` devolve `shipping_address?: string` (texto solto); `Create.tsx` joga num campo livre **dentro de "Mais opções"** (marcado "só serviços com entrega").
2. **Sem o conceito fiscal** destinatário ↔ local de entrega. `FiscalSection` só **emite**, não estrutura endereço. Na NF-e 55: `<dest>` = cadastro do cliente; `<entrega>` (grupo G) = local de entrega quando difere → `city_code`/cMun dispara MDF-e.
3. **Enterrado.** Pra gráfica de comunicação visual a entrega é **regra**, não exceção (prod esconde porque a persona prod é Larissa-vestuário-balcão).
4. **Risco L-21 (duplicação):** a sessão Cowork 2026-06-01 desenhou `customer_addresses` em `data-os.jsx` — **quase-duplicata** do `contact_addresses` do repo. → **Convergir o mock pro schema do repo** (`contact_addresses` + `city_code`), não manter modelo paralelo.

### Direção do P0 (a confirmar com [W])
Venda comercial = aparato do prod (status/FSM, autocompletes, FiscalSection, validações BR) **+ seletor estruturado de endereço** lendo `contact.addresses[]`, reposicionado como **Destinatário (cadastro/is_default) + Local de entrega (is_shipping / "Outro")**, com `city_code` alimentando o gatilho de MDF-e — promovido pra **seção própria** quando a venda tem entrega.

---

## 4. ÍNDICE RANKEADO DE FONTES (o que vale o quê pra contextualizar a venda)

> Peso: **P0** = leitura obrigatória antes de tocar a venda · **P1** = ler pro escopo da rodada · **P2** = consulta pontual.

### Git — domínio & contrato
| Peso | Arquivo (repo) | Por que importa |
|---|---|---|
| **P0** | `resources/js/Pages/Sells/Create.charter.md` | Contrato canônico do Create: missão, Non-Goals (POS/cotação/NFC-e separados), 8+10 campos, anti-patterns. **O "gosto" travado.** |
| **P0** | `resources/js/Pages/Sells/Create.tsx` (69KB) | Implementação real: useForm shape, os 5 campos `shipping_*`, `handleCustomerSelect`, transform→`/pos`, FSM status, draft, atalhos. **A verdade do que existe.** |
| **P0** | `app/ContactAddress.php` + `app/Contact.php` | Schema real do endereço (P0). `contact_addresses` + `city_code` + relations. **Base do P0 — já existe.** |
| **P0** | `memory/requisitos/Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md` | A venda da gráfica end-to-end: banner NF-e55 + instalação NFS-e56 + MDF-e, FSM concreto, gate "CNPJ + endereço completo". **O caso real do piloto.** |
| **P1** | `resources/js/Pages/Sells/Index.charter.md` (v6, 14KB) | Contrato da listagem: FSM dots, fiscal badges, drawer SaleSheet, bulk emit, saved views. Espelho do estado-da-arte. |
| **P1** | `memory/requisitos/Sells/CASOS-USO-PIPELINE-VENDAS.md` | 7 GAPs do pipeline + FSM Given/When/Then + RBAC + cancelamento cascata + sequencial fiscal. **A lógica de negócio dura.** |
| **P1** | `memory/requisitos/Sells/RUNBOOK-create.md` | Mapa campo-legacy→alvo (tabela 18 campos), props do controller, tokens, estados, pegadinhas (`format_now_local`, biz=1 nunca biz=4). |
| **P1** | `resources/js/Pages/Sells/_components/FiscalSection.tsx` | Como a UI emite/exibe 55/65 (badge, chave 44, DANFE). Onde o endereço fiscal vai plugar. |
| **P1** | `_components/CustomerSearchAutocomplete.tsx` | Contrato do cliente na venda: hoje devolve `shipping_address` string (o ponto a estender pra `addresses[]`). |
| **P2** | `memory/requisitos/Sells/SPEC.md` (73KB) | US-SELL-* completas. Consultar US específica sob demanda (não ler inteiro). |
| **P2** | `_components/{PaymentRow,FsmActionPanel,CommissionSplitEditor,CobrancaDrawer,CriarOsButton}.tsx` | Peças prontas — ler a que a rodada tocar. |
| **P2** | `Create.review.md` · `Edit/Show/Quotations/Drafts/Subscriptions.charter.md` | Telas-irmãs + débitos conhecidos (ex: `Inertia::defer` ausente, responsivo 49/100). |
| **P2** | `Modules/NfeBrasil/Services/NfeService.php` (75KB) | Builder do XML 55/65 + sequencial + `<entrega>`. Mergulhar só quando ligar o local de entrega de fato. |
| **P2** | ADRs `0129` (FSM) · `0143` (FSM live) · `0192` (OS→venda) · `0110` (Cockpit V2) · `0093` (multi-tenant) · `0121` (vertical) | Lei de fundo. Citar, não reler inteiro. |

### Cowork — estado atual do protótipo
| Peso | Arquivo (local) | Por que importa |
|---|---|---|
| **P0** | `vendas-create-page.jsx` | A tela que regrediu pra POS. **Alvo da reescrita.** Já tem o picker de endereço estruturado (bom) mas sob "Frete" e com DNA POS. |
| **P0** | `data-os.jsx` | Modelo de endereço do Cowork (`addresses[]` + `cliEntregaAddr`/`fmtAddrLinha` + `OS_MATRIZ_CIDADE`). **Convergir pro `contact_addresses` do repo (city_code).** |
| **P1** | `vendas-page.jsx` + `data-vendas.jsx` | Index (9.5, referência): FSM stepper, fiscal cells, comissão, saved views, source. O que NÃO mexer. |
| **P1** | `clientes-page.jsx` | `CliEnderecoSection` (cards de endereço, flags cadastro/entrega) — o par cadastro↔venda do P0. |
| **P2** | `vendas-{ai,curation,extras,flow,output,shortcuts,tweaks}.jsx` · `pg-vendas-integration.jsx` · `pg-sells-cobranca-preview.jsx` | Camadas de apoio do protótipo (IA, atalhos, integração oficina, preview cobrança). |

---

## 5. Decisões / suposições (a confirmar com [W])
- **D-a:** Create do Cowork vira **só a venda comercial**; balcão rápido é `/sale-pos` (superfície separada). *[assumido — "sells não é POS"]*
- **D-b:** Endereço de entrega vira **seção própria** quando há entrega (não fica em "Mais opções"). *[assumido p/ comvis]*
- **D-c:** Endereço estruturado **converge pro `contact_addresses` do repo** (com `city_code`), aposentando o `customer_addresses` paralelo do Cowork. *[recomendação anti-L-21]*

## 6. Próximo passo
1. [W] bate D-a/D-b/D-c.
2. [CC] escreve `Vendas.charter.md` (cristaliza este dossiê: venda≠POS, FSM, 8+10 seções, endereço destinatário↔local de entrega como goal).
3. [CC] desenha o Create reposicionado (endereço de 1ª classe ligado à NF-e).

## Refs
- Git: `Sells/{Create,Index,Quotations}.charter.md` · `Create.tsx` · `Create.review.md` · `app/{Contact,ContactAddress}.php` · `FiscalSection.tsx` · `CustomerSearchAutocomplete.tsx` · `memory/requisitos/Sells/{CASO-PRATICO-OS-COMUNICACAO-VISUAL,CASOS-USO-PIPELINE-VENDAS,RUNBOOK-create}.md`
- Cowork: `vendas-create-page.jsx` · `data-os.jsx` · `vendas-page.jsx` · `data-vendas.jsx` · `clientes-page.jsx` · `memory/sessions/2026-06-01-endereco-cliente-e-venda.md`
- ADRs: 0129/0143 (FSM) · 0192 (OS→venda) · 0110 (Cockpit V2) · 0093 (multi-tenant) · 0121 (vertical)
