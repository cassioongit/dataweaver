<?php

namespace Vogel\Utils;

require_once __DIR__ . '/TextEncoding.php';

/**
 * NativeDbf - A pure PHP DBF Reader/Writer (dBASE 7 compatible)
 * Designed to replace PHP's native dbase extension.
 */
class NativeDbf {
    private $handle;
    private $header;
    private $fields = [];
    private $path;
    private $mode;
    private const STORAGE_ENCODING = 'Windows-1252';

    const READ = 'rb';
    const WRITE = 'r+b';

    private static function getFormatSpec(int $version): ?array
    {
        return match ($version) {
            0x03 => [
                'name' => 'dBASE III',
                'descriptorStart' => 32,
                'descriptorSize' => 32,
            ],
            0x04 => [
                'name' => 'Legacy extended header',
                'descriptorStart' => 68,
                'descriptorSize' => 48,
            ],
            default => null,
        };
    }

    public function __construct($path, $mode = 0) {
        $this->path = $path;
        $this->mode = ($mode == 2) ? 'r+b' : 'rb';

        $this->handle = fopen($path, $this->mode);
        if (!$this->handle) {
            throw new \Exception("Could not open DBF file: $path");
        }
        $this->readHeader();

        if ($this->mode === 'r+b' && self::getFormatSpec((int) $this->header->version) === null) {
            throw new \Exception('Writable DBF operations require a supported legacy DBF format.');
        }
    }

    public static function inspectHeader(string $path): array
    {
        $snapshot = [
            'path' => $path,
            'exists' => file_exists($path),
            'readable' => is_readable($path),
        ];

        if (!$snapshot['exists']) {
            $snapshot['issues'] = ['missing_file'];
            return $snapshot;
        }

        $snapshot['fileSize'] = filesize($path);
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            $snapshot['issues'] = ['unreadable_file'];
            return $snapshot;
        }

        $raw = fread($handle, 32);
        fclose($handle);

        if ($raw === false || strlen($raw) < 32) {
            $snapshot['issues'] = ['short_header'];
            $snapshot['rawHeaderLength'] = $raw === false ? 0 : strlen($raw);
            return $snapshot;
        }

        $header = unpack('Cversion/Cyy/Cmm/Cdd/VnumRecords/vheaderSize/vrecordSize', $raw);
        $snapshot['version'] = $header['version'] ?? 0;
        $snapshot['versionHex'] = sprintf('0x%02X', (int) ($header['version'] ?? 0));
        $snapshot['headerSize'] = $header['headerSize'] ?? 0;
        $snapshot['recordSize'] = $header['recordSize'] ?? 0;
        $snapshot['numRecords'] = $header['numRecords'] ?? 0;
        $spec = self::getFormatSpec((int) $snapshot['version']);
        $snapshot['formatName'] = $spec['name'] ?? 'unsupported';
        $snapshot['descriptorStart'] = $spec['descriptorStart'] ?? null;
        $snapshot['descriptorSize'] = $spec['descriptorSize'] ?? null;
        $snapshot['expectedDataSize'] =
            ($snapshot['headerSize'] ?? 0) + (($snapshot['numRecords'] ?? 0) * ($snapshot['recordSize'] ?? 0));
        $snapshot['expectedTerminatedSize'] = ($snapshot['expectedDataSize'] ?? 0) + 1;
        $snapshot['hasTerminatorByte'] = ($snapshot['fileSize'] ?? 0) >= ($snapshot['expectedTerminatedSize'] ?? PHP_INT_MAX);

        $issues = [];
        if ($spec === null) {
            $issues[] = 'invalid_version';
        }
        if (($snapshot['headerSize'] ?? 0) < ((($spec['descriptorStart'] ?? 32)) + 1)) {
            $issues[] = 'header_too_small';
        }
        if ($spec !== null && ((($snapshot['headerSize'] ?? 0) - (($spec['descriptorStart'] ?? 0) + 1)) % ($spec['descriptorSize'] ?? 1)) !== 0) {
            $issues[] = 'misaligned_field_descriptors';
        }
        if (($snapshot['recordSize'] ?? 0) < 2) {
            $issues[] = 'record_size_too_small';
        }
        if (($snapshot['fileSize'] ?? 0) < ($snapshot['expectedDataSize'] ?? 0)) {
            $issues[] = 'file_too_small_for_header';
        }

        $snapshot['issues'] = $issues;
        $snapshot['isValidLegacyDbf'] = count($issues) === 0;

        return $snapshot;
    }

    private function readHeader() {
        fseek($this->handle, 0);
        $raw = fread($this->handle, 32);
        if (strlen($raw) < 32) throw new \Exception("Invalid DBF header");

        $data = unpack('Cversion/Cyy/Cmm/Cdd/VnumRecords/vheaderSize/vrecordSize', $raw);
        $this->header = (object)$data;
        $spec = self::getFormatSpec((int) $this->header->version);
        if ($spec === null) {
            throw new \Exception(sprintf('Unsupported DBF header version: 0x%02X', (int) $this->header->version));
        }

        $descStart = $spec['descriptorStart'];
        $descSize  = $spec['descriptorSize'];

        fseek($this->handle, $descStart);
        $numFields = ($this->header->headerSize - $descStart - 1) / $descSize;

        for ($i = 0; $i < $numFields; $i++) {
            $rawDesc = fread($this->handle, $descSize);
            
            $nameLen = $descSize === 32 ? 11 : 32;
            $name = trim(substr($rawDesc, 0, $nameLen), "\0");
            
            $typeOffset = $descSize === 32 ? 11 : 32;
            $type = substr($rawDesc, $typeOffset, 1);
            
            $lenOffset = $descSize === 32 ? 16 : 33;
            $length = ord(substr($rawDesc, $lenOffset, 1));
            
        $this->fields[] = [
                'name' => $name,
                'type' => $type,
                'length' => $length
            ];
        }
    }

    public function getNumRecords() {
        return $this->header->numRecords;
    }

    public function getRecord($index) {
        if ($index < 1 || $index > $this->header->numRecords) return false;

        $pos = $this->header->headerSize + (($index - 1) * $this->header->recordSize);
        fseek($this->handle, $pos);
        $raw = fread($this->handle, $this->header->recordSize);
        if (strlen($raw) < $this->header->recordSize) return false;

        $record = [];
        $record['deleted'] = ($raw[0] === '*') ? 1 : 0;
        
        $offset = 1;
        foreach ($this->fields as $i => $field) {
            $val = substr($raw, $offset, $field['length']);
            // Convert to proper type
            if ($field['type'] === 'O') {
                // Double/Double-precision number (stored as 8-byte little endian int/float?)
                // Actually dBase 7 NCAD is often 8 bytes. For this project, NCAD is ID.
                $val = unpack('P', $val)[1]; // 64-bit unsigned int
            } else {
                $val = TextEncoding::toUtf8(trim($val));
            }
            $record[$i] = $val;
            $offset += $field['length'];
        }
        return $record;
    }

    public function addRecord(array $data) {
        if ($this->mode !== 'r+b') throw new \Exception("DBF opened in read-only mode");

        // Format data
        $buffer = ' '; // Not deleted
        foreach ($this->fields as $i => $field) {
            $val = $data[$i] ?? '';
            
            if ($field['type'] === 'O') {
                $buffer .= pack('P', (int)$val);
            } else {
                $val = TextEncoding::fromUtf8((string) $val);
                $val = substr($val, 0, $field['length']);
                if ($field['type'] === 'N' || $field['type'] === 'F') {
                    $buffer .= str_pad($val, $field['length'], ' ', STR_PAD_LEFT);
                } else {
                    $buffer .= str_pad($val, $field['length'], ' ', STR_PAD_RIGHT);
                }
            }
        }

        // Seek to end of records (or start of 0x1A terminator)
        $pos = $this->header->headerSize + ($this->header->numRecords * $this->header->recordSize);
        fseek($this->handle, $pos);
        $written = fwrite($this->handle, $buffer);
        if ($written !== strlen($buffer)) {
            throw new \Exception('Failed to write DBF record.');
        }
        
        // Add/Update 0x1A terminator
        $terminatorWritten = fwrite($this->handle, chr(0x1A));
        if ($terminatorWritten !== 1) {
            throw new \Exception('Failed to write DBF terminator.');
        }

        // Update record count in header
        $this->header->numRecords++;
        fseek($this->handle, 4);
        $headerWritten = fwrite($this->handle, pack('V', $this->header->numRecords));
        if ($headerWritten !== 4) {
            throw new \Exception('Failed to update DBF record count.');
        }
        
        return true;
    }

    public function updateRecord($index, array $data) {
        if ($this->mode !== 'r+b') throw new \Exception("DBF opened in read-only mode");
        if ($index < 1 || $index > $this->header->numRecords) return false;

        $buffer = ' '; // Not deleted
        foreach ($this->fields as $i => $field) {
            $val = $data[$i] ?? '';
            if ($field['type'] === 'O') {
                $buffer .= pack('P', (int)$val);
            } else {
                $val = TextEncoding::fromUtf8((string) $val);
                $val = substr($val, 0, $field['length']);
                if ($field['type'] === 'N' || $field['type'] === 'F') {
                    $buffer .= str_pad($val, $field['length'], ' ', STR_PAD_LEFT);
                } else {
                    $buffer .= str_pad($val, $field['length'], ' ', STR_PAD_RIGHT);
                }
            }
        }

        $pos = $this->header->headerSize + (($index - 1) * $this->header->recordSize);
        fseek($this->handle, $pos);
        $written = fwrite($this->handle, $buffer);
        if ($written !== strlen($buffer)) {
            throw new \Exception('Failed to update DBF record.');
        }
        return true;
    }

    public function close() {
        if ($this->handle) fclose($this->handle);
        $this->handle = null;
    }

    public function __destruct() {
        $this->close();
    }
}
