import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineFePluginConfig } from '@blyattebayo/polymorph-sdk/vite';

// Конфиг плагина собирается SDK-пресетом из ../extension.json (contributes.frontend).
export default defineFePluginConfig({
  dirname: path.dirname(fileURLToPath(import.meta.url)),
});
