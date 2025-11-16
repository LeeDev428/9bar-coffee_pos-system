<?php
/**
 * Thermal Printer Class for ESC/POS Compatible Printers
 * Supports USB, Network, and Bluetooth thermal printers
 */
class ThermalPrinter {
    private $connector;
    private $printer;
    private $characterSet = 'CP437'; // Default character set for most thermal printers
    
    // ESC/POS Commands
    const ESC = "\x1B";
    const GS = "\x1D";
    const CR = "\x0D";
    const LF = "\x0A";
    const FF = "\x0C";
    
    // Alignment
    const ALIGN_LEFT = 0;
    const ALIGN_CENTER = 1;
    const ALIGN_RIGHT = 2;
    
    // Text Size
    const SIZE_NORMAL = 0;
    const SIZE_DOUBLE_HEIGHT = 1;
    const SIZE_DOUBLE_WIDTH = 2;
    const SIZE_DOUBLE = 3;
    
    /**
     * Constructor
     */
    public function __construct($printerType = 'windows', $connectionString = '') {
        switch($printerType) {
            case 'network':
                $this->initNetworkPrinter($connectionString);
                break;
            case 'usb':
                $this->initUSBPrinter($connectionString);
                break;
            case 'windows':
            default:
                $this->initWindowsPrinter($connectionString);
                break;
        }
    }
    
    /**
     * Initialize Windows printer (using printer name)
     */
    private function initWindowsPrinter($printerName = '') {
        if (empty($printerName)) {
            $printerName = $this->getDefaultPrinter();
        }
        
        if (function_exists('printer_open')) {
            $this->printer = printer_open($printerName);
            if (!$this->printer) {
                throw new Exception("Cannot connect to printer: $printerName");
            }
        } else {
            // Try multiple connection methods for Windows printers
            $connectionAttempts = [
                "\\\\localhost\\$printerName",
                "\\\\127.0.0.1\\$printerName", 
                "//.//$printerName",
                $printerName
            ];
            
            $connected = false;
            $lastError = '';
            
            foreach ($connectionAttempts as $attempt) {
                // Clear any previous errors
                error_clear_last();
                
                // Try to open the printer with a timeout-friendly approach
                $this->connector = @fopen($attempt, "wb");
                
                if ($this->connector && is_resource($this->connector)) {
                    $connected = true;
                    
                    // Keep blocking mode to ensure data is actually sent
                    // Set a reasonable timeout for Windows printers
                    stream_set_timeout($this->connector, 10);
                    
                    break;
                }
                
                $error = error_get_last();
                if ($error) {
                    $lastError = $error['message'];
                }
            }
            
            if (!$connected) {
                // If all connection methods fail, create a dummy connection to continue processing
                // This allows sales to complete even if printing fails
                error_log("WARNING: Printer connection failed for '$printerName' - Last error: $lastError");
                $this->connector = tmpfile(); // Temporary file that gets discarded
            }
        }
    }
    
    /**
     * Initialize Network printer (IP:Port)
     */
    private function initNetworkPrinter($ipPort) {
        list($ip, $port) = explode(':', $ipPort);
        $port = $port ?: 9100; // Default ESC/POS port
        
        $this->connector = fsockopen($ip, $port, $errno, $errstr, 10);
        if (!$this->connector) {
            throw new Exception("Network printer connection failed: $errstr ($errno)");
        }
    }
    
    /**
     * Initialize USB printer (COM port or device path)
     */
    private function initUSBPrinter($devicePath) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows COM port
            $this->connector = fopen($devicePath, "w");
        } else {
            // Linux/Unix device path
            $this->connector = fopen($devicePath, "w");
        }
        
        if (!$this->connector) {
            throw new Exception("USB printer connection failed: $devicePath");
        }
    }
    
    /**
     * Get default Windows printer
     */
    private function getDefaultPrinter() {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $output = shell_exec('wmic printer where default=true get name /value 2>nul');
            if ($output && preg_match('/Name=(.+)/', $output, $matches)) {
                return trim($matches[1]);
            }
            
            // Try alternative method to find Xprinter
            $output = shell_exec('wmic printer get name /value 2>nul');
            if ($output && preg_match('/Name=.*[Xx]printer.*/', $output, $matches)) {
                return trim(str_replace('Name=', '', $matches[0]));
            }
        }
        return 'Generic / Text Only'; // Fallback
    }
    
    /**
     * Send raw data to printer
     */
    private function write($data) {
        try {
            if ($this->printer && function_exists('printer_write')) {
                printer_write($this->printer, $data);
            } elseif ($this->connector && is_resource($this->connector)) {
                // Write data with error checking
                $written = fwrite($this->connector, $data);
                
                if ($written === false) {
                    error_log("Failed to write to printer");
                    return false;
                }
                
                // Force flush to ensure data is sent immediately
                fflush($this->connector);
                
                // Small delay to prevent overwhelming the printer buffer
                usleep(1000); // 1ms delay between writes
            }
            return true;
        } catch (Exception $e) {
            error_log("Printer write error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Initialize printer
     */
    public function initialize() {
        $this->write(self::ESC . "@"); // Initialize printer
        return $this;
    }
    
    /**
     * Set text alignment
     */
    public function setAlign($align = self::ALIGN_LEFT) {
        $this->write(self::ESC . "a" . chr($align));
        return $this;
    }
    
    /**
     * Set text size
     */
    public function setTextSize($size = self::SIZE_NORMAL) {
        $this->write(self::GS . "!" . chr($size));
        return $this;
    }
    
    /**
     * Set bold text
     */
    public function setBold($bold = true) {
        $this->write(self::ESC . "E" . ($bold ? chr(1) : chr(0)));
        return $this;
    }
    
    /**
     * Set underline
     */
    public function setUnderline($underline = true) {
        $this->write(self::ESC . "-" . ($underline ? chr(1) : chr(0)));
        return $this;
    }
    
    /**
     * Print text
     */
    public function text($text = "") {
        $text = iconv('UTF-8', $this->characterSet.'//IGNORE', $text);
        $this->write($text);
        return $this;
    }
    
    /**
     * Print line of text
     */
    public function textln($text = "") {
        return $this->text($text . self::LF);
    }
    
    /**
     * Feed lines
     */
    public function feed($lines = 1) {
        $this->write(str_repeat(self::LF, $lines));
        return $this;
    }
    
    /**
     * Cut paper
     */
    public function cut($lines = 3) {
        $this->feed($lines);
        $this->write(self::GS . "V" . chr(0)); // Full cut
        return $this;
    }
    
    /**
     * Print horizontal line
     */
    public function line($char = "-", $length = 32) {
        $this->textln(str_repeat($char, $length));
        return $this;
    }
    
    /**
     * Print two-column text (left and right aligned)
     */
    public function columns($left, $right, $width = 32) {
        $leftLen = strlen($left);
        $rightLen = strlen($right);
        $spaces = $width - $leftLen - $rightLen;
        
        if ($spaces < 1) {
            $this->textln($left);
            $this->setAlign(self::ALIGN_RIGHT)->textln($right)->setAlign(self::ALIGN_LEFT);
        } else {
            $this->textln($left . str_repeat(' ', $spaces) . $right);
        }
        return $this;
    }
    
    /**
     * Open cash drawer (if connected)
     */
    public function openDrawer() {
        $this->write(self::ESC . "p" . chr(0) . chr(50) . chr(250)); // Standard cash drawer command
        return $this;
    }
    
    /**
     * Print QR Code (if supported)
     */
    public function qrCode($text, $size = 3) {
        // ESC/POS QR Code commands
        $this->write(self::GS . "(k" . chr(4) . chr(0) . chr(49) . chr(65) . chr(50) . chr(0)); // Model
        $this->write(self::GS . "(k" . chr(3) . chr(0) . chr(49) . chr(67) . chr($size)); // Size
        $this->write(self::GS . "(k" . chr(3) . chr(0) . chr(49) . chr(69) . chr(48)); // Error correction
        
        $len = strlen($text);
        $this->write(self::GS . "(k" . chr($len + 3) . chr(0) . chr(49) . chr(80) . chr(48) . $text); // Store data
        $this->write(self::GS . "(k" . chr(3) . chr(0) . chr(49) . chr(81) . chr(48)); // Print
        return $this;
    }
    
    /**
     * Close connection
     */
    public function close() {
        try {
            if ($this->printer && function_exists('printer_close')) {
                printer_close($this->printer);
                $this->printer = null;
            } elseif ($this->connector) {
                // Ensure all data is written before closing
                if (is_resource($this->connector)) {
                    fflush($this->connector);
                    fclose($this->connector);
                }
                $this->connector = null;
            }
            
            // Delay to allow Windows to fully release the printer resource
            // Critical for Windows USB thermal printers on immediate reprints
            usleep(500000); // 500ms delay
        } catch (Exception $e) {
            error_log("Error closing printer connection: " . $e->getMessage());
        }
    }
    
    /**
     * Print receipt - Matches Modal Format Exactly
     */
    public function printReceipt($receiptData) {
        try {
            $this->initialize();
            
            // ========== HEADER SECTION (centered) ==========
            $this->feed(1)
                 ->setAlign(self::ALIGN_CENTER)
                 ->line('-', 32)
                 ->setTextSize(self::SIZE_DOUBLE)
                 ->setBold(true)
                 ->textln($receiptData['business_name'])
                 ->setTextSize(self::SIZE_NORMAL)
                 ->setBold(false)
                 ->textln($receiptData['business_address'])
                 ->textln($receiptData['business_phone'])
                 ->line('-', 32)
                 ->feed(1);
            
            // ========== TRANSACTION INFO (left aligned) ==========
            $this->setAlign(self::ALIGN_LEFT);
            
            // Sale # and Date/Time on same line
            $dateTime = date('n/j/Y, g:i:s A');
            $this->columns('Sale #: ' . $receiptData['sale_id'], $dateTime);
            
            // Cashier
            // $this->textln('Cashier: ' . substr($receiptData['cashier'], 0, 24));
            
            // Customer
            $this->textln('Customer: ' . substr($receiptData['customer_name'] ?: 'Walk-in', 0, 22));
            
            $this->feed(1)
                 ->line('-', 32)
                 ->feed(1);
            
            // ========== ITEMS SECTION ==========
            foreach ($receiptData['items'] as $item) {
                // Product name (bold)
                $productName = substr($item['product_name'], 0, 32);
                $this->setBold(true)
                     ->textln($productName)
                     ->setBold(false);
                
                // Quantity x Price and subtotal on same line
                $qtyPrice = $item['quantity'] . ' x P' . number_format($item['unit_price'], 2);
                $subtotal = 'P' . number_format($item['subtotal'], 2);
                $this->columns($qtyPrice, $subtotal);
                $this->feed(1);
            }
            
            // ========== TOTALS SECTION ==========
            $this->line('-', 32)
                 ->feed(1);
            
            // Subtotal
            $this->columns('Subtotal:', 'P' . number_format($receiptData['subtotal'], 2));
            
            // TOTAL (bold and larger)
            $this->setBold(true)
                 ->columns('TOTAL:', 'P' . number_format($receiptData['total_amount'], 2))
                 ->setBold(false);
            
            // Payment method
            $paymentMethod = strtoupper($receiptData['payment_method']);
            $this->columns('Payment (' . $paymentMethod . '):', 'P' . number_format($receiptData['amount_paid'], 2));
            
            // GCash Reference Number (if applicable)
            if ($receiptData['payment_method'] === 'gcash' && !empty($receiptData['gcash_reference'])) {
                $this->columns('GCash Ref #:', $receiptData['gcash_reference']);
            }
            
            // Change (if applicable)
            if ($receiptData['change_amount'] > 0) {
                $this->columns('Change:', 'P' . number_format($receiptData['change_amount'], 2));
            }
            
            $this->feed(1)
                 ->line('-', 32)
                 ->feed(1);
            
            // ========== FOOTER SECTION (centered) ==========
            $this->setAlign(self::ALIGN_CENTER)
                 ->textln('Thank you for visiting 9BARs Coffee!')
                 ->textln('Please come again!')
                 ->feed(2);
            
            // Cut paper
            $this->cut(3);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Thermal printer error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Test print
     */
    public function testPrint() {
        $this->initialize()
             ->setAlign(self::ALIGN_CENTER)
             ->setTextSize(self::SIZE_DOUBLE)
             ->setBold(true)
             ->textln("9BARs COFFEE")
             ->setTextSize(self::SIZE_NORMAL)
             ->setBold(false)
             ->textln("Thermal Printer Test")
             ->feed(1)
             ->setAlign(self::ALIGN_LEFT)
             ->textln("Date: " . date('Y-m-d H:i:s'))
             ->textln("Status: Connected")
             ->textln("Printer: Working!")
             ->feed(2)
             ->setAlign(self::ALIGN_CENTER)
             ->textln("Test completed successfully")
             ->cut();
        
        return $this;
    }
}