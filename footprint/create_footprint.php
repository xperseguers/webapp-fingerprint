<?php
const JSON_URL = 'http://get.typo3.org/json';
const GIT_REPOSITORY = 'git://git.typo3.org/Packages/TYPO3.CMS.git';
const TEMP_DIRECTORY = '/tmp/typo3-sources';

$json = file_get_contents(JSON_URL);
$data = json_decode($json, TRUE);

$releases = array();
foreach ($data as $branch => $info) {
	if (!is_array($info['releases'])) continue;
	foreach ($info['releases'] as $release => $_) {
		$releases[] = $release;
	}
}

// Ensure we start with older versions first
sort($releases);

echo 'Cloning TYPO3 Git repository into ' . TEMP_DIRECTORY . "\n";
exec('rm -rf ' . TEMP_DIRECTORY);
mkdir(TEMP_DIRECTORY);
exec('cd ' . TEMP_DIRECTORY . '; git clone ' . GIT_REPOSITORY . ' git-repo');
chdir(TEMP_DIRECTORY . '/git-repo');

$tempFiles = TEMP_DIRECTORY . '/files.txt';
$files = array();
$footprint = array();

foreach ($releases as $release) {
	if (!preg_match('/^\\d+\\.\\d+\\.\\d+$/', $release)) {
		echo 'Skipping intermediate TYPO3 version ' . $release . "\n";
		continue;
	}

	$output = array();
	$ret = 0;
	exec('git checkout TYPO3_' . str_replace('.', '-', $release), $output, $ret);
	if ($ret != 0) continue;

	echo 'Looking for additional interesting files for TYPO3 version ' . $release . "\n";
	$command = <<<BASH
rm -f $tempFiles && touch $tempFiles
for EXT in js sql inc txt css yaml rst csv; do
	find . -type f -iname "*.\$EXT" | grep -v "Tests/" | cut -b3- >> $tempFiles
done
BASH;
	exec($command);
	$allFiles = explode("\n", trim(file_get_contents($tempFiles)));
	$newFiles = array_diff($allFiles, $files);
	if (count($newFiles) > 0) {
		echo 'New files found: '; print_r($newFiles); echo "\n";
	}
	$files = array_merge($files, $newFiles);

	echo 'Computing checksums for TYPO3 version ' . $release . ' ... ';
	foreach ($files as $file) {
		$footprint[$file] = array();
		foreach ($releases as $release) {
			$data = getRevisionMd5($base_dir . $version . '/' . $file);
			foreach ($data as $key) {
				if ($key != -1) {
					if (!isset($footprint[$file][$key])) $footprint[$file][$key] = array();
					$footprint[$file][$key][] = $version;
				}
			}
		}
	}
	echo "done.\n";
}

file_put_contents(dirname(__FILE__) . '/footprint.data.json', json_encode($footprint));

function getRevisionMd5($file) {
	if (!is_file($file)) {
		return array();
	}
	$content = file_get_contents($file);
	$lines = explode("\n", $content);

	foreach ($lines as $line) {
		$matches = array();
		if (preg_match('/\$Id: [^ ]+ ([0-9]+) /', $line, $matches)) {
			return array($matches[1], md5($content));
		}
	}
		// File exists but $Id$ line not found
	return array('-1', md5($content));
}
