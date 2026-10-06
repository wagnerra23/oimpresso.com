---
sessao: "A1"
titulo: "ALVO das 7 telas soltas — 7 de 7 medidas — saída da thread"
autor: "[CL]"
data: 2026-10-06
base: origin/main 73ba361e01
thread: A1-alvos.md
veredito: "entregue — 7 alvos medidos (34 seções, 0 ausentes), cada um 2× byte-idêntico, hashes distintos entre si; secao-check conforme."
---

# _saida-A1 · Alvos das telas soltas

## Medida / não medida

| tela | slug | rota no protótipo | âncora (`ancora.mjs`) | seções | estado |
|---|---|---|---|---|---|
| Visão geral | `soltas--home--index` | `dash-legacy` → `DashLegacyPage` | `Home/Index` → `dash-legacy-page.jsx` | 7 · header · bloco_2 · bloco_3 · bloco_4 · bloco_5 · grades · rodape | **medida** |
| Base de Conhecimento | `soltas--kb--index` | `kb` → `KBPage` | `kb/Index` → `kb-page.jsx` | 4 · header · stats · abas_mobile · tri | **medida** |
| Documentação | `soltas--documentacao--index` | `documentacao` → `DocumentacaoPage` | `Documentacao/Index` → `documentacao-page.jsx` | 3 · header · lentebar · corpo | **medida** |
| Meu perfil | `soltas--perfil--index` | `perfil` → `PerfilPage` | `User/Perfil` → `perfil-page.jsx` | 3 · header · tabs · corpo | **medida** |
| Suporte · empresas | `soltas--suporte-empresas--index` | `suporte` → `SuportePage view=empresas` (`app.jsx:912`) | `Suporte/Empresas` → **n/a** (herda PT-01) | 3 · header · nota · lista | **medida** (ver ressalva 2) |
| Suporte · visão do cliente | `soltas--suporte-visao--index` | `suporte-visao` → `SuportePage view=visao` (`app.jsx:913`) | `Suporte/Visao` → `suporte-page.jsx` (bundle_source) | 7 · navrow · faixa · bloco_3 · cards · titulo_lista · lista · rodape | **medida** |
| Cobrança | `soltas--cobranca--index` | `cobranca` → `CobrancaPage` (`pg-shell-adapters.jsx`) → `PG_CobrancaPage` | `Financeiro/Cobranca/Index` → `pg-cobranca-page.jsx` | 7 · header · nav · bloco_3 · kpis · bloco_5 · filtros · lista | **medida** |

Não medida: nenhuma. Só o lado protótipo foi pedido; o lado produção logado não entrou nesta thread, então nenhum token foi preciso.

Arquivos: `governance/design/targets/<slug>.{secoes,alvo}.json` para os 7 slugs. Os `.alvo.json` saem do `alvo.mjs --alvo`, não foram editados à mão.

## Como foi medido

- Caso de sanidade antes de qualquer tela: `node scripts/design-sync/alvo.mjs --selftest --browser` → **25/25 ok** (conversão de cor provada, 2 runs byte-idênticos, injeção muda o JSON, `--aguardar-sumir`/`--clicar` em seletor que não casa → NÃO MEDI).
- Espelho servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`, raiz = `MIRROR_DIR`) na porta 5581. Playwright de `D:\oimpresso.com\node_modules`.
- Tema conferido por sonda à parte nas 7 rotas: `data-theme="dark"`, `window.__oiLazyDone === true`, `window.__route` igual à rota pedida. Dark é o default do espelho (`app.jsx`, `TWEAK_DEFAULTS.theme`).
- Seletores colhidos com `alvo:mapa --rota <r> --raiz '.main-body > *'` (e `--raiz '.pg-shell-scope > *'` na Cobrança), no DOM vivo. Onde o protótipo usa classe própria (`kb-*`, `doc-*`, `upf-*`, `sup-*`), o seletor usa a classe; onde só há estilo inline ou Tailwind (Visão geral, Cobrança, 1º bloco do Suporte), vai por posição, e o nome da seção é posicional (`bloco_N`) para não inventar papel.
- `alvo.mjs --alvo http://127.0.0.1:5581/ --tela <slug> --rota <r> --secoes …/<slug>.secoes.json --quieto-ms 2000`, duas vezes por tela com `--saida` (byte-idêntico nas duas) e uma terceira no destino canônico (idêntica às duas). sha256 (16 primeiros):
  home `e5fb8e077b7d4c15` · kb `c9fb3e54c4d72273` · documentacao `3086da7a2139f3af` · perfil `6c6dd46ca8eb4753` · suporte-empresas `22593d7b5b98ecfe` · suporte-visao `05c9a856234c68f7` · cobranca `fcc2bb8da789b6c9`. Nenhum hash repete — a parada A-LOTE do `A1-alvos.md` não disparou.
- `nos_totais`: 890 · 859 · 646 · 495 · 570 · 547 · 1113. `ausentes: []` nos sete.
- `node scripts/qa/secao-check.mjs --tela <slug> --url http://127.0.0.1:5581/`: **conforme** nos sete.

## Ressalvas medidas

1. **`perfil`, não `prefs`.** O `A1-alvos.md` lista a rota `prefs`. No `app.jsx`, `prefs` monta `PrefsPage` (`prefs-page.jsx`, "Preferências da empresa e do usuário"), que é outra tela. A âncora de `User/Perfil` resolvida pelo `ancora.mjs` é `perfil-page.jsx`, montado pela rota `perfil` (`app.jsx:785`). Medi `perfil`. Se a intenção era medir Preferências, ela não tem Page viva correspondente a `User/Perfil` e fica para o Cowork decidir no índice.
2. **Suporte · empresas não tem âncora declarada.** O charter de `Suporte/Empresas` declara `n/a (herda PT-01)`. A rota `suporte` do protótipo monta a vista `empresas` do mesmo `suporte-page.jsx` que é `bundle_source` de `Suporte/Visao`. O alvo mede essa vista; se o charter for a autoridade, este alvo é do protótipo, não de uma âncora aprovada.
3. **Nomes posicionais.** `bloco_N` na Visão geral, Suporte · visão e Cobrança é posição no DOM, não papel. A thread 01 (contratos) é quem dá nome de papel a eles, se precisar.

## Provas do json conferidas

As 7 provas `json_com_chaves` (`secoes`) do índice existem: `governance/design/targets/soltas--{home,kb,documentacao,perfil,suporte-empresas,suporte-visao,cobranca}--index.alvo.json`.

## Fora do escopo

- O `00-INDICE.md` não foi editado (é do Cowork). As ressalvas 1 e 2 são para ele.
- A tabela "Alvos exportados" do `governance/design/targets/README.md` está fora do prefixo `soltas--*` desta thread e não foi tocada.
