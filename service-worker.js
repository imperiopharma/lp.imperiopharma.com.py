// Quando o SW é instalado
self.addEventListener('install', (event) => {
  // skipWaiting() faz com que esta nova versão do SW
  // seja ativada imediatamente, sem esperar o SW antigo "morrer".
  self.skipWaiting();
});

// Quando o SW é ativado (após instalar)
self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      // Desregistra o SW atual, ou seja,
      // avisa ao navegador que não queremos mais manter este service worker
      await self.registration.unregister();
      
      // Localiza todas as abas (clients) que estão usando este SW
      // e faz com que elas recarreguem, assim param de usar o SW.
      const allClients = await clients.matchAll({ includeUncontrolled: true });
      allClients.forEach(client => {
        client.navigate(client.url);
      });
    })()
  );
});
