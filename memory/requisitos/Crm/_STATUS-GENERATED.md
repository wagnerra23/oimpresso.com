---
authority: generated
---

<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — Crm · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 4 |
| CU no SDD | 0 |
| Telas (.tsx) | 13 |
| Telas com `casos.md` | 13 |
| UC declarados | 98 |
| UC com teste que os cita | 96 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

_Nenhuma lacuna: toda tela tem caso **com UC**, todo CU é citado, e toda US **entregue** tem contrato._

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-CRM-094 | `desconhecido` | Chamado de garantia com SLA — prazo por tipo, fila, responsável e alerta |
| US-CRM-096 | `desconhecido` | Captura de lead com origem e UTM — o lead entra no funil já com a origem |
| US-CRM-098 | `desconhecido` | Segmentação RFM — recência, frequência e valor por cliente |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-COS-24 | Portal/Painel/Index | 📝 sem_teste |
| UC-CRMACO-01 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-02 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-03 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-04 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-05 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-06 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-07 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-08 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-09 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-10 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-11 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-12 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-13 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-14 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-15 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-16 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-17 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-18 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-19 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-20 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-21 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-22 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMACO-23 | Acompanhamentos/Index | 🧪 aguarda veredito da lane |
| UC-CRMCAM-01 | Campanhas/Index | 🧪 aguarda veredito da lane |
| UC-CRMCAM-02 | Campanhas/Index | 🧪 aguarda veredito da lane |
| UC-CRMCAM-03 | Campanhas/Index | 🧪 aguarda veredito da lane |
| UC-CRMCAM-04 | Campanhas/Index | 🧪 aguarda veredito da lane |
| UC-CRMCAM-05 | Campanhas/Index | 🧪 aguarda veredito da lane |
| UC-CRMCAM-06 | Campanhas/Index | 🧪 aguarda veredito da lane |
| UC-CRMCAM-07 | Campanhas/Index | 🧪 aguarda veredito da lane |
| UC-CRMCFG-01 | Configuracoes/Index | 🧪 aguarda veredito da lane |
| UC-CRMCFG-02 | Configuracoes/Index | 🧪 aguarda veredito da lane |
| UC-CRMCFG-03 | Configuracoes/Index | 🧪 aguarda veredito da lane |
| UC-CRMCFG-04 | Configuracoes/Index | 🧪 aguarda veredito da lane |
| UC-CRMCFG-05 | Configuracoes/Index | 🧪 aguarda veredito da lane |
| UC-CRMCHA-01 | Chamadas/Index | 🧪 aguarda veredito da lane |
| UC-CRMCHA-02 | Chamadas/Index | 🧪 aguarda veredito da lane |
| UC-CRMCHA-03 | Chamadas/Index | 🧪 aguarda veredito da lane |
| UC-CRMCHA-04 | Chamadas/Index | 🧪 aguarda veredito da lane |
| UC-CRMCHA-05 | Chamadas/Index | 🧪 aguarda veredito da lane |
| UC-CRMCHA-06 | Chamadas/Index | 🧪 aguarda veredito da lane |
| UC-CRMCHA-07 | Chamadas/Index | 🧪 aguarda veredito da lane |
| UC-CRMFN-01 | Funil/Index | 🧪 aguarda veredito da lane |
| UC-CRMFN-02 | Funil/Index | 🧪 aguarda veredito da lane |
| UC-CRMFN-03 | Funil/Index | 🧪 aguarda veredito da lane |
| UC-CRMFN-04 | Funil/Index | 🧪 aguarda veredito da lane |
| UC-CRMFN-05 | Funil/Index | 🧪 aguarda veredito da lane |
| UC-CRMLD-01 | Leads/Index | 🧪 aguarda veredito da lane |
| UC-CRMLD-02 | Leads/Index | 🧪 aguarda veredito da lane |
| UC-CRMLD-03 | Leads/Index | 🧪 aguarda veredito da lane |
| UC-CRMLD-04 | Leads/Index | 🧪 aguarda veredito da lane |
| UC-CRMLD-05 | Leads/Index | 🧪 aguarda veredito da lane |
| UC-CRMLD-06 | Leads/Index | 🧪 aguarda veredito da lane |
| UC-CRMLD-07 | Leads/Index | 🧪 aguarda veredito da lane |
| UC-CRMLD-08 | Leads/Index | 🧪 aguarda veredito da lane |
| UC-CRMLD-09 | Leads/Index | 🧪 aguarda veredito da lane |
| UC-CRMLD-10 | Leads/Index | 🧪 aguarda veredito da lane |
| UC-CRMLD-11 | Leads/Index | 🧪 aguarda veredito da lane |
| UC-CRMLD-12 | Leads/Index | 🧪 aguarda veredito da lane |
| UC-CRMLD-13 | Leads/Index | 🧪 aguarda veredito da lane |
| UC-CRMLOG-01 | Logins/Index | 🧪 aguarda veredito da lane |
| UC-CRMLOG-02 | Logins/Index | 🧪 aguarda veredito da lane |
| UC-CRMLOG-03 | Logins/Index | 🧪 aguarda veredito da lane |
| UC-CRMLOG-04 | Logins/Index | 🧪 aguarda veredito da lane |
| UC-CRMLOG-05 | Logins/Index | 🧪 aguarda veredito da lane |
| UC-CRMPAI-01 | Painel/Index | 🧪 aguarda veredito da lane |
| UC-CRMPAI-02 | Painel/Index | 🧪 aguarda veredito da lane |
| UC-CRMPAI-03 | Painel/Index | 🧪 aguarda veredito da lane |
| UC-CRMPAI-04 | Painel/Index | 🧪 aguarda veredito da lane |
| UC-CRMPAI-05 | Painel/Index | 🧪 aguarda veredito da lane |
| UC-CRMPAI-06 | Painel/Index | 🧪 aguarda veredito da lane |
| UC-CRMPED-01 | Pedidos/Index | 🧪 aguarda veredito da lane |
| UC-CRMPED-02 | Pedidos/Index | 🧪 aguarda veredito da lane |
| UC-CRMPED-03 | Pedidos/Index | 🧪 aguarda veredito da lane |
| UC-CRMPED-04 | Pedidos/Index | 🧪 aguarda veredito da lane |
| UC-CRMPED-05 | Pedidos/Index | 🧪 aguarda veredito da lane |
| UC-CRMPO-01 | Portal/Painel/Index | 🧪 aguarda veredito da lane |
| UC-CRMPO-02 | Portal/Painel/Index | 🧪 aguarda veredito da lane |
| UC-CRMPO-03 | Portal/Painel/Index | 🧪 aguarda veredito da lane |
| UC-CRMPO-04 | Portal/Perfil/Index | 🧪 aguarda veredito da lane |
| UC-CRMPO-05 | Portal/Perfil/Index | 🧪 aguarda veredito da lane |
| UC-CRMPO-17 | Portal/Painel/Index | 📝 sem_teste |
| UC-CRMPO-18 | Portal/Painel/Index | 🧪 aguarda veredito da lane |
| UC-CRMPRO-01 | Propostas/Index | 🧪 aguarda veredito da lane |
| UC-CRMPRO-02 | Propostas/Index | 🧪 aguarda veredito da lane |
| UC-CRMPRO-03 | Propostas/Index | 🧪 aguarda veredito da lane |
| UC-CRMPRO-04 | Propostas/Index | 🧪 aguarda veredito da lane |
| UC-CRMPRO-05 | Propostas/Index | 🧪 aguarda veredito da lane |
| UC-CRMPRO-06 | Propostas/Index | 🧪 aguarda veredito da lane |
| UC-CRMPRO-07 | Propostas/Index | 🧪 aguarda veredito da lane |
| UC-CRMREL-01 | Relatorios/Index | 🧪 aguarda veredito da lane |
| UC-CRMREL-02 | Relatorios/Index | 🧪 aguarda veredito da lane |
| UC-CRMREL-03 | Relatorios/Index | 🧪 aguarda veredito da lane |
| UC-CRMREL-04 | Relatorios/Index | 🧪 aguarda veredito da lane |
| UC-CRMREL-05 | Relatorios/Index | 🧪 aguarda veredito da lane |
| UC-CRMREL-06 | Relatorios/Index | 🧪 aguarda veredito da lane |
| UC-SANEG-14 | Leads/Index | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
