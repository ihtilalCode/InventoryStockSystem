<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

class Database
{
    private string $host = 'localhost';
    private string $dbName = 'inventory_stock_db'; // kendi DB adın
    private string $username = 'root';             // XAMPP varsayılan kullanıcı
    private string $password = '';                 // XAMPP varsayılan şifre (boş)
    private ?PDO $conn = null;

    public function getConnection(): PDO
    {
        if ($this->conn === null) {
            $dsn = "mysql:host={$this->host};dbname={$this->dbName};charset=utf8mb4";

            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            
        }

        return $this->conn;
    }
}

// Bu dosyayı tek başına çalıştırırsan bağlantıyı test eder:
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    (new Database())->getConnection();
}
