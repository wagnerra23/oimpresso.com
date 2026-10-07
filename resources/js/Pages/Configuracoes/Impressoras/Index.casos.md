---
id: resources-js-pages-configuracoes-impressoras-index-casos
casos: Impressoras · /printers
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: impressora de outro negócio na lista, ou excluída por engano, tira o cupom do caixa de alguém.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Impressoras

> Thread `sistema/playbook/04`, tela 1 de 3. Derivados do comportamento do `PrinterController` e da Blade
> `printer/*` (RUNBOOK-impressoras, `impressoras-parity.md`) — não do `Index.tsx`.
> Teste: [`tests/Feature/Configuracoes/ImpressorasContratoTest.php`](../../../../../tests/Feature/Configuracoes/ImpressorasContratoTest.php),
> lane `acessos-pest.yml` (MySQL). O comportamento da Blade está travado em `ImpressorasBaselineTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-IMPR-01 · Com a flag ligada, a tela abre em React
- **Persona:** quem configura onde o caixa imprime o cupom.
- **Aceite:** Dado `access_printers` e a flag `useV2ConfiguracoesImpressoras` ligada · Quando faço `GET /printers` como o
  browser faz (`X-Inertia` **e** `X-Requested-With`) · Então renderiza Inertia **`Configuracoes/Impressoras/Index`** —
  não o JSON da DataTable.
- **Status: 🧪**

## UC-IMPR-02 · Com a flag desligada, a Blade continua
- **Aceite:** Dado a flag desligada · Quando faço `GET /printers` · Então a resposta é a Blade `printer.index`.
- **Status: 🧪**

## UC-IMPR-03 · A lista é só do meu negócio · `[T0]`
- **Aceite:** Dado uma impressora minha e uma de outro negócio · Quando a lista carrega (prop deferida `impressoras`) ·
  Então vejo a minha, com conexão e endereço, e não vejo a alheia.
- **Status: 🧪**

## UC-IMPR-04 · Cadastrar e editar pelo drawer gravam no meu negócio
- **Aceite:** Dado o corpo que o drawer manda (`name`, `connection_type`, `capability_profile`, `char_per_line`,
  `ip_address`, `port`, `path`) numa visita Inertia · Quando cadastro uma impressora de rede · Então ela é gravada no
  meu negócio, sem caminho, e a resposta volta para `/printers` · E editar pelo `PUT` altera o nome.
- **Status: 🧪**

## UC-IMPR-05 · As abas de Configurações vêm do menu, com a permissão de cada uma
- **Decisão D1 ([W] 2026-10-06):** Configurações é uma tela com abas.
- **Aceite:** Dado `access_printers` + `business_settings.access` · Quando a tela pede `shell.menu` · Então o grupo que
  contém `/printers` está lá, com `/business-location` (é dele que o `ConfiguracoesSubNav` tira as abas) · E sem
  `barcode_settings.access` a aba de código de barras não vem.
- **Limite medido:** com **só** `access_printers` o grupo inteiro some — a condição externa do dropdown no
  `AdminSidebarMenu` não lista essa permissão. A tela abre sem abas. Defeito legado, fora do prefixo da thread.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] "Testar" impressão de cupom — não existe endpoint no legado.

## Trilha do tempo
- 2026-10-07 · [CL] criado com a F3 da thread `sistema/playbook/04`.
