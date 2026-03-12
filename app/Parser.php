<?php

namespace App;

final class Parser {
	private const int URI_PREFIX_LEN = 19;	// "https://stitcher.io";
	private const int DATE_LEN = 10;		// "2026-12-03"
	private const string KEY_SEPARATOR = "\t";

	public function parse(string $input_path, string $output_path): void {
		$input = fopen($input_path, 'r');
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

		unset($counts);

		file_put_contents($output_path, json_encode($grouped_data, JSON_PRETTY_PRINT));
	}
}