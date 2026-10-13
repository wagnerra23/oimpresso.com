---
page: /officeimpresso/computadores
component: Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Empresa/Show.tsx
status: draft
owner: wagner
parent_module: Officeimpresso
last_validated: '2026-10-13'
related_prototype: prototipo-ui/cowork/Wagner/officeimpresso-page.jsx
related_runbook: memory/requisitos/Officeimpresso/RUNBOOK-empresa.md
related_us: [US-OI-011, US-OI-012]
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
  - 0066-format-date-shift-3h-preservado-legacy-clientes
charter_version: 1
---

# Charter — Ficha da empresa licenciada

**Rotas:** `/officeimpresso/computadores` (empresa da sessão) e
`/officeimpresso/licenca_computado/licencas/{id}` (qualquer empresa).

**Missão.** Mostrar ao suporte da operadora o cadastro, a assinatura e as máquinas de uma empresa com o
desktop Office Impresso, com o caminho para o pacote e para o log.

**Padrão de tela:** PT-03 Detalhe.
**Plano:** [RUNBOOK-empresa.md](../../../../../../../memory/requisitos/Officeimpresso/RUNBOOK-empresa.md) ·
**Paridade:** [empresa-parity.md](../../../../../../../memory/requisitos/Officeimpresso/empresa-parity.md) ·
**Casos:** [Show.casos.md](./Show.casos.md).

## Âncora de design

`officeimpresso-page.jsx` → `EmpresaDrawer`. A ficha é página (como a Blade), não drawer. As
ações da empresa entram como modal ("Editar", Dialog — parity D6) e confirmação (bloquear empresa).

## Goals

- Ficha: nome, razão social, endereço, telefone, versões, caminho do banco, último acesso e situação.
- Pacote e assinatura só quando existem: limite (0 = "Ilimitado"), data final e dias restantes.
- Tabela das máquinas da empresa com status e link de log; "Ver log da empresa"; "Ver pacote".
- "Editar" (caminho do banco, versão obrigatória, versão disponível) e "Bloquear/Liberar empresa", só para
  quem tem `officeimpresso.empresa.gerenciar` na operadora.

## Non-Goals (NÃO faz)

- ❌ Nesta entrega: bloquear máquina — fica na Blade (`?legacy=1`, link "Abrir na tela antiga") até o PR
  dele.
- ❌ Editar `officeimpresso_numerodemaquinas`: a Blade não tem o input; seria capacidade nova.
- ❌ Filtrar o id da URL pela sessão — decisão D1 de [W] em 2026-10-12: a operadora vê qualquer empresa.

## Anti-hooks

- ❌ Não serializa `Licenca_Computador` nem `Business`: DTOs explícitos (empresa-parity item 45).
- ❌ Não reformata a data final no navegador: ela chega formatada pelo backend com a mesma conta do
  `@format_date` ([ADR 0066](../../../../../../../memory/decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md)).
- ❌ Bloquear a empresa não é toggle cego: a tela manda `bloquear` (a intenção) e o backend recusa o
  pedido que já está cumprido — um duplo clique não libera um cliente que se quis bloquear.
- ❌ `permissions` não autoriza nada: só esconde botão. A guarda é `authorizeEmpresa()` (AcessoOperador).
- ❌ Não liga a flag `useV2OfficeimpressoEmpresa` por default: ligar é cutover do [W].

## Divergências declaradas

- Dias restantes com **sinal**: a Blade usa `Carbon::today()->diffInDays($end)`, que no Carbon 3 do
  projeto devolve negativo para assinatura vencida. O empresa-parity item 14 previa valor absoluto
  (semântica do Carbon 2); porta-se o que a Blade mostra hoje.
- Último acesso da ficha vem da máquina mais recente, não de `business.dt_ultimo_acesso`, que não tem
  escritor (mesma decisão da lista, empresas-parity D1).
- Status com `StatusBadge kind="licenca"` (`empresa_bloqueada`/`maquina_bloqueada`/`ativa`), não um
  `kind="empresa"` novo (parity D7): os valores já existem e significam exatamente isto.

## Dados / props

```
Officeimpresso/Empresa/Show (Inertia)
  permissions:  eager → { empresa: bool, gerenciar: bool }
  empresa:      eager → { id, name, razao_social, rua, telefone, versao_obrigatoria, versao_disponivel,
                          caminho_banco_servidor, ultimo_acesso, bloqueado, pacote_url }
  computadores: Inertia::defer → Array<{ id, dt_cadastro, user_win, pasta_instalacao, versao_exe,
                                         ip_interno, caminho_banco, dt_ultimo_acesso, bloqueado }>
  assinatura:   Inertia::defer → { limite_maquinas|null, fim|null, dias_restantes|null }
```

## Testes

`Modules/Officeimpresso/Tests/Feature/ComputadoresEmpresaShowContratoTest.php` (lane
`officeimpresso-pest`, MySQL, tenant 98 × 99) — UC-EMP-01, 04, 15, 19, 20, 21.
`Modules/Officeimpresso/Tests/Feature/ComputadoresEmpresaAcoesContratoTest.php` (mesma lane) — UC-EMP-08,
09, 22, 23.
