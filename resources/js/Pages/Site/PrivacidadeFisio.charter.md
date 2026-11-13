---
page: /fisio/privacidade
component: resources/js/Pages/Site/PrivacidadeFisio.tsx
owner: wagner
status: draft
parent_module: Site
related_us: [US-FISIO-007]
related_prototype: n/a (página pública de texto legal — bespoke; não segue um dos 5 Padrões de Tela)
runbook: memory/requisitos/Site/RUNBOOK-privacidade.md
alcance:
  rota: /fisio/privacidade
  rota_nome: publico.privacidade.fisio
  permission: n/a (pública, sem login — exigência do Google Play)
tier: B
charter_version: 1
---

# Page Charter — Site/PrivacidadeFisio (política do Portal do Paciente e do app Uniafibro)

> Casos em [`PrivacidadeFisio.casos.md`](PrivacidadeFisio.casos.md); plano em
> [RUNBOOK-privacidade](../../../../memory/requisitos/Site/RUNBOOK-privacidade.md) §5.
> Texto aprovado pelo [W] em 2026-11-13; revisão jurídica da Eliana [E] pendente.

## Mission

Dar ao Google Play e a quem usa o app Uniafibro (`com.oimpresso.fisio`) uma URL pública e estável
que diga o que o Portal do Paciente do Fisio trata, por quê, com quem compartilha, por quanto tempo
e quais são os direitos LGPD — fiel ao que o código de `Modules/Fisio` faz, inclusive dado de saúde.

## Goals — Features (faz)

- Abre sem login, no mesmo layout das páginas públicas (`DocumentoPublico`)
- Nomeia controladora (a clínica) e operador (oimpresso)
- Explica o acesso por link + PIN e o cookie de "lembrar aparelho"
- Declara a guarda de 20 anos do prontuário (Lei 13.787/2018) e que dado clínico não é apagado
- Declara a IA de transcrição como opcional, desligada por padrão, com conferência do profissional
- Declara que o app não faz teleconsulta por vídeo

## Non-Goals — Features (NÃO faz)

- ❌ NÃO lê banco, sessão ou dado de tenant — é só texto
- ❌ NÃO promete apagar dado clínico (proibido por lei)
- ❌ NÃO lista dado que o portal não coleta (nem omite o que coleta)
- ❌ NÃO usa o shell administrativo (AppShellV2/Sidebar)

## Automation Anti-hooks (NÃO faz)

- ❌ NÃO grava nada em GET
