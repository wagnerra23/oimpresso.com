# Demo script reproduzível 15min — oimpresso

> Pra demo síncrona via Google Meet / Zoom / WhatsApp video.
> Audiência: dono(a) ou dono+gerente operações.
> Pré-requisito: dados de demo em `business_id=99` stub (NÃO usar biz=4 ROTA LIVRE — cliente real).
> Ferramentas abertas antes da call: Chrome com 3 abas pré-logadas (Visão Unificada, Repair, Jana chat).

---

## Pré-call (5 min antes — Wagner check obrigatório)

- [ ] Confirmar `business_id=99` carregado com dados de demo (clientes fake, ~30 vendas, ~15 NFes, 5 OS abertas)
- [ ] Browser em modo anônimo (sem cookies vazando dados de outro cliente)
- [ ] Som da notificação Slack/WhatsApp **silenciado** (vergonha vazamento PII)
- [ ] Tab do oimpresso já logada como user demo (`demo@oimpresso.com` / senha cofre)
- [ ] Card de pagamento NÃO compartilhar (cliente não pediu pricing ainda)
- [ ] Janela em **1280px wide** (gráfica típica, monitor pequeno — testa que tudo cabe)

---

## Roteiro — 15min

### Ato 1 — Abertura (1min)

> "{{primeiro_nome}}, antes de te mostrar tela: **15 minutos é pouco pra mostrar tudo.** Vou focar nos 3 momentos que cliente piloto disse 'agora faz sentido'. Se der dúvida no meio, pergunta — pode ser. Tá bom?"

Espera "ok". **Não começa demo sem permissão verbal.**

### Ato 2 — Visão Unificada Financeiro (3min) — Aha-moment 1

1. Abre `/financeiro/visao-unificada` (já logado)
2. **Aha-moment 1 (60s):** "**Veja aqui** — AR + AP + Saldo Asaas + projeção 30 dias, **uma tela**. Antes você abria quantas?" (espera resposta, anota)
3. Filtra por cliente "Cliente Demo Atrasado SA" → mostra aging vermelho 90+
4. Clica no número R$ [redacted Tier 0] a receber → drill-down lista as 3 faturas do cliente
5. **Não cliques em "abrir cobrança Asaas"** — só aponta: "esse botão dispara cobrança automática, faz parte do tier Pro"

### Ato 3 — NFe automática (3min) — Aha-moment 2

1. Volta pra venda Demo nº 217 já paga
2. Mostra timeline: "boleto pago às 14:32 → NFe enviada SEFAZ 14:32:08 → autorizada 14:32:14 → e-mail cliente 14:32:18"
3. **Aha-moment 2:** "**Ninguém clicou.** Isso é o `Listener` do pagamento Asaas chamando o pipeline NFe. **Quanto tempo seu pessoal gasta hoje fazendo essa parte manual?**" (escuta — anota)
4. Mostra DANFE PDF aberto (já gerado)
5. **Não tenta gerar nova NFe ao vivo** — risco de SEFAZ lenta / homologação fora. Mostra a já-gerada.

### Ato 4 — Jana IA chat (4min) — Aha-moment 3

1. Abre `/copiloto/chat`
2. Pergunta digitada na hora: "qual cliente atrasou mais nos últimos 90 dias?"
3. Jana responde: lista top 5, com valor e dias atrasados
4. **Aha-moment 3:** "**Note que ela buscou nos seus dados, não inventou.** É IA com memória do seu negócio."
5. Pergunta 2 (variar pelo perfil do prospect):
   - Se ele falou de fluxo de caixa: "quanto recebi essa semana comparado com a passada?"
   - Se falou de produção: "tem alguma OS travada há mais de 5 dias?"
   - Se falou de cliente: "qual cliente cresceu mais em volume últimos 3 meses?"
6. Resposta vem em ~3-5s. Se demorar mais: "o servidor de IA tá fora hoje, te mando o screenshot por WhatsApp depois" — **não trava na demo**.

### Ato 5 — Repair (2min)

1. Abre `/repair` (Kanban)
2. Mostra 5 colunas com 12 cards
3. **Drag-drop ao vivo:** arrasta OS Demo-105 de "produção" pra "acabamento" — status atualiza no DB de verdade (mostrar timestamp na coluna)
4. Filtra por cor: aponta a OS vermelha (atrasada) — "Filtro de SLA, decisor sabe onde tá pegando fogo."

### Ato 6 — Encerramento (2min)

1. **Não passa pricing na tela.** Diz: "Te mando os tiers por WhatsApp em até 1h, com a versão que faz sentido pro porte da {{empresa}}."
2. Pergunta de fechamento: "**O que mais te chamou atenção dos 3 momentos?**" (escuta — anota)
3. Two-option close: "Próximo passo seria **migrar dados de teste seus pra ambiente sandbox** (te mostro isso na tela em 30min outro dia) **OU mandar proposta escrita** com cronograma. Qual encaixa?"
4. **Encerra na hora marcada.** Se passar dos 15min, perde respeito.

---

## Trapas conhecidas — NÃO mostrar nem mencionar

| Feature | Por que não mostrar | Workaround |
|---|---|---|
| **Officeimpresso (superadmin)** | Tela legacy 3.7→6.7 restaurada, design feio | Não abre. Se perguntar, "é tela admin interna, não cliente" |
| **welcome.blade.php** legacy | Obsoleto | Já redirecionou pra Cms — nem aparece |
| **PontoWr2** módulo | Específico de cliente WR2, não cabe pra gráfica | Não menciona |
| **Form:: shim migration** | Feito interno, não vende | Nada |
| **Charter / ADR / Constituição v2** | Governança interna, não interessa cliente | Só menciona se pedirem "como vocês organizam código" — vira diferencial enterprise |
| **Auto-emissão NFe (flag .env)** | Liga só depois de smoke fiscal homologação | Demo mostra como **já tendo rodado**, não liga ao vivo |
| **Vendas reais em biz=4 ROTA LIVRE** | PII, tier 0 violation | Sempre biz=99 demo |

---

## Plano B — se o ambiente cair

- Vídeo pré-gravado de 5min cobrindo Atos 2-4-5 (gravar Loom semanalmente, manter atualizado)
- Slides 8 telas estáticas (PNG) caso até o vídeo falhe
- "Te mando link assíncrono de demo gravada **agora** e remarco call ao vivo essa semana" — never empurra demo travada

---

## Pós-call — 1h depois (Wagner faz)

1. WhatsApp pro prospect: "{{primeiro_nome}}, segue resumo + tier proposto pra {{empresa}}"
2. Anexar: 1 onepager mais relevante (não os 4) + tier escolhido + 2 datas de follow-up
3. Cria task no MCP: `tasks-create` modulo `Sales` ref `{{empresa}}` próximo passo `{{data}}`
4. Se prospect entusiasmado → marcar follow-up em 3 dias. Se morno → 7 dias. Se frio → 14 dias e depois deixar pra lá.
