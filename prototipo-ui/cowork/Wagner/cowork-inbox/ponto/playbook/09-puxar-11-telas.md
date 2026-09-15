---
sessao: "09"
titulo: PUXAR as 11 telas restantes → protótipo
dono: "[CC] read-only → build"
base: e86130722de1
prefixo: prototipo-ui/cowork/ponto-telas.jsx · ponto-data.jsx · ponto-ui.jsx · oimpresso.com.html (bump)
nao_toca: resources/js/Pages/** · ponto-page.jsx (08) · ponto-mobile.jsx · ponto-fechamento.jsx (alvos das 04–06, não se puxa o que não existe)
depende: thread 10 feita (Lei 1: ambas tocam ponto-data.jsx) — vaga 2
---
# 09 · PUXAR as 11 telas

## Telas (produção → protótipo), 1 linha cada no `_saida`
| Page viva | protótipo | teste que a defende |
|---|---|---|
| `Aprovacoes/Index.tsx` (24 KB) | `PontoTelas.Aprovacoes` | `AprovacaoTest` |
| `Intercorrencias/{Index,Create,Show,Edit}.tsx` | `PontoTelas.Intercorrencias` | `Intercorrencia{,Edit}ContratoTest` · `AIClassifierTest` (classificador IA `throttle:10,1` — o protótipo não sabe que existe) |
| `BancoHoras/{Index,Show}.tsx` | `PontoTelas.BancoHoras` | `BancoHorasIndexContratoTest` |
| `Escalas/{Index,Form}.tsx` | `PontoTelas.Escalas` | `EscalaIndex/FormContratoTest` · `Wave27CrossTenantEscalaTest` |
| `Colaboradores/{Index,Edit}.tsx` | `PontoTelas.Colaboradores` | `ColaboradorContratoTest` |
| `Importacoes/{Index,Create,Show}.tsx` | `PontoTelas.Importacoes` | `Importacao*ContratoTest` |
| `Relatorios/Index.tsx` | `PontoTelas.Relatorios` | `RelatorioCatalogoContratoTest` — **7 chaves 501**: o protótipo tem de mostrar `NAO_IMPLEMENTADO`, nunca sucesso |
| `Configuracoes/{Index,Reps}.tsx` | `PontoTelas.Configuracoes` | `ConfiguracaoContratoTest` |

## O que entra no build
Átomos, `aria-*`, `data-testid`, estados reais, copy literal, **campos reais** (o `ponto-data.jsx` tem mock de jun/2026 — alinhar nomes de coluna às Pages, sem inventar número). O que **não** entra: layout da produção por inércia (o alvo de layout continua o protótipo), número de apuração (VALOR).

## PARAR SE
- Uma Page viva tiver comportamento que o protótipo contradiz (ex.: Relatórios prometendo AFD) → corrigir o protótipo, nunca pedir mudança na Page.
- Puxar exigir mexer em `ponto-mobile.jsx` → é da 10; parar.

## Prova
- `_saida-09.md` com a tabela de 11 linhas (tela × átomos puxados × divergência declarada × sha) · `ponto-telas.jsx`/`ponto-data.jsx` no espelho `prototipo-ui/cowork/`
