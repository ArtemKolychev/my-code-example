import eslint from '@eslint/js';
import tseslint from 'typescript-eslint';
import prettierConfig from 'eslint-config-prettier';

export function createEslintConfig(tsconfigPath) {
  return tseslint.config(
    eslint.configs.recommended,
    ...tseslint.configs.recommendedTypeChecked,
    prettierConfig,
    {
      languageOptions: {
        parserOptions: {
          projectService: true,
          tsconfigRootDir: tsconfigPath,
        },
      },
      rules: {
        'no-var': 'error',
        '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
        '@typescript-eslint/no-explicit-any': 'error',
        '@typescript-eslint/explicit-function-return-type': 'error',
        '@typescript-eslint/no-floating-promises': 'error',
      },
    },
    {
      ignores: ['dist/**', 'node_modules/**', '**/*.js'],
    },
  );
}
