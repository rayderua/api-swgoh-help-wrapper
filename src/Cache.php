<?php

namespace ApiSwgohHelp;
use Monolog\Logger;

Class Cache {

    /**
     * Default expires for cache
     */
    const CACHE_PLAYER      = 3600*6;
    const CACHE_GUILD       = 3600*24;
    const CACHE_DATA        = 3600*24;

    const CACHE_MIN_PLAYER  = 3600*1;
    const CACHE_MIN_GUILD   = 3600*1;

    /**
     * Monolog logger
     * @var Logger
     */
    protected $logger = null;

    private $params;

    public function __construct($config, $logger)
    {
        $this->logger = $logger;
    }

    public function config($config) {
        $this->params = $config;

        /* Cache storage */
        if ( TRUE == array_key_exists('cache', $config) ) {
            if (is_bool($config['cache']) ) {
                $this->params['cache'] = $config['cache'];
            } else {
                $this->logger->warning(sprintf('Invalid value for cache: %s', $config['cache']));
                $this->params['cache'] = false;
            }
        }

        if ( TRUE == array_key_exists('cache.player', $config) ) {
            if ( is_integer($config['cache.player']) ) {
                if ( $config['cache.player'] >= static::CACHE_MIN_PLAYER ) {
                    $this->params['cache.player'] = $config['cache.player'];
                } else {
                    $this->logger->error(sprintf('cache.player too small: %s, set to default: %s', $config['cache.player'], static::CACHE_MIN_PLAYER));
                    $this->params['cache.player'] = static::CACHE_MIN_PLAYER;
                }
            } else {
                $this->logger->error(sprintf('Invalid value for cache.player: %s', $config['cache.player']));
                $this->params['cache.player'] = static::CACHE_MIN_PLAYER;
            }
        }

        if ( TRUE == array_key_exists('cache.guild', $config) ) {
            if ( is_integer($config['cache.guild']) ) {
                if ( $config['cache.guild'] >= static::CACHE_MIN_GUILD ) {
                    $this->params['cache.guild'] = $config['cache.guild'];
                } else {
                    $this->logger->error(sprintf('cache.guild too small: %s, set to default: %s', $config['cache.guild'], static::CACHE_MIN_GUILD));
                    $this->params['cache.guild'] = static::CACHE_MIN_GUILD;
                }

            } else {
                $this->logger->error(sprintf('Invalid value for cache.guild: %s', $config['cache.guild']));
            }
        }

        if ( TRUE == array_key_exists('cache.data', $config) ) {
            if ( is_integer($config['cache.data']) ) {
                $this->params['cache.data'] = $config['cache.data'];
            } else {
                $this->logger->error(sprintf('Invalid value for cache.data: %s', $config['cache.data']));
            }
        }
        if ( TRUE == $this->params['cache'] && TRUE == array_key_exists('cache.storage', $this->params) ) {

            if ( FALSE == $this->initStorage($this->params['cache.storage']) ) {
                $this->logger->error(sprintf('Cache disabled cache.data: %s', $config['cache.data']));
                $this->params['cache'] = false;
            }
        }
    }

    private function initStorage($dir){
        if ( FALSE == file_exists($dir) ) {
            if ( FALSE == @mkdir($dir,0775,true) ) {
                $this->logger->error(sprintf('Could not create cache.storage directory: %s', $dir));
                return false;
            }
        }
        return true;
    }


    public function getConfig()
    {
        return array(
            'cache'         => $this->params['cache'],
            'cache.storage' => $this->params['cache.storage'],
            'cache.player'  => $this->params['cache.player'],
            'cache.guild'   => $this->params['cache.guild'],
            'cache.data'    => $this->params['cache.data'],
        );
    }


    public function players( $players ) {
        $result = [];
        foreach ( $players as $player ) {
            $result[$player] = [ 'success' => false, 'data' => null ];
        }
        if ( FALSE == $this->params['cache'] ) {
            $this->logger->error("Cache disabled, set cache=true for use cache data");
            foreach ( $players as $player ) {
                $result[$player] = ['success' => true, 'data' => null];
            }
        }
        return $result;
    }

    public function guilds( $players ) {
        $result = [];
        foreach ( $players as $player ) {
            $result[$player] = [ 'success' => false, 'data' => null ];
        }
        if ( FALSE == $this->params['cache'] ) {
            $this->logger->error("Cache disabled, set cache=true for use cache data");
            foreach ( $players as $player ) {
                $result[$player] = ['success' => true, 'data' => null];
            }
        }
        return $result;
    }

    public function data( $collections ) {
        $result = [];
        foreach ( $collections as $collection ) {
            $result[$collection] = [ 'success' => false, 'data' => null ];
        }
        if ( FALSE == $this->params['cache'] ) {
            $this->logger->error("Cache disabled, set cache=true for use cache data");
            foreach ( $collections as $collection ) {
                $result[$collection] = ['success' => true, 'data' => null];
            }
        }
        return $result;
    }
}
