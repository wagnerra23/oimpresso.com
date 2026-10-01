---
slug: 0422-arquivos-aviso-titular-canal-email-whatsapp
number: 422
title: "Arquivos — aviso ao titular sai por e-mail e WhatsApp, com liga/desliga por negócio (emenda da 0421)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-01"
module: null
tags: [arquivos, lgpd, retencao, aviso-titular, whatsapp, email, playbook]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0421-arquivos-aviso-ao-titular-registro-e-janela
  - 0123-modules-arquivos-backbone
  - 0093-multi-tenant-isolation-tier-0
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
pii: false
---

# ADR 0422 — Arquivos: o canal do aviso ao titular é e-mail e WhatsApp, configurável por negócio

## Contexto

A ADR 0421 entregou só o **registro** do aviso ao titular (`titular_avisado_at` + linha `notice`
na trilha) e deixou o canal como pendência [W] (§Pendências: *"Canal do aviso (e-mail, WhatsApp,
Notification de tela) e quem dispara"*). Ela é append-only; esta ADR fecha a primeira metade da
pendência sem reescrevê-la.

**Fonte da decisão:** [W] 2026-10-01, 2ª rodada de decisões do playbook Arquivos, textual
*"e-mail e whatsapp. pode ter configuração"* — registrada em
`prototipo-ui/cowork/Wagner/cowork-inbox/arquivos/playbook/_DECISOES-W-2026-10-01b.md`
(PR #8394).

O que já existia e foi reusado (nada inventado):

- opt-in LGPD por contato: `App\Contact::canReceiveEmailNotification()` e
  `canReceiveWhatsappNotification()` (NULL/TRUE permitem, FALSE bloqueia);
- envio WhatsApp: `Modules\Whatsapp\Jobs\SendWhatsappMessageJob` + número
  `handles_outbound_default` do negócio — o mesmo caminho do `NotificarClienteCancelamentoJob`;
- liga/desliga por negócio: `business.common_settings` (JSON), que o núcleo já usa para
  `enable_purchase_order`, `enable_lot_number` e outros.

## Decisão

1. **Canais:** e-mail e WhatsApp. Os dois podem estar ligados ao mesmo tempo; cada um é tentado
   de forma independente.
2. **Configuração por negócio** em `business.common_settings['arquivos_aviso_titular']`
   = `{email: bool, whatsapp: bool}`, lida/gravada por `AvisoTitularCanais`. **Default: os dois
   desligados** — nenhum titular recebe nada até o negócio ligar o canal.
3. **Opt-in LGPD obrigatório:** cada canal só sai se o consentimento daquele canal não for
   FALSE no contato. Opt-out de um canal não bloqueia o outro.
4. **Envio antes do registro:** `AvisarTitularJob($businessId, $arquivoId)` re-checa a janela,
   envia e só então chama `AvisoTitularService::registrarAviso()` com os canais que saíram
   (`"email"`, `"whatsapp"` ou `"email,whatsapp"`). Nenhum canal saiu → nada é registrado.
   "Saiu" = aceito pelo provedor (SMTP síncrono; `SendWhatsappMessageJob` em `dispatchSync`, que
   lança se o provedor recusar). Não é confirmação de leitura.
5. **Conteúdo sem dado do documento:** a mensagem diz só que existe documento com dados do
   titular e quando ele vence — sem nome de arquivo, caminho ou conteúdo.
6. **Multi-tenant (ADR 0093):** `$businessId` no construtor do job; o titular (`Contact`) tem de
   ser do mesmo business do arquivo, senão o job aborta sem enviar.
7. **Quem dispara:** por ora, manual — `php artisan arquivos:avisar-titulares {business}`
   (com `--dry-run`, `--canais`, `--email=on|off`, `--whatsapp=on|off`). Agendar no `Kernel` fica
   para decisão [W]; o default desligado torna o agendamento inofensivo quando vier.

## Justificativa

É o desenho mais curto que cumpre a palavra do [W] reaproveitando o que o ERP já tem para
avisar cliente. O default desligado e o "registra só depois de sair" preservam a garantia
central da 0421: a trilha não afirma aviso que não aconteceu.

Reabrir se: o negócio precisar de tela para ligar/desligar (hoje é o comando); o WhatsApp
oficial (Meta Cloud) exigir template aprovado fora da janela de 24h — o envio é `freeform`, como
no aviso de cancelamento; ou o aviso precisar de agendamento automático.

## Consequências

**Positivas:** o titular passa a poder ser avisado de fato; a prova continua append-only; cada
negócio escolhe os canais.

**Negativas / Trade-offs:** sem tela de configuração (só o comando); `freeform` pode ser recusado
pela Meta fora da janela de conversa — nesse caso o canal conta como "não saiu" e o arquivo segue
na janela para nova tentativa.

**Riscos mitigados:** envio em massa por deploy (default desligado); envio a quem recusou
(opt-in por canal); envio a titular de outro tenant; dado do documento na mensagem.

## Pendências [W]

- Agendar o `arquivos:avisar-titulares` (e a cadência) ou manter manual.
- Tela de configuração dos canais no admin de Arquivos.

## Referências

- ADR 0421 — registro + janela (esta emenda fecha o canal)
- ADR 0093 — Multi-tenant Tier 0 · ADR 0358 — tenant 98 em teste
- `Modules/Arquivos/Jobs/AvisarTitularJob.php`
- `Modules/Arquivos/Services/AvisoTitularCanais.php`
- `Modules/Arquivos/Mail/AvisoTitularMail.php`
- `Modules/Arquivos/Console/Commands/AvisarTitularesCommand.php`
- `Modules/Arquivos/Tests/Feature/AvisarTitularJobTest.php`
