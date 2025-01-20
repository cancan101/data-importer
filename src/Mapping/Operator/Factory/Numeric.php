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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;

class Numeric extends AbstractOperator
{
    private bool $returnNullIfEmpty = false;

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return float|null
     */
    public function process($inputData, bool $dryRun = false)
    {
        if (is_array($inputData)) {
            $inputData = reset($inputData);
        }

        $floatValue = floatval($inputData);

        if ($this->returnNullIfEmpty && empty($floatValue)) {
            return null;
        }

        return $floatValue;
    }

    /**
     * @param string $inputType
     * @param int|null $index
     *
     * @return string
     *
     * @throws InvalidConfigurationException
     */
    public function evaluateReturnType(string $inputType, ?int $index = null): string
    {
        if (!in_array($inputType, [TransformationDataTypeService::DEFAULT_TYPE, TransformationDataTypeService::BOOLEAN])) {
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for numeric operator at transformation position %s", $inputType, $index));
        }

        return TransformationDataTypeService::NUMERIC;
    }

    /**
     * @param mixed $inputData
     *
     * @return mixed
     */
    public function generateResultPreview($inputData)
    {
        if ($this->returnNullIfEmpty && empty($inputData)) {
            return null;
        }

        return $inputData;
    }

    public function setSettings(array $settings): void
    {
        $this->returnNullIfEmpty = (bool) ($settings['returnNullIfEmpty'] ?? false);
    }
}
