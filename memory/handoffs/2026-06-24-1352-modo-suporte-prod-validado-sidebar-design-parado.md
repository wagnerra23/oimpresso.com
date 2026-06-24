---
date: "2026-06-24"
time: "13:52 BRT"
slug: modo-suporte-prod-validado-sidebar-design-parado
tldr: 'Modo Suporte validado end-to-end em produção (lista, trava 403, "Acessar como" auditado); #3340 destrava o 403. Sidebar e artefato de design parados por decisão do Wagner.'
decided_by: [wagner]
cycle: CYCLE-08
prs: [3340]
related_adrs: [0305-modo-suporte-cross-tenant-exceto-operador, 0308-modo-suporte-fase-a-acessar-como-login-as-guardado, 0309-modo-suporte-operadora-e-o-time-de-suporte]
next_steps: ['retomar Modo Suporte — sidebar + design']
---

## Estado MCP no momento

- **Cycle:** CYCLE-08 "Receita — Onda A" · 86% decorrido · 4 dias restantes (→ 2026-06-28).
- **my-work:** 30 tasks ativas; **nenhuma** task de Modo Suporte em DOING/REVIEW (o trabalho desta sessão foi validação + merge, não US trackada).
- **MCP git/gh:** conectado (gh autenticado; merges e deploy via Actions OK).

## O que aconteceu

Sessão de **validação em produção** do Modo Suporte (fases A+B já codadas em sessões anteriores). Eu cheguei com **info defasada** ("PR #3329 está aberta com CI vermelho") — estava ERRADO: **#3329 já estava MERGEADA** (12:14). Corrigido ao checar `gh pr view`.

- **#3340 mergeado nesta sessão** (operadora biz=1 = time de suporte, ADR 0309) — é o que **destrava o 403**. Deploy Hostinger concluído com sucesso (site saiu do 503).
- **Validação ao vivo no Chrome (prod):**
  1. Como **cliente** (impersonando biz=210 MMF Adesivos via login-as) → `/suporte/empresas` deu **403 "Acesso restrito a agentes do Modo Suporte"** — trava correta (cliente ≠ agente). _O 403 inicial do Wagner era isso: ele estava logado-como um cliente, não como a operadora._
  2. Voltei pra **Wagner-01 (operadora, user 635)** via `/sign-in-as-user/635` → `/suporte/empresas` **renderizou** (lista de empresas-cliente, operadora excluída). ✓
  3. `/suporte/empresas/164` (MARTINHO CAÇAMBAS) → detalhe com banner âmbar de auditoria + 5 stat cards + tabela "Todos os usuários" com botão **"Acessar como"** por linha. ✓
  4. **"Acessar como"** → entrou como usuário da MARTINHO (Contatos reais) com banner "Voltar para Wagner-01". ✓ Fluxo audita ANTES de trocar identidade (`SupportController::acessarComo` → `canImpersonate` Tier-0 + `recordImpersonation` append-only).
- **Fecha a pergunta original do Wagner** ("essa tela já existe? vai duplicar? quero React"): a tela React do Modo Suporte **substitui** a tela Blade antiga do superadmin (`/superadmin/business/164` "Todos os usuários" + "Acessar como") — mesma função, agora React + auditada + sem god-mode.
- **Wagner dirigia o mesmo Chrome em paralelo** (a aba se movia entre minhas ações) — sessões paralelas, conforme padrão dele.

## Artefatos gerados

- Nenhum código novo. Apenas **merge #3340** + deploy + validação.
- Handoff (este) + índice. Screenshots das 2 telas capturados em 1440 (em disco, efêmero).

## Persistência

- **git:** este handoff em branch `chore/handoff-modo-suporte-prod-validado` (off origin/main) → PR.
- **MCP:** propaga via webhook GitHub→MCP ~2min após merge.
- **BRIEFING:** não atualizado (sem mudança de capacidade — só validação).

## Próximos passos pra retomar

Comando: **"retomar Modo Suporte — sidebar + design"**. Dois itens PARADOS por decisão do Wagner (ele dismissou a pergunta e foi testar o protocolo em outra sessão):

1. **Sidebar — onde o item "Suporte" nasce.** Nó: **Suporte NÃO é módulo nWidart** (vive em `app/Http/Controllers/Support/`), então **não há `DataController` pra publicar o item** (ADR 0180) → tela **órfã de navegação** (só por URL). Some-se: **sidebar da operadora está VAZIO** ("Menu vazio") → Modo Suporte seria a **nav principal da operadora**. Opções levantadas: (a) **item single-link no core `AdminSidebarMenu.php` + gate `isSupportAgent`** [recomendado]; (b) criar `Modules/Suporte` mínimo só pro DataController; (c) cascata Superadmin no rodapé [rejeitado: esconde agentes não-superadmin].
2. **Artefato de design** da tela (lista + detalhe): pendente escolher entre `design-arte` (nota+ficha), `design:design-critique`, ou handoff-doc.

## Lições catalogadas

- **Não confiar em estado de PR de memória/sessão anterior** — declarei "#3329 aberta/vermelha" sem checar; estava mergeada. SEMPRE `gh pr view <n>` antes de afirmar.
- **403 do Modo Suporte ≠ bug quando o usuário está logado-como cliente** — checar a identidade EFETIVA da sessão (Inertia `auth.user.business_id` / banner "Voltar para X") antes de diagnosticar a trava.

## Pointers detalhados

- [ADR 0305](../decisions/0305-modo-suporte-cross-tenant-exceto-operador.md) · [0308](../decisions/0308-modo-suporte-fase-a-acessar-como-login-as-guardado.md) · [0309](../decisions/0309-modo-suporte-operadora-e-o-time-de-suporte.md)
- `app/Services/Support/SupportAccessService.php` (`isSupportAgent` / `canImpersonate`) · `app/Http/Controllers/Support/SupportController.php` (`acessarComo`)
- Sidebar: `app/Http/Middleware/AdminSidebarMenu.php` · `resources/js/Components/cockpit/Sidebar.tsx` (SIDEBAR_GROUPS) · skill `sidebar-menu-arch`
- Handoff anterior: [2026-06-23 19:15 — fase B tela Empresas](2026-06-23-1915-modo-suporte-fase-b-tela-empresas.md)
