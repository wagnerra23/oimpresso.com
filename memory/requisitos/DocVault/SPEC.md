# Especificação funcional · DocVault

## 1. Escopo

Cofre de documentação viva. Ingere evidências (screenshots, chat logs, erros, arquivos), classifica (manual ou IA), e estrutura em requisitos rastreáveis por módulo do sistema.

## 2. User stories

### US-DOCVAULT-001 · Ver dashboard com cobertura por módulo

**Como** desenvolvedor responsável pela documentação
**Quero** abrir `/docs` e ver de relance quais módulos têm spec completa
**Para** priorizar onde escrever/revisar requisitos.

**DoD:**
- [x] Lista módulos com status, contagem de stories, regras e % DoD
- [x] KPIs globais no topo (total de módulos, stories, regras, DoD %)
- [x] Lista fontes recentes (últimas 10 evidências)
- [x] Link direto pra cada módulo
- [x] Indicador de evidências pendentes no topo

**Implementado em:** `Modules/DocVault/Http/Controllers/DashboardController.php`

### US-DOCVAULT-002 · Adicionar evidência

**Como** qualquer colaborador
**Quero** submeter uma evidência (print de erro, link de issue, trecho de chat)
**Para** não perder informação informal que descreve comportamento do sistema.

**DoD:**
- [x] Suporta upload de arquivo (até 20MB, mime list configurável)
- [x] Suporta URL (link pra issue/wiki)
- [x] Suporta texto livre (quote/nota rápida)
- [x] Pergunta módulo alvo (dropdown)
- [x] Pergunta tipo (bug/rule/flow/quote/screenshot/decision)
- [x] Cria DocSource + DocEvidence com status=pending
- [ ] IA sugere automaticamente módulo+tipo+story (Fase 3)

**Implementado em:** `IngestController.php`

### US-DOCVAULT-003 · Triar evidências pendentes

**Como** desenvolvedor
**Quero** abrir `/docs/inbox` e classificar evidências
**Para** transformar ruído em requisitos estruturados.

**DoD:**
- [x] Lista com filtros (status, módulo)
- [x] Badges de quantidade por status
- [x] Preview do conteúdo + link pra fonte original
- [x] Editar kind, module_target, suggested_story_id, notes
- [x] Marcar como triaged/applied/rejected/duplicate
- [x] Deletar evidência
- [ ] Botão "Apply" regrava automaticamente o SPEC.md (Fase 3)

**Implementado em:** `InboxController.php`

### US-DOCVAULT-004 · Ver detalhes de um módulo

**Como** dev novo no projeto
**Quero** abrir `/docs/modulos/{Nome}` e ver tudo sobre aquele módulo
**Para** entender o escopo sem perguntar pra ninguém.

**DoD:**
- [x] Overview com frontmatter + áreas funcionais + KPIs
- [x] Tab Stories com progresso DoD
- [x] Tab Regras com status testado
- [x] Tab Arquitetura (quando formato pasta)
- [x] Tab Changelog (quando formato pasta)
- [x] Tab Markdown bruto
- [ ] Tab Evidências com fontes rastreáveis (Fase 3)

**Implementado em:** `ModuloController.php`

### US-DOCVAULT-005 · Chat com o conhecimento

**Como** usuário do sistema
**Quero** conversar com um assistente treinado no conteúdo do DocVault
**Para** tirar dúvidas sobre o sistema sem procurar manualmente.

**DoD:**
- [ ] Rota `/docs/chat` com interface conversacional
- [ ] Contexto montado a partir dos 4 arquivos de cada módulo
- [ ] Busca relevante por módulo via keywords (Fase 2) ou embeddings (Fase 4)
- [ ] Usa OpenAI quando `AI_ENABLED=true`, senão responde "desabilitado"
- [ ] Histórico por usuário persistido em `docs_chat_messages`

**Implementado em:** `ChatController.php` + `ChatAssistant.php` + `resources/js/Pages/DocVault/Chat.tsx` (modo offline funcionando; modo AI com stub aguardando OPENAI_API_KEY).

### US-DOCVAULT-006 · Navegar memória unificada

**Como** dev ou agente de IA
**Quero** abrir `/docs/memoria` e ver em um só lugar os 3 tipos de memória do projeto (primer, project, Claude)
**Para** entender o contexto completo sem garimpar 3 diretórios.

**DoD:**
- [x] Três colunas/seções: Primer (`CLAUDE.md`, `AGENTS.md` na raiz), Project (`memory/`), Claude (`~/.claude/projects/.../memory/`)
- [x] Árvore navegável com contagem por seção
- [x] Click em arquivo → preview renderizado (`/docs/memoria/file?key=project::sessions/2026-04-24.md`)
- [x] Whitelist de extensões (`md`, `txt`, `json`, `yaml`, `yml`) — bloqueia binário
- [x] Fallback gracioso quando diretório Claude não existe (usuário nunca escreveu memória)

**Implementado em:** `MemoriaController.php` + `Services/MemoryReader.php` + `resources/js/Pages/DocVault/Memoria.tsx`.

### US-DOCVAULT-007 · Migrar módulo plano → pasta

**Como** dev documentando módulos legados
**Quero** rodar `php artisan docvault:migrate-module NomeModulo`
**Para** promover um `{Modulo}.md` plano para a estrutura pasta de 4 arquivos sem reescrever tudo à mão.

**DoD:**
- [x] Comando detecta seções do .md plano e distribui entre `README`, `ARCHITECTURE`, `SPEC`, `CHANGELOG`
- [x] Preserva frontmatter YAML no novo `README.md`
- [x] Cria backup `{Modulo}.md.bak` antes de tocar no original
- [x] Cria pasta `adr/` vazia pronta pra receber decisões
- [x] Idempotente: rodar duas vezes não duplica conteúdo
- [x] Testado em PontoWr2 (12 stories + 6 regras) e Essentials (1 story + 7 regras)

**Implementado em:** `Console/Commands/MigrateModuleCommand.php`.

### US-DOCVAULT-008 · Declarar stories/regras na tela via `@docvault`

**Como** dev que acaba de criar uma tela React
**Quero** anotar no topo do `.tsx` quais stories/rules ela atende
**Para** que o sistema audite automaticamente se toda story tem tela e toda tela tem story.

**DoD:**
- [x] Parser lê blocos `// @docvault` no topo de `.tsx` (ver `SyncPagesCommand`)
- [x] Suporta campos `tela`, `module`, `stories`, `rules`, `adrs`, `tests` (arrays CSV)
- [x] `docvault:sync-pages` popula `docs_pages` com 1 registro por tela anotada
- [x] `DocValidator::STORY_ORPHAN` e `PAGE_NO_META` usam essa tabela
- [x] Validação rejeita `@docvault` malformado com mensagem clara (ver RUNBOOK)

**Implementado em:** `Console/Commands/SyncPagesCommand.php` + `Entities/DocPage.php`.

### US-DOCVAULT-009 · Auditar qualidade de um módulo

**Como** tech lead
**Quero** rodar `php artisan docvault:audit-module DocVault --save`
**Para** receber um score 0-100 + lista de findings acionáveis de qualidade documental.

**DoD:**
- [x] 15 checks (C01-C15) cobrindo frontmatter, README, ARCHITECTURE, SPEC, ADRs mínimos, páginas anotadas, glossary, runbook, diagrams, contracts, status vs modules_statuses.json, placeholders
- [x] Score 0-100 ponderado + classificação (A/B/C/D/F)
- [x] Flag `--save` grava `memory/requisitos/{Modulo}/audits/YYYY-MM-DD.md`
- [x] Output legível pra humano (tabela markdown) + JSON pra CI
- [x] DocVault audita a si mesmo sem loop

**Implementado em:** `Console/Commands/AuditModuleCommand.php` + `Services/ModuleAuditor.php`.

### US-DOCVAULT-010 · Validar integridade global da documentação

**Como** time de qualidade / CI
**Quero** rodar `php artisan docvault:validate` e ter um health_score 0-100
**Para** saber o estado agregado do DocVault sem auditar módulo por módulo.

**DoD:**
- [x] 5 checks (definidos em ADR 0005): STORY_ORPHAN, RULE_NO_TEST, ADR_DANGLING, PAGE_NO_META, PAGE_STALE (≥30 dias)
- [x] Flag `--module=Nome` limita o escopo
- [x] Resultado persistido em `docs_validation_runs` (histórico pra gráfico de tendência)
- [x] Exit code ≠ 0 quando score abaixo de threshold (usável em pre-commit/CI)
- [x] Hooks de pre-commit opcionais via `docvault:install-hooks`

**Implementado em:** `Console/Commands/ValidateCommand.php` + `Services/DocValidator.php` + `Console/Commands/InstallHooksCommand.php` + `Entities/DocValidationRun.php`.

### US-DOCVAULT-011 · Gerar stub de teste a partir de regra Gherkin

**Como** dev que acabou de escrever uma regra Gherkin em SPEC.md
**Quero** rodar `php artisan docvault:gen-test R-DOCVAULT-003`
**Para** não escrever o boilerplate de Pest/PHPUnit à mão.

**DoD:**
- [x] Lê a regra pelo ID (ex.: `R-DOCVAULT-003`) em `memory/requisitos/*/SPEC.md`
- [x] Gera arquivo de teste com skeleton Given/When/Then em `Modules/{Modulo}/Tests/Feature/`
- [x] Preenche "Testado em:" no SPEC.md apontando para o teste criado
- [x] Marca o teste como `@todo` — não roda verde falsamente

**Implementado em:** `Console/Commands/GenTestCommand.php`.

## 3. Regras

### R-DOCVAULT-001 · Evidência só vira requisito depois de triada

```gherkin
Dado que uma evidência tem status = "pending"
Quando o usuário não a classificou ainda
Então ela não aparece em docs_requirements
E não é aplicada automaticamente no SPEC.md
```

**Por quê**: ruído não vira documentação. Requer curadoria humana.

**Testado em:** `Modules/DocVault/Tests/Feature/InboxTest::test_rule_holds` (stub gerado por docvault:gen-test — implementar)

### R-DOCVAULT-002 · Uma fonte pode gerar múltiplas evidências

```gherkin
Dado um arquivo de chat log com 500 linhas
Quando o usuário faz ingest desse arquivo como DocSource
Então pode criar N DocEvidences apontando pra diferentes trechos
E cada evidência tem seu próprio status/triagem
```

**Testado em:** `Modules/DocVault/Tests/Feature/InboxTest::test_rule_holds` (stub gerado por docvault:gen-test — implementar)

### R-DOCVAULT-003 · business_id obrigatório

```gherkin
Dado um usuário autenticado em business X
Quando ele cria/edita/lista evidências
Então só vê evidências de business X
E tentativa de acessar business Y retorna 404
```

**Por quê**: multi-tenancy (padrão UltimatePOS).

**Testado em:** `Modules/DocVault/Tests/Feature/InboxTest::test_rule_holds` (stub gerado por docvault:gen-test — implementar)

### R-DOCVAULT-004 · Reader faz fallback de formato

```gherkin
Dado que existe memory/requisitos/{Modulo}/SPEC.md (formato pasta)
Quando o usuário abre /docs/modulos/{Modulo}
Então o reader usa SPEC.md + README/ARCHITECTURE/CHANGELOG
E ignora memory/requisitos/{Modulo}.md se existir

Dado que existe só memory/requisitos/{Modulo}.md (formato plano)
Quando o usuário abre /docs/modulos/{Modulo}
Então o reader usa o arquivo plano
E o módulo funciona normalmente com 3 tabs
```

**Por quê**: migração gradual — não migrar os 29 de uma vez.

**Testado em:** `Modules/DocVault/Tests/Feature/InboxTest::test_rule_holds` (stub gerado por docvault:gen-test — implementar)

### R-DOCVAULT-005 · IA desligada por padrão

```gherkin
Dado que DOCVAULT_AI_ENABLED não está setado
Quando o usuário ingere uma evidência
Então o sistema NÃO chama OpenAI
E deixa kind/module_target pra ser preenchido manualmente
```

**Por quê**: evita custo/latência/alucinação em ambiente sem validação.

**Testado em:** `Modules/DocVault/Tests/Feature/InboxTest::test_rule_holds` (stub gerado por docvault:gen-test — implementar)
