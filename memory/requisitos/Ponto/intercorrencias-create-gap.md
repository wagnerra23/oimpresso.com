---
id: requisitos-ponto-intercorrencias-create-gap
tela: Ponto/Intercorrencias/Create (/ponto/intercorrencias/create)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Intercorrencias/Create.tsx
gerado_em: 2026-09-29
---

# GAP-SPEC — Ponto/Intercorrencias/Create

> **Origem:** thread `20-gap-intercorrencias.md` do playbook do Ponto. Decisões citadas só existem em
> `ATA-DECISOES-2026-09-14.md`: **D-PONTO-DETALHE** (rota própria), **D-INTERC-ANEXO** (INCORPORA; atestado
> é dado de saúde, tratado como PII desde o primeiro commit).
> **Protótipo medido nesta sha:** `ponto-telas.jsx` @ `2e3f8adb4e`, símbolo `FormIntercorrencia`
> (`:146-201`). O mesmo símbolo serve Create e Edit (`registro` decide o modo, `:196`). Este gap cobre
> só o Create; o Edit tem charter próprio que proíbe IA (`Edit.charter.md:60`) e não foi medido aqui.
> **Vivo medido nesta sha:** `resources/js/Pages/Ponto/Intercorrencias/Create.tsx` @ `e4289e688`
> (453 linhas). Toda linha abaixo saiu de `grep -n`. **Re-medido em 2026-09-29:** protótipo após o
> handoff 43 (`FormIntercorrencia` segue em `:146-201`, `:196` e `Edit.charter.md:60` conferem) e
> vivo com 468 linhas; as citações de linha abaixo são desta data.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho da página | **Paridade desde 2026-09-29 (thread 28, handoff 43).** Vivo: `os-page-h` com "Nova Intercorrência" (`Create.tsx:160-170`). Protótipo: a criação ganhou endereço próprio (`pt-intercorrencias-create`, `ponto-telas.jsx:206`) e cabeçalho `pt-sub` com "Voltar à lista" e "Nova intercorrência" (`ponto-telas.jsx:240-243`). Fato datado: quando este gap foi medido, em 2026-09-28, o form era um `Card` dentro da lista, sem cabeçalho próprio. | Nada — `D-PONTO-DETALHE` cumprida no protótipo pela thread 28. A copy ("Nova Intercorrência" × "Nova intercorrência") é da passada de FORMA. |
| Campo IA em texto livre | **Vivo à frente.** Vivo: card com `Textarea`, badge "IA desligada no servidor" quando `ai_enabled=false`, botão `Preencher com IA`, alerts de erro e de sucesso com confiança e cache (`Create.tsx:173-239`), chamada a `/ponto/intercorrencias-ai/classify` (`:101`). Protótipo: não existe no símbolo. Goal do charter (`Create.charter.md:31-32`). | Protótipo corrige: modelar a região no build, declarando que a resposta da IA é mock. Sem ela, qualquer thread de layout exporta uma tela que já não existe. |
| Colaborador e tipo | **Paridade.** Vivo: `Field` Colaborador e Tipo, obrigatórios (`Create.tsx:253-291`). Protótipo: 2 selects obrigatórios (`ponto-telas.jsx:164-173`). | Nada. |
| Data, dia todo e intervalo | **Diverge em comportamento.** Vivo: Data (`Create.tsx:293-301`), checkbox "dia todo" (`:319-330`) e Início/Fim **escondidos** quando dia todo (`:332-351`). Protótipo: Início/Fim **desabilitados** quando dia todo (`ponto-telas.jsx:174-179`). | Protótipo corrige para esconder, como o vivo e o charter do Edit (`Edit.charter.md:48`). |
| Justificativa | **Paridade.** Vivo: `Textarea` obrigatório com contador `n/2000 · mínimo 10` (`Create.tsx:353-364`). Protótipo: `PtTexto` 2000 com mínimo 10 (`ponto-telas.jsx:180-181`). | Nada. |
| Prioridade e flags | **Paridade de dado; átomo pendente.** Vivo: Prioridade (`Create.tsx:303-316`) e as flags "Impacta apuração" e "Descontar do banco de horas" como `input type="checkbox"` (`:378-397`). Protótipo: `PtEscolha` + `PtCheck` (`ponto-telas.jsx:182-187`). | As flags são dado persistido: pela D-PONTO-ATOMO-BOOLEANO (R3), flag persistida é `Switch`, não checkbox. Aplica nos dois lados; dono é a onda de átomos, não este gap. |
| Anexo de comprovante | **Paridade desde 2026-09-29 ([#8128](https://github.com/wagnerra23/oimpresso.com/pull/8128), `UC-INTCRE-04`).** Vivo: `Field` "Anexo (PDF, JPG, PNG — máx 5 MB)" com `type="file"` e `accept=".pdf,.jpg,.jpeg,.png"` (`Create.tsx:369-376`); download por rota própria `ponto.intercorrencias.anexo` (`Modules/Ponto/Http/routes.php:69`). Protótipo: o mesmo rótulo e formatos (`ponto-telas.jsx:188-190`). Fato datado: quando este gap foi medido, em 2026-09-28, `grep -n "anexo"` e `grep -n 'type="file"'` nos 4 `.tsx` de Intercorrências davam 0 cada. | Nada — incorporado pelo #8128 (`D-INTERC-ANEXO`). |
| Nota de rascunho e append-only | **Paridade desde 2026-09-28 ([#8076](https://github.com/wagnerra23/oimpresso.com/pull/8076)).** Protótipo: `Nota` "nasce como rascunho… nada é aplicado até você submeter" (`ponto-telas.jsx:192`). Vivo: a `CardDescription` do card de dados diz "Salvar cria um rascunho. Nada é aplicado na apuração até você submeter, no detalhe, e um aprovador decidir. A marcação original nunca é alterada." (`Create.tsx:246-249`), como pede o charter (`Create.charter.md:71`: salvar não dispara aprovação; submeter é ação separada no `Show`). Fato datado: quando este gap foi medido, em 2026-09-28, a frase era "Eles serão submetidos ao RH para aprovação" (`Create.tsx:244-246`) e prometia um efeito que o salvar não tem. | Nada — corrigido no vivo pelo #8076 (copy + teste em `IntercorrenciaContratoTest.php`). A diferença que sobra é de FORMA (`Nota` × `CardDescription`), da passada de forma. |
| Rodapé de ações | **Diverge em copy e em validação.** Vivo: `Cancelar` volta à lista e `Salvar como rascunho` submete, com erros do servidor por campo (`Create.tsx:401-418`, `Field error=`). Protótipo: `Salvar rascunho` desabilitado com o erro no `title` (`ponto-telas.jsx:193-198`). | Protótipo corrige: erro inline por campo, não `title` em botão desabilitado (inalcançável por teclado, mesma lição do D-ESC-DESTROY na ata). A copy do botão é da passada de FORMA. |

## Região do form: `intercorrencias-dados-da-ocorrencia` (2026-09-29, thread 17)

Quando o id foi derivado, o protótipo embrulhava os campos num `Card` de título dinâmico ("Nova intercorrência" · "Editar rascunho <código>"), e por isso a derivação gerou o id `intercorrencias-card`. 2026-09-29 (handoff 43): o título dinâmico foi para o cabeçalho da página (`ponto-telas.jsx:242`) e o `Card` passou a ter título fixo "Dados da ocorrência" com o contrato (`ponto-telas.jsx:244`). No vivo a mesma região é o card de título fixo "Dados da ocorrência" (`Create.tsx:243-245`). O nome da região passa a ser **`intercorrencias-dados-da-ocorrencia`**, gravado nos dois lados no mesmo PR. O card da IA (`Create.tsx:173`) é outra região e segue sem id: o protótipo ainda não o modela (linha "Campo IA em texto livre" acima).
