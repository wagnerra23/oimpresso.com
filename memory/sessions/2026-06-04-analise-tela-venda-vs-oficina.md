---
title: "Análise tela de venda (add + consulta) vs estado-da-arte oficina — o que falta pra operar numa oficina"
topic: "Análise tela de venda vs estado-da-arte oficina — gaps pra operar numa oficina (P0-2 estoque)"
date: "2026-06-04"
type: session-log
status: ativo
authors: [W, C]
scope_modulos: [Sells, OficinaAuto, NfeBrasil, Compras, Whatsapp]
cliente_ancora: Martinho Caçambas LTDA (biz=164 · mecânica pesada CNAE 4520)
related_adrs:
  - "0137-modules-oficinaauto-qualificada"
  - "0143-fsm-pipeline-live-prod-marco-2026-05-12"
  - "0171-oficinaauto-ativacao-piloto-martinho-faseada"
  - "0179-cliente-drawer-760px-substitui-show-fullpage"
  - "0192-auto-faturar-os-venda-jobsheet-observer"
  - "0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada"
  - "0110-cockpit-pattern-v2-canon-list-detail"
  - "0093-multi-tenant-isolation-tier-0"
owner: [W]
sem_aprovacao_humana: tasks_propostas_nao_criadas_no_mcp
metodo: leitura código real + typecheck + WebSearch concorrentes BR 2026
---

# Análise — tela de venda × oficina (2026-06-04)

## Resumo executivo (4 bullets)

1. **A "tela de adicionar venda" (`Sells/Create.tsx`, 1888 LOC) é um PDV de produto genérico — zero conceito de oficina.** Não tem veículo, placa, OS, mão-de-obra, mecânico nem km. Isso é correto por arquitetura: numa oficina o documento-mãe é a **Ordem de Serviço (OS)**, não a venda. A venda é o *fim* do fluxo (faturamento), gerada automaticamente a partir da OS (ADR 0192).
2. **A "tela de consultar venda" (`Sells/Index.tsx`, 1805 LOC) JÁ é oficina-aware** — coluna Origem (Balcão/Oficina/Online), árvore "Por origem", e link cross-módulo pro kanban da OS. É a parte mais madura do conjunto.
3. **O fluxo real da oficina vive em `Modules/OficinaAuto`** (OS Create/Show/Board/ProducaoOficina + Vehicles + DVI + ApprovalGate + FiscalSplit). Ele cobre a *espinha* do ciclo, mas **3 peças essenciais estão quebradas ou ausentes**: (a) item da OS é **texto livre sem catálogo nem baixa de estoque**; (b) `final_total=0` na OS de manutenção → venda faturada sai R$ [redacted Tier 0]; (c) `/fiscal/nfse` 500 em prod → NFS-e (mão de obra) inutilizada.
4. **Integridade técnica das telas: OK.** `npm run typecheck` → 17 erros TS no projeto, **0 em `Pages/Sells` ou `Pages/OficinaAuto`** (erros isolados em Whatsapp + routes gerado + ssr). As telas de venda/oficina compilam limpo.

---

## 1. Onde a "venda" encaixa numa oficina (arquitetura)

```
Cliente chega
   │
   ▼
[OS Create]  ◄── porta de entrada da oficina (NÃO a venda)
 veículo + km + combustível + avarias + defeito relatado
   │
   ▼
[Vistoria DVI]  → achado vira linha de orçamento
   │
   ▼
[Orçamento]  peças + mão-de-obra + serviço terceiro
   │
   ▼
[Aprovação do cliente]  (gate trava execução)
   │
   ▼
[Execução]  mecânico, apontamento de tempo, checklist
   │
   ▼
[Conclusão / QA]
   │
   ▼
[Faturamento] ──► VENDA (Sells)  +  NF-e peças / NFS-e serviço (split)
   │
   ▼
[Entrega + garantia + histórico do veículo]
```

A `Sells/Create` só seria usada para **balcão puro** (vender uma peça sem OS). Para o serviço de oficina, a venda **não deve** ser preenchida à mão — ela é derivada da OS. Logo, "fazer a tela de venda funcionar numa oficina" = **fechar a ponte OS→Venda** + completar o que falta na OS, não inchar a `Sells/Create` com campos de veículo.

---

## 2. Teste de integridade executado

| Verificação | Comando | Resultado |
|---|---|---|
| Compilação TS das telas | `npm run typecheck` | ✅ 0 erro em Sells/OficinaAuto (17 erros no projeto, todos fora do escopo: Whatsapp, routes gerado, ssr.tsx) |
| Pest backend (estrutural) | `php artisan test` | ⚠️ não rodável local (sem PHP no host) — 24 testes OficinaAuto + ~49 Sells/Create existem; rodar no CT 100 antes de smoke prod |
| Drift charter↔código | leitura | ⚠️ `ServiceOrders/Show.charter.md` lista `ServiceOrderFsmActionPanel` + `Timeline` como Goals, mas a **Show.tsx não os renderiza** — FSM + timeline vivem no drawer do Kanban (ProducaoOficina) e no ServiceOrderSheet. Coerente com ADR 0179 (drawer 760 substitui Show), mas o charter está desatualizado. |

---

## 3. Grade comparativa — oficina estado-da-arte 2026 vs oimpresso

Dimensões essenciais levantadas dos líderes BR (Oficina Integrada, WorkMotor, WSoft, OnMotor, AutoPro, Soften, Tecnomotor/Mecânico).

| # | Capacidade essencial (best-in-class) | oimpresso hoje | Onde vive | Nota |
|---|---|---|---|---|
| 1 | Cadastro veículo + cliente (placa, chassi, km) | ✅ Completo | `OficinaAuto/Vehicles` | 🟢 |
| 2 | Check-in de entrada (km, combustível, avarias) | 🟡 Sem **fotos** de entrada | `EntryCheckinFields` | 🟡 |
| 3 | Vistoria digital (DVI) com foto + laudo | 🟡 DVI existe (`DviBudgetSection`/`DviPhotoGrid`) — laudo/checklist por tipo de veículo ausente | OS Show + Producao | 🟡 |
| 4 | **Catálogo de peças no orçamento + baixa de estoque** | ❌ Item da OS é **texto livre** (descrição + valor manual). Sem product picker, sem reserva/baixa de estoque | `ServiceOrderItemFormSheet` | 🔴 |
| 5 | **Tabela tempária (tempo padrão de mão-de-obra)** | ❌ Ausente | — | 🔴 |
| 6 | Orçamento → estado "enviado" + versões + aprovação registrada | 🟡 `ApprovalGateCard` (gate) ok; UC-04 (estado "enviado" + versões) sem cobertura | OS Show | 🟡 |
| 7 | **Aprovação do cliente via WhatsApp/PIN** | 🟡 `AprovacaoPublica` charter `draft` (não live); add-on WhatsApp R$ [redacted Tier 0] bloqueado | OficinaAuto | 🟡 |
| 8 | Execução: atribuir mecânico, apontamento de tempo, checklist roteiro, pausa c/ motivo (UC-07) | ❌ Ausente | — | 🔴 |
| 9 | Controle de qualidade pré-entrega (UC-08) | ❌ Ausente | — | 🟡 |
| 10 | **Faturamento split: venda + NF-e peças / NFS-e serviço** | 🔴 `FiscalSplitCard` prepara, mas `/fiscal/nfse` **500 em prod** + `final_total=0` | OS Show + NfeBrasil | 🔴 |
| 11 | Histórico do veículo (passagens), garantia, retorno (UC-10) | ❌ Ausente | — | 🟡 |
| 12 | Agendamento / agenda de boxes por técnico | ❌ Ausente | — | 🟡 |
| 13 | CRM + lembrete de revisão automático | 🟡 base Whatsapp existe, sem trigger de revisão | Whatsapp | 🟡 |
| 14 | Listagem/consulta de vendas com pipeline + origem | ✅ Forte (`Sells/Index`) | Sells | 🟢 |
| 15 | Pipeline FSM por estágio (Recepção→Pronto) | ✅ Vivo prod (ADR 0143) | Producao kanban | 🟢 |

**Leitura:** a *moldura* (cadastro, FSM, consulta, fiscal-prep) está madura (🟢). O que separa o oimpresso de um sistema de oficina "que funciona de verdade" são **4 buracos vermelhos**: catálogo+estoque no item (#4), tempária (#5), faturamento real (#10) e execução/apontamento (#8).

---

## 4. Grade de tarefas priorizada (mais urgente → menos)

> Esforço em h-humano reais (IA-pair), não 10x recalibrado. Tasks **propostas** — não criadas no MCP (publication-policy: Wagner aprova batch).

### 🔴 P0 — Sem isto a oficina NÃO fatura (loop de receita quebrado)

| # | Tarefa | Onde | Esforço | Por quê é P0 |
|---|---|---|---|---|
| P0-1 | **Corrigir `/fiscal/nfse` 500 em prod** (schema race migration duplicada) | `Modules/Fiscal/.../NfseCockpitController.php` | 4h | Oficina fatura serviço via NFS-e; tela quebrada = não emite nota de mão-de-obra |
| P0-2 | **Catálogo de peças no item da OS + baixa de estoque** — trocar texto livre por product picker (autocomplete) com preço/estoque; baixa ao concluir | `ServiceOrderItemFormSheet` + Controller + Observer | 10h | É a base do orçamento e do estoque. Hoje preço é digitado na mão e estoque não baixa |
| P0-3 | **Recalcular `final_total` da OS de manutenção** (`peça×qty + hora×horas`) | `ServiceOrderObserver::computeFinalTotal` | 6h | Venda gerada pela OS sai R$ [redacted Tier 0] → Wagner edita manual cada OS (depende de P0-2) |
| P0-4 | **Recovery de dados prod biz=164** (sell_lines/produtos/estoque/compras da maratona 13-17/05) | `scripts/legacy-migration/import-*.py` | 8-12h | Pré-req de P0-2/P0-3: prod tem 0 sell_lines e só 1.838 produtos legacy. Sem dado real, catálogo e cálculo não têm o que somar |

### 🟡 P1 — Essencial pra operar bem e competir

| # | Tarefa | Onde | Esforço | Por quê |
|---|---|---|---|---|
| P1-1 | **Tabela tempária / tempo de mão-de-obra** (cadastro de serviço com horas padrão → orçamento em minutos) | OficinaAuto (novo) | 8h | Diferencial padrão de mercado; padroniza preço da hora |
| P1-2 | **Apontamento de tempo + atribuir mecânico + checklist roteiro** (UC-07) | OS Show/Producao | 10h | Sem isso não há controle de execução nem produtividade |
| P1-3 | **`AprovacaoPublica` → `live` + aprovação via WhatsApp** (add-on R$ [redacted Tier 0]) | `AprovacaoPublica.tsx` + FSM wire-up | 7h | Revenue stream incremental; aprovação digital é esperada em 2026 |
| P1-4 | **Fotos no check-in de entrada + laudo DVI por tipo de veículo** | `EntryCheckinFields` + DVI | 5h | Proteção jurídica (avarias) + padrão de vistoria |
| P1-5 | **Histórico do veículo + garantia + retorno** (UC-10) | Vehicles/Show + OS | 6h | Fidelização + gatilho de retorno de garantia |
| P1-6 | **Sincronizar `Show.charter.md` com a realidade** (FSM/timeline vivem no drawer, não na Show) | charter + Show.tsx | 1h | Corrige drift; decidir se Show ganha o painel FSM ou vira read-only declarado |

### 🟢 P2 — Diferenciais (depois do core)

| # | Tarefa | Esforço | Por quê |
|---|---|---|---|
| P2-1 | **Agendamento / agenda de boxes por técnico** | 12h | Diferencial forte; nenhuma base hoje |
| P2-2 | **Controle de qualidade pré-entrega** (UC-08) | 4h | Reduz retrabalho |
| P2-3 | **Ciclo de peça (cotada→pedida→recebida) + reserva de estoque** (UC-06) | 8h | Importante p/ peças sob encomenda (mecânica pesada) |
| P2-4 | **CRM lembrete de revisão automático** | 5h | Recompra |
| P2-5 | **Estado "Orçamento enviado" + versões** (UC-04) | 4h | Rastreabilidade comercial |

### Totais
- **P0: ~28-32h** (destrava faturamento real da oficina)
- **P1: ~37h**
- **P2: ~33h**

---

## 5. Recomendação de sequência

1. **P0-4 → P0-2 → P0-3** (recovery dados → catálogo/estoque → cálculo) nessa ordem — são dependentes e juntos fazem a OS gerar uma venda com valor correto.
2. **P0-1** em paralelo (NFS-e) — independente.
3. Só então **P1** (tempária, apontamento, aprovação WhatsApp).
4. **P2** quando o cliente-âncora (Martinho) estiver operando o loop completo.

A `Sells/Create` **não precisa de mudança estrutural** pra oficina — o caminho certo é a OS. O único ajuste de venda recomendável: na `Sells/Show`/drawer, exibir veículo + placa + link da OS quando `source='oficina'` (já há a coluna Origem no Index; falta o detalhe no drawer).

---

## Refs
- [Levantamento Martinho-ready 2026-05-26](./2026-05-26-levantamento-martinho-ready.md) — fonte dos bugs P0 de dados/fiscal
- [ADR 0192 Auto-faturar OS→Venda](../decisions/0192-auto-faturar-os-venda-jobsheet-observer.md)
- [ADR 0194 OficinaAuto mecânica pesada](../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)
- [ADR 0179 Drawer 760 substitui Show fullpage](../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)
- Concorrentes BR 2026: Oficina Integrada, WorkMotor, WSoft, OnMotor, AutoPro, Soften, Tecnomotor/Mecânico
</content>
</invoke>
