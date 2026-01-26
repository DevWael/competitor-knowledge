<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Unit\Core;

use CompetitorKnowledge\Core\Encryption;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;

class EncryptionTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Define WordPress constant for encryption key
		if ( ! defined( 'SECURE_AUTH_SALT' ) ) {
			define( 'SECURE_AUTH_SALT', 'test-salt-for-encryption-testing-12345' );
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_encrypt_returns_base64_string() {
		$plaintext = 'my-secret-api-key';
		$encrypted = Encryption::encrypt( $plaintext );

		$this->assertNotEmpty( $encrypted );
		$this->assertNotEquals( $plaintext, $encrypted );
		// Base64 encoded strings should be decodable
		$this->assertNotFalse( base64_decode( $encrypted, true ) );
	}

	public function test_decrypt_returns_original_value() {
		$plaintext = 'my-secret-api-key-12345';
		$encrypted = Encryption::encrypt( $plaintext );
		$decrypted = Encryption::decrypt( $encrypted );

		$this->assertEquals( $plaintext, $decrypted );
	}

	public function test_encrypt_empty_string_returns_empty() {
		$encrypted = Encryption::encrypt( '' );

		$this->assertEquals( '', $encrypted );
	}

	public function test_decrypt_empty_string_returns_empty() {
		$decrypted = Encryption::decrypt( '' );

		$this->assertEquals( '', $decrypted );
	}

	public function test_is_encrypted_detects_encrypted_data() {
		$plaintext = 'test-data';
		$encrypted = Encryption::encrypt( $plaintext );

		$this->assertTrue( Encryption::is_encrypted( $encrypted ) );
	}

	public function test_is_encrypted_returns_false_for_plain_text() {
		$plaintext = 'plain-text-not-encrypted';

		$this->assertFalse( Encryption::is_encrypted( $plaintext ) );
	}

	public function test_is_encrypted_returns_false_for_empty_string() {
		$this->assertFalse( Encryption::is_encrypted( '' ) );
	}

	public function test_decrypt_handles_backward_compatibility() {
		// Plain text should be returned as-is if not encrypted
		$plaintext = 'legacy-unencrypted-value';
		$decrypted = Encryption::decrypt( $plaintext );

		$this->assertEquals( $plaintext, $decrypted );
	}

	public function test_encrypt_decrypt_roundtrip_with_special_characters() {
		$plaintext = 'api-key-with-special-chars-!@#$%^&*()_+-=[]{}|;:,.<>?';
		$encrypted = Encryption::encrypt( $plaintext );
		$decrypted = Encryption::decrypt( $encrypted );

		$this->assertEquals( $plaintext, $decrypted );
	}

	public function test_encrypt_decrypt_roundtrip_with_unicode() {
		$plaintext = 'unicode-test-你好世界-مرحبا-🔐';
		$encrypted = Encryption::encrypt( $plaintext );
		$decrypted = Encryption::decrypt( $encrypted );

		$this->assertEquals( $plaintext, $decrypted );
	}

	public function test_multiple_encryptions_produce_different_ciphertexts() {
		// Due to random IV, same plaintext should produce different ciphertexts
		$plaintext  = 'same-plaintext';
		$encrypted1 = Encryption::encrypt( $plaintext );
		$encrypted2 = Encryption::encrypt( $plaintext );

		$this->assertNotEquals( $encrypted1, $encrypted2 );

		// But both should decrypt to the same value
		$this->assertEquals( $plaintext, Encryption::decrypt( $encrypted1 ) );
		$this->assertEquals( $plaintext, Encryption::decrypt( $encrypted2 ) );
	}

	public function test_decrypt_invalid_base64_returns_original() {
		$invalid = 'not-valid-base64-!!!';
		$result  = Encryption::decrypt( $invalid );

		// Should return original if decryption fails
		$this->assertEquals( $invalid, $result );
	}

	public function test_encrypt_long_string() {
		$long_plaintext = str_repeat( 'a', 1000 );
		$encrypted      = Encryption::encrypt( $long_plaintext );
		$decrypted      = Encryption::decrypt( $encrypted );

		$this->assertEquals( $long_plaintext, $decrypted );
	}

	public function test_encrypt_short_string() {
		$short_plaintext = 'a';
		$encrypted       = Encryption::encrypt( $short_plaintext );
		$decrypted       = Encryption::decrypt( $encrypted );

		$this->assertEquals( $short_plaintext, $decrypted );
	}

	public function test_is_encrypted_with_null_bytes() {
		// Test that is_encrypted handles strings with null bytes
		$weird_string = "test\x00string";
		$this->assertFalse( Encryption::is_encrypted( $weird_string ) );
	}

	public function test_encrypt_decrypt_with_whitespace() {
		$whitespace = "  spaces and\ttabs\nand\nnewlines  ";
		$encrypted  = Encryption::encrypt( $whitespace );
		$decrypted  = Encryption::decrypt( $encrypted );

		$this->assertEquals( $whitespace, $decrypted );
	}

	public function test_is_encrypted_returns_false_for_short_base64() {
		// Short base64 strings shouldn't be considered encrypted
		$short_base64 = base64_encode( 'short' );
		$this->assertFalse( Encryption::is_encrypted( $short_base64 ) );
	}

	public function test_encrypt_returns_string_type() {
		$encrypted = Encryption::encrypt( 'test' );
		$this->assertIsString( $encrypted );
	}

	public function test_decrypt_returns_string_type() {
		$encrypted = Encryption::encrypt( 'test' );
		$decrypted = Encryption::decrypt( $encrypted );
		$this->assertIsString( $decrypted );
	}
}

