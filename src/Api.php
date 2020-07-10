<?php

namespace ApiSwgohHelp;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

Class Api {

    /**
     * Api config
     * @var
     */
    private $params = [
        /* API */
        'api.username'          => null,
        'api.password'          => null,
        'api.credentials'       => __DIR__ . '/api-swgoh-help.json',
        'api.lang'              => 'eng_us',
        'api.version.lang'      => null,
        'api.version.version'   => null,
        /* logging */
        'log'           => false,
        'log.verbose'   => false,
        'log.level'     => false,
        'log.file'      => false,
        /* http options */
        'http.timeout'  => 300,
        'http.debug'    => false,
        'http.errors'   => false,
        'http.stream'   => true,
        /* cache options */
        'cache'         => false,
        'cache.storage' => null,
        'cache.player'  => 3600,
        'cache.guild'   => 3600*4,
        'cache.data'    => 3600*24,
    ];


    /**
     * Logger name
     * @var string
     */
    const LOGGER = 'api.swgoh.help';


    /**
     * Guzzle Http Client
     * @var
     */
    protected $httpClient;


    /**
     * The Cache storage
     * @var
     */
    protected $cache;


    /**
     * Monolog logger
     * @var Logger
     */
    protected $logger = null;


    /**
     * API constants
     */
    const API_URL       = 'https://api.swgoh.help';
    const API_AUTH      = '/auth/signin';
    const API_PLAYERS   = '/swgoh/players';
    const API_GUILDS    = '/swgoh/guilds';
    const API_DATA      = '/swgoh/data';
    const API_VERSION   = '/version';
    const API_LANGS     = [
        'chs_cn',
        'cht_cn',
        'eng_us',
        'fre_fr',
        'ger_de',
        'ind_id',
        'ita_it',
        'jpn_jp',
        'kor_kr',
        'por_br',
        'rus_ru',
        'spa_xm',
        'tha_th',
        'tur_tr'
    ];
    const API_DATA_COLLECTIONS = array(
        'abilityList',
        'battleEnvironmentsList',
        'battleTargetingRuleList',
        'categoryList',
        'challengeList',
        'challengeStyleList',
        'effectList',
        'environmentCollectionList',
        'equipmentList',
        'eventSamplingList',
        'guildExchangeItemList',
        'guildRaidList',
        'helpEntryList',
        'materialList',
        'playerTitleList',
        'powerUpBundleList',
        'raidConfigList',
        'recipeList',
        'requirementList',
        'skillList',
        'starterGuildList',
        'statModList',
        'statModSetList',
        'statProgressionList',
        'tableList',
        'targetingSetList',
        'territoryBattleDefinitionList',
        'territoryWarDefinitionList',
        'unitsList',
        'unlockAnnouncementDefinitionList',
        'warDefinitionList',
        'xpTableList'
    );

    /**
     * API Roken
     * @var string
     */
    private $token = null;


    /**
     * Api constructor.
     * @param array $config
     */
    public  function __construct(array $config)
    {
        $this->init($config);
    }


    /**
     * @param $config
     */
    private function init($config)
    {
        $this->config($config);
        $this->initLogger($config);
        $this->initCache($config);
    }


    /**
     * Update current configuration
     * @param $config
     */
    public  function config($config)
    {
        /* Username / Password */
        if ( TRUE == array_key_exists('api.username', $config) ) {
            $this->params['api.username'] = $config['api.username'];
        }

        if ( TRUE == array_key_exists('api.password', $config) ) {
            $this->params['api.password'] = $config['api.password'];
        }

        if ( TRUE == array_key_exists('api.credentials', $config) ) {
            $this->params['api.credentials'] = $config['api.credentials'];
        }

        /* Update http config */
        if ( TRUE == array_key_exists('http.timeout', $config) ) {
            if ( is_integer($config['http.timeout']) ) {
                $this->params['http.timeout'] = $config['http.timeout'];
            } else {
                $this->logger->error(sprintf('Invalid value for http.timeout: %s', $config['http.timeout']));
            }
        }

        if ( TRUE == array_key_exists('http.debug', $config) ) {
            if (is_bool($config['http.debug']) ) {
                $this->params['http.debug'] = $config['http.debug'];
            } else {
                $this->logger->error(sprintf('Invalid value for http.debug: %s', $config['http.debug']));
            }
        }

        if ( TRUE == array_key_exists('http.stream', $config) ) {
            if (is_bool($config['http.stream']) ) {
                $this->params['http.stream'] = $config['http.stream'];
            } else {
                $this->logger->error(sprintf('Invalid value for http.stream: %s', $config['http.stream']));
            }
        }

        if ( TRUE == array_key_exists('http.errors', $config) ) {
            if (is_bool($config['http.errors']) ) {
                $this->params['http.errors'] = $config['http.errors'];
            } else {
                $this->logger->error(sprintf('Invalid value for http.errors: %s', $config['http.errors']));
            }
        }

        if ( TRUE == array_key_exists('api.lang', $config) ) {
            if ( TRUE == in_array($config['api.lang'], static::API_LANGS) ) {
                $this->params['api.lang'] = $config['api.lang'];
            } else {
                $this->logger->error(sprintf('Invalid value for api.lang: %s', $config['api.lang']));
            }
        }

        /* update cache config */
        if ( FALSE == is_null($this->cache) ) {

            $this->cache()->config($config);
        }
    }


    /**
     * @param $config
     */
    private function initLogger($config)
    {
        /* Init null logger */
        $this->logger = new Logger(self::LOGGER);
        $this->logger->pushHandler(new NullHandler());

        if ( FALSE == array_key_exists('log', $config) || TRUE != $config['log'] ) {
            return;
        }
        $this->params['log'] = true;

        if ( FALSE == array_key_exists('log.verbose', $config) ) {
            $config['log.verbose'] = false;
        }

        if ( FALSE == array_key_exists('log.file', $config) ) {
            $config['log.file'] = false;
        }

        if ( FALSE == $config['log.verbose'] && FALSE == $config['log.file']) {
            return;
        }

        if ( FALSE == array_key_exists('log.level', $config) ) {
            $config['log.level'] = 'info';
        } elseif ( FALSE == in_array( strtolower($config['log.level']), ['info','warning','error','debug']) ) {
            $config['log.level'] = 'info';
        }

        $this->params['log.verbose'] = $config['log.verbose'];
        $this->params['log.file']    = $config['log.file'];
        $this->params['log.level']   = $config['log.level'];

        $formatter = new LineFormatter(null, "Y-m-dTH:i:s", false, true);
        if ( TRUE == array_key_exists('log.format', $config)  ) {
            if ( strtolower('json') == $config['log.format']) {
                $formatter = new JsonFormatter();
            }
        }

        if ( TRUE == $config['log.verbose'] ) {
            $handler = new StreamHandler('php://stdout', $config['log.level']);
            $handler->setFormatter($formatter);
            $this->logger->pushHandler($handler);
        }

        if ( TRUE == $config['log.file'] ) {
            $handler = new RotatingFileHandler($config['log.file'], 31, $config['log.level']);
            $handler->setFormatter($formatter);
            $this->logger->pushHandler($handler);
        }

        $this->params['log'] = true;
        $this->params['log.level'] = true;
        $this->params['log'] = true;
        $this->params['log'] = true;

    }


    /**
     * @return mixed
     */
    private function initCache($config)
    {
        if ( TRUE == is_null($this->cache) ) {
            $this->cache = new \ApiSwgohHelp\Cache($config, $this->logger);
        }
        $this->cache()->config($config);
        return $this->cache;
    }


    /**
     * @return Client
     */
    private function http()
    {
        if ( is_null($this->httpClient) ) {
            $this->httpClient = new Client([
                'base_uri'      => self::API_URL,
                'http_errors'   => $this->params['http.errors']
            ]);
        }
        return $this->httpClient;
    }


    /**
     * @return mixed
     */
    public  function cache()
    {
        if ( TRUE == is_null($this->cache) ) {
            $this->cache = new \ApiSwgohHelp\Cache($this->params, $this->logger);
        }
        return $this->cache;
    }


    /**
     * @param array $allycodes
     * @param array $payload
     * @return array|null
     */
    public  function players($allycodes = [], $payload = [] )
    {
        $players = $this->validateAllycodes($allycodes);
        if ( 0 == count($players) ) {
            $this->logger->error(sprintf('No valid allycodes found in: %s', $allycodes));
            return null;
        }

        $result = array();
        foreach ($players as $player ) {
            $result[$player] = [ 'success' => false, 'data' => null ];
        }

        // TODO: add cache checks
        $payload = $this->validatePayload(static::API_PLAYERS, $payload);
        $payload->allycodes = $allycodes;
        $res = $this->requestAPI(static::API_PLAYERS, $payload);

        if ( TRUE == in_array($res['code'], [200, 404] ) ) {
            foreach ($players as $player) {
                $result[$player] = ['success' => true, 'data' => null];
            }

            if (200 == $res['code']) {
                foreach ($res['data'] as $r) {
                    $player = $r['allyCode'];
                    $result[$player] = ['success' => true, 'data' => $r];
                }
            }
        }
        return $result;
    }


    /**
     * @param array $allycodes
     * @param array $payload
     * @return array|null
     */
    public  function guilds($allycodes = [], $payload = [] )
    {
        $players = $this->validateAllycodes($allycodes);
        if ( 0 == count($players) ) {
            $this->logger->error(sprintf('No valid allycodes found in: %s', $allycodes));
            return null;
        }

        $result = array();
        foreach ($players as $player ) {
            $result[$player] = [ 'success' => false, 'data' => null ];
        }

        $payload = $this->validatePayload(static::API_GUILDS, $payload);
        $chunks = array_chunk($players, 2);
        foreach ( $chunks as $chunk ) {
            $payload->allycodes = $chunk;
            $res = $this->requestAPI(static::API_GUILDS, $payload);

            if ( TRUE == in_array($res['code'], [200, 404] ) ) {

                foreach ($chunk as $player) {
                    $result[$player] = ['success' => true, 'data' => null];
                }

                if ( 200 == $res['code']) {
                    foreach ($res['data'] as $guild) {

                        foreach($guild['roster'] as $player) {
                            $player = $player['allyCode'];
                            if ( TRUE == in_array($player, $chunk) ) {
                                $result[$player] = ['success' => true, 'data' => $guild];
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }


    /**
     * Get swgoh data
     * @param array $list
     * @param array $payload
     * @return array|null
     */
    public  function data($list = [], $payload = [] )
    {
        $collections = $this->validateCollections($list);
        if ( 0 == count($collections) ) {
            return null;
        }

        $result = array();
        foreach ($collections as $collection ) {
            $result[$collection] = [ 'success' => false, 'data' => null ];

            $payload = $this->validatePayload(static::API_DATA, $payload, $collection);
            $payload['collection'] = $collection;

            $res = $this->requestAPI(static::API_DATA, $payload);

            if ( TRUE == in_array($res['code'], [200, 404] ) ) {
                $result[$collection] = ['success' => true, 'data' => null];

                if ( 200 == $res['code']) {
                    $result[$collection] = ['success' => true, 'data' => $res['data']];
                }
            }
        }

        return $result;
    }


    /**
     * @param $endpoint
     * @param $payload
     * @return array|mixed|null
     * @throws GuzzleException
     */
    private function requestAPI($endpoint, $payload )
    {

        if ( FALSE == $this->checkAPI() ) {
            $this->logger->error(sprintf('Api check failed'));
            return null;
        }

        try {
            $this->logger->debug(sprintf('Send request to: %s, payload: %s', $endpoint, json_encode($payload)));
            $res = $this->http()->request('POST', $endpoint, [
                'headers' => [
                    'Content-type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $this->token,
                ],
                'body'          => json_encode($payload),
                'debug'         => $this->params['http.debug'],
                'stream'        => $this->params['http.stream'],
                'http_errors'   => $this->params['http.errors'],
                'timeout'       => $this->params['http.timeout'],
            ]);


            $code = $res->getStatusCode();
            $body = null;
            while (FALSE == $res->getBody()->eof()) {
                $body .= $res->getBody()->read(4096);
            }
            $res = null;
            unset($res);

            file_put_contents('http.json', $body);
            if ( 200 != $code)  {
                $this->logger->debug(sprintf('Api request error [code %s]', $code));
                $this->logger->debug(sprintf('==========================='));
                $this->logger->debug(sprintf('%s', $body));
                $this->logger->debug(sprintf('==========================='));
            }

            return [
                'code' => $code,
                'data'   => json_decode($body, JSON_UNESCAPED_UNICODE|JSON_OBJECT_AS_ARRAY)
            ];

        } catch (ClientException | ServerException $e) {
            $response = $e->getResponse();
            $body = json_decode($response->getBody(), true);

            if ( $response->getStatusCode() == 401 || $body['code'] == 401 || $response->getStatusCode() == 503) {
                $this->token = null;
                $args = func_get_args();
                return call_user_func_array([$this, __METHOD__], $args);
            }

            throw $e;
        }
    }


    /**
     * @return bool|mixed
     * @throws GuzzleException
     */
    private function loginAPI()
    {

        if ( NULL == $this->params['api.username'] ) {
            $this->logger->critical(sprintf('api.username required'));
            return false;
        }

        if ( NULL == $this->params['api.password'] ) {
            $this->logger->critical(sprintf('api.password required'));
            return false;
        }

        if ( NULL == $this->params['api.credentials'] ) {
            $this->logger->critical(sprintf('api.credentials required'));
            return false;
        }

        try {
            $res = $this->http()->request('POST', static::API_AUTH, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'username' => $this->params['api.username'],
                    'password' => $this->params['api.password'],
                    'grant_type' => 'password',
                    'client_id' => 'abc',
                    'client_secret' => '123',
                ],
                'debug' => $this->params['http.debug'],
                'http_errors' => $this->params['http.errors'],
                'timeout' => $this->params['http.timeout'],
            ]);

            $body = $res->getBody();
            $code = $res->getStatusCode();
            $res = null;
            unset($res);

            if (200 != $code) {
                $this->logger->critical(sprintf('Api auth error [code %s]', $code));
                $this->logger->critical(sprintf('==========================='));
                $this->logger->critical(sprintf('%s', $body));
                $this->logger->critical(sprintf('==========================='));
                return false;
            }

            if (0 == strlen($body)) {
                $this->logger->critical(sprintf('Empty response', $body));
                return false;
            }

            $json = json_decode($body);
            if (NULL == $json) {
                $this->logger->critical(sprintf('Invalid response', $body));
                return false;
            }

            if (FALSE == isset($json->access_token)) {
                return false;
            }

            if (FALSE == isset($json->expires_in)) {
                return false;
            }

            if (FALSE == isset($json->expires_at)) {
                $json->expires_at = time() + $json->expires_in - 60;
            }

            $this->token = $json->access_token;
            $this->logger->debug(sprintf('API logged in [%s]', $this->token));

            file_put_contents($this->params['api.credentials'], json_encode($json, JSON_PRETTY_PRINT));
            return true;
        } catch (ClientException | ServerException $e) {
            $response = $e->getResponse();
            $body = json_decode($response->getBody(), true);

            if ( $response->getStatusCode() == 401 || $body['code'] == 401 || $response->getStatusCode() == 503) {
                $this->token = null;
                $args = func_get_args();
                return call_user_func_array([$this, __METHOD__], $args);
            }

            throw $e;
        }
    }


    /**
     * @return bool|mixed
     */
    private function checkAPI()
    {

        if ( FALSE == $this->aliveAPI() ) {
            return false;
        }

        if ( NULL == $this->token ) {
            return $this->loginAPI();
        }

        if ( FALSE == file_exists($this->params['api.credentials']) ) {
            return $this->loginAPI();
        }

        $json = json_decode(file_get_contents($this->params['api.credentials']), JSON_OBJECT_AS_ARRAY);
        if ( NULL == $json ) {
            return $this->loginAPI();
        }

        if ( FALSE == array_key_exists('expires_at', $json) ) {
            return $this->loginAPI();
        }

        if ( FALSE == array_key_exists('access_token', $json) ) {
            return $this->loginAPI();
        }

        if ( time() >= $json['expires_at'] - 60 ) {
            return $this->loginAPI();
        }

        $this->token = $json['access_token'];
        return true;
    }


    /**
     * Check if API is alive
     * @return bool
     */
    private function aliveAPI()
    {

        try {
            $res = $this->http()->request('GET', static::API_VERSION, [
                'timeout'   => 10,
                'stream'    => false,
                'debug'     => $this->params['http.debug'],
            ]);

            $code = $res->getStatusCode();
            $body = null;
            while ( FALSE == $res->getBody()->eof()) {
                $body .= $res->getBody()->read(4096);
            }


            if ( 200 == $code ) {
                $this->params['api.version.lang'] = json_decode($body)->language;
                $this->params['api.version.game'] = json_decode($body)->game;
                // $this->logger->debug(sprintf("api.version.lang: %s", $this->params['api.version.lang']));
                // $this->logger->debug(sprintf("api.version.game: %s", $this->params['api.version.game']));
                return true;
            }


        }

        catch ( GuzzleException $e) {
            $this->logger->error(sprintf("Api request error: %s", $e->getMessage()));
            $this->logger->debug("API is down");
            return false;
        }

        return false;
    }


    /**
     * Validate allycodes
     * @param $allycodes
     * @return array
     */
    private function validateAllycodes($list = [])
    {
        $result = array();
        if ( FALSE == is_array($list) ) { $list = [ $list ]; }

        foreach ( $list as $ally ) {
            if (ctype_digit($ally) && ($ally/100000000) >= 1 && ($ally/100000000) < 10) {
                array_push($result, $ally);
            } else {
                $this->logger->warning(sprintf('Invalid allycode: %s', $ally));
            }
        }

        $result = array_unique($result);
        return $result;
    }


    /**
     * Validate collections
     * @param array $list
     * @return array
     */
    private function validateCollections($list = [])
    {
        $result = array();
        if ( FALSE == is_array($list) ) { $list = [ $list ]; }

        foreach ( $list as $collection ) {
            if ( TRUE == in_array($collection, static::API_DATA_COLLECTIONS) ) {
                array_push($result, $collection);
            } else {
                $this->logger->warning(sprintf('Invalid collection: %s', $collection));
            }
        }

        $result = array_unique($result);
        return $result;
    }


    /**
     * Validate request payload
     * @param $endpoint
     * @param $payload
     * @param null $collection
     * @return object
     */
    private function validatePayload($endpoint, $payload, $collection = null)
    {
        $result = [];

        /* language */
        $result['language'] = $this->params['lang'];

        /* enums */
        $result['enums'] = false;


        if ( TRUE == array_key_exists('enums', $payload) && TRUE == is_bool($payload['enums']) ) {
            $result['enums'] = $payload['enums'];
        }

        if ( static::API_GUILDS == $endpoint ) {
            $result['roster'] = false;
            $result['units'] = false;
            $result['mods'] = false;
        }

        return $result;
    }

}