---
date: "2026-08-12"
slug: "pages-para-modulos-5-ondas"
tldr: "5 ondas migraram 73 de 445 telas Inertia para dentro do módulo dono, sem mudar nenhum dos 232 Inertia::render. Branch claude/sleepy-lumiere-6eaa07 com 5 commits, sem PR. Falta smoke real e o Pest do UC-5 (só roda no CI)."
topic: "Pages para dentro dos módulos — 5 ondas, 73 telas migradas, 2 gates novos"
title: "Pages para dentro dos módulos — 5 ondas, 73 telas migradas, 2 gates novos"
type: reference
authority: historical
lifecycle: ativo
owner: W
---

# Handoff — Pages para dentro dos módulos (5 ondas)

## Onde parou

Branch **`claude/sleepy-lumiere-6eaa07`**, 5 commits, **sem PR aberto**. Base sincronizada com
`origin/main` (3 merges ao longo da sessão — o main avançou 3×).

## A decisão que originou

[W]: *"eles têm que ficar nos seus respectivos módulos e o Inertia tem que achar e o Vite tem que
compilar"*. Etapa 1 = **mover**. Renomear namespace ficou explicitamente para outro PR.

## O mecanismo (o que qualquer um precisa saber antes de tocar)

Onde as Pages vivem é **convenção nossa**, não imposição do Inertia. `app.tsx` e `ssr.tsx` declaram
**dois** globs cada e normalizam a chave do módulo para o namespace do núcleo:

```
import.meta.glob('./Pages/**/*.tsx')
import.meta.glob('../../Modules/*/Resources/js/Pages/**/*.tsx')
```

**O namespace não muda com o local do arquivo → nenhum dos 232 `Inertia::render` mudou.**

## Estado da migração

| Namespace | Dono(s) | Estado |
|---|---|---|
| `Settings` | PaymentGateway | ✅ piloto, 12 arquivos |
| `Atendimento` | Whatsapp | ✅ 38 arquivos |
| `ads` | Forja (4) + KB (1) | ✅ dividido, **sem renomear** (ADR 0363: URLs/permissions congeladas) |
| `Site` | Cms (4) + Superadmin (1) | ✅ dividido; **Login/Register ficam no núcleo** (são `app/Http/Controllers/Auth`) |
| `team-mcp` + `Forja` | Forja | ✅ **juntos** — `Pages/Forja` importava `ForjaHub` de `team-mcp` |

**73 de 445** páginas moram no módulo dono. O resto é namespace homônimo — migra quando alguém
tocar (forward-only, sem big-bang).

## O que entrou de máquina

- **`scripts/governance/pages-colisao.mjs`** — gate novo. Duas fontes na mesma chave colidem em
  **silêncio** (build exit 0, uma vence, a outra some). Selftest 5 asserts. **Nasce advisory.**
- **`module-surface --namespaces`** — o mapa módulo↔Pages virou **derivado** dos renders reais.
- **UC-5** em `InertiaPagesGlobContratoTest.php` — cobre o glob de módulos nas duas pontas.
- 2 jobs novos em `module-surface.yml` (`namespaces`, `colisao`), ambos advisory.

## As 6 armadilhas (todas medidas, nenhuma teórica)

1. **Casing** — a convenção nWidart é `Resources/` **maiúsculo** (711 contra 12) e o glob do Vite é
   case-sensitive. No Windows o `mkdir` funde; só o CI Linux acusaria. **Mordeu 3× nesta sessão.**
2. **Build verde não prova nada** — sem o glob de módulos o build **também** sai exit 0; a tela só
   não entra no bundle. A prova é o **manifest**.
3. **Prefixo ≠ existência** ao decidir se um import saiu da área.
4. **Um comprimento de `../` não é a família toda.**
5. **Reescrita sem âncora come o vizinho** (`Modules/PG/Modules/PG/...`).
6. **Direção inversa** — o que **ficou** importando o que **saiu**. Esses arquivos não estão no
   diff, e se o import usa `@/` o resolvedor nem olha. Sintoma do **recorte errado**: se A importa
   de B e ambos são do mesmo módulo, migre juntos.

## Provas do estado atual

```
build client exit 0 · build SSR exit 0
manifest: Forja 120 · Whatsapp 100 · PaymentGateway 22 · Cms 12 · KB 3 · Superadmin 3
pages-colisao: 445 páginas · 73 no módulo · total preservado nas 5 ondas
module-surface --all --check 0 · --namespaces 0 · suites node 93/93 · memory-schema 1/1
```

## O que NÃO foi validado (dívida honesta)

- **Pest / UC-5**: não rodado. O CT 100 tem checkout de 2026-07-23 com alterações não-commitadas de
  outra sessão — a proibição diz para não dar `git pull` lá sem combinar. **Validação fica no CI.**
- **Smoke em prod / browser**: nenhuma tela foi aberta. Provado que **compila e resolve**, não que
  renderiza igual. Antes de declarar "pronto", R1 exige o smoke.
- **`bootstrap/ssr/` não está no `.gitignore`** (ao contrário de `public/build-inertia/`). Quem
  rodar build SSR suja o working tree. Não consertei — é escopo próprio.

## Próximos passos sugeridos

1. Abrir o PR desta branch e deixar o CI validar o UC-5 (que nunca rodou local).
2. Smoke real pós-merge nas telas migradas (R1) — em especial Whatsapp/Atendimento, que é a maior.
3. **Etapa 2** (decisão [W]): renomear os namespaces divergentes para casar com o módulo
   (`Atendimento`→`Whatsapp` etc). PR separado, como combinado.
4. Considerar promover `pages-colisao` a required depois de mordida provada (ADR 0336).

## Sessões irmãs no mesmo dia (ler antes de mexer no glob)

- **#5679** — errata em `.claude/rules/pages.md`: o glob é escolha nossa. Antecipou este trabalho.
- **#5681** — descobriu que `CoworkBundleIntegralTest.php` está em **quarentena**; extraiu os UCs
  do glob para `InertiaPagesGlobContratoTest.php`. **Meu UC estava no arquivo em quarentena e
  nunca rodaria** — movido no merge.
- **#5683** — `block-mwart-violation` parou de oferecer o `/mwart-override` que nunca existiu.

## Estado MCP no momento do fechamento

Não consultei as tools MCP nesta sessão (trabalho foi 100% em código/gates locais, sem task
associada). O `brief-fetch` do SessionStart registrou: cycle sem nome, 5 HITL pendentes,
12 tasks em voo, nenhuma relacionada a este trabalho.
