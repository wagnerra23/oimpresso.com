# reference/ — Cobertura de referência visual

Screenshots-alvo para o gate de fidelidade. Cada frame mostra iOS (iPhone 15) + Android (Pixel 8) lado a lado, como o protótipo roda.

## Coberto (referência PNG)
| tela | arquivo | rota (nav) |
|---|---|---|
| Início (dashboard) | home.png | inicio |
| Oficina — pátio | oficina.png | manutencao |
| Pedidos (lista) | pedidos.png | pedidos |
| Detalhe do pedido/OS | pedido.png | pedido |
| Clientes/Pessoas | clientes.png | clientes |
| Mais (menu por perfil) | mais.png | mais |

## Ainda sem PNG (30 telas)
As demais telas têm **build sheet** (`catalog/screens/*.md`) + **fonte** (`app/screens-*.jsx`), que já fixam estrutura, componentes e classes. Para o gate visual dessas, capture navegando pela UI:
1. Abrir `app/Oimpresso Mobile.html`; usar a rota em `catalog/routes.md` (`nav.push` via clique nos itens).
2. Telas de detalhe: clicar a 1ª linha/card da lista da aba correspondente.
3. Telas de módulo (financeiro, produção, relatórios, equipe, perfis…): acessar pela aba **Mais**.

> A referência é um auxílio de verificação, não a fonte estrutural. Estrutura vem da fonte + build sheet; estas 6 capturas cobrem os pontos de entrada de cada fluxo principal.
