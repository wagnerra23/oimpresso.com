---
page: /officeimpresso/licenca_computador
component: Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Licencas/Index.tsx
status: draft
owner: wagner
parent_module: Officeimpresso
last_validated: '2026-10-01'
related_prototype: prototipo-ui/cowork/Wagner/officeimpresso-page.jsx
related_runbook: memory/requisitos/Officeimpresso/RUNBOOK-licencas.md
related_us:
  - US-OI-009
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
  - 0189-pageheader-canon-v3-1-cadastro-roxo
charter_version: 2
---

# Charter — Licenças de computador (`/officeimpresso/licenca_computador`)

**Missão.** Mostrar ao suporte o parque de máquinas do WR Comercial (Delphi) dos clientes —
quem está em campo, quem sumiu, quem está bloqueado e o que vence — sem consulta ao banco e sem
nunca expor a senha do equipamento.

**Padrão de tela:** [PT-01 Lista](../../../../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md).
**Plano:** [RUNBOOK-licencas.md](../../../../../../../memory/requisitos/Officeimpresso/RUNBOOK-licencas.md) ·
**Paridade:** [licencas-parity.md](../../../../../../../memory/requisitos/Officeimpresso/licencas-parity.md) ·
**Casos:** [Index.casos.md](./Index.casos.md) ·
**Contrato:** `governance/design/contracts/officeimpresso-licencas.contract.json`.

## Âncora de design

`officeimpresso-page.jsx` → `ViewLicencas()` (rota `oi-licencas`), medida na thread A1
(`governance/design/targets/officeimpresso--licencas--index.alvo.json`: header · toolbar · grade ·
rodape). Os KPI-filtros vêm da ficha 06 do playbook e moram na toolbar, onde o protótipo põe os
filtros. Decisão [W] D2 (2026-10-01): **tela nova no Officeimpresso**, não fusão com `Suporte/*`.

## Goals — o que a tela faz (PR-a, thread 06)

- Índice: uma linha por equipamento — negócio (só para quem vê todos), host / usuário do Windows,
  HD em mono, versão do executável e do banco, último acesso com frescor, validade e situação
  (ativa · bloqueada com motivo).
- KPI-filtros clicáveis: **Em campo** (acesso em 24 h) · **Sem acesso há 7 dias** (ou nunca) ·
  **Bloqueados** · **Vencendo em 30 dias** (pela `dt_validade`).
- Aviso de **HD compartilhado** (L4 da proposta): o mesmo HD em mais de um negócio — a API do
  desktop atualiza todas as linhas desse HD e recusa se qualquer uma estiver bloqueada.
- Link "Log" por linha para `/officeimpresso/licenca_log?licenca_id={id}`.

## Goals — drawer (PR-b, thread 06)

- Clicar no nome da máquina abre o **drawer PT-02 (760px)**: ficha do equipamento (empresa, usuário
  do Windows, HD, sistema, IP, processador, memória, pasta, versões, datas, mensagem que o desktop
  recebe) e **histórico de acessos e bloqueios** (`licenca_log`, últimos 50).
- **Liberar/bloquear com motivo obrigatório** (5 a 500 letras), pela mesma ação POST
  `/officeimpresso/licenca_computador/{id}/toggle-block`, mandando a intenção (`bloquear`). O motivo
  vai para o histórico (`admin_action`) com o negócio do equipamento e o autor.

## Non-Goals (NÃO faz)

- ❌ Exibir `senha`/`contra_senha` — nem mascaradas. O payload é DTO explícito e não as seleciona
  ([W] 2026-08-19, proposta de licenças). `serial` e `token` também não saem.
- ❌ Mudar a regra do bloqueio ou reescrever a mensagem que o desktop recebe (`licenca_computador.motivo`,
  devolvida como `N;<motivo>`) — o motivo do operador é só histórico. Se ele deve virar a mensagem
  ao desktop é decisão [W] pendente.
- ❌ Revogar licença e bloqueio em lote (o protótipo tem) — fora da ficha 06.
- ❌ Cobrança por equipamento (`valor`, `gera_mensalidade`) — [W] D5: fica no Financeiro/Superadmin.
- ❌ Ampliar quem vê o quê: só `superadmin` vê todos os negócios; `officeimpresso.access` segue
  vendo o negócio da sessão, como o `index()` sempre fez.

## Anti-hooks

- ❌ Não muda estado em GET (bloquear/liberar já é POST desde a thread 04).
- ❌ Não liga a flag sozinho: `useV2OfficeimpressoLicencas` nasce OFF e o Blade segue como rota de fuga.

## Dados / props

```
Officeimpresso/Licencas/Index (Inertia)
  permissions: { pode_ver_todas_empresas: bool }        // eager
  licencas: Inertia::defer → Array<{ id, business_id, empresa, hostname, user_win, hd,
    versao_exe, versao_banco, versao_obrigatoria, dt_ultimo_acesso, frescor, dt_validade,
    bloqueado, motivo, hd_compartilhado }>
  permissions.pode_gerenciar: bool                       // eager — mostra o rodapé de bloquear
  detalhe: Inertia::optional (only: ['detalhe'], ?licenca={id}) → { ficha: {…sem senha},
    historico: Array<{ id, quando, evento, origem, ip, rota, http_status, erro, motivo, autor }> } | null
```

`frescor`: `recente` (<24 h) · `fresc` (<7 d) · `frio` (<30 d) · `distante` (mais ou nunca) — o
vocabulário do `StatusBadge kind="frescor"` que o protótipo usa.

## Testes

`Modules/Officeimpresso/Tests/Feature/LicencasIndexContratoTest.php` (lane `officeimpresso-pest`,
MySQL, tenant 98) — UC-OILIC-01..15.
