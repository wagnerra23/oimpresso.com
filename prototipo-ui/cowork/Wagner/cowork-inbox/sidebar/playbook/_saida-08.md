---
sessao: "08"
titulo: CORPO · cabeçalho do grupo — seta à direita + cor/raio
autor: "[CL]"
data: 2026-09-25
base: wagnerra23/oimpresso.com@main 034e47689 (o #7943, thread 07, já mergeado)
---

# _saida-08 · Cabeçalho do grupo

## Feito

Entregue **5 de 5** itens do "Faz" (a · b · c · d-rótulo · d-ícone).

| # | o que | antes (vivo) | depois | fonte do valor |
|---|---|---|---|---|
| a | ordem no `.sb-group-h` | seta · ícone · rótulo · contador | ícone · rótulo · contador · **seta** | protótipo `MenuGroup` |
| a | contador à direita | colado ao rótulo | rótulo com `flex:1 1 auto` empurra contador e seta | protótipo `.sb-group-l` |
| b | cor do cabeçalho | `var(--sb-text-dim)` | `var(--sb-text)` | 07, linha b |
| c | raio | `var(--radius-sm)` = 6px | `4px` | 07, linha c |
| d | rótulo (dark) | `oklch(0.78 0.08 h)` | `oklch(0.72 0.09 h)` | 07, linha d |
| d | ícone do grupo | inline `oklch(0.65 0.15 h)` | inline `oklch(0.65 0.14 h)` | 07, linha d (corrigida pela 07) |

- **Sem wrapper novo:** o `ChevronDown` só mudou de posição dentro do `<button>`. O empurrão vem do rótulo, com a mesma regra do protótipo. Pus `flex:1` no rótulo em vez de `margin-left:auto` no contador: sem contador (`total` 0), a seta ainda fica na direita, como no protótipo.
- **Cor só por token:** `--sb-text` já existia no bloco dark-fixo. As duas mudanças de oklch alteram valores que já estavam no bloco de hue. Nenhum literal de cor novo.
- **Mantido:** `GROUP_ICON_MAP`, contador `.sb-group-n` e `defaultOpen` com a exceção de PLATAFORMA.

## Recibos

- **Pré-condição** (`contem sb-group-n`, seta depois do contador), medida **no DOM renderizado**, não no fonte. Uma sonda vitest temporária renderizou o `SidebarMenu` e foi apagada depois:
  `book-open.sb-group-ic > sb-group-l > sb-group-n > chevron-down.chev | ic=oklch(0.65 0.14 202)` (idem para `sistema`, h=245). Último filho = `.chev` nos 2 grupos.
- **vitest:** `tests/js/sidebar-plataforma-forja.test.tsx` (6) + `tests/sidebarMenuSemantics.spec.tsx` (10) → **16 passed**.

## Não feito, e por quê

1. **Prova `comparacao` (`${REC}/08-comparacao.json`) não escrita.** Dois motivos independentes. O avaliador de recibo não foi portado (ADR 0397, o placar diz). E o alvo versionado da 07 cobre **só o modo rail** (1280×900), onde o cabeçalho de grupo não existe. O `design-diff --compare --check` da seção `sb-corpo` não tem esse elemento no alvo. A thread só vira cobrança de CI quando o `alvo.mjs` medir o expanded (resíduo 1 da `_saida-07`).
2. **Gates Pest `--filter=Sidebar` / `--filter=Cockpit` não rodados no CT 100.** O checkout de lá está em `e57b78bf5` (2026-09-21), com 14 arquivos sujos de outras sessões. Rodar ali testaria aquele checkout, não este diff. Copiar os arquivos para lá mexeria em estado compartilhado. Medido por `git grep`: nenhum teste PHP asserta cor, raio ou ordem do `.sb-group-h`. Os únicos que leem `cockpit.css`/`Sidebar.tsx` (`CockpitAccentCanonTest`, `Biz4RotaLivreSidebarTest`) checam accent e itens de menu. Fica o CI do PR como gate.
3. **Linha e (cor do contador `--text-mute` × `--sb-text-dim`) não aplicada:** a ficha pede só b · c · d.

## Descobertas (não consertadas, fora do escopo)

- **Rótulo no tema claro:** a regra light `.cockpit .sb-group[style*="--gh"] .sb-group-h .sb-group-l` pinta `oklch(0.46 0.10 h)`. A sidebar é dark-fixo nos dois temas (UI-0023) e o protótipo usa `0.72 0.09` independentemente do tema. Então, no tema claro, o rótulo escuro fica sobre fundo escuro. A 07 mediu só dark e eu não mexi no que não foi medido. Vale medir no claro antes de igualar.
- **Flyout do rail** (`cockpit.css`, regras de `.sb-rail-flyout` com `0.46 0.10` / `0.78 0.08`) ficou com os valores antigos. É prefixo da thread 11 (rail).
- **`ds-guard` em `cockpit.css`** acusa a paleta `--sb-*` (10) do bloco dark-fixo, que já existia antes. Não aplicável: a lápide §5 2026-09-04 diz que o §8 governa build de protótipo, não `resources/css/`.

## Prefixo tocado

- `resources/js/Components/cockpit/Sidebar.tsx` (`SidebarGroup`)
- `resources/css/cockpit.css` (2ª regra `.cockpit .sb-group-h`, nova `.cockpit .sb-group-h .sb-group-l`, rótulo dark por `--gh`)
- este `_saida-08.md`

Nada em `prototipo-ui/cowork/Wagner/` (build), `app/Sidebar/`, `AppShellV2.tsx`, nem no índice ou nas fichas.
