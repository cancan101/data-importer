<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter;

use Pimcore\Bundle\DataImporterBundle\Preview\Model\PreviewData;
use Symfony\Component\Mime\MimeTypes;

class CsvFileInterpreter extends AbstractInterpreter
{
    private const UTF8_BOM = "\xEF\xBB\xBF";

    /**
     * @var bool
     */
    protected $skipFirstRow;

    protected bool $saveHeaderName;

    /**
     * @var string
     */
    protected $delimiter;

    /**
     * @var string
     */
    protected $enclosure;

    /**
     * @var string
     */
    protected $escape;

    protected function doInterpretFileAndCallProcessRow(string $path): void
    {
        if (($handle = fopen($path, 'r')) !== false) {
            $this->skipByteOrderMark($handle);

            $header = null;
            if ($this->skipFirstRow) {
                //load first row and ignore it
                $data = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape);
                if ($this->saveHeaderName) {
                    $header = $data;
                }
            }

            while (($data = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape)) !== false) {
                if ($header !== null) {
                    $data = array_combine($header, $data);
                }
                $this->processImportRow($data);
            }
            fclose($handle);
        }
    }

    public function setSettings(array $settings): void
    {
        $this->skipFirstRow = $settings['skipFirstRow'] ?? false;
        $this->saveHeaderName = $settings['saveHeaderName'] ?? false;
        $this->delimiter = $settings['delimiter'] ?? ',';
        $this->enclosure = $settings['enclosure'] ?? '"';
        $this->escape = $settings['escape'] ?? '\\';
    }

    public function fileValid(string $path, bool $originalFilename = false): bool
    {
        if ($originalFilename) {
            $filename = $path;
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if ($ext !== 'csv') {
                return false;
            }
        }

        // csv that has html tags might be recognized as text/html
        $csvMimes = ['text/html', 'text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'text/plain'];
        $mimeTypes = new MimeTypes();
        $mime = $mimeTypes->guessMimeType($path);

        return in_array($mime, $csvMimes);
    }

    public function previewData(string $path, int $recordNumber = 0, array $mappedColumns = []): PreviewData
    {
        $previewData = [];
        $columns = [];
        $readRecordNumber = -1;
        $header = null;

        if ($this->fileValid($path) && ($handle = fopen($path, 'r')) !== false) {
            $this->skipByteOrderMark($handle);

            if ($this->skipFirstRow) {
                //load first row and ignore it
                $data = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape);

                if ($this->saveHeaderName) {
                    $header = $data;
                    foreach ($data as $index => $columnHeader) {
                        $columns[$columnHeader] = trim($columnHeader);
                    }
                } else {
                    foreach ($data as $index => $columnHeader) {
                        $columns[$index] = trim($columnHeader) . " [$index]";
                    }
                }
            }

            $previousData = null;
            while ($readRecordNumber < $recordNumber && ($data = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape)) !== false) {
                if ($header !== null) {
                    $data = array_combine($header, $data);
                }
                $previousData = $data;
                $readRecordNumber++;
            }

            if (empty($data)) {
                $data = $previousData;
            }

            foreach ($data as $index => $columnData) {
                $previewData[$index] = $columnData;
            }

            fclose($handle);
        }

        $previewDataColumns = array_keys($previewData);
        if (empty($columns)) {
            $columns = $previewDataColumns;
        } elseif (count($columns) < count($previewDataColumns)) {
            foreach ($previewDataColumns as $columnIdx) {
                if (isset($columns[$columnIdx]) === false) {
                    $columns[$columnIdx] = "[$columnIdx]";
                }
            }
        }

        return new PreviewData($columns, $previewData, $readRecordNumber, $mappedColumns);
    }

    private function skipByteOrderMark($handle): void
    {
        $bom = fread($handle, strlen(self::UTF8_BOM));
        if (0 !== strncmp(self::UTF8_BOM, $bom, strlen(self::UTF8_BOM))) {
            rewind($handle);
        }
    }
}
