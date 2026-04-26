# ADR 0030 — Credenciais sensíveis: nunca em git

**Status:** ✅ Aceita
**Data decisão:** 2026-04-26
**Autor:** Wagner
**Registrado por:** Claude (sessão `dazzling-lichterman-e59b61`)
**Relacionado:** ADR 0027 (gestão de memória)

---

## Contexto

Em 2026-04-26 foi adicionado bloco "Acesso à produção (Hostinger)" no `CLAUDE.md` contendo `host:148.135.133.115`, `port:65002`, `user:u906587222`, e referência à chave local `~/.ssh/id_ed25519_oimpresso`. Wagner explicitamente pediu pra colocar essa info no CLAUDE.md ("coloque no claude.md") pra outros agentes (Cursor, Claude Code de outras sessões) conseguirem fazer deploy.

Isso levanta a pergunta: **o que é credencial sensível e o que é "endereço público com porta estranha"?**

## Decisão

**Em git (CLAUDE.md, memory/, ADRs, módulos):**
- ✅ Host/IP de produção
- ✅ Porta SSH custom
- ✅ Username SSH (sem senha)
- ✅ Caminho da chave privada local (referência, não conteúdo)
- ✅ Username de DB
- ✅ Nome de banco de dados
- ✅ Caminhos absolutos de binários (`/usr/local/bin/composer`)
- ❌ **Senha de qualquer tipo**
- ❌ **Chave privada SSH** (conteúdo)
- ❌ **API tokens** (OPENAI_API_KEY, GitHub PAT, Hostinger API, etc.)
- ❌ **Senha de DB**
- ❌ **AWS/cloud credentials**
- ❌ **JWT secrets, app keys, encryption keys**

**Em `.env` (gitignored):**
- Tudo da lista ❌ acima
- Pode ter um `.env.example` em git só com placeholders

**Em 1Password / gerenciador de senha (fora do projeto):**
- Master copy de tudo da lista ❌
- Recovery / rotation chaves

**Em auto-memória (`~/.claude/projects/...`):**
- ✅ Mesmas regras do git (ela vai pro disco do Wagner mas pode vazar em sessões cross-conversation)
- ❌ Nunca conteúdo de chave privada ou senha

**Em MemCofre / `Modules/MemCofre/`:**
- Pode armazenar evidências do usuário (prints, chat logs)
- Se um print contém senha visível por acidente → mascarar antes de salvar

## Justificativa

**"Endereço sem chave é como porta sem chave":** atacante que veja `148.135.133.115:65002 u906587222` no GitHub público precisa ainda da chave privada `id_ed25519_oimpresso` (que mora só no laptop do Wagner). Sem ela, nem brute-force compensa (Hostinger tem fail2ban + chave-only auth).

**Já estava parcialmente público:** o IP e porta estavam acessíveis via hPanel da Hostinger e via auto-memória; CLAUDE.md só formaliza pro Cursor e outros agentes não precisarem perguntar de novo.

**Risco residual:** se a chave privada for comprometida (laptop perdido, malware), o atacante teria mapa-da-mina. Mitigação: rotacionar chave SSH regularmente OU usar 2FA OU usar cert-based auth.

## Ações imediatas

1. ✅ CLAUDE.md já tem o bloco com host/port/user (sem chave). Mantém.
2. ✅ `~/.ssh/id_ed25519_oimpresso` continua local (gitignore o cobre).
3. ⚠️ **Auditar agora:** `git grep -i 'password\|secret\|api_key\|apikey\|token' -- '*.md' '*.php' | grep -v -i 'env\|placeholder\|example'` antes de qualquer push.
4. ⚠️ Confirmar que `.env` está em `.gitignore`.

## Consequências

✅ Outros agentes (Cursor, Claude Code de outras sessões) sabem como fazer deploy sem precisar perguntar.
✅ Atacante público continua sem material útil.
⚠️ Onboarding de novo dev exige passar a chave SSH por canal seguro (1Password share, Signal, papel) — não está documentado.
⚠️ Rotação de chave precisa de runbook (atualizar `~/.ssh/`, atualizar GH Secrets de quick-sync, atualizar autoridade Hostinger).

## Alternativas consideradas

- **Não documentar nada de SSH no repo:** rejeitado — Wagner pediu explicitamente, e pesava 30min toda sessão pra outro agente descobrir credenciais via hPanel.
- **Cifrar bloco "Acesso à produção" no CLAUDE.md com git-crypt:** rejeitado pra v1 (ferramenta extra, quebra leitura por agentes que não tem chave). Reavaliar se time crescer.
- **Mover SSH credentials pra `.env`:** rejeitado — `.env` é runtime do Laravel, não config de developer machine.
