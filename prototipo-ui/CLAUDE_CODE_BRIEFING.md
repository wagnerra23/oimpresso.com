# CLAUDE_CODE_BRIEFING.md — pra Claude Code (que sou eu) trabalhando neste loop

> Lê este arquivo PRIMEIRO ao receber pedido pra processar trabalho do `prototipo-ui/`.

## 1. Quem você é neste loop

`[CL]` — Claude Code rodando localmente. Sua função é **traduzir** protótipo aprovado em `prototipos/<tela>/page.tsx` (export Cowork, design system genérico) pra `resources/js/Pages/<Mod>/<Tela>.tsx` (Inertia + React 19 + Tailwind 4 + canon Cockpit V2).

Você NÃO desenha visual. Quem desenha é `[CC]` (Cowork). Você traduz, ajusta, integra com backend, valida acessibilidade.

## 2. Stack canônica (3 frases)

- Laravel 13.6 + PHP 8.4 + Inertia v3 + React 19 + Tailwind 4 + nWidart/laravel-modules
- Multi-tenant Tier 0 IRREVOGÁVEL: `business_id` global scope ([ADR 0093](../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
- Padrão Cockpit V2 ([ADR 0110](../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)) — header sticky + abas + footer ações + drawer detail

Detalhes: [memory/what-oimpresso.md](../memory/what-oimpresso.md).

## 3. Tradução protótipo → Inertia (regras duras)

| Cowork (genérico) | Inertia oimpresso |
|---|---|
| `<button>` raw | `<Button>` shadcn |
| `<input>` raw | `<Input>` shadcn |
| `<table>` raw | `<DataTable>` (Components/shared) |
| `<dialog>` / modal full | `<Sheet>` shadcn (drawer lateral) |
| `<select>` raw | `<Select>` shadcn (Radix) |
| Cores hex inline (`#3b82f6`) | tokens semânticos (`bg-primary`, `text-foreground`) |
| Texto em inglês | PT-BR (regra dura — Wagner+Eliana+Larissa BR) |
| Estado mock local (`useState`) | props Inertia + `useForm` quando faz POST |
| Routes hardcoded (`/sells`) | `route('sells.index')` (Ziggy) |
| Sem multi-tenant | `business_id` no controller (skill multi-tenant-patterns) |
| Date `new Date()` | `format_date($x)` server-side (preserva shift +3h ROTA LIVRE) |
| Money `$1,000.00` | `formatCurrency()` PT-BR (`R$ [redacted Tier 0]`) |

[GLOSSARY.md](GLOSSARY.md) tem mapa completo.

## 4. Fluxo no F3 (CODE)

```
1. Lê HANDOFF.md → identifica tela em F3 pendente
2. Lê prototipos/<tela>/COMPARISON.md → 15 dimensões
3. Lê prototipos/<tela>/page.tsx → fonte de verdade visual
4. Lê prototipos/<tela>/critique-score.json → o que reforçar/melhorar
5. Lê resources/js/Pages/<Mod>/<Tela>.tsx (se existe) → diff visual
6. Edit/Write na <Tela>.tsx — preserva backend, troca visual
7. Atualiza/cria <Tela>.charter.md (Mission/Goals/Non-Goals/Anti-hooks)
8. Build local (npm run build) — sem erros TS
9. Append em CODE_NOTES.md
10. Append em SYNC_LOG.md
11. Atualiza HANDOFF.md (próxima fase F3.5)
12. Abre PR draft com label `mwart-from-cowork`
```

## 5. Auto-check (responde 3 perguntas pra confirmar entendimento)

Quando ler este briefing pela primeira vez na sessão, registra em `CODE_NOTES.md` resposta pras 3:

1. **Quem aprova merge final?**
   Resposta esperada: `[W]` (Wagner), nunca `[CL]` sozinho

2. **Onde vive o protótipo Cowork?**
   Resposta esperada: `prototipos/<tela-kebab>/page.tsx` (read-only, vem do export Cowork — não editar direto)

3. **Qual skill orquestra este loop?**
   Resposta esperada: `mwart-comparative` V4 (Tier A always-on)

Se errar qualquer uma, RE-LÊ este arquivo. Não prossegue.

## 6. Quando NÃO usar este loop

- Bug fix direto sem mudança visual → fluxo normal de PR, sem `prototipo-ui/`
- Tela superadmin/interna sem cliente vendo → `/screenshot-override` ok
- Mudança de copy só (texto) → `design:ux-copy` direto, sem F1
- Mudança de schema/lógica de negócio → MWART process completo, não só visual

## 7. Regras de Tier 0 que continuam valendo

Mesmo neste loop, Tier 0 não cede:

- ❌ Nunca rodar daemons no Hostinger ([ADR 0062](../memory/decisions/0062-separacao-runtime-hostinger-ct100.md))
- ❌ Nunca tocar tabelas core UltimatePOS sem bridge
- ❌ Nunca ZERO auto-mem privada ([ADR 0061](../memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md))
- ❌ PII reais em commit/log/PR ([ADR 0093](../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
- ✅ Sempre PT-BR, sempre `business_id` scopado, sempre Pest se mexe em regra negócio

## 8. Refs

- [PROTOCOL.md](PROTOCOL.md) — regras formais
- [GLOSSARY.md](GLOSSARY.md) — mapa termos design ↔ shadcn
- [Skill mwart-comparative V4](../.claude/skills/mwart-comparative/SKILL.md)
- [Padrão Cockpit V2 — ADR 0110](../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [memory/what-oimpresso.md](../memory/what-oimpresso.md) — stack
- [memory/proibicoes.md](../memory/proibicoes.md) — Tier 0
