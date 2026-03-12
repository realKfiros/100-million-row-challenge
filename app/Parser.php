<?php

namespace App;

final class Parser {
	public function parse(string $input_path, string $output_path): void {
		$input = fopen($input_path, 'r');
		$data = [];
		while (false !== $visit = fgetcsv($input, escape: '\\')) {
			[$url, $date] = $visit;
			$url = substr($url, 19);
			$date = substr($date, 0, 10);
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