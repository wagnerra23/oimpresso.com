# 11 — Metas de Negócio & Plano de Execução

> **Meta-âncora:** R$ 5 milhões/ano de faturamento (ADR 0022)
> **Status do prazo:** ⚠️ pendente — Wagner precisa fixar horizonte (12/24/36 meses)
> **Módulo responsável:** [`Copiloto`](requisitos/Copiloto/README.md) — esta doc vira **seed inicial** das metas + conversas quando o scaffold entrar.
> **Última atualização:** 2026-04-24

---

## 1. Onde estamos hoje (snapshot 2026-04-24)

| Dimensão | Valor |
|---|---|
| Businesses cadastrados | 56 |
| Com vendas registradas | 7 |
| Concentração | ROTA LIVRE (biz=4) = 99% do volume |
| MRR atual (R$/mês) | ⚠️ não medido — pendente |
| Faturamento últimos 12 meses | ⚠️ não medido — pendente |
| Churn histórico | ⚠️ não medido — pendente |
| Ticket médio por cliente | ⚠️ não medido — pendente |

**Gap até a meta (se pura recorrência SaaS):** R$ 5mi/ano ÷ 12 = **R$ 417k/mês de MRR**.

## 2. Cenários de composição para chegar a R$ 417k/mês

Três caminhos pra chegar na mesma receita, cada um com operação/funil/preço diferente:

### Cenário A — Massa (long tail)
- **1.000 clientes × R$ 417/mês**
- Ticket baixo, self-service, automação pesada
- Exige marketing digital + onboarding sem toque humano
- Requer operação 24×7 (1000 clientes = 10 tickets/dia mesmo com 1% SLA)
- **Viabilidade baixa pra Wagner sozinho — só faz sentido com equipe.**

### Cenário B — Médio (SMB)
- **250 clientes × R$ 1.668/mês**
- Ticket médio, comercial leve, CS reativo
- Requer ~5–10 onboardings/mês sustentados
- Permite alguma customização, mas não muita
- **Viabilidade média — precisa de 1–2 pessoas além de Wagner.**

### Cenário C — Enterprise (poucos grandes)
- **50 clientes × R$ 8.340/mês** (faixa de ERP mid-market)
- Ticket alto, venda consultiva, CS dedicado
- Requer parcerias/canais (contadores, associações)
- **Viabilidade alta pro perfil atual — Wagner já opera ROTA LIVRE nesse nível.**

### Cenário D — Misto (recomendado pra explorar primeiro)
- 30 enterprise × R$ 5k = R$ 150k/mês
- 120 médios × R$ 1,5k = R$ 180k/mês
- 200 pequenos × R$ 440 = R$ 88k/mês
- **Total: ~R$ 418k/mês** — diversificado, reduz dependência.

## 3. Três alavancas (trilhas) para execução

### Trilha 1 — Ativar a base ociosa (mais barato, primeiro passo)

**Hipótese:** há 49 businesses cadastrados sem vendas. Alguns são testes mortos, outros podem ser clientes que desligaram mas mantêm cadastro, outros podem ser "stand-by" esperando serem cobrados.

**Ação:**
- [ ] Auditar os 49 — vivos × mortos × dormentes (query: últimos logins, última venda, último contato).
- [ ] Para dormentes: campanha de reativação direta (Wagner liga).
- [ ] Para mortos: arquivar ou oferecer desconto de retorno.
- [ ] Meta conservadora: 10 reativações × R$ 500/mês = **+R$ 5k MRR** (primeiros 90 dias).

**Prioridade: ALTA** — alavanca mais barata e fala com quem já conhece o produto.

### Trilha 2 — Produto-âncora de aquisição

**Hipótese:** PontoWr2 é o único módulo vendável por **obrigação legal** (Portaria 671/2021, empresas 20+ funcionários). É a porta de entrada mais previsível. Uma vez dentro, o cliente tende a consolidar outros módulos (UltimatePOS vendas, Essentials/HRM, etc.).

**Ação:**
- [ ] **Completar Fase 1 do PontoWr2** — validar boot em staging, matar pendências do roadmap Fase 1 (`memory/07-roadmap.md`). Sem produto pronto, não há venda.
- [ ] Fazer **1 piloto real** (Fase 11 do roadmap) — Eliana/WR2 é candidata óbvia (ela é uma das personas-alvo).
- [ ] Pacote comercial claro: "Ponto eletrônico conforme Portaria 671 — R$ X/colaborador/mês". Preço simples, sem tabela complicada.
- [ ] Lista de 20 prospects qualificados (empresas 20+ func. na base de contatos do Wagner).

**Prioridade: ALTA** — maior potencial de ticket repetível e venda por obrigação. Mas depende do produto ficar production-ready.

### Trilha 3 — Upsell vertical nos clientes ativos

**Hipótese:** dos 7 clientes ativos, quantos usam só UltimatePOS base? Se 6 de 7 não têm Ponto/Grow/HRM/MemCofre, cada módulo adicional é receita marginal quase pura (CAC = 0, só dev/suporte).

**Ação:**
- [ ] Matriz clientes × módulos ativos — ver quem tem o quê.
- [ ] Para cada gap, propor módulo com trial 30 dias.
- [ ] **Grow** é prioridade (`preference_modulos_prioridade.md`) — começar por ele.
- [ ] **AiAssistance fica fora** (marcado pra descartar — não invista).

**Prioridade: MÉDIA-ALTA** — retorno rápido mas receita adicional é pequena (6 clientes × 2 módulos × R$ 500 = +R$ 6k MRR).

## 4. Riscos e premissas a validar

| Risco | Mitigação |
|---|---|
| ROTA LIVRE churnar antes de diversificar | Trilha 1+3 reduzem dependência; monitorar SLA agressivamente |
| PontoWr2 atrasar e a meta virar só "vontade" | Fase 1 tem que fechar em prazo; se não, rever ADR 0022 |
| Wagner operar sozinho não escala | Ao atingir ~R$ 50k MRR, contratar/terceirizar CS |
| Preço não validado | Experimentar em 2–3 prospects antes de publicar tabela |

## 5. Cadência sugerida de acompanhamento

- **Mensal:** faturamento realizado × meta mensal; churn; novos clientes.
- **Trimestral:** revisão deste documento; ajuste de prioridades por cenário.
- **Anual:** revisão do ADR 0022 (meta ainda faz sentido? horizonte ainda é esse?).

## 6. Próximos passos imediatos (Wagner + Claude — sessão seguinte)

1. [ ] Wagner confirma interpretação da meta (faturamento total × vertente específica).
2. [ ] Wagner fixa prazo (12/24/36 meses).
3. [ ] Levantar **faturamento real dos últimos 12 meses** (query no banco Hostinger — receita `ssh+mysql`, ver `reference_hostinger_analise.md`).
4. [ ] Rodar query "businesses ativos × módulos habilitados" para montar a matriz da Trilha 3.
5. [ ] Escolher UMA trilha para atacar primeiro e definir KPI de 30 dias.

---

**Criado:** 2026-04-24 (contexto: Wagner estabeleceu meta em conversa com Claude, ADR 0022).
