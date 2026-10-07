# oimpresso — Data Safety e App Privacy · VERSÃO COMPLETA (futura) · RASCUNHO

> ⏸️ **Não usar na 1ª submissão** — declara dados de telas que o app ainda não tem. Até lá use
> [`privacidade-lojas.md`](privacidade-lojas.md).

> App único (decisões em [`../DECISOES.md`](../DECISOES.md)): telas próprias do app (visual do protótipo Mobile) sobre o mesmo backend do ERP, empacotado com **Capacitor** (`com.oimpresso.app`) — decisão [W]
> 2026-10-01 na coordenação; o Expo de `mobile/` fica fora das lojas. Levantado do código em main (2026-10-01):
> - Ponto (`MobileMarcacaoController@registrar`): `lat`, `lng`, `accuracy` (não gravada), `device_uuid`, `timestamp_device`.
> - ERP web: login do oimpresso (sessão), cadastro de clientes (nome, CPF/CNPJ, contato), anexos de OS escolhidos pelo usuário.
> - App Capacitor medido em 2026-10-01 (repo [`wagnerra23/oimpresso-app`](https://github.com/wagnerra23/oimpresso-app), antes `oimpresso-ponto-app`, package.json + build.gradle, via sessão ANDROID):
>   **sem** Crashlytics/Analytics nem outro SDK de crash ou analytics; push só por FCM (`@capacitor/push-notifications`,
>   token = identificador do aparelho). Permissões: INTERNET, localização aproximada e precisa **só em uso**
>   (sem BACKGROUND), POST_NOTIFICATIONS. Sem CAMERA e sem microfone. Fotos de OS chegam pelo seletor de arquivos do
>   sistema dentro do ERP web (sem permissão de galeria).
> - Prazo de guarda: sessão LEGAL.

## O que entra

| Dado | Onde | Obrigatório? | Finalidade |
|---|---|---|---|
| Localização precisa | ponto, só na batida | sim (para bater ponto) | funcionalidade; prevenção de fraude |
| ID do aparelho (`device_uuid`) + token de push | ponto; notificações | sim | prevenção de fraude; funcionalidade |
| Nome, e-mail, ID de usuário | login | sim | funcionalidade; gerenciamento de conta |
| Registros de falhas (diagnóstico): modelo do aparelho, versão do sistema e do app, onde o erro aconteceu | qualquer tela, quando o app fecha ou dá erro | sim | análise de falhas — servidor próprio (GlitchTip no CT 100, ADR 0429), apagados após 90 dias |
| Dados de clientes da empresa (nome, CPF/CNPJ, telefone, e-mail, endereço) | cadastro de clientes | não (o usuário digita) | funcionalidade |
| Fotos | anexos de OS, escolhidas pelo usuário | não | funcionalidade |
| Registros de ponto, justificativas, notas de serviço, tarefas | ponto; OS; tarefas | varia | funcionalidade |
| Transações da empresa (vendas, pagamentos) | vendas, financeiro | — | dado da empresa, não do usuário — ver nota abaixo |

Tudo é ligado à pessoa, nada é vendido, usado para publicidade ou rastreamento. Os dados vão para o servidor do
oimpresso (operador) por conta da empresa cliente (controladora). Sem localização em segundo plano.

## Google Play — Data Safety (tela a tela)
1. **Coleta ou compartilha dados?** Sim.
2. **Criptografado em trânsito?** Sim (HTTPS). **Pedido de exclusão?** Sim — URL da página de exclusão (sessão PRIVACIDADE); declarar que registros de ponto e fiscais são mantidos pelo prazo legal.
3. **Tipos (todos: Coletado · NÃO compartilhado · não efêmero):**
   - Localização → **Localização precisa** — Obrigatório · Funcionalidade do app; Prevenção de fraude, segurança e conformidade.
   - Informações pessoais → **Nome**, **Endereço de e-mail**, **IDs de usuário** — Obrigatório · Funcionalidade; Gerenciamento de conta.
   - Informações pessoais → **Número de telefone**, **Endereço**, **Outras informações** (CPF/CNPJ) — Opcional · Funcionalidade (dados dos clientes cadastrados).
   - Fotos e vídeos → **Fotos** — Opcional · Funcionalidade.
   - Atividade no app → **Outro conteúdo gerado pelo usuário** (justificativas, notas de OS, tarefas) — Opcional · Funcionalidade.
   - Identificadores do dispositivo ou outros → **ID do dispositivo** — Obrigatório · Prevenção de fraude; Funcionalidade (push).
   - Informações e desempenho do app → **Registros de falhas** e **Outros dados de diagnóstico** — Obrigatório · Análise · não compartilhado (servidor próprio, ADR 0429; desde 2026-10-07).
   - Informações financeiras → ⚠️ decidir com a sessão LEGAL: vendas e pagamentos são da **empresa**, não do usuário; "Histórico de compras" da Google é do usuário. Recomendação: não marcar, e explicar na política de privacidade.
4. **Permissões:** `ACCESS_FINE_LOCATION` (sim), `ACCESS_BACKGROUND_LOCATION` (**não**), `POST_NOTIFICATIONS` (sim), `RECORD_AUDIO` (**remover**), `CAMERA` (não usado).

## Apple — App Privacy (tela a tela)
Todos: **Linked to the user** · **Not used for tracking** · finalidade **App Functionality**.
- Location → **Precise Location**
- Contact Info → **Name**, **Email Address**, **Phone Number**, **Physical Address**
- Identifiers → **User ID**, **Device ID**
- User Content → **Photos or Videos**, **Other User Content**
- Sensitive Info → não marcar (CPF/CNPJ não é "sensível" no sentido Apple: saúde, religião etc.).
- **Tracking (ATT):** não. Sem prompt ATT.

`Info.plist` (via `app.config.ts`, sessão iOS):
- `NSLocationWhenInUseUsageDescription`: "O oimpresso usa a localização somente quando você bate o ponto, para registrar onde a marcação foi feita."
- `NSPhotoLibraryUsageDescription`: "O oimpresso acessa as suas fotos somente quando você escolhe uma imagem para anexar a uma ordem de serviço."
- **Remover** `NSMicrophoneUsageDescription` (expo-audio sem uso).
