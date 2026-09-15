---
sessao: "03"
titulo: Fornecedores — aba do protótipo sem receptor no main
dono: "[W]"
base: 9101f86af501
prefixo: — (nenhum arquivo até [W] responder D-FORN)
nao_toca: resources/js/Pages/Compras/Index.tsx · qualquer Page nova
depende: D-FORN
---
# 03 · Fornecedores — BLOQUEADA

## Por que está bloqueada
O protótipo tem 3 abas (`NAV.ds-tabbar.jm-tabs`): Painel · Pedidos (7) · **Fornecedores (4)**. No `main`:
- **não existe `resources/js/Pages/Fornecedor*`** — a árvore de `Pages/Compras/` tem só `Index` (+3 componentes) e `Pages/Purchase/` tem as 4 telas do CRUD;
- fornecedor **não é entidade própria**: é `contacts` com `type=supplier` (o mesmo cadastro de contatos que serve cliente);
- `Modules/Compras/Routes/web.php` tem **2 rotas** e nenhuma de fornecedor.

Overlay sem receptor **não vira Page** sem [W] declarar a rota (§Granularidade do PROTOCOLO). Não há denominador que decida isto por leitura — é decisão de produto.

## As 3 saídas, com custo medido
| saída | o que nasce | arquivos |
|---|---|---|
| (a) **view do cadastro de contatos** | aba/filtro `type=supplier` na tela de contatos que já existe | ~1–2, e nenhum no Compras |
| (b) **tela própria do módulo** | Page + charter + casos + rota/controller + contrato | **5** |
| (c) **Non-Goal escrito** | 1 linha no charter do cockpit + a aba sai do protótipo | 1 (+ build daqui) |

## O que NÃO fazer enquanto isso
- Não criar `Pages/Fornecedor/Index.tsx` "pra adiantar" — Page sem rota é órfã e o guard a acusa.
- Não abrir rota nova em `Modules/Compras/Routes/web.php`: o SCOPE diz que o módulo é **cockpit de leitura** e que create/edit/destroy são Waves 3 e 6.
- Não transformar a aba num link pro cadastro de contatos sem [W]: isso já é a saída (a), e é decisão.

## Prova
Quando D-FORN for respondida, esta thread se reescreve com prefixo e provas reais. Até então: `respondida: false` no §7 e a thread não conta como pendência do Code.
