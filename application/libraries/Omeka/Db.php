<?php 
/**
 * @version $Id$
 * @copyright Center for History and New Media, 2007-2010
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Omeka
 **/

/**
 * Database manager object for Omeka
 *
 * While mostly a wrapper for a Zend_Db_Adapter instance, this also provides shortcuts for
 * retrieving table objects and table names for use in SQL. 
 *
 * @uses Zend_Db_Adapter_Mysqli
 * @package Omeka
 * @author CHNM
 * @copyright Center for History and New Media, 2007-2010
 **/
class Omeka_Db
{
    protected $_conn;
    
    /**
     * The prefix that every table in the omeka database will use.  If null this is ignored
     *
     * @var string
     **/
    public $prefix = null;
    
    /**
     * All the tables that are currently managed by this database object
     *
     * @var array
     **/
    protected $_tables = array();
    
    /**
     * @var Zend_Log|null The logger to use for logging SQL queries.  If not set,
     * no logging will be done.
     */
    private $_logger;
    
    /**
     * @param Zend_Db_Adapter $conn A connection object courtesy of Zend Framework
     * @param string $prefix The prefix for the database (if applicable)
     * @return void
     **/
    public function __construct($conn, $prefix=null)
    {   
        $this->_conn = $conn;        
        $this->prefix = (string) $prefix;        
    }
    
    /**
     * Delegate to the Zend_Db_Adapter instance.  Log queries if necessary
     * 
     * @todo Come up with a better solution for logging bad queries.  
     *  Zend_Db_Profiler won't help with logging broken queries, so we need to 
     *  keep this for the sake of logging those.
     * @param string
     * @param array
     * @return mixed
     **/
    public function __call($m, $a)
    {
        // Log SQL for certain adapter calls
        $logFor = array('fetchOne', 'fetchAll', 'prepare', 'query', 'fetchRow', 
                        'fetchAssoc', 'fetchCol', 'fetchPairs');
        if (in_array($m, $logFor)) {
            $this->log($a[0]);
        }
                
        return call_user_func_array(array($this->_conn, $m), $a);
    }
    
    public function setLogger($logger)
    {
        $this->_logger = $logger;
    }
        
    public function getAdapter()
    {
        return $this->_conn;
    }
    
    /**
     * Retrieve the name of the table (including the prefix)
     *
     * @return string
     **/
    public function getTableName($class) {
        return $this->getTable($class)->getTableName();
    }
        
    /**
     * A shortcut for checking to see whether the database tables have a prefix
     *
     * @return bool
     **/
    public function hasPrefix() {
        return !empty($this->prefix);
    }
    
    /**
     * Retrieve a table object corresponding to the model class.
     * 
     * Table classes can be extended by inheriting off of Omeka_Db_Table
     * and then calling your table ModelNameTable, i.e. ItemTable or 
     * CollectionTable, etc.
     * 
     * @internal This will cache every table object so that tables
     * are not instantiated multiple times for complicated web requests.
     * @param string Model class name
     * @return Omeka_Db_Table
     **/
    public function getTable($class) {
        $tableClass = $class . 'Table';
        
        if (array_key_exists($class, $this->_tables)) {
            return $this->_tables[$class];
        }
        
        if (class_exists($tableClass)) {
            $table = new $tableClass($class, $this);
        } else {
            $table = new Omeka_Db_Table($class, $this);
        }
        
        $this->setTable($class, $table);
        
        return $table;
    }
    
    public function setTable($alias, Omeka_Db_Table $table)
    {
        $this->_tables[$alias] = $table;
    }
    
    /**
     * Magic getter is a synonym for Omeka_Db::getTableName()
     *
     * @return string|null
     **/
    public function __get($name)
    {
        return $this->getTableName($name);
    }
    
    /**
     * Every query ends up looking like: 
     *    INSERT INTO table (field, field2, field3, ...) VALUES (?, ?, ?, ...) 
     *    ON DUPLICATE KEY UPDATE field = ?, field2 = ?, ...
     *
     * Note on portability: ON DUPLICATE KEY UPDATE is a MySQL extension.  
     * The advantage to using this is that it doesn't care whether a row exists already.
     * Basically it combines what would be insert() and update() methods in other 
     * ORMs into a single method
     * 
     * @return int The ID for the row that got inserted (or updated)
     **/
    public function insert($table, array $values = array()) {
        
        $table = $this->getTableName($table);
        
        if (empty($values)) {
            return false;
        }
        
        // column names are specified as array keys
        $cols = array_keys($values);
        
        // build the statement
        $query = "
        INSERT INTO `$table` (
        `" . implode('`, `', $cols) . "`
        ) VALUES (";
        
        $a = array();
        $a = array_fill(0, count($values), '?');
        $query .= implode(', ', $a) . ')';
        
        $insert_params = array_values($values);
        $update_query = array();
        $update_params = $values;
        
        foreach ($cols as $col) {
            switch ($col) {
                case 'id':
                    $update_query[] = "`id` = LAST_INSERT_ID(`id`)";
                    
                    // Since we're not actually using the 'id' param in the UPDATE 
                    // clause, remove it
                    unset($update_params['id']);
                    break;
                default:
                    $update_query[] = "`$col` = ?";
                    break;
            }
        }
        $update_params = array_values($update_params);
        
        $query .= " ON DUPLICATE KEY UPDATE ". join(', ', $update_query);        
        
        // prepare and execute the statement
        $params = array_merge($insert_params, $update_params);
        $this->exec($query, $params);
        
        return (int) $this->_conn->lastInsertId();
   }
    
    protected function log($sql)
    {
        if ($this->_logger) {
            $this->_logger->debug((string) $sql);
        }
    }
    
    /**
     * @deprecated Since 4/30/08
     * @param string
     * @param array
     * @return Zend_Db_Statement
     **/
    public function exec($sql, $params=array())
    {    
        return $this->query($sql, $params);
    }

    /**
     * Execute more than one SQL query at once.  
     *
     * @param string $sql String containing SQL queries.
     * @param string $delimiter Character that delimits each SQL query.  Defaults
     * to semicolon ';'.
     * @return void
     **/
    public function execBlock($sql, $delimiter = ';')
    {
        $queries = explode($delimiter, $sql);
        foreach ($queries as $query) {
            if (strlen(trim($query))) {
                $this->exec($query);
            }
        }
    }

    /**
     * Read the contents of an SQL file and execute all the queries therein.
     * 
     * In addition to reading the file, this will make substitutions based on 
     * specific naming conventions.  Currently makes the following substitutions:
     *      %PREFIX% will be replaced by the table prefix
     * 
     * @since 1.3
     * @param string $filePath Path to the SQL file to load
     * @return void
     */
    public function loadSqlFile($filePath)
    {
        if (!is_readable($filePath)) {
            throw new InvalidArgumentException("Cannot read SQL file at '$filePath'.");
        }
        $loadSql = file_get_contents($filePath);
        $subbedSql = str_replace('%PREFIX%', $this->prefix, $loadSql);
        $this->execBlock($subbedSql, ";\n");
    }
   
}