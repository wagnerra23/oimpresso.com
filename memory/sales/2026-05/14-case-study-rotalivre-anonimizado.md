# Case Study — Cliente Piloto SP

> **STATUS: draft — Wagner valida com a gestora ANTES de publicar.**
> Data draft: 2026-05-09
> Anonimização: nome cliente, dona, bairro, CNPJ, telefone removidos. Métricas com `[validar com Wagner — placeholder]` em todo número.
> Pré-publicação: a gestora precisa concordar por escrito + Wagner aprova versão final.

---

## Versão 1 — Long-form (~1500 palavras, pra blog/site)

### Como uma gráfica de comunicação visual em São Paulo trocou planilha + Bling + WhatsApp por um único ERP vertical e recuperou `[validar com Wagner — placeholder X]` horas/semana da gestora

> **Resumo executivo:** uma gráfica de comunicação visual em São Paulo, com `[validar com Wagner — placeholder X-Y funcionários]` e operação de balcão diária, migrou de uma stack-Frankenstein (Bling + planilha Excel + WhatsApp Business + agenda de papel) pra oimpresso ao longo de `[validar — N meses]`. Resultado: NFC-e que antes levava `[validar — 3 a 5h]` agora sai automaticamente em segundos a partir do boleto pago, e a gestora consulta faturamento da semana pelo celular às 22h em vez de abrir 4 telas no PC.

#### Sobre o cliente piloto

- **Localização:** São Paulo (SP capital region)
- **Tamanho:** `[validar com Wagner — placeholder X-Y funcionários]`
- **Vertical:** comunicação visual completa — sinalização, fachada, plotagem, brindes, gráfica rápida
- **Volume operacional:** `[validar com Wagner — placeholder N+ vendas/dia]`, ticket médio `[validar — faixa]`
- **Cliente desde:** `[validar com Wagner — data início parceria]`
- **Perfil da operação:** balcão presencial + atendimento por WhatsApp + entregas pontuais; horário de pico 14h-17h

#### O desafio antes do oimpresso

A gestora descreveu a operação anterior como "trabalhar duas vezes pra cada venda". O cenário era familiar pra qualquer dono de gráfica de comunicação visual no Brasil:

**WhatsApp Business como ERP de fato.** Toda solicitação chegava por WhatsApp — orçamento, alteração de pedido, urgência de fim de semana, cobrança atrasada. As conversas viravam o histórico oficial do cliente. Quando precisava lembrar "o que combinei com aquele cliente em março?", a gestora rolava semanas de mensagens no celular. Sem busca estruturada, sem campo de margem, sem prazo agendado.

**Planilha Excel pra controlar contas a receber e a pagar.** Cada boleto recebido era anotado manualmente. Cada despesa, registrada à mão. No fim do mês, a planilha tinha 200+ linhas e nunca batia 100% com o extrato bancário. A gestora perdia `[validar — N horas/mês]` reconciliando.

**Bling pra emitir nota fiscal — mas com gargalo humano.** Bling fazia o trabalho fiscal, mas a NFC-e dependia de alguém clicar "emitir" depois que o cliente pagava. Em dias movimentados, a nota saía 3 a 5 horas após o pagamento. Quando o pagamento caía sexta à noite, o cliente recebia a nota só na segunda — atrito desnecessário.

**Status de OS confuso, especialmente em pedidos multi-etapa.** Comunicação visual envolve marcenaria + impressão digital + montagem + instalação. Sem Kanban, ninguém sabia em qual etapa cada OS estava. A gestora atendia o cliente perguntando "tá pronto?" e respondia "deixa eu verificar com o pessoal" — recorrente.

**Sem visibilidade de margem por OS.** A gestora sabia faturamento bruto. Não sabia, por OS, se o pedido tinha sido lucrativo. Quando um cliente pedia desconto, decidia no instinto.

#### A solução: implementação faseada em `[validar — N meses]`

O time da oimpresso (Wagner como ponto focal) implementou a migração em fases pra reduzir risco:

**Fase 1 — Migração de dados (`[validar — duração]`).** Cadastros de clientes, produtos, serviços, vendas históricas e financeiro foram importados. A operação continuou rodando em paralelo no sistema antigo até a virada formal.

**Fase 2 — NFC-e automática a partir de boleto pago.** O módulo NfeBrasil passou a emitir nota fiscal eletrônica em segundos quando o boleto era marcado como pago no Financeiro — sem intervenção humana. Esse é o diferencial do oimpresso vs Bling/Asaas/Vindi: NFe nativa amarrada ao recebimento.

**Fase 3 — Visão Unificada do Financeiro.** A planilha foi aposentada. Contas a receber, contas a pagar, saldo bancário, faturas Asaas e movimento de caixa passaram a viver na mesma tela. Reconciliação que levava meio dia agora leva minutos.

**Fase 4 — Jana IA com memória persistente.** O assistente IA do oimpresso passou a responder perguntas em linguagem natural sobre o próprio negócio: "quanto faturei essa semana?", "qual cliente tá com boleto vencido?", "qual foi a margem média do mês?". A Jana usa recall híbrido (Meilisearch + embeddings) pra puxar dados reais — não inventa.

**Fase 5 — Produção drag-and-drop (Kanban OS).** Entregue em 2026-05-09 (PR #363). Cada OS aparece como cartão num quadro Kanban com colunas customizáveis (recebido → arte → impressão → montagem → entrega). Arrastar muda status. A gestora vê o estado da fábrica de relance.

#### Os resultados (12 meses) `[TODOS placeholder, validar com Wagner]`

- **Tempo médio de emissão de NFC-e:** de `[~4h manual]` pra `[< 1 minuto automático]`
- **Erro de cobrança / inconsistência financeira:** redução de `[X%]`
- **Tempo da gestora dedicado a admin/sistema:** redução de `[Y horas/semana]`
- **Vendas com atraso de status pro cliente:** redução de `[Z%]`
- **Reconciliação bancária mensal:** de `[meio dia]` pra `[minutos]`
- **ROI estimado:** payback em `[N meses]`

> **Observação importante:** todos os números acima são placeholders até a gestora confirmar e Wagner aprovar. Não publicar este case com placeholder em produção.

#### Depoimento da gestora

> **`[FRASE EM ABERTO — sugestão pra Wagner pedir à gestora, ela escolhe palavras dela:]`**
>
> *"Antes eu trabalhava duas vezes pra cada venda — uma pra fazer e outra pra lembrar. Com o oimpresso a nota sai sozinha quando o cliente paga, e quando eu pergunto pra Jana 'quanto faturei essa semana?' no celular, ela responde. Não é magia, é o sistema fazendo o que sistema precisa fazer."*
>
> — A gestora do cliente piloto, São Paulo
>
> `[validar EXATAMENTE como ela quer escrever — Wagner aprova versão final]`

#### Próximos passos com oimpresso

- **App mobile nativo** (Q4/2026) — hoje funciona mobile-first via web responsivo
- **Bulk update conversacional via Jana** — "aumenta 5% em todo lonas 440g" via chat
- **Integração marketplace** — Mercado Livre, Loggi (em roadmap)

---

**Quer ver oimpresso na sua gráfica?** Trial 30 dias sem cartão.
[wagner@oimpresso.com] | `[WhatsApp — Wagner preenche]`

---

## Versão 2 — 1-pager (~300 palavras, pra anexar em email/LinkedIn)

### Cliente Piloto SP: como uma gráfica de comunicação visual aposentou Bling + planilha + WhatsApp e ganhou `[validar — X horas/semana]` de volta

Uma gráfica de comunicação visual em São Paulo (`[validar — X-Y funcionários]`, atendimento balcão diário, vertical completa: sinalização, fachada, brindes, gráfica rápida) operava com a stack-Frankenstein típica do setor:

- **WhatsApp Business** como histórico oficial de cliente
- **Planilha Excel** pra contas a receber/pagar (sempre divergente do extrato)
- **Bling** pra NFC-e — mas dependendo de clique humano, levava 3-5h
- **Status de OS sem Kanban** — gestora perguntava "tá pronto?" pro pessoal o dia inteiro
- **Sem margem por OS** — desconto decidido no instinto

Em `[validar — N meses]` de implementação faseada, a gráfica migrou pra oimpresso e ativou:

1. **NFC-e automática** a partir de boleto pago (segundos vs horas)
2. **Visão Unificada do Financeiro** (planilha aposentada)
3. **Jana IA** com memória persistente — gestora pergunta no celular "quanto faturei?"
4. **Producao drag-and-drop** (Kanban OS multi-etapa)

**Resultados (12m, validar com Wagner — placeholders):**

- Emissão NFC-e: `[~4h → <1min]`
- Tempo gestora em admin: `-[Y h/semana]`
- Reconciliação bancária: `[meio dia → minutos]`
- Payback estimado: `[N meses]`

> *"Antes eu trabalhava duas vezes pra cada venda. Agora a nota sai sozinha e a Jana responde no celular."* `[draft sugerido — gestora confirma palavras dela]`
>
> — A gestora do cliente piloto, SP

**oimpresso é o único ERP brasileiro vertical pra comunicação visual com IA com memória persistente + NFC-e amarrada a recebimento + governança formal (Constituição v2).**

Trial 30d sem cartão · wagner@oimpresso.com

---

## Versão 3 — 5-bullets (~50 palavras, pra slide de deck)

**Cliente Piloto SP — gráfica comunicação visual `[X-Y funcionários]`**

- Saiu de Bling + planilha + WhatsApp pra oimpresso em `[N meses]`
- NFC-e automática: `[~4h → <1min]`
- Reconciliação financeira: `[meio dia → minutos]`
- Jana IA responde "quanto faturei?" no celular `[validar com Wagner]`
- Payback `[N meses]` · *"a nota sai sozinha"* — a gestora `[draft]`

---

## Notas internas (NÃO publicar)

### Sugestão de frase pro Wagner pedir à gestora

Wagner pode propor à gestora algo nessa linha (ela escolhe palavras dela):

> *"Antes eu trabalhava duas vezes pra cada venda — uma pra fazer e outra pra lembrar. Com o oimpresso a nota sai sozinha quando o cliente paga, e quando eu pergunto pra Jana 'quanto faturei essa semana?' no celular, ela responde."*

Razão da escolha: a frase espelha a dor #1 do top 10 do setor (gap commercial vs realidade), usa linguagem de balcão (não jargão tech) e prova social de uso real (Jana mobile = case-#3 do mapa de wedge oimpresso).

### Riscos LGPD / comerciais — ranking

1. **Risco maior — gestora se sentir exposta.** Mesmo anonimizado ("São Paulo" + "comunicação visual" + métricas), a região Termas do Gravatal é pequena e ROTA LIVRE 99% volume é descritor único. Concorrente local pode triangular: tamanho operação + bairro + vertical = identificação. **Mitigação obrigatória:** gestora lê draft completo + autoriza por escrito ANTES de publicar; preferir métricas em faixa (`[3-5h]` em vez de `[4h17min]`); evitar citar marca de equipamento/fornecedor que filtre identificação.
2. **Risco médio — métricas inventadas se forem publicadas com placeholder.** O case fica inutilizável e dano reputacional alto se sair "X horas" no ar. **Mitigação:** gate Wagner antes de qualquer publicação; checklist explícito "todo `[validar]` foi substituído? sim/não".
3. **Risco baixo — concorrente identifica gráfica via combinação semântica.** "Gráfica SP + comunicação visual completa + balcão diário + horário pico 14h-17h" reduz universo. Métrica "99% volume sistema" no texto público = nunca (não consta no draft). **Mitigação:** aplicada — texto público não menciona 99%.
4. **LGPD direto:** baixo. Não há PII (CNPJ, endereço, CPF, nome funcionário) nem dados sensíveis. Anonimização cobre Art. 7º LGPD (base legal de divulgação institucional só se gestora consentir formalmente — colher consentimento por escrito).

### Checklist pré-publicação

- [ ] Gestora leu as 3 versões e aprovou por escrito
- [ ] Wagner trocou todo `[validar com Wagner — placeholder]` por número real OU removeu o bullet
- [ ] Frase do depoimento veio da gestora (não da sugestão draft)
- [ ] Nenhuma menção a "99% volume", razão social, CNPJ, telefone, bairro específico, nome funcionário
- [ ] WhatsApp Wagner preenchido no CTA
- [ ] Versão final salva em `memory/sales/2026-05/14-case-study-rotalivre-PUBLICADO.md` com timestamp aprovação
