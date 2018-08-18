let mix = require('laravel-mix');
let purgeCss = require('purgecss-webpack-plugin');
let glob = require('glob-all');
let path = require('path');

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

 if (mix.inProduction()) {
  mix.webpackConfig({
    plugins: [
    new purgeCss({
      paths: glob.sync([
        path.join(__dirname, 'resources/views/**/*.blade.php'),
        path.join(__dirname, 'resources/assets/js/**/*.vue')
        ]),
      extractors: [
      {
        extractor: class {
          static extract(content) {
            return content.match(/[A-z0-9-:\/]+/g)
          }
        },
        extensions: ['html', 'js', 'php', 'vue']
      }
      ]
    })
    ]
  })
}

mix.setPublicPath('dist')
.js('resources/assets/js/app.js', 'js/')
.js('resources/assets/js/customizer.js', 'js/')
.extract([
 'babel-polyfill',
 'vue'
 ])
.sass('resources/assets/sass/app.scss', 'css/')
.options({
 processCssUrls: false,
 postCss: [ require('tailwindcss')('./tailwind.js') ],
})


if (!mix.inProduction()) {
 mix.browserSync({
  proxy: 'yellowstudio.rogue',
  port: 8080,
  files: [
  "resources/assets/sass/**/*.scss", 
  "resources/assets/js/**/*.js", 
  "resources/assets/js/**/*.vue", 
  "resources/views/**/*.blade.php", 
  ]
})
}

if (mix.inProduction()) {
  mix.version()
}
