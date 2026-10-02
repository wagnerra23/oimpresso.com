# Notas para o revisor das lojas (App Store / Google Play)

> Texto para colar em **App Store Connect → App Review Information → Notes** e em
> **Google Play Console → Acesso ao app**.
>
> - **App:** telas próprias do app (repo `oimpresso-app`), falando com o ERP pela API Passport
>   (`/oauth/token` + `/ponto/api/*` + `/api/app/*`). Decisão [W] 2026-10-01 ("telas próprias + API
>   Passport"); registro em `docs/lojas-app/DECISOES.md`.
> - **Nomes das telas e botões:** os do `main` do `oimpresso-app` em 2026-10-02 (as 7 áreas da v1, D13),
>   conferidos no código do app pela sessão APP CAPACITOR. Se o app mudar um rótulo, mude aqui também.
> - **Navegação por perfil (D6):** o app monta a barra de baixo a partir de `perfil`/`abre_em`/`areas`
>   do `GET /api/app/inicio` (contrato §6, ERP #8495; app `oimpresso-app` #20). Colaborador abre no
>   **Ponto**, com a barra **Ponto · Mais**. ⚠️ Se essa rota não estiver em produção, o app cai na
>   barra antiga de 5 abas, e o revisor veria áreas sem acesso. **Só enviar à loja com o #8495 em
>   produção** (portão segurado pela sessão de coordenação do app).
> - **Contas (D7, [W]): duas**, ambas no business demo 235 ("Demo Ponto — revisão das lojas"),
>   criadas por `php artisan ponto:demo-revisor`. Empresa de demonstração isolada; nenhum cliente real.
>   - `revisor.ponto` — colaborador: só o Ponto (barra Ponto · Mais).
>   - `gestor.demo` — gestor: as 7 áreas da v1 (Início, Tarefas, Pedidos, Produção, Pessoas, Ponto,
>     Mais), que são as que a ficha da loja descreve (D9).
>   ⚠️ **Conferir antes de enviar:** com o #8495 em produção, `gestor.demo` precisa receber as 7 áreas
>   no `GET /api/app/inicio` (`areas`). Pedido à sessão da conta demo pela coordenação em 2026-10-02.
>   Sem isso, a ficha descreve área que o revisor não alcança (risco de recusa).
> - **Senhas:** não estão aqui. Ficam no Vaultwarden, itens `ponto-demo-revisor` e `ponto-demo-gestor`;
>   quem preenche a loja copia de lá para o campo de credenciais da revisão.
> - **Antes de enviar para revisão:** `php artisan ponto:demo-smoke --sem-marcar` (confere a conta
>   pela API, como o app) e `php artisan ponto:demo-dados` (deixa os dados de "hoje" com a data do
>   dia). O login real pelo app depende do client OAuth público do app existir no ERP.
> - **Sem "REP-P", "REP" nem "registrador oficial"** no texto (D9, levantamento legal #8417). A versão
>   anterior destas notas citava "REP-P"; foi tirado em 2026-10-02.

---

## Português

**Contas de demonstração**
Duas contas da mesma empresa de demonstração. As senhas vão no campo de credenciais da revisão.
- `revisor.ponto` — funcionário: vê o **Ponto** (registro de ponto).
- `gestor.demo` — gestor: vê o app inteiro (**Início**, **Tarefas**, **Pedidos**, **Produção**,
  **Pessoas**, **Ponto** e **Mais**).

**O que testar com `revisor.ponto` (funcionário)**
1. Abra o app. Em **Usuário ou e-mail** digite `revisor.ponto`, preencha a **Senha** e toque em
   **Entrar**.
2. A conta é de um funcionário, então o app abre direto no **Ponto**. A barra de baixo tem **Ponto**
   e **Mais**.
3. **Ponto → Bater ponto:** ao abrir o Ponto, o app pede a localização (somente durante o uso).
   Escolha o tipo (**Entrada**, **Saída almoço**, **Retorno almoço** ou **Saída**) e toque em
   **Bater ponto — Entrada** (o texto acompanha o tipo escolhido). Aparece o **Comprovante de
   marcação** com o NSR, o hash e o local. Se o sinal estiver fraco, toque em **Atualizar local**.
4. **Ponto → Meu espelho:** mostra as marcações do mês.
5. **Ponto → Justificar:** escreva uma justificativa (por exemplo, "esqueci de bater a saída") e
   toque em **Enviar para aprovação**. Ela fica pendente para o gestor.
6. **Mais → Conta:** **Ativar lembrete**, **Política de privacidade**, **Excluir minha conta** e
   **Sair**.

**O que testar com `gestor.demo` (gestor)**
1. Em **Mais → Conta**, toque em **Sair**. Entre com `gestor.demo` e a senha dele.
2. **Início:** faturamento de hoje, pedidos, financeiro e as próximas tarefas.
3. **Tarefas:** tarefas agrupadas por prazo. Toque numa tarefa e em **Concluir tarefa**.
4. **Pedidos:** lista por etapa. Toque num pedido para ver itens, cliente e andamento. No app o
   pedido é só consulta: mudar a etapa é feito no computador.
5. **Produção:** os pedidos em produção, separados por etapa. Toque numa etapa para ver os pedidos
   dela.
6. **Mais → Pessoas:** clientes, fornecedores e equipe, com busca. Toque numa pessoa para ver a
   ficha.
7. **Mais → Ponto:** o mesmo registro de ponto do funcionário.

**Por que o app pede localização**
O registro de ponto eletrônico guarda onde a marcação foi feita, para o empregador conferir
(Portaria MTP 671/2021). O app pede localização somente durante o uso, ao abrir o **Ponto**, para
mostrar a precisão do GPS antes de bater; a localização é registrada apenas no momento da marcação.
Não há permissão de localização em segundo plano. O login não pede localização. Se a precisão do
GPS for muito baixa (acima de 500 m), o registro é recusado e o app pede para ir a uma área aberta.
O app não usa câmera nem biometria.

**Você está fora do Brasil?**
Pode registrar normalmente. Marcações longe do local da empresa não são recusadas; no máximo ficam
sinalizadas para conferência do gestor.

**Por que não há cadastro dentro do app**
O app é de uso interno de empresas clientes do Oimpresso. Quem cria a conta do funcionário é o
empregador, no sistema da empresa. Por isso não existe tela de criação de conta no app.

**Por que as duas contas veem telas diferentes**
O app mostra a cada pessoa só as áreas que o empregador liberou para ela no sistema da empresa. Um
funcionário (`revisor.ponto`) vê o Ponto; um gestor (`gestor.demo`) vê também Início, Tarefas,
Pedidos, Produção e Pessoas.

**Exclusão de conta**
Pelo próprio app, em **Mais → Conta → Excluir minha conta**, ou pela página pública de exclusão de
dados do Oimpresso. Registros de ponto têm guarda obrigatória por lei (Portaria MTP 671/2021) e são
mantidos pelo prazo legal.

---

## English

**Demo accounts**
Two accounts from the same demo company. The passwords are in the review credentials field.
- `revisor.ponto` — employee: sees **Ponto** (time clock).
- `gestor.demo` — manager: sees the whole app (**Início** home, **Tarefas** tasks, **Pedidos**
  orders, **Produção** production, **Pessoas** contacts, **Ponto** and **Mais** more).

**What to test with `revisor.ponto` (employee)**
1. Open the app. In **Usuário ou e-mail** (username or e-mail) type `revisor.ponto`, fill in
   **Senha** (password) and tap **Entrar** (sign in).
2. This is an employee account, so the app opens straight on **Ponto** (time clock). The bottom bar
   has **Ponto** and **Mais** (more).
3. **Ponto → Bater ponto (Clock in):** when Ponto opens, the app asks for location (while using the
   app only). Choose the punch type (**Entrada** = clock in, **Saída almoço** = lunch out,
   **Retorno almoço** = back from lunch, **Saída** = clock out) and tap **Bater ponto — Entrada**
   (the label follows the chosen type). A **Comprovante de marcação** (punch receipt) appears with
   the NSR sequence number, hash and location. If the GPS signal is weak, tap **Atualizar local**
   (refresh location).
4. **Ponto → Meu espelho (My timesheet):** shows this month's punches.
5. **Ponto → Justificar (Justify):** write a justification (for example, "forgot to clock out") and
   tap **Enviar para aprovação** (send for approval). It stays pending for the manager.
6. **Mais → Conta (More → Account):** **Ativar lembrete** (turn on reminder), **Política de
   privacidade** (privacy policy), **Excluir minha conta** (delete my account) and **Sair** (sign
   out).

**What to test with `gestor.demo` (manager)**
1. In **Mais → Conta**, tap **Sair** (sign out). Sign in with `gestor.demo` and its password.
2. **Início (Home):** today's sales, orders, finance and the next tasks.
3. **Tarefas (Tasks):** tasks grouped by due date. Tap a task, then **Concluir tarefa** (complete
   task).
4. **Pedidos (Orders):** list by stage. Tap an order to see items, customer and progress. In the
   app orders are read-only: changing the stage is done on the computer.
5. **Produção (Production):** orders in production, split by stage. Tap a stage to see its orders.
6. **Mais → Pessoas (More → Contacts):** customers, suppliers and staff, with search. Tap a person
   to see their details.
7. **Mais → Ponto (More → Time clock):** the same time clock as the employee.

**Why the app asks for location**
Brazilian electronic time-tracking rules (Portaria MTP 671/2021) require recording where each punch
was made, so the employer can review it. The app asks for location only while in use, when Ponto
opens, to show GPS accuracy before clocking in; location is recorded only at the moment of the
punch. There is no background location permission. Sign-in does not ask for location. If GPS
accuracy is too poor (worse than 500 m), the punch is refused and the app asks the user to move to
an open area. The app uses no camera and no biometrics.

**Testing from outside Brazil?**
You can punch normally. Punches far from the company location are not rejected; at most they are
flagged for the manager to review.

**Why there is no sign-up in the app**
This is an internal app for companies that use Oimpresso. Employee accounts are created by the
employer in the company's system, so the app has no sign-up screen.

**Why the two accounts see different screens**
The app shows each person only the areas their employer enabled for them in the company's system.
An employee (`revisor.ponto`) sees the time clock; a manager (`gestor.demo`) also sees Início
(home), Tarefas (tasks), Pedidos (orders), Produção (production) and Pessoas (contacts).

**Account deletion**
In the app, under **Mais → Conta → Excluir minha conta**, or through Oimpresso's public
data-deletion page. Time records must be kept for the period required by Brazilian law (Portaria MTP
671/2021) and are retained for that period.
