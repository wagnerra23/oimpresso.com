---
sessao: "A2"
titulo: "ALVO cliente--grupos--index — saída da thread"
autor: "[CL]"
data: 2026-10-05
base: origin/main cb1fe1d6f4
thread: A2-alvos.md
veredito: "entregue — 1 alvo medido (8 seções, 0 ausentes), 2× byte-idêntico; secao-check conforme."
---

# _saida-A2 · Alvo de Grupos de cliente

## Premissa conferida antes de medir

- `git log origin/main -- 'governance/design/targets/cliente--*'` vazio e nenhum `cliente--*.alvo.json` em `cb1fe1d6f4`: a ficha (base `ca44a3d54cd2`) segue valendo.
- Nenhum PR aberto toca `targets/cliente*`, `cliente-grupos` ou `Cliente/Grupos`. `whats-active` (3h) sem sessão nesses paths.
- D2 respondida (tela própria `Cliente/Grupos`), então a dependência da thread está satisfeita.
- A rota existe no protótipo: `app.jsx:801` → `window.ClienteGruposPage` (`cliente-grupos.jsx`).

## O que saiu

| tela | slug | rota no protótipo | seções |
|---|---|---|---|
| Grupos de cliente | `cliente--grupos--index` | `cli-grupos` → `ClienteGruposPage` (`cliente-grupos.jsx`) | 8 · header · voltar · titulo · resumo · acoes · tabela · cabecalho · linha |

Arquivos: `governance/design/targets/cliente--grupos--index.{secoes,alvo}.json`. O `.alvo.json` é saída do `alvo:medir`, não editado à mão.

## Como foi medido

- Espelho servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`, raiz = `MIRROR_DIR`) na porta 5571.
- **Sanidade antes:** `alvo.mjs --alvo … --tela crm--leads--index --rota crm-leads` contra o mesmo servidor, gravado fora do repo: as 6 seções saíram idênticas ao `crm--leads--index.alvo.json` versionado (só `nos_totais` 892 × 891, campo informativo).
- Seletores colhidos com `alvo:mapa --rota cli-grupos` no DOM vivo (raízes `.cg-page`, `.cg-page > .os-page-h`, `… > .os-page-h-l`). A tela não tem `data-contract`: seletores estruturais. A `linha` é a 1ª `tbody > tr.cli-row` (a `tableRow` que a ficha pede).
- `node scripts/design-sync/alvo.mjs --alvo http://127.0.0.1:5571/ --tela cliente--grupos--index --rota cli-grupos --secoes governance/design/targets/cliente--grupos--index.secoes.json --quieto-ms 2000`, duas vezes; sha256 (16 primeiros) idêntico nas duas: `20543f4a8f23bfc0`.
- Viewport 1280×900, tema escuro (default do `alvo.mjs`, que espera `__oiLazyDone` e 2000 ms sem mudança no nº de nós). Conferido no próprio alvo: `titulo` com texto `oklch(0.94 0.005 90)` sobre fundo `oklch(0.26 0.006 240)`, razão 13,02. `nos_totais` 488. `ausentes: []`.
- `node scripts/qa/secao-check.mjs --tela cliente--grupos--index --servir-espelho`: **conforme**, 8 seções.

## Provas do json conferidas

- `governance/design/targets/cliente--grupos--index.alvo.json` com a chave `secoes` — existe (8 seções).

## Pendente (não inventado)

1. **Modal Novo/Editar grupo fora do alvo.** Só existe após clique em "Novo grupo" ou no kebab da linha. Se o contrato da thread 03 quiser o modal, é outro alvo com `--clicar`.
2. **`tableRow` em `__DD_ROLES`** (pedido da ficha) é do `design-diff`, não do `alvo.mjs` — no alvo, `base.roles.tabela` sai `null`. A linha da tabela está coberta aqui pela seção `linha`. Mesmo caso do `_saida-A1` do Crm.
3. A tabela "Alvos exportados" do `governance/design/targets/README.md` não foi atualizada: o README está fora do `prefixo`.
4. O alvo mede o protótipo, não o Blade vivo `customer-group`. A tela Inertia é a thread 03.

## Placar

entregue 1 de 1 alvo · 8 de 8 seções medidas · ausentes 0.

## PR

O PR que adiciona este arquivo — branch `claude/cliente-thread-a2`.
