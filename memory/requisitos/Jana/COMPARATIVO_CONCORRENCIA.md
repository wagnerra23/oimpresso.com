# Jana — Comparativo Concorrência (estilo Capterra)

**Última atualização:** 2026-07-02 | **Próx. revisão:** 2026-10-02
**Método de nota:** 8 critérios ponderados (P0=peso 4, P1=peso 2) → soma ponderada / 240 × 100.

> ⚠️ **Refresh 2026-07-02:** ficha reescrita a partir de pesquisa web 2026 (Omie WhatsApp jun/2025, Olist Lis, Conta AI ago/2025, Bling beta, Sankhya nov/2025, TOTVS Carol). A versão anterior (2026-04-25) dava Jana=0 ("não implementado"); hoje a Jana está **live em prod (~85% operacional)**. Trilha de evolução no rodapé.

## Sobre o módulo

| Campo | Valor |
|---|---|
| **Best for** | "Dono de PME BR que quer um analista IA que conhece o negócio, propõe metas e respeita LGPD" |
| **Setor** | AI assistant / business intelligence conversacional ERP-nativo pra PME |
| **Stage** | **Live em prod (~85% operacional)** — 9 Agents, brief diário, chat, RAG/MCP, memória persistente |
| **Persona** | Wagner (dono PME) + Larissa (ROTA LIVRE, operadora não-técnica, balcão, monitor 1280px) |
| **JTBD** | "Conversar com IA sobre meu negócio e receber metas + acompanhamento, sem vazar dado do cliente" |

## Concorrentes 2026

### Grupo A — Copilots de IA embutidos em ERPs BR (diretos)

| Player | Recurso de IA | O que faz de fato | Preço/tier | Falha vs. ERP-nativo LGPD |
|---|---|---|---|---|
| **Olist Tiny — "Lis"** | Agente supervisor + agentes especializados, **multi-LLM** (ChatGPT/Gemini/Claude/LLaMA) | Orquestra NFe, produtos, estoque/logística, pedidos, financeiro/conciliação; diagnósticos + automações | Modelo de **créditos** mensais (10/20/40 por plano) | Cloud + LLMs de terceiros (incl. EUA); crédito limitado gera atrito a escala |
| **Omie — ERP via WhatsApp** | ERP inteiro operado por **voz/texto no WhatsApp** (jun/2025) | **Executa ações**: emite NFe, gera boletos, movimentações, consulta caixa por comando | Incluso na base (~175 mil empresas) | Dados trafegam pela **Meta/WhatsApp**; sem evidência de PII redaction pré-LLM |
| **Conta Azul — "Conta AI Captura"** | IA generativa de captura (ago/2025) | **Lê/lança documentos**: boletos, NFe, extratos, cartão via WhatsApp/e-mail/upload/DDA; classifica receita×despesa | Grátis temporário / incluso no **Pro** | Escopo restrito a captura; proatividade/monitoramento ainda em roadmap |
| **TOTVS — Carol / Copilot** | Plataforma dados+IA + agentes generativos | Conversacional, análise preditiva, alertas, automação (−46% tempo de implantação) | **Enterprise/mid-market** | Segmento errado pra PME pequena; preço/complexidade fora do alvo |
| **Sankhya — BIA + Deploy Agent** | BIA Studio (analytics) + agente de implantação | Analytics, service desk, transacional; processa XML/SPED/eSocial | **Enterprise** | Foco implantação enterprise, não copiloto diário de dono de PME |
| **Bling — Assistente de IA** | Assistente + chatbot vendas + App BI (beta 2025-26) | Gera relatórios/gráficos/planilhas; "painel de comando" com ações em lote | Incluso (beta limitado) | Beta não-comprovado a escala; sem evidência de LGPD/PII ou proatividade |
| **Nibo** | IA financeira | Conciliação bancária lendo PDF, leitura NFe/extratos, automação de cobranças | Incluso (planos financeiros/BPO) | Escopo financeiro-only; não é copiloto do negócio inteiro |
| **Granatum** | Controle financeiro | Fluxo de caixa/DRE/conciliação — IA marginal | Plano único | Sem produto de IA conversacional relevante evidenciado (não pontuado) |

### Grupo B — Assistentes horizontais (indiretos, o dono já usa)

| Player | O que faz | Preço | Falha |
|---|---|---|---|
| **ChatGPT** (Projects / Custom GPT) | Análise/chat sobre o que **você cola**; não acessa o ERP | Assinatura USD; DPA só Business/Enterprise | **Risco LGPD grave**: Free/Plus pode treinar com seus dados; CPF/CNPJ vai pra EUA sem DPA. Reativo |
| **MS Copilot / Gemini Workspace** | IA dentro de Office/Docs; precisa conector custom pra "ver" ERP | Licença por seat (USD) | Sem integração ERP BR nativa; domain-fit BR fraco |

**Fontes:** Omie (blog/Startups/Mobiletime jun/2025) · Olist Agentes/Lis (olist.com/agentes, Exame) · Conta AI (contaazul.com/funcionalidades/conta-ai, Convergência Digital ago/2025) · TOTVS Carol (totvs.com) · Sankhya BIA (sankhya.com.br, Computer Weekly nov/2025) · Bling Novidades (bling.com.br/novidades) · Nibo (nibo.com.br/blog) · OpenAI Enterprise Privacy · ChatGPT Projects (help.openai.com).

## Matriz de notas (0-10)

| Critério (peso) | **Jana** | Olist Lis | Omie WA | TOTVS | Sankhya | Bling | Conta AI | Nibo | ChatGPT | MS/Gemini |
|---|---|---|---|---|---|---|---|---|---|---|
| Ease of use (P1) | 7 | 8 | 9 | 6 | 6 | 8 | 8 | 8 | 8 | 7 |
| Inteligência/LLM (P1) | 6 | 9 | 7 | 8 | 7 | 6 | 6 | 6 | 10 | 9 |
| **Contexto negócio/ERP (P0)** | 9 | 9 | 8 | 8 | 8 | 6 | 6 | 6 | 3 | 3 |
| **Privacidade LGPD/PII BR (P0)** | 10 | 6 | 6 | 8 | 8 | 6 | 7 | 7 | 4 | 6 |
| **Integração ERP BR (P0)** | 9 | 9 | 9 | 9 | 9 | 9 | 8 | 7 | 2 | 3 |
| Preço BR (P1) | 8 | 6 | 8 | 3 | 3 | 8 | 7 | 7 | 6 | 5 |
| Proatividade (P1) | 9 | 8 | 6 | 7 | 7 | 4 | 5 | 5 | 3 | 4 |
| **Domain-fit BR (P0)** | 9 | 9 | 9 | 9 | 9 | 9 | 8 | 8 | 5 | 4 |

## Nota final (0-100) + ranking

| # | Player | Nota | Leitura |
|---|---|---|---|
| 1 | **Jana (oimpresso)** | **87** | Lidera em LGPD/self-host, proatividade e domain-fit |
| 2 | **Olist Tiny (Lis)** | **81** | Multi-LLM + orquestração multi-agente madura; crédito limita |
| 3 | **Omie (WhatsApp IA)** | **78** | Opera ERP inteiro por voz no WhatsApp, escala massiva |
| 4 | **TOTVS Carol/Copilot** | **77** | Forte, mas enterprise — segmento errado pra PME |
| 5 | **Sankhya BIA** | **76** | Enterprise/EIP, foco implantação |
| 6 | **Bling (Assistente IA)** | **72** | Beta promissor, não-comprovado a escala |
| 7 | **Conta Azul (Conta AI)** | **70** | Captura de documentos forte; escopo estreito |
| 8 | **Nibo** | **68** | IA financeira sólida, não é copiloto do negócio |
| 9 | **MS Copilot / Gemini** | **48** | Frontier LLM, cego ao ERP BR sem integração custom |
| 10 | **ChatGPT (Projects/GPTs)** | **46** | Melhor LLM, pior fit: risco LGPD + reativo |

> ⚠️ **Caveat de honestidade (Tier 0 — claim sem evidência):** a nota da Jana é de **arquitetura/privacidade**, NÃO de tração. Em escala/prova de campo, Omie (~175 mil empresas) e Olist eclipsam a Jana (**1 piloto em prod, ROTA LIVRE**). Os critérios ponderam pesado justamente os diferenciais arquiteturais da Jana. Ler 87 como "melhor produto do mercado" seria enganoso — é "melhor arquitetura pro fosso LGPD+proatividade, ainda por provar a escala".

## 5 GAPS onde a Jana perde pros líderes em 2026

1. **LLM frontier + roteamento multi-modelo** — Olist roteia ChatGPT/Gemini/Claude/LLaMA; a Jana está presa a **um** gpt-4o-mini (teto de raciocínio mais baixo, sem fallback).
2. **WhatsApp como interface primária** — a Omie opera o ERP por voz/texto no WhatsApp, onde a Larissa já vive. A Jana é chat **in-app** (exige estar logada no ERP).
3. **Execução agêntica (write-actions)** — Omie emite NFe/boleto por comando; agentes Olist editam cadastros e conciliam. A Jana é forte em **narrar/consultar/RAG**; evidência de ações transacionais é fraca.
4. **Captura/ingestão de documentos (OCR)** — Conta AI e Nibo leem boletos/NFe/extratos de WhatsApp/e-mail/DDA e lançam automático. A Jana não tem pipeline de captura documental — perde a dor #1 de PME (digitação manual).
5. **Escala, tuning e ecossistema** — Omie/Olist têm base gigante que amadureceu edge-cases e confiança. A Jana = 1 piloto; ainda não provou robustez/latência/custo a escala real.

## 5 DIFERENCIAIS onde a Jana lidera

1. **LGPD/PII redaction pré-LLM BR + self-hosted (CT100)** — remove CPF/CNPJ/CEP antes de chamar o provedor. Concorrentes mandam dado pra cloud/EUA (Olist, ChatGPT) ou pela Meta (Omie). **Fosso mais defensável** — nenhum concorrente anuncia isso.
2. **Multi-tenant Tier 0 irrevogável** — isolamento `business_id` como invariante de constituição. Ninguém no comparativo trata isolamento assim.
3. **Proatividade real, não sob demanda** — brief diário 06:00 automático + **propõe E monitora metas** (SugestoesMetasAgent/WeeklyDigest/HealthNarrator). A maioria é reativa (comando) ou captura de documento.
4. **MCP server exposto + RAG hybrid** (Meilisearch+HyDE+reranker RRF/BGE) sobre governança canon — conhecimento governado como produto interoperável. Concorrentes são caixas-pretas.
5. **Custo previsível self-host vs. crédito medido** — Olist cobra por créditos ("consumo varia por complexidade" = ansiedade de custo). Jana em gpt-4o-mini self-host tem custo previsível e barato, sem teto.

## Estratégia

### Posicionamento
> _"O analista IA que conhece seu negócio porque está dentro do seu ERP — e não vaza o CPF do seu cliente."_

### Onde apostar (defender o fosso + fechar gaps de canal)
- **Fosso:** LGPD/PII pré-LLM + multi-tenant Tier 0 + proatividade — comunicar como diferencial de venda #1.
- **Fechar gap de canal:** interface WhatsApp (paridade com Omie) — hoje o maior gap prático pra Larissa.
- **Fechar gap agêntico:** write-actions transacionais (emitir/lançar por comando), não só narrar.
- **Captura documental (OCR):** ingestão de boleto/NFe/extrato — dor #1 de PME que Conta AI/Nibo já resolvem.
- **Roteamento multi-modelo:** fallback pra modelo frontier em perguntas complexas (Brain B já previsto).

### Preço
- Free (entrada) · Pro R$ [redacted Tier 0] · Enterprise R$ [redacted Tier 0] ilimitado + custom drivers.

## Refs
- Fichas irmãs: [IA-MATURITY-FICHA.md](IA-MATURITY-FICHA.md) (lente infra LLM: Vellum/LangSmith/Braintrust/Helicone, Jana 88/100) · [BRIEFING.md](BRIEFING.md)
- ADRs: 0035 (stack IA), 0048 (Vizra rejeitada), 0052 (3 ângulos faturamento), 0053 (MCP server), 0093 (multi-tenant Tier 0)

---

## Trilha de evolução (append-only)

- **2026-07-02** — Refresh completo pós-pesquisa web 2026. Concorrentes atualizados de {ChatGPT, MS Copilot, Tiny IA, Bling} → conjunto real 2026 (Olist Lis, Omie WhatsApp, Conta AI, TOTVS Carol, Sankhya BIA, Bling, Nibo + horizontais). Jana reavaliada 0 → **87** (live em prod). Método migrado pra 8 critérios ponderados P0/P1. Caveat de tração (1 piloto) adicionado. Gerado por pesquisa `general-purpose` + curadoria [C].
- **2026-04-25** — Versão inicial (spec-ready, Jana=0 não-implementado). Concorrentes: ChatGPT (50), MS Copilot (57), Tiny IA (77). Método Capterra 6 critérios /60.
