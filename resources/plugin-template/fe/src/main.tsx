import { createStubHost, loadValidationConstraintsFromApi } from '@polymorph/plugin-sdk';
import { mount } from './entry';

async function bootstrap(): Promise<void> {
  await loadValidationConstraintsFromApi().catch(() => undefined);

  const root = document.getElementById('root');
  if (!root) {
    throw new Error('Root element #root not found.');
  }

  mount(root, createStubHost({ basePath: '__PLUGIN_MOUNT_PATH__' }));
}

void bootstrap();
