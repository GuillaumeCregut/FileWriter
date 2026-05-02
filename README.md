# FileWriter

A robust PHP library for writing and reading binary files with customizable headers and structured data formats. FileWriter enables you to efficiently manage binary data with type safety and version control.

## Features

- **Binary File Management**: Write and read binary files with a structured format
- **Custom Headers**: Define file signatures and versions for file validation
- **Multiple Data Types**: Support for strings, 16-bit integers, 32-bit integers, and floating-point numbers
- **Type Safety**: Automatic type detection and conversion for binary data
- **Append Support**: Create new files or append data to existing files
- **Data Validation**: Verify file signatures and versions before reading

## Installation

Since this library is hosted on GitHub, you need to configure Composer to use the VCS repository.

Add the following to your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/guillaumecregut/FileWriter"
        }
    ],
    "require": {
        "guillaumecregut/filewriter": "^1.0"
    }
}
```

Then install the library:

```bash
composer update
```

Alternatively, if you already have a composer.json, you can run:

```bash
composer require guillaumecregut/filewriter:^1.0
```

## Usage

### Basic Setup

To use FileWriter, you need to define a header with a signature and version:

```php
use Editiel98\FileWriter\FileWriter;

// Define header for your binary file
$header = [
    'signature' => 'MYAPP',  // Up to 6 characters
    'version'   => 1         // Integer version number
];

// Create a FileWriter instance
$writer = new FileWriter('data.bin', $header, true); // true = create new file
```

### Writing Binary Files

The `writeBinaryFile()` method writes structured data to a binary file:

```php
// Prepare data as an array of records
$data = [
    [
        'John Doe',      // String
        25,              // Integer (automatically stored as INT16 or INT32)
        175.5            // Float
    ],
    [
        'Jane Smith',
        30,
        162.3
    ]
];

// Write to file
$writer->writeBinaryFile($data);
```

Each record in the data array is written sequentially to the binary file. Values are automatically converted to their binary representation.

### Reading Binary Files

The `readFile()` method reads binary files and parses them into structured data:

```php
// Define the structure of your data
// The order of keys determines the order of fields when reading
$structure = [
    'name'   => 'string',
    'age'    => 'int',
    'height' => 'float'
];

// Read the file
$data = $writer->readFile('data.bin', $header, $structure);

// Output: Array of records matching the structure
foreach ($data as $record) {
    echo $record['name'] . " - " . $record['age'] . " years old\n";
}
```

### Reading File Headers

To verify or retrieve only the header information (signature and version) without reading the entire file data, use the `readHeaderFile()` method:

```php
// Read just the header from the file
$header = $writer->readHeaderFile('data.bin', [
    'signature' => 'MYAPP',
    'version'   => 1
]);

// Verify the file signature and version
if ($header['signature'] === 'MYAPP' && $header['version'] === 1) {
    echo "File is valid!\n";
} else {
    echo "File format mismatch!\n";
}
```

This is useful for quickly validating file format before processing the entire file data.

### Appending to Existing Files

To append data to an existing file, set the third parameter to `false`:

```php
$writer = new FileWriter('data.bin', $header, false); // false = append to existing
$writer->writeBinaryFile($newData);
```

The class automatically detects if a file exists and appends data without rewriting the header.

## API Reference

### Constructor

```php
FileWriter::__construct(string $filename, array $header, bool $isNew = true)
```

**Parameters:**
- `$filename` (string): Path to the binary file
- `$header` (array): File header containing 'signature' and 'version' keys
  - `signature` (string): File identifier (max 6 characters)
  - `version` (int): File format version number
- `$isNew` (bool): If true, creates a new file; if false, appends to existing file

### Methods

#### writeBinaryFile()

```php
public function writeBinaryFile(array $data): void
```

Writes binary data to a file. Creates a new file with header if it doesn't exist, or appends to existing file.

**Parameters:**
- `$data` (array): Array of records, where each record is an array of values (string, int, or float)

**Throws:** `Exception` if file cannot be opened or header cannot be written

#### readFile()

```php
public function readFile(string $file, array $header, array $structure): array
```

Reads binary data from a file and returns parsed records.

**Parameters:**
- `$file` (string): Path to the binary file to read
- `$header` (array): Expected file header for validation
- `$structure` (array): Map of field names to their types

**Returns:** Array of records with fields named according to the structure

**Throws:** `Exception` if file cannot be opened, header doesn't match, or version mismatch occurs

#### readHeaderFile()

```php
public function readHeaderFile(string $file, array $header): array
```

Reads only the header information from a file without processing the data section. Useful for validating file format before full parsing.

**Parameters:**
- `$file` (string): Path to the binary file to read
- `$header` (array): Expected file header for validation

**Returns:** Array containing:
- `signature` (string): The file signature from the file
- `version` (int): The file version number

**Throws:** `Exception` if file cannot be opened or header bytes cannot be read

## File Format Specification

FileWriter uses a structured binary format:

### Header (written once per file)
- **Signature**: Variable length string (1-6 bytes) identifying the file type
- **Version**: 32-bit unsigned integer (4 bytes) in little-endian format

### Data Records
Each record consists of typed fields:

| Type | Identifier | Size | Format |
|------|-----------|------|--------|
| String | 0x01 | Variable | 1 byte (type) + 2 bytes (length) + n bytes (data) |
| INT16 | 0x02 | Fixed | 1 byte (type) + 2 bytes (value, little-endian) |
| INT32 | 0x03 | Fixed | 1 byte (type) + 4 bytes (value, little-endian) |
| FLOAT | 0x04 | Fixed | 1 byte (type) + 4 bytes (IEEE 754 single precision) |

### Example Binary Layout

```
SIGNATURE (up to 6 bytes) | VERSION (4 bytes) | RECORD1 DATA | RECORD2 DATA | ...
```

## Error Handling

FileWriter throws exceptions for common error conditions:

- **InvalidArgumentException**: Invalid header format (missing signature/version, signature too long, wrong type)
- **RuntimeException**: File I/O errors (cannot open file, cannot read/write)
- **UnexpectedValueException**: Data reading error (unknown type, signature mismatch, version mismatch)