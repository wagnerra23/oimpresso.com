# Protótipo — Plano de assinatura: Criar + Editar

> **Fonte de design GERADA pelo designer-agente** ([ADR 0282](../../../../../memory/decisions/0282-protocolo-v2-colapso-ratificacao.md) §0.1), não importada do Cowork. Cobre `RecurringBilling/Planos/Create` e `.../Edit` — duas das 7 telas que tocam valor e não tinham fonte visual.

## 1 · Status — PROPOSTA de forma, não lei

⚠️ As duas telas **já existem em produção** (395 + 407 linhas de `.tsx`, charter `status: live`, `related_us: [US-RB-001]`) e declaram `related_prototype: n/a (herda PT-02 …)`.

- **NÃO promover a `related_prototype`** sem decisão [W] — pela cadeia FORMA da [UI-0029](../../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md), promover tornaria este desenho soberano sobre telas vivas que definem **quanto o cliente é cobrado**.
- O `n/a` **permanece**; é declaração consciente, não defeito ([§5 2026-08-28](../../../../../memory/proibicoes.md)). Nenhum charter foi tocado.

## 2 · Ausência confirmada — os dois donos, medido 2026-09-09

| dono | método | resultado |
|---|---|---|
| repo + espelho | `rg --hidden` + leitura do hub | a aba **"planos"** do `cobranca-recorrente-page.jsx:369` é um `<Placeholder>` de 3 elementos (def. `:332-338`) |
| Cowork vivo | `DesignSync.list_files` **por ID** (`019dcfd3…`) | nenhum formulário de plano |

**E o hub não serviria mesmo se desenhasse:** `cobranca-recorrente-page.jsx:2` declara *"Reescreve a RecurringBilling do git na linguagem do DS"* — é **porte reverso**, e o texto do próprio placeholder diz *"Espelha /recurring-billing/planos do git"*. Ancorar nele seria ancorar a tela nela mesma ([§5 2026-06-05](../../../../../memory/proibicoes.md)). Os 3 charters irmãos (`Planos/Index`, `Faturas/Index`, `Configuracoes/Index`) já carregam essa anotação desde o [#7099](https://github.com/wagnerra23/oimpresso.com/pull/7099). E `Create`/`Edit` **nem são abas** daquele hub — há menos ainda.

## 3 · O que foi desenhado

Um formulário único (o charter proíbe wizard), em 3 cards + trilho de prévia:

| bloco | campos |
|---|---|
| **Identificação** | `name`* · `slug` (derivado do nome quando vazio) · `descricao_curta` · `description` |
| **Cobrança** | `valor`* · `ciclo`* (5 opções) · `trial_days` · `ativo` · **bloco condicional** `ciclo_dias` quando ciclo = personalizado |
| **Documento fiscal** | `fiscal_type` (3 opções) · **condicional** `fiscal_cfop` (NF-e) ou `fiscal_servico` (NFS-e) |
| **trilho** | prévia "como o cliente vê" — preço, periodicidade, slug efetivo, teste, fiscal, situação |

Estados desenhados: **criar vazio** (defaults do controller), **editar preenchido**, **ciclo personalizado**, **fiscal NF-e**, **fiscal NFS-e**, e o **retorno 422 do servidor** (alerta + erros inline + `aria-invalid`).

## 4 · Âncora do domínio — nada inventado

| o que | de onde |
|---|---|
| colunas e tipos (`valor` decimal(15,2), `ciclo` enum de 5, `ciclo_dias` smallint, `trial_days`, `ativo`, unique `business_id+slug`) | migration `2026_05_06_001000_create_rb_plans_table` |
| limites (`name` 150 · `descricao_curta` 200 · `description` 2000 · `slug` 80 · `trial_days` 0-90 · `ciclo_dias` 1-365 · `valor` ≥ 0 · CFOP/serviço 8) | `StorePlanRequest::rules()` |
| condicionais (`ciclo_dias` `required_if:ciclo,custom` · `fiscal_cfop` `required_if:fiscal_type,nfe` · `fiscal_servico` `required_if:...,nfse`) | idem |
| mensagens de erro do estado 422 | `StorePlanRequest::messages()` |
| defaults do Criar (`monthly` · `trial_days` 0 · `ativo` true · `fiscal_type` none) | `PlanController::create()` (`:65`) |
| shape do Editar | `PlanController::edit()` (`:115`) |
| forma (form único, header + Voltar, erros inline, Salvar/Cancelar, `*` nos obrigatórios) | `Planos/Create.charter.md` §Goals |

⚠️ **O charter proíbe validação client-side própria** — *"duplicaria FormRequest; server fala a palavra final"* (§UX Anti-patterns). Então este desenho **não tem regra própria**: o único gate no cliente é `required`/`min`/`max` do HTML, e todo erro exibido é o que volta do `FormRequest`. Isso é o oposto do irmão `transaction-payment/`, cujo charter **pede** validação de cliente — a diferença é deliberada e vem de cada charter.

## 5 · Conformidade de token

Zero cor crua (`conformance-gate` = LEI). **13 tokens** do DS, prefixo `.pl-`. Medido no browser com as 3 folhas do shell: **0 dos 13 não resolvem**, sonda com controle **positivo e negativo**.

## 5-bis · Validação de runtime — medida, não screenshot

Host estático, React 18.3.1 + Babel, viewport **1280**:

| medida | resultado |
|---|---|
| console | **0 erro** |
| tokens não resolvidos | **0** de 13 |
| scroll horizontal @1280 | **false** |
| opções de ciclo | **5** — igual ao enum da migration |
| slug derivado | `Manutencao Mensal — Plano Prata` → `manutencao-mensal-plano-prata` (acento e travessão tratados) |
| ciclo personalizado | abre "Ciclo personalizado" com `A cada quantos dias*` |
| fiscal NF-e / NFS-e | `CFOP*` / `Código do serviço*` |
| estado 422 | alerta + 2 erros inline com o texto do `StorePlanRequest` + **2** `aria-invalid` |
| valor no Editar | `349,90` — **2 casas**, e a prévia acompanha |

⚠️ **Dois defeitos meus, achados na medição e corrigidos:** (1) o Editar nascia com `349,9` — uma casa —, que num campo de dinheiro `decimal(15,2)` é o descuido que convida truncamento ([§5 2026-06-05](../../../../../memory/proibicoes.md)); agora nasce com `toFixed(2)`. (2) o "equivale a" aparecia em plano **mensal**, repetindo o próprio preço; agora só aparece quando acrescenta informação (ciclo ≠ 30 dias).

## 6 · Uma coisa que este desenho PROPÕE e o backend não tem

O trilho mostra **"Equivale a R$ X / mês"** para ciclos ≠ mensal (anual `R$ 349,90` → `R$ 28,76/mês`). **Isso não existe no backend** — é `valor / (dias / 30)`, calculado só para exibir, e a aproximação de 30 dias é escolha minha.

Está aqui porque é a pergunta que um operador faz ao comparar planos de ciclos diferentes. Mas: **é proposta, não domínio**. Se for adotado no `.tsx`, vira número sobre dinheiro na tela e cai na regra-mestre de [proibicoes.md](../../../../../memory/proibicoes.md) — dois caminhos de prova + antes→depois + [W]. Quem não quiser, apaga o bloco: nada mais depende dele.

## 7 · Aviso Tier 0 para quem for aplicar isto no `.tsx`

As duas telas gravam `valor` (`decimal(15,2)`). O desenho **não muda cálculo nenhum**, mas quem levar ao `.tsx` mexe em VALOR: o incidente `num_uf` de 2026-06-05 nasceu de float locale-ambíguo no submit. Vale a regra inteira.

## 8 · O que este protótipo NÃO decide

- **Se vira âncora** — decisão [W] (§1).
- **A forma da produção** — divergência é *dado para comparar* (`design-diff --probe`), nunca veredito no olho (LC-06).
- **Os 4 Non-Goals do charter** (wizard · preview de cobrança simulada · importar de template · upload de imagem) seguem fora: são decisão [W], e desenhar a resposta é inferir o que `charter-write` é proibida de inferir.

## Refs
- [ADR 0282](../../../../../memory/decisions/0282-protocolo-v2-colapso-ratificacao.md) §0.1 · [ADR UI-0013](../../../../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) · [UI-0029](../../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)
- [INVENTÁRIO 2026-09-09](../../../../../memory/requisitos/_DesignSystem/INVENTARIO-ANCORAS-2026-09-09.md) §5.3 e §5.7 · anotação irmã: [#7099](https://github.com/wagnerra23/oimpresso.com/pull/7099)
- precedente de forma: [`prototipos/nfe-tributacao/SOURCE.md`](nfe-tributacao.md) ([#7145](https://github.com/wagnerra23/oimpresso.com/pull/7145)) · irmãos: [`payment-gateway-cnab`](payment-gateway-cnab.md) · [`transaction-payment`](transaction-payment.md)
