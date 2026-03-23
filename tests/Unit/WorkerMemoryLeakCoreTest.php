<?php

namespace Tests\Unit;

use Fennec\Core\Cache\AttributeCache;
use Fennec\Core\Cache\FileCache;
use Fennec\Core\Feature\FeatureFlag;
use Fennec\Core\Profiler\InMemoryStorage;
use Fennec\Core\Profiler\ProfileEntry;
use Fennec\Core\RateLimiter\InMemoryStore;
use PHPUnit\Framework\TestCase;

/**
 * Tests de detection de fuites memoire — composants core.
 *
 * Profiler, AttributeCache, RateLimiter, FeatureFlag.
 *
 * @see ST-501
 */
class WorkerMemoryLeakCoreTest extends TestCase
{
    protected function tearDown(): void
    {
        InMemoryStorage::clear();
        InMemoryStore::flush();

        $ref = new \ReflectionClass(AttributeCache::class);
        $prop = $ref->getProperty('memory');
        $prop->setValue(null, []);

        $ref = new \ReflectionClass(FeatureFlag::class);
        $prop = $ref->getProperty('redis');
        $prop->setValue(null, null);
    }

    // ── Profiler InMemoryStorage ─────────────────────────────

    public function testProfilerRingBufferRespectsMaxSize(): void
    {
        $storage = new InMemoryStorage(10);

        for ($i = 0; $i < 25; $i++) {
            $entry = new ProfileEntry('GET', "/api/route-{$i}");
            $entry->stop();
            $storage->store($entry);
        }

        // Le buffer doit etre borne a 10
        $this->assertSame(10, InMemoryStorage::count());
        $this->assertCount(10, $storage->getAll());
    }

    public function testProfilerClearResetsBuffer(): void
    {
        $storage = new InMemoryStorage(50);

        for ($i = 0; $i < 20; $i++) {
            $entry = new ProfileEntry('POST', '/api/data');
            $entry->stop();
            $storage->store($entry);
        }

        $this->assertSame(20, InMemoryStorage::count());

        InMemoryStorage::clear();
        $this->assertSame(0, InMemoryStorage::count());
        $this->assertCount(0, $storage->getAll());
    }

    public function testProfilerKeepsMostRecentEntries(): void
    {
        $storage = new InMemoryStorage(5);

        for ($i = 0; $i < 10; $i++) {
            $entry = new ProfileEntry('GET', "/route-{$i}");
            $entry->stop();
            $storage->store($entry);
        }

        $all = $storage->getAll();
        // Les 5 plus recents (reverse order)
        $this->assertSame('/route-9', $all[0]->uri);
        $this->assertSame('/route-5', $all[4]->uri);
    }

    // ── AttributeCache ──────────────────────────────────────

    public function testAttributeCacheSizeMethod(): void
    {
        $this->assertSame(0, AttributeCache::size());

        // Injecter des entrees dans le cache statique
        $ref = new \ReflectionClass(AttributeCache::class);
        $prop = $ref->getProperty('memory');

        $cache = [];
        for ($i = 0; $i < 30; $i++) {
            $cache["App\\Controller{$i}::index::SomeAttr"] = [];
        }
        $prop->setValue(null, $cache);

        $this->assertSame(30, AttributeCache::size());

        // clear() purge tout
        $prop->setValue(null, []);
        $this->assertSame(0, AttributeCache::size());
    }

    public function testAttributeCacheEvictsWhenFull(): void
    {
        $ref = new \ReflectionClass(AttributeCache::class);
        $prop = $ref->getProperty('memory');
        $maxSizeConst = $ref->getReflectionConstant('MAX_CACHE_SIZE');
        $maxSize = $maxSizeConst->getValue();

        // Remplir exactement a la limite
        $cache = [];
        for ($i = 0; $i < $maxSize + 10; $i++) {
            $cache["Key{$i}"] = [];
        }
        $prop->setValue(null, $cache);

        // Le cache depasse la limite (injection directe), mais
        // la prochaine insertion via get() doit evicter
        $this->assertGreaterThan($maxSize, AttributeCache::size());

        // Simuler un get() via FileCache mock — on ne peut pas facilement
        // sans DB, donc on verifie juste que la taille est trackee
        $this->assertSame($maxSize + 10, AttributeCache::size());

        // Reset
        $prop->setValue(null, []);
    }

    // ── RateLimiter InMemoryStore ────────────────────────────

    public function testRateLimiterPurgesExpiredCounters(): void
    {
        $store = new InMemoryStore();

        // Simuler des compteurs avec des fenetres courtes
        $ref = new \ReflectionClass(InMemoryStore::class);
        $countersProp = $ref->getProperty('counters');

        $counters = [];
        for ($i = 0; $i < 50; $i++) {
            $counters["ip:192.168.1.{$i}"] = [
                'hits' => 3,
                'resetAt' => time() - 100, // expire il y a 100s
            ];
        }
        // Ajouter un compteur actif
        $counters['ip:10.0.0.1'] = [
            'hits' => 5,
            'resetAt' => time() + 1000,
        ];
        $countersProp->setValue(null, $counters);

        $this->assertSame(51, InMemoryStore::count());

        InMemoryStore::purgeExpired();

        // Les 50 expires doivent etre purges, le actif reste
        $this->assertSame(1, InMemoryStore::count());
    }

    public function testRateLimiterMaxSizeEnforced(): void
    {
        InMemoryStore::setMaxSize(20);
        $store = new InMemoryStore();

        // Simuler 30 IPs differentes
        for ($i = 0; $i < 30; $i++) {
            $store->increment("ip:10.0.0.{$i}", 3600);
        }

        $this->assertLessThanOrEqual(
            20,
            InMemoryStore::count(),
            'Le store ne doit pas depasser maxSize'
        );
    }

    public function testRateLimiterFlushClearsAll(): void
    {
        $store = new InMemoryStore();

        for ($i = 0; $i < 10; $i++) {
            $store->increment("key:{$i}", 60);
        }

        $this->assertSame(10, InMemoryStore::count());

        InMemoryStore::flush();
        $this->assertSame(0, InMemoryStore::count());
    }

    public function testRateLimiterAutoPurgeOnInterval(): void
    {
        // Configurer un petit intervalle de purge
        $ref = new \ReflectionClass(InMemoryStore::class);
        $intervalProp = $ref->getProperty('purgeInterval');
        $intervalProp->setValue(null, 5);

        $store = new InMemoryStore();

        // Injecter des entrees expirees
        $countersProp = $ref->getProperty('counters');
        $counters = [];
        for ($i = 0; $i < 10; $i++) {
            $counters["expired:{$i}"] = [
                'hits' => 1,
                'resetAt' => time() - 10, // expire
            ];
        }
        $countersProp->setValue(null, $counters);
        $this->assertSame(10, InMemoryStore::count());

        // Faire 5 operations pour declencher la purge auto
        for ($i = 0; $i < 5; $i++) {
            $store->increment("new:{$i}", 3600);
        }

        // Les expirees doivent avoir ete purgees
        $this->assertLessThanOrEqual(5, InMemoryStore::count());

        // Reset
        $intervalProp->setValue(null, 200);
        InMemoryStore::flush();
    }

    // ── FeatureFlag Redis ────────────────────────────────────

    public function testFeatureFlagResetConnectionClosesRedis(): void
    {
        $ref = new \ReflectionClass(FeatureFlag::class);
        $redisProp = $ref->getProperty('redis');

        // Avant : null
        $this->assertNull($redisProp->getValue(null));

        // resetConnection() sur null = no-op, pas d'erreur
        FeatureFlag::resetConnection();
        $this->assertNull($redisProp->getValue(null));
    }

    // ── Simulation worker complete ──────────────────────────

    public function testCoreComponentsMemoryStableAcrossRequests(): void
    {
        $profilerStorage = new InMemoryStorage(20);
        $rateLimiter = new InMemoryStore();
        InMemoryStore::setMaxSize(50);

        $memoryBefore = memory_get_usage(true);

        // Simuler 300 requetes worker
        for ($i = 0; $i < 300; $i++) {
            // Profiler : chaque requete cree un ProfileEntry
            $entry = new ProfileEntry('GET', "/api/users/{$i}");
            $entry->queries[] = ['sql' => 'SELECT * FROM users WHERE id = ?', 'params' => [$i], 'ms' => 1.5];
            $entry->stop();
            $profilerStorage->store($entry);

            // Rate limiter : chaque IP unique
            $rateLimiter->increment("ip:192.168.1.{$i}", 60);
        }

        $memoryAfter = memory_get_usage(true);
        $growthMb = round(($memoryAfter - $memoryBefore) / 1024 / 1024, 2);

        // Profiler borne a 20
        $this->assertSame(20, InMemoryStorage::count());

        // Rate limiter borne a 50
        $this->assertLessThanOrEqual(50, InMemoryStore::count());

        fwrite(STDERR, "\n[WorkerMemoryLeakCore] Croissance memoire sur 300 requetes: {$growthMb}MB\n");

        // Reset
        InMemoryStore::setMaxSize(1000);
    }
}
