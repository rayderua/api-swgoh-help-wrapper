<?php

namespace ApiSwgohHelp;
use Monolog\Logger;

Class Cache {

    /**
     * Monolog logger
     * @var Logger
     */
    protected $logger = null;


    /**
     * Default expires for cache
     */
    const CACHE_PLAYER      = 3600*6;
    const CACHE_GUILD       = 3600*24;
    const CACHE_DATA        = 3600*24;
    const CACHE_MIN_PLAYER  = 3600*1;
    const CACHE_MIN_GUILD   = 3600*1;


    /**
     * Cache config
     * @var array
     */
    private $params;


    /**
     * Cache constructor.
     * @param $logger
     */
    public  function __construct($logger)
    {
        $this->logger = $logger;
    }


    /**
     * Update config
     * @param array $config
     * @return array
     */
    public  function config($config = [])
    {
        if ( TRUE == array_key_exists('cache', $config) ) {
            if ( is_bool($config['cache']) ) {
                $this->params['cache'] = $config['cache'];
            } else {
                $this->logger->warning(sprintf('Invalid value for cache: %s', $config['cache']));
                $this->params['cache'] = false;
            }
        }

        if ( FALSE == $this->params['cache'] ) {
            return  $this->params;
        }

        if ( TRUE == array_key_exists('cache.force', $config)) {
            if ( is_bool($config['cache.force'])) {
                $this->params['cache.force'] = $config['cache.force'];
            } else {
                $this->params['cache.force'] = false;
            }
        }

        if ( TRUE == array_key_exists('cache.player', $config)) {
            if ( is_integer($config['cache.player'])) {
                if ( $config['cache.player'] >= static::CACHE_MIN_PLAYER) {
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

        if ( TRUE == array_key_exists('cache.guild', $config)) {
            if ( is_integer($config['cache.guild'])) {
                if ( $config['cache.guild'] >= static::CACHE_MIN_GUILD) {
                    $this->params['cache.guild'] = $config['cache.guild'];
                } else {
                    $this->logger->error(sprintf('cache.guild too small: %s, set to default: %s', $config['cache.guild'], static::CACHE_MIN_GUILD));
                    $this->params['cache.guild'] = static::CACHE_MIN_GUILD;
                }

            } else {
                $this->logger->error(sprintf('Invalid value for cache.guild: %s', $config['cache.guild']));
            }
        }

        if ( TRUE == array_key_exists('cache.data', $config)) {
            if ( is_integer($config['cache.data'])) {
                $this->params['cache.data'] = $config['cache.data'];
            } else {
                $this->logger->error(sprintf('Invalid value for cache.data: %s', $config['cache.data']));
            }
        }

        if ( TRUE == array_key_exists('cache.storage', $config)) {
            $this->params['cache.storage'] = $config['cache.storage'];
        }

        if ( TRUE == $this->params['cache'] ) {
            if ( TRUE == $this->params['cache'] && NULL != $this->params['cache.storage'] ) {
                if ( FALSE == $this->initStorage($this->params['cache.storage']) ) {
                    $this->logger->debug(sprintf('сache: disabled'));
                    $this->params['cache'] = false;
                }
            }
        }

        $this->logger->debug(sprintf('cache:config update'));
        return $this->params;
    }


    /**
     * Create storage directories
     * @param $dir
     * @return bool
     */
    private function initStorage($dir){
        if ( FALSE == file_exists($dir) ) {
            if ( FALSE == @mkdir($dir,0775,true) ) {
                $this->logger->error(sprintf('cache: could not create cache.storage directory: %s', $dir));
                return false;
            }
        }

        $subdirs = array ('players', 'guilds', 'data');
        foreach ( $subdirs as $subdir ) {
            $path = implode('/',[$dir, $subdir]);
            if ( FALSE == file_exists($path) ) {
                if ( FALSE == @mkdir($path, 0775, true) ) {
                    $this->logger->error(sprintf('cache: could not create cache.storage directory: %s',  $path));
                    return false;
                }
            }
        }
        $this->logger->debug(sprintf('cache:storage ok'));
        return true;
    }

    /*
     * TODO: merge cachePlayerPath/cacheGuildPath/cacheDataPath to one function
     */
    /**
     * Get player storage path
     * @param $player
     * @return string[]
     */
    private function cachePlayerPath($player){
        // $this->logger->debug(sprintf('config: %s', json_encode($this->params, JSON_PRETTY_PRINT)));
        $path = implode('/', [ $this->params['cache.storage'], 'players', $player]);
        return  array(
            'meta' => $path . '.meta',
            'data' => $path . '.json',
        );
    }


    /**
     * Get guild storage path
     * @param $guild
     * @return string[]
     */
    private function cacheGuildPath($guild){
        $path = implode('/', [ $this->params['cache.storage'], 'guilds', $guild]);
        return  array(
            'meta' => $path . '.meta',
            'data' => $path . '.json',
        );
    }


    /**
     * Get data/collection storage path
     * @param $collection
     * @return string[]
     */
    private function cacheDataPath($collection){
        $path = implode('/', [ $this->params['cache.storage'], 'data', $collection]);
        return  array(
            'meta' => $path . '.meta',
            'data' => $path . '.json',
        );
    }


    /**
     * Get player info from cache
     * @param $players
     * @return array|null
     */
    public  function players($players) {

        $players = $this->validateAllycodes($players);
        if ( 0 == count($players) ) {
            return null;
        }

        if ( FALSE == $this->params['cache'] ) {
            return null;
        }

        $result = array();
        foreach ($players as $player ) {
            $result[$player] = $this->playerCache($player);
        }

        return  $result;
    }


    /**
     * Get guilds info from cache
     * @param $players
     * @return array|null
     */
    public  function guilds($players) {

        $players = $this->validateAllycodes($players);
        if ( 0 == count($players) ) {
            $this->logger->debug(sprintf('cache:guilds has no valid allycodes'));
            return null;
        }

        if ( FALSE == $this->params['cache'] ) {
            return null;
        }

        $result = array();
        foreach ($players as $player ) {
            $result[$player] = $this->guildCache($player);
        }

        return  $result;
    }


    /**
     * Fetch player from cache
     * @param $player
     * @return mixed|null
     */
    private function playerCache($player) {

        $path = $this->cachePlayerPath($player);

        if ( FALSE == file_exists($path['meta']) ) {
            $this->logger->debug(sprintf('player/%s: cache absent', $player));
            return null;
        }
        $meta = json_decode(file_get_contents($path['meta']), JSON_OBJECT_AS_ARRAY);

        $status = false;
        if ( 404 ==  $meta['code'] ) {
            if ( $meta['updated_at'] > time() - $this->params['cache.player'] ) {
                $this->logger->debug(sprintf('player/%s: cache ok = %s', $player, $meta['code']));
                return $meta;
            }
        } else {
            if ( FALSE == file_exists($path['data'])) {
                $this->logger->debug(sprintf('player/%s: cache error, no data', $player));
                return null;
            }

            if ( $meta['updated_at'] > time() - $this->params['cache.player']) {
                $this->logger->debug(sprintf('player/%s: cache ok = %s', $player, $meta['code']));
                $status = true;
            } else {
                $this->logger->debug(sprintf('player/%s: cache expired', $player));
                if ( TRUE == $this->params['cache.force']) {
                    $this->logger->debug(sprintf('player/%s: cache forced', $player));
                    $status = true;
                }
            }

            if ( TRUE == $status) {
                return json_decode(file_get_contents($path['data']), JSON_OBJECT_AS_ARRAY);
            }
        }
        return null;
    }


    /** 
     * Fetch guild from cache
     * @param $guild
     * @return mixed|null
     */
    private function guildCache($guild) {
        $this->logger->debug(sprintf('guild/%s: cache check ', $guild));
        $path = $this->cacheGuildPath($guild);
        $player = $guild;
        if ( FALSE == file_exists($path['meta']) ) {
            $this->logger->debug(sprintf('guild/%s: cache absent', $guild));
            return null;
        }

        $meta = json_decode(file_get_contents($path['meta']), JSON_OBJECT_AS_ARRAY);
        if ( 'G' != strval($guild)[0] ) {
            /* convert ally code to guild_id */
            $guild = $meta['id'];
            $path = $this->cacheGuildPath($guild);
        }

        if ( 404 == $meta['code'] ) {
            $this->logger->debug(sprintf('guild/%s: cache ok - %s', $player, $meta['code']));
            return $meta;
        }

        if ( FALSE == file_exists($path['data']) ) {
            $this->logger->debug(sprintf('guild/%s: cache broken', $player));
            return null;
        }

        $status = false;
        if ( $meta['updated_at'] > time() - $this->params['cache.guild'] ) {
            $this->logger->debug(sprintf('guild/%s: cache ok - %s', $player, $meta['code']));
            $status = true;
        } else {
            $this->logger->debug(sprintf('guild/%s: cache expired', $player));
            if ( TRUE == $this->params['cache.force'] ) {
                $this->logger->debug(sprintf('guild/%s: cache forced', $player));
                $status = true;
            }
        }

        if ( TRUE == $status ) {
            return json_decode(file_get_contents($path['data']), JSON_OBJECT_AS_ARRAY);
        }
        return null;
    }


    /**
     * Save player cache
     * @param $data
     * @param int $code
     * @return bool
     */
    public  function playerSave($data, $code = 200 ){
        
        if ( FALSE == $this->params['cache'] ) {
            return false;
        }

        if ( FALSE == array_key_exists('allyCode', $data) ) {
            $this->logger->error(sprintf('player:?????????: cache error, data has no .allyCode'));
            return false;
        }
        $player = $data['allyCode'];

        if ( FALSE == array_key_exists('updated', $data) ) {
            $this->logger->error(sprintf('player:%s cache error, data has no .updated', $player));
            return false;
        }

        $path = $this->cachePlayerPath($player);
        $metadata = [
            'allyCode'   => $data['allyCode'],
            'name'       => $data['name'],
            'guild_id'   => (NULL == strlen($data['guildRefId']) ? null : $data['guildRefId']),
            'updated_at' => intval($data['updated'] / 1000),
            'request_at' => time(),
            'code'       => $code
        ];


        if ( FALSE == @file_put_contents($path['meta'], json_encode($metadata), LOCK_EX) ) {
            $this->logger->debug(sprintf('player:%s meta save error', $player));
            return false;
        }

        if ( 200 == $code ) {
            if ( FALSE == @file_put_contents($path['data'], json_encode($data), LOCK_EX) ) {
                $this->logger->debug(sprintf('player:%s data save error', $player));
                return false;
            }
        }

        $this->logger->debug(sprintf('player:%s saved ', $player));
        return true;
    }


    /**
     * Save guild in cache
     * @param $data
     * @param int $code
     * @return bool
     */
    public  function guildSave($data, $code = 200){

        if ( FALSE == $this->params['cache'] ) {
            return false;
        }

        if ( 400 == $code ) {
            $guild = $data['allyCode'];
        } else {
            $guild = $data['id'];
        }

        $path = $this->cacheGuildPath($guild);
        $metadata = [
            'id'    => $data['id'],
            'name'  => $data['name'],
            'code'  => $code,
        ];

        if ( 200 == $code ) {
            if ( FALSE == array_key_exists('updated', $data)) {
                $this->logger->error(sprintf('guild/%s invalid data, has no .updated', $data['id']));
                return false;
            }
            $metadata['updated_at'] = intval($data['updated'] / 1000);
            $metadata['request_at'] = time();

            /* save guild */
            if ( TRUE == file_exists($path['meta']) ) {
                $meta = json_decode(file_get_contents($path['meta']), JSON_OBJECT_AS_ARRAY);
                if ( $meta['updated_at'] == $metadata['updated_at']) {
                    return false;
                }
            }

            if ( FALSE == file_put_contents($path['meta'], json_encode($metadata), LOCK_EX) ) {
                $this->logger->debug(sprintf('guild:%s meta save error', $guild));
                return false;
            }

            if ( FALSE == file_put_contents($path['data'], json_encode($data), LOCK_EX) ) {
                $this->logger->debug(sprintf('guild:%s data save error', $guild));
                return false;
            }
            $this->logger->debug(sprintf('guild/%s: saved', $guild));
        }

        /* save roster */
        foreach ( $data['roster'] as $player ) {
            $path = $this->cacheGuildPath($player['allyCode']);
            if ( FALSE == file_put_contents($path['meta'], json_encode($metadata), LOCK_EX) ) {
                $this->logger->debug(sprintf('guild:%s meta save error', $guild));
            }
            $this->logger->debug(sprintf('guild/%s: saved', $player['allyCode']));
        }

        return true;
    }


    /**
     * Get data from cache
     * @param $collections
     * @return array
     */
    public function data($collections ) {
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


    /**
     * Validate allycodes
     * @param array $list
     * @return array
     */
    private function validateAllycodes($list = [])
    {
        $result = array();
        if ( FALSE == is_array($list) ) {
            $list = [ $list ];
        }

        foreach ( $list as $ally ) {
            if ( ctype_digit($ally) && ($ally/100000000) >= 1 && ($ally/100000000) < 10) {
                array_push($result, $ally);
            } else {
                $this->logger->warning(sprintf('Invalid allycode: %s', $ally));
            }
        }

        $result = array_unique($result);
        return $result;
    }

}
