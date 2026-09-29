# catalog/assets.md — Fontes, ícones e imagens

> Tudo que a UI carrega. Regra de completude: o protótipo deve rodar offline — nada remoto sem registro aqui.

| asset | path / origem | tipo | usado por | tratamento |
|---|---|---|---|---|
| Inter (400–800) | Google Fonts `@import` em `app/oimpresso-tokens.css:7` | fonte | global (`--font-sans`) | **port** — instalar como fonte local no alvo |
| JetBrains Mono (400–600) | Google Fonts `@import` em `app/oimpresso-tokens.css:7` | fonte | números/mono (`--font-mono`, `.oi-mono`, `.oi-money`) | **port** — fonte local |
| Ícones | `app/icons.jsx` (`window.Ic.*`) | SVG inline (JS) | todas as telas | **reference** — mapear para o icon set do alvo (lucide) |
| Chevron do `<select>` | data-URI SVG em `.oi-select` (`oimpresso-tokens.css`) | SVG embutido | selects de formulário | port junto com a classe |
| Avatares | CSS gradients `.oi-av-1..6` | CSS puro (sem imagem) | avatares de usuário/empresa | port |
| Ilustrações/fotos | **nenhuma** — a UI não usa bitmaps | — | — | — |
| Referência visual | `reference/*.png` | PNG | gate de fidelidade | apenas comparação (não vai pra produção) |

## Observações
- **Zero dependência de bitmap.** Thumbs de produto (`.oi-thumb`), cubos de KPI e sparklines são desenhados via CSS/SVG inline — reproduzíveis 1:1 sem assets externos.
- **Fontes remotas:** o único recurso de rede é o `@import` do Google Fonts. No alvo, prefira empacotar Inter + JetBrains Mono localmente (offline-first).
