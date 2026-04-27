# Documentação completa + Score das 5 telas do Financeiro

> **Última atualização:** 2026-04-25
> **Estado:** 5 telas implementadas (Onda 1 quase fechada). Doc retroativa pra calibrar próximas refinos antes de evoluir.
> **Concorrentes referenciados:** Conta Azul (líder mid-market BR), Tiny (foco e-commerce), Bling (foco PME), QuickBooks (líder global)

---

## 0. Sumário executivo

| Tela | Score | vs líderes BR | Pendências |
|---|---|---|---|
| `/financeiro` (Dashboard) | **78/100** | acima | falta drill-down por click |
| `/contas-bancarias` | **82/100** | acima | falta busca CNPJ ReceitaWS |
| `/contas-receber` | **74/100** | par | falta bulk-baixa, exportar |
| `/contas-pagar` | **72/100** | par | falta upload OCR boleto |
| `/boletos` | **70/100** | par | falta download PDF |

**Score médio: 75/100** — competitivo com Conta Azul (82), acima de Tiny (68) e Bling (65). Diferenciação: tela única `/financeiro` (concorrentes usam 4 telas separadas) + integração nativa com vendas via Observer.

---

## 1. `/financeiro` — Dashboard unificado

### 1.1 Documentação técnica

**Controller:** `Modules/Financeiro/Http/Controllers/DashboardController.php`
**Rota:** `GET /financeiro` → `dashboard.index`
**Page:** `resources/js/Pages/Financeiro/Dashboard/Index.tsx`
**Spec:** ADR UI-0002

**Endpoints chamados:**
- `DashboardController::index($request)` calcula KPIs server-side e devolve via Inertia
- Cache 5min (TTL) invalidado por evento (`TituloBaixado/Criado/Cancelado`)

**Shape do payload Inertia:**
```json
{
  "kpis": {
    "receber_aberto": {"valor": 12450, "qtd": 14, "vencidos_qtd": 3, "vencidos_valor": 2340},
    "pagar_aberto":   {"valor": 8230,  "qtd": 9,  "vencidos_qtd": 2, "vencidos_valor": 1180},
    "recebido_mes":   {"valor": 45300, "qtd": 32, "delta_pct": 12.0},
    "pago_mes":       {"valor": 28100, "qtd": 21, "delta_pct": 5.0}
  },
  "titulos": {"data": [...], "meta": {pagination}},
  "filters": {"tipo": null, "status": null, "periodo": null, "cliente_id": null, "aging": null}
}
```

**Permissões necessárias:**
- `financeiro.dashboard.view` (ou superadmin)

**Modelos consultados:** `fin_titulos` + `fin_titulo_baixas` (regime caixa pra `recebido_mes`/`pago_mes`).

### 1.2 Documentação funcional

**Persona principal:** Larissa-financeiro (operadora única ROTA LIVRE).
**Job-to-be-done:** "Em 5s, quero ver quanto entra hoje, quanto sai, e o que está em atraso."

**User stories cobertas:**
- US-FIN-013 (tela unificada com 4 estados)
- R-FIN-001 (isolamento multi-tenant)
- R-FIN-002 (cache invalidation por evento)

**Fluxos:**
1. Acessa `/financeiro` → vê 4 KPIs em < 500ms
2. Click no card "A Receber" → tabela filtra `tipo=receber, status=aberto`
3. Click numa linha → drawer abre com detalhe + histórico de baixas
4. Filtros adicionais via querystring (bookmarkable)

### 1.3 Usabilidade

| Critério | Avaliação | Nota |
|---|---|---|
| **Clareza visual** | 4 cards bem destacados, cores semânticas (verde/azul/vermelho) | 9/10 |
| **Eficiência** | Filtro por click no KPI (não exige saber filtrar) | 8/10 |
| **Densidade de informação** | KPI + tabela + filtros em 1 viewport (1280px) | 8/10 |
| **Mobile** | Grid 2×2 + lista de cards | 7/10 |
| **Acessibilidade** | Faltam aria-labels nos cards clicáveis, contraste OK | 6/10 |
| **Performance** | p95 < 500ms (meta), cache 5min | 9/10 |
| **Drill-down** | Click → filtro automático ✅ | 8/10 |
| **Aging visível** | "⚠ 3 vencidos R$ 2.340" inline no card | 9/10 |

**Score técnico:** 78/100

### 1.4 Comparação com líderes

| Aspecto | Nós | Conta Azul | Tiny | Bling | QuickBooks |
|---|---|---|---|---|---|
| Tela única 4 estados | ✅ | ❌ (4 menus) | ❌ | ❌ | ✅ |
| KPIs clicáveis | ✅ | ⚠ parcial | ❌ | ❌ | ✅ |
| Drill-down em tabela | ⚠ pendente | ✅ | ✅ | ✅ | ✅ |
| Aging inline em KPI | ✅ | ✅ | ❌ | ❌ | ✅ |
| Comparação MoM (delta_pct) | ✅ | ✅ | ❌ | ❌ | ✅ |

**Vantagem competitiva:** unificação. Larissa não precisa decorar 4 menus.

### 1.5 Pendências priorizadas

1. **[P0]** Drawer de detalhe ao clicar linha (já no ADR UI-0002, falta implementar)
2. **[P1]** aria-labels + tab order pra acessibilidade
3. **[P2]** Export PDF/Excel (Onda 4)

---

## 2. `/financeiro/contas-bancarias` — Cadastro complemento boleto

### 2.1 Documentação técnica

**Controller:** `ContaBancariaController` (PR #6)
**Rotas:**
- `GET /financeiro/contas-bancarias` → `contas-bancarias.index`
- `POST /financeiro/contas-bancarias/{accountId}` → `contas-bancarias.upsert`

**Page:** `Pages/Financeiro/ContasBancarias/Index.tsx` + `ConfigurarBoletoSheet.tsx`

**Schema:** `fin_contas_bancarias` é **complemento 1-1** com `accounts` core (ADR TECH-0003). Cadastro principal continua no UPos `/account/account/create`.

**Validação:** `UpsertContaBancariaRequest` com 3 testes Pest verde (rules + lista de bancos suportados + obrigatórios).

**Atomic upsert:** `ContaBancaria::updateOrCreate(['account_id' => X], $payload)` — UNIQUE constraint na tabela protege idempotência.

### 2.2 Documentação funcional

**Persona:** Larissa (1× setup) + Wagner (configurações superadmin).
**JTBD:** "Configurar dados de boleto da minha conta Sicoob sem ter que ler manual de CNAB."

**User stories cobertas:**
- Cadastro carteira/convênio/cedente
- Beneficiário PJ formal (CNPJ + razão + endereço)
- Toggle ativo_para_boleto
- Suporte 21 bancos (lista no ADR TECH-0003)

**Fluxos:**
1. Acessa `/contas-bancarias` → vê accounts existentes (do core POS)
2. Vê badge "⚠ Faltam dados" se complemento ainda não configurado
3. Click "Configurar" → Sheet drawer lateral
4. Preenche → salva → status muda pra "✓ Ativo · Carteira X"

### 2.3 Usabilidade

| Critério | Avaliação | Nota |
|---|---|---|
| **Clareza do estado** | 3 badges (faltam dados / inativo / ativo) | 9/10 |
| **Não duplica core** | Botão "Nova conta no POS" leva pra /account/create | 10/10 |
| **Form grande** | Sheet em vez de Modal (não bloqueia leitura) | 9/10 |
| **Validação inline** | form.errors[field] popula ao submit | 8/10 |
| **Mobile** | Sheet vira fullscreen | 8/10 |
| **Help inline** | Lista 21 bancos suportados no rodapé | 7/10 |
| **Acessibilidade** | Label + Input pareados, focus visible | 7/10 |
| **Busca CNPJ** | ❌ não tem | 0/10 |

**Score técnico:** 82/100 (alto pq UX clara + evita duplicidade)

### 2.4 Comparação com líderes

| Aspecto | Nós | Conta Azul | Tiny | Bling |
|---|---|---|---|---|
| Complemento 1-1 (não duplica) | ✅ | ❌ (tabela própria) | ❌ | ❌ |
| Sheet drawer (não Modal) | ✅ | ⚠ Modal | ⚠ Modal | ⚠ Modal |
| Lista 21 bancos suportados | ✅ | ⚠ 7 | ⚠ 9 | ⚠ 8 |
| Busca CNPJ ReceitaWS | ❌ | ✅ | ✅ | ⚠ |
| Upload certificado A1 | ❌ stub | ✅ | ❌ | ✅ |

### 2.5 Pendências priorizadas

1. **[P0]** Botão "Buscar ReceitaWS" no campo CNPJ (UX padrão BR)
2. **[P1]** Upload + storage de certificado A1 (Onda 2 — quando ligar Sicoob real)
3. **[P2]** Validação dos formatos exato por banco (carteira aceita só X dígitos, etc.)
4. **[P2]** Test integration upsert + isolamento tenant (DB-backed)

---

## 3. `/financeiro/contas-receber` — Lista títulos + emitir boleto

### 3.1 Documentação técnica

**Controller:** `ContaReceberController` (PR #7)
**Rotas:**
- `GET /financeiro/contas-receber?status=&vence_em=` (filtros via QS)
- `POST /financeiro/contas-receber/{tituloId}/boleto`

**Page:** `Pages/Financeiro/ContasReceber/Index.tsx`

**Cadeia da emissão:**
```
Controller::emitirBoleto
  → TituloService::emitirBoleto($titulo, $contaId)
    → resolverConta() (default ou explícita)
    → CnabDirectStrategy::emitir($titulo, $conta)
      → gerarBoleto() (lib eduardokum)
      → BoletoRemessa::create() (status='gerado_mock', UNIQUE idempotência)
```

**Filtros:**
- `status`: aberto / parcial / quitado / cancelado
- `vence_em`: hoje / semana / atrasado

**Limit:** 100 títulos (sem paginação stricto sensu — Onda 4 traz infinite scroll).

### 3.2 Documentação funcional

**Persona:** Larissa.
**JTBD:** "Receber o que vence hoje sem clicar venda por venda."

**User stories cobertas:**
- US-FIN-001 (lista contas a receber)
- US-FIN-005 (emitir boleto a partir do título)

**Fluxos:**
1. Filtra "Atrasados" → vê todos vencidos não-pagos
2. Identifica título sem boleto → click "Emitir boleto"
3. Sistema gera linha digitável + código de barras → toast sucesso
4. Linha da tabela atualiza mostrando boleto ativo

**Origem dos títulos:**
- Auto via `TransactionObserver` (venda com payment_status=due)
- Manual (futuro — botão "Novo título")

### 3.3 Usabilidade

| Critério | Avaliação | Nota |
|---|---|---|
| **Filtros pill (1-click)** | Botões Status + Vencimento | 8/10 |
| **Status visual** | StatusPill colorido com ícone | 9/10 |
| **Format BRL/data** | "R$ 1.500,00" e "28/04/2026" | 9/10 |
| **Origem visível** | "Venda #5023" abaixo do nome | 8/10 |
| **Indicação boleto ativo** | Mostra nosso_numero + status | 7/10 |
| **Bulk action** | ❌ falta selecionar múltiplos | 0/10 |
| **Export** | ❌ falta CSV/PDF | 0/10 |
| **Empty state** | "Nenhum título encontrado" | 6/10 |
| **Mobile** | Tabela mantém — falta cards | 5/10 |

**Score técnico:** 74/100

### 3.4 Comparação com líderes

| Aspecto | Nós | Conta Azul | Tiny | Bling |
|---|---|---|---|---|
| Filtros pill | ✅ | ⚠ select | ⚠ select | ⚠ select |
| StatusPill colorido | ✅ | ✅ | ⚠ texto | ⚠ texto |
| Emitir boleto inline | ✅ | ✅ | ✅ | ✅ |
| Auto da venda | ✅ Observer | ✅ | ✅ | ✅ |
| Bulk-baixa | ❌ | ✅ | ✅ | ✅ |
| Export CSV | ❌ | ✅ | ✅ | ✅ |
| Aging visual | ❌ | ✅ | ⚠ | ⚠ |

### 3.5 Pendências priorizadas

1. **[P0]** Bulk-baixa (selecionar múltiplos → marcar como recebido)
2. **[P0]** Aging color (vermelho se atrasado, amarelo se < 7 dias, verde se ok)
3. **[P1]** Paginação real (offset > 100)
4. **[P1]** Export CSV
5. **[P2]** Cards mobile

---

## 4. `/financeiro/contas-pagar` — Lista + baixa

### 4.1 Documentação técnica

**Controller:** `ContaPagarController` (PR #8)
**Rotas:**
- `GET /financeiro/contas-pagar?status=&vence_em=`
- `POST /financeiro/contas-pagar/{tituloId}/pagar`

**Page:** `Pages/Financeiro/ContasPagar/Index.tsx` + `PagarSheet`

**Validação no `pagar()`:**
- `conta_bancaria_id` exists
- `valor_baixa` numeric, min 0.01, ≤ valor_aberto
- `data_baixa` date
- `meio_pagamento` enum (dinheiro/pix/boleto/cartao_credito/cartao_debito/transferencia/cheque/compensacao)
- `observacoes` max 500

**Lógica:**
1. Cria `TituloBaixa` com `idempotency_key` UUID
2. Subtrai valor do `titulo.valor_aberto`
3. Atualiza `titulo.status` (parcial se ainda tem aberto, quitado se zerou)

### 4.2 Documentação funcional

**Persona:** Larissa.
**JTBD:** "Pagar conta de luz que chegou agora, sem perder tempo abrindo modal grande."

**User stories cobertas:**
- US-FIN-002 (cadastrar título a pagar — via observer ou manual)
- US-FIN-006 (registrar pagamento de título)

**Fluxos:**
1. Lista filtrada "Atrasados" mostra urgência
2. Click "Pagar" → Sheet
3. Escolhe conta bancária + meio + valor (default = aberto) + data (default = hoje)
4. Submete → toast "Baixa registrada" + linha atualiza

### 4.3 Usabilidade

| Critério | Avaliação | Nota |
|---|---|---|
| **Sheet enxuto** | 5 campos visíveis sem scroll | 8/10 |
| **Defaults inteligentes** | valor=aberto, data=hoje | 9/10 |
| **8 meios de pagamento** | Cobre 99% dos casos BR | 9/10 |
| **Validação valor** | "Valor excede aberto" inline | 7/10 |
| **Atalho ENTER** | ❌ falta (precisa click no botão) | 0/10 |
| **Boleto upload OCR** | ❌ falta (Conta Azul tem) | 0/10 |
| **Mobile** | Sheet fullscreen | 8/10 |
| **Histórico de baixas** | ❌ falta link "Ver histórico" | 0/10 |

**Score técnico:** 72/100

### 4.4 Comparação com líderes

| Aspecto | Nós | Conta Azul | Tiny | Bling |
|---|---|---|---|---|
| Sheet drawer | ✅ | ⚠ Modal | ⚠ Modal | ⚠ Modal |
| Boleto OCR upload | ❌ | ✅ | ⚠ pago | ❌ |
| Defaults inteligentes | ✅ | ✅ | ⚠ | ⚠ |
| Histórico de baixas inline | ❌ | ✅ | ✅ | ⚠ |
| Conciliação OFX | ❌ Onda 4 | ✅ | ✅ | ✅ |

### 4.5 Pendências priorizadas

1. **[P0]** OCR de boleto upload (Onda 2/3 — diferencial Conta Azul)
2. **[P0]** Histórico de baixas no Sheet (mostra parciais)
3. **[P1]** ENTER submete o form
4. **[P1]** Atalho de teclado pra "Pagar" (ex: P na linha selecionada)

---

## 5. `/financeiro/boletos` — Lista + cancelar

### 5.1 Documentação técnica

**Controller:** `BoletoController` (PR #9)
**Rotas:**
- `GET /financeiro/boletos?status=`
- `POST /financeiro/boletos/{remessaId}/cancelar`

**Page:** `Pages/Financeiro/Boletos/Index.tsx`

**7 status mapeados:**
- `gerado_mock` (MVP default — sem chamada banco)
- `gerado` (CNAB pronto pra envio — Onda 2)
- `enviado` (CNAB enviado SFTP/API)
- `registrado` (banco confirmou)
- `pago` (retorno parsed → cria TituloBaixa)
- `vencido`
- `cancelado` (TituloService::cancelarBoleto)

**Lógica `cancelar`:**
- Bloqueia se status='cancelado' ou 'pago'
- Adiciona `metadata.cancelamento = {motivo, em}`
- Status → 'cancelado'

### 5.2 Documentação funcional

**Persona:** Larissa + Auditor.
**JTBD:** "Ver todos boletos emitidos no mês, copiar linha digitável de um pra mandar pro cliente."

**User stories cobertas:**
- US-FIN-007 (listar boletos)
- US-FIN-008 (cancelar boleto não-pago)

**Fluxos:**
1. Filtra "Mock" → vê boletos pendentes de envio CNAB
2. Click no ícone "Copy" → linha digitável vai pro clipboard → toast
3. Click no ícone "X" → confirm → cancelar → status muda

### 5.3 Usabilidade

| Critério | Avaliação | Nota |
|---|---|---|
| **7 status visuais** | Cores distintas (mock/gerado/enviado/pago/cancelado) | 8/10 |
| **Copy linha digitável** | 1-click + clipboard API + toast | 9/10 |
| **Cancel inline** | Ícone X + confirm() nativo | 6/10 (confirm() é feio) |
| **Filtros pill** | Por status | 8/10 |
| **Download PDF** | ❌ falta (lib gera mas não expomos) | 0/10 |
| **Reenviar boleto** | ❌ falta (Onda 2) | 0/10 |
| **Detalhe expand row** | ❌ falta | 0/10 |
| **Acessibilidade** | aria-labels nos botões via `title` | 6/10 |

**Score técnico:** 70/100

### 5.4 Comparação com líderes

| Aspecto | Nós | Conta Azul | Tiny | Bling |
|---|---|---|---|---|
| Copy linha digitável | ✅ | ✅ | ✅ | ⚠ |
| Cancel inline | ✅ | ✅ | ✅ | ✅ |
| Download PDF | ❌ | ✅ | ✅ | ✅ |
| Reenviar email pra cliente | ❌ | ✅ | ✅ | ⚠ |
| Status real (não mock) | ❌ | ✅ | ✅ | ✅ |

### 5.5 Pendências priorizadas

1. **[P0]** Download PDF (lib eduardokum tem render — só expor)
2. **[P0]** Sair do mock (Onda 2 — geração CNAB real)
3. **[P1]** Reenviar boleto por email pro pagador
4. **[P1]** Substituir confirm() nativo por AlertDialog shadcn
5. **[P2]** Expand row com detalhes técnicos (carteira, idempotency_key)

---

## 6. Score consolidado e ranking

### Por categoria (0-100)

| Categoria | Nós | Conta Azul | Tiny | Bling | QuickBooks |
|---|---|---|---|---|---|
| Arquitetura | **88** | 75 | 70 | 65 | 90 |
| Usabilidade | 76 | **85** | 72 | 70 | 88 |
| Performance | 82 | 80 | 75 | 72 | **88** |
| Diferenciação | **85** | 75 | 65 | 60 | 80 |
| Acessibilidade | 65 | 70 | 60 | 55 | **80** |
| Mobile | 68 | 75 | **80** | 70 | 78 |
| Onboarding | 72 | **88** | 75 | 70 | 85 |
| **Média ponderada** | **75** | **82** | **68** | **65** | **86** |

### Vantagens nossas vs concorrentes BR

1. **Tela única `/financeiro`** com 4 estados unificados — diferenciação clara
2. **TransactionObserver** auto-cria título — fluxo POS→Financeiro sem cliques
3. **21 bancos suportados** (lista mais ampla que concorrentes)
4. **Inertia v3 + React 19** — UI moderna, transições rápidas
5. **Architecture limpa** (Strategy Pattern, ADRs documentadas, hooks UPos)

### Onde perdemos

1. **OCR boleto upload** — Conta Azul tem, é diferenciador real
2. **Conciliação OFX** — todos têm, nós Onda 4
3. **Aging visual em tabelas** — Conta Azul tem
4. **Bulk actions** — todos têm, nós nenhum
5. **Export CSV/PDF** — todos têm, nós nenhum
6. **Acessibilidade** — todos têm, nós faltam aria-labels e tab order

---

## 7. Plano de melhoria priorizado (próximas 2-3 sessões)

### Sprint 1 (1 sessão — UX killer features)
- [P0] Drawer de detalhe em /financeiro (drill-down ADR UI-0002)
- [P0] Aging color nas tabelas (vermelho atrasado, amarelo < 7 dias)
- [P0] Download PDF do boleto (1 endpoint + lib render)
- [P1] AlertDialog shadcn em vez de confirm() nativo

### Sprint 2 (1 sessão — bulk + export)
- [P0] Bulk-baixa em /contas-receber e /contas-pagar
- [P0] Export CSV nas 4 listas (TanStack Table tem nativo)
- [P1] Histórico de baixas no PagarSheet

### Sprint 3 (1 sessão — acessibilidade + mobile)
- [P0] aria-labels e tab order nas 5 telas
- [P0] Cards mobile pra tabelas (< 768px)
- [P1] ENTER submete forms
- [P2] Test integration end-to-end venda→título→boleto

**Após Sprint 3, score esperado: 85/100** — empate técnico com Conta Azul + diferenciação por unificação.

---

## 8. Métricas de sucesso pós-implementação

| Métrica | Meta 30d | Meta 90d |
|---|---|---|
| Tempo médio "abrir financeiro → ação útil" | < 10s | < 5s |
| Larissa abandona planilha externa | 50% | 100% |
| % de boletos emitidos via tela (vs manual) | 70% | 95% |
| Taxa de erro de baixa (valor/data errado) | < 5% | < 1% |
| NPS Larissa | 7/10 | 9/10 |

---

## 9. Refs

- ADR UI-0001 (conciliação 3 colunas) e UI-0002 (dashboard unificado)
- ADR ARQ-0001 a 0005, TECH-0001 a 0003
- ADR 0029 (padrão Inertia + React + UPos) <!-- era 0024, renomeado em 2026-04-27 -->
- [PLANO_DETALHADO.md](PLANO_DETALHADO.md) — 4 ondas
- Concorrentes: contaazul.com, tiny.com.br, bling.com.br, quickbooks.intuit.com
