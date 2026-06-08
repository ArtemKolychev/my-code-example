import { createEslintConfig } from '../../eslint.config.base.mjs';
import { fileURLToPath } from 'url';

export default createEslintConfig(fileURLToPath(import.meta.url));
