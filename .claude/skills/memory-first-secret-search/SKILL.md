---
name: memory-first-secret-search
description: BLOQUEADOR Tier A — ATIVAR ANTES de qualquer busca por token / API key / password / SSH key / credential / secret. Skill canon que IMPEDE busca disgrega em paths aleatórios (.env, containers, etc) sem consultar PRIMEIRO o índice canônico `memory/_INDEX-SECRETS.md`. Origem falha 2026-05-28 18:30: agente declarou Tier 0 gap "token Hostinger inacessível" sem ter pesquisado memory canon (o token estava catalogado, o agente é que não consultou o índice). Wagner cobrou "tem api da hostinger na memoria". Skill força ordem fixa: (1) Read memory/_INDEX-SECRETS.md (2) follow ponteiro (3) se expired/missing registra rotação. NÃO escala Wagner se ponteiro existe.
tier: A
resumo: consultar `_INDEX-SECRETS` antes de buscar token
---

# Memory-First Secret Search — Tier A bloqueante

> **Princípio:** índice canon é fonte de verdade #0. Buscar secret em outro lugar primeiro = violação.
> **Falha origem:** 2026-05-28 — agente fez 7+ paths arbitrários (`/root/.hostinger-api-token`, `/opt/*/.env`, containers, Vaultwarden API, etc) SEM consultar `memory/_INDEX-SECRETS.md`. Token estava literalmente em git canon desde 2026-04-28. Wagner cobrou.

## Quando ativar

ANTES de qualquer:
- Busca por token / API key / password / OAuth client_secret / SSH key
- `grep -r "Bearer\|API_KEY\|token" ...`
- `find / -name "*.token"`
- Chamada `curl ... -H "Authorization: Bearer $TOKEN"` se `$TOKEN` desconhecido
- Pensamento "preciso do secret X — onde está?"

## Ordem fixa de busca (3 passos)

### Passo 1 — Read `memory/_INDEX-SECRETS.md` (PRIMEIRO sempre)

```bash
grep -A 1 "<nome-do-secret>" memory/_INDEX-SECRETS.md
# Ou Read direto pra varrer tabela canon
```

A tabela tem 4 colunas críticas:
- **Onde está** (path/Vault item/CT 100 file)
- **Como acessar agente** (comando exato)
- **Frequência rotação**
- **Status** (✅/🟡/🔴/🔒/⏸)

### Passo 2 — Seguir ponteiro indicado

| Se Onde diz... | Comando |
|---|---|
| `memory/<file>` | `Read memory/<file>` |
| `Hostinger .env` | `ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 'grep ^XXX .env'` |
| `CT 100 /opt/...` | `tailscale ssh root@ct100-mcp 'cat /opt/.../arquivo'` |
| `Vaultwarden item <slug>` | Skill `secret-vaultwarden` (a criar) OR Wagner manual |
| `DB table <X>` | SSH MySQL via Hostinger receita canon `memory/reference/hostinger.md` |

### Passo 3 — Interpretar status

| Status | Ação |
|---|---|
| ✅ active | Usa o ponteiro, segue trabalho |
| 🟡 warning | Usa mas regista TODO no PR pra completar setup secundário |
| 🔴 EXPIRED | Registra como **rotação necessária** + propõe ADR/PR com receita pra Wagner regerar. NÃO é Tier 0 gap. |
| 🔒 LOCKED humano-only | Aceita limite. Documenta workaround OR escala explicitamente (caso permitido) |
| ⏸ pending | Implementação ainda não feita. Sprint planning em vez de execução |

## Anti-padrão Tier A — busca dispersa sem índice

❌ **NÃO faça** ANTES de Read `_INDEX-SECRETS.md`:
- `grep -r "Bearer" /opt/ /root/ /home/ ...`
- `for c in $(docker ps); do docker inspect $c ...; done`
- `find / -name "*.token"`
- `cat /opt/*/.env`
- Consultar Vaultwarden API admin pra dump items
- WebSearch "como pegar token <provider>"

✅ **FAÇA** primeiro:
- `Read memory/_INDEX-SECRETS.md`
- ENTÃO segue ponteiro indicado pra fonte canon
- Se ausente OU 🔴 EXPIRED → propõe atualização do índice ANTES de implementar

## Atualizar índice (toda vez que rotacionar ou criar novo secret)

Quando agente:
- Descobre secret novo na sessão → adiciona linha no índice ANTES do commit
- Cria integração nova (Asaas, Sicoob, novo provider) → linha + Status pendente
- Detecta secret 🔴 EXPIRED → atualiza coluna Status + data

PR title canon: `chore(secrets): rotaciona <secret-name> <data>` OU `chore(secrets): adiciona <secret-name> ao índice`.

## Casos especiais

### Secret achado em memory canon git mas expirou (caso 2026-05-28 Hostinger)

NÃO é Tier 0 gap (gap = não acessível). É **secret rotation needed**:

1. Valida com curl HTTP (esperado 401 se expirou)
2. Atualiza `memory/_INDEX-SECRETS.md` linha status: ✅ → 🔴 EXPIRED data
3. Cria PR com:
   - Receita pra Wagner regerar (link hPanel ou similar)
   - Onde colar novo token (path canon `/root/.hostinger-api-token` CT 100 + `memory/<arquivo-canon>` se índice diz)
4. NÃO escala Wagner como interrupção urgente — registra como follow-up Sprint

### Secret nunca documentado E ausente

1. Search exaustiva (todas as 7 paths conhecidas)
2. Se realmente ausente → propõe **adicionar ao índice** com status ⏸ pending
3. Sprint planning pra implementar integração + provisionar secret
4. NÃO assume "Wagner faz no painel" como solução padrão

## Refs

- [`memory/_INDEX-SECRETS.md`](../../../memory/_INDEX-SECRETS.md) — índice canon (fonte verdade #0)
- [`memory/proibicoes.md`](../../../memory/proibicoes.md) — regra Tier 0 enforcement
- [`memory/reference/PATTERN-INCIDENT-RESPONSE-VELOCITY.md`](../../../memory/reference/PATTERN-INCIDENT-RESPONSE-VELOCITY.md) — multiplicador G (autonomia)
- Skill correlata: `hostinger-dns-autonomy` Tier A — Path 0 atualizado pra consultar `_INDEX-SECRETS.md`
- Skill correlata futura: `secret-vaultwarden` (a criar) — wrapper bw CLI pra items Vault user-level
- Falha origem: 2026-05-28 18:30 — agente declarou Tier 0 gap falsamente sem consultar memory canon
