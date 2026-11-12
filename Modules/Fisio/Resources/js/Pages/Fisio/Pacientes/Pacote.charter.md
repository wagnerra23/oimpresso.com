---
id: modules-fisio-pages-fisio-pacientes-pacote-charter
page: /fisio/pacientes/{id}/pacote
component: Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Pacote.tsx
related_prototype: prototipo-ui/cowork/Wagner/prototipos/fisio/Financeiro.dc.html
owner: wagner
status: draft
last_validated: "2026-11-04"
parent_module: Fisio
related_adrs: [93, 358, 437]
alcance:
  rota: /fisio/pacientes/{id}/pacote
  rota_nome: fisio.pacientes.pacote
  permission: fisio.access
  menu_hook: Modules/Fisio/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: fisio_module
tier: B
charter_version: 1
---

# Page Charter — /fisio/pacientes/{id}/pacote (DRAFT · pacote de sessões e cobrança)

> Nasce na US-FISIO-021 (lote 4 do protótipo Prescreve Pro). Mapa de donos no SPEC do Fisio, seção "F3 · Dinheiro". Casos: [`Pacote.casos.md`](Pacote.casos.md).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Mostrar quantas sessões do pacote o paciente já usou, registrar e devolver sessão, e mostrar a cobrança e a nota da venda do pacote, que pertencem ao Financeiro e ao NFSe.

## Goals — Features (faz)

- Mostrar o pacote atual com "usadas / contratadas", validade e a lista de sessões — UC-FPK-01.
- Abrir pacote (só sessões, ritmo e validade) e registrar sessão manualmente; sem saldo, a tela avisa e nada é lançado — UC-FPK-02.
- Devolver uma sessão por lançamento inverso, com motivo — UC-FPK-02 (via `SaldoDoPacote::estornar`).
- Mostrar os títulos a receber da venda ligada ao pacote, lidos do Financeiro — UC-FPK-05.
- Mostrar o status da NFS-e da venda, lido do NFSe — UC-FPK-06.
- Ligar ao pacote a venda já feita na tela de venda do núcleo, e levar à venda nova — UC-FPK-07, UC-FPK-08 (US-FISIO-022).
- Venda cancelada no núcleo tira do saldo as sessões que sobraram e encerra o pacote — UC-FPK-09.
- Imprimir o comprovante (voucher) do pacote em PDF para entregar ao paciente: contratadas, usadas, saldo, validade e sessões, sem valor em dinheiro — UC-FPK-10 (pedido do [W] em 2026-11-12).

## Non-Goals (não faz)

- Gravar título, baixa ou valor em dinheiro. O preço é digitado na tela de venda do núcleo; o Fisio só guarda o id da venda (US-FISIO-022).
- Devolver dinheiro de venda cancelada. É do Financeiro.
- Emitir NFS-e. Quem emite é o módulo NFSe.

## Automation Anti-hooks

- Nenhum `Titulo::create`, `TituloBaixa::create` ou `DB::table('fin_*')` a partir do Fisio — teste: UC-FPK-05 confere que abrir a tela não muda a contagem de `fin_titulos`.
