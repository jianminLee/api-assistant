<?php


namespace Orzlee\ApiAssistant\Traits\Model;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

trait HasCache
{
    protected $ttl;

    /**
     * @return string
     */
    protected function tag(): string
    {
        return config('api-assistant.model_cache_tag');
    }

    /**
     * @param null $key
     * @param \Closure|null $closure
     *
     * @return mixed|Collection
     */
    public static function cache($key = null, \Closure $closure = null)
    {
        $model = new static();

        if ($key instanceof \Closure) {
            $closure = $key;
            $key     = null;
        }

        return Cache::tags($model->tag())
            ->remember(
                $key ?? get_class(),
                now()->addMinutes($model->getCacheTtl()),
                function () use ($closure, $model) {
                    if ($closure) {
                        return $closure($model);
                    }
                    return $model->get();
                }
            );
    }

    /**
     * forget this model cache
     *
     * @param null $key
     * @return bool
     */
    public function forgetCache($key = null): bool
    {
        return Cache::tags($this->tag())->forget($key ?? get_class());
    }

    /**
     * set cache ttl
     *
     * @param $minutes
     * @return $this
     */
    public function setCacheTtl($minutes): HasCache
    {
        $this->ttl = $minutes;
        return $this;
    }

    /**
     * @return int
     */
    public function getCacheTtl(): int
    {
        return $this->ttl ?: 60;
    }
}
