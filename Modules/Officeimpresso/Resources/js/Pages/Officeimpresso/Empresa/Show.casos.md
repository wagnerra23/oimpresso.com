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
> Testes: `Modules/Officeimpresso/Tests/Feature/ComputadoresEmpresaShowContratoTest.php` (leitura) e
> `ComputadoresEmpresaAcoesContratoTest.php` (ações da empresa) — lane `officeimpresso-pest`, MySQL,
> tenant 98 × 99.
> Status: 🧪 escrito e na allowlist, veredito ainda não emitido.

## UC-EMP-01 · Flag decide a tela; `?legacy=1` volta à Blade · `must`

**Dado** a flag `useV2OfficeimpressoEmpresa` desligada **Quando** o suporte abre qualquer das duas rotas
**Então** recebe a Blade `licenca_computador.computadores` **E** com a flag ligada recebe
`Officeimpresso/Empresa/Show`, com `computadores` e `assinatura` adiadas **E** com `?legacy=1` volta à
Blade, onde fica o bloqueio de máquina até o PR dele.

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

## UC-EMP-08 · Editar a configuração do desktop · `must`

**Dado** um usuário da operadora com `officeimpresso.empresa.gerenciar` **Quando** salva caminho do banco,
versão obrigatória e versão disponível no modal "Editar" **Então** os três ficam gravados na empresa da URL
**E** um caminho com mais de 255 caracteres volta como erro no campo `caminho_banco_servidor`, sem gravar
nada (empresa-parity D3: a regra antiga validava `caminho_banco`, que o formulário não manda).

Status: 🧪

## UC-EMP-09 · Bloquear e liberar a empresa inteira · `must`

**Dado** a empresa liberada **Quando** o suporte confirma "Bloquear empresa" **Então** ela fica bloqueada
**E** um segundo pedido de bloquear (duplo clique, outra aba) volta erro e **não** a libera **E** "Liberar
empresa" a libera.

Status: 🧪

## UC-EMP-22 · A ficha diz o que o usuário pode fazer · `must`

**Dado** um usuário só com `officeimpresso.access` **Quando** abre a ficha **Então** `permissions.empresa`
é falso e a tela não mostra "Editar" nem o bloqueio da empresa **E** com `officeimpresso.empresa.gerenciar`
é verdadeiro. O botão escondido não é a guarda: o controller recusa do mesmo jeito.

Status: 🧪

## UC-EMP-23 · Usuário de empresa cliente não mexe em outra empresa · `must` `[T0]`

**Dado** um usuário de empresa CLIENTE que recebeu `officeimpresso.empresa.gerenciar` **Quando** tenta
editar ou bloquear outra empresa **Então** recebe 403 nas duas ações **E** nenhuma coluna da empresa
alvo muda (`AcessoOperador`: a permissão só vale na operadora).

Status: 🧪

## Fora do teste por ora

- [BACKLOG] Bloco de pacote e assinatura só quando existem; limite 0 = "Ilimitado" (parity 11–14).
- [BACKLOG] Bloquear máquina na ficha (parity 34) — PR próprio; hoje fica na Blade (`?legacy=1`).
