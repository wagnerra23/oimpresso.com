---
page: /arquivos
component: resources/js/Pages/Arquivos/Index.tsx
owner: wagner
status: draft
parent_module: Arquivos
related_us: [US-ARQ-013]
related_adrs: [0123-modules-arquivos-backbone, 0093-multi-tenant-isolation-tier-0, 0360-deprecacao-admin-center-supersede-0122]
related_prototype: prototipo-ui/cowork/arquivos-page.jsx
tier: B
charter_version: 2
---

# Page Charter — Arquivos/Index (DRAFT · carimbado do PT-01)

> Nascida do Padrão de Tela **PT-01 Lista** via `criar-tela.mjs` (UI-0013 — herança
> de padrão, NÃO bespoke). Golden do arquétipo: [PT-01](../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md).
> Preencha os TODO antes de subir de `draft` → `live` (exige screenshot aprovado por Wagner).

> **Onde a tela mora — decidido, não em aberto.** `resources/js/Pages/Arquivos/`, por
> decisão [W] de 2026-07-29 registrada no [SPEC §US-ARQ-013](../../../../memory/requisitos/Arquivos/SPEC.md)
> (*"pode ser dentro do arquivo mesmo"*), depois que a [ADR 0360](../../../../memory/decisions/0360-deprecacao-admin-center-supersede-0122.md)
> deprecou o Admin Center. O docblock de `DataController` e o protótipo F1 ainda dizem
> "Admin Center" — são a fonte **stale**. O PR-1 prometeu corrigir o docblock e **não
> corrigiu**: medido em 2026-08-25, `Modules/Arquivos/Http/Controllers/DataController.php`
> segue com "Admin Center" na L15 e no `label` da permission `arquivos.access` (L37, texto
> que aparece na tela de papéis). Fica como pendência abaixo — mexer no label é mudança de
> UI visível, não reconciliação de doc.

> **CSS do protótipo — decidido em 2026-08-25, etapa 1 de 2.** O protótipo carrega 11 classes
> próprias `.arq-*`, e não existia `cowork-arquivos-bundle.css` no repo: a tela nasceu em DS
> canon. A proibição Tier 0 manda copiar o bundle INTEIRO na 1ª aplicação. A origem
> (`modulos-faltantes.css`) é **multi-módulo**: 6 módulos no mesmo CSS, cada um com marcador de
> seção — copiar inteiro traria 5 módulos alheios. Desceu a **seção inteira** do Arquivos
> (L209-303), com as 4 regras multi-módulo **reescritas escopadas** e um assert que barra
> seletor alheio. **A tela ainda NÃO usa as classes** — aplicar o visual é PR próprio, como a
> mesma proibição manda ("1 PR de bundle copy + N PRs de customização").

> **O `.tsx` existe desde 2026-08-24** ([PR #6216](https://github.com/wagnerra23/oimpresso.com/pull/6216),
> commit `8c30820`), com rota `GET /arquivos` → `arquivos.index` (`can:arquivos.access`),
> `ArquivosAdminController@index` e o acervo real — não stub. Escopo do que nasceu: **só a
> vista Acervo**.
>
> **Em 2026-08-25 o PR-2 acrescentou a vista Trilha** (`arquivos_audit_log`, read-only) e,
> com ela, **a barra de abas** — que era o combinado: ela nasce com a segunda vista, não
> antes. Navega por rota (`?tab=`), sem rota nova no backend. Retenção (PR-3) e cofre (PR-4)
> seguem pendentes. O contrato de teste da trilha é o **UC-INDEX-02** do `casos.md`.
>
> _Fato datado — por que o PR-0 não trouxe um stub:_ em 2026-08-24 o stub de 23 linhas foi
> removido (`c85bfa7`) porque forçaria uma baseline de pixel de placeholder, e baseline é a
> referência contra a qual todo PR futuro é comparado — uma falsa é pior que nenhuma. A tela
> nasceu depois, no PR-1, derivada do protótipo `prototipo-ui/cowork/arquivos-page.jsx`
> (4 vistas completas, já no `main`).

## Mission

Arquivos guarda coisa que a lei manda guardar (XML de NF-e por 5 anos) junto com coisa que a
lei manda apagar (PII depois da finalidade). Sem tela, ninguém no negócio sabe qual é qual — e
retenção sem visão é multa esperando acontecer. Esta tela dá a quem responde pela conformidade
um lugar pra ver o que o sistema guardou, por quanto tempo a lei manda guardar, o que já passou
do prazo e quem tocou em quê.

**Persona-alvo:** Wagner (escritório, 1440px) e Eliana (financeiro) — conformidade e custo de
disco. **Não é tela de balcão:** Larissa continua alcançando o anexo pela tela da OS.

## Goals — Features (faz)

- **Acervo** — lista administrativa por dono (`arquivable` polimórfico), com bucket, disco,
  tamanho, contexto e data de vencimento da guarda.
- **Retenção** — o que vence em 30/90 dias por `sub_destination`, **com a base legal ao lado do
  prazo**, o que está no grace de 30 dias, e o que passou do prazo sem ser apagado (o WARN do
  `HealthCheckCommand` check #4).
- **Cofre** — espaço por disco, arquivo acima do cap de 50 MB que o `VaultEncryptionService`
  recusa, órfão sem `arquivable`, MD5 repetido.
- **Trilha** — `arquivos_audit_log` read-only (upload · download · signed_url · soft_delete ·
  restore · hard_delete).
- PT-BR em todo label/placeholder/mensagem.

## Non-Goals — Features (NÃO faz)

> Proposta [CC] a partir do protótipo F1 — **[W] aprova antes de `status: live`.** Cada item
> vira Pest GUARD quando a onda correspondente entrar.

- ❌ NÃO faz upload (isso é dos módulos, via trait `HasArquivos`, cap de 50 MB).
- ❌ NÃO é gerenciador de pastas: não existe árvore de diretórios, existe dono.
- ❌ NÃO edita o conteúdo do arquivo nem gera pré-visualização de documento sensível.
- ❌ NÃO serve arquivo do vault por `Storage::url` — sempre `DownloadController`, signed URL
  de 60 min (ADR 0123 §6).
- ❌ NÃO renderiza filename/storage_path/MD5 em vista de governança (PII fica só em
  `arquivos_audit_log` — LGPD Art. 37).
- ❌ NÃO cruza tenants, em nenhuma vista nem em nenhum job disparado daqui (ADR 0093, Tier 0).
- ❌ NÃO edita, apaga nem corrige linha de `arquivos_audit_log` — append-only, nunca purgado,
  mesmo quando o arquivo é.
- ❌ NÃO substitui a Auditoria geral do sistema — a trilha aqui é só de arquivo.

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO apaga nada sozinho — quem apaga é o comando, com política.
- ❌ NÃO reclassifica bucket por heurística de mime/nome (classificação tem autor:
  `classified_by`).
- ❌ NÃO cifra arquivo acima do cap "na melhor das tentativas" — recusar é o comportamento
  correto (OOM).
- ❌ NÃO notifica titular por conta própria.

## UX Targets

- Cabe em 1280px sem scroll horizontal (monitor da Larissa/ROTA LIVRE); tabela densa rola na
  horizontal em vez de esmagar coluna.
- Prazo sempre acompanhado da lei — número sozinho não ensina o domínio.
- Estados: cheia · filtrada-vazia · vazia · carregando · erro · sem-permissão
  (`arquivos.access`, default off).

## Pendências antes de `status: live`

- [ ] [W] aprova Non-Goals + Anti-hooks acima.
- [x] **PR-1 (acervo) mergeado** — rota `GET /arquivos` viva, `.tsx` real ([#6216](https://github.com/wagnerra23/oimpresso.com/pull/6216), 2026-08-24).
- [x] **PR-2 (trilha) + barra de abas** — 2026-08-25. `?tab=trilha`, leitura pura de
      `arquivos_audit_log`, UC-INDEX-02 com 6 asserções.
- [ ] Onda 1 **completa**: faltam PR-3 (retenção) · PR-4 (cofre) — a tabela de escopo está
      no [RUNBOOK-index §5](../../../../memory/requisitos/Arquivos/RUNBOOK-index.md).
- [ ] Screenshot 1280/1440 aprovado por [W].
- [ ] Definir se reclassificar bucket/visibility fica nesta tela ou só no dono do arquivo
      (a onda 2 esbarra nisso — ver PR-6).
- [ ] Reconciliar `DataController`: docblock L15 e o `label` de `arquivos.access` (L37) ainda
      dizem "Admin Center" (deprecado pela ADR 0360) e `modifyAdminMenu()` ainda afirma que o
      módulo "não tem tela própria". É mudança de texto de UI — decisão [W], não faxina de doc.

## Refs

- Padrão de Tela: PT-01 Lista (DataTable + PageHeader + filtros)
- Constituição UI v2: UI-0013
- US: [US-ARQ-013](../../../../memory/requisitos/Arquivos/SPEC.md) (Sprint 2)
- ADRs: [0123](../../../../memory/decisions/0123-modules-arquivos-backbone.md) (módulo mãe) ·
  [0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) (multi-tenant Tier 0) ·
  [0360](../../../../memory/decisions/0360-deprecacao-admin-center-supersede-0122.md) (Admin Center deprecado)
- Contrato de tela: **ainda não existe** — medido em 2026-08-25, nenhum
  `arquivos*.contract.json` no repositório (varredura do índice inteiro, com controle
  positivo em `backup.contract.json`). _A razão original caducou:_ ele saiu do PR-0
  (`d738bdc`) porque o job `Preflight + contratos ativos` roda `git ls-files '*.contract.json'`
  e exige que **todos** passem (só o `EXEMPLO` é isento), e um contrato apontando pro stub
  reprovaria por construção — mas o stub acabou, e o `.tsx` já carrega as âncoras
  `data-contract` de `cabecalho`, `acervo-filtros` e `acervo`, mais `abas`, `trilha-filtros`
  e `trilha` desde o PR-2 (2026-08-25). O que falta agora é escrever o contrato contra a tela
  real, com a copy literal — e `abas` só fica completa quando as 2 vistas restantes entrarem
  (hoje são 2 de 4). Conteúdo do rascunho original preservado
  no commit `943cc23` (o ponteiro anterior, `cowork-inbox/modulos-faltantes/arquivos.contract.json`,
  nunca existiu no repositório nem no espelho do Cowork — era ponteiro podre).
- Casos: [Index.casos.md](Index.casos.md)
