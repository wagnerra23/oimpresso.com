---
id: resources-js-pages-configuracoes-codigobarras-index-casos
casos: Código de barras · /barcodes
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a configuração de etiqueta decide o que sai na impressora; a de outro negócio, ou a global, não pode aparecer nem mudar daqui.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Código de barras

> Thread `sistema/playbook/04`, tela 2 de 3. Derivados do `BarcodeController` e da Blade `barcode/*`
> (RUNBOOK-codigo-barras, `codigo-barras-parity.md`) — não do `Index.tsx`.
> Teste: [`tests/Feature/Configuracoes/CodigoBarrasContratoTest.php`](../../../../../tests/Feature/Configuracoes/CodigoBarrasContratoTest.php),
> lane `acessos-pest.yml` (MySQL). A Blade está travada em `CodigoBarrasBaselineTest` e o isolamento das ações em
> `CodigoBarrasTenantTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-ETQ-01 · Com a flag ligada, a tela abre em React
- **Persona:** quem configura a folha ou o rolo de etiqueta da loja.
- **Aceite:** Dado `barcode_settings.access` e a flag `useV2ConfiguracoesCodigoBarras` ligada · Quando faço `GET /barcodes`
  como o browser faz (`X-Inertia` **e** `X-Requested-With`) · Então renderiza **`Configuracoes/CodigoBarras/Index`**.
- **Status: 🧪**

## UC-ETQ-02 · Com a flag desligada, a Blade continua
- **Aceite:** Dado a flag desligada · Quando faço `GET /barcodes` · Então a resposta é a Blade `barcode.index`.
- **Status: 🧪**

## UC-ETQ-03 · A lista é só do meu negócio, com a padrão primeiro e as medidas em polegada · `[T0]`
- **Aceite:** Dado uma configuração minha (padrão, largura 1,5"), uma de outro negócio e um modelo global · Quando a
  lista carrega (prop deferida `etiquetas`) · Então vejo só as minhas, a padrão em primeiro e marcada, com largura 1,5.
- **Status: 🧪**

## UC-ETQ-04 · Cadastrar e editar pelo drawer gravam no meu negócio, em polegada
- **Aceite:** Dado o corpo que o drawer manda numa visita Inertia · Quando cadastro uma folha (largura 1,5", 24 por
  folha) marcada como padrão · Então ela é gravada no meu negócio, como padrão, com 1,5 e 24, e a padrão anterior deixa
  de ser · E editar para rolo contínuo grava `is_continuous` e 28 por folha, como o `update()` força.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Medidas em mm, como o protótipo — depende de decisão [W].

## Trilha do tempo
- 2026-10-07 · [CL] criado com a F3 da thread `sistema/playbook/04`.
