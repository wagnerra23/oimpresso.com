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
> deprecou o Admin Center.
>
> **A fonte stale foi reconciliada em 2026-08-25.** Até então o `DataController` dizia "Admin
> Center" em dois lugares, e este charter registrava a dívida: *"o PR-1 prometeu corrigir o
> docblock e não corrigiu"*. Os dois foram corrigidos — o docblock da classe e, o que pesa
> mais, o `label` da permission `arquivos.access`, **texto que o [W] lê em `/roles/{id}/edit`
> ao marcar**. O label passa a descrever o PODER (ver o acervo, prazo, base legal, cofre) em
> vez de um lugar que deixou de existir. O que sobrou no arquivo é **errata datada em passado**,
> que é a forma que não apodrece.

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
> antes. Navega por rota (`?tab=`), sem rota nova no backend. O contrato de teste da trilha é
> o **UC-INDEX-02** do `casos.md`.
>
> **O PR-4 acrescentou a vista Cofre** — espaço por disco + os 3 achados —, também sem rota
> nova: só um valor a mais no vocabulário de `tab`. Contrato: **UC-INDEX-03**. Duas coisas que valem ler antes de mexer: a agregação mora
> num leitor próprio ([`CofreStatsReader`](../../../../Modules/Arquivos/Services/CofreStatsReader.php))
> porque o assert de LGPD do controller proíbe hash em qualquer método dele — e o que o gate
> protege passou a ser cobrado por assert comportamental sobre o payload; e o Tier 0 aqui é o
> **oposto** do da trilha: `arquivos` tem model e global scope, então repetir o `where` é que
> seria o defeito. Detalhe no [RUNBOOK §5.2](../../../../memory/requisitos/Arquivos/RUNBOOK-index.md).
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
- [x] **PR-4 (cofre)** — 2026-08-25. `?tab=cofre`, espaço por disco + 3 achados, UC-INDEX-03
      com 8 asserções (contadas com `grep -c`, não de memória).
- [x] **PR-3 (retenção)** — 2026-08-25. `?tab=retencao`: KPIs (vence 30/90 · no grace ·
      passou do prazo), a política com a base legal por contexto e a contagem de arquivos,
      e os 4 cards de regra. Leitura pura — a vista MOSTRA o que o `retention-cleanup`
      faria, e diz que ele **não está agendado** (medido no runtime via `Schedule::events()`,
      não deduzido do fonte). Rodar pela tela, avisar titular e purgar seguem sendo a onda 3.
- [x] **Onda 1 COMPLETA** — as 4 vistas do charter existem: acervo · retenção · cofre · trilha.
- [x] **Refino do acervo (2026-08-27)** — a linha ganhou **Baixar** (botão só-ícone com
      `aria-label`, link assinado de 60 min pro `DownloadController` — o endpoint existia desde
      a Sprint 1 e não tinha leitor de UI) e o **"Vinculado a"** passou a mostrar o nome de
      negócio do dono com o tipo técnico em mono embaixo, virando link só pros 2 tipos cuja
      rota foi provada em `route:list` (`ServiceOrder`, `JobSheet`). Buraco medido com a mesma
      sonda em produção e no protótipo: 6 colunas × 7, 0 botões × 3, `mono` em 0 de 6 × 5 de 7.
      UC-INDEX-05 e UC-INDEX-06 no [casos.md](Index.casos.md); detalhe no
      [RUNBOOK §5.4](../../../../memory/requisitos/Arquivos/RUNBOOK-index.md).
      **Segue leitura pura:** classificar e excluir NÃO entraram — não existe endpoint pra
      nenhum dos dois, e a decisão de onde a reclassificação mora é a pendência aberta abaixo.
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
- Contrato de tela: [`prototipo-ui/contrato/arquivos-index.contract.json`](../../../../prototipo-ui/contrato/arquivos-index.contract.json)
  — copy DERIVADA da tela por script (nunca transcrita) e provada presente no alvo.
  O veredito de hoje é do gate, não desta linha: `npm run contrato:check -- <o arquivo>` e
  `npm run contrato:map:check`. _Fatos datados, preservados:_ ele saiu do PR-0 (`d738bdc`)
  porque o job `Preflight + contratos ativos` roda `git ls-files '*.contract.json'` e exige
  que **todos** passem (só o `EXEMPLO` é isento), e um contrato apontando pro stub reprovaria
  por construção; em 2026-08-25 essa razão caducou — o stub acabou e o `.tsx` ganhou as
  âncoras (`cabecalho`, `acervo-filtros`, `acervo` no PR-1; `abas`, `trilha-filtros`, `trilha`
  no PR-2). **O PR-4 acrescentou `cofre`, `cofre-discos` e `cofre-achados`, e a aba "Cofre"
  entre Acervo e Trilha** — logo o contrato nasceu descrevendo 2 vistas e a tela já tem 3;
  fechar essa defasagem é trabalho do PR que reconciliar os dois, não desta linha. Falta só a
  retenção. O contrato registra as pendências em `_pendente_w`, junto da divergência de copy
  medida contra o protótipo (`Payload` × `Detalhe`), que é decisão [W]. Conteúdo do rascunho
  original preservado no commit `943cc23`.

  ⚠️ _Correção de fato (2026-08-25, medido com `DesignSync.list_files` no projeto Cowork
  `019dcfd3`): a nota anterior dizia que `cowork-inbox/modulos-faltantes/arquivos.contract.json`
  **"nunca existiu no repositório nem no espelho do Cowork"**. A primeira metade continua
  verdadeira; a segunda induz a erro — **o arquivo existe no projeto Cowork vivo** e foi lido
  neste dia. Ele é o contrato F1 das 4 vistas, e não deve ser aplicado literalmente: os chips
  que ele lista para o acervo incluem `common` e `public`, os dois valores que o
  [#6244](https://github.com/wagnerra23/oimpresso.com/pull/6244) provou não existirem no enum
  do banco. Portá-lo ao pé da letra reintroduziria aquele bug._
- Casos: [Index.casos.md](Index.casos.md)
