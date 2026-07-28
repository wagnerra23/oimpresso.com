<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — ComunicacaoVisual · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 18 |
| CU no SDD | 10 |
| Telas (.tsx) | 1 |
| Telas com `casos.md` | 1 |
| UC declarados | 12 |
| UC com teste que os cita | 12 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

_Nenhuma lacuna: toda tela tem caso **com UC**, todo CU é citado, e toda US **entregue** tem contrato._

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-COMVIS-001 | `desconhecido` | Cálculo automático por m² (lona, vinil, banner, fachada) — **P0** |
| US-COMVIS-002 | `desconhecido` | Cadastro de material com preço por gramatura — **P0** |
| US-COMVIS-003 | `desconhecido` | PCP gráfico — fluxo OS multi-etapa com responsável + prazo + custo — **P0** |
| US-COMVIS-004 | `desconhecido` | Apontamento de máquina (Roland/Mimaki/Mutoh/HP Latex) — **P1** |
| US-COMVIS-005 | `desconhecido` | Pós-cálculo (orçado vs realizado) — **P1** |
| US-COMVIS-006 | `desconhecido` | Tabela tributária CNAE 1813-0/01 (CFOP/CSOSN/NCM padrão) — **P0** |
| US-COMVIS-007 | `desconhecido` | Gestão de fachada/instalação (agenda + equipe + EPI) — **P1** |
| US-COMVIS-008 | `desconhecido` | NFSe automática pra serviço de instalação — **P1** |
| US-COMVIS-009 | `desconhecido` | NFe automática a partir de boleto pago — **P0** (já entregue no núcleo) |
| US-COMVIS-010 | `desconhecido` | Provador de orçamento online (formulário web público) — **P2** |
| US-COMVIS-011 | `desconhecido` | Comissão por OS (vendedor + instalador) — **P1** |
| US-COMVIS-012 | `desconhecido` | DAM básico — cliente envia arquivo print-ready — **P2** |
| US-COMVIS-013 | `desconhecido` | Bulk update preço material via Jana — **P2** |
| US-COMVIS-014 | `desconhecido` | Dashboard "Larissa pergunta no chat às 22h" — **P2** |
| US-COMVIS-015 | `desconhecido` | Cadastro de máquina com tinta/CMYK consumption tracking — **P2** |
| US-COMVIS-016 | `desconhecido` | CT-e/MDF-e pra entrega — **P3** |
| US-COMVIS-017 | `desconhecido` | Importação massiva de clientes/produtos do legacy OfficeImpresso — **P0** |
| US-COMVIS-018 | `desconhecido` | Loja whitelabel pra catálogo público — **P3** |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-CV-01 | Index | 🧪 aguarda veredito da lane |
| UC-CV-02 | Index | 🧪 aguarda veredito da lane |
| UC-CV-03 | Index | 🧪 aguarda veredito da lane |
| UC-CV-04 | Index | 🧪 aguarda veredito da lane |
| UC-CV-05 | Index | 🧪 aguarda veredito da lane |
| UC-CV-06 | Index | 🧪 aguarda veredito da lane |
| UC-CV-07 | Index | 🧪 aguarda veredito da lane |
| UC-CV-08 | Index | 🧪 aguarda veredito da lane |
| UC-CV-09 | Index | 🧪 aguarda veredito da lane |
| UC-CV-10 | Index | 🧪 aguarda veredito da lane |
| UC-CV-11 | Index | 🧪 aguarda veredito da lane |
| UC-CV-12 | Index | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
