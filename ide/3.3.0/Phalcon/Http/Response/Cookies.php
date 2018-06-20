<?php 

namespace Phalcon\Http\Response {

	/**
	 * Phalcon\Http\Response\Cookies
	 *
	 * This class is a bag to manage the cookies.
	 *
	 * A cookies bag is automatically registered as part of the 'response' service in the DI.
	 * By default, cookies are automatically encrypted before being sent to the client and are
	 * decrypted when retrieved from the user. To set sign key used to generate a message
	 * authentication code use `Phalcon\Http\Response\Cookies::setSignKey`.
	 *
	 * <code>
	 * use Phalcon\Di;
	 * use Phalcon\Crypt;
	 * use Phalcon\Http\Response\Cookies;
	 *
	 * $di = new Di();
	 *
	 * $di->set(
	 *     'crypt',
	 *     function () {
	 *         $crypt = new Crypt();
	 *
	 *         // The `$key' should have been previously generated in a cryptographically safe way.
	 *         $key = "T4\xb1\x8d\xa9\x98\x05\\\x8c\xbe\x1d\x07&[\x99\x18\xa4~Lc1\xbeW\xb3";
	 *         $crypt->setKey($key);
	 *
	 *         return $crypt;
	 *     }
	 * );
	 *
	 *
	 * $di->set(
	 *     'cookies',
	 *     function () {
	 *         $cookies = new Cookies();
	 *
	 *         // The `$key' MUST be at least 32 characters long and generated using a
	 *         // cryptographically secure pseudo random generator.
	 *         $key = "#1dj8$=dp?.ak//j1V$~%*0XaK\xb1\x8d\xa9\x98\x054t7w!z%C*F-Jk\x98\x05\\\x5c";
	 *         $cookies->setSignKey($key);
	 *
	 *         return $cookies;
	 *     }
	 * );
	 * </code>
	 */
	
	class Cookies implements \Phalcon\Http\Response\CookiesInterface, \Phalcon\Di\InjectionAwareInterface {

		protected $_dependencyInjector;

		protected $_registered;

		protected $_useEncryption;

		protected $_cookies;

		/**
		 * \Phalcon\Http\Response\Cookies constructor
		 */
		public function __construct(){ }


		/**
		 * Sets the dependency injector
		 */
		public function setDI(\Phalcon\DiInterface $dependencyInjector){ }


		/**
		 * Returns the internal dependency injector
		 */
		public function getDI(){ }


		/**
		 * Set if cookies in the bag must be automatically encrypted/decrypted
		 */
		public function useEncryption($useEncryption){ }


		/**
		 * Returns if the bag is automatically encrypting/decrypting cookies
		 */
		public function isUsingEncryption(){ }


		/**
		 * Sets a cookie to be sent at the end of the request.
		 *
		 * This method overrides any cookie set before with the same name.
		 *
		 * <code>
		 * use \Phalcon\Http\Response\Cookies;
		 *
		 * $now = new DateTimeImmutable();
		 * $tomorrow = $now->modify('tomorrow');
		 *
		 * $cookies = new Cookies();
		 * $cookies->set(
		 *     'remember-me',
		 *     json_encode(['user_id' => 1]),
		 *     (int) $tomorrow->format('U'),
		 * );
		 * </code>
		 */
		public function set($name, $value=null, $expire=null, $path=null, $secure=null, $domain=null, $httpOnly=null){ }


		/**
		 * Gets a cookie from the bag
		 */
		public function get($name){ }


		/**
		 * Check if a cookie is defined in the bag or exists in the _COOKIE superglobal
		 */
		public function has($name){ }


		/**
		 * Deletes a cookie by its name
		 * This method does not removes cookies from the _COOKIE superglobal
		 */
		public function delete($name){ }


		/**
		 * Sends the cookies to the client
		 * Cookies aren't sent if headers are sent in the current request
		 */
		public function send(){ }


		/**
		 * Reset set cookies
		 */
		public function reset(){ }

	}
}
