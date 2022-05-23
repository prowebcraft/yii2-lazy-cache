<?php
/**
 * Adds lazy cache functionality to your class
 */

namespace prowebcraft\yii2lazycache;

use yii\caching\TagDependency;

trait Lazy
{

    /**
     * Local cache field
     * @see lazyField
     * @var array
     */
    private array $lazyCache = [];

    /**
     * Local cache static field
     * @see lazyField
     * @var array
     */
    private static array $staticLazyCache = [];

    /**
     * Provide local/cache storage for multiple data access between requests
     * @param array|string $key
     * Unique cache key
     * @param callable $getValueCallback
     * Callback, providing data if no cache available
     * @param string $storage
     * Storage type:
     * - local - runtime local variable, stored in specific object
     * - registry - runtime local variable, stored in global registry
     * - cache - variable, stored in Yii cache
     * - session - variable, stored in user session
     * @param int $ttl
     * TTL in seconds (valid only for cache and session storage), one day by default
     * @param array|string $tags
     * Tags for invalidating cache
     * @return mixed
     * @throws \Throwable
     */
    private function lazy(
        array|string $key,
        callable     $getValueCallback,
        string       $storage = 'registry',
        int          $ttl = 86400,
        array|string $tags = []
    ): mixed
    {
        $cacheKey = static::getLazyCacheKey($key);
        if (!isset($this->lazyCache[$cacheKey])) {
            $result = static::processStorage($cacheKey, $storage, $getValueCallback, $ttl, $tags);
            $this->lazyCache[$cacheKey] = $result;
        }

        return $this->lazyCache[$cacheKey];
    }

    /**
     * Provide local/cache storage for multiple data access between requests
     * @param array|string $key
     * Unique cache key
     * @param callable $getValueCallback
     * Callback, providing data if no cache available
     * @param string $storage
     * Storage type:
     * - local - runtime local variable, stored in specific object
     * - registry - runtime local variable, stored in global registry
     * - cache - variable, stored in Yii cache
     * - session - variable, stored in user session
     * @param int $ttl
     * TTL in seconds (valid only for cache and session storage), one day by default
     * @param array|string $tags
     * Tags for invalidating cache
     * @return mixed
     * @throws \Throwable
     */
    protected static function lazyStatic(
        array|string $key,
        callable     $getValueCallback,
        string       $storage = 'registry',
        int          $ttl = 86400,
        array|string $tags = []
    ): mixed
    {
        $cacheKey = static::getLazyCacheKey($key);
        if (!isset(static::$staticLazyCache[$cacheKey])) {
            $result = static::processStorage($cacheKey, $storage, $getValueCallback, $ttl, $tags);
            static::$staticLazyCache[$cacheKey] = $result;
        }

        return static::$staticLazyCache[$cacheKey];
    }

    /**
     * Check cached value in storage
     * @param string $cacheKey
     * @param string $storage
     * @param callable $getValueCallback
     * @param int $ttl
     * @param array|string $tags
     * @return mixed
     */
    private static function processStorage(
        string       $cacheKey,
        string       $storage,
        callable     $getValueCallback,
        int          $ttl,
        array|string $tags,
    ): mixed
    {
        switch ($storage) {
            case 'registry':
                if (($result = Registry::get($cacheKey)) !== null) {
                    return $result;
                }
                $result = $getValueCallback();
                Registry::set($cacheKey, $result);
                return $result;
            case 'cache':
                $cache = \Yii::$app->cache;
                if (($result = $cache->get($cacheKey)) === false) {
                    $result = $getValueCallback();
                    $dep = null;
                    if ($tags) {
                        $dep = new TagDependency(['tags' => $tags]);
                    }
                    $cache->set($cacheKey, $result, $ttl, $dep);
                }
                return $result;
            case 'session':
                if ($session = \Yii::$app->session) {
                    if (($result = $session->get($cacheKey)) === null) {
                        $result = $getValueCallback();
                        $session->set($cacheKey, $result);
                    }
                } else {
                    $result = $getValueCallback();
                }
                return $result;
            default:
                return $getValueCallback();
        }
    }

    /**
     * Invalidate cache by tags
     * @param string|array $tag
     * @return void
     */
    public static function invalidateByTag(string|array $tag): void
    {
        TagDependency::invalidate(\Yii::$app->cache, $tag);
    }

    /**
     * Provide cache storage for multiple data access between requests
     * @param string|array $key
     * Unique cache key
     * @param callable $getValueCallback
     * Callback, providing data if no cache available
     * @param int $ttl
     * TTL in seconds (valid only for cache and session storage), one day by default
     * @param array $tags
     * Tags for invalidating cache
     * @return mixed
     * @throws \Throwable
     */
    protected function lazyWithCache(
        string|array $key,
        callable     $getValueCallback,
        int          $ttl = 86400,
        array|string $tags = []
    ): mixed
    {
        return $this->lazy($key, $getValueCallback, 'cache', $ttl, $tags);
    }

    /**
     * Provide cache storage for multiple data access between requests
     * @param string|array $key
     * Unique cache key
     * @param callable $getValueCallback
     * Callback, providing data if no cache available
     * @param int $ttl
     * TTL in seconds (valid only for cache and session storage), one day by default
     * @param array $tags
     * Tags for invalidating cache
     * @return mixed
     * @throws \Throwable
     */
    protected static function lazyWithCacheStatic(
        string|array $key,
        callable     $getValueCallback,
        int          $ttl = 86400,
        array|string $tags = []
    ): mixed
    {
        return static::lazyStatic($key, $getValueCallback, 'cache', $ttl, $tags);
    }

    /**
     * Provide session storage for multiple data access between requests
     * @param string|array $key
     * @param callable $getValueCallback
     * @return mixed
     * @throws \Throwable
     */
    protected function lazyInSession(string|array $key, callable $getValueCallback): mixed
    {
        return $this->lazy($key, $getValueCallback, 'session');
    }

    /**
     * Provide session storage for multiple data access between requests
     * @param string|array $key
     * @param callable $getValueCallback
     * @return mixed
     * @throws \Throwable
     */
    protected function lazyInSessionStatic(string|array $key, callable $getValueCallback): mixed
    {
        return static::lazyStatic($key, $getValueCallback, 'session');
    }

    /**
     * Locally stored data
     * @param string|array $key
     * Unique cache key
     * @param callable $getValueCallback
     * Callback, providing data if no cache available
     * @return mixed
     * @throws \Throwable
     */
    public function lazyField(array|string $key, callable $getValueCallback): mixed
    {
        return $this->lazy($key, $getValueCallback, 'local');
    }

    /**
     * Locally stored data
     * @param string|array $key
     * Unique cache key
     * @param callable $getValueCallback
     * Callback, providing data if no cache available
     * @return mixed
     * @throws \Throwable
     */
    public static function lazyFieldStatic(array|string $key, callable $getValueCallback): mixed
    {
        return static::lazyStatic($key, $getValueCallback, 'local');
    }

    /**
     * Clear cache
     * @param array|string $key
     * @param string $type
     */
    protected function clearLazy(array|string $key, string $type = 'cache'): void
    {
        $cacheKey = static::getLazyCacheKey($key);
        unset($this->lazyCache[$cacheKey]);
        self::processLazyClear($cacheKey, $type);
    }

    /**
     * Clear cache
     * @param array|string $key
     * @param string $type
     * @return void
     */
    protected static function clearLazyStatic(array|string $key, string $type = 'cache'): void
    {
        $cacheKey = static::getLazyCacheKey($key);
        unset(static::$staticLazyCache[$cacheKey]);
        self::processLazyClear($cacheKey, $type);
    }

    /**
     * Clear cache processing
     * @param string $key
     * @param string $storage
     * @return void
     */
    private static function processLazyClear(string $key, string $storage): void
    {
        switch ($storage) {
            case 'registry':
                Registry::unset($key);
                break;
            case 'cache':
                \Yii::$app->cache->delete($key);
                break;
            case 'session':
                \Yii::$app->session?->remove($key);
                break;
        }
    }

    /**
     * Stringify key
     * @param array|string $key
     * @return string
     */
    protected static function getLazyCacheKey(array|string $key): string
    {
        if (is_array($key)) {
            $key = array_map(function ($item) {
                if (is_bool($item)) {
                    return $item ? 'true' : 'false';
                }

                if (is_object($item) || is_array($item)) {
                    return json_encode($item, JSON_UNESCAPED_UNICODE);
                }

                return (string)$item;
            }, $key);
            $key = implode(".", $key);
        }

        return "lc.$key";
    }

}