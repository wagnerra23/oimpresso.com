---
data: '2026-05-27'
hora: 'inferido manhã'
canal: voz                                # Kamila pediu Wagner em conversa; Wagner reportou pro Claude

quem:
  persona_slug: kamila-martinho
  cliente_real: martinho-cacambas
  business_id: 164

o_que_disse:
  literal: '"kamila quer cadastrar o cobrança pelo sicoob"'
  contexto: 'Wagner reportou pedido da Kamila pra cadastrar cobrança bancária Sicoob (banco operacional do Martinho). Inferi inicialmente que era CNAB OU API e perguntei — Wagner confirmou API REST com webhook real-time (querem baixa automática sem importar arquivo retorno 2x/dia).'

quando_no_produto:
  modulo: PaymentGateway
  tela: '/settings/payment-gateways novo gateway wizard'
  acao: cadastrar-driver-sicoob-api-mtls

job_por_tras:
  job: 'emitir boleto Sicoob com baixa real-time (sem importar CNAB arquivo 2x/dia manualmente)'
  motivacao: funcional                    # tempo + eliminar tarefa repetitiva manual diária

workaround_atual:
  o_que_faz: 'usar SicoobCnabDriver (CNAB 240 arquivo remessa/retorno) — Kamila emite boleto + 2x/dia baixa arquivo retorno do Sicoob internet banking + upload manual em /financeiro/cobranca'
  custo: '10-15 min/dia manual + risco esquecer importar = saldo cliente errado + cobranças "fantasma" pendentes'

severity_nng: 2                            # minor — tem workaround funcional (CNAB), mas é fricção diária

frequencia:
  primeira_vez: true                       # primeira menção formal Sicoob API
  recorrente_para_ela: 1                   # diário de fato (workaround atual), mas pedido em si é 1ª vez
  outros_clientes_tambem: []               # ROTA LIVRE não usa Sicoob; nenhum outro biz pediu API moderna ainda
  pattern_emergente: false                 # 1 cliente. Mas é PRIMEIRO API driver moderno banco PJ — destrava pattern pra Bradesco/BB/Itaú/Santander futuros

acao_imediata:
  status: in_progress                      # backend live em prod; pré-req humano credenciais
  responder_cliente: 'Wagner mandou checklist credenciais (memory/sessions/2026-05-27-sicoob-api-credenciais-pedido.md). Kamila precisa pedir gerente Sicoob liberar Developer Portal + cert A1 (já tem na empresa pra NFe — serve)'
  task_mcp_id: US-FIN-044                  # US-FIN-044 in_progress no SPEC Financeiro

resolucao:
  data_resolvido: null                     # pendente smoke E2E com credenciais sandbox reais
  pr_link: '11 PRs mergeados hoje 2026-05-27 — #1718 #1720 #1722 #1724 #1725 #1728 #1730 #1734 #1737 #1740 #1742'
  cliente_confirmou: null                  # aguarda Kamila trazer credenciais + Wagner cadastrar + smoke E2E
  re_reclamacao: false
---

# Feedback — Kamila quer cadastrar Cobrança Sicoob API (webhook real-time)

## Resumo

Kamila (Admin#164 Martinho Caçambas) pediu via Wagner pra cadastrar cobrança bancária Sicoob no oimpresso usando **API REST + webhook real-time** em vez de CNAB 240 arquivo (workaround atual). Sicoob é banco operacional do Martinho; Kamila roda baixa manual 2× por dia hoje (importa arquivo retorno Sicoob internet banking).

## Contexto completo

Pedido entrou na sessão 2026-05-27 manhã. Wagner conversou com Kamila (provavelmente WhatsApp ou voz), depois passou pro Claude:

> "kamila quer cadastrar o cobrança pelo sicoob, quais dados precisa?"

Eu inicialmente assumi errado que Kamila era da ROTA LIVRE (Larissa biz=4). Wagner corrigiu durante a sessão — Kamila é Admin#164 do Martinho Caçambas, NÃO da ROTA LIVRE. Confusão pessoa↔empresa registrada em [PR #1734](https://github.com/wagnerra23/oimpresso.com/pull/1734) + [memory/sessions/2026-05-27-us-fin-044-sicoob-api-completion.md](../../../sessions/2026-05-27-us-fin-044-sicoob-api-completion.md).

Sicoob expõe API Cobrança Bancária v3 (REST + OAuth2 + mTLS) — Martinho já tem conta PJ cooperativada Sicoob ativa, basta contratar produto adicional no Developer Portal.

## Análise

### Por que isso aconteceu?

Operação real Kamila atualmente:
1. Cliente PJ frota fecha OS faturada → Kamila emite NF-e → gera boleto Sicoob CNAB (arquivo remessa enviado pra Sicoob via internet banking)
2. Sicoob processa registro do boleto (5-15 min)
3. Cliente paga boleto qualquer hora do dia (banco recebe)
4. Kamila baixa arquivo retorno Sicoob 2× por dia (manhã + tarde) → faz upload em `/financeiro/cobranca` → títulos baixam
5. Atraso entre pagamento real e baixa no sistema = 12-24h em pior caso

Com API + webhook: Sicoob notifica oimpresso em tempo real quando cliente paga → baixa automática. Zero trabalho manual da Kamila.

### Por que importa pra Kamila?

`kamila.yml` mostra pesos `density: 3` + `error_recovery: 3` — quer ver tudo controlado + recuperação rápida de erro. Importar retorno manual 2× ao dia é FRICÇÃO operacional crônica + risco de esquecer (cliente pagou mas sistema mostra atrasado, ela cobra cliente que JÁ pagou — embaraçoso).

JTBD existente em `kamila.yml`:
> "quando: fim do dia, fechar caixa — eu quero ver fluxo do dia: o que entrou, o que saiu, saldo final"

API + webhook entrega isso em tempo real, não no fim do dia.

### Quem mais pode sofrer disso?

- **Jair (dono Martinho)** — sim, indireto: relatórios financeiros ficam mais precisos pra ele acompanhar
- **Daniela (gerente operacional Martinho)** — sim, indireto: cobrança de clientes PJ frota fica mais precisa (não cobra cliente que pagou)
- **Larissa (ROTA LIVRE biz=4)** — NÃO usa Sicoob (vestuário, banco diferente)
- **Outros clientes legacy WR Comercial** — possivelmente Sicoob também (Vargas/Gold/etc), mas não validado

### Pattern emergente?

**Primeiro driver API moderna banco PJ brasileiro** que oimpresso vai entregar nesta categoria. Destrava pattern pra próximos:
- Bradesco API (cliente Larissa/outros eventualmente)
- BB API
- Itaú API
- Santander API
- Inter API mTLS PJ moderno (já existe driver Inter, mas formato diferente)

Padrão canon registrado em [memory/reference/cert-a1-icp-brasil-multi-uso.md](../../../reference/cert-a1-icp-brasil-multi-uso.md) — TODOS reusam `NfeCertificado`.

## Próximos passos sugeridos

- [x] Backend completo + deployed em prod (US-FIN-044 + US-FIN-046)
- [x] RUNBOOK + Charter + Pest cross-tenant
- [x] Wizard UI Sicoob no `/settings/payment-gateways`
- [x] Indicador cert A1 ativo reutiliza Fiscal (cliente NÃO precisa upload `.pfx` separado)
- [ ] **Kamila pede gerente Sicoob:** Developer Portal + scopes `boletos_inclusao/consulta/alteracao` + client_id + secret + convênio (~2-7 dias úteis depende cooperativa singular)
- [ ] **Wagner cadastra biz=164 no wizard sandbox** + smoke E2E real (emitir boleto fake → Sicoob retorna nossoNumero → simular pagamento → webhook chega → GatewayWebhookEvent gravado)
- [ ] Wagner aprova migração biz=164 `sicoob_cnab → sicoob_api` em produção (manter ambos 30d como fallback)
- [ ] Follow-up Wagner → Kamila: "tá ajudando? quanto tempo/dia ela economizou?"
- [ ] Se Kamila confirma → atualizar `resolucao.cliente_confirmou: true` + `data_resolvido`
- [ ] Eventual padrão: oferecer mesmo upgrade pra Larissa (Sicredi? outro banco?) — futuro

## Refs

- US-FIN-044 (this feedback origin)
- US-FIN-046 (fix bug arquitetural .pfx duplicado)
- US-FIN-047 backlog (extrair `NfeCertificado` pra módulo neutro)
- [memory/sessions/2026-05-27-sicoob-api-credenciais-pedido.md](../../../sessions/2026-05-27-sicoob-api-credenciais-pedido.md) — checklist Kamila
- [memory/sessions/2026-05-27-us-fin-044-sicoob-api-completion.md](../../../sessions/2026-05-27-us-fin-044-sicoob-api-completion.md) — 8 lições
- [memory/reference/cert-a1-icp-brasil-multi-uso.md](../../../reference/cert-a1-icp-brasil-multi-uso.md) — padrão canon próximos drivers banco
