# oimpresso — Data Safety (Google) e App Privacy (Apple) · 1ª SUBMISSÃO · RASCUNHO

> ⛔ **NÃO USAR NA SUBMISSÃO (D13, [W] 2026-10-01):** a v1 voltou às 7 áreas (Início, Tarefas, Pedidos, Produção,
> Pessoas, Ponto, Mais) e a submissão só acontece quando elas existirem no app. Este arquivo "só Ponto" fica guardado
> como histórico. O texto a usar será o de `privacidade-lojas-completa.md`, revisado quando as telas ficarem prontas.


> **Só o que o app coleta hoje** (Login, Início, Ponto, Conta + lembrete por push — sessão que gerencia o app,
> 2026-10-01). Telas próprias no `oimpresso-app` (React empacotado + API Passport do ERP). Quando Tarefas, Pedidos,
> Produção e Pessoas entrarem, use [`privacidade-lojas-completa.md`](privacidade-lojas-completa.md) e atualize o console
> **antes** de enviar a versão nova.
>
> Medido em 2026-10-01: ponto (`MobileMarcacaoController@registrar`) recebe `lat`, `lng`, `accuracy` (não gravada),
> `device_uuid`, `timestamp_device`. App Capacitor ([`wagnerra23/oimpresso-app`](https://github.com/wagnerra23/oimpresso-app),
> via sessão ANDROID): **sem** SDK de crash ou analytics; push só por FCM (token = identificador do aparelho); localização
> **só em uso**, sem background; **sem** câmera e sem microfone. Prazo de guarda: sessão LEGAL.

## Google Play — Data Safety (tela a tela)
1. **Coleta ou compartilha dados?** Sim.
2. **Criptografado em trânsito?** Sim (HTTPS). **Pedido de exclusão?** Sim — `https://oimpresso.com/privacidade/ponto/exclusao`; declarar que registros de ponto são mantidos pelo prazo legal.
3. **Tipos (todos: Coletado · NÃO compartilhado · não efêmero):**
   - Localização → **Localização precisa** — Obrigatório · Funcionalidade do app; Prevenção de fraude, segurança e conformidade.
   - Informações pessoais → **Nome**, **Endereço de e-mail**, **IDs de usuário** — Obrigatório · Funcionalidade; Gerenciamento de conta.
   - Atividade no app → **Outro conteúdo gerado pelo usuário** (justificativas) — Opcional · Funcionalidade.
   - Identificadores do dispositivo ou outros → **ID do dispositivo** (identificador do aparelho + token de push) — Obrigatório · Prevenção de fraude; Funcionalidade.
   - **Não** marcar: fotos, telefone, endereço, informações financeiras, contatos, mensagens, diagnóstico, analytics.
4. **Permissões:** `ACCESS_FINE_LOCATION` e `ACCESS_COARSE_LOCATION` (sim), `ACCESS_BACKGROUND_LOCATION` (**não**), `POST_NOTIFICATIONS` (sim), sem `CAMERA` e sem `RECORD_AUDIO`.

## Apple — App Privacy (tela a tela)
Todos: **Linked to the user** · **Not used for tracking** · finalidade **App Functionality**.
- Location → **Precise Location**
- Contact Info → **Name**, **Email Address**
- Identifiers → **User ID**, **Device ID**
- User Content → **Other User Content** (justificativas)
- **Tracking (ATT):** não. Sem prompt ATT.

`Info.plist` (projeto `oimpresso-app`):
- `NSLocationWhenInUseUsageDescription`: "O oimpresso usa a localização somente quando você bate o ponto, para registrar onde a marcação foi feita."
- Sem `NSPhotoLibraryUsageDescription`, `NSCameraUsageDescription` e `NSMicrophoneUsageDescription` nesta versão.
