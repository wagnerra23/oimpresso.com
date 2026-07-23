---
id: requisitos-sells-caso-pratico-os-comunicacao-visual
---

# Caso prático — OS Comunicação Visual com 2 documentos fiscais

> Documento de referência citado por: US-SELL-011, US-SELL-012, US-SELL-013, US-SELL-014, US-NFE-059, US-NFE-060.
> Origem: sessão 2026-05-10 (Wagner pediu caso prático recente cobrindo novas funções fiscais BR).
> Tipo: documento vivo — atualizar quando novos casos surgirem (oficina auto, eletricista, dentista, etc).

## Cenário

**Vendedor:** Gráfica Vargas Ltda (CNAE 1813-0/01 impressão materiais publicitários, Simples Nacional, Florianópolis/SC, IE estadual ativa, IM municipal ativa).

**Cliente final (tomador):** Pizzaria Nicola Ltda (Florianópolis/SC, CNPJ ativo, regime Lucro Presumido — não retém ISS de prestador Simples).

**Pedido:** 1 banner lona 380gr 3×2m + instalação na fachada.

## Itens da OS

| Item | Tipo | Valor | Documento fiscal | Classificação | Estoque |
|---|---|---|---|---|---|
| Banner lona 380gr 3×2m | mercadoria | R$ [redacted Tier 0] | **NFe modelo 55** | NCM 5907.00.00, CFOP 5101, CSOSN 102 | reserva 6m² lona em "aprovado" → consome em "produção concluída" |
| Instalação fachada | serviço | R$ [redacted Tier 0] | **NFSe modelo 56 nacional** (`nfse.gov.br/sefin`) | LC 116/2003 item 17.06 (publicidade), ISS 5% Florianópolis | — |
| **Total OS** | — | **R$ [redacted Tier 0]** | **2 documentos atrelados a 1 transaction** | — | — |

## Estados (FSM concreto)

```
draft
  ↓ aprovar_orcamento [vendedor]   →  side-effect: ReservarEstoque (6m² lona, expires_at +30d)
quote_approved
  ↓ iniciar_producao [operador]
in_production
  ↓ concluir_producao [operador]   →  side-effect: ConsumirEstoque (decrementa qty_available)
ready_to_install
  ↓ iniciar_instalacao [instalador]
installing
  ↓ concluir_instalacao [instalador]
completed
  ↓ faturar_os [gerente]           →  side-effect 1: EmitirNFeJob (banner, value=350)
                                   →  side-effect 2: EmitirNFSeJob (instalação, value=200, item_lc=17.06)
                                   →  side-effect 3: BaixarFinanceiro (total=550)
invoiced
  ↓ entregar_ao_cliente [instalador]  →  side-effect (opcional): EmitirMDFeJob (>R$ [redacted Tier 0] carga)
delivered
  ↓ fechar_os [gerente]            (após confirmar pagamento)
closed
```

**Caminho alternativo — cancelar OS pós aprovação:**

```
quote_approved | in_production | ready_to_install
  ↓ cancelar_os [gerente, motivo obrigatório]
   →  side-effect: LiberarReserva (reservation status=released)
cancelled
```

**Caminho alternativo — cancelar OS pós faturamento (>15min ICMS-SC):**

```
invoiced
  ↓ cancelar_nfe_dentro_prazo [gerente]
   →  side-effect: emite evento 110111 (cancelamento NFe55) + RPS substituição NFSe56
   →  estorna_financeiro
cancelled
```

## RBAC por transição (sale_stage_action_roles)

| Action | Roles permitidas | Validação extra |
|---|---|---|
| `aprovar_orcamento` | vendedor, gerente | — |
| `iniciar_producao` | operador, gerente | reserva ativa existe |
| `concluir_producao` | operador, gerente | qty produzida == qty reservada |
| `iniciar_instalacao` | instalador, gerente | — |
| `concluir_instalacao` | instalador, gerente | — |
| `faturar_os` | gerente, financeiro | cliente tem CNPJ + endereço completo |
| `cancelar_os` | gerente | motivo texto >20 chars |
| `cancelar_nfe_dentro_prazo` | gerente | <24h emissão NFe (varia por UF) |
| `entregar_ao_cliente` | instalador, gerente | NFe + NFSe autorizadas (cstat 100) |
| `fechar_os` | gerente, financeiro | total quitado no Financeiro |

## Documentos gerados (transaction_documents poly)

```
transaction_id = 12345
├── doc_type=nfe55  · doc_class=Modules\NfeBrasil\Models\NfeEmissao   · doc_id=789  · value=350.00 · status=authorized
├── doc_type=nfse56 · doc_class=Modules\NfeBrasil\Models\NfseEmissao  · doc_id=44   · value=200.00 · status=authorized
└── (opcional) doc_type=mdfe58 · doc_class=Modules\NfeBrasil\Models\MdfeEmissao · doc_id=12 · value=550.00 · status=authorized
```

UI tela `/sells/12345` mostra card "Documentos Fiscais":

```
┌─ Documentos Fiscais (3) ────────────────────────────┐
│ ✅ NFe 55 nº 789      R$ [redacted Tier 0]   Banner          │
│ ✅ NFSe 56 nº 44      R$ [redacted Tier 0]   Instalação      │
│ ✅ MDFe 58 nº 12      R$ [redacted Tier 0]   Transporte      │
│                                                     │
│ Total documentado: R$ [redacted Tier 0] = total OS ✓          │
└─────────────────────────────────────────────────────┘
```

## Funções fiscais BR cobertas (estado-da-arte 2026)

| Função | Cobertura no caso | US relacionada |
|---|---|---|
| **NFe modelo 55** | banner | já existe (`Modules/NfeBrasil`) |
| **NFC-e modelo 65** | venda balcão sem boleto | já existe (US-NFE-002) |
| **NFSe modelo 56 nacional** (NT 2024-001) | instalação | **US-NFE-060** (novo) |
| **NFCom modelo 62** | telefonia/comunicação | fora deste caso (vertical telcos futura) |
| **MDFe modelo 58** | transporte do banner (>R$ [redacted Tier 0] carga) | opcional, US futura |
| **CT-e modelo 57** | frete contratado de terceiros | fora deste caso |
| **Manifestação destinatário** (eventos 210/220/230/240) | Pizzaria recebe NFe55 → manifesta ciência | já implementado (US-NFE-049/050/051/052) |
| **Reforma Tributária 2026 fase teste** (LC 214/2025) | IBS 0,1% + CBS 0,9% destacados informativamente nas duas notas (sem efeito caixa) | US futura — emendas em US-NFE-060 + tabela alíquotas IBS/CBS |
| **Reserva de estoque (não baixa)** | 6m² lona reservados em "aprovado" | **US-SELL-013** (novo) |
| **Multi-documento por venda** | NFe55 + NFSe56 atrelados a transaction | **US-SELL-014** (novo) |
| **Cancelamento NFe** dentro do prazo | OS cancelada após emissão dispara cancelamento + RPS substituição | já implementado parcial (US-NFE-004) |
| **DF-e ecosystem** (visão unificada) | tela cliente mostra TODAS notas vinculadas | depende US-SELL-014 |

## Por que isso torna o sistema competitivo

**vs. Mubisys / Zênite / Calcgraf** (concorrentes Modules/ComunicacaoVisual):
- Eles emitem 1 documento por venda. Pra OS produto+serviço, fazem **DUAS vendas separadas** (cadastro duplo, financeiro duplo, estoque duplo). UX ruim e financeiro descasado.
- Nenhum tem reserva de estoque (sai do disponível na inserção do orçamento, "embolando" controle).
- Nenhum tem FSM com RBAC granular por transição (autorização é "edit/no-edit" generic).

**vs. Bling / Tiny / Conta Azul** (horizontais raso):
- Não suportam NFSe nacional 56 com profundidade (ainda emitem direto pra emissores municipais — fadados a parar de funcionar conforme adesão obrigatória rolar 2025-2026).
- Não têm conceito de "OS" (sale = ato único; não modelam fluxo produção/instalação).

**vs. Linx Microvix** (vestuário) e **Linx Big** (gráfica):
- Caros (~R$ [redacted Tier 0]-3000/mês). FSM custom não é exposto na UI — parametrização é via consultoria paga, lock-in alto.

**oimpresso pós cadeia FSM** vai oferecer:
- ✅ NFe + NFSe na mesma OS, 1 cadastro
- ✅ Reserva de estoque sem baixa (proteção contra over-selling)
- ✅ FSM + RBAC granular configurável por business via UI admin (não consultoria paga)
- ✅ Pronto pra Reforma Tributária 2026 (estrutura dual NFe55 + NFSe56 + futura adição CBS/IBS)
- ✅ DF-e ecosystem unificado (manifestação + multi-doc + eventos)

## Aplicabilidade em outros módulos

| Módulo | Caso análogo | Adaptação |
|---|---|---|
| **Modules/Repair** | OS conserto eletrônica (peças NFe55 + mão de obra NFSe56) | mesmo FSM, processos seed `OS Conserto Com Nota` / `OS Conserto Sem Nota` |
| **Modules/OficinaAuto** (futuro) | OS oficina (peças NFe55 + serviço NFSe56) | mesmo FSM, item LC 14.01 (lubrificação/limpeza) ou 14.05 (reparação veículos) |
| **Modules/Vestuario** (ROTA LIVRE) | venda balcão (1 nota NFC-e ou nada) | processo `Venda Sem Nota` default; `Venda Com Nota Manual` opcional |
| **Modules/ComunicacaoVisual** | EXATAMENTE este caso | processos seed na install do módulo |

## Próximos passos

1. **Wagner aprova ADR US-SELL-010** (recomendação do sub-agent: custom 4 tabelas + Spatie Permission, sem ModelStates/Symfony — ver [_AGENT_FSM_FINDINGS-2026-05-10.md](../../decisions/proposals/drafts/_AGENT_FSM_FINDINGS-2026-05-10.md))
2. Implementar **US-SELL-011** (4 tabelas FSM + side_effect_class)
3. Implementar paralelos: **US-SELL-013** (estoque) + **US-SELL-014** (poly N notas)
4. Implementar **US-NFE-060** (NFSe nacional 56)
5. Aplicar **US-SELL-012** (gate emissão) usando US-SELL-013 + US-SELL-014
6. Smoke prod end-to-end **US-NFE-059** com cliente real (Gold candidato natural)

---

**Última atualização:** 2026-05-10 — sessão Wagner case fiscal BR + cadeia FSM.
