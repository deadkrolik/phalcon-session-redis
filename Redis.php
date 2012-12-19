<?php
namespace Phalcon\Session\Adapter;

use \Phalcon\Session\Adapter,
	\Phalcon\Session\AdapterInterface,
	\Phalcon\Session\Exception;

/**
 * Redis session adapter
 */
class Redis extends Adapter implements AdapterInterface
{
	/**
	 * @var bool Is session started
	 */
	protected $is_started = false;

	/**
	 * @var Rediska_Key_Hash Redis connection object
	 */
	protected $redis_hash_storage = null;
	
	/**
	 * @var array Session data internal storage
	 */
	protected $payload = array();
	
	/**
	 * @var string Key prefix for sessions, keys will be like that: "session:bd30942e07cc1589586b729ec8d9c6d6"
	 */
	protected $key_prefix = 'session:';
	
	/**
	 * @var string Domain name for cookie set
	 */
	protected $cookie_domain = '';

	/**
	 * User cookie name
	 */
	const COOKIE_NAME = 'app_token';
	
	/**
	 * Session time to live in seconds
	 */
	const SESSION_TTL = 86400;//1 day = 60*60*24 = 86400
	
	/**
	 * Phalcon\Session\Adapter\Redis construtor
	 */
	public function __construct($options=null)
	{
		if (isset($options['cookie_domain'])) {
			$this->cookie_domain = $options['cookie_domain'];
		}
		
		parent::__construct($options);
	}
	
	/**
	 * Starts session
	 */
	public function start()
	{
		$this->is_started = true;

		$hash = $this->getUserCurrentHashObject();
		if ($hash === false) {
			$hash = $this->createToken();
		}
		$this->redis_hash_storage = $hash;
		
		//fill internal payload array
		foreach($this->redis_hash_storage->getFieldsAndValues() as $key => $value) {
			
			if ($key == '_spec') {
				continue;
			}
			
			$real_key = str_replace('payload_','',$key);
			$encoded  = json_decode($value, true);//always arrays :'(

			$this->payload[$real_key] = is_array($encoded) ? $encoded : $value;
		}
	}
	
	/**
	 * Gets a session variable from an application context
	 */
	public function get($index)
	{
		if (!$this->isStarted()) {
			$this->start();
		}
		
		return isset($this->payload[$index]) ? $this->payload[$index] : null;
	}
	
	/**
	 * Sets a session variable in an application context
	 */
	public function set($index, $value)
	{
		if (!$this->isStarted()) {
			$this->start();
		}
		
		$this->payload[$index] = $value;
		$this->updateUserPayload();
	}
	
	/**
	 * Check whether a session variable is set in an application context
	 */
	public function has($index)
	{
		return isset($this->payload[$index]);
	}
	
	/**
	 * Removes a session variable from an application context
	 */
	public function remove($index)
	{
		unset($this->payload[$index]);
		$this->updateUserPayload();
	}
	
	/**
	 * Returns active session id
	 */
	public function getId()
	{
		return $this->redis_hash_storage ? str_replace($this->key_prefix,'',$this->redis_hash_storage->getName()) : 0;
	}
	
	/**
	 * Check whether the session has been started
	 */
	public function isStarted()
	{
		return $this->is_started;
	}
	
	/**
	 * Destroys the active session
	 */
	public function destroy()
	{
		$this->deleteUserSession();
	}
	
	/**
	 * Gets cookie from user request
	 */
	protected function getUserCookie()
	{
		$cookie = isset($_COOKIE[Redis::COOKIE_NAME]) ? $_COOKIE[Redis::COOKIE_NAME] : '';
		//replace bad symbols
		return preg_replace('|[^a-z0-9]+|','',$cookie);
	}
	
	/**
	 * Write user cookie
	 */
	protected function setUserCookie($token_string)
	{
		setcookie(Redis::COOKIE_NAME, $token_string, time() + Redis::SESSION_TTL, '/', $this->cookie_domain);
	}

	/**
	 * Генерация нового токена с содержимым массива сессий
	 */
	protected function createToken()
	{
		static $installed_token = null;
		
		//to prevent double cookie set
		if ($installed_token !== null) {
			return $installed_token;
		}
		
		//try to make it really rand
		$token_string = md5(rand().time().microtime().time().Redis::SESSION_TTL.$this->key_prefix.serialize($this->payload));
		
		$hash = new \Rediska_Key_Hash($this->key_prefix.$token_string);
		$hash->set('_spec',0);
		$hash->expire(Redis::SESSION_TTL);

		$this->setUserCookie($token_string);
		
		$installed_token = $hash;
		return $hash;
	}
	
	/**
	 * Get ready for use redis-hash object
	 */
	protected function getUserCurrentHashObject() {
		
		//а есть ли кука вообще
		$user_token_string = $this->getUserCookie();

		if (!$user_token_string) {
			return false;
		}
		
		$hash = new \Rediska_Key_Hash($this->key_prefix.$user_token_string);
		
		//if standard field exists, then return hash object
		return $hash->exists('_spec') ? $hash : false;
	}
	
	/**
	 * Обновление сессии в базе
	 */
	protected function updateUserPayload()
	{
		if (!$this->redis_hash_storage) {
			return false;
		}
		
		foreach($this->payload as $key => $value) {
			
			$field = 'payload_'.$key;
			
			//complex datatypes store as json-string
			if (is_array($value) || is_object($value)) {
				$value = json_encode($value);
			}
			
			$this->redis_hash_storage->set($field, $value);
		}
		
		//update ttl
		$this->redis_hash_storage->expire(Redis::SESSION_TTL);
	}
	
	/**
	 * Delete current session from redis
	 */
	protected function deleteUserSession()
	{
		if (!$this->redis_hash_storage) {
			return false;
		}
		
		$this->redis_hash_storage->delete();
		$this->redis_hash_storage = null;
		$this->is_started = false;
	}
}
