# [CC]→[CL] · HANDOFF MESTRE — Sessão 2026-06-20 (Caixa + roxo canon + dark em todas as telas)

> **Zero-toque [W]:** este doc é o índice único da sessão. O Wagner cola este prompt UMA vez no Claude Code.
> **[CC] é read-only no git** — nada aqui está commitado; o Code transporta.
> **Validar vs `main` (§10.4): repo vence.** Não cunhar ADR (constituição = Tier 0 = só [W]).
> **Princípio geral:** o protótipo Cowork é **fonte visual**; produção (React 19 + Tailwind 4 + Inertia) **traduz**. Não copiar CSS/oklch cru — usar utilitários de token (`bg-card`, `text-foreground`, `bg-*/10`, `text-primary`).

---

## Referências (curl — efêmeras ~1h; se expirar, [W] pede que eu regenero)
```bash
# Guias da sessão (contêm o detalhe por onda)
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/PROMPT_PARA_CODE_CAIXA-FOTO-ROXO-FILTRO-CONTEXTO.md?t=af6a8c66861651b84cc69d2621e94e8cbf55dd4f3019884f2030dd5a02f406d3.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1782007952.fp&direct=1" -o /tmp/CAIXA.md
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/LISTA_RECONCILIACAO_CAIXA_COWORK-vs-PRODUCAO.md?t=8940dad929d76d7dde31dec8461e68b575d6a27c5606393e42c7efeae56d41a8.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1782007948.fp&direct=1" -o /tmp/RECONCILIACAO.md
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/AUDITORIA_DARK_TELAS_ATIVAS.md?t=14b287a4ee9e84eb9f6754685100267d61bd8293c332c75fe533f71e7b12e18a.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1782007950.fp&direct=1" -o /tmp/AUDITORIA_DARK.md
# Referência visual dos valores (tokens dark + chips)
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/prototipos/caixa-unificada/inbox-page.css?t=de7a78a700e370e5a785cfc7493069bc2d0bf01ec92aba8ab9aea3be1cb54c97.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1782007953.fp&direct=1" -o /tmp/inbox-page.css
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/prototipos/_dark-tier1/oficina-os-page.css?t=9d3ee499dd758611927e0a2de7cac4a9c75b8212278a1774b7d11af99cc6a38f.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1782007955.fp&direct=1" -o /tmp/oficina-os-page.css
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/prototipos/_dark-tier2/clientes-page.css?t=8ccb09ec8e70792a12a1ddd2fad0455148228cbd2253f0627723a9e8f1e662a1.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1782007956.fp&direct=1" -o /tmp/clientes-page.css
```

---

## VISÃO GERAL — 3 frentes, 9 ondas

| Frente | Ondas | Estado protótipo | Tipo |
|---|---|---|---|
| **A. Caixa Unificada** (4 comments [W]) | A1–A4 | ✅ pronto | feature + re-skin |
| **B. Roxo canon (anti-verde-de-marca)** | B1–B2 | ✅ pronto | re-skin |
| **C. Dark em todas as telas** | C1–C2 | ✅ pronto | re-skin |

Cada onda abaixo é **autônoma e mergeável** (PR separado, para no gate visual F2). Ordem sugerida no fim.

---

## FRENTE A — CAIXA UNIFICADA · `resources/js/Pages/Atendimento/CaixaUnificada/`
> Detalhe completo em `/tmp/CAIXA.md`. Confronto com produção em `/tmp/RECONCILIACAO.md` (**produção avançou muito — não regredir as 9 seções do ContextSidebarV4, nem a IA "Inteligência", nem o assignee/memory**).

- **A1 · Avatar com foto WhatsApp** — backend expõe `avatar_url: string|null` em `CaixaUnifConversation`+`CaixaUnifThread` (profile pic via whatsmeow/Baileys `GetProfilePictureInfo`, coluna nova). Front: `<img …object-cover rounded-full>` com fallback `initials()`+`avatarHue()`. Mantém badge de canal + dot de presença.
- **A2 · Status no popover Filtros** — remover botão Status standalone; vira 1º grupo de chips dentro do Popover "Filtros" (badge `countActiveFilters` já conta status). 1 botão (funil) no header. URL-sync intacto.
- **A3 · Contexto** — ⚠️ **DECISÃO [W]:** protótipo fez **drawer flutuante**; produção usa **coluna recolhível (trilho 44px, sem scrim)**. **Default recomendado = manter o padrão de produção** (não portar o drawer). Confirmar com [W].
- **A4 · Fundo dark = `bg-card` neutro** — garantir que superfície ≠ acento (ver Frente C). No protótipo já corrigido.

## FRENTE B — ROXO CANON (`--accent` oklch(0.55 0.15 295) · anti-verde-de-marca)
- **B1 · Canal WhatsApp** — no catálogo de canais do `CaixaUnificadaController`: `hue: 145 → 295` (todos os `wa_*`). Repinta glyph/badge/chips do canal. **Preservar** verdes semânticos (SLA-fresh, presença, sim/não do troubleshooter).
- **B2 · Vendas modo apresentação** — `.vd-pres-chip.primary` e o número do apresentador eram verde-155 (cor de marca) → roxo. Em produção, garantir que esses elementos usam `bg-primary`/`text-primary`, não verde.

## FRENTE C — DARK EM TODAS AS TELAS
> Auditoria completa por tela em `/tmp/AUDITORIA_DARK.md`. **Regra de ouro:** dark é automático se a tela usa só tokens; quebra só com **cor crua**. **Nunca** criar sistema paralelo (`--omd-*` da Caixa foi anti-padrão).

- **C1 · Tier-1 (quebra a tela toda)** — 3 telas: `OficinaOS` (paleta `.ofx` crua → tokens), `mockup-pages` (re-flip de `--surface/--bg`), `Oficina` (`bg-white` → `bg-card`). Em produção = trocar `bg-white`/`text-stone-900`/`border-stone-200` por `bg-card`/`text-foreground`/`border-border`. Itens "papel" propositais (placa Mercosul, A4 da transmissão `.vd-trans-page`) **continuam brancos**.
- **C2 · Tier-2 (soft-chips pastéis)** — 10 folhas (`crm, prod, cobranca, clientes, forja, kb, boletos, equipe, pg, vendas`). Chip pastel-claro-fixo no dark deve virar `bg-*/10 text-*` semântico (success/warning/destructive/primary), que já flipam. No protótipo: bloco `[data-theme="dark"] .chip{ background:oklch(0.27 c h); color:oklch(0.84 c h) }` gerado no fim de cada CSS — usar como **referência de hue**, não portar oklch cru.

---

## Receita de tradução (Cowork CSS → produção Tailwind)
| Protótipo (referência) | Produção (usar) |
|---|---|
| `background: var(--surface)` / `white` de superfície | `bg-card` / `bg-background` |
| `color: var(--text/-2/-3)` | `text-foreground` / `text-muted-foreground` |
| `border: var(--border)` | `border-border` |
| `oklch(0.55 0.15 295)` (acento) | `text-primary` / `bg-primary` |
| chip `oklch(0.9x c h)` + `oklch(0.4x c h)` | `bg-{sem}/10 text-{sem}` (success/warning/destructive) |
| `[data-theme="dark"] .x{…}` cru | `dark:` utilitário derivado de token |

## Ordem sugerida de PRs (cada um para no gate F2)
1. **B1** (hue WhatsApp no Controller) — menor, isolado.
2. **A2** (Status no Filtros) — só apresentação.
3. **C1** (Tier-1 dark) — 3 telas, corrige quebra real.
4. **C2** (Tier-2 dark) — re-skin amplo, baixo risco.
5. **B2** (Vendas apresentação roxo).
6. **A1** (avatar foto) — precisa backend `avatar_url`, US própria.
7. **A3** — só depois de [W] decidir drawer vs coluna (**default: não fazer / manter produção**).

Ao concluir cada onda: marcar `[PROCESSADO 2026-06-20]` na referência + retorno em `CODE_NOTES.md`. Cowork permanece read-only no git.
