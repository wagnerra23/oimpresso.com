# Demo script — Reunião Martinho Caçambas · 13/maio 10h

> Roteiro 15-20min pra Wagner conduzir presencialmente. Ordem importa: começa com o **mockup personalizado** (gancho emocional), passa por **provas concretas em prod** (credibilidade), termina com **roadmap + pricing + descoberta** (próximo passo).
>
> ⚠️ **REGISTRO HISTÓRICO 2026-05-13 — vocabulário desatualizado pós-[ADR 0194](../../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) (2026-05-26).** Mockup KPIs "91 caçambas cadastradas / 23 locadas / 4 manutenção / 3 atrasada" foram baseados em leitura errada (Martinho NÃO é locação caçamba container — é mecânica pesada caminhão basculante CNAE 4520). Preserva-se este histórico do roteiro original que rodou na reunião 10h 2026-05-13 (Martinho aceitou migração). Próximas apresentações pra Martinho (operação real) usam métricas reais: 91 **caminhões de clientes** atendidos pela oficina · OSs abertas/em-serviço/concluídas/aguardando-peça · faturamento R$ por mês (não R$ por diária). Vocabulário canon: peça hidráulica · PTO · hora-trabalho.

---

## ⏱ Pré-flight 5min antes da reunião

- [ ] Abrir **mockup** local: `memory/requisitos/OficinaAuto/demo-martinho-2026-05-13/mockup.html` (já tá no Launch preview do Claude Code — print/screenshot ou abre no browser)
- [ ] Login em prod biz=1: `https://oimpresso.com` (sua sessão admin)
- [ ] 3 abas Chrome prontas:
  1. `/sells` (Grade Avançada já ativa do dia anterior)
  2. `/oficina-auto/vehicles` (V0 scaffold real)
  3. `/whatsapp` (inbox)
- [ ] Imprimir/PDF charter 1-pager (`charter-1pager.md`)
- [ ] Levar Charter impresso pra deixar com ele

---

## 🎬 Roteiro (15min total)

### 1. Abertura (2min) — "Você é nosso piloto qualificado"

**Mostrar:** charter 1-pager impresso + monitor com mockup HTML

**Falar:**
> "Martinho, antes da gente falar de qualquer feature, queria te mostrar uma coisa importante: amostramos os 4 maiores clientes saudáveis do OfficeImpresso e **2 deles são oficina** — você e o Vargas (recapagem). Isso nos deu **sinal qualificado** pra construir um módulo dedicado pra esse setor. Você é o nosso **piloto #1** do **Modules/OficinaAuto**. Deixa eu te mostrar o que já está pronto e o que vamos fazer pra você nos próximos meses."

**Ponto de virada:** ele percebe que NÃO é só mais um cliente — é o **caso de uso central** do produto novo.

---

### 2. Mockup personalizado (3min) — gancho emocional

**Mostrar:** abrir `mockup.html` em tela cheia (F11)

**Apontar:**
- "Olha aqui no topo: '91 caçambas cadastradas · 23 locadas · 4 em manutenção'. Esses números **virão direto do seu Office Impresso** quando importarmos."
- KPI cards (Disponíveis 64 / Locadas 23 / Manutenção 4 / Atrasada 3) — "visão de 1 segundo do seu negócio inteiro"
- Tabela com placas reais BR + clientes fictícios — "essa é a tela que você vai usar todo dia. Veja a CC-003 destacada em vermelho — locação atrasada há 15 diárias, R$ 2.250 a receber. **O sistema avisa antes de você perder dinheiro**."
- WhatsApp inbox lateral — "atendimento integrado. Construtora Aliança pediu prorrogação? Você responde aqui sem sair do sistema."
- Pipeline FSM (CC-018 manutenção) — "cada caçamba tem histórico auditado: quando entrou na oficina, quem trabalhou, previsão de saída. **Ninguém perde caçamba**."
- Roadmap teaser embaixo — "esses 5 itens são o nosso compromisso com você."

---

### 3. Prova de vida em prod (5min) — credibilidade

> "Esse mockup é simulação. Mas tem coisa real funcionando AGORA. Deixa eu te mostrar."

> ⚠️ **IMPORTANTE — incluir ESTOQUE + VENDAS + BOLETOS na demo** (descoberta sessão 12/maio: Martinho pergunta sempre sobre esses 3 — não é só locação caçamba).

#### 3-prep. Estoque + Vendas + Boletos (PRINCIPAL — começa por aqui!)

- **`/products`** Estoque: "olha aqui — você cadastra peça/lona/material com unidade (kg, un, m), estoque mínimo, preço custo + venda. Sistema avisa quando vai acabar. Multi-variação (peça com 3 cores)"
- **`/pos/create`** Venda balcão: "cliente entra na sua loja querendo lona — você bate 1, 2 produtos, recebe na hora ou faz boleto. Gera NFC-e direto"
- **`/sells`** Vendas faturadas: "venda a prazo — Grade Avançada que mostra todas, filtros por mês/cliente/status pagamento, totalizadores. KPI 'A receber' mostra inadimplência em tempo real"
- **`/recurring-billing`** Boletos: "para mensalidade de locação caçamba — cadastra cliente + valor + dia vencimento, sistema emite boleto automático todo mês via Inter PJ. Cliente paga, baixa automática"
- **`/financeiro`** Consolidado: "tudo junto — saldo Inter PJ, contas a receber, contas a pagar, extrato"

#### 3a. Modules/OficinaAuto V0 (1min)
- **Aba 2:** `/oficina-auto/vehicles`
- "Esse é o módulo OficinaAuto **rodando em produção desde 11/maio**. 8 telas (Veículos + Ordens de Serviço × Listar/Criar/Ver/Editar). 16 testes automatizados. **Pronto pra você cadastrar suas caçambas amanhã se quiser**."
- Demonstrar: `+ Nova caçamba` → mostrar formulário (placa, descrição, capacidade nullable)

#### 3b. Fluxo de venda canônico FSM (2min)
- **Aba 1:** `/sells` (Grade Avançada)
- "Esse é o **pipeline de venda padrão**. Veja: cliquei numa venda da minha empresa." → abrir uma venda → drawer abre
- Apontar tab "Pipeline FSM": "esse é o **estado atual da venda** (em produção / pronto / faturada / paga / entregue). Cada botão dispara um efeito real: estoque reserva, NFe emite, cobrança gera. **Auditável** — log mostra quem mudou o quê e quando."
- Tab "Histórico": "vejo timeline de todas as transições. Útil quando cliente reclama 'mas eu mandei aprovar' — você vê hora exata."

#### 3c. WhatsApp Inbox (1min)
- **Aba 3:** `/whatsapp`
- "Atendimento real. Aqui você responde clientes, anexa boleto, manda foto. Tem **macros e respostas rápidas** (`/lembrar /corrigir /lembrete`). Tem **SLA + escalation** — se cliente espera mais de X minutos, alerta supervisor."
- "Hoje você atende por celular separado, certo? Aqui tudo num lugar só, atendentes podem ser vários."

#### 3d. Financeiro consolidado (1min)
- Sidebar `/financeiro`
- "Visão única: contas a receber + pagar + boletos Inter PJ + saldo. **Conciliação automática** — boleto pago → venda atualizada → estoque consumido → tudo automático."

---

### 4. Roadmap específico Martinho (3min) — visão de futuro

**Mostrar:** mockup novamente, role até o card azul "Roadmap específico Martinho Caçambas"

**Ler com ele:**
1. ✅ V0 fundação — pronto
2. ◐ V1 importer — "vamos rodar um script Python que lê seu banco Firebird e migra as 91 caçambas + 44 mil vendas pro novo sistema. **Idempotente**: se der erro, reexecuta sem duplicar nada. **Dry-run primeiro** pra você validar."
3. ○ V2 NFSe locação — "emissão fiscal automática pra cada locação (CNAE 4581-4/00)"
4. ○ V3 cobrança Inter PJ — "boleto/PIX automático no início da locação, baixa automática quando cliente paga"
5. ○ V4 IA Jana — "assistente que cobra inadimplente via WhatsApp educadamente, classifica priorização, responde FAQ"

> "Cronograma realista pro V1 importer: **2 semanas**. NFSe + Inter: **+ 3 semanas**. IA Jana: **+ 4 semanas**. Total: **2 meses pra ter tudo isso rodando pra você**."

---

### 5. Descoberta (3min) — pegar dados PRA validar pricing

**Perguntas — ANOTAR respostas:**

| Pergunta | Por que importa |
|---|---|
| Quanto você paga hoje no Office Impresso? (mensalidade / suporte / certificado) | Baseline pricing |
| Quantos atendentes você tem? Eles usam o Office Impresso direto? | Sizing licenças |
| Que dor MAIS aperta hoje? (1-3) | Priorizar V2/V3/V4 |
| Já perdeu cliente por demora atendimento WhatsApp? | Validar valor inbox |
| Já perdeu caçamba (ou demorou pra recuperar)? | Validar valor rastreio |
| Quanto pagaria por mês pra ter tudo isso? (sem comprometer) | Anchor pricing |
| Já usa boleto Inter? | Provedor pagamentos |
| Tem contador? Como ele acessa hoje? | Integração contábil |

---

### 6. Fechamento (2min) — próximo passo concreto

**3 opções progressivas (do menos comprometedor pro mais):**

**Opção A — Beta gratuito 30 dias (zero risco pra ele)**
- Importamos suas 91 caçambas em ambiente teste
- Você usa em paralelo ao Office Impresso por 30 dias
- Sem custo, sem cancelamento
- Vencendo prazo: decide migrar OU continua Office Impresso

**Opção B — Migração faseada (recomendado se confiança alta)**
- Vamos fazendo features V1→V4 em 2 meses
- Office Impresso continua rodando até feature equivalente estar pronta
- Cobrança só começa em V2 (NFSe LIVE)

**Opção C — Pacote pequeno empresa** (se ele perguntar valor)
- Mostra MATRIZ-ROI 1-pager
- Pricing tier sugerido (~R$ 300-500/mês baseado em 91 caçambas + 1 atendente WhatsApp + módulos OficinaAuto + Financeiro + WhatsApp + Jana básico)

---

## 🛑 Coisas pra NÃO fazer/dizer

- ❌ Não promete data se não tiver certeza
- ❌ Não fala "vai substituir Office Impresso amanhã" — migração é **gradual e validada**
- ❌ Não menciona Vargas pelo nome (concorrente dele potencial — referir como "cliente recapagem")
- ❌ Não cota fechado sem ouvir as dores dele primeiro (descoberta antes pricing)
- ❌ Não promete IA Jana hoje — V4 é último, depois de V1+V2+V3 estabilizado
- ❌ Não menciona ROTA LIVRE como prova social (ele é vestuário, vertical diferente — pode confundir)

---

## 📞 Pós-reunião (mesmo dia)

- [ ] Anotar respostas dele (criar `discovery-martinho.md` na mesma pasta)
- [ ] Se aceitou Opção A: criar US-OFICINA-002 "Importer Martinho Firebird→MySQL dry-run" (P0)
- [ ] Se mostrou interesse: enviar charter PDF + mockup print por WhatsApp
- [ ] Se hesitou: criar task follow-up "Ligar Martinho 7 dias úteis" no MCP
- [ ] Atualizar `memory/research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md` com sinais novos (status comercial atualizado, dores reais)

---

## 🎯 Critério de sucesso da reunião

**Mínimo (não te demitir):** Martinho diz "interessante, vou pensar" + aceita receber proposta por escrito.

**Bom:** aceita Opção A (beta 30d) — zero risco, máximo valor pra ele e pra gente (pega dados reais cedo).

**Ótimo:** aceita Opção B — começa pagar Maio/Junho 2026 quando V2 LIVE.

**Excepcional:** indica outro cliente caçambas/oficina pra você apresentar.
