# Protótipo — Tributação · NF-e Brasil (4 telas)

> **Fonte de design GERADA pelo designer-agente** ([ADR 0282](../../../../../memory/decisions/0282-protocolo-v2-colapso-ratificacao.md) §0.1 — *"o agente gera, ancorado no DS canon"*), não importada do Cowork. Cobre o bloco `NfeBrasil/Tributacao` inteiro, que não tem fonte visual em nenhum dos dois donos do inventário.

## 1 · Status — PROPOSTA de forma, não lei

⚠️ **Leia isto antes de promover a âncora.** As 4 telas **já existem em produção** (1.227 linhas de `.tsx`, trio completo, `status: live` desde 2026-05-10). Este protótipo nasceu **depois** delas, então:

- **NÃO promover a `related_prototype`** nos charters sem decisão [W]. A cadeia FORMA da [UI-0029](../../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md) é *protótipo > teste > casos > charter* — promover isto tornaria um desenho novo **soberano sobre 4 telas vivas**, e "produto" é soberania [W] Tier 0 (§0.1).
- O `related_prototype: n/a (herda PT-0X; segue o Padrão de Tela)` dos 4 charters **permanece como está**. Ele é declaração consciente que a máquina reconhece (`ancora.mjs::ehDeclaracaoNa` → *"NÃO entra no anchor-content-check"*), **não um defeito** — lápide [§5 2026-08-28](../../../../../memory/proibicoes.md).
- Enquanto não houver decisão [W], isto é **material de comparação** (roda no `design-diff` como um dos lados), não alvo de `visual-regression`.

## 2 · Ausência confirmada — os dois donos, medido 2026-09-09

Claim de ausência exige repo **e** `DesignSync` (lápide §5 2026-08-07). Ambos consultados:

| dono | método | resultado |
|---|---|---|
| espelho + repo | `rg --hidden -li 'aliquota_icms\|uf_origem\|csosn\|regras? tribut\|ncm' prototipo-ui/` | nenhum `.jsx` desenha CRUD de regra NCM |
| Cowork vivo | `DesignSync.list_files` no projeto de telas **por ID** (`019dcfd3…`) | idem — nem `cowork-inbox/fiscal/` cobre Tributação |

**A fonte declara o próprio limite** — é evidência melhor que "não achei":
- `configuracoes-page.jsx:324` — *"Alíquota, CFOP, NCM e CST ficam em NF-e Brasil"*
- `prefs-page.jsx:121` — *"Certificado A1 e regras de NCM ficam em NF-e Brasil"*
- `fiscal-data.jsx:206` — o card de tributação do cockpit é `kind: "real"`, *"vêm do NfeBrasil"* — **leitura**, não edição

⚠️ **Sonda validada por controle positivo.** A primeira varredura usou `rg -E`, que é *encoding*, não regex estendida: ela falhou com `rc≠0` e teria passado por "zero ocorrências" se o controle positivo não estivesse ao lado (§5 2026-08-01).

`fiscal-page.jsx` **não serve** como âncora destas: é o cockpit do `Modules/Fiscal`, já é âncora declarada das 7 telas de `Pages/Fiscal/*`, e é porte reverso (`:2` *"Import do vivo"*).

## 3 · O que foi desenhado

| tela | padrão | conteúdo |
|---|---|---|
| `Index` | hub (não casa os 5 PT — segue o DS) | config default · gate de emissão automática · 11 templates setoriais · tabela densa de regras NCM |
| `ConfigDefault` | PT-02 Formulário | regime (4 valores do enum) · CFOP · CSOSN⊕CST conforme regime · 4 alíquotas |
| `RegraForm` | PT-02 Formulário | NCM · UF origem/destino · CFOP · CSOSN⊕CST alternado · 4 alíquotas · MVA/FCP |
| `ImportCsv` | PT-02 Formulário | upload com as 10 colunas obrigatórias · prévia válidas/recusadas · aplicar |

## 4 · Âncora do domínio — nada inventado

Todo campo, limite e rótulo saiu de fonte canônica. Nenhum veio do `.tsx` vivo (porte reverso é proibido — §5 [2026-06-05](../../../../../memory/proibicoes.md) e 2026-08-28):

| o que | de onde |
|---|---|
| colunas, tipos, "NULL = todas as UFs", cascade nível 3 | `Modules/NfeBrasil/Database/Migrations/2026_05_06_010000_create_nfe_fiscal_rules_table.php` |
| NCM 8 dígitos · CFOP 4 · CSOSN⊕CST exclusivo · alíquota ≤ 1 · MVA ≤ 5 · as 27 UFs | `Modules/NfeBrasil/Http/Requests/UpsertRegraTributariaRequest.php` |
| as 10 colunas do CSV, na ordem | `Modules/NfeBrasil/Services/Tributacao/ImportRegrasCsvService::COLUNAS_OBRIGATORIAS` |
| enum `regime` + JSON `tributacao_default` | migration de `nfe_business_configs` |
| os 11 templates (título, setor, regime, UF, modelo) | `Modules/NfeBrasil/Resources/templates/*.php` |
| NCM `XXXX.XX.XX` · alíquota 2 casas com vírgula · sem modal pra editar regra | `Index.charter.md` §UX Targets e §UX Anti-patterns |

**Alíquota é decimal no banco e percentual na tela** (0.18 ⇄ 18,00%) — a conversão é a mesma do charter (`(decimal * 100).toFixed(2)` PT-BR).

## 5 · Conformidade — e o token que eu tinha errado

Zero cor crua; só token do DS. **Mas os nomes precisaram ser medidos, não lidos.**

⚠️ **Achado — não copie a paleta do `prototipos/perfil/`.** A primeira versão deste CSS usou os 14 tokens do `perfil-page.css`, assumindo que fossem o DS. **Medido no browser com as 3 folhas que o shell carrega** (`colors_and_type.css` → `styles.css` → `cockpit_domains.css`): **8 dos 14 não existem** e resolviam vazio — `--surface`, `--text`, `--text-dim`, `--text-mute`, `--bg-2` (e `--radius`/`--shadow-pop`/`--accent-soft` só resolvem com as 3 folhas, não com a primeira sozinha). Card com `background:var(--surface)` renderizava **transparente**.

Mapa corrigido (58 substituições, ancoradas — `--text` é prefixo de `--text-dim`, e substituir na ordem errada comeria o vizinho, §5 2026-08-02):

| usei antes (não existe) | token real do DS |
|---|---|
| `--surface` | `--card` |
| `--text` | `--fg` |
| `--text-dim` | `--fg-2` |
| `--text-mute` | `--fg-3` |
| `--bg-2` | `--muted` |

`--accent` resolve `oklch(0.55 0.15 295)` — o **roxo 295** do primary universal ([UI-0190](../../../../../memory/requisitos/_DesignSystem/adr/ui/)), confirmado no computed do botão primário.

- Prefixo `.tb-` — sem colisão com outros bundles.
- Tabela dentro de `overflow-x:auto`; `role="switch"` + `aria-checked` no gate; `aria-label` nas ações de linha.

## 5-bis · Validação de runtime (não é screenshot — é medida)

Renderizado num host estático com as 3 folhas do shell, viewport 1280 (alvo do charter). Protótipo que não renderiza não é fonte de design (§5 2026-08-28: passa no CI e é inerte no runtime).

| medida | resultado |
|---|---|
| console | 0 erro |
| tokens não resolvidos | **0** (antes da correção: 8) |
| `scrollWidth > clientWidth` da página @1280 | **false** — cabe sem scroll horizontal |
| templates renderizados | 11 (= os 11 arquivos de `Resources/templates/`) |
| `UF origem` / `UF destino` | 27 / 28 opções (as 27 UFs, +"Todas" no destino) |
| NCM na tabela | `4819.10.00` — formato `XXXX.XX.XX` do charter |
| alíquotas | `18,00% · 0,65% · 3,00%` — 2 casas, vírgula PT-BR |
| CSOSN⊕CST | alternar para CST troca rótulo, hint (`CRT 3`) e placeholder |
| colunas do CSV na tela | as 10 de `COLUNAS_OBRIGATORIAS`, na ordem |

⚠️ A primeira leitura deu **tudo zero** e o console estava limpo — o defeito era do **host** (bootstrap esperava `load`, que dispara antes de o Babel compilar), não do protótipo. Instrumento antes do artefato (§5 2026-08-17).

## 6 · O que este protótipo NÃO decide

- **Se ele vira âncora** — decisão [W] (§1).
- **A forma da produção.** Onde ele diverge do `.tsx` vivo, a divergência é *dado para comparar*, não veredito. O caminho é `design-diff --probe` nos dois lados, nunca leitura no olho (LC-06).

## Refs
- [ADR 0282](../../../../../memory/decisions/0282-protocolo-v2-colapso-ratificacao.md) §0.1 — o Code é o designer-agente e **gera**
- [ADR UI-0013](../../../../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) — Constituição UI v2 (4 camadas)
- [ADR UI-0029](../../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md) — protótipo soberano na FORMA (por que §1 importa)
- [ADR 0299](../../../../../memory/decisions/0299-figma-nao-e-fonte-de-design.md) — fonte de design é protótipo + DS + charter
- [INVENTÁRIO 2026-09-09](../../../../../memory/requisitos/_DesignSystem/INVENTARIO-ANCORAS-2026-09-09.md) §5.3 — o recibo da ausência
