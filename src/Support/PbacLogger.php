<?php

namespace Modules\Pbac\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class PbacLogger extends Log
{

    public function log($level, $message, $context = [])
    {
        if (Config::get('pbac.logging.enabled')) {
            $this->channel(Config::get('pbac.logging.channel'))
                ->log($level, $message, $context);
        }
    }

    public function error($message, $context = [])
    {
        if (Config::get('pbac.logging.enabled')) {
            $this->channel(Config::get('pbac.logging.channel'))
                ->error($message, $context);
        }
    }

    public function warning($message, $context = [])
    {
        if (Config::get('pbac.logging.enabled')) {
            $this->channel(Config::get('pbac.logging.channel'))
                ->warning($message, $context);
        }
    }

    public function debug($message, $context = [])
    {
        if (Config::get('pbac.logging.enabled')) {
            $this->channel(Config::get('pbac.logging.channel'))
                ->debug($message, $context);
        }
    }

    public function info($message, $context = [])
    {
        if (Config::get('pbac.logging.enabled')) {
            $this->channel(Config::get('pbac.logging.channel'))
                ->info($message, $context);
        }

    }

}
