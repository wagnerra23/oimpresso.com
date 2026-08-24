# PROMPT PARA CLAUDE CODE — Refino v2 dos primitivos de layout + relatório + porquê na memória

> **[W] cola isto UMA VEZ no Claude Code. Não toca em mais nada.**
> Origem: avaliação [CC] dos primitivos de layout (ADR 0253), 2026-06-07. As URLs valem ~1h — se expirarem, [CC] regenera.
> **[CC] é read-only no git** — quem commita é você ([CL]). Eu (CC) só produzi os arquivos finais + este prompt.

## Contexto (1 parágrafo)

A ADR 0253 criou `resources/js/Components/layout/` (Box/Stack/Inline/Grid/Container/Text) — arquitetura ótima, mas a v1 não expressa o que o próprio ERP usa: número mono/tabular, tom de KPI, grade responsiva, escala até 4xl, superfície no Box, e o Container usa `max-w-screen-*` (removido no Tailwind v4). A avaliação [CC] (nota 7.6/10) produziu o **refino v2** que fecha esses gaps — aditivo, sem breaking change, fiado nos tokens reais do `@theme` (`inertia.css`). Projeção: 9.2/10.

## Passo 1 — Aplicar o refino v2 (sobrescreve os 7 arquivos da camada)

```bash
cd resources/js/Components/layout

curl -fsSL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/resources/js/Components/layout/box.tsx?t=de7cec538498688c9bcadc0f56a422c74934884475addfbff1b5db5832fe0399.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1780836141.fp&direct=1" -o box.tsx
curl -fsSL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/resources/js/Components/layout/stack.tsx?t=421cbc1c992abe9535087cb66463f19838f2b3189b6b4f07fed956aaf38bb017.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1780836142.fp&direct=1" -o stack.tsx
curl -fsSL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/resources/js/Components/layout/inline.tsx?t=d7f5a1bc07f45a0e817460f820ef1a932655c8db46314fa5c41491d32b1728d9.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1780836143.fp&direct=1" -o inline.tsx
curl -fsSL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/resources/js/Components/layout/grid.tsx?t=b433adb1b722d43f4b2929fb4b4093581913dc7e673f2bd1f0afd34463ed95a5.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1780836144.fp&direct=1" -o grid.tsx
curl -fsSL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/resources/js/Components/layout/container.tsx?t=a941cd9c4bebf3218b2b6198542c9cef584d7ba3aade1e0c136a06b72808e430.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1780836144.fp&direct=1" -o container.tsx
curl -fsSL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/resources/js/Components/layout/text.tsx?t=569f07f3e62b7aaf68e821d90eb86c5ad3d8f709b41b96de359c1dde4f983b88.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1780836145.fp&direct=1" -o text.tsx
curl -fsSL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/resources/js/Components/layout/index.ts?t=1b946c843616a1e7ff398397415e41d862e040c1d44252c0e02c7374266b79c2.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1780836146.fp&direct=1" -o index.ts

cd -
```

## Passo 2 — Salvar o porquê + o relatório na memória

```bash
# Proposta de emenda à ADR 0253 (o racional). VOCÊ ([CL]) numera/versiona sob OK de [W] — NÃO use número alucinado.
curl -fsSL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/memory/decisions/_PROPOSTA-amend-0253-primitivos-layout-completos.md?t=735464d9dc44f5a5ffd4208078cffc3b8b184e1563ed4ee9e4a88641845efd2b.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1780836147.fp&direct=1" -o memory/decisions/_PROPOSTA-amend-0253-primitivos-layout-completos.md

# Log de sessão
curl -fsSL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/memory/sessions/2026-06-07-avaliacao-e-refino-primitivos-layout.md?t=db988d92176c3b13931dbcc5d10387b9c7d6b4f5a05cd7dbd28d3bdff9bf14b3.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1780836148.fp&direct=1" -o memory/sessions/2026-06-07-avaliacao-e-refino-primitivos-layout.md

# Relatório de avaliação (HTML) — guardar nos audits do DS
curl -fsSL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/Avaliacao%20-%20Primitivos%20de%20Layout%20(ADR%200253).html?t=d5ca826d30e1ac77147f979ef1f2b614fd99076e5ed2cdc2b27da3dd66196261.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1780836154.fp&direct=1" -o memory/requisitos/_DesignSystem/audits/2026-06-07-avaliacao-primitivos-layout.html
```

## Passo 3 — Numerar a emenda + validar + commitar

1. **Numere a emenda**: renomeie `_PROPOSTA-amend-0253-*.md` pro próximo ADR livre (confirme o número no `main`, monotônico — NÃO alucine; é a colisão recorrente L-09). Preencha `amends: 0253`, `status: aceito`, `decided_by: [W]`.
2. **Rode os gates** (todos devem passar / baseline não cresce):
   ```bash
   npm run typecheck && npm run lint && npm run stylelint
   npm run foundation:check && npm run conformance:check
   ```
3. **Verifique o fix do Container** (fecha o risco TW v4 que [CC] não testou): renderize `<Container size="xl">` e confirme `max-w-7xl` aplicado no DOM (ou `npm run build` + grep do CSS gerado).
4. **Tela piloto** (critério de pronto da ADR 0253): componha ≥1 tela 100% primitivos — KPI em `<Text family="mono" numeric="tabular" size="4xl">`, grade em `<Grid min="md">`, card em `<Box bg="card" rounded="lg" border>`. `grep` não deve achar `className="...flex` solto nem `.css` da tela.
5. **Doc + REGISTRY**: adicione a camada ao REGISTRY de componentes e à doc do DS.
6. **Commite e abra PR**:
   ```bash
   git checkout -b design/primitivos-layout-v2-refino
   git add resources/js/Components/layout/ memory/decisions/ memory/sessions/ memory/requisitos/_DesignSystem/audits/
   git commit -m "design(layout): refino v2 dos primitivos — superfície no Box, divider, Grid auto-fit, Container max-w v4-safe, Text mono/tabular/tons/escala (emenda ADR 0253)"
   git push -u origin design/primitivos-layout-v2-refino
   gh pr create --fill
   ```
   Merge autônomo `gh --admin` quando CI verde (loop 0-humano), **exceto** se você for adicionar `--font-mono` ao `@theme` → isso é **Fundações = Tier 0**, para e pede [W].

## Tier 0 (NÃO faça sem [W] explícito)
- Adicionar `--font-mono` (IBM Plex Mono) ao `@theme` do `inertia.css`. O `family="mono"` já funciona (mono do sistema); o token é decisão de Fundações.
- Numerar/aceitar a emenda da ADR (só [W] aprova mudança de constituição).

## Resumo do que muda (pra você conferir o diff)
- **Box** +`bg`/`rounded`/`border` (tokens `@theme`). **Stack/Inline** +`divider`. **Grid** +`min` (auto-fit). **Container** `size`→`max-w-*` v4-safe. **Text** +`family`/`numeric`/`leading`, +tons `success/warning/destructive`, `size` até `5xl`. Exports inalterados.
