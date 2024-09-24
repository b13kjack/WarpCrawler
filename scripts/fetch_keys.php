<?php
declare(strict_types=1);

/**
 * Fetch and process keys from specified Telegram channels.
 *
 * This script retrieves content from predefined Telegram channel URLs,
 * extracts keys using a regex pattern, and writes them into 'full' and 'lite'
 * files within the 'plus' directory.
 */

// Configuration Constants
const SOURCE_URLS = [
    'https://t.me/s/warpplus',
    'https://t.me/s/warppluscn',
    'https://t.me/s/warpPlusHome',
    'https://t.me/s/warp_veyke',
];

const FULL_KEYS_LIMIT = 100;
const LITE_KEYS_LIMIT = 15;
const REGEX_PATTERN = '/<code>([A-Za-z0-9-]+)<\/code>/';

// Define paths relative to the script's directory
const OUTPUT_DIR = __DIR__ . '/../plus';
const FULL_FILE_PATH = OUTPUT_DIR . '/full';
const LITE_FILE_PATH = OUTPUT_DIR . '/lite';

/**
 * Ensures that the output directory exists.
 *
 * @param string $dirPath The path to the directory.
 * @return void
 */
function ensureOutputDirectory(string $dirPath): void
{
    if (!is_dir($dirPath)) {
        echo "Output directory does not exist. Creating: {$dirPath}\n";
        if (!mkdir($dirPath, 0755, true) && !is_dir($dirPath)) {
            error_log("Failed to create directory: {$dirPath}");
            exit(1);
        }
        echo "Output directory created successfully.\n";
    } else {
        echo "Output directory exists: {$dirPath}\n";
    }
}

/**
 * Fetches the content from a given URL using cURL with error handling.
 *
 * @param string $url The URL to fetch.
 * @return string|false The fetched content or false on failure.
 */
function fetchContent(string $url)
{
    echo "Fetching content from URL: {$url}\n";
    $ch = curl_init($url);
    if ($ch === false) {
        error_log("cURL initialization failed for URL: {$url}");
        return false;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15, // Increased timeout for reliability
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'PHP Script/1.0',
    ]);

    $data = curl_exec($ch);
    if ($data === false) {
        error_log("cURL error for URL {$url}: " . curl_error($ch));
    } else {
        echo "Successfully fetched content from {$url}\n";
    }

    curl_close($ch);
    return $data;
}

/**
 * Extracts keys from the given content using the specified regex pattern.
 *
 * @param string $content The content to search.
 * @param string $pattern The regex pattern.
 * @return array An array of matched keys.
 */
function extractKeys(string $content, string $pattern): array
{
    if (preg_match_all($pattern, $content, $matches)) {
        $count = count($matches[1]);
        echo "Extracted {$count} keys using regex pattern.\n";
        return $matches[1];
    }
    echo "No keys found using regex pattern.\n";
    return [];
}

/**
 * Writes an array of keys to a file, limited by the specified maximum count.
 *
 * @param array  $keys      The array of keys.
 * @param string $filePath  The file path to write to.
 * @param int    $maxCount  The maximum number of keys to write.
 * @param bool   $shuffle   Whether to shuffle the keys before selecting.
 * @return void
 */
function writeKeysToFile(array $keys, string $filePath, int $maxCount, bool $shuffle = false): void
{
    if ($shuffle) {
        shuffle($keys);
        echo "Keys shuffled for 'lite' file.\n";
    }

    $selectedKeys = array_slice($keys, 0, $maxCount);
    $count = count($selectedKeys);
    echo "Writing {$count} keys to file: {$filePath}\n";
    $content = implode(PHP_EOL, $selectedKeys) . PHP_EOL;

    if (file_put_contents($filePath, $content) === false) {
        error_log("Failed to write to file: {$filePath}");
    } else {
        echo "Successfully wrote to file: {$filePath}\n";
    }
}

/**
 * Main execution function.
 *
 * @return void
 */
function main(): void
{
    echo "Starting key fetching process.\n";

    // Ensure the output directory exists
    ensureOutputDirectory(OUTPUT_DIR);

    $allKeys = [];

    foreach (SOURCE_URLS as $url) {
        $content = fetchContent($url);
        if ($content === false) {
            // Log and skip to the next URL on failure
            error_log("Skipping URL due to fetch failure: {$url}");
            continue;
        }

        $extractedKeys = extractKeys($content, REGEX_PATTERN);
        if (!empty($extractedKeys)) {
            $allKeys = array_merge($allKeys, $extractedKeys);
            echo "Merged " . count($extractedKeys) . " keys from {$url}\n";
        } else {
            echo "No keys extracted from {$url}\n";
        }
    }

    $uniqueKeys = array_unique($allKeys);
    $totalUnique = count($uniqueKeys);
    echo "Total unique keys extracted: {$totalUnique}\n";

    if ($totalUnique === 0) {
        error_log("No unique keys found across all URLs.");
        exit(0);
    }

    // Prepare and write the 'full' keys file
    $fullKeys = array_slice($uniqueKeys, 0, FULL_KEYS_LIMIT);
    writeKeysToFile($fullKeys, FULL_FILE_PATH, FULL_KEYS_LIMIT);

    // Prepare and write the 'lite' keys file
    writeKeysToFile($uniqueKeys, LITE_FILE_PATH, LITE_KEYS_LIMIT, true);

    echo "Keys have been successfully processed and saved.\n";
}

// Execute the main function
main();
?>
