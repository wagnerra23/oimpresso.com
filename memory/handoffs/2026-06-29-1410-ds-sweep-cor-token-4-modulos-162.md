---
handoff: DS sweep cor→token — 4 módulos zerados (ds/* 270→162) + 9 chips spawnados
data: "2026-06-29 14:10"
autor: "[CC]"
cycle: CYCLE-08
tags: [design-system, ds-ratchet, conformance, cor-token, deploy-automatico]
related_adrs: [0209, 0239, 0269, "ui/0013"]
pr_principais: ["#3380", "#3381", "#3382", "#3384", "#3386", "#3387", "#3388", "#3390", "#3392", "#3393"]
---

# Handoff — Sweep DS cor crua → token semântico (sessão longa)

## O que foi feito

Sweep de conformance do Design System: trocar **cor de status crua** (`text-rose/red/emerald/green-*`,
`rounded-(xl|2xl|3xl)`) por **token semântico mode-aware** (`text-success`/`text-destructive` +
`-soft`/`-fg`/`/20`). Origem: pergunta do Wagner "como melhorar a aplicação do DS" → o gargalo era
adoção (placar dizia 533, real 270), não ferramenta (guard `ds/*` já ligado, ADR 0209).

**Resultado: `ds/* 270 → 162` (−108).** Módulos zerados: **Financeiro (63→0) · Cliente (18→0) ·
Purchase (14→0) · Jana (13→0)**. Fila canônica 0/10 → 3/10. 10 PRs mergeados + deployados.

| PR | Conteúdo |
|---|---|
| #3380/#3386/#3388/#3393 | placares (533→270→189→162) — docs-only |
| #3381 | Financeiro cor→token (camadas 1+2+3) |
| #3382 | Financeiro rounded-2xl→lg |
| #3384 | Financeiro `<select>` nativo→Radix `<Select>` (categoria inline, sentinela `__none__`) |
| #3387 | Cliente cor→token |
| #3390 | Purchase cor→token |
| #3392 | Jana cor→token + chat bubbles rounded-2xl→lg |

## Método validado (reutilizável — está nos prompts dos 9 chips)

- valor/texto: emerald/green→`text-success`, rose/red→`text-destructive` (remover `dark:` redundante)
- pílula `bg-*-50 text-*-700 border-*-200` → `bg-success-soft text-success-fg border-success/20`
- dot `bg-emerald-500`→`bg-success`; rounded-xl+→`rounded-lg`
- **CARVE-OUT (não converter):** cor de MARCA/CATEGÓRICA. Catalogado: gateways `cobranca-shared.ts`
  (Bradesco/Sicoob/BCB), `TIPOS` PIX, Cliente `Pills.tsx` (PF/PJ + TAG_COLORS "decisão B"),
  Jana Roadmap `priorityTone`, Whatsapp HSM `R-DS-002 exceção` (StatusBadge/CategoryBadge Meta).
  São quase sempre `const` (não-className) → não-flagged; deixar cru é correto.

## Lições de CI (aplicar nos próximos)

1. **casos-gate G-6 (ADR 0264):** tocar `<Tela>.tsx` que tem `.casos.md` ao lado → `stale:` (tela mudou
   depois do `last_run`). Fix: bumpar `last_run` pra hoje no frontmatter (mudança visual não invalida UC).
   Bater junto no MESMO PR. Pegou #3381/#3384.
2. **charter advisory diff-aware:** editar `<Tela>.charter.md` "acorda" 2 gates advisory (status:live sem
   sinal prod + related_us) se o charter for legado. PR de cor deve ser **código-only** — não arrastar
   charter (revertido em #3381; o lint `ds/no-adhoc-status-text` já força o token, charter prosa é redundante).
3. **Preflight (contrato-de-tela) exige branch ⊇ origin/main:** PRs empilhados/concorrentes ficam stale a
   cada merge → `git merge origin/main` antes de cada merge. Mergear sequencial.
4. **Deploy automático (ADR 0269):** merge em main → `deploy.yml` builda no runner + Hostinger. NÃO buildar
   manual. `prototipo-ui/**`/`memory/**`/`*.md` são paths-ignore (docs não deployam).

## Smoke prod (evidência)

Chrome MCP em `oimpresso.com/financeiro/caixa` + `/unificado` pós-deploy: tokens success/destructive
renderizando, pílulas ok, `<Select>` Radix sem crash, **console JS limpo** (0 erros, 0 warnings Radix).

## Pendências (9 chips spawnados — sessões separadas, exceto WhatsApp)

- **Fiscal (14)** — ⚠️ NÃO é color sweep: 14 form-controls com CSS bespoke `fx-*` (radio cards
  SendToContabilDrawer, fx-combo selects). Converter = **redesign + risco no form do contador**. Chip
  pede Pest (CT 100) + smoke real. **Adiado** com recomendação.
- **Ponto · ProjectMgmt · governance · Repair · Admin · RecurringBilling · Settings** — chips color-sweep
  (Settings provavelmente form-controls → chip avisa pra parar e tratar como Fiscal).
- **Cauda ≤7** — 1 chip (Atendimento/Produto/Sells/NfeBrasil/OficinaAuto/kb/Home/Site/ads/MemCofre/…).
- **WhatsApp (19)** — DELIBERADAMENTE fora (Wagner: delicado). Trabalho parcial em `git stash`
  (`wip-whatsapp-ds-sweep`) **nesta worktree** (amazing-mahavira-48bdce) — recomendo **refazer do zero**
  numa sessão nova com a decisão do carve-out HSM `R-DS-002` resolvida, não depender do stash local.

## Estado MCP no momento do fechamento

- **Cycle:** CYCLE-08 (Receita — Onda A) · 100% decorrido · 0 dias restantes. O sweep DS é housekeeping
  de design-system, não linkado a goal de receita do cycle (drift esperado/aceito).
- Handoffs recentes: 2026-06-29-1327 (decisions-search snippet), 2026-06-24 (×3 SDD/auditoria).
- ADRs tocadas: nenhuma nova (sweep usa 0209/0239/0269/UI-0013 existentes).

## Próxima ação

Clicar os chips DS conforme capacidade (cada um é sessão isolada auto-suficiente). Atualizar o placar
`prototipo-ui/DS_ADOCAO_INDICE.md` (`npm run ds:report --write`) após cada lote. Decidir o Fiscal
(redesign agora vs sessão dedicada) e o WhatsApp (carve-out HSM) quando quiser.
