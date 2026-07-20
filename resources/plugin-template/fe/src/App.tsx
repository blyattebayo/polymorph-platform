import type { PluginHost } from '@polymorph/plugin-sdk';
import { useEffect, useState } from 'react';

type HelloPayload = {
  plugin: string;
  message: string;
  user_id: number;
  items_count: number;
};

const HelloView = ({ host }: { host: PluginHost }) => {
  const http = host.http;
  const notifications = host.notifications;
  const [pending, setPending] = useState(false);
  const [payload, setPayload] = useState<HelloPayload | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    notifications?.success('__PLUGIN_NAME__ mounted');
    return () => notifications?.warning('__PLUGIN_NAME__ unmounted');
  }, [notifications]);

  const loadHello = async () => {
    setPending(true);
    setError(null);
    try {
      const response = await http.get<{ data: HelloPayload }>('/api/v1/plugins/__PLUGIN_ID__/hello');
      setPayload(response.data.data);
      notifications?.success('__PLUGIN_NAME__: backend connected');
    } catch (e) {
      const message = e instanceof Error ? e.message : 'Request failed';
      setError(message);
      notifications?.error('__PLUGIN_NAME__: failed to load /hello');
    } finally {
      setPending(false);
    }
  };

  return (
    <main style={{ maxWidth: 840, margin: '24px auto', padding: '0 16px' }}>
      <h1 style={{ marginBottom: 8 }}>__PLUGIN_NAME__</h1>
      <p style={{ marginTop: 0, opacity: 0.8 }}>
        mountPath: <code>{host.navigation?.basePath ?? '(standalone)'}</code>
      </p>
      <div style={{ display: 'flex', gap: 8 }}>
        <button onClick={loadHello} disabled={pending}>
          {pending ? 'Loading...' : 'Load /hello'}
        </button>
        {host.navigation ? (
          <button onClick={() => host.navigation?.navigate('/plugins/__PLUGIN_ID__')}>Go to root</button>
        ) : null}
      </div>
      {error ? (
        <p style={{ color: '#b91c1c' }}>
          Error: <code>{error}</code>
        </p>
      ) : null}
      {payload ? <pre>{JSON.stringify(payload, null, 2)}</pre> : null}
    </main>
  );
};

export default function __PLUGIN_CLASS__App({ host }: { host: PluginHost }) {
  return <HelloView host={host} />;
}
