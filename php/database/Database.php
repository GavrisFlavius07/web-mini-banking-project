<?php

class Database extends MySQLi
{
    static protected $instance = NULL;

    protected function __construct() {
        parent::__construct('my_mariadb', 'root', 'ciccio', 'banking');
    }

    static function instance() {
        if ($instance == NULL)
            $instance = new Database();
        return $instance;
    }
}