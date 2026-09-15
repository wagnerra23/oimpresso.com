---
date: "2026-09-15"
time: "22:30 UTC"
slug: mcp-desligado-type-e-rotacao-token
tldr: "O MCP não estava desligado — estava sendo PULADO: o cliente 2.1.257 passou a exigir `type` em entrada com `url`, e `Skipped` não aparece como `failed`. Três travas em série (type → approval → OAuth/419), a terceira revelando que `mcpServers` do settings.local.json é ignorado pelo cliente. Depois: token rotacionado e 9 → 3 vivos."
prs: [7366, 7369, 7372, 7378, 7379]
decided_by: [W]
related_adrs: [0056-mcp-fonte-unica-memoria-copiloto-claude-code]
next_steps:
  - "Decidir os 3 tokens vivos restantes: #1 (WagnerLaptop, uso 12/08) e #10 (DXT, uso 15/09) seguem ativos por escolha"
  - "US-COPI-149 aberta: escolher entre os 3 caminhos pro tasks-create (DB / PR automático / parar de afirmar durabilidade)"
  - "Cada dev do time precisa exportar OIMPRESSO_MCP_TOKEN — US-INFRA-049..052 no my-work de cada um"
---

## Estado MCP no momento do fechamento

`cycles-active` → nenhum cycle ATIVO em COPI. `my-work` → sem tasks ativas pra @wr23.
`whats-active` → nenhuma sessão em 2h, **mas declarando cegueira** (ingest sem heartbeat: fresh=0 · dead=352) — não tratei como escopo livre.
10 handoffs e 9 session logs já existiam de hoje; conferido antes de criar (não duplicar).

## O que aconteceu

A pergunta foi *"porque o MCP está desligado?"*. **Não estava.** O servidor respondia e o token era válido — o que havia era o cliente **pulando** a entrada.

**As três travas, em série.** Cada conserto revelou a próxima, e parar na primeira teria dado "consertado" com o MCP ainda mudo:

1. **`type` ausente** → `Skipped`. O cliente 2.1.257 passou a exigir `"type"` em `mcpServers` com `url`. O `.mcp.json` não mudou desde 2026-09-02; quem mudou foi o cliente.
2. **`Pending approval`** → `enabledMcpjsonServers=[]` em **852 de 852** projects oimpresso.
3. **`Dynamic Client Registration rejected (419)`** → o cliente caiu em OAuth porque a entrada viva não tinha headers.

O experimento que isolou a (3): uma entrada de teste em `.claude/settings.local.json` **não apareceu em lugar nenhum** — `mcpServers` ali é **ignorado** pelo cliente MCP. Ele segue necessário como **cofre dos hooks** (`brief-fetch-curl.mjs`, `cc-watcher`, `fluxo-sistema.mjs` leem o `Authorization` dali). Quem alimenta o cliente é o `.mcp.json` expandindo `${OIMPRESSO_MCP_TOKEN}` do ambiente.

**Por que ninguém percebeu:** `Skipped` **não é** `failed` — o servidor some da lista inteira e o silêncio fica indistinguível de "nunca foi configurado". Pior: o brief **continua chegando**, porque o hook bate por `curl` sem passar pelo cliente. LC-13 na camada de config.

**O bug do `tasks-create` (US-COPI-149).** Ao avisar o time, a tool respondeu `✅ criada` 4×. Nenhuma sobreviveu: `tasks-detail` respondeu igual ao controle negativo (id inventado), o SPEC versionado tinha 0 de 4, e o checkout do servidor tinha as 5 num arquivo não-commitado. Entre minhas duas leituras o servidor puxou o main e o SPEC caiu de 89.531 → 82.068 bytes. **O pull apagou tudo.** O ramo `$written=true` escreve onde ninguém pode salvar — o `git add` que ele instrui teria de rodar no servidor, que é proibição Tier 0.

**Rotação.** Usei `teammcp:token:rotate` (revoke+issue atômico), não o `mcp:token:gerar` (só emite) — ele estava escondido atrás de um `head -6` da minha própria sonda. #4 → #30, mais 7 revogados. **9 → 3 vivos.**

## Artefatos gerados

| PR | o quê |
|---|---|
| [#7366](https://github.com/wagnerra23/oimpresso.com/pull/7366) | `.mcp.json` (`type` + headers via env) · `settings.json` (aprovação nominal) · `.example` |
| [#7369](https://github.com/wagnerra23/oimpresso.com/pull/7369) | `MEMORY_TEAM_ONBOARDING` passo E · skill Modo D · US-INFRA-049..052 |
| [#7372](https://github.com/wagnerra23/oimpresso.com/pull/7372) | US-COPI-149 (bug do `tasks-create`) |
| [#7378](https://github.com/wagnerra23/oimpresso.com/pull/7378) · [#7379](https://github.com/wagnerra23/oimpresso.com/pull/7379) | `_INDEX-SECRETS` + errata da contagem |

## Persistência

**git:** 5 PRs em main (21:10 → 22:26). **MCP:** webhook propaga o SPEC; as 4 US caem no `my-work` de cada dev. **Runtime:** token novo nos 3 JSONs + env var `User`; `claude mcp list` → `✔ Connected`.

## Lições catalogadas (erros MEUS, medidos)

- **Contei ativos com `whereNull('revoked_at')` no query builder cru** — ignora SoftDeletes. O #24 tinha `deleted_at=2026-06-17` e nunca foi vivo: eram **9**, não 10. Errata no #7379, e a linha do índice agora carrega a pegadinha.
- **Conclui "a máquina não existe" olhando `/app`** no container — o checkout é `/var/www/html`; e meu `find -maxdepth 6` não alcançava o arquivo (nível 7). Ausência da minha busca, não do mundo.
- **`grep -c '^-[^-]'` contou 4 remoções onde havia 54** — linhas de markdown começam com `- `, viram `-- ` no diff. §5 2026-08-12: contagem de diff é `--numstat`.
- **Imprimi "(vazio = nenhuma falha)" com o comando quebrado** (`--arg` não existe em `gh run list`) — §5 2026-07-31, na própria conferência final.
- **Não confiei no `head -6`** e foi o que achou o `teammcp:token:rotate`. O acerto do dia veio da mesma disciplina que os erros violaram.

## Próximos passos pra retomar

```bash
gh pr view 7372 --json state && node scripts/governance/tasks-index-generate.mjs --check
```

## Pointers detalhados

Mecanismo e medições: corpo dos PRs #7366 e #7379. Bug do `tasks-create`: `memory/requisitos/Jana/SPEC.md` §US-COPI-149. Passo do token: `MEMORY_TEAM_ONBOARDING.md` §E.
