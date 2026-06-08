# Cold call script 90s — oimpresso

> Pra ligação fria pra dono(a) de gráfica rápida / comunicação visual.
> Objetivo: agendar demo de 15min OU fazer demo curta na hora se a porta abrir.
> Tom: direto, respeitoso, permission-based. Nunca empurrar.

---

## Estrutura 90s

### Abertura permission-based — 15s

> "Oi, {{primeiro_nome}}? Aqui é o {{remetente}} do oimpresso. **A ligação não é venda agora — é 90 segundos pra ver se faz sentido a gente conversar de novo. Posso continuar?**"

**Pause de verdade.** Espera "pode" ou "fala" antes de seguir. Se "agora não", pergunta "tem um horário melhor essa semana?" e desliga educado.

---

### Pitch valor — 30s

> "A gente fez um ERP vertical pra gráfica de comunicação visual — plotter, fachada, sinalização, esse tipo de operação. **Três coisas que ninguém mais faz junto:**
>
> Um — quando o boleto do cliente cai, a NFe sai sozinha, sem alguém clicar.
>
> Dois — tem uma IA chamada Jana que responde no chat tipo 'qual cliente atrasa mais', 'quanto fechei esse mês', sem precisar abrir relatório.
>
> Três — a tela de produção é drag-drop, igual Trello, do orçamento até a entrega.
>
> Já tá rodando em gráfica de São Paulo, faz [XX]/mês em vendas — placeholder, validar."

---

### 2 perguntas SPIN — 30s

**SPIN 1 (Problema):**
> "Pra entender se faz sentido pra vocês — hoje, quando um boleto é pago, **quem emite a NFe**? É manual ou tem alguma automação?"

(escutar — anotar nome do sistema mencionado: Bling, Tiny, Conta Azul, Omie, Asaas, planilha)

**SPIN 2 (Implicação):**
> "E quando você quer saber **quanto fechou no mês ou qual cliente tá atrasando**, você abre relatório, planilha, ou pergunta pra alguém da equipe?"

(escutar — esse é o gancho pra Jana IA)

---

### Pedido próximo passo — 15s

> "Olha, {{primeiro_nome}}, pelo que você descreveu **faz sentido sim a gente conversar 15 minutos com a tela na sua frente**. Posso te mandar dois horários por WhatsApp agora? Quinta de manhã ou sexta à tarde, qual encaixa melhor?"

**Two-option close.** Não pergunta "você tem disponibilidade?" — oferece dois slots.

---

## Objection handling — 8 objeções

| # | Objeção | Resposta (2-3 frases) |
|---|---|---|
| 1 | **"Tá caro."** | "Entendo. **Antes de falar de preço, posso te mostrar o que substitui?** Cliente típico hoje paga Bling + Conta Azul + Asaas separados — geralmente o oimpresso fica abaixo da soma dos três. Te mando o cálculo por WhatsApp depois da demo, fechado?" |
| 2 | **"Já tenho Bling/Tiny."** | "Bling é bom pra emissor genérico. A pergunta é: **ele sabe calcular m² de adesivo, gerenciar produção em fila por máquina, e emitir NFe a partir de boleto pago no Asaas?** Se sim, não precisa trocar. Se não, vale 15 min pra comparar lado a lado." |
| 3 | **"Não preciso de IA."** | "Justo. A Jana é só uma camada — **o ERP funciona inteiro sem ela**. A diferença é que quando você quiser saber 'quanto cliente X me deve', em vez de abrir relatório, você pergunta no chat. **É opcional, e vem desligada.** Mas a maior parte dos donos liga depois da primeira semana." |
| 4 | **"NFe meu contador faz."** | "Faz no fim do mês, certo? **O problema é o cliente final que pediu nota agora e seu contador só processa quinta-feira.** Com a gente sai automática quando o boleto cai — o contador continua recebendo o XML pra escrituração. **Ninguém perde, ele agradece.**" |
| 5 | **"Vou pensar."** | "Claro. Pra eu te ajudar a pensar, **o que ficou em dúvida concretamente** — preço, migração, ou alguma feature específica? Te mando o que faltou por WhatsApp pra você decidir com calma." |
| 6 | **"Migrar é dor de cabeça."** | "É o medo certo. Por isso a gente faz migração em 2 semanas com **rollback garantido nos primeiros 30 dias** — se não rodar, voltamos os dados pro seu sistema atual sem custo. Quer ver o checklist de migração antes de decidir?" |
| 7 | **"Não confio em nuvem brasileira."** | "Justo — backup do banco é seu, **export SQL completo a qualquer momento** sem pedir pra suporte. Hospedagem é Hostinger BR + servidor próprio Proxmox aqui em SP. Se quiser auditar, te mando o ADR público de infraestrutura." |
| 8 | **"Manda por email que eu vejo."** | "Mando. Mas pergunta sincera: **quantos PDFs de ERP você abriu esse mês**? Se for igual a maioria, nenhum. **15 minutos com a tela rodando vale 3 PDFs**. Posso te marcar quinta cedo? Se não rolar, mando o material igual." |

---

## Notas

- **Nunca prometer feature que não existe.** Lista canônica em `memory/what-oimpresso.md` + ADRs.
- **Se o lead pedir cliente referência:** "Tem cliente piloto em SP rodando há mais de [XX meses — validar]. Posso pedir autorização e conectar vocês depois da demo."
- **Se chegar em preço sem ter visto demo:** "Te mando os tiers por escrito depois da demo — preço sem contexto vira número solto. Fechado?"
- **Se falar mal de concorrente:** evita. "Bling é ferramenta consolidada, oimpresso é vertical. Comparação justa só com tela aberta."
