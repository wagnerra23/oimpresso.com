---
id: resources-js-pages-vozdocliente-caixa-casos
casos: Voz do Cliente · /voz-do-cliente
irmaos: Caixa.charter.md (lei) · Caixa.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o relato carrega texto livre, autor e tela de origem — vazar de tenant ou abrir a quem não tria expõe pessoa identificada.
owner: wagner
last_run: "2026-10-13"
---

# Casos de Uso & Aceite — Voz do Cliente · Caixa

> Thread `modulos-faltantes/playbook/03`. Derivados do contrato do SPEC (US-INFRA-002, R-VOZ-001/005), do comportamento
> da Blade `caixa.blade.php` + `SinalController::index`, do caso proposto pelo [CC]
> (`cowork-inbox/modulos-faltantes/voz-do-cliente.casos.md`, UC-VOZ-01…12) e da decisão `PERM-VOZ` — não do `.tsx`.
> Teste: [`tests/Feature/VozDoCliente/CaixaContratoTest.php`](../../../../tests/Feature/VozDoCliente/CaixaContratoTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-VOZ-01 · Abrir a caixa em React
- **Persona:** quem prioriza o produto.
- **Aceite:** Dado `vozdocliente.triar` · Quando faço
  `GET /voz-do-cliente` como o browser faz (`X-Inertia` **e** `X-Requested-With`) · Então renderiza Inertia
  **`VozDoCliente/Caixa`** com `contagem` (3 pendentes / 2 triados / 1 fechado para esse cenário) e `sinais` adiado.
- **Status: 🧪**

## UC-VOZ-02 · A Blade saiu (F5 2026-10-27)
- **Aceite:** Dado o cutover · Quando abro a caixa, inclusive com `?legacy=1` · Então a resposta é a Page
  `VozDoCliente/Caixa`, e a view `vozdocliente::caixa` não existe mais.
- **Histórico:** até 2026-10-27 este caso dizia "flag desligada ou `?legacy=1` mantém a Blade".
- **Status: 🧪**

## UC-VOZ-03 · Filtro por situação
- **Aceite:** Dado sinais nas três situações · Quando peço `?situacao=pending` · Então `sinais` traz só pendentes, e
  `filtro` volta `pending` · E situação desconhecida é ignorada (volta todos).
- **Status: 🧪**

## UC-VOZ-04 · Ordem da caixa
- **Aceite:** Quando a lista abre em "Todos" · Então pendentes vêm antes de triados, e triados antes de fechados; dentro
  da situação, o mais novo primeiro — a mesma ordem da Blade.
- **Status: 🧪**

## UC-VOZ-07 · Relato anônimo
- **Aceite:** Dado um sinal sem `autor_nome` · Quando a lista carrega · Então o item vem com `autor` nulo (a tela mostra
  "—", nunca célula vazia).
- **Status: 🧪**

## UC-VOZ-12 · Papel sem acesso · `[T0]`
- **Aceite:** Dado um usuário do negócio sem `vozdocliente.triar` · Quando abre a caixa · Então 403; nenhum texto de relato na resposta.
- **Status: 🧪**

## UC-VOZ-13 · Só os relatos do meu negócio · `[T0]`
- **Aceite:** Dado um relato do meu negócio e um de outro negócio · Quando a caixa abre · Então `contagem` e `sinais` só
  contam e trazem o meu; o texto alheio não aparece na resposta.
- **Status: 🧪**

## Sem teste ainda

- [BACKLOG] Pendente com gravidade alta tem a trilha `urgent`; fechado aparece atenuado (comportamento só no navegador — E2E).
- [BACKLOG] Lista vazia mostra "Nenhum relato ainda." sem KPI nem filtro; filtro sem resultado mostra "Nada nesta situação." (E2E).
- [BACKLOG] Triagem — "Virar US do backlog" com código (UC-VOZ-08), sem código vira US-NOVA-001 (UC-VOZ-09), "Fechar sem
  ação" com motivo (UC-VOZ-10), papel que lê mas não tria (UC-VOZ-11): entram com a onda da triagem.

## Anti-regressão

- Nenhum caminho de escrita sobre o texto do relato.
- A caixa não dispara e-mail, SMS, WhatsApp nem webhook.
- Sem `localStorage` de filtro (a caixa abre sempre em "Todos").
