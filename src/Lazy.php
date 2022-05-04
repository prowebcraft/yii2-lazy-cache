<?php
/**
 * Adds lazy cache functionality to your class
 */

namespace prowebcraft\yii2lazycache;

trait Lazy
{

    /**
     * Local cache field
     * @see lazyField
     * @see getAllLazyFields
     * @see getCountLazyFields
     * @var array
     */
    private array $lazyCache = [];

    /**
     * Provide local/cache storage for multiple data access requests
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
     * @param array $tags
     * Tags for invalidating cache
     * @param bool $catch
     * catch exceptions inside callback
     * @return mixed
     */
    protected function lazy(
        array|string $key,
        callable     $getValueCallback,
        string       $storage = 'registry',
        int          $ttl = 86400,
        array        $tags = [],
        bool         $catch = true
    )
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
                        if ($tags) {
                            // todo tags dependency
                        }
                        $cache->set($cacheKey, $this->lazyCache[$cacheKey], $ttl);
                    }
                } catch (\Throwable $e) {
                    if ($catch) {
                        //todo log error
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
     * Provide cache storage for multiple data access requests
     * @param string|array $key
     * Unique cache key
     * @param callable $getValueCallback
     * Callback, providing data if no cache available
     * @param int $ttl
     * TTL in seconds (valid only for cache and session storage), one day by default
     * @param array|string $tags
     * Tags for invalidating cache
     * @param bool $catch
     * @return mixed
     */
    protected function lazyWithCache(string|array $key, callable $getValueCallback, int $ttl = 86400, $tags = [], $catch = true)
    {
        return $this->lazy($key, $getValueCallback, 'cache', $ttl, $tags, $catch);
    }

    /**
     * Locally stored data
     * @param string|array $key
     * Unique cache key
     * @param callable $getValueCallback
     * Callback, providing data if no cache available
     * @return mixed
     */
    public function lazyField(array|string $key, callable $getValueCallback): mixed
    {
        return $this->lazy($key, $getValueCallback, 'local');
    }

    /**
     * Возвращает ассоциативный массив с данными установленными во внутреннем кеше класса
     * @return array
     */
    public function getAllLazyFields(): array
    {
        return $this->lazyCache;
    }

    /**
     * Возвращает количество элементов во внутреннем кеше класса
     * @return int
     */
    public function getCountLazyFields(): int
    {
        return count($this->lazyCache);
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
                //todo yii2 cache logic
                break;
            case 'session':
                //todo yii2 session cache logic
                break;
        }

        return $this;
    }

    /**
     * @param $key
     * @return string
     */
    protected function getLazyCacheKey($key): string
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