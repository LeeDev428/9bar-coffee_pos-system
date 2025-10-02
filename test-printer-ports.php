<?php
/**
 * Simple Printer Port Test
 * Tests different ways to connect to your thermal printer
 */

echo "<h2>🔧 Printer Port Connection Test</h2>";

// Test different connection methods
$testMethods = [
    'PRN' => 'Default printer port',
    'LPT1' => 'Parallel port 1', 
    'COM1' => 'Serial port 1',
    'COM3' => 'Serial port 3',
    '\\\\.\\Generic / Text Only' => 'UNC path to printer',
    '\\\\localhost\\Generic / Text Only' => 'Network path to printer'
];

foreach ($testMethods as $port => $description) {
    echo "<h3>Testing: $description ($port)</h3>";
    
    try {
        $handle = @fopen($port, "wb");
        if ($handle) {
            echo "✅ Successfully opened connection to $port<br>";
            
            // Try to send some test data
            $testData = "\x1B@"; // ESC @ - Initialize printer
            $testData .= "TEST PRINT FROM PHP\n";
            $testData .= "Port: $port\n";
            $testData .= "Time: " . date('Y-m-d H:i:s') . "\n";
            $testData .= "\n\n\n"; // Feed some lines
            
            $bytesWritten = fwrite($handle, $testData);
            fflush($handle);
            fclose($handle);
            
            echo "✅ Sent $bytesWritten bytes to printer<br>";
            echo "<strong>👀 Check your printer - it should have printed!</strong><br>";
        } else {
            echo "❌ Could not open connection to $port<br>";
        }
    } catch (Exception $e) {
        echo "❌ Error with $port: " . $e->getMessage() . "<br>";
    }
    
    echo "<br>";
}

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>💡 What This Test Does:</h3>";
echo "<ul>";
echo "<li>Tries to open different printer ports/connections</li>";
echo "<li>Sends raw ESC/POS commands directly to the printer</li>";
echo "<li>If successful, your printer should print test text</li>";
echo "</ul>";
echo "<p><strong>If any test shows '✅ Successfully opened' and you see a print on your thermal printer, that's the connection method we should use!</strong></p>";
echo "</div>";

// Also test if printer_open function exists
echo "<h3>PHP Printer Extension Check:</h3>";
if (function_exists('printer_open')) {
    echo "✅ PHP printer extension is available<br>";
    
    // Try to list printers
    if (function_exists('printer_list')) {
        $printers = printer_list(PRINTER_ENUM_LOCAL);
        echo "Available printers:<br>";
        foreach ($printers as $printer) {
            echo "- $printer<br>";
        }
    }
} else {
    echo "❌ PHP printer extension not available - using file operations<br>";
}
?>