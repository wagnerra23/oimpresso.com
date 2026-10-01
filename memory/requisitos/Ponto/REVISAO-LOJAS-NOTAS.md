# Notas para o revisor das lojas (App Store / Google Play)

> Texto para colar em **App Store Connect → App Review Information → Notes** e em
> **Google Play Console → Acesso ao app**.
>
> - **App:** telas próprias do app (repo `oimpresso-app`), falando com o ERP pela API Passport
>   (`/oauth/token` + `/ponto/api/*`). Decisão [W] 2026-10-01 ("telas próprias + API Passport");
>   registro em `docs/lojas-app/DECISOES.md`. Esta versão substitui a das notas de webview.
> - **Nomes das telas e botões:** os do app v4 (`oimpresso-app` PR #6), medidos no emulador pela
>   sessão do app em 2026-10-01. Se o app mudar um rótulo, mude aqui também.
> - **Conta:** `revisor.ponto`, colaborador do business demo 235 ("Demo Ponto — revisão das lojas"),
>   criada por `php artisan ponto:demo-revisor`. Empresa de demonstração isolada; nenhum cliente
>   real. A conta `gestor.demo` **não** entra na revisão da v1 (não há tela de gestor no app).
> - **Senha:** não está aqui. Fica no Vaultwarden, item `ponto-demo-revisor`; quem preenche a loja
>   copia de lá.
> - **Antes de enviar para revisão:** `php artisan ponto:demo-smoke --sem-marcar` (confere a conta
>   pela API, como o app) e `php artisan ponto:demo-dados` (deixa os dados de "hoje" com a data do
>   dia). O login real pelo app depende do client OAuth público do app existir no ERP.

---

## Português

**Conta de demonstração**
- Usuário: `revisor.ponto`
- Senha: informada no campo de credenciais da revisão

**O que testar**
1. Abra o app. Em **Usuário ou e-mail** digite `revisor.ponto`, preencha a **Senha** e toque em
   **Entrar**.
2. **Início:** o cartão **Próxima marcação** tem o atalho **Bater ›**.
3. **Ponto → Bater ponto:** ao abrir a aba **Ponto**, o app pede a localização (somente durante o
   uso). Escolha o tipo (**Entrada**, **Saída almoço**, **Retorno almoço** ou **Saída**) e toque em
   **Bater ponto — Entrada** (o texto acompanha o tipo escolhido). Aparece o **Comprovante de
   marcação** com o NSR, o hash e o local. Se o sinal estiver fraco, toque em **Atualizar local**.
4. **Ponto → Meu espelho:** mostra as marcações do mês.
5. **Ponto → Justificar:** escreva uma justificativa (por exemplo, "esqueci de bater a saída") e
   toque em **Enviar para aprovação**. Ela fica pendente para o gestor.
6. **Conta:** **Ativar lembrete**, **Política de privacidade**, **Excluir minha conta** e **Sair**.

**Por que o app pede localização**
O registro de ponto eletrônico (Portaria MTP 671/2021, REP-P) guarda onde a marcação foi feita,
para o empregador conferir. O app pede localização somente durante o uso, ao abrir a aba **Ponto**,
para mostrar a precisão do GPS antes de bater; a localização é registrada apenas no momento da
marcação. Não há permissão de localização em segundo plano. O login não pede localização. Se a
precisão do GPS for muito baixa (acima de 500 m), o registro é recusado e o app pede para ir a uma
área aberta. O app não usa câmera nem biometria.

**Você está fora do Brasil?**
Pode registrar normalmente. Marcações longe do local da empresa não são recusadas; no máximo ficam
sinalizadas para conferência do gestor.

**Por que não há cadastro dentro do app**
O app é de uso interno de empresas clientes do Oimpresso. Quem cria a conta do funcionário é o
empregador, no sistema da empresa. Por isso não existe tela de criação de conta no app.

**Exclusão de conta**
Pelo próprio app, em **Conta → Excluir minha conta**, ou pela página pública de exclusão de dados
do Oimpresso. Registros de ponto têm guarda obrigatória por lei (Portaria MTP 671/2021) e são
mantidos pelo prazo legal.

---

## English

**Demo account**
- Username: `revisor.ponto`
- Password: provided in the review credentials field

**What to test**
1. Open the app. In **Usuário ou e-mail** (username or e-mail) type `revisor.ponto`, fill in
   **Senha** (password) and tap **Entrar** (sign in).
2. **Início (Home):** the **Próxima marcação** (next punch) card has a **Bater ›** (clock in)
   shortcut.
3. **Ponto → Bater ponto (Time clock → Clock in):** when the **Ponto** tab opens, the app asks for
   location (while using the app only). Choose the punch type (**Entrada** = clock in,
   **Saída almoço** = lunch out, **Retorno almoço** = back from lunch, **Saída** = clock out) and tap
   **Bater ponto — Entrada** (the label follows the chosen type). A **Comprovante de marcação**
   (punch receipt) appears with the NSR sequence number, hash and location. If the GPS signal is
   weak, tap **Atualizar local** (refresh location).
4. **Ponto → Meu espelho (My timesheet):** shows this month's punches.
5. **Ponto → Justificar (Justify):** write a justification (for example, "forgot to clock out") and
   tap **Enviar para aprovação** (send for approval). It stays pending for the manager.
6. **Conta (Account):** **Ativar lembrete** (turn on reminder), **Política de privacidade**
   (privacy policy), **Excluir minha conta** (delete my account) and **Sair** (sign out).

**Why the app asks for location**
Brazilian electronic time-tracking rules (Portaria MTP 671/2021, REP-P) require recording where
each punch was made, so the employer can review it. The app asks for location only while in use,
when the **Ponto** tab opens, to show GPS accuracy before clocking in; location is recorded only at
the moment of the punch. There is no background location permission. Sign-in does not ask for
location. If GPS accuracy is too poor (worse than 500 m), the punch is refused and the app asks the
user to move to an open area. The app uses no camera and no biometrics.

**Testing from outside Brazil?**
You can punch normally. Punches far from the company location are not rejected; at most they are
flagged for the manager to review.

**Why there is no sign-up in the app**
This is an internal app for companies that use Oimpresso. Employee accounts are created by the
employer in the company's system, so the app has no sign-up screen.

**Account deletion**
In the app, under **Conta → Excluir minha conta**, or through Oimpresso's public data-deletion
page. Time records must be kept for the period required by Brazilian law (Portaria MTP 671/2021)
and are retained for that period.
