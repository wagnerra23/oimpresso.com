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

**Implementado em:** [TODO — Fase 3]

## 3. Regras

### R-DOCVAULT-001 · Evidência só vira requisito depois de triada

```gherkin
Dado que uma evidência tem status = "pending"
Quando o usuário não a classificou ainda
Então ela não aparece em docs_requirements
E não é aplicada automaticamente no SPEC.md
```

**Por quê**: ruído não vira documentação. Requer curadoria humana.

**Testado em:** [TODO]

### R-DOCVAULT-002 · Uma fonte pode gerar múltiplas evidências

```gherkin
Dado um arquivo de chat log com 500 linhas
Quando o usuário faz ingest desse arquivo como DocSource
Então pode criar N DocEvidences apontando pra diferentes trechos
E cada evidência tem seu próprio status/triagem
```

**Testado em:** [TODO]

### R-DOCVAULT-003 · business_id obrigatório

```gherkin
Dado um usuário autenticado em business X
Quando ele cria/edita/lista evidências
Então só vê evidências de business X
E tentativa de acessar business Y retorna 404
```

**Por quê**: multi-tenancy (padrão UltimatePOS).

**Testado em:** [TODO]

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

**Testado em:** [TODO]

### R-DOCVAULT-005 · IA desligada por padrão

```gherkin
Dado que DOCVAULT_AI_ENABLED não está setado
Quando o usuário ingere uma evidência
Então o sistema NÃO chama OpenAI
E deixa kind/module_target pra ser preenchido manualmente
```

**Por quê**: evita custo/latência/alucinação em ambiente sem validação.

**Testado em:** [TODO]
