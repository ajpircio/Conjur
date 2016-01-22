<?php

/**
 * Class Conjur_Database
 */
class Conjur_Database
{
    static private $host;
    static private $user;
    static private $pass;
    static private $name;
    static private $error;
    static private $connected;
    static $query;
    static $rows;
    static $link;

    /**
     * Connects to the database
     *
     * @param $host
     * @param $user
     * @param $pass
     * @param $name
     */
    public function __construct($host, $user, $pass, $name)
    {
        $link = @mysqli_connect($host, $user, $pass, $name);
        if (!mysqli_connect_errno()) {
            self::$host = $host;
            self::$user = $user;
            self::$pass = $name;
            self::$name = $name;
            self::$link = $link;
            self::$connected = TRUE;
        }
        else {
            self::$error = 'Error connecting to MySQL Server: ' . mysqli_connect_error();
            self::$connected = FALSE;
        }
    }

    /**
     * Gets a the mysql error of the last executed query
     *
     * @return string
     */
    public static function error() {
        return self::$error;
    }

    /**
     * Execute a given query and return true/false on success/fail
     *
     * @return bool
     */
    public static function run()
    {
        $argv = func_get_args();
        $protected_query = call_user_func_array(array(self, 'Q'), $argv);

        $rs = self::doQuery($protected_query);

        if (!$rs) {
            return FALSE;
        }
        else {
            return TRUE;
        }
    }

    // execute a given query and return insert id on success
    /**
     * Performs an insert query and returns the result id inserted
     *
     * @return bool|int|string
     */
    public static function insert()
    {
        $argv = func_get_args();
        $protected_query = call_user_func_array(array(self, 'Q'), $argv);
        $rs = self::doQuery($protected_query);

        if ($rs) {
            return mysqli_insert_id(self::$link);
        }
        else {
            return FALSE;
        }
    }

    /**
     * Performs a simple Mysql Result and returns a single column of the first row found
     * @return bool
     */
    public static function result()
    {
        $argv = func_get_args();
        $protected_query = call_user_func_array(array(self, 'Q'), $argv);
        $rs = self::doQuery($protected_query);

        if (!$rs) {
            return FALSE;
        }

        if (mysqli_num_rows($rs) > 0) {
            $row = mysqli_fetch_row($rs);
            $return = $row[0];
        }
        else {
            $return = FALSE;
        }
        return $return;
    }

    /**
     * Performs query and returns an single or multi-dimensional array of results based on result row count
     *
     * @return array|bool|null
     */
    public static function assoc()
    {
        $argv = func_get_args();
        $protected_query = call_user_func_array(array(self, 'Q'), $argv);
        $rs = self::doQuery($protected_query);

        if (!$rs) {
            return false;
        }

        if (mysqli_num_rows($rs) == 0) {
            self::$rows = mysqli_num_rows($rs);
            return false;
        }

        $return = array();
        if (mysqli_num_rows($rs) == 1) {
            $return = mysqli_fetch_assoc($rs);
        }
        else {
            while ($entry = mysqli_fetch_assoc($rs)) {
                $return[] = $entry;
            }
        }
        return $return;
    }

    /**
     * Performs query and returns an multi-dimensional array of results
     *
     * @return array|bool
     */
    public static function multiAssoc()
    {
        $argv = func_get_args();
        $protected_query = call_user_func_array(array(self, 'Q'), $argv);
        $rs = self::doQuery($protected_query);

        if (!$rs) {
            return FALSE;
        }

        if (mysqli_num_rows($rs) == 0) { return false; }

        $return = array();
        while ($entry = mysqli_fetch_assoc($rs))
        {
            $return[] = $entry;
        }
        return $return;
    }

    /**
     * Gets all columns from a given table and returns as array
     *
     * @param $table_name
     * @return array
     */
    public static function getColumns($table_name)
    {
        $fields = self::assoc("SHOW COLUMNS FROM `$table_name`");
        $all_fields = array();
        foreach ($fields as $f) {
            $all_fields[] = $f['Field'];
        }
        return $all_fields;
    }

    /**
     * Drops a column from a database table
     *
     * @param $table_name
     * @param $column_name
     * @return bool
     */
    public static function dropColumn($table_name, $column_name)
    {
        // get columns
        $all_fields = self::getColumns($table_name);

        if (in_array($column_name, $all_fields)) {
            self::run("ALTER TABLE `$table_name` DROP COLUMN `$column_name`");
            return true;
        }

        return false;
    }

    /**
     * Add a column to a given table
     *
     * @param $table_name
     * @param $column_name
     * @param $declaration - e.g. VARCHAR(30) NOT NULL
     * @return bool
     */
    public static function addColumn($table_name, $column_name, $declaration)
    {
        // get columns
        $all_fields = self::getColumns($table_name);

        if (!in_array($column_name, $all_fields)) {
            self::run("ALTER TABLE `$table_name` ADD COLUMN `$column_name` $declaration");
            return true;
        }

        return false;
    }

    /**
     * Sees if a database connection has been established
     *
     * @return bool
     */
    public static function isConnected() {
        return self::$connected;
    }

    /**
     * @param $_query
     * @return string - Formatted and sanitized query string
     */
    private static function Q($_query)
    {
        $argv = func_get_args();
        $argc = func_num_args();
        $n = 1;			// first vararg $argv[1]

        $out = '';
        $quote = FALSE;		// quoted string state
        $slash = FALSE;		// backslash state

        // b - pointer to start of uncopied text
        // e - pointer to current input character
        // end - end of string pointer
        $end = strlen($_query);
        for ($b = $e = 0; $e < $end; ++$e)
        {
            $ch = $_query{$e};

            if ($quote !== FALSE)
            {
                if ($slash)
                {
                    $slash = FALSE;
                }
                elseif ($ch === '\\')
                {
                    $slash = TRUE;
                }
                elseif ($ch === $quote)
                {
                    $quote = FALSE;
                }
            }
            elseif ($ch === "'" || $ch === '"')
            {
                $quote = $ch;
            }
            elseif ($ch === '?')
            {
                $out .= substr($_query, $b, $e - $b) .
                    self::_Q_escape($argv[$n], $n);
                $b = $e + 1;
                $n++;
            }
        }
        $out .= substr($_query, $b, $e - $b);

        // warn on arg count mismatch
        if ($argc != $n)
        {
            $adj = ($argc > $n) ? 'many' : 'few';
            trigger_error('Too ' . $adj . ' arguments ' .
                '(expected ' . $n . '; got ' . $argc . ')',
                E_USER_WARNING);
        }

        return $out;
    }

    /**
     * @param $_value
     * @param bool $_position
     * @return string - Escaped value
     */
    private static function _Q_escape($_value, $_position = FALSE)
    {
        static $r_position;

        // Save $_position to simplify recursive calls.
        if ($_position !== FALSE)
        {
            $r_position = $_position;
        }

        if (is_null($_value))
        {
            // The NULL value
            return 'NULL';
        }
        elseif (is_int($_value) || is_float($_value))
        {
            // All integer and float representations should be
            // safe for mysql (including 5e-12 notation)
            $result = "$_value";
        }
        elseif (is_array($_value))
        {
            // Arrays are written as a comma-separated list of
            // values.  Useful for IN, find_in_set(), etc.

            // KM, AS: PHP stoneage is crashing here, when the
            // _values array is missing a 0 index.. hence the array_values()
            $result = implode(', ', array_map(array(self, '_Q_escape'), array_values($_value)));
        }
        else
        {
            // Warn if given an unexpected value type
            if (!is_string($_value))
            {
                trigger_error('Unexpected value of type "' .
                    gettype($_value) . '" in arg '.$r_position,
                    E_USER_WARNING);
            }

            // Everything else gets escaped as a string
            $result = "'" . addslashes($_value) . "'";
        }

        return $result;
    }

    /**
     * Performs the final query and returns the result
     *
     * @param $query
     * @return bool|mysqli_result - FALSE on error, result on success
     */
    private static function doQuery($query) {
        self::$query = $query;
        $result = mysqli_query(self::$link, $query);
        if (!$result) {
            self::$error = "Error processing query: " . mysqli_error(self::$link);
            return FALSE;
        }
        return $result;
    }

    /**
     * Updates or Inserts database row in $table:
     *
     * @param string $table - name of table to be updated
     * @param string $keyValue - value of the key to update in $table
     * @param array $fields - key/value pair of fields to be updated
     * @return mixed - the value of the primary key that was inserted or updated
     */
    public static function update($table, $keyValue, $fields = array()) {
        try {
            // get primary key field name
            $tableInfo = self::assoc("SHOW KEYS FROM `$table` WHERE Key_name = ?", 'PRIMARY');
            $keyFieldName = $tableInfo['Column_name'];
            if (!$keyFieldName) {
                throw new Exception("Primary key field not found in table: $table");
            }
            // see if we have an try with this key
            $check = self::result("SELECT count(1) FROM `$table` WHERE `$keyFieldName`=?", $keyValue);

            if ($check) {
                // update row
                $params = array();
                $query  = "UPDATE `$table` SET ";

                if ((sizeof($fields) > 0) and (is_array($fields))) {
                    $part = '';
                    foreach ($fields as $fieldName => $fieldValue) {
                        $part .= "`$fieldName`=?, ";
                        $params[] = $fieldValue;
                    }
                    $part = trim($part, ', ');
                    $query .= $part;
                }

                $query .= " WHERE `$keyFieldName`=?";
                $params[] = $keyValue;

                array_unshift($params, $query);

                $result = call_user_func_array(array(self, 'run'), $params);
                if ($result) {
                    return $keyValue;
                }
            }
            else {
                // insert new row

                $query  = "INSERT INTO `$table` (`$keyFieldName`,";
                $params = array();
                $params[] = $keyValue;

                if ((sizeof($fields) > 0) and (is_array($fields))) {
                    $part1 = '';
                    $part2 = '?,';
                    foreach ($fields as $fieldName => $fieldValue) {
                        $part1 .= "`$fieldName`,";
                        $part2 .= "?,";
                        $params[] = $fieldValue;
                    }

                    $part1 = trim($part1, ',');
                    $part2 = trim($part2, ',');

                    $query .= "$part1) VALUES ($part2)";
                    array_unshift($params, $query);

                    $result = call_user_func_array(array(self, 'insert'), $params);
                    return $result;
                }
            }
        }
        catch (Exception $e) {
            echo 'Caught exception: ', $e->getMessage(), "\n";
        }
    }
}