const path = require('path');
const MonacoWebpackPlugin = require('monaco-editor-webpack-plugin');

module.exports = {
  mode: 'development',
  entry: {
    app: './resources/js/app.js',
    'script-editor': './resources/js/script-editor.js',
    'monaco-editor': './resources/js/monaco-editor.js',
    'version-manager': './resources/js/version-manager.js',
    'metrics-dashboard': './resources/js/metrics-dashboard.js'
  },
  output: {
    path: path.resolve(__dirname, 'public/build'),
    filename: '[name].js',
    publicPath: '/build/',
  },
  module: {
    rules: [
      {
        test: /\.tsx?$/,
        use: 'ts-loader',
        exclude: /node_modules/,
      },
      {
        test: /\.css$/,
        use: ['style-loader', 'css-loader'],
      },
      {
        test: /\.ttf$/,
        use: ['file-loader'],
      },
    ],
  },
  resolve: {
    extensions: ['.tsx', '.ts', '.js'],
  },
  plugins: [
    new MonacoWebpackPlugin({
      languages: ['javascript', 'typescript', 'json', 'sql'],
      features: [
        'accessibilityHelp',
        'anchorSelect',
        'bracketMatching',
        'caretOperations',
        'clipboard',
        'codeAction',
        'codelens',
        'colorPicker',
        'comment',
        'contextmenu',
        'coreCommands',
        'cursorUndo',
        'find',
        'folding',
        'fontZoom',
        'format',
        'gotoError',
        'gotoLine',
        'gotoSymbol',
        'hover',
        'inPlaceReplace',
        'indentation',
        'inlineHints',
        'inspectTokens',
        'linesOperations',
        'linkedEditing',
        'links',
        'multicursor',
        'parameterHints',
        'quickCommand',
        'quickHelp',
        'quickOutline',
        'referenceSearch',
        'rename',
        'smartSelect',
        'snippets',
        'suggest',
        'toggleHighContrast',
        'toggleTabFocusMode',
        'transpose',
        'unusualLineTerminators',
        'viewportSemanticTokens',
        'wordHighlighter',
        'wordOperations',
        'wordPartOperations'
      ]
    }),
  ],
  devtool: 'source-map',
  devServer: {
    contentBase: path.join(__dirname, 'public'),
    compress: true,
    port: 9000,
  },
};