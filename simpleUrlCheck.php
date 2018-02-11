<?php

// Preload config data
define("CONFIG_FILENAME",'config.json');

if (!is_file(CONFIG_FILENAME))
    die("Can't locate config file.\n");

$config = fopen(CONFIG_FILENAME,'r');
define("CONFIG", json_decode(fread($config, filesize(CONFIG_FILENAME)), true) );

// Check for CLI usage
if (php_sapi_name() != CONFIG['allowedRuntimeEnv'])
    die("Only CLI use allowed. For more information, check the documentation: ".CONFIG['repository']['url']."\n");

if (!function_exists('curl_version')) {
    die("Error: No cURL installed on your system.\n");
}

// Check arguments
ini_set('register_argc_argv', 0);

if ($argc < 2)
    die("Not enough arguments.\nUsage: php simpleUrlCheck.php <filename>\n");

define("INPUT_FILENAME", $argv[1]);

if (!is_file(INPUT_FILENAME))
    die("Error: input file not exists.\n");

if (!in_array(substr(INPUT_FILENAME, -3), CONFIG['allowedFileExtensions']))
    die("Error: invalid file extension. Only these types allowed: ".implode(", ",CONFIG['allowedFileExtensions'])."\n");

// Find the column that contains URLs, then exit at the first match
$inputFileStream = fopen(INPUT_FILENAME, 'r');
$patternToFind = '/^(http|https):\/\//m';
$foundUrls = [];
$urlIndex = 0;

while ($stream = fgetcsv($inputFileStream,0,",")) {
    for ($i = 0; $i < count($stream); $i++) {
        if (preg_match($patternToFind, $stream[$i])) {
            $urlIndex = $i;
            break;
        }
    }
}

// Seek back to the beginning of the file
fseek($inputFileStream, 0);

// Load the URLs to an array
while ($stream = fgetcsv($inputFileStream,0,",")) {
    if (preg_match($patternToFind, $stream[$urlIndex]))
        array_push($foundUrls, $stream[$urlIndex]);
}

fclose($inputFileStream);

$output = "<table>
<tr style='background-color:lightgrey;'>
  <th>Status CODE</th>
  <th>Status TEXT</th>
  <th>Checked URL</th>
  <th>New URL (if any)</th>
</tr>";

foreach ($foundUrls as $url) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_HEADER, TRUE);
    curl_setopt($ch, CURLOPT_NOBODY, TRUE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    curl_setopt($ch, CURLOPT_URL, $url);

    $head = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode >= 300 && $httpCode < 400)
    {
        $newUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);
        $output .= "<tr style='background-color:yellow;'>
          <td>".$httpCode."</td>
          <td>REDIRECTED</td>
          <td>".$url."</td>
          <td>".$newUrl."</td></tr>";
    }
    else if ($httpCode >= 400 && $httpCode < 500)
    {
        $output .= "<tr style='background-color:red;'>
            <td>".$httpCode."</td>
            <td> NOT FOUND </td>
            <td>".$url."</td>
            <td></td>
          </tr>";
    }
    else if ($httpCode >= 500 && $httpCode < 600)
    {
        $output .= "<tr style='background-color:red;'>
        <td>".$httpCode."</td>
        <td> SERVER ERROR </td>
        <td>".$url."</td>
        <td></td>
      </tr>";
    }
    else if ($httpCode == 200)
    {
        $output .= "<tr style='background-color:lightgreen;'>
            <td>".$httpCode."</td>
            <td> OK </td>
            <td>".$url."</td>
            <td></td>
          </tr>";
    }
}

$resultFileName = CONFIG['resultFileName'] . '_' . date('YmdHis') . '.' .CONFIG['resultFileExtension'];

$resultFile = fopen($resultFileName, 'w');
fwrite($resultFile,$output);
fclose($resultFile);

?>