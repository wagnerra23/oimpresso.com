# PROMPT ZERO-TOQUE — Arquivos Sprint 2 (US-ARQ-013 · ADR 0123)

> **[W]: cole este bloco UMA vez** no Claude Code plugado em `wagnerra23/oimpresso.com`.
> Responda as 6 decisões (D1–D6) na primeira linha da sua mensagem ou deixe o Code parar e perguntar.

---

Claude Code: implemente a tela do módulo **Arquivos** em ondas de PR no repo `wagnerra23/oimpresso.com` (branch base `main`).

**Escopo e ordem estão em** `cowork-inbox/arquivos/PEDIDO-PARA-CODE.md` do projeto Cowork — se o arquivo não estiver no repo, use o texto que [W] colou junto. Referência visual F1: `prototipo-ui/cowork/arquivos/arquivos-page.jsx` + `arquivos-data.jsx` (ou os mesmos arquivos no projeto Cowork).

## Regras do lote

1. **Uma onda por vez.** Abra os PRs da onda, rode a lane, e **pare** ao fim de cada onda com um resumo (PRs abertos, lane, o que quebrou). Não avance de onda sem eu responder.
2. **1 PR por item** (PR-0 … PR-12), branches empilhadas `arq/w<onda>-<slug>`, base = PR anterior da mesma onda. Não faça squash de itens diferentes.
3. **Não invente endpoint nem Service.** As 7 FormRequests em `Modules/Arquivos/Http/Requests/` e os métodos de `ArquivosService` / `ArquivosRetentionService` já existem — ligue o que existe. Se faltar algo, **pare e pergunte**; não crie método novo por conta.
4. **Não toque** em `arquivos.download` (signed + `throttle:60,1`), nas 3 rotas Install (ADR 0024), nem no enum de `arquivos_audit_log` fora do PR-9.
5. **Nenhum ADR novo por sua conta** — ADR é Tier 0, só [W] cunha. Se o trabalho exigir ADR, escreva a proposta em `memory/decisions/proposals/` e siga.
6. **Nenhum arquivo derivado** (inventário, mapa de rotas, manifesto). Fonte e teste, nada de retrato.
7. Multi-tenant Tier 0 (ADR 0093) é lei em toda rota nova: `business_id` da sessão, nunca do request. Espelhe `MultiTenantTest` pra cada vista.
8. UI em **PT-BR**, DS vivo, padrão Cockpit V2 (sidebar + page header + cards + **drawer** pra detalhe). Sem modal full-screen, sem emoji, sem `rounded-xl+`, sem cor fora dos tokens.

## Ondas (detalhe no anexo)

- **Onda 0** — PR-0 trio (`Pages/Arquivos/Index.charter.md` + `.casos.md` + `prototipo-ui/contrato/arquivos.contract.json`); PR-0b ADR do aviso ao titular (só proposta) se D5=sim.
- **Onda 1 (ler)** — PR-1 acervo · PR-2 trilha · PR-3 retenção em dry-run puro (`summary()`+`preview()`) · PR-4 cofre (health-check + dedupe + curador stats). **Portão:** smoke 1280/1440, zero PII nas vistas de governança, `contrato:check` verde → então PR-5 acende o menu (`DataController::modifyAdminMenu()`).
- **Onda 2 (mutar reversível)** — PR-6 classificar (`ReclassifyArquivoRequest` → `classify()`, `motivo` obrigatório, grava `classified_by/at`) · PR-7 soft-delete + restore dentro do grace 30d.
- **Onda 3 (irreversível/legal)** — PR-8 rodar retenção pela tela **forçando `dry_run=true`** no controller, resultado via fila + `report()` · PR-9 aviso ao titular (migration `titular_avisado_at`, ação `notice`, janela `notice`=30d, só `sensitive` com titular) · PR-10 purge atrás de portão (confirmação dupla + `motivo`≥10 + permissão de governança + aprovação [W2]).
- **Onda 4 (acabamento)** — PR-11 DS vivo · PR-12 a11y + paginação server-side.

## Decisões pendentes (se eu não respondi, pergunte antes do PR-1)

D1 onde a tela mora (`Pages/Arquivos` vs `Modules/Admin`) · D2 permissão (`arquivos.access`) · D3 segunda permissão de governança · D4 purge pela UI existe? · D5 aviso ao titular (ADR + coluna) · D6 quando acender o menu.

## Definição de pronto (por PR)

- lane `Modules/Arquivos/Tests/**` verde (25 arquivos existentes) + teste novo do que o PR adiciona
- `npm run screen:files -- Arquivos/Index` com trio ✅ (depois do PR-0)
- `contrato:check` verde nas 4 vistas (depois do PR-0)
- nenhum enum cru na UI · nenhuma PII nas vistas retenção/cofre/trilha
- descrição do PR cita o arquivo do repo que sustenta cada afirmação
