---
sessao: "06"
titulo: Saída — PLANO-MESTRE, Trilha D com o ciclo completo (ratificado [W] 2026-08-06)
dono: "[CL]"
base: origin/main 1061dbf2e (branch fresco, 0 ahead / 0 behind no início)
prefixo_escrito: memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md
veredito: ENTREGUE — patch aplicado como DELTA, não como substituição (a substituição regrediria o main)
---

# 06 · Saída

## A · O que foi feito

1. **`gh pr list --state open` × prefixo.** Dos 12 PRs abertos, nenhum toca o `PLANO-MESTRE.md`. O
   último commit no arquivo foi o #6979 (Onda 7).
2. **Medição antes de aplicar.** O patch (`../PLANO-MESTRE-trilha-d-ciclo-completo.md`, base
   `68a938c5ec5f`) mandava substituir a seção `## Trilha D` inteira. No `main` de hoje **o conteúdo
   já estava lá**: o #5353 aplicou o ciclo de 11 estações, as ondas D0–D10, o caminho por tipo e o DoD
   com "um incidente já girou o ciclo", e o #5833 reconciliou a célula de status com a D0 medida
   (2/5 AC). O que de fato faltava:
   - o marcador de ratificação **"ciclo completo 2026-08-06"**, que é também a prova do índice;
   - a frase de programa (*"mede, traduz, publica, opera, detecta drift e aprende"*);
   - o **diagrama Mermaid** do ciclo.
3. **Delta aplicado, 23+ / 5−, um arquivo:**
   - célula da Trilha D no `## Status vivo`: `([W] 2026-08-05)` → `([W] 2026-08-05 · ciclo completo 2026-08-06)`.
     **O resto da célula ficou intacto** (`🟡 D0 em execução — 2/5 AC…`);
   - intro da § Trilha D: + a ratificação de 2026-08-06 + a frase de programa;
   - D.4: + o diagrama Mermaid, **antes** da lista numerada.

## B · O que NÃO apliquei, e por quê

| Parte do patch | Por que ficou fora |
|---|---|
| Célula de status `🟡 D0 em execução; merge ratifica` | O `main` já traz o estado **medido** (#5833: 2/5 AC, gate travado pela credencial MCP). Trocar seria regressão. E a célula é **entrada de máquina**: o `DocumentacaoController::execucaoDaTrilha` lê dela `D<n> em execução` e a US, e o `DocumentacaoRouteTest` (caso da linha 564) trava isso |
| Renumeração D.2 (ciclo) / D.3 (estado) / D.5 (ondas)… | A rota `/documentacao/programa` recorta por **código** (`D.3` = ondas, `D.4` = estações, `D.5` = caminhos, `D.6` = batimento, `D.7` = DoD). Com a numeração do patch, a página leria o ciclo como tabela de ondas e cairia em 503 |
| D.4 do patch em subseções (`#### Máquinas…`) | O `main` tem a mesma informação na tabela D.5, que é o formato que a rota renderiza como "caminhos". Converter apagaria os cartões |
| Nomes "Camada", redação de Operação/Visão humana | São diferenças de redação, não de conteúdo. O #5353 é posterior e a redação dele é a vigente |

## C · Recibos

- **Sonda da máquina consumidora** (a mesma lógica de `DocumentacaoController`, em Node, sobre `HEAD`
  e sobre o arquivo editado):
  `ANTES  estacoes 11 | D.3 11 D.5 5 D.6 6 DoD 10 | onda D0 US-INFRA-048`
  `DEPOIS estacoes 11 | D.3 11 D.5 5 D.6 6 DoD 10 | onda D0 US-INFRA-048`
  A primeira versão da sonda devolveu **zero nos dois lados**. Como o `HEAD` sabidamente renderiza,
  isso provou que a sonda estava cega (causa não isolada; o suspeito é a regex de recorte portada do PHP), e ela foi reescrita com recorte por linhas.
  O zero não foi lido como resultado.
- **Mermaid não vaza para as estações:** as linhas do diagrama começam com espaço, e o parser de
  estações exige `^\d+\.` no início da linha. A contagem continua 11.
- **`plan-health`:** rc=0; o PLANO-MESTRE não aparece entre os flagados.
- **Prova do índice** (`contem "ciclo completo 2026-08-06"`): casa na linha 63.
- **Pest `DocumentacaoRouteTest`:** **não rodado local** (regra CT 100). O CI do PR é o oráculo.

## D · Placar

entregue 3 de 3 partes faltantes (marcador · frase de programa · diagrama) · ausentes: substituição
integral e renumeração, **recusadas** por regressão medida (§B), não esquecidas.
