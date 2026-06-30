<?php
class Database {
    private $db;

    public function __construct($db_path = '/var/www/db/blackhole.sq3') {
        $this->db = new SQLite3($db_path);
        date_default_timezone_set('UTC');
    }

    public function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        return $stmt->execute();
    }

    public function fetchAll($sql, $params = []) {
        $result = $this->query($sql, $params);
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function fetchOne($sql, $params = []) {
        $result = $this->query($sql, $params);
        return $result->fetchArray(SQLITE3_ASSOC);
    }

    public function execute($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        return $stmt->execute();
    }

    public function logAction($action, $ip = null, $details = null) {
        $this->execute("INSERT INTO logs (action, ip_address, details) VALUES (:action, :ip, :details)", [
            ':action' => $action,
            ':ip' => $ip,
            ':details' => $details
        ]);
    }

    public function getSetting($key, $default = "") {
        $row = $this->fetchOne("SELECT value FROM settings WHERE key = :key", [':key' => $key]);
        return $row ? $row['value'] : $default;
    }
}
