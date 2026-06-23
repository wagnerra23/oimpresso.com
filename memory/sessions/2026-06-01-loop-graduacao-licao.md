# Sessão 2026-06-01 — Loop de graduação de lição (camada 5 ativa) · [CC]

**Pedido [W] (chat):** "isso vai fazer ele aprender com os erros? zero-toque não é transferência de conhecimento. prefiro saber como resolver para não errar mais. — sim eu quero."

## Racional dado a [W] (a parte que importa: entendimento, não transporte)
- Somos **stateless**: "aprender" ≠ lembrar; é **mudar o ambiente**. Só persiste (a) check que sempre roda, (b) arquivo sempre-lido. Não há 3º lugar.
- **Zero-toque é transporte, não transferência.** O prompt entrega o *código do check*; a transferência é o check **existir e rodar** (ou a regra estar no arquivo que o [CL] lê ao abrir o repo).
- Forma forte de "não errar mais" = tornar o erro **impossível** (check), não **lembrado** (prosa). Prova viva: `#2078` é a lição "doc não pode envelhecer" virada check.

## O que foi feito (F1, Cowork)
- **`memory/APRENDER-COM-ERRO.md`** — loop de 3 estágios (Registrar → **Triar/Graduar** → **Colher**) + classificador MEC/JULG + matriz "onde a graduação aterrissa" (git CI vs regra-carregada vs higiene Cowork) + Gate (`L-NN` sem `Graduação:` = incompleta).
- **Colheita retroativa das 22 lições** (rodapé `LICOES_CC.md`, append-only — respeita L-22): 15 carregadas/fato · 5 já em check · **4 pendentes mecanizáveis (L-07/11/21/22)** = higiene filesystem Cowork.
- **`COWORK_NOTES.md`** — entrada propondo guard único Cowork-side (cobre as 4) + link no PROTOCOL §6/§10. Como **peer-review (L-17)**, [CL] decide o melhor lugar.

## Decisões
- Loop generaliza **L-16** (cobrança = máquina) + **L-17** (proposta + soberania) — já eram a semente; agora a graduação é **obrigatória** (Gate) e **periódica** (colheita no sync).
- **Anti-reflexo (L-20):** nem toda lição vira check no git — higiene Cowork fica no pre-flight (sem CI aqui); só repo-bound transporta.
- Toda lição NOVA nasce com campo `Graduação:`.

## Erros + correção
- Nenhum erro novo. A regra ataca a causa-raiz "log que só cresce não ensina" — fecha o gap que [W] apontou (transporte ≠ aprendizado).

## Residual
- 4 lições pendentes (L-07/11/21/22) aguardam o guard de higiene (ou viram só Regra de Ouro carregada — já estão lá). L-14 gradua na ponte FRESCOR.
- Trio de regras desta tarde (`CONTEXTO-DE-TELA` + `FRESCOR-DE-TELA` + `APRENDER-COM-ERRO`) aguarda [W] disparar pro [CL].

## Refs
- `memory/APRENDER-COM-ERRO.md` · `memory/LICOES_CC.md` §Graduação 2026-06-01 · `memory/{CONTEXTO,FRESCOR}-DE-TELA.md` (camadas 1–2) · L-16/L-17/L-20 · #2078 · PROTOCOL §6/§10.4

## Próximo passo
Camadas 1–2–5 fechadas (3–4 já existiam: crítica #2078/F1.5 + humano F2/canary). Sistema de garantia completo. Voltar ao trabalho de tela: `Vendas.charter.md` (1º uso do trio) → Create venda≠POS + endereço destinatário↔local de entrega.
