---
page: /ponto/configuracoes
component: resources/js/Pages/Ponto/Configuracoes/Index.tsx
related_prototype: n/a (tela de painel de parâmetros read-only — não segue um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_us: [US-PONT-005]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/configuracoes (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/ConfiguracaoController@index` (rota `ponto.configuracoes.index`, permissão `ponto.access`). Painel read-only dos parâmetros CLT + módulo (fonte: `config/pontowr2.php`).

---

## Mission
O gestor consulta os parâmetros vigentes do módulo de ponto — tolerâncias e limites CLT (Art. 58, 59, 66, 71, 73), regras de banco de horas, estado de imutabilidade dos REPs e configuração AFD/eSocial. É um painel de leitura que dá transparência sobre como a apuração está parametrizada, com atalho para gerenciar REPs.

---

## Goals — Features (faz)
- Exibe (read-only) 4 blocos: CLT (tolerâncias/HE/noturno/DSR), Banco de Horas (limite/expiração), REPs & Imutabilidade (triggers MySQL, hash encadeado, NSR), AFD & eSocial (versão Portaria, hash chain, stubs).
- Cita o artigo legal aplicável em cada parâmetro CLT.
- Atalho "Gerenciar REPs" (`/ponto/configuracoes/reps`).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita parâmetros na UI — alteração é via `config/pontowr2.php` (read-only por enquanto).
- ❌ Não configura eSocial de verdade — S-1010/S-2230/S-2240 são stubs (fase 3).
- ❌ Não liga/desliga a imutabilidade dos REPs — só reporta o estado (triggers são infra, Portaria MTP 671/2021).
- ❌ Config é de arquivo, não escopada por `business_id` — parâmetros são do módulo, não por tenant (confirmar com Wagner se deve virar por-business).

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2.

---

## Automation hooks (faz)
- Lê a configuração diretamente de `config('pontowr2')` no controller (sem query).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não persiste nada — tela puramente informativa.
- ❌ Não altera triggers/imutabilidade do banco.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Decidir se parâmetros passam a ser editáveis por-business (hoje é config de arquivo global)
