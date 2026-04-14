// @ts-check
import eslint from '@eslint/js';
import eslintPluginPrettierRecommended from 'eslint-plugin-prettier/recommended';
import globals from 'globals';
import tseslint from 'typescript-eslint';

import { createEslintConfig } from '../eslint.config.base.mjs';

export default createEslintConfig({ eslint, tseslint, eslintPluginPrettierRecommended, globals, tsconfigRootDir: import.meta.dirname });
