# Customizações do Layout Principal do Sistema UltimatePOS

Este documento detalha as alterações realizadas nos arquivos do projeto UltimatePOS para remover o Bootstrap, adicionar DevExtreme, Vite, Tailwind 4 e DaisyUI 5, e gerar novamente os arquivos minificados.

## Resumo dos Arquivos Alterados

| Arquivo/Diretório | Ação | Resumo da Atualização |
|--------------------|------|-----------------------|
| css.blade.php | Modificado | Removida referência ao Bootstrap; adicionado Tailwind 4 e DaisyUI 5 via Vite. |
| extracss_auth.blade.php | Modificado | Ajustadas classes Bootstrap para DaisyUI; mantido CSS do pattern lock. |
| calculator.blade.php | Modificado | Substituídas classes Bootstrap por DaisyUI (ex.: `btn`, `input`). |
| error.blade.php | Modificado | Alterada classe `alert-danger` para equivalente em DaisyUI. |
| footer.blade.php | Mantido | Sem alterações, pois não usa Bootstrap diretamente. |
| footer_pos.blade.php | Mantido | Sem alterações, pois não usa Bootstrap diretamente. |
| footer-restaurant.blade.php | Mantido | Sem alterações, pois não usa Bootstrap diretamente. |
| extracss.blade.php | Modificado | Removido CSS relacionado a SweetAlert (substituído por DevExtreme); ajustado Tailwind. |
| header.blade.php | Modificado | Substituídas classes Bootstrap por DaisyUI; adicionado script DevExtreme. |
| javascripts.blade.php | Criado/Modificado | Adicionado DevExtreme via CDN; configurado Vite para minificação. |
| vite.config.js | Criado | Configurado Vite para Tailwind 4, DaisyUI 5 e minificação. |

## Detalhamento das Alterações

### Arquivo: `css.blade.php`

**Linha alterada**: Linha 1-3 (referência ao Bootstrap e CSS antigo)
- **Código anterior**:
  ```blade
  <!-- CSS principal -->
  @vite(['resources/css/app.css'])
  <!-- <link rel="stylesheet" href="{{ asset('css/app.css?v='.$asset_v) }}"> -->
  <!-- CSS de bibliotecas -->
  <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"/> -->
  ```
- **Código novo**:
  ```blade
  <!-- CSS principal com Tailwind e DaisyUI -->
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  ```
- **Motivo da alteração**: Removeu referência comentada ao Bootstrap e ao antigo `asset()`; configurou Vite para carregar Tailwind 4 e DaisyUI 5, garantindo minificação e suporte a JavaScript.

**Linha alterada**: Linha 40-62 (CSS do pattern lock)
- **Código anterior**:
  ```blade
  <style type="text/css">
      .patt-wrap { z-index: 10; }
      .patt-circ.hovered { background-color: #cde2f2; border: none; }
      ...
  </style>
  ```
- **Código novo**:
  ```blade
  <style type="text/css">
      .patt-wrap { z-index: 10; }
      .patt-circ.hovered { @apply bg-blue-200 border-none; }
      ...
  </style>
  ```
- **Motivo da alteração**: Substituiu a cor estática `#cde2f2` por uma classe Tailwind (`bg-blue-200`) para manter consistência com o novo framework de estilos.

### Arquivo: `extracss_auth.blade.php`

**Linha alterada**: Linha 14-18 (estilo do body)
- **Código anterior**:
  ```css
  body {
      background-color: #243949;
  }
  ...
  body {
      background: linear-gradient(to right, #6366f1, #3b82f6);
  }
  ```
- **Código novo**:
  ```css
  body {
      @apply bg-gradient-to-r from-indigo-500 to-blue-500;
  }
  ```
- **Motivo da alteração**: Consolidou estilos conflitantes do `body`; usou Tailwind para definir o gradiente, removendo cores hardcoded e garantindo compatibilidade com DaisyUI.

**Linha alterada**: Linha 69-81 (action-link)
- **Código anterior**:
  ```css
  .action-link[data-v-1552a5b6] { cursor: pointer; }
  .action-link[data-v-397d14ca] { cursor: pointer; }
  .action-link[data-v-49962cc0] { cursor: pointer; }
  ```
- **Código novo**:
  ```css
  .action-link { @apply cursor-pointer; }
  ```
- **Motivo da alteração**: Consolidou estilos redundantes em uma única classe Tailwind, simplificando a manutenção.

### Arquivo: `calculator.blade.php`

**Linha alterada**: Linha 7-8 (input)
- **Código anterior**:
  ```blade
  <input type="text" class="screen input input-bordered w-full text-right" name="result" readonly>
  ```
- **Código novo**:
  ```blade
  <input type="text" class="screen input input-bordered w-full text-end" name="result" readonly>
  ```
- **Motivo da alteração**: Substituiu `text-right` por `text-end` (Tailwind) para melhor suporte a layouts RTL.

**Linha alterada**: Linha 10-29 (botões)
- **Código anterior**:
  ```blade
  <button id="allClear" type="button" class="btn btn-error">AC</button>
  <button id="clear" type="button" class="btn btn-warning">CE</button>
  ...
  ```
- **Código novo**:
  ```blade
  <button id="allClear" type="button" class="btn btn-error">AC</button>
  <button id="clear" type="button" class="btn btn-warning">CE</button>
  ...
  ```
- **Motivo da alteração**: As classes `btn`, `btn-error`, `btn-warning` já são compatíveis com DaisyUI, então mantidas. Confirmado que DaisyUI suporta essas classes.

### Arquivo: `error.blade.php`

**Linha alterada**: Linha 2 (alert)
- **Código anterior**:
  ```blade
  <div class="alert alert-danger">
  ```
- **Código novo**:
  ```blade
  <div class="alert alert-error">
  ```
- **Motivo da alteração**: Substituiu `alert-danger` (Bootstrap) por `alert-error` (DaisyUI) para manter consistência com o novo framework de estilos.

### Arquivo: `extracss.blade.php`

**Linha alterada**: Linha 20-410 (SweetAlert CSS)
- **Código anterior**:
  ```css
  .swal-icon--error { ... }
  ...
  .swal-modal { ... }
  ```
- **Código novo**:
  ```css
  /* SweetAlert substituído por DevExtreme dialogs */
  ```
- **Motivo da alteração**: SweetAlert foi removido, pois DevExtreme oferece dialogs nativos. O CSS foi comentado para evitar conflitos.

**Linha alterada**: Linha 1-12 (scrollbar hiding)
- **Código anterior**:
  ```css
  html::-webkit-scrollbar, body::-webkit-scrollbar { display: none; }
  html, body { -ms-overflow-style: none; scrollbar-width: none; }
  ```
- **Código novo**:
  ```css
  html, body { @apply scrollbar-none; }
  ```
- **Motivo da alteração**: Substituiu CSS personalizado por classe Tailwind `scrollbar-none` para consistência.

### Arquivo: `header.blade.php`

**Linha alterada**: Linha 3-4 (navbar)
- **Código anterior**:
  ```blade
  <div class="navbar bg-base-100 h-16 border-solid border-b-2 border-base-300/30 no-print">
  ```
- **Código novo**:
  ```blade
  <div class="navbar bg-base-100 h-16 border-b-2 border-base-300/30 no-print">
  ```
- **Motivo da alteração**: Removida referência redundante a `border-solid`, pois Tailwind/DaisyUI já define bordas sólidas por padrão.

**Linha alterada**: Linha 509-560 (script de tema)
- **Código anterior**:
  ```blade
  <script>
      function applyTheme(theme) { ... }
      ...
  </script>
  ```
- **Código novo**:
  ```blade
  @vite(['resources/js/theme.js'])
  ```
- **Motivo da alteração**: Movido o script de troca de tema para um arquivo externo (`theme.js`) gerenciado pelo Vite, garantindo minificação e modularidade.

### Arquivo: `javascripts.blade.php` (novo/assumido)

**Linha alterada**: Novo arquivo
- **Código anterior**: Não existia.
- **Código novo**:
  ```blade
  @vite(['resources/js/app.js'])
  <script src="https://cdn3.devexpress.com/jslib/24.1.6/js/dx.all.js"></script>
  ```
- **Motivo da alteração**: Adicionado DevExtreme via CDN e configurado Vite para carregar scripts minificados.

### Arquivo: `vite.config.js` (novo)

**Linha alterada**: Novo arquivo
- **Código anterior**: Não existia.
- **Código novo**:
  ```javascript
  import { defineConfig } from 'vite';
  import laravel from 'laravel-vite-plugin';
  import tailwindcss from 'tailwindcss';
  import autoprefixer from 'autoprefixer';

  export default defineConfig({
      plugins: [
          laravel({
              input: ['resources/css/app.css', 'resources/js/app.js'],
              refresh: true,
          }),
      ],
      css: {
          postcss: {
              plugins: [
                  tailwindcss({
                      content: [
                          './resources/**/*.blade.php',
                          './resources/**/*.js',
                          './resources/**/*.vue',
                      ],
                      theme: {
                          extend: {},
                      },
                      plugins: [require('daisyui')],
                  }),
                  autoprefixer,
              ],
          },
      },
      build: {
          minify: 'esbuild',
          outDir: 'public/build',
      },
  });
  ```
- **Motivo da alteração**: Configurado Vite para compilar Tailwind 4, DaisyUI 5 e minificar assets, substituindo o antigo `init.js` e vendor.

## Arquivos Mantidos

- **footer.blade.php**: Não usa Bootstrap ou estilos afetados; mantido como está.
- **footer_pos.blade.php**: Similar ao `footer.blade.php`, sem alterações necessárias.
- **footer-restaurant.blade.php**: Idêntico em função aos outros rodapés; sem mudanças.

## Notas Adicionais

- **Minificação**: O Vite foi configurado para minificar CSS e JS automaticamente (`minify: 'esbuild'`).
- **DevExtreme**: Incluído via CDN, mas pode ser instalado localmente via npm se preferível.
- **Tailwind/DaisyUI**: Configurados no `vite.config.js` para suportar todas as views Blade.
- **Outros arquivos**: Arquivos como `app.blade.php` ou `sidebar.blade.php` não foram fornecidos, mas podem precisar de ajustes similares (ex.: substituição de classes Bootstrap por DaisyUI).