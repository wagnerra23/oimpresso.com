<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — Vestuario · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 20 |
| CU no SDD | 8 |
| Telas (.tsx) | 1 |
| Telas com `casos.md` | 1 |
| UC declarados | 9 |
| UC com teste que os cita | 9 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

_Nenhuma lacuna: toda tela tem caso **com UC**, todo CU é citado, e toda US **entregue** tem contrato._

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-VEST-001 | `desconhecido` | Cadastro de produto com variações tamanho + cor `live` |
| US-VEST-002 | `desconhecido` | Venda balcão (PDV) com leitor de código de barras `live` |
| US-VEST-003 | `desconhecido` | Emissão de NFC-e modelo 65 a partir do POS `live (parcial — biz=4 não usa hoje,  |
| US-VEST-004 | `desconhecido` | Histórico de vendas com filtros (cliente, período, vendedor) `live` |
| US-VEST-005 | `desconhecido` | Estoque por localização (matriz tamanho × cor × loja) `live` |
| US-VEST-006 | `desconhecido` | Compra (purchase) com fornecedor + recebimento `live` |
| US-VEST-007 | `desconhecido` | Conta a receber (boleto Asaas) e a pagar `live` |
| US-VEST-008 | `desconhecido` | Múltiplos schemes de invoice convivendo (`2026/NNNN` + `17NNN`) `live` |
| US-VEST-009 | `desconhecido` | Sidebar/topnav adaptado por monitor 1280px `live` |
| US-VEST-021 | `todo` | Devolução/troca com prazo CDC + crédito em conta-cliente `p0` |
| US-VEST-022 | `todo` | Comissão de vendedor (% sobre venda + meta) `p1` |
| US-VEST-023 | `todo` | Liquidação por categoria/marca/estação (desconto em massa) `p1` |
| US-VEST-024 | `todo` | Programa fidelidade (R$ [redacted Tier 0] = 1 ponto) com resgate em desconto `p1 |
| US-VEST-025 | `todo` | Vale-presente / cartão presente (gift card) `p2` |
| US-VEST-026 | `todo` | Crediário próprio (layaway / parcelado loja) `p2` |
| US-VEST-027 | `todo` | Provador / fila / pré-venda (separação reserva 24h) `p3` |
| US-VEST-028 | `todo` | Vendas externas (sacoleira / venda direta) `p3` |
| US-VEST-029 | `todo` | Atributo "estação" (verão/inverno/meia-estação/atemporal) `p1` |
| US-VEST-030 | `todo` | Ecommerce (loja virtual / WhatsApp catálogo) `p3` |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-VET-01 | Etiquetas/Index | 🧪 aguarda veredito da lane |
| UC-VET-02 | Etiquetas/Index | 🧪 aguarda veredito da lane |
| UC-VET-03 | Etiquetas/Index | 🧪 aguarda veredito da lane |
| UC-VET-04 | Etiquetas/Index | 🧪 aguarda veredito da lane |
| UC-VET-05 | Etiquetas/Index | 🧪 aguarda veredito da lane |
| UC-VET-06 | Etiquetas/Index | 🧪 aguarda veredito da lane |
| UC-VET-07 | Etiquetas/Index | 🧪 aguarda veredito da lane |
| UC-VET-08 | Etiquetas/Index | 🧪 aguarda veredito da lane |
| UC-VET-09 | Etiquetas/Index | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
