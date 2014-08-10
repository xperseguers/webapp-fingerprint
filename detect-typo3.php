<?php
class t3detect {

	const minTYPO3 = '3.6.1';
	const maxTYPO3 = '6.2.4';

	protected static $versionsTested = array();

	public static function getVersion($website, $ratio) {
		// Ensure a trailing slash is present
		$website = rtrim($website, '/') . '/';
		if (!self::isTYPO3($website)) {
			return '';
		}

		// Most obvious way if website is running a checkout of TYPO3
		$version = self::getSVNStatus($website);
		if ($version) return $version;
		// Try another way to guess
		$version = self::guessVersion($website, $ratio);
		return $version;
	}

	public static function isTYPO3($website) {
		// Ensure a trailing slash is present
		$website = rtrim($website, '/') . '/';
		return self::http_file_exists($website . 'typo3/index.html')
			|| self::http_file_exists($website . 'typo3temp/extensions.xml.gz')
			|| self::http_file_exists($website . 'typo3temp/sprites/zextensions.css');
	}

	protected static function guessVersion($website, $ratio) {
		require_once(dirname(__FILE__) . '/footprint/footprint.data.php');

		$keys = array_rand($footprint, floor($ratio / 100 * count($footprint)));
		$tmp = array();
		foreach ($keys as $key) $tmp[$key] = $footprint[$key];
		$footprint = $tmp;

		$guess = array();
		foreach ($footprint as $file => $data) {
			$info = self::getRevisionMd5($website . $file);
			foreach ($info as $key) {
				if ($key != -1 && isset($data[$key])) {
					foreach ($data[$key] as $version) {
						if (!isset($guess[$version])) {
							$guess[$version] = 0;
						}
						$guess[$version]++;
					}
				}
			}
		}

		// Aggregate version with occurence
		$aggregate = array();
		foreach ($guess as $version => $occurences) {
			if (!isset($aggregate[$occurences])) {
				$aggregate[$occurences] = array();
			}
			$aggregate[$occurences][] = $version;
		}

		// Sort by occurences (take the highest)
		krsort($aggregate);
		$versions = array_shift($aggregate);
		return implode(' / ', $versions);
	}

	public static function getChangelogHint($website) {
		// Ensure a trailing slash is present
		$website = rtrim($website, '/') . '/';
		$content = self::getFile($website . 'ChangeLog');
		$lines = explode("\n", $content);

		foreach ($lines as $line) {
			$matches = array();
			if (preg_match('/Release of TYPO3 ([0-9.]+)/', $line, $matches)) {
				return $matches[1];
			}
		}
		return '';
	}

	protected static function getRevisionMd5($url) {
		$content = self::getFile($url);
		if (!$content) {
			return array();
		}
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

	protected static function getSVNStatus($website) {
		$version = '';
		$entries = self::getFile($website . 'typo3/.svn/entries', 4096);
		if ($entries) {
			$lines = explode("\n", $entries);
			$repository = 'https://svn.typo3.org/TYPO3v4/Core/';
			if (self::isFirstPartOfStr($lines[4], $repository)) {
				$revision = $lines[3];
				$branch = substr($lines[4], strlen($repository));
				$parts = explode('/', $branch);
				switch ($parts[0]) {
					case 'trunk':
						$version = 'trunk (rev. ' . $revision . ')';
						break;
					case 'branches':
						$version = str_replace('-', '.', substr($parts[1], strlen('TYPO3_'))) . ' (rev. ' . $revision . ')';
						break;
					case 'tags':
						$version = str_replace('-', '.', substr($parts[1], strlen('TYPO3_')));
						break;
				}
			}
		}
		return $version;
	}

	protected static function http_file_exists($url) {
		$f = @fopen($url, 'r');
		if ($f) {
			fclose($f);
			return TRUE;
		}
		return FALSE;
	}

	protected static function getFile($url, $maxSize = 0) {
		$buffer = '';
		$f = @fopen($url, 'r');
		if ($f) {
			$f = @fopen($url, 'r');
			if ($f) {
				while (!feof($f)) {
					$buffer .= fread($f, 4096);
					if ($maxSize && strlen($buffer) >= $maxSize) {
						break;
					}
				}
				fclose($f);
			}
		}
		return $buffer;
	}

	protected static function isFirstPartOfStr($str, $partStr) {
		// Returns true, if the first part of a $str equals $partStr and $partStr is not ''
		$psLen = strlen($partStr);
		if ($psLen)	{
			return substr($str,0,$psLen)==(string)$partStr;
		} else return false;
	}
}

$website = trim($_GET['website']);
$ratio = isset($_GET['ratio']) ? min(10, max(1, intval($_GET['ratio']))) : 1;
if (!preg_match('#^(http://|https://)#', $website)) {
	$website = '';
}
$isTYPO3 = FALSE;
$version = '';

if ($website) {
	$isTYPO3 = t3detect::isTYPO3($website);
	if ($isTYPO3) {
		$version = t3detect::getVersion($website, $ratio * 10);
	}
} else {
	$website = 'http://';
}
?>
<html>
<body>

<h1>Website</h1>

<form method="get">
	<label for="website">Website:</label>
	<input type="text" id="website" name="website" size="50" value="<?php echo $website ?>"/><br />
	<label for="ratio">Ratio:</label>
	<select id="ratio" name="ratio">
<?php
	for ($i = 1; $i <= 10; $i++) {
		echo '<option value="' . $i . '"' . ($i == $ratio ? ' selected="selected"' : '') . '>' . ($i *10) . '%</option>';
	}
?>
	</select>
	<br />
	<input type="submit" value="get version" />
</form>

<h1>Version</h1>

<?php
if ($isTYPO3) {
		if ($version) {
			echo '<p>Website is running TYPO3 ' . $version . '</p>';
		} else {
			echo '<p>Unknown version of TYPO3</p>';
		}
		$hint = t3detect::getChangelogHint($website);
		if ($hint) {
			echo '<p>ChangeLog highest release: ' . $hint . '</p>';
		}
} else {
	echo '<p>Website is not running TYPO3</p>';
}
?>

</body>
</html>
