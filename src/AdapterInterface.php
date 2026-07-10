<?php

namespace FreeCap;

interface AdapterInterface
{
	public function get(string $key, mixed $default = null): mixed;
	public function set(string $key, mixed $value): void;
	public function delete(string $key): void;
}