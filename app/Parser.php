<?php

namespace App;

final class Parser {
	private const int URI_PREFIX_LEN = 19;	// "https://stitcher.io";
	private const int DATE_LEN = 10;		// "2026-12-03"
	private const string KEY_SEPARATOR = "\t";

	private static string $input_path = '';
	private static string $output_path = '';
	private static ?array $date_to_id = null;
	private static ?array $id_to_date = null;

	public function parse(string $input_path, string $output_path): void {
		Parser::initDateRegistry();

		Parser::$input_path = $input_path;
		Parser::$output_path = $output_path;

		$counts = Parser::countVisits();

		file_put_contents(Parser::$output_path, json_encode($counts, JSON_PRETTY_PRINT));

		unset($counts);
	}

	private static function initDateRegistry(): void {
		if (self::$date_to_id !== null && self::$id_to_date !== null) {
			return;
		}

		$date_to_id = [];
		$id_to_date = [];
		$id = 0;

		for ($year = 2021; $year <= 2026; ++$year) {
			for ($month = 1; $month <= 12; ++$month) {
				$max_day = match ($month) {
					2 => self::isLeapYear($year) ? 29 : 28,
					4, 6, 9, 11 => 30,
					default => 31,
				};

				for ($day = 1; $day <= $max_day; ++$day) {
					$date =
						$year . '-' .
						($month < 10 ? '0' : '') . $month . '-' .
						($day < 10 ? '0' : '') . $day;

					$date_to_id[$date] = $id;
					$id_to_date[$id] = $date;
					++$id;
				}
			}
		}

		self::$date_to_id = $date_to_id;
		self::$id_to_date = $id_to_date;
	}

	private static function isLeapYear(int $year): bool {
		return ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
	}

	private static function countVisits(): array {
		$input = fopen(Parser::$input_path, 'r');
		$data = [];
		while (false !== $visit = fgets($input)) {
			$comma = strpos($visit, ',');
			if ($comma === false) {
				continue;
			}

			$url = substr($visit, Parser::URI_PREFIX_LEN, $comma - Parser::URI_PREFIX_LEN);
			$date = substr($visit, $comma + 1, Parser::DATE_LEN);

			$day_id = self::$date_to_id[$date] ?? null;
			if ($day_id === null) {
				continue;
			}

			if (isset($data[$url][$day_id])) {
				++$data[$url][$day_id];
			} else {
				$data[$url][$day_id] = 1;
			}
		}

		fclose($input);

		foreach ($data as &$days) {
			ksort($days, SORT_NUMERIC);
		}

		unset($days);

		return $data;
	}
}