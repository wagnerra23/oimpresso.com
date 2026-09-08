---
page: /asset/settings
component: resources/js/Pages/Patrimonio/Configuracoes.tsx
owner: wagner
status: draft
parent_module: AssetManagement
related_us: [US-ASSET-W05]
related_adrs: [0394-endereco-de-ui-do-patrimonio-pages-patrimonio, 0104-processo-mwart-canonico-unico-caminho, 0093-multi-tenant-isolation-tier-0, 0180-sidebar-v3-5-grupos-ghosts-header]
related_prototype: prototipo-ui/cowork/patrimonio-page.jsx
related_runbook: memory/requisitos/AssetManagement/RUNBOOK-configuracoes.md
tier: B
charter_version: 1
last_validated: "2026-09-08"
---

# Page Charter — Patrimonio/Configuracoes (DRAFT)

> **Sexta tela da frente do Patrimônio, e a menor.** Não funda nada: herda o `_shared/` e o
> padrão de charter/casos/teste fundados por [Bens](./Bens.charter.md) (PR #7035).
> Padrão de Tela: **formulário de configuração de módulo**, não lista.

> **Pasta ≠ módulo.** A tela mora em `resources/js/Pages/Patrimonio/` por decisão [W]
> ([ADR 0394](../../../../memory/decisions/0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md));
> o PHP vive em `Modules/AssetManagement/` e os requisitos em
> `memory/requisitos/AssetManagement/`. **Não existe `Modules/Patrimonio`.** O campo
> `related_runbook` acima é o que destrava o hook `block-mwart-violation`, que deriva o
> RUNBOOK do nome da pasta de `Pages/` e procuraria `requisitos/Patrimonio/` — pasta que não
> existe nem deve existir.

## Mission

Dar ao **administrador** da empresa o painel das duas coisas que o módulo guarda por business:
os **prefixos** que geram os códigos de bem, alocação, devolução e manutenção, e as
**notificações de manutenção** — quem recebe, se sai e-mail além do sino do sistema, e o texto
de cada mensagem. Tela de configuração inicial, não de operação diária.

## Goals — Features (faz)

- Os **quatro** prefixos que o backend grava: `asset_code_prefix`, `allocation_code_prefix`,
  `revoke_code_prefix`, `asset_maintenance_prefix`.
- Destinatários da notificação "enviado para manutenção" — multi-seleção sobre os usuários do
  business (`User::forDropdown`).
- Os **dois** interruptores de e-mail (`enable_asset_send_for_maintenance_email` e
  `enable_asset_assigned_for_maintenance_email`), cada um revelando o assunto e o corpo do seu
  template, como o Blade fazia.
- As **tags disponíveis** de cada bloco, literais como no Blade — elas são o contrato de
  `AssetUtil::replaceEmailTags()`, e não coincidem entre os dois blocos. Vêm do backend na prop
  `tags`, para não virarem lista decorativa mantida à mão no front.

**Props do `Inertia::render`:** `settings` · `templates` · `usuarios` · `tags`. Não há prop de
permissão: quem chega na tela é admin por construção (a guarda barra antes), então um
`permissoes` aqui seria campo que nunca varia.
- Sub-navegação do módulo **derivada** de `shell.menu`, via `_shared/PatrimonioSubNav`,
  nunca declarada aqui.
- Estados: carregado · salvando · sucesso · erro do backend · 403 (não-admin não vê a tela).

## Non-Goals — Features (NÃO faz)

> Proposta [CC] a partir da medição desta onda — **[W] aprova antes de `status: live`.**
> Cada item vira Pest GUARD quando a onda correspondente entrar. Os três primeiros são
> **adiamento com motivo** (§5 do RUNBOOK); os demais são limite real.

- ❌ NÃO traz editor WYSIWYG para o corpo do e-mail. O Blade usa TinyMCE
  (`index.blade.php:44-50`); editor rich-text no React é **dependência nova**, que exige ADR
  ([proibicoes](../../../../memory/proibicoes.md) §Código). O corpo é textarea monoespaçada,
  com o rótulo dizendo que o conteúdo é HTML.
- ❌ NÃO oferece os três interruptores do protótipo (`patrimonio-page.jsx:589`) — "Garantia
  expirando em 30 dias" e "Alocação registrada pro colaborador" **não têm backend**: nenhuma
  coluna, nenhum job, nenhuma `Notification`. As duas notificações que existem são
  `AssetSentForMaintenance` e `AssetAssignedForMaintenance`.
- ❌ NÃO exibe a linha "Retenção do histórico: 5 anos" do rodapé do protótipo. Ela aponta
  `Config/retention.php`, da **thread 05, BARRADA** pela lápide §5 de 2026-07-27 (num ERP não
  se apaga PII); e as tabelas que aquele arquivo declara (`am_assets`, `am_maintenance_logs`)
  **não existem** — migration nenhuma as cria.
- ❌ NÃO mostra "o próximo código será X" ao lado de cada prefixo, como o protótipo sugere
  (`PAT-0012 é o próximo`) — a sequência é calculada na gravação do bem, e não há endpoint que
  a exponha sem duplicar a regra.
- ❌ NÃO valida formato de prefixo (tamanho, caracteres, unicidade). O Blade não valida, e
  inventar regra aqui mudaria o contrato de uma coluna que já tem dado gravado.
- ❌ NÃO cria rota. `Route::resource('settings', …)` já serve `GET`/`POST` `/asset/settings`.
- ❌ NÃO afrouxa a guarda `is_admin`. Ver Anti-hooks.

## Anti-hooks (NÃO faz automaticamente)

> Mesma ressalva: proposta [CC], [W] aprova antes de `live`.

- ❌ NÃO manda `false` para as chaves `enable_*`. O `store()` decide por
  `$request->has(...)`, não `boolean(...)`: mandar `false` faria `has()` devolver **true** e
  gravaria **1** — desligar deixaria de funcionar. A tela **omite a chave** quando desmarcada,
  replicando o checkbox HTML. Preserva o contrato sem tocar no controller.
- ❌ NÃO renomeia, normaliza nem "conserta" chave do JSON `asset_settings` — inclusive o typo
  de `send_for_maintenence_recipients`, que é contrato gravado.
- ❌ NÃO acrescenta chave ao payload sem acrescentá-la ao `only()` do `store()`: o controller
  **regrava o JSON inteiro**, então chave fora do `only()` seria perdida no primeiro save.
- ❌ NÃO deriva, arredonda nem preenche prefixo sozinha. Campo vazio é campo vazio — o backend
  já trata a ausência.
- ❌ NÃO dispara notificação de teste, não envia e-mail, não toca `NotificationTemplate` fora
  do submit explícito do usuário.
- ❌ NÃO renderiza aba que não navega — a sub-navegação deriva de `shell.menu` (são 6 ghosts
  vivos, não as 7 do protótipo).

## Tier 0 declarado — prefixo é numeração de bem já cadastrado

⚠️ Os prefixos **não são rótulo cosmético**: `AssetService`, `AssetAllocationService`,
`AssetMaintenanceService` e `RevokeAllocatedAssetController` os leem para **gerar o código** do
próximo registro. Mudar o **formato** do JSON (renomear chave, mudar tipo, aninhar) quebraria a
geração de código de bens já cadastrados — é Tier 0 de dados: exige migration + backfill e
decisão do [W].

Esta onda **não muda o shape**: lê pelas mesmas chaves e grava pelo mesmo `store()`, intocado.
O que muda é só quem desenha o formulário.

## UX Targets

- Cabe em 1280px (monitor do piloto) sem scroll horizontal.
- Duas seções empilhadas, na ordem do Blade (Prefixos → Notificações) — o Blade as apresentava
  como abas laterais; empilhar é mais honesto num formulário de 11 campos que cabe na tela e
  tem **um** botão de salvar no fim.
- O bloco de assunto/corpo aparece só quando o interruptor está ligado, como no Blade
  (`index.blade.php:52-64` fazia isso por jQuery; aqui é estado do React).
- Nenhum atalho de teclado é anunciado nesta onda.

## Pendências antes de `status: live`

1. [W] aprovar os Non-Goals e Anti-hooks acima (hoje são proposta [CC]).
2. Screenshot aprovado por [W] (gate visual F1.5).
3. `Configuracoes-visual-comparison.md` — comparação **medida** contra o protótipo
   (`design-diff --probe` nos dois lados), não no olho. Vai registrar as três divergências já
   conhecidas: o protótipo tem 3 prefixos (o backend tem 4), 3 interruptores sem backend, e a
   linha de retenção que aponta arquivo barrado.
4. Decidir se o WYSIWYG do corpo de e-mail volta (exige ADR de dependência).
5. **US no `SPEC.md`** — o `related_us` aponta `US-ASSET-W05` (migração Blade→Inertia), que
   segue marcada lá como backlog feature-wish, embora a ADR 0394 e o `SCOPE.md` já a tenham
   liberado. Mesma pendência que [Bens](./Bens.charter.md) registrou; fora do prefixo desta onda.

## Refs

- RUNBOOK: [`memory/requisitos/AssetManagement/RUNBOOK-configuracoes.md`](../../../../memory/requisitos/AssetManagement/RUNBOOK-configuracoes.md)
- Casos: [`./Configuracoes.casos.md`](./Configuracoes.casos.md)
- Fonte visual: `prototipo-ui/cowork/patrimonio-page.jsx` (aba `config`, `:589`) — **alvo**, não
  decisão de produto
- Playbook: `prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/11-configuracoes.md`
- Tela irmã que fundou o padrão: [`./Bens.charter.md`](./Bens.charter.md)
