---
id: resources-js-pages-vozdocliente-caixa-charter
page: /voz-do-cliente
component: resources/js/Pages/VozDoCliente/Caixa.tsx
related_prototype: prototipo-ui/cowork/Wagner/voz-do-cliente-page.jsx
owner: wagner
status: draft
last_validated: "2026-10-13"
parent_module: VozDoCliente
related_adrs: [93, 104, 105, 358]
tier: C
charter_version: 1
related_runbook: memory/requisitos/VozDoCliente/RUNBOOK-caixa.md
related_us: [US-INFRA-002]
---

# Page Charter — /voz-do-cliente

> **Status:** draft. Thread `modulos-faltantes/playbook/03` (executa também `telas-soltas/playbook/05`). Proposto pelo
> [CC] em `cowork-inbox/modulos-faltantes/voz-do-cliente.charter.md`; este é o charter ao lado do `.tsx`.
> **Non-Goals e Anti-hooks abaixo são os do [CC] — [W] ainda não aprovou** (a skill `charter-write` não infere esses dois).
> `GET /voz-do-cliente` serve só esta Page desde o F5 de 2026-10-27; até então era dual (flag
> `useV2VozDoClienteCaixa` + Blade `vozdocliente::caixa`, removidas no F5). Casos: [`Caixa.casos.md`](./Caixa.casos.md).

## Mission

Deixar quem cuida do produto ver, num lugar só, o que as pessoas relataram de dentro do sistema — com a tela em que
aconteceu — e decidir à mão o que vira US.

## Persona-alvo

Wagner (escritório, 1440px) lendo a caixa pra priorizar. Larissa e Eliana **produzem** relato, não consomem esta tela.

## Goals

- Lista: Quando · Quem · O que disse · Onde (`url_vista`) · Grav. · Situação — pendente primeiro, mais novo primeiro (a
  mesma ordem da Blade).
- Contagem por situação + gravidade alta; filtro por situação (Todos / Pendentes / Triados / Fechados).
- Frescor visível: data e idade do relato.
- **(onda seguinte)** Triagem manual: pendente → US do backlog ou fechado com motivo.

## Non-Goals

- ❌ NÃO é helpdesk: não abre chamado, não promete SLA, não responde quem relatou.
- ❌ NÃO notifica ninguém — nem ao triar, nem ao fechar.
- ❌ NÃO edita nem apaga o texto do relato (é registro do que a pessoa disse).
- ❌ NÃO cruza tenants (`business_id` da sessão, Tier 0).
- ❌ NÃO expõe a caixa a quem não tem `vozdocliente.triar` (texto + autor + tela = pessoa identificada, LGPD Art. 7º).

## Automation hooks

- Classifica a gravidade auto-declarada (0–4) em Alta / Média / Baixa pelo limiar `vozdocliente.severidade_que_exige_triagem`
  (padrão 3): `≥ limiar` Alta · `limiar − 1` Média · abaixo Baixa · sem valor "—".
- Marca a linha pendente de gravidade alta (trilha `urgent`) e atenua a fechada (`archived`).

## Anti-hooks

- ❌ NÃO cria US por severidade nem por volume de relatos parecidos.
- ❌ NÃO escala nem reordena backlog sozinho.
- ❌ NÃO deduplica relato na tela (a dedup de reenvio idêntico é do `store`; duplicado parecido é decisão de quem tria).

## Acesso

`vozdocliente.triar` ou `superadmin` — decisão `PERM-VOZ` ([W] 07/10, D1 de telas-soltas). Sem isso, 403 do servidor,
antes de a flag ser avaliada.

## UX targets

- Cabe em 1280px; "O que disse" nunca esmagada (a tabela rola na horizontal).
- Copy do vazio literal da Blade: "Nenhum relato ainda."
- Estados: cheia · filtrada-vazia · vazia · carregando.

## Pendências antes de `status: live`

- [ ] [W] aprova Non-Goals + Anti-hooks.
- [ ] Screenshot da Page com a flag ligada (biz=1) + smoke de produção.
- [ ] Triagem (onda seguinte) — inclui decidir onde mora o "motivo do fechamento" (não há coluna).
