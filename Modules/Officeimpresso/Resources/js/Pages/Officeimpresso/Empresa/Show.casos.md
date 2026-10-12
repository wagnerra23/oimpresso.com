---
id: modules-officeimpresso-pages-empresa-show-casos
casos: Ficha da empresa licenciada · /officeimpresso/computadores e /officeimpresso/licenca_computado/licencas/{id}
irmaos: Show.charter.md (lei) · Show.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a ficha mostra o cadastro da empresa e as máquinas do desktop, cujo registro guarda senha e serial; a visão é cross-empresa para a operadora, então escopo e payload são Tier 0.
owner: wagner
last_run: "2026-10-12"
---

# Casos de Uso & Aceite — Ficha da empresa licenciada

> Derivados do [empresa-parity.md](../../../../../../../memory/requisitos/Officeimpresso/empresa-parity.md),
> do [RUNBOOK-empresa.md](../../../../../../../memory/requisitos/Officeimpresso/RUNBOOK-empresa.md) e da
> decisão D1 de [W] em 2026-10-12 (a operadora vê a ficha de qualquer empresa, com a regra nomeada no
> código) — **não do `Show.tsx`**.
> Teste: `Modules/Officeimpresso/Tests/Feature/ComputadoresEmpresaShowContratoTest.php`
> (lane `officeimpresso-pest`, MySQL, tenant 98 × 99).
> Status: 🧪 escrito e na allowlist, veredito ainda não emitido.

## UC-EMP-01 · Flag decide a tela; `?legacy=1` volta à Blade · `must`

**Dado** a flag `useV2OfficeimpressoEmpresa` desligada **Quando** o suporte abre qualquer das duas rotas
**Então** recebe a Blade `licenca_computador.computadores` **E** com a flag ligada recebe
`Officeimpresso/Empresa/Show`, com `computadores` e `assinatura` adiadas **E** com `?legacy=1` volta à
Blade, onde ficam as ações até o PR delas.

Status: 🧪

## UC-EMP-04 · Versões e último acesso reais · `must`

**Dado** a empresa com versão obrigatória no cadastro e duas máquinas **Quando** a ficha abre **Então**
mostra a versão obrigatória **E** o último acesso da máquina mais recente.

Status: 🧪

## UC-EMP-15 · Status de cada máquina · `must`

**Dado** uma máquina bloqueada e outra liberada **Quando** a tabela carrega **Então** cada uma sai com o
próprio `bloqueado`.

Status: 🧪

## UC-EMP-19 · `/computadores` mostra a empresa da sessão · `must` `[T0]`

**Dado** um usuário da operadora **Quando** abre `/officeimpresso/computadores` **Então** a ficha é a
da empresa da sessão.

Status: 🧪

## UC-EMP-20 · A operadora abre a ficha de qualquer empresa pela URL · `must` `[T0]`

**Dado** a decisão D1 **Quando** o usuário da operadora abre a ficha de outra empresa pelo id **Então**
recebe a ficha dela **E** um id sem empresa responde 404.

Status: 🧪

## UC-EMP-21 · Máquina é DTO; senha, serial e token não saem · `must` `[T0]`

**Dado** uma máquina com serial gravado **Quando** a tabela carrega **Então** a linha tem exatamente
`id, dt_cadastro, user_win, pasta_instalacao, versao_exe, ip_interno, caminho_banco, dt_ultimo_acesso,
bloqueado` **E** o serial não aparece em lugar nenhum da resposta.

Status: 🧪

## Fora do teste por ora

- [BACKLOG] Bloco de pacote e assinatura só quando existem; limite 0 = "Ilimitado" (parity 11–14).
- [BACKLOG] Ações: editar configuração, bloquear a empresa e bloquear máquina (parity 16–21, 34) — PR das ações.
