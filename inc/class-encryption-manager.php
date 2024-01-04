<?php
/**
 * Copyright: © 2021-2022, SNS
 * License: GNU General Public License v3.0
 *
 * @author      ICT Scuola Normale Superiore
 * @category    Payment Module
 * @package     PagoPA Gateway Cineca
 * @version     1.1.6
 * @copyright   Copyright (c) 2021 SNS)
 * @license     GNU General Public License v3.0
 */

DEFINE( 'CIPHER', 'aes-256-ctr' );
DEFINE( 'IV_KEY', 'p2345hd9101f45t1' );

/**
 * EncryptionManager class
 */
class Encryption_Manager {

	/**
	 * Encrypt the text passed as parameter.
	 *
	 * @param String $text - The text to be encrypted.
	 * @param String $key - The key for the encryption.
	 * @return string
	 */
	public static function encrypt_text( $text, $key ) {
		return openssl_encrypt( $text, CIPHER, $key, 0, IV_KEY );
	}

	/**
	 * Decrypt the text passed as parameter.
	 *
	 * @param String $text - The text to be decrypted.
	 * @param String $key - The key for the decryption.
	 * @return string
	 */
	public static function decrypt_text( $text, $key ) {
		return openssl_decrypt( $text, CIPHER, $key, 0, IV_KEY );
	}

}
