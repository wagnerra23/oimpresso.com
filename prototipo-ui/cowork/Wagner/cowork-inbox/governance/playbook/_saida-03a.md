---
sessao: "_saida-03a"
thread: "03a · casos.md de Policies (a menor tela) — abre a frente do trio"
dono: "[CL]"
data: 2026-09-13
prefixo_tocado: resources/js/Pages/governance/Policies.casos.md
base_lida: wagnerra23/oimpresso.com@main (PR #7255)
natureza: RECIBO DE RECONCILIAÇÃO — ver §0
---
# _saida-03a

## 0 · Por que este recibo não foi escrito pela sessão executora

A thread rodou em 2026-09-13, quando o `cowork-inbox/` **não existia no repo** (apagado pela
#7224; a `R3` recusava `.md` aninhado). O executor registrou em
[`memory/sessions/2026-09-13-governanca-03a-casos-policies.md`](../../../../../../memory/sessions/2026-09-13-governanca-03a-casos-policies.md)
e no [PR #7255](https://github.com/wagnerra23/oimpresso.com/pull/7255). A ADR 0398 devolveu a
pasta; este arquivo transporta aquele recibo. **Nenhuma afirmação aqui é nova.**

## 1 · Feito (palavras do executor)

> Thread 03a do playbook Governança — **Policies ganha `casos.md`**, e **o contrato que a âncora
> mandava usar não existe**.

## 2 · O achado, e por que ele não vira "pendência" automática

A âncora do playbook apontava um contrato ausente. O executor **não criou o contrato para fazer
a prova passar** — reportou. Isso é o comportamento certo: inventar o artefato que um gate procura
é a classe LC-11 (presence-gate), e um contrato de tela inventado vira anti-padrão que parece canon.

Resolver o contrato é decisão de quem emite a âncora, não conserto de passagem.

## 3 · Provas

As **2 provas de arquivo** do §7 passam (medidas contra `origin/main` em 2026-09-14).
