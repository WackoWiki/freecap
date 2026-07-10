<?php

namespace FreeCap;

class SessionAdapter implements AdapterInterface
{
	public function __construct(
		private object|array $session
		) {}

		public function get(string $key, mixed $default = null): mixed
		{
			if (is_array($this->session))
			{
				return $this->session[$key] ?? $default;
			}

			// WackoWiki custom session store might use magic properties
			if (isset($this->session->{$key}))
			{
				return $this->session->{$key};
			}

			// Or it might implement ArrayAccess
			if ($this->session instanceof \ArrayAccess && isset($this->session[$key]))
			{
				return $this->session[$key];
			}

			return $default;
		}

		public function set(string $key, mixed $value): void
		{
			if (is_array($this->session))
			{
				$this->session[$key] = $value;
				return;
			}

			// Prefer property access as used in WackoWiki's native code ($this->sess->freecap_shown = 1)
			$this->session->{$key} = $value;

			// Ensure ArrayAccess is updated too (if implemented)
			if ($this->session instanceof \ArrayAccess)
			{
				$this->session[$key] = $value;
			}
		}

		public function delete(string $key): void
		{
			if (is_array($this->session))
			{
				unset($this->session[$key]);
				return;
			}

			// Unset via magic property
			unset($this->session->{$key});

			// Unset via ArrayAccess (if implemented)
			if ($this->session instanceof \ArrayAccess)
			{
				unset($this->session[$key]);
			}
		}
}