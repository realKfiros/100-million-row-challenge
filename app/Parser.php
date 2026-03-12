<?php

namespace App;

final class Parser {
	private const int URI_PREFIX_LEN = 19;	// "https://stitcher.io";
	private const int DATE_LEN = 10;		// "2026-12-03"
	private const string KEY_SEPARATOR = "\t";

	private static string $input_path = '';
	private static string $output_path = '';

	public function parse(string $input_path, string $output_path): void {
		Parser::$input_path = $input_path;
		Parser::$output_path = $output_path;

		$counts = Parser::countVisits();

		$grouped_data = Parser::groupCounts($counts);
		unset($counts);

		Parser::writeJson($grouped_data, $output_path);
	}

	private static function countVisits(): array {
		$input = fopen(Parser::$input_path, 'r');
		$counts = [];
		while (false !== $visit = fgets($input)) {
			$comma = strpos($visit, ',');
			if ($comma === false) {
				continue;
			}

			$url = substr($visit, Parser::URI_PREFIX_LEN, $comma - Parser::URI_PREFIX_LEN);
			$date = substr($visit, $comma + 1, Parser::DATE_LEN);
			$key = $url . Parser::KEY_SEPARATOR . $date;
			if (isset($counts[$key])) {
				++$counts[$key];
			} else {
				$counts[$key] = 1;
			}
		}
		fclose($input);
		return $counts;
	}

	private static function groupCounts(array $counts): array {
		$grouped_data = [];
		foreach ($counts as $key => $count) {
			$separator = strrpos($key, Parser::KEY_SEPARATOR);
			$url = substr($key, 0, $separator);
			$date = substr($key, $separator + 1);
			if (!isset($grouped_data[$url])) {
				$grouped_data[$url] = [];
			}
			$grouped_data[$url][$date] = $count;
		}
		return $grouped_data;
	}

	private static function writeJson(array $grouped_data): void {
		$output = fopen(Parser::$output_path, 'w');
		fwrite($output, "{\n\t");
		$first_url = true;
		foreach ($grouped_data as $url => $dates) {
			if (!$first_url) {
				fwrite($output, ",\n\t");
			}
			$first_url = false;
			fwrite($output, json_encode((string) $url, JSON_PRETTY_PRINT));
			fwrite($output, ": {\n\t\t");
			$first_date = true;
			foreach ($dates as $date => $count) {
				if (!$first_date) {
					fwrite($output, ",\n\t\t");
				}
				$first_date = false;
				fwrite($output, json_encode((string) $date));
				fwrite($output, ": ");
				fwrite($output, json_encode((int) $count));
			}
			fwrite($output, "\n\t}");
		}
		fwrite($output, "\n}");
		fclose($output);
	}
}