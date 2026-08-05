---
authority: generated
---

<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — Financeiro · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 59 |
| CU no SDD | 16 |
| Telas (.tsx) | 21 |
| Telas com `casos.md` | 7 |
| UC declarados | 45 |
| UC com teste que os cita | 43 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

| Lacuna | O que falta escrever |
|---|---|
| Tela `Advisor/Dashboard` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Advisor/Login` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `AssinaturaAtualizar` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Categorias/Index` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Cobranca/Index` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Configuracoes/Contador` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `ContasBancarias/Index` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Dashboard/Index` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Dre/Index` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Extrato/Index` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Fluxo/Index` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `PlanoContas/Index` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Relatorios/Index` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Unificado/Novo` sem `casos.md` | o contrato da tela (trio incompleto) |
| `Index.casos.md` existe mas **não declara nenhum UC** | o contrato de verdade — arquivo presente ≠ tela coberta (LC-11) |
| `CU-FIN-09` sem UC | caso de uso que o exercite — Boleto do título não duplica o recebível |
| `CU-FIN-10` sem UC | caso de uso que o exercite — Importar OFX é idempotente por hash |
| `CU-FIN-11` sem UC | caso de uso que o exercite —  |
| `CU-FIN-12` sem UC | caso de uso que o exercite —  |
| `CU-FIN-23` sem UC | caso de uso que o exercite — Abrir/fechar turno |
| `US-FIN-015` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Fix BUG-3 — Listener cria titulo_pagar pra purchase com paym |
| `US-FIN-026` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — UI lista anexos GET no drawer Unificado + thumbnail PDF + de |
| `US-FIN-027` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Pill aprovacao_status na tabela Unificado + filtro workflow |
| `US-FIN-028` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Spatie permission financeiro.titulo.aprovar + gate UI |
| `US-FIN-029` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — OCR boleto upload — OpenAI Vision API extrai linha digitável |
| `US-FIN-032` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Inter API webhook PIX recebido → titulo auto-pago (auto-conc |
| `US-FIN-037` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Customer service network — Portal Advisor Contadores parceir |
| `US-FIN-046` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Sicoob mTLS reusa NfeCertificado (single source of truth) |
| `US-FIN-053` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — WR2 backfill recorrência 2026 biz=1 — assinaturas+invoices+c |
| `US-FIN-062` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Tela Impostos & obrigações (/financeiro/impostos) — estimati |
| `US-FIN-063` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Tela Atualizar Cobrança de assinatura (/financeiro/assinatur |
| `US-FIN-068` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — BridgeExpenseToTitulosCommand filtra transactions.deleted_at |

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-FIN-001 | `desconhecido` | Listar Contas a Receber em aberto |
| US-FIN-002 | `desconhecido` | Lançar título a receber manual |
| US-FIN-004 | `desconhecido` | Listar Contas a Pagar com vencimento próximo |
| US-FIN-005 | `desconhecido` | Cadastrar título a pagar com upload de boleto OCR |
| US-FIN-006 | `desconhecido` | Pagar título (registrar saída do caixa) |
| US-FIN-007 | `desconhecido` | Visualizar fluxo de caixa projetado |
| US-FIN-008 | `desconhecido` | Cadastrar conta bancária |
| US-FIN-009 | `desconhecido` | Importar extrato OFX e conciliar |
| US-FIN-010 | `desconhecido` | Emitir boleto bancário (CNAB ou via gateway) |
| US-FIN-011 | `desconhecido` | DRE (Demonstração de Resultado) |
| US-FIN-012 | `desconhecido` | Aging de inadimplência |
| US-FIN-014 | `todo` | Imprimir 2ª via boleto Inter pelo título financeiro (botão na tela /boletos) |
| US-FIN-016 | `todo` | Auto-emite boleto Inter ao criar titulo_receber (Observer + Job idempotente) |
| US-FIN-017 | `todo` | Boletos — Sheet Emitir multi-título (bulk emission) |
| US-FIN-018 | `desconhecido` | Boletos — Sheet Remessa/Retorno CNAB upload + processing |
| US-FIN-019 | `todo` | Boletos — Drawer timeline cronológica rica via activity_log Spatie |
| US-FIN-020 | `todo` | Boletos — Jobs automáticos cobrança (lembrete + ativa + protesto) |
| US-FIN-021 | `todo` | Fluxo de caixa — Margem mínima configurável via business_settings |
| US-FIN-022 | `todo` | Onda 4d.6.1 — Widget Asaas JS tokenização cartão (PCI-DSS) |
| US-FIN-023 | `todo` | Onda 4d.6.2 — SheetNovaCobranca UI tipo=card (campos cartão) |
| US-FIN-024 | `todo` | Onda 5 — Dogfooding Superadmin (Plan SaaS Oimpresso Premium biz=1) |
| US-FIN-025 | `todo` | Onda 6 — Cleanup colunas legacy + remover redirects 301 |
| US-FIN-030 | `todo` | Aging buckets <30/30-60/60-90/90+ no header Unificado + filtro |
| US-FIN-033 | `todo` | Notificações vencimento próximo (e-mail + WhatsApp X dias antes) |
| US-FIN-034 | `todo` | Importação massiva CSV/Excel — mapping wizard + dry-run + commit |
| US-FIN-035 | `desconhecido` | Repetir lançamento próximo mês + Combobox autocomplete contraparte |
| US-FIN-036 | `desconhecido` | PWA básico Financeiro — manifest + service worker + offline cache + install prom |
| US-FIN-039 | `todo` | Artisan command financeiro:vincular-baixas-sem-conta - reconciliacao posterior |
| US-FIN-040 | `desconhecido` | Artisan command financeiro:health-check cron daily 06:00 BRT - detecta gaps brid |
| US-FIN-041 | `todo` | Onda 6 Accounting DROP TABLE - 6 vazias + ARCHIVE 2 seed + DELETE permissions |
| US-FIN-042 | `todo` | Backfill cliente_descricao biz=1 - 52 fin_titulos pre-Onda-Edit NULL |
| US-FIN-043 | `todo` | Coleta pre-migracao Financeiro Delphi cliente piloto (Maiara) |
| US-FIN-044 | `desconhecido` | SicoobApiDriver nativo (OAuth2 + mTLS + webhook real-time) |
| US-FIN-045 | `todo` | Wizard bank-first 2-step (banco → modo conexão) |
| US-FIN-055 | `todo` | Purgar coluna-fantasma transactions.total_remaining_amount (resto Financeiro + T |
| US-FIN-058 | `todo` | Reparar 59 boletos órfãos + 3.372 fin_titulos com origem_id bug (Firebird) |
| US-FIN-059 | `todo` | Observers Sells/Compras→Financeiro: try/catch + report() (nunca propagar) + idea |
| US-FIN-060 | `todo` | Reabilitar acesso OpenAI gpt-4o do BoletoOcrService (403 silencioso em prod) |
| US-FIN-061 | `todo` | Otimizar LCP das telas núcleo (Financeiro/Unificado + Sells) — verificar prod re |
| US-FIN-064 | `todo` | Redirect ContasReceber/ContasPagar → Unificado (deprecação) |
| US-FIN-065 | `todo` | Elevar tela Unificado/Novo a ≥70 (form unificado real de cobrança) |
| US-FIN-066 | `todo` | Elevar tela AssinaturaAtualizar a ≥70 (PageHeader canon + preview de valor) |
| US-FIN-067 | `todo` | Elevar tela Advisor/Login a ≥70 (DS v4 roxo + @/ui + charter) |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-F01 | Unificado/Index | 🧪 aguarda veredito da lane |
| UC-F02 | Unificado/Index | 🧪 aguarda veredito da lane |
| UC-F03 | Unificado/Index | 🧪 aguarda veredito da lane |
| UC-F04 | Unificado/Index | 🧪 aguarda veredito da lane |
| UC-F05 | Unificado/Index | 🧪 aguarda veredito da lane |
| UC-F0N | Unificado/Index | 📝 sem_teste |
| UC-FCC-01 | Conciliacao/Index | 🧪 aguarda veredito da lane |
| UC-FCC-02 | Conciliacao/Index | 🧪 aguarda veredito da lane |
| UC-FCC-03 | Conciliacao/Index | 🧪 aguarda veredito da lane |
| UC-FCC-04 | Conciliacao/Index | 🧪 aguarda veredito da lane |
| UC-FCC-05 | Conciliacao/Index | 🧪 aguarda veredito da lane |
| UC-FCC-06 | Conciliacao/Index | 🧪 aguarda veredito da lane |
| UC-FCC-07 | Conciliacao/Index | 🧪 aguarda veredito da lane |
| UC-FCC-08 | Conciliacao/Index | 🧪 aguarda veredito da lane |
| UC-FCC-09 | Conciliacao/Index | 🧪 aguarda veredito da lane |
| UC-FCC-10 | Conciliacao/Index | 🧪 aguarda veredito da lane |
| UC-FCC-11 | Conciliacao/Index | 🧪 aguarda veredito da lane |
| UC-FCC-12 | Conciliacao/Index | 🧪 aguarda veredito da lane |
| UC-FCC-13 | Conciliacao/Index | 🧪 aguarda veredito da lane |
| UC-FCX-01 | Caixa/Index | 🧪 aguarda veredito da lane |
| UC-FCX-02 | Caixa/Index | 🧪 aguarda veredito da lane |
| UC-FCX-03 | Caixa/Index | 🧪 aguarda veredito da lane |
| UC-FCX-04 | Caixa/Index | 🧪 aguarda veredito da lane |
| UC-FCX-05 | Caixa/Index | 🧪 aguarda veredito da lane |
| UC-FUNI-01 | Unificado/Index | 🧪 aguarda veredito da lane |
| UC-FUNI-02 | Unificado/Index | 🧪 aguarda veredito da lane |
| UC-FUNI-03 | Unificado/Index | 🧪 aguarda veredito da lane |
| UC-FUNI-04 | Unificado/Index | 🧪 aguarda veredito da lane |
| UC-IMP-01 | Impostos/Index | 🧪 aguarda veredito da lane |
| UC-IMP-02 | Impostos/Index | 🧪 aguarda veredito da lane |
| UC-IMP-03 | Impostos/Index | 🧪 aguarda veredito da lane |
| UC-IMP-04 | Impostos/Index | 🧪 aguarda veredito da lane |
| UC-IMP-05 | Impostos/Index | 🧪 aguarda veredito da lane |
| UC-IMP-06 | Impostos/Index | 🧪 aguarda veredito da lane |
| UC-IMP-07 | Impostos/Index | 📝 sem_teste |
| UC-IMP-08 | Impostos/Index | 🧪 aguarda veredito da lane |
| UC-IMP-09 | Impostos/Index | 🧪 aguarda veredito da lane |
| UC-IMP-10 | Impostos/Index | 🧪 aguarda veredito da lane |
| UC-IMP-11 | Impostos/Index | 🧪 aguarda veredito da lane |
| UC-PV-01 | ProvaViva | 🧪 aguarda veredito da lane |
| UC-PV-02 | ProvaViva | 🧪 aguarda veredito da lane |
| UC-PV-03 | ProvaViva | 🧪 aguarda veredito da lane |
| UC-PV-04 | ProvaViva | 🧪 aguarda veredito da lane |
| UC-PV-05 | ProvaViva | 🧪 aguarda veredito da lane |
| UC-PV-06 | ProvaViva | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
