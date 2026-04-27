# Modules/Help — SPEC (resumo legado)

## Propósito

Centraliza ajuda, documentação, vídeos, fóruns e treinamentos. A maior
parte do "conteúdo" são iframes que apontam para sites externos
hospedados pela WR2 (gitbook, doc.oimpresso, oimpresso.com/ajuda).

Também inclui uma API REST sob `/help/api` (espelho do Connector) usada
pelo app mobile e por integrações.

## Superfícies relevantes

### Web (`web + auth`, prefixo `/help`)

- `GET /help/api` → `HelpController@index` (landing)
- `resource('/client', ClientController)` + `GET /help/regenerate`
- Iframes (todos rendering `superadmin.iframe`):
  - `superadmin.faq`         → https://wr2.gitbook.io/faq
  - `superadmin.videos`      → https://doc.oimpresso.com/home
  - `superadmin.foruns`      → https://oimpresso.com/ajuda/forums
  - `superadmin.treinamentos` → https://oimpresso.com/ajuda/comunidade/como-funciona

### API (`auth:api`, prefixo `/help/api`)

Espelho do Connector — `business-location`, `contactapi`, `unit`,
`taxonomy`, `brand`, `product`, `tax`, `table`, `user`, `sell`,
`expense`, `cash-register`, `attendance` etc.

### Install

`GET/POST /help/install`, `/install/uninstall`, `/install/update`.

## Riscos regressivos conhecidos

1. Renomear a view `superadmin.iframe` quebra todas as 4 rotas de iframe
   silenciosamente (são closures).
2. Trocar URL externa por engano vaza tráfego para terceiros.
3. Remover `auth:api` na API expõe dados de qualquer business.

## Cobertura de testes (batch 7)

- `tests/Feature/Modules/Help/HelpIframeTest.php`

Filtro: `vendor/bin/pest --filter=Help`

## Recomendação

**MANTER, MAS REFATORAR.** Os iframes deveriam ser configuráveis
(uma tabela `help_links`) ao invés de URLs hardcoded em closures.
Hoje qualquer mudança de domínio exige PR. API REST tem grande
sobreposição com Connector — avaliar consolidação no roadmap.
