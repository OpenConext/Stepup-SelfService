import js from '@eslint/js';
import typescriptEslint from 'typescript-eslint';

export default [
    {
        ignores: [
            'vendor/**',
            'node_modules/**',
            'var/**',
            'public/build/**',
            'build/**',
            'dist/**',
        ],
    },
    js.configs.recommended,
    ...typescriptEslint.configs.recommendedTypeChecked,
    {
        files: ['**/*.ts', '**/*.tsx'],
        languageOptions: {
            parserOptions: {
                project: './tsconfig.json',
                tsconfigRootDir: import.meta.dirname,
            },
        },
        rules: {
            '@typescript-eslint/no-explicit-any': 'warn',
            '@typescript-eslint/no-unused-vars': 'warn',
        },
    },
    {
        // Disable type-checking rules for JS files
        files: ['**/*.js', '**/*.jsx'],
        ...typescriptEslint.configs.disableTypeChecked,
    },
];
