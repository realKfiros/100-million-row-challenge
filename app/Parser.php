<?php

namespace App;

use App\Commands\Visit;

final class Parser {
	private const int URI_PREFIX_LEN = 19;	// "https://stitcher.io"
	private const int READ_CHUNK_SIZE = 1_048_576;
	private const int NEXT_PATH_OFFSET = 46;

	private static string $input_path = '';
	private static string $output_path = '';
	private static ?array $known_path_set = null;
	private static ?array $date_list = null;
	private static ?array $year_offsets = null;
	private static ?array $month_offsets_common = null;
	private static ?array $month_offsets_leap = null;

	public function parse(string $input_path, string $output_path): void {
		gc_disable();

		$date_list = Parser::buildDateList();
		$date_count = count($date_list);

		Parser::$input_path = $input_path;
		Parser::$output_path = $output_path;

		$paths = Parser::discoverPaths();
		$path_count = count($paths);

		$path_base_map = [];
		foreach ($paths as $path_id => $path) {
			$path_base_map[$path] = $path_id * $date_count;
		}

		$counts = array_fill(0, $path_count * $date_count, 0);

		Parser::countVisits($path_base_map, $counts);
		Parser::writePrettyJson($counts, $paths, $date_list, $date_count);
	}

	// get all the known paths
	private static function getKnownPathSet(): array {
		if (!empty(Parser::$known_path_set)) {
			return Parser::$known_path_set;
		}

		$known_path_set = [];

		foreach (Visit::all() as $visit) {
			$path = substr($visit->uri, Parser::URI_PREFIX_LEN);
			$known_path_set[$path] = true;
		}

		return Parser::$known_path_set = $known_path_set;
	}

	// build a list of all the dates we'll be counting - in our case the start of 2021 to the end of 2026
	private static function buildDateList(): array {
		if (!empty(Parser::$date_list)) {
			return Parser::$date_list;
		}

		$date_list = [];
		$date_id = 0;

		for ($year = 2021; $year <= 2026; ++$year) {
			for ($month = 1; $month <= 12; ++$month) {
				$max_day = match ($month) {
					2 => Parser::isLeapYear($year) ? 29 : 28,
					4, 6, 9, 11 => 30,
					default => 31,
				};

				for ($day = 1; $day <= $max_day; ++$day) {
					$date_list[$date_id++] =
						$year . '-' .
						($month < 10 ? '0' : '') . $month . '-' .
						($day < 10 ? '0' : '') . $day;
				}
			}
		}

		return Parser::$date_list = $date_list;
	}

	private static function initYearOffsets(): void {
		if (!empty(Parser::$year_offsets)) {
			return;
		}

		Parser::$year_offsets = [
			2021 => 0,
			2022 => 365,
			2023 => 730,
			2024 => 1095,
			2025 => 1461,
			2026 => 1826,
		];
	}

	// get the day of year of the start of every month
	private static function initMonthOffsets(): void {
		if (!empty(Parser::$month_offsets_common)) {
			return;
		}

		// start of every month in a common year and in a leap year
		Parser::$month_offsets_common = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
		Parser::$month_offsets_leap = [0, 31, 60, 91, 121, 152, 182, 213, 244, 274, 305, 335];
	}

	private static function isLeapYear(int $year): bool {
		return ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
	}

	// read the whole csv and extract the paths
	private static function discoverPaths(): array {
		$input = fopen(Parser::$input_path, 'r');

		$known_path_set = Parser::getKnownPathSet();
		$paths = [];
		$seen = [];
		$tail = '';

		// optimization - read 1mb of data every time
		while (!feof($input)) {
			$buffer = fread($input, Parser::READ_CHUNK_SIZE);

			if ($buffer === '') {
				break;
			}

			if ($tail !== '') {
				$buffer = $tail . $buffer;
			}

			$last_newline = strrpos($buffer, "\n");
			if ($last_newline === false) {
				$tail = $buffer;
				continue;
			}

			$chunk_len = $last_newline;
			$p = self::URI_PREFIX_LEN;
			$fence = $chunk_len - 1024;

			while ($p < $fence) {
				$sep = strpos($buffer, ',', $p);
				if ($sep === false || $sep >= $chunk_len) {
					break;
				}

				$path = substr($buffer, $p, $sep - $p);
				if (isset($known_path_set[$path]) && !isset($seen[$path])) {
					$seen[$path] = true;
					$paths[] = $path;
				}
				$p = $sep + self::NEXT_PATH_OFFSET;

				$sep = strpos($buffer, ',', $p);
				if ($sep === false || $sep >= $chunk_len) {
					break;
				}

				$path = substr($buffer, $p, $sep - $p);
				if (isset($known_path_set[$path]) && !isset($seen[$path])) {
					$seen[$path] = true;
					$paths[] = $path;
				}
				$p = $sep + self::NEXT_PATH_OFFSET;

				$sep = strpos($buffer, ',', $p);
				if ($sep === false || $sep >= $chunk_len) {
					break;
				}

				$path = substr($buffer, $p, $sep - $p);
				if (isset($known_path_set[$path]) && !isset($seen[$path])) {
					$seen[$path] = true;
					$paths[] = $path;
				}
				$p = $sep + self::NEXT_PATH_OFFSET;

				$sep = strpos($buffer, ',', $p);
				if ($sep === false || $sep >= $chunk_len) {
					break;
				}

				$path = substr($buffer, $p, $sep - $p);
				if (isset($known_path_set[$path]) && !isset($seen[$path])) {
					$seen[$path] = true;
					$paths[] = $path;
				}
				$p = $sep + self::NEXT_PATH_OFFSET;

				$sep = strpos($buffer, ',', $p);
				if ($sep === false || $sep >= $chunk_len) {
					break;
				}

				$path = substr($buffer, $p, $sep - $p);
				if (isset($known_path_set[$path]) && !isset($seen[$path])) {
					$seen[$path] = true;
					$paths[] = $path;
				}
				$p = $sep + self::NEXT_PATH_OFFSET;

				$sep = strpos($buffer, ',', $p);
				if ($sep === false || $sep >= $chunk_len) {
					break;
				}

				$path = substr($buffer, $p, $sep - $p);
				if (isset($known_path_set[$path]) && !isset($seen[$path])) {
					$seen[$path] = true;
					$paths[] = $path;
				}
				$p = $sep + self::NEXT_PATH_OFFSET;

				$sep = strpos($buffer, ',', $p);
				if ($sep === false || $sep >= $chunk_len) {
					break;
				}

				$path = substr($buffer, $p, $sep - $p);
				if (isset($known_path_set[$path]) && !isset($seen[$path])) {
					$seen[$path] = true;
					$paths[] = $path;
				}
				$p = $sep + self::NEXT_PATH_OFFSET;

				$sep = strpos($buffer, ',', $p);
				if ($sep === false || $sep >= $chunk_len) {
					break;
				}

				$path = substr($buffer, $p, $sep - $p);
				if (isset($known_path_set[$path]) && !isset($seen[$path])) {
					$seen[$path] = true;
					$paths[] = $path;
				}
				$p = $sep + self::NEXT_PATH_OFFSET;
			}

			while ($p < $chunk_len) {
				$sep = strpos($buffer, ',', $p);
				if ($sep === false || $sep >= $chunk_len) {
					break;
				}

				$path = substr($buffer, $p, $sep - $p);
				if (isset($known_path_set[$path]) && !isset($seen[$path])) {
					$seen[$path] = true;
					$paths[] = $path;
				}

				$p = $sep + self::NEXT_PATH_OFFSET;
			}

			$tail = substr($buffer, $last_newline + 1);
		}

		if ($tail !== '') {
			$comma = strpos($tail, ',');
			if ($comma !== false && $comma > self::URI_PREFIX_LEN) {
				$path = substr($tail, self::URI_PREFIX_LEN, $comma - self::URI_PREFIX_LEN);
				if (isset($known_path_set[$path]) && !isset($seen[$path])) {
					$paths[] = $path;
				}
			}
		}

		fclose($input);

		return $paths;
	}

	// counts the visits to each blogpost (path)
	private static function countVisits(array $path_base_map, array &$counts): void {
		Parser::initYearOffsets();
		Parser::initMonthOffsets();

		$input = fopen(Parser::$input_path, 'r');

		$tail = '';

		// optimization - read 1mb of data every time
		while (!feof($input)) {
			$buffer = fread($input, Parser::READ_CHUNK_SIZE);

			if ($buffer === '') {
				break;
			}

			if ($tail !== '') {
				$buffer = $tail . $buffer;
			}

			$last_newline = strrpos($buffer, "\n");
			if ($last_newline === false) {
				$tail = $buffer;
				continue;
			}

			$chunk_len = $last_newline;
			$p = self::URI_PREFIX_LEN;

			// keep a bit of space in the end so it won't override the limit in the aggressive loop
			$fence = $chunk_len - 1024;

			while ($p < $fence) {
				$sep = strpos($buffer, ',', $p);
				if ($sep === false || $sep >= $chunk_len) {
					break;
				}

				$path = substr($buffer, $p, $sep - $p);
				$path_base = $path_base_map[$path] ?? null;
				if ($path_base !== null) {
					$date_id = Parser::parseDateId($buffer, $sep + 1);
					if ($date_id !== null) {
						++$counts[$path_base + $date_id];
					}
				}
				$p = $sep + self::NEXT_PATH_OFFSET;

				$sep = strpos($buffer, ',', $p);
				if ($sep === false || $sep >= $chunk_len) {
					break;
				}

				$path = substr($buffer, $p, $sep - $p);
				$path_base = $path_base_map[$path] ?? null;
				if ($path_base !== null) {
					$date_id = Parser::parseDateId($buffer, $sep + 1);
					if ($date_id !== null) {
						++$counts[$path_base + $date_id];
					}
				}
				$p = $sep + self::NEXT_PATH_OFFSET;

				$sep = strpos($buffer, ',', $p);
				if ($sep === false || $sep >= $chunk_len) {
					break;
				}

				$path = substr($buffer, $p, $sep - $p);
				$path_base = $path_base_map[$path] ?? null;
				if ($path_base !== null) {
					$date_id = Parser::parseDateId($buffer, $sep + 1);
					if ($date_id !== null) {
						++$counts[$path_base + $date_id];
					}
				}
				$p = $sep + self::NEXT_PATH_OFFSET;

				$sep = strpos($buffer, ',', $p);
				if ($sep === false || $sep >= $chunk_len) {
					break;
				}

				$path = substr($buffer, $p, $sep - $p);
				$path_base = $path_base_map[$path] ?? null;
				if ($path_base !== null) {
					$date_id = Parser::parseDateId($buffer, $sep + 1);
					if ($date_id !== null) {
						++$counts[$path_base + $date_id];
					}
				}
				$p = $sep + self::NEXT_PATH_OFFSET;

				$sep = strpos($buffer, ',', $p);
				if ($sep === false || $sep >= $chunk_len) {
					break;
				}

				$path = substr($buffer, $p, $sep - $p);
				$path_base = $path_base_map[$path] ?? null;
				if ($path_base !== null) {
					$date_id = Parser::parseDateId($buffer, $sep + 1);
					if ($date_id !== null) {
						++$counts[$path_base + $date_id];
					}
				}
				$p = $sep + self::NEXT_PATH_OFFSET;

				$sep = strpos($buffer, ',', $p);
				if ($sep === false || $sep >= $chunk_len) {
					break;
				}

				$path = substr($buffer, $p, $sep - $p);
				$path_base = $path_base_map[$path] ?? null;
				if ($path_base !== null) {
					$date_id = Parser::parseDateId($buffer, $sep + 1);
					if ($date_id !== null) {
						++$counts[$path_base + $date_id];
					}
				}
				$p = $sep + self::NEXT_PATH_OFFSET;

				$sep = strpos($buffer, ',', $p);
				if ($sep === false || $sep >= $chunk_len) {
					break;
				}

				$path = substr($buffer, $p, $sep - $p);
				$path_base = $path_base_map[$path] ?? null;
				if ($path_base !== null) {
					$date_id = Parser::parseDateId($buffer, $sep + 1);
					if ($date_id !== null) {
						++$counts[$path_base + $date_id];
					}
				}
				$p = $sep + self::NEXT_PATH_OFFSET;

				$sep = strpos($buffer, ',', $p);
				if ($sep === false || $sep >= $chunk_len) {
					break;
				}

				$path = substr($buffer, $p, $sep - $p);
				$path_base = $path_base_map[$path] ?? null;
				if ($path_base !== null) {
					$date_id = Parser::parseDateId($buffer, $sep + 1);
					if ($date_id !== null) {
						++$counts[$path_base + $date_id];
					}
				}
				$p = $sep + self::NEXT_PATH_OFFSET;
			}

			// safe chunk for the fallback's end
			while ($p < $chunk_len) {
				$sep = strpos($buffer, ',', $p);
				if ($sep === false || $sep >= $chunk_len) {
					break;
				}

				$path = substr($buffer, $p, $sep - $p);
				$path_base = $path_base_map[$path] ?? null;
				if ($path_base !== null) {
					$date_id = Parser::parseDateId($buffer, $sep + 1);
					if ($date_id !== null) {
						++$counts[$path_base + $date_id];
					}
				}

				$p = $sep + self::NEXT_PATH_OFFSET;
			}

			$tail = substr($buffer, $last_newline + 1);
		}

		if ($tail !== '') {
			$comma = strpos($tail, ',');
			if ($comma !== false && $comma > self::URI_PREFIX_LEN) {
				$path = substr($tail, self::URI_PREFIX_LEN, $comma - self::URI_PREFIX_LEN);
				$path_base = $path_base_map[$path] ?? null;

				if ($path_base !== null) {
					$date_id = Parser::parseDateId($tail, $comma + 1);
					if ($date_id !== null) {
						++$counts[$path_base + $date_id];
					}
				}
			}
		}

		fclose($input);
	}

	// parse the buffer and fetch the date id according to the offset given (year offset - days in years from 2021 to the given year, month offset - days in months from the start of the year to the given month, day offset - well... it adds the days)
	private static function parseDateId(string $buffer, int $offset): ?int {
		if (!isset(
			$buffer[$offset + 9],
			$buffer[$offset + 8],
			$buffer[$offset + 7],
			$buffer[$offset + 6],
			$buffer[$offset + 5],
			$buffer[$offset + 4],
			$buffer[$offset + 3],
			$buffer[$offset + 2],
			$buffer[$offset + 1],
			$buffer[$offset]
		)) {
			return null;
		}

		$year =
			((ord($buffer[$offset]) - 48) * 1000) +
			((ord($buffer[$offset + 1]) - 48) * 100) +
			((ord($buffer[$offset + 2]) - 48) * 10) +
			(ord($buffer[$offset + 3]) - 48);

		$month =
			((ord($buffer[$offset + 5]) - 48) * 10) +
			(ord($buffer[$offset + 6]) - 48);

		$day =
			((ord($buffer[$offset + 8]) - 48) * 10) +
			(ord($buffer[$offset + 9]) - 48);

		$year_offset = Parser::$year_offsets[$year] ?? null;
		if ($year_offset === null || $month < 1 || $month > 12 || $day < 1 || $day > 31) {
			return null;
		}

		$month_offsets = ($year === 2024) ? self::$month_offsets_leap : self::$month_offsets_common;

		return $year_offset + $month_offsets[$month - 1] + ($day - 1);
	}

	private static function writePrettyJson(array $counts, array $paths, array $date_list, int $date_count): void {
		$output = fopen(Parser::$output_path, 'w');

		fwrite($output, "{\n");

		$date_prefixes = [];
		for ($date_id = 0; $date_id < $date_count; ++$date_id) {
			$date_prefixes[$date_id] = "        \"" . $date_list[$date_id] . '": ';
		}

		$separator = '';

		foreach ($paths as $path_id => $path) {
			$base = $path_id * $date_count;

			$first_date_id = -1;
			for ($date_id = 0; $date_id < $date_count; ++$date_id) {
				if ($counts[$base + $date_id] !== 0) {
					$first_date_id = $date_id;
					break;
				}
			}

			if ($first_date_id === -1) {
				continue;
			}

			$buffer = $separator;
			$buffer .= "    " . json_encode($path, JSON_PRETTY_PRINT) . ": {\n";
			$buffer .= $date_prefixes[$first_date_id] . $counts[$base + $first_date_id];

			for ($date_id = $first_date_id + 1; $date_id < $date_count; ++$date_id) {
				$count = $counts[$base + $date_id];
				if ($count === 0) {
					continue;
				}

				$buffer .= ",\n";
				$buffer .= $date_prefixes[$date_id] . $count;
			}

			$buffer .= "\n    }";

			fwrite($output, $buffer);
			$separator = ",\n";
		}

		fwrite($output, "\n}");
		fclose($output);
	}
}