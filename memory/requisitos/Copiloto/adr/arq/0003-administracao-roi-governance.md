# ADR ARQ-0003 — Camada administrativa, governança e ROI

**Data:** 2026-04-26
**Status:** Proposta (aguarda aval do Wagner)
**Escopo:** Módulo Copiloto
**Autor/a:** Claude (decisão registrada com aval pendente em conversa Cowork 2026-04-26)
**Relacionado:** [ADR ARQ-0001 — Tenancy híbrida](0001-tenancy-hibrida.md), [ADR 0020 — Grupo econômico no Officeimpresso](../../../../decisions/0020-officeimpresso-grupo-economico.md)

---

## Contexto

O módulo Copiloto está em estado de **MVP de chat funcional** (deploy 2026-04-26):

- Cada usuário cria suas conversas, manda mensagens, recebe sugestões de meta
- Isolamento por `business_id` via global scope; isolamento por `user_id` via guard no controller
- Mensagens append-only (`copiloto_mensagens` sem `updated_at`)
- Tokens (`tokens_in`/`tokens_out`) gravados por mensagem mas nunca exibidos

Wagner perguntou em 2026-04-26: **"meu interesse é o ROI"** + **"como é a parte administrativa das memórias geradas?"**

A análise do código revelou que **a camada administrativa praticamente não existe**:

| Capacidade | Estado |
|---|---|
| Isolamento por business | ✅ Scope global (forte) |
| Isolamento por usuário | ⚠️ Apenas guards no controller (frágil — depende de cada endpoint lembrar) |
| Visão admin do business sobre conversas dos seus usuários | ❌ Não existe |
| Apagar conversa | ❌ Não existe rota DELETE |
| Audit log (quem leu o quê quando) | ❌ `spatie/laravel-activitylog` está no composer mas não é usado |
| Dashboard de custo de IA | ❌ Dados existem (tokens), UI não existe |
| Limite de orçamento (mensal/diário) | ❌ Não existe |
| LGPD — right to delete / export | ❌ Não existe |
| Insights agregados (perguntas frequentes) | ❌ Não existe |
| Visibilidade cross-business pra grupo econômico | ❌ Não existe (ADR 0020 trata grupo no Officeimpresso, não no Copiloto) |

Sem essa camada, o dono de PME **não tem ROI mensurável**: paga conta de IA sem ver o gasto, não consegue auditar o que os funcionários perguntam, não consegue extrair valor de inteligência agregada das conversas, e não atende LGPD se pintar uma fiscalização.

## Decisão proposta

Criar uma **camada administrativa em 3 ondas**, priorizando o que destrava ROI imediato (custo + visibilidade) antes do que destrava compliance (LGPD + audit) antes do que destrava insights (agregado + grupo).

### Onda 1 — ROI direto (P0, ~2 sprints)

**1.1 Dashboard de Custo IA** (`/copiloto/admin/custos`)

- Card "Esse mês" com R$ gasto, # mensagens, # tokens, # usuários ativos
- Tabela por usuário: nome, # conversas, # mensagens, tokens consumidos, R$ aproximado
- Gráfico: gasto diário/semanal últimos 90 dias
- Permissão `copiloto.admin.custos.view` (independente de `copiloto.superadmin`)
- Cálculo: `tokens × preço_por_token` configurado em `config/copiloto.php` (`ai.pricing.gpt-4o-mini.input` e `output` em USD/1k tokens, com câmbio configurável)

**1.2 Controle de orçamento** (`/copiloto/admin/orcamento`)

- Tabela `copiloto_orcamentos` com `business_id`, `tipo` (`mensal_business` / `diario_user`), `limite_tokens`, `limite_brl`, `acao_estouro` (`bloquear` / `alertar` / `degradar_pra_modelo_barato`)
- Middleware `EnforceOrcamento` checka antes de chamar IA → se estourou, comportamento conforme `acao_estouro`
- Plano (Essencial/Profissional/Enterprise) define limite default que admin do business pode reduzir mas não aumentar acima do plano
- Admin recebe notificação quando atinge 80% / 100% do limite

**1.3 Visão admin das conversas** (`/copiloto/admin/conversas`)

- Lista todas conversas do business (não só do usuário logado) com filtros (usuário, período, palavras-chave em `content`)
- Drill-down: abrir conversa em modo **read-only** (sem campo de input)
- Permissão `copiloto.admin.conversas.view` (separada da `copiloto.superadmin` que é cross-business)
- **Transparência:** quando admin abre conversa de outro usuário, gera mensagem `system` na conversa (`"Esta conversa foi visualizada por {admin_nome} em {timestamp}"`) — usuário vê na próxima vez que abrir. Compliance + confiança.

### Onda 2 — Compliance e auditoria (P1, ~1 sprint)

**2.1 Audit log** (ativar `spatie/laravel-activitylog`)

- Logar: visualização de conversa por terceiro (admin), arquivamento/desarquivamento, alteração de orçamento, exclusão
- Tabela `activity_log` (já existe se Spatie estiver instalado)
- Tela `/copiloto/admin/auditoria` com filtros (ação, usuário, período)
- Retenção configurável (default 365 dias)

**2.2 LGPD — direitos do titular** (`/copiloto/perfil/dados`)

- Botão **"Baixar minhas conversas"** → JSON + PDF com todas mensagens do usuário logado
- Botão **"Apagar minhas conversas"** → 2-step confirm + soft-delete (`deleted_at` em `copiloto_conversas` + cascade lógico em mensagens) + scheduling de hard-delete em 30 dias (job `HardDeleteConversaJob` com `delay(30 days)`)
- Tela admin pode reverter soft-delete dentro da janela de 30 dias
- Política de retenção configurável: `config('copiloto.retencao.conversas_arquivadas_dias') = 365` → cron diário arquiva conversas inativas há 12 meses; `retencao.conversas_apagadas_dias = 30` → hard-delete

**2.3 Anonymização opcional** (alternativa ao delete)

- Botão "Anonimizar" → substitui `user_id` por `null` + apaga PII detectada via regex (email/CPF/telefone) no `content`
- Mantém estatística (tokens, contagem) sem expor identidade — útil pra dataset de fine-tuning futuro

### Onda 3 — Insights e cross-business (P1-P2, ~2 sprints)

**3.1 Insights agregados** (`/copiloto/admin/insights`)

- "Top 10 tópicos perguntados esse mês" (clusterização semântica via embeddings — usa o mesmo provider IA já configurado)
- "Taxa de aceite de sugestão" — % das `copiloto_sugestoes` com `status='escolhida'` vs `'rejeitada'`
- "Heatmap de uso" (dia × hora) pra dimensionar peak hours
- "Usuários mais ativos" e "usuários ociosos" (conversaram nos últimos 30 dias?)
- Roda em background (job `GerarInsightsJob` semanal) e cacheia resultado pra não estourar custo IA

**3.2 Tags em conversa** (UI-driven taxonomy)

- Coluna `copiloto_conversas.tags` (JSON array)
- Usuário marca conversa como `["vendas", "operacional", "estrategico"]` (chips clicáveis)
- Permite filtros e relatórios temáticos

**3.3 Visibilidade cross-business pra grupo econômico** (depende de ADR 0020 implementado)

Quando ADR 0020 (`business.matriz_id`) estiver implementado:

- Permissão `copiloto.grupo.viewer` amplia o scope de `where business_id = X` pra `whereIn business_id IN (X, X.filiais)`
- Dashboard executivo do grupo: rola sobre todos os businesses do grupo (custo IA agregado, metas consolidadas, top insights cross-loja)
- Modificar `ScopeByBusiness` pra reconhecer esse caso:

```php
if ($user->can('copiloto.grupo.viewer')) {
    $businessIds = Business::where('id', $businessId)
        ->orWhere('matriz_id', $businessId)
        ->pluck('id');
    $builder->whereIn("...business_id", $businessIds);
    return;
}
```

Isso destrava o caso do dono que tem ROTA LIVRE (matriz) + outras lojas (filiais) e quer comparar/agregar custo e insights entre elas.

## Recomendações de usabilidade

1. **Telas admin separadas das do usuário comum** — subnav "Administração" só visível pra quem tem qualquer permissão `copiloto.admin.*`. Não polui a UI dos vendedores.

2. **Card de status do orçamento no dashboard principal do Copiloto** — vermelho/amarelo/verde no canto superior. Visível pra admin; usuário comum vê só "Você usou X% da sua cota diária".

3. **Botões destrutivos com 2-step confirm + delay** — apagar conversa pede confirm + dá 24h pra desfazer (anti-impulso, anti-acidente, anti-LGPD).

4. **Toggle "Permissão de visibilidade" por conversa** — `privada` (só usuário) vs `compartilhada com admin do business`. Default = `privada`. Usuário decide explicitamente o que quer expor.

5. **Notificação no chat quando admin lê** — mensagem `system` no chat ("Esta conversa foi visualizada por {admin}"). Transparência cria confiança e desinibição (paradoxalmente: gente fala mais quando sabe que pode ser auditada conforme regra clara, não quando suspeita de espionagem secreta).

6. **Export "Baixar conversas" gera PDF visualmente bonito** — cabeçalho com logo da empresa, paginação, índice. Vira artefato profissional, não dump técnico.

7. **Configuração de orçamento como wizard** — primeiro setup quando admin habilita o módulo: "Quanto você quer gastar com IA por mês? R$ ___" + sugestão baseada no plano. Evita admin se assustar com conta no fim do mês.

8. **Insights aparecem como notificação pró-ativa** — toda segunda-feira de manhã, admin recebe email/notif: "Esse fim de semana sua equipe perguntou X vezes sobre Y. Quer criar um FAQ?". Empurra valor pra cara do usuário, não fica esperando ele descobrir a tela.

9. **Tag de "qualidade" na resposta da IA** (usuário marca 👍/👎) — alimenta dataset de melhoria + dá ao admin métrica de "satisfação com IA" nos insights.

10. **Botão "Pergunta privada" no input** — desliga o salvamento daquela mensagem específica (`role='user'` mas `content='[REDACTED]'`, só `tokens` salvos pra contabilidade). Pra perguntas estratégicas/sensíveis. Custa caro em LGPD se a IA hospedeira logar — então combinar com prompt privacy mode.

## Impacto

| Componente | Mudança |
|---|---|
| `copiloto_conversas` | + `deleted_at` (soft-delete), + `tags` JSON, + `visibilidade` enum |
| `copiloto_mensagens` | + `redacted` boolean (pra perguntas privadas), + `feedback` enum (`positivo`/`negativo`/null) |
| Nova `copiloto_orcamentos` | id, business_id, tipo, limite_tokens, limite_brl, acao_estouro |
| Nova `copiloto_insights_cache` | tipo, business_id, periodo, resultado JSON, gerado_em |
| Novos middlewares | `EnforceOrcamento`, `LogAdminViewConversa` |
| Novos jobs | `HardDeleteConversaJob`, `GerarInsightsJob`, `ArquivarConversasInativasJob` |
| Novas permissões | `copiloto.admin.custos.view`, `.admin.conversas.view`, `.admin.orcamento.manage`, `.admin.insights.view`, `.grupo.viewer` |
| Novos controllers | `Admin/CustosController`, `Admin/ConversasController`, `Admin/OrcamentoController`, `Admin/AuditoriaController`, `Admin/InsightsController`, `PerfilDadosController` |
| 7 novas telas Inertia | sob `/copiloto/admin/*` + `/copiloto/perfil/dados` |

## Alternativas consideradas

| Opção | Por que rejeitada |
|---|---|
| Não fazer nada (deixa MVP como está) | Dono nunca vê ROI → módulo morre comercialmente |
| Fazer só o dashboard de custo, esquecer LGPD | LGPD é regulatório; uma multa paga 5 anos do desenvolvimento dessa camada |
| Aderir a uma plataforma externa de governance (ex.: Langfuse) | Adiciona dependência cara, perde controle do dado, usuários PME BR não pagam por isso |
| Implementar tudo na onda 1 | 6+ sprints sem entrega → vira espera longa demais. Faseamento P0/P1/P2 entrega valor incremental |

## Conexão com ADRs existentes

- **ARQ-0001 (tenancy híbrida):** essa proposta refina o scope global pra suportar grupo econômico (cross-business controlado). Não invalida ARQ-0001, complementa.
- **0020 (grupo econômico Officeimpresso):** reusa a coluna `business.matriz_id` proposta lá. Implementação de Onda 3.3 fica bloqueada até ADR 0020 ser implementado.
- **TECH-0002 (adapter IA):** dashboard de custo lê `tokens_in`/`tokens_out` que o adapter já preenche. Sem mudança no adapter.

## Próximos passos

1. **Wagner valida priorização** das 3 ondas (pode reordenar P0/P1/P2 conforme necessidade comercial)
2. **Estimar esforço** com base em complexidade real (essa ADR estima 5 sprints total — pode estar otimista)
3. **Spec de cada onda** vira issue/sprint individual em `memory/requisitos/Copiloto/SPEC.md` (adicionar US-COPI-NNN)
4. **Implementar Onda 1** primeiro — é a que destrava narrativa comercial ("você vê o que gasta + audita o que sua equipe pergunta") e é o que aparece em case study/screencast
