<?php
/**
 * Global Registry static class
 * User: Andrey Mistulov
 * Company: Aristos
 * Email: a.mistulov@aristos.pw
 * Date: 04.05.2022 20:54
 */

namespace prowebcraft\yii2lazycache;

class Registry
{

    protected static array $registry = [];

    /**
     * Write global registry
     * @param string $key
     * Key for storing values
     * @param mixed $value
     * Value to be stored
     * @param bool $merge
     * Merge arrays
     * @param $recursive bool
     * Merge arrays recursively
     */
    public static function set(string $key, mixed $value, bool $merge = false, bool $recursive = false): void
    {
        $root = &self::$registry;
        if (str_contains($key, '.')) {
            $keys = explode('.', $key);
            for ($k = 0; $k < count($keys) - 1; $k++) {
                $key = $keys[$k];
                if (!isset($root[$key]) || !is_array($root[$key])) $root[$key] = [];
                $root = &$root[$key];
            }
            $key = $keys[$k];
        }
        if ($merge && is_array($value) && isset($root[$key])) {
            $currentValue = $root[$key];
            $function = $recursive ? 'array_replace_recursive' : 'array_replace';
            $root[$key] = $function($currentValue, $value);
        } else {
            $root[$key] = $value;
        }
    }

    /**
     * Read global registry
     * @param string $key
     * Key for storing
     * @param mixed|null $default
     * Default value
     * @return mixed
     * @noinspection SuspiciousLoopInspection
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $array = self::$registry;
        if (isset($array[$key])) {

            return $array[$key];
        }

        $keys = explode('.', $key);
        foreach ($keys as $key) {
            if (isset($array[$key])) {
                $array = $array[$key];
            } else {
                return $default;
            }
        }

        return $array;
    }

    /**
     * Unset registry record
     * @param $key
     * @return void
     */
    public static function unset($key): void
    {
        $root = &self::$registry;
        if (str_contains($key, '.')) {
            $keys = explode('.', $key);
            for ($k = 0; $k < count($keys) - 1; $k++) {
                $key = $keys[$k];
                if (!isset($root[$key]) || !is_array($root[$key])) {

                    return;
                }
                $root = &$root[$key];
            }
            $key = $keys[$k];
        }
        if (isset($root[$key])) {
            unset($root[$key]);
        }
    }

}