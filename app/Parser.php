<?php

namespace App;

final class Parser {
	private const int URI_PREFIX_LEN = 19;	// "https://stitcher.io";
	private const int DATE_LEN = 10;		// "2026-12-03"

	public function parse(string $input_path, string $output_path): void {
		$input = fopen($input_path, 'r');
		$data = [];
		while (false !== $visit = fgets($input)) {
			$comma = strpos($visit, ',');
			if ($comma === false) {
				continue;
			}

			$url = substr($visit, Parser::URI_PREFIX_LEN, $comma - Parser::URI_PREFIX_LEN);
			$date = substr($visit, $comma + 1, Parser::DATE_LEN);

			if (!isset($data[$url])) {
				$data[$url] = [];
			}
			if (!isset($data[$url][$date])) {
				$data[$url][$date] = 0;
			}
			$data[$url][$date]++;
		}
		fclose($input);
		file_put_contents($output_path, json_encode($data, JSON_PRETTY_PRINT));
	}
}