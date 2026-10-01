# oimpresso Ponto — Data Safety (Google) e App Privacy (Apple) · RASCUNHO

> Baseado no código em main (2026-10-01): `MobileMarcacaoController@registrar` recebe `lat`, `lng`, `accuracy`,
> `device_uuid`, `timestamp_device`; grava latitude/longitude e o identificador do aparelho; a precisão é usada para
> recusar sinal fraco e não é gravada (RUNBOOK-mobile §4). Sem câmera/biometria (ADR 0383).
> ⚠️ Confirmar com a sessão do APP CAPACITOR: há SDK de crash/analytics/push (Firebase)? Se houver, entra
> "Diagnóstico" e, com push, "Identificadores do dispositivo" já cobre o token. Confirmar com a sessão LEGAL o prazo de guarda.

## Dados coletados

| Dado | Por quê | Obrigatório? | Ligado à pessoa? | Compartilhado? |
|---|---|---|---|---|
| Localização **precisa** (lat/lng) | registrar onde a marcação foi feita | sim | sim | não (vai ao empregador, que é o controlador — não conta como terceiro) |
| ID do aparelho (`device_uuid` gerado pelo app) | impedir troca de aparelho / fraude | sim | sim | não |
| Nome, e-mail/usuário, matrícula | login e identificação no espelho | sim (criado pela empresa) | sim | não |
| Registros de ponto (horários) | finalidade principal | sim | sim | não |
| Texto da justificativa | pedido ao gestor | não (opcional) | sim | não |

Coleta só **com o app aberto, no momento da batida** — sem localização em segundo plano.

## Google Play — Data Safety (tela a tela)
1. **Coleta ou compartilha dados?** Sim.
2. **Criptografado em trânsito?** Sim (HTTPS). **Usuário pode pedir exclusão?** Sim — link da página de exclusão (sessão PRIVACIDADE). Observação a declarar: registros de ponto são mantidos pelo prazo legal (Portaria MTP 671/2021).
3. **Tipos de dados** — marcar:
   - Localização → **Localização precisa** — Coletado · NÃO compartilhado · Obrigatório · Finalidade: **Funcionalidade do app** (e **Prevenção de fraude, segurança e conformidade**).
   - Informações pessoais → **Nome**, **Endereço de e-mail**, **IDs de usuário** — Coletado · Obrigatório · Funcionalidade do app + Gerenciamento de conta.
   - Identificadores do dispositivo ou outros → **ID do dispositivo** — Coletado · Obrigatório · Prevenção de fraude, segurança e conformidade.
   - Atividade no app → **Outro conteúdo gerado pelo usuário** (justificativa) — Coletado · Opcional · Funcionalidade do app.
   - **Não** marcar: fotos, áudio, contatos, financeiro, saúde, mensagens, histórico web, publicidade.
4. **Processamento efêmero?** Não (é gravado).
5. Política de privacidade: URL da sessão PRIVACIDADE.
6. **Permissão de localização em segundo plano:** NÃO pedir (`ACCESS_BACKGROUND_LOCATION` fora do manifest) — assim não cai na declaração de localização em segundo plano.

## Apple — App Privacy (tela a tela)
1. **Coleta dados?** Sim.
2. Tipos:
   - **Location → Precise Location**: App Functionality · **Linked to the user** · **Not used for tracking**.
   - **Identifiers → Device ID**: App Functionality · Linked · Not tracking.
   - **Contact Info → Name, Email Address**: App Functionality · Linked · Not tracking.
   - **User Content → Other User Content** (justificativa): App Functionality · Linked · Not tracking.
3. **Tracking (ATT)?** Não — nenhum SDK de publicidade, sem prompt ATT.
4. `Info.plist`: só `NSLocationWhenInUseUsageDescription` = "Usamos a localização somente quando você bate o ponto, para registrar onde a marcação foi feita." Sem `NSLocationAlways…`, sem `NSCameraUsageDescription`.

## Texto do pedido de permissão (Android e iOS — mesmo texto)
"O oimpresso Ponto usa a localização somente no momento em que você bate o ponto, para registrar onde a marcação foi feita."
