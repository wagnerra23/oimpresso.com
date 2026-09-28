---
id: resources-js-pages-ponto-configuracoes-index-charter
page: /ponto/configuracoes
component: resources/js/Pages/Ponto/Configuracoes/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/ponto-telas.jsx
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
- Exibe (read-only) 4 blocos, lendo as chaves reais de `Modules/Ponto/Config/config.php`: CLT (tolerâncias, inter/intrajornada, hora noturna ficta, adicionais de noturno/HE/DSR), Banco de Horas (habilitado, prazo de compensação, saldos, multiplicadores), REPs e Imutabilidade (tipos permitidos, NSR, assinatura ICP e se o certificado está configurado, janela de correção, append-only, hash) e AFD & eSocial (encoding, limites, hash de registros, ambiente e eventos). Toda chave exibida tem de existir no payload — `UC-CFGIDX-02`. *(Até 2026-09-28 este Goal citava "triggers MySQL", "versão Portaria" e horário noturno início/fim, que não existem no config.)*
- 5º bloco "IA do Ponto" (read-only): master switch, classificação de intercorrência, explicação de
  divergência, geração de justificativa e modelo em uso — as chaves `pontowr2.ai.*` do mesmo
  config. — `D-CFG-IA`, [W] 2026-09-14 (ATA-DECISOES-2026-09-14, bloco 4, linha 41). ⚠️ **Estado em
  2026-09-28:** a construir — a tela mostra os 4 blocos acima. Região no map: `ia-do-ponto`
  (`memory/requisitos/Ponto/configuracoes-index.map.json`).
- Cita o artigo legal aplicável em cada parâmetro CLT.
- Atalho "Gerenciar REPs" (`/ponto/configuracoes/reps`).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita parâmetros na UI — alteração é via `config/pontowr2.php` (read-only por enquanto).
- ❌ Não configura eSocial de verdade — S-1010/S-2230/S-2240 são stubs (fase 3).
- ❌ Não liga/desliga a imutabilidade dos REPs — só reporta o estado (triggers são infra, Portaria MTP 671/2021).
- ❌ Ligar/desligar IA por business NÃO se faz por `if` no código — passa pela UI canônica de
  pacote/permissão. Enquanto esse caminho não existir, o bloco "IA do Ponto" é somente leitura.
  — condição de [W] em `D-CFG-IA` (ATA-DECISOES-2026-09-14 linha 41).
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
- [ ] `D-CFG-IA-CAMINHO` — item próprio aberto pela ata (linha 41: *"se não tiver, vira item
      próprio"*). **Medido em 2026-09-28: o caminho não existe.** As flags vêm de variável de ambiente
      (`Modules/Ponto/Config/config.php`, bloco `ai`: `AI_ENABLED`, `AI_CLASSIFICACAO_INTERCORRENCIA`,
      `AI_EXPLICACAO_DIVERGENCIA`, `AI_GERACAO_JUSTIFICATIVA`), valem igual para todos os tenants, e o
      `DataController` do módulo declara só a chave de pacote `ponto_module` e 7 permissões `ponto.*`,
      nenhuma de IA. Construir o caminho (chave de pacote ou permissão) é trabalho à parte; até lá, o
      bloco fica só leitura.
