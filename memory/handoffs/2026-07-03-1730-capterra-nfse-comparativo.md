---
date: "2026-07-03"
time: "17:30 BRT"
slug: capterra-nfse-comparativo
tldr: "Capterra sênior do Modules/NFSe (serviço/ISS, SN-NFSe federal) — nota de capacidade 45/100 vs topo BR ~83. Achado regulatório LIVE: NT 008/2026 descontinua a API ADN do DANFSe em 15/07/2026 e o oimpresso não gera DANFSe. Wagner confirmou intenção de emitir NFSe real antes dessa data → /comparativo criou 7 US (016-022) + re-priorizou 014/015. PR #3740 merged."
prs: [3740]
decided_by: [W]
related_adrs: [0089-capterra-driven-module-evolution, 0105-cliente-como-sinal-guiar-sem-mandar, 0093-multi-tenant-isolation-tier-0]
next_steps:
  - "Emitir antes de 15/07: US-NFSE-016 (DANFSe NT 008) → 017 (validar DPS) → 013 (1 nota real) → 015 (ambiente per-business no cancelar)"
  - "Opcional: abrir cycle pra alocar as 7 US (hoje backlog do módulo, owner eliana, sem cycle ativo)"
---

# Handoff — Capterra sênior NFSe + /comparativo (2026-07-03 17:30)

## Estado MCP no momento do fechamento

- **cycles-active (COPI):** nenhum cycle ativo.
- **my-work @eliana:** 22 tasks TODO — inclui as 7 novas NFSe (016-022) + 013/014/015 re-priorizadas. P0: US-NFSE-013 (deploy real), US-NFSE-017 (validar DPS).
- **decisions-search "NFSe":** ARQ-0001 (standalone), TECH-0001 (Service+Adapter+DTO), ARQ-0002 RB. **Nenhuma ADR nova necessária** — a auditoria é decisão-suporte, não decisão arquitetural.

## O que aconteceu

Pedido: adversário de mercado (`capterra-senior`) do módulo **NFSe** — camada fiscal de **serviço/ISS**, distinta de NfeBrasil (produto/ICMS). Sinal fraco (ADR 0105): a ficha decide se vale onda.

Pesquisa limpa (eNotas, PlugNotas, Nuvem Fiscal, Focus NFe + padrão ABRASF/Nacional) + leitura do código real (`NfseEmissaoService`/`SnNfseAdapter`/models/migrations/tests). Nota honesta: **45/100** (topo BR Focus/PlugNotas ~83). O módulo é bem-arquitetado (Tier 0, idempotência, cert encrypted, LGPD, vínculo venda→NFSe, custo zero per-emissão via SN-NFSe direto) mas **nunca emitiu 1 NFSe**, **não gera DANFSe**, e cobre 1 município.

**Achado regulatório LIVE:** NT 008/2026 descontinua a API do ADN que gera o DANFSe em **15/07/2026** — a responsabilidade passa ao emissor. O oimpresso só proxia `urlDanfse` do provider → PDF quebra na virada. Wagner respondeu "1 e 2": (1) há intenção de emitir NFSe real antes de 15/07 → G-01 (DANFSe) vira P0 urgente; (2) rodar `/comparativo`. Depois "autorizo" + "merge evai".

`/comparativo NFSe`: inventário 3 buckets (✅5 🟡8 ❌7) + 7 US novas criadas no MCP (016-022) + 014/015 re-priorizadas p2→p1 + 013/014/015 comentadas. Ordem crítica registrada: **016 → 017 → 013 → 015**.

## Artefatos gerados (canon, via PR #3740 merged)

- `memory/requisitos/NFSe/CAPTERRA-FICHA.md` (novo, 10 seções, 20 caps P0-P3, nota 45)
- `memory/requisitos/NFSe/CAPTERRA-INVENTARIO.md` (novo, buckets + batch tasks)
- `memory/sessions/2026-07-03-capterra-nfse.md` (novo, pesquisa expandida)
- `memory/requisitos/NFSe/SPEC.md` (+7 US: US-NFSE-016..022)

## Persistência (3 canais)

- **Git:** PR [#3740](https://github.com/wagnerra23/oimpresso.com/pull/3740) MERGED em origin/main (`cf8565ede5`).
- **MCP:** 7 tasks criadas (016-022) + 014/015 update p1 + 3 comments — duráveis (ADR 0144), independem do git.
- **BRIEFING:** não atualizado (auditoria não mudou capacidade real do módulo; BRIEFING NFSe reflete estado 2026-05-16 + agora aponta pra FICHA).

## Próximos passos pra retomar

```
tasks-list module:NFSe status:todo   # ver as 7 novas + ordem 016→017→013→015
```
Se for executar o hotfix regulatório: começar por **US-NFSE-016** (gerar DANFSe conforme NT 008/2026) — prazo 15/07/2026.

## Lições catalogadas

- **memory-schema (session log):** frontmatter exige `topic` (não só `title`) E `date` entre aspas — `date: 2026-07-03` sem aspas o YAML parseia como Date e o AJV falha `type: string`. Dois commits de fix antes do CI verde. Skill `memory-schema-preflight` teria pego local.
- **tasks-create (MCP) escreve o SPEC.md na cópia canon do servidor, não no worktree** — para não duplicar, apendei os blocos retornados no meu worktree SPEC e deixei o git canon reconciliar por ID (ADR 0144). Confirmado com grep (0 matches no worktree antes do append).

## Pointers detalhados

- FICHA §8 (leitura adversarial) + §10 (recomendação sinal fraco) — `memory/requisitos/NFSe/CAPTERRA-FICHA.md`
- Session log com pesquisa bruta dos concorrentes — `memory/sessions/2026-07-03-capterra-nfse.md`
