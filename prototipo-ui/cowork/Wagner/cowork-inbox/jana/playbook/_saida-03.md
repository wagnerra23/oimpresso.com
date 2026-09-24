---
sessao: "_saida-03"
thread: "03 · Jana/Pro: tirar os style={{}} inline (cores → tokens/classes)"
dono: "[C]"
data: 2026-09-23
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main 1061dbf2e
---
# _saida-03

## Entregue
`resources/js/Pages/Jana/Pro.tsx`: os **13** atributos `style` do card de prova (ilha dark do hero)
viraram classes Tailwind que apontam para os **mesmos** tokens `--sb-*`. **Zero mudança visual**, e
isso foi medido, não suposto (ver abaixo). Nenhum token novo, nenhum arquivo além do `Pro.tsx`.

## Divergência do pedido original, declarada
O pedido de 26/08 (`../JANA-PRO-SEM-INLINE-2026-08-26.md`) descrevia um estado que **já não existe**:
falava em `oklch(...)` cru no `style`, mas hoje os valores já são `var(--sb-*)`. Aplicar o patch
dele **trocaria cores**: a bolha passaria de `--sb-active` para `--sb-hover`, o véu de
`--sb-accent-glow` para `--primary`, e o positivo de `--sb-pos` para `--success`. Esse último é
justamente o token com par de tema que o comentário do arquivo proíbe na ilha: no tema claro o
verde escurece sobre fundo escuro. Por isso o patch não foi aplicado; os tokens atuais ficaram.

**`resources/css/cockpit.css` não foi tocado**, embora esteja no prefixo. Motivo medido: o
`scripts/governance/ui-impact.mjs` classifica `resources/css/**` como `fundacao-visual`, com escopo
**global**. O `Pro.tsx` sozinho fica `targeted` (`page-inertia`).

## Provas medidas
1. Placar: as 2 provas `nao_contem` do índice ficaram verdes.
2. `grep -c 'style={{' resources/js/Pages/Jana/Pro.tsx` → **0** (era 13).
3. CSS compilado pelo Tailwind 4.3.3 (o do repo) para as 11 classes: cada uma emite exatamente a
   declaração de antes (`background-color: var(--sb-bg)`, o mesmo `radial-gradient(...)` etc.).
4. **Cor computada antes→depois, em produção** (`/ia/pro`, DOM estável 726/726):
   - **antes:** as 13 cores medidas por `getComputedStyle` no build no ar;
   - **depois:** na mesma página, o CSS compilado injetado em `@layer utilities`, os `style`
     removidos e as classes aplicadas nos 13 elementos.

   As 13 cores saíram **idênticas**. Única diferença: o `box-shadow` do marcador ganhou 4 camadas
   `rgba(0,0,0,0) 0 0 0 0`, padrão do Tailwind e sem efeito visível. Trocando o tema (claro ↔
   escuro), o card, a bolha, o "Caixa" e o texto apagado dão os mesmos valores. `style` dentro da
   ilha: 13 → 0.
5. `node scripts/layout-primitives-guard.mjs` → sem regressões (rc 0).

## Não medido
- **T7 (`design-diff --compare --check`) no build deployado:** fica para depois do merge. A medida
  acima é a mesma sonda (`getComputedStyle`) nos dois lados, mas a do "depois" usou o CSS injetado,
  não o bundle final do Vite.
- typecheck/eslint locais: o worktree não tem `node_modules`, então quem roda é o CI.
