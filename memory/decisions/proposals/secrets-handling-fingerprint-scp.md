---
slug: secrets-handling-fingerprint-scp
title: Protocolo Secrets — SCP + SHA-256 fingerprint pra transportar credenciais Tier 0 sem expor ao Claude
type: adr
status: proposta
authority: canonical
lifecycle: ativo
proposed_by:
  - W
  - C
proposed_at: '2026-05-15'
quarter: 2026-Q2
tags:
  - secrets
  - lgpd
  - tier-0
  - multi-tenant
related:
  - '0030'
  - '0093'
  - '0101'
pii: false
supersedes: []
---

# ADR proposal — Protocolo Secrets: SCP + SHA-256 fingerprint

**Status:** 🟡 Proposta (aguardando Wagner aprovar)
**Data proposta:** 2026-05-15
**Autor:** Wagner + Claude (sessão pivot CYCLE-05 → CYCLE-06)
**Supersedes:** —
**Relacionado:** ADR 0030 (credenciais jamais em git), ADR 0093 (multi-tenant Tier 0), ADR 0101 (biz=4 não pra testes)

## Contexto

ADR 0030 estabeleceu que **credenciais sensíveis nunca vão pra git**. Não cobriu o **como** operacional: quando Wagner precisa cadastrar credencial nova (ex: Inter PJ client_id/secret/cert), o caminho era:

1. Wagner abre SSH manual: `ssh -i ~/.ssh/id_ed25519_oimpresso ...`
2. Wagner abre tinker: `php artisan tinker`
3. Wagner cola valores no terminal aberto (Crypt::encryptString inline)
4. Wagner sai

Problemas observados:
- **Chat Claude exposto** — em várias sessões, Wagner colocou credenciais no chat por engano, ou Claude pediu credenciais sem proteção. `feedback-nunca-publicar-credenciais.md` documenta o incidente.
- **Terminal local logado** — `~/.bash_history`, `.zsh_history`, PowerShell `ConsoleHost_history.txt`. Credenciais ficam em log local sem intenção.
- **Sem fingerprint** — Wagner não tem como verificar se colou o valor certo do Vaultwarden. Erro de paste passa silencioso.
- **Sem idempotência** — segundo paste duplica registro `rb_boleto_credentials`.
- **Sem audit trail** — ninguém sabe quando/quem cadastrou.

## Decisão

Adotamos o **pattern SCP + SHA-256 fingerprint** como caminho canônico pra cadastrar credenciais sensíveis no DB Hostinger / CT 100. Implementação canônica em [`scripts/inter-credentials/`](../../../scripts/inter-credentials/) (Inter PJ como caso piloto; pattern reusável pra próximos bancos / provedores).

### Princípios duros

1. **Claude nunca vê o valor.** Apenas SHA-256[:12] fingerprint.
2. **Valor mora em arquivo local gitignored** durante o transporte. Nunca em commit, log, chat, env shell, argv.
3. **Transporte criptografado.** SCP (SSH). Nunca HTTP/curl/email.
4. **Cleanup garantido.** Try/finally em ambas as pontas.
5. **Idempotente.** Refuse sobrescrever credencial existente sem flag explícita de update.
6. **Tier 0 obrigatório.** `--business-id` required, validado contra allowlist (1 ou 4).

### Fluxo canônico

```
[Wagner local]                  [Wagner local]                  [Canal SSH]            [Hostinger]                [Cleanup ambos]
preenche credentials.local  →   Python valida + SHA-256[:12]  →  SCP JSON+PHP        →  PHP boota Laravel    →    /tmp deletado
.json (gitignored)              mostra fingerprints                                     Crypt::encryptString       arquivo local
                                pra Wagner conferir                                     INSERT DB                  (--shred opcional)
                                                                                        DELETE /tmp/json
```

### LGPD — quando aplica

Credencial pertence a **titular** (pessoa física ou PJ gerida por PF) → LGPD aplica:
- Art. 6º princípios: segurança (VII) + prevenção (VIII)
- Art. 46: medidas técnicas adequadas — SCP + Crypt cobrem
- Art. 48: vazamento = incidente reportável à ANPD em 72h

Exemplos:
- ✅ Credencial Inter biz=4 ROTA LIVRE (Larissa) → **LGPD aplica**
- ⚪ Credencial Inter biz=1 Wagner WR2 → segredo corporativo, **mesmo protocolo** por consistência

## Consequências

### Positivas

- **Zero exposição ao Claude/chat/log**: arquitetura técnica impede, não depende de disciplina humana
- **Audit trail visível**: fingerprint[:12] pode ir pra log/git sem ser segredo (irreversível)
- **Multi-banco reusável**: pattern serve pra Bradesco, Itaú, Santander, OPENAI_API_KEY, futuros provedores. Só muda `_remote_install_<provider>.php`
- **LGPD-ready**: cumpre art. 46 com evidência técnica
- **Idempotência cobre erros de paste**: refuse insert duplicado, erro claro

### Negativas

- **Dependência SCP/SSH**: rede Hostinger flaky quebra fluxo (autossh REJEITADO doc, [hostinger-mysql-conexao.md](../../reference/hostinger-mysql-conexao.md))
- **Não 100% pra SSDs**: `--shred` overwrite reduz superfície mas firmware copy-on-write pode preservar
- **Curva de aprendizado**: Wagner precisa lembrar `--apply` (não é default por safety)
- **Não cobre rotação automática**: pattern é insert; UPDATE de credencial existente requer extensão manual

### Neutras

- **Não impede vazamento upstream**: se Wagner copiar credencial do Vaultwarden pro Slack por engano, esse pattern não protege (escopo é Wagner→DB Hostinger)
- **Substitui mas não obriga**: tinker manual continua possível em casos exepcionais (gap intencional)

## Alternativas consideradas

1. **HashiCorp Vault self-host** — overkill pra escala atual (3-4 credenciais ativas). Custo manutenção > benefício.
2. **AWS Secrets Manager** — vendor lock + R$/mês contínuo. Reavaliar quando tier 0 ≥ 20 credenciais.
3. **GPG-encrypted JSON em git** — viola ADR 0030 (mesmo encrypted, vazamento APP_KEY descriptografa retroativamente). Rejeitado.
4. **Senha master Vaultwarden → API call direto Vaultwarden → DB** — funciona mas exige Vaultwarden API operacional + ainda passa secret em memória do Python. Marginal vs SCP.
5. **Manter tinker manual** (status quo) — não escala, expõe via terminal log, sem fingerprint. Rejeitado.

## Implementação

Status atual:
- [`scripts/inter-credentials/install-biz.py`](../../../scripts/inter-credentials/install-biz.py) — orquestrador Python
- [`scripts/inter-credentials/_remote_install.php`](../../../scripts/inter-credentials/_remote_install.php) — payload Laravel
- [`scripts/inter-credentials/credentials.example.json`](../../../scripts/inter-credentials/credentials.example.json) — template
- [`scripts/inter-credentials/.gitignore`](../../../scripts/inter-credentials/.gitignore) — bloqueia segredos
- [`scripts/inter-credentials/README.md`](../../../scripts/inter-credentials/README.md) — docs
- [`memory/reference/protocolo-secrets-fingerprint.md`](../../reference/protocolo-secrets-fingerprint.md) — referência canônica curta

### Pra Wagner aceitar como ADR

1. Renomear arquivo: `0145-secrets-handling-fingerprint-scp.md` (próximo número)
2. Mudar frontmatter `status: proposta` → `status: aceito` + adicionar `decided_at: 'YYYY-MM-DD'`
3. Adicionar `accepted_by: [W]` no frontmatter
4. Commit + sync MCP (skill `memory-sync` ou `sync-mem`)

### Pra extensão futura

- **Pattern audit log**: tabela `secrets_audit_log` com colunas `id, who, when, biz, banco, fingerprint, op (insert|update|delete)` — sem valor
- **Pattern HMAC pré-SCP**: defesa zero-trust contra MITM no canal SSH (improvável mas possível)
- **Pattern rotação**: `--update` flag que compara fingerprint atual vs novo, gera diff, exige confirmação
- **Generalização Python**: `--driver <name>` aponta pra `_remote_install_<driver>.php`

## Refs externos

- [LGPD art. 6º](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm) — princípios
- [LGPD art. 46](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm) — medidas técnicas
- [LGPD art. 48](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm) — incidentes
- [NIST SP 800-53 IA-5](https://csrc.nist.gov/projects/risk-management/sp800-53-controls/release-search) — Authenticator Management
- [ISO 27001 A.9.2.4](https://www.iso.org/standard/27001) — Management of secret authentication information
