---
sessao: "19c"
titulo: Saída da thread 19 (adendo 2) — errata do _saida-19b e conserto do foco em Manutenções
dono: "[CL]"
medido_em: 2026-09-30
complementa: "_saida-19b.md (2026-09-30), achado 1"
---

# 19c · Errata: a lista de Manutenções TEM botão de cadastrar

Adendo datado. O `_saida-19b.md` fica intacto; esta é a correção dele.

## O que o 19b afirmou e estava errado
O achado 1 do `_saida-19b` dizia que *"a lista de Manutenções não tem botão de cadastrar, então
não existe trigger na tela para onde devolver o foco"*. **É falso.** A tela tem
**"+ Enviar bem pra manutenção"** no rodapé (`data-contract="rodape"`), conferido em produção no
mesmo dia: o botão está no DOM. O vitest `patrimonio-manutencoes-drawer.test.tsx` já o afirmava.

**Por que errei:** a sonda do smoke procurou botões só dentro de `[data-contract="cabecalho"]` e
concluiu ausência na tela inteira. Afirmação de ausência a partir de varredura parcial — a classe
LC-08. O estado vazio da lista ("O envio começa na tela de Bens") reforçou a leitura errada.

## O que muda
O "agravante" não existe: há trigger estável. O conserto do foco (PR deste adendo) devolve o foco a:
- o lápis **da própria linha**, quando o drawer foi aberto para editar e a linha está na tela;
- **"+ Enviar bem pra manutenção"**, no cadastro e quando a linha ainda não carregou (a lista vem
  em prop deferida).

O achado 2 do 19b (X de fechar com 16×16px no `Sheet` compartilhado) segue válido.
