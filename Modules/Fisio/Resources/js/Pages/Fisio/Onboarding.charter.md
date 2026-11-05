---
id: modules-fisio-pages-fisio-onboarding-charter
page: /fisio/onboarding
component: Modules/Fisio/Resources/js/Pages/Fisio/Onboarding.tsx
related_prototype: prototipo-ui/cowork/Wagner/prototipos/fisio/Onboarding.dc.html
owner: wagner
status: draft
last_validated: "2026-11-04"
parent_module: Fisio
related_adrs: [93, 437, 438]
alcance:
  rota: /fisio/onboarding
  rota_nome: fisio.onboarding
  permission: fisio.access
  menu_hook: "n/a — sem item de menu (Lote 5 não mexe no menu)"
  pacote: fisio_module
tier: B
charter_version: 1
---

# Page Charter — /fisio/onboarding (DRAFT · primeira configuração da clínica)

> Lote 5 do canvas Prescreve Pro (ADR 0438). US-FISIO-050. Casos: [`Onboarding.casos.md`](Onboarding.casos.md).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Levar a clínica do zero ao primeiro paciente com o link no celular: perfil, forma de atendimento, canal do WhatsApp, protocolos e o cadastro do primeiro paciente.

## Goals — Features (faz)

- Cinco passos com navegação livre entre eles; cada "Continuar" grava só o passo atual — UC-FOB-02..05.
- Perfil por profissional (nome que o paciente vê e CREFITO) — UC-FOB-02.
- Forma de atendimento PF/PJ da clínica — UC-FOB-02.
- Canal do WhatsApp com a regra de Configurações › WhatsApp: QR só com o aviso de risco aceito — UC-FOB-03.
- Protocolos marcados entre os que a clínica enxerga; id de outra empresa é descartado — UC-FOB-01, UC-FOB-04.
- Conclusão registrada; o último passo leva a Pacientes — UC-FOB-05.
- Só a empresa da sessão — UC-FOB-06.

## Non-Goals — Features (NÃO faz)

- ❌ Foto do perfil: o protótipo tem "Enviar foto"; fica para depois.
- ❌ Montar recibo ou NFS-e: o passo 2 só guarda PF/PJ; o fiscal é do Lote 4. O texto do protótipo que promete "Receita Saúde em lote" e preço adicional não foi copiado, porque nada aqui faz isso.
- ❌ Reordenar protocolos por favorito: o protótipo diz "marcar deixa os seus no topo"; quem lista protocolos (Lote 2) ainda não lê `protocolos.favoritos`, então o texto da tela diz só que ficam guardados.
- ❌ Bloquear o módulo até concluir.

## Refs

- ADR 0437 · SPEC US-FISIO-050 · `Modules/Fisio/Http/Controllers/OnboardingController.php` · PT-02 Form · UI-0013
