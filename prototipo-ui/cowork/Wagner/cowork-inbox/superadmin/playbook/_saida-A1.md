---
sessao: "A1"
titulo: "ALVO superadmin--usuario360--index + remedir as 4 medidas — saída da thread"
autor: "[CL]"
data: 2026-10-05
base: origin/main cb1fe1d6f4
thread: A1-alvos.md
veredito: "entregue 1 de 1 na prova do json — alvo do Usuário 360° medido (3 seções, 0 ausentes, 2× byte-idêntico, secao-check conforme); remedir as 4 medidas = NÃO MEDI, com a causa medida."
---

# _saida-A1 · Alvos do Superadmin

Placar: **entregue 1 de 1** (`placar.mjs --thread A1`: a única prova do json é o
`superadmin--usuario360--index.alvo.json` com a chave `secoes`). A segunda metade da ficha —
remedir as 4 medidas — **não foi feita**, e o motivo está abaixo.

## O que saiu

| tela | slug | rota no protótipo | seções |
|---|---|---|---|
| Usuário 360° (`Usuario360/Index`) | `superadmin--usuario360--index` | `sa-usuarios` → `SuperadminUsuariosPage` (`superadmin-usuarios.jsx`, app.jsx:888) | 3 · header · toolbar · vazio |

Arquivos: `governance/design/targets/superadmin--usuario360--index.{secoes,alvo}.json`. O
`.alvo.json` é saída do `alvo:medir`, não editado à mão.

## Como foi medido

- Espelho servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`) na porta 5591.
  A 5550 estava ocupada por outra sessão.
- Seletores colhidos com `alvo.mjs --mapa --rota sa-usuarios --raiz '.os-page.sa-page'` no DOM
  vivo: `div.sa-ph · div.sa-toolbar · div.sa-vazio-wrap`. Sem `data-contract` no protótipo; os
  seletores vão por classe.
- `node scripts/design-sync/alvo.mjs --alvo http://127.0.0.1:5591/ --tela superadmin--usuario360--index --rota sa-usuarios --secoes governance/design/targets/superadmin--usuario360--index.secoes.json --quieto-ms 2000`,
  duas vezes. sha256 idêntico nas duas: `fc4967cd2786c07b…`. `nos_totais` 421, `ausentes: []`.
- Viewport 1280×900, tema escuro (padrão do protótipo; o header mede `color: oklch(0.94 0.005 90)`).
  A `base.assinatura` começa com "Usuário 360° · Tudo sobre um usuário num lugar só", então montou
  a tela certa, não a rota default.
- Controle: `--injetar-falha '.os-page.sa-page'` com `--saida` em arquivo temporário → 1 seção
  ausente e JSON diferente do versionado.
- `node scripts/qa/secao-check.mjs --tela superadmin--usuario360--index --url http://127.0.0.1:5591/`:
  **conforme** (3 seções). O modo `--servir-espelho` deu NÃO MEDI só porque a porta 5550 estava em uso.

**Estado medido:** o de entrada, sem busca — EmptyState "Comece uma busca". A lista não aparece
sem busca, por desenho. Tabela, paginação, estado sem-resultado e o drawer 360° exigem digitar no
campo, e o `alvo.mjs` só tem `--clicar`. Ficaram fora do alvo.

## Remedir as 4 medidas — NÃO MEDI

Não regravei `governance/design/targets/medidas/superadmin--{Dashboard,Negocios,Pacotes,Assinaturas}--Index/`.
O que segue é medido:

1. **Por que o `design.json` é o mesmo nas 4.** `design-diff-lote.mjs --dry --tela superadmin/Negocios/Index`
   imprime `shell route=superadmin (window.SuperadminPage)`. As 4 telas são tiradas na rota
   `superadmin`, que no protótipo é `view="visao"` (app.jsx:882). Os 4 `design.json` têm o mesmo
   sha256 (`b4cb290eb306…`). Os `prod.json` diferem entre si.
2. **O conserto do lado design está fora do prefixo.** O lote aceita override de rota em
   `governance/design/targets/roles/<Mod--Tela>.json` (`{"token": "sa-negocios"}` etc.). Não
   existe nenhum para o Superadmin hoje. Seriam 3 arquivos (Negocios → `sa-negocios`, Pacotes →
   `sa-pacotes`, Assinaturas → `sa-assinaturas`; Dashboard fica em `superadmin`), e o prefixo
   desta thread não inclui `targets/roles/`.
3. **O lado prod continua sem login, como na A3 do Officeimpresso.** Conferido em 2026-10-05:
   `VISREG_LOGIN_TOKEN` não aparece no `memory/_INDEX-SECRETS.md` (0 ocorrências de "visreg"), a
   env não está definida nesta sessão, nenhum `DESIGN_DIFF_COOKIE`, e não há app local
   (`curl http://127.0.0.1:8000/` → sem resposta). Não busquei o token fora do índice.
4. **O lote não mede só o lado design.** `design-diff-lote.mjs` grava `design.json`, `prod.json` e
   `resultado.json` juntos; não há flag de lado único. Regravar só o `design.json` deixaria o
   `resultado.json` descrevendo uma comparação contra um arquivo que já não é o dele.

## Pendente / decisões [W]

- Para remedir as 4: (a) o `VISREG_LOGIN_TOKEN` do staging no `_INDEX-SECRETS` (ou app local com
  `/_visreg-login`) e (b) os 3 overrides em `targets/roles/`, numa thread cujo prefixo os inclua.
- O `README.md` de `governance/design/targets/` tem a tabela "Alvos exportados"; a linha deste
  alvo não entrou porque o README está fora do prefixo.
- Aprovação F1.5 da tela e entrada no contrato visual: são do [W]. A tela não tem contrato.
