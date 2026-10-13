---
sessao: "07b"
titulo: Saída — G-NOTAS no contrato vigente, com as âncoras nas 4 telas
dono: "[CL]"
base: origin/main d5f65d3776 (rebaseado antes do PR)
constituicao: CONSTITUICAO-COWORK.md (C1–C12) + memory/proibicoes.md
prefixo_escrito: governance/design/contracts/governance.contract.json · 10 atributos data-contract em resources/js/Pages/governance/{Dashboard,Policies,Audit,DriftAlerts}.tsx · este _saida
veredito: ENTREGUE — escopo ampliado pelo gerente da fila em 2026-10-13 (âncoras + promoção)
---

# 07b · Saída

## A · Por que existe uma 07b

A `_saida-07` (30/09) parou porque promover o contrato sem as âncoras deixaria o job "Contratos de tela"
vermelho. Medido de novo em 13/10 no `main`: a versão sem `notas-*` e sem ModuleGrades dava **82 falhas**,
23 de 23 seções sem âncora (`grep -c data-contract` = 0 nas 7 telas). O gerente da fila ampliou o escopo
desta thread para incluir as âncoras, só o atributo, como o #9252 fez na Recorrente.

## B · O que entrou

- **10 âncoras `data-contract`**, só o atributo: Dashboard 4 (`mcp-secao`, `mcp-periodo`, `kpis-constituicao`,
  `saude-ecossistema`), Policies 2, Audit 2, DriftAlerts 2.
- **Contrato vigente** `governance/design/contracts/governance.contract.json`: 10 seções, 35 strings. Gerado por
  script a partir do contrato do Cowork. Uma string entra só se aparece, com a fronteira de identificador do
  verificador, no protótipo (fora de comentário) **e** na tela da seção. Sem `notas-*` (G-NOTAS) e sem
  `ModuleGrades/Index` como alvo (ADR 0399). `nao_contem notas-kpis` = 0 ocorrências.

## C · O que ficou de fora, e por quê (divergência registrada, não corrigida)

| seção do Cowork | motivo |
|---|---|
| `page-header`, `aviso-rota`, `conformidade-regua`, `card-sdd`, `aguardando-decisao`, `ocorrencias-24h` | a copy do protótipo não existe no Dashboard (ex.: protótipo "ADRs aguardando decisão", tela "ADRs pendentes") |
| `tabs` | os rótulos vêm do `DataController` (PHP); o verificador só lê `.tsx/.ts` |
| `politicas-lista` | "Ativa"/"Desligada" não existem como palavra na tela |
| `auditoria-kpis`, `auditoria-selo`, `auditoria-teto` | a tela usa "Entries no período", "Errors", "Users distintos" e outra frase de teto |
| `drift-lista`, `drift-historico` | a copy não existe na tela |
| strings cortadas dentro das seções que entraram | "Regras no total", "Disparos", "Ator", "Resultado", "1 h", "Ferramenta / recurso", "Módulos no total": só no protótipo |

**Acordos de estado fora.** Os 5 aprovados por [W] em 30/09 (`_saida-07` §E) só entram quando os valores
existirem como literal no backend e no frontend (catraca ADR 0286 §5). Hoje não existem. O 6º saiu com a vista.

**`ordem` cobre só o corpo do Dashboard.** `mcp-secao` mora no componente `McpSection`, declarado antes do
corpo da página no arquivo, e o verificador lê a ordem pela posição no arquivo.

**Aviso esperado do anti-tautologia:** `fonte` aceita um arquivo e a copy vem dos três do protótipo, então
21 de 35 strings aparecem como "no alvo e não na fonte". Estão em `governance-telas.jsx`/`governance-data.jsx`.

## D · Modos do job "Contratos de tela", rodados localmente

| modo | resultado |
|---|---|
| `--contract` nos 114 contratos (sem EXEMPLO) | rc=0, nenhuma falha; o novo ✅ limpo, 10 de 10 seções |
| `--map --check` | rc=0 · governança `🟢 portado` 10/10 |
| `--anti-tautologia` | rc=0 · 0 reprovados · aviso do §C acima |
| `--preflight origin/main` | rc=0 (depois do rebase) |
| `--omission origin/main --alvo resources/js/Pages,Modules` | rc=0 |
| `contrato-de-tela.test.mjs` / `-relevante.test.mjs` | rc=0 / rc=0 |

**Mordida por mutação** (cópia isolada via `--root`, árvore real intacta): intacta rc=0 · sem âncora
`politicas-kpis` rc=1 · sem âncora `drift-sem-scope` rc=1 · "Latência p95" renomeada rc=1 · `kpis-constituicao`
e `saude-ecossistema` trocadas de lugar rc=1 (ordem divergente).

Typecheck não rodou localmente (worktree sem `node_modules`); fica com o CI.
