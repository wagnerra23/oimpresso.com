# Protótipo — Retorno CNAB · Payment Gateways

> **Fonte de design GERADA pelo designer-agente** ([ADR 0282](../../../memory/decisions/0282-protocolo-v2-colapso-ratificacao.md) §0.1 — *"o agente gera, ancorado no DS canon"*), não importada do Cowork. Cobre `Settings/PaymentGateways/CnabRetorno`, uma das 7 telas que tocam valor e não tinham fonte visual.

## 1 · Status — PROPOSTA de forma, não lei

⚠️ **Leia antes de promover a âncora.** A tela **já existe em produção** (372 linhas de `.tsx`, com charter e `casos.md`) e declara `related_prototype: n/a (herda PT-02 Form-Drawer; segue o Padrão de Tela)`. Este protótipo nasceu **depois** dela. Portanto:

- **NÃO promover a `related_prototype`** sem decisão [W]. A cadeia FORMA da [UI-0029](../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md) é *protótipo > teste > casos > charter* — promover tornaria este desenho **soberano sobre uma tela viva que mexe em baixa de cobrança**.
- O `n/a` do charter **permanece**. Ele é declaração consciente que a máquina reconhece (`ancora.mjs::ehDeclaracaoNa` → *"NÃO entra no anchor-content-check"*), **não um defeito** — lápide [§5 2026-08-28](../../../memory/proibicoes.md). Nenhum charter foi tocado por este PR.
- Enquanto não houver decisão [W], isto é **material de comparação** (um dos lados do `design-diff`), não alvo de `visual-regression`.

## 2 · Ausência confirmada — os dois donos, medido 2026-09-09

Claim de ausência exige repo **e** `DesignSync` ([§5 2026-08-07](../../../memory/proibicoes.md)):

| dono | método | resultado |
|---|---|---|
| repo + espelho | `rg -l --hidden 'cnab-retorno\|CnabRetorno' prototipo-ui/` | **0** |
| Cowork vivo | `DesignSync.list_files` no projeto de telas **por ID** (`019dcfd3…`, não-listado — `list_projects` não o enxerga, §5 2026-08-11) | nenhuma tela de importação CNAB |

⚠️ **Errata do recibo que eu herdei — o instrumento estava errado.** Buscar o nome literal `cnab-retorno`/`CnabRetorno` dá 0, mas isso é grep de string literal, o instrumento que a lápide [§5 2026-08-18](../../../memory/proibicoes.md) proíbe. Buscando o **termo de domínio** `cnab`, o espelho tem **15 ocorrências em 5 arquivos**, e existem **dois** `SheetRemessaRetorno`:

- `prototipo-ui/cowork/boletos-page.jsx:509` — sheet 560px, lista de arquivos REM/RET (`REMESSAS_CNAB` em `:75`)
- `prototipo-ui/cowork/pg-cobranca-page.jsx:863` — o mesmo componente, dentro da Cobrança do PaymentGateway

**Por que nenhum dos dois serve de âncora desta tela:** os dois são um sheet de **lista** dentro da Cobrança — *"Gerar remessa do dia"* + *"Importar arquivo retorno"* + histórico de arquivos. Não têm dropzone, nem validação de formato/limite, nem contadores por arquivo, que são exatamente os G1–G4 do charter desta tela. E pertencem a **outra US**: a `US-FIN-018` (`Financeiro/SPEC.md:849`), cujo `**Implementado em:**` aponta `resources/js/Pages/Financeiro/Cobranca/_components/SheetRemessaRetorno.tsx` — outra superfície, com dados mock. Contados no repo inteiro, são **quatro** artefatos com esse nome (os 2 do espelho, o `prototipos/payment-gateway-ui/cobranca-page.jsx`, e o vivo).

## 3 · O que foi desenhado

Uma tela, três zonas — dropzone como ação primária, histórico secundário abaixo (UX target do charter):

| zona | conteúdo | goal |
|---|---|---|
| credencial | `gateway_key` · `nome_display` · ambiente · ativo | contexto: qual banco se está conciliando |
| envio | dropzone drag&drop, extensões e limite reais, prévia nome/tamanho, recusa no front | G1 + G2 |
| histórico | tabela densa com os 4 contadores persistidos + estado + erros expansíveis por linha | G3 + G4 |
| rodapé | o que cada coluna conta, em PT-BR de operador | domínio |

Estados desenhados: **vazio** (EmptyState), **carregando** (skeleton do `Inertia::defer`), **populado**, **arquivo recusado** (2 motivos distintos), **erros expandidos**.

## 4 · Âncora do domínio — nada inventado

Todo campo, limite e rótulo saiu de fonte canônica. Nenhum veio do `.tsx` vivo (porte reverso é proibido — [§5 2026-06-05](../../../memory/proibicoes.md) e 2026-08-28):

| o que | de onde |
|---|---|
| props `credential` / `uploads[]` / `limites`, rota POST campo `arquivo` | `Modules/PaymentGateway/Http/Controllers/Settings/PaymentGatewaysCnabRetornoController.php` |
| extensões `txt · ret · cnab · rem` e limite **8192 KB** | `EXT_ACEITAS` e `TAMANHO_MAX_KB` do mesmo Controller |
| colunas da linha do histórico (`arquivo_nome_original`, `arquivo_tamanho_bytes`, `processado_em`, `qtd_*`, `erros`) | migration `2026_05_26_120100_create_cnab_retorno_uploads_table` |
| o que cada contador significa (rodapé) | `CnabRetornoProcessor.php` — `switch` de `OCORRENCIA_LIQUIDADA` / `_BAIXADA` / `_ENTRADA` |
| "240 ou 400, detectado automaticamente" | docblock do processor (*"auto-detecta layout 240 vs 400 + banco"*) |
| "reenviar o mesmo arquivo é seguro" | `aplicarLiquidacao` — *"se `paga_em` já setado, skip dispatch (rerun seguro)"* |
| dropzone primário, histórico secundário, EmptyState, skeleton, zero cor crua | `CnabRetorno.charter.md` §Goals e §UX targets |

O módulo tem **11 drivers CNAB** (`Ailos · BB · Banrisul · Bradesco · BTG · Caixa · Cresol · Itaú · Santander · Sicoob · Sicredi`) — por isso a credencial fica no topo: o mesmo formato serve bancos diferentes.

## 5 · Conformidade de token

Zero cor crua (`conformance-gate` = LEI). **13 tokens** do DS, prefixo `.cn-` pra não colidir com outros bundles. Medidos no browser com as 3 folhas do shell na ordem do `oimpresso.com.html`:

- **0 dos 13 não resolvem.** Sonda validada por controle positivo (`--fg` → `oklch(0.137 0.036 258.5)`) **e** negativo (`--nao-existe-mesmo` → vazio, logo a sonda discrimina).
- ⚠️ A primeira versão da sonda deu "46 tokens não resolvem" e estava **errada**: eu duplicava o prefixo (`'--' + '--fg'`). O controle positivo é o que denunciou — as cores computadas resolviam enquanto a sonda dizia que não.
- Não copiei a paleta do `prototipos/perfil/` — o `SOURCE.md` do `nfe-tributacao` mediu que **8 dos 14 tokens de lá não existem**.

## 5-bis · Validação de runtime — medida, não screenshot

Renderizado num host estático com as 3 folhas do shell, React 18.3.1 + Babel standalone, viewport **1280** (alvo do charter). Protótipo que não renderiza não é fonte de design.

| medida | resultado |
|---|---|
| console | **0 erro** |
| tokens não resolvidos | **0** de 13 |
| `scrollWidth > clientWidth` da página @1280 | **false** (1280 / 1280) |
| tabela larga | tem `overflow-x: auto` própria — o corpo da página não rola lateralmente |
| colunas do histórico | 8 — as 4 de contador batem com as 4 colunas da migration |
| limites na tela | `.txt, .ret, .cnab, .rem` até `8 MB` — iguais ao Controller |
| G2 · extensão recusada | `.xlsx` → *"Extensao .xlsx nao aceita. O banco entrega .txt, .ret, .cnab, .rem."* · envio desabilitado |
| G2 · tamanho recusado | 9,00 MB → *"Arquivo de 9.00 MB passa do limite de 8 MB."* · envio desabilitado |
| G4 · erros expansíveis | 0 → 2 linhas ao abrir, `aria-expanded="true"` |
| datas | `08/09/2026 22:14` — PT-BR |
| modo escuro | tokens viram (card `oklch(1 0 0)` → `oklch(0.3 0.008 240)`) |

⚠️ **Dois defeitos do host, não do protótipo, e valem registro.** (1) A primeira montagem gerou `<\/script>` — idioma de string **JS** usado dentro de string **Python** — e o parser HTML engoliu tudo depois do primeiro script; sobrou 1 tag de 5. (2) O bootstrap não pode esperar `load`: ele dispara antes de o Babel compilar os `text/babel` (achado já registrado no `SOURCE.md` do `nfe-tributacao`). Instrumento antes do artefato.

## 6 · Achado Tier 0 — registrado, NÃO desenhado

O `CnabRetornoProcessor` incrementa **6** contadores — `paga`, `cancelada`, `vencida`, `registrada`, `sem_match`, `ignorada` (contados com `rg -o`, 3+3+3+3+2+2 incrementos). A migration tem **4** colunas e o `update()` do `finalizarUpload` persiste **4**. **`sem_match` e `ignorada` são calculados e descartados.**

São justamente as linhas que exigem follow-up humano: `sem_match` é pagamento chegando **sem cobrança correspondente**, e `ignorada` são ocorrências de protesto, alteração e erro. Num arquivo de 200 linhas, o operador vê os quatro contadores somarem 150 e **não tem como saber** o que houve com as outras 50.

**Este protótipo desenha os 4 que existem.** Não desenhei os 2 ausentes: fazê-lo seria propor mudança de comportamento sobre VALOR disfarçada de desenho. Persistir e expor os dois é **decisão [W]** (regra-mestre de [proibicoes.md](../../../memory/proibicoes.md)), e exigiria migration + mudança no processor.

## 7 · O que este protótipo NÃO decide

- **Se vira âncora** — decisão [W] (§1).
- **A forma da produção.** Onde diverge do `.tsx` vivo, a divergência é *dado para comparar*, não veredito — o caminho é `design-diff --probe` nos dois lados, nunca leitura no olho (LC-06).
- **As 3 perguntas abertas do charter** (`NG1` reprocessar arquivo enviado · `NG2` baixar o original/relatório · `NG3` upload em lote) seguem `(revisar Wagner)`. Não as desenhei — `charter-write` é proibida de inferir Non-Goal, e desenhar a resposta é a mesma inferência por outro caminho.

## Refs
- [ADR 0282](../../../memory/decisions/0282-protocolo-v2-colapso-ratificacao.md) §0.1 — o Code é o designer-agente e **gera**
- [ADR 0170](../../../memory/decisions/0170-bancos-nativos-top5-drivers-separados.md) — drivers de banco separados (Onda 4f.0, origem desta tela)
- [ADR UI-0013](../../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) — Constituição UI v2
- [ADR UI-0029](../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md) — protótipo soberano na FORMA (por que §1 importa)
- [INVENTÁRIO 2026-09-09](../../../memory/requisitos/_DesignSystem/INVENTARIO-ANCORAS-2026-09-09.md) §5.3 e §5.7 — o recibo da ausência e a errata
- precedente de forma: [`prototipos/nfe-tributacao/SOURCE.md`](../nfe-tributacao/SOURCE.md) ([#7145](https://github.com/wagnerra23/oimpresso.com/pull/7145))
