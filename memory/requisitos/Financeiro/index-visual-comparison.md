---
id: requisitos-financeiro-index-visual-comparison
tela: /financeiro/conciliacao
component: resources/js/Pages/Financeiro/Conciliacao/Index.tsx
charter: resources/js/Pages/Financeiro/Conciliacao/Index.charter.md
status: approved
related_adrs: [0093, 0104, 0107, 0236]
data: 2026-05-31
---

# Comparativo visual — Financeiro · Conciliação (Fase 1 ADR 0236)

> ADR 0107 §F1.5 — gate visual obrigatório. Esta tela **não é** uma migração
> Blade→Inertia (já nasceu Inertia na Onda 19). O comparativo aqui documenta a
> evolução **dentro do Inertia**: antes a Conciliação só via o upload OFX; a
> Fase 1 da [ADR 0236](../../decisions/0236-extrato-conciliacao-modelo-unificado.md)
> passou a unir as duas origens de extrato (OFX + API do banco) na mesma tela.

## Resumo executivo

A tela ganhou a coluna **Origem** (chip Banco / OFX) e passou a listar/conciliar
também as linhas do extrato sincronizado via API (`fin_extrato_lancamentos`),
que antes ficavam invisíveis aqui. Zero migração de dado — leitura unificada +
colunas de workflow aditivas na tabela do extrato.

## Tabela comparativa — 8 dimensões

### 1. Layout

| Aspecto | Antes (Onda 19) | Fase 1 (ADR 0236) | Decisão |
|---|---|---|---|
| Header | `<PageHeader>` "Conciliação · OFX bancário" | **Mantido** | sem mudança |
| KPI strip | 4 KPIs (pendentes/sugeridos/conciliados/ignorados) | **Mantido** — agora somam as 2 origens | evolução |
| Tabela | 6 colunas (Data/Descrição/Valor/Tipo/Status/Ações) | **7 colunas** — adiciona **Origem** entre Data e Descrição | Fase 1 adiciona |

### 2. Conteúdo informacional

| Aspecto | Antes | Fase 1 | Decisão |
|---|---|---|---|
| Linhas listadas | só `fin_bank_statement_lines` (OFX upload) | OFX **+** `fin_extrato_lancamentos` (API), normalizadas | Fase 1 une |
| Coluna Origem | ❌ ausente | ✅ chip `Banco` (API) / `OFX` (upload) com tooltip | Fase 1 adiciona |
| KPIs | contam só OFX | contam as 2 origens (API status NULL = pendente) | Fase 1 evolui |

### 3. Ações disponíveis (CRUD)

| Ação | Antes | Fase 1 | Decisão |
|---|---|---|---|
| Upload OFX | Sim → `insert` linha a linha | Sim → `insertOrIgnore` idempotente (anti-race) | hardening |
| Confirmar match | Sim (só OFX) | Sim — resolve tabela por `origem` (OFX/API) | Fase 1 estende |
| Ignorar | Sim (só OFX) | Sim — idem por `origem` | Fase 1 estende |
| Migrar linha entre origens | ❌ | ❌ (Fase 2, atrás de flag) | fora de escopo |

### 4. Multi-tenant Tier 0

| Aspecto | Antes | Fase 1 | Decisão |
|---|---|---|---|
| Filter `business_id` | Sim em todas queries OFX | Sim — OFX **e** API, inclusive nos UPDATE de match/ignorar | ✅ ADR 0093 IRREVOGÁVEL |
| Pest cross-tenant | parcial | ✅ `match api respeita business id tier0` (2 businesses reais) | Fase 1 adiciona GUARD |

### 5. Permissões

| Permissão | Antes | Fase 1 | Decisão |
|---|---|---|---|
| Gate | `financeiro.conciliacao.manage` | **Mesma** | sem mudança |

### 6. Cores / tokens (R1 ui:lint)

| Aspecto | Antes | Fase 1 | Decisão |
|---|---|---|---|
| Chip origem | n/a | tokens semânticos (`bg-accent` / `bg-transparent`) — NÃO cor crua | respeita R1 |

### 7. Performance

| Aspecto | Antes | Fase 1 | Decisão |
|---|---|---|---|
| Queries | 1 tabela (limit 200) | 2 tabelas (limit 200 cada) + normalização PHP | custo desprezível (volume baixo) |

### 8. Acessibilidade

| Aspecto | Antes | Fase 1 | Decisão |
|---|---|---|---|
| Chip origem | n/a | `title` (tooltip) + contraste via token + texto "Banco"/"OFX" (não só cor) | OK — não depende só de cor |

## Evidência

Validado em `staging.oimpresso.com/financeiro/conciliacao` (2026-05-31): tela
renderiza as 2 origens lado a lado (chip Banco/OFX), sem erro 500, com a
migration Fase 1 aplicada. Screenshot na sessão de origem.

---

## Onda 7 · paridade medida no runtime — 2026-09-08 [CC]

Mesma sonda estrutural · **mesmo tema** (`dark`) · **mesma viewport** (`2560`) · ambos estabilizados.
Âncora `financeiro-telas-extras.jsx` (TelaConciliacao) provada **SYNC** antes de comparar.
Prod `/financeiro/conciliacao` × design rota `fin-concil`.

| Elemento | PROD | DESIGN |
|---|---|---|
| h1 | "Conciliação · OFX bancário" | "Financeiro · Conciliação" |
| KPIs | **4** `.fin-stat` | **0** (2 `.fin-card`) |
| Tabela | **1** — `Data · Origem · Descrição · Valor · Tipo · Status · Ações` (7 col) | **0** |
| `<svg>` | 72 | 65 |

### Veredito: divergência **ESPERADA e já declarada** — não é trabalho

O próprio charter da tela declara, no campo `related_prototype_nota`:
*"(TelaConciliacao) — tela viva evoluiu além do protótipo (extrato via API, ADR 0236)"*.

A coluna **Origem** (chip Banco/OFX) e as colunas de workflow que a Fase 1 da
[ADR 0236](../../decisions/0236-extrato-conciliacao-modelo-unificado.md) trouxe são
exatamente o que o protótipo **não** tem. A prod está à frente por decisão registrada;
o protótipo é que está atrás. **Nada a corrigir na produção por conta desta divergência.**

---

## FIN-4a — layout do protótipo, só forma (2026-09-23)

Onda do [RUNBOOK-paridade-ondas](RUNBOOK-paridade-ondas.md) §6 (Conciliação = 4ª). Âncora:
`TelaConciliacao` em `prototipo-ui/cowork/Wagner/financeiro-telas-extras.jsx`, servida com o DS
carregado (`servirEspelho`, ADR 0401).

| Dimensão | Protótipo | Produção antes | FIN-4a | Veredito |
|---|---|---|---|---|
| Título | "Financeiro · Conciliação" | "Conciliação · OFX bancário" | igual ao protótipo; "OFX bancário" vai pro subtítulo | IGUAL |
| Primário | "Novo título" | nenhum | "Novo título" (só navega para `/financeiro/unificado/novo`) | IGUAL |
| Faixa de KPIs | cartão único, divisões verticais, valor `--fs-8` | 4 cartões `fin-stat` | cartão único no formato do protótipo, **com os 4 contadores da produção** | IGUAL na forma |
| KPIs "Período" e "Total no extrato" | presentes | ausentes | ausentes | DÍVIDA A FECHAR — FIN-4b (dado novo + soma de valor na tela) |
| Selo de status | pílula com ponto, tokens | retângulo com borda, cor crua (`stone`/`amber`) | pílula com ponto, tokens; texto segue o status da produção | IGUAL na forma |
| Cor crua | — | 16 usos (charter proíbe) | 0 | IGUAL |
| Lista em duas colunas Extrato × Sistema | presente | tabela de 7 colunas | tabela de 7 colunas | DÍVIDA A FECHAR — FIN-4b (resumo do título vem do backend; ação "Criar" é nova) |

**Contraste medido** (tokens resolvidos em produção no tema escuro = arquivo gerado; claro pelo
arquivo gerado): selo "sugerido" `--warn`/`--warn-soft` = **5,94:1** escuro · **3,71:1** claro.
O par é o do protótipo (UI-0029: forma é dele), então fica — e vai como dívida do tema claro para o
design, junto da "atrasada" do Impostos. Os demais selos passam AA nos dois temas
(`--text-dim`/`--bg-2` 5,42 claro · 6,80 escuro; `--pos`/`--pos-soft` 5,03 · 5,52).

**Valor:** nenhuma expressão de valor mudou (`stats.*`, `brl(l.valor)`, `match_score`) — só a
classe em volta. Leitura "antes" em produção (empresa 1): 4 contadores em 0 e tabela vazia.

## FIN-4b — dados que a forma do protótipo pedia (2026-09-23)

Backend no #7783, tela neste PR.

| Dimensão | Protótipo | FIN-4a | FIN-4b | Veredito |
|---|---|---|---|---|
| KPI "Período" | presente | ausente | presente (prop deferida `resumo`) | IGUAL |
| KPI "Total no extrato" | entradas + saídas | ausente | entradas + saídas, duas origens, todas as situações | IGUAL |
| Extrato × Sistema | grade em duas metades | tabela de 7 colunas | tabela com dois grupos de colunas; a metade "Sistema" mostra o título vinculado | IGUAL na forma (segue `<table>`: o E2E lê as colunas por índice) |
| Ação "Criar" na linha sem match | botão | ausente | link "criar título" para `/financeiro/unificado/novo` | IGUAL na afordância; é link porque só navega (a tela não cria título — Automation Anti-hook do charter) |
| Rótulo "Aceitar" | "Aceitar" | "Confirmar" | "Confirmar" | DÍVIDA A FECHAR — rótulo fixado pelo E2E e pelo charter ("Confirmar match"); trocar é copy de contrato, decisão [W] |
| Valor da linha sem "R$" e com sinal "+/−" | presente | `brl()` | `brl()` | DÍVIDA A FECHAR — é formatação de número, cai na regra de valor; fica para decisão |
