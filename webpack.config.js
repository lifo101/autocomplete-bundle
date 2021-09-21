const path                 = require('path');
const {CleanWebpackPlugin} = require('clean-webpack-plugin');

const config = {
    entry: path.resolve(__dirname, 'src/js/autocomplete.js'),
    plugins: [
        new CleanWebpackPlugin(),
    ],
    output: {
        path: path.resolve(__dirname, 'src/Resources/public/js/dist'),
        filename: 'autocomplete.js',
        library: 'lifoAutocomplete',
        libraryTarget: 'umd',
    },
    externals: {
        jquery: 'jquery',
    },
    module: {
        rules: [
            {
                test: /\.(js)$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-env'],
                    }
                }
            },
        ],
    },
    resolve: {
        extensions: ['.js'],
        modules: [path.resolve(__dirname, 'src/js')],
    },
    mode: 'production'
};

module.exports = (env, argv) => {
    if (argv.mode === 'development') {
        config.devtool = 'source-map';
    }
    return config;
};
