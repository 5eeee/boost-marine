<?php
require_once __DIR__ . '/../config.php';
requireAuth();
// Temporary diagnostic - DELETE AFTER USE
header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$autoload = ADMIN_ROOT . '/vendor/autoload.php';
require_once $autoload;

$creds = ADMIN_ROOT . '/credentials.json';
$client = new \Google\Client();
$client->setApplicationName('Boost Marine Admin');
$client->setScopes(['https://www.googleapis.com/auth/spreadsheets', 'https://www.googleapis.com/auth/drive.file']);
$client->setAuthConfig($creds);

$spreadsheetId = '1B6GURoeE_HaL9ZkZ-kkNpx1dSngaFiFWQZiVk9-9C-c';

echo "Testing write to shared spreadsheet...\n";
try {
    $service = new \Google\Service\Sheets($client);
    
    // Clear existing data
    $service->spreadsheets_values->clear($spreadsheetId, 'A1:Z1000', new \Google\Service\Sheets\ClearValuesRequest());
    echo "Cleared OK\n";
    
    // Write test data
    $values = [['Test', 'Data', 'From', 'Diag'], ['Row2', '123', '456', '789']];
    $body = new \Google\Service\Sheets\ValueRange(['values' => $values]);
    $result = $service->spreadsheets_values->update($spreadsheetId, 'A1', $body, ['valueInputOption' => 'RAW']);
    echo "Written " . $result->getUpdatedCells() . " cells\n";
    echo "SUCCESS! Check: https://docs.google.com/spreadsheets/d/$spreadsheetId\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
