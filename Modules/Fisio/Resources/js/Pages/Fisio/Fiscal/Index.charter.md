---
id: modules-fisio-pages-fisio-fiscal-index-charter
page: /fisio/fiscal
component: Modules/Fisio/Resources/js/Pages/Fisio/Fiscal/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/prototipos/fisio/FiscalFila.dc.html
owner: wagner
status: draft
last_validated: "2026-11-04"
parent_module: Fisio
related_adrs: [93, 358, 437]
alcance:
  rota: /fisio/fiscal
  rota_nome: fisio.fiscal.index
  permission: fisio.access
  menu_hook: Modules/Fisio/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: fisio_module
tier: B
charter_version: 1
---

# Page Charter — /fisio/fiscal (DRAFT · documentos fiscais do dia)

> Nasce na US-FISIO-023 (lote 4 do protótipo Prescreve Pro). Mapa de donos no SPEC do Fisio, seção "F3 · Dinheiro". Casos: [`Index.casos.md`](Index.casos.md).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Mostrar, para o dia, a NFS-e das vendas a pacientes (clínica com CNPJ) e a fila de recibos do Receita Saúde dos pagamentos de pacientes (autônomo com CPF), para quem atende não esquecer nenhum documento.

## Goals — Features (faz)

- Fila do Receita Saúde: um item por pagamento do dia de venda a paciente, com "falta CPF do pagador" quando não há CPF — UC-FFI-01, UC-FFI-02.
- Marcar o recibo como enviado, com número opcional — UC-FFI-03.
- Listar as NFS-e do dia das vendas a pacientes, com o erro da prefeitura quando rejeitada.

## Non-Goals (não faz)

- Emitir NFS-e ou recibo. NFS-e é do módulo NFSe; o recibo é emitido pelo profissional no app Receita Saúde ou no Carnê-Leão.
- Gerar o lote oficial do Receita Saúde enquanto o layout do Manual v2.1 não estiver em mãos (US-FISIO-024).
- Copiar valor ou data do pagamento para tabela do Fisio.

## Automation Anti-hooks

- A tela nunca recebe o CPF inteiro: só se há CPF válido e os 2 últimos dígitos — UC-FFI-02.
