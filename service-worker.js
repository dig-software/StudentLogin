self.addEventListener('install', function(e) {
  e.waitUntil(
    caches.open('student-portal-v1').then(function(cache) {
      return cache.addAll([
        '/',
        '/index.html',
        '/view.php',
        '/messages.php',
        '/group_chat.php',
        '/manifest.json',
        // Add more static assets as needed
      ]);
    })
  );
});

self.addEventListener('fetch', function(e) {
  e.respondWith(
    caches.match(e.request).then(function(response) {
      return response || fetch(e.request);
    })
  );
});
