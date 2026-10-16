---
casos: Fluxos/Index · /fluxos
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa + critério de aceite verificável (Dado/Quando/Então), provado por teste citando o id.
por_que: quem configura o fluxo muda como toda venda anda — o acesso é fronteira de permissão, e o encaixe tem de bater com o catálogo.
owner: wagner
last_run: "2026-10-16"
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

---

## Backlog de casos (sem id — entram quando tiverem teste)

- **[BACKLOG]** Aba Processos: estados, transições e roteiro (PR 2).
- **[BACKLOG]** Ligar/desligar regra por processo e a Compra editável do protótipo — o ERP ainda não tem regra configurável.
- **[BACKLOG]** Aba Casos de uso: UC-FLX com fontes e estado (PR 4).
- **[BACKLOG]** Assistente de configuração com IA e queda para a regra de encaixe (PR 5).
