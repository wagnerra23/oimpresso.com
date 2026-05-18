# Cowork Preview — Mock Original (Wagner 2026-05-18)

Mock canon do design Cowork servido **direto** sem adaptação Inertia/React/build.
React + Babel-Standalone carregam do CDN (não roda Vite/build local).

## Quando usar

Wagner regra IRREVOGÁVEL 2026-05-18:

> "Quero o original primeiro, Mock mesmo depois de eu ter o visual correto. eu vou autorizar mudanças. esta mesclando e não esta dando certo. Pode ser só copia?"

Cherry-pick + adaptar Inertia em 1 onda só falhou 4× no Financeiro (PR #1085 → #1091 → #1092 → #1094 → #1095). Estratégia nova:

1. **PRIMEIRO** servir mock original como está — Wagner vê visual canon
2. **DEPOIS** Wagner autoriza adaptação Inertia tela por tela

## URLs

Acessar via:

- `https://oimpresso.com/cowork-preview/Oimpresso%20ERP%20-%20Chat.html` — **Shell completo** (todos módulos)
- `https://oimpresso.com/cowork-preview/Financeiro%20Unificado.html` — Tela Financeiro standalone
- `https://oimpresso.com/cowork-preview/Vendas%20A%2B.html` — Vendas A+ standalone
- (outros 27 HTMLs no diretório)

## Stack

- React 18.3.1 (UMD CDN)
- React-DOM 18.3.1 (UMD CDN)
- Babel Standalone 7.29.0 (transpila JSX no browser)
- Tailwind CDN (preflight=false, não sobrescreve styles.css)
- IBM Plex Sans + Mono (Google Fonts)
- `styles.css` (9054 LOC — bundle canon)
- 70+ JSX components

## Como ATUALIZAR

Quando Wagner mandar bundle Cowork v(N+1):

1. Extrair tarball pra `/tmp/cowork-vN/`
2. `cp /tmp/cowork-vN/oimpresso-erp-conunica-o-visual/project/*.{jsx,css,html,js} public/cowork-preview/`
3. Overwrite `Oimpresso ERP - Chat.html` + `styles.css` + financeiro-* do canon mais recente (`vendas-financeiro-completo/` ou `vendas-refino-kb-9.75/`)
4. Commit + smoke

## Regras Tier 0 desta pasta

⚠️ **NÃO EDITAR manualmente** os arquivos aqui — é mock auto-gerado do bundle Cowork.

⚠️ **NÃO IMPORTAR** desses arquivos no projeto Laravel/Inertia. Eles vivem isolados em `public/` (servidos direto pelo nginx/Apache sem PHP).

⚠️ **Vite NÃO empacota** essa pasta — não há referência em `vite.config.js` ou `vite.inertia.config.mjs`. Validar antes de cada bundle copy.

## Refs

- `memory/reference/feedback-cowork-bundle-aplicar-inteiro.md` — regra Tier 0
- `memory/proibicoes.md` §"Design System / Pacote Cowork novo"
- Tarball origem: Anthropic API design fetch (URL: `rDpq_G9tEiBeT2nb2x4vWw`)
