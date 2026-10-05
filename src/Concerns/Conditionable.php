<?php

declare(strict_types=1);

namespace CloudInit\Concerns;

use Closure;

/**
 * Fluent conditional mutations inspired by Laravel's builder conventions.
 */
trait Conditionable
{
    /**
     * Apply a callback when the condition is true, or the default otherwise.
     * Callback return values are ignored; this builder is always returned.
     *
     * @param  bool  $condition  Boolean condition used to select the callback.
     * @param  \Closure(static): mixed  $callback  Callback receiving the current builder; its result is ignored.
     * @param  (\Closure(static): mixed)|null  $default  Optional callback for the opposite condition.
     * @return $this
     */
    public function when(bool $condition, Closure $callback, ?Closure $default = null): static
    {
        $action = $condition ? $callback : $default;
        if ($action !== null) {
            $action($this);
        }

        return $this;
    }

    /**
     * Apply a callback when the condition is false.
     *
     * @param  bool  $condition  Boolean condition used to select the callback.
     * @param  \Closure(static): mixed  $callback  Callback receiving the current builder; its result is ignored.
     * @param  (\Closure(static): mixed)|null  $default  Optional callback for the opposite condition.
     * @return $this
     */
    public function unless(bool $condition, Closure $callback, ?Closure $default = null): static
    {
        return $this->when(!$condition, $callback, $default);
    }

    /**
     * Inspect or mutate this builder without breaking the chain.
     *
     * @param  \Closure(static): mixed  $callback  Callback receiving the current builder; its result is ignored.
     * @return $this
     */
    public function tap(Closure $callback): static
    {
        $callback($this);

        return $this;
    }
}
