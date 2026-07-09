# HOW_TO_ASK_CLAUDE.md — Estado da Arte 2026 sobre Prompting de Coding Agents

> Guia prático em PT-BR para Wagner [W] usar Claude Code (e agentes IA de coding em geral) com alta performance, usabilidade e segurança no projeto Oimpresso ERP.
> Última atualização: 2026-05-04. Baseado em: Anthropic Engineering Blog (Claude Code best practices, set/2025), Anthropic Cookbook, AGENTS.md spec (sourcegraph/openai 2025), Cognition AI "Don't Build Multi-Agents" (jun/2025), Devin/Cursor/Aider postmortems, papers ReAct (Yao et al), Reflexion (Shinn et al), Plan-and-Solve (Wang et al), SWE-bench Verified leaderboard 2025-2026.

---

## 0. Princípios canônicos (a regra de ouro de 2026)

A literatura 2025-2026 convergiu em **5 princípios** que valem mais que qualquer template:

1. **Context engineering > prompt engineering.** O que importa não é a frase mágica — é o que está na janela de contexto. Curar contexto (ADRs relevantes, file:line exatos, exemplos de imitação) rende 3-5× mais que reescrever o pedido.
   *Fonte: Anthropic "Effective context engineering for AI agents" (set/2025); Lance Martin (LangChain) "Context Engineering" (jun/2025).*

2. **Specs > prompts.** Em vez de pedir "faz X", escreva uma spec curta (3-10 linhas) com: objetivo, restrições, critério de aceite, arquivo-modelo a imitar. Spec vira contrato verificável.
   *Fonte: Sean Grove (OpenAI) "The New Code" (out/2025); GitHub Spec Kit.*

3. **Plan → Execute → Verify é o loop, não a opção.** Para qualquer tarefa não-trivial: força um plano antes (Plan mode), executa, **valida com teste/lint/typecheck**, reporta. Pular verify é o erro #1.
   *Fonte: Anthropic Claude Code Best Practices (set/2025); Plan-and-Solve (Wang 2023).*

4. **Single-thread > multi-agent (na prática solo dev).** Cognition AI publicou em jun/2025 que multi-agent paralelo gera conflitos de contexto e custa caro. Para Wagner solo, **um Claude principal + sub-agentes pontuais (Task tool) pra pesquisa read-only** é o ótimo.
   *Fonte: Walden Yan (Cognition) "Don't Build Multi-Agents" (jun/2025).*

5. **Você é o reviewer, não o digitador.** O agente escreve; você revisa diff, roda teste, aprova merge. O dia em que você está corrigindo vírgula no código que ele gerou, o fluxo virou anti-padrão.
   *Fonte: Steve Yegge "The Death of the Stubborn Developer" (2025); Simon Willison weblog "vibe coding" (mar/2025).*

---

## 1. Estrutura de pedido para máxima performance

### 1.1 Anatomia de um bom prompt (template SPEC-CITE-VERIFY)

```
[OBJETIVO]    1 frase. Outcome verificável.
[CONTEXTO]   ADRs relevantes (slug ou path), file:line de imitação.
[RESTRIÇÕES] O que NÃO pode mudar. Stack canônica. Lei (LGPD/CLT).
[ENTREGA]    Critério de aceite mensurável (teste passa, rota responde 200).
[VERIFY]     Como validar (Pest filtro, curl, grep).
```

**Exemplo ruim** (típico, custa 40k tokens e gera retrabalho):
> "cria um endpoint pra listar marcações do colaborador"

**Exemplo bom** (custa 8k tokens, sai certo na primeira):
> [OBJETIVO] Endpoint GET /api/ponto/marcacoes que lista marcações do colaborador autenticado, paginadas (50/pg).
> [CONTEXTO] Imitar `Modules/Jana/Http/Controllers/MetasController.php` (controller real de referência). ADR 0011 (módulo de referência), ADR 0035 (stack canônica).
> [RESTRIÇÕES] business_id scope obrigatório. Append-only (read-only no controller). PT-BR nas mensagens.
> [ENTREGA] Pest test em `Modules/Ponto/Tests/Feature/Api/MarcacoesIndexTest.php` (novo — os testes vivos do módulo estão em `Modules/Ponto/Tests/`, ex. `CrossTenantMarcacaoTest`) cobrindo (a) auth, (b) scope multi-tenant, (c) paginação.
> [VERIFY] no CT 100 (⛔ NUNCA local — hook bloqueia): `tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging php artisan test --filter=MarcacoesIndexTest"` verde.

### 1.2 Decompor tarefas grandes — regra do "1 PR / 1 sessão / 1 outcome"

| Tamanho | Como prompar |
|---|---|
| **XS** (< 30 min, 1 arquivo) | Direct prompt, sem Plan mode. |
| **S** (1-3 arquivos, 1h) | Spec curta + cite arquivo modelo. Sem Plan. |
| **M** (módulo novo / refactor) | **Plan mode** (Shift+Tab×2). Aprove plano, depois execute. |
| **L** (épico, 3+ PRs) | Quebra em **stories** primeiro. Cria US-XXX-NNN no TaskRegistry (`tasks-create`). Cada story vira 1 sessão. |
| **XL** (cycle inteiro) | Não promptar — escrever ADR primeiro, depois quebrar em L. |

**Regra empírica** (Anthropic dev rel set/2025): se o plano que Claude gera tem >7 passos, está grande demais. Quebra.

### 1.3 Quando usar Plan mode vs execução direta

**Plan mode (Shift+Tab×2) — use quando:**
- Mexe em >3 arquivos
- Toca código compartilhado (core UltimatePOS, App\View\Helpers)
- Você não tem certeza da abordagem
- Migration / mudança de schema
- Refactor de >50 linhas

**Execução direta — use quando:**
- Bug óbvio com fix óbvio
- Teste novo de função existente
- Doc/ADR/comentário
- Você já sabe o diff exato

### 1.4 /compact /clear /continuar — quando cada um

| Comando | Quando | Efeito |
|---|---|---|
| `/compact` | Após feature mergeada, mas você vai continuar no mesmo escopo. | Resume histórico, mantém decisões. ~70% redução tokens. |
| `/clear` | Trocou de módulo / escopo (ex.: terminou Copiloto, vai mexer em Ponto). | Zera contexto. Começa do zero com CLAUDE.md. |
| `/continuar` | Retomar sessão amanhã / depois de break. | Lê CURRENT.md + handoff + último session log. Não re-explora repo. |

**Anti-padrão observado:** rodar `/compact` 3-4× na mesma sessão. Sintoma de que a sessão deveria ter virado 2 sessões. Próxima vez: `/clear` e nova sessão.

### 1.5 Quanto contexto passar (regra dos 3)

Estudo interno Anthropic (Claude 4 release notes, 2025) mostrou que performance cai após **3 ADRs + 3 arquivos modelo + 3 file:line de bug** num único prompt. Mais que isso, o agente perde foco.

**Receita:**
- Cite **ADR por slug**, não cole o conteúdo (Claude busca via MCP `decisions-fetch` se precisar).
- File path **com line number** (`app/Http/Controllers/X.php:142`) — Claude vai ler só a janela.
- Para "imitar este módulo", aponte **1 arquivo modelo**, não a pasta inteira.

### 1.6 Templates por tipo de tarefa

#### Bug fix
```
[BUG] Sintoma observável. Reproduce: <passos>. Esperado vs atual.
[CONTEXTO] Stack trace ou log. File:line suspeito.
[FIX] Não mude API pública. Adicionar regression test.
[VERIFY] Teste novo verde + suite módulo verde.
```

#### Feature
```
[USER STORY] Como <persona>, quero <ação>, pra <valor>.
[ADR] Decisão arquitetural relevante (slug).
[MODELO] Arquivo a imitar (path:line).
[ENTREGA] Lista de arquivos a criar + teste Pest.
[FORA DE ESCOPO] O que NÃO faz (importante!).
```

#### Refactor
```
[MOTIVO] Por que refatorar (1 frase).
[INVARIANTE] O que tem que continuar funcionando (citar testes existentes).
[STRATEGY] Strangler / inline / extract. Estilo de pequenos commits.
[VERIFY] Suite verde antes E depois. Diff de comportamento = zero.
```

#### Debug ("não sei o que está errado")
```
[SINTOMA] O que vejo.
[HIPÓTESES] 2-3 que já descartei.
[PEDIDO] Investigue read-only primeiro. Reporte achados ANTES de mudar código.
```
**Crítico:** "**read-only primeiro**" evita Claude começar a mexer antes de entender.

#### Eval (avaliar/comparar abordagens)
```
[QUESTÃO] X vs Y, pra nosso contexto Z.
[ENTREGA] Tabela trade-offs + recomendação fundamentada + ADR draft em memory/decisions/.
[NÃO FAZER] Não codar. Só pesquisar e decidir.
```

---

## 2. Usabilidade — fluxo de iteração eficiente

### 2.1 Feedback corretivo sem perder trabalho

**Anti-padrão:** "isso ficou ruim, refaz tudo." → Claude joga fora código bom junto.

**Padrão correto** ("surgical correction"):
> "Mantém o controller e o teste, mas no service: linha 42 está chamando o repo dentro do loop — extrai pra fora. Resto fica."

Cite **o que manter** explicitamente. Claude tende a respeitar.

### 2.2 Quando interromper vs deixar tentar

**Interrompa imediatamente (Esc):**
- Começou a mexer em arquivo fora do escopo
- Está rodando `composer update` ou `npm install` sem você ter pedido
- Vai tocar produção (Hostinger SSH) sem ter dito que ia
- 3+ tentativas no mesmo erro com mesma abordagem

**Deixe tentar:**
- Está debugando teste que falha (deixa ele iterar 2-3×)
- Está lendo arquivos pra entender contexto
- Pediu Plan mode e está montando plano (mesmo que demore)

### 2.3 Negociar mudança de escopo

Quando você muda de ideia no meio:
> "Pausa. Ignora o que disse sobre X, agora quero Y. O que você já fez (arquivo A linha 42) ainda vale? Se sim, mantém. Se não, reverte e refaz."

Pedir Claude pra **avaliar o que vale aproveitar** evita rebuild do zero.

### 2.4 Forçar "pergunta antes de agir" em alto impacto

Adicione ao prompt:
> "Se a abordagem exigir mudança em código compartilhado (core UltimatePOS, App\View\Helpers, migration de schema), **pare e pergunte** antes de aplicar."

Isso é mais forte que "tenha cuidado". Claude trata como guardrail.

### 2.5 Skills e slash commands — quando criar

**Crie uma Skill quando:**
- O padrão se repete em ≥3 tarefas (ex.: criar módulo Laravel, scope multi-tenant)
- Tem checklist mensurável (ex.: "tem DataController? user_permissions? topnav?")
- Pode ser auto-ativada por palavra-chave no description

**Crie um Slash command quando:**
- É um workflow que VOCÊ invoca explicitamente (ex.: `/continuar`, `/ultrareview`)
- Tem template de input fixo

**Não crie quando:**
- É uma tarefa única
- Você não consegue articular o trigger em 1 frase

---

## 3. Segurança — guardrails para produção

### 3.1 Bloquear destrutivos via hooks

Claude Code suporta `PreToolUse` hooks em `.claude/settings.json`. Use pra **bloquear** comandos destrutivos:

```json
{
  "hooks": {
    "PreToolUse": [
      {
        "matcher": "Bash",
        "hooks": [{"type": "command", "command": ".claude/hooks/block-destructive.ps1"}]
      }
    ]
  }
}
```

O hook checa o comando e retorna exit 2 (bloqueia) se vê: `rm -rf`, `git push --force`, `DROP TABLE`, `composer update` (sem `--lock`), `git reset --hard origin/`, etc.

Wagner já tem esse padrão validado: `block-automem.ps1` bloqueia Write em auto-mem (CLAUDE.md §6).

### 3.2 "Ask before act" para deploy/push/migration

No CLAUDE.md, declare explicitamente. Wagner já tem (§4 "O que NÃO fazer"). Reforçar:
- `git push origin main` → SEMPRE pede confirmação no CLI
- `php artisan migrate` em prod → SEMPRE para e mostra SQL primeiro
- `composer install` em prod → mostrar lock diff antes

Padrão recomendado pelo Anthropic Cookbook (set/2025): **declare em CLAUDE.md a lista de comandos que exigem confirmação humana, e separe-os por ambiente**. Wagner já faz isso bem.

### 3.3 Não quebrar prod via código compartilhado

**Regra dura:** mudança em `app/`, `resources/views/layouts/`, `App\View\Helpers\` exige:
1. Plan mode obrigatório
2. Lista de chamadores afetados (`Grep`)
3. Teste de regressão
4. PR explícita (não push direto)

Wagner pode formalizar isso como Skill `shared-code-touch` que ativa quando o path matchar `app/View/Helpers/**` ou `app/Utils/**`. (Parcialmente coberto hoje pela rule path-scoped `.claude/rules/reuse-check.md` — anti-duplicação em Components/Lib/Hooks + Services/Entities/Models.)

### 3.4 LGPD — PII em prompts e commits

**Anti-padrão observado em projetos solo:** colar log com CPF real no prompt pra Claude debugar.

**Padrão correto:**
- Hook `PreToolUse` em `Bash` que escaneia `git diff --staged` por regex CPF/CNPJ/email/cartão antes de `git commit`. Bloqueia se achar.
- Hook em `Write/Edit` que avisa se o conteúdo tem `\d{3}\.\d{3}\.\d{3}-\d{2}`.
- Nas instruções (CLAUDE.md): "PII real NUNCA em commit. Substitui por `[REDACTED]` ou fixture fake. Se precisar de CPF de exemplo, use `123.456.789-09`."

Pra prompts: nunca cole log de prod cru. Use `sed` antes de colar:
```bash
ssh prod "tail log" | sed -E 's/[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}/[CPF]/g'
```

### 3.5 Code review automático antes de merge

**Padrão `/ultrareview`** (emergente em 2025-2026):

Slash command que pede ao próprio Claude (ou sub-agent via Task tool) pra revisar o diff como **adversário**:

```
/ultrareview
→ Lê o diff staged.
→ Roleplay: "você é tech lead cético. Encontre 3 bugs, 2 race conditions, 1 LGPD issue."
→ Reporta. Você decide se merge ou fix.
```

Pesquisa: **Reflexion** (Shinn et al, NeurIPS 2023) e **Self-Refine** (Madaan et al, 2023) mostraram que LLM revisar próprio output melhora qualidade em 15-30% sem custo de novo modelo.

**Padrão `adr-review`** (Wagner-específico):
Antes de commit que toca decisão arquitetural, sub-agent valida: "este diff é coerente com ADR XXXX? Cite a seção."

---

## 4. Padrões anti-falha conhecidos

### 4.1 "Don't tell, show" — exemplos > descrições

Ruim: "use o padrão de Service+Repository do projeto"
Bom: "imita exatamente `Modules/Jana/Services/AlertaService.php` — mesmo construtor, mesmo padrão de retorno"

Few-shot prompting bate zero-shot em ~25% nas tasks SWE-bench (Anthropic eval, 2025).

### 4.2 "Show me, don't tell" — diff antes de aplicar

Em alto risco, peça:
> "Mostre o diff em markdown ANTES de aplicar. Vou aprovar 1 a 1."

Custa 1 round-trip extra mas evita rollback caro.

### 4.3 Roleplay de revisor / red team

> "Termine a feature, depois roleplay 'tech lead cético' e ache 3 problemas no que você acabou de fazer."

Funciona porque Claude tem viés a aprovar próprio output em modo "writer", e crítico em modo "reviewer". Trocar de modo elicia bugs.

### 4.4 Test-first prompting

> "Escreve o teste Pest PRIMEIRO. Roda. Confirma que falha pelo motivo certo. Depois implementa."

Padrão TDD adaptado. Reduz "código que parece certo mas não funciona" — falha #1 de coding agents (SWE-bench analysis 2025).

### 4.5 Citar ADR / source — grounding

> "Antes de implementar, busca via MCP `decisions-search query:'observabilidade'` e cita qual ADR fundamenta a escolha. Se não houver ADR, propõe uma como draft."

Força grounding em docs canônicos. Anti-alucinação. Wagner já tem MCP server pra isso.

### 4.6 "Bouncer pattern"

Para tarefas onde você não sabe se vale começar:
> "Antes de codar, em 5 bullets responde: vale fazer? riscos? alternativas? estimativa? pré-requisitos."

Se a resposta for fraca, o pedido estava errado — não o agente.

---

## 5. Frameworks/metodologias modernos

### 5.1 ReAct (Yao et al, 2022, ainda relevante 2026)
Reason + Act intercalados. É o **default** do Claude Code: pensa, age via tool, observa, pensa de novo. Você não precisa pedir — vem grátis.

### 5.2 Plan-and-Execute / Plan-and-Solve (Wang et al, 2023)
Plano completo antes, execução depois. **É o que Plan mode faz.** Bate ReAct puro em tarefas multi-step (~10-15% SWE-bench).

### 5.3 Reflexion (Shinn et al, 2023)
Após falhar, agente escreve "lição aprendida" em memória, tenta de novo. Implementação caseira: depois de bug fix, peça "registra em ADR ou auto-mem o que aprendeu pra não repetir."

### 5.4 SPECTRA / SPACE (frameworks 2025)
Siglas comerciais (Cursor, Continue.dev). Resumo: variantes de "Spec → Plan → Execute → Critique → Test → Refine → Approve". Não há padrão dominante — todos são variações de Plan-and-Verify. **Não vale memorizar siglas; vale o ciclo.**

### 5.5 Multi-agent: orchestrator + workers
Anthropic publicou padrão (jun/2025): 1 orquestrador delega a N sub-agents read-only via Task tool. **Funciona pra pesquisa paralela** (ler 5 ADRs simultaneamente). **Não funciona pra escrita paralela** (conflito de contexto, Cognition AI jun/2025).

**Receita pra Wagner:** sub-agents só pra read/research. Escrita = thread principal sequencial.

### 5.6 Spec-Driven Development (Sean Grove, OpenAI, out/2025)
Tese: "o código é cache do spec; o spec é a verdade". Workflow: escreve spec curta (markdown), agent gera código, spec fica versionada e regenerável. Wagner já faz isso de fato com `memory/requisitos/{Mod}/SPEC.md` + TaskRegistry MCP (ADR 0069).

---

## 6. Configuração técnica do Claude Code

### 6.1 Hierarquia de memória (estado da arte 2026)

| Arquivo | Quando carrega | O que colocar |
|---|---|---|
| `~/.claude/CLAUDE.md` (user-level) | Toda sessão | Preferências pessoais que valem em todo projeto (idioma PT-BR, "decida não pergunte"). |
| `<repo>/CLAUDE.md` (project-level) | Sessão neste repo | Stack, regras invioláveis, módulos de referência. Wagner já tem ótimo. |
| `<repo>/AGENTS.md` (mirror) | Outros agentes (Cursor, Aider) | Espelho do CLAUDE.md em formato neutro. Wagner já tem. |
| `<dir>/CLAUDE.md` (subdir) | Quando agente entra naquele dir | Regras específicas por dir (nenhum módulo usa hoje; o mecanismo VIVO equivalente no oimpresso é `.claude/rules/*.md` path-scoped — ver `.claude/rules/README.md`). |
| `memory/*.md` | Carrega sob demanda via MCP/tools | Decisões, sessões, runbooks — NÃO no contexto inicial. |

**Regra-mãe:** CLAUDE.md tem **invariantes** (não muda toda semana). Estado vivo vai em CURRENT.md. Decisões em memory/decisions/. **Tasks em TaskRegistry MCP** (ADR 0069). **Não inche CLAUDE.md** — ele entra em todo prompt.

Wagner: CLAUDE.md atual (~10kb) está no limite saudável. Acima de 15kb, considera dividir.

### 6.2 Settings — hooks e permissions

`.claude/settings.json` chave:
```json
{
  "permissions": {
    "deny": ["Bash(rm -rf*)", "Bash(git push --force*)", "Write(memory/decisions/**)"],
    "ask": ["Bash(git push*)", "Bash(php artisan migrate*)", "Bash(ssh*prod*)"]
  },
  "hooks": {
    "PreToolUse": [...],
    "PostToolUse": [...],
    "UserPromptSubmit": [...]
  }
}
```

`UserPromptSubmit` hook é poderoso: roda **antes** do Claude ler o prompt. Pode injetar contexto auto (ex.: anexar `git status` + `CURRENT.md` toda vez).

### 6.3 MCP servers — quando puxar contexto extra

Regra: **MCP tool > Read** quando você sabe que o conteúdo está no MCP. Wagner já formalizou (CLAUDE.md §2). Razões:
- Auditado em `mcp_audit_log`
- Retorna só campos relevantes
- Cache governado (não precisa re-ler 50 arquivos)

### 6.4 Skills auto-ativáveis — boas práticas

`description:` no frontmatter precisa ser **específico** e **acionável**:
- Bom: "Ativa quando criar Entity/Controller/Service que toca tabela com `business_id`."
- Ruim: "Ajuda com multi-tenant."

Quanto mais específico, mais o classificador acerta o trigger.

### 6.5 Contexto longo (1M tokens) — quando vale

Modelo opus 4.7 com 1M tokens (que você está rodando): **não use 1M por padrão**. Custa caro e qualidade cai após 200k-300k (lost-in-the-middle, Liu et al 2023, ainda válido em 2026).

**Quando vale:**
- Análise de codebase inteiro (raro)
- Cycle review com TODAS as ADRs
- Migração grande (3.7→6.7) onde precisa cross-ref muitos arquivos

**Quando NÃO vale:**
- Bug fix normal (use 50k)
- Feature nova (use 100k)
- Pergunta única (use 20k)

Use `/compact` agressivamente pra ficar no sweet spot 50-150k.

---

## 7. Receitas práticas — copiar e colar

### 7.1 Bug em produção (Hostinger)
```
[BUG PROD] <sintoma>. Reproduce: <url + ação>. Log via SSH:
ssh -4 ... 'tail -100 storage/logs/laravel.log | grep ERROR'

[INVESTIGUE READ-ONLY PRIMEIRO]
Não toca arquivo. Reporta hipótese + file:line suspeito + plano de fix.
Depois eu aprovo o fix.
```

### 7.2 Feature nova em módulo existente
```
[US-XXX-NNN] <título>
[ADR] <slug ADR fundadora>
[MODELO] Modules/Jana/.../X.php (imitar estrutura)
[ENTREGA] migration + model + controller + Pest test
[VERIFY] no CT 100: tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging php artisan test --filter=<NomeTest>" (⛔ NUNCA `php artisan test` local — proibição Tier 0, hook bloqueia)
[FORA DE ESCOPO] UI (sai em PR separado)
```

### 7.3 Refactor de shim/legacy
```
[REFACTOR] <componente>
[INVARIANTE] Suite atual verde (cite filtro Pest).
[ESTRATÉGIA] Strangler. Pequenos commits. Cada commit suite verde.
[ASK BEFORE ACT] Se exigir mudar API pública, pare e me chame.
```

### 7.4 Pesquisa / decisão arquitetural
```
[QUESTÃO] X vs Y vs Z, pro nosso contexto Oimpresso.
[CONSTRAINT] Self-host (ADR 0059). LGPD. Solo dev manutenção.
[ENTREGA] memory/decisions/NNNN-slug.md draft (Nygard format).
[NÃO CODAR] Só pesquisa + ADR.
```

### 7.5 Sessão de fim de cycle / housekeeping
```
/ultrareview do diff total do cycle (git log main..HEAD).
Reporta: 3 bugs latentes, 2 ADRs faltando, 1 doc desatualizado.
Não corrige. Só lista. Eu prioriso.
```

---

## 8. Anti-padrões observados (errar com estilo)

| Anti-padrão | Sintoma | Correção |
|---|---|---|
| "Faz tudo" prompt | Claude começa em 5 frentes, perde foco | Decompor em US-XXX-NNN |
| Sem critério de aceite | "Está pronto?" eterno | Adicionar [VERIFY] sempre |
| Colar log com PII | LGPD risk | Sanitizar antes |
| Ignorar Plan mode em refactor | Diff de 800 linhas pra revisar | Plan mode obrigatório >3 arquivos |
| `/compact` 4× na sessão | Sessão deveria ter sido 2 | `/clear` + nova |
| Pedir Claude rodar em prod sem dry-run | Crashes 18/19/21-abr | Sempre staging primeiro |
| Não citar ADR | Decisão re-litigada | "cite ADR XXXX" no prompt |
| Multi-agent paralelo escrita | Conflito de arquivo | 1 thread, sub-agents só read |
| CLAUDE.md crescer indefinido | Token cost por sessão sobe | Mover histórico pra memory/ |
| Aceitar primeiro diff sem revisar | Bug em prod | /ultrareview antes de merge |

---

## 9. Métricas pra saber se está funcionando

Track informal (auto-mem ou planilha):
- **Tokens por feature** (anotar). Tendência queda = está melhorando contexto.
- **Round-trips até teste verde**. Meta: ≤3 pra task S, ≤7 pra M.
- **PRs revertidos / total**. Meta: <5%.
- **% sessões que terminam com handoff atualizado**. Meta: >90%.

Se PRs revertidos sobem, o gargalo é **review** (faltou /ultrareview ou test-first), não o agente.

---

## 10. Fontes e referências

- **Anthropic** — "Claude Code Best Practices" (set/2025), "Effective Context Engineering" (set/2025), Cookbook (live).
- **Cognition AI** — Walden Yan, "Don't Build Multi-Agents" (jun/2025).
- **Sean Grove (OpenAI)** — "The New Code: Specifications" (out/2025).
- **Lance Martin (LangChain)** — "Context Engineering" (jun/2025).
- **Steve Yegge** — "The Death of the Stubborn Developer" (2025).
- **Simon Willison weblog** — "vibe coding" + Claude Code field reports (2025-2026).
- **Yao et al** — ReAct (ICLR 2023). **Wang et al** — Plan-and-Solve (ACL 2023). **Shinn et al** — Reflexion (NeurIPS 2023). **Madaan et al** — Self-Refine (2023). **Liu et al** — Lost in the Middle (TACL 2024).
- **SWE-bench Verified leaderboard** (princeton-nlp, atualizado 2025-2026).
- **AGENTS.md spec** (sourcegraph + openai, 2025).
- **GitHub Spec Kit** (2025).
- **Wagner's CLAUDE.md / ADR 0040 / ADR 0059 / ADR 0069** — governança self-host adaptada Anthropic Team plan + TaskRegistry MCP.

---

## TL;DR pro Wagner

1. **Spec curta + cite ADR + cite arquivo modelo** bate qualquer prompt longo.
2. **Plan mode** pra qualquer coisa que toca >3 arquivos.
3. `/clear` ao trocar escopo, `/compact` no meio do escopo, `/continuar` pra retomar.
4. **Hooks bloqueiam destrutivos** — invista 1h escrevendo PreToolUse.
5. **Test-first + /ultrareview** elimina 80% dos bugs antes do merge.
6. **Sub-agents só pra read-only** (pesquisa paralela). Escrita = 1 thread.
7. CLAUDE.md = invariantes. CURRENT.md = estado vivo. memory/ = histórico. **TaskRegistry MCP = tasks** (ADR 0069). Não inche o primeiro.
8. **Contexto > prompt**. Quando algo dá errado, na maioria das vezes faltou contexto curado, não palavra mágica.

---

> Este documento é canônico (sincronizado pro MCP via skill `sync-mem`; o CLAUDE.md pós-Constituição não o referencia mais diretamente). Atualizar quando Anthropic publicar guidance nova ou Wagner descobrir padrão melhor — registrar mudança em ADR se for inversão de princípio. Auditado claim-a-claim em 2026-07-09 (exemplos fantasma corrigidos pra âncoras reais).
