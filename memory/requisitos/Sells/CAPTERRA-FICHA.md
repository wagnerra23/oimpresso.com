# CAPTERRA-FICHA — Sells (capacidade)

> Ficha canônica de benchmark de **capacidade** do módulo Sells (venda/PDV — core UltimatePOS + MWART Inertia).
> **Gerada:** 2026-07-02 · agente `capterra-senior` · Onda 1.1 do programa de ondas ([1.1-adversario-capterra.md](../_Governanca/programa-ondas/onda-1-sells/1.1-adversario-capterra.md))
> **Persona primária:** Larissa @ ROTA LIVRE (`business_id=4`), balconista não-técnica, `Sells/Create` ~50×/dia, monitor 1280×1024, vestuário Termas do Gravatal/SC (internet de loja instável). 99% do volume do oimpresso novo.
> **Alvo de código:** `resources/js/Pages/Sells/Create.tsx` (~1647 LOC, V2 live atrás da flag `useV2SellsCreate`) · `app/Http/Controllers/SellPosController.php@store` (legado UltimatePOS) · `app/Domain/Fsm/` (pipeline Sells 11 stages, [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md))
> ADR governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md) (Capterra-driven) + [0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) (tests biz=1) + [0284](../../decisions/0284-pipeline-incidente-graduado-confianca.md) (incidente graduado)

> ⚠️ **Complementar, não substituto.** Já existe [`CAPTERRA-DESIGN-FICHA.md`](CAPTERRA-DESIGN-FICHA.md) (nota **68**, foco UX/design 15 dim) e o screen-grade (`Sells/Create` **88 Leader**, `Sells/Index` **90 Leader**). Esta ficha mede **CAPACIDADE** (features/automação/fiscal/pagamento/resiliência) vs os líderes de PDV/venda — eixo que a nota de design **não mede**. Ver §8 "O que a nota alta esconde".

---

## 1. Identidade do módulo

- **Nome interno:** `Sells` (feature core UltimatePOS — sem diretório próprio em `Modules/`; rotas/views fora de `Modules/`, FSM em `app/Domain/Fsm/`)
- **Domínio:** Venda / frente de caixa / orçamento-cotação + pipeline FSM canônico
- **Função:** criação e gestão de vendas (à vista, a prazo, split, com frete, com desconto), integrando o legado UltimatePOS com UI moderna Inertia/React (MWART)
- **Estado lifecycle:** V2 (`Create.tsx`) **live atrás de flag** `useV2SellsCreate` (guard biz=4 removido 2026-05-27); canary 7d biz=1; Blade legado coexiste (US-SELL-009 remoção pendente)
- **Clientes diretos:** ROTA LIVRE biz=4 (Larissa, 99% volume) + Wagner biz=1 (canary/smoke seguro)
- **Diferencial-chave:** multi-tenant Tier 0 real ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) + autosave de rascunho por `{biz}.{user}` + FSM pipeline auditável + vínculo venda↔OS/Oficina ([ADR 0251](../../decisions/0251-veiculo-na-venda-direta-oficina.md))

## 2. Concorrentes-alvo

Pricing qualitativo (Tier 0: não commitar valores BRL — [proibicoes](../../proibicoes.md)). Global em US$ (referência pública).

| # | Concorrente | Tipo | Faixa | Lacuna que o oimpresso pode preencher | Fonte |
|---|---|---|---|---|---|
| 1 | **Bling** | ERP PME BR + frente de caixa | entrada baixa → sério | **Zero PDV offline** (100% online, limitação documentada); UI Bootstrap legado | bling.com.br |
| 2 | **Tiny (Olist)** | ERP PME BR | entrada → médio | Não anuncia offline-first; autosave silencioso ausente | tiny.com.br |
| 3 | **Omie** | ERP + Omie.PDV BR | PDV cobrado à parte | **Líder BR de capacidade** (offline contingência + Pix QR + NFC-e/SAT) — nossa referência-topo | omie.com.br |
| 4 | **Conta Azul** | ERP/financeiro PME BR | entrada → médio | NFC-e não cobre CE; checkout mouse-free forte (a copiar) | contaazul.com |
| 5 | **Shopify POS** | PDV varejo global | POS Pro US$89/loja/mês | **Sem fiscal BR (NFC-e)** — desqualificado p/ loja BR; UX/offline exemplar | help.shopify.com |
| 6 | **Square POS** | PDV varejo/rest. global | free + US$49/loja/mês | Sem NFC-e; **offline card ON por padrão (abr/26)** — a copiar | squareup.com |
| 7 | **Stripe Terminal** | Pagamento presencial (API) | por-transação | Só trilho de pagamento — sem PDV/fiscal completo | docs.stripe.com |
| 8 | **Lightspeed Retail** | PDV varejo global | US$89–239/mês + por-caixa | Sem fiscal BR; devolução documentada como truncada | lightspeedhq.com |
| 9 | **Nex (Nextar)** | PDV pequeno-varejo BR | free → entrada | Vende offline; escolha reembolso-caixa vs crédito-loja (a copiar) | nextar.com.br |
| 10 | **Hiper** | ERP pequeno-varejo BR | entrada | **Fiscal mais amplo** (CF-e/SAT SP+CE, NFC-e, PAF-NFC-e SC, MDF-e) + offline + Pix QR | hiper.com.br |
| 11 | **Linx Microvix** | ERP varejo enterprise BR | quote | PDV por function-key (F2/L) + offline contingência; UX pesada | linx.com.br |
| 12 | **Clover** | PDV varejo/rest. US | HW + planos | Sem fiscal BR; offline enfileira mas trava cancel/refund | clover.com |
| 13 | **Toast** | PDV restaurante US | free HW/SW + tiers | Sem fiscal BR; usabilidade/busca fortes (a copiar) | toasttab.com |

## 3. Capacidades em produção (validadas)

```yaml
capacidades_em_prod:
  - us: US-SELL-005
    nome: "Busca de produto autocomplete (debounce 250ms) + tabela editável cálculo reativo"
    score: P0
    onde: "Create.tsx + _components/ProductSearchAutocomplete.tsx"
    evidencia: "ProductSearchAutocompleteRaceTest + ProductSearchConfigurableFieldsTest"

  - us: US-SELL-006
    nome: "Split de pagamento (N linhas) + frete + desconto%/fixo + imposto pedido"
    score: P0
    onde: "Create.tsx + _components/PaymentRow.tsx (NumericInputPtBR)"

  - us: US-SELL-007
    nome: "Autosave de rascunho localStorage debounced por {biz}.{user} (Tier 0) + atalhos / Esc Ctrl/Cmd+Enter"
    score: P1
    onde: "Create.tsx (keydown listeners c/ cleanup, guard input)"
    evidencia: "SellsCreateAutosaveContractTest"

  - us: US-SELL-004
    nome: "Triagem de densidade: 8 campos visíveis + 10 colapsáveis (1280px-first)"
    score: P1
    onde: "Create.tsx <details> 'Mais opções', estado persistido localStorage"

  - us: US-SELL-FSM
    nome: "Pipeline FSM canônico 11 stages × 21 actions × 10 roles per-business (audit append-only)"
    score: P0
    onde: "app/Domain/Fsm/ (ADR 0143 LIVE prod biz=1 2026-05-12)"
    evidencia: "GuardsFsmTransitions trait + sale_stage_history"

  - us: US-NFE-002
    nome: "Emissão NF-e/NFS-e (via Modules/NfeBrasil/NfseBrasil) — modais no detalhe da venda"
    score: P0
    onde: "Sells/Show.tsx + _components/VdNfeEmitModal.tsx + FiscalSection.tsx"

  - us: US-SELL-251
    nome: "Vínculo venda↔veículo/OS (integração Oficina) — diferencial vertical"
    score: P2
    onde: "ADR 0251 (jun/2026) + VeiculoNaVendaSchemaTest"

  - us: US-SELL-COMM
    nome: "Comissão por vendedor/profissional (split editor)"
    score: P1
    onde: "_components CommissionSplitEditor + CommissionSplitEditorTest"

  - us: US-SELL-RET
    nome: "Devolução de venda (/sell-return) — menu Ações por linha"
    score: P1
    onde: "SellController + SellsTabelaUnificada (restaurado #3494)"

  - us: US-SELL-CAIXA
    nome: "Caixa do dia por origem + Web Share API mobile"
    score: P2
    onde: "Sells/Caixa/Index (/vendas/caixa, ADR 0192)"
```

## 4. Dimensões de capacidade P0-P3 — comparativa

Legenda: ✅ pareia/supera líder · 🟡 parcial · ❌ ausente. Nota /10 por mecanismo concreto (não por nome do concorrente).

| ID | Capacidade | Peso | Líder do eixo (mecanismo SOTA) | oimpresso Sells hoje | Nota /10 |
|---|---|:-:|---|---|:-:|
| **C01 (P0)** | Busca produto rápida (autocomplete/barcode-into-search) | 4 | Shopify (scanner→search bar universal) | 🟡 autocomplete debounce + `/`; scanner cai na busca mas sem fluxo barcode-first dedicado | **7** |
| **C02 (P0)** | **Correção de cálculo (subtotal/desc/imposto/total) — comprovada por teste** | 4 | ninguém *anuncia*; QA-vendors só | 🟡 calcula reativo + `Math.round` 2 casas + `num_uf` unit tests; **sem teste E2E que a venda persiste total certo** — incidente prod (§8) | **5** |
| **C03 (P0)** | Fiscal BR no PDV (NFC-e + contingência `tpEmis=9`) | 4 | Hiper/Omie (contingência auto após 10s SEFAZ) | 🟡 NfeBrasil emite NF-e/NFS-e no **detalhe** (pós-venda); **sem NFC-e-no-checkout nem contingência automática** | **6** |
| **C04 (P0)** | Pagamento: split + Pix auto-reconcile + TEF | 4 | Omie.Cash/Gálago (QR + webhook ~10-15s); Conta Azul↔Stone (TEF→NFC-e) | 🟡 split payment sim; **Pix-webhook auto-reconcile e TEF auto-fill NFC-e ausentes** | **5** |
| **C05 (P0)** | Offline / resiliência de conexão | 4 | Omie/Hiper/Square (offline default, sync depois) | ❌ **100% online** (igual Bling) — Larissa em SC com internet instável fica travada | **2** |
| **C06 (P0)** | Isolamento multi-tenant (Tier 0) | 4 | — (concorrentes multi-empresa, não Tier 0 rígido) | ✅ `business_id` global scope + guard SQL cross-tenant testado | **9** |
| **C07 (P1)** | Keyboard-first coeso (barcode→Enter→pagar) | 2 | Bling (Alt+key config), Conta Azul (mouse-free), Linx (F2/L) | 🟡 `/`, Esc, Ctrl/Cmd+Enter; **sem hotkeys configuráveis nem fluxo Enter-avança-estágio** | **5** |
| **C08 (P1)** | Autosave rascunho crash-proof | 2 | **ninguém anuncia** (todos só "pré-venda" explícita) | ✅ localStorage debounced por `{biz}.{user}` — supera o mercado (lane vazia) | **8** |
| **C09 (P1)** | Pré-venda / parked sale c/ reserva de estoque | 2 | Omie (park reserva estoque) | 🟡 draft/quotation sim; `ReservarEstoque` existe no FSM mas não no park do rascunho | **6** |
| **C10 (P1)** | Devolução/troca (parcial + crédito-loja) | 2 | Shopify POS Pro / Bling (por CPF, parcial, store credit) | 🟡 `/sell-return` existe; parcial + escolha reembolso-vs-crédito rasos | **5** |
| **C11 (P1)** | Densidade/triagem de campos (form longo) | 2 | Conta Azul (qtd estoque inline) | ✅ 8 visíveis + 10 colapsáveis, 1280px-first, sem overflow | **8** |
| **C12 (P1)** | Comissão por vendedor | 2 | Cellity/Linx | ✅ split editor por profissional | **7** |
| **C13 (P2)** | Perceived performance (skeleton, INP<200ms) | 1 | Shopify (Spring'26 −1min carts) | 🟡 `Inertia::defer` na Index; **Create sem skeleton inicial** | **5** |
| **C14 (P2)** | Empty states + microcopy PT-BR | 1 | Toast (busca/copy limpos) | ✅ EmptyState shared + CTA + PT-BR consistente | **8** |
| **C15 (P2)** | A11y (WCAG 2.1 AA) | 1 | Shopify Polaris | 🟡 aria-labels ok, focus herdado shadcn, `aria-current` parcial | **6** |
| **C16 (P2)** | Integração venda↔OS/Oficina (vertical) | 1 | — (POS genérico não tem) | ✅ vínculo veículo/OS (ADR 0251) — diferencial vertical | **8** |
| **C17 (P2)** | Mobile / compartilhamento | 1 | Square (iPad camera scan) | 🟡 1280px-first + Web Share API; não é mobile-first | **6** |
| **C18 (P3)** | Onboarding / tour 1ª venda | 0.5 | Shopify (tooltips contextuais) | ❌ sem tour nem tooltips | **3** |
| **C19 (P3)** | Periféricos (balança/impressora/gaveta/scanner) | 0.5 | Tiny/Nex/Hiper (kit completo) | 🟡 legado UltimatePOS suporta; V2 Create não expõe explicitamente | **5** |

## 5. Cálculo da nota ponderada

Pesos canônicos: **P0=4 · P1=2 · P2=1 · P3=0.5**.

```
P0 (peso 4): (C01 7 + C02 5 + C03 6 + C04 5 + C05 2 + C06 9) = 34 × 4 = 136
P1 (peso 2): (C07 5 + C08 8 + C09 6 + C10 5 + C11 8 + C12 7) = 39 × 2 =  78
P2 (peso 1): (C13 5 + C14 8 + C15 6 + C16 8 + C17 6)         = 33 × 1 =  33
P3 (peso 0.5):(C18 3 + C19 5)                                =  8 × 0.5=   4

Σ ponderado = 136 + 78 + 33 + 4 = 251

Máximo possível:
  P0: 6×10×4 = 240 · P1: 6×10×2 = 120 · P2: 5×10×1 = 50 · P3: 2×10×0.5 = 10  → 420

nota_capacidade = 251 / 420 × 100 = 59.8 → **60/100**
```

```
NOTA CAPACIDADE oimpresso Sells: 60/100
Referência-topo BR (Omie, mesma persona): ~75/100  — offline contingência + Pix QR + NFC-e/SAT
Referência BR direta (Bling):             ~66/100  — fiscal+split+devolução fortes, MAS zero offline
Referência UX-global (Shopify POS):       ~48/100  — desqualificado p/ BR (sem NFC-e), apesar de UX/offline exemplar

Gap pro topo BR (Omie): -15 pts. Causa: offline (C05=2) + fiscal-no-PDV/contingência (C03) + automação de pagamento (C04).
Vantagem sobre Bling em: autosave silencioso (C08), densidade (C11), multi-tenant Tier 0 (C06), venda↔oficina (C16).
```

**Leitura honesta:** a capacidade (60) fica **abaixo** do design (88-90) e do UX (68) — e isso é o ponto. O benchmark de features contra líderes fiscais/offline expõe lacunas que a tela bonita esconde. O oimpresso ganha nos eixos **modernos** (autosave, Tier 0, densidade, integração vertical) e perde nos eixos **operacionais-duros do varejo BR** (offline, contingência fiscal, Pix/TEF automático).

## 6. Top gaps P0/P1 (pra subir a nota)

| # | Gap | Cap | Esforço | ROI (persona Larissa) | Sinal ADR 0105 | Concorrente que tem |
|---|---|---|---|---|---|---|
| **G-01** | **Teste E2E de correção de cálculo** (venda desc%/split/frete/imposto → assert `final_total`/subtotais persistidos) — fecha C02 | C02 | M (~6-10h, US-SELL-040) | **P0 crítico** — incidente R$ inflado ×100k já ocorreu em prod (§8) | ✅ execute (dor real comprovada) | ninguém *anuncia*, mas é dever Tier 0 |
| **G-02** | **Offline-first (fila IndexedDB + reemissão ao reconectar)** — fecha C05 | C05 | L (~40h) | **alto** — internet de loja instável em SC é o modo de falha exato da Larissa | 🟡 medir frequência de queda antes | Omie, Hiper, Nex, Square |
| **G-03** | **NFC-e no checkout + contingência automática `tpEmis=9`** (SEFAZ timeout 10s → offline series) — fecha C03 | C03 | L (~30h) | alto (loja precisa cupom na hora) | ✅ execute (fiscal é obrigação) | Hiper, Omie, Linx |
| **G-04** | **Pix QR no PDV + webhook auto-reconcile (~15s) ligado à venda/caixa** — fecha C04 | C04 | M (~20h) | alto (elimina "chegou o Pix?") | ✅ execute | Omie.Cash, Gálago |
| **G-05** | **Keyboard-first coeso** (hotkeys configuráveis + Enter-avança + F-key pagar) — fecha C07 | C07 | S (~8h) | médio-alto (50 vendas/dia = muscle memory) | 🟡 Larissa não é power-user; medir | Bling, Conta Azul, Linx |
| **G-06** | **Skeleton no Create + INP<200ms** — fecha C13 | C13 | S (~3h) | médio (percepção a cada venda) | 🟡 medir Web Vitals biz=4 primeiro | Shopify (silencioso) |

## 7. Diferenciais oimpresso vs concorrentes

1. **Autosave silencioso de rascunho** (`{biz}.{user}`, Tier 0) — **lane de mercado vazia**: concorrentes só têm "pré-venda" explícita, ninguém anuncia autosave crash-proof.
2. **Multi-tenant Tier 0 real** (`business_id` global scope + guard SQL cross-tenant testado) — concorrentes são multi-empresa mas sem isolamento auditável desse nível.
3. **FSM pipeline canônico auditável** (11 stages × actions × roles, `sale_stage_history` append-only) — governança de estado que PDV genérico não tem.
4. **Integração venda↔OS/Oficina** (vínculo veículo, ADR 0251) — diferencial vertical inexistente em PDV horizontal.
5. **Stack moderna** Laravel 13.6 + React 19 + Inertia v3 — vs Bootstrap legado (Bling) / UI pesada (Linx).
6. **Disciplina de valor Tier 0** (regra-mestre cálculo: dupla confirmação + antes→depois + aprovação) — base pra o único diferencial que **nenhum** concorrente anuncia: "o total está sempre certo" (ver §8 + G-01).

## 8. O que a nota "90 Leader" esconde (leitura adversarial)

O pedido da Onda 1.1: procurar o que a nota alta **esconde**. Três achados:

1. **A nota 90/88 é de DESIGN, não de capacidade.** `Sells/Index 90` e `Sells/Create 88` vêm do **screen-grade** (16 dimensões de UX/DS). Esse grader **não mede correção de cálculo, fiscal, offline ou pagamento**. A nota de capacidade (esta ficha) é **60**. A tela bonita mascara lacunas operacionais duras.

2. **A defesa de cálculo é real mas incompleta — e o pior já aconteceu em produção.** O que defende o total hoje:
   - frontend `Math.round(total×100)/100` (`Create.tsx:692`) + `NumericInputPtBR`;
   - `num_uf` com 2 unit tests (`IncidentValorInfladoNumUfTest`, `NumUfHeuristicPtBRTest`) — **reativos**, nascidos do incidente;
   - sensor **runtime** `sells_value_sanity` (`jana:health-check`, `final_total > (tbt+tax+ship)×1.5`, ADR 0284) — **detecção post-hoc diária**, não prevenção em CI nem em write-time.

   **O que falta:** **nenhum teste E2E** submete uma venda (desc% + split + frete + imposto) e verifica que o `final_total`/subtotais **persistem corretos**. O `SellPosControllerStoreInvariantsTest` são **11 asserts estruturais** (`strpos`/`toContain` no *source* do `store()`) — teste **tautológico** (anti-padrão catalogado em [proibicoes §5, 2026-06-05](../../proibicoes.md)): passa mesmo se a conta estiver errada. O `SellsTotalsTest` idem (testa agregação da lista, não a venda). O próprio SPEC admite (US-SELL-008/040): *"não valida que a venda persiste corretamente — apenas que o código não regrediu"*.

   **Prova de que isso morde:** incidente 2026-06-05, ROTA LIVRE biz=4 — `num_uf` strippou o decimal de um desconto% → **16 vendas infladas ~×100.000** + pagamentos corrompidos. Pego **em produção**, não por teste. Deu origem à regra-mestre Tier 0 de cálculo de valor/estoque.

3. **Larissa fica travada quando cai a internet.** C05=2: PDV 100% online. A persona opera numa loja em SC com conexão instável; o modo de falha mais comum dela (queda de rede no meio da venda) **não tem cobertura** — enquanto Omie/Hiper/Nex/Square já resolveram isso. O autosave (C08=8) protege contra crash de aba, mas **não** contra falta de rede na hora de finalizar/emitir.

**Síntese adversarial:** o `90 Leader` diz "linda e usável"; a capacidade diz "ainda sem prova de que a conta fecha, sem rede de segurança fiscal/offline". O gap #1 (G-01, teste de cálculo E2E) é barato perto do custo de um segundo incidente de valor.

## 9. Anti-padrões / pegadinhas Tier 0 (Sells)

- ⛔ **Mexer em cálculo de valor/estoque** (`final_total`, desconto, `num_uf`, `Create.tsx` totais, `PaymentRow`) sem **dupla confirmação** (2 caminhos com números) + **tabela antes→depois** + aprovação humana — regra-mestre Tier 0 ([proibicoes](../../proibicoes.md)).
- ⛔ **Frontend mandar float locale-ambíguo** (`204.99605`) pro parser pt-BR — arredondar 2 casas no submit; separador de milhar tem SEMPRE 3 dígitos.
- ⛔ **Teste derivado do código** (strpos/toContain no source, ou invariante extraída do que o método faz hoje) — tautológico, trava o desvio (proibicoes §5). Teste ancora em contrato (SPEC/ADR/caso), não na implementação.
- ⛔ **Smoke em `business_id=4`** (ROTA LIVRE prod, 99% volume) — usar biz=1 ou biz=99 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)).
- ⛔ **UPDATE direto em `current_stage_id`** — `GuardsFsmTransitions` bloqueia; usar `ExecuteStageActionService` ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)).
- ⛔ **Alterar `format_date`** de biz=4 — shift +3h preservado intencionalmente ([ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md)).
- ⛔ **`Inertia::render` com prop cara sem `defer`** — ver skill `inertia-defer-default`.

## 10. Decisão / Nota / Recomendação

### Nota de capacidade
**60/100** — entre Shopify-p/-BR (~48, desqualificado por fiscal) e Bling (~66) / Omie (~75). Honesto: o Sells é **melhor que o mercado nos eixos modernos** (autosave, Tier 0, densidade, integração vertical) e **atrás nos eixos operacionais-duros** (offline, contingência fiscal, Pix/TEF automático) — exatamente o oposto do que a nota de design 90 sugere.

### Causa principal do gap (1 frase)
**A tela é estado-da-arte em UX, mas a capacidade carece de (a) prova de correção de cálculo, (b) resiliência offline e (c) automação fiscal/pagamento no ponto de venda — os três pilares que os líderes de PDV BR já resolveram.**

### Top 3 P0 pra fechar (executável)
1. **G-01 — Teste E2E de cálculo** (US-SELL-040): a rede de segurança mais barata contra um 2º incidente de valor. Esforço M, ROI P0. Comece por aqui.
2. **G-03 — NFC-e no checkout + contingência automática**: obrigação fiscal + resiliência. Esforço L.
3. **G-02 — Offline-first (fila IndexedDB)**: casa com o modo de falha real da Larissa (internet instável SC). Esforço L; medir frequência de queda antes (ADR 0105).

### Referências
- [CAPTERRA-DESIGN-FICHA.md](CAPTERRA-DESIGN-FICHA.md) (UX, nota 68) · [BRIEFING.md](BRIEFING.md) · [SPEC.md](SPEC.md) (US-SELL-001..009/040) · [RUNBOOK-create.md](RUNBOOK-create.md)
- Screen-grade board: [SCREEN-GRADE-BOARD-2026-05-30.md](../../governance/scorecards/SCREEN-GRADE-BOARD-2026-05-30.md) (Sells/Index 90, Create 88)
- Session log: [2026-07-02-capterra-sells.md](../../sessions/2026-07-02-capterra-sells.md)
- Plano da onda: [1.1-adversario-capterra.md](../_Governanca/programa-ondas/onda-1-sells/1.1-adversario-capterra.md)

---

**Próxima revisão:** 2026-10-02 (trimestre) ou quando G-01 (teste de cálculo) fechar.
**Onda:** 1.1 (adversário concorrente Sells — programa de ondas).
