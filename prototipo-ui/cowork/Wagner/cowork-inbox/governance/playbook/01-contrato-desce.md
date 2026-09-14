---
sessao: "01"
titulo: Descer o contrato de governança — cobre 5 de 9 telas, e isso vai escrito nele
dono: "[CL]"
base: 0d159eb84a10
constituicao: CONSTITUICAO-COWORK.md (C1–C12)
prefixo: prototipo-ui/design-docs/contrato-cowork/governance.contract.json (CRIAR — pasta de ESTÁGIO)
nao_toca: prototipo-ui/contrato/** (pasta VIGENTE — promoção é outro PR) · resources/js/Pages/governance/** · Modules/Governance/**
depende: — (vaga 1)
antes:  prototipo-ui/contrato/ tem 31 arquivos e NENHUM de governança
depois: 1 contrato advisory no CI, declarando cobertura parcial
---
# 01 · Descer o contrato

## A · O estado — e as DUAS pastas de contrato (medido 2026-09-08)
`prototipo-ui/contrato/` tem **31 `.contract.json`** — Fiscal (7), Compras (2), outros. É a pasta **vigente**, a que o `contrato-de-tela.yml` lê, e usa nome de **tela** (`fiscal-cockpit`, `purchase-create`).
`prototipo-ui/design-docs/contrato-cowork/` tem **3** — `patrimonio`, `configuracoes`, `venda-menu`. É a pasta de **estágio**, para contrato nascido no Cowork, com nome **minúsculo do módulo**.

**Zero de governança nas duas.** O contrato existe do lado Cowork (`cowork-inbox/governance/governance.contract.json`, 10.174 B) e **nunca desceu**.

Ele é bom: trava seções, copy literal e estados, e nomeia **6 proibições** e **6 divergências** com o arquivo de backend de cada uma. É material que só quem leu o código produz.

**Mas cobre 5 telas de 9.** A produção ganhou `Custos`, `DsRollout`, `QualidadeIa` e `ModuleGrades/Show` depois que ele foi escrito. Descer sem dizer isso faria o CI afirmar cobertura que não existe.

## B · O que fazer
1. Copiar o contrato do Cowork para **`prototipo-ui/design-docs/contrato-cowork/governance.contract.json`** — pasta de **estágio**, nome minúsculo do módulo, como os 3 que já estão lá. **Não** ir direto pra `prototipo-ui/contrato/`.
2. **Acrescentar um campo `cobertura_parcial`** no topo, literal:
   ```
   "cobertura_parcial": {
     "cobertas": ["Dashboard","Policies","Audit","DriftAlerts","ModuleGrades/Index"],
     "nao_cobertas": ["Custos","DsRollout","QualidadeIa","ModuleGrades/Show"],
     "motivo": "telas nascidas em producao depois do contrato; estender custa 96 KB de leitura (D-CONTRATO-9)"
   }
   ```
3. **Não** registrar no `contrato-de-tela.yml` neste PR. Promoção estágio → vigente é PR próprio, e só depois de [W] decidir se um contrato de cobertura parcial pode virar gate.
4. Conferir que o `alvo` do JSON usa **`governance/` minúsculo** — é o caminho real (9 de 9 `Inertia::render` confirmam). **Não normalizar para maiúsculo.**

## C · Não inventar
- **Não** reescrever as seções/copy do contrato para "melhorar": a copy literal dele foi medida contra a tela. Se divergir da produção hoje, isso é **achado** para o `_saida`, não conserto silencioso.
- **Não** promover à pasta vigente (`prototipo-ui/contrato/`) nem tocar no `contrato-de-tela.yml` neste PR.
- **Não** criar contrato para as 4 telas não cobertas aqui — é a D-CONTRATO-9, e o custo já foi medido como RECUSA por teto.

## Execução
```
PASSO   1) gh pr list --state open × prototipo-ui/design-docs/contrato-cowork/
        2) copiar o JSON pro ESTÁGIO, acrescentar cobertura_parcial
        3) validar o JSON (parse) e conferir o caminho minúsculo do alvo
        4) _saida-01.md com QUALQUER divergência copy×tela que notar de passagem
PARAR SE (a) alguém já tiver posto governança na pasta vigente → então o dono mudou;
             reporte e pare, não duplique
         (b) a copy do contrato divergir da tela em ponto que você veja sem
             abrir o .tsx inteiro → registre no _saida; NÃO edite os dois lados
```

## Checklist de saída
1. arquivo em `design-docs/contrato-cowork/` (estágio) · 2. `cobertura_parcial` presente com as 4 não cobertas · 3. JSON parseia · 4. `prototipo-ui/contrato/` e o workflow **intocados** · 5. caminho minúsculo preservado · 6. divergências notadas registradas · 7. placar no PR
