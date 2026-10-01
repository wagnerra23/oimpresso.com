# Notas para o revisor das lojas (App Store / Google Play)

> Texto para colar em **App Store Connect → App Review Information → Notes** e em
> **Google Play Console → Acesso ao app**. A conta é criada por
> `php artisan ponto:demo-revisor` num business de demonstração isolado (nunca um cliente real).
> **As senhas não estão aqui:** ficam no Vaultwarden, itens `ponto-demo-revisor` e `ponto-demo-gestor`.
> Quem preenche a loja copia de lá. Duas contas ([W] 2026-10-01 "ok duas contas, colaborador e gestor").
>
> Decisão [W] 2026-10-01: o ponto abre no app como webview de `/ponto/mobile`.

---

## Português

**Duas contas de demonstração**
- **Colaborador** — usuário `revisor.ponto`: abre direto no ponto. É a conta para testar o registro de ponto.
- **Gestor** — usuário `gestor.demo`: vê o ERP da empresa de demonstração (todos os dados são fictícios).
- As senhas estão informadas no campo de credenciais da revisão.

**O que testar**
1. Abra o app e entre com a conta **Colaborador** (`revisor.ponto`).
2. Na tela **Bater ponto**, permita a localização quando o sistema pedir.
3. Escolha o tipo (Entrada, Saída almoço, Retorno ou Saída) e toque em **Bater ponto**.
4. A marcação aparece em **Hoje**, com o número sequencial (NSR) dado pelo servidor.
5. Em **Meu espelho** você vê o mês; em **Justificar** pode enviar uma justificativa, que fica pendente para o gestor.

**Por que o app pede localização**
O registro de ponto eletrônico (Portaria MTP 671/2021, REP-P) guarda onde a marcação foi feita, para o empregador conferir. A localização é lida uma única vez, quando a tela de bater ponto é aberta (ou quando o colaborador toca em **Atualizar local**); não há rastreamento contínuo. Se a precisão do GPS for muito baixa (acima de 500 m), o registro é recusado e o app pede para ir a uma área aberta. O app não usa câmera nem biometria.

**Você está fora do Brasil?**
Pode registrar normalmente. Marcações longe do local da empresa não são recusadas; no máximo ficam sinalizadas para conferência do gestor.

**Por que não há cadastro dentro do app**
O app é de uso interno de empresas clientes do Oimpresso. Quem cria a conta do funcionário é o empregador, no sistema da empresa. Por isso não existe tela de criação de conta no app.

**Exclusão de conta**
O funcionário pede a exclusão ao empregador ou pela página pública de exclusão de dados do Oimpresso. Registros de ponto têm guarda obrigatória por lei e não podem ser apagados antes do prazo legal.

---

## English

**Two demo accounts**
- **Employee** — username `revisor.ponto`: opens straight into time clock. Use it to test clocking in.
- **Manager** — username `gestor.demo`: sees the demo company's ERP (all data is fictitious).
- Passwords are provided in the review credentials field.

**What to test**
1. Open the app and sign in with the **Employee** account (`revisor.ponto`).
2. On the **Bater ponto** (clock in) screen, allow location access when prompted.
3. Choose the punch type (Entrada = clock in, Saída almoço = lunch out, Retorno = back from lunch, Saída = clock out) and tap **Bater ponto**.
4. The punch shows under **Hoje** (Today), with the sequence number (NSR) issued by the server.
5. **Meu espelho** shows the monthly timesheet; **Justificar** lets you submit a justification, which stays pending for the manager.

**Why the app asks for location**
Brazilian electronic time-tracking rules (Portaria MTP 671/2021, REP-P) require recording where each punch was made, so the employer can review it. Location is read once, when the clock-in screen opens (or when the employee taps **Atualizar local**, refresh location); there is no continuous tracking. If GPS accuracy is too poor (worse than 500 m), the punch is refused and the app asks the user to move to an open area. The app uses no camera and no biometrics.

**Testing from outside Brazil?**
You can punch normally. Punches far from the company location are not rejected; at most they are flagged for the manager to review.

**Why there is no sign-up in the app**
This is an internal app for companies that use Oimpresso. Employee accounts are created by the employer in the company's system, so the app has no sign-up screen.

**Account deletion**
Employees request deletion from their employer or through Oimpresso's public data-deletion page. Time records must be kept for the period required by Brazilian law and cannot be deleted before it ends.
