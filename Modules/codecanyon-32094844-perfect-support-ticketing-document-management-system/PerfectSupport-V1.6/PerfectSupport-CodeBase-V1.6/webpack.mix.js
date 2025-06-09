const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js('resources/js/app.js', 'public/js')
    .combine([
        'resources/js/vendor-all.js'
      ],
     'public/js/vendor-all.js')
    .copy('resources/js/pcoded.min.js', 'public/js')
    .sass('resources/sass/app.scss', 'public/css')
    .combine([
          'resources/sass/style.css',
          'resources/plugins/smart-wizard/css/smart_wizard.min.css',
          'resources/plugins/smart-wizard/css/smart_wizard_theme_dots.min.css'
        ], 
      'public/css/style.css')
    .webpackConfig({
    	output: { chunkFilename: 'js/[name].[contenthash].js' },
    	resolve: {
      		alias: {
        		vue$: 'vue/dist/vue.runtime.js',
        		'@': path.resolve('resources/js'),
      		},
    	},
  	})
    .copyDirectory('resources/plugins/tinymce/skins', 'public/js/skins/')
    .copyDirectory('resources/fonts/feather', 'public/fonts/feather/')
    .setResourceRoot('../');
