# Pedido zero-toque — grupo Usuários: /roles, /sales-commission-agents, /prefs

> De [CC] para [CL] · 2026-08-19 · escopo aprovado por [W] no chat do Cowork.
> Leitura base no `main` **deste turno**: `routes/web.php` (323, 327, 599, 715), `RoleController.php`,
> `SalesCommissionAgentController.php`, `config/app.php`, `app/Http/Middleware/Timezone.php`,
> `app/Http/Kernel.php`, `app/Console/Kernel.php`, `Modules/Superadmin/.../Usuario360/*`.
> **Nada commitado** — tools read-only. Cole ou abra Issue `cowork-intake`.

## Contexto em 4 linhas

`/roles` e `/sales-commission-agents` seguem **Blade + DataTables ajax** — é onda de **tradução**, não de
acabamento (diferente de `/modulos`). `/user/profile` legado é Blade, mas a tela Inertia de "Meu perfil"
já existe (web.php:327). `/superadmin/usuarios` (Usuário 360) **já está vivo em Inertia com charter** —
não refazer. Sobram três telas para traduzir e uma para criar (apuração de comissão).

## Antes de codar — 4 decisões de [W]

| # | Decisão | Recomendação |
|---|---|---|
| **D-A** | Permissão própria de comissionado (`commission_agent.view|manage`) ou segue em `user.*`? Hoje quem vê comissão vê usuários. | criar a permissão própria na PR-3 |
| **D-B** | Comissionado unificado: marcar `is_cmmsn_agnt` no usuário existente em vez de criar segunda linha em `users`? | sim, com migration de dedupe por e-mail |
| **D-C** | `APP_TIMEZONE` fixo e boot que falha sem ele (mata o fallback `Europe/London`)? | sim — é a PR-1, a mais barata |
| **D-D** | Editar o papel `Cashier` queima o `is_default` para sempre (regra do controller). A tela avisa antes ou o backend passa a preservar? | avisar na tela; não mexer no backend |

## PRs propostos (nesta ordem)

> Ordem = do mais barato e reversível para o irreversível. Legado sai por último.

### PR-1 · fix: fuso — matar o Europe/London (5 min, risco zero, depende de D-C)
`config/app.php` tem `env('APP_TIMEZONE', 'Europe/London')`. Todo caminho **fora** do middleware
`timezone` (API sem sessão, job de fila, cron, emissão) roda em Londres: +3 h no verão inglês, +4 h no
inverno. É o "empurra 3h" comentado em `SellController.php:923` e o bug com memória própria
(`feedback_carbon_timezone_bug`).
- fixar `APP_TIMEZONE=America/Sao_Paulo` em `.env` de todos os ambientes + `.env.example`;
- trocar o default do `config/app.php` para `America/Sao_Paulo` (defesa em profundidade);
- teste pronto: `tests/Feature/Architecture/TimezoneGuardTest.php` (G1).

### PR-2 · fix: vazamento de fuso em job (curto, alto impacto)
`RecurringExpense.php:60` e `RecurringInvoice.php:66` fazem `date_default_timezone_set($transaction->business->time_zone)`
**dentro do loop** e nunca restauram: o fuso do último negócio processado vaza para o próximo e para o
resto do job. Trocar por formatação no fuso do negócio na saída (Carbon), sem tocar o estado global;
se precisar do global, `try/finally` restaurando. O mesmo teste (G2) trava a regra.
Inclui: `NfeController.php:881` e `SocialAuthController.php:162` param de usar o literal (G3).

### PR-3 · sec: catálogo de permissões fechado (o achado mais grave)
`RoleController::__createPermissionIfNotExists()` **cria qualquer nome de permissão que chegar no POST** —
sem whitelist, e `permissions` é global (sem `business_id`). Um POST forjado polui a tabela para todos os
tenants.
- validar `permissions[]` contra o catálogo (fonte: `ModuleUtil::getModuleData('user_permissions')` +
  as chaves core) e **422** no que não casar;
- manter `__createPermissionIfNotExists` só para chaves do catálogo que ainda não têm linha;
- teste pronto: `RoleControllerTest` casos 6 e 7.

### PR-4 · fix: grupo de preço duplicado no create (bug de divergência)
Em `store()`, `radio_option` é lido **duas vezes** (como `$spg_permissions` e como `$radio_options`) ⇒ a
permissão de grupo de preço entra duplicada no array. Em `update()` a primeira leitura usa a chave certa
(`spg_permissions`). Alinhar os dois métodos; teste caso 7 cobre create **e** update.

### PR-5 · fix: exclusão com guarda ([W]: "tenho medo do excluir")
- `SalesCommissionAgentController::destroy()` é **hard delete** de `users`: vendas com
  `commission_agent` apontando para o id ficam órfãs. Passar a **bloquear** (422 com a contagem) quando
  houver venda; sem venda, `is_cmmsn_agnt = 0` em vez de `delete()`.
- `RoleController::destroy()` não checa quantos usuários usam o papel: bloquear com a contagem.
- Regra de produto: **a guarda é por vínculo de dado, não por negócio**. Só ROTA LIVRE está viva e
  Martinho (biz=164) está em migração, mas isso libera limpeza de **base**, não afrouxa a tela.
  Cuidado na limpeza: `config/app.php` protege `PROTECTED_BUSINESS_IDS=1,4` e biz=1 (WR2) tem dado real.
- testes prontos: `SalesCommissionAgentTest` casos 5–7.

### PR-6 · feat: tradução de /roles para Inertia (a grande)
Destino `resources/js/Pages/Roles/{Index,Form}.tsx` + charter e casos já escritos em `repo/`.
O F1 (`prototipo-ui/cowork/acessos/funcoes-page.jsx` + `funcoes-perms.jsx`) normaliza o checkbox-soup em
5 formas de controle — **esta é a parte que não se deve reinventar na tradução**:
1. **escopo** colapsa "ver todos X" + "ver próprio X" num segmented (Todos · Só os próprios · Sem acesso);
2. **crud** numa linha (ver/criar/editar/excluir), com "ver" implícito;
3. **toggle** avulso;
4. **seletor exclusivo** (os 5 "cliente sem venda", as 4 situações de venda, os 3 níveis de reversão);
5. **chave crua** com aviso, para as 27 sem lang string (`Fiscal` 7, `PaymentGateway` 10, `RecurringBilling` 10).
Mais: grupo de preço é **radio** (não checkbox), `is_service_staff` é **coluna do papel** (não permissão),
papel `is_default` é somente leitura exceto `Cashier` (D-D), e o rodapé mostra a diferença desde o padrão
(`+N −M`), quantas permissões de risco estão ativas e quantos usuários são afetados.

### PR-7 · feat: tradução de /sales-commission-agents + apuração
- Índice + cadastro (campos reais: `surname` usado como prefixo, `first_name`, `last_name`, `email`,
  `contact_no`, `address`, `cmmsn_percent` com `num_uf`) em `resources/js/Pages/CommissionAgents/`.
- **Novo backend de comissão** (D6 do trio, aprovado por [W]): agente na venda, regra por agente
  (fixa · faixa de meta · margem), apuração por período, fechamento e geração de **título a pagar** no
  Financeiro. O legado não tem nada disso — hoje só existe `cmmsn_percent`.
- F1 de referência: `comissionados-page.jsx` (cadastro) e `comissoes-page.jsx` (apuração + extrato).

### PR-8 · feat: /prefs em Inertia + os três relógios
`prefs-page.jsx` traz Empresa (identidade · fiscal · numeração · formato e região) e Você (conta ·
aparência · avisos), **e o bloco de diagnóstico de fuso**: relógio da empresa, do servidor (UTC) e do
navegador, com destaque na divergência. Responde por si a pergunta recorrente de onde vêm as 3 h.
⚠️ **Não li as views do `/prefs`** — os campos são proposta e precisam de conferência campo a campo
contra `BusinessController` (`postBusinessSettings`) e as preferências do Essentials.

### PR-9 · chore: aposentar o Blade (só com a lane verde)
`resources/views/role/{index,create,edit}.blade.php`, `resources/views/sales_commission_agent/*` e as
rotas resource antigas. Antes: `LegacyMenuAdapter` mapeando `/roles → /funcoes` e
`/sales-commission-agents → /comissionados`, no padrão que já foi usado para `/manage-modules → /modulos`.

## Checklist pós-merge

- [ ] `php artisan test --filter=RoleController` e `--filter=SalesCommissionAgent` verdes.
- [ ] `--filter=TimezoneGuard` verde (G1–G3) e `APP_TIMEZONE` presente em produção.
- [ ] Cron de recorrentes rodado com **dois** negócios de fusos diferentes na mesma execução (prova do PR-2).
- [ ] `prototipo-ui/contrato/funcoes.contract.json` e `comissoes.contract.json` no CI.
- [ ] Nenhuma permissão nova criada por POST (contar `permissions` antes/depois de um smoke).
- [ ] Sidebar: `/roles` e `/sales-commission-agents` levando às telas novas.
