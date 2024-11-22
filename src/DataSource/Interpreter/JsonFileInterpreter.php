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

use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
use Pimcore\Bundle\DataImporterBundle\Preview\Model\PreviewData;

class JsonFileInterpreter extends AbstractInterpreter
{
    protected string $path;

    /**
     * @var array|null
     */
    protected $cachedContent = null;

    /**
     * @var string|null
     */
    protected $cachedFilePath = null;

    protected function loadData(string $path): array
    {
        if ($this->cachedFilePath === $path && !empty($this->cachedContent)) {
            $content = file_get_contents($path);
            $data = json_decode($this->prepareContent($content), true);

            if (!empty($this->path)) {
                return $this->getValueFromPath($this->path, $data);
            }

            return $data;
        } else {
            return $this->cachedContent;
        }
    }

    protected function doInterpretFileAndCallProcessRow(string $path): void
    {
        $data = $this->loadData($path);

        foreach ($data as $dataRow) {
            $this->processImportRow($dataRow);
        }
    }

    public function setSettings(array $settings): void
    {
        $this->path = $settings['path'];
    }

    /**
     * remove BOM bytes to have a proper UTF-8
     *
     * @param string $content
     *
     * @return string
     */
    protected function prepareContent($content)
    {
        $UTF8_BOM = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $first3 = substr($content, 0, 3);
        if ($first3 === $UTF8_BOM) {
            $content = substr($content, 3);
        }

        return $content;
    }

    public function fileValid(string $path, bool $originalFilename = false): bool
    {
        $this->cachedContent = null;
        $this->cachedFilePath = null;

        if ($originalFilename) {
            $filename = $path;
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if ($ext !== 'json') {
                return false;
            }
        }

        $content = file_get_contents($path);

        $data = json_decode($this->prepareContent($content), true);

        if (!$this->isJsonValid($data)) {
            $this->applicationLogger->error('Reading file ERROR: ' . json_last_error_msg(), [
                'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName
            ]);

            return false;
        }

        $this->cachedContent = $data;
        $this->cachedFilePath = $path;

        return true;
    }

    public function previewData(string $path, int $recordNumber = 0, array $mappedColumns = []): PreviewData
    {
        $previewData = [];
        $columns = [];
        $readRecordNumber = 0;

        if ($this->fileValid($path)) {
            $data = $this->loadData($path);

            $previewDataRow = $data[$recordNumber] ?? null;

            if (empty($previewDataRow)) {
                $previewDataRow = end($data);
                $readRecordNumber = count($data) - 1;
            } else {
                $readRecordNumber = $recordNumber;
            }

            foreach ($previewDataRow as $index => $columnData) {
                $previewData[$index] = $columnData;
            }

            $keys = array_keys($previewData);
            $columns = array_combine($keys, $keys);
        }

        return new PreviewData($columns, $previewData, $readRecordNumber, $mappedColumns);
    }

    /**
     * Returns false if any errors occurred during the last JSON decoding or
     * if the user-specified path wasn't found in the array `$data`
     */
    private function isJsonValid(?array $data): bool
    {
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        if (!empty($this->path) && !$this->isValueOnPath($this->path, $data)) {
            return false;
        }

        return true;
    }

    /**
     * Checks if a value exists at the specified path in a nested array `$data`.
     *
     * This method takes a path string (in the form of '/key1/key2/.../keyN') and traverses
     * the provided array `$data` to determine if the specified path exists.
     *
     * @param string $path The path to check, represented as a string with keys separated by slashes.
     * @param array $data The associative array to search through.
     *
     * @return bool Returns true if the entire path exists in the data array
     *              false if any part of the path does not exist or if the value on the path isn't array
     */
    private function isValueOnPath(string $path, array $data): bool
    {
        $pathParts = explode('/', trim($path, '/'));

        foreach ($pathParts as $pathPart) {
            if (isset($data[$pathPart])) {
                $data = $data[$pathPart];
            } else {
                return false;
            }
        }

        if (!is_array($data)) {
            return false;
        }

        return true;
    }

    /**
     * Returns a value from the specified path in a nested array `$data`.
     * Validation is done by the function `JsonFileInterpreter::isValueOnPath`
     */
    private function getValueFromPath(string $path, array $data): array
    {
        $pathParts = explode('/', trim($path, '/'));

        foreach ($pathParts as $pathPart) {
            $data = $data[$pathPart];
        }

        return $data;
    }
}
