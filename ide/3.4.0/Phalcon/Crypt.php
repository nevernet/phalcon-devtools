<?php 

namespace Phalcon {

	/**
	 * Phalcon\Crypt
	 *
	 * Provides encryption facilities to Phalcon applications.
	 *
	 * <code>
	 * use Phalcon\Crypt;
	 *
	 * $crypt = new Crypt();
	 *
	 * $crypt->setCipher('aes-256-ctr');
	 *
	 * $key  = "T4\xb1\x8d\xa9\x98\x05\\\x8c\xbe\x1d\x07&[\x99\x18\xa4~Lc1\xbeW\xb3";
	 * $text = "The message to be encrypted";
	 *
	 * $encrypted = $crypt->encrypt($text, $key);
	 *
	 * echo $crypt->decrypt($encrypted, $key);
	 * </code>
	 */
	
	class Crypt implements \Phalcon\CryptInterface {

		const PADDING_DEFAULT = 0;

		const PADDING_ANSI_X_923 = 1;

		const PADDING_PKCS7 = 2;

		const PADDING_ISO_10126 = 3;

		const PADDING_ISO_IEC_7816_4 = 4;

		const PADDING_ZERO = 5;

		const PADDING_SPACE = 6;

		protected $_key;

		protected $_padding;

		protected $_cipher;

		protected $availableCiphers;

		protected $ivLength;

		protected $hashAlgo;

		protected $useSigning;

		/**
		 * \Phalcon\Crypt constructor.
		 */
		public function __construct($cipher=null, $useSigning=null){ }


		/**
		 * Changes the padding scheme used.
		 */
		public function setPadding($scheme){ }


		/**
		 * Sets the cipher algorithm for data encryption and decryption.
		 *
		 * The `aes-256-gcm' is the preferable cipher, but it is not usable
		 * until the openssl library is upgraded, which is available in PHP 7.1.
		 *
		 * The `aes-256-ctr' is arguably the best choice for cipher
		 * algorithm for current openssl library version.
		 */
		public function setCipher($cipher){ }


		/**
		 * Returns the current cipher
		 */
		public function getCipher(){ }


		/**
		 * Sets the encryption key.
		 *
		 * The `$key' should have been previously generated in a cryptographically safe way.
		 *
		 * Bad key:
		 * "le password"
		 *
		 * Better (but still unsafe):
		 * "#1dj8$=dp?.ak//j1V$~%*0X"
		 *
		 * Good key:
		 * "T4\xb1\x8d\xa9\x98\x05\\\x8c\xbe\x1d\x07&[\x99\x18\xa4~Lc1\xbeW\xb3"
		 *
		 * @see \Phalcon\Security\Random
		 */
		public function setKey($key){ }


		/**
		 * Returns the encryption key
		 */
		public function getKey(){ }


		/**
		 * Set the name of hashing algorithm.
		 *
		 * @throws \Phalcon\Crypt\Exception
		 */
		public function setHashAlgo($hashAlgo){ }


		/**
		 * Get the name of hashing algorithm.
		 */
		public function getHashAlgo(){ }


		/**
		 * Sets if the calculating message digest must used.
		 *
		 * NOTE: This feature will be enabled by default in \Phalcon 4.0.0
		 */
		public function useSigning($useSigning){ }


		/**
		 * Pads texts before encryption.
		 *
		 * @link http://www.di-mgt.com.au/cryptopad.html
		 */
		protected function _cryptPadText($text, $mode, $blockSize, $paddingType){ }


		/**
		 * Removes a padding from a text.
		 *
		 * If the function detects that the text was not padded, it will return it unmodified.
		 *
		 * @param string text Message to be unpadded
		 * @param string mode Encryption mode; unpadding is applied only in CBC or ECB mode
		 * @param int blockSize Cipher block size
		 * @param int paddingType Padding scheme
		 */
		protected function _cryptUnpadText($text, $mode, $blockSize, $paddingType){ }


		/**
		 * Encrypts a text.
		 *
		 * <code>
		 * $encrypted = $crypt->encrypt(
		 *     "Top secret",
		 *     "T4\xb1\x8d\xa9\x98\x05\\\x8c\xbe\x1d\x07&[\x99\x18\xa4~Lc1\xbeW\xb3"
		 * );
		 * </code>
		 */
		public function encrypt($text, $key=null){ }


		/**
		 * Decrypts an encrypted text.
		 *
		 * <code>
		 * $encrypted = $crypt->decrypt(
		 *     $encrypted,
		 *     "T4\xb1\x8d\xa9\x98\x05\\\x8c\xbe\x1d\x07&[\x99\x18\xa4~Lc1\xbeW\xb3"
		 * );
		 * </code>
		 *
		 * @throws \Phalcon\Crypt\Mismatch
		 */
		public function decrypt($text, $key=null){ }


		/**
		 * Encrypts a text returning the result as a base64 string.
		 */
		public function encryptBase64($text, $key=null, $safe=null){ }


		/**
		 * Decrypt a text that is coded as a base64 string.
		 *
		 * @throws \Phalcon\Crypt\Mismatch
		 */
		public function decryptBase64($text, $key=null, $safe=null){ }


		/**
		 * Returns a list of available ciphers.
		 */
		public function getAvailableCiphers(){ }


		/**
		 * Return a list of registered hashing algorithms suitable for hash_hmac.
		 */
		public function getAvailableHashAlgos(){ }


		/**
		 * Assert the cipher is available.
		 *
		 * @throws \Phalcon\Crypt\Exception
		 */
		protected function assertCipherIsAvailable($cipher){ }


		/**
		 * Assert the hash algorithm is available.
		 *
		 * @throws \Phalcon\Crypt\Exception
		 */
		protected function assertHashAlgorithmAvailable($hashAlgo){ }


		/**
		 * Initialize available cipher algorithms.
		 *
		 * @throws \Phalcon\Crypt\Exception
		 */
		protected function getIvLength($cipher){ }


		/**
		 * Initialize available cipher algorithms.
		 *
		 * @throws \Phalcon\Crypt\Exception
		 */
		protected function initializeAvailableCiphers(){ }

	}
}
