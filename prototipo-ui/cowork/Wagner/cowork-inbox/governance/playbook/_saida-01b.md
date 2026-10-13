---
sessao: "01b"
titulo: Saída — a thread 01 não cabe ao Code; o contrato de estágio nasce no Cowork
dono: "[CL]"
base: origin/main 94c005aec5 (medição) · d5f65d3776 (este recibo)
constituicao: CONSTITUICAO-COWORK.md (C1–C12) + memory/proibicoes.md
prefixo_escrito: só este _saida
veredito: NÃO ENTREGUE PELO CODE — decisão do gerente da fila em 2026-10-13: a 01 fica com o Cowork
---

# 01b · Saída

## A · Por que o Code não criou o arquivo

A prova da 01 pede `prototipo-ui/cowork/Wagner/contrato/governance.contract.json`. Medido em 13/10:

1. **A pasta é do espelho.** Os 3 contratos que estão lá (`configuracoes`, `patrimonio`, `venda-menu`) desceram
   pelo bundle no #7445. No projeto Cowork, `contrato/` tem esses mesmos 3 e **não** tem `governance.contract.json`
   (`list_files`; o `get_file` do caminho deu 404, e os outros 3 aparecem na mesma listagem, então o 404 vale).
   Um arquivo criado à mão no espelho não tem par no Cowork, e o `receber-handoff` importa com `/PURGE`.
2. **O verificador pula a pasta.** `scripts/contrato-de-tela.mjs` trata tudo em `prototipo-ui/cowork/Wagner/`
   como espelho (`ehEspelhoCowork`) e devolve "pulado". O arquivo só satisfaria a prova do placar.
3. **O conteúdo literal do pedido envelheceu.** O `cobertura_parcial` do §B.2 lista `ModuleGrades/Index` como coberta
   e `ModuleGrades/Show` como não coberta. As duas telas foram apagadas (ADR 0399): `resources/js/Pages/governance/`
   tem 7 `.tsx` e nenhuma `ModuleGrades`. E o contrato-fonte do Cowork ainda carrega as 4 seções `notas-*`, que a
   decisão G-NOTAS tirou.

## B · Pedido ao Cowork

Criar no projeto Cowork `contrato/governance.contract.json`, para descer no próximo handoff:

- a partir de `cowork-inbox/governance/governance.contract.json`;
- **sem** as seções `notas-kpis`, `notas-faixas`, `notas-tabela`, `notas-gate-ci` e sem o rótulo "Notas dos módulos"
  em `page-header`/`tabs` (G-NOTAS);
- `alvo` sem `ModuleGrades/Index.tsx`;
- `cobertura_parcial` com as telas que existem: cobertas `Dashboard`, `Policies`, `Audit`, `DriftAlerts`;
  não cobertas `Custos`, `DsRollout`, `QualidadeIa` (D-CONTRATO-9);
- o acordo `dimensao-nao-avaliada` fora, e os 5 aprovados por [W] em 30/09 com `verdict: "aprovado"` (`_saida-07` §E).

O mesmo ajuste vale para `cowork-inbox/governance/governance.contract.json`, de onde a cópia sai.

## C · Relação com a 07

O contrato **vigente** saiu pela thread 07 (`_saida-07b`, #9273), em `governance/design/contracts/governance.contract.json`,
derivado do mesmo contrato do Cowork e já sem a vista Notas. O de estágio pedido aqui é registro do lado Cowork;
não muda nenhum gate.
