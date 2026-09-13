---
sessao: "_saida-05"
thread: "05 · Ghosts × ADR 0180 — emenda (UI-0029 corolário 4)"
dono: "[CL]"
data: 2026-09-10
prefixo_tocado: memory/decisions/0180-sidebar-v3-5-grupos-ghosts-header.md
base_lida: wagnerra23/oimpresso.com@main — 1e384b443c
---
# _saida-05

## Feito

**Emenda datada anexada à ADR 0180** ([PR #7186](https://github.com/wagnerra23/oimpresso.com/pull/7186), mergeado
2026-09-10, label `adr-body-edit-W` conforme [ADR 0377](../../../../memory/decisions/0377-append-only-adr-excecao-por-label-emenda-0094.md)).
Append, não deleção — ADR canon é append-only, e a UI-0029 diz *"a ADR é emendada ou superseded"*.

**Cai** a cláusula visual do §Decisão 3: *"Hierarquia segue in-screen, não in-sidebar"* e o ghost
exclusivo na Zona C do PageHeader.

**Fica** o que não é forma: 5 grupos canônicos · hue por grupo · Cmd+K · Pinned/Favoritos ·
atalhos `G X` · Contrato DataController v2 · LEGACY_GROUP_MAP · Multi-tenant Tier 0 · métricas.
A Zona C **não é revogada** — vira complemento.

## A descoberta, e ela é sobre o processo, não sobre ghosts

**Esta thread não deveria ter existido como decisão.** A [ADR UI-0029](../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)
(accepted 2026-08-28, ratificada 08-31) já resolvia, e dois corolários dela são explícitos:

- **corolário 4** — *"ADR de núcleo (`memory/decisions/`) que decide visual de tela entra aqui pela
  cláusula visual"*. É o que alcança a 0180, que não vive em `adr/ui/`.
- **corolário 1** — *"Divergência é DEFEITO, não pauta. O agente não devolve a [W] 'qual dos dois
  vale?' — a regra já respondeu."*

O `RESÍDUO-1` do índice era exatamente o que o corolário 1 proíbe. Eu revisei este playbook na
aterrissagem, achei 13 defeitos, e **não cruzei com a UI-0029** — foi preciso [W] repetir em 09-10
uma regra que ele deu em 08-28: *"remover a LEI ADR 0180, quero igual ao protótipo."*

Mesma falha no `RESÍDUO-3`: modo `hidden` é forma, o protótipo tem, logo o shell ganha — nunca foi
pergunta. Os dois foram marcados `respondida: true` no §7 com a razão, não com um despacho novo.

## Medição

Os dois lados **já concordavam**; quem divergia era a ADR:

| o que | protótipo `sidebar.jsx` | vivo `Sidebar.tsx` |
|---|---|---|
| ghosts sob o item ativo | `GhostList` `:180` | `:569` |
| teto 5 + "⋯ mais N" | `GHOST_TETO = 5` `:179` | `:544` |
| contador de telas por hub | `ItemEnd`/`ghostCount` `:31` | `:642` `telas={ghosts.length}` |

Placar, antes → depois da emenda: `próximo 1 · bloqueada 1` → `próximo 2 · bloqueada 0`.

## Não feito, e por quê

- **O comentário em `Sidebar.tsx:529`** afirma que o contador *"NÃO vem junto: ghost no sidebar
  contraria a ADR 0180"* — enquanto `:642` o passa. Está errado desde 08-28. Não corrigi aqui
  porque `Components/cockpit/` está fora do prefixo desta thread (Lei 1); a **04** toca esse arquivo
  e leva o conserto junto. Registrado na emenda.
- **AP19 e as telas que o citam** não foram revistos. A emenda diz que a Zona C vira complemento,
  mas não varri quem cita o AP19 esperando exclusividade. É trabalho de quem tocar o PageHeader.
- **`app/Sidebar/SidebarGhost.php`** segue emitindo `ghosts[]`, agora com dois consumidores legítimos
  (sidebar + PageHeader). Nada a fazer — a pergunta "fica sem consumidor?" só existia na saída B,
  que não foi a escolhida.
