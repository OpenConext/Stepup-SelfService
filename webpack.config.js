const Encore = require('@symfony/webpack-encore');
const ESLintPlugin = require('eslint-webpack-plugin');

Encore
    .setOutputPath('public/build/')
    .copyFiles([
        {
            from: './assets/images',
            to: './images/[path][name].[ext]',
        },
        {
            from: './assets/openconext/images',
            to: './images/logo/[path][name].[ext]',
        }
    ])
    .setPublicPath('/build')
    .cleanupOutputBeforeBuild()
    // Convert typescript files.
    .enableTypeScriptLoader()
    .enableLessLoader()
    .addStyleEntry('global', [
        './assets/scss/application.scss',
        './vendor/surfnet/stepup-bundle/src/Resources/public/less/stepup.less'
    ])
    .addEntry('registration-print', './assets/typescript/registration-print.ts')
    .addEntry('app', [
        './assets/typescript/app.ts',
        './vendor/surfnet/stepup-bundle/src/Resources/public/js/stepup.js'
    ])

    // Convert sass files.
    .enableSassLoader(function (options) {
        options.sassOptions = {
            outputStyle: 'expanded',
            includePaths: ['public'],
        };
    })
    .addLoader({test: /\.scss$/, loader: 'webpack-import-glob-loader', enforce: 'pre'})
    .addPlugin(new ESLintPlugin({
        extensions: ['js', 'jsx', 'ts', 'tsx', 'vue'],
    }))
    .enableSingleRuntimeChunk()
    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())
;


module.exports = Encore.getWebpackConfig();
