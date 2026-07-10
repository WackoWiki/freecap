<?php
/************************************************************\
 *
 *	freeCap v1.5.0 Copyright
 *	2005 Howard Yeend (solidred.co.uk),
 *	2008 - 2025 WackoWiki Team
 *
 *	This file is part of freeCap.
 *
 *	freeCap is free software; you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation; either version 2 of the License, or
 *	(at your option) any later version.
 *
 *	freeCap is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with freeCap; if not, write to the Free Software
 *	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 *
 \************************************************************/

namespace FreeCap;

use GdImage;

class FreeCap
{
	public const VERSION = '1.5.0';
	private const SESSION_SHOWN = 'freecap_shown';
	private const SESSION_ATTEMPTS = 'freecap_attempts';
	private const SESSION_WORD_HASH = 'freecap_word_hash';
	private const SESSION_HASH_ALGO = 'hash_algo';

	// try to avoid the 'free p*rn' method of CAPTCHA circumvention
	// see en.wikipedia.org/captcha for more info
	// $site_tags[0] = 'To avoid spam, please do NOT enter the text if';
	// $site_tags[1] = 'this site is not example.com';
	// or more simply:
	// $site_tags[0] = 'for use only on example.com';
	// reword or add lines as you please
	// or if you don't want any text:
	public readonly ?array $site_tags;

	// where to write the above:
	// 0 = top
	// 1 = bottom
	// 2 = both
	public readonly int $tag_pos;

	// which type of hash to use?
	// possible values: 'sha1', 'sha256', 'SHA512'
	public readonly string $algo;

	// image type:
	// possible values: 'avif', 'gif', 'jpg', 'png', 'webp'
	// jpg doesn't support transparency (transparent bg option ends up white)
	// avif and webp may not be supported by your GD Lib.
	public readonly string $output;

	// 0 = generate pseudo-random string, 1 = use dictionary
	// dictionary is easier to recognise
	// - both for humans and computers, so use random string if you're paranoid.
	public readonly int $use_dict;

	// if your server is NOT set up to deny web access to files beginning '.ht'
	// then you should ensure the dictionary file is kept outside the web directory
	// eg: if www.example.com/index.html points to c:\website\www\index.html
	// then the dictionary should be placed in c:\website\dict.txt
	// test your server's config by trying to access the dictionary through a web browser
	// you should NOT be able to view the contents.
	// can leave this blank if not using dictionary
	public readonly string $dict_location;

	// used to calculate image width, and for non-dictionary word generation
	public readonly int $max_word_length;

	// text colour
	// 0 = one random colour for all letters
	// 1 = different random colour for each letter
	public readonly int $col_type;

	// maximum times a user can refresh the image
	// on a 6500 word dictionary, I think 15-50 is enough to not annoy users and make BF unfeasible.
	// further notes re: BF attacks in "avoid brute force attacks" section, below
	// on the other hand, those attempting OCR will find the ability to request new images
	// very useful; if they can't crack one, just grab an easier target...
	// for the ultra-paranoid, setting it to < 5 will still work for most users
	public readonly int $max_attempts;

	// background:
	// 0 = transparent (if jpg, white)
	// 1 = white bg with grid
	// 2 = white bg with squiggles
	// 3 = morphed image blocks
	// 'random' background from v1.3 didn't provide any extra security (according to 2 independent experts)
	// many thanks to http://ocr-research.org.ua and http://sam.zoy.org/pwntcha/ for testing
	// for jpgs, 'transparent' is white
	public readonly int $bg_type;

	// should we blur the background? (looks nicer, makes text easier to read, takes longer)
	public readonly bool $blur_bg;

	// for bg_type 3, which images should we use?
	// if you add your own, make sure they're fairly 'busy' images (ie a lot of shapes in them)
	public readonly array $bg_images;

	// for non-transparent backgrounds only:
	//   if 0, merges CAPTCHA with bg
	//   if 1, write CAPTCHA over bg
	public readonly int $merge_type;

	// should we morph the bg? (recommend yes, but takes a little longer to compute)
	public readonly bool $morph_bg;

	// If you are not successful in displaying an image (but the
	// background is displayed), it's likely you are on a Mac PowerPC,
	// Sun, or other machine that uses "big-endian" byte storage for
	// multibyte data types.  Switch the flag below for an alternate font
	// set that uses big-endian byte format.
	// Auto-Detect system endian value
	// Modified from: http://www.phpdig.net/ref/rn45re877.html
	// Convert $abyz to a binary string containing 32 bits
	// Do the conversion the way that the system architecture wants to
	// Then compare that to the Big-Endian version
	public readonly bool $big_endian;

	// list of fonts to use
	// font size should be around 35 pixels wide for each character.
	// you can use my GD fontmaker script at www.puremango.co.uk to create your own fonts
	// There are other programs to can create GD fonts, but my script allows a greater
	// degree of control over exactly how wide each character is, and is therefore
	// recommended for 'special' uses. For normal use of GD fonts,
	// the GDFontGenerator @ http://www.philiplb.de is excellent for converting ttf to GD

	// the fonts included with freeCap *only* include lowercase alphabetic characters
	// so are not suitable for most other uses
	// to increase security, you really should add other fonts
	public readonly array $font_locations;

	public function __construct(
		private AdapterInterface $adapter,
		array $custom = []
		)
	{
		if (!extension_loaded('gd'))
		{
			throw new \RuntimeException('GD extension is required for FreeCap');
		}

		// Default to the new 'resources' directory
		$asset_path = $custom['asset_path'] ?? __DIR__ . '/../resources';
		$this->big_endian = (pack('L', 0x6162797A) === pack('N', 0x6162797A));

		$font_files = $this->big_endian ? [
			'.ht_freecap_font1_big_e.gdf',
			'.ht_freecap_font2_big_e.gdf',
			'.ht_freecap_font3_big_e.gdf',
			'.ht_freecap_font4_big_e.gdf',
			'.ht_freecap_font5_big_e.gdf',
		] : [
			'.ht_freecap_font1.gdf',
			'.ht_freecap_font2.gdf',
			'.ht_freecap_font3.gdf',
			'.ht_freecap_font4.gdf',
			'.ht_freecap_font5.gdf',
		];

		$default_fonts = array_map(fn($f) => $asset_path . '/' . $f, $font_files);
		$default_bg_images = array_map(fn($f) => $asset_path . '/' . $f, [
			'.ht_freecap_im1.jpg', '.ht_freecap_im2.jpg', '.ht_freecap_im3.jpg',
			'.ht_freecap_im4.jpg', '.ht_freecap_im5.jpg'
		]);

		$this->site_tags       = $custom['site_tags'] ?? null;
		$this->tag_pos         = $custom['tag_pos'] ?? 1;
		$this->algo            = $custom['algo'] ?? 'sha1';
		$this->output          = $custom['output'] ?? 'webp';
		$this->use_dict        = $custom['use_dict'] ?? 1;
		$this->dict_location   = $custom['dict_location'] ?? ($asset_path . '/.ht_freecap_words');
		$this->max_word_length = $custom['max_word_length'] ?? 6;
		$this->col_type        = $custom['col_type'] ?? 1;
		$this->max_attempts    = $custom['max_attempts'] ?? 15;
		$this->bg_type         = $custom['bg_type'] ?? 1;
		$this->blur_bg         = $custom['blur_bg'] ?? true;
		$this->morph_bg        = $custom['morph_bg'] ?? true;
		$this->merge_type      = $custom['merge_type'] ?? 0;
		$this->font_locations  = $custom['font_locations'] ?? $default_fonts;
		$this->bg_images       = $custom['bg_images'] ?? $default_bg_images;
	}

	public function is_shown(): bool
	{
		// Use !empty() to loosely check for truthiness (handles boolean true or integer 1)
		return !empty($this->adapter->get(self::SESSION_SHOWN));
	}

	public function set_shown(bool $shown = true): void
	{
		$this->adapter->set(self::SESSION_SHOWN, $shown);
	}

	// checks whether user's captcha solution was right. function
	// takes no arguments, instead it receives user input from
	// HTTP-POST variable 'captcha', submitted through webform.
	public function validate(string $input): bool
	{
		$word_ok = true;

		if ($this->is_shown())
		{
			$word_ok = false;
			$this->adapter->delete(self::SESSION_SHOWN);

			$hash = $this->adapter->get(self::SESSION_WORD_HASH);
			if (!empty($hash) && !empty($input)
				&& hash($this->adapter->get(self::SESSION_HASH_ALGO, $this->algo), strtolower($input)) === $hash)
			{
				$this->adapter->delete(self::SESSION_ATTEMPTS);
				$word_ok = true;
			}
		}

		return $word_ok;
	}

	public function render(): never
	{
		$image_data = $this->build();
		header('Content-Type: ' . $this->get_content_type());
		echo $image_data;
		exit;
	}

	public function build(): string
	{
		// store in session so can validate in form processor
		$this->adapter->set(self::SESSION_HASH_ALGO, $this->algo);

		//////////////////////////////////////////////////////
		////// Avoid Brute Force Attacks:
		//////////////////////////////////////////////////////
		$attempts = $this->adapter->get(self::SESSION_ATTEMPTS, 0) + 1;
		$this->adapter->set(self::SESSION_ATTEMPTS, $attempts);

		if ($attempts > $this->max_attempts)
		{
			$this->adapter->set(self::SESSION_WORD_HASH, false);
			return $this->render_error_image('service no longer available');
		}

		//////////////////////////////////////////////////////
		////// Create Images + initialise a few things
		//////////////////////////////////////////////////////
		$font_widths = [];
		$header_length = $this->big_endian ? 12 : 11;

		foreach ($this->font_locations as $i => $loc)
		{
			if (!is_readable($loc))
			{
				continue;
			}
			$handle = fopen($loc, 'rb');
			$c_wid  = fread($handle, $header_length);
			fclose($handle);

			$font_widths[$i] = ord($c_wid[8]) + ord($c_wid[9]) + ord($c_wid[10]);
			if ($this->big_endian)
			{
				$font_widths[$i] += ord($c_wid[11]);
			}
		}

		// modify image width depending on maximum possible length of word
		// you shouldn't need to use words > 6 chars in length really.
		$width  = (int) (($this->max_word_length * (array_sum($font_widths) / max(1, count($font_widths)))) + 75);
		$height = 90;

		$im  = ImageCreate($width, $height);
		$im2 = ImageCreate($width, $height);

		//////////////////////////////////////////////////////
		////// Choose Word:
		//////////////////////////////////////////////////////
		$word = $this->choose_word();

		// save hash of word for comparison
		// using hash so that if there's an insecurity elsewhere (e.g. on the form processor),
		// an attacker could only get the hash
		// also, shared servers usually give all users access to the session files
		// echo `ls /tmp`; and echo `more /tmp/someone_elses_session_file`; usually work
		// so even if your site is 100% secure, someone else's site on your server might not be
		// hence, even if attackers can read the session file, they can't get the freeCap word
		// (though most hashes are easy to brute force for simple strings)
		$this->adapter->set(self::SESSION_WORD_HASH, hash($this->algo, $word));

		//////////////////////////////////////////////////////
		////// Fill BGs and Allocate Colours:
		//////////////////////////////////////////////////////
		$tag_col       = ImageColorAllocate($im, 10, 10, 10);
		$site_tag_col2 = ImageColorAllocate($im2, 0, 0, 0);

		$bg  = ImageColorAllocate($im, 254, 254, 254);
		$bg2 = ImageColorAllocate($im2, 254, 254, 254);

		ImageColorTransparent($im, $bg);
		ImageColorTransparent($im2, $bg2);
		ImageFill($im, 0, 0, $bg);
		ImageFill($im2, 0, 0, $bg2);

		$im3 = null;
		if ($this->bg_type)
		{
			$im3        = ImageCreateTrueColor($width, $height);
			$temp_bg    = ImageCreateTrueColor((int) round($width * 1.5), (int) round($height * 1.5));
			$bg3        = ImageColorAllocate($im3, 255, 255, 255);
			$temp_bg_col = ImageColorAllocate($temp_bg, 255, 255, 255);
			ImageFill($im3, 0, 0, $bg3);
			ImageFill($temp_bg, 0, 0, $temp_bg_col);

			if ($this->bg_type === 1)
			{
				$this->draw_grid($temp_bg, $width, $height);
			}
			elseif ($this->bg_type === 2)
			{
				$this->draw_squiggles($temp_bg, $height, $word);
			}
			elseif ($this->bg_type === 3)
			{
				$this->draw_morphed_blocks($temp_bg, $width, $height, $im3);
			}

			if ($this->morph_bg)
			{
				$this->morph_background($im3, $temp_bg, $width, $height);
			}
			else
			{
				ImageCopy($im3, $temp_bg, 0, 0, 30, 30, $width, $height);
			}

			if ($this->blur_bg)
			{
				$this->blur($im3);
			}
		}

		//////////////////////////////////////////////////////
		////// Write Word
		//////////////////////////////////////////////////////
		$word_start_x = $this->rand(5, 32);
		$word_start_y = 15;
		$text_colour2 = null;

		if ($this->col_type === 0)
		{
			$text_colour2 = ImageColorAllocate($im2, $this->rand_color(), $this->rand_color(), $this->rand_color());
		}

		$last_width = 15;
		foreach (str_split($word) as $i => $char)
		{
			if ($this->col_type === 1)
			{
				$text_colour2 = ImageColorAllocate($im2, $this->rand_color(), $this->rand_color(), $this->rand_color());
			}

			$j = $this->rand(0, count($this->font_locations) - 1);
			$font_path = $this->font_locations[$j] ?? '';
			$current_width = $font_widths[$j] ?? 15;

			if (!empty($font_path) && file_exists($font_path) && is_readable($font_path))
			{
				$font = ImageLoadFont($font_path);
			}
			else
			{
				$font = 5; // Fallback to built-in font
				$current_width = 15;
			}

			ImageString($im2, $font, $word_start_x + ($current_width * $i), $word_start_y, $char, $text_colour2);
			$last_width = $current_width;
		}

		$font_pixelwidth = $last_width;
		$word_pix_size   = $word_start_x + (strlen($word) * $font_pixelwidth);

		//////////////////////////////////////////////////////
		////// Morph: per-character vertical shift
		//////////////////////////////////////////////////////
		$y_pos = 0;
		for ($i = $word_start_x; $i < $word_pix_size; $i += $font_pixelwidth)
		{
			$prev_y = $y_pos;
			do
			{
				$y_pos = $this->rand(-5, 5);
			}
			while ($y_pos < $prev_y + 2 && $y_pos > $prev_y - 2);
			ImageCopy($im, $im2, $i, $y_pos, $i, 0, $font_pixelwidth, $height);
		}

		ImageFilledRectangle($im2, 0, 0, $width, $height, $bg2);

		//////////////////////////////////////////////////////
		////// Morph: per-character horizontal wave
		//////////////////////////////////////////////////////
		$morph_factor = 1;
		$morph_x = 0;
		for ($j = 0; $j < strlen($word); $j++)
		{
			$y_pos = 0;
			for ($i = 0; $i <= $height; $i += 1)
			{
				$orig_x  = $word_start_x + ($j * $font_pixelwidth);
				$morph_x += $this->rand(-$morph_factor, $morph_factor);
				ImageCopyMerge($im2, $im, $orig_x + $morph_x, $i + $y_pos, $orig_x, $i, $font_pixelwidth, 1, 100);
			}
		}

		ImageFilledRectangle($im, 0, 0, $width, $height, $bg);

		//////////////////////////////////////////////////////
		////// Morph: vertical wave across whole image
		//////////////////////////////////////////////////////
		$y_pos  = 0;
		for ($i = 0; $i <= $width; $i += 1)
		{
			$y_pos += $this->rand(-1, 1);
			ImageCopy($im, $im2, $i, $y_pos, $i, 0, 1, $height);
		}

		$this->blur($im);

		if ($this->output !== 'jpg' && $this->bg_type === 0)
		{
			ImageColorTransparent($im, $bg);
		}

		//////////////////////////////////////////////////////
		////// Site tags
		//////////////////////////////////////////////////////
		ImageFilledRectangle($im2, 0, 0, $width, $height, $bg2);
		if (is_array($this->site_tags))
		{
			foreach ($this->site_tags as $i => $tag)
			{
				$tag_width = strlen($tag) * 6;
				if ($this->tag_pos === 0 || $this->tag_pos === 2)
				{
					ImageString($im2, 2, intdiv($width, 2) - intdiv($tag_width, 2), 10 * $i, $tag, $site_tag_col2);
				}
				if ($this->tag_pos === 1 || $this->tag_pos === 2)
				{
					ImageString($im2, 2, intdiv($width, 2) - intdiv($tag_width, 2), $height - 34 + ($i * 10), $tag, $site_tag_col2);
				}
			}
		}

		ImageCopyMerge($im2, $im, 0, 0, 0, 0, $width, $height, 80);
		ImageCopy($im, $im2, 0, 0, 0, 0, $width, $height);

		//////////////////////////////////////////////////////
		////// Merge CAPTCHA with background
		//////////////////////////////////////////////////////
		if ($this->bg_type && $im3 !== null)
		{
			$bg_fade_pct = match($this->bg_type)
			{
				3 => 50,
				default => 65
			};

			if ($this->bg_type !== 3)
			{
				$temp_im = ImageCreateTrueColor($width, $height);
				$white   = ImageColorAllocate($temp_im, 255, 255, 255);
				ImageFill($temp_im, 0, 0, $white);
				ImageCopyMerge($im3, $temp_im, 0, 0, 0, 0, $width, $height, $bg_fade_pct);
				$c_fade_pct = 50;
			}
			else
			{
				$c_fade_pct = $bg_fade_pct;
			}

			if ($this->merge_type === 1)
			{
				ImageCopyMerge($im3, $im, 0, 0, 0, 0, $width, $height, 100);
				ImageCopy($im, $im3, 0, 0, 0, 0, $width, $height);
			}
			else
			{
				ImageCopyMerge($im, $im3, 0, 0, 0, 0, $width, $height, $c_fade_pct);
			}
		}

		// unset all sensitive vars
		unset($word);

		return $this->to_string($im);
	}

	private function choose_word(): string
	{
		if ($this->use_dict === 1)
		{
			if (!is_readable($this->dict_location))
			{
				// Gracefully fallback to pseudo-random string if dictionary is missing
				error_log('[freeCap] Dictionary file not found: ' . $this->dict_location . '. Falling back to pseudo-random string.');
				return $this->generate_random_word();
			}

			$words = file($this->dict_location, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			if (empty($words))
			{
				error_log('[freeCap] Dictionary file is empty: ' . $this->dict_location . '. Falling back to pseudo-random string.');
				return $this->generate_random_word();
			}

			$word = strtolower($words[array_rand($words)]);
			$word = preg_replace('/[^a-z]/', '', $word);
			return $word;
		}
		else
		{
			return $this->generate_random_word();
		}
	}

	private function generate_random_word(): string
	{
		// generate pseudo-random string
		// doesn't use ijtf as are easily mistaken
		// I'm not using numbers because the custom fonts I've created don't support anything other than
		// lowercase or space (but you can download new fonts or create your own using my GD fontmaker script)
		$consonants = 'bcdghklmnpqrsvwxyz';
		$vowels     = 'aeuo';
		$word       = '';
		$wordlen    = $this->rand(5, $this->max_word_length);

		for ($i = 0; $i < $wordlen; $i++)
		{
			// don't allow to start with 'vowel'
			if ($this->rand(0, 4) >= 2 && $i !== 0)
			{
				$word .= $vowels[$this->rand(0, strlen($vowels) - 1)];
			}
			else
			{
				$word .= $consonants[$this->rand(0, strlen($consonants) - 1)];
			}
		}

		return $word;
	}

	private function draw_grid(GdImage $temp, int $width, int $height): void
	{
		for ($i = $this->rand(6, 20); $i < $width * 2; $i += $this->rand(10, 25))
		{
			ImageSetThickness($temp, $this->rand(2, 6));
			$colour = ImageColorAllocate($temp, $this->rand(100, 150), $this->rand(100, 150), $this->rand(100, 150));
			ImageLine($temp, $i, 0, $i, $height * 2, $colour);
		}

		for ($i = $this->rand(6, 20); $i < $height * 2; $i += $this->rand(10, 25))
		{
			ImageSetThickness($temp, $this->rand(2, 6));
			$colour = ImageColorAllocate($temp, $this->rand(100, 150), $this->rand(100, 150), $this->rand(100, 150));
			ImageLine($temp, 0, $i, $width * 2, $i, $colour);
		}
	}

	private function draw_squiggles(GdImage $temp, int $height, string $word): void
	{
		ImageSetThickness($temp, 4);

		for ($i = 0; $i < strlen($word) + 1; $i++)
		{
			$colour = ImageColorAllocate($temp, $this->rand(100, 150), $this->rand(100, 150), $this->rand(100, 150));
			$points = [];
			for ($j = 1; $j < $this->rand(5, 10); $j++)
			{
				$points[] = $this->rand(20 * ($i + 1), 50 * ($i + 1));
				$points[] = $this->rand(30, $height + 30);
			}
			ImagePolygon($temp, $points, intdiv(count($points), 2), $colour);
		}
	}

	private function draw_morphed_blocks(GdImage $temp, int $width, int $height, GdImage $im3): void
	{
		$temp_imgs = [];
		foreach ($this->bg_images as $i => $img)
		{
			if (!is_readable($img))
			{
				continue;
			}
			$temp_imgs[$i] = ImageCreateFromJPEG($img);
		}

		if (empty($temp_imgs))
		{
			return;
		}

		$blocksize = $this->rand(20, 60);

		for ($i = 0; $i < $width * 2; $i += $blocksize)
		{
			for ($j = 0; $j < $height * 2; $j += $blocksize)
			{
				$idx = array_rand($temp_imgs);
				$cut_x = $this->rand(0, imagesx($temp_imgs[$idx]) - $blocksize);
				$cut_y = $this->rand(0, imagesy($temp_imgs[$idx]) - $blocksize);
				ImageCopy($temp, $temp_imgs[$idx], $i, $j, $cut_x, $cut_y, $blocksize, $blocksize);
			}
		}
	}

	private function morph_background(GdImage $im3, GdImage $temp, int $width, int $height): void
	{
		$chunk = $this->rand(1, 5);
		$my    = 0;

		for ($x = 0; $x < $width; $x += $chunk)
		{
			$chunk = $this->rand(1, 5);
			$my   += $this->rand(-1, 1);
			ImageCopy($im3, $temp, $x, 0, $x + 30, 30 + $my, $chunk, $height * 2);
		}

		ImageCopy($temp, $im3, 0, 0, 0, 0, $width, $height);

		$mx = 0;
		for ($y = 0; $y <= $height; $y += $chunk)
		{
			$chunk = $this->rand(1, 5);
			$mx   += $this->rand(-1, 1);
			ImageCopy($im3, $temp, $mx, $y, 0, $y, $width, $chunk);
		}
	}

	private function blur(GdImage $im): GdImage
	{
		// w00t. my very own blur function
		// in GD2, there's a gaussian blur function. bunch of bloody show-offs... :-)
		$width  = imagesx($im);
		$height = imagesy($im);

		$temp = ImageCreateTrueColor($width, $height);
		$bg   = ImageColorAllocate($temp, 150, 150, 150);

		// preserves transparency if in orig image
		ImageColorTransparent($temp, $bg);
		ImageFill($temp, 0, 0, $bg);

		// anything higher than 3 makes it totally unreadable
		// might be useful in a 'real' blur function, though (ie blurring pictures not text)
		$distance = 1;

		// blur by merging with itself at different x/y offsets:
		ImageCopyMerge($temp, $im, 0, 0, 0, $distance, $width, $height - $distance, 70);
		ImageCopyMerge($im, $temp, 0, 0, $distance, 0, $width - $distance, $height, 70);
		ImageCopyMerge($temp, $im, 0, $distance, 0, 0, $width, $height, 70);
		ImageCopyMerge($im, $temp, $distance, 0, 0, 0, $width, $height, 70);

		return $im;
	}

	private function rand(int $min, int $max): int
	{
		try
		{
			return random_int($min, $max);
		}
		catch (\Throwable)
		{
			return mt_rand($min, $max);
		}
	}

	private function rand_color(): int
	{
		// needs darker colour..
		if ($this->bg_type == 3)
		{
			return $this->rand(10, 100);
		}
		return $this->rand(60, 170);
	}

	private function get_content_type(): string
	{
		return match ($this->output)
		{
			'avif' => 'image/avif',
			'gif'  => 'image/gif',
			'jpg'  => 'image/jpeg',
			'png'  => 'image/png',
			default => 'image/webp',
		};
	}

	private function to_string(GdImage $im): string
	{
		ob_start();
		if ($this->output === 'avif' || $this->output === 'webp')
		{
			imagepalettetotruecolor($im);
		}
		match ($this->output)
		{
			'avif' => imageavif($im),
			'gif'  => imagegif($im),
			'jpg'  => imagejpeg($im),
			'png'  => imagepng($im),
			default => imagewebp($im),
		};
		return ob_get_clean();
	}

	private function render_error_image(string $message): string
	{
		$im = ImageCreate(150, 40);
		$bg = ImageColorAllocate($im, 255, 255, 255);
		$fg = ImageColorAllocate($im, 200, 0, 0);
		ImageString($im, 3, 5, 12, $message, $fg);

		ob_start();
		imagepng($im);
		return ob_get_clean();
	}
}