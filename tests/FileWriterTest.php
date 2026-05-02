<?php

namespace Editiel98\FileWriter\Tests;

use stdClass;
use RuntimeException;
use InvalidArgumentException;
use UnexpectedValueException;
use PHPUnit\Framework\TestCase;
use Editiel98\FileWriter\FileWriter;

class FileWriterTest extends TestCase
{

 private string $testFile;
 
    private array $validHeader = [
        'signature' => 'MYFILE',
        'version'   => 1,
    ];
 
    private array $validStructure = [
        'name'   => 'string',
        'number' => 'int',
        'float'  => 'float',
    ];

    private const TYPE_STRING = 0x01;
    private const TYPE_INT16  = 0x02;
    private const TYPE_INT32  = 0x03;
    private const TYPE_FLOAT  = 0x04;
 
    protected function setUp(): void
    {
        $this->testFile = sys_get_temp_dir() . '/filewriter_test_' . uniqid() . '.bin';
    }
 
    protected function tearDown(): void
    {
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
    }
    //------ Test  misformatted header ---------
    public function testHeaderMissingSignatureThrowException(): void
    {
        $header = [
            'version' => 1
        ];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Header must contain a 'signature' key with a string value.");
        $writer = new FileWriter('file.bin', $header, true);
    }

    public function testIntSignatureWillThrowException(): void
    {
        $header = [
            'version' => 1,
            'signature' => 1
        ];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Header must contain a 'signature' key with a string value.");
        $writer = new FileWriter('file.bin', $header, true);
    }

    public function testFloatSignatureWillThrowException(): void
    {
        $header = [
            'version' => 1,
            'signature' => 1,
            1
        ];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Header must contain a 'signature' key with a string value.");
        $writer = new FileWriter('file.bin', $header, true);
    }

    public function testArraySignatureWillThrowException(): void
    {
        $header = [
            'version' => 1,
            'signature' => []
        ];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Header must contain a 'signature' key with a string value.");
        $writer = new FileWriter('file.bin', $header, true);
    }

    public function testObjectSignatureWillThrowException(): void
    {
        $object = new stdClass();
        $header = [
            'version' => 1,
            'signature' => $object
        ];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Header must contain a 'signature' key with a string value.");
        $writer = new FileWriter('file.bin', $header, true);
    }

    public function testSignatureExceed6CharWillThrowException(): void
    {
        $header = [
            'version' => 1,
            'signature' => 'ABCDEFG'
        ];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Header 'signature' value must not exceed 6 characters.");
        $writer = new FileWriter('file.bin', $header, true);
    }

    public function testHeaderMissingVersionThrowExcecption(): void
    {
        $header = [
            'signature' => 'ABCD'
        ];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Header must contain a 'version' key with an integer value.");
        $writer = new FileWriter('file.bin', $header, true);
    }

    public function testObjectVersionWillThrowException(): void
    {
        $object = new stdClass();
        $header = [
            'version' => $object,
            'signature' => 'ABCDEF'
        ];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Header must contain a 'version' key with an integer value.");
        $writer = new FileWriter('file.bin', $header, true);
    }

    public function testStringVersionThrowException(): void
    {
        $header = [
            'signature' => 'ABCD',
            'version' => 'Hello'
        ];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Header must contain a 'version' key with an integer value.");
        $writer = new FileWriter('file.bin', $header, true);
    }    

    public function testFloatVersionThrowException(): void
    {
        $header = [
            'signature' => 'ABCD',
            'version' => 1.1
        ];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Header must contain a 'version' key with an integer value.");
        $writer = new FileWriter('file.bin', $header, true);
    }

    public function testArrayVersionThrowException(): void
    {
        $header = [
            'signature' => 'ABCD',
            'version' => []
        ];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Header must contain a 'version' key with an integer value.");
        $writer = new FileWriter('file.bin', $header, true);
    }

//------ Test writing file  ---------

    public function testWriteNewFileWithIsNewTrueWritesHeader(): void
    {
        $this->assertFileDoesNotExist($this->testFile);
 
        $writer = new TestableFileWriter($this->testFile, $this->validHeader, true);
        $writer->writeBinaryFile([['Hello', 123, 45.67]]);
 
        $this->assertSame(1, $writer->writeHeaderCallCount);
 
        $raw = file_get_contents($this->testFile);
        $this->assertStringStartsWith('MYFILE', $raw);
        $this->assertStringContainsString('Hello', $raw);
    }

    public function testWriteExistingFileWithIsNewFalseDoesNotWriteHeader(): void
    {
        $this->buildValidFile([['Init', 1, 0.0]]);
        $sizeBefore = filesize($this->testFile);
 
        $writer = new TestableFileWriter($this->testFile, $this->validHeader, false);
        $writer->writeBinaryFile([['World', -456, 89.01]]);
 
        $this->assertSame(0, $writer->writeHeaderCallCount);
        $this->assertGreaterThan($sizeBefore, filesize($this->testFile));
        $this->assertStringContainsString('World', file_get_contents($this->testFile));
    }

    public function testWriteNewFileWithIsNewFalseStillWritesHeader(): void
    {
        $this->assertFileDoesNotExist($this->testFile);
 
        $writer = new TestableFileWriter($this->testFile, $this->validHeader, false);
        $writer->writeBinaryFile([['Test', 10, 1.5]]);
 
        $this->assertSame(1, $writer->writeHeaderCallCount,
            'writeHeaderFile doit être appelé car le fichier n\'existait pas, peu importe isNew'
        );
        $this->assertStringStartsWith('MYFILE', file_get_contents($this->testFile));
    }

    public function testWriteExistingFileWithIsNewTrueDoesNotWriteHeader(): void
    {
        $this->buildValidFile([['Init', 1, 0.0]]);
        $this->assertFileExists($this->testFile);
 
        $writer = new TestableFileWriter($this->testFile, $this->validHeader, true);
        $writer->writeBinaryFile([['Extra', 99, 0.5]]);
 
        $this->assertSame(0, $writer->writeHeaderCallCount);
    }

    public function testWriteMultipleRecordsWritesAllData(): void
    {
        $writer = new FileWriter($this->testFile, $this->validHeader, true);
        $writer->writeBinaryFile([
            ['Alice', 30,    1.75],
            ['Bob',   -10,   2.01],
            ['Carol', 32000, 0.99],
        ]);
 
        $raw = file_get_contents($this->testFile);
        $this->assertStringStartsWith('MYFILE', $raw);
        $this->assertStringContainsString('Alice', $raw);
        $this->assertStringContainsString('Bob',   $raw);
        $this->assertStringContainsString('Carol', $raw);
    }

    public function testBinaryFormatIsCorrect(): void
    {
        $writer = new FileWriter($this->testFile, $this->validHeader, true);
        $writer->writeBinaryFile([['Hi', 5, 1.5]]);
 
        $raw    = file_get_contents($this->testFile);
        $offset = 0;
 
       
        $this->assertSame('MYFILE', substr($raw, $offset, 6));
        $offset += 6;
 
       
        $this->assertSame(1, unpack('V', substr($raw, $offset, 4))[1]);
        $offset += 4;
 
       
        $this->assertSame(self::TYPE_STRING, unpack('C', substr($raw, $offset, 1))[1]);
        $offset += 1;
        
        $strLen = unpack('v', substr($raw, $offset, 2))[1];
        $this->assertSame(2, $strLen);
        $offset += 2;
       
        $this->assertSame('Hi', substr($raw, $offset, $strLen));
        $offset += $strLen;
 
        
        $this->assertSame(self::TYPE_INT16, unpack('C', substr($raw, $offset, 1))[1]);
        $offset += 1;
        $this->assertSame(5, unpack('v', substr($raw, $offset, 2))[1]);
        $offset += 2;
 
        
        $this->assertSame(self::TYPE_FLOAT, unpack('C', substr($raw, $offset, 1))[1]);
        $offset += 1;
        $this->assertEqualsWithDelta(1.5, unpack('f', substr($raw, $offset, 4))[1], 0.0001);
    }

//------ Test reading file  ---------

    public function testReadFileWithWrongSignatureThrowsException(): void
    {
        $badHeader = ['signature' => 'BADsig', 'version' => 1];
        $writer    = new FileWriter($this->testFile, $badHeader, true);
        $writer->writeBinaryFile([['Test', 1, 1.0]]);
 
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Magic bytes do not match expected signature");
 
        $writer->readFile($this->testFile, $this->validHeader, $this->validStructure);
    }

    public function testReadFileWithWrongVersionThrowsException(): void
    {
        $writer = new FileWriter($this->testFile, $this->validHeader, true);
        $writer->writeBinaryFile([['Test', 1, 1.0]]);
 
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Version mismatch");
 
        $writer->readFile($this->testFile, ['signature' => 'MYFILE', 'version' => 99], $this->validStructure);
    }

    public function testReadFileWithUnknownTypeTagThrowsException(): void
    {
        $writer = new FileWriter($this->testFile, $this->validHeader, true);
        $writer->writeBinaryFile([['Test', 1, 1.0]]);
        $raw              = file_get_contents($this->testFile);
        $raw[6 + 4]       = chr(0xFF); // type tag inconnu
        file_put_contents($this->testFile, $raw);
 
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("Unknown tag type");
 
        $writer->readFile($this->testFile, $this->validHeader, $this->validStructure);
    }

    public function testReadFileWithSingleRecord(): void
    {
        $writer = new FileWriter($this->testFile, $this->validHeader, true);
        $writer->writeBinaryFile([['Alice', 30, 1.75]]);
 
        $result = $writer->readFile($this->testFile, $this->validHeader, $this->validStructure);
 
        $this->assertCount(1, $result);
        $this->assertSame('Alice', $result[0]['name']);
        $this->assertSame(30,      $result[0]['number']);
        $this->assertEqualsWithDelta(1.75, $result[0]['float'], 0.0001);
    }

    public function testReadFileWithMultipleRecords(): void
    {
        $writer = new FileWriter($this->testFile, $this->validHeader, true);
        $writer->writeBinaryFile([
            ['Alice', 30,  1.75],
            ['Bob',   -10, 2.01],
        ]);
 
        $result = $writer->readFile($this->testFile, $this->validHeader, $this->validStructure);
 
        $this->assertCount(2, $result);
        $this->assertSame('Alice', $result[0]['name']);
        $this->assertSame(30,      $result[0]['number']);
        $this->assertEqualsWithDelta(1.75, $result[0]['float'], 0.0001);
        $this->assertSame('Bob',   $result[1]['name']);
        $this->assertSame(-10,     $result[1]['number']);
        $this->assertEqualsWithDelta(2.01, $result[1]['float'], 0.0001);
    }

    public function testReadFileHandlesNegativeValues(): void
    {
        $writer = new FileWriter($this->testFile, $this->validHeader, true);
        $writer->writeBinaryFile([['Temp', -5, -3.14]]);
 
        $result = $writer->readFile($this->testFile, $this->validHeader, $this->validStructure);
 
        $this->assertSame(-5, $result[0]['number']);
        $this->assertEqualsWithDelta(-3.14, $result[0]['float'], 0.0001);
    }
    
/* Reading Header tests */
    public function testReadHeaderFileWillReturnHeaderValues(): void
    {
        $writer = new FileWriter($this->testFile, $this->validHeader, true);
        $writer->writeBinaryFile([['Temp', -5, -3.14]]);
        $header = $writer->readHeaderFile($this->testFile,$this->validHeader);
        $this->assertIsArray($header);
        $this->assertArrayHasKey('signature', $header);
        $this->assertArrayHasKey('version', $header);
        $this->assertSame($this->validHeader['signature'], $header['signature']);
        $this->assertSame($this->validHeader['version'], $header['version']);

    }
    

    private function buildValidFile(array $data): void
    {
        (new FileWriter($this->testFile, $this->validHeader, true))->writeBinaryFile($data);
    }
}

class TestableFileWriter extends FileWriter
{
    public int $writeHeaderCallCount = 0;
 
    protected function writeHeaderFile(mixed $file): bool
    {
        $this->writeHeaderCallCount++;
        return parent::writeHeaderFile($file);
    }
}