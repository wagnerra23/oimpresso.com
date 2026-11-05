---
id: modules-fisio-pages-fisio-configuracoes-whatsapp-charter
page: /fisio/configuracoes/whatsapp
component: Modules/Fisio/Resources/js/Pages/Fisio/Configuracoes/Whatsapp.tsx
related_prototype: prototipo-ui/cowork/Wagner/prototipos/fisio/ConfigWhatsApp.dc.html
owner: wagner
status: draft
last_validated: "2026-11-04"
parent_module: Fisio
related_adrs: [93, 437, 438]
alcance:
  rota: /fisio/configuracoes/whatsapp
  rota_nome: fisio.configuracoes.whatsapp
  permission: fisio.access
  menu_hook: "n/a — sem item de menu (Lote 5 não mexe no menu); acesso pela URL e, depois, pelo Onboarding"
  pacote: fisio_module
tier: B
charter_version: 1
---

# Page Charter — /fisio/configuracoes/whatsapp (DRAFT · canal do WhatsApp da clínica)

> Lote 5 do canvas Prescreve Pro (ADR 0438: o protótipo no git é a fonte). Casos: [`Whatsapp.casos.md`](Whatsapp.casos.md).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

A clínica escolhe por onde o Fisio manda o link do portal, o programa e o lembrete diário: pelo próprio WhatsApp do profissional (`wa.me`), pelo número oficial da Meta ou pelo número conectado por QR, sabendo o risco.

## Goals — Features (faz)

- Três canais em cartões de escolha única (`proprio`, `oficial`, `qr`) com o selo do protótipo — UC-FCW-01.
- A escolha muda o envio real: `proprio` desliga o automático; `oficial` só envia se o número do módulo for `meta_cloud`; `qr` só com aceite e número não oficial — UC-FCW-02, UC-FCW-04.
- QR exige marcar o aviso de risco do protótipo; o aceite fica gravado com quem, quando e a versão do termo — UC-FCW-03.
- Máximo por hora e horário de silêncio da clínica, com limites no servidor — UC-FCW-02, UC-FCW-05.
- Estado do número do módulo `Whatsapp` (só o final, oficial ou não, saúde) e link para conectá-lo lá — UC-FCW-01.
- Só a empresa da sessão — UC-FCW-06.

## Non-Goals — Features (NÃO faz)

- ❌ Conectar número, ler QR, cadastrar modelo da Meta ou trocar driver: é do módulo `Whatsapp` (US-FISIO-009). O protótipo desenha QR e modelos dentro desta tela; aqui viram o link para o módulo.
- ❌ Horário do lembrete por clínica: o agendamento é um só (`fisio.whatsapp.lembrete_horario`); a tela mostra o horário, não edita.
- ❌ Confirmação de consulta: é da Agenda (Lote 3).

## Refs

- ADR 0437 §13.1 · SPEC US-FISIO-009 · `Modules/Fisio/Services/Whatsapp/CanalDaClinica.php` · PT-02 Form · UI-0013
