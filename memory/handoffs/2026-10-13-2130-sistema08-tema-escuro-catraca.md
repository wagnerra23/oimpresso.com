---
date: "2026-10-13"
time: "21:30 BRT"
slug: sistema08-tema-escuro-catraca
tldr: "Pilha Sistema 08 (Configuração da empresa, 16 abas em Inertia atrás da flag) inteira no main até o #9223. Tema escuro 05 (catraca da sonda de produção em job próprio) e 03 (as 9 Pages restantes) entregues na pilha #9265→#9321; o gerente mergeia só o #9321, que contém tudo. Com a pilha, a sonda mede 75 de 75 Pages sem superfície clara e a baseline fica vazia."
prs: [9219, 9223, 9265, 9289, 9314, 9316, 9317, 9318, 9319, 9320, 9321]
---

# Handoff — Playbook Sistema 08 + Tema escuro 03/05

Sessão de executor: sincroniza, prova e manda o run ao gerente da fila; quem mergeia é o gerente.

## Fechado

- **Sistema 08 (Configuração da empresa)** — #9219 (fidelidade e módulos) e #9223 (rótulos) no main. Prova no head de cada um: lane Acessos por dispatch, `EmpresaContratoTest` PASS pelo nome (14 e 15 casos). A tela segue atrás de `useV2ConfiguracoesEmpresa` (OFF; `?classico=1` força o Blade). Recibos `_saida-08*` sobem com o gerente.
- **Tema escuro 05** — catraca sobre a sonda de produção: `scripts/design/tema-escuro-producao-baseline.json` + `--catraca` na sonda. Job próprio `tema-escuro · Pages de produção vs baseline` no `visual-regression.yml` (o job visual já leva 1048–1316 s contra teto de 1500 s). Setup Laravel e semente saíram do YAML, literais, para `scripts/tests/visreg-ci-setup.sh`. Controle vermelho provado (run 38458067977). Recibo `_saida-05.md`.
- **Tema escuro 03** — as 9 Pages que sobravam, 1 por PR, empilhadas: Oficina/OS, StockTransfer/Create, Governance/Dashboard, Manufacturing/Settings (thumb do Switch isentado, não era defeito), Financeiro Dre+Fluxo (aviso em 5 cópias), Unificado, Compras, PlanoContas. Topo (#9321, run 38473412958): **75 de 75 Pages com 0 superfície clara**, baseline vazia. Recibo `_saida-03.md`.

## Em voo ao fechar

- **#9321** no gerente: mergeia só ele (contém #9265–#9320, que fecha como incorporados). Não empurrar nada nele.
- **Recibos** `_saida-03.md` e `_saida-05.md` estão no #9321 e não subiram para o Cowork.

## Decisões [W] pendentes

- Promover o job da sonda a required (hoje mora no `visual-regression.yml`, advisory).
- Thread 02 do Tema escuro aparece "indecidível" no placar porque o avaliador de prova `execucao` não foi portado; o recibo existe desde 07/10.
- Sistema 08: `amount_for_unit_rp = 0` divide por zero; testar e-mail/SMS e apagar segredo só existem na tela antiga; cutover da flag.

## Lições

- O job da sonda só dispara em PR para `main`: pilha empilhada precisa abrir cada PR contra `main` (diff mostra os de baixo).
- Cor em `style` inline não pega `dark:`: o conserto é token dark-aware ou classe com par `dark:`.

## Estado MCP no momento do fechamento

Não consultado: a cota da API do GitHub estourou no dia e o gerente pediu para espaçar chamadas. Fonte deste handoff: os runs e PRs citados acima.
