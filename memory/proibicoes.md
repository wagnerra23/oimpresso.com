# Proibições (Tier 0 — sem ADR mãe nova é proibido)

## REGRA ZERO — PROTOCOLO WAGNER SEMPRE (Tier 0 IRREVOGÁVEL)

> ⛔⛔⛔ **Toda sessão Claude no oimpresso DEVE executar automaticamente as 10 regras do [PROTOCOLO-WAGNER-SEMPRE.md](reference/PROTOCOLO-WAGNER-SEMPRE.md) — sem Wagner precisar repedir.** Wagner palavras textuais 2026-05-17, sessão `stupefied-noether-89f83d`: *"não é justo eu sempre ficar pedindo a mesma coisa. mantenha o conhecimento agregado e automatize não me irrite. apreenda. se torne especialista."*
>
> **As 10 regras (R1-R10):**
>
> | # | Regra | Quando dispara |
> |---|---|---|
> | R1 | Smoke real (não narração) | Merge / deploy / "funcionando" |
> | R2 | Cópia literal design aprovado | Wagner aprovou screenshot |
> | R3 | Workflow 3 fases (PRE+DURING+POST) | Edit `Modules/<X>/` |
> | R4 | Multi-tenant Tier 0 IRREVOGÁVEL | Edit Model/Service/Job |
> | R5 | PT-BR + economia crédito | SEMPRE |
> | R6 | biz=1 não biz=4 (cliente piloto) | Pest + smoke |
> | R7 | Charter + visual-comparison antes Edit Page | `Pages/<Mod>/<Tela>.tsx` |
> | R8 | Branch + worktree disciplina | Worktree filha |
> | R9 | Zero auto-mem privada | Write `~/.claude/projects/*/memory/` |
> | R10 | Aprovação humana antes commit/push/merge | git push / `gh pr merge` (autorização cobre ESCOPO inteiro, não só ação isolada — calibrada com R11) |
> | R11 | **Continuar autonomamente até desfecho dentro do escopo pré-aprovado** | Wagner aprovou caminho ("sim pode" + N passos) — Claude executa do começo ao fim sem pausa interna. Origem 2026-05-17 Wagner *"atualize seu protocolo para ficar esperando eu tive que vir aqui lembrar"*. |
>
> **Skill enforcement:** [`wagner-protocol-enforce`](../.claude/skills/wagner-protocol-enforce/SKILL.md) Tier A always-on carrega no SessionStart de toda sessão.
>
> **Agent companion:** [`wagner-understand`](../.claude/agents/wagner-understand.md) subagente proativo — Claude pai spawn ANTES de executar pedido cru não-trivial. Decodifica pedido em estrutura + cruza com PROTOCOLO + inventaria projeto + lista pegadinhas + plug-points + tasks atômicas.
>
> **Sinal de violação:** Wagner pergunta "o que eu sempre solicito?" — significa Claude esqueceu uma das 10 regras. Catalogar incidente + atualizar protocolo + post-mortem inline.

---

## REGRA PRIMÁRIA — Mexeu, REGISTRA (Tier 0 IRREVOGÁVEL)

> ⛔⛔⛔ **Toda mudança em código de `Module/`, daemon CT 100, schema DB, config infra ou qualquer artefato operacional DEVE ser registrada IMEDIATAMENTE em git + tests + docs canon.** Não existe "ajuste rápido", "fix temporário", "depois eu commito". Drift entre prod e git canônico É O VETOR Nº 1 de incidentes catalogado (maratona WhatsApp 14-15/mai: 5 instâncias de drift custaram ~5h investigação retrospectiva + 12 PRs corretivos).
>
> Wagner palavras textuais 2026-05-15: *"mexeu na merda do módulo registra caralho"*.
>
> **Detalhe completo + 5 vetores catalogados + 7 defesas automáticas + caminhos canônicos por tipo de mudança em [`memory/reference/feedback-modulo-mexeu-registra-sempre.md`](reference/feedback-modulo-mexeu-registra-sempre.md).**
>
> **Caminhos canônicos por tipo de mudança:**
>
> | Você mexeu em... | Caminho obrigatório |
> |---|---|
> | Código Module (PHP/TS/React) | PR no git → CI verde → merge |
> | Comando artisan / cron | PR + entry em `app/Console/Kernel.php` + log estruturado |
> | Schema DB (DDL) | Migration PHP + Pest sobrevive re-run + ADR se decisão arquitetural |
> | INSERT/UPDATE direto no DB (tinker, SQL, phpMyAdmin) | Seeder OR comando artisan idempotente OR backfill job + commit |
> | Arquivo no servidor (SSH Hostinger, CT 100, daemon source) | **PROIBIDO** — via git pull do canônico apenas |
> | Cache `Cache::put`/`Cache::forget` ad-hoc em prod | Observer ou comando artisan registrado, NUNCA tinker direto sem commit |
>
> **Se Wagner aprovar Tier 0 superadmin "ajuste rápido" em emergência:** Claude marca log com `// DRIFT TIER 0 — Wagner aprovou em <data>, follow-up PR <hash>` E spawna PR follow-up imediato.
>
> ### EVOLUÇÃO 2026-05-15 14h — Workflow Tier 0 expandido (3 fases obrigatórias)
>
> Wagner segundo corte explícito (Tier 0 IRREVOGÁVEL):
>
> *"vai mecher no modulo ler brefing e se mexer salva o progresso. (...) porr mexe não registra, altera sem ler as regras do modulo fica sempre errando, caramba se organiza caralho seja responsavel porra. vao entrar os outros no MCP e isso vai ficar uma zona caralho"*
>
> **REGRA "mexeu, registra" sozinha NÃO é suficiente.** Workflow completo:
>
> | Fase | Quando | O que fazer | Sintoma de violação |
> |---|---|---|---|
> | **PRÉ-FLIGHT** | ANTES de qualquer Edit/Write em `Modules/<X>/` | Ler `SPEC.md` + `RUNBOOK*.md` + `CAPTERRA*.md` + ADRs relacionadas + charter da página + skill `como-integrar` se feature parcial | "Vou mexer rápido sem ler" → bug |
> | **DURING** | Mexendo no código | Commit incremental por step lógico; `git push` WIP a cada ~30min; `TodoWrite` mark completed após cada step; **NUNCA** `git checkout` outra branch sem `stash` ou `commit` | "trabalho de 2h perdido" / "esqueci de commitar" |
> | **POST** | Mexeu | PR no git + CI + merge + docs canon (regra original "mexeu, registra") | "depois eu commito" / drift |
>
> **PRÉ-FLIGHT leitura obrigatória por tipo de Edit:**
>
> | Vai editar... | LEIA ANTES |
> |---|---|
> | `Modules/<X>/Http/Controllers/...` | `memory/requisitos/<X>/SPEC.md` (US-XXX-NNN) |
> | `resources/js/Pages/<X>/<Tela>.tsx` | charter `<Tela>.charter.md` + skill `mwart-process` (ADR 0104) |
> | `Modules/<X>/Database/Migrations/...` | ADR 0093 (multi-tenant) + Schema existente |
> | Comando artisan novo | skill `criar-modulo` + Console/Kernel.php pattern |
> | Service/Job que toca prod biz=1 | ADR 0101 (tests biz=1) + skill `multi-tenant-patterns` |
> | Observer/Event | ADR 0143 FSM (se aplicável) + proibições deste arquivo |
>
> **Por que isso importa MAIS agora (2026-05-15+):**
> 1. **Time MCP entra em breve** (Felipe/Maiara/Eliana/Luiz) — sem workflow estrito, drift escala N× pessoas
> 2. **Maratona WhatsApp 14-15/mai** mostrou que **TODOS** os 5 drifts catalogados vieram de violação de FASE 1 (mexer sem ler) ou FASE 2 (não salvar progresso)
> 3. **MCP server `mcp.oimpresso.com`** vai expor estado vivo pro time — drift = dado errado servido a Felipe/Maiara
>
> **Detalhe completo + 5 vetores catalogados + 7 defesas automáticas + comportamento Claude esperado em [`memory/reference/feedback-modulo-mexeu-registra-sempre.md`](reference/feedback-modulo-mexeu-registra-sempre.md).**

---

## REGRA MESTRE — CÁLCULO DE VALOR ou ESTOQUE (Tier 0 IRREVOGÁVEL)

> ⛔⛔⛔ **TODA alteração que possa mexer em VALOR (preço, total, subtotal, desconto, imposto, frete, `final_total`, `total_before_tax`, pagamento, comissão, parsing/format de número — `num_uf`/`num_f`/`parseDecimalPtBR`) ou em ESTOQUE (quantidade, movimentação, reserva, baixa) exige, ANTES de mergear/deploiar:**
>
> | # | Exigência | Como |
> |---|---|---|
> | **1** | **DUPLA CONFIRMAÇÃO do cálculo** | Provar o resultado por **DOIS caminhos independentes** com NÚMEROS CONCRETOS: ex (a) caso de teste manual ponta-a-ponta + (b) cross-check frontend×backend, OU dry-run + recompute à mão. Nunca confiar num só. |
> | **2** | **APRESENTAR O IMPACTO antes de aplicar** | Mostrar ao Wagner **tabela antes→depois** explícita: quais vendas/valores/estoques mudam e como. Em prod, **dry-run obrigatório** mostrando o delta de cada registro afetado ANTES de qualquer escrita. |
> | **3** | **Aprovação humana explícita** | Só aplicar após Wagner ver o impacto (#2) e confirmar. Pareia com R10. |
>
> **Origem (2026-06-05):** incidente ROTA LIVRE biz=4 — `Util::num_uf` strippava o ponto decimal de total fracionado (desconto %): React mandava `final_total: 204.99605` → `num_uf` interpretava "." como milhar → gravou **R$ [redacted Tier 0]** numa venda de **R$ [redacted Tier 0]**. 16 vendas infladas ~×100k + pagamentos corrompidos antes de detectar. Wagner palavras textuais: *"cada modificação que possa mexer em valor precisa ser confirmada duas vezes para garantir e você precisa apresentar as mudanças que vão ocorrer — regra mestre para qualquer alteração de cálculo que mexa em qualquer valor ou estoque."*
>
> **Lição técnica perene:** separador de milhar tem SEMPRE 3 dígitos; frontend NUNCA manda float locale-ambíguo (`204.99605`) pra parser pt-BR — arredonda a 2 casas no submit. Ver [session 2026-06-05](sessions/2026-06-05-veiculo-na-venda-e-incidente-numuf-valor-inflado.md) + fix #2279.
>
> **Sintoma de violação:** mexer em `TransactionUtil`/`ProductUtil`/`Util::num_uf`/`Sells/Create|Edit.tsx`/`numberPtBR.ts` (ou qualquer cálculo de total/desconto/estoque) e mergear SEM (1) provar com 2 caminhos + (2) apresentar antes→depois. Rule path-scoped [`.claude/rules/calculo-valor-estoque.md`](../.claude/rules/calculo-valor-estoque.md) carrega esta regra ao tocar esses arquivos.

---

## Ambiente

- ⛔ **Nunca instalar `laravel/mcp` ou `laravel/octane` no Hostinger** (nem em worktree, nem em `/tmp`). Esses pacotes só vivem em CT 100 Proxmox e local. Hostinger é shared hosting; daemons lá violam contrato ([ADR 0062](decisions/0062-separacao-runtime-hostinger-ct100.md))
- ⛔ **Nunca expor rota `Mcp::web()` (laravel/mcp) sem condicional `if (config('mcp.tools_exposed'))`.** MCP server tools são exposed APENAS no CT 100 Proxmox (`mcp.oimpresso.com`); Hostinger NÃO suporta MCP (lento + crasheia — Wagner regra 2026-05-07). Schema + service backend (cron `brief:generate` etc) podem ficar em Hostinger, mas tool MCP exposed nunca. Default `MCP_TOOLS_EXPOSED=false`. CT 100 .env tem `MCP_TOOLS_EXPOSED=true`
- ⛔ **Nunca rodar Pest da suite Jana/MCP no Hostinger** — usar CT 100 (via Tailscale). **NÃO na máquina local** (ver regra "Testes/PHPStan SEMPRE no CT 100" no fim desta seção)
- ⛔ **Nunca rodar `composer update` (sem `--lock`) em servidor de produção** sem PR aprovado
- ⛔ **Nunca alterar branch ativa em produção pra "testar"** (Hostinger ou CT 100) — usar worktree e limpar depois
- ⛔ **Nunca editar arquivo direto via SSH** sem commit no git — drift mata governança
- ⛔ **DDL direto em prod** (`ALTER TABLE`, `CREATE/REPLACE PROCEDURE` via SQL prompt ou phpMyAdmin) sem migration — o check `procedure_drift` em `jana:health-check` detecta e alerta; o `ProcedureDriftSnapshotTest` quebra em CI (US-COPI-092, ADR 0094 §5 SoC brutal)
- ⛔ **Nunca rodar daemons no Hostinger** (Reverb, Centrifugo, Horizon, autossh, Meilisearch). Pra daemons → CT 100
- ⛔ **Nunca rodar `git worktree remove --force` no Windows com junction de `vendor/` ainda presente.** A junction NTFS é seguida pelo delete recursivo → esvazia o `vendor/` do repo principal (318MB → 0B em segundos). Remover a junction explícita ANTES: `Remove-Item <worktree>\vendor -Force` então `git worktree remove <worktree>` (sem `--force`). Detalhes + recovery em [`memory/requisitos/Infra/PEGADINHA-junction-vendor-worktree-windows.md`](requisitos/Infra/PEGADINHA-junction-vendor-worktree-windows.md). Caí 2026-05-11 — composer install demora 3-5min pra recuperar
- ⛔ **PowerShell 5.1 `Set-Content -Encoding utf8` grava UTF-8 COM BOM** (`EF BB BF` prefix), NÃO UTF-8 puro. PHP não interpreta `<?php` quando há BOM antes → arquivo vira HTML output, namespace declaration falha, prod CRASHA com erro `Namespace declaration statement has to be the very first statement`. Catalogado 2026-05-16: hotfix #984 corrigiu 5 arquivos (CmsController.php + 4 Crm/Entities) quebrando `oimpresso.com` inteiro. **Caminhos seguros pra escrever PHP/JS/MD/TS em PowerShell:** (a) `[System.IO.File]::WriteAllText($path, $content, (New-Object System.Text.UTF8Encoding $false))` — UTF8 sem BOM explícito; (b) PowerShell 7+ `Set-Content -Encoding utf8NoBOM`; (c) **Python via `python -c`** — mais robusto cross-platform (decoda UTF-16/UTF-8-sig/UTF-8 corretamente). NUNCA usar `Set-Content -Encoding utf8` sem o sufixo `NoBOM` em PS 5.1. Validar pós-write: `file <path>` deve dizer "UTF-8 text" sem "with BOM"
- ⛔ **Testes (Pest) e análise estática (PHPStan/Larastan) NUNCA rodam na máquina local nem no Hostinger — SEMPRE no CT 100** (container `oimpresso-staging`: tem CPU/RAM + stack completo incl. OTel SDK/larastan `require-dev` + **MySQL real `oimpresso-staging-db`, biz=1 dogfooding**). Wagner textual 2026-06-01: *"os testes não devem ser feito local, as maquinas não suportariam faça no ct 100 obrigatoriamente la tem recursos para isso. é o lugar correto anote na memória para não errar denovo"*. **Reincidi no MESMO dia** (tentei PHPStan local, `php` nem está no PATH do bash + junction `vendor` falhou) → Wagner: *"erro denovo coloque nas proibições ou crie um gatilho"*. **Caminho canônico:** `tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging php artisan test --filter=X"` (ou `vendor/bin/phpstan analyse <path> --memory-limit=1G`). CI GitHub Actions continua o gate de merge. **Gatilho ativo (defesa em profundidade):** hook PreToolUse [`.claude/hooks/block-test-fora-ct100.ps1`](../.claude/hooks/block-test-fora-ct100.ps1) (matcher `Bash|PowerShell`) bloqueia `php artisan test` / `vendor/bin/{pest,phpstan,phpunit}` / `composer {test,pest,phpstan,larastan}` fora do CT 100. Escape valve emergência Wagner: incluir `test-local-override` no comando. Ref [`memory/reference/feedback-testes-no-ct100-nao-local.md`](reference/feedback-testes-no-ct100-nao-local.md) + [ADR 0062](decisions/0062-separacao-runtime-hostinger-ct100.md).

## Código

- ⛔ **Não modificar tabelas core UltimatePOS** (`users`, `business`, `employees`) sem bridge table
- ⛔ **Não fazer UPDATE/DELETE em `ponto_marcacoes`** — append-only por força de lei (Portaria 671/2021). Use `Marcacao::anular()`
- ⛔ **Não remover triggers MySQL de imutabilidade** sem abrir ADR justificando
- ⛔ **Não criar nova tecnologia/dependência** sem registrar ADR
- ⛔ **Não responder em inglês** — Wagner+Eliana são brasileiros, preferem PT-BR
- ⛔ **Não assumir completude** — Wagner valoriza economia de crédito; confirme escopo com perguntas curtas antes de implementar massivamente
- ⛔ **Não remover shim `App\View\Helpers\Form`** sem antes migrar ~6.4k chamadas Blade `Form::`
- ⛔ **Identificadores MySQL >64 chars** — sempre passar nome explícito em índices compostos
- ⛔ **NUNCA buscar secret (token/API key/password/SSH key/credential) sem consultar `memory/_INDEX-SECRETS.md` PRIMEIRO**. Falha 2026-05-28: agente declarou Tier 0 gap "token Hostinger inacessível" sem ter pesquisado memory canon. O token estava catalogado no canon, mas o agente não consultou. Wagner cobrou: *"tem api da hostinger na memoria"* + *"arrume essa memoria, não esta indexada?"*. (Fonte canônica hoje: CT100 `/root/.hostinger-api-token` + Vault — o legado `memory/claude/` foi purgado na auditoria 2026-06-07.) Skill canon `memory-first-secret-search` Tier A bloqueia. Ordem fixa: (1) Read `_INDEX-SECRETS.md` → (2) follow ponteiro → (3) se 🔴 EXPIRED registra rotação. Pular = violação Tier A.
- ⛔ **NUNCA escalar pro Wagner ação automatizável (hPanel, painéis web, copy-paste secret no chat)** se existe API/CLI documentada. Falha 2026-05-28: agente pediu Wagner criar A record no hPanel quando ADR 0045 já dava receita exata via Hostinger DNS API. Wagner alertou 3× nesta sessão "não pede o que pode fazer". Skill canon `hostinger-dns-autonomy` Tier A lista 6 paths pra buscar token Hostinger antes de "desistir" — se TODOS falharem, registra Tier 0 gap, NÃO escala. Princípio: Wagner NÃO é helpdesk do agente. Ref [PROTOCOLO-WAGNER-SEMPRE](reference/PROTOCOLO-WAGNER-SEMPRE.md) regra 1, skill `publication-policy`, skill `hostinger-dns-autonomy`

## Tier 0 gaps catalogados (precisam ADR estrutural pra unblock)

### 2026-05-28 — Token Hostinger API inacessível ao agente autônomo

**Bloqueador:** ADR 0045 (DNS API canônica) prevê uso programático mas token "Claude da hostinger" só vive encrypted no Vaultwarden `db.sqlite3` (CT 100). 6 paths skill `hostinger-dns-autonomy` testados, TODOS retornaram MISSING:
1. `/root/.hostinger-api-token` CT 100 — vazio (file pronto, Wagner não colou)
2. `~/.hostinger-api-token` Hostinger — não existe
3. ENV `HOSTINGER_API_*` `.env` Hostinger — não set
4. Containers CT 100 com env Hostinger — nenhum
5. `bw` (Bitwarden CLI) — não instalado, sem session
6. ENV CT 100 root sources — não set

**Único arquivo `docker-host/.env` tem `VAULTWARDEN_ADMIN_TOKEN`** mas admin panel não dá acesso a items de outros users (só management). Items encrypted com master pass de cada user.

**Impacto:** agente bloqueia em qualquer ação DNS Hostinger (criar subdomínio, alterar A/CNAME/MX). Cada incident requer Wagner colar token novamente (violação setup único irredutível).

**Soluções estruturais propostas (ADR pendente):**
- **A)** Setup único irredutível: Wagner cola token UMA VEZ em `/root/.hostinger-api-token` CT 100 chmod 600. Skill `hostinger-dns-autonomy` força agente a SEMPRE ler de lá daí em diante. Rotação anual via Wagner re-cola.
- **B)** Vaultwarden API key user-level: Wagner cria service account "claude-agent" + API key + setup `bw` CLI no CT 100 + script `get-secret.sh`. Agente lê qualquer secret via `bw get item <slug>`. Setup 30min uma vez. Bonus: extensível pra outros secrets (Asaas, Sicoob, etc).
- **C)** Migrar pra Hashicorp Vault / Doppler / 1Password Connect — over-engineering pra startup, descartado.

**Recomendação:** B (Vaultwarden API key) — escalável + Wagner setup só 1× pra todos secrets futuros.

**Status:** awaiting Wagner decisão A vs B. Próxima sessão começa por aceite + implementação.
- ⛔ **Não suba código sem alertar pré-requisitos e riscos**. Histórico de crashes:
  - 2026-04-18: scaffold incompatível
  - 2026-04-19: PHP 8 em servidor PHP 7.1
  - 2026-04-21: módulo desativado após upgrade 6.7
- ⛔ **Não criar `Modules/X/Tests/` sem registrar em `phpunit.xml`** — testes ficam no repo mas CI nunca roda → falsa cobertura

## Design System / Pacote Cowork novo (Tier 0 — sessão 2026-05-18 Wagner)

> Catalogado após 3 tentativas falhas no Financeiro ([PR #1085](https://github.com/wagnerra23/oimpresso.com/pull/1085) → [#1091](https://github.com/wagnerra23/oimpresso.com/pull/1091) → [#1092](https://github.com/wagnerra23/oimpresso.com/pull/1092)) onde cherry-pick incremental de classes deixou drawer/painéis quebrados (cores erradas, abas faltando, textos colados). Wagner palavras textuais 2026-05-18: *"não deu certo, acredito que deva copiar o css na integra do financeiro. depois ver o que precisa fazer para integrar. 3 vez que errou. e deu certo nos outros módulos quando na primeiras vez do modulo copiar tudo. e depois customizar."*

- ⛔ **Cherry-pick incremental** de classes CSS do bundle Cowork pra módulo recém-chegando — banido. PRIMEIRA aplicação = COPIAR `styles.css` INTEIRO do bundle direto pro projeto (`resources/css/cowork-<modulo>-bundle.css`). Customização Inertia/React vem DEPOIS. Cherry-pick gasta 3-5 ondas iterando bugs visuais que Wagner reabre.
- ⛔ **Misturar bundle copy com customização Inertia/React em mesma PR** — separar: 1 PR de bundle copy ("base canônica") + N PRs de customização. Bundle deve passar smoke independente antes de qualquer Edit em `.tsx`.
- ⛔ **Aplicar bundle Cowork SEM scopear** — bundles trazem classes globais (sem `.fin-curadoria` wrapper). Validar que classes não colidem com outros módulos: se colidir, importar bundle com `@scope` ou `@layer` específico do módulo.
- ⛔ **Esquecer de validar com Chrome MCP CSS computed** pós-merge bundle copy — `feedback-brave-mcp-primeiro-sempre.md` aplica em dobro: bundles novos exigem screenshot + smoke antes de declarar pronto.

**Detalhe completo (procedimento canônico 7 passos + quando aplicar + validação histórica + o que falhou no Financeiro)** em [`memory/reference/feedback-cowork-bundle-aplicar-inteiro.md`](reference/feedback-cowork-bundle-aplicar-inteiro.md).

**Validado historicamente (referência):** Vendas (Sells/Index, Sells/Create), Pedidos (POS layout v1), Cockpit (AppShellV2) — todos primeira aplicação foi bundle inteiro.

---

## Memória/governança

- ⛔ **ZERO auto-mem privada legada** ([ADR 0061](decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) + [ADR 0131](decisions/0131-tiering-memoria-canonico-local-segredo.md)). Hook `block-automem.ps1` BLOQUEIA `Write/Edit` em `~/.claude/projects/*/memory/*.md`. **Escape valves legítimas (ADR 0131):** (a) `~/.claude/oimpresso-local/**` pra máquina-local pessoal; (b) Vaultwarden pra segredos. Critério: segredo? → Vaultwarden; só seu? → oimpresso-local; time precisa ver? → git canônico
- ⛔ **Não duplicar info entre sistemas.** Git é canônico; MCP é cache governado
- ⛔ **ADRs CANON são append-only.** NUNCA editar accepted records — criar nova com `supersedes: [N]`. CI `governance-gate.yml` Job 1 (Mecanismo #2 ENFORCEMENT) bloqueia merge de PR que tenha status `M`/`R*` em `memory/decisions/NNNN-*.md` ou `memory/handoffs/*.md`. CONSTITUTION editada exige label `constitution-amendment` + `audit-*.md` no mesmo PR (§10.4 Cascade Review). Runbook: [RUNBOOK-governance-gate-ci.md](requisitos/Infra/RUNBOOK-governance-gate-ci.md).
- ⛔ **Tasks NÃO em markdown.** Estado vivo via tools MCP (`cycles-active`, `tasks-list`) — CURRENT.md/TASKS.md REMOVIDOS ([ADR 0070](decisions/0070-jira-style-task-management-current-md-removed.md))
- ⛔ **NUNCA pular `brief-fetch` no início de sessão** — Tier A bloqueador via skill `brief-first` (custo trivial ~3k tokens, cache 5min, economiza ~27k tokens de exploração). Sintoma de degradação clássico: Claude começa a trabalhar via `my-work`/`tasks-list`/Read sem ter chamado brief antes → opera com dados parciais → gera plano duplicado. Catalogado sessão 2026-05-13 (Claude gerou plano de paralelização de backlog que duplicava ROADMAP existente porque não tinha brief). Auditável: se hoje você (Claude) não chamou `brief-fetch` antes de outra tool MCP/Read no início, é violação.
- ⛔ **NUNCA criar arquivo em `memory/` sem `Glob`/`Grep` antes** pra checar duplicação. Especialmente: session logs (`memory/sessions/YYYY-MM-DD-*.md`), planos por módulo (`memory/requisitos/<Mod>/*.md`), ADRs (`memory/decisions/*.md`). Se já existe similar, EDITA o existente — não cria novo. Catalogado sessão 2026-05-13.
- ⛔ **Artefato de DESIGN** (`.html`/protótipo/relatório *meta* do Cowork) **segue as mesmas duas regras acima** — não-duplicação (1 tema = 1 doc; edita o existente, nunca `vN.html`) + append-only/trilha-do-tempo (rodapé de evolução + lápide ao arquivar). Versão escopada + exemplos em [`prototipo-ui/CLAUDE_DESIGN_BRIEFING.md §7.1`](../prototipo-ui/CLAUDE_DESIGN_BRIEFING.md). Origem: Cowork 2026-06-01 (L-21/L-22).
- ⛔ **NUNCA commitar valores BRL** (R$, MRR, totais, valor por mês) em `memory/`, `*.md` canon, PR body, ou commit message. **Só Wagner/Eliana têm acesso a valores via git** — Felipe/Maiara/Luiz veem **escopo, contagens, estrutura** (108 subs, 1311 invoices, etc) mas NÃO valores monetários do que foi migrado. Origem: Wagner regra explícita 2026-06-08 *"maiara felipe e luiz não podem ter acesso a nada de valores no git. pode saber o que migrou mas não saber o conteudo (valores)"*. **Reincidência custou:** sessão 2026-06-08 vazei valores em handoff + session + SPEC + 08-handoff (PRs #2433/#2434). Recuperação: redact forward #2435 + `git filter-repo --replace-text 'R\$\s?\d[\d.,]*' → 'R$ [redacted Tier 0]'` em **5.033 commits** + force-push `main` (allow_force_pushes habilitado temporariamente via gh API + restaurado). Custo defesa: ~30min + branch protection window aberta. **Pattern futuro:** se precisar comunicar valor → mencione "[redacted Tier 0]" ou comunique fora-banda (chat Wagner direto, não git). Para reforço mecânico, considerar hook PreToolUse `block-brl-values-in-memory.ps1` que detecta `R\$\s?\d` em Edit/Write de `memory/**/*.md` e bloqueia.

## Comportamento Claude (sessão)

- ⛔ **Após Wagner cortar minha proposta 1x, PARAR e PERGUNTAR** — não re-inflar com "versão refinada". Re-inflar é não-ouvir disfarçado de iteração. Sessão 2026-05-13 teve 3 cortes consecutivos (Wagner cortou skill+subagent → cortou modo brabo → cortou estado-da-arte complexo) antes de Claude obedecer; ROI cortado em cada inflação. Sinal de degradação comportamental — ver `memory/how-trabalhar.md` §"Reconhecer degradação de sessão".
- ⛔ **Não usar tom inflado falso-confiante** — "P0 fatal", "consultor brabo", "auto-derrota" sobre premissas não validadas (taxa de revisão <90d, ROI, etc) é teatro. Wagner detecta. Brevidade + honestidade > drama performado.
- ⛔ **Não esquecer `TodoWrite` em tarefas multi-step ≥3 passos** — sintoma de degradação. Sessão 2026-05-13 sistema lembrou 2x via reminder.
- ⛔ **NÃO sugerir Baileys 6.7.9 ou "esperar Baileys 7.0.0 final"** — Wagner decidiu migração 7.x irreversível (informado 3× em 13/14/15-mai). Próxima ação esperada: EXECUTAR migração (PR, runbook, deploy), nunca re-debater. Issues rc abertas são gerenciadas DURANTE execução, NÃO citadas a priori como bloqueio. Detalhes + histórico das 3 regressões em [memory/reference/feedback-baileys-7x-decisao-irreversivel.md](reference/feedback-baileys-7x-decisao-irreversivel.md). Wagner palavras textuais: *"se reclamar de novo que ele é rc vai ser muito desagradavel."*

## Processo MWART canônico — único caminho ([ADR 0104](decisions/0104-processo-mwart-canonico-unico-caminho.md))

- ⛔ **Caminho alternativo de MWART** — Edit/Write em `resources/js/Pages/<Mod>/<Tela>.tsx` SEM `memory/requisitos/<Mod>/RUNBOOK-<tela-kebab>.md` existir. Hook `block-mwart-violation.ps1` bloqueia em runtime. ⚠️ **Atualização 2026-06-11 (ADR 0271 onda 2):** o CI `mwart-gate.yml` foi **deletado** (era soft `continue-on-error`, só comentava — teatro). A régua viva de cobertura de tela é o `casos-gate` (required, ADR 0264) + `screen-coverage`. O enforcement de RUNBOOK segue só via hook runtime. Override runtime: comentar `/mwart-override <razão>` em PR (vira ADR per-tela `lifecycle: historical`)
- ⛔ **F2 BACKEND BASELINE sem Pest 5+ fixtures** do `store()` antes de mexer — gera regressão silenciosa
- ⛔ **F4 QA sem smoke biz=1** ([ADR 0101](decisions/0101-tests-business-id-1-nunca-cliente.md)) — usar biz=4 (cliente) em smoke = grave
- ⛔ **F5 CUTOVER sem aviso prévio cliente + canary 7d** — ROTA LIVRE 99% volume, surprise = perda

## Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093](decisions/0093-multi-tenant-isolation-tier-0.md))

- ⛔ **`business_id` global scope obrigatório** em toda Eloquent Model que toca dados de negócio
- ⛔ **Não usar `withoutGlobalScopes`** sem comentário `// SUPERADMIN: <razão>`
- ⛔ **Job assíncrono SEMPRE passa `$businessId`** no constructor — `session()` não funciona em fila
- ⛔ **Tabela de negócio nova obrigatoriamente** tem `business_id` indexado + FK
- ⛔ **PII reais (CPF/CNPJ cliente) NUNCA em PR/commit/log** — use `[REDACTED]` ou `PiiRedactor`
- ⛔ **NUNCA hardcode `if ($business_id === N) return` pra esconder/liberar módulo do sidebar.** Habilitar/desabilitar feature por business é SEMPRE via UI canônica (zero deploy code) — pareia conceitualmente com `business_id` global scope, Wagner 2026-05-18: *"Regra basica junto com Business_id acho que não porderia ser diferente"*. **TRÊS CAMADAS INDEPENDENTES** (todas precisam estar OK pra item aparecer no sidebar):
  - **Camada 1 — Módulos nWidart por BUSINESS** (`Modules/X/`): Financeiro/CRM/WooCommerce/Governance/etc. UI: `/superadmin/packages/{id}/edit` → marcar/desmarcar `X_module` no `package_details` + **marcar tb "Atualizar inscrições existentes"** (sem isso só pacote muda, NÃO subscription ativa). Code: `ModuleUtil::hasThePermissionInSubscription($business_id, 'X_module', 'superadmin_package')`. **Quem edita:** Superadmin (Wagner) — vê todos businesses.
  - **Camada 2 — Módulos core UltimatePOS por BUSINESS**: Despesas (`expenses`) / Pedidos (`service_staff`) / Cocina (`kitchen`) / Mesas (`tables`) / Reservas (`booking`) / etc. UI: `/business/settings` → aba "Recursos do negócio" → checkboxes. **Pegadinha:** só user DO PRÓPRIO business edita via essa rota (Wagner@biz=1 NÃO edita biz=4 por aí). Wagner afirma 2026-05-18 que existe **`/superadmin/business/{id}/settings`** (verificar próxima sessão — Claude não localizou rota; doc canon registra como afirmação). Code: `in_array('expenses', session('business.enabled_modules'))`.
  - **Camada 3 — Permissions Spatie por USER/ROLE dentro do business** (granular feature-level): `financeiro.access`, `financeiro.dashboard.view`, `financeiro.contas_receber.baixar`, `governance.dashboard.view`, `crm.access_proposal`, etc. **260+ permissions catalogadas em `/roles/{id}/edit`**. Origem: método `user_permissions()` em cada DataController do módulo. UI: `/roles/{id}/edit` → checkboxes por feature. Code: `auth()->user()->can('X.permission')`. **Quem edita:** admin do business (não superadmin necessariamente). Wagner 2026-05-18: *"tem permisões por modulos"* — verdade confirmada com 260 checkboxes em prod.
  - **Origem da regra:** Wagner IRREVOGÁVEL 2026-05-18 *"Nuca faça isso, habilitar e desabilitar é compra de pacote no modulo superadmin"* após PR #1077 reverter 6 hardcodes errados (#1073/#1074/#1076). Detalhe completo (lista 24+ chaves Camada 1 + 13 keys Camada 2 + 260+ Spatie permissions Camada 3 + checklist Wagner recorrente + pattern criar gate quando módulo não tem) em [`memory/reference/feedback-habilitar-modulo-por-business.md`](reference/feedback-habilitar-modulo-por-business.md). Defesas: Pest global [`tests/Feature/Architecture/NoHardcodeBusinessIdInModulesTest.php`](../tests/Feature/Architecture/NoHardcodeBusinessIdInModulesTest.php) varre 30+ files no CI; [`tests/Feature/Sidebar/Biz4RotaLivreSidebarTest.php`](../tests/Feature/Sidebar/Biz4RotaLivreSidebarTest.php) anti-regressão dos 5 arquivos sessão 2026-05-18.

## FSM Pipeline Canônico ([ADR 0143](decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — LIVE prod biz=1 desde 2026-05-12)

- ⛔ **UPDATE direto em `current_stage_id`** (Eloquent `$tx->current_stage_id = X; $tx->save()` ou tinker raw) — trait `GuardsFsmTransitions` em Transaction + JobSheet lança `UnauthorizedActionException`. Use `ExecuteStageActionService::execute(subject, action_key, user, payload)` que aciona `FsmAuthorizationFlag::mark()` singleton ([app/Domain/Fsm/Support/FsmAuthorizationFlag.php](../app/Domain/Fsm/Support/FsmAuthorizationFlag.php))
- ⛔ **Property dinâmica em Eloquent Model com nome ≠ coluna real** (`$model->_flag = true`) — Eloquent interpreta como atributo persistível e SQL UPDATE inclui na cláusula SET → "Unknown column" error. Use singleton estático per-request OU registrar `protected $appends` + accessor (lição hotfix #640 — 2026-05-12)
- ⛔ **`static::observe(ObserverClass)` dentro de `bootXxx()` do trait** — Laravel detecta recursão de boot e lança `LogicException: bootIfNotBooted method may not be called`. Use `static::updating(closure)` que é syntactic sugar de `static::registerModelEvent` (lição hotfix #639 — 2026-05-12)
- ⛔ **Mudar `sale_stage_history.action_id` pra NOT NULL** — entrada "Pipeline iniciado" (via `startPipeline` ou `bulk-start-pipeline`) cria audit log SEM action (transição não veio de action cadastrada). Coluna é nullable desde hotfix #643 (2026-05-12)
- ⛔ **Roles Spatie sem suffix `#{biz}` em UltimatePOS** — tabela `roles.business_id` é NOT NULL com FK pra business; criar role global (sem business_id) viola FK. Use `Role::firstOrCreate(['name' => "{$role}#{$bizId}", 'business_id' => $bizId, 'guard_name' => 'web'])` ou auto-detect via `Schema::hasColumn('roles', 'business_id')` (lição hotfix #624 — 2026-05-12)
- ⛔ **Action FSM `is_critical=true` SEM role cadastrada** em `sale_stage_action_roles` — Service lança `UnauthorizedActionException` (fail-secure). Seed sempre cadastra role pra actions de risco (cancelar_venda, voltar_estagio, iniciar_producao)
- ⛔ **NFe cancelada via SEFAZ `forceDelete()` em `nfe_emissoes`** — número permanece usado oficialmente (CONFAZ SINIEF 07/2005 Art. 14). Marca status `cancelada` + permanece no banco. Apenas `rejeitada/denegada/erro_envio` viram status `inutilizada` (preserva registro, NÃO hard delete)
- ⛔ **Refund Asaas POST `/v3/payments/{id}/refund` sem flag `ASAAS_REFUND_ENABLED=true`** — RefundCobrancaAsaasJob respeita config; default false em prod = só loga TODO. Wagner ativa manual no .env após validação homolog
- ⛔ **`Mail::raw` ou Mail dispatch em `NotificarClienteCancelamentoJob` sem checar `Contact::canReceiveEmailNotification()`** — LGPD opt-in. NULL=permite (back-compat); FALSE=bloqueia + log. Mesma regra pra WhatsApp via `canReceiveWhatsappNotification()`

## Claim sem evidência (Tier 0 — 6ª camada Governance, sessão 2026-05-17)

> Catalogado após 3 PRs em cascata em 17/mai/2026 ([#1024](https://github.com/wagnerra23/oimpresso.com/pull/1024) → [#1026](https://github.com/wagnerra23/oimpresso.com/pull/1026) → [#1028](https://github.com/wagnerra23/oimpresso.com/pull/1028)) onde Claude declarou "funcionando" sem `curl -sv` em prod. Pesquisa estado-da-arte 2025-2026 em [memory/sessions/2026-05-17-arte-evidencia-llm-agents.md](sessions/2026-05-17-arte-evidencia-llm-agents.md) identificou pattern canônico Anthropic Mar 2026 (Default-FAIL + Evidence Opening + Sprint Contract upfront).

- ⛔ **"✅ funcionando"** / **"smoke OK"** / **"deploy ok"** / **"está rodando"** SEM cole literal de `curl -sv URL 2>&1 | grep '^< HTTP'` mostrando status code esperado — banido. Status code de cada hop literal, não consequência observável compatível (ex: `redirects=1, final=/login` não distingue 301 do 302).
- ⛔ **`Request::create()` em Pest** como prova de comportamento prod em middleware que olha `path()` — em prod o Symfony strip `SCRIPT_NAME` (`/public/index.php`), test com `Request::create()` não tem SCRIPT_NAME. Use `getRequestUri()` em código, e teste real via `curl -sv` pós-deploy.
- ⛔ **"Pest verde + smoke local Herd"** declarado como "funciona em prod" — Herd (nginx) ignora `.htaccess`, não reproduz LiteSpeed quirks, OPcache validate_timestamps, LSCache. Sempre validar em `https://oimpresso.com/...` real após deploy SSH.
- ⛔ **PR que modifica runtime crítico SEM seção `## Infra Contract` no body** — `.htaccess`, `app/Http/Middleware/`, `app/Http/Kernel.php`, `routes/`, `app/Providers/*ServiceProvider.php`, `bootstrap/app.php` exigem seção obrigatória com (1) happy path curl + status esperado literal, (2) regression adjacent 2-3 rotas, (3) environment delta explícito. CI workflow `infra-contract-required.yml` bloqueia merge. Template canon: [memory/templates/INFRA-CONTRACT.md](templates/INFRA-CONTRACT.md).
- ⛔ **Hook `block-claim-without-evidence.ps1` PreToolUse Bash matcher** — bloqueia `gh pr create`/`gh pr merge --admin`/`git push` em branch que toca infra crítica sem evidência curl/HTTP em PR body, últimos 5 commits, ou `.claude/run/curl-evidence-*.txt` <30min. Escape valve: `<!-- evidence-override: <razão> -->` em PR body, ou `# evidence-override: <razão>` em commit message, ou `$env:OIMPRESSO_EVIDENCE_OVERRIDE='1'` (Tier 0 Wagner emergência).
- ⛔ **Cobertura de 1 caso só** em validação — sempre incluir 2-3 regression adjacent (rotas similares que NÃO devem mudar) pra detectar regressão.
- ⛔ **APÓS qualquer merge de PR que mexa em UI** (`resources/js/Pages/**/*.tsx` · `resources/js/Components/**/*.tsx` · `resources/css/*.css` · `.blade.php` ou Inertia render) — Claude **OBRIGATORIAMENTE** abre Chrome MCP + screenshot + relata o que viu **ANTES de declarar "pronto"/"deployed"/"funcionando"**. **Sem screenshot post-deploy = não está pronto**. Wagner regra reincidente Tier 0: *"sempre estou tendo que fazer isso, os metodos de memória ainda não estão sendo garantidos"* — após `feedback-brave-mcp-primeiro-sempre.md` (instalado 2026-05-15) ter falhado em prevenir 6+ reincidências. Hook bloqueador: [`.claude/hooks/post-merge-ui-smoke-required.ps1`](../.claude/hooks/post-merge-ui-smoke-required.ps1) detecta `gh pr merge` em PR que tocou UI files e bloqueia próximas declarações `"pronto|deployed|funcionando|ao vivo|live em prod"` no chat até detectar uso de `mcp__computer-use__screenshot` ou `mcp__Claude_in_Chrome__*` nos últimos 5min. Escape: pra mudança backend-only OR docs, mencionar `<!-- no-ui-smoke: <razão> -->` no PR body. Skill pareada (Tier B): [`smoke-prod-evidence`](../.claude/skills/smoke-prod-evidence/SKILL.md).

Skill pareada (cultural, Tier B auto-trigger): [`.claude/skills/smoke-prod-evidence/SKILL.md`](../.claude/skills/smoke-prod-evidence/SKILL.md).

## Ideias avaliadas e DESCARTADAS — não re-propor (registro de regressões · equiv. §5 do PROCESSO_MEMORIA_CC)

> **Por que existe:** o método de design ([`prototipo-ui/PROCESSO_MEMORIA_CC.md`](../prototipo-ui/PROCESSO_MEMORIA_CC.md) §5) tem um registro de "tentei, reprovou, não repete". O mundo de **código** não tinha equivalente — então uma sessão futura **re-propõe ideia morta** e regride na prática. Este é o §5 do código (Opção B: sistema separado do design, mesmo princípio). **Antes de propor abordagem técnica, conferir aqui; se bater, eu mesmo barro e cito a entrada.**
>
> Estrutura de cada entrada (igual ao §5): **o que foi tentado · por que caiu · o limite (a variante parecida também é proibida)**. Append-only — nada sai daqui sem ADR explícito.

### 2026-06-05 — Roadmap/plano de evolução PARALELO a canon existente
- **O que foi tentado:** criar um roadmap de evolução (design + teste) num doc novo, em paralelo ao [`AUTOMATION-ROADMAP.md`](requisitos/_DesignSystem/AUTOMATION-ROADMAP.md) (que já tem Ondas) — e um roadmap de teste órfão sem casa.
- **Por que caiu:** viola o gate **T6 (DURO)** do método — *"evoluindo o que EXISTE, não criando paralelo; 1 tema = 1 doc"*. Dois roadmaps competindo pelo mesmo juiz = fragmentação (a dor que Wagner levantou: "não saber onde está nada").
- **O limite (variante também proibida):** qualquer doc de plano/roadmap/SPEC novo que **duplique** roadmap/SPEC/ADR já existente. Antes de criar plano, achar o canon dono do tema e **estender** (nova Onda/seção), nunca abrir paralelo.

### 2026-06-05 — Teste que deriva do CÓDIGO (tautológico) em vez do contrato
- **O que foi tentado:** property test (`FsmAuthorizationFlagPropertyTest`) cujas invariantes foram extraídas do que a classe **faz hoje**, não de um contrato independente ([ADR 0143](decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) + §"FSM Pipeline Canônico" deste arquivo).
- **Por que caiu:** teste derivado do código é tautológico — passa ✅ mesmo se o comportamento estiver errado vs a intenção, e **trava (catraca) o desvio** em vez de pegá-lo. Pior que não ter teste. (NÚCLEO invariante 4 do método: *"Casos = contrato de não-regressão"*.)
- **O limite (variante também proibida):** qualquer teste cuja asserção venha da implementação (ou do palpite do agente) e não de um contrato externo (SPEC / ADR / proibicoes / charter / casos). **Teste sem âncora de contrato citada = rejeitado** no gate pré-adoção (Peça 2).

### 2026-06-09 — Domínio de "locação" na Oficina (alucinação herdada do legado)
- **O que foi tentado:** tratar **locação de caçamba** como fluxo de negócio vivo da OficinaAuto — `order_type = 'locacao'`, KPI "locação ativa", colunas/labels "locada/disponível", componentes `Cacamba*`. Veio do enquadramento legado WR Sistemas, e o próprio [CC] já tratou como "legado vivo intencional".
- **Por que caiu:** veredito de Wagner (dono do negócio, soberano do domínio) 2026-06-09 — *"Pode apagar aluguel de caçamba e fundamentar para não voltar mais, eu não uso é alucinação"* + *"Eu decido que sim eu quero reparo."* A Oficina é **reparo/mecânica, ponto.** "Caçambas" é só o **nome comercial** do cliente Martinho (razão social), nunca conceito de domínio. Formalizado na [ADR 0265](decisions/0265-oficina-reparo-erradica-locacao.md) (errata que fecha o resíduo da [ADR 0194](decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)).
- **O limite (variante também proibida):** reintroduzir `locacao`/`locada`/`disponivel` como **conceito de negócio** (tipo de ordem, coluna de kanban, KPI, label de UI, enum de domínio). `order_type ∈ {manutencao, mecanica}`. **Agora é máquina, não memória:** o gate `dominio:check` ([ADR 0264](decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-4, fonte única `memory/dominio/oficina-auto.md`) **falha o CI** se um enum de migration divergir do dicionário. Exceção única: "Caçambas" como **nome/razão social do cliente** Martinho (string de negócio) = ok, não é regressão. O resíduo de schema ainda pendente (enum `order_type=locacao`, importer `normalizeOrderType`, FSM keys `disponivel/locada`) está catalogado em [RUNBOOK-erradicacao-locacao.md](requisitos/OficinaAuto/RUNBOOK-erradicacao-locacao.md) — exige Pest local (não-blind, Tier 0 live-prod Martinho biz=164).

## Sempre fazer

- ✅ **PT-BR em tudo** — texto, commit, comentário, label. Código em inglês ok; domínio negócio em PT (`Marcacao`, `Intercorrencia`, `BancoHoras`)
- ✅ **Cite a lei quando aplicável** — *Art. 66 CLT*, *Portaria 671/2021 Anexo I*, *LGPD Art. 7º*
- ✅ **Preserve imutabilidade** de marcações e movimentos de banco de horas
- ✅ **Mantenha `business_id` scopado** em todas queries (skill `multi-tenant-patterns` Tier A)
- ✅ **Escreva tests Pest** ao menos pra regras CLT (tolerâncias, intra/interjornada, HE) e isolamento multi-tenant
- ✅ **Antes de criar/mudar módulo, abra `Modules/Jana/`/`Repair/`/`Project/`** e imite. Se quiser divergir — registre ADR
- ✅ **Use stack de middlewares UltimatePOS** pra rotas web: `['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']`
- ✅ **`Inertia::defer()` DEFAULT em props caras** ([RUNBOOK-inertia-defer-pattern.md](requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md)) — toda prop com `paginate()`, `count()`, `with()` eager-load, aggregated query, Service call DB, subquery scalar, ou HTTP externo **DEVE** ser `Inertia::defer(fn () => $this->buildXxxPayload(...))`. Frontend wrap em `<Deferred data="..." fallback={skeleton}>`. Exceções (sempre eager): filters UI state, IDs/booleanos, config static, tokens curtos (~1ms), props target de partial reload (`thread`/`messages` no Inbox por ex). **Origem da regra:** D-14 incident 2026-05-15 — switch conversa Inbox sentia "carregando página inteira" (~300-800ms) apesar de partial reload existir, porque Controller executava queries todas mesmo com `only:[...]`. Fix: defer pula closures não-solicitadas. Pattern validado: 300ms → 50ms (-83%). Skill `inertia-defer-default` (Tier B) auto-trigger ANTES de Edit em qualquer Controller `Inertia::render(...)`.
- ✅ **`BRIEFING.md` atualizado em todo PR mergeado** que altere capacidades/diferenciais/UX de um módulo — skill `brief-update` (Tier B) auto-ativa ao terminar feature que toque `Modules/<X>/` + `resources/js/Pages/<X>/`. BRIEFING canônico em `memory/requisitos/<Modulo>/BRIEFING.md` mantém estado consolidado da capacidade (1 página executiva, atualizado por PR). Wagner enxerga estado real do módulo sem precisar pedir. Template em [memory/requisitos/_DesignSystem/BRIEFING-TEMPLATE.md](requisitos/_DesignSystem/BRIEFING-TEMPLATE.md). **Origem da regra:** Wagner 2026-05-15: "manter atualizado o briefing acho isso super necessário" + "ja era para ser assim sempre, é chato alterar algo no módulo e ter que avisar para fazer isso".
- ✅ **Verificar `gh pr checks <PR>` VERDE antes de declarar "PR pronto" / "feito" / propor merge** ([ADR 0094](decisions/0094-constituicao-v2-7-camadas-8-principios.md) §Princípio 7 Transparência + §Princípio 8 Confiabilidade com fallback). Workflow operacional pós-`gh pr create`: (1) rodar `gh pr checks <PR>`; (2) se 100% pass → declarar pronto; (3) se algum fail → investigar logs via `gh run view <ID> --log-failed` ANTES de pedir merge ao Wagner. **Anti-padrão catalogado sessão 2026-05-17:** abri PR #1031 + #1037 + declarei "PR aberto, próximo passo merge" sem rodar `gh pr checks`. Wagner descobriu CI vermelho ao tentar mergear (drift Waves 23-28 + falso-positivo PII pré-existentes em main). PR #1038 corrigiu drift; esta regra previne reincidência. Complementa §"Claim sem evidência" — aquela cobre smoke em prod, esta cobre CI antes de propor merge. Skill pareada Tier B auto-trigger: `smoke-prod-evidence` (descrição menciona "PR/commit acaba de ser mergeado e deploy SSH foi feito" — agora extendida pra cobrir pré-merge também).
