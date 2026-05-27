---
data: '2026-05-26'
hora: 'inferido'
canal: inferido                          # via Daniela → Wagner — não capturei literal

quem:
  persona_slug: jair-martinho            # pedido foi do dono (Jair) via Daniela
  cliente_real: martinho-cacambas
  business_id: null

o_que_disse:
  literal: '"queria registrar os caminhões do cliente, com placas. e cliente tem vários endereços (entrega, comercial), tem que escolher na nota qual usar. observação no cliente — ela anexa várias mensagens lá relativas ao cliente. anexos para poder colocar contrato social. no mínimo 3 telefones. email comercial e para notafiscal"'
  contexto: 'Wagner conversou com Daniela (gerente operacional), que mediou pedido do Jair (dono). Inferido pela quantidade de itens listados que é demanda real do Jair pra modernizar cadastro cliente PJ frota'

quando_no_produto:
  modulo: cliente
  tela: '/cliente/<id> drawer + Edit standalone'
  acao: visualizar-cadastro-pj-frota

job_por_tras:
  job: 'ver TUDO do cliente PJ frota numa tela só — frota + contatos + endereços + docs'
  motivacao: emocional                   # "se sentir no controle da operação"

workaround_atual:
  o_que_faz: 'abrir Modules/OficinaAuto separadamente pra ver frota — perde contexto'
  custo: '3-5 cliques extras + troca de contexto + risco esquecer info entre telas'

severity_nng: 3                          # major — impede tarefa frequente (ver cliente PJ frota)

frequencia:
  primeira_vez: false                    # demanda canalizada via Daniela acumulada
  recorrente_para_ela: 3                 # Daniela mediou 3 vezes pelo menos
  outros_clientes_tambem: []             # Wagner ainda não validou se Larissa/outros têm mesma fricção
  pattern_emergente: false               # 1 cliente (Martinho) por enquanto

acao_imediata:
  status: in_progress                    # PRs #1693-1706 atacam ondas 1+2 do pedido
  responder_cliente: 'Daniela já viu PR #1694 (aba Veículos) em prod'
  task_mcp_id: null

resolucao:
  data_resolvido: null                   # parcial — PR C múltiplos endereços ainda pendente
  pr_link: 'PRs #1693, #1694, #1695 mergeados 2026-05-26'
  cliente_confirmou: null                # follow-up Wagner pendente com Daniela após prod estabilizar
  re_reclamacao: false
---

# Feedback — Pedido Jair (via Daniela): frota + endereços múltiplos + obs + anexos + tel/email extras

## Resumo

Jair (dono Martinho Caçambas) pediu via Daniela (gerente operacional) modernização do cadastro de cliente PJ frota: aba Veículos no Cliente, múltiplos endereços (Comercial/Entrega/Casa) selecionáveis na NF-e, observações livres, anexos (contrato social), 3+ telefones, e-mail comercial + e-mail NF-e separados.

## Contexto completo

Demanda canalizada via Wagner → Daniela → Jair durante reunião de 2026-05-26. Daniela trouxe lista consolidada com 5 itens que ela vinha guardando das interações dela com Jair + uso operacional dela.

Wagner começou Onda 1 mesmo dia com 3 PRs paralelos:
- PR #1693 — fix Edit (CNPJ não carregava + Update Inertia quebrado)
- PR #1694 — aba Veículos no Show (reutiliza schema OficinaAuto.Vehicle)
- PR #1695 — 3º telefone + 2 emails extras (comercial + NF-e)

Próximas ondas pendentes:
- PR C — múltiplos endereços + selector NF-e (não iniciado)
- Onda 2 — observações já existem em DocumentsTab, anexos idem (descoberto durante análise)

## Análise

### Por que isso aconteceu?

Cliente PJ frota (caso Martinho) precisa visão consolidada que oimpresso tinha **fragmentada** entre módulos (Cliente + OficinaAuto separados). Demanda vem de uso operacional real — Daniela atende cliente PJ frota perguntando status + Jair quer ver portfólio cliente sem trocar de tela.

### Por que importa pra Jair?

Jair (persona dono) tem peso 3× em Brand confidence + Information hierarchy (framework 15D). Cliente PJ frota é receita maior — quer "parecer empresa séria" com cadastro completo + portfólio visível. Cliente importante reclamando que oimpresso não tem frota visível na ficha = sinal de fragilidade competitiva vs Bling/Tiny.

### Quem mais pode sofrer disso?

- Daniela (gerente operacional) — fricção dela JÁ documentada em persona.fricoes
- Kamila (admin/fiscal) — múltiplos endereços e e-mails ajudam emissão NF-e dela
- Larissa (vestuário) — não, vestuário PF balcão raramente tem frota

## Próximos passos sugeridos

- [x] Onda 1 mergeada (PRs #1693-#1695)
- [x] Aba Veículos visível em prod (#1694) — Wagner já validou via Chrome MCP
- [ ] **PR C — múltiplos endereços** + selector NF-e (Kamila precisa pra emissão fiscal)
- [ ] Follow-up Wagner → Daniela pra confirmar: "tá ajudando?"
- [ ] Se Daniela confirma → atualizar `resolucao.cliente_confirmou: true` + `data_resolvido`
- [ ] Apresentar pro Jair próxima visita Tubarão (dezembro 2026?)
