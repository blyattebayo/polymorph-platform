import type { PluginMount } from '@polymorph/plugin-sdk';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import __PLUGIN_CLASS__App from './App';

export const mount: PluginMount = (root, host) => {
  const appRoot = createRoot(root);

  appRoot.render(
    <StrictMode>
      <BrowserRouter>
        <__PLUGIN_CLASS__App host={host} />
      </BrowserRouter>
    </StrictMode>
  );

  return {
    unmount() {
      appRoot.unmount();
    },
  };
};
