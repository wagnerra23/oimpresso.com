---
sessao: "06"
titulo: Modelos de notificação · Contas → Inertia — saída
playbook: sistema
thread: "06"
dono: "[CL]"
data: "2026-10-13"
base: wagnerra23/oimpresso.com@main d0e6c8b476 (lido 2026-10-13)
---

# _saida-06 · Modelos de notificação · Contas → Inertia

As duas telas pelo MWART (ADR 0104), com a página React atrás de flag **desligada**. **F4 (smoke biz=1 com a flag
ligada) e F5 (cutover) não foram feitas**: as duas são decisão [W]. Com as flags desligadas, produção segue nas Blades.

## 1 · Feito

| Tela | Rota | Flag (default OFF) | F1+F2 | F3 |
|---|---|---|---|---|
| Modelos de notificação | `/notification-templates` | `useV2NotificationTemplates` | #9113 (backend dual) | #9115 (seleção) · #9159 (editor) |
| Contas de pagamento | `/account/account` | `useV2ConfiguracoesContas` | #9167 | PR deste recibo (lista) |

- **Notificação:** entregue por outra sessão antes desta (#9113 em 2026-10-09; o #9159 cita `Sistema/06`). A prova
  `Inertia::render(` no `NotificationTemplateController` nasceu em 2026-10-09, **depois** do índice (2026-10-05): é
  entrega desta thread, não padrão pré-existente. Esta sessão não tocou a tela de notificação.
- **Contas:** Page `resources/js/Pages/Configuracoes/Contas/Index.tsx` com charter, casos e `ContasContratoTest`
  (UC-CONTA-01..04). O SQL do saldo e a restrição por local saíram do `index()` para
  `AccountController::consultaContas`, usado pela DataTable da Blade e pela tela React — um SQL só. O saldo chega à
  tela já formatado pelo mesmo `num_f` da Blade; o navegador não calcula nem formata valor. `?legacy=1` abre a Blade
  com a flag ligada, porque cadastrar e movimentar ainda são modais dela.
- `AccountController.php` entrou no gatilho da lane `acessos-pest`, que descobre `tests/Feature/Configuracoes/`.

## 2 · Não feito e por quê

- **F4 e F5** das duas telas (acima).
- **Contas, drawer de cadastro/edição e ações de movimento** (depositar, transferir, encerrar, reativar): mexem em
  valor (saldo de abertura, depósito, transferência). Ficam para PR próprio, com dupla prova e merge do [W].
- **Total do rodapé e KPIs de saldo do protótipo** ("Disponível", "Conta de capital"): somar saldo é cálculo de valor.
  Fora até o [W] decidir (`contas-parity` #9).
- **Tipos de conta:** são do `AccountTypeController`, fora do prefixo; a tela nova leva à Blade.
- **Notificação:** os pendentes que o #9159 lista (modo Visual, prévia sanitizada, restaurar padrão) seguem com a
  sessão dona da US-NOTIF-004.

## 3 · Achados para o índice (não editei o índice — é do Cowork)

1. **Duplicata com `notificacoes/playbook/01`.** A thread 01 de `notificacoes` ("Modelos de notificação em Inertia")
   é a mesma tela desta thread. A entrega que fecha as duas é a mesma: #9113 + #9115 + #9159. **Esta saída fecha a
   06 de `sistema`.** A 01 de `notificacoes` não fecha sozinha porque a prova dela é `${PAGE}` com `PAGE` nulo:
   sugestão ao Cowork — fixar `PAGE = resources/js/Pages/NotificationTemplate/Index.tsx` e dar a 01 por entregue
   pelos mesmos PRs, ou descartá-la como duplicata desta.
2. A Page de Contas mora em `Configuracoes/` (prefixo do índice), mas o protótipo desenha Contas no Financeiro
   (`financeiro-legado.jsx`, `fin-bancos`) e o `_saida-00` já registrou isso. A rota não muda; a aba de
   Configurações (`ConfiguracoesSubNav`) deriva do grupo de menu onde a rota está — o de Contas de pagamento.
3. `permitted_locations()` lê só as permissões **diretas** do usuário (`$user->permissions`), não as do papel.
   Quem recebe `location.N` só pelo papel não vê as contas daquele local. Vale para a Blade e para a tela nova;
   travado no baseline como está. Mudar é decisão [W].

## 4 · Provas

- `Inertia::render(` no `NotificationTemplateController` (desde #9113) e no `AccountController` (PR deste recibo).
- `ContasBaselineTest` (#9167): saldo crédito − débito sem lançamento apagado por dois caminhos (à mão × `data-orig-value`
  da DataTable) e saldo de abertura por dois caminhos (endpoint × `num_uf`), tenant 98 × 99, filtro de situação,
  restrição por local, 403.
- `ContasContratoTest`: o mesmo saldo e o mesmo texto `num_f` na prop da tela React, a conta alheia fora, a restrição
  por local também na tela nova.
- Pest não rodou local (regra CT 100): a prova é a lane `acessos-pest`, conferida por nome de teste no PR.

## 5 · Placar

entregue 2 de 2 telas até F3 (Contas: lista; drawer pendente) · ausentes F4/F5 por decisão [W] · total de saldo e
ações de movimento por decisão [W] (regra mestre de valor).
