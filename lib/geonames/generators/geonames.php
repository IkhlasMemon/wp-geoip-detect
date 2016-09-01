<?php
// Usage: $ php lib/geonames/generators/geonames.php api_username > lib/geonames/data/country-info.php
if (php_sapi_name() != "cli")
	die('This can only be run from command line.');

function output_to_stderr($text) {
	fwrite(STDERR, $text);
}

require_once(__DIR__ . '/vendor/autoload.php');

$username = @$argv[1]; // Get your free geonames.org - Account here: http://www.geonames.org/login


// List of languages that the Maxmind Database support
$langs = ['en', 'de', 'it', 'es', 'fr', 'ja', 'pt-BR', 'ru', 'zh-CN'];

$lang_geonames = array_combine($langs, $langs);
$lang_geonames['pt-BR'] = 'pt';
$lang_geonames['zh-CN'] = 'zh';
//$langs = ['en', 'de'];

$all_records = [];
output_to_stderr("Getting Country Information from geonames.org with API username " . $username . ":" . PHP_EOL);
foreach ($lang_geonames as $lang_maxmind => $lang_geonames) {
	// Load country information of all countries
	$client = new \spacedealer\geonames\api\Geonames($username, $lang_geonames);

	try {
		$records = [];
		$response = $client->countryInfo();

		if ($response->isOk()) {
			$count = $response->count();
			output_to_stderr("Lang " . $lang_geonames . ": Found countries: $count" . PHP_EOL);
			
			foreach ($response as $row) {
				$r = [];
				$id = $row['countryCode'];
				if (!$id)
					continue;
				
				// Country data
				$r['country']['iso_code'] = $id;
				if ($row['geonameId'])
					$r['country']['geoname_id'] = $row['geonameId'];
				if ($row['countryName'])
					$r['country']['names'][$lang_maxmind] = $row['countryName'];
				
				// Continent data
				if ($row['continent'])
					$r['continent']['code'] = $row['continent'];
				if ($row['continentName'])
					$r['continent']['names'][$lang_maxmind] = $row['continentName'];
				
				// Special country data
				if (isset($row['north']) && isset($row['south']))
					$r['location']['latitude'] = ($row['north'] + $row['south']) / 2.0;
				if (isset($row['west']) && isset($row['east']))
					$r['location']['longitude'] = ($row['west'] + $row['east']) / 2.0;
				
				$records[$id] = $r;
			}
		} else {
			output_to_stderr('Fehler: ' . $response['message'] . PHP_EOL);
		}
	} catch (\RuntimeException $e) {
		output_to_stderr('Fehler:' . $e->getMessage() . PHP_EOL);
	}
	
	// Merge the languages together
	$all_records = array_replace_recursive($all_records, $records);
}
output_to_stderr("Writing the results to the standard output...");
echo '<?php ' . PHP_EOL;
echo '// Generated at ' . date('r') . PHP_EOL;
echo 'return ';
var_export($all_records);
echo ';';
output_to_stderr("Done. You should now run phpunit to see if the file data is valid.");