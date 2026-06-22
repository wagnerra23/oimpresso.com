---
register: Caixa Unificada / Inbox · window.InboxPage
irmao_charter: Inbox.charter.md
tecnica: Decision Register (ADR 0293 D-B · anéis Avaliar/Testar/Adotar/Descartar)
owner: wagner
last_update: 2026-06-22
schema: ADR-0293-D-B
related_adr: 0293-governanca-decisao-design-responsavel-registro-veredito
pii: false
---

# Decision Register — Caixa Unificada / Inbox

> **Materialização do par charter↔decisoes que o `integrity-check` IT2 exige** (ADR 0293 D-B).
> Decisões inferidas do SYNC_LOG + CODE_NOTES (handoff Caixa Unificada) — nenhuma fabricada.
> Schema mínimo `D-NN` da ADR 0293: responsável · detecção · padrão · opções · status.

## D-01 · Dark bespoke `--omd-*` do handoff "n" (CASO-GATILHO da ADR 0293)
- responsável: [W] (Tier-0 cor/dark) · gate `ds-guard` (automático, barrou)
- detecção: `ds-guard` barrou o handoff da Caixa Unificada — dark bespoke `--omd-*` (13 tokens; baseline tinha 0), viola L-02
- padrão: dark por `[data-theme="dark"]` com token canônico, **sem paleta por-tela** (ADR 0281)
- opções: (a) aceitar a paleta `--omd-*` por-tela (REPROVADO); (b) refazer no padrão `[data-theme]` canônico (correto)
- status: DEVOLVIDO ([Design/Cowork]) — vira veredito append-only em `governance/design-requests/` (ADR 0293 D-C); próximo handoff lê o motivo + padrão antes de refazer

## D-02 · Sync da fonte espelho do protótipo (recriar path canon do visual_source)
- responsável: [CC] (mecânico sob gate)
- detecção: PROCESSED 2026-06-10 — a cópia PR-D 05-15 tinha ido pro `_BACKUP-NAO-USAR` na faxina; `Index.charter.md` visual_source apontava pra path ausente
- padrão: espelhar os 6 arquivos V2 do handoff em `prototipos/caixa-unificada/` (recria o path canon) · `ds-guard` limpo
- opções: n/a — restauração mecânica do espelho
- status: APLICADO (PROCESSED 2026-06-10 · ds-guard limpo)

## D-03 · Filtros como Popover flutuante (não empurra a lista) + Status como DropdownMenu
- responsável: [W] ratifica · [CC] migra
- detecção: Onda 2 #2879 — `ConversationListV4` header vira Status (Dropdown 7-valor `?tab=`) + Filtros (Popover, 9 grupos)
- padrão: contrato backend intacto (`buildQuery` carrega channel/account_id/queue) · não inventar grupo morto (anti M-AP-2 — Atribuição omitida por não ter param)
- opções: (a) filtros inline empurrando a lista; (b) Popover flutuante (escolhida)
- status: PENDENTE [W] (charter v15 · verificado local tsc+vite verde; sem screenshot — gate visual ADR 0107 + revisão [W])

---

## Graduados (saíram daqui → viraram ✅ no charter canônico no git)
- D-02 (sync espelho) aplicado. D-03 reflete o estado vivo do `Index.charter.md` v15 (git).

## Devolvidos ([Design/Cowork] · ledger governance/design-requests/)
- D-01 (dark `--omd-*`) — é o caso-gatilho da ADR 0293; o veredito formal pertence a `governance/design-requests/` (ledger append-only, ADR 0293 D-C), fora do escopo desta onda (PR-A da ADR).

## Trilha do tempo
- 2026-06-22 · [CC] materializou o par `Inbox.decisoes.md` no schema ADR 0293 D-B (IT2 deixa de passar no vácuo). Decisões inferidas do SYNC_LOG/CODE_NOTES — nenhuma fabricada.
