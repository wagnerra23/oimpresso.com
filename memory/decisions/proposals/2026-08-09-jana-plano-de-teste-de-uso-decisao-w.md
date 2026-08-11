---
title: "Jana — plano de teste por uso: o que dá pra usar hoje, o que seria teste injusto"
status: proposta
date: "2026-08-09"
owners: [W]
parent_module: Jana
related_adrs: [35, 62, 93, 141, 145, 245, 318, 334, 366]
related_specs:
  - memory/requisitos/Jana/SPEC.md (US-COPI-133, 135, 136, 137, 141, 142, 145)
related_charters:
  - resources/js/Pages/Jana/Index.charter.md
  - resources/js/Pages/Jana/Memoria.charter.md
---

# Jana — plano de teste por uso

> **Pergunta de [W] (2026-08-09):** *"quero testar os possíveis usos da IA pra ter certeza do que e como usar a Jana. Podemos descrever os possíveis usos, categorias, testes propostos e resultados esperados?"*
>
> **Resposta curta: sim, e é o critério certo.** Melhor que decidir pela superfície (route-hits) — decide pela capacidade. Mas **a ordem importa**: três coisas tornariam um teste feito hoje injusto, e duas delas só afetam *uma* das categorias. Este doc separa o que é justo testar já do que seria falso-negativo.
>
> Deriva do [`BRIEFING.md`](../../requisitos/Jana/BRIEFING.md) (dono da intenção) — não abre catálogo paralelo.

## 1. Correção de premissa (registrada porque o erro foi meu)

Na mesma sessão eu apresentei *"o módulo Jana teve 4 acessos em 30 dias"*. O número está certo e a leitura é enganosa: ele mede **rota web**, e a Jana quase não é web.

| Papel | Qtd |
|---|---|
| Services · Commands · Jobs · Entities | 91 · 46 · 7 · 43 |
| Testes Pest | 157 |
| **Telas (9 Blade + 4 Inertia)** | **13** |

**6 schedules ativos** no Kernel. Prova direta: o `brief-fetch` do SessionStart de 2026-08-09 devolveu **Brief #489, gerado há 32 min** — saída de produção viva deste módulo.

Os 4 hits descrevem 13 arquivos de 572. É a armadilha do `Modules/Auditoria` ([§5 2026-07-30](../../proibicoes.md)): medir a tabela própria vazia de um módulo que vive de outra fonte.

## 2. As duas Janas (a divisão que o route-hits esconde)

| | **A · Jana do negócio** | **B · Jana do time** |
|---|---|---|
| Quem usa | [W], e no futuro Larissa | [W], [F], [M], [L] — e os agentes |
| Por onde | telas `/ia/*`, brief, digest | MCP (`brief-fetch`, `tasks-*`, `decisions-*`) |
| Estado | construída, **pouco usada** | **em uso diário** |
| Evidência | 4 hits/30d | o brief desta sessão; `cc-search`; 40 tools MCP |

**A decisão de [W] é sobre a A.** A B já se paga e não está em questão.

## 3. Os 3 bloqueadores que tornariam o teste injusto

> Testar sem saber disto produz **falso-negativo**: mede-se uma Jana amarrada e conclui-se que "IA não serve".

| # | Bloqueador | Medido | Afeta |
|---|---|---|---|
| **B1** | **Modelo fraco.** `model_chat = gpt-4o-mini`; o config registra que `gpt-4o` devolve **403 "does not have access"** | [`Config/config.php:43-45`](../../../Modules/Jana/Config/config.php) · US-COPI-145 `todo` | tudo que é conversa |
| **B2** | **Busca acha ~38% do contexto.** `context_recall_avg = 0,3839` (n=51, 01/07, CT 100) | [`governance/jana-ragas-real-baseline.json`](../../../governance/jana-ragas-real-baseline.json) | **só a categoria 1** |
| **B3** | **Clarificador desligado.** `JANA_CLARIFY_ENABLED` OFF — a Jana **chuta** em vez de perguntar quando o pedido é ambíguo | `config.php:527` · BRIEFING §Gaps | conversa |

⚠️ **O selo verde de RAGAS nos PRs é mock.** Ele mostra `faithfulness 0,850`; o real é **0,69**. Não use o badge como leitura de qualidade.

**B2 é o dominante — e é localizado.** Ele mede *retrieval da memória/KB*. As categorias 2 a 5 leem **SQL do negócio** (`SellsCockpitAggregator`, drivers de apuração), não retrieval. Por isso são justas de testar hoje.

## 4. Limite de capacidade — a Jana **lê e fala; não age**

Write-action não existe. A [ADR 0145](../../decisions/0145-ia-administradora-pivot-ads-fsm-piloto-cobradora.md) foi aceita em 2026-05-15 com **0 commits de implementação**: `FsmActionBridge`, `CobradoraAgent`, `cobrar_fatura` e Audit Card **não existem** (registrado na ressalva do adversário na US-COPI-141).

O chat ganhou **5 tools READ-ONLY** (US-COPI-141), com flag flipada em prod por [W] em 2026-07-17 (US-COPI-142 `done`).

Então "IA que faz por mim" **não está na mesa hoje**. O que está: *IA que responde, avisa e propõe*.

## 5. Categorias de uso, testes e resultado esperado

Ordenadas por **justiça do teste hoje** — as 3 primeiras não dependem de B2.

### Cat. 1 — Brief do negócio · ✅ testável — **e já foi testado, ver §5.2**

> ⚠️ **Duas correções à 1ª redação, provadas pelo teste de 2026-08-09.** Ela dizia *"todo dia a Jana monta o retrato do negócio sem ninguém pedir — `BriefDiarioAgent` + cron"* e mandava *"ler 5 briefs seguidos"*. **As duas estão erradas.**

- **O que é de fato:** **não há cron e não há histórico.** O brief do negócio é **sob demanda** — nasce quando alguém digita `brief` no chat `/ia/conversa` (`BriefDiarioChatTrigger`). Não persiste em tabela nenhuma: *"gera na hora"*. Confirmado pela proposal [`brief-se-divide-em-dois`](2026-07-30-brief-se-divide-em-dois.md) (2026-07-30), que já era dona desta distinção, e por varredura de invocadores (`BriefDiarioService`, `BriefDiarioChatTrigger`, `ChatController`, `JanaProController` — **nenhum schedule**).
- ⛔ **Não confundir com o `brief-fetch` do MCP.** Aquele é o brief de **governança** (`Modules/Brief`, tabela `mcp_briefs`, sem `business_id`) — é a categoria 6, não esta.
- **Teste:** digitar `brief` no chat e conferir cada número contra a fonte. Repetir em dias diferentes pra ter n>1.
- **Esperado pra valer a pena:** ≥4 de 5 blocos corretos **e** ≥1 achado que você não sabia.
- **Ressalva que a 1ª redação não tinha:** os dados vêm de 5 tools SQL (imune a **B2**), mas **quem narra é o LLM** — logo **B1 se aplica**. A separação "brief = SQL, justo de testar" era limpa demais.

### 5.2 · Resultado do 1º teste real (2026-08-09, chat `/ia/conversa`, biz=1)

**✅ O que funcionou — e é exatamente a doutrina do produto:**

> **ANTONELLA ALVES ARAUJO** — LTV histórico · última compra 22/06/2025 · **412 dias ausente**, com mensagem de WhatsApp pronta pra copiar.

Verificado na tela `/cliente`: **a cliente existe** (MAGAZINE LUIZA, FRANCA/SP, 25 OS) e o selo de frescor diz *"distante · há 1a"*, **coerente** com os 412 dias. É proposta acionável, não gráfico. O LTV exato **não** foi conferido — o `Saldo` da tela é outra métrica, cruzar exige o histórico de compras.

**❌ Três defeitos reais:**

| # | Defeito | Por quê importa |
|---|---|---|
| 1 | **Linha fabricada** — a "Ideia da semana" trouxe `PRODUTO BEST-SELLER · Saídas em 90d: 0` (nome de placeholder, valor zero) e o LLM escreveu conselho por cima: *"criar campanha focada nos best-sellers"* | recomendação construída sobre linha vazia |
| 2 | **Promessa falsa** — o rodapé diz *"próximo brief: amanhã, 8h"*, e **não existe cron** | é a classe **LC-15** (mecanismo anuncia o que não implementa), desta vez **na cara do cliente** |
| 3 | **Entusiasmo sobre zeros** — *"ainda tem potencial para ser amplamente produtivo!"*, projeção `0 vendas/dia → ±0%` | aritmética de zero vestida de análise |

**⚠️ O problema estrutural do teste (achado maior que os 3 defeitos):**

`biz=1` **não tem vendas** — toda a seção "Operação" veio zerada porque não há o que reportar. O único bloco com dado real (o cliente ausente) foi o único que funcionou. Testar o brief aqui é testá-lo numa **empresa vazia**.

Quem tem dado é **`biz=4` ROTA LIVRE** (~21 mil vendas) — e a [R6](../../proibicoes.md) proíbe usar o tenant do cliente em teste. **O tenant que dá pra testar não tem dado; o que tem dado não dá pra tocar.** Sair disso é decisão [W]: ele pode olhar o brief da Larissa como dono e relatar, o que nenhum agente pode fazer por ele.

**Veredito honesto com n=1:** a máquina funciona; a **curadoria do texto não está pronta pra cliente**. Não dá pra fechar o corte (≥4 de 5) com uma amostra num tenant vazio.

### Cat. 2 — Metas: apuração e desvio · 🔨 justo, mas é **construção**, não observação

> ⚠️ **Corrigido depois do smoke de 2026-08-09** (esta linha era "✅ justo hoje / só observar" na 1ª redação — estava errada). **Não existe nenhuma meta cadastrada**, em lugar nenhum: ver §5.1.

- **O que é:** meta cadastrada, apuração automática (`ApurarMetaJob`), farol e alerta de desvio (`AlertaService`).
- **Teste:** cadastrar **as 2 primeiras metas do sistema** (uma que você sabe que vai bater, outra que não), esperar a apuração, conferir contra o número que você tira à mão.
- **Esperado:** valor apurado **idêntico** ao seu cálculo, nas duas. Farol coerente.
- **Falha significa:** driver/fonte errada — Tier 0 de valor, conserto obrigatório antes de qualquer uso.
- **Custo real:** você precisa *criar dado*, não só olhar. Ainda é o teste mais barato que dá resposta binária — a apuração bate ou não bate.
- ⚠️ Hoje só dá pra fazer isso por tela **Blade**. Foi o que a onda 5 tornou honesta.

### 5.1 · O que o smoke de 2026-08-09 achou (e reordena tudo)

Smoke real em prod, biz=1, logado, pós-deploy do [#5496](https://github.com/wagnerra23/oimpresso.com/pull/5496):

| Medida | Valor |
|---|---|
| Metas em `biz=1` | **0** — *"Nenhuma meta cadastrada"* |
| Metas em **qualquer** cliente (cross-business, tela superadmin) | **0** — *"Nenhum cliente configurou metas ainda"* |
| Hits nas telas de metas (30d, `route-hits.json`) | **0** |

Não é uso baixo — é **zero**. `ApurarMetaJob`, `ApuracaoService`, os drivers, as 12 janelas, o farol e a projeção **nunca tiveram um único dado pra processar**.

Consequência direta: as telas Blade que o pedido [CC] queria migrar pra Inertia (ondas 8/10/11) servem uma feature que **ninguém jamais cadastrou**. Migrar a forma antes de saber se a função vale é o caminho caro.

Isso também limitou o smoke: `/ia/metas/{id}/fonte` e o botão de reapuração **não puderam ser exercidos** — exigem uma meta que não existe. As outras 3 telas alteradas foram confirmadas no ar.

### Cat. 3 — Resumo semanal / síntese · ✅ justo hoje

- **O que é:** `jana:weekly-digest` + `copiloto:sintese-semanal`, por cron, entrega por e-mail.
- **Teste:** receber 2 semanas e comparar com o que você percebeu na operação.
- **Esperado:** ≥1 constatação por semana que você endossaria numa reunião.
- **Falha significa:** vira ruído de inbox — desligar é barato.

### Cat. 4 — Sugestão de meta / insight de venda · 🟡 parcial

- **O que é:** `SugestoesMetasAgent`, `SaleInsightAgent` — a Jana **propõe**.
- **Teste:** pedir 5 sugestões e classificar: *aceito* / *plausível mas não* / *absurda*.
- **Esperado:** ≥3 aceitas ou plausíveis. É aqui que se testa a doutrina do BRIEFING (*"o valor está na proposta aceita, não no gráfico"*).
- **Ressalva:** usa LLM → sofre de **B1**. Resultado ruim pode ser o modelo, não o produto.

### Cat. 5 — Perguntar em linguagem natural sobre o negócio · ⛔ **teste injusto hoje**

- **O que é:** o chat `/ia/conversa` + `KbAnswer` + memória. É o que a maioria imagina como "usar IA".
- **Por que é injusto:** sofre dos **três** bloqueadores ao mesmo tempo — modelo fraco (B1), busca achando 38% (B2), sem clarificação (B3).
- **Se testar assim mesmo:** trate como **piso**, não como veredito. Pergunte 10 coisas cuja resposta você conhece e conte acertos; espere algo em torno de metade, e isso **não** condena o produto.
- **Pra ficar justo:** fechar B2 (US-COPI-133/136) e B1 (US-COPI-145 — precisa de `ANTHROPIC_API_KEY` em prod **ou** acesso a `gpt-4o`; é compra/acesso, não código).

### Cat. 6 — Copiloto do time via MCP · ✅ já em uso, não precisa de teste

40 tools (`brief-fetch`, `tasks-*`, `cycles-*`, `decisions-*`, `memoria-search`, `handoff-*`). Já é usada todo dia — esta sessão é a prova. Fora da decisão.

## 6. Recomendação de sequência

> Reordenada após o smoke de §5.1 — a 1ª versão punha 1, 2 e 3 no mesmo degrau, e só a 1 e a 3 são observação pura.

1. ~~**Começar pela categoria 1.**~~ **FEITA em 2026-08-09** — resultado em §5.2. (O texto original citava o "Brief #489 gerado nesta sessão" como prova de vida: era o brief de **governança**, categoria 6, não este. Correção registrada.)
2. **Se o brief servir, ir pra 3 (resumo semanal)** — também observação, cadência de 2 semanas.
3. **Só então a 2 (metas)**, que exige cadastrar as primeiras metas do sistema. Vale porque dá resposta binária (a apuração bate ou não), mas é trabalho seu, não só leitura.
4. **Decidir.** Se 1 e 3 não te servirem, o problema é de produto e não vale destravar B1/B2 nem cadastrar meta nenhuma.
5. **Só se servirem**, atacar B1 e B2 e então testar a categoria 5.

**Regra de parada:** se a categoria 1 falhar, pare aí. Todo o resto da Jana do negócio se apoia no mesmo agregador de dados — se o retrato diário estiver errado ou for genérico, nada acima dele conserta.

**Próximo passo concreto, pós-§5.2:** a categoria 1 **não falhou nem passou** — ficou indeterminada por falta de dado no tenant testável. Destravá-la não custa código: [W] abre o chat em `biz=4`, digita `brief`, e relata o que viu. É o único caminho que dá amostra com dado real, e nenhum agente pode percorrê-lo por ele (R6).

Independente disso, **os 3 defeitos de §5.2 são consertáveis já** e não dependem de amostra nova: a linha fabricada, a promessa *"próximo brief: amanhã, 8h"* e o entusiasmo sobre zeros. Os três são de **curadoria de texto**, não de dado.

Isto **antecede** a decisão de migrar ou aposentar as telas Blade: não faz sentido escolher a forma de telas antes de saber se a capacidade por trás vale.

## 7. Limite honesto

- Os números de RAGAS são de **2026-07-01**, medidos no CT 100 (staging), n=51. Não remedi hoje: rodar é CT 100 ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)) e custa dinheiro no modo real.
- Não verifiquei em runtime se `JANA_CHAT_TOOLS_ENABLED` está de fato `true` no `.env` do Hostinger — a US-COPI-142 diz que o flip foi feito em 2026-07-17, mas isso é **declaração**, não leitura do runtime. Conferir antes da categoria 5.
- Os "resultados esperados" (≥4 de 5, ≥3 de 5) são **critérios de decisão propostos**, não medições. São o que eu sugiro como corte; o corte é de [W].
