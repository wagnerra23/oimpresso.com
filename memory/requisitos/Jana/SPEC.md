---
module: Jana
status: ativo
version: "3.1.0"
last_updated: "2026-05-25"
owner: wagner
parent_adr: "0094-constituicao-v2-7-camadas-8-principios"
related_adrs:
  - "0035-stack-ai-canonica-wagner-2026-04-26"
  - "0048-framework-agentes-laravel-ai-vizra-rejeitada"
  - "0052-contextonegocio-expor-multiplos-angulos"
  - "0053-mcp-server-governanca-como-produto"
  - "0061-conhecimento-canonico-git-mcp-zero-automem"
  - "0062-separacao-runtime-hostinger-ct100"
  - "0091-daily-brief"
  - "0093-multi-tenant-isolation-tier-0"
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0095-skills-tiers-convencao-interna"
  - "0101-tests-business-id-1-nunca-cliente"
  - "0104-processo-mwart-canonico-unico-caminho"
  - "0106-recalibracao-velocidade-fator-10x-ia-pair"
  - "0114-prototipo-ui-cowork-loop-formalizado"
  - "0119-paralelismo-sessoes-whats-active-tier-1"
  - "0130-handoff-append-only-mcp-first"
  - "0131-tiering-memoria-canonico-local-segredo"
---

# Especificação funcional — Jana

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

#### US-COPI-001 · Iniciar conversa com a Jana
**Implementado em:** `Modules/Jana/Http/Controllers/ChatController.php` · `resources/js/Pages/Jana/Chat.tsx` · `Modules/Jana/Services/ContextSnapshotService.php` · verificado@dd3ed7c (2026-07-01) — index() cria conversa + gera briefing auto e renderiza a page Jana/Chat; rota viva GET /ia/ (jana.chat.index)
- **Rota:** `GET /copiloto`
- **Controller:** `ChatController@index`
- **Como** gestor **quero** abrir a Jana **para** ver snapshot atual e iniciar conversa.
- **DoD extra:** página carrega com briefing auto-gerado (faturamento 90d, tendência, nº clientes ativos) sem clique adicional.

#### US-COPI-002 · Enviar mensagem à Jana
**Implementado em:** `Modules/Jana/Http/Controllers/ChatController.php` · `Modules/Jana/Ai/Agents/ChatCopilotoAgent.php` · verificado@dd3ed7c (2026-07-01) — send() na rota viva POST /ia/conversas/{id}/mensagens + sendStream() SSE token-a-token na rota .../stream
- **Rota:** `POST /copiloto/conversas/{id}/mensagens`
- **Controller:** `ChatController@send`
- **Como** gestor **quero** descrever cenário ou pedir sugestão **para** obter propostas.
- **DoD extra:** resposta assíncrona (`202` + polling OU streaming SSE); tokens contados por request.

#### US-COPI-003 · Receber propostas estruturadas
**Implementado em:** _parcial_ · `Modules/Jana/Ai/Agents/SugestoesMetasAgent.php` · `Modules/Jana/Entities/Sugestao.php` · `Modules/Jana/Http/Controllers/ChatController.php` · verificado@dd3ed7c (2026-07-01) — agente de sugestões + persistência `Sugestao` + payload `sugestoesPendentes` no render existem; validação zod do shape 3–5 propostas lado a lado no frontend não confirmada
- **Controller:** `ChatController@send` (mesmo endpoint, response inclui sugestões)
- **Como** gestor **quero** ver 3–5 propostas lado a lado **para** comparar cenários.
- **DoD extra:** schema zod valida shape `{propostas: [{nome, metrica, valor, periodo, racional, dificuldade, dependencias}]}`.

#### US-COPI-004 · Escolher proposta
**Implementado em:** `Modules/Jana/Http/Controllers/ChatController.php` · `Modules/Jana/Entities/Meta.php` · `Modules/Jana/Entities/MetaPeriodo.php` · `Modules/Jana/Entities/MetaFonte.php` · verificado@dd3ed7c (2026-07-01) — escolher() cria Meta+MetaPeriodo+MetaFonte, dispara ApurarMetaJob e redireciona pra meta (rota viva POST /ia/sugestoes/{id}/escolher)
- **Rota:** `POST /copiloto/sugestoes/{id}/escolher`
- **Controller:** `ChatController@escolher`
- **Como** gestor **quero** aceitar uma proposta **para** criar a meta automaticamente + agendar apuração.
- **DoD extra:** cria `Meta` + `MetaPeriodo` + `MetaFonte`; agenda `ApurarMetaJob` no Horizon; redireciona pro dashboard.

#### US-COPI-005 · Arquivar conversa
**Implementado em:** `Modules/Jana/Http/Controllers/ChatController.php` · `Modules/Jana/Entities/Conversa.php` · verificado@dd3ed7c (2026-07-01) — updateConversa() faz update apenas de titulo+status (rota viva PATCH /ia/conversas/{id})
- **Rota:** `PATCH /copiloto/conversas/{id}` body `{status: 'arquivada'}`
- **Como** gestor **quero** arquivar conversas antigas **para** limpar a listagem.

### Área Metas

#### US-COPI-010 · Listar metas ativas
**Implementado em:** `Modules/Jana/Http/Controllers/MetasController.php` · `Modules/Jana/Resources/views/metas/index.blade.php` · verificado@dd3ed7c (2026-07-01) — index() lista metas scopadas por business (rota viva GET /ia/metas); UI ainda Blade (não migrada pra Inertia)
- **Rota:** `GET /copiloto/metas`
- **Controller:** `MetasController@index`
- **Como** gestor **quero** ver todas minhas metas **para** visão consolidada.

#### US-COPI-011 · Ver detalhe de meta + série temporal
**Implementado em:** _parcial_ · `Modules/Jana/Http/Controllers/MetasController.php` · `Modules/Jana/Resources/views/metas/show.blade.php` · verificado@dd3ed7c (2026-07-01) — show() entrega meta + apurações (rota viva GET /ia/metas/{id}); série 12 janelas + projeção linear + farol dependem do render Blade (farol/projeção não confirmados)
- **Rota:** `GET /copiloto/metas/{id}`
- **Controller:** `MetasController@show`
- **DoD extra:** série temporal últimas 12 janelas; projeção linear; farol verde/amarelo/vermelho por threshold.

#### US-COPI-012 · Criar meta manualmente (sem chat)
**Implementado em:** _parcial_ · `Modules/Jana/Http/Controllers/MetasController.php` · `Modules/Jana/Resources/views/metas/create.blade.php` · verificado@dd3ed7c (2026-07-01) — create()/store() (via StoreMetaRequest) existem (rotas vivas GET /ia/metas/create + POST /ia/metas); wizard 3 passos da UX não confirmado no Blade
- **Rota:** `GET /copiloto/metas/create` + `POST /copiloto/metas`
- **Como** gestor **quero** criar meta direto **para** casos em que já sei o que quero.
- **DoD extra:** wizard 3 passos: escolher métrica, definir período + alvo, configurar fonte.

#### US-COPI-013 · Editar meta
**Implementado em:** _parcial_ · `Modules/Jana/Http/Controllers/MetasController.php` · `Modules/Jana/Resources/views/metas/edit.blade.php` · verificado@dd3ed7c (2026-07-01) — edit()/update() (via UpdateMetaRequest) existem (rota viva PATCH /ia/metas/{id}); motivo obrigatório + registro em activitylog não confirmados
- **Rota:** `PATCH /copiloto/metas/{id}`
- **DoD extra:** edição registra `activitylog` com motivo obrigatório (campo textarea).

#### US-COPI-014 · Arquivar meta (soft delete)
**Implementado em:** `Modules/Jana/Http/Controllers/MetasController.php` · `Modules/Jana/Entities/Meta.php` · verificado@dd3ed7c (2026-07-01) — destroy() faz soft delete (ativo=false) preservando histórico (rota viva DELETE /ia/metas/{id})
- **Rota:** `DELETE /copiloto/metas/{id}`
- **DoD extra:** `ativo=false`, não apaga histórico. AlertDialog `"você tem certeza"`.

### Área Períodos

#### US-COPI-020 · Adicionar período a uma meta
**Implementado em:** `Modules/Jana/Http/Controllers/PeriodosController.php` · `Modules/Jana/Entities/MetaPeriodo.php` · verificado@dd3ed7c (2026-07-01) — `store()` (via `StorePeriodoRequest`) cria `MetaPeriodo` scopado (rota resource viva `/ia/metas.periodos` only store/update/destroy)
- **Rota:** `POST /copiloto/metas/{id}/periodos`
- **Como** gestor **quero** segmentar meta anual em trimestres/meses **para** cobrar trajetória.

#### US-COPI-021 · Editar alvo de período
**Implementado em:** _parcial_ · `Modules/Jana/Http/Controllers/PeriodosController.php` · `Modules/Jana/Entities/MetaPeriodo.php` · verificado@dd3ed7c (2026-07-01) — `update()` (via `UpdatePeriodoRequest`) escopado por `meta_id` existe; log em activitylog + motivo não confirmados
- **Rota:** `PATCH /copiloto/periodos/{id}`
- **DoD extra:** log em activitylog + motivo.

### Área Apuração

#### US-COPI-030 · Apuração automática por job
**Implementado em:** `Modules/Jana/Jobs/ApurarMetaJob.php` · `Modules/Jana/Services/ApuracaoService.php` · `Modules/Jana/Entities/MetaApuracao.php` · verificado@dd3ed7c (2026-07-01) — `ApuracaoService::apurar()` resolve driver por `MetaFonte` e persiste com `updateOrCreate` idempotente por (`meta_id`,`data_ref`,`fonte_query_hash`); Job `ShouldQueue` recebe `$businessId`
- **Fluxo:** `ApurarMetaJob` (agendado) lê `MetaFonte` → executa driver → grava `MetaApuracao`.
- **DoD extra:** idempotente (mesma `data_ref` + `fonte_query_hash` não duplica); erro loga e alerta superadmin.

#### US-COPI-031 · Forçar reapuração manual
**Implementado em:** _parcial_ · `Modules/Jana/Http/Controllers/MetasController.php` · verificado@dd3ed7c (2026-07-01) — rota viva POST /ia/metas/{id}/reapurar + método reapurar() existem, mas o dispatch do ApurarMetaJob está comentado (// TODO) — só redireciona com flash; apagar MetaApuracao do range + reexecutar driver ainda não plugado
- **Rota:** `POST /copiloto/metas/{id}/reapurar`
- **Controller:** `MetasController@reapurar`
- **Como** gestor **quero** reapurar meta **para** casos de correção retroativa de venda.
- **DoD extra:** apaga `MetaApuracao` do range + reexecuta driver.

### Área Fontes

#### US-COPI-040 · Ver/editar fonte da meta
**Implementado em:** _parcial_ · `Modules/KB/Http/Controllers/FontesController.php` · `Modules/Jana/Entities/MetaFonte.php` · verificado@dd3ed7c (2026-07-01) — FontesController show/update (migrado pra Modules/KB, rotas vivas GET+PATCH em /ia/metas/{id}/fonte) existe; preview + injeção segura de business_id no SQL não confirmados nesta verificação
- **Rota:** `GET /copiloto/metas/{id}/fonte` + `PATCH`
- **Como** usuário técnico/superadmin **quero** editar o SQL ou PHP **para** ajustar o cálculo.
- **DoD extra:** permissão `copiloto.fontes.edit`; preview do resultado antes de salvar; SQL roda em contexto `business_id` injetado (não o usuário mete `SELECT * FROM users`).

### Área Dashboard

#### US-COPI-050 · Dashboard consolidado
**Implementado em:** `Modules/Jana/Http/Controllers/DashboardController.php` · `resources/js/Pages/Jana/Dashboard.tsx` · verificado@dd3ed7c (2026-07-01) — index() renderiza a page Jana Dashboard com metas ativas (cards + sparkline via buildMetasPayload, scope multi-tenant); rota viva GET /ia/dashboard (jana.dashboard.index)
- **Rota:** `GET /copiloto/dashboard`
- **Controller:** `DashboardController@index`
- **DoD extra:** cards por meta ativa; sparkline inline; farol; link direto pro detalhe.

### Área Alertas

#### US-COPI-060 · Listar alertas pendentes
**Implementado em:** _parcial_ · `Modules/Jana/Http/Controllers/AlertasController.php` · `Modules/Jana/Services/AlertaService.php` · `Modules/Jana/Resources/views/alertas/index.blade.php` · verificado@dd3ed7c (2026-07-01) — `index()` + `AlertaService::avaliar()` (dispara desvio > threshold) existem; filtros por severidade/status na UI Blade não confirmados
- **Rota:** `GET /copiloto/alertas`
- **DoD extra:** filtro por severidade, status (novo/visto/resolvido).

#### US-COPI-061 · Configurar thresholds
**Implementado em:** _parcial_ · `Modules/Jana/Http/Controllers/AlertasController.php` · `Modules/Jana/Resources/views/alertas/config.blade.php` · verificado@dd3ed7c (2026-07-01) — config()/updateConfig() (via UpdateAlertasConfigRequest) + rotas vivas GET+PATCH em /ia/alertas/config; campos canal (email/in-app/WhatsApp) + frequência não confirmados como persistidos
- **Rota:** `GET /copiloto/alertas/config` + `PATCH`
- **Campos:** desvio % aceitável, canal (email, in-app, WhatsApp futuro), frequência.

### Área Administração — Onda 1 (ROI direto, ver ADR [`arq/0003`](adr/arq/0003-administracao-roi-governance.md))

#### US-COPI-070 · Dashboard de custo IA
**Implementado em:** `Modules/Jana/Http/Controllers/Admin/CustosController.php` · `resources/js/Pages/Jana/Admin/Custos/Index.tsx` · `Modules/Jana/Services/CustosService.php` · verificado@dd3ed7c (2026-07-01) — index() renderiza a page Custos com card/tabela por usuário + custo (rota viva GET /ia/admin/custos, jana.admin.custos.index)
- **Rota:** `GET /copiloto/admin/custos`
- **Controller:** `Admin\CustosController@index`
- **Como** admin do business **quero** ver quanto a IA custou esse mês **para** controlar orçamento e justificar ROI.
- **DoD extra:** card "Esse mês" (R$, #mensagens, #tokens, #usuários ativos); tabela por usuário (nome, #conversas, #mensagens, tokens consumidos, R$ aprox); gráfico diário 90d; preço lido de `config('copiloto.ai.pricing.{modelo}.{input,output}')` em USD/1k tokens × câmbio configurável; permissão `copiloto.admin.custos.view`.

#### US-COPI-071 · Definir orçamento mensal de IA
**Implementado em:** _pendente_ — tabela `copiloto_orcamentos`/`jana_orcamentos`, controller `Admin\OrcamentoController` e rotas `/ia/admin/orcamento` não existem (grep amplo em Modules/ + Database/Migrations sem match); QuotaEnforcer atual cobre cota por token MCP (`mcp_quotas`), não orçamento R$ por business
- **Rota:** `GET /copiloto/admin/orcamento` + `POST`
- **Controller:** `Admin\OrcamentoController@show` + `@update`
- **Como** admin do business **quero** definir limite de R$ por mês de IA **para** nunca tomar susto na fatura.
- **DoD extra:** tabela `copiloto_orcamentos` (id, business_id, tipo enum [`mensal_business`, `diario_user`], limite_tokens, limite_brl, acao_estouro enum [`bloquear`, `alertar`, `degradar_modelo`]); plano comercial (Essencial/Profissional/Enterprise) define teto que admin não pode ultrapassar; wizard no primeiro acesso pergunta "quanto você quer gastar com IA por mês? R$ ___" + sugestão baseada no plano; permissão `copiloto.admin.orcamento.manage`.

#### US-COPI-072 · Bloquear chamada IA quando orçamento estourar
**Implementado em:** _pendente_ — middleware `EnforceOrcamento` não existe (grep sem match em Modules/); depende de US-COPI-071 (schema de orçamento R$ por business). QuotaEnforcer bloqueia por cota de token MCP (429), conceito distinto do teto R$/mês por business
- **Componente:** middleware `EnforceOrcamento` (cross-cutting, sem rota própria)
- **Como** admin do business **quero** que usuários não consigam usar IA depois de estourar o limite **para** conter custo.
- **DoD extra:** middleware aplicado em `ChatController@send` e qualquer rota futura que chame IA; resposta HTTP `402 Payment Required` (ou `429`) com mensagem clara "Cota IA esgotada esse mês — fale com o admin"; admin recebe notificação in-app + email quando atinge 80% e 100% do limite; soft-degrade opcional (troca pra modelo mais barato em vez de bloquear).

#### US-COPI-073 · Listar conversas do business (admin)
**Implementado em:** _pendente_ — controller `Admin\ConversasController`, rota `/ia/admin/conversas` e permissão `copiloto.admin.conversas.view` não existem (grep sem match nas rotas + Controllers do módulo)
- **Rota:** `GET /copiloto/admin/conversas`
- **Controller:** `Admin\ConversasController@index`
- **Como** admin do business **quero** ver todas as conversas dos meus funcionários **para** auditar uso e extrair valor (FAQ recorrente, dúvidas comuns).
- **DoD extra:** filtros (usuário, período, busca full-text no `content`); paginação; permissão `copiloto.admin.conversas.view` (SEPARADA de `copiloto.superadmin` que é cross-business); link drill-down pra US-COPI-074.

#### US-COPI-074 · Visualizar conversa em modo read-only (admin)
**Implementado em:** _pendente_ — depende de US-COPI-073; `Admin\ConversasController@show` (modo read-only + mensagem system de auditoria "Visualizada por…") não existe no módulo
- **Rota:** `GET /copiloto/admin/conversas/{id}`
- **Controller:** `Admin\ConversasController@show`
- **Como** admin do business **quero** abrir uma conversa de funcionário em modo só-leitura **para** auditar contexto sem poder responder.
- **DoD extra:** UI sem campo de input; ao abrir, insere mensagem `role='system'` na própria conversa com texto "Visualizada por {admin_nome} em {timestamp}" (transparência — usuário vê na próxima vez que abrir); activitylog grava a ação; permissão `copiloto.admin.conversas.view`.

#### US-COPI-075 · Card "Status do orçamento" no chat
**Implementado em:** _pendente_ — componente `OrcamentoStatusCard` não existe (grep sem match em resources/); depende de US-COPI-071/072 (schema + cota R$ por business)
- **Componente:** `OrcamentoStatusCard` (visível em todas as telas `/copiloto/*`)
- **Como** qualquer usuário **quero** ver quanto da minha cota / cota do business já foi consumida **para** me autorregular sem precisar perguntar pro admin.
- **DoD extra:** badge no canto superior; verde (<60%), amarelo (60-90%), vermelho (>90%); admin enxerga cota do business, usuário comum enxerga cota individual; tooltip mostra detalhe (ex.: "23.4k de 50k tokens — sobram 7 dias do mês"); polling a cada 5min ou após cada mensagem enviada.

## 3. Regras de negócio (Gherkin)

### Feature: Proposta de metas pela Jana

```gherkin
Cenário: Jana precisa de contexto mínimo antes de propor
  Dado que o business não tem NENHUMA transação registrada
  Quando o gestor pede "sugira metas"
  Então a Jana NÃO propõe metas numéricas
  E responde pedindo dados básicos (setor, expectativa, histórico fora do sistema)

Cenário: Propostas vêm em cenários contrastantes
  Dado que o business tem histórico de 90+ dias de transações
  Quando o gestor pede "sugira metas de faturamento pra 2026"
  Então a Jana retorna 3-5 propostas
  E cada proposta tem dificuldade classificada em (fácil | realista | ambiciosa)
  E pelo menos uma proposta é da categoria "realista"

Cenário: Escolher proposta cria meta ativa imediatamente
  Dado que a Jana entregou 3 propostas
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
| `JanaMetaCriada` | `meta_id, origem, conversa_id?` | ActivityLog, agenda ApurarMetaJob |
| `JanaMetaEscolhida` | `sugestao_id, meta_id` | activitylog, feedback prompt |
| `JanaMetaApurada` | `meta_id, data_ref, valor` | AlertaService |
| `JanaDesvioDetectado` | `meta_id, desvio_pct, severidade` | NotificationBus |
| `JanaConversaIniciada` | `conversa_id, user_id` | telemetria |

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

**Implementado em:** _parcial_ · `memory/decisions/0064-modularizacao-split-teammcp-kb-superadmin360.md` · `memory/decisions/0065-permission-registry-contract.md` · `Modules/ADS/Services/ContextForTaskService.php` · `app/Console/Commands/EvalRagasBaselineCommand.php` · verificado@dd3ed7c (2026-07-01) — epic agregado: 076/077/078/081/082/083 entregues (ADRs + contexto MCP + KB tipado + RAGAS baseline); US-079 (demo Maiara) e US-080 (buffer fix) seguem `todo`
Tasks criadas após sessão 2026-05-04 que entregou 4 PRs de modularização (split TeamMcp, split KB, Usuário 360°, delete /ads/admin/kb duplicado). Sequência prioriza fechar dívida documental, evoluir contexto Claude, validar com user real, e medir com RAGAS no fim do cycle.

### US-COPI-076 · ADRs formais split modular + Permission Registry + atualizar 5 ADRs com URLs antigas

**Implementado em:** `memory/decisions/0064-modularizacao-split-teammcp-kb-superadmin360.md` · `memory/decisions/0065-permission-registry-contract.md` · verificado@dd3ed7c (2026-07-01) — 2 ADRs formais criadas (split modular + Permission Registry contract)

> owner: wagner · sprint: 2026-W19 · priority: p2 · estimate: 2h · status: done · done_at: 2026-05-04

Fechar dívida documental da sessão 2026-05-04:

- Criar ADR "Modularização — split TeamMcp/KB/360°" (registra a decisão arquitetural dos 4 PRs mergeados)
- Criar ADR "Permission Registry contract" (formaliza padrão `Modules/*/Resources/permissions.php`)
- Atualizar URLs antigas em 5 ADRs (search & replace doc-only): 0055, 0057, 0059, 0061, _SCHEMA

**Acceptance**: 2 ADRs novos commitados + 5 ADRs com URLs `/copiloto/admin/{team,memoria,cc-sessions}` substituídas por `/team-mcp/*` e `/kb`.

### US-COPI-077 · ContextForTaskService consumir tasks-current MCP em vez de ler CURRENT.md

**Implementado em:** `Modules/ADS/Services/ContextForTaskService.php` · verificado@dd3ed7c (2026-07-01) — lê `mcp_dual_brain_decisions` (outcome success, LIMIT 5, business_id scoped) em vez de filesystem CURRENT.md

> owner: wagner · sprint: 2026-W19 · priority: p1 · estimate: 2h · status: done · done_at: 2026-05-04 · commit: 6bca4c1b

Wagner reclamou 2026-05-03: "CURRENT.md ativo deve ser substituido pelas tarefas que ja foi feito". Hoje `Modules/ADS/Services/ContextForTaskService.php::buildCycleFocus()` lê filesystem CURRENT.md.

Trocar por chamada interna à tool MCP `tasks-current` (mesma fonte que `/team-mcp/tasks` consome). Output: últimas 5 tasks `outcome=success` + tasks ativas, em vez do "Goal do ciclo" estático.

**Acceptance**: `buildCycleFocus()` removido/reescrito pra `buildRecentlyCompleted()` · lê de `mcp_dual_brain_decisions WHERE outcome='success' ORDER BY id DESC LIMIT 5` + tasks ativas · `POST /api/ads/context-for-task` retorna seção atualizada · 1 teste Pest.

### US-COPI-078 · Schema tipado KB — migration + validação webhook

**Implementado em:** `Modules/Jana/Database/Migrations/2026_05_01_100001_add_typed_cols_to_mcp_memory_documents.php` · `Modules/Jana/Database/Migrations/2026_04_30_120001_expand_mcp_memory_documents_type_enum.php` · verificado@dd3ed7c (2026-07-01) — migrations de colunas tipadas (status/expires_at/superseded_by/frontmatter_json) + enum type aplicadas

> owner: wagner · sprint: 2026-W19 · priority: p1 · estimate: 6h · status: done · progress: 90% · done_at: 2026-05-04 · session: memory/sessions/2026-05-04-ragas-baseline-infra.md

Etapa 2.5 do plano modular (adiada do PR feat/split-kb). KB hoje é schema genérico (`mcp_memory_documents` com `type` text livre). Tipar formalmente:

- Migration: `status` (active/deprecated/superseded), `expires_at`, `superseded_by` (FK self), `frontmatter_json`
- Contrato por type: ADR, Session, Runbook, Comparativo
- Validação no webhook GitHub `/api/mcp/sync-memory`: rejeita doc inválido com mensagem clara
- Tela `/kb` mostra status colorido + filtro por type+status

**Acceptance**: migration aplicada · 3 types validados (ADR/Session/Runbook) · webhook rejeita doc inválido · 5 testes Pest.

### US-COPI-079 · Demo Maiara real — Claude Code + /team-mcp + tela 360°

**Implementado em:** _pendente_ — sessão presencial Wagner+Maiara de validação end-to-end (6 passos + gravação + session log) ainda não realizada (status todo)

> owner: wagner · sprint: 2026-W19 · priority: p1 · estimate: 2h · status: todo

Validação de produto end-to-end com user real (Maiara, dev junior). Sessão presencial Wagner+Maiara:

1. Maiara abre Claude Code dela apontando pro `mcp.oimpresso.com` com token MCP
2. Tenta tocar arquivo NFSe → bloqueio Permission Registry / scopes ADS
3. Wagner abre `/superadmin/usuarios/{maira_id}/360` e mostra tudo dela num lugar
4. Wagner concede acesso a Compras via `/ads/admin/team-scopes`
5. Maiara repete e funciona
6. Wagner trança usuário (botão Trancar) → Maiara perde acesso em <30s
7. Wagner destranca

**Acceptance**: 6 passos rodam sem manual fix · gravação curta · session log com lessons learned.

### US-COPI-080 · Buffer fix — corrigir o que demo Maiara encontrar

**Implementado em:** _pendente_ — slot reservado (blocked_by US-COPI-079); sem demo executada, não há bug/UX-issue a corrigir (status todo)

> owner: wagner · sprint: 2026-W19 · priority: p2 · estimate: 4h · status: todo · blocked_by: US-COPI-079

Slot reservado pra fix dos bugs/UX issues que aparecerem na demo. Margem de segurança Cycle 01 antes do gate RAGAS.

**Acceptance**: backlog vazio do que veio da demo OR documentado como tech-debt pra Cycle 02 com priority justificada.

### US-COPI-081 · Sprint 7 RAGAS — gate de medição Cycle 01

**Implementado em:** `app/Console/Commands/EvalRagasBaselineCommand.php` · `memory/sessions/2026-05-04-ragas-baseline-infra.md` · verificado@dd3ed7c (2026-07-01) — comando de eval RAGAS reproduzível + baseline registrado (0.72, 8 ADRs)

> owner: wagner · sprint: 2026-W20 · priority: p0 · estimate: 12h · status: done · done_at: 2026-05-04 · baseline_ragas: 0.72 (8 ADRs, OpenAI gpt-4o-mini) · session: memory/sessions/2026-05-04-ragas-baseline-infra.md

Sprint 7 do roadmap Tier 7-9 (ADR 0037). Gate quantitativo do Cycle 01: provar que memória/RAG funciona com métrica reproduzível.

- Setup RAGAS no CT 100 (pip install + container Docker se necessário)
- Golden set: 50 perguntas Larissa-style (faturamento, metas, custos) com resposta correta conhecida
- Pipeline: pergunta → ContextoNegocio + memoria_recall → resposta Sonnet → score RAGAS (faithfulness + answer_relevancy + context_precision)
- Baseline: rodar contra prod, registrar score
- Documentar em `memory/sessions/2026-05-NN-ragas-baseline.md`

**Acceptance**: golden set 50q · script RAGAS reproduzível · baseline numérico em ADR ou session log · 3 perguntas+scores como evidência.

### US-COPI-082 · Sprint 9 retrieval — diagnóstico nomic + fixes (recovery 0.158 → 0.700)

**Implementado em:** `Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php` · `app/Console/Commands/EvalRagasBaselineCommand.php` · `memory/requisitos/Jana/RETRIEVAL-GOTCHAS.md` · verificado@dd3ed7c (2026-07-01) — `withoutSyncingToSearch()` no branch sem mudança + `--semantic-ratio` bypass MySQL FT; score recuperado 0.158→0.700

> owner: wagner · sprint: 2026-W19 · priority: p1 · estimate: 6h · status: done · done_at: 2026-05-04 · score_ragas: 0.700 · session: memory/sessions/2026-05-04-sprint9-retrieval-diagnostico.md
> blocked_by: —

Sprint 9 fase 2 — investigar regressão score RAGAS 0.66 → 0.158 após troca embedder OpenAI → Ollama nomic-embed-text, e implementar fixes.

**Diagnósticos:**
- `nomic-embed-text:137M` é EN-only — gera cosine ~0.97 uniforme em PT-BR (treinado predominantemente em inglês)
- Meilisearch BM25 sem stopwords PT-BR ranqueia CHANGELOG longo acima de ADRs específicas (`format-date-shift`, `permission-registry` foram 0.00 com Meilisearch, 1.00 com MySQL FT)
- Scout observer dispara em qualquer `model->update()` — `IndexarMemoryGitParaDb` re-embedava 383 docs a cada `mcp:sync-memory` mesmo sem mudança de conteúdo

**Entregas:**
- [x] `IndexarMemoryGitParaDb`: `withoutSyncingToSearch()` no branch sem mudança (`ebca7a37`)
- [x] `EvalRagasBaselineCommand`: `--semantic-ratio` option + bypass MySQL FT quando ratio < 0.25 (`1b33f258`)
- [x] ADR 0068 aceito + session log retrieval-diagnostico (`d260c33a`)
- [x] Doc canônico `MEILISEARCH-EVOLUCAO.md` (Sprint 7→9 timeline) (`32686abe`)
- [x] Pesquisa estado da arte 2026 + `RETRIEVAL-ESTADO-ARTE-2026-05.md` com recomendação `qwen3-embedding:4b` (`d1eff5af`)
- [x] `RETRIEVAL-GOTCHAS.md` — 13 armadilhas anti-regressão (`fbb89adc`)
- [x] ADR 0069 — TaskRegistry MCP tools canônico, TASKS.md ASCII deprecated

**Score recuperado:** 0.158 → **0.700** RAGAS (8 perguntas ADR) via MySQL FT bypass.

**Próximo passo (Sprint 9b — futura US):** `ollama pull qwen3-embedding:4b` no CT 100 (top MTEB multilingual Jun/2025, PT-BR explícito) → reconfigurar embedder Meilisearch → re-importar 383 docs → meta superar 0.72 com semantic real PT-BR.

**Acceptance**: score RAGAS recuperado pra ≥0.66 (atingido 0.700) · 3 fixes commitados em prod · 3 docs canônicos de governança em `memory/requisitos/Jana/` · ADR 0068 + 0069 aceitas · session log gravado.

### US-COPI-083 · Sprint 9b — qwen3-embedding:0.6b + stopwords PT-BR (em par com baseline)

**Implementado em:** `app/Console/Commands/EvalRagasBaselineCommand.php` · `memory/requisitos/Jana/RETRIEVAL-ESTADO-ARTE-2026-05.md` · verificado@dd3ed7c (2026-07-01) — eval matrix qwen3:0.6b + stopwords PT-BR + localizedAttributes; ratio=0.6 vencedor (0.692), model 4b descartado por CPU-only

> owner: wagner · sprint: 2026-W19 · priority: p0 · estimate: 4h · status: done · done_at: 2026-05-04 · score_ragas: 0.692 (ratio=0.6 vencedor) · model: qwen3-embedding:0.6b (CT 100 CPU-only)
> blocked_by: —

**Resultado eval matrix (qwen3 + stopwords PT-BR + localizedAttributes):**
- ratio=0.4 → 0.637 · ratio=0.5 → 0.642 · **ratio=0.6 → 0.692** · ratio=0.0 (MySQL FT bypass) → 0.700

**Smoke test cosine:** qwen3 dá 0.55 entre ADRs distintas (nomic dava 0.97 uniforme). Semantic discrimina PT-BR de verdade.

**Decisão:** model 4b descartado pelo CT 100 ser CPU-only (proibitivo). 0.6b validado.

Substituir nomic-embed-text (EN-only, gera cosine ~0.97 uniforme em PT-BR) por qwen3-embedding:4b (#1 MTEB multilingual Jun/2025, PT-BR explícito) + ajustes Meilisearch PT-BR.

**Steps:**
1. CT 100: `ollama pull qwen3-embedding:4b` (~3.5GB VRAM)
2. Smoke test cosine similarity: 2 docs PT-BR diferentes devem dar cosine 0.3-0.8 (não mais ~0.97 uniforme)
3. PATCH embedder Meilisearch `mcp_memory_documents` (model: `qwen3-embedding:4b`, dimensions: 1024)
4. PUT stopwords PT-BR (lista canônica em `memory/requisitos/Jana/RETRIEVAL-ESTADO-ARTE-2026-05.md` §2)
5. PUT localizedAttributes `[{"locales": ["por"], "attributePatterns": ["*"]}]`
6. `php artisan scout:import McpMemoryDocument` (re-embeda 383 docs)
7. Eval matrix: `eval:ragas-baseline --semantic-ratio=0.0|0.4|0.6|0.8` → escolher melhor
8. Atualizar `COPILOTO_MEMORIA_SEMANTIC_RATIO` no .env Hostinger com vencedor

**Acceptance:** Score RAGAS médio ≥ **0.80** (meta) ou ≥ 0.72 (baseline original) · semanticRatio vencedor documentado em ADR ou comment · 383 docs reindexados sem erro · stopwords PT-BR + localizedAttributes aplicados.

**Referências:** ADR 0068, RETRIEVAL-ESTADO-ARTE-2026-05.md, RETRIEVAL-GOTCHAS.md.

### US-COPI-084 · Slash command /ultrareview — code review adversarial automático

**Implementado em:** `.claude/commands/ultrareview.md` · verificado@dd3ed7c (2026-07-01) — slash command com prompt template roleplay "tech lead cético" (bugs/race/LGPD/anti-padrão)

> owner: wagner · sprint: 2026-W19 · priority: p0 · estimate: 2h · status: done · done_at: 2026-05-04
> blocked_by: —

Implementar `.claude/commands/ultrareview.md` que pede ao Claude (ou sub-agent via Task tool) pra revisar `git diff staged|HEAD` como adversário cético: encontre 3 bugs, 2 race conditions, 1 LGPD issue, 1 anti-padrão de stack canônica.

**Por quê:** Reflexion (NeurIPS 2023) e Self-Refine (2023) mostraram que LLM revisar próprio output melhora qualidade em 15-30% sem custo de novo modelo. Prevenção barata vs custo de bug em prod.

**Acceptance:** Slash command `/ultrareview` em `.claude/commands/` com prompt template estruturado · roleplay "tech lead cético" · output em formato lista priorizada (severity/file:line/fix sugerido) · documentado no HOW_TO_ASK_CLAUDE §3.5 · testado em 1 PR real e reportado.

### US-COPI-085 · Hook block-destructive — guardrails Bash em produção

**Implementado em:** `.claude/hooks/block-destructive.mjs` · verificado@1f4fdc9 (2026-07-12) — path reconciliado `.ps1`→`.mjs` (migração de hooks do projeto; arquivo `.ps1` não existe mais, `.mjs` + `.test.mjs` presentes). Hook PreToolUse bloqueia Bash destrutivo (rm -rf, push --force, DROP/DELETE, migrate:fresh)

> owner: wagner · sprint: 2026-W19 · priority: p0 · estimate: 3h · status: done · done_at: 2026-05-04 · tests_passing: 14/14
> blocked_by: —

Hook PreToolUse em `.claude/settings.json` que bloqueia (exit 2) comandos Bash destrutivos sem confirmação humana: `rm -rf`, `git push --force`, `git reset --hard origin/`, `DROP TABLE`, `DELETE FROM ... WHERE 1`, `composer update` (sem `--lock`), `php artisan migrate:fresh --force` em produção.

**Por quê:** HOW_TO_ASK_CLAUDE §3.1. Padrão Anthropic Cookbook (set/2025). Wagner já tem precedente: `block-automem.ps1` bloqueando Write em auto-mem. Replicar pattern pra Bash destrutivo.

**Acceptance:** `.claude/hooks/block-destructive.ps1` testado · regex cobrindo 7 categorias de destrutivo · whitelist explícita pra casos legítimos (ex.: `rm -rf` em `/tmp/`) · README com receita de bypass via `--allow-destructive` flag explícita · zero falso-positivo em 1 semana de uso.

### US-COPI-086 · Hook pii-redactor — bloquear commit com PII (LGPD)

**Implementado em:** `.claude/hooks/pii-redactor.mjs` · verificado@1f4fdc9 (2026-07-12) — path reconciliado `.ps1`→`.mjs` (migração de hooks do projeto; arquivo `.ps1` não existe mais, `.mjs` + `.test.mjs` presentes). Hook escaneia `git diff --staged` por CPF/CNPJ/email/cartão e bloqueia com whitelist de fixtures

> owner: wagner · sprint: 2026-W19 · priority: p1 · estimate: 3h · status: done · done_at: 2026-05-04 · tests_passing: 10/10
> blocked_by: —

Hook PreToolUse em Bash (`git commit`) que escaneia `git diff --staged` por regex PII (CPF, CNPJ, email, cartão) e bloqueia se achar. Avisa ao Claude com mensagem "[PII detectada em path:line] — substitua por [REDACTED] ou fixture fake (ex.: 123.456.789-09)". <!-- pii-allowlist: CPF fake canônico (fixture de exemplo na doc do hook) -->

**Por quê:** HOW_TO_ASK_CLAUDE §3.4. LGPD Art. 7º (princípio de minimização). Já houve incidente: log de prod com CPF real colado em prompt — risco de vazar em commit/transcript.

**Acceptance:** `.claude/hooks/pii-redactor.ps1` testado · regex BR validados (CPF formato 000.000.000-00 e 00000000000; CNPJ idem; email RFC 5322 simplificado; cartão Luhn) · whitelist pra fixtures conhecidos (123.456.789-09, etc.) · documentação com lista de PIIs cobertos · zero falso-positivo em 50 commits validados. <!-- pii-allowlist: placeholder 000.000.000-00 e CPF fake canônico 123.456.789-09 (fixtures de exemplo na doc do hook PII) -->

### US-COPI-087 · Sprint 9c — Cross-encoder reranker (qwen3-reranker ou bge-reranker-v2-m3)

**Implementado em:** _parcial_ · `Modules/Jana/Services/Retrieval/BgeReranker.php` · `Modules/Jana/Services/Retrieval/Reranker.php` · `Modules/Jana/Services/Memoria/MeilisearchDriver.php` · verificado@3acabd2 (2026-07-12) — contrato `Reranker` + `BgeReranker` cross-encoder plugado no driver via `config('copiloto.reranker.driver')` (feature-flag, fetch 2× candidatos). **Reconciliado 2026-07-12 (4º):** o container CT 100 FOI confirmado live pela verificação de máquina da US-COPI-107 (tabela Onda 4 — "reranker container live 7d"); a âncora anterior ("container não confirmado", verificado@dd3ed7c 2026-07-01) estava stale e contradizia a tabela. Segue _parcial_ por 1 item só: meta RAGAS ≥0.85 não medida (validação no Langfuse — mesma pendência NDCG da US-COPI-110)

> owner: wagner · sprint: 2026-W20 · priority: p1 · estimate: 6h
> blocked_by: US-COPI-083

Adicionar reranker cross-encoder pós-fetch top-50 do Meilisearch hybrid → top-3 pra LLM. Meta: superar 0.85 RAGAS.

**Steps:**
1. CT 100: `ollama pull dengcao/Qwen3-Reranker-0.6B` (community Ollama) OU `docker run TEI bge-reranker-v2-m3`
2. Implementar `RerankerService` em `Modules/Jana/Services/Retrieval/`
3. Modificar `EvalRagasBaselineCommand::retrieveKbContext()` pra fetch top-50 → reranker → top-3
4. Eval com e sem reranker, comparar score + latência
5. ADR documentando trade-off (latência +100-200ms vs ganho de score)
6. Aplicar em prod chat real-time se latência < 500ms total

**Acceptance:** Score RAGAS médio ≥ 0.85 · latência reranker documentada · ADR criada · serviço testado · feature flag `COPILOTO_RERANKER_ENABLED` (default false até validar).

**Pré-requisito:** US-COPI-083 entregue (qwen3 base funcionando).

### US-COPI-088 · BRIEF-A1 — Fix aggregator (in_flight + ADR DATE bug + activity_24h)

**Implementado em:** `database/migrations/2026_05_06_172445_fix_brief_procedure_real_schema.php` · `Modules/Brief/Services/BriefGeneratorService.php` · verificado@dd3ed7c (2026-07-01) — migration corrige o procedure aggregator (DATE bug + mcp_activity_24h + in_flight via mcp_tasks doing/review); validado brief #5 em prod 2026-05-07

> owner: wagner · sprint: 2026-W20 · priority: p1 · estimate: 3h · status: done · done_at: 2026-05-07
> blocked_by: —

**Contexto:** Auditoria 2026-05-07 do L7 Daily Brief revelou 3 bugs no `refresh_brief_inputs_cache` causando brief com 217 tokens (vs alvo 3k):

1. `recent_24h.adrs_approved` sempre NULL — query usava `decided_at > NOW() - INTERVAL 24 HOUR`. Coluna é DATE, comparação trunca à meia-noite, ADRs de ontem somem.
2. `recent_24h.commits_count` sempre 0 — `mcp_audit_log` é log MCP API, não recebe webhook GitHub. Substituído por `mcp_activity_24h` + distinct_tools + distinct_users.
3. `in_flight` sempre NULL hardcoded — pivot pra `mcp_tasks WHERE status IN ('doing','review')`.

**Validação prod 2026-05-07 11:47:** brief #5 mostra ADRs 0087-0091, in_flight wagner@RecurringBilling, mcp_activity_24h=122. Tokens 217→235 (+8% mas conteúdo agora informativo).

**Refs:** PR #162, ADR 0091, sessão BRIEF-A1.

### US-COPI-089 · BRIEF-A2 — Validar brief-fetch exposto + remover do Hostinger

**Implementado em:** _parcial_ · `Modules/Jana/Mcp/Tools/CyclesActiveTool.php` · `Modules/Jana/Mcp/OimpressoMcpServer.php` · verificado@dd3ed7c (2026-07-01) — validação via chamada tools-list feita (brief-fetch é 1ª tool listada); a remoção do Hostinger virou follow-up US-COPI-094 (gap residual). brief-fetch tool vive no servidor MCP (Brief module gera o payload)

> owner: wagner · sprint: 2026-W20 · priority: p1 · estimate: 2h · status: done · done_at: 2026-05-07
> blocked_by: —

**Contexto:** Skill `brief-first` Tier A não dispara se tool MCP `brief-fetch` não está exposto. Auditoria 2026-05-07 via `curl POST tools/list`: ✅ brief-fetch é a 1ª tool listada em ambos endpoints (mcp.oimpresso.com CT 100 + oimpresso.com Hostinger).

**Gap residual:** Wagner regra canônica reforçada hoje — MCP roda APENAS no CT 100 (Hostinger lento + crasheia). Spawnado follow-up US-COPI-094.

**Refs:** ADR 0053, ADR 0062, [auto-mem feedback_mcp_so_ct100](memory MCP só CT100).

### US-COPI-090 · BRIEF-A3 — ADR 0096 superseding parcial 0091 (model real gpt-4o-mini)

**Implementado em:** _pendente_ — ADR canônica documentando o model real (`gpt-4o-mini`, supersede parcial 0091) não foi criada; o número 0096 foi usado por outra ADR (WhatsApp Meta Cloud API). Decisão só no docblock do BriefGeneratorService (status todo)

> owner: wagner · sprint: 2026-W20 · priority: p2 · estimate: 1h · status: todo
> blocked_by: —

ADR 0091 diz `claude-sonnet-4-6` (custo projetado $0.30/dia). Realidade: usa `gpt-4o-mini` (custo real $0.0004/brief = $0.024/dia, 30× mais barato). Decisão documentada no docblock do BriefGeneratorService mas não em ADR canônica. Atualizar checklist de adoção (5/7 itens já feitos).

### US-COPI-091 · BRIEF-A4 — Investigar baixa adoção brief-first (2 triggers em 7d)

**Implementado em:** _pendente_ — investigação de adoção (soak 48h pós US-COPI-094) não realizada; blocked_by US-COPI-094 (status todo)

> owner: wagner · sprint: 2026-W20 · priority: p2 · estimate: 2h · status: todo
> blocked_by: US-COPI-094

Skill `brief-first` Tier A registrou apenas 2 triggers em 7d (alvo ≥90% sessões). Hipóteses: SKILL.md não distribuído pra cada dev, description ambígua, ou cache client Claude Code (tools listadas no startup faltam brief-fetch mesmo com servidor expondo). Soak 48h após US-COPI-094 mergear.

### US-COPI-092 · GUARD-01 — Schema snapshot Pest test + procedure_drift health-check

**Implementado em:** `Modules/Jana/Tests/Feature/Smoke/ProcedureDriftSnapshotTest.php` · `Modules/Jana/Console/Commands/HealthCheckCommand.php` · verificado@dd3ed7c (2026-07-01) — Pest snapshot de `SHOW CREATE PROCEDURE` + check `procedure_drift` no `jana:health-check` (`checkProcedureDrift()`)

> owner: wagner · sprint: 2026-W20 · priority: p1 · estimate: 3h · status: done
> blocked_by: US-COPI-088

Auditoria 2026-05-07 BRIEF-A1 revelou que `02-schema-aggregator.sql` no repo divergiu do procedure deployado em prod. Migration `2026_05_06_172445` capturou estado mas spec doc ficou stale. Solução: Pest snapshot test que faz `SHOW CREATE PROCEDURE` + compara hash congelado. CI quebra se diverge → força migration. Adicionar `procedure_drift` ao `jana:health-check`. Política dura `memory/proibicoes.md`: ⛔ DDL só via migration.

**Refs:** ADR 0094 §princípio #5 SoC brutal.

### US-COPI-093 · GUARD-02 — Pest audit ModuleScaffolding

**Implementado em:** `tests/Feature/Audit/ModuleScaffoldingTest.php` · verificado@dd3ed7c (2026-07-01) — Pest itera Modules/* e falha CI se módulo nasce sem InstallController/DataController/ServiceProvider/module.json (allowlist API_ONLY)

> owner: wagner · sprint: 2026-W20 · priority: p1 · estimate: 5h · status: done · done_at: 2026-05-07 · tests_passing: 5/5
> blocked_by: —

Pest test em `tests/Feature/Audit/ModuleScaffoldingTest.php` que itera Modules/*/ e falha CI se módulo novo nasce sem InstallController, DataController, ServiceProvider ou module.json válido. 30 módulos auditados, allowlist API_ONLY=['Brief']. Padrão recorrente: ConsultaOs 2026-05-04 (botão Install vai pra `#`). MVP enxuto entregue PR #162. Iteração 2 (override `module:make`) fica pra Sprint 21+.

**Refs:** PR #162, RUNBOOK-criar-modulo, ADR 0024.

### US-COPI-094 · BRIEF-A2 follow-up — Remover brief-fetch do Hostinger MCP server

**Implementado em:** `Modules/Jana/Http/routes.php` · verificado@dd3ed7c (2026-07-01) — registro do servidor MCP (`Mcp::web`) condicionado a `config('mcp.tools_exposed')` (default false Hostinger, true CT 100); tools MCP só expostas no CT 100 (ADR 0062)

> owner: wagner · sprint: 2026-W20 · priority: p1 · estimate: 2h · status: done
> blocked_by: —

Wagner regra 2026-05-07: MCP roda APENAS no CT 100 (Hostinger lento + crasheia). Atualmente `brief-fetch` está exposto em ambos endpoints. Investigar onde `Mcp::web('/api/mcp', OimpressoMcpServer::class)` registra rota no Hostinger (`Modules/Jana/Http/routes.php:211`). Mover registro pra provider que SÓ boota no CT 100, ou condicionar via `env('MCP_TOOLS_EXPOSED', false)` true em CT 100 false em Hostinger. Schema `mcp_briefs` + service `BriefGeneratorService` continuam em Hostinger (cron + DB local). Tool MCP exposed só em CT 100 (acessa MySQL via SSH tunnel autossh per ADR 0053).

**Refs:** [auto-mem feedback_mcp_so_ct100](memory), ADR 0053, ADR 0062.

### Área Cockpit Saúde do Ecossistema

> Origem: pedido Wagner 2026-05-09 — "tela grandiona com IA cuidando da saúde do ecossistema".
> Aproveita `php artisan jana:health-check` (6 checks SQL já existentes em `Modules/Jana/Console/Commands/HealthCheckCommand.php` com flag `--json` machine-readable) + Horizon (a publicar) + Centrifugo metrics + `mcp_audit_log` + `failed_jobs`.

#### US-COPI-095 · EPIC — Cockpit Saúde do Ecossistema

**Implementado em:** _parcial_ · `Modules/Jana/Services/HealthSnapshotService.php` · `Modules/Jana/Services/HealthNarratorService.php` · `Modules/Governance/Http/Controllers/DashboardController.php` · `resources/js/Pages/governance/Dashboard.tsx` · verificado@dd3ed7c (2026-07-01) — epic entregue via PIVOT (US-COPI-098): estendeu `/governance` Dashboard em vez de criar `/copiloto/admin/health`; sub-stories 097/098/099 done, 100 (Job hourly) em `doing`
> owner: wagner · sprint: 2026-W21 · priority: p2 · estimate: 12h
> blocked_by: US-COPI-096

**Como** superadmin oimpresso (Wagner) **quero** uma tela única `/copiloto/admin/health` que mostre saúde do ecossistema todo **para** detectar incidentes antes do cliente reportar.

Agrega num lugar só:
- Horizon — filas/throughput/failed jobs/workers
- `jana:health-check` — 6 checks (multi-tenant, brief uptime, custo Brain B, PII leak, profile drift, procedure drift) + histórico 7d
- Centrifugo — clientes conectados + eventos/min
- MCP server — uptime + custo Brain B 24h
- Jobs SEFAZ — emissões/falhas últimas 24h
- **Brain A narrador horário** — lê `mcp_audit_log` + `failed_jobs` + último `health-check.json` e gera 1 narrativa curta ("3 jobs SEFAZ falharam empresa X — investigar cert vencido"); escala HITL Wagner se severity high

**Sub-stories** (a destrinchar quando sair de planning):
- HealthSnapshotService — agrega 4 fontes em 1 JSON
- Page Inertia `Pages/Copiloto/Admin/Health.tsx` (MWART F1-F4 com visual-comparison)
- Brain A narrador horário (job + prompt + persistência em `health_narratives`)

**Não-goals:**
- Substituir Grafana/Datadog (não competimos com observability tool)
- Multi-tenant (cockpit é superadmin-only, agrega plataforma toda)
- Auto-resolução (só observa + narra, ação é HITL Wagner por publication-policy)

**Dependências:**
- US-COPI-096 (Horizon publicado) bloqueia
- ADR 0104 MWART process F1 PLAN antes de codar Page
- Quando S4 entregar `charter-fetch`, adicionar `Health.charter.md` ao lado do `.tsx`

**Refs:** ADR 0094 (Constituição V2 — health-check é o sentinel), ADR 0106 (estimate recalibrado IA-pair), `jana:health-check` (6 checks já existentes).

#### US-COPI-096 · Setup Horizon — provider + auth gate superadmin + flag CT-only

**Implementado em:** `app/Providers/HorizonServiceProvider.php` · `config/horizon.php` · verificado@dd3ed7c (2026-07-01) — provider + config publicados com gate `Horizon::auth` superadmin; rota condicionada a flag CT-only (padrão MCP_TOOLS_EXPOSED, ADR 0062)
> owner: wagner · sprint: 2026-W20 · priority: p2 · estimate: 1h · status: done
> blocked_by: —

Hoje `laravel/horizon ^5.46` está no `composer.json` mas nunca foi publicado: sem `config/horizon.php`, sem `HorizonServiceProvider`, sem rota `/horizon`. Pacote dormente.

**Como** superadmin oimpresso **quero** acessar `/horizon` no CT 100 **para** ver filas, jobs, throughput e failed jobs em UI nativa Laravel.

**DoD:**
- `php artisan horizon:install` rodado + arquivos commitados
- `app/Providers/HorizonServiceProvider.php` com gate `Horizon::auth(fn ($req) => auth()->check() && auth()->user()->can('superadmin'))`
- Flag `HORIZON_TOOLS_EXPOSED=false` por default; rota `/horizon` SÓ registra se flag true (análogo a `MCP_TOOLS_EXPOSED` per ADR 0062)
- `.env.example` documenta flag
- CT 100 `.env` recebe `HORIZON_TOOLS_EXPOSED=true` (via SSH separado, fora desta US)
- Worker daemon (`horizon:work`) NÃO sobe nesta US — fica pro deploy CT 100 com supervisord/systemd
- Pest local validando: rota retorna 404 quando flag false; rota retorna 403 pra user não-superadmin
- ADR 0062 não violada — Hostinger nunca expõe Horizon UI

**Refs:** ADR 0062 (Hostinger ≠ CT 100), ADR 0094 §5 SoC brutal, US-COPI-094 (mesmo padrão flag CT-only do MCP), composer.json:laravel/horizon.

#### US-COPI-097 · HealthSnapshotService — agregador 4 fontes em 1 JSON estável

**Implementado em:** `Modules/Jana/Services/HealthSnapshotService.php` · `Modules/Jana/Entities/Mcp/McpAuditLog.php` · verificado@dd3ed7c (2026-07-01) — `snapshot(): array` agrega health/queues/mcp/brain_b com degradação graciosa (`available:false`), shape estável
> owner: wagner · sprint: 2026-W20 · priority: p2 · estimate: 1h · status: done · done_at: 2026-05-09 · tests_passing: 5/5
> blocked_by: —

Backend service que alimenta a Page Inertia do cockpit (US-COPI-098) e o Brain A narrador (US-COPI-099). Independente de Horizon estar ativo — lê tabelas DB direto.

**Shape contractual:**

```json
{
  "generated_at": "2026-05-09T...",
  "health": { /* output de jana:health-check --json (6 checks) */ },
  "queues": { "available": true, "failed_24h": N, "failed_total": N },
  "mcp": { "available": true, "requests_24h": N, "errors_24h": N, "taxa_erro": 0.xxxx, "custo_brl_24h": N.NNNN },
  "brain_b": { "available": true, "tokens_in_24h": N, "tokens_out_24h": N, "custo_brl_24h": N.NNNN }
}
```

**DoD:**
- `Modules/Jana/Services/HealthSnapshotService.php` final class com 1 método público `snapshot(): array`
- Cada fonte degrada graciosamente (`available: false`) quando tabela ausente — shape sempre estável
- Pricing Brain B canônico (gpt-4o-mini: $0.15/1M in + $0.60/1M out * R$ [redacted Tier 0]/USD), igual `HealthCheckCommand`
- Pest test em `Modules/Jana/Tests/Feature/HealthSnapshotServiceTest.php` cobre 5 cenários (shape, queueStats 24h, mcpStats agregação, brain_b pricing, degradação graceful)
- Não toca tenancy — superadmin-only por design (cockpit agrega plataforma toda)

**Refs:** US-COPI-095 (epic), `Modules/Jana/Console/Commands/HealthCheckCommand.php` (já produz `--json`), `Modules/Jana/Entities/Mcp/McpAuditLog.php` (schema mcp_audit_log), PR #333.

#### US-COPI-098 · /governance Dashboard ganha "Saúde do Ecossistema" (pivot)

**Implementado em:** `Modules/Governance/Http/Controllers/DashboardController.php` · `resources/js/Pages/governance/Dashboard.tsx` · verificado@dd3ed7c (2026-07-01) — 3 fontes (failed_jobs, jana_mensagens 24h, jana_health_narratives top5) via `Schema::hasTable` graceful; KpiGrid Saúde do Ecossistema (Page do módulo Governance)
> owner: wagner · sprint: 2026-W20 · priority: p2 · estimate: 2h · status: done · done_at: 2026-05-09 · tests_passing: 8/8
> blocked_by: —

Pivot da decisão original: ao invés de criar `/copiloto/admin/health` separada, estendemos `/governance` Dashboard que já é o cockpit de saúde gold-standard (charter `live`, Cockpit Pattern V2 ADR 0110). Princípio Constituição V2 §5 SoC brutal + ADR 0105 (cliente como sinal — sem cliente pedindo separação, default é unificar).

3 fontes adicionadas via DashboardController graceful (Schema::hasTable):
- **failed_jobs Horizon** — KPI 24h count + tone (warning>0, danger>100)
- **jana_mensagens 24h** — KPI custo IA Brain B (pricing gpt-4o-mini canônico)
- **jana_health_narratives top 5** — KPI última severity + section "Narrativas Brain A 24h"

Page agora tem 2 fileiras KpiGrid separadas por h2 de seção (Constituição cols=6 + Saúde cols=3) + grid lateral 2 → 3 col. Charter v1 → v2.

**Refs:** PR #342, ADR 0110 Cockpit V2, ADR 0114 mwart-comparative V4, charter [`Dashboard.charter.md`](../../../resources/js/Pages/governance/Dashboard.charter.md), visual-comparison [`governance-dashboard-extension-visual-comparison.md`](governance-dashboard-extension-visual-comparison.md).

#### US-COPI-099 · HealthNarratorService — Brain A horário do Cockpit Saúde

**Implementado em:** `Modules/Jana/Services/HealthNarratorService.php` · `Modules/Jana/Ai/Agents/HealthNarratorAgent.php` · `Modules/Jana/Entities/HealthNarrative.php` · verificado@dd3ed7c (2026-07-01) — `narrate(array $snapshot): HealthNarrative` (gpt-4o-mini canon) com severity info/warning/critical + fallback graceful + hash idempotente
> owner: wagner · sprint: 2026-W20 · priority: p2 · estimate: 1h · status: done · done_at: 2026-05-09 · tests_passing: 5/5
> blocked_by: —

Brain A (gpt-4o-mini canônico ADR 0035) recebe snapshot agregado por HealthSnapshotService (US-COPI-097) e gera narrativa curta com severity (info/warning/critical) — alimenta UI cockpit + escala HITL Wagner via log channel `single` quando severity high.

**Pipeline:**
1. Migration `jana_health_narratives` (sem business_id — superadmin/plataforma)
2. Entity `HealthNarrative` + `Agent HealthNarratorAgent` (laravel/ai pattern canon — Promptable trait)
3. Service `HealthNarratorService::narrate(array $snapshot): HealthNarrative`
4. Output JSON estruturado validado: `{severity: "info|warning|critical", message: "..."}`
5. Falha graciosamente em parse error / dry_run / exception → fixture severity baseado em health.ok
6. Hash determinístico (sha256) pra idempotência
7. payload_summary reduzido (4 campos chave)

**Refs:** PR #339, ADR 0035 (laravel/ai canon), US-COPI-097 (consome snapshot).

#### US-COPI-100 · NarrarSaudeEcosistemaJob — Job hourly + schedule + escalation HITL

**Implementado em:** `Modules/Jana/Jobs/NarrarSaudeEcosistemaJob.php` · `app/Console/Kernel.php` · verificado@dd3ed7c (2026-07-01) — Job `ShouldQueue` orquestra snapshot→narrate→persist; agendado `->job(...)->hourlyAt(30)` no Kernel com escalation critical via Log::single ALERT
> owner: wagner · sprint: 2026-W20 · priority: p2 · estimate: 30min · status: done
> blocked_by: —

Job que orquestra `HealthSnapshotService::snapshot()` → `HealthNarratorService::narrate()` → persist em `jana_health_narratives`. Schedule hourly em `app/Console/Kernel.php` (live only) no minuto 30 pra evitar conflito com brief/cron pesados.

**Pipeline:**
1. `Modules/Jana/Jobs/NarrarSaudeEcosistemaJob.php` (ShouldQueue, sem business_id)
2. handle() injeta HealthSnapshotService + HealthNarratorService via container
3. Escalation HITL Wagner: severity=critical → `Log::channel('single')->error("BRAIN_A_ALERT [critical] ...")` (mesmo padrão `jana:health-check --notify`, Wagner faz tail/grep)
4. Schedule via `$schedule->job(new NarrarSaudeEcosistemaJob)->hourlyAt(30)->environments(['live'])`

**DoD:**
- Pest test cobre: persist, info-no-alert, critical-dispara-ALERT, tags
- Custo gpt-4o-mini ~R$ [redacted Tier 0]/dia (24x × R$ [redacted Tier 0]) — protegido por `jana:health-check` check `custo_brain_b_24h <= R$ [redacted Tier 0]/dia`
- Não toca tenancy — superadmin-only

**Refs:** US-COPI-095 (epic), US-COPI-097 (HealthSnapshotService), US-COPI-099 (HealthNarratorService), `app/Console/Kernel.php`.



---

## Auditoria de completude — 2026-05-10

Disparada por: `/module-completeness-audit` (skill `module-completeness-audit` v0.1.0, sessão Wagner 2026-05-10).

**Resultado: 4 ✅ / 4 🟡 / 0 ❌ (de 8 dimensões)**

| Dim | Nome | Status | Evidência |
|---|---|---|---|
| 1 | Multi-instance scope | 🟡 PARCIAL | `ChatController.php:37-41` filtra business_id+user_id, mas UI sem business switcher mid-conversa (props já em `shellPropsFor():124`, falta render) |
| 2 | Permissions middleware + UI | 🟡 PARCIAL | `McpAuthMiddleware.php:55` + permissions Spatie `jana.*` (migration 2026-05-09) OK; mas sem `Pages/Jana/Admin/Permissions.tsx` — gestão via painel Spatie genérico |
| 3 | Charter | ✅ APROVADO | `Pages/Jana/Chat.charter.md` status:live, atualizado 2026-05-09, 11 seções + 12 Pest GUARD tests canônicos |
| 4 | RUNBOOK | ✅ APROVADO | 8+ RUNBOOKs (RUNBOOK.md, RUNBOOK-chat.md, RUNBOOK-cockpit.md, RUNBOOK-memoria-semanal.md, RUNBOOK-governanca-mcp.md, RUNBOOK-qualidade-admin.md, RUNBOOK-custos-admin.md, RUNBOOK-dashboard.md) |
| 5 | Pest golden + cross-tenant biz=99 | 🟡 PARCIAL | `JanaHealthCheckTest.php:1-76` (golden smoke OK) + `HitTrackerServiceTest.php:12-20` (cross-tenant scoped); sem `biz_99` hardcoded como pattern canon |
| 6 | AuditLog em mutações | ✅ APROVADO | `McpAuthMiddleware.php:113-127` chama `McpAuditLog::registrar()` em toda request MCP com user_id, business_id, endpoint, tokens, custo, ip, duration |
| 7 | business_id global scope | ✅ APROVADO | 32+ migrations com business_id; `ChatController.php:40-42` + `DashboardController.php:20-24` aplicam scope; TIER 0 IRREVOGÁVEL conformado |
| 8 | Browser MCP smoke | 🟡 PARCIAL | `2026-05-06-pr-9-tabela-rename-copiloto-jana.md` (4d) + `2026-04-26-copiloto-testes-merge.md` (14d) — sem screenshot/console MCP recente |

### Gaps virando US-fix

— nenhum P0 detectado nesta auditoria.

### Gaps deferred (P1/P2 — não aprovados nesta auditoria)

- 🟡 Dim 1 Multi-instance UI (P2) — implementar business switcher na sidebar do Chat. Razão deferred: P2.
- 🟡 Dim 2 Permissions UI (P1) — criar `Pages/Jana/Admin/Permissions/Index.tsx` + controller CRUD roles/scopes. Razão deferred: Wagner aprovou só P0; reauditar próximo cycle.
- 🟡 Dim 5 Cross-tenant biz=99 hardcoded (P2) — adicionar `testCrossTenantGuardBiz99()` em `HitTrackerServiceTest`. Razão deferred: P2.
- 🟡 Dim 8 Smoke MCP fresh (P2) — capturar screenshot Browser MCP em `memory/requisitos/Jana/smoke-2026-05-10.md`. Razão deferred: P2.


### Atualização da auditoria 2026-05-10 — re-aprovação batch completo

Wagner re-aprovou (mesma data, turno seguinte) o batch completo: P1 e P2 viraram US-fix. **Lista "Gaps deferred" acima zerada.**

US-fix criadas:
- **US-COPI-101** (P1): Dim 2 Pages/Jana/Admin/Permissions UI dedicada
- **US-COPI-102** (P2): Dim 1 Business switcher na sidebar do Chat
- **US-COPI-103** (P2): Dim 5 Pest cross-tenant biz=99 hardcoded
- **US-COPI-104** (P2): Dim 8 Smoke Browser MCP fresh

Total de gaps Jana convertidos em US-fix: **4 de 4 detectados.**

> Nota: o MCP server usa prefixo `US-COPI-*` (legacy do nome anterior do módulo "Copiloto"). O módulo PHP/git foi renomeado pra `Modules/Jana/` em 2026-05-09 (migration `2026_05_09_140000_rename_copiloto_permissions_to_jana.php`). IDs `US-COPI-*` permanecem por convenção MCP — referem-se ao mesmo módulo.


### US-COPI-101 · Pages/Jana/Admin/Permissions — UI dedicada CRUD roles+scopes

**Implementado em:** _pendente_ — `PermissionsAdminController` + `resources/js/Pages/Jana/Admin/Permissions/Index.tsx` + rota `/ia/admin/permissions` não existem; gestão segue via painel Spatie genérico (status todo)

> owner: — · sprint: cycle-04 · priority: p1 · estimate: 3h · status: todo · type: story
> blocked_by: —

Gap detectado por skill `module-completeness-audit` em 2026-05-10 (Dim 2 Permissions middleware + UI — 🟡 PARCIAL).

**Evidência:** `Modules/Jana/Http/Middleware/McpAuthMiddleware.php:55` valida `jana.mcp.use` (permissions Spatie renomeadas em migration `2026_05_09_140000_rename_copiloto_permissions_to_jana.php`). Mas **NÃO existe** `resources/js/Pages/Jana/Admin/Permissions.tsx`. Maiara/Felipe gestionam via painel Spatie genérico — sem UI especializada Jana (não vê escopos `jana.mcp.*` agrupados).

**Fix sugerido:**
1. Criar `Modules/Jana/Http/Controllers/PermissionsAdminController.php` (index/store/update/destroy)
2. Pages: `resources/js/Pages/Jana/Admin/Permissions/Index.tsx` lista roles + scopes Jana (`jana.mcp.use`, `jana.mcp.tools_exposed`, `jana.memoria.read`, etc) com CRUD
3. Charter ao lado: `Permissions.charter.md` status:live
4. Middleware `can:jana.admin.permissions.manage` (superadmin only)

**Acceptance criteria:**
- [ ] Page Inertia `Jana/Admin/Permissions/Index.tsx` mostra todas roles
- [ ] Toggle visual de cada permission jana.* por role
- [ ] Apenas superadmin acessa (middleware can:jana.admin.permissions.manage)
- [ ] Pest test feature: superadmin lista + toggle; user normal recebe 403
- [ ] Charter Permissions.charter.md aprovado por Wagner

**Disparo:** Auditoria de completude 2026-05-10.
**Tags:** completeness-gap, from-skill, audit-2026-05-10

### US-COPI-102 · Business switcher na sidebar do Chat (UI mid-conversa)

**Implementado em:** _pendente_ — componente `Pages/Jana/_components/BusinessSwitcher.tsx` não existe; `shellPropsFor()` já entrega a lista de businesses, mas o seletor não é renderizado (status todo)

> owner: — · sprint: cycle-04 · priority: p2 · estimate: 2h · status: todo · type: story
> blocked_by: —

Gap detectado por skill `module-completeness-audit` em 2026-05-10 (Dim 1 Multi-instance scope — 🟡 PARCIAL).

**Evidência:** `Modules/Jana/Http/Controllers/ChatController.php:37-41` filtra business_id+user_id corretamente. `shellPropsFor():124` já entrega lista de businesses do user. **MAS** `Pages/Jana/Chat.tsx` não renderiza seletor — usuário entra com `session('user.business_id')` e fica preso ao primeiro business até logout/troca manual. Wagner+superadmin que tocam múltiplos businesses precisam abrir tab nova.

**Fix sugerido:** adicionar `<BusinessSwitcher />` na sidebar do Chat:
- Dropdown com lista de businesses do user (já em props)
- Trocar dispara reload de conversas + memoria_facts daquele tenant
- Persistir escolha em session OU URL param `?business=X`

**Acceptance criteria:**
- [ ] Componente `Pages/Jana/_components/BusinessSwitcher.tsx`
- [ ] Renderiza no topo da sidebar quando `props.businesses.length > 1`
- [ ] Trocar reload conversas (server-side via Inertia partial reload)
- [ ] Pest test: superadmin com biz=[1,99] consegue trocar e vê só conversas do biz ativo
- [ ] Charter Chat.charter.md atualizado mencionando BusinessSwitcher

**Disparo:** Auditoria de completude 2026-05-10.
**Tags:** completeness-gap, from-skill, audit-2026-05-10

### US-COPI-103 · Pest cross-tenant biz=99 hardcoded em HitTrackerServiceTest

**Implementado em:** _pendente_ — `Modules/Jana/Tests/Feature/HitTrackerServiceTest.php` existe, mas o teste explícito `testCrossTenantGuardBiz99` (pattern canon biz=1/biz=99) ainda não foi adicionado (status todo)

> owner: — · sprint: cycle-04 · priority: p2 · estimate: 1h · status: todo · type: story
> blocked_by: —

Gap detectado por skill `module-completeness-audit` em 2026-05-10 (Dim 5 Pest golden + cross-tenant biz=99 — 🟡 PARCIAL).

**Evidência:** `Modules/Jana/Tests/Feature/HitTrackerServiceTest.php:12-20` valida isolation via query scope inline (genérico) mas **não usa `biz_99` hardcoded como pattern canon**. Convenção do time é biz=1 default + biz=99 cross-tenant ([feedback_test_biz_99_cross_tenant_convention.md](memory/feedback_test_biz_99_cross_tenant_convention.md)).

**Fix sugerido:** adicionar test explícito:
```php
test('cross-tenant guard biz=99', function () {
    $hitsBiz1 = JanaMemoria::factory()->create(['business_id' => 1]);
    $hitsBiz99 = JanaMemoria::factory()->create(['business_id' => 99]);

    actingAs($userBiz99 = User::factory()->create(['business_id' => 99]));

    expect(JanaMemoria::all())->toHaveCount(1)
        ->and(JanaMemoria::first()->id)->toBe($hitsBiz99->id);
});
```

**Acceptance criteria:**
- [ ] Test `testCrossTenantGuardBiz99()` em `HitTrackerServiceTest`
- [ ] Test passa local + CI
- [ ] Pattern documentado pra outros módulos copiarem

**Disparo:** Auditoria de completude 2026-05-10.
**Tags:** completeness-gap, from-skill, audit-2026-05-10, pest, cross-tenant

### US-COPI-104 · Smoke Browser MCP fresh (screenshot+console) para Chat Jana

**Implementado em:** _pendente_ — smoke Browser MCP fresh (screenshot + console clean biz=1) não capturado; `memory/requisitos/Jana/smoke-2026-05-10.md` não existe (status todo)

> owner: — · sprint: cycle-04 · priority: p2 · estimate: 1h · status: todo · type: story
> blocked_by: —

Gap detectado por skill `module-completeness-audit` em 2026-05-10 (Dim 8 Browser MCP smoke — 🟡 PARCIAL).

**Evidência:** Última smoke é session log `memory/sessions/2026-05-06-pr-9-tabela-rename-copiloto-jana.md` (4d) — sem screenshot/console capture via Browser MCP recente. Outro `2026-04-26-copiloto-testes-merge.md` (14d) também sem visual.

**Fix sugerido:** rodar `mcp__Claude_in_Chrome__*` em fluxos Chat:
1. Abrir `/jana/chat` (golden path)
2. Enviar pergunta "qual o faturamento de hoje?" (3 ângulos faturamento ADR 0052)
3. Validar resposta + memória persistida
4. Captura screenshot + console clean

Salvar em `memory/requisitos/Jana/smoke-2026-05-10.md`.

**Acceptance criteria:**
- [ ] Screenshot do chat respondendo pergunta
- [ ] Console messages clean (sem error/Error/TypeError/ReferenceError)
- [ ] biz=1 (não cliente real, ADR 0101)
- [ ] Salvo em memory/requisitos/Jana/smoke-2026-05-10.md
- [ ] Inclui curl/PowerShell snippet pra repetir o smoke

**Disparo:** Auditoria de completude 2026-05-10.
**Tags:** completeness-gap, from-skill, audit-2026-05-10, smoke-mcp

### US-COPI-105 · Jana Chat V2 — block renderer (4 kinds) + streaming + citations + atalhos

**Implementado em:** _pendente_ — refator V2 do `resources/js/Pages/Jana/Chat.tsx` não aplicado: sem block renderer (4 kinds), streaming token-a-token, citations inline ou atalhos (grep zero em Chat.tsx). Backend `sendStream` SSE existe, mas o frontend V2 aguarda gate F1.5 ≥80 do Cowork (status todo)

> owner: wagner · priority: p1 · estimate: 24h · status: todo · type: story
> blocked_by: —

Refator completo da tela `/jana` aplicando amendment `COWORK_NOTES.amendment-jana-chat-block-renderer.md` (PR #839, sessão 2026-05-14).

**Estado atual:** V0 em prod mostra problemas catalogados (topnav 9-10 itens vs charter ≤6, empty empurrado pra ⅔ tela, avatar "CP" não-canon, lista repetida "Nova conversa", sem block renderer, sem streaming token-a-token, sem citations). Score 24/100 vs Glean/ChatGPT Enterprise/Notion AI/Copilot M365 (2026).

**Protótipo F1 V2 navegável** existe em `prototipo-ui/prototipos/chat/cowork-app-v2.jsx`:
- JanaAvatar quadrado mono "J" `bg-primary`
- 4 block kinds: MarkdownBubble (citations `[1][2]`) + ToolUseChip + DataTableBubble + ActionCardBubble (`confirm_required`)
- ThinkingIndicator 1-pulse (substitui 3-dots loop anti-pattern)
- Streaming mock SSE chunks
- Atalhos `/`, `J/K`, `Esc`, `⌘K`
- PII detector regex CPF/CNPJ/cartão composer
- 4 prompt chips empty state
- Chip business `LARISSA · biz=4` no header
- Tabs canon `Todas / Minhas / Compartilhadas / Arquivadas`

**Próximo passo:** [CC] Claude Cowork consome trio (pedido #316 + amendment-avatar 2026-05-09 + amendment-block-renderer 2026-05-14) → gera V2 oficial → [CD] critique F1.5 score ≥80 → F2 screenshot Wagner → [CL] F3 em `resources/js/Pages/Jana/Chat.tsx`.

**Bloqueia:** F3 Inertia real até F1.5 ≥80 do V2 oficial Cowork.

**Acceptance criteria (F3):**
- 0 anti-patterns Bloco A (5 itens) reaparecidos
- 0 vocabulário humano vazado Bloco B (7 itens removidos: read receipts, botão ligar, online dot, etc)
- 4 kinds renderer funcionando (Pest GUARD response shape)
- Streaming token-a-token via SSE/Centrifugo (latência <800ms primeiro token p95)
- Citations inline clicáveis → expand source card
- PII detector funcional no composer
- Cabe 1280px sem scroll horizontal
- Pest GUARD: charter Métricas Vivas (12 testes em Chat.charter.md)

**Refs:**
- Charter `resources/js/Pages/Jana/Chat.charter.md` (canon)
- `prototipo-ui/COWORK_NOTES.amendment-jana-chat-block-renderer.md` (19 divergências)
- PR #839 amendment + protótipo V2 navegável
- ADR ui/0114 loop Cowork + ADR 0107 gate F1.5

**Estimate:** 24h total — [CC] V2 (~6h) + [CD] critique (~1h) + [CL] F3 Inertia (~16h IA-pair) + Wagner aprovação (~1h)

---

### US-COPI-106 · Jana V2 demo — tela navegável apresentável a 1 cliente piloto

**Implementado em:** _parcial_ · `Modules/Jana/Http/Controllers/PainelController.php` · `resources/js/Pages/Jana/Painel.tsx` · verificado@dd3ed7c (2026-07-01) — tela Cockpit Analista IA (`/ia/painel`, charter live) existe, mas ainda com `buildMockPayload` (mock data); fluxo navegável real biz=4 + smoke + demo script a cliente piloto não confirmados (status todo)

> owner: wagner · priority: p0 · estimate: 8h · type: story
> blocked_by: —

Entregar Jana V2 demo navegável (goal #4 CYCLE-06 — alvo: 1 cliente piloto apresentado).

**DoD:**
- [ ] Definir 1 cliente piloto candidato (sugestão: Larissa ROTA LIVRE biz=4 — usuária mais ativa do oimpresso)
- [ ] Tela Cockpit Analista IA (Modules/Jana — referência F3 Cockpit já mergeada em 39ae79434)
- [ ] Fluxo navegável: brief diário → consulta memória → conversa → ação (não apenas mockup estático)
- [ ] Smoke browser MCP biz=1 (ADR 0101) + screenshot salvo em memory/sessions/
- [ ] Demo session script: 3-5 perguntas Larissa + respostas Jana citando dados reais biz=4
- [ ] Charter da tela demo `<Tela>.charter.md` ao lado do `.tsx` (Tier A always-on)
- [ ] Wagner aprova SCREENSHOT antes de demo real ([ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md))
- **Estimate:** 8h IA-pair (fator 10x ADR 0106 — telas Inertia + Charter + smoke)

---

## Backlog ativo — Roadmap de Ondas (pós-Onda 3, planejado 2026-05-13 — apendado SPEC 2026-05-20)

> Cruza [GAP-ANALYSIS-91-100](GAP-ANALYSIS-91-100-2026-05-13.md) + [ONDA-5-DOSSIER](ONDA-5-DOSSIER-2026-05-13.md). Ondas 1-3 entregues (70%→91% maturidade). Ondas 4-6 ainda não iniciadas — Onda 4 = candidato CYCLE-06 (8d restantes em 2026-05-20).

| Onda | US (nesta SPEC) | Esforço IA-pair | Δ score global | Status | Meta cumulativa |
|---|---|---:|---:|---|---:|
| **Onda 1** (bugs MCP sync) | múltiplas COPI legacy | 4d | +10pp (70→80%) | ✅ entregue 2026-05-13 | 80% |
| **Onda 2** (KB + handoffs) | múltiplas COPI legacy | 12d | +7pp (80→87%) | ✅ entregue 2026-05-13 | 87% |
| **Onda 3** (consolidação — Reranker RRF + backlinks + RAGAS gate + weekly digest) | múltiplas COPI legacy | 25d (~1d real IA-pair) | +4pp (87→91%) | ✅ entregue 2026-05-13 | 91% |
| **Onda 4** P0 (R1+L1+C1 — destrava medição honesta) | **US-COPI-107 + 108 + 109** | 5d (~3d real) | +4pp (91→95%) | ✅ **DONE** — verificado por máquina 2026-07-12 (107 reranker container live 7d · 108 Langfuse live 2sem · 109 tool+hook, Tier A superseded ADR 0225) | 95% |
| **Onda 5** P1 (K1+V1+H1+S1 — estruturais) | **US-COPI-110 + 111 + 112 + 113** | 9d (~5d real) | +3pp (95→98%) | 🟢 **3/4 DONE** — 110 time-decay wired **+ reordenação efetiva** (fix 2026-07-12: antes o RRF descartava o score decaído — ver lápide na US) · 112 handoff-tool · 113 schema-gate ✅; **falta só 111** (Gantt UI — precisa design-source + gate visual Wagner) | ~97% |
| **Onda 6** P2-P3 (A1+M1+G1+F1+L2 — saturação) | n/a (gate sinal qualificado) | 27d (~10d real) | +2pp (98→100%) | ❌ NÃO entrar sem sinal cliente externo (ADR 0105) | 100% (teto não-pragmático) |

**Pré-requisito Onda 4 → Onda 5:** L1 (Langfuse) fechado E rodando ≥14d em prod com métricas live antes de Onda 5 começar — sem isso, Ondas 5-6 viram "subjetivas" (princípio 4 Constituição v2 "Loop fechado por métrica").

**Teto pragmático recomendado:** 97-98% (parar pós-Onda 5). Onda 6 custa 13.5 d/pp e time de 5 não tem dor real — só ativa se cliente externo pedir feature específica via [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md).

### US-COPI-107 · Onda 4 R1 — Reranker BGE-v2-m3 self-host CT 100

> owner: wagner · priority: p0 · estimate: 12h IA-pair (1.5d) · status: done · type: story · sprint: CYCLE-06
> blocked_by: — · spawned_from: JANA-10X-016 (GAP-ANALYSIS-91-100 §2 + ONDA-5-DOSSIER §2)

**Como** time IA Jana
**Quero** reranker cross-encoder (BGE-reranker-v2-m3 self-host CT 100) plugado em `MeilisearchDriver` após hybrid recall (Onda 3 RRF)
**Para** elevar NDCG@10 em ≥6pp medido no dataset 50 queries Wagner, fechando gap Knowledge R3 (60%→90%) e habilitando medição de impacto K1 (time-decay) e L1 (Langfuse) downstream

**Implementado em:** `Modules/Jana/Services/Memoria/MeilisearchDriver.php` (novo passo `rerank` opcional via env `JANA_RERANKER_URL`) + container Docker CT 100 (`bge-reranker-v2-m3` via TEI ou Ollama-compat)

**Definition of Done:**
- [ ] Container CT 100 expõe endpoint reranker (`POST /rerank` → array re-ordenado) — TEI (Text Embeddings Inference HuggingFace) ou alternativa Ollama-compat
- [ ] `MeilisearchDriver::recall()` chama reranker quando `JANA_RERANKER_URL` setado (opt-in por env, fallback gracioso pra hybrid puro)
- [ ] Latência p95 <600ms para top-20→top-5 rerank (medido `RetrievalSpan` OTel)
- [ ] NDCG@10 +6pp validado vs baseline pré-reranker (dataset 50 queries em `Modules/Jana/Tests/Fixtures/RetrievalGoldenSet.php`)
- [ ] Pest `RerankerIntegrationTest::it preserves business_id scope ao rerankar` (ADR 0093 Tier 0)
- [ ] Skill `runtime-rules-hostinger-ct100` respeitada (container vai CT 100, NÃO Hostinger — ADR 0062)
- [ ] Documentação `RETRIEVAL-ESTADO-ARTE-2026-05.md` atualizada com config + métricas observadas

**Non-Goals:**
- ❌ Reranker hosted SaaS (Cohere Rerank 3.5) — preserva contrato self-host CT 100
- ❌ Fine-tune do reranker — usar baseline BGE-v2-m3 como-é
- ❌ Re-treinar Meilisearch embeddings — só camada de re-ranking pós-hybrid

**Refs:** [GAP-ANALYSIS §R1](GAP-ANALYSIS-91-100-2026-05-13.md) · [ONDA-5-DOSSIER §2](ONDA-5-DOSSIER-2026-05-13.md) · [BGE-v2-m3 vs Cohere benchmark](https://agentset.ai/rerankers/compare/baaibge-reranker-v2-m3-vs-cohere-rerank-35) · [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)

---

### US-COPI-108 · Onda 4 L1 — Langfuse v3 self-host CT 100 (MULTIPLICADOR)

> owner: wagner · priority: p0 · estimate: 16h IA-pair (2d) · status: done · type: story · sprint: CYCLE-06
> blocked_by: — · spawned_from: JANA-10X-017 (GAP-ANALYSIS-91-100 §2 + ONDA-5-DOSSIER §3)

**Como** time IA Jana + Wagner (governança custo)
**Quero** Langfuse v3 self-host em CT 100 (docker-compose: web + worker + ClickHouse + Postgres + Redis + MinIO) instrumentando 100% das chamadas LLM em prod (BriefDiarioAgent + kb-answer + recall + RAGAS gate)
**Para** ter observability LLM real (trace + cost + latency + RAGAS metrics) — sem isso, claims de "95%+" são não-falsificáveis (princípio 4 Constituição v2). Destrava medição de R1 (reranker NDCG), K1 (time-decay impact), A1 (auto-summary ROI), RAGAS gate trend semanal

**Implementado em:** `Modules/Jana/Services/Telemetry/LangfuseClient.php` · `docker/langfuse/docker-compose.yml` · `Modules/Jana/Services/Ai/LaravelAiSdkDriver.php` · `Modules/Jana/Services/Memoria/Telemetry/RetrievalTelemetryDecorator.php` · verificado@42af777 (2026-07-12) — **deploy CONFIRMADO vivo por máquina** (`docker ps` CT 100): 6 containers healthy up 2 semanas + tráfego real (query ClickHouse: 3.226 traces, 200-450/dia, agents `brain-b`/`planner`/`kb-answer`/`brief-diario` instrumentados via `LaravelAiSdkDriver` + `RetrievalTelemetryDecorator`). **Gap remanescente único:** tag `business_id` por trace (traces têm só `['live']`) → US-COPI-132.

**Definition of Done:**
- [x] Stack Langfuse v3 rodando CT 100 — verificado 2026-07-12 (`docker ps`: langfuse-web/worker/postgres/clickhouse/redis/minio up 2 semanas healthy)
- [ ] `business_id` propagado como TAG em todo trace (ADR 0093 Tier 0) — **GAP** confirmado 2026-07-12: query ClickHouse mostra só `['live']` → rastreado em **US-COPI-132**
- [x] Agents emitem trace com spans — verificado: brain-b-agent (2043), planner-agent (180), kb-answer-agent (102), brief-diario-agent instrumentados
- [x] RAGAS gate CI emite traces em batch — `jana-ragas-canary.yml` + `jana-ragas-gate.yml` rodando (verde diário)
- [ ] Dashboard "Custo Brain B por business" filtra por TAG `business_id` — depende de US-COPI-132 (tag ausente)
- [x] Skill `runtime-rules-hostinger-ct100` respeitada — stack só no CT 100, NÃO no Hostinger (ADR 0062) ✓
- [x] Smoke prod: tráfego real contínuo 200-450 traces/dia últimos 10 dias (evidência ClickHouse) — supera o critério 7d

**Non-Goals:**
- ❌ Langfuse cloud SaaS (preserva soberania + LGPD + custo)
- ❌ Substituir OTel local (`RetrievalSpan`) — Langfuse complementa, não substitui
- ❌ Instrumentar 100% módulos não-IA (escopo: chamadas LLM + retrieval)

**Refs:** [GAP-ANALYSIS §L1](GAP-ANALYSIS-91-100-2026-05-13.md) · [ONDA-5-DOSSIER §3](ONDA-5-DOSSIER-2026-05-13.md) · [Langfuse v3 self-host docs](https://langfuse.com/self-hosting) · [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0091](../../decisions/0091-daily-brief.md)

---

### US-COPI-109 · Onda 4 C1 — Charters S4 ativos (charter-fetch tool + Tier A)

> owner: wagner · priority: p0 · estimate: 12h IA-pair · status: done · type: story · sprint: CYCLE-06
> blocked_by: — · spawned_from: JANA-10X-018 (GAP-ANALYSIS-91-100 §2 + ONDA-5-DOSSIER §4)
> **STATUS REAL (reconciliado 2026-07-12 por verificação de máquina):** `CharterFetchTool.php` existe + hook `charter-validate.{ps1,sh}` registrado + 10 Pest + RUNBOOK existem desde 2026-05-13. **A meta "promover a Tier A" foi SUPERSEDED pela [ADR 0225](../../decisions/0225-skills-tier-a-recalibracao-claude-4.8.md)** (recalibração Claude 4.8, aceita): `charter-first` foi deliberadamente rebaixada `tier:A → tier:B` + `always_on: true → false` — dispara por auto-trigger ao editar `.tsx` com `.charter.md` ao lado, com hook `charter-validate` advisory. Re-promover a Tier A = regressão da 0225 (ideia descartada, não re-propor). US fechada: tool + hook + testes entregues; o item de tiering está resolvido pela via oposta (Tier B por design).

**Como** Claude (agent) + time MCP (Felipe/Maira/Eliana)
**Quero** tool MCP `charter-fetch <page-id>` exposta + skill `charter-first` Tier A ativa via hook SessionStart, BLOQUEANDO Edit/Write em `resources/js/Pages/**/*.tsx` que tenha `.charter.md` correspondente sem carregar charter primeiro
**Para** transformar 26 charters hoje dormentes em contrato vivo (resolve dor recorrente "Edit `.tsx` sem ler charter primeiro" + fecha Knowledge G7 + Handoff onboarding)

**Implementado em:** `Modules/Jana/Mcp/Tools/CharterFetchTool.php` · `.claude/skills/charter-first/SKILL.md` (`tier: B` auto-trigger por design — ADR 0225) · `.claude/hooks/charter-validate.ps1` + `.claude/hooks/charter-validate.sh` (advisory) · verificado@42af777 (2026-07-12) — tool + hook + skill presentes; tiering resolvido como Tier B (ADR 0225 supersede a meta "Tier A" original)

**Definition of Done:**
- [ ] Tool MCP `charter-fetch` retorna frontmatter + Goals + Non-Goals + Anti-hooks + UX targets (JSON)
- [ ] Skill `charter-first` promovida Tier A (SessionStart auto-load) — atualizar [SKILL.md](.claude/skills/charter-first/SKILL.md) tier + CLAUDE.md raiz lista Tier A
- [ ] Hook `charter-preflight-warning.ps1` BLOQUEIA Edit em `resources/js/Pages/**/*.tsx` se charter existe mas tool MCP não foi chamada na sessão
- [ ] ADR 0094 (Constituição v2) recebe amendment "S4 charters live" — escrever ADR nova superseding parcial
- [ ] Pest `CharterFetchToolTest::it returns parsed charter for valid page-id` + `::it 404s for unknown page-id`
- [ ] 26 charters validados via tool — listar em `memory/requisitos/_DesignSystem/CHARTERS-INDEX.md` (novo)
- [ ] Smoke: editar `Cockpit.tsx` Jana e verificar charter-fetch foi chamado (auto-mem trace)

**Non-Goals:**
- ❌ Migrar charters legacy (formato `.md` ao lado `.tsx` já é canon — ADR 0114)
- ❌ Auto-gerar charters faltantes (`charter-write` skill já cobre isso sob demanda)
- ❌ Bloquear sessão inteira se charter ausente — só warning se charter EXISTE mas não foi consumido

**Refs:** [GAP-ANALYSIS §C1](GAP-ANALYSIS-91-100-2026-05-13.md) · [ONDA-5-DOSSIER §4](ONDA-5-DOSSIER-2026-05-13.md) · [Agent Charter governance 2026](https://www.iamagazine.com/2026/05/12/agent-charter-creating-an-ai-governance-framework-to-ensure-operational-reliance/) · [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)

---

### US-COPI-110 · Onda 5 K1 — Time-decay weighting recall (boost recente + decay historical)

> owner: wagner · priority: p1 · estimate: 20h IA-pair (2.5d) · status: done · type: story · sprint: pós-Onda 4 (gate Langfuse)
> blocked_by: — (US-COPI-107 Reranker + US-COPI-108 Langfuse ambos DONE) · spawned_from: JANA-10X-019 (ONDA-5-DOSSIER §2)

**Como** time IA Jana
**Quero** `MeilisearchDriver` retornar score composto (relevance × 0.6 + recency × 0.3 com half-life 90d + importance × 0.1) com decay rate 0 pra ADR `lifecycle: accepted` e 0.5 pra `historical`/superseded
**Para** documentos canônicos recentes vencerem antigos no recall — fechando gap Knowledge R5 (0%→75%) que hoje mistura regras vigentes com revogadas em queries multi-dia

**Implementado em:** `Modules/Jana/Services/Memoria/MeilisearchDriver.php` — `applyTimeDecay()` chamada no pipeline de recall (`buscarInterno`), half-life decay exponencial + status multipliers, **com reordenação por score decaído DENTRO do estágio** (sort estável desc; empate preserva ordem RRF de entrada) · verificado@3acabd2 (2026-07-12).

> ⚠️ **Lápide (reconciliação adversarial 2026-07-13, wf_33e38126):** entre 2026-05 e 2026-07-12 o decay era calculado e **DESCARTADO** — o `RrfReranker` default (caso single-source, `Modules/Jana/Services/Retrieval/RrfReranker.php`) ranqueia por POSIÇÃO de entrada e nunca lê `score`, então pra ADRs a idade tinha efeito ZERO na ordem final. O "done" de 2026-07-12 (1º) foi done-por-existência: o `TimeDecayTest` cobria a fórmula isolada e assertava "ordem preservada" (tautológico — travava o bug, lápide §5 proibicoes.md "teste que deriva do código"). Fix: ordenação vive dentro do `applyTimeDecay` (flag-guarded); com `JANA_TIME_DECAY_ENABLED=false` o pipeline é byte-idêntico ao legado. **Atenção rollout:** a flag está default **true** — no deploy deste fix o decay passa a reordenar o recall em prod de fato (era o objetivo declarado da US; kill-switch = env false).

**Testado em:** `Modules/Jana/Tests/Feature/Memoria/TemporalScoringTest.php` (**contrato fim-de-estágio** decay→reranker REAL do container: ADR accepted recente vence superseded antiga com input adverso — falha no código pré-fix; `// @covers-us US-COPI-110`) + `Modules/Jana/Tests/Feature/Memoria/TimeDecayTest.php` (fórmula half-life + multipliers + flag + edge cases + contrato de reordenamento do estágio)

**Definition of Done:** _(reconciliado 2026-07-12, 4º)_
- [x] Fórmula canônica documentada — **divergência declarada vs DoD original:** implementada a forma multiplicativa `base × ((1-w) + w×0.5^(age/half_life)) × status_mult` (aprovada Wagner 2026-05-13, docblock `applyTimeDecay`), NÃO a aditiva `relevance×0.6 + recency×0.3 + importance×0.1`; relevância entra como ordem de entrada preservada em empate, `importance` não implementado
- [x] `recency_decay()`: half-life exponencial `0.5^(age_days/half_life)` per doc_type (adr=365, spec=180, session=30, handoff=14) · status multipliers accepted=1.2 / historical=0.5 / superseded=0.3
- [x] `lifecycle`/`status` lido de `metadata.status` do fato indexado (via `MemoriaFato.metadata`)
- [x] Pest `TemporalScoringTest` — "ranks recent accepted ADR above old superseded ADR for same query" (fim-de-estágio, reranker real)
- [x] Pest `TemporalScoringTest` — "preserves business_id scope" (ADR 0093 Tier 0; output ⊆ input, material scoped upstream)
- [x] Feature flag — **nome real** `JANA_TIME_DECAY_ENABLED` (não `JANA_TEMPORAL_SCORING_ENABLED`), **default true** (divergência vs DoD original "default false + canary 7d" — flag já estava on em prod desde 2026-05 calculando score sem efeito; o efeito real liga no deploy do fix)

**Validação pós-ship (ABERTA — não coberta pelo done acima):**
- [ ] NDCG@10 multi-dia +15pp medido em dataset 50 queries (Langfuse dashboard — US-COPI-108 vivo)
- [ ] Trace OTel `recall.temporal_scoring` com atributos (age_days, lifecycle, decay_applied)
- [ ] [RETRIEVAL-ESTADO-ARTE-2026-05.md](RETRIEVAL-ESTADO-ARTE-2026-05.md) atualizado com config + métricas

**Non-Goals:**
- ❌ Temporal Knowledge Graph (Graphiti/Zep) — opção EVOLUIR Onda 6 backlog
- ❌ Per-query decay tuning UI — escopo só backend
- ❌ Re-indexar Meilisearch (decay é runtime, não index-time)

**Refs:** [GAP-ANALYSIS §K1](GAP-ANALYSIS-91-100-2026-05-13.md) · [ONDA-5-DOSSIER §2](ONDA-5-DOSSIER-2026-05-13.md) · [Towards Data Science — RAG is Blind to Time](https://towardsdatascience.com/rag-is-blind-to-time-i-built-a-temporal-layer-to-fix-it-in-production/) · [Zep/Graphiti temporal KG](https://github.com/getzep/graphiti) · [ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)

---

### US-COPI-111 · Onda 5 V1 — Roadmap timeline UI (SVAR Gantt MIT + sub-issues)

> owner: wagner · priority: p1 · estimate: 32h IA-pair (4d) · status: done · type: story · sprint: pós-Onda 4
> blocked_by: — (independente, mas C1 charter US-COPI-109 ajuda template) · spawned_from: JANA-10X-022 (ONDA-5-DOSSIER §3)

**Como** Wagner (planejamento) + time MCP (Felipe/Maira/Eliana/Luiz)
**Quero** rota `/copiloto/admin/roadmap` com Gantt visual cronológico (SVAR React Gantt MIT) + sub-issues hierarchy view (parent_task_id) + drag-drop datas + filtro current cycle default
**Para** fechar gap Viz (5%→70%) — listas markdown via tools MCP não mostram cronologia/dependências; Linear/Plane/GitHub Projects vão 5 anos à frente em viz

**Implementado em:** _parcial_ · `resources/js/Pages/Jana/Admin/Roadmap.tsx` · `Modules/Jana/Http/Controllers/Admin/RoadmapController.php` · `resources/js/Pages/Jana/Admin/Roadmap.charter.md` · verificado@a68b435 (2026-07-12, máquina) — **CORREÇÃO de página:** o Gantt do V1 vive na tela Jana Admin Roadmap (não na tela ProjectMgmt Roadmap, que é outra — colunas por quarter). O Gantt SVAR de LEITURA já estava construído (barras + summary por módulo + dependency arrows via `blocked_by` + drawer de detalhe), roteado em `/ia/admin/roadmap` e surfaced no menu IA (ghost em `DataController`). **B2 — drag-drop reschedule (Wagner-explícito 2026-07-12, override do Non-Goal do charter):** arrastar/redimensionar a barra reagenda o PRAZO (`due_date`) via PATCH em /ia/admin/roadmap/tasks/{taskId}/schedule (gated `jana.mcp.tasks.write`, persiste pelo `TaskCrudService` canônico). `started_at` fica lifecycle-managed (não arrastável). **Gate visual R7/R1 FECHADO (2026-07-12):** Wagner arrastou a barra "Listar Budget" em prod (tela /ia/admin/roadmap no oimpresso.com) e o prazo persistiu — evidência objetiva: Duration 6→8 entre dois screenshots (due_date estendido, recalculado e servido pelo backend) + confirmação humana "persistiu". Deploy Hostinger @f8d68b232d. Backend coberto por 3 Pest (403/422/update). status→done.

**Testado em:** `Modules/Jana/Tests/Feature/Roadmap/RoadmapControllerTest.php` (reschedule: 403 sem write, 422 sem due_date, update de due_date pelo TaskCrudService; `// @covers-us US-COPI-111`)

**Definition of Done:**
- [ ] npm dep `@svar-widgets/react-gantt` (MIT, ~80KB, React 19 nativo — rejeitado DHTMLX/Bryntum/Frappe por licença ou bundle)
- [ ] `RoadmapController@index` lê `mcp_tasks` + `mcp_cycles` + `mcp_task_links` (blocked_by[]) com HasBusinessScope (ADR 0093)
- [ ] `RoadmapTaskResource` shape compatível SVAR (id, text, start_date, end_date, parent, dependencies[])
- [ ] Migration `mcp_tasks.parent_task_id` nullable (se não existe — auditar primeiro)
- [ ] Page Inertia: Gantt full-width + sidebar sub-issues panel + filtro chip cycle (default current) + lazy load cycles passados
- [ ] DataController hook ou sidebar.blade.php — entry "Roadmap" sob Copiloto/admin (skill `sidebar-menu-arch`)
- [ ] Rota `Route::get('/copiloto/admin/roadmap')` middleware `web,auth,permission:copiloto.admin.roadmap`
- [ ] Feature flag `feature.roadmap_hierarchy_enabled` default false (flat first, ativar nested iterativamente)
- [ ] Charter `Index.charter.md` Goals/Non-Goals/UX-targets (consumir via charter-fetch — US-COPI-109)
- [ ] Pest `RoadmapTest::it renders only business_id current tasks` (Tier 0) + `::it groups by cycle sorts by due_date` + `::it serializes blocked_by[] as SVAR dependencies` + `::it handles tasks without due_date (backlog lane)`
- [ ] RUNBOOK `memory/requisitos/Copiloto/RUNBOOK-roadmap.md` (skill `cockpit-runbook`)
- [ ] Smoke 1280px monitor (Larissa) — sem scroll horizontal, filtro current cycle default não polui Gantt

**Non-Goals:**
- ❌ Substituir tools MCP `tasks-list`/`my-work` (Gantt é viz adicional, não replace)
- ❌ Editar tasks inline no Gantt (clicar abre drawer → futuro)
- ❌ Custom fields typed (JANA-10X-026 / Onda 6 P3)
- ❌ Comentários inline timeline (next iter)

**Refs:** [GAP-ANALYSIS §V1](GAP-ANALYSIS-91-100-2026-05-13.md) · [ONDA-5-DOSSIER §3](ONDA-5-DOSSIER-2026-05-13.md) · [SVAR React Gantt MIT](https://svar.dev/react/gantt/) · [SVAR Gantt 2.4 release](https://medium.com/@SvarWidgets/svar-gantt-2-4-a-modern-gantt-chart-library-for-react-svelte-under-the-mit-license-ae62f36a5dde) · [Linear roadmap timeline](https://linear.app/changelog/2021-05-27-linear-preview-roadmap-timeline) · [GitHub Projects Hierarchy GA mar/2026](https://github.blog/changelog/2026-03-19-hierarchy-view-in-github-projects-is-now-generally-available/) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)

---

### US-COPI-112 · Onda 5 H1 — Auto-skeleton handoff-draft (tool MCP)

> owner: wagner · priority: p1 · estimate: 8h IA-pair (1d) · status: done · type: story · sprint: pós-Onda 4
> blocked_by: — (H3 `handoff-diff` Onda 3 já em prod desde 2026-05-13) · spawned_from: JANA-10X-020 (ONDA-5-DOSSIER §4)

**Como** Wagner + time MCP (Felipe/Maira/Eliana/Luiz) escrevendo handoffs
**Quero** tool MCP `handoff-draft` que lê `git log origin/main..HEAD` + `cycles-active` + `tasks-list status:doing` + `handoff-diff` (Onda 3) → 1 chamada `gpt-4o-mini` rascunha `.md` template canônico ADR 0130 que eu reviso + completo + Write final
**Para** reduzir ~1h/dia que Wagner gasta escrevendo handoff manual (~10-20min × várias/dia) — fechar Handoff #4 auto-capture (30%→80%)

**Implementado em:** `Modules/Jana/Mcp/Tools/HandoffDraftTool.php` · `Modules/Jana/Mcp/OimpressoMcpServer.php` · `Modules/Jana/Tests/Feature/Mcp/HandoffDraftToolTest.php` · verificado@08c4a8f (2026-06-21)

**Definition of Done:**
- [ ] Tool MCP `handoff-draft` exposta com schema documentado
- [ ] `HandoffDrafterService` orquestra git log (via Process) + `CyclesActiveTool::handle()` + `TasksListTool::handle(filters: {status:doing})` + `HandoffDiffTool::handle()` → monta prompt LLM
- [ ] Mock mode `HandoffDrafterService::enableMock($skeleton)` obrigatório pra Pest sem chave OpenAI (`RAGAS_FORCE_MOCK` pattern existente)
- [ ] Output respeita template canon ADR 0130: frontmatter YAML + `## Estado MCP no momento do fechamento` + `## Próximos passos` + `## Bloqueios`
- [ ] **APPEND-ONLY:** tool RASCUNHA mas NUNCA escreve `.md` — Wagner copia output e cria handoff manual (ADR 0130 §append-only)
- [ ] Prompt enforce "ONLY use facts from git log + tools output below; do NOT infer commits not present" (anti-hallucination)
- [ ] Cap git log 100 commits (cost guard)
- [ ] Cache 5min por `(business_id, since_hours)` — chamadas repetidas retornam cached
- [ ] Custo medido ~R$ [redacted Tier 0]/handoff (input ~6k tokens; output ~800 tokens) — instrumentado Langfuse (US-COPI-108)
- [ ] Pest cobre: frontmatter ADR 0130 compliant · seção "Estado MCP" presente · business_id global scope (ADR 0093 Tier 0) · empty git log gracefully · mock determinístico · cap 100 commits
- [ ] [how-trabalhar.md §Ao terminar uma sessão](../../how-trabalhar.md) atualizado: novo passo "0. (opcional) `handoff-draft` rascunha — você revisa + completa + Write"

**Non-Goals:**
- ❌ Pre-commit hook Git auto-handoff (rejeitado: bloqueia commits comuns; viola "Wagner revisa")
- ❌ PreCompact hook Claude Code custom (rejeitado: só dentro de sessão Claude Code; Eliana usa Cursor)
- ❌ AgentDiff bridge Python (rejeitado: Python-only; integration custom)
- ❌ CrewAI Memory + Mem0 refactor (rejeitado: viola CONSOLIDAR Constituição v2)

**Refs:** [GAP-ANALYSIS §H1](GAP-ANALYSIS-91-100-2026-05-13.md) · [ONDA-5-DOSSIER §4](ONDA-5-DOSSIER-2026-05-13.md) · [AgentDiff (Sunil Mallya)](https://github.com/sunilmallya/agentdiff) · [Session Handoff skill (softaworks)](https://github.com/softaworks/agent-toolkit/blob/main/skills/session-handoff/README.md) · [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md) · [ADR 0130](../../decisions/0130-handoff-append-only-mcp-first.md)

---

### US-COPI-113 · Onda 5 S1 — Schema rígido CI validation (SPEC/RUNBOOK/Session/Handoff/Charter)

> owner: wagner · priority: p1 · estimate: 12h IA-pair (1.5d) · status: done · type: story · sprint: pós-Onda 4
> blocked_by: — (estende `adr-lint.yml` + `validate-memory-schema.sh` existentes) · spawned_from: JANA-10X-021 (ONDA-5-DOSSIER §5)

**Como** Wagner + governança canon
**Quero** workflow CI `memory-schema-lint.yml` matrix-strategy validando frontmatter de TODOS tipos `.md` canon (ADR + SPEC + RUNBOOK + session + handoff + charter) via JSON Schema 2020-12 + artisan command `jana:validate-memory` rodando daily 06:30 BRT pra detectar drift fora-de-PR
**Para** fechar gap Knowledge S4 (40%→90%) — hoje só ADR é validado via `adr-lint.yml`; SPEC/RUNBOOK/session/handoff têm drift silencioso (ex: ADR sem `lifecycle:` passa, `decisions-search` devolve doc malformado)

**Implementado em:** `scripts/memory-schemas/adr.schema.json` · `scripts/memory-schemas/spec.schema.json` · `scripts/memory-schemas/runbook.schema.json` · `scripts/memory-schemas/session.schema.json` · `scripts/memory-schemas/handoff.schema.json` · `scripts/memory-schemas/charter.schema.json` · `scripts/memory-schemas/README.md` · `.github/workflows/memory-schema-gate.yml` · `Modules/Jana/Console/Commands/JanaValidateMemoryCommand.php` · verificado@08c4a8f (2026-06-21)

**Definition of Done:**
- [ ] 6 JSON Schemas 2020-12 declarados em `memory/schemas/` (adr, spec, runbook, session, handoff, charter) — required minimalista (só `title`, `type`, `decided_at`/`last_updated`), demais opcionais com defaults documentados
- [ ] Workflow `memory-schema-lint.yml` matrix: cada schema valida seu glob (`memory/decisions/*.md` → adr, `memory/requisitos/**/SPEC.md` → spec, `memory/requisitos/**/RUNBOOK*.md` → runbook, etc) — AJV via Node
- [ ] Artisan `jana:validate-memory` PHP usa `mnapoli/FrontYAML` + `opis/json-schema` — integra `jana:health-check` daily 06:30
- [ ] Flag `JANA_VALIDATE_MEMORY_STRICT=false` default 14d — emite warning sem bloquear merge (grace period migração docs antigos)
- [ ] Flag `--strict` (CLI) força exit 1 em violation
- [ ] `--exclude` glob pra docs `lifecycle: historical` (não bloquear histórico)
- [ ] Pest `ValidateMemorySchemasTest`: flags missing required ADR field · passes valid ADR · flags invalid lifecycle enum · respects --strict · graceful em unknown type
- [ ] Update `memory/decisions/_SCHEMA.md` + `memory/sessions/_INDEX.md` + `memory/handoffs/_TEMPLATE.md` referenciando schemas formais

**Non-Goals:**
- ❌ Migrar docs históricos malformados (grace period 14d + warning-only resolve org)
- ❌ Pre-commit hook local (rejeitado: não enforça em devs externos sem setup)
- ❌ Schema dinâmico runtime (overkill — JSON Schema estático suficiente)

**Refs:** [GAP-ANALYSIS §S1](GAP-ANALYSIS-91-100-2026-05-13.md) · [ONDA-5-DOSSIER §5](ONDA-5-DOSSIER-2026-05-13.md) · [adr-architecture-kit](https://github.com/egallmann/adr-architecture-kit) · [remark-lint-frontmatter-schema](https://github.com/JulianCataldo/remark-lint-frontmatter-schema) · [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)

---

## Histórico (Roadmap de Ondas)

- **v2.0.0** (2026-05-20) — Apendado seção "Roadmap de Ondas" + US-COPI-107/108/109 (Onda 4 P0: Reranker BGE-v2-m3 + Langfuse v3 + Charters S4). Cruza [GAP-ANALYSIS-91-100](GAP-ANALYSIS-91-100-2026-05-13.md) + [ONDA-5-DOSSIER](ONDA-5-DOSSIER-2026-05-13.md). Mapping: US-COPI-107=JANA-10X-016 (R1) · US-COPI-108=JANA-10X-017 (L1) · US-COPI-109=JANA-10X-018 (C1). Ondas 5-6 ficam como backlog visível na tabela — entrarão como US-COPI-110..113 quando Onda 4 fechar + Langfuse rodar 14d. Tasks MCP NÃO criadas — Wagner aprova batch.
- **v3.0.0** (2026-05-20) — Apendado **Onda 5 P1 inteira** (US-COPI-110/111/112/113): K1 Time-decay weighting + V1 Roadmap Gantt UI + H1 Auto-skeleton handoff-draft + S1 Schema CI memory-schema-lint. Mapping: US-COPI-110=JANA-10X-019 (K1) · US-COPI-111=JANA-10X-022 (V1) · US-COPI-112=JANA-10X-020 (H1) · US-COPI-113=JANA-10X-021 (S1). Tabela Roadmap atualizada: Onda 5 vai de "🔒 backlog" pra "🟡 SPEC pronto, gate Langfuse". **Bloqueio explícito:** US-COPI-110 (K1) depende de US-COPI-107 (R1) + US-COPI-108 (L1) — sem reranker E Langfuse rodando 14d em prod, ganho NDCG temporal é fé teórica. US-COPI-111/112/113 são independentes. Onda 6 (JANA-10X-023..027) permanece backlog ADR feature-wish gate ADR 0105.

---

## Onda 6 Audit Sênior 2026-05-25

> Origem: [`AUDIT-SENIOR-2026-05-25.md`](AUDIT-SENIOR-2026-05-25.md). Jana é REAL 96/100 (grade engine mostra 71 por bug — ver US-GOV-012). LGPD operacional + RAGAS canary + Langfuse self-host fecham gaps últimos.

### US-COPI-115 · LGPD jana:retention-purge artisan + DSR Art. 18 §VI + tool MCP lgpd-esquecer-titular

**Implementado em:** `Modules/Jana/Console/Commands/RetentionPurgeCommand.php` · `Modules/Jana/Services/Lgpd/DsrService.php` · `Modules/Jana/Mcp/Tools/LgpdEsquecerTitularTool.php` · verificado@dd3ed7c (2026-07-01) — artisan `jana:retention-purge` (com --dry-run/--business/--entity) + serviço DSR esquecimento + tool MCP `lgpd-esquecer-titular`

> owner: — · priority: p0 · estimate: 6h · status: done · type: story
> blocked_by: —

**Origem:** Audit Sênior Jana 2026-05-25 — G1 P0 (Onda 6).

**Estado real:** Jana é 96/100 (não 71 como grade engine mostra). Config canônica de retention existe; jobs faltam.

**Acceptance:**
- [ ] Artisan `jana:retention-purge` (schedule daily 03:00 BRT)
- [ ] DSR (Data Subject Request) flow Art. 18 §VI LGPD (direito esquecimento)
- [ ] Tool MCP `lgpd-esquecer-titular(cpf_or_cnpj)` — purga chat_messages + memoria_facts + contextos do titular
- [ ] Pest cobre: purge por retention OK, DSR completa em <30d (LGPD limite), evidência auditável em audit_log
- [ ] Wagner aprova `JANA_RETENTION_ENABLED=true` em prod biz=1 após canary 7d

**Refs:** AUDIT-SENIOR-2026-05-25.md §G1, [DSR Fulfillment Timeline](https://securiti.ai/dsr-fulfillment-timeline/), [LGPD Compliance 2026](https://secureprivacy.ai/blog/lgpd-compliance-requirements)

### US-COPI-116 · RAGAS canary CI daily 06:00 UTC + 30 golden questions gate

**Implementado em:** `.github/workflows/jana-ragas-canary.yml` · `.github/workflows/jana-ragas-gate.yml` · `Modules/Jana/Console/Commands/JanaRagasCiCommand.php` · verificado@dd3ed7c (2026-07-01) — workflows CI RAGAS (canary + gate) + comando `jana:ragas-ci-eval`; cron/golden-set/baseline conforme DoD

> owner: — · priority: p0 · estimate: 3h · status: done · type: story
> blocked_by: —

**Origem:** Audit Sênior Jana 2026-05-25 — G2 P0 (Onda 6).

**Sintoma:** `jana-gold-set.json` com golden questions já existe, mas falta gate CI que bloqueie regressão.

**Acceptance:**
- [ ] GitHub Actions cron daily 06:00 UTC
- [ ] Roda RAGAS contra 30 golden questions
- [ ] Métricas: faithfulness, answer_relevancy, context_precision, context_recall
- [ ] Gate: se score regredir >5% vs baseline → fail CI + alert Slack/Discord
- [ ] Baseline salvo em `governance/jana-ragas-baseline.json`

**Refs:** AUDIT-SENIOR-2026-05-25.md §G2, [Cohere Rerank RAG 2026](https://futureagi.com/blog/evaluating-cohere-rerank-rag-2026/)

### US-COPI-117 · Deploy Langfuse self-host CT 100 (ADR 0132)

**Implementado em:** `Modules/Jana/Services/Telemetry/LangfuseClient.php` · `docker/langfuse/docker-compose.yml` · `Modules/Jana/Jobs/Telemetry/LangfuseTraceJob.php` · verificado@1f4fdc98 (2026-07-12) — **deploy CT 100 CONFIRMADO vivo** via `docker ps` direto no host (não só código): 6 containers healthy (`langfuse-web`, `langfuse-worker`, `postgres-langfuse`, `clickhouse-langfuse`, `redis-langfuse`, `minio-langfuse`), up 2 semanas. Tráfego real verificado via query ClickHouse: **3.226 traces**, volume diário ativo 200-450/dia (últimos 10d), 7 agents instrumentados (`brain-b-agent` 2043, `anonymous-agent` 722, `planner-agent` 180, `health-narrator-agent` 174, `kb-answer-agent` 102, `brief-diario-agent`/`briefing-agent` 3). **GAP encontrado nesta verificação:** tag de trace é só `['live']` — isolamento por `business_id` (acceptance item 3) NÃO implementado. Sobrepõe US-COPI-108.

> owner: — · priority: p1 · estimate: 2h (fecha só o gap business_id) · type: story
> blocked_by: —

**Origem:** Audit Sênior Jana 2026-05-25 — G3 P0 (Onda 6).

**Sintoma (histórico):** OTel GenAI instrumentado mas collector off. ADR 0132 já decidida (Langfuse self-host CT 100). **Sintoma atual (2026-07-12):** collector está ON e recebendo tráfego real — o gap real é isolamento multi-tenant na tag, não o deploy.

**Acceptance:**
- [x] Langfuse docker-compose no CT 100 Proxmox — verificado 2026-07-12 via `docker ps` (6/6 containers healthy)
- [ ] OTel GenAI semconv native pointing pro Langfuse — não re-verificado nesta passada (traces chegam via `LangfuseClient`/`LangfuseTraceJob`, não confirmado se é semconv OTel nativo ou wrapper direto)
- [ ] **Workspace-level isolation (1 workspace por business_id) — GAP CONFIRMADO 2026-07-12:** `SELECT tags FROM traces WHERE timestamp > now() - INTERVAL 1 DAY` retorna só `['live']`. Sem `business_id` na tag, viola isolamento Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) para efeitos de observability (dado operacional continua isolado — é o TRACE que vaza granularidade)
- [ ] Custo: R$ [redacted Tier 0]-80/mês CT 100 — Wagner aprova — não verificado
- [ ] Dashboards default: token usage, latência p50/p95, custo por agent, traces por business — não verificado (não abri a UI Langfuse)

**Refs:** AUDIT-SENIOR-2026-05-25.md §G3, ADR 0132, [LLM Observability 2026](https://www.spheron.network/blog/llm-observability-gpu-cloud-langfuse-arize-phoenix-helicone/), [OpenTelemetry GenAI](https://dev.to/x4nent/opentelemetry-genai-semantic-conventions-the-standard-for-llm-observability-1o2a)

### US-COPI-132 · Langfuse traces sem tag business_id (isolamento multi-tenant observability)

> owner: — · priority: p1 · estimate: 2h · status: todo · type: story
> blocked_by: —

**Origem:** verificação de máquina 2026-07-12 (query direta ClickHouse no CT 100, não doc) durante fechamento do gap US-COPI-117.

**Achado:** `docker exec clickhouse-langfuse clickhouse-client --query "SELECT tags, count() FROM traces WHERE timestamp > now() - INTERVAL 1 DAY GROUP BY tags"` retorna só `['live']`. Nenhuma trace tem `business_id` na tag, apesar do deploy Langfuse estar vivo e recebendo tráfego real (3.226 traces, 200-450/dia, 7 agents instrumentados incl. brain-b-agent, planner-agent, kb-answer-agent).

**Por que importa:** ADR 0093 (multi-tenant Tier 0) exige isolamento por business_id em toda camada que toca dado de negócio — observability inclusa quando prompts/respostas carregam contexto do cliente. Sem a tag, não dá pra filtrar "custo/latência/traces do business X" nem auditar vazamento cross-tenant no nível de trace.

**Acceptance:**
- [ ] `LangfuseClient`/`LangfuseTraceJob` propaga `business_id` como tag em toda trace criada
- [ ] Query ClickHouse `SELECT DISTINCT tags FROM traces WHERE timestamp > now() - INTERVAL 1 DAY` mostra tags com `business_id:N` (não só `live`)
- [ ] Dashboard "custo/latência por business" fica possível filtrando pela tag

**Relacionado:** US-COPI-117 (Deploy Langfuse self-host CT 100, ADR 0132) — deploy confirmado done nesta mesma verificação, este é o sub-gap que ficou.

---

**Última atualização:** 2026-07-12 (4º) — **Reconciliação adversarial Ondas 4-5 (juiz wf_33e38126: "meio honesta, meio inflada")**: (a) **US-COPI-110** — defeito verificado: o score do `applyTimeDecay` era DESCARTADO pelo `RrfReranker` default (ranqueia por posição, nunca lê score) → idade tinha efeito ZERO na ordem final; fix = ordenação por score decaído dentro do estágio (flag-guarded) + `TemporalScoringTest` fim-de-estágio (contrato: recente accepted vence antiga superseded, falha no pré-fix); teste tautológico "ordem preservada" do `TimeDecayTest` reescrito pra contrato de reordenamento; DoD reconciliado com divergências declaradas (fórmula multiplicativa, flag `JANA_TIME_DECAY_ENABLED` default true). (b) **US-COPI-087** — âncora stale ("container CT 100 não confirmado") reconciliada com a tabela Onda 4 (container live via 107); resta só meta RAGAS ≥0.85. (c) **US-COPI-108** — checkbox falso "RAGAS gate CI emite traces" corrigido em PR pareado (call-sites `Http::` fora do listener Langfuse). Anterior: 2026-07-12 (3º) — **US-COPI-133 + US-COPI-134 criadas via MCP `tasks-create`** (sessão CYCLE-BI-01): 133 = descongelar Jana-BI (context_recall 0,38→0,60, o número 132 planejado no handoff 2026-07-10-1443 estava ocupado); 134 = régua ADR 0318 órfã (schedules staging sem runner no CT100, achado do spike hybrid 2026-07-12). Anterior: 2026-07-12 (2º) — **Reconciliação Ondas 4-5 por verificação de máquina** (não doc): US-COPI-108 (Langfuse) parcial→done, US-COPI-110 (time-decay) todo→done (método `applyTimeDecay()` wired, SPEC chutava nome errado), US-COPI-109 (charters) →done com meta "Tier A" marcada SUPERSEDED pela ADR 0225. Tabela de maturidade: Onda 4 🟡→✅ DONE, Onda 5 🟡→🟢 3/4 (falta só 111 Gantt UI). Maturidade real ~97% (doc subcontava 91%). Anterior: 2026-07-12 — US-COPI-117 reverificado por máquina; gap business_id virou US-COPI-132. Antes: 2026-05-25 — v3.1.0 Onda 6 Audit Sênior 2026-05-25 apendada (US-COPI-115/116/117). US-COPI-115 implementada em paralelo com US-GOV-011 + US-PG-001 + US-COM-006 (PR #1567/1568/1569 + PR Jana em curso). Bypass MCP `tasks-create` aplicado em SPEC.md direto (mcp_jira_projects entry "Jana" → COPI mapeada — 115/116/117 criadas via MCP server remoto, este apend sincroniza local via webhook).

### US-COPI-118 · Tokenizar cores cruas do card-de-prova Pro.tsx (fix ui:lint R1 pré-existente)

**Implementado em:** _pendente_ — fix não aplicado: `resources/js/Pages/Jana/Pro.tsx` ainda tem cores cruas `oklch(...)` + `linear-gradient(135deg…)` (gate ui:lint R1 vermelho pré-existente do #2069); substituição por tokens DS de superfície-dark ainda não feita (status todo)

> owner: — · priority: p1 · estimate: 2h · status: todo · type: story
> blocked_by: —

**Origem:** sessão 2026-06-01 (design:review #2078). O gate `ui:lint · ratchet` está **vermelho** em `resources/js/Pages/Jana/Pro.tsx · R1 · 0 → 2` — cor crua (`oklch(...)` literal no card-de-prova dark + `linear-gradient(135deg, oklch…)` na linha 183, o trope que R5/AP5 proíbe). **Pré-existente do #2069** (Pro.tsx mergeado sem entrada em `config/ui-lint-baseline.json`), não do #2078. Jamma a CI verde de QUALQUER PR derivado do main.

**Aceite:**
- [ ] Substituir os `oklch(...)` crus de `Pro.tsx` por tokens DS (provável criar **tokens de superfície-dark** novos — decisão de design, alinhar com Cowork/BRIEFING §tokens).
- [ ] Remover/tokenizar o `linear-gradient(135deg…)` (AP5).
- [ ] `php artisan ui:lint` → `Pro.tsx` R1 = 0 (ou baseline atualizado se intencional).
- [ ] CI `UI Lint · ratchet` verde.

**Refs:** PR #2078 · `Jana/Pro.review.md` (P0 R1 registrado) · GOLDEN-REFERENCE R1/R5 · ADR 0190/0235.

---

### US-COPI-119 · design:review Fase 2 — juiz LLM (R5/R8/R10 + nota holística + best_of_class)

**Implementado em:** _pendente_ — Fase 2 (juiz LLM que preenche R5/R8/R10 + nota holística + best_of_class no `<Tela>.review.md`) não implementada; custo/infra Tier 0 aguarda decisão [W] (status todo)

> owner: — · priority: p2 · estimate: 8h · status: todo · type: story
> blocked_by: —

**Origem:** sessão 2026-06-01 (design:review #2078 MERGED). A Fase 1 (mecanizada, `score-mechanized.mjs` + `review-gen.mjs`) entrega o backlog por tela, mas as 3 regras JULGADAS (R5 gradient · R8 PT-BR · R10 overflow-chain) + nota holística + `top_gaps.best_of_class` ficam pendentes do juiz LLM. Hoje a nota é **teto provisório** (só conformidade-DS), mascarável.

**Aceite:**
- [ ] Estender `review-gen.mjs`/pipeline com Fase 2 (agente LLM read-only) que preenche R5/R8/R10 + nota holística + `best_of_class`/`fix`/`esforco` no `<Tela>.review.md` (round N append-only).
- [ ] Cadência **real-mode** na régua que [W] paga (espelha gate RAGAS). Ratchet: nota só sobe (ADR 0236).
- [ ] Hardenizar `design_review_stale` (advisory→HARD) conforme reviews regenerados com sha.
- [ ] 1ª execução piloto = `Jana/Pro` (já tem round 1 mecanizado nota 88).

**Custo/infra = Tier 0 → espera decisão [W].** Refs: proposta `memory/decisions/proposals/design-review-por-tela-charter-page.md` · `prototipo-ui/audit/` · PROTOCOL §6.

---

**Última atualização:** 2026-06-01 — US-COPI-118 + US-COPI-119 apendadas (follow-ups do design:review #2078 MERGED: fix ui:lint Pro.tsx + Fase 2 juiz-LLM). Criadas via `tasks-create` MCP (US-COPI-118/119); este apend sincroniza pro DB via webhook no push.

### US-COPI-123 · Remover startMockStream da rota live /ia/dashboard (Cockpit responde mock)

**Implementado em:** _pendente_ — fix não aplicado: `resources/js/Pages/Jana/Cockpit.tsx` ainda define e chama `startMockStream` (linhas ~707/780) na rota live `/ia/cockpit`; falta plugar streaming real (Jana chat/SSE) com fallback (status todo)

> owner: — · priority: p0 · estimate: 4h · status: todo · type: story
> blocked_by: —
> parent_plan: adr0270-cockpit-mock-kill

**Iniciativa-plano perdida** recuperada pro backlog (triagem 2026-06-20 · run wf_1bfbefba).
labels: `plano-perdido`, `backlog-2026-06-20`

**Sinal (ADR 0105 · P0 prod):** o Cockpit (`resources/js/Pages/Jana/Cockpit.tsx:705-780`) usa `startMockStream` numa rota **live** `/ia/dashboard` — responde dados mock em produção. Gap P0 do handoff 2026-06-11.

**DoD:**
- Remover `startMockStream` da rota live.
- Plugar fonte de streaming real (Jana chat / SSE) com fallback.
- Smoke browser MCP + Pest do payload real.

**Fonte:** memory/requisitos/_processo/BATCH-BACKLOG-34-2026-06-20.md (§Aprovação [W] 2026-06-20)

### US-COPI-124 · Escopar delete do ContentReconciler por business_id (healable=false, Tier-0)

**Implementado em:** _pendente_ — fix não aplicado: `Modules/Jana/Services/Reconcile/Reconcilers/ContentReconciler.php` segue `healable=false` (delete global sem `business_id`, Tier-0-inseguro documentado no código); falta escopar o delete + reativar `healable=true` com guard de tenant (status todo)

> owner: — · priority: p0 · estimate: 4h · status: todo · type: story
> blocked_by: —
> parent_plan: content-reconciler-safe-heal

**Iniciativa-plano perdida** recuperada pro backlog (triagem 2026-06-20 · run wf_1bfbefba).
labels: `plano-perdido`, `backlog-2026-06-20`

**Sinal (ADR 0105 · P0 Tier-0):** `ContentReconciler` está `healable=false` porque o delete é **global, sem `business_id`** (comment no código: "delete global sem business_id — Tier-0-inseguro (ADR 0093)"). Risco de corrupção cross-tenant se reativado sem guard.

**DoD:**
- Escopar o delete por `business_id`.
- Reativar `healable=true` só com o guard de tenant.
- Teste de isolamento multi-tenant.

**Fonte:** memory/requisitos/_processo/BATCH-BACKLOG-34-2026-06-20.md (§Aprovação [W] 2026-06-20)

### US-COPI-125 · Adicionar kb_node_visibility + filtro ACL pre-retrieval no KbRagService (LGPD)

**Implementado em:** _pendente_ — coluna/tabela `kb_node_visibility` não existe (grep zero em Modules/) e `Modules/KB/Services/KbRagService.php` não aplica ACL row-level pré-retrieve; feature ACL-aware RAG ainda não construída (status todo)

> owner: — · priority: p0 · estimate: 8h · status: todo · type: story
> blocked_by: —
> parent_plan: kb-acl-aware-rag

**Iniciativa-plano perdida** recuperada pro backlog (triagem 2026-06-20 · run wf_1bfbefba).
labels: `plano-perdido`, `backlog-2026-06-20`

**Sinal (ADR 0105 · P0 LGPD):** sem `kb_node_visibility` + ACL row-level no pre-retrieval, o RAG não pode ser liberado pro time MCP (risco de vazamento entre escopos). Bloqueante levantado pelo Agent D 2026-05-15.

**DoD:**
- Coluna/tabela `kb_node_visibility`.
- Filtro ACL **antes** do retrieve (não pós-filtro).
- Teste de isolamento de visibilidade.

**Fonte:** memory/requisitos/_processo/BATCH-BACKLOG-34-2026-06-20.md (§Aprovação [W] 2026-06-20)

### US-COPI-126 · Propagar renames Copiloto→Jana / MemCofre→SRS nos ~112 PHP em Modules/

**Implementado em:** _pendente_ — rename em massa não propagado: ~115 arquivos PHP em `Modules/` ainda citam `Copiloto` (config keys `copiloto.*`, permissões, comentários); decisão de canonical + propagação segura (namespaces/rotas/views) + testes pós-rename ainda não feitos (status todo)

> owner: — · priority: p0 · estimate: 6h · status: todo · type: story
> blocked_by: —
> parent_plan: knowledge-drift-rename-propagation

**Iniciativa-plano perdida** recuperada pro backlog (triagem 2026-06-20 · run wf_1bfbefba).
labels: `plano-perdido`, `backlog-2026-06-20`

**Sinal (ADR 0105):** drift de nomenclatura — `git grep` acha ~112 PHP em `Modules/` citando `Copiloto` + 27 citando `MemCofre` (renames Copiloto→Jana / MemCofre→SRS não propagados).

**⚠️ Pré-condição:** CONFIRMAR primeiro se o módulo ainda é `Copiloto` no código (vs já renomeado pra Jana) antes de qualquer rename em massa — risco de quebrar referências.

**DoD:**
- Inventário grep atualizado (Copiloto + MemCofre).
- Decidir canonical e propagar com segurança (namespaces, rotas, views).
- Testes verdes pós-rename.

**Fonte:** memory/requisitos/_processo/BATCH-BACKLOG-34-2026-06-20.md (§Aprovação [W] 2026-06-20)

### US-COPI-127 · Criar view cliente /copiloto/decisoes/{id}/revisao (LGPD Art.20)

**Implementado em:** _pendente_ — rota + página de revisão de decisão automatizada pro cliente-final (`/copiloto/decisoes/{id}/revisao`) não existe (grep zero nas rotas); só há UI admin do HITL (status todo)

> owner: — · priority: p0 · estimate: 4h · status: todo · type: story
> blocked_by: —
> parent_plan: hitl-audit-card-ui-copiloto

**Iniciativa-plano perdida** recuperada pro backlog (triagem 2026-06-20 · run wf_1bfbefba).
labels: `plano-perdido`, `backlog-2026-06-20`

**Sinal (ADR 0105 · LGPD Art.20):** direito de revisão de decisão automatizada — a UI admin do HITL existe, mas falta a view do **cliente-final** em `/copiloto/decisoes/{id}/revisao`.

**DoD:**
- Rota + página de revisão da decisão (cliente-final).
- Permissions adequadas (cliente vê só as próprias).
- Audit log da revisão.

**Fonte:** memory/requisitos/_processo/BATCH-BACKLOG-34-2026-06-20.md (§Aprovação [W] 2026-06-20)

### US-COPI-128 · Health-check multi_tenant_isolation cego a C3 — ler information_schema + probe cross-tenant READ

**Implementado em:** _pendente_ — fix não aplicado: `HealthCheckCommand::checkMultiTenant()` ainda valida array hard-coded de tabelas + só procura órfãos `business_id IS NULL`; falta ler tabelas do `information_schema` (cobrir C1/C3) + probe de cross-tenant READ real (status todo)

> owner: — · priority: p1 · estimate: 5h · status: todo · type: story
> blocked_by: —

**Origem:** rodada adversarial do ADR 0296 (achado S-1, critical). `HealthCheckCommand::checkMultiTenant()` (linha ~178) valida um array HARD-CODED de 3 tabelas Jana e só procura órfãos `business_id IS NULL` — nunca olha `mcp_dual_brain_decisions` (C3) nem faz cross-tenant READ. O critério de sucesso do cutover P1 do ADR 0296 ("isolamento idêntico antes/depois") é hoje **inverificável**.

**Acceptance:**
- [ ] Ler a lista de tabelas com `business_id` do `information_schema` (não array hard-coded) e cobrir todas C1/C3.
- [ ] Adicionar probe de cross-tenant READ real: autentica biz=X, consulta C3 pelos MESMOS code-paths do app, afirma zero rows de biz≠X.
- [ ] Tornar isso pré-requisito do gate de cutover P1 (INVARIANTE-TIER0-VERIFICADA do ADR 0296).

Refs: ADR 0296 (S-1) · ADR 0093 · HealthCheckCommand.php:178.

### US-COPI-129 · Consertar jana:recall-eval (mock) — golden set estrutura_ok:false, 10 violações

**Implementado em:** _pendente_ — investigação/fix não realizado: `Modules/Jana/Console/Commands/JanaRecallEvalCommand.php` existe e roda, mas o gate `jana:recall-eval --mode=mock` segue vermelho no main (`estrutura_ok:false`, 10 violações); ainda não decidido se golden `tests/eval/recall-golden.yaml` está stale ou se é regressão real (status todo)

> owner: — · priority: p2 · status: todo · type: story
> blocked_by: —

O gate CI `Jana recall-eval (mock — golden set determinístico)` está vermelho no main (não-required, pré-existente — descoberto 2026-07-01 durante hotfix não-relacionado #3506).

**Evidência (run 28528973733):** `php artisan jana:recall-eval --mode=mock --json` retorna:
- `n_queries: 27`, `n_collision_queries: 6`, `n_queries_with_violations: 10`
- `checks.yaml_parse: true` mas `checks.estrutura_ok: false` → exit 1

**Investigar:** (1) o que `estrutura_ok:false` significa (schema do golden `tests/eval/recall-golden.yaml` divergiu do que o comando espera?); (2) as 10 queries com violação são regressão real de recall ou o golden ficou stale; (3) se o gate deve ser required (hoje não é) ou é teatro a aposentar (cf. ADR 0271 subtração segura + jana-ragas-gate teatro).

**Contexto:** não bloqueia merges (não-required). Fora do escopo do incidente da devolução (#3488/#3494/#3506). Não mexer em Jana sem OK Wagner.

### US-COPI-130 · Reranker BGE + Contextual Retrieval no docs_pipeline (context_recall 0.42 → âncora ~0.85)

**Implementado em:** _pendente_ — camada 1 (hybrid) LIVE via PR #3791 (`McpMemoryDocument::buscarHybrid` HTTP direto no Meilisearch, bypassa o Scout `collection` engine que ignorava o `hybrid`); camadas 2 (contextual) e 3 (reranker) ainda desconectadas do índice `mcp_memory_documents`

> owner: — · priority: p2 · estimate: 12h · status: todo · type: story
> blocked_by: —

**Contexto (medido no CT 100 staging, 2026-07-04):** o fix hybrid (PR #3791) ligou a **camada 1** (retrieval semântico) do `docs_pipeline`. Ganho comprovado em query semântica: recall@5 determinístico no golden set 0.074 → 0.704 (~9.5x), acha o doc-fonte no top-1. Porém o context_recall LLM-judged (régua [ADR 0318](../../decisions/0318-ragas-eval-real-mata-tautologia-ct100-staging.md)) mal moveu — A/B limpo mesmo corpus (N=51): faithfulness 0.715→0.719, relevancy 0.875→0.880, **context_recall 0.395→0.422**. O gargalo remanescente é downstream do ranking (excerpt/chunk + reordenação), não a camada 1.

**Âncora dos melhores (RAG 2026):** context_recall ~0.85-0.90. As 2 camadas que faltam já existem na casa:

- **Camada 2 — Contextual Retrieval (Anthropic, ~−35% failed retrievals):** popular `contextual_context` + indexar. Os campos JÁ existem em `McpMemoryDocument` (`contextual_context`, `contextual_indexed`, `contextualized_at`) — falta o job de geração + incluir no template do embedder do índice.
- **Camada 3 — Reranker cross-encoder (~−67% combinado):** **REUSAR** o `BgeReranker` (`Modules/Jana/Services/Retrieval/BgeReranker.php`) já implementado em **US-COPI-087** + a infra self-host de **US-COPI-107** (Onda 4 R1) — aquelas atacam o índice `jana_memoria_facts` (chat de negócio); **esta** aplica ao índice `mcp_memory_documents` (docs/ADRs, usado por `kb-answer`/`decisions-search`). Pipeline distinto, mesmo componente — NÃO recriar.

**Meta:** context_recall canônico 0.42 → ~0.80-0.85 (medido por `jana:ragas-real-eval` no CT 100 staging, mesma régua do ADR 0318). Cada camada verificada por A/B (golden set + ragas) antes de promover.

**Refs:** PR #3791 · ADR 0318 · US-RET-003 · US-COPI-087 (reranker existente) · US-COPI-107 (Onda 4 R1) · [RUNBOOK-bge-reranker-ct100](../Infra/RUNBOOK-bge-reranker-ct100.md)

### US-COPI-131 · Elevar tela Regras/Index a ≥70 (listar policies read-only + token roxo)

> owner: — · priority: p3 · status: todo · type: story
> blocked_by: —

Achado Onda 1 (re-grade telas stale). `Jana/Regras/Index` = **68 (Developing)**. Tela interna (admin Jana/PolicyEngine).

Gaps (`jana-regras-index.yaml`):
- **Speed-to-task (alto):** hoje só redireciona; listar policies do PolicyEngine (4 outcomes) read-only.
- **Pre-Flight (baixo):** `oklch(0.62 0.13 220)` + `violet-500` inline fora do primary roxo v4.
- **Information-hierarchy (médio):** sem preview de regras; agregar contagem/estado das políticas ativas.

DoD: nota ≥70 + ratchet verde. Charter + gate visual antes de Editar a Page.

### US-COPI-133 · Descongelar Jana-BI — context_recall 0,38→0,60 (régua jana:ragas-real-eval CT100)

**Implementado em:** _pendente_

> owner: wagner · sprint: CYCLE-BI-01 · priority: p0 · estimate: 12h · status: todo · type: story
> blocked_by: —

Descongelar a Jana-BI (camada B, ADR 0334): subir context_recall do pipeline kb-answer/docs de 0,3839 (baseline `governance/jana-ragas-real-baseline.json`, ADR 0318) pra ≥0,60, medido por `jana:ragas-real-eval` no CT 100 staging (NUNCA local/Hostinger — Tier 0).

**Contexto (verificado 2026-07-12):**
- Substitui o número planejado "US-COPI-132" do handoff 2026-07-10-1443 — o 132 foi consumido pela tag business_id no Langfuse (handoff 2026-07-12-2245). Esta é a US nova do CYCLE-BI-01 (cycle fica em **planning** — NÃO ativar; `client_signal` só faz sentido depois que a Larissa usar).
- Spike hybrid (docs_pipeline camada 1) MEDIDO 2×: A/B 2026-07-04 (US-COPI-130) context_recall 0,395→0,422; **re-run 2026-07-12 desta US (CT 100 staging, N=51, ~USD 0,17): faithfulness 0,7355→0,7675 (+0,032) · relevancy 0,8784→0,8510 (−0,027) · context_recall 0,3939→0,4506 (+0,057)**. Hybrid melhora mas NÃO chega a 0,60 — reabertura sozinha não é o PR pra prod.
- **Causa dominante identificada no código (2026-07-12):** `KbAnswerService::renderFontes()` corta cada doc a **400 chars do INÍCIO** (`extrairExcerpt`) — síntese e juiz nunca veem o fato se ele mora no meio da ADR. É o item (b) pendente da condição de reativação da ADR 0312 (documentTemplate real). Por isso ranking 9,5× melhor (recall@5 0,074→0,704) quase não move o context_recall.

**Caminho (ordem de ROI, recalibrada pelo spike):**
1. **Excerpt/chunk query-aware no `renderFontes`** (passar o trecho relevante à pergunta, não os primeiros 400 chars) — alavanca mais barata, ataca o gargalo medido.
2. US-COPI-130 camadas 2-3 (Contextual Retrieval nos campos `contextual_*` já existentes + REUSAR BgeReranker de US-COPI-087 no índice `mcp_memory_documents`) — âncora 0,80-0,85.
3. Bipartir corpus negócio ≠ processo (ADR 0334 §4) + **bipartir o eval junto**: o golden set atual (`jana-gold-set.json`, 51q) é de PROCESSO (format_date, Permission Registry, Vizra) — bipartir corpus sem bipartir eval não move o número.
4. Re-medir antes→depois a cada camada com `jana:ragas-real-eval` (mesma régua, N=51).

**Guard-rails Tier 0:** eval SÓ no CT 100 staging · não tocar prod nem biz=4 · gates humanos (confiabilidade → mão da Larissa → `client_signal`) ficam com Wagner · dupla-conferência em qualquer número apresentado.

**Refs:** ADR 0334 · ADR 0318 · ADR 0312 (condição de reativação hybrid) · ADR 0322 · US-COPI-130 · handoffs 2026-07-10-1443 e 2026-07-12-2245.

### US-COPI-134 · Régua ADR 0318 órfã — schedules staging (ragas-real-eval + recall-eval) sem runner no CT100

**Implementado em:** _pendente_

> owner: wagner · priority: p1 · estimate: 3h · status: todo · type: story
> blocked_by: —

A régua semanal do ADR 0318 (`jana:ragas-real-eval`, Kernel dom 07:00 staging) e a do loop IA-OS #3 (`jana:recall-eval --mode=real`, dom 06:30 staging) NÃO têm runner — verificado 2026-07-12 no CT 100: container `oimpresso-staging` sem processo `schedule:run` e sem cron de container (só `/etc/periodic` default Alpine); crontab do host só tem o PUBLISHER (`ct100-ragas-publish.sh`, dom 08:30).

**Consequências medidas:** `storage/app/governance/ragas-real-eval-latest.json` datado 04/jul (rodada MANUAL do A/B US-COPI-130, não cron); trend órfã `governance/ragas-real-trend` com última semana válida 2026-06-28; publisher de 12/jul publicou dado stale.

**Fix:** instalar runner canônico (host cron chamando `docker exec oimpresso-staging php artisan schedule:run` por minuto, OU cron semanal direto dos 2 comandos, seguindo o pattern do `ct100-ragas-publish.sh`) + REGISTRAR no git (mexeu-registra) + investigar por que o trend rejeitou a semana de 04/jul ("1 semana(s), última 2026-06-28"). Sem isso, `ragas_real_uptime` do scorecard SDD mede uma régua morta.

**Refs:** ADR 0318 · `scripts/tests/ct100-ragas-publish.sh` · `app/Console/Kernel.php` (schedules dom 06:30/07:00 staging).
