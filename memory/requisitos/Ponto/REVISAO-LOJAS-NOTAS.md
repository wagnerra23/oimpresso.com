# Notas para o revisor das lojas (App Store / Google Play)

> Texto para colar em **App Store Connect → App Review Information → Notes** e em
> **Google Play Console → Acesso ao app**.
>
> - **App:** telas próprias do app (repo `oimpresso-app`), falando com o ERP pela API Passport
>   (`/oauth/token` + `/ponto/api/*`). Decisão [W] 2026-10-01; registro em `docs/lojas-app/DECISOES.md`.
>   Esta versão substitui a das notas de webview (`/ponto/mobile`).
> - **Conta:** `revisor.ponto`, colaborador do business demo 235 ("Demo Ponto — revisão das lojas"),
>   criada por `php artisan ponto:demo-revisor`. Empresa de demonstração isolada; nenhum cliente
>   real. A conta `gestor.demo` **não** entra na revisão da v1 (não há tela de gestor no app).
> - **Senha:** não está aqui. Fica no Vaultwarden, item `ponto-demo-revisor`; quem preenche a loja
>   copia de lá.
> - **Antes de enviar para revisão:** `php artisan ponto:demo-smoke --sem-marcar` (confere a conta
>   pela API, como o app) e `php artisan ponto:demo-dados` (deixa os dados de "hoje" com a data do dia).
> - **A conferir com a sessão do app antes de colar:** os nomes exatos das abas e botões, e se o app
>   pede localização só ao bater o ponto. O texto abaixo segue a lista da v1 passada pela
>   coordenação em 2026-10-01 (Início · Ponto: Bater, Meu espelho, Justificar · Conta: lembrete,
>   privacidade, Excluir minha conta).

---

## Português

**Conta de demonstração**
- Usuário: `revisor.ponto`
- Senha: informada no campo de credenciais da revisão

**O que testar**
1. Abra o app e entre com o usuário e a senha acima.
2. **Início:** tela inicial do colaborador, com o atalho para o ponto.
3. **Ponto → Bater:** permita a localização quando o sistema pedir, escolha o tipo (Entrada,
   Saída almoço, Retorno ou Saída) e registre. A marcação aparece em "Hoje" com o número
   sequencial (NSR) dado pelo servidor.
4. **Ponto → Meu espelho:** mostra as marcações do mês.
5. **Ponto → Justificar:** envie uma justificativa (por exemplo, "esqueci de bater a saída"). Ela
   fica pendente para o gestor.
6. **Conta:** lembrete de bater ponto, política de privacidade e **Excluir minha conta**.

**Por que o app pede localização**
O registro de ponto eletrônico (Portaria MTP 671/2021, REP-P) guarda onde a marcação foi feita,
para o empregador conferir. A localização é usada só para registrar a marcação. Se a precisão do
GPS for muito baixa (acima de 500 m), o registro é recusado e o app pede para ir a uma área aberta.
O app não usa câmera nem biometria.

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
1. Open the app and sign in with the username and password above.
2. **Início (Home):** the employee's home screen, with a shortcut to time clock.
3. **Ponto → Bater (Clock in):** allow location access when prompted, choose the punch type
   (Entrada = clock in, Saída almoço = lunch out, Retorno = back from lunch, Saída = clock out) and
   register it. The punch shows under "Hoje" (Today) with the sequence number (NSR) issued by the
   server.
4. **Ponto → Meu espelho (My timesheet):** shows this month's punches.
5. **Ponto → Justificar (Justify):** submit a justification (for example, "forgot to clock out").
   It stays pending for the manager.
6. **Conta (Account):** clock-in reminder, privacy policy and **Excluir minha conta** (Delete my
   account).

**Why the app asks for location**
Brazilian electronic time-tracking rules (Portaria MTP 671/2021, REP-P) require recording where
each punch was made, so the employer can review it. Location is used only to record the punch. If
GPS accuracy is too poor (worse than 500 m), the punch is refused and the app asks the user to move
to an open area. The app uses no camera and no biometrics.

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
