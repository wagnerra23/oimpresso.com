# `governance/design-requests/` — ledger de vereditos de design (retorno pro Cowork)

> Canal estruturado de **retorno do que não foi aprovado** pro design (Cowork) refazer.
> Etapa 6 do ciclo de vida ([ADR 0270]), governança em [ADR 0293]. **Append-only.**

## O que é

Quando um protótipo de design **não passa** — um gate barrou (`ds-guard`/`integrity-check`) ou
[W] rejeitou no gate visual ([ADR 0107]) — o veredito é registrado aqui, com **motivo + o padrão
canônico a seguir**. O **próximo handoff do Cowork lê esta pasta** antes de refazer a tela, em vez
de o feedback virar "lição solta" ou o Code maquiar a regressão.

## Formato (um arquivo por veredito, `AAAA-MM-DD-<tela>-<tema>.md`)

```
---
tela: <chave-cowork-map>
tema: <ex: dark, densidade, copy>
veredito: rejeitado | aprovado-com-ressalva
responsavel: [W] | [CC] | gate-automatico
status: pendente | resolvido
---
# <título>
- detecção: <o que disparou>
- motivo:   <por que não passou>
- padrão:   <ADR/regra que o redesign deve seguir>
- ação Cowork: <o que refazer, concretamente>
```

## Relação com o Decision Register

O **Decision Register por tela** (`<tela>.decisoes.md`, [ADR 0293] D-B) é o debate interno da tela.
Este ledger é o **recorte que volta pro Cowork** — só os vereditos que exigem redesign na fonte.

[ADR 0270]: ../../memory/decisions/0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento.md
[ADR 0293]: ../../memory/decisions/0293-governanca-decisao-design-responsavel-registro-veredito.md
[ADR 0107]: ../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md
