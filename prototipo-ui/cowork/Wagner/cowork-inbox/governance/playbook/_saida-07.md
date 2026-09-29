---
sessao: "07"
titulo: Saída — G-NOTAS no main + refazer o #7138 (rótulos PT-BR das 4 abas)
dono: "[CL]"
base: origin/main 5606344ca (0 atrás no início)
constituicao: CONSTITUICAO-COWORK.md (C1–C12) + memory/proibicoes.md
prefixo_escrito: Modules/Governance/Http/Controllers/DataController.php (3 rótulos + 2 comentários) · este _saida
veredito: PARCIAL — itens 2–4 NÃO executáveis como o índice os escreve (medido); o #7138 foi refeito
---

# 07 · Saída

## A · O que foi entregue

**O #7138 refeito**, só os rótulos. As 4 vistas que o protótipo desenha passam a ter o rótulo do protótipo
(`governance-page.jsx` → `VIEWS`, handoff 43) e saem do inglês:

| key | antes | depois | protótipo |
|---|---|---|---|
| `dashboard` | Painel | Painel | Painel |
| `policies` | Policies | **Políticas** | Políticas |
| `audit` | Audit log | **Auditoria** | Auditoria |
| `drift` | Drift alerts | **Drift** | Drift |

`DS Rollout`, `Custos de IA` e `Qualidade IA` ficam como estão: o protótipo não as desenha (vivo à frente, ADR 0366 §D-B).
O comentário vizinho dizia que a lista tinha 8 itens e que 3 caíam no overflow. Hoje são **7** (`grep -c "'key' =>"`) e
`maxVisible={5}` (`GovernancaSubNav.tsx:74`), então caem **2**. Corrigido com a data do motivo (#7283 / ADR 0399).

Varredura de quem asserta os rótulos antigos (`tests/`, `Modules/Governance/Tests`, `e2e/`, `resources/js`, `scripts/`,
`governance/design/`): **0 ocorrências**.

## B · Por que os itens 2–4 não entraram (medido, não opinado)

### B1 · O contrato não pode ir para a pasta vigente sem mexer nas 4 telas

Montei por script, a partir de `cowork-inbox/governance/governance.contract.json`, a versão sem a vista: tirei as 4
seções `notas-*`, o 5º rótulo de `tabs` e o alvo `ModuleGrades/Index.tsx` (a tela foi apagada na Onda 2 da ADR 0399).
Rodei o verificador vigente:

```
node scripts/contrato-de-tela.mjs --contract <versão sem notas>
❌ 92 falha(s).
```

As causas são estruturais, não de copy:
- `grep -c data-contract resources/js/Pages/governance/*.tsx` = **0 em todos os 7 arquivos**. Toda seção falha por falta de âncora.
- metade da copy não existe em produção (a `_saida-01` §B1 já tinha medido 59 de 118).
- os 6 `acordos_estado` usam `verdict: "proposto"` / `"ratificado_ADR_0094"`; o verificador só aceita `aprovado | recusado`.

Pôr esse arquivo em `governance/design/contracts/` deixaria o job de contratos ativos vermelho em todo PR. Fazer ele
passar exige ancorar e reescrever as 4 telas, o que é trabalho de tela (e o `nao_toca` desta thread nem o prevê).
**Não criei o arquivo.** Consequência honesta: a prova do índice (`nao_contem notas-kpis` em
`governance/design/contracts/governance.contract.json`) continua falhando por **arquivo ausente**, e o placar segue
mostrando a 07 como não entregue. Isso está certo: a 07 não está entregue.

### B2 · O charter e os casos do prefixo não existem no main, e os do main já estão sem a vista

O prefixo aponta `resources/js/Pages/governance/Index.charter.md` e `Index.casos.md`. **Não há `Index.tsx`** nessa pasta,
e esses dois arquivos só existem no espelho (`cowork-inbox/governance/Index.{charter,casos}.md`). Criá-los no main
geraria charter e casos de uma tela que não existe.

As telas vivas têm charter próprio (`Dashboard`, `Policies`, `Audit`, `DriftAlerts`, …) e nenhum cita a vista:
`git grep -niE "module-?grade|notas dos m|ModuleGrades" -- resources/js/Pages/governance/ governance/design/contracts`
= **0**. A aba já tinha saído do `DataController` no #7283. **No main, o G-NOTAS já está cumprido.**

### B3 · O que falta é do Cowork

O `_DECISOES-W-2026-09-24` pede 4 edições. O handoff 43 fez a 1 (`governance-page.jsx`: 1 ocorrência de "notas", a do
cache-bust). As 2–4 continuam no espelho:

| arquivo do espelho | ocorrências de notas / module-grades |
|---|---:|
| `cowork-inbox/governance/governance.contract.json` | 7 |
| `cowork-inbox/governance/Index.charter.md` | 6 |
| `cowork-inbox/governance/Index.casos.md` | 4 |
| `governance-telas.jsx` (Wagner) | 20 |
| `governance-data.jsx` (Wagner) | 3 |

O Code não edita o espelho (derruba o check required do espelho). Essas edições nascem no Cowork e descem no próximo handoff.

## C · Erratas do índice — registradas aqui, não no índice

1. **Prefixo da 07 aponta para o lugar errado.** Contrato, charter e casos vivem em `cowork-inbox/governance/`; o prefixo
   manda para `governance/design/contracts/` e `resources/js/Pages/governance/`. Sugestão: ou o prefixo passa a ser o
   espelho (e a thread vira do [CC]), ou vira uma thread nova de **ancorar as 4 telas** antes de promover o contrato.
2. **`nao_toca: DataController.php` conflita com o "depois, refazer o #7138".** O #7138 só existe dentro do `DataController`
   (os rótulos da sub-nav moram lá, e o `GovernancaSubNav.tsx` manda não duplicá-los). Cruzei o `nao_toca` só nas 3 strings
   de rótulo e no comentário vizinho, por pedido explícito do [W] na abertura desta sessão. A aba `module-grades` não voltou.
3. **O contrato da sub-nav do #7138 não foi refeito.** O verificador só lê `.tsx/.ts` (`contrato-de-tela.mjs:74`), e os
   rótulos estão em PHP. Travar os rótulos exige estender o verificador — PR próprio, que o #7138 misturava com a correção.
4. **O contrato de estágio da `_saida-01` não existe mais.** O #7224 ("separar fontes por dono e remover paralelos")
   apagou `design-docs/contrato-cowork/governance.contract.json`. A única cópia é a do espelho.

## D · Placar

`node scripts/qa/placar.mjs --indice …/governance/playbook/00-INDICE.md --thread 07` antes e depois deste PR:
`07 [proximo] — governance/design/contracts/governance.contract.json (arquivo ausente)`. O `_saida` passa a existir e a
prova continua falhando, pelos motivos de B1. **Entregue 1 de 4** (o #7138) · **ausentes** contrato (B1), charter e casos (B2).
