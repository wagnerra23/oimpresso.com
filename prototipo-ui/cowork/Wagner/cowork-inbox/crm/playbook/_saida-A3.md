---
sessao: "A3"
titulo: "ALVO lote 3 do Crm (portal do contato) — 8 de 8 telas medidas, em tela cheia e em 390 — saída da thread"
autor: "[CL]"
data: 2026-10-21
base: origin/main ff2dd7fffc
thread: A3-alvos-portal.md
veredito: "entregue — 16 alvos (8 telas × tela cheia + Celular 390), 84 seções, 0 ausentes; cada alvo 2× byte-idêntico; secao-check conforme nos dezesseis."
---

# _saida-A3 · Alvos do portal do contato (lote 3)

## O que saiu

| tela | slug | rota | seções | sha256 (16) tela cheia | sha256 (16) 390 |
|---|---|---|---|---|---|
| Início (painel) | `crm--portal-painel--index` | `crm-portal-painel` | 6 · header · nav · nav_celular · titulo · saldo · atalhos | `e4b705203d72d967` | `3842d9ed29407800` |
| Meu perfil | `crm--portal-perfil--index` | `crm-portal-perfil` | 6 · header · nav · nav_celular · titulo · dados · senha | `d05d8aae6b9055a8` | `aa92e223d6c4932d` |
| Minhas compras | `crm--portal-compras--index` | `crm-portal-compras` | 5 · header · nav · nav_celular · titulo · lista | `8abe871b32276805` | `a48b9397ef05070e` |
| Minhas vendas | `crm--portal-vendas--index` | `crm-portal-vendas` | 5 · … · lista | `13e4fa36b27ad1ee` | `0b1c261af3c142dc` |
| Extrato | `crm--portal-extrato--index` | `crm-portal-extrato` | 5 · … · lista | `0e8150f27a486d51` | `532fdac1fbfd526d` |
| Agendamentos | `crm--portal-agendamentos--index` | `crm-portal-agendamentos` | 5 · … · lista | `47e9dc2a3032f032` | `8ef0f1c094c5d8ff` |
| Pedidos | `crm--portal-pedidos--index` | `crm-portal-pedidos` | 5 · … · lista | `467114bb44ff41c5` | `09a9f083591bddde` |
| Assistência | `crm--portal-assistencia--index` | `crm-portal-assistencia` | 5 · … · corpo | `8c7c2309984c7d48` | `023799323b4ba786` |

Arquivos: `governance/design/targets/<slug>.{secoes,alvo}.json` e `<slug>-390.{secoes,alvo}.json`. Os `.alvo.json` saíram do `alvo:medir` sem edição à mão. Os 16 hashes são diferentes entre si.

## Como foi medido

- O espelho `prototipo-ui/cowork/Wagner/` foi servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`, raiz = `MIRROR_DIR`) na porta 5591.
- Antes de medir, conferi a fonte: `crm-portal.css` do espelho bate com o Cowork vivo (projeto `019dcfd3…`, `get_file`, `truncated:false`) em seis regras distintivas, entre elas `.cp-qtd-b button:disabled`, `.cp-os-and li::before` e o `@container (max-width:720px)` final. A conferência foi por marcador, não byte a byte. `crm-portal.jsx` é o do #9466 (2026-10-15), já na casca própria (D6). Não conferi o jsx contra o vivo.
- Os seletores vêm da estrutura de `crm-portal.jsx:322-360` (`.cp-frame > .cp-root > .cp-top · .cp-nav · .cp-main · .cp-bottom`) e dos `data-contract="portal-*"` das views (`crm-portal.jsx:149-292`). A Assistência não tem contrato, então o corpo é `.cp-main > :nth-child(2)` (o card `OsAcompanhamento`). A tira `.cp-preview` fica fora de todos os alvos.
- Tela cheia: `alvo.mjs --alvo http://127.0.0.1:5591/ --tela <slug> --rota crm-portal-<x> --secoes … --quieto-ms 2000`, 1280×900, tema dark (default).
- 390: o mesmo comando com `--tela <slug>-390` e `--clicar '.cp-preview > div:nth-child(3) > :first-child'` (o botão "Celular 390" da tira, como pede a A3). Dá frame de 388 px úteis (390 menos a borda): `header` mede 388, `nav` mede 0 (o container query esconde) e `nav_celular` mede 388×57. O sufixo `-390` segue o padrão dos alvos da Produção (`producao--apontamento--index-390`).
- Cada alvo foi medido duas vezes (a 2ª fora do repo) e o `cmp` deu idêntico nos 16. `nos_totais`: 603 · 597 · 592 · 622 · 608 · 577 · 580 · 698. `ausentes: []` nos 16.
- `node scripts/qa/secao-check.mjs --tela <slug> --servir-espelho --porta 5593|5595` deu **conforme** nos 16.

## Ressalvas medidas

1. **Prefixo.** O prefixo da A3 é `${ALVOS}/crm--portal-<tela>--index.*`. Os alvos de 390 (`…--index-390.*`) não casam com esse glob. Uma primeira versão gravada como `…--index.celular.alvo.json` casava com o glob, mas o `placar.mjs` (`agregar`, :188-200) procura `<slug>.secoes.json` ao lado de cada `.alvo.json`, e com esse nome não acharia. Ficou o padrão da Produção. Para o índice: incluir `${ALVOS}/crm--portal-<tela>--index-390.*` no prefixo da A3.
2. **Claro não foi medido.** As threads 18–25 pedem design-diff "escuro+claro, 1280+390" no recibo, mas a A3 pede só tela cheia e 390. O tema claro fica para a própria thread de tela ou para um alvo `-claro`, como na Produção.
3. **Contato padrão.** O protótipo abre no `CONTATOS[0]`, e todos os alvos são desse contato. Compras e Vendas aparecem porque a rota força a view, mesmo que a aba não apareça na nav para aquele tipo de contato. Estados (carregando/erro/vazio) e a view `novo` (dentro de Pedidos) não foram medidos: precisam de `--clicar` e são outro alvo.
4. A fidelidade do protótipo às views Blade de `/contact/*` não está provada: o alvo mede só o protótipo.

## Fora do prefixo, para o Cowork

- **Thread 05.** O placar mostra `[bloqueada]`, mas ela foi fatiada: lote 2 = A2 + 11..17 (todas feitas) e lote 3 = A3 + 18..25. D3 tem resposta (`_DECISOES-W-2026-10-01.md`, ampliada em `_DECISOES-W-2026-10-14.md`) e D6 também (`_DECISOES-W-2026-10-15.md`). O que segura a 05 é só o campo `bloqueio` no JSON, sem `_saida-05`. Proposta: fechar a 05 com um `_saida-05.md` do Cowork, ou trocar o `bloqueio` por um marcador de absorção, para o placar parar de contá-la como bloqueada.
- **"18..24" × 25.** O `bloqueio` da 05 e a `nota_provas` dizem "18..24". A A3 e a thread 25 (Assistência) entraram depois.

## Placar

entregue 16 de 16 alvos · 84 de 84 seções medidas · ausentes 0.
