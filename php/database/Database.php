<?php

class Database extends mysqli
{
    private static $instance = null;

    private function __construct()
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        parent::__construct('my_mariadb', 'root', '', 'banking');
        $this->set_charset('utf8mb4');
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __clone()
    {
        throw new RuntimeException('Database singleton cannot be cloned');
    }

    public function __wakeup()
    {
        throw new RuntimeException('Database singleton cannot be unserialized');
    }
}
