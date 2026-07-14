# Plano · Profissionalizar acesso do time (proteger fonte + controlar novos integrantes)

> **Status:** proposta (doc-only) — **decisão de topologia e execução são do Wagner** (pareia com R10 + regra "habilitar/desabilitar é decisão consciente").
> **Pergunta que originou:** *"como esconder o fonte? como controlar os funcionários novos?"* (Wagner, contexto: profissionalizar o oimpresso, time cresce de 5 → N pessoas).
> **Método:** cruzamento do canon do projeto (ADRs 0080/0081/0055/0057/0044/0030/0131/0093 + CODEOWNERS + branch-protection + `_INDEX-SECRETS.md` + tailscale-acl) com estado-da-arte 2026 de least-privilege / GitHub org / secrets management. Sem valores BRL, sem PII. Nada foi executado — este doc só propõe.
> **Não duplica:** estende o que já existe (Trust Tiers, Identity Mesh, RUNBOOK-branch-protection, RUNBOOK-credenciais-hierarquia, RUNBOOK-rotacao-segredos, RUNBOOK-acesso-ct100-testes-time, skill `oimpresso-team-onboarding`). Onde há RUNBOOK/ADR dono do tema, aponto pra ele.

---

## 0. Enquadramento honesto (ler antes de tudo)

**"Esconder o fonte" de um dev que precisa editá-lo é impossível.** Qualquer pessoa que abre o código pra trabalhar nele consegue lê-lo, copiá-lo e memorizá-lo. Ofuscação/minificação protege binário distribuído, não protege de quem tem o repositório. Não existe botão "código invisível pro dev". Quem promete isso está vendendo ilusão.

O que é **realmente** possível — e é o que este plano entrega — são três defesas que se somam:

| Defesa | O que faz | O que **não** faz |
|---|---|---|
| **(a) Segmentar acesso** (least privilege) | Cada pessoa vê/edita só o que o papel dela exige; júnior não toca dinheiro/fiscal/LGPD; ninguém tem prod por padrão | Não impede quem tem acesso legítimo de ler o que lhe cabe |
| **(b) Blindar o segredo** (não o código) | Tokens, chaves, certificados, senhas e a **camada de valores R$** ficam fora do repo, em cofre, rotacionáveis | Não esconde a lógica de negócio de quem edita aquele módulo |
| **(c) Controlar por governança** | Branch protection + review obrigatório + audit trail + gates de CI tornam toda mudança rastreável e revisável | Não substitui contrato/NDA — a defesa **jurídica** do IP é o contrato, não o técnico |

**A tradução da pergunta do Wagner:**
- *"esconder o fonte"* → na prática = **(1) fechar a exposição atual** (o repo está **público** hoje — ver §1) + **(2) isolar as joias da coroa** das pessoas que não precisam delas.
- *"controlar os funcionários novos"* → **(3) least-privilege por papel + (4) guarda-corpos automáticos (branch protection/review/gates) + (5) audit trail + (6) offboarding com revogação**.

**O que PODE ser genuinamente isolado dos novos** (as "joias da coroa" = o IP real):
- Prompts/lógica da **Jana** (IA + memória) — `Modules/Jana/`
- Lógica **multi-tenant Tier 0** (`business_id` global scope — [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- **Tokens MCP** + governança do MCP server ([ADR 0055](../../decisions/0055-self-host-team-plan-equivalente-anthropic.md)/[0057](../../decisions/0057-tela-team-admin-regras-governanca-tokens-mcp.md))
- **Integrações fiscais/financeiras** (NFe, boleto, gateway) — `Modules/NfeBrasil/`, `Modules/Financeiro/`, `Modules/RecurringBilling/`
- A **camada de valores R$** — Wagner já estabeleceu (2026-06-08): Felipe/Maiara/Luiz veem **escopo/contagem**, NÃO **valores monetários**; foi feito `git filter-repo` em 5.033 commits pra redigir R$ do histórico. **Este plano estende esse princípio** (ver §2.4 e proibições `memory/proibicoes.md` §"NUNCA commitar valores BRL").

---

## 1. Estado atual — o diagnóstico brutal

> Antes de propor: o que já está de pé (muita coisa) e o buraco aberto (um, grande).

### 1.1 🔴 O buraco: o repositório é PÚBLICO

`github.com/wagnerra23/oimpresso.com` está com visibilidade **PUBLIC** (verificado 2026-07-13 via `gh repo view --json visibility` → `"PUBLIC"`).

Consequência crua: **hoje, qualquer pessoa na internet** — não só os "funcionários novos" — clona o ERP inteiro, com todos os módulos (Jana, multi-tenant, fiscal) **e o histórico completo**, que contém segredos vazados marcados 🔴 em [`_INDEX-SECRETS.md`](../../_INDEX-SECRETS.md) (Meilisearch master key, Vaultwarden ADMIN_TOKEN, etc.). O próprio [RUNBOOK-rotacao-segredos](RUNBOOK-rotacao-segredos.md) §4 já recomenda: *"Recomendado: GitHub → Settings → Change visibility → Private. Enquanto for público, qualquer um clona o histórico e lê tudo."*

**Perguntar "como esconder o fonte dos novos" enquanto o fonte está aberto pro mundo é trancar a gaveta com a porta de casa escancarada.** Tornar o repo privado é a ação #1 — mais barata, maior alavanca, e pré-requisito de tudo abaixo.

⚠️ Ir pra privado **não** apaga o que já foi exposto (git é append-only; forks/clones/caches já existem). Estanca a exposição **contínua** e **obriga** a rotação dos segredos 🔴 (§2.3). As duas coisas andam juntas.

### 1.2 🔴 O time ainda não é colaborador do repo

O [`.github/CODEOWNERS`](../../../.github/CODEOWNERS) diz explicitamente: *"TIME AINDA NÃO É COLABORADOR DO REPO. Só @wagnerra23 é dono válido hoje. GitHub IGNORA code owner sem acesso de escrita."* Os `@handle` de Eliana/Felipe/Maiara estão como `# TODO`.

Ou seja: o modelo de permissão de review foi **desenhado** mas ainda **não vale de verdade** — porque não há mais ninguém com write pra ser code owner. Adicionar o time (com escopo certo) é parte do "controlar os novos", não o oposto.

### 1.3 🟢 O que já está maduro (não reinventar — reusar)

O oimpresso já construiu uma camada de governança forte. O plano **mapeia** os novos integrantes sobre ela:

| Peça pronta | Onde | Cobre |
|---|---|---|
| **Trust Tiers L0–L4** | [ADR 0080](../../decisions/0080-trust-tiers-operacional-audit-findings.md) + [`governance/TRUST-TIERS.md`](../../governance/TRUST-TIERS.md) | Quem pode tocar o quê, por tier. Tem `review_trigger` literal: *"Time crescer >10 pessoas — refinamento dos tiers necessário"* |
| **Identity Mesh** (`mcp_actors`) | [ADR 0081](../../decisions/0081-identity-mesh-mcp-actors.md) | Cada actor com `modules_write/read/blocked`, `actions_blocked`, `trust_level`, `revoked_at`/`revoked_by` |
| **Branch protection `main`** | [RUNBOOK-branch-protection](RUNBOOK-branch-protection.md) + [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json) | 22 required checks (Tier-0 guards, PII scan, secret scan gitleaks, multi-tenant, fiscal), `require_code_owner_reviews=true`, no force-push, linear history, `enforce_admins` |
| **CODEOWNERS por path sensível** | [`.github/CODEOWNERS`](../../../.github/CODEOWNERS) | Dinheiro/fiscal, LGPD/Jana, segredos, infra/CI/governança já mapeados (falta preencher @handles) |
| **Vaultwarden** (cofre) | [ADR 0044](../../decisions/0044-vaultwarden-self-hosted-cofre.md) + [RUNBOOK-credenciais-hierarquia](RUNBOOK-credenciais-hierarquia.md) | Segredos fora do git; hierarquia de 5 tiers de credencial |
| **Service account `claude-agent`** (Vaultwarden API) | [`_INDEX-SECRETS.md`](../../_INDEX-SECRETS.md) + `scripts/infra/get-secret.sh` | Mecanismo pronto (`bw` CLI + `get-secret.sh`), **falta setup 1× do Wagner** (Opção B do gap 2026-05-28) |
| **Índice de secrets** | [`_INDEX-SECRETS.md`](../../_INDEX-SECRETS.md) | Uma linha por segredo, só ponteiros, status de rotação |
| **Least-privilege de infra (Tailscale)** | [`tailscale-acl.hujson`](tailscale-acl.hujson) + [RUNBOOK-acesso-ct100-testes-time](RUNBOOK-acesso-ct100-testes-time.md) | `group:admin` (Wagner, total) vs `group:suporte` (Maiara/Felipe, **só CT 100, só SSH, não-root, sessão gravada** via tsrecorder) |
| **SSH hardening CT** | [RUNBOOK-ssh-hardening-ct](RUNBOOK-ssh-hardening-ct.md) | Só chave, sem senha, público bloqueado no NAT |
| **Rotação de segredos** | [RUNBOOK-rotacao-segredos](RUNBOOK-rotacao-segredos.md) | Receita Wagner-only + push protection |
| **Redação de valores R$** | proibições §"NUNCA commitar valores BRL" | Time vê escopo/contagem, não valores; `git filter-repo` já rodado em 5.033 commits |
| **Onboarding MCP** | skill [`oimpresso-team-onboarding`](../../../.claude/skills/oimpresso-team-onboarding/SKILL.md) + `MEMORY_TEAM_ONBOARDING.md` | Conectar dev novo ao MCP server (token pessoal via Vaultwarden) |
| **"Credenciais jamais em git"** | [ADR 0030](../../decisions/0030-credenciais-jamais-em-git.md) | O que pode/não pode ir pro repo |

**Conclusão do diagnóstico:** o oimpresso não precisa *construir* controle do zero. Precisa **(1) fechar o repo, (2) ligar o que já está desenhado** (adicionar time como colaborador com escopo, preencher CODEOWNERS, aplicar reviews≥1) **e (3) fechar dois gaps operacionais** (offboarding formal + setup 1× do cofre).

---

## 2. Parte 1 — Proteção de fonte / segredo (topologias A/B/C)

> **Fork material que só o Wagner decide.** As três topologias não são mutuamente exclusivas em teoria, mas a escolha da **casa principal do código** (A vs C) e do **grau de isolamento das joias** (quanto de B) muda o roadmap. Apresento as três com trade-offs concretos + recomendação + critério.

### Topologia A — GitHub privado + Org + Teams + CODEOWNERS (fica 1 repo, segmenta por review/ownership)

Mantém o monólito modular num único repositório **privado**, dentro de uma **GitHub Organization**, com times (Teams) e permissão por papel; segmentação de escrita/sensibilidade via CODEOWNERS + branch protection.

- **Como fica o acesso:** todo dev com write vê todo o código (é a natureza do monorepo), mas **só consegue mergear** onde a governança permite. Módulos sensíveis (Jana, Financeiro, NfeBrasil, infra, ADRs) exigem review do code owner (Wagner/Eliana/Felipe conforme CODEOWNERS §1.3). O resto: PR verde é mergeável.
- **Prós:** menor atrito (é o que já existe, só falta fechar+ligar); reusa 22 required checks + Trust Tiers direto; DX intacta (1 clone, 1 CI, 1 histórico); GitHub Actions/secret scanning/push protection sem migração; onboarding trivial (add collaborator).
- **Contras:** **não esconde o código-fonte de quem tem write** — só controla merge. A "leitura" das joias não é bloqueada pra um dev com acesso ao repo. Mitiga-se com B (extrair) se um subconjunto do IP não pode ser lido por ninguém abaixo de L1.
- **Custo/esforço:** baixo. Privado é grátis no plano atual; Org Free permite times ilimitados; permissão por time é nativa.
- **Já-existe:** CODEOWNERS, branch protection, Trust Tiers, required checks.

### Topologia B — Extrair as "joias da coroa" pra repos privados separados / submodules

Tira o IP mais sensível do monólito e coloca em **repositórios privados próprios** com lista de acesso restrita (só L0/L1), referenciados via **git submodule** ou pacote/artefato. O ERP grande fica acessível ao time; as joias, não.

- **Candidatos a joia:** prompts/pesos e lógica de decisão da Jana (`Modules/Jana/Ai/` — os *prompts* e a orquestração dos Agents, não o CRUD); regras fiscais proprietárias; segredos de integração já **estão** fora (cofre) — B é sobre **código/lógica**, não segredo.
- **Prós:** isolamento **de leitura** real — um júnior nunca clona o que está no repo restrito. É a única topologia que "esconde o fonte" de um subconjunto de devs de fato.
- **Contras:** custo de DX alto — submodules são notoriamente chatos (sync, CI que precisa de credencial pros dois repos, PRs cruzados, onboarding mais complexo); risco de "muro no meio do módulo" quebrar o build de quem não tem a joia; a fronteira precisa ser **limpa** (interface estável) senão vira dor recorrente. Só compensa pra um núcleo **pequeno e estável**.
- **Custo/esforço:** médio-alto (recorrente). Vale a pena **apenas** pro subconjunto que realmente não pode ser lido por ninguém abaixo de L1 — não pro módulo inteiro.
- **Já-existe:** parcialmente — a Constituição já trata `Modules/Connector` e `Modules/Superadmin` como L0-only (TRUST-TIERS §1); B formalizaria isso em fronteira de repo.

### Topologia C — Self-hosted git (Gitea / GitLab CE / Forgejo no CT 100)

Migrar o hospedagem do código pra um servidor próprio (o CT 100 já roda a stack self-host: MCP, Centrifugo, Vaultwarden, Langfuse), com RBAC granular sob controle total.

- **Prós:** controle fino de acesso (RBAC por repo/branch/path em GitLab CE; branch protection granular); dados no servidor da empresa (data residency); air-gap possível; sem depender de política/preço do GitHub.
- **Contras:** **perde o ecossistema GitHub** que o projeto usa pesado — 60+ workflows GitHub Actions, secret scanning/push protection nativo, `gh` CLI em todos os fluxos, integração Claude Code/PR. Migrar CI é trabalho grande e recorrente. Vira **mais infra pra operar** (backup, uptime, upgrade do próprio git server) — e a [AUDITORIA-OPS-DR-2026-07](AUDITORIA-OPS-DR-2026-07.md) já apontou P0 de backup/DR no CT 100. Adicionar o git aí aumenta o SPOF.
- **Custo/esforço:** alto (setup + recorrente). Só justifica se houver exigência forte de air-gap/residência ou desconforto estratégico com GitHub — não é o caso hoje.
- **Já-existe:** a stack self-host e o padrão de operar containers no CT 100.

### 2.4 Recomendação clara + critério

**Recomendo A agora, B cirúrgico depois, C não (por ora).**

1. **A (fazer já):** fechar o repo (privado) + ligar Org/Teams/CODEOWNERS/reviews. Resolve 90% do problema real ("controlar os novos" + estancar exposição) com o menor atrito, reusando tudo que já existe. É pré-requisito de qualquer outra topologia.
2. **B (avaliar em seguida, escopo mínimo) — com ressalva forte do §2.6:** extrair só código **medidamente extraível** (medir por grep, não pelo nome "é módulo") que alguém precise *não ler*. ⚠️ A revisão adversarial do §2.6 provou que **Jana/Financeiro/NfeBrasil NÃO são extraíveis** (a Jana hospeda o global scope multi-tenant importado por ~207 arquivos). Candidatos reais a B são verticais folha (`OficinaAuto`/`ComunicacaoVisual`) — que **não são IP secreto**, logo B raramente se aplica. Nunca "por precaução"; só com um dev concreto a excluir **e** extração comprovada.
3. **C (não agora):** o ganho (RBAC granular + residência) não paga o custo (perder GitHub Actions/ecossistema + mais SPOF no CT 100 já apertado de DR). Reavaliar só se (a) exigência contratual de air-gap/residência aparecer, ou (b) o time passar de ~15 pessoas com necessidade de RBAC por-path que o GitHub não dá.

**Critério de decisão (o que faz mudar de A→B ou A→C):**

| Se... | Então |
|---|---|
| Entra dev que **não pode ler** um subconjunto de IP, mas precisa mexer no resto | Acionar **B** só pra esse subconjunto (extrair pra repo restrito) |
| Exigência de **air-gap / dados só no servidor da empresa** (contrato, regulação) | Reavaliar **C** (GitLab CE no CT 100) — com plano de DR antes |
| Nenhum dos dois | Ficar em **A** — é suficiente e mais barato |

**Sobre segredo (transversal às 3 topologias, fazer independente da escolha):**
- Segredo **NUNCA** no repo ([ADR 0030](../../decisions/0030-credenciais-jamais-em-git.md)) — cofre Vaultwarden ([ADR 0044](../../decisions/0044-vaultwarden-self-hosted-cofre.md)), hierarquia em [RUNBOOK-credenciais-hierarquia](RUNBOOK-credenciais-hierarquia.md).
- ⚠️ **Limite honesto do Vaultwarden:** ele é cofre de **senhas/tokens humanos** (org + collections + roles) — **não** é secrets manager de *aplicação* (não tem machine accounts / API service-to-service; o Bitwarden Secrets Manager não foi liberado sob a licença do Vaultwarden). O service account `claude-agent` funciona porque usa o lado "cofre de senhas" via `bw` CLI. Se um dia o time precisar injetar segredos em CI/deploy por conta de serviço self-hosted, o encaixe natural no CT 100 é **Infisical** (open-source, self-host) — **não** HashiCorp Vault (over-engineering pra ~10 pessoas). Não é ação agora; é a direção quando `get-secret.sh` + Vaultwarden não bastarem.
- **Rotacionar** os 🔴 do [`_INDEX-SECRETS.md`](../../_INDEX-SECRETS.md) (Meilisearch, Vaultwarden ADMIN_TOKEN, Hostinger DNS token) — obrigatório junto com o "repo privado" (§1.1).
- **Fechar o gap do cofre (Opção B, 2026-05-28):** Wagner faz o setup 1× do service account `claude-agent` (criar user + API key + colar credenciais em `/root/.vaultwarden-agent-creds`), destravando `get-secret.sh` pra todo secret futuro. Elimina o "Wagner é helpdesk de token".
- **Camada de valores R$:** manter e reforçar a regra 2026-06-08 (time vê escopo, não valores) — considerar o hook `block-brl-values-in-memory.ps1` já cogitado nas proibições.
- **Secret scanning:** o gate `gitleaks` já é required no PR; ligar também **GitHub secret scanning + push protection** no repo (nativo, grátis em privado) como segunda camada.

### 2.5 "Vão poder ver e copiar tudo — as máquinas e os fontes?" (a verdade sem ilusão)

Pergunta recorrente do Wagner. Resposta separada por superfície, porque é **diferente** pra máquina e pra código.

**Máquinas → NÃO.** Infra é genuinamente segmentável. Um integrante novo **não alcança** produção (Hostinger) nem o Proxmox (hypervisor) — zero acesso. No CT 100 entra só no staging, **como não-root e com a sessão gravada**. Ninguém "copia as máquinas". Já está montado ([`tailscale-acl.hujson`](tailscale-acl.hujson) + SSH só-chave + Proxmox atrás do NAT). Mudar a estrutura (criar/derrubar container, rede, Proxmox) exige ser **admin** — e admin é só o Wagner.

**Código → depende de QUAIS repositórios você libera.** Verdade dura: **quem tem acesso de leitura a um repositório pode clonar tudo daquele repo e copiar pra onde quiser.** No git, *clonar já é copiar* — e **não existe "ver só metade do repo"** (o GitHub é tudo-ou-nada na leitura de um repositório; ver Apêndice §2). Logo:
- Se está **tudo num repo só** e você dá acesso → sim, copiam tudo.
- A **única** forma de impedir que copiem um pedaço é esse pedaço morar em **outro repositório sem acesso deles**.

Ou seja: *"esconder e liberar só o necessário"* no código = escolher **quais repositórios cada pessoa enxerga**, não "esconder pastas dentro do repo".

**A analogia da loja:** o funcionário entra no salão e no estoque onde trabalha (vê e pega o que está ali); o **cofre da fórmula** fica numa sala sem a chave dele; e o **contrato/NDA** é a lei sobre o que ele faz com o que viu no salão. Least-privilege = dar a chave só das salas certas. NDA = a defesa jurídica do resto.

**O que NÃO dá pra prevenir tecnicamente (honestidade):** não existe como impedir que alguém com acesso **legítimo** a um repo copie aquele repo. As defesas contra o *uso indevido* dessa cópia são **(1)** least-privilege (limita **quanto** cada um pode copiar — só os repos do papel), **(2)** audit/detecção (clone anômalo / download em massa vira alerta — "trust but verify", Apêndice §1) e **(3)** NDA + cessão de IP (a defesa **jurídica** real — competência da Eliana[E]). É contrato + auditoria, não uma muralha técnica, que protege o núcleo.

### 2.6 Quantos repositórios? — e por que a Jana NÃO é uma joia extraível (revisão adversarial 2026-07-13)

> Uma primeira versão deste plano sugeriu 2 repos (`oimpresso-nucleo` + `oimpresso-joias` com a Jana dentro). Uma **revisão adversarial** (subagente cético, grep no `origin/main`) **refutou** a premissa. Registro a correção — deixar canon errado é pior que não ter.

**Resposta:** **1 repositório agora** (só torná-lo privado). **+1 em 6–12 meses** — o dos **daemons/infra do CT 100** (Baileys, crons, Proxmox/Docker), justificado por **fronteira de runtime real** ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)), **não** por segredo. **Zero repos "só-Wagner".**

**Por que a Jana não sai (prova por grep):**
- `Modules\Jana\Scopes\ScopeByBusiness` — o **global scope multi-tenant Tier 0** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — é importado por **~207 arquivos em 30 módulos**; `PiiRedactor` (LGPD) por **~90**; o core (`app/`) importa Jana em **~23** arquivos.
- A "joia" que se quer esconder **é a fundação multi-tenant do ERP inteiro**, não um pedaço isolável. Extrair pra repo separado (Nível 2) → **o build quebra pra todo o time**, ou pior, o isolamento entre empresas **some silenciosamente** (o pior bug possível).
- Nível 3 (serviço) também não resolve: `ScopeByBusiness`/`PiiRedactor` rodam **in-process no Eloquent/pipeline**, não são endpoint; e pôr lógica fiscal atrás de HTTP num CT 100 que **já é P0 de DR** ([AUDITORIA-OPS-DR-2026-07](AUDITORIA-OPS-DR-2026-07.md)) vira **SPOF de faturamento** (CT 100 cai → ROTA LIVRE, 99% do volume, para de vender/emitir NFe).
- O que **seria** extraível (verticais folha `OficinaAuto`/`ComunicacaoVisual`) **não é IP secreto** — logo, não há o que esconder ali.

**A divisão pode ser ilusória mesmo quando feita:** a lógica vaza pela **interface** (request/response, OpenAPI, DTOs tipados, migrations `mcp_*`/`fin_*`, enums de domínio). Regra fiscal BR é **lei pública** (CONFAZ/SINIEF), não segredo. Prompt de LLM vaza no primeiro log/telemetria (Langfuse está no CT 100).

**Correção de prioridade (o erro maior):** debater topologia de repo enquanto o repo está **público com segredo vivo no histórico** é rearranjar cadeiras. A ordem correta está na Fase 0 (§4): **rotacionar segredo → privado → fechar DR do CT 100 → Nível 1 + NDA + branch protection + CODEOWNERS + review**. Topologia de repo é o **último** passo, e provavelmente **nunca** pra Jana. A defesa do IP da Jana é **contrato + auditoria**, não arquitetura.

> ⚠️ Isto **revisa a Topologia B do §2**: ela vale só pra código **medidamente extraível** (verificar por grep, não pelo nome "é módulo") e que alguém precise *não ler* — condição que hoje **não existe** pra Jana/Financeiro/NfeBrasil (entrelaçados com a fundação).

---

## 3. Parte 2 — Controle dos novos integrantes (onboarding, guarda-corpos, offboarding)

### 3.1 Matriz de papel → permissão GitHub (estende TEAM.md + Trust Tiers)

O TEAM.md §3 já tem a matriz "quem pode fazer o quê" e as regras duras (L não mergeia sozinho; M não deploya sozinha; E não mexe em Jana LGPD). Trust Tiers dá L0–L4. Falta o mapa pra **permissão de repositório GitHub** (read/triage/write/maintain/admin):

| Pessoa | Trust Tier | Permissão GitHub sugerida | Code owner de (CODEOWNERS) | Merge sem Wagner? |
|---|---|---|---|---|
| **Wagner [W]** | L0/L1 | **Admin** (dono) | tudo sensível | — (é o aprovador) |
| **Felipe [F]** | L2 | **Write** | + Jana/LGPD (futuro, com estudo) | Sim em paths livres; não em sensíveis |
| **Maiara [M]** | L2 | **Write** | + SRS (deprecação) | Sim em paths livres; não em sensíveis |
| **Eliana [E]** | L3 | **Write** (escopo fiscal/financeiro) | + Financeiro/NfeBrasil/RecurringBilling | Sim no domínio dela após review; não em Jana |
| **Luiz [L]** | L2 (pair) | **Write** — mas **nunca mergeia sozinho** (regra dura TEAM.md) | nenhum (júnior) | **Não** — sempre Felipe/Wagner aprovam |
| **Novo júnior/contratado** | L3 conservador (default) | **Triage** ou **Write** limitado | nenhum | **Não** — review obrigatório |

> **Princípio:** permissão de repo começa no **mínimo** (Triage/Write, nunca Admin) e sobe por mérito, igual ao "plano de evolução do Luiz" já no TEAM.md (após N cycles sem hotfix, promove). Admin é só do Wagner (L0).

**Para ligar isso (todos são cliques do Wagner):**
1. Criar/usar GitHub Org (recomendado — dá Teams; hoje é repo pessoal).
2. Adicionar cada pessoa como collaborator/membro do time com a permissão da coluna acima.
3. Preencher os `@handle` no CODEOWNERS (Felipe=`felipewr2`?, Maiara=`wr2backup`?, Eliana=?) — confirmar os handles com cada um.
4. Trust Tier de cada novo actor entra no `mcp_actors` ([ADR 0081](../../decisions/0081-identity-mesh-mcp-actors.md)) com `modules_write/read/blocked`.

### 3.2 Guarda-corpos automáticos (branch protection + review + gates)

Já quase tudo pronto (§1.3). O que falta **ligar** quando o time ganhar write:

- ✅ **Já ativo:** 22 required checks (incl. Tier-0 guards, PII scan, secret scan, multi-tenant, fiscal), no force-push, linear history, `require_code_owner_reviews=true`, `enforce_admins`.
- 🔲 **Ligar:** `required_approving_review_count: 1` (o `_INDEX-SECRETS` e as notas de governança marcam "reviews≥1 quando o time ganhar write" como pendente — hoje CODEOWNERS delega "merge verde sem review" ao Claude porque **não há revisor humano com write**). Assim que houver, subir pra ≥1 nos paths sensíveis (o CODEOWNERS já força isso onde tem owner).
- 🔲 **Avaliar signed commits:** `require_signed_commits` na branch protection (assinatura GPG/SSH). Ganho: autoria verificável (rastreabilidade pra júnior/contratado). Custo: cada dev configura chave de assinatura uma vez. Recomendo **ligar** — é barato e o commit signing por SSH é simples em 2026. Casa com o audit trail.
- 🔲 **2FA obrigatório na Org:** exigir 2FA de todos os membros (política nativa da Org). **Obrigatório** antes de dar write a qualquer pessoa.

O júnior fica cercado por construção: não mergeia sozinho (CODEOWNERS + review≥1), não passa gate Tier-0/PII/secret quebrado, não force-pusha, não vê valor R$, e toda ação fica no `mcp_audit_log` + git blame assinado.

### 3.3 Least-privilege de infra (deploy/SSH/prod vs staging)

- **Deploy:** produção (Hostinger, deploy SSH) é **Wagner/Felipe** apenas (TEAM.md: M com supervisão; L/E não). Deploy keys por-serviço, read-only onde possível.
- **CT 100 (testes/staging):** já segmentado pelo [`tailscale-acl.hujson`](tailscale-acl.hujson) — `group:suporte` (Maiara/Felipe) entra **só no CT 100, só SSH, como usuário não-root, sessão gravada** (tsrecorder). Novo integrante entra em `group:suporte`, nunca `group:admin`. Ver [RUNBOOK-acesso-ct100-testes-time](RUNBOOK-acesso-ct100-testes-time.md).
- **Prod vs staging:** testes rodam no CT 100 staging (`oimpresso-staging`), nunca local nem Hostinger (proibições Tier 0). Júnior **nunca** recebe SSH de produção.
- **2FA/MFA** em GitHub (org), Hostinger (hPanel), Vaultwarden — obrigatório pra todos.

### 3.4 Audit + trilha (o controle de qualidade que já é enforcement)

O "controle dos novos" em grande parte **já acontece** por construção:
- **`mcp_audit_log`** (append-only, trigger MySQL de imutabilidade) registra toda ação MCP por actor.
- **Regra "mexeu, registra"** (proibições Tier 0) + workflow 3 fases (PRÉ-FLIGHT/DURING/POST) — para júnior isso vira: leu SPEC/RUNBOOK antes, commitou incremental, abriu PR.
- **Gates de CI** (60+ workflows) são o revisor automático incansável — o júnior não consegue mergear código que viole multi-tenant, vaze PII/CPF, ou quebre um contrato de tela.
- **git blame + commits assinados** (se §3.2 ligar signed commits) = autoria rastreável.

O que resta é humano: **review≥1** nos paths sensíveis (§3.2) e a matriz de quem-aprova-quem (TEAM.md §3).

### 3.5 Offboarding (o gap real — hoje não existe checklist)

Quando alguém sai (ou um contratado termina), **não há checklist canônico de revogação**. Este é o gap operacional mais concreto. Proposta de checklist (a virar `RUNBOOK-offboarding-time.md` — ver roadmap §4):

1. **GitHub:** remover collaborator/membro da Org; revisar se era code owner (repassar).
2. **MCP token:** revogar em `/copiloto/admin/team` (`copiloto:mcp-token:revogar`) → `mcp_tokens.revoked_at` ([ADR 0057](../../decisions/0057-tela-team-admin-regras-governanca-tokens-mcp.md)).
3. **Identity Mesh:** marcar `mcp_actors.revoked_at` + `revoked_by_actor_id` ([ADR 0081](../../decisions/0081-identity-mesh-mcp-actors.md)).
4. **Tailscale:** remover do `group:suporte` no [`tailscale-acl.hujson`](tailscale-acl.hujson) + revogar device no painel.
5. **SSH:** remover chave de `~/.ssh/authorized_keys` (Hostinger + CT 100).
6. **Vaultwarden:** revogar acesso/compartilhamentos; **rotacionar** todo segredo compartilhado que a pessoa conhecia (senhas de serviço, tokens que ela usava) — [RUNBOOK-rotacao-segredos](RUNBOOK-rotacao-segredos.md).
7. **hPanel/Hostinger, 2FA, gateways:** revogar acessos administrativos.
8. **Registrar** o offboarding (data, quem, o que foi revogado/rotacionado) — trilha.

> **Regra de ouro do offboarding:** *revogar acesso é metade; a outra metade é **rotacionar** todo segredo compartilhado que a pessoa pôde ver.* Chave revogada não desfaz uma chave copiada.
>
> ⚠️ **Desabilitar a conta NÃO revoga tokens/PATs/deploy keys** — são credenciais de máquina que autenticam independentes do login e **seguem funcionando** até serem revogadas explicitamente (por isso os passos 2–7 são um a um, não "basta remover a pessoa"). É o item que mais falha no offboarding da indústria (§Apêndice §7).

### 3.6 Contrato / NDA / propriedade intelectual (aponta pra Eliana)

A defesa **jurídica** do IP — a única que realmente "protege o fonte" de quem legitimamente o edita — é **contratual**: NDA + cláusula de cessão de propriedade intelectual (IP assignment) + confidencialidade, no contrato de cada integrante (CLT ou PJ/contratado). **Não redijo jurídico aqui** — a **Eliana[E]** é advogada do time e é a pessoa certa pra estruturar isso. Item de ação, não de engenharia:
- [ ] Wagner + Eliana definem modelo de NDA + IP assignment pra novos integrantes (CLT e PJ).
- [ ] Assinatura vira **pré-requisito** do onboarding técnico (nenhum acesso antes do contrato).

---

## 4. Parte 3 — Roadmap por fases

> **HITL** = clique/decisão do Wagner (não automatizável — R10). **Auto** = agente/CI executa. Legenda esforço: 🟢 baixo · 🟡 médio · 🔴 alto. "Já existe" = não construir, só usar/ligar.

### Fase 0 — Estancar (fazer primeiro, dias)

| Ação | Tipo | Esforço | Estado | Desbloqueia |
|---|---|---|---|---|
| **Tornar o repo privado** (`gh repo edit --visibility private` ou UI) | **HITL** | 🟢 | 🔲 falta | Tudo — é pré-requisito |
| **Rotacionar segredos 🔴** do `_INDEX-SECRETS` (Meilisearch, Vaultwarden ADMIN_TOKEN, Hostinger DNS) | **HITL** | 🟡 | 🔲 falta | Fecha exposição real; [RUNBOOK-rotacao-segredos](RUNBOOK-rotacao-segredos.md) pronto |
| **Ligar GitHub secret scanning + push protection** (nativo, grátis em privado) | **HITL** | 🟢 | 🔲 falta | 2ª camada anti-vazamento (gitleaks já é o 1º) |

### Fase 1 — Ligar o que já está desenhado (semana 1)

| Ação | Tipo | Esforço | Estado |
|---|---|---|---|
| Criar GitHub **Org** + Teams (ou usar repo pessoal com collaborators, se preferir simples) | **HITL** | 🟢 | 🔲 |
| Adicionar time como collaborator com **permissão mínima** por papel (§3.1) | **HITL** | 🟢 | 🔲 (CODEOWNERS aponta que ninguém é collaborator ainda) |
| **2FA obrigatório** na Org antes de dar write | **HITL** | 🟢 | 🔲 |
| Preencher `@handle` no [CODEOWNERS](../../../.github/CODEOWNERS) (confirmar handles) | Auto (PR) + **HITL** confirma | 🟢 | 🔲 (hoje `# TODO`) |
| Ligar `required_approving_review_count: 1` na branch protection | **HITL** | 🟢 | 🔲 (pendente "quando time ganhar write") |
| Cadastrar cada actor em `mcp_actors` (Trust Tier + modules_write/read/blocked) | Auto propõe + **HITL** aprova | 🟡 | 🔲 |

### Fase 2 — Fechar gaps operacionais (semana 2)

| Ação | Tipo | Esforço | Estado |
|---|---|---|---|
| **Setup 1× do cofre** (service account `claude-agent`, Opção B) — destrava `get-secret.sh` | **HITL** (setup único) | 🟡 | 🟡 mecanismo pronto, falta Wagner colar credenciais |
| Escrever **`RUNBOOK-offboarding-time.md`** (checklist §3.5) | Auto (PR) | 🟢 | 🔲 **gap — não existe hoje** |
| Avaliar/ligar **signed commits** (SSH signing) na branch protection | **HITL** decide + Auto documenta | 🟡 | 🔲 |
| Estender skill [`oimpresso-team-onboarding`](../../../.claude/skills/oimpresso-team-onboarding/SKILL.md) pra cobrir **provisionamento GitHub + 2FA + NDA-first**, não só MCP | Auto (PR) | 🟡 | 🟡 cobre MCP; falta o resto |
| **NDA + IP assignment** (Wagner + Eliana) — pré-requisito de onboarding | **HITL** (jurídico, Eliana) | 🟡 | 🔲 |

### Fase 3 — Isolamento avançado (só se o critério §2.4 disparar)

| Ação | Tipo | Esforço | Estado |
|---|---|---|---|
| **Topologia B:** extrair código **medidamente extraível** (vertical folha) pra repo restrito — **NÃO** Jana/Financeiro/NfeBrasil (§2.6: entrelaçados com a fundação) | **HITL** decide + Auto executa | 🔴 | ⏸️ raro — só com dev concreto a excluir + extração comprovada por grep |
| **Repo dos daemons/infra CT 100** (Baileys, crons, Proxmox/Docker) — fronteira de runtime real, não segredo | **HITL** decide + Auto executa | 🟡 | ⏸️ 6–12 meses (o 2º repo que faz sentido de verdade — §2.6) |
| **Topologia C:** self-host git (GitLab CE/Gitea no CT 100) — **precede** plano de DR (AUDITORIA-OPS-DR §P0) | **HITL** decide | 🔴 | ⏸️ só se air-gap/residência exigir |

### O que já existe (não construir)

Branch protection + 22 required checks · CODEOWNERS (estrutura) · Trust Tiers L0–L4 · Identity Mesh `mcp_actors` · Vaultwarden + hierarquia de credenciais · `_INDEX-SECRETS` · Tailscale ACL least-privilege + session recording · SSH hardening · rotação de segredos · redação de valores R$ · onboarding MCP · secret scan gitleaks no CI · regra "mexeu, registra" + workflow 3 fases + `mcp_audit_log` append-only.

---

## 5. Decisão que só o Wagner toma (resumo do fork)

1. **Topologia principal:** A (privado + Org/Teams/CODEOWNERS) — recomendada — vs C (self-host). → **recomendo A**; C só se air-gap/residência exigir.
2. **Quantos repositórios (§2.6):** **1 agora** (privado) + **1 em 6–12 meses** (daemons/infra CT 100, por runtime). **Zero repos "só-Wagner"** — a Jana não é extraível (grep: global scope multi-tenant em ~207 arquivos). → **recomendo 1 agora**; topologia de repo é o último passo, depois da rotação de segredo + DR.
3. **GitHub Org sim/não:** Org dá Teams (mais escalável) vs repo pessoal com collaborators (mais simples). → **recomendo Org** pra profissionalizar.
4. **Signed commits obrigatórios:** sim/não. → **recomendo sim** (barato, rastreabilidade).
5. **Ordem/timing das fases** e quando adicionar cada pessoa.

Nada acima foi executado. Ao aprovar, a Fase 0 (privado + rotação) é a primeira e mais urgente.

---

## Fontes

**Canon interno:** [ADR 0080](../../decisions/0080-trust-tiers-operacional-audit-findings.md) · [ADR 0081](../../decisions/0081-identity-mesh-mcp-actors.md) · [ADR 0055](../../decisions/0055-self-host-team-plan-equivalente-anthropic.md) · [ADR 0057](../../decisions/0057-tela-team-admin-regras-governanca-tokens-mcp.md) · [ADR 0044](../../decisions/0044-vaultwarden-self-hosted-cofre.md) · [ADR 0030](../../decisions/0030-credenciais-jamais-em-git.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0131](../../decisions/0131-tiering-memoria-canonico-local-segredo.md) · [`governance/TRUST-TIERS.md`](../../governance/TRUST-TIERS.md) · [`.github/CODEOWNERS`](../../../.github/CODEOWNERS) · [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json) · [`_INDEX-SECRETS.md`](../../_INDEX-SECRETS.md) · [`tailscale-acl.hujson`](tailscale-acl.hujson) · [RUNBOOK-branch-protection](RUNBOOK-branch-protection.md) · [RUNBOOK-credenciais-hierarquia](RUNBOOK-credenciais-hierarquia.md) · [RUNBOOK-rotacao-segredos](RUNBOOK-rotacao-segredos.md) · [RUNBOOK-acesso-ct100-testes-time](RUNBOOK-acesso-ct100-testes-time.md) · [RUNBOOK-ssh-hardening-ct](RUNBOOK-ssh-hardening-ct.md) · [AUDITORIA-OPS-DR-2026-07](AUDITORIA-OPS-DR-2026-07.md) · [TEAM.md](../../../TEAM.md) · `memory/proibicoes.md` §"NUNCA commitar valores BRL" · skill [`oimpresso-team-onboarding`](../../../.claude/skills/oimpresso-team-onboarding/SKILL.md).

**Estado-da-arte externo 2026:** ver §"Apêndice — pesquisa externa" abaixo.

---

## Apêndice — Pesquisa externa (estado-da-arte 2026)

> Síntese da pesquisa web (13 buscas, 2026-07-13). Cada afirmação com fonte. Reforça a recomendação do §2.4.

**§1. "Esconder o fonte" — consenso da indústria.** Código que o dev edita não pode ser escondido dele (é constraint estrutural do Git distribuído, não falha de ferramenta). Obfuscation protege **binário distribuído**, não repo de trabalho. O realista: least-privilege + blindar segredo + NDA/IP assignment + auditoria comportamental ("trust but verify" / detecção de insider threat: alertar clone anômalo, pico de commits, remoção de checks de segurança). Fontes: [Softsuave](https://www.softsuave.com/blog/how-can-companies-keep-their-source-code-private/), [Fortra](https://www.fortra.com/blog/code-protection-how-protect-your-source-code), [hoop.dev](https://hoop.dev/blog/insider-threat-detection-in-svn-how-to-protect-your-source-code-from-internal-risks).

**§2. GitHub Org/Teams/CODEOWNERS/rulesets.** 5 papéis de repo (Read→Triage→Write→Maintain→Admin; júnior = Write, nunca Admin). **Rulesets (2026) > branch protection rules** (múltiplos ao mesmo tempo, vale o mais restritivo). **Required reviewer rule GA fev/2026** com negação `!` estilo `.gitignore`. `enforce_admins` força regras até pra admin. **Limite duro:** GitHub **não restringe leitura por pasta** — é tudo-ou-nada no repo; CODEOWNERS só controla *quem revisa*, não *quem lê*. Fontes: [GitHub Docs — repository roles](https://docs.github.com/en/organizations/managing-user-access-to-your-organizations-repositories/managing-repository-roles/repository-roles-for-an-organization), [GitHub Docs — about rulesets](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-rulesets/about-rulesets), [GitHub Changelog 2026-02-17](https://github.blog/changelog/2026-02-17-required-reviewer-rule-is-now-generally-available/), [GitHub Community #102755](https://github.com/orgs/community/discussions/102755).

**§3. Extrair joias (repos separados / submodules).** Única forma técnica de esconder **leitura**. Trade-off: monorepo (melhor DX, tudo-ou-nada) vs submodules (segmentável por time/user, DX pior — "esqueci `submodule update`" e breaking change cross-repo são dores recorrentes) vs multi-repo (ACL limpa, coordenação cara). Vale só pra IP **nuclear, isolável e diferenciador** — não por reflexo. Fontes: [Aviator — submodules](https://www.aviator.co/blog/managing-repositories-with-git-submodules/), [graphite](https://graphite.com/guides/managing-permissions-access-control-monorepo), [blog.timhutt (contra submodules)](https://blog.timhutt.co.uk/against-submodules/).

**§4. Self-hosted git 2026.** Forgejo (default ~90% dos novos self-hosters, single-binary ~512MB), Gitea, GitLab CE (único com CI/CD embutido, mas exige 4-8GB + Postgres/Redis/Gitaly/Sidekiq). RBAC GitLab: Guest→Reporter→Developer→Maintainer→Owner. **Veredito:** pra 5→10 pessoas, migrar só por controle de acesso **raramente compensa** — troca ACL resolvida do GitHub por virar SRE do forge + perde Actions/Copilot/secret-scanning nativo. Só vale se air-gap/soberania for requisito duro. Fontes: [techverdict](https://www.techverdict.io/articles/self-hosted-git-2026), [GitLab Docs — permissions](https://docs.gitlab.com/user/permissions/).

**§5. Secrets management.** Segredo nunca no repo (~29M segredos hardcoded no GitHub público em 2025; 64% dos vazados em 2022 seguiam ativos em jan/2026). ⚠️ **Vaultwarden = cofre de senhas humanas, NÃO secrets manager de aplicação** (sem machine accounts / API service-to-service — o Bitwarden Secrets Manager não veio pro Vaultwarden). Pra segredos de CI/serviço self-host: **Infisical** (open-source) — não HashiCorp Vault (expertise operacional alta demais pra time pequeno). **4 camadas de scanning:** pre-commit (Gitleaks) + CI diff + varredura de histórico (TruffleHog) + plataforma (GitHub Secret Scanning/Push Protection). Fontes: [dev.to/thedailyagent](https://dev.to/thedailyagent/top-6-secrets-management-tools-for-devs-in-2026-4ahe), [Vaultwarden Discussion #3368](https://github.com/dani-garcia/vaultwarden/discussions/3368), [devsecops.ae](https://devsecops.ae/secrets-scanners-comparison-2026/), [Entro/GitGuardian](https://entro.security/blog/secure-it-onboarding-and-offboarding-checklists/).

**§6. Least-privilege de infra.** Deploy key **read-only Ed25519 por-serviço** (1 repo por chave; prod só faz `git pull`). SSH segmentado por servidor/ambiente. **2FA obrigatório na org** (antes de aceitar convite). Júnior: sem chave de prod, deploy só via CI. Fontes: [GitHub Docs — deploy keys](https://docs.github.com/en/authentication/connecting-to-github-with-ssh/managing-deploy-keys), [GitHub Docs — requiring 2FA](https://docs.github.com/en/organizations/keeping-your-organization-secure/managing-two-factor-authentication-for-your-organization/requiring-two-factor-authentication-in-your-organization).

**§7. Onboarding/offboarding.** Onboarding: conta no IdP primeiro → 2FA antes do convite → escopo mínimo → NDA/IP assignment antes do acesso. Offboarding: ⚠️ **desabilitar a conta NÃO revoga API tokens/PATs/deploy keys** (credenciais de máquina seguem vivas) → revogar um a um + **rotacionar todo segredo compartilhado que o saído conhecia** (item que mais falha) + deixar evidência. **NDA ≠ IP assignment:** NDA = confidencialidade de IP existente; assignment = transfere titularidade do IP criado no trabalho. **No Brasil, sem assignment o criador é presumido dono**; NDA/assignment executáveis no BR, **redigir em português** + conformidade LGPD. → competência da **Eliana[E]**/counsel, não do agente. Fontes: [Entro](https://entro.security/blog/secure-it-onboarding-and-offboarding-checklists/), [StrongDM](https://www.strongdm.com/blog/technical-staff-offboarding-checklist), [Rippling — IP Brazil](https://www.rippling.com/blog/ip-ownership-in-brazil), [Rippling — NDA Brazil](https://www.rippling.com/blog/non-disclosure-agreement-in-brazil).

**§8. Signed commits + audit trail.** GitHub verifica GPG/SSH/S/MIME; **SSH signing = melhor custo/benefício** pra time pequeno (mais simples que GPG). Ruleset "require signed commits" só deixa entrar commit assinado+verificado. Sigstore/gitsign (keyless, OIDC) existe mas a UI do GitHub ainda não mostra badge "Verified" — por isso SSH ganha hoje. Audit log da org: 180 dias (eventos Git 7 dias), export JSON/CSV. Valor pro júnior: autoria não-repudiável = o "verify" do "trust but verify". Fontes: [GitHub Docs — commit signature verification](https://docs.github.com/en/authentication/managing-commit-signature-verification/about-commit-signature-verification), [sigstore/gitsign](https://github.com/sigstore/gitsign), [GitHub Docs — reviewing audit log](https://docs.github.com/en/organizations/keeping-your-organization-secure/managing-security-settings-for-your-organization/reviewing-the-audit-log-for-your-organization).

---

**Rodapé de evolução**
- 2026-07-13 (3) — add §2.6 "quantos repositórios" + **revisão adversarial**: refuta a Jana como joia extraível (grep origin/main: `ScopeByBusiness` multi-tenant Tier 0 em ~207 arquivos/30 módulos; core importa Jana em ~23). Resposta: 1 repo agora + 1 (daemons CT 100) em 6–12 meses; zero "só-Wagner". Corrige §2.4/§5/roadmap Fase 3. Prioridade: rotacionar segredo antes de topologia.
- 2026-07-13 (2) — add §2.5 "vão poder ver e copiar tudo?" (máquinas NÃO / código = quais repos; estrutura de 2 repos núcleo+joias; analogia da loja; o que não dá pra prevenir tecnicamente). Concretiza a Topologia B. Origem: pergunta direta do Wagner na sessão.
- 2026-07-13 — criação. Cruzamento canon interno (ADRs 0080/0081/0055/0057/0044/0030/0131/0093 + CODEOWNERS + branch-protection + `_INDEX-SECRETS` + tailscale-acl) × estado-da-arte 2026. Diagnóstico-chave: repo **público** + time ainda não é colaborador. Recomendação: **A** (privado + Org/Teams/CODEOWNERS) agora, **B** cirúrgico sob demanda, **C** não. Aguarda decisão do Wagner sobre o fork §5. Nada executado.
