---
date: "2026-10-13"
time: "18:57 BRT"
slug: gestao-fila-merges-13-out-2
tldr: "Segunda gerência da fila em 13/10. 44 PRs no main (#9198–#9243), 3 fechados sem merge (#9205, #9221, #9237). Compras fecha 9 de 9, Essenciais 5 de 5, NFS-e thread 23 inteira no main, pilha Sistema 08 até o #9217. Main voltou ao verde na lane Acessos (#9232). Redirect /contacts → /cliente em produção com smoke. Placar 310 de 392. Em voo: #9246, #9247, #9219 e #9223, mais 4 sessões abertas por chip."
prs: [9198, 9199, 9202, 9203, 9204, 9206, 9207, 9208, 9209, 9210, 9211, 9212, 9213, 9214, 9215, 9216, 9217, 9218, 9220, 9222, 9224, 9225, 9226, 9227, 9228, 9229, 9230, 9231, 9232, 9236, 9238, 9239, 9240, 9241, 9242, 9243]
---

# Handoff — Gerente da fila de merges (13/10 · 2)

Sucede `2026-10-13-1100-gestao-fila-merges-13-out.md`. Mandato [W]: "gerencie o merge de todos, decida". Continuam com ele: valor fora do molde, migration destrutiva e cutover. Baseline/foto passou para o gerente (ver "Decisões [W]").

## Em voo ao fechar

Os processos em background desta sessão morrem com ela: **relance o conferir-e-mergear** de cada item.

- **#9247** (Sistema 06, conserto do 500 no "Testar envio" de notificações): PHPStan corrigido no head `79505ac99`. Rode `conferir-e-mergear.sh 9247 38450802716 NotificationTemplateTest` (lane Acessos por dispatch) e confira o UC-NOT-22 pelo nome. Até ele entrar, o "Testar envio" pode dar 500 em produção com modelo que usa `{contact_name}`.
- **#9246** ([W+C], prévia do e-mail pelo HtmlSanitizer): só required. `conferir-e-mergear.sh 9246`.
- **Pilha Sistema 08** (Configuração da empresa): faltam **#9219** e **#9223**, nessa ordem. A sessão "Playbook Sistema 08" faz o sync com o main e dispara a lane Acessos por dispatch; você mergeia com `EmpresaContratoTest` PASS pelo nome no head. Fale com ela pelo canal do app (`mcp__ccd_session_mgmt__send_message` para `local_b2de70d5-28a2-4452-94a0-4433a808c919`): o `SendMessage` comum fica retido pela diferença de modo.
- **4 sessões abertas por chip** (mandam PR + teste + run ao gerente; não mergeiam nem sobem recibo): Financeiro 11 + A3, Crm A2, Contratos (Recorrente 01, Telas soltas 01, Módulos Faltantes 01), Alvos (Produto A1, Restaurante A1, Woocommerce A1). **Avise as quatro do nome do novo gerente.** A quinta, Forja 09, não abriu PR: a thread já estava no main pelo #8985; só falta o Cowork aplicar a errata do índice (#9220).
- **Recibos a subir ao Cowork**: `cliente/_saida-02` e os que chegarem (08i/08j do Sistema 08 e os das sessões acima). `pendentes-cowork --plano` num worktree em origin/main.
- **Próximas tarefas já combinadas**: Sistema 06 → Repair/02 e Manufacturing/04; Sistema 08 → Tema escuro/02 depois da pilha.

## Cliente 04 em produção (#9239)

Smoke logado em biz=1, deploy 38446898813: `/contacts?type=customer` e `?type=supplier&q=teste` caem em `/cliente` com a query; a ficha `/contacts/1` segue o redirect antigo do `ContactController@show`, sem salto extra; AJAX legado com `X-Requested-With` não redireciona (200 JSON); sem login, os dois endereços vão para `/login` como antes. O 302 (e não 301) é provado pelo `RedirectLegacyContactsTest`, não pelo browser.

## Decisões [W] desta sessão

- **Baseline/foto sem perguntar** ([W] 13/10: "grave sem eu a baseline"). Os 5 snaps de Sells/Index foram regravados no #9208.
- **Configuração da empresa sem baseline de pixel** ("opção 2"): vai pela comparação protótipo vivo × render quando for ligada. O visual-regression dela fica vermelho na pilha e não é required.
- **#9211** autorizado: ADR 0430 ratificada (retenção LGPD do Patrimônio como anonimização, nasce desligada).
- **#9215 e #9221** autorizados. O #9221 foi fechado como incorporado pelo #9226, que o [W] mergeou direto.
- **Re-medir Cliente/Index e User/Perfil no staging**: [W] preferiu registrar a pendência a logar. Comando no `cliente/_saida-02`.

## Pendências do [W]

1. **Re-medição** do Cliente/Index e do User/Perfil no staging (precisa de sessão logada).
2. **Devoluções de compra**: somar valores no rodapé/KPI (regra mestre); corrigir no protótipo a frase "nunca avulsa".
3. **Configuração da empresa**: F4 (smoke biz=1 com a flag) e cutover depois da pilha.
4. **Projeto `copia` do Cowork** (da Maiara): faltam os recibos da fiscal 23–23d e o plano lista 1.476 arquivos. Fora desta gerência sem pedido explícito.
5. Do handoff anterior: Voz do Cliente (motivo do fechamento), grupo de preço de outro negócio, KPIs de Contas e textos do Estoque.

## Lições

- **PR redirecionado para o main não dispara os workflows de PR.** O #9199 chegou ao main sem a lane Acessos ter rodado no head. Confira que existe run da lane no head; se não houver, faça merge do main ou `workflow_dispatch`.
- **Lane não required não segura nada.** O #9194 entrou (pela conta do [W]) com o `EmpresaContratoTest` quebrado e deixou o main vermelho na lane Acessos até o #9232. O critério da fila é o teste pelo nome no head.
- **Fixture que deixa coluna nula esconde normalização.** O "salvar não mexe no resto" falhou porque o salvar normaliza nulos como a Blade. Antes de aceitar a mudança do fixture, exigi a prova de que a Blade faz o mesmo (UC-EMP-04 controle).
- **Os dois arquivos de estado do espelho conflitam a cada merge** (`.cowork-freshness-ledger.json`, `enviados-cowork.json`). Resolva por união com `node D:/oimpresso.com/.claude/gestor-fila-scripts/noite-0710/uniao-estado.mjs FETCH_HEAD` depois de `git merge FETCH_HEAD`. Junte vários recibos num PR.
- **`get_file` devolve inline**: a cópia de volta é escrita a partir do retorno (ADR 0389); divergência sai DIFERENTE, nunca IGUAL falso.
- **Watcher antigo sobrescreve o log.** Dois `conferir-e-mergear` do mesmo PR escrevem no mesmo `m<PR>.log`. Pare o antigo antes de relançar com head novo.
- **`gh pr ready` dispara checks novos**: o script viu os verdes antigos e tentou mergear cedo. Espere 90 s antes de reconferir.
- **Duas sessões registraram o mesmo recibo** (#9236 × #9237, 26 s de diferença). Só o gerente sobe e registra recibo.

## Estado MCP no momento do fechamento

Não consultado nesta sessão (contexto grande). O brief do início não tinha cycle ativo e tinha 4 HITL do [W].
