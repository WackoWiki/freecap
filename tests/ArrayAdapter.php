<?php

namespace FreeCap\Tests;

use FreeCap\AdapterInterface;

class ArrayAdapter implements AdapterInterface
{
	private array $data = [];

	public function get(string $key, mixed $default = null): mixed
	{
		return $this->data[$key] ?? $default;
	}

	public function set(string $key, mixed $value): void
	{
		$this->data[$key] = $value;
	}

	public function delete(string $key): void
	{
		unset($this->data[$key]);
	}
}