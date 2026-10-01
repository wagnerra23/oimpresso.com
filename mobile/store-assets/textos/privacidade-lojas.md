# oimpresso — Data Safety (Google) e App Privacy (Apple) · RASCUNHO

> App único: ERP no celular + ponto ([W] 2026-10-01). Levantado do código em main (2026-10-01):
> - Ponto (`MobileMarcacaoController@registrar`): `lat`, `lng`, `accuracy` (não gravada), `device_uuid`, `timestamp_device`.
> - App Expo: login (`expo-secure-store`), push (`expo-notifications`, token do aparelho), fotos da galeria em OS
>   (`expo-image-picker` em `app/oss/[id].tsx`), cadastro de clientes (nome, CPF/CNPJ, contato).
> - `expo-audio` e `expo-video` estão no `package.json`/`app.config.ts` mas **nenhuma tela usa** — remover antes do build
>   (a string de microfone em inglês é motivo de rejeição Apple 5.1.1 e obrigaria declarar "Áudio").
> - ⚠️ Confirmar no build: há SDK de crash/analytics? Se sim, entra "Diagnóstico". Prazo de guarda: sessão LEGAL.

## O que entra

| Dado | Onde | Obrigatório? | Finalidade |
|---|---|---|---|
| Localização precisa | ponto, só na batida | sim (para bater ponto) | funcionalidade; prevenção de fraude |
| ID do aparelho (`device_uuid`) + token de push | ponto; notificações | sim | prevenção de fraude; funcionalidade |
| Nome, e-mail, ID de usuário | login | sim | funcionalidade; gerenciamento de conta |
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
