<!-- SESSÃO FRIA · abra esta thread sozinha. Read-order mínimo e prompt de abertura: `_SESSAO-FRIA.md` (linha "emendas de charter + 2 guards").
     Os ids de decisão (D-*) só existem em `ATA-DECISOES-2026-09-14.md` — leia a ata antes, ou as siglas ficam órfãs.
     Não leia as outras threads: cada uma é 1 PR e o contexto delas não é pré-requisito desta. -->

# 27 · As 8 emendas de charter + os 2 guards (bloco 4 e R1 da ata)

> **Origem:** `ATA-DECISOES-2026-09-14.md`, bloco 4 (INCORPORA) + **R1** de [W] (*"Non-Goal que eu ratifico vira Pest GUARD no charter E some do protótipo no mesmo pacote"*).
> **Por que isto é proposta e não arquivo meu:** charter e `.casos.md` são **canon** em `resources/js/Pages/**` — dono é o repo. Eu leio e proponho; o [CL] aplica no PR. Emenda que eu commitasse do meu lado criaria charter divergente, que é o defeito que o guard R4 existe para pegar.
> **Regra de fechamento (3 partes por emenda):** Goal novo **+** `data-contract` da região (sem ele o `map.json` não ancora — *"declarada e ausente = DRIFT"*) **+** UC no `.casos.md` quando o comportamento muda (a máquina de prontidão exige o **trio**: `.tsx` + charter + casos com UC).

---

## E1 · `Ponto/Colaboradores/Index.charter.md` — D-COLAB-COLUNAS + D-COLAB-CPF

**Acrescentar em Goals:**
```
- Colunas adicionais: "Último ponto" (última marcação do mês, derivada) e "Saldo BH" (com cor por sinal).
- Filtros: escala (select) e situação — Ativos · Só quem controla ponto · **Sem PIS cadastrado** · Desligados · Todos.
- CPF e PIS **mascarados** na listagem (3 últimos dígitos), valor inteiro apenas no formulário de edição.
```
**Substituir a pendência** `[ ] Confirmar mascaramento de CPF na coluna (LGPD)` por:
```
- [x] Mascaramento de CPF/PIS na listagem — DECIDIDO por [W] 2026-09-14: mascara na lista, inteiro só no form.
      Motivo registrado: "lista é tela de varredura, vista por quem passa atrás da mesa; minimização de dado é o default".
```
**`data-contract`:** `colaboradores-colaboradores` (já existe no protótipo desde 2026-09-14).
**UC novo para `.casos.md`:**
```
UC-PONT-COL-06 · Achar quem não pode bater ponto por falta de PIS
  Dado que existem colaboradores sem PIS cadastrado
  Quando o gestor escolhe a situação "Sem PIS cadastrado"
  Então a lista mostra só esses colaboradores, com o aviso "PIS não cadastrado" na célula de CPF/PIS
  E o gestor descobre isso ANTES da importação do AFD rejeitar a marcação (hoje só descobre depois do erro)
```
> **A frase de [W] que justifica a prioridade:** *"é o item de maior valor do lote inteiro"*.

---

## E2 · `Ponto/Importacoes/Show.charter.md` — D-IMP-EXTRAS

**Acrescentar em Goals:**
```
- Card "Diagnóstico do processamento": log bruto do job, em monoespaçado.
- Card "Amostra de erros": as primeiras linhas rejeitadas com linha, NSR, tipo e mensagem.
```
**`data-contract`:** `importacoes-diagnostico-do-processamento` · `importacoes-amostra-de-erros`.
**UC novo:**
```
UC-PONT-IMP-04 · Saber POR QUE linhas falharam
  Dado um AFD processado com N linhas rejeitadas
  Quando o RH abre a importação
  Então vê a amostra de erros com linha, NSR, tipo e mensagem
  E não precisa abrir o arquivo .txt para descobrir a causa
```
> *"Sem isso a tela de importação só sabe dizer que falhou."* — [W]

---

## E3 · `Ponto/BancoHoras/Index.charter.md` — D-BH-KPI

**Substituir a linha de KPIs em Goals** por:
```
- KPIs agregados: crédito total e débito total (cada um com a contagem de colaboradores na sub-linha),
  total de colaboradores no banco e multiplicadores de crédito/débito vigentes.
```
> [W]: *"ficam os seus 4. A informação está toda lá e **o charter é o que está atrasado**."*

## E3-bis · `Ponto/BancoHoras/Show.charter.md` — fecha pendência aberta

**Acrescentar em Goals** e **fechar a pendência** `[ ] Confirmar regra de expiração de crédito exibida ao usuário`:
```
- KPIs de acordo: "Teto do acordo" (saldo máximo/mínimo em horas) e "Prazo de compensação" (meses, acordo individual).
- [x] Regra de expiração/limite EXIBIDA ao usuário — atendido pelos 2 KPIs acima (Cowork 2026-09-14).
```

---

## E4 · `Ponto/Intercorrencias/Create.charter.md` — D-INTERC-ANEXO (com a ressalva de PII)

**Acrescentar em Goals:**
```
- Anexo de comprovante: PDF/JPG/PNG, obrigatório tratamento como dado sensível.
```
**Acrescentar em Non-Goals (a ressalva de [W], literal):**
```
- ❌ O anexo NÃO é público e NÃO entra em log — atestado é dado de saúde (dado sensível, LGPD Art. 11).
  Armazenamento privado, acesso por permissão, desde o primeiro commit. Não é "depois a gente protege".
```
**UC novo:**
```
UC-PONT-INT-07 · Anexar comprovante a uma intercorrência
  Dado um atestado médico em PDF
  Quando o solicitante anexa o arquivo ao rascunho
  Então o arquivo é armazenado em disco privado e vinculado à intercorrência
  E só quem tem permissão de aprovação consegue baixá-lo
  E nenhum caminho/URL do arquivo aparece em log
```
> **Registrado:** [W] ofereceu revisão de **[E] Eliana** antes de virar código e **dispensou por agora** — não é pré-requisito desta onda.

---

## E5 · `Ponto/Configuracoes/Index.charter.md` — D-CFG-IA + correção de fonte

**Trocar "4 blocos" por "5 blocos" em Goals e acrescentar:**
```
- Bloco "IA do Ponto" (read-only): master switch, classificação de intercorrência,
  explicação de divergência, geração de justificativa e modelo em uso.
```
**Acrescentar em Non-Goals (a condição de [W]):**
```
- ❌ Ligar/desligar IA por business NÃO se faz por `if` no código — passa pela UI canônica
  de pacote/permissão. Enquanto esse caminho não existir, o bloco é somente leitura.
```
**`data-contract`:** `configuracoes-ia-do-ponto`.
**⚠️ Item próprio aberto:** `D-CFG-IA-CAMINHO` — *as flags têm hoje caminho de pacote/permissão?* **Não medi.** [W] pediu que virasse item próprio se não tiver; medir antes de construir.

---

## E6 · `Ponto/Espelho/*.charter.md` — D-PRINT-TINTA (exceção declarada, sem token novo)

**Acrescentar em Non-Goals:**
```
- ❌ As cores da folha de prova (bloco `@media print` de `.pt-folha`) NÃO são tokens do DS e não devem virar:
  são TINTA DE PAPEL (cinzas de impressão + amarelo de destaque de divergência). Exceção declarada
  por [W] 2026-09-14: "cor de impressão não é cor de tela e não deve poluir o DS".
  Isolamento: vivem SOMENTE dentro do `@media print`; nenhuma delas aparece em superfície de tela.
```
> Medido: `ponto-page.css` tem **0 token de cor bespoke** (o predicado "paleta inventada" do `ds-guard`, ≥4, **não dispara**) e 8 cores cruas, **todas** dentro do `@media print`.

---

## E7 · `Ponto/Intercorrencias/Index.charter.md` — **GUARD** (R1, Non-Goal ratificado)

O Non-Goal já existe no charter. O que falta é a **máquina**:
```php
/** D-INTERC-ACOES ([W] 2026-09-14): a lista NÃO submete nem edita — Show é o dono da ação. */
it('a lista de intercorrencias nao expoe submeter nem editar', function () {
    $this->actingAs($gestor)
        ->get(route('ponto.intercorrencias.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Ponto/Intercorrencias/Index')
            ->where('acoes_da_linha', ['ver'])        // contrato explícito, não varredura de HTML
        );
});
```
**Estado do protótipo:** ✅ já obedece — `Editar`/`Submeter` saíram da linha em 2026-09-14 (coluna Ação 170px → 88px). **O guard nasce verde**, que é o ponto da R1.

---

## E8 · `Ponto/Escalas/Index.charter.md` — **GUARD** + emenda (D-ESC-DESTROY)

**Substituir o Non-Goal** `❌ Não exclui escala nesta lista (… confirmar com Wagner)` por:
```
- Exclusão de escala DISPONÍVEL na lista, com trava dura: indisponível (não "confirmação") quando
  houver colaborador vinculado, com o motivo escrito no próprio controle ("N colaboradores vinculados").
  Confirmação por dialog do DS, nunca window.confirm. Decidido por [W] 2026-09-14:
  "perder referência de escala é perder histórico de jornada, e isso a CLT cobra".
```
**Fechar a pendência** `[ ] Definir se exclusão de escala (destroy) entra na UI e com quais guardas` → `[x]` com a linha acima.
```php
/** D-ESC-DESTROY ([W] 2026-09-14): escala com vínculo não pode ser removida — nem pela UI, nem pela rota. */
it('destroy de escala com colaborador vinculado e recusado', function () {
    $escala = Escala::factory()->for($business)->create();
    ColaboradorConfig::factory()->for($business)->create(['escala_atual_id' => $escala->id]);

    $this->actingAs($gestor)
        ->delete(route('ponto.escalas.destroy', $escala))
        ->assertForbidden();                          // 403, não 302 silencioso

    expect(Escala::find($escala->id))->not->toBeNull();
});
```
**Estado do protótipo:** ✅ já obedece — botão `disabled` com o motivo + `Modal` do DS.

---

## Quem aplica o quê

| arquivo | quem | o que |
|---|---|---|
| `Pages/Ponto/**/*.charter.md` (7 arquivos) | **[CL]** | E1–E8, blocos acima |
| `Pages/Ponto/**/*.casos.md` (4 arquivos) | **[CL]** | os 3 UC novos (E1, E2, E4) |
| `tests/.../Ponto/` (2 arquivos) | **[CL]** | os 2 guards (E7, E8) |
| `governance/design/contracts/` | **[CL]** | contrato de tela onde o comportamento travar (hoje o Ponto tem **2** para 21 telas) |
| `memory/requisitos/Ponto/*.map.json` | **[CL]** | regenerar com `gerar-map.mjs --atualizar` depois das emendas (as regiões novas viram partes) |
| `ponto-telas.jsx` | **[CC]** | ✅ E7 e E8 já aplicados; E1 (mascaramento) ✅; o resto entra nas ondas do bloco 4/5 |

**PARAR SE** — algum charter já tiver o Goal proposto ⇒ a emenda é **catch-up meu**, não pedido (minha hipótese "produção atrás" já caiu 3 de 3) · faltar o `data-contract` correspondente no `.tsx` ⇒ **não aplicar a emenda sozinha**: charter que declara região sem âncora é DRIFT por construção.
