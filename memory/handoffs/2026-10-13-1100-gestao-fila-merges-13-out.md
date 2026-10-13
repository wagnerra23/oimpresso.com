---
date: "2026-10-13"
time: "11:00 BRT"
slug: gestao-fila-merges-13-out
tldr: "Gestão da fila de merges em 13/10. Cerca de 40 PRs mergeados (#9152–#9203). Cutover 'ligar tudo' entrou (#9175, #9192) com smoke em produção. Seeder ICMS rodado em produção. Decisões [W] registradas (NFS-e no NfeBrasil, folha gerencial, #9086 aceito, #9188 aprovado). Índices de playbook corrigidos e recibos enviados ao Cowork. Sessão encerrada a pedido do [W] (contexto grande)."
prs: [9152, 9157, 9160, 9162, 9163, 9165, 9166, 9167, 9168, 9170, 9171, 9173, 9174, 9175, 9176, 9177, 9178, 9179, 9180, 9182, 9183, 9184, 9185, 9186, 9187, 9188, 9189, 9190, 9191, 9192, 9193, 9195, 9196, 9200, 9201, 9086, 9126]
---

# Handoff — Gerente da fila de merges (13/10)

Sucede `2026-10-08-0340-gestao-fila-merges-noite-07-out.md`. Mandato [W]: "gerencie o merge de todos, decida". Continuam com ele: valor fora do molde, migration destrutiva, cutover e baseline/foto (ADR 0409).

## Em voo ao fechar

- **#9203** (Compras 06 F3, Pedidos de compra em React atrás de `useV2ComprasPedidos` OFF): o teste passou pelo nome na lane Purchase em 38405529520. O PHPStan foi corrigido no SHA `7b5cdb7728`; o conferir-e-mergear espera a lane desse head. Se ele não mergear, reconfira e mergeie. A sessão dona ("Compras 03 e 04…") segue com a **Compras 07** (Requisições): F1/F2 primeiro, depois F3.
- **Regravação da baseline `sells-index · dark`** (8,6694%, causa medida: #8919 de 07/10, mudança intencional). O [W] aprovou regravar em 13/10. O 1º dispatch falhou porque `screens` usa o campo `source` ("Sells"), não `screen`. O 2º é o run **38405871771** (`screens=["Sells"]`): ele abre um PR com o `.snap`. Mergeie esse PR citando a aprovação do [W].
- **Compras 05 + Acessos 01**: branch local `claude/compras05-acessos-1310` (worktree `hopeful-noether-2a667f`), com 1 commit. São os recibos `_saida-05` e `acessos/_saida-01`, mais o índice de Compras (sem `bloqueio` na 05) e o de Acessos (`ROLES_PAGE = resources/js/Pages/Funcoes/Index.tsx`). Os dois índices já foram verificados, então é preciso subir ao Cowork ANTES do merge, e isso exige o [W] escrever `/design-sync` na sessão que for subir. Com o opt-in: refaça o branch a partir de origin/main, rode `pendentes-cowork --plano`, `finalize_plan`, `write_files`, `get_file`, `--conferir`, e abra o PR com os 4 arquivos e o estado.
- **Recibos ainda não enviados ao Cowork** (isentos de opt-in, já no main): `compras/_saida-A2`, `compras/_saida-06` (depois do #9203), `fiscal/_saida-23`, `sistema/_saida-09`, `sistema/_saida-13`. Rode `node scripts/design-sync/pendentes-cowork.mjs --plano` num worktree em origin/main.

## O que entrou (destaques)

- **Cutover [W] 13/10 "pode ligar tudo"**: #9175 (Notificações) e #9192, que liga as flags (Contas, Funções, Voz do Cliente, Planilhas e a ficha e as empresas do Officeimpresso) e as chaves MWART (Catálogo QR para todas; Descontos, Importação e Pedido de venda só no biz=1). O #9192 foi mergeado pela conta do [W], sem passar pelo meu conferir. Depois do merge medi: a lane Acessos no main ficou verde, com Contas, Funções e Caixa passando pelo nome. Smoke em produção: as 11 rotas devolvem o componente React, e há screenshot de Contas, Funções, Voz do Cliente e Planilhas.
- **Seeder ICMS** rodado em produção (D-SEED-ICMS): biz=1 com 146 a 7%, 556 a 12% e 27 internas. Recibo em `fiscal/_saida-30`.
- **Decisões [W]**: D-NFSE-CONSOLIDA (NFS-e consolida no NfeBrasil, #9186); D-FOLHA-ROTULO ("folha gerencial"); #9086 (Fabricação × Produção, proposta `aceito`); #9188 (retenções NFS-e, valor, aprovado e mergeado); Compras 05 aprovada por screenshot (biz=1, porque R6 proíbe biz=4).
- **Grade de compra no tema escuro**: #9193 (tabela) e #9201 (painel e rótulos). O resto de `Purchase/Create.tsx` ainda tem `stone-*`: dados gerais, itens e totais.
- **Índices corrigidos** (#9190, com opt-in [W]): `PAGE` das Notificações, prova 11 do Officeimpresso, Compras 03/04, Fiscal 23.

## Pendências do [W]

1. **Voz do Cliente**: onde fica o "motivo do fechamento" (coluna nova em `voz_sinais` ou sem motivo).
2. **Grupo de preço de outro negócio** (Sistema 02) e **GroupTaxController** (Tier 0 + valor).
3. **Contas**: KPIs e total de saldo do protótipo (cálculo de valor).
4. **Divergências de texto do Estoque** (`_saida-00`): filtro, Sim/Não × moveu/só reserva, Finalizada, Itens/id, paginação.
5. **`/design-sync`** para a Compras 05 e a Acessos 01 (acima).
6. **venda-menu C2–C6**: a C1 foi ligada no biz=1 pelo #9192, e agora começa a observação de 7 dias (C3 depois da C2).

## Lições

- **O filtro `screens` do dispatch do visual-regression casa o campo `source`** do `visreg-screens.json` (ex.: "Sells"), não `screen` ("Sells/Index").
- **O opt-in `/design-sync` não persiste**: o hook lê a mensagem recente do [W]. Arquivo NOVO no espelho sobe depois do merge sem opt-in. Editar arquivo JÁ verificado (índice) exige opt-in antes do merge.
- **Mudar o default de flag/chave para ligado** quebra os testes que zeravam a flag para medir a Blade; eles precisam de stub do FeatureFlagService desligado ou de `?legacy=1`. O agente do #9192 resolveu assim.
- **`withHeaders` persiste entre requisições do mesmo teste** (Laravel): o 2º caminho do UC-PEDC-03 saiu com `X-Inertia`. O conserto é `flushHeaders()`.
- **`Symfony\…\Response` não cobre `Inertia\Response` nem `View`**: para controller dual, use a união `JsonResponse|View|Inertia\Response`. Eu instruí errado e a sessão mediu.
- **#9126 e #9086 travavam por review**: CODEOWNERS e pedido de mudanças. Com a aprovação do [W] no chat, registrei a revisão pela conta dele, citando a mensagem.

## Estado MCP no momento do fechamento

Não consultado nesta sessão, para economizar contexto. O brief do início da sessão não tinha cycle ativo e tinha 4 HITL do [W].
