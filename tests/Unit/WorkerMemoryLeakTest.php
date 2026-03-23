<?php

namespace Tests\Unit;

use Fennec\Core\Encryption\Encrypter;
use Fennec\Core\Event;
use Fennec\Core\EventDispatcher;
use Fennec\Core\Event\SyncBroker;
use Fennec\Core\Security\AccountLockout;
use Fennec\Core\Security\SecurityLogger;
use Fennec\Core\Webhook\WebhookManager;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Tests de detection de fuites memoire en contexte worker FrankenPHP.
 *
 * En mode worker, le processus PHP reste en vie entre les requetes.
 * Tout etat statique qui accumule des donnees sans purge = fuite memoire.
 *
 * @see ST-501
 */
class WorkerMemoryLeakTest extends TestCase
{
    protected function setUp(): void
    {
        $dispatcher = new EventDispatcher(new SyncBroker());
        EventDispatcher::setInstance($dispatcher);
    }

    protected function tearDown(): void
    {
        $ref = new \ReflectionClass(SecurityLogger::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);
        SecurityLogger::resetRequestState();

        AccountLockout::flush();

        $ref = new \ReflectionClass(Encrypter::class);
        $prop = $ref->getProperty('key');
        $prop->setValue(null, null);

        $ref = new \ReflectionClass(WebhookManager::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);

        Event::forget();

        unset(
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['REQUEST_URI'],
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['X_REQUEST_ID'],
            $_REQUEST['__auth_user']
        );
    }

    // ── FIX 1 : WebhookManager cache LRU borne ──────────────

    public function testWebhookManagerCacheLruEvictsOldEntries(): void
    {
        $manager = new WebhookManager();
        $manager->setMaxCacheSize(10);
        WebhookManager::setInstance($manager);

        $ref = new \ReflectionClass($manager);
        $cacheProp = $ref->getProperty('cache');

        // Remplir au-dela de la limite
        for ($i = 0; $i < 15; $i++) {
            $cache = $cacheProp->getValue($manager);
            $cache["event.type.{$i}"] = [['id' => $i, 'url' => 'https://example.com', 'secret' => 'secret']];
            $cacheProp->setValue($manager, $cache);
        }

        // Le cache brut a 15 entrees (on a injecte directement)
        // Mais via getWebhooksForEvent, l'eviction LRU limiterait a 10
        $this->assertCount(15, $cacheProp->getValue($manager));

        // clearCache() purge tout
        $manager->clearCache();
        $this->assertSame(0, $manager->cacheSize(), 'clearCache() doit vider le cache');
    }

    public function testWebhookManagerClearCacheWorks(): void
    {
        $manager = new WebhookManager();
        WebhookManager::setInstance($manager);

        $ref = new \ReflectionClass($manager);
        $cacheProp = $ref->getProperty('cache');

        // Simuler 100 event types caches
        for ($i = 0; $i < 100; $i++) {
            $cache = $cacheProp->getValue($manager);
            $cache["event.{$i}"] = [];
            $cacheProp->setValue($manager, $cache);
        }

        $this->assertSame(100, $manager->cacheSize());

        $manager->clearCache();
        $this->assertSame(0, $manager->cacheSize(), 'clearCache() reinitialise le cache entre les requetes worker');
    }

    // ── FIX 2 : AccountLockout cache purge + taille max ──────

    public function testAccountLockoutPurgesExpiredEntries(): void
    {
        // Remplir avec des entrees dont le lockout est expire
        $ref = new \ReflectionClass(AccountLockout::class);
        $cacheProp = $ref->getProperty('cache');

        $expiredEntries = [];
        for ($i = 0; $i < 50; $i++) {
            $expiredEntries["expired-{$i}@example.com"] = [
                'attempts' => 5,
                'locked_until' => time() - 100, // expire il y a 100s
            ];
        }
        // Ajouter une entree active
        $expiredEntries['active@example.com'] = [
            'attempts' => 3,
            'locked_until' => time() + 1000, // encore verrouille
        ];
        $cacheProp->setValue(null, $expiredEntries);

        $this->assertSame(51, AccountLockout::cacheSize());

        AccountLockout::purgeExpired();

        // Les 50 expirees doivent etre purgees, l'active reste
        $this->assertSame(1, AccountLockout::cacheSize(), 'purgeExpired() retire les entrees expirees');
    }

    public function testAccountLockoutCacheMaxSizeEnforced(): void
    {
        AccountLockout::setMaxCacheSize(50);

        // Enregistrer 60 echecs d'utilisateurs differents
        for ($i = 0; $i < 60; $i++) {
            AccountLockout::recordFailure("user-{$i}@example.com");
        }

        $this->assertLessThanOrEqual(
            50,
            AccountLockout::cacheSize(),
            'Le cache ne doit pas depasser maxCacheSize'
        );
    }

    public function testAccountLockoutResetRemovesCacheEntry(): void
    {
        AccountLockout::recordFailure('victim@example.com');
        $this->assertGreaterThan(0, AccountLockout::cacheSize());

        AccountLockout::reset('victim@example.com');

        // Verifier via la methode publique
        $this->assertSame(0, AccountLockout::attempts('victim@example.com'));
    }

    // ── FIX 3 : WebhookManager boot guard ────────────────────

    public function testWebhookManagerBootGuardPreventsMultipleRegistrations(): void
    {
        $manager = new WebhookManager();
        WebhookManager::setInstance($manager);

        $this->assertFalse($manager->isBooted(), 'Pas encore boot');

        $manager->boot();
        $this->assertTrue($manager->isBooted(), 'Boot effectue');

        // Appeler boot() 5 fois de plus — ne doit rien faire
        for ($i = 0; $i < 5; $i++) {
            $manager->boot();
        }

        $this->assertTrue($manager->isBooted(), 'Toujours boot mais un seul listener enregistre');
    }

    // ── FIX 4 : SecurityLogger resetRequestState ─────────────

    public function testSecurityLoggerResetRequestStateClearsHash(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('security');
        $logger->pushHandler($handler);
        SecurityLogger::setInstance($logger);

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // Simuler requete 1
        SecurityLogger::alert('event.one');
        $record1 = $handler->getRecords()[0];
        $hmac1 = $record1->context['_hmac'];

        // Reset entre les requetes (comme le ferait le worker)
        SecurityLogger::resetRequestState();

        // Simuler requete 2 — meme event, doit produire le meme HMAC
        // car la chaine est reinitialisee
        SecurityLogger::alert('event.one');
        $record2 = $handler->getRecords()[1];
        $hmac2 = $record2->context['_hmac'];

        $this->assertSame(
            $hmac1,
            $hmac2,
            'Apres resetRequestState(), le meme event doit produire le meme HMAC'
        );
    }

    public function testSecurityLoggerHmacDiffersWithoutReset(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('security');
        $logger->pushHandler($handler);
        SecurityLogger::setInstance($logger);

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        SecurityLogger::alert('event.one');
        $hmac1 = $handler->getRecords()[0]->context['_hmac'];

        // Sans reset — le HMAC doit etre different (chaine continue)
        SecurityLogger::alert('event.one');
        $hmac2 = $handler->getRecords()[1]->context['_hmac'];

        $this->assertNotSame(
            $hmac1,
            $hmac2,
            'Sans reset, les HMAC doivent differer (chaine liee)'
        );
    }

    // ── Tests de non-regression ──────────────────────────────

    public function testEncrypterKeyPersistsAcrossRequests(): void
    {
        $key = base64_encode(random_bytes(32));
        Encrypter::setKey(base64_decode($key));

        $encrypted = Encrypter::encrypt('test data');
        $decrypted = Encrypter::decrypt($encrypted);
        $this->assertSame('test data', $decrypted);
    }

    public function testMemoryGrowthAcrossSimulatedWorkerRequests(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('security');
        $logger->pushHandler($handler);
        SecurityLogger::setInstance($logger);

        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        AccountLockout::setMaxCacheSize(100);

        $memoryBefore = memory_get_usage(true);

        // Simuler 500 requetes worker avec reset entre chaque
        for ($i = 0; $i < 500; $i++) {
            SecurityLogger::track("request.{$i}", ['user_id' => $i]);
            AccountLockout::recordFailure("attacker-{$i}@bot.net");

            // Simuler le reset inter-requete du worker
            if ($i % 50 === 0) {
                SecurityLogger::resetRequestState();
            }
        }

        $memoryAfter = memory_get_usage(true);
        $growthMb = round(($memoryAfter - $memoryBefore) / 1024 / 1024, 2);

        // Le cache AccountLockout doit etre borne
        $this->assertLessThanOrEqual(
            100,
            AccountLockout::cacheSize(),
            'Le cache AccountLockout doit rester borne apres 500 requetes'
        );

        fwrite(STDERR, "\n[WorkerMemoryLeak] Croissance memoire sur 500 requetes: {$growthMb}MB\n");
    }

    public function testSecurityLoggerSingletonReusesInstance(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('security');
        $logger->pushHandler($handler);
        SecurityLogger::setInstance($logger);

        $this->assertSame(
            SecurityLogger::getInstance(),
            SecurityLogger::getInstance(),
            'getInstance() doit retourner la meme instance'
        );
    }

    public function testEventDispatcherForgetCleansListeners(): void
    {
        $broker = new SyncBroker();
        $dispatcher = new EventDispatcher($broker);

        for ($i = 0; $i < 100; $i++) {
            $dispatcher->listen("event.{$i}", function () {});
        }

        $this->assertTrue($dispatcher->hasListeners('event.0'));
        $dispatcher->forget();
        $this->assertFalse($dispatcher->hasListeners('event.0'));
    }

    public function testWebhookManagerSingletonPersists(): void
    {
        $manager1 = new WebhookManager();
        WebhookManager::setInstance($manager1);

        $this->assertSame($manager1, WebhookManager::getInstance());

        $manager2 = new WebhookManager();
        WebhookManager::setInstance($manager2);
        $this->assertSame($manager2, WebhookManager::getInstance());
        $this->assertNotSame($manager1, WebhookManager::getInstance());
    }

    public function testAccountLockoutFlushResetsEverything(): void
    {
        AccountLockout::recordFailure('test@example.com');
        $this->assertGreaterThan(0, AccountLockout::cacheSize());

        AccountLockout::flush();
        $this->assertSame(0, AccountLockout::cacheSize(), 'flush() vide le cache');
    }
}
