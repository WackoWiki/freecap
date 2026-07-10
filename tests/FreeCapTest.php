<?php

namespace FreeCap\Tests;

use PHPUnit\Framework\TestCase;
use FreeCap\FreeCap;

final class FreeCapTest extends TestCase
{
	private ArrayAdapter $adapter;

	protected function setUp(): void
	{
		if (!extension_loaded('gd'))
		{
			$this->markTestSkipped('GD extension is not available.');
		}
		$this->adapter = new ArrayAdapter();
	}

	#[Test]
	public function test_generates_image_and_stores_hash(): void
	{
		// Enable pseudo-random string to avoid file dependency on dictionary
		$captcha = new FreeCap($this->adapter, ['use_dict' => 0, 'bg_type' => 0]);

		$image_data = $captcha->build();

		$this->assertNotEmpty($image_data, 'The generated image data should not be empty.');
		$this->assertNotEmpty($this->adapter->get('freecap_word_hash'), 'The CAPTCHA hash should be stored in the session.');
	}

	#[Test]
	public function test_validation_success(): void
	{
		$captcha = new FreeCap($this->adapter, ['use_dict' => 0, 'algo' => 'sha1']);

		// Simulate word generation and visualization
		$captcha->build();
		$captcha->set_shown(true);

		$hash = $this->adapter->get('freecap_word_hash');
		$original_word = 'dummy'; // We'll reconstruct what needs to be sent

		// We bypass the actual image word by manually mapping a test hash
		$test_word = 'testword';
		$this->adapter->set('freecap_word_hash', hash('sha1', $test_word));

		$this->assertTrue($captcha->validate($test_word), 'Validation should succeed with the correct word.');
		$this->assertFalse($captcha->is_shown(), 'Shown flag should be cleared after validation.');
	}

	#[Test]
	public function test_validation_failure(): void
	{
		$captcha = new FreeCap($this->adapter, ['use_dict' => 0, 'algo' => 'sha1']);

		$captcha->build();
		$captcha->set_shown(true);

		$this->adapter->set('freecap_word_hash', hash('sha1', 'correctword'));

		$this->assertFalse($captcha->validate('wrongword'), 'Validation should fail with the wrong word.');
	}

	#[Test]
	public function test_brute_force_protection(): void
	{
		$captcha = new FreeCap($this->adapter, [
			'use_dict' => 0,
			'max_attempts' => 3
		]);

		// Simulate reaching the max attempts limit
		$this->adapter->set('freecap_attempts', 3);

		// Should trigger lockout and return error image
		$image_data = $captcha->build();

		$this->assertNotEmpty($image_data);
		$this->assertFalse($this->adapter->get('freecap_word_hash'), 'Hash should be false after brute force lockout.');
	}
}