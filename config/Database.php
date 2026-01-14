<?php
class Database {
    public static function connect() {
        $env = parse_ini_file(__DIR__ . '/../.env');
        return new PDO(
            "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']}",
            $env['DB_USER'],
            $env['DB_PASS'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
}
