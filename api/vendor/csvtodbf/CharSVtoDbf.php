<?php

/*
 * File Parser
 */
class FileParser
{
    private $filePath = null;
    private $objectFields = null;
    private $formatters = [];
    private $filter = null;
    private $each = null;
    private $group = null;
    private $fromEncoding = null;
    private $toEncoding = null;
    private $delimiter;

    public static function instance() { return new static(); }

    public function setFile($filePath, $delimiter = null) {
        $this->filePath = $filePath;
        $this->delimiter = $delimiter;
        return $this;
    }

    public function setEncoding($fromEncoding = 'UTF-8', $toEncoding = 'UTF-8') {
        $this->fromEncoding = $fromEncoding;
        $this->toEncoding = $toEncoding;
        return $this;
    }

    public function toObject(array $objectFields = []) {
        $this->objectFields = $objectFields;
        return $this;
    }

    public function format($key, callable $callable) {
        foreach ((array)$key as $k) { $this->formatters[$k][] = $callable; }
        return $this;
    }

    public function filter(callable $callable) {
        $this->filter = $callable;
        return $this;
    }

    public function each(callable $callable) {
        $this->each = $callable;
        return $this;
    }

    public function group(callable $callable) {
        $this->group = $callable;
        return $this;
    }

    public function parse() {
        $lines = [];
        $file = fopen($this->filePath, "r");
        while (($line = fgets($file)) !== false) {
            if ($this->delimiter !== null) {
                $line = explode($this->delimiter, $line);
                if ($this->fromEncoding !== null && $this->toEncoding !== null) {
                    $line = array_map(function ($val) {
                        return iconv($this->fromEncoding, $this->toEncoding, $val);
                    }, $line);
                }
            } else {
                $line = iconv($this->fromEncoding, $this->toEncoding, $line);
            }
            if ($this->objectFields !== null) {
                $line = (object)array_combine($this->objectFields, $line);
            }
            if (is_callable($this->each)) {
                $func = $this->each;
                $line = $func($line);
            }
            if (is_callable($this->filter)) {
                $func = $this->filter;
                if (!(bool)$func($line)) continue;
            }
            foreach ($this->formatters as $key => $callables) {
                if (is_object($line) && !isset($line->{$key}) || is_array($line) && !isset($line[$key])) {
                    continue;
                }
                foreach ($callables as $callable) {
                    if (is_object($line)) { $line->{$key} = $callable($line->{$key}); } 
                    else { $line[$key] = $callable($line[$key]); }
                }
            }
            if (is_callable($this->group)) {
                $func = $this->group;
                $lines[$func($line)][] = $line;
            } else {
                $lines[] = $line;
            }
        }
        return $lines;
    }
}

/**
 * CharSVtoDBF - Updated to use NativeDbf (Pure PHP Driver)
 */
require_once __DIR__ . '/../../src/utils/NativeDbf.php';
use Vogel\Utils\NativeDbf;

class CharSVtoDBF
{
    private $db;

    public function __construct($path, $dataTypes = array(), $mode = 2)
    {
        // Using NativeDbf instead of dbase_open
        // Mode 2 in legacy dbase was read/write.
        $this->db = new NativeDbf($path, $mode);
    }

    public function insertOne(array $data)
    {
        return $this->db->addRecord($data);
    }

    public function editOne($index, array $data)
    {
        return $this->db->updateRecord($index, $data);
    }

    public function getNumRows()
    {
        return $this->db->getNumRecords();
    }

    public function getHeaderInfo()
    {
        // Minimal header info mock if needed
        return [];
    }

    public function getAllRows()
    {
        return $this->getRange(1, $this->db->getNumRecords());
    }

    public function getRow($index)
    {
        return $this->db->getRecord($index);
    }

    public function getRange($offset, $limit)
    {
        $records = [];
        $total = $this->db->getNumRecords();
        $end = min($offset + $limit - 1, $total);
        
        for ($i = (int)$offset; $i <= $end; $i++) {
            $records[] = $this->db->getRecord($i);
        }
        return $records;
    }

    public function getMaxId()
    {
        $count = $this->db->getNumRecords();
        $maior = 0;
        for ($i = 1; $i <= $count; $i++) {
            $row = $this->db->getRecord($i);
            if ($row && $row[0] > $maior) {
                $maior = $row[0];
            }
        }
        return $maior;
    }

    public function findRecordByName($nome)
    {
        $count = $this->db->getNumRecords();
        $inputName = $this->normalizeName($nome);
       
        for ($i = 1; $i <= $count; $i++) {
            $row = $this->db->getRecord($i);
            if ($row) {
                $rawDbName = trim($row[1]);
                $dbName = $this->normalizeName($rawDbName);
                if ($inputName === $dbName) {
                    return [
                        'index' => $i,
                        'data' => $row
                    ];
                }
            }
        }
        return null;
    }

    public function getExisteItem($nome, $data)
    {
        return $this->findRecordByName($nome) !== null;
    }

    /**
     * Normalize a name for duplicate detection:
     * - Trim whitespace
     * - Lowercase
     * - Remove accents (normalize to ASCII)
     */
    private function normalizeName($name)
    {
        $name = trim($name);
        $name = mb_strtolower($name, 'UTF-8');
        // Transliterate accented chars to ASCII equivalents
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        // Collapse multiple spaces
        $name = preg_replace('/\s+/', ' ', $name);
        return substr(trim($name), 0, 38);
    }

    public function insertMulti(array $data)
    {
        foreach ($data as $v) { $this->insertOne($v); }
    }

    public function closeDbase()
    {
        $this->db->close();
    }
}
