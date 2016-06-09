<?php

class Colours {
  /* Colours
  */
  static private $foreground = array(
    'black' => '0;30',
    'dark_grey' => '1;30',
    'red' => '0;31',
    'bold_red' => '1;31',
    'green' => '0;32',
    'bold_green' => '1;32',
    'brown' => '0;33',
    'yellow' => '1;33',
    'blue' => '0;34',
    'bold_blue' => '1;34',
    'purple' => '0;35',
    'bold_purple' => '1;35',
    'cyan' => '0;36',
    'bold_cyan' => '1;36',
    'white' => '0;37',
    'bold_grey' => '0;37',
  );

  static private $background = array(
    'black' => '40',
    'red' => '41',
    'magenta' => '45',
    'yellow' => '43',
    'green' => '42',
    'blue' => '44',
    'cyan' => '46',
    'light_grey' => '47',
  );

  static public function off() {
    return "\033[m";
  }

  static public function fg($colour) {
    return "\033[" . Colours::$foreground[$colour] . "m";
  }

  static public function bg($colour) {
    return "\033[" . Colours::$background[$colour] . "m";
  }

  static public function highlight_sql($sql) {

    $sql_keywords = array(
      'ADD', 'ALL', 'ALTER', 'ANALYZE', 'AND', 'AS', 'ASC', 'ASENSITIVE',
      'BEFORE', 'BETWEEN', 'BIGINT', 'BINARY', 'BLOB', 'BOTH', 'BY',
      'CALL', 'CASCADE', 'CASE', 'CHANGE', 'CHAR', 'CHARACTER', 'CHECK',
      'COLLATE', 'COLUMN', 'CONDITION', 'CONNECTION', 'CONSTRAINT', 'CONTINUE',
      'CONVERT', 'CREATE', 'CROSS', 'CURRENT_DATE', 'CURRENT_TIME',
      'CURRENT_TIMESTAMP', 'CURRENT_USER', 'CURSOR', 'DATABASE', 'DATABASES',
      'DAY_HOUR', 'DAY_MICROSECOND', 'DAY_MINUTE', 'DAY_SECOND', 'DEC',
      'DECIMAL', 'DECLARE', 'DEFAULT', 'DELAYED', 'DELETE', 'DESC', 'DESCRIBE',
      'DETERMINISTIC', 'DISTINCT', 'DISTINCTROW', 'DIV', 'DOUBLE', 'DROP',
      'DUAL', 'DUPLICATE', 'EACH', 'ELSE', 'ELSEIF', 'ENCLOSED', 'ESCAPED',
      'EXISTS', 'EXIT', 'EXPLAIN', 'FALSE', 'FETCH', 'FLOAT', 'FLOAT4', 'FLOAT8',
      'FOR', 'FORCE', 'FOREIGN', 'FROM', 'FULLTEXT', 'GOTO', 'GRANT', 'GROUP',
      'HAVING', 'HIGH_PRIORITY', 'HOUR_MICROSECOND', 'HOUR_MINUTE', 'HOUR_SECOND',
      'IF', 'IGNORE', 'IN', 'INDEX', 'INFILE', 'INNER', 'INOUT', 'INSENSITIVE',
      'INSERT', 'INT', 'INT1', 'INT2', 'INT3', 'INT4', 'INT8', 'INTEGER',
      'INTERVAL', 'INTO', 'IS', 'ITERATE', 'JOIN', 'KEY', 'KEYS', 'KILL',
      'LABEL', 'LEADING', 'LEAVE', 'LEFT', 'LIKE', 'LIMIT', 'LINES', 'LOAD',
      'LOCALTIME', 'LOCALTIMESTAMP', 'LOCK', 'LONG', 'LONGBLOB', 'LONGTEXT',
      'LOOP', 'LOW_PRIORITY', 'MATCH', 'MEDIUMBLOB', 'MEDIUMINT', 'MEDIUMTEXT',
      'MIDDLEINT', 'MINUTE_MICROSECOND', 'MINUTE_SECOND', 'MOD', 'MODIFIES',
      'NATURAL', 'NOT', 'NO_WRITE_TO_BINLOG', 'NULL', 'NUMERIC', 'ON',
      'OPTIMIZE', 'OPTION', 'OPTIONALLY', 'OR', 'ORDER', 'OUT', 'OUTER',
      'OUTFILE', 'PRECISION', 'PRIMARY', 'PROCEDURE', 'PURGE', 'READ', 'READS',
      'REAL', 'REFERENCES', 'REGEXP', 'RELEASE', 'RENAME', 'REPEAT', 'REPLACE',
      'REQUIRE', 'RESTRICT', 'RETURN', 'REVOKE', 'RIGHT', 'RLIKE', 'SCHEMA',
      'SCHEMAS', 'SECOND_MICROSECOND', 'SELECT', 'SENSITIVE', 'SEPARATOR',
      'SET', 'SHOW', 'SMALLINT', 'SONAME', 'SPATIAL', 'SPECIFIC', 'SQL',
      'SQL_BIG_RESULT', 'SQL_CALC_FOUND_ROWS', 'SQLEXCEPTION',
      'SQL_SMALL_RESULT', 'SQLSTATE', 'SQLWARNING', 'SSL', 'STARTING',
      'STRAIGHT_JOIN', 'TABLE', 'TERMINATED', 'THEN', 'TINYBLOB', 'TINYINT',
      'TINYTEXT', 'TO', 'TRAILING', 'TRIGGER', 'TRUE', 'UNDO', 'UNION',
      'UNIQUE', 'UNLOCK', 'UNSIGNED', 'UPDATE', 'UPGRADE', 'USAGE', 'USE',
      'USING', 'UTC_DATE', 'UTC_TIME', 'UTC_TIMESTAMP', 'VALUES', 'VARBINARY',
      'VARCHAR', 'VARCHARACTER', 'VARYING', 'WHEN', 'WHERE', 'WHILE', 'WITH',
      'WRITE', 'XOR', 'YEAR_MONTH', 'ZEROFILL'
    );

    $wp_tables = array(
      'commentmeta', 'comments', 'links', 'options', 'postmeta', 'posts',
      'terms', 'term_relationships', 'term_taxonomy', 'usermeta', 'users',
      'blogs', 'blog_versions', 'registration_log', 'signups', 'site',
      'sitecategories', 'sitemeta'
    );


    //
    // Highlight keywords
    //

    foreach($sql_keywords as $keyword) {
      $sql = preg_replace('/\b' . $keyword . '\b/', Colours::fg('bold_cyan') . $keyword . Colours::fg('white'), $sql);
    }

    //
    // Highlight WordPress table names
    //


    global $wpdb;

    foreach($wp_tables as $table) {
      $sql = preg_replace('/\b(' . $wpdb->prefix . '(\d+_)?'  . $table . ')\b/', Colours::fg('purple') . '\\1' . Colours::fg('white'), $sql);
    }

    return $sql;
  }
}
