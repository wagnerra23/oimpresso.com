# Playbook migração Calcme → oimpresso

> Documento operacional — não marketing. Tom realista, cita gaps abertamente.
> Data: 2026-05-09 · Owner: Wagner [W] + suporte Maiara [M] · Revisão: por cliente
> Sinais públicos base: 4 RA Calcme + reviews PrintPlanet + post oficial reajuste 2026 ([fonte](../../research/2026-05-prospeccao/03-concorrentes-alfa-visua-calcgraf-reviews.md))

---

## Perfil do prospect típico

**Demografia:**
- **Porte:** 1-10 funcionários, 1-3 plotters/impressoras, 1 dono-operador
- **Faturamento:** R$ 30k-200k/mês
- **Segmento:** comunicação visual (lonas, fachadas, adesivos, banners), gráfica rápida, brindes — pode ter marcenaria/MDF se Calcme3D pesou na compra
- **HQ:** SP/SC/RS/MG concentra (base Calcme em Blumenau-SC)
- **Tempo no Calcme:** 2-18 meses — o **doloroso é o cliente entre mês 3 e mês 12** (passou da fase trial-amor, ainda não amortizou setup, sente atrito diário)

**Sinais comportamentais (tirados das 4 RA + post reajuste 2026):**
- Pesquisou trial 7d → fechou contrato em 1-2 semanas → mês 3 começou a sentir desencontro promessa vs realidade
- Sente que **importação de produto é manual** (RA literal: *"Fui informada na captação que poderia importar os produtos por nota fiscal e descobri que tenho que inserir um a um"*)
- **PDV duplica valor** (RA literal: *"problemas com o PDV que duplicava os valores"*) — perde venda ou cobra cliente errado
- **Suporte empurra chat/vídeo** quando precisa de telefone urgente
- **Sem reembolso** (só crédito) — sente-se preso financeiramente
- **Reajuste 2026** mexeu com base — mensagem oficial pediu "diálogo aberto"; cliente borderline está saindo

**Não-perfil (pular esses):**
- Quem usa Calcme3D pesado pra marcenaria com renderização → oimpresso não tem 3D nativo, gap óbvio
- Quem amou Chatme (WhatsApp nativo) e usa intensamente → oimpresso tem fluxo diferente, atrito de mudança alto
- Quem tem >50 funcionários ou ERP integrado a Asaas/CRM externo via Calcme → tem contrato enterprise, não é wedge fácil

---

## Diagnóstico — perguntas iniciais (call discovery, 30-45min)

**Situação (S):**
1. Há quanto tempo vocês estão no Calcme? Lembra o que fez vocês escolherem na época?
2. Quantas pessoas usam o sistema todo dia? Quantos vendedores, quantos operadores PDV/produção?
3. Quantas notas (NFe + NFCe) emitem por dia? Quantos orçamentos por semana?

**Problema (P):**
4. No dia-a-dia, qual é a função do sistema que mais te tira a paciência? (deixa abrir — não direcionar pra PDV/importação ainda)
5. Quando você precisa **atualizar preço de matéria-prima** (lona 440g subiu 5%, MDF subiu 8%), como você faz hoje? Quanto tempo leva?
6. Já aconteceu de fechar venda no PDV e o valor sair errado? Quem percebeu — vocês ou o cliente?
7. Quando precisa puxar relatório do tipo "qual cliente atrasou boleto nos últimos 90 dias" ou "quanto faturei semana passada" — você abre relatório, exporta Excel, ou tem alguém que faz pra você?

**Implicação (I):**
8. Esses 30min/dia que você gasta com [dor específica X que ele citou] — multiplica por 22 dias úteis × 12 meses = ~130 horas/ano. Vale quanto pra você?
9. Quando o suporte do Calcme demora a responder, qual é o impacto? Cliente esperando? Venda parada?

**Necessidade (N):**
10. Se eu te mostrasse uma demo onde vc fala "aumenta 5% em todo lona 440g" e o sistema atualiza 80 produtos sozinho, e onde a IA te responde "essa semana faturou R$ 47k, 2 clientes atrasaram" no chat sem você abrir relatório — isso resolve o que tá te incomodando hoje?

> **Frame da conversa:** *"Não estou aqui pra falar mal do Calcme. Eles têm coisa boa — Chatme, Kanban, 3D. Quero entender se o que você sente diariamente bate com o que outros clientes Calcme nos relataram, e se o oimpresso resolve melhor pra você especificamente."*

---

## O que migrar (escopo)

| Dado | Volume típico | Como sai do Calcme | Como entra no oimpresso | Status oimpresso |
|------|---------------|--------------------|-------------------------|-----------------|
| Cadastro clientes | 200-2.000 | **[gap — validar com cliente se Calcme oferece export CSV nativo. Não confirmado em fonte pública.]** Plano B: scraping autorizado das listagens (cliente cede credencial via 1Password) | Import via UltimatePOS `/contacts/import` (CSV padrão UltimatePOS, multi-tenant `business_id`) | ✅ Existe, funciona em prod (ROTA LIVRE rodou) |
| Cadastro produtos/papéis/m² | 50-500 SKUs | **[gap — validar export Calcme]**. CSV manual mais provável | Import UltimatePOS `/products/import` + popular `unit=m²` | 🟡 Import existe; **conversão tabela m² Calcme → variação produto oimpresso precisa script custom (4-8h dev)** |
| Tabelas de preço por cliente | 5-30 tabelas | **[gap]** — extrair manual ou via planilha | Multi-tenant: criar Selling Price Group por tabela, vincular ao Contact | ✅ Existe |
| Vendas/OS abertas (work in progress) | 10-100 | Migração manual (não migrar histórico) — re-cadastrar como nova OS no oimpresso | Manual via `/sells/create` durante D-day | ⚠️ Risco: cliente perde rastreabilidade pré-cutover. Comunicar |
| Vendas histórico (12 meses) | 1k-10k | **[gap real — Calcme não publica export de transações]**. Opções: (a) screenshot/PDF readonly mantido offline; (b) extração via SQL se contrato permitir; (c) só carregar saldo abertura | Import via SQL custom OU saldo de abertura por cliente | 🟡 **Recomendado:** carregar SALDO ABERTURA (AR/AP em 1 lançamento por cliente) — não migrar histórico transacional |
| Financeiro AR (a receber) | saldos por cliente | Saldo final por cliente em planilha | Lançar como `transaction` tipo opening_balance via UltimatePOS | ✅ Existe |
| Financeiro AP (a pagar) | fornecedores ativos | Idem AR | Idem | ✅ Existe |
| Boletos abertos | 10-100 | Lista com vencimento/valor/cliente | Re-emitir no Asaas via Modules/RecurringBilling após cutover | 🟡 **Re-emissão:** cliente final recebe boleto novo. Comunicar! |
| NFe/NFCe histórico | XMLs | Manter em pasta arquivo (legal: 5 anos) — NÃO importar | Acessar XMLs antigos via storage externo se Receita pedir | ✅ Conformidade legal mantida |
| Certificado digital A1 | 1 cert | Cliente exporta .pfx do Calcme | Upload em Modules/NfeBrasil `/nfe-brasil/certs` | ✅ Funciona — runbook biz=1 já validado |
| Logo + branding | imagens | Download manual | Upload em business settings | ✅ Trivial |
| MubiDrive equiv (arquivos cliente) | N/A | Calcme NÃO tem DAM como Mubisys — provavelmente cliente já usa Drive/Dropbox externo | Manter Drive/Dropbox externo, link em campo "obs" da venda | **[gap — oimpresso não tem DAM nativo; ponto a discutir antes do contrato]** |

**Resumo escopo padrão Pro (cliente até 500 SKUs / 1.000 clientes):** clientes + produtos + tabelas + saldos AR/AP + boletos abertos + cert A1. **NÃO migra:** histórico transacional, OS abertas (re-cadastrar), arquivos pesados (cliente mantém storage externo).

---

## Cronograma proposto (cutover 1 sábado, paralelo 7 dias)

### D-7 a D-1 (semana de prep)
- **D-7 segunda:** kickoff call 1h30 — Wagner [W] + dono cliente + (se houver) operador-chave. Valida escopo desta tabela, decide o que migrar/o que ficar pra depois, agenda D-day
- **D-6 a D-4:** **extração** — cliente exporta o que conseguir do Calcme (CSV ou screenshot), envia em pasta compartilhada. Maiara [M] valida formato
- **D-3 quarta:** **sandbox oimpresso** sobe com `business_id` novo (subdomínio temporário tipo `cliente-novo.staging.oimpresso.com`), import inicial roda, cliente faz **smoke** (login, vê seus 200 clientes, abre 1 produto, faz 1 orçamento teste)
- **D-2 quinta:** ajustes pós-smoke + treinamento 4h síncrono Maiara [M] (gravar — substitui retreinos)
- **D-1 sexta:** congela alterações grandes no Calcme (cliente pára de cadastrar produto novo lá). Re-extração delta clientes/produtos novos da semana. Boletos abertos exportados em planilha
- **Sex 18h:** confirma D-day amanhã

### D-day (sábado, ~6-8h trabalho)
- **08h-10h:** import final (clientes + produtos + saldos)
- **10h-12h:** cliente testa no sandbox real (não-staging) — 5 fluxos críticos: login, abrir cadastro, fazer orçamento, emitir NFCe homologação, ver dashboard
- **12h-13h:** pausa almoço
- **13h-15h:** **virada:** sandbox vira prod (DNS/`business_id` definitivo), Calcme passa pra readonly (cliente decide se cancela ou só não loga). Cert A1 sobe pra Modules/NfeBrasil prod
- **15h-17h:** **smoke prod** — cliente faz 1 venda real PDV (se tiver Balcão), 1 NFCe real homologação primeiro, depois prod, 1 boleto Asaas teste
- **17h-18h:** **go/no-go** — Wagner [W] decide: ✅ go (cliente abre seg de manhã com oimpresso) ou ❌ rollback (volta Calcme, agenda novo D-day)

### D+1 a D+7 (paralelo controlado)
- **Calcme readonly** (cliente loga só pra consultar histórico) + **oimpresso writable** (toda venda nova lá)
- WhatsApp grupo com Wagner [W] + Maiara [M] + cliente — resposta ≤4h business
- Bug bloqueador (PDV não fecha, NFe rejeitada): 24h fix ou rollback
- D+3: check-in 30min Maiara [M] valida com cliente

### D+30 (desligar Calcme)
- Cliente cancela contrato Calcme (lembrete: contrato Calcme é só crédito não reembolso — calendário pra não perder janela)
- Backup final XMLs NFe (5 anos legal)
- Pós-mortem 1h Wagner [W] + cliente — o que rolou, o que faltou, próximo passo (treinamento Jana avançado, Modules/Repair se faz manutenção etc)

---

## Riscos identificados + mitigação

| # | Risco | Probabilidade | Impacto | Mitigação |
|---|-------|---------------|---------|-----------|
| 1 | **Calcme não oferece export limpo de produtos** (4 RA confirmam: importação manual no Calcme; export pode ser igual) | Alta | Alto | D-7 validar com cliente; se não tiver → orçar 8-16h dev pra script de extração via SQL/scraping autorizado. Cobrar como migração custom |
| 2 | **Cliente Larissa-style decora horário do sistema antigo** (auto-mem ROTA LIVRE: dor real) | Média | Médio | Treinamento 4h gravado + Jana IA configurada pra responder dúvidas operacionais ("onde fica X agora?"). Wagner [W] em standby D+1 a D+3 |
| 3 | **Cert A1 expira durante migração** (raro mas catastrófico) | Baixa | Crítico | D-7 validar validade do cert (>30d). Se <30d, renovar antes — não migrar com cert apertado |
| 4 | **NFCe rejeitada SEFAZ pós-cutover** (CSC/CSOSN/CFOP novo ambiente) | Média | Alto | D-3 smoke em **homologação** primeiro (runbook `runbook_smoke_sefaz_biz1`). Manter Calcme apto a emitir 7d |
| 5 | **Boletos Asaas (re-emitidos) confundem cliente final** (recebe boleto novo + número diferente) | Alta | Médio | **Comunicação obrigatória cliente final** (script abaixo). Manter boleto antigo Calcme válido por 7d (cliente paga onde quiser) |
| 6 | **Backup financeiro AR/AP errado** (saldo de abertura não bate) | Média | Alto | Reconciliação dupla: planilha cliente vs oimpresso D-day antes de 15h. Wagner [W] revisa pessoalmente |
| 7 | **Cliente final do prospect (gráfica) reclama da troca** (frente loja Larissa-like) | Média | Médio | Comunicação avisada D-7 + 0 (script abaixo). Manter visual oimpresso parecido (logo cliente, cores) |
| 8 | **Gap DAM (MubiDrive equiv) descoberto tarde — cliente Calcme tinha arquivos pesados lá** | Média | Médio | **Discutir ANTES do contrato.** Se cliente tem >5GB de arte armazenada, propor: manter Drive/Dropbox externo + link no campo obs da venda. Não esconder gap |
| 9 | **Cliente tinha integração custom Calcme→Asaas/CRM** | Baixa-Média | Alto | Discovery pergunta 4 captura. Se sim → escopo extra (cobrar). oimpresso tem API/Webhook (publicizar) |

---

## Comunicação ao cliente final do prospect

**Script WhatsApp (cliente final da gráfica recebe — D-1 sexta):**

```
Olá [Nome cliente]! Aqui é [Larissa/dono gráfica], da [Nome Gráfica].

Tô passando pra avisar que neste sábado [DATA] vamos atualizar nosso
sistema interno. Pra você muda quase nada — só o boleto, se você
costuma pagar pelo nosso boleto antigo, vai vir um link novo a partir
de [DATA+2]. O boleto antigo continua valendo até [DATA+7], pode pagar
nos dois sem problema.

Se precisar de orçamento, NFe ou tirar dúvida, me chama aqui no Whats
normal — atendimento não para. Qualquer coisa estranha, me avisa que
resolvo na hora.

Obrigado pela confiança! 🙏
```

**E-mail formal (anexo NFe automática D+1):**
- Assunto: "Atualização sistema [Gráfica] — sua nota fiscal"
- Corpo: 3 linhas explicando + telefone Larissa + link pasta com boletos

---

## Pricing migração (Pro tier — perfil Calcme típico)

| Item | Valor | Observação |
|------|-------|------------|
| **Setup standard Pro** | R$ 2.500 (one-time) | Inclui migração até **1.000 clientes + 500 produtos + saldos AR/AP** |
| Migração custom (>1k clientes ou >500 SKUs) | R$ 800/dia dev | Estimar D-7 |
| **Treinamento 4h síncrono** | Incluso no setup | Maiara [M] grava |
| **Treinamento extra 2h (operador novo)** | R$ 300 | Após D+30 |
| **Mensalidade Pro 60 dias garantia** | R$ 499/mês | Escalonamento R$ 0 mês 1 → R$ 249 mês 2 → R$ 499 mês 3+ se cliente quiser amortecer (negociável Wagner [W]) |
| Setup Asaas (re-emissão boletos) | R$ 0 | Já incluso (oimpresso tem RecurringBilling US-RB-044) |
| Cert A1 (se cliente precisar emitir novo) | repasse + R$ 150 | Não obrigatório se .pfx já existe |

**Comparação com pricing-tiers oficial:** consultar [`memory/sales/2026-05/06-pricing-tiers.md`](06-pricing-tiers.md) — alinhamento Pro R$ 499/mês mantido.

---

## SLA pós-migração (60 dias garantia)

> **⚠️ Importante:** SLA realista pra time de 5 (Wagner+Maiara+Felipe+Luiz+Eliana). Não prometer 24x7.

| Item | SLA | Janela |
|------|-----|--------|
| Resposta WhatsApp | ≤ 4h | Seg-sex 09h-19h, Sáb 09h-13h |
| Bug bloqueador (PDV/NFe/login down) | Fix ou workaround em 24h | 7 dias/semana, melhor esforço madrugada |
| Bug não-bloqueador | Fix em 5 dias úteis | seg-sex |
| Pergunta de uso ("como faz X?") | Resposta WhatsApp + link tutorial Jana | ≤ 4h |
| Treinamento extra (1ª vez) | Agendar em 5 dias úteis | Maiara [M] |
| **Rollback contratual** | Cliente decide até D+7 → volta Calcme, devolução 100% setup | Cláusula tranquilizadora explícita no contrato |
| **Pós-D+60** | Mensalidade normal + suporte ticket padrão Pro | sem SLA premium |

---

## Vitórias rápidas (week 1) pra cliente sentir valor

> **Objetivo:** D+1 até D+7, cliente fala "valeu a pena" antes de receber o primeiro boleto da mensalidade.

### Dia 1 — login D+1 segunda (impacto emocional alto)
1. **Dashboard primeira tela mostra "Hoje você tem X orçamentos abertos, Y boletos vencendo, Z mil em pipeline"** — Larissa-style abriu sistema, viu valor sem clicar nada. Configurar antes do D-day.

### Dia 2-3 — bulk update via Jana (bate dor #3 universal)
2. **Demo na pelo menos 1 reunião curta (15min):** "Aumenta 5% em toda lona 440g" → Jana atualiza N produtos em segundos. Cliente VÊ a IA fazer trabalho que ele faria em 30min. **Impacto:** cliente conta pro vizinho.
   - **Status técnico:** [gap — Jana atualiza preço bulk via SQL custom hoje, mas **fluxo conversacional "fala com Jana → ela executa"** ainda não tá publicado como feature pública. Construir antes de prometer ao 2º cliente Calcme]

### Dia 3-5 — NFe automática a partir de boleto (US-RB-044)
3. **Primeiro boleto Asaas pago → NFe emitida sozinha em 30s** — cliente recebe e-mail com NFe sem ninguém clicar. **Aha moment fiscal.** Funciona em prod (US-RB-044 entregue).

### Dia 5-7 — Visão Unificada Financeiro (substitui planilha contador)
4. **Tela `/financeiro/visao-unificada`** mostra AR + AP + saldo bancário + projeção 30/60/90d em uma tela. Cliente fala "isso eu mandava o contador montar toda semana". 
   - **Status:** entregue (PR #349, ADR ui/0003 amends 0002 — ver session log 2026-05-09)

### Bônus week 2
5. **Jana responde "qual cliente atrasou mais nos últimos 90d"** no chat — bate dor #4 do top 10 do setor (margem/relatório). Mostrar configurada com dados reais do cliente.

---

## Gaps oimpresso que **podem travar** essa migração (declarar antes do contrato)

| Gap | Impacto cliente Calcme | Mitigação ofertável |
|-----|------------------------|---------------------|
| **DAM nativo (MubiDrive equiv)** | Baixo (Calcme não tem DAM forte; cliente provavelmente já usa Drive externo) | Manter Drive/Dropbox externo |
| **App mobile nativo iOS/Android** | Médio (Calcme não tem app nativo destacado, mas tem Chatme integrado WhatsApp — perda emocional pode ser real) | PWA via Inertia (instalável Android), iOS web responsivo. **Falta polir ícone home + push notif** |
| **Integração 3D Calcme3D** | Alto se cliente usa marcenaria/MDF | **Não migrar** — cliente segue Calcme se 3D é core. Não é wedge. |
| **Chatme WhatsApp nativo dentro do sistema** | Médio | oimpresso tem Centrifugo + integração WhatsApp Business, mas UX Chatme-like (chat dentro do CRM) **falta polir** |
| **Bulk update conversacional via Jana** | Alto (é um dos selling points week 1) | **Construir antes do 2º cliente Calcme** — hoje é SQL custom. US a criar |

---

## Fluxo da venda (resumo executivo pra Wagner)

1. **Lead capture:** monitorar Reclame Aqui Calcme + grupos Telegram de gráfica + comentários post Calcme novos preços 2026
2. **Outreach:** cold email/DM personalizado citando RA específica (ver `01-cold-emails.md` adaptado)
3. **Discovery 30-45min** (perguntas SPIN acima)
4. **Demo 45min** com Rota Livre real (pedir Larissa autorização) — mostra Jana, NFe automática, Visão Unificada
5. **Proposta** com pricing tabela + escopo migração desta tabela
6. **D-7 kickoff** após contrato assinado
7. **Cutover D-day** → **garantia 60d** → cliente fidelizado

---

**Última atualização:** 2026-05-09 · próximo review após 1º cliente Calcme migrado
