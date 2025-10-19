<?php

namespace Modules\Pbac\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Psr\Log\LogLevel;


class PbacLogger
{
    private const LEVELS = [
        LogLevel::DEBUG => 100,
        LogLevel::INFO => 200,
        LogLevel::NOTICE => 250,
        LogLevel::WARNING => 300,
        LogLevel::ERROR => 400,
        LogLevel::CRITICAL => 500,
        LogLevel::ALERT => 550,
        LogLevel::EMERGENCY => 600,
    ];


    /**
     * Checks if a given log level should be processed based on the configuration.
     *
     * @param  string  $level
     * @return bool
     */
    private function shouldLog(string $level): bool
    {
        if (!Config::get('pbac.logging.enabled', false)) {
            return false;
        }

        $minimumLevel = Config::get('pbac.logging.level', LogLevel::DEBUG);

        // If the level is not a valid PSR-3 level, we don't log it.
        if (!isset(self::LEVELS[$level]) || !isset(self::LEVELS[$minimumLevel])) {
            return false;
        }

        return self::LEVELS[$level] >= self::LEVELS[$minimumLevel];
    }


    public function log($level, $message, $context = []): void
    {
        if ($this->shouldLog($level)) {
            Log::channel(Config::get('pbac.logging.channel'))->log($level, $message, $context);
        }
    }


    public function error(string $message, array $context = []): void
    {
        if ($this->shouldLog(LogLevel::ERROR)) {
            Log::channel(Config::get('pbac.logging.channel'))->error($message, $context);
        }
    }

    public function warning(string $message, array $context = []): void
    {
        if ($this->shouldLog(LogLevel::WARNING)) {
            Log::channel(Config::get('pbac.logging.channel'))->warning($message, $context);
        }
    }

    public function debug(string $message, array $context = []): void
    {
        if ($this->shouldLog(LogLevel::DEBUG)) {
            Log::channel(Config::get('pbac.logging.channel'))->debug($message, $context);
        }
    }

    public function info(string $message, array $context = []): void
    {
        if ($this->shouldLog(LogLevel::INFO)) {
            Log::channel(Config::get('pbac.logging.channel'))->info($message, $context);
        }
    }

}
