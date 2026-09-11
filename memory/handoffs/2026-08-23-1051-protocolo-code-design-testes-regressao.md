---
date: "2026-08-23"
time: "10:51 BRT"
slug: protocolo-code-design-testes-regressao
tldr: "O retorno §10.2 deixou de aceitar só SYNC_LOG e passou a validar os três canais; o cache _ds passou a recusar traversal e a publicar atomicamente; assinatura, seleção e HTTP dos handoffs ganharam testes herméticos. 76/76 casos do gate-selftest passaram."
prs: []
decided_by: [W]
next_steps:
  - "[W] revisar o diff da branch local codex/design-protocol-regressions e autorizar commit/PR se estiver de acordo."
  - "Promover o retorno a required somente pelo rito da ADR 0336, em decisão separada; esta mudança preservou o workflow advisory."
---

# O protocolo agora prova o retorno — e o cache só publica um lote inteiro

## O falso-verde que existia

O PROTOCOL §10.2 exige três canais a cada retorno Code → Design. O workflow de produção, porém,
considerava o ciclo fechado quando encontrava somente `prototipo-ui/SYNC_LOG.md`. Ele também media
apenas `HEAD~1..HEAD`, de modo que um push com mais de um commit podia esconder a alteração de UI.

A regra agora vive em `scripts/governance/design-return-check.mjs`, invocado diretamente pelo
workflow. O verificador mede todo o push, cobre UI compartilhada e modular, exige os três paths e
valida o conteúdo mínimo de cada canal. O par de fixtures good/bad entrou no `gate-selftest`, de
forma que voltar a aceitar somente SYNC_LOG derruba a catraca consolidada.

## O que `_ds` realmente é

O shell exportado pede arquivos na URL relativa `_ds/<project-id>/...`, mas a fonte versionada
vive em `scripts/design-sync/mirror-snapshot/`. `_ds` é a materialização descartável dessa fonte no
endereço que o navegador estático espera. Ele não é fonte de verdade e não deve ser versionado.

O comando anterior copiava arquivo a arquivo e só depois descobria ausência ou JavaScript inválido.
Também confiava em paths extraídos do HTML/CSS. Agora referências inseguras são recusadas, imports
CSS recursivos têm fechamento com ciclo, e o lote validado é montado fora do diretório servido e
publicado por troca de diretório. Falha preserva o cache bom; sucesso remove órfãos.

## Handoff zero-paste

A assinatura PHP ganhou fronteiras de parsing/HMAC. A seleção deixou o Bash inline e passou para
um módulo testável, cobrindo push multi-commit, first push, deleções, espaços e dispatch. O POST foi
testado sem rede real contra todos os resultados relevantes, inclusive continuar o lote depois de
uma falha sem esconder o exit final. O job de submit agora depende do self-test.

## Prova e limite de enforcement

Passaram 9 suítes Node relacionadas, 76/76 casos do gate-selftest, selftest do protocolo e as três
suítes PHP/Bash/HTTP no WSL. A única vermelhidão encontrada foi uma dívida anterior fora do diff:
o registry aponta um `--selftest` órfão em `block-sonda-que-mente.mjs`.

O retorno pós-merge continua advisory por desenho. Os testes impedem a lógica de voltar ao
falso-verde; eles não transformam automaticamente a lane em bloqueio de merge. Essa promoção exige
o rito e a autorização próprios da ADR 0336.

## Complemento: o aplicador estava cego para módulos e para protótipos 1:N

A clarificação [W] sobre Officeimpresso, Superadmin e as telas importadas de Blade revelou uma
segunda causa concreta de “não consigo aplicar”: a prontidão só varria `resources/js/Pages`, embora
as Pages dessas duas áreas vivam em `Modules/<X>/Resources/js/Pages`. Além disso, o detector guardava
um único alvo por mockup; `superadmin-page.jsx` alimenta quatro Pages e `officeimpresso-page.jsx`, duas.

As duas réguas agora consomem as raízes canônicas de `page-path.mjs`, preservam módulo/path físico e
tratam `mockup → Pages` como 1:N. O relatório real passou de 44 para 54 telas com protótipo — dez
Pages modulares deixaram de ficar invisíveis. Superadmin lista Dashboard, Negócios, Assinaturas e
Pacotes; Officeimpresso lista Logs/Index e Logs/Timeline. Todas as seis aparecem como `1-ciclo` por
falta de scorecard, que é uma pendência explícita em vez de silêncio.

O detector do espelho real publicou ainda 33 alvos semânticos, 6 TSX diffáveis alterados, 15 mockups
órfãos e 9 telas registradas a criar. Os 15 órfãos seguem reprovando o gate: esta mudança ampliou o
inventário, não transformou ausência de vínculo em verde.
