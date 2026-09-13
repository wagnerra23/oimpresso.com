---
id: cowork-inbox-fiscal-config-charter
page: /fiscal/config
component: resources/js/Pages/Fiscal/Config.tsx (alvo da tradução)
related_prototype: "F1 Cowork — fiscal-subpages.jsx §FxConfigPage"
page_id: fiscal-config-ondas-f1
module: Fiscal
status: proposta [CC] — aguarda ratificação [W]
owner: wagner
autor: "[CC]"
created: 2026-08-24
related_us: [US-FISCAL-004, US-FISCAL-011]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0286-contrato-de-tela]
contrato: prototipo-ui/contrato/fiscal-config.contract.json
supersedes: cowork-inbox/fiscal/Config.divergencia.md
---

# Charter — Certificado e configuração fiscal

> ⚠️ **Este charter resolve uma divergência que o `main` deixou aberta de propósito.** O `Config.casos.md` do vivo se recusa a escrever UC porque isso seria escolher o vencedor entre o charter (read-only) e o código (formulários existentes). [W] autorizou a decisão nesta sessão; **até a ratificação em ADR, isto é proposta, não canon.**

## As três decisões tomadas

1. **A tela é editável — o charter cede, o código fica.** Os formulários já existem, foram pagos, e a alternativa read-only empurra o operador pra UI legada do emissor pra fazer a coisa mais arriscada do módulo. Esconder a ação não a torna segura; cercá-la, sim.
2. **Gate próprio: `fiscal.config.ambiente`**, separado de `fiscal.config.edit`. Editar e-mail do contador e trocar o ambiente de emissão não são o mesmo risco — a segunda muda o valor fiscal de **toda** nota emitida depois dela.
3. **Séries são lidas de verdade**, uma linha por modelo do local. A filial inventada do mock sai. Se houver filial real, ela entra pelo mesmo caminho de leitura, nunca por dado fixo no código.

## Mission

Dar à pessoa fiscal um lugar único para **ver** a configuração que governa a emissão e **mudar** as duas coisas que só ela pode mudar — ambiente e certificado — sem sair do ERP e sem conseguir fazê-lo por acidente.

## Goals (faz)

1. **Quatro abas:** Certificado e regime · Séries · Ambiente e certificado · SPED (ponteiro pra tela dona).
2. **Estado da permissão dito em texto** na aba de risco: concedida ou ausente, com os campos travados quando ausente — nunca um botão cinza sem motivo.
3. **Trocar ambiente exige duas provas**: o nome do destino digitado à mão (`PRODUÇÃO` / `HOMOLOGAÇÃO`) **e** um motivo de 15+ caracteres.
4. **Enviar certificado** aceita arquivo + senha; a senha nunca volta em payload, nunca vai pra log, nunca é reexibida.
5. **Toda troca vira evento de auditoria** com autor, horário e o par antes → depois.
6. **Validade do certificado com urgência visível** (≤7d crítico, ≤60d atenção) em todas as abas onde o certificado aparece.

## Non-Goals (NÃO faz)

- ❌ Editar regime tributário, CRT, CFOP ou tributação default — isso é cascata de recálculo, vive no emissor canônico.
- ❌ Criar, renumerar ou pular série — numeração é do emissor; salto de faixa se resolve por inutilização.
- ❌ Validar titular/CNPJ do certificado na tela — quem recusa arquivo de outro CNPJ é o emissor.
- ❌ Emitir nota de teste em homologação para "conferir" a troca.
- ❌ Guardar a senha do certificado em qualquer lugar que a tela consiga ler de volta.

## Anti-hooks

- 🚫 Não trocar ambiente com um clique só, nem com confirmação de uma palavra genérica ("sim", "ok") — o texto digitado é o destino, para não haver troca por reflexo.
- 🚫 Não deixar `fiscal.config.ambiente` cair no mesmo gate de `edit`: quem edita e-mail do contador não troca ambiente.
- 🚫 Não registrar a senha do certificado em evento, log, mensagem de erro ou payload de resposta.
- 🚫 Não trocar ambiente sem escrever evento: sem a trilha, ninguém explica por que a nota de terça não tem valor fiscal.
- 🚫 Não exibir série de outro business (Tier 0, ADR 0093).
- 🚫 Não voltar a fingir read-only mantendo os formulários no código — a divergência que originou este charter nasceu exatamente assim.
