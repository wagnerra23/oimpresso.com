---
id: modules-associacoes-pages-associacoes-public-membro-charter
page: /associacoes/m/{token}
component: Modules/Associacoes/Resources/js/Pages/Associacoes/Public/Membro.tsx
related_prototype: "n/a (sem protótipo Cowork; gerado ancorado no Design System e no visual do portal do paciente do Fisio, Modules/Fisio/Resources/js/Pages/Fisio/Public/Portal.tsx, que roda no mesmo app)"
related_us: [US-ASSOC-005]
owner: wagner
status: draft
parent_module: Associacoes
related_adrs: [93, 358, 451]
alcance:
  rota: /associacoes/m/{token}
  rota_nome: associacoes.membro.show
  permission: n/a (pública, sem login — link + PIN gerados pela associação)
  pacote: associacoes_module
tier: B
charter_version: 1
---

# Page Charter — /associacoes/m/{token} (DRAFT · área da associada no app)

> Nasce na US-ASSOC-005 (ADR 0451 D6, onda 6). Casos: [`Membro.casos.md`](Membro.casos.md).
> Padrão copiado: portal do paciente do Fisio (`/fisio/p/{token}`, US-FISIO-007a). Non-Goals e Anti-hooks além dos abaixo são de [W].

## Mission

Deixar a associada ver, pelo link que a associação mandou e com o PIN que veio junto, o próprio cadastro e as próprias autorizações, e retirar as que quiser.

## Goals — Features (faz)

- Link válido pede o PIN; nenhum dado antes dele — UC-AMB-01.
- Link de outra empresa, adulterado, vencido, revogado ou substituído: o mesmo estado vazio — UC-AMB-02.
- PIN de 4 dígitos, 5 tentativas, trava de 30 min — UC-AMB-03.
- "Meus dados": nome, associação, situação e data de entrada — UC-AMB-04.
- Autorizações por finalidade, com "Retirar autorização"; retirar grava a data e não apaga — UC-AMB-05.
- A associação gera e revoga o link pelo sistema — UC-AMB-06.

## Non-Goals (NÃO faz)

- ❌ Editar o próprio cadastro: dado errado se corrige com a associação.
- ❌ Retirar a autorização `associada` (ser associada): só a associação encerra a filiação (ADR 0451 D5).
- ❌ Mostrar CPF, diagnóstico, laudo ou CID: não existem neste módulo (D5).

## Automation Anti-hooks

- ❌ Nada de dado da associada na resposta antes do PIN aceito.
- ❌ O business nunca vem de parâmetro da rota: vem assinado no token.
