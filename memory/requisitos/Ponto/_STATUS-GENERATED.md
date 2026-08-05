<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — Ponto · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 13 |
| CU no SDD | 14 |
| Telas (.tsx) | 20 |
| Telas com `casos.md` | 13 |
| UC declarados | 31 |
| UC com teste que os cita | 31 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

| Lacuna | O que falta escrever |
|---|---|
| Tela `Colaboradores/Edit` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Colaboradores/Index` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Configuracoes/Index` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Configuracoes/Reps` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Dashboard/Index` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Escalas/Index` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Welcome` sem `casos.md` | o contrato da tela (trio incompleto) |
| `Show.casos.md` existe mas **não declara nenhum UC** | o contrato de verdade — arquivo presente ≠ tela coberta (LC-11) |
| `Show.casos.md` existe mas **não declara nenhum UC** | o contrato de verdade — arquivo presente ≠ tela coberta (LC-11) |
| `US-PONTO-001` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Relogio web pra registrar entrada/saida (REP-P) |
| `US-PONTO-007` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Multi-tenant isolation (Tier 0 IRREVOGAVEL) |

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-PONTO-005 | `wip` | Apuracao automatica de jornada (Art. 66 + 71 CLT) |
| US-PONTO-006 | `backlog` | Geracao AFD legacy pra fiscalizacao MTE (REP-A INMETRO) |
| US-PONTO-009 | `backlog` | Geracao AEJ canon Portaria 671/2021 Anexo VI (CRITICO REGULATORIO) |
| US-PONTO-010 | `backlog` | Comprovante PDF QR Code (Anexo I §5.5 Portaria 671) |
| US-PONTO-011 | `todo` | Fechar o append-only do ledger de banco de horas |
| US-PONTO-012 | `todo` | Corrigir os atributos fantasma do modulo (4 instancias) |
| US-PONTO-013 | `todo` | Consertar as duas telas que nao persistem |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-01 | Escalas/Form | 🧪 aguarda veredito da lane |
| UC-02 | BancoHoras/Index | 🧪 aguarda veredito da lane |
| UC-APROV-01 | Aprovacoes/Index | 🧪 aguarda veredito da lane |
| UC-APROV-02 | Aprovacoes/Index | 🧪 aguarda veredito da lane |
| UC-APROV-03 | Aprovacoes/Index | 🧪 aguarda veredito da lane |
| UC-APROV-04 | Aprovacoes/Index | 🧪 aguarda veredito da lane |
| UC-BHIDX-01 | BancoHoras/Index | 🧪 aguarda veredito da lane |
| UC-BHIDX-02 | BancoHoras/Index | 🧪 aguarda veredito da lane |
| UC-BHIDX-03 | BancoHoras/Index | 🧪 aguarda veredito da lane |
| UC-BHIDX-04 | BancoHoras/Index | 🧪 aguarda veredito da lane |
| UC-BHSHOW-01 | BancoHoras/Show | 🧪 aguarda veredito da lane |
| UC-BHSHOW-02 | BancoHoras/Show | 🧪 aguarda veredito da lane |
| UC-BHSHOW-03 | BancoHoras/Show | 🧪 aguarda veredito da lane |
| UC-ESCF-01 | Escalas/Form | 🧪 aguarda veredito da lane |
| UC-ESCF-02 | Escalas/Form | 🧪 aguarda veredito da lane |
| UC-ESCF-03 | Escalas/Form | 🧪 aguarda veredito da lane |
| UC-ESPIDX-01 | Espelho/Index | 🧪 aguarda veredito da lane |
| UC-ESPIDX-02 | Espelho/Index | 🧪 aguarda veredito da lane |
| UC-ESPIDX-03 | Espelho/Index | 🧪 aguarda veredito da lane |
| UC-IMPCRE-01 | Importacoes/Create | 🧪 aguarda veredito da lane |
| UC-IMPCRE-02 | Importacoes/Create | 🧪 aguarda veredito da lane |
| UC-IMPIDX-01 | Importacoes/Index | 🧪 aguarda veredito da lane |
| UC-IMPIDX-02 | Importacoes/Index | 🧪 aguarda veredito da lane |
| UC-IMPIDX-03 | Importacoes/Index | 🧪 aguarda veredito da lane |
| UC-INTCRE-01 | Intercorrencias/Create | 🧪 aguarda veredito da lane |
| UC-INTCRE-02 | Intercorrencias/Create | 🧪 aguarda veredito da lane |
| UC-INTIDX-01 | Intercorrencias/Index | 🧪 aguarda veredito da lane |
| UC-INTIDX-02 | Intercorrencias/Index | 🧪 aguarda veredito da lane |
| UC-INTIDX-03 | Intercorrencias/Index | 🧪 aguarda veredito da lane |
| UC-RELIDX-01 | Relatorios/Index | 🧪 aguarda veredito da lane |
| UC-RELIDX-02 | Relatorios/Index | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
