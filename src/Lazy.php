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
     * @param bool $catch
     * catch exceptions inside callback
     * @return mixed
     * @throws \Throwable
     */
    protected function lazy(
        array|string $key,
        callable     $getValueCallback,
        string       $storage = 'registry',
        int          $ttl = 86400,
        array|string        $tags = [],
        bool         $catch = true
    ): mixed
    {
        $cacheKey = $this->getLazyCacheKey($key);
        if (!isset($this->lazyCache[$cacheKey])) {
            if ($storage === 'registry') {
                if (($result = Registry::get($cacheKey)) !== null) {

                    return $result;
                }
                $result = $getValueCallback();
                Registry::set($cacheKey, $result);
                return $result;
            }

            if ($storage === 'cache') {
                try {
                    $cache = \Yii::$app->cache;
                    if (($this->lazyCache[$cacheKey] = $cache->get($cacheKey)) === null) {
                        $result = $getValueCallback();
                        $this->lazyCache[$cacheKey] = $result;
                        $dep = null;
                        if ($tags) {
                            $dep = new TagDependency(['tags' => $tags]);
                        }
                        $cache->set($cacheKey, $this->lazyCache[$cacheKey], $ttl, $dep);
                    }
                } catch (\Throwable $e) {
                    if ($catch) {
                        \Yii::error('Error during lazy cache function execution: ' . $e->getMessage(), 'lazy');
                    } else {
                        throw $e;
                    }
                }
            } elseif ($storage === 'session') {
                if ($session = \Yii::$app->session) {
                    if (!$this->lazyCache[$cacheKey] = $session->get($cacheKey)) {
                        $result = $getValueCallback();
                        $session->set($cacheKey, $result);
                    }
                } else {
                    $this->lazyCache[$cacheKey] = $getValueCallback();
                }
            } else {
                $result = $getValueCallback();
                $this->lazyCache[$cacheKey] = $result;
            }
        }

        return $this->lazyCache[$cacheKey];
    }

    /**
     * Invalidate cache by tags
     * @param string|array $tag
     * @return void
     */
    public function invalidateByTag(string|array $tag): void
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
     * @param bool $catch
     * @return mixed
     * @throws \Throwable
     */
    protected function lazyWithCache(
        string|array $key,
        callable $getValueCallback,
        int $ttl = 86400,
        array|string $tags = [],
        bool $catch = true
    ): mixed
    {
        return $this->lazy($key, $getValueCallback, 'cache', $ttl, $tags, $catch);
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
     * Clear cache
     * @param array|string $key
     * @param string $type
     * @return $this
     */
    protected function clearLazy(array|string $key, string $type = 'local'): static
    {
        $cacheKey = $this->getLazyCacheKey($key);
        unset($this->lazyCache[$cacheKey]);
        switch ($type) {
            case 'registry':
                Registry::unset($cacheKey);
                break;
            case 'cache':
                \Yii::$app->cache->delete($cacheKey);
                break;
            case 'session':
                \Yii::$app->session?->remove($cacheKey);
                break;
        }

        return $this;
    }

    /**
     * Stringify key
     * @param array|string $key
     * @return string
     */
    protected function getLazyCacheKey(array|string $key): string
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