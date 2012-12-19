Phalcon redis session adapter
========

Put this file into directory library\Phalcon\Session\Adapter\ in your phalcon-project. 

Rediska configuration
--------

You should use 
(http://rediska.geometria-lab.net "Rediska") class to work with redis. It should be configured before session configuration:

```php
$options = array(
	'name' => 'default',
	'namespace' => 'ns_',
	'servers'   => array(
		array('host' => '127.0.0.1', 'port' => 6379)
	)
);
Rediska_Manager::add($options);
```

Phalcon session configuration
--------

```php
$this->_di->set(
            'session',
            function() use ($config)
            {
				$session = new Phalcon\Session\Adapter\Redis(array(
					'cookie_domain' => 'your.cookie.domain',
				));
				
                if (!$session->isStarted()) {
                    $session->start();
                }
                return $session;
            }
        );
```

Where 'your.cookie.domain' is domain name of your current enviroment without any symbols. 
For example: 'www.server.local'.

Class configuration
--------

If you wish, you can change some properties of class:

* $key_prefix - internal prefix for session keys in redis
* COOKIE_NAME - cookie name, which will be set to user
* SESSION_TTL - session time to live (cookies and redis keys)
