---
id: modules-officeimpresso-pages-empresas-index-casos
casos: Empresas licenciadas · /officeimpresso/businessall
irmaos: Index.charter.md (lei) · Index.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a porta de entrada do suporte para os clientes do desktop; lista global mostra o cadastro de todos os clientes, então o payload tem de ser DTO.
owner: wagner
last_run: "2026-10-12"
---

# Casos de Uso & Aceite — Empresas licenciadas (`/officeimpresso/businessall`)

> Derivados do [empresas-parity.md](../../../../../../../memory/requisitos/Officeimpresso/empresas-parity.md),
> do [RUNBOOK-empresas.md](../../../../../../../memory/requisitos/Officeimpresso/RUNBOOK-empresas.md) e da
> decisão D1 de [W] em 2026-10-12 (versão obrigatória e último acesso entram; máquinas e banco ficam
> para a ficha) — **não do `Index.tsx`**.
> Teste: `Modules/Officeimpresso/Tests/Feature/ComputadoresEmpresasIndexContratoTest.php`
> (lane `officeimpresso-pest`, MySQL, tenant 98 × 99).
> Status: 🧪 escrito e na allowlist, veredito ainda não emitido.

## UC-EMPS-01 · A rota serve a tela React, sem Blade nem flag · `must`

**Dado** o cutover ([W] 2026-10-27, RUNBOOK-empresas §F5: flag `useV2OfficeimpressoEmpresas` e Blade
`licenca_computador.businessall` removidos) **Quando** o suporte abre a lista, mesmo com uma regra no
GrowthBook desligando a flag antiga **Então** recebe `Officeimpresso/Empresas/Index`, com `empresas` e
`kpis` adiadas, e a view Blade não existe mais. (Até 2026-10-27 este UC era a rota de fuga: flag OFF
servia a Blade.)

Status: 🧪

## UC-EMPS-02 · KPIs vêm do backend · `must`

**Dado** empresas licenciadas, bloqueadas e liberadas **Quando** a tela pede os KPIs **Então** recebe
`total`, `ativas` e `bloqueadas`, com `total = ativas + bloqueadas`, contando só empresas licenciadas.

Status: 🧪

## UC-EMPS-04 · Versão obrigatória e último acesso reais · `must`

**Dado** uma empresa com `versao_obrigatoria` no cadastro e três máquinas, uma sem acesso **Quando**
a lista carrega **Então** a linha mostra a versão obrigatória do cadastro **E** o último acesso é o
da máquina mais recente. O `business.dt_ultimo_acesso` não serve: nenhum código grava nele.

Status: 🧪

## UC-EMPS-05 · Status pelo bloqueio da empresa · `must`

**Dado** uma empresa bloqueada e outra liberada **Quando** a lista carrega **Então** a primeira sai
`bloqueado: true` e a segunda `false`.

Status: 🧪

## UC-EMPS-10 · Só empresa licenciada aparece · `must`

**Dado** uma empresa com `is_officeimpresso` ligado e outra desligado **Quando** a lista carrega
**Então** só a primeira aparece.

Status: 🧪

## UC-EMPS-11 · Lista global por design · `must` `[T0]`

**Dado** um usuário da operadora com `officeimpresso.access` **Quando** a lista carrega **Então** ele
vê também a empresa cliente — é a lista de clientes da WR2, e a guarda (`AcessoOperador`) já restringe
quem entra.

Status: 🧪

## UC-EMPS-12 · Cada linha é DTO, nunca o model `Business` · `must` `[T0]`

**Dado** uma empresa licenciada **Quando** a lista carrega **Então** a linha tem exatamente os 8 campos
`id, name, razao_social, cnpj, versao_disponivel, versao_obrigatoria, ultimo_acesso, bloqueado`.

Status: 🧪

## Fora do teste por ora

- [BACKLOG] Os dois botões da linha levam à ficha de computadores e ao log da empresa, com `aria-label`
  (parity 16–18). Hoje é só código; vira caso quando houver teste de navegador (F4).
- [BACKLOG] Busca, ordenação por id decrescente e 25 por página (parity 19–21).
