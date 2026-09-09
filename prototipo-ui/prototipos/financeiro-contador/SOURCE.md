# Protótipo — Contador parceiro (Financeiro / Configurações)

> **Fonte de design GERADA pelo designer-agente** ([ADR 0282](../../../memory/decisions/0282-protocolo-v2-colapso-ratificacao.md) §0.1). Cobre `Financeiro/Configuracoes/Contador` — a 7ª e última das telas de dinheiro sem fonte visual.

## 1 · Status — PROPOSTA de forma, não lei

A tela **já existe em produção** (350 linhas de `.tsx`, `related_us: [US-FIN-037]`) e declara `related_prototype: n/a (sem protótipo Cowork …)`. **Não promover a âncora sem decisão [W].** O `n/a` permanece; nenhum charter foi tocado.

## 2 · Ausência confirmada — os dois donos, medido 2026-09-09

| dono | método | resultado |
|---|---|---|
| repo + espelho | `rg -ci 'contador\|advisor'` nos 3 `configuracoes-*.jsx` | **rc=1** (zero) — com **controle positivo** na mesma sonda: `configura` → **15 hits**, logo ela funciona |
| Cowork vivo | `DesignSync.list_files` **por ID** (`019dcfd3…`) | nenhuma tela de acesso de terceiro |

E a fonte mais próxima declara outro escopo: `configuracoes-page.jsx:3` se apresenta como *"Configuração da empresa (BusinessController) — 16 abas"*. Acesso de contador não é configuração de empresa — é concessão a um **terceiro**, com consentimento.

## 3 · O que foi desenhado

| bloco | conteúdo |
|---|---|
| lista | acessos ativos: contador (nome + e-mail), **CNPJ mascarado**, escopo em chips, data, botão Revogar |
| form | CNPJ · nome · e-mail · telefone · **escopo** (visão unificada e/ou relatórios) |
| consentimento | bloco LGPD Art. 7º, II — checkbox **opt-in**, obrigatório |
| revogação | diálogo próprio (`role="dialog"` + `aria-modal`), com "Manter acesso" / "Revogar" |

Estados desenhados: **lista vazia**, **lista com acessos**, **CNPJ incompleto**, **e-mail inválido**, **escopo vazio**, **sem consentimento**, **concedido** (flash), **revogado** (flash).

## 4 · Os três anti-hooks do charter mandam no desenho — e estão aplicados

O charter tem anti-hooks que não são estética, são regra. Cada um virou uma decisão de desenho verificável:

| anti-hook | como o desenho cumpre | medido |
|---|---|---|
| *"Não expor CNPJ completo no front — sempre `advisor_cnpj_mascarado` do backend"* | a lista **nunca monta** CNPJ; e o registro recém-criado também entra mascarado | `12.***.***/0001-**` · `31.***.***/0001-**` na lista, e o novo idem |
| *"Não transformar o consent LGPD em opt-out — é checkbox obrigatório opt-in"* | nasce **desmarcado** e **trava** o botão | `consentInicial: false` · botão desabilitado até marcar |
| *"Ações destrutivas via AlertDialog controlado (sem `window.confirm`)"* | diálogo próprio com `aria-modal="true"` | `window.confirm` **intacto** (nunca chamado) |

A máscara de digitação existe só no campo de entrada — é conveniência de quem digita, e só os 14 dígitos seguem para o backend, como o `grant()` espera.

## 5 · Âncora do domínio — nada inventado

| o que | de onde |
|---|---|
| shape da lista (`advisor_nome` · `advisor_email` · `advisor_cnpj_mascarado` · `granted_at_label` · `can_view_unificado` · `can_view_reports` · `has_consent`) | `AdvisorAccessController::index` (`:41-72`) |
| `cnpj_contador` `size:14` + `regex:/^\d{14}$/` · `nome` max 200 · `email` `email:rfc` max 191 · `telefone` max 20 | `AdvisorAccessController::grant` (`:92-95`) |
| breadcrumb Financeiro › Configurações › Contador | o próprio controller monta (`:69-72`) |
| escopo (unificada / relatórios), consentimento LGPD, revogação com confirmação deliberada, read-only sempre | `Contador.charter.md` §Goals · §Non-Goals |

O charter é explícito em quatro Non-Goals que respeitei: **não** é a tela de login do contador; **não** edita cadastro do advisor depois de criado; **não** concede escrita; **não** gerencia múltiplos negócios.

## 6 · Conformidade e runtime — medido, não screenshot

Zero cor crua. **13 tokens** do DS, prefixo `.ct-`. Host estático com as 3 folhas do shell, viewport **1280**:

| medida | resultado |
|---|---|
| console | **0 erro** |
| tokens não resolvidos | **0** de 13 (controle positivo e negativo) |
| scroll horizontal @1280 | **false** |
| CNPJ `123` | *"O CNPJ precisa ter 14 dígitos."* |
| CNPJ 14 dígitos | vira `12.345.678/0001-99` no campo, sem erro |
| e-mail inválido | *"Informe um e-mail válido."* |
| escopo desmarcado | *"Escolha ao menos uma coisa que ele pode ver."* + botão travado |
| sem consentimento | botão **travado**; ao marcar, libera |
| conceder | flash + lista 2 → 3 · **novo CNPJ entra mascarado** · consentimento volta a desmarcado |
| revogar | diálogo `aria-modal` → lista 3 → 2 + flash · `window.confirm` nunca usado |

## 7 · O que este protótipo NÃO decide

- **Se vira âncora** — decisão [W] (§1).
- **A forma da produção** — divergência é dado para `design-diff --probe`, nunca veredito no olho (LC-06).
- **O texto jurídico do consentimento.** Escrevi uma frase de consentimento legível, mas redação de cláusula LGPD é matéria de quem responde por ela — **[E] é advogada** e a decisão é dela e do [W], não do desenho.

## Refs
[ADR 0282](../../../memory/decisions/0282-protocolo-v2-colapso-ratificacao.md) §0.1 · [UI-0013](../../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) · [UI-0029](../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md) · [INVENTÁRIO §5.3/§5.7](../../../memory/requisitos/_DesignSystem/INVENTARIO-ANCORAS-2026-09-09.md) · precedente [#7145](https://github.com/wagnerra23/oimpresso.com/pull/7145)
