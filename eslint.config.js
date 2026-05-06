import react from 'eslint-plugin-react'
import reactHooks from 'eslint-plugin-react-hooks'

const browserGlobals = {
  window: 'readonly',
  document: 'readonly',
  navigator: 'readonly',
  location: 'readonly',
  localStorage: 'readonly',
  sessionStorage: 'readonly',
  fetch: 'readonly',
  Headers: 'readonly',
  Request: 'readonly',
  Response: 'readonly',
  URL: 'readonly',
  URLSearchParams: 'readonly',
  FormData: 'readonly',
  Blob: 'readonly',
  File: 'readonly',
  FileReader: 'readonly',
  atob: 'readonly',
  btoa: 'readonly',
  setTimeout: 'readonly',
  clearTimeout: 'readonly',
  setInterval: 'readonly',
  clearInterval: 'readonly',
  requestAnimationFrame: 'readonly',
  cancelAnimationFrame: 'readonly',
  console: 'readonly',
  alert: 'readonly',
  confirm: 'readonly',
  prompt: 'readonly',
  performance: 'readonly',
  Event: 'readonly',
  CustomEvent: 'readonly',
  HTMLElement: 'readonly',
  Element: 'readonly',
  Node: 'readonly',
  MutationObserver: 'readonly',
  IntersectionObserver: 'readonly',
  ResizeObserver: 'readonly',
  Image: 'readonly',
  DOMParser: 'readonly',
  structuredClone: 'readonly',
}

const nodeGlobals = {
  process: 'readonly',
  __dirname: 'readonly',
  __filename: 'readonly',
  module: 'readonly',
  exports: 'readonly',
  require: 'readonly',
  Buffer: 'readonly',
  global: 'readonly',
  globalThis: 'readonly',
  setImmediate: 'readonly',
  clearImmediate: 'readonly',
  queueMicrotask: 'readonly',
}

export default [
  {
    ignores: [
      'dist/**',
      'node_modules/**',
      'api/vendor/**',
      'api/assets/**',
      '_bmad-output/**',
      'docs/archive/**',
      '.agent/**',
      '.agents/**',
      '.claude/**',
      '.codex/**',
      '_bmad/**',
    ],
  },
  {
    files: ['**/*.{js,jsx,mjs,cjs}'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      parserOptions: {
        ecmaFeatures: {
          jsx: true,
        },
      },
      globals: {
        ...browserGlobals,
        ...nodeGlobals,
      },
    },
    plugins: {
      react,
      'react-hooks': reactHooks,
    },
    rules: {
      'no-undef': 'error',
      'no-unused-vars': [
        'warn',
        {
          argsIgnorePattern: '^_',
          caughtErrorsIgnorePattern: '^(e|err|error|_.*)$',
          varsIgnorePattern: '^React$',
        },
      ],
      'react/jsx-uses-vars': 'error',
      'react-hooks/rules-of-hooks': 'error',
    },
  },
]
