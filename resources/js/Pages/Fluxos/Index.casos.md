---
casos: Fluxos/Index · /fluxos
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa + critério de aceite verificável (Dado/Quando/Então), provado por teste citando o id.
por_que: quem configura o fluxo muda como toda venda anda — o acesso é fronteira de permissão, e o encaixe tem de bater com o catálogo.
owner: wagner
last_run: "2026-10-20"
---

# Casos de Uso & Aceite — Fluxos/Index

> Derivados do protótipo `prototipo-ui/cowork/Wagner/fluxos-page.jsx` (aba Modelos) e da regra de
> encaixe do catálogo (`CatalogoModelos::encaixe`, ADR 0435). Provados por
> `tests/Feature/Domain/Fsm/FluxosTelaContratoTest.php` (lane domain-pest, MySQL, tenants 98/99).
> NUNCA biz=4.
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.

---

## UC-FLXT-01 · Quem tem fluxos.ver chega pelo menu Sistema › Fluxos e regras
- **Persona:** dono ou gerente — procura onde muda o fluxo do pedido.
- **Aceite:** Dado um usuário com `fluxos.ver` · Quando abre o ERP · Então o sidebar tem
  "Fluxos e regras" no grupo SISTEMA levando a `/fluxos`, e a página é `Fluxos/Index`.
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-01").
- **Status: 🧪**

## UC-FLXT-02 · Sem fluxos.ver não abre a tela
- **Persona:** vendedor — não configura o fluxo da empresa.
- **Aceite:** Dado um usuário sem `fluxos.ver` · Quando abre `/fluxos` · Então recebe 403 e o
  item não aparece no menu.
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-02").
- **Status: 🧪**

## UC-FLXT-03 · Aba Modelos mostra os 7 modelos com o encaixe do catálogo
- **Persona:** gerente de uma comunicação visual pequena.
- **Aceite:** Dado o catálogo de 7 modelos · Quando a tela abre · Então vêm os 7 modelos com os
  processos e, para Pequena · Comunicação visual, "Comunicação visual · pequena" vem Recomendado
  (encaixe 4, "Porte e atividade batem") e "Oficina mecânica" vem Parcial com porte igual e outra atividade.
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-03").
- **Status: 🧪**

## UC-FLXT-04 · fluxos.configurar chega à tela para quem configura a empresa
- **Persona:** gerente com acesso às Configurações da empresa.
- **Aceite:** Dado um papel com `business_settings.access` antes do deploy · Quando a migration
  roda · Então o papel ganha `fluxos.ver` e `fluxos.configurar`, e a página informa
  `pode_configurar = true`; papel sem essa origem não ganha nada.
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-04").
- **Status: 🧪**

## UC-FLXT-05 · Quem configura usa um modelo e a empresa passa a tê-lo
- **Persona:** gerente com `fluxos.configurar`.
- **Aceite:** Dado a empresa sem modelo · Quando escolhe "Comunicação visual · pequena" e confirma ·
  Então `fsm_modelos_empresa` da empresa da sessão grava o modelo, o porte e a atividade, os processos
  `flx_cv_pequena_*` nascem só nessa empresa, e a tela volta na aba Fluxos da empresa com o aviso
  "Documentos em andamento continuam na etapa em que estavam".
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-05").
- **Status: 🧪**

## UC-FLXT-06 · Sem fluxos.configurar não troca o modelo
- **Persona:** usuário que só vê a tela (`fluxos.ver`).
- **Aceite:** Dado um usuário sem `fluxos.configurar` · Quando envia "Usar modelo" · Então recebe 403 e
  nada é gravado; a tela informa `pode_configurar = false` (o botão não aparece).
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-06").
- **Status: 🧪**

## UC-FLXT-07 · Fluxos da empresa mostra o modelo em uso
- **Persona:** dono conferindo o fluxo atual.
- **Aceite:** Dado a empresa com um modelo gravado · Quando abre `?aba=empresa` · Então `empresa` traz
  o modelo, o porte e a atividade dela, e não os de outra empresa.
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-07").
- **Status: 🧪**

## UC-FLXT-08 · O assistente sugere um modelo a partir da descrição
- **Persona:** dono que não sabe qual modelo escolher.
- **Aceite:** Dado a IA do ERP disponível · Quando descreve a empresa e pede a recomendação · Então
  recebe o modelo sugerido, o porquê, os casos a ligar/desligar (só ids do catálogo) e o perfil
  indicado, marcado como "Sugestão da IA". Nada é gravado.
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-08").
- **Status: 🧪**

## UC-FLXT-09 · Sem IA, o assistente usa a regra de encaixe e diz isso
- **Persona:** dono, com o provedor de IA fora do ar.
- **Aceite:** Dado a IA indisponível · Quando pede a recomendação · Então recebe o modelo de maior
  encaixe para o perfil e o motivo "Escolhido só pelo porte e pela atividade — a IA não respondeu.";
  a tela mostra "Sugestão pelo perfil".
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-09").
- **Status: 🧪**

## UC-FLXT-10 · Resposta da IA fora do catálogo não vira sugestão
- **Aceite:** Dado a IA devolvendo um modelo que não existe · Então vale a regra de encaixe.
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-10").
- **Status: 🧪**

## UC-FLXT-11 · Sem fluxos.ver o assistente não responde
- **Aceite:** Dado um usuário sem `fluxos.ver` · Quando chama o assistente · Então recebe 403.
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-11").
- **Status: 🧪**

## UC-FLXT-12 · A aba Processos mostra estados, transições e roteiro de cada processo
- **Persona:** dono entendendo como um documento anda antes de escolher o modelo.
- **Aceite:** Dado o catálogo de processos (protótipo aprovado no #9498) · Quando abre `?aba=processos`
  · Então vê os processos por área, e no escolhido os estados (com início, espera e fins), a tabela
  de transições (de → para, ação, só passa se, efeito, quem) com filtro por estado de origem, o
  roteiro em paralelo da produção e o comparativo NF-e × NFS-e nos fiscais. `?processo=` abre um
  processo direto (o link "Ver estados e condições" da gaveta do modelo usa isso).
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-12").
- **Status: 🧪**

## UC-FLXT-13 · A aba Casos de uso mostra cada UC-FLX com fontes e estado no oimpresso
- **Persona:** dono comparando o que os concorrentes anunciam com o que o oimpresso já tem.
- **Aceite:** Dado o catálogo de casos (#9501) · Quando abre `?aba=casos` · Então vê a tabela (caso, o que
  faz, processo, quem anuncia, a partir de que porte, quantos modelos usam, estado no oimpresso e US),
  filtra por busca, processo, fonte e estado, e ao clicar abre a gaveta com a regra, o estado, as fontes
  com link e os modelos que usam. A aba Modelos passa a mostrar a descrição do porte e "Inspirado em".
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-13").
- **Status: 🧪**

## UC-FLXT-14 · Quem configura desliga e religa uma regra do modelo
- **Persona:** dono que não quer, por exemplo, o apontamento pelo celular na Impressão.
- **Aceite:** Dado um modelo em uso e `fluxos.configurar` · Quando desliga uma regra "ao entrar" na
  aba Fluxos da empresa · Então a regra fica `ligada = false` na etapa do processo da própria empresa
  (o `business_id` do formulário é ignorado), a tela passa a mostrar desligada, aplicar o mesmo
  modelo de novo não a religa, e regra que não existe no modelo em uso é recusada.
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-14") + `tests/js/fluxos-aba-empresa.test.tsx` (a tela).
- **Status: 🧪**

## UC-FLXT-15 · A Compra é configurável e "Usar modelo" traz o custo do modelo
- **Persona:** dono que decide como o custo do insumo muda quando a compra chega.
- **Aceite:** Dado "Usar modelo" · Então a regra "Atualizar o custo dos insumos" liga com os parâmetros
  do modelo (método, preço, quem segue, aprovação, OPs abertas, etapa) · Quando a empresa muda um
  parâmetro ou desliga outra regra da Compra · Então a mudança fica gravada e a etapa onde a regra
  dispara acompanha · E trocar de modelo volta só o custo para o do novo modelo.
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-15") + `tests/js/fluxos-aba-empresa.test.tsx` (a tela).
- **Status: 🧪**

## UC-FLXT-16 · Entrada no estoque é obrigatória; valor fora das opções é recusado
- **Aceite:** Dado a Compra configurável · Quando tenta desligar "Dar entrada no estoque", mandar um
  valor que não está nas opções ou um parâmetro que a regra não tem · Então recebe erro e nada muda.
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-16") + `tests/js/fluxos-aba-empresa.test.tsx` (a tela).
- **Status: 🧪**

## UC-FLXT-17 · Sem fluxos.configurar ou sem modelo, nada muda
- **Aceite:** Dado um usuário sem `fluxos.configurar` · Quando muda uma regra · Então recebe 403 ·
  E dado uma empresa no processo antigo (`legado`) · Quando quem configura muda a Compra · Então
  recebe "Escolha um modelo antes de configurar as regras" e nada é gravado.
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-17") + `tests/js/fluxos-aba-empresa.test.tsx` (a tela).
- **Status: 🧪**

## UC-FLXT-18 · A aba Processos mostra quanto o ERP executa
- **Persona:** dono decidindo se um modelo já funciona no oimpresso ou ainda é plano.
- **Aceite:** Dado o catálogo de processos com a cobertura do ERP (#9496, #9505, #9507) · Quando abre
  `?aba=processos` · Então cada processo da lista tem o selo "X de Y no ERP" (ou "ERP não verificado"),
  o detalhe mostra o resumo (executa · parcial · não existe · não verificado) e a tabela de transições
  ganha a coluna "No oimpresso" com o estado e onde está no código — os mesmos números do protótipo
  aprovado (#9506).
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-18").
- **Status: 🧪**

## UC-FLXT-19 · Quem configura define a margem mínima do orçamento
- **Persona:** dono que não quer orçamento convertido em venda com margem abaixo de um piso (UC-FLX-35).
- **Aceite:** Dado uma empresa com modelo em uso · Quando quem tem `fluxos.configurar` grava uma margem
  mínima entre 0 e 100 na aba Fluxos da empresa · Então ela fica gravada só para a empresa da sessão e
  aparece na aba · Quando apaga o valor · Então a regra fica desligada · E dado um valor fora de 0–100
  · Então recebe erro e nada muda · E sem `fluxos.configurar` recebe 403.
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-19").
- **Status: 🧪**

## UC-FLXT-20 · A baixa de material dos modelos sem OS diz por que não executa
- **Persona:** dono que liga o modelo Balcão enxuto (ou Brindes) e lê a regra "Baixa o material do estoque".
- **Fonte:** UC-FLX-14 do catálogo (`CatalogoModelos`, regra "Aprovado" do balcão e "Produção" dos
  brindes) + US-PCP-021 (o material consumido baixa no apontamento da etapa da OS).
- **Aceite:** Dado "Usar modelo" com Balcão enxuto ou Brindes · Então a regra do UC-FLX-14 fica
  `planejado`, sem side effect, com o `ConsumirEstoque` só como candidato e com a `pendencia` que diz
  por que não executa (a peça já baixa na venda finalizada; o consumido só baixa no apontamento da OS,
  que esses modelos não têm) · E a ação que entra na etapa não carrega baixa · E regra sem motivo
  conhecido não ganha pendência.
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-20").
- **Status: 🧪**

## UC-FLXT-21 · Venda finalizada do balcão entra em Aprovado sem segunda baixa
- **Persona:** dono do balcão que finaliza a venda (o núcleo já baixa a peça) e depois a aprova no fluxo.
- **Aceite:** Dado uma venda `final` no processo de venda do Balcão enxuto, na etapa Orçamento, com uma
  reserva de estoque ativa · Quando avança para Aprovado, com a regra do UC-FLX-14 ligada ou desligada ·
  Então a venda entra em Aprovado, nenhuma reserva é consumida (nem a de outra empresa) e o saldo de
  estoque não muda. ESTOQUE: regra mestre — a prova é por dois caminhos (reserva e saldo).
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-21").
- **Status: 🧪**

## UC-FLXT-24 · Quem configura liga e desliga a requisição pelo estoque mínimo
- **Persona:** dono que quer que o item abaixo do mínimo vire requisição de compra sozinho (UC-FLX-19).
- **Fonte:** US-COM-027 (SPEC Compras) — o job diário existe e nasce desligado em
  `business.common_settings['compras_requisicao_estoque_minimo']`; faltava a tela para ligar.
- **Aceite:** Dado uma empresa com modelo em uso e o módulo Compras · Quando quem tem
  `fluxos.configurar` liga o interruptor no card Compra da aba Fluxos da empresa · Então a regra fica
  ligada só para a empresa da sessão, as outras chaves de `common_settings` continuam e a aba mostra
  ligada · Quando desliga · Então fica desligada · E dado um valor que não é sim/não · Então recebe
  erro e nada muda · E sem `fluxos.configurar` recebe 403.
- **Teste:** `FluxosTelaContratoTest` ("UC-FLXT-24").
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste)

- **[BACKLOG]** UC-FLX-14 — o que é "material consumido" numa venda sem OS (a peça vendida, que o
  núcleo já baixa? o insumo pela ficha do produto?) é decisão [W]. Até lá a regra só diz por que não
  executa (UC-FLXT-20).
