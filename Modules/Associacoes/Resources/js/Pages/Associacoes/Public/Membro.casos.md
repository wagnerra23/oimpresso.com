---
id: modules-associacoes-pages-associacoes-public-membro-casos
casos: Associacoes · Área da associada no app · /associacoes/m/{token}
irmaos: Membro.charter.md (lei) · Membro.tsx (tela) · precedente Modules/Fisio/Resources/js/Pages/Fisio/Public/Portal.casos.md
tecnica: Caso de uso = narrativa da associada + critério de aceite verificável (Dado/Quando/Então)
por_que: a rota é pública na internet e a associada é pessoa com fibromialgia (dado sensível, LGPD art. 11). Sem PIN e trava é força bruta; sem token assinado é vazamento entre empresas.
owner: wagner
related_us: [US-ASSOC-005]
---

# Casos de Uso & Aceite — Associacoes · Área da associada (`/associacoes/m/{token}`)

> **Âncora:** ADR 0451 D4, D5 e D6. Padrão copiado do portal do paciente do Fisio (`Portal.casos.md`, UC-FPP-01..07). Os UCs não derivam do `.tsx`.
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou. **O veredito é da lane** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-AMB-01 | link válido pede PIN e não mostra dado | must | `AssociacoesPortalMembroContratoTest` |
| UC-AMB-02 | outra empresa, adulterado ou revogado: o mesmo estado vazio | must | `AssociacoesPortalMembroContratoTest` |
| UC-AMB-03 | 5 PINs errados travam | must | `AssociacoesPortalMembroContratoTest` |
| UC-AMB-04 | PIN certo abre Meus dados e autorizações | must | `AssociacoesPortalMembroContratoTest` |
| UC-AMB-05 | retirar autorização grava a data e não apaga; `associada` só pela associação | must | `AssociacoesPortalMembroContratoTest` |
| UC-AMB-06 | gerar e revogar o link: só quem edita e alcança, nunca de outra empresa | must | `AssociacoesPortalMembroContratoTest` |

---

## UC-AMB-01 · O link abre o PIN, não o cadastro · `must`
- **Persona:** associada que recebeu o link da associação.
- **Aceite:** Dado um link válido · Quando é aberto · Então a tela pede o PIN e não traz nome, associação nem autorizações. No banco, só hash do token e do PIN.
- **Teste:** `Modules/Associacoes/Tests/Feature/AssociacoesPortalMembroContratoTest.php`
- **Status: 🧪**

## UC-AMB-02 · Link ruim é sempre o mesmo link ruim · `must`
- **Aceite:** Token de empresa sem o pacote, com assinatura adulterada para apontar outra empresa, ou substituído por um link mais novo · Então a tela mostra o mesmo estado vazio (`link_invalido`), sem dizer qual condição quebrou, e o PIN responde 404.
- **Teste:** `Modules/Associacoes/Tests/Feature/AssociacoesPortalMembroContratoTest.php`
- **Status: 🧪**

## UC-AMB-03 · PIN errado trava · `must`
- **Aceite:** Dadas 4 tentativas erradas · Então cada uma diz quantas restam · Quando a 5ª erra · Então o acesso trava por 30 min e nem o PIN certo entra até a trava acabar.
- **Teste:** `Modules/Associacoes/Tests/Feature/AssociacoesPortalMembroContratoTest.php`
- **Status: 🧪**

## UC-AMB-04 · Meus dados · `must`
- **Aceite:** Dado o PIN certo · Então a tela mostra nome, associação, situação, data de entrada e cada autorização (autorizada ou não), e marca `associada` como não revogável.
- **Teste:** `Modules/Associacoes/Tests/Feature/AssociacoesPortalMembroContratoTest.php`
- **Status: 🧪**

## UC-AMB-05 · Retirar autorização · `must`
- **Aceite:** Sem PIN, retirar responde 403 · Com PIN, retirar `whatsapp` grava `revoked_at` e a linha continua no banco · Retirar `associada` responde 422 e nada muda · Finalidade desconhecida responde 404.
- **Teste:** `Modules/Associacoes/Tests/Feature/AssociacoesPortalMembroContratoTest.php`
- **Status: 🧪**

## UC-AMB-06 · A associação gera e revoga o link · `must`
- **Persona:** diretoria ou representante com `associacoes.editar`.
- **Aceite:** Quem edita e alcança a associada recebe link e PIN uma vez · Associada de outra empresa responde 404 · Sem `associacoes.editar` responde 403 e nada é emitido · Revogar preenche `revogado_em` e não apaga.
- **Teste:** `Modules/Associacoes/Tests/Feature/AssociacoesPortalMembroContratoTest.php`
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- [BACKLOG] "Lembrar este aparelho" por 30 dias (ADR 0451 D6, como UC-FPP-22/23 do Fisio).
- [BACKLOG] Botão de gerar/revogar link na tela de associadas da onda 4.

## Histórico

- 2026-11-13 · [CC] criado com a onda 6 da ADR 0451 (US-ASSOC-005).
