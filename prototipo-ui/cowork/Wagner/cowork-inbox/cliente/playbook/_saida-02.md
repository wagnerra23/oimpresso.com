---
sessao: "02"
titulo: "DIVERGE do Cliente/Index — saída da thread"
autor: "[CL]"
data: 2026-10-13
base: origin/main f7451057f4
thread: 02-divergencia-index.md
veredito: "premissa refutada — a medida de 2026-09-18 mediu outra página (URL feita do texto do charter). Causa consertada na máquina; a tela não foi tocada; a comparação nova segue pendente de sessão no staging."
---
# _saida-02 · O "título 18px" do Cliente/Index

## O que a ficha pedia
`governance/design/targets/medidas/Cliente--Index/resultado.json` (2026-09-18): D4 título `18px` na prod × `22px` no
design, DIVERGE (bug). Corrigir para 22px e medir a linha da tabela.

## O que a medição de fato mediu
- O código do título já pede **22px** desde 2026-05-25 (#1477): `resources/js/Pages/Cliente/Index.tsx:865`,
  `text-[22px] font-bold`. Nenhuma regra de CSS do repo sobrescreve esse `h1`.
- A URL que o lote abriu no staging, gravada no próprio `resultado.json` (`meta.urlFinal`), foi o **texto** do
  `page:` do charter: `/cliente (canon) · /contacts (legacy dual-render via config('mwart.cliente_index.enabled'))`.
  O `componenteInertia` saiu `null`: a sonda mediu uma página que não era a lista de Clientes. O "18px" é o `h1`
  dessa outra página, e a "linha da tabela não medida" vem do mesmo motivo.
- O mesmo resultado registra `sameTheme: false`, que por si só invalida a comparação (PROTOCOLO-COMPARACAO-RUNTIME).
- **Conclusão:** o DIVERGE não é da tela. Mudar o título com base nele seria consertar a página errada. A tela não foi
  tocada.

## Causa consertada (na máquina, não na tela)
`scripts/design/design-diff-lote.mjs::rotaViva` usava o `page:` inteiro como rota. Agora a rota é o 1º caminho no
início do campo (até espaço, `·`, `(` ou `#`), e campo que não começa com `/` não tem rota.
- **Antes→depois nos 344 charters com `page:`:** mudam exatamente os 13 que têm prosa (todos para a rota certa;
  os dois `n/a — …` deixam de virar URL). Os outros 331 saem idênticos.
- **Selftest:** `design-diff-lote.mjs --selftest` 81/81, com 6 casos novos. Mordida medida aplicando os mesmos
  predicados à função antiga: 5 dos 6 reprovam a antiga e passam na nova. O caso "parametrizada com prosa" passa
  nas duas (o texto antigo também tem `{`); fica como guarda da nova, não como prova da troca.

## Achado para o índice (não editei o índice nem as medidas)
Dos 79 `resultado.json` em `governance/design/targets/medidas/`, **2** abriram URL de texto:
1. `Cliente--Index` → DIVERGE (bug), falso.
2. `User--Perfil` → **IGUAL**, também falso: o veredito "igual" foi dado sobre a página errada. É o mais perigoso
   dos dois, porque ninguém desconfia de um verde.

Os dois precisam ser re-medidos com o lote consertado (`--incluir-comparadas`, que existe para medição anterior
inválida). Não re-medi: o staging exige sessão, e eu não entro com credencial em host remoto.

## Pendente
- **Comparação da lista real** (a prova desta thread): `node scripts/design/design-diff-lote.mjs --tela Cliente/Index
  --incluir-comparadas --base-url https://staging.oimpresso.com` (com `VISREG_LOGIN_TOKEN` exportado), por quem tem a sessão ou o `VISREG_LOGIN_TOKEN`. Se der 22px nos dois
  lados, a thread fecha sem mudar a tela; se der outro valor, o conserto parte dessa medida.
- **Linha da tabela:** a mesma rodada mede, com `tableRow` nos dois lados.
- Mesma re-medição para `User/Perfil`.

## Placar
premissa da thread refutada por medida · 0 telas tocadas · causa consertada no lote · comparação pendente de sessão.
