<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — Produto · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 9 |
| CU no SDD | 14 |
| Telas (.tsx) | 7 |
| Telas com `casos.md` | 6 |
| UC declarados | 33 |
| UC com teste que os cita | 33 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

| Lacuna | O que falta escrever |
|---|---|
| Tela `BulkEdit` sem `casos.md` | o contrato da tela (trio incompleto) |
| `CU-PROD-04` sem UC | caso de uso que o exercite — Estoque inicial + localização + alerta + validade/lote |
| `CU-PROD-05` sem UC | caso de uso que o exercite — Combo/kit + BOM |
| `CU-PROD-06` sem UC | caso de uso que o exercite — Importação Excel + bulk-edit + mass-ops |
| `US-PROD-021` sem caso | UC que a atenda — [G-01] Kardex real na tela React StockHistory (deixar de linkar Blade) |
| `US-PROD-024` sem caso | UC que a atenda — [G-03] ⚠️Tier0 · Custo médio + valor/custo em estoque — SPIKE de desco |
| `US-PROD-025` sem caso | UC que a atenda — [G-06] UI de BOM drag-drop + baixa-de-componente do kit no PDV |
| `US-PROD-026` sem caso | UC que a atenda — Fornecedores/cotação por produto (melhor preço no drawer) |
| `US-PROD-027` sem caso | UC que a atenda — [V0] Travar o acidente do 0-row: preço zero em tabela é inerte só por  |
| `US-PROD-028` sem caso | UC que a atenda — Blindar `fixVariationStockMisMatch` com parsing locale-safe |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-PCAD-01 | Create | 🧪 aguarda veredito da lane |
| UC-PCAD-02 | Create | 🧪 aguarda veredito da lane |
| UC-PCAD-03 | Create | 🧪 aguarda veredito da lane |
| UC-PCAD-04 | Create | 🧪 aguarda veredito da lane |
| UC-PCAD-05 | Create | 🧪 aguarda veredito da lane |
| UC-PCAD-06 | Create | 🧪 aguarda veredito da lane |
| UC-PEDIT-01 | Edit | 🧪 stub (não executa) |
| UC-PEDIT-02 | Edit | 🧪 stub (não executa) |
| UC-PEDIT-03 | Edit | 🧪 aguarda veredito da lane |
| UC-PEDIT-04 | Edit | 🧪 stub (não executa) |
| UC-PEDIT-05 | Edit | 🧪 aguarda veredito da lane |
| UC-PEDIT-06 | Edit | 🧪 aguarda veredito da lane |
| UC-PEDIT-07 | Edit | 🧪 aguarda veredito da lane |
| UC-PIDX-01 | Index | 🧪 aguarda veredito da lane |
| UC-PIDX-02 | Index | 🧪 aguarda veredito da lane |
| UC-PIDX-03 | Index | 🧪 aguarda veredito da lane |
| UC-PIDX-04 | Index | 🧪 aguarda veredito da lane |
| UC-PIDX-05 | Index | 🧪 aguarda veredito da lane |
| UC-PIDX-06 | Index | 🧪 aguarda veredito da lane |
| UC-PSHOW-01 | Show | 🧪 aguarda veredito da lane |
| UC-PSHOW-02 | Show | 🧪 aguarda veredito da lane |
| UC-PSHOW-03 | Show | 🧪 aguarda veredito da lane |
| UC-PSHOW-04 | Show | 🧪 stub (não executa) |
| UC-PSHOW-05 | Show | 🧪 stub (não executa) |
| UC-PSHOW-06 | Show | 🧪 aguarda veredito da lane |
| UC-PSHOW-07 | Show | 🧪 stub (não executa) |
| UC-PSTK-01 | StockHistory | 🧪 aguarda veredito da lane |
| UC-PSTK-02 | StockHistory | 🧪 aguarda veredito da lane |
| UC-PSTK-03 | StockHistory | 🧪 aguarda veredito da lane |
| UC-PTAB-01 | SellingPrices | 🧪 aguarda veredito da lane |
| UC-PTAB-02 | SellingPrices | 🧪 aguarda veredito da lane |
| UC-PTAB-03 | SellingPrices | 🧪 aguarda veredito da lane |
| UC-PTAB-04 | SellingPrices | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
