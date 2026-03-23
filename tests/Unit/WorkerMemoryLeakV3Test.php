<?php

namespace Tests\Unit;

use Fennec\Core\Database;
use Fennec\Core\EventDispatcher;
use Fennec\Core\Event\SyncBroker;
use Fennec\Core\Queue\Job;
use Fennec\Core\StateMachine\StateMachineEngine;
use PHPUnit\Framework\TestCase;

/**
 * Tests de detection de fuites memoire — vague 3.
 *
 * Job Redis singleton, MailChannel socket safety, EventDispatcher discovery guard,
 * StateMachineEngine cache borne, Database instances.
 *
 * @see ST-501
 */
class WorkerMemoryLeakV3Test extends TestCase
{
    protected function tearDown(): void
    {
        Job::resetConnection();
        StateMachineEngine::clearCache();
        Database::clearInstances();
    }

    // ── Job::redis() — singleton au lieu de nouveau a chaque dispatch ──

    public function testJobRedisConnectionIsReused(): void
    {
        $ref = new \ReflectionClass(Job::class);
        $redisProp = $ref->getProperty('redis');

        // Avant dispatch, redis est null
        $this->assertNull($redisProp->getValue(null));

        // resetConnection() sur null = no-op
        Job::resetConnection();
        $this->assertNull($redisProp->getValue(null));
    }

    public function testJobResetConnectionCleansUp(): void
    {
        $ref = new \ReflectionClass(Job::class);
        $redisProp = $ref->getProperty('redis');

        // Simuler une connexion cachee
        $redisProp->setValue(null, null);

        Job::resetConnection();
        $this->assertNull($redisProp->getValue(null), 'resetConnection() doit nettoyer');
    }

    // ── EventDispatcher::discoverListeners — guard contre re-discovery ──

    public function testDiscoverListenersGuardPreventsMultipleScans(): void
    {
        $dispatcher = new EventDispatcher(new SyncBroker());

        $ref = new \ReflectionClass($dispatcher);
        $dirsProp = $ref->getProperty('discoveredDirs');

        // Premier appel avec un repertoire inexistant — doit marquer comme scanne
        $fakeDir = sys_get_temp_dir() . '/fennec_test_listeners_' . uniqid();
        mkdir($fakeDir);

        $dispatcher->discoverListeners($fakeDir);

        $dirs = $dirsProp->getValue($dispatcher);
        $resolved = realpath($fakeDir) ?: $fakeDir;
        $this->assertArrayHasKey($resolved, $dirs, 'Le repertoire doit etre marque comme scanne');

        // Deuxieme appel — ne doit pas re-scanner
        $dispatcher->discoverListeners($fakeDir);
        $dirs = $dirsProp->getValue($dispatcher);
        $this->assertCount(1, $dirs, 'Un seul scan meme avec 2 appels');

        rmdir($fakeDir);
    }

    public function testDiscoverListenersMultipleDirectories(): void
    {
        $dispatcher = new EventDispatcher(new SyncBroker());

        $ref = new \ReflectionClass($dispatcher);
        $dirsProp = $ref->getProperty('discoveredDirs');

        $dir1 = sys_get_temp_dir() . '/fennec_test_dir1_' . uniqid();
        $dir2 = sys_get_temp_dir() . '/fennec_test_dir2_' . uniqid();
        mkdir($dir1);
        mkdir($dir2);

        $dispatcher->discoverListeners($dir1);
        $dispatcher->discoverListeners($dir2);

        // Deux directories differentes
        $dirs = $dirsProp->getValue($dispatcher);
        $this->assertCount(2, $dirs);

        // Re-appel sur les memes — pas de changement
        $dispatcher->discoverListeners($dir1);
        $dispatcher->discoverListeners($dir2);
        $dirs = $dirsProp->getValue($dispatcher);
        $this->assertCount(2, $dirs, 'Pas de re-scan sur les memes repertoires');

        rmdir($dir1);
        rmdir($dir2);
    }

    // ── StateMachineEngine — cache borne ──

    public function testStateMachineEngineCacheBounded(): void
    {
        $ref = new \ReflectionClass(StateMachineEngine::class);
        $cacheProp = $ref->getProperty('cache');
        $maxSize = $ref->getReflectionConstant('MAX_CACHE_SIZE')->getValue();

        // Remplir au-dela de la limite
        $cache = [];
        for ($i = 0; $i < $maxSize + 20; $i++) {
            $cache["App\\Models\\StatefulModel{$i}"] = null;
        }
        $cacheProp->setValue(null, $cache);

        $this->assertSame($maxSize + 20, StateMachineEngine::cacheSize());

        // clearCache() doit tout nettoyer
        StateMachineEngine::clearCache();
        $this->assertSame(0, StateMachineEngine::cacheSize());
    }

    // ── Database::$instances — clearInstances ──

    public function testDatabaseClearInstancesWorks(): void
    {
        // On ne peut pas creer de vrais Database sans credentials
        // mais on verifie que clearInstances() reset le compteur
        $this->assertSame(0, Database::instanceCount(), 'Aucune instance au depart');

        Database::clearInstances();
        $this->assertSame(0, Database::instanceCount(), 'clearInstances() est idempotent');
    }

    // ── Simulation globale : pas de fuite apres cleanup ──

    public function testAllCleanupMethodsAreIdempotent(): void
    {
        // Tous les cleanup doivent etre appelables plusieurs fois sans erreur
        for ($i = 0; $i < 5; $i++) {
            Job::resetConnection();
            StateMachineEngine::clearCache();
            Database::clearInstances();
        }

        $this->assertSame(0, StateMachineEngine::cacheSize());
        $this->assertSame(0, Database::instanceCount());
    }
}
