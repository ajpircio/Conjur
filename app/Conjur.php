<?php

spl_autoload_register('conjurAutoloader');

function conjurAutoloader($class) {
    $classFile = str_replace(' ', DIRECTORY_SEPARATOR, ucwords(str_replace('_', ' ', $class))) . '.php';
    $localFile = CONJUR_ROOT . '/app/code/local/' . $classFile;
    $commFile  = CONJUR_ROOT . '/app/code/community/' . $classFile;
    $coreFile  = CONJUR_ROOT . '/app/code/core/' . $classFile;

    try {
        if (is_file($localFile)) {
            require_once($localFile);
        }
        elseif (is_file($commFile)) {
            require_once($commFile);
        }
        elseif (is_file($coreFile)) {
            require_once($coreFile);
        }
        else {
            throw new Exception("Class file not found: $class");
        }
    }
    catch (Exception $e) {
        echo 'Caught exception: ', $e->getMessage(), "\n";
    }
}

final class Conjur
{
    static private $db;
    static private $config; // for things located on disk
    static private $settings = array(); // for this located in database

    public function run() {
        self::readConfig();

        // try to connect to database

        self::$db = new Conjur_Database(
            self::$config->resources->db->host,
            self::$config->resources->db->username,
            self::$config->resources->db->password,
            self::$config->resources->db->dbname
        );

        if (!self::$db->isConnected()) {
            echo "not connected";
            //todo: installation script here
        }
        else {
            self::loadSettings();
        }
    }

    private static function loadSettings() {
        $settings = self::$db->multiAssoc('SELECT * FROM `conjur_settings`');
        if (is_array($settings)) {
            while ($setting = array_shift($settings)) {
                self::$settings[$setting['key']] = $setting['value'];
            }
        }
    }

    private static function readConfig() {
        $xml = simplexml_load_file(CONJUR_ROOT .'/app/etc/config.xml');
        self::$config = $xml;
    }

    public static function getSetting($key) {
        if (isset(self::$settings[$key])) {
            return self::$settings[$key];
        }
        else {
            return FALSE;
        }
    }

    public static function setSetting($key, $value) {
        self::$db->update('conjur_settings', $key, array('value'=>$value));
        self::loadSettings();
    }
}