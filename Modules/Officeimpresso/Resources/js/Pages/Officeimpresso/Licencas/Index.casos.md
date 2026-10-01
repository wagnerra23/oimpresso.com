---
id: modules-officeimpresso-pages-licencas-index-casos
casos: Licenças de computador · /officeimpresso/licenca_computador
irmaos: Index.charter.md (lei) · Index.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela abre o parque de máquinas dos clientes; máquina de outro negócio ou senha no payload é incidente Tier 0.
owner: wagner
last_run: "2026-10-01"
---

# Casos de Uso & Aceite — Licenças de computador (`/officeimpresso/licenca_computador`)

> Derivados da ficha 06 do playbook Officeimpresso, da proposta de licenças (`cowork-inbox/connector/PROPOSTA-licencas-equipamentos.md`),
> do [RUNBOOK-licencas](../../../../../../../memory/requisitos/Officeimpresso/RUNBOOK-licencas.md) e do
> [licencas-parity](../../../../../../../memory/requisitos/Officeimpresso/licencas-parity.md) — **não do `Index.tsx`**.
> Os ids do parity (`UC-LIC-*`) colidem com os do Ponto (`LicencaAbonaDiaContratoTest`), por isso aqui é `UC-OILIC-*`.
> Teste: `Modules/Officeimpresso/Tests/Feature/LicencasIndexContratoTest.php` (lane `officeimpresso-pest`, MySQL, tenant 98).
> Status: 🧪 escrito e na allowlist, veredito ainda não emitido.

## UC-OILIC-01 · Sem permissão, 403 · `must` `[T0]`

**Dado** um usuário sem `superadmin` nem `officeimpresso.access` **Quando** abre a lista, com a
flag ligada ou desligada **Então** recebe 403.

Status: 🧪

## UC-OILIC-02 · Flag OFF serve o Blade · `must`

**Dado** a flag `useV2OfficeimpressoLicencas` desligada (estado de produção) **Quando** o suporte
abre a lista **Então** recebe a view Blade de sempre.

Status: 🧪

## UC-OILIC-03 · Flag ON serve a tela React · `must`

**Dado** a flag ligada **Quando** a lista abre **Então** a resposta é `Officeimpresso/Licencas/Index`,
com `permissions` no payload inicial e `licencas` adiada.

Status: 🧪

## UC-OILIC-04 · Suporte vê só o negócio da sessão · `must` `[T0]`

**Dado** máquinas no negócio da sessão e em outro **Quando** quem tem só `officeimpresso.access`
pede a lista **Então** vê as suas e não as do outro negócio (parity item 27).

Status: 🧪

## UC-OILIC-05 · Superadmin vê todos os negócios · `must`

**Dado** máquina de outro negócio **Quando** o superadmin pede a lista **Então** ela aparece com o
`business_id` e o nome da empresa.

Status: 🧪

## UC-OILIC-06 · Senha nunca sai · `must` `[T0]`

**Dado** uma máquina com `senha`, `contra_senha` e `serial` gravados **Quando** a lista é carregada
**Então** a linha não tem essas chaves nem `token`, e os valores não aparecem na resposta (parity item 29).

Status: 🧪

## UC-OILIC-07 · Frescor do último acesso · `should`

**Dado** acessos há 2 h, 3 d, 15 d e nenhum **Então** o frescor é `recente`, `fresc`, `frio` e
`distante`.

Status: 🧪

## UC-OILIC-08 · HD compartilhado é avisado · `must`

**Dado** o mesmo HD em dois negócios **Quando** o superadmin vê a lista **Então** a linha diz em
quantos outros negócios ele está; HD sozinho diz zero (L4).

Status: 🧪

## UC-OILIC-09 · Versões, validade e motivo na linha · `should`

**Dado** máquina com versão do executável e do banco, validade e bloqueio com motivo **Então** a
linha traz os quatro dados.

Status: 🧪

## UC-OILIC-10 · Ficha do equipamento sem segredo · `must` `[T0]`

**Dado** uma máquina com sistema, IP, `senha`, `contra_senha` e `usuario` gravados **Quando** o
suporte abre o drawer **Então** a ficha traz sistema e IP, e não tem `senha`, `contra_senha`,
`serial`, `token`, `usuario` nem `conexao` — nem os valores aparecem na resposta (ficha 06: *nunca exibir senha*).

Status: 🧪

## UC-OILIC-11 · Ficha de outro negócio não abre · `must` `[T0]`

**Dado** máquina de outro negócio **Quando** quem tem só `officeimpresso.access` pede a ficha
**Então** recebe vazio; a máquina do próprio negócio abre (mesma regra de visão da lista, UC-OILIC-04).

Status: 🧪

## UC-OILIC-12 · Histórico de acessos e bloqueios da máquina · `must`

**Dado** um acesso e um bloqueio com motivo da máquina, e um acesso de outra máquina **Quando** o
drawer abre **Então** o histórico traz os dois eventos dela, com o motivo do bloqueio, e não o da outra.

Status: 🧪

## UC-OILIC-13 · Bloquear exige motivo · `must`

**Dado** máquina liberada **Quando** o operador manda bloquear sem motivo, ou com menos de 5 letras
**Então** recebe erro no motivo, a máquina segue liberada e nada vai para o histórico (protótipo
`ConfirmMotivo`: mínimo 5, máximo 500).

Status: 🧪

## UC-OILIC-14 · O motivo fica no histórico do negócio do equipamento · `must` `[T0]`

**Dado** o superadmin bloqueando máquina de OUTRO negócio, com motivo **Então** a máquina fica
bloqueada, o histórico (`licenca_log`, `admin_action`) registra o evento com o `business_id` do
equipamento, o autor e o motivo, e a mensagem que o desktop recebe (`licenca_computador.motivo`)
não muda — a regra do bloqueio é a mesma de antes.

Status: 🧪

## UC-OILIC-15 · Clique repetido não desfaz · `should`

**Dado** máquina já bloqueada **Quando** chega de novo o pedido de bloquear **Então** recebe erro e
continua bloqueada. O toggle sem intenção (Blade e tela de Logs) segue funcionando sem motivo.

Status: 🧪

## Backlog (sem teste ainda — não são UC até ganharem um)

- [BACKLOG] KPI-filtros (Em campo · Sem acesso há 7 dias · Bloqueados · Vencendo em 30 dias) filtram a lista e se desligam no segundo clique — regra no cliente, sem teste.
- [BACKLOG] Versão do executável abaixo da obrigatória do negócio fica em destaque — sem teste.
- [BACKLOG] O drawer abre ao clicar no nome da máquina e o botão de bloquear só habilita com 5+ letras de motivo — regra no cliente, sem teste.
