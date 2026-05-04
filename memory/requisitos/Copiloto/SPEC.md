# Especificação funcional — Copiloto

## 1. Personas

| Persona | Contexto | Acesso |
|---|---|---|
| **Dono/operador de business** | Ex.: Larissa (ROTA LIVRE). Quer clareza de rumo sem virar analista. | `business_id` scoped |
| **Superadmin oimpresso** | Wagner. Vê metas da plataforma + pode auditar metas de clientes | global |
| **Gestor delegado** | Funcionário com permissão comercial/financeira. | `business_id` scoped, sem delete |
| **Leitor/auditor** | Contador, sócio passivo. Só consulta, sem chat ativo. | read-only |

## 2. User stories

> **Convenção:** `US-COPI-NNN`
> **DoD mínimo (aplicado a todas):** rota autorizada (`403` se não), scope `business_id` quando aplicável, FormRequest pra input, shape JSON-friendly via `->transform()`, Feature test (auth + permissão + validação), dark mode, mobile responsivo, toast `sonner` em mutations.

### Área Chat

#### US-COPI-001 · Iniciar conversa com o Copiloto
- **Rota:** `GET /copiloto`
- **Controller:** `ChatController@index`
- **Como** gestor **quero** abrir o Copiloto **para** ver snapshot atual e iniciar conversa.
- **DoD extra:** página carrega com briefing auto-gerado (faturamento 90d, tendência, nº clientes ativos) sem clique adicional.

#### US-COPI-002 · Enviar mensagem ao Copiloto
- **Rota:** `POST /copiloto/conversas/{id}/mensagens`
- **Controller:** `ChatController@send`
- **Como** gestor **quero** descrever cenário ou pedir sugestão **para** obter propostas.
- **DoD extra:** resposta assíncrona (`202` + polling OU streaming SSE); tokens contados por request.

#### US-COPI-003 · Receber propostas estruturadas
- **Controller:** `ChatController@send` (mesmo endpoint, response inclui sugestões)
- **Como** gestor **quero** ver 3–5 propostas lado a lado **para** comparar cenários.
- **DoD extra:** schema zod valida shape `{propostas: [{nome, metrica, valor, periodo, racional, dificuldade, dependencias}]}`.

#### US-COPI-004 · Escolher proposta
- **Rota:** `POST /copiloto/sugestoes/{id}/escolher`
- **Controller:** `ChatController@escolher`
- **Como** gestor **quero** aceitar uma proposta **para** criar a meta automaticamente + agendar apuração.
- **DoD extra:** cria `Meta` + `MetaPeriodo` + `MetaFonte`; agenda `ApurarMetaJob` no Horizon; redireciona pro dashboard.

#### US-COPI-005 · Arquivar conversa
- **Rota:** `PATCH /copiloto/conversas/{id}` body `{status: 'arquivada'}`
- **Como** gestor **quero** arquivar conversas antigas **para** limpar a listagem.

### Área Metas

#### US-COPI-010 · Listar metas ativas
- **Rota:** `GET /copiloto/metas`
- **Controller:** `MetasController@index`
- **Como** gestor **quero** ver todas minhas metas **para** visão consolidada.

#### US-COPI-011 · Ver detalhe de meta + série temporal
- **Rota:** `GET /copiloto/metas/{id}`
- **Controller:** `MetasController@show`
- **DoD extra:** série temporal últimas 12 janelas; projeção linear; farol verde/amarelo/vermelho por threshold.

#### US-COPI-012 · Criar meta manualmente (sem chat)
- **Rota:** `GET /copiloto/metas/create` + `POST /copiloto/metas`
- **Como** gestor **quero** criar meta direto **para** casos em que já sei o que quero.
- **DoD extra:** wizard 3 passos: escolher métrica, definir período + alvo, configurar fonte.

#### US-COPI-013 · Editar meta
- **Rota:** `PATCH /copiloto/metas/{id}`
- **DoD extra:** edição registra `activitylog` com motivo obrigatório (campo textarea).

#### US-COPI-014 · Arquivar meta (soft delete)
- **Rota:** `DELETE /copiloto/metas/{id}`
- **DoD extra:** `ativo=false`, não apaga histórico. AlertDialog `"você tem certeza"`.

### Área Períodos

#### US-COPI-020 · Adicionar período a uma meta
- **Rota:** `POST /copiloto/metas/{id}/periodos`
- **Como** gestor **quero** segmentar meta anual em trimestres/meses **para** cobrar trajetória.

#### US-COPI-021 · Editar alvo de período
- **Rota:** `PATCH /copiloto/periodos/{id}`
- **DoD extra:** log em activitylog + motivo.

### Área Apuração

#### US-COPI-030 · Apuração automática por job
- **Fluxo:** `ApurarMetaJob` (agendado) lê `MetaFonte` → executa driver → grava `MetaApuracao`.
- **DoD extra:** idempotente (mesma `data_ref` + `fonte_query_hash` não duplica); erro loga e alerta superadmin.

#### US-COPI-031 · Forçar reapuração manual
- **Rota:** `POST /copiloto/metas/{id}/reapurar`
- **Controller:** `MetasController@reapurar`
- **Como** gestor **quero** reapurar meta **para** casos de correção retroativa de venda.
- **DoD extra:** apaga `MetaApuracao` do range + reexecuta driver.

### Área Fontes

#### US-COPI-040 · Ver/editar fonte da meta
- **Rota:** `GET /copiloto/metas/{id}/fonte` + `PATCH`
- **Como** usuário técnico/superadmin **quero** editar o SQL ou PHP **para** ajustar o cálculo.
- **DoD extra:** permissão `copiloto.fontes.edit`; preview do resultado antes de salvar; SQL roda em contexto `business_id` injetado (não o usuário mete `SELECT * FROM users`).

### Área Dashboard

#### US-COPI-050 · Dashboard consolidado
- **Rota:** `GET /copiloto/dashboard`
- **Controller:** `DashboardController@index`
- **DoD extra:** cards por meta ativa; sparkline inline; farol; link direto pro detalhe.

### Área Alertas

#### US-COPI-060 · Listar alertas pendentes
- **Rota:** `GET /copiloto/alertas`
- **DoD extra:** filtro por severidade, status (novo/visto/resolvido).

#### US-COPI-061 · Configurar thresholds
- **Rota:** `GET /copiloto/alertas/config` + `PATCH`
- **Campos:** desvio % aceitável, canal (email, in-app, WhatsApp futuro), frequência.

### Área Administração — Onda 1 (ROI direto, ver ADR [`arq/0003`](adr/arq/0003-administracao-roi-governance.md))

#### US-COPI-070 · Dashboard de custo IA
- **Rota:** `GET /copiloto/admin/custos`
- **Controller:** `Admin\CustosController@index`
- **Como** admin do business **quero** ver quanto a IA custou esse mês **para** controlar orçamento e justificar ROI.
- **DoD extra:** card "Esse mês" (R$, #mensagens, #tokens, #usuários ativos); tabela por usuário (nome, #conversas, #mensagens, tokens consumidos, R$ aprox); gráfico diário 90d; preço lido de `config('copiloto.ai.pricing.{modelo}.{input,output}')` em USD/1k tokens × câmbio configurável; permissão `copiloto.admin.custos.view`.

#### US-COPI-071 · Definir orçamento mensal de IA
- **Rota:** `GET /copiloto/admin/orcamento` + `POST`
- **Controller:** `Admin\OrcamentoController@show` + `@update`
- **Como** admin do business **quero** definir limite de R$ por mês de IA **para** nunca tomar susto na fatura.
- **DoD extra:** tabela `copiloto_orcamentos` (id, business_id, tipo enum [`mensal_business`, `diario_user`], limite_tokens, limite_brl, acao_estouro enum [`bloquear`, `alertar`, `degradar_modelo`]); plano comercial (Essencial/Profissional/Enterprise) define teto que admin não pode ultrapassar; wizard no primeiro acesso pergunta "quanto você quer gastar com IA por mês? R$ ___" + sugestão baseada no plano; permissão `copiloto.admin.orcamento.manage`.

#### US-COPI-072 · Bloquear chamada IA quando orçamento estourar
- **Componente:** middleware `EnforceOrcamento` (cross-cutting, sem rota própria)
- **Como** admin do business **quero** que usuários não consigam usar IA depois de estourar o limite **para** conter custo.
- **DoD extra:** middleware aplicado em `ChatController@send` e qualquer rota futura que chame IA; resposta HTTP `402 Payment Required` (ou `429`) com mensagem clara "Cota IA esgotada esse mês — fale com o admin"; admin recebe notificação in-app + email quando atinge 80% e 100% do limite; soft-degrade opcional (troca pra modelo mais barato em vez de bloquear).

#### US-COPI-073 · Listar conversas do business (admin)
- **Rota:** `GET /copiloto/admin/conversas`
- **Controller:** `Admin\ConversasController@index`
- **Como** admin do business **quero** ver todas as conversas dos meus funcionários **para** auditar uso e extrair valor (FAQ recorrente, dúvidas comuns).
- **DoD extra:** filtros (usuário, período, busca full-text no `content`); paginação; permissão `copiloto.admin.conversas.view` (SEPARADA de `copiloto.superadmin` que é cross-business); link drill-down pra US-COPI-074.

#### US-COPI-074 · Visualizar conversa em modo read-only (admin)
- **Rota:** `GET /copiloto/admin/conversas/{id}`
- **Controller:** `Admin\ConversasController@show`
- **Como** admin do business **quero** abrir uma conversa de funcionário em modo só-leitura **para** auditar contexto sem poder responder.
- **DoD extra:** UI sem campo de input; ao abrir, insere mensagem `role='system'` na própria conversa com texto "Visualizada por {admin_nome} em {timestamp}" (transparência — usuário vê na próxima vez que abrir); activitylog grava a ação; permissão `copiloto.admin.conversas.view`.

#### US-COPI-075 · Card "Status do orçamento" no chat
- **Componente:** `OrcamentoStatusCard` (visível em todas as telas `/copiloto/*`)
- **Como** qualquer usuário **quero** ver quanto da minha cota / cota do business já foi consumida **para** me autorregular sem precisar perguntar pro admin.
- **DoD extra:** badge no canto superior; verde (<60%), amarelo (60-90%), vermelho (>90%); admin enxerga cota do business, usuário comum enxerga cota individual; tooltip mostra detalhe (ex.: "23.4k de 50k tokens — sobram 7 dias do mês"); polling a cada 5min ou após cada mensagem enviada.

## 3. Regras de negócio (Gherkin)

### Feature: Proposta de metas pelo Copiloto

```gherkin
Cenário: Copiloto precisa de contexto mínimo antes de propor
  Dado que o business não tem NENHUMA transação registrada
  Quando o gestor pede "sugira metas"
  Então o Copiloto NÃO propõe metas numéricas
  E responde pedindo dados básicos (setor, expectativa, histórico fora do sistema)

Cenário: Propostas vêm em cenários contrastantes
  Dado que o business tem histórico de 90+ dias de transações
  Quando o gestor pede "sugira metas de faturamento pra 2026"
  Então o Copiloto retorna 3-5 propostas
  E cada proposta tem dificuldade classificada em (fácil | realista | ambiciosa)
  E pelo menos uma proposta é da categoria "realista"

Cenário: Escolher proposta cria meta ativa imediatamente
  Dado que o Copiloto entregou 3 propostas
  Quando o gestor escolhe a proposta #2
  Então uma nova Meta é criada com origem=chat_ia
  E um MetaPeriodo é criado com o valor_alvo da proposta
  E um MetaFonte é criado com driver SQL default pra aquela métrica
  E um ApurarMetaJob é agendado no Horizon
  E activitylog registra "meta criada via copiloto, sugestão #<id>"
```

### Feature: Apuração

```gherkin
Cenário: Apuração diária de meta de faturamento
  Dado que existe Meta "Faturamento Mensal" com fonte SQL
  E que são 02:00 de um dia útil
  Quando o job ApurarMetaJob roda
  Então lê a SQL da MetaFonte
  E executa com business_id injetado via bind
  E grava MetaApuracao com data_ref = hoje-1
  E se `fonte_query_hash` já existe pra essa data, substitui valor_realizado sem duplicar linha

Cenário: Erro na apuração notifica superadmin
  Dado que a SQL de uma MetaFonte tem sintaxe inválida
  Quando ApurarMetaJob roda
  Então o job NÃO falha silenciosamente
  E um alerta é gerado com severidade "alta"
  E o superadmin é notificado em <= 5min
```

### Feature: Tenancy

```gherkin
Cenário: Usuário de business não vê metas de outro business
  Dado que existe Meta A (business_id=4) e Meta B (business_id=7)
  Quando um usuário do business 4 chama GET /copiloto/metas
  Então só Meta A aparece

Cenário: Superadmin vê metas da plataforma
  Dado que existe Meta P (business_id=null, platform-wide)
  Quando o superadmin acessa /copiloto/superadmin/metas
  Então Meta P aparece

Cenário: Usuário comum NÃO vê metas da plataforma
  Dado que existe Meta P (business_id=null)
  Quando um usuário sem permissão copiloto.superadmin chama GET /copiloto/metas
  Então Meta P NÃO aparece
```

### Feature: Alertas

```gherkin
Cenário: Desvio acima do threshold dispara alerta
  Dado que uma Meta tem threshold de desvio = 10%
  E que a projeção linear até hoje é R$ [redacted Tier 0]k
  E que o realizado apurado é R$ [redacted Tier 0]k
  Quando o AlertaService roda
  Então um alerta é criado com severidade "média" (desvio = 15% > 10%)
  E canal configurado (email ou in-app) recebe notificação
```

## 4. Eventos de domínio

| Evento | Payload | Ouvintes |
|---|---|---|
| `CopilotoMetaCriada` | `meta_id, origem, conversa_id?` | ActivityLog, agenda ApurarMetaJob |
| `CopilotoMetaEscolhida` | `sugestao_id, meta_id` | activitylog, feedback prompt |
| `CopilotoMetaApurada` | `meta_id, data_ref, valor` | AlertaService |
| `CopilotoDesvioDetectado` | `meta_id, desvio_pct, severidade` | NotificationBus |
| `CopilotoConversaIniciada` | `conversa_id, user_id` | telemetria |

## 5. Decisões em aberto (que viram US futuras)

- ~~Limite de tokens por conversa (custo IA)?~~ → endereçado por US-COPI-070/071/072/075 (Onda 1 da camada admin, ADR `arq/0003`).
- Audit log + LGPD (export/delete/anonimização) → Onda 2 da ADR `arq/0003`, ainda não quebrado em US.
- Insights agregados (top tópicos, heatmap), tags, cross-business pra grupo econômico → Onda 3 da ADR `arq/0003`, depende da implementação da ADR `decisions/0020` (matriz_id em business).
- Exportação do dashboard em PDF? v2.
- Comparação com período anterior no dashboard? Já dá pra fazer desde v1 — incluir se sobrar tempo.

---

**Última atualização:** 2026-05-04 (adicionadas US-COPI-076..081 — cronograma Cycle 01 W19/W20 pós-modularização)

---

## US-COPI-076..081 · Cronograma Cycle 01 (semanas W19+W20)

Tasks criadas após sessão 2026-05-04 que entregou 4 PRs de modularização (split TeamMcp, split KB, Usuário 360°, delete /ads/admin/kb duplicado). Sequência prioriza fechar dívida documental, evoluir contexto Claude, validar com user real, e medir com RAGAS no fim do cycle.

### US-COPI-076 · ADRs formais split modular + Permission Registry + atualizar 5 ADRs com URLs antigas

> owner: wagner · sprint: 2026-W19 · priority: p2 · estimate: 2h · status: done · done_at: 2026-05-04

Fechar dívida documental da sessão 2026-05-04:

- Criar ADR "Modularização — split TeamMcp/KB/360°" (registra a decisão arquitetural dos 4 PRs mergeados)
- Criar ADR "Permission Registry contract" (formaliza padrão `Modules/*/Resources/permissions.php`)
- Atualizar URLs antigas em 5 ADRs (search & replace doc-only): 0055, 0057, 0059, 0061, _SCHEMA

**Acceptance**: 2 ADRs novos commitados + 5 ADRs com URLs `/copiloto/admin/{team,memoria,cc-sessions}` substituídas por `/team-mcp/*` e `/kb`.

### US-COPI-077 · ContextForTaskService consumir tasks-current MCP em vez de ler CURRENT.md

> owner: wagner · sprint: 2026-W19 · priority: p1 · estimate: 2h · status: done · done_at: 2026-05-04 · commit: 6bca4c1b

Wagner reclamou 2026-05-03: "CURRENT.md ativo deve ser substituido pelas tarefas que ja foi feito". Hoje `Modules/ADS/Services/ContextForTaskService.php::buildCycleFocus()` lê filesystem CURRENT.md.

Trocar por chamada interna à tool MCP `tasks-current` (mesma fonte que `/team-mcp/tasks` consome). Output: últimas 5 tasks `outcome=success` + tasks ativas, em vez do "Goal do ciclo" estático.

**Acceptance**: `buildCycleFocus()` removido/reescrito pra `buildRecentlyCompleted()` · lê de `mcp_dual_brain_decisions WHERE outcome='success' ORDER BY id DESC LIMIT 5` + tasks ativas · `POST /api/ads/context-for-task` retorna seção atualizada · 1 teste Pest.

### US-COPI-078 · Schema tipado KB — migration + validação webhook

> owner: wagner · sprint: 2026-W19 · priority: p1 · estimate: 6h · status: todo

Etapa 2.5 do plano modular (adiada do PR feat/split-kb). KB hoje é schema genérico (`mcp_memory_documents` com `type` text livre). Tipar formalmente:

- Migration: `status` (active/deprecated/superseded), `expires_at`, `superseded_by` (FK self), `frontmatter_json`
- Contrato por type: ADR, Session, Runbook, Comparativo
- Validação no webhook GitHub `/api/mcp/sync-memory`: rejeita doc inválido com mensagem clara
- Tela `/kb` mostra status colorido + filtro por type+status

**Acceptance**: migration aplicada · 3 types validados (ADR/Session/Runbook) · webhook rejeita doc inválido · 5 testes Pest.

### US-COPI-079 · Demo Maíra real — Claude Code + /team-mcp + tela 360°

> owner: wagner · sprint: 2026-W19 · priority: p1 · estimate: 2h · status: todo

Validação de produto end-to-end com user real (Maíra, dev junior). Sessão presencial Wagner+Maíra:

1. Maíra abre Claude Code dela apontando pro `mcp.oimpresso.com` com token MCP
2. Tenta tocar arquivo NFSe → bloqueio Permission Registry / scopes ADS
3. Wagner abre `/superadmin/usuarios/{maira_id}/360` e mostra tudo dela num lugar
4. Wagner concede acesso a Compras via `/ads/admin/team-scopes`
5. Maíra repete e funciona
6. Wagner trança usuário (botão Trancar) → Maíra perde acesso em <30s
7. Wagner destranca

**Acceptance**: 6 passos rodam sem manual fix · gravação curta · session log com lessons learned.

### US-COPI-080 · Buffer fix — corrigir o que demo Maíra encontrar

> owner: wagner · sprint: 2026-W19 · priority: p2 · estimate: 4h · status: todo · blocked_by: US-COPI-079

Slot reservado pra fix dos bugs/UX issues que aparecerem na demo. Margem de segurança Cycle 01 antes do gate RAGAS.

**Acceptance**: backlog vazio do que veio da demo OR documentado como tech-debt pra Cycle 02 com priority justificada.

### US-COPI-081 · Sprint 7 RAGAS — gate de medição Cycle 01

> owner: wagner · sprint: 2026-W20 · priority: p0 · estimate: 12h · status: todo

Sprint 7 do roadmap Tier 7-9 (ADR 0037). Gate quantitativo do Cycle 01: provar que memória/RAG funciona com métrica reproduzível.

- Setup RAGAS no CT 100 (pip install + container Docker se necessário)
- Golden set: 50 perguntas Larissa-style (faturamento, metas, custos) com resposta correta conhecida
- Pipeline: pergunta → ContextoNegocio + memoria_recall → resposta Sonnet → score RAGAS (faithfulness + answer_relevancy + context_precision)
- Baseline: rodar contra prod, registrar score
- Documentar em `memory/sessions/2026-05-NN-ragas-baseline.md`

**Acceptance**: golden set 50q · script RAGAS reproduzível · baseline numérico em ADR ou session log · 3 perguntas+scores como evidência.
