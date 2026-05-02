<?php

namespace Editiel98\FileWriter;

use RuntimeException;
use InvalidArgumentException;
use UnexpectedValueException;

class FileWriter
{
    private const TYPE_STRING = 0x01;
    private const TYPE_INT16  = 0x02;
    private const TYPE_INT32  = 0x03;
    private const TYPE_FLOAT  = 0x04;

    private string $filename;
    private bool $isNew;
    /**
     * Array representing Header. Must have
     * 'signature' => string representing bytes signature
     * 'version' => int representing version file
     *
     * @var array
     */
    private array $header;

    public function __construct(string $filename, array $header, bool $isNew = true)
    {
        $this->filename = $filename;
        $this->isNew = $isNew;
        $this->checkHeader($header);
        $this->header = $header;
    }

    /**
     * Write binaries datas in file
     * format of $data :
     * [
     * '0' => unit data,
     * '1' => unit data
     * ]
     *
     * unit data format :
     * [
     *     0 => mixed (int, float, string),
     *     1 => mixed  (int, float, string),
     * ...
     * ]
     * unit data keys maybe numeric or string
     * 
     * @param array $data
     * @return void
     */
    public function writeBinaryFile(array $data)
    {
        $mode = 'ab';
        if (file_exists($this->filename)) {
            $this->isNew = false;
        } else {
            $mode = 'wb';
            $this->isNew = true;
        }
        $file = fopen($this->filename, $mode);
        if ($file === false) {
            throw new RuntimeException("Unable to open file for writing: {$this->filename}");
        }
        if ($this->isNew) {
            if (empty($this->header)) {
                throw new InvalidArgumentException("Header information is required for new files.");
            }
            $headerWriten = $this->writeHeaderFile($file);
            if (!$headerWriten) {
                throw new RuntimeException("Failed to write header to file: {$this->filename}");
            }
        }
        foreach ($data as $unitData) {
            $this->writeUnitData($file, $unitData);
        }
        fclose($file);
    }

    public static function readHeaderFile(string $file, array $header): array
    {
        self::checkHeader($header);
        $fileHandle = fopen($file, 'rb');
        if ($fileHandle === false) {
            throw new RuntimeException("Unable to open file for reading: {$file}");
        }
        $magicByteSize = strlen($header['signature']);
        $magicBytes = fread($fileHandle, $magicByteSize);
        if ($magicBytes === false) {
            throw new UnexpectedValueException("Failed to read magic bytes from file: {$file}");
        }
        $returnArray['signature'] = $magicBytes;
        $versionBytes = fread($fileHandle, 4);
        if ($versionBytes === false) {
            throw new UnexpectedValueException("Failed to read version bytes from file: {$file}");
        }
        $version = unpack('V', $versionBytes)[1];
        $returnArray['version'] = $version;
        return $returnArray;
    }

    public function readFile(string $file, array $header, array $structure): array
    {
        $this->checkHeader($header);
        $fileHandle = fopen($file, 'rb');
        if ($fileHandle === false) {
            throw new RuntimeException("Unable to open file for reading: {$file}");
        }

        $magicByteSize = $this->getHeaderLength();
        $magicBytes = fread($fileHandle, $magicByteSize);
        if ($magicBytes === false) {
            throw new UnexpectedValueException("Failed to read magic bytes from file: {$file}");
        }
        if ($magicBytes !== $header['signature']) {
            throw new UnexpectedValueException("Magic bytes do not match expected signature in file: {$file}");
        }
        $versionBytes = fread($fileHandle, 4);
        if ($versionBytes === false) {
            throw new UnexpectedValueException("Failed to read version bytes from file: {$file}");
        }
        $version = unpack('V', $versionBytes)[1];
        if ($version !== $header['version']) {
            throw new UnexpectedValueException("Version mismatch: expected {$header['version']}, got {$version} in file: {$file}");
        }
        $fileSize = filesize($file);
        $data = fread($fileHandle, $fileSize - ftell($fileHandle));
        fclose($fileHandle);
        return $this->parseData($data, $structure);
    }

    private function parseData(string $data, array $structure): array
    {
        $returnArray = [];
        $offset = 0;
        $length = strlen($data);
        $record = [];
        $keys = array_keys($structure);
        $keyIndex = 0;
        while ($offset < $length) {
            $type = unpack('C', substr($data, $offset, 1))[1];
            $offset += 1;

            switch ($type) {
                case self::TYPE_STRING:
                    $size = unpack('v', substr($data, $offset, 2))[1];
                    $offset += 2;
                    $value = substr($data, $offset, $size);
                    $offset += $size;
                    break;
                case self::TYPE_INT16:
                    $raw = unpack('v', substr($data, $offset, 2))[1];
                    $value = $raw >= 32768 ? $raw - 65536 : $raw;
                    $offset += 2;
                    break;
                case self::TYPE_INT32:
                    $raw = unpack('V', substr($data, $offset, 4))[1];
                    $value = $raw >= 2147483648 ? $raw - 4294967296 : $raw;
                    $offset += 4;
                    break;
                case self::TYPE_FLOAT:
                    $value = unpack('f', substr($data, $offset, 4))[1];
                    $offset += 4;
                    break;
                default:
                    throw new UnexpectedValueException("Unknown tag type : 0x" . dechex($type));
            }
            $key = $keys[$keyIndex % count($keys)];
            $record[$key] = $value;
            $keyIndex++;

            if ($keyIndex % count($keys) === 0) {
                $returnArray[] = $record;
                $record = [];
            }
        }

        return $returnArray;
    }

    private static function checkHeader(array $header): void
    {
        if (!isset($header['signature']) || !is_string($header['signature'])) {
            throw new InvalidArgumentException("Header must contain a 'signature' key with a string value.");
        }
        if (strlen($header['signature']) > 6) {
            throw new InvalidArgumentException("Header 'signature' value must not exceed 6 characters.");
        }
        if (!isset($header['version']) || !is_int($header['version'])) {
            throw new InvalidArgumentException("Header must contain a 'version' key with an integer value.");
        }
    }
    
    protected function writeHeaderFile(mixed $file): bool
    {
        if (!is_resource($file)) {
            throw new RuntimeException("Invalid file resource provided.");
        }
        $headerBytes = $this->header['signature'];
        $headerLength = 'a' . $this->getHeaderLength();
        fwrite($file, pack($headerLength, $headerBytes));
        $result = fwrite($file, pack('V', $this->header['version']));
        if ($result === false) {
            return false;
        }
        return $result !== 0;
    }

    private function getHeaderLength(): int
    {
        return strlen($this->header['signature']);
    }

    private function writeUnitData(mixed $file, array $unitData): bool
    {
        if (!is_resource($file)) {
            throw new RuntimeException("Invalid file resource provided.");
        }
        foreach ($unitData as $data) {
            if (!is_int($data) && !is_float($data) && !is_string($data)) {
                throw new InvalidArgumentException("Unit data must be of type int, float, or string.");
            }

            $binaryData = $this->convertToBinary($data);
            $result = fwrite($file, $binaryData);
            if ($result === false) {
                return false;
            }
        }
        return true;
    }

    private function convertToBinary(mixed $value): string
    {
        $size = 0;
        $data = null;
        if (is_string($value)) {
            $size = strlen($value);
            $data = pack('Cv', self::TYPE_STRING, $size);
            $data .= pack('a' . $size, $value);
        }
        if (is_int($value)) {
            $data = $this->convertInt($value);
        }
        if (is_float($value)) {
            $data = $this->convertFloat($value);
        }
        return $data;
    }

    private function convertInt(int $value): string
    {
        //Detect if int16 or int32
        if ($value >= -32768 && $value <= 32767) {
            return  $this->convertInt16($value);
        }
        return $this->convertInt32($value);
    }

    private function convertInt16(int $value): string
    {
        if ($value < 0) {
            $value += 65536;
        }
        return pack('Cv', self::TYPE_INT16, $value);
    }

    private function convertInt32(int $value): string
    {
        if ($value < 0) {
            $value += 4294967296;
        }
        return pack('CV', self::TYPE_INT32, $value);
    }

    private function convertFloat(float $value): string
    {
        $packed = pack('f', $value);
        if (pack('V', 1) === pack('N', 1)) {
            $packed = strrev($packed);
        }
        return pack('C', self::TYPE_FLOAT) . $packed;
    }
}
