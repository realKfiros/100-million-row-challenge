<?php

namespace App;

use App\Commands\Visit;

final class Parser {
	private const int URI_PREFIX_LEN = 19;	// "https://stitcher.io"
	private const int DATE_LEN = 10;		// "2026-12-03"
	private const int READ_CHUNK_SIZE = 1_048_576;

	private static string $input_path = '';
	private static string $output_path = '';
	private static ?array $known_path_set = null;
	private static ?array $date_registry = null;

	public function parse(string $input_path, string $output_path): void {
		[$date_id_map, $date_list] = Parser::buildDateRegistry();
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

		Parser::countVisits($path_base_map, $date_id_map, $counts);
		Parser::writePrettyJson($counts, $paths, $date_list, $date_count);
	}

	private static function getKnownPathSet(): array {
		if (Parser::$known_path_set !== null) {
			return Parser::$known_path_set;
		}

		$known_path_set = [];

		foreach (Visit::all() as $visit) {
			$path = substr($visit->uri, Parser::URI_PREFIX_LEN);
			$known_path_set[$path] = true;
		}

		return Parser::$known_path_set = $known_path_set;
	}

	private static function discoverPaths(): array {
		$input = fopen(Parser::$input_path, 'r');

		$known_path_set = Parser::getKnownPathSet();
		$paths = [];
		$seen = [];

		while (($line = fgets($input)) !== false) {
			$comma = strpos($line, ',');
			if ($comma === false || $comma <= Parser::URI_PREFIX_LEN) {
				continue;
			}

			$path = substr($line, Parser::URI_PREFIX_LEN, $comma - Parser::URI_PREFIX_LEN);

			if (!isset($known_path_set[$path]) || isset($seen[$path])) {
				continue;
			}

			$seen[$path] = true;
			$paths[] = $path;
		}

		fclose($input);

		return $paths;
	}

	private static function buildDateRegistry(): array {
		if (Parser::$date_registry !== null) {
			return Parser::$date_registry;
		}

		$date_id_map = [];
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
					$date =
						$year . '-' .
						($month < 10 ? '0' : '') . $month . '-' .
						($day < 10 ? '0' : '') . $day;

					$date_id_map[$date] = $date_id;
					$date_list[$date_id] = $date;
					++$date_id;
				}
			}
		}

		return Parser::$date_registry = [$date_id_map, $date_list];
	}

	private static function isLeapYear(int $year): bool {
		return ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
	}

	private static function countVisits(array $path_base_map, array $date_id_map, array &$counts): void {
		$input = fopen(Parser::$input_path, 'r');

		$tail = '';

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

			$chunk = substr($buffer, 0, $last_newline + 1);
			$tail = substr($buffer, $last_newline + 1);

			Parser::countChunk($chunk, $path_base_map, $date_id_map, $counts);
		}

		if ($tail !== '') {
			Parser::countLine($tail, $path_base_map, $date_id_map, $counts);
		}

		fclose($input);
	}

	private static function countChunk(string $chunk, array $path_base_map, array $date_id_map, array &$counts): void {
		$offset = 0;
		$chunk_len = strlen($chunk);

		while ($offset < $chunk_len) {
			$eol = strpos($chunk, "\n", $offset);
			if ($eol === false) {
				break;
			}

			if ($eol > $offset) {
				Parser::countLineSlice($chunk, $offset, $eol, $path_base_map, $date_id_map, $counts);
			}

			$offset = $eol + 1;
		}
	}

	private static function countLine(string $line, array $path_base_map, array $date_id_map, array &$counts): void {
		$line_len = strlen($line);
		if ($line_len === 0) {
			return;
		}

		if ($line[$line_len - 1] === "\n") {
			--$line_len;
			if ($line_len > 0 && $line[$line_len - 1] === "\r") {
				--$line_len;
			}
		}

		if ($line_len === 0) {
			return;
		}

		$comma = strpos($line, ',');
		if ($comma === false || $comma <= Parser::URI_PREFIX_LEN) {
			return;
		}

		$path = substr($line, Parser::URI_PREFIX_LEN, $comma - Parser::URI_PREFIX_LEN);
		$date = substr($line, $comma + 1, Parser::DATE_LEN);

		$path_base = $path_base_map[$path] ?? null;
		$date_id = $date_id_map[$date] ?? null;

		if ($path_base === null || $date_id === null) {
			return;
		}

		++$counts[$path_base + $date_id];
	}

	private static function countLineSlice(string $chunk, int $line_offset, int $eol, array $path_base_map, array $date_id_map, array &$counts): void {
		$comma = strpos($chunk, ',', $line_offset);
		if ($comma === false || $comma >= $eol || $comma <= $line_offset + Parser::URI_PREFIX_LEN) {
			return;
		}

		$path_start = $line_offset + Parser::URI_PREFIX_LEN;
		$path = substr($chunk, $path_start, $comma - $path_start);
		$date = substr($chunk, $comma + 1, Parser::DATE_LEN);

		$path_base = $path_base_map[$path] ?? null;
		$date_id = $date_id_map[$date] ?? null;

		if ($path_base === null || $date_id === null) {
			return;
		}

		++$counts[$path_base + $date_id];
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