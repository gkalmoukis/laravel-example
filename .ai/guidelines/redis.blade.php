# Redis

Redis backs the cache, the queue and sessions in local and production, through the phpredis
extension.

- Queues and sessions use Redis database 0; the cache uses database 1, so clearing the cache
  never drops queued jobs or sessions.
- Failed jobs and job batches live in MySQL, not Redis.
- Tests do **not** use Redis: `phpunit.xml` pins the cache to `array`, the queue to `sync` and
  sessions to `array`. Assert queue behaviour with `Queue::fake()` and dedicated job tests
  rather than by running a worker.
- Anything that sends email or does slow work implements `ShouldQueue`.
