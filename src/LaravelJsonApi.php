<?php

namespace CloudCreativity\LaravelJsonApi;

class LaravelJsonApi
{

    /**
     * Indicates if Laravel JSON API migrations will be run.
     *
     * @var bool
     */
    public static $runMigrations = true;

    /**
     * Indicates if listeners will be bound to the Laravel queue events.
     *
     * @var bool
     */
    public static $queueBindings = true;

    /**
     * @return LaravelJsonApi
     */
    public static function ignoreMigrations(): self
    {
        static::$runMigrations = false;

        return new self();
    }

    /**
     * @return LaravelJsonApi
     */
    public static function skipQueueBindings(): self
    {
        static::$queueBindings = false;

        return new self();
    }
}