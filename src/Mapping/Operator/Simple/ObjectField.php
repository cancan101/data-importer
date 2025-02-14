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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Model\Element\ElementInterface;


class ObjectField extends AbstractOperator
{
    private string $attribute;

    private string $forwardParameter;

    public function setSettings(array $settings): void
    {
        // are there better defautls than empty string?
        $this->attribute = $settings['attribute'] ?? '';
        $this->forwardParameter = $settings['forwardParameter'] ?? '';
    }

    public function process(mixed $inputData, bool $dryRun = false): mixed
    {
        if (!$inputData instanceof ElementInterface) {
            // is this how to handle type mismatch?
            return null;
        }

        if(!$this->attribute){
            // is this how to handle no attrinute
            return null;
        }

        // better to pull full logic from ObjectFieldGetter / AnyGetter
        $getter = 'get' . ucfirst($this->attribute);

        if (!method_exists($inputData, $getter)) {
            // is there a better default here?
            return null;
        }

        if ($this->forwardParameter) {
            $value = $inputData->$getter($this->forwardParameter);
        } else {
            $value = $inputData->$getter();
        }

        // this expands paths
        if ($value instanceof ElementInterface) {
            $value = $value->getFullPath();
        }

        return $value;
    }

    /**
     *
     * @throws InvalidConfigurationException
     */
    public function evaluateReturnType(string $inputType, int $index = null): string
    {
        if ($inputType === TransformationDataTypeService::DATA_OBJECT) {
            // for numerics?
            return TransformationDataTypeService::DEFAULT_TYPE;
        } else {
            throw new InvalidConfigurationException(
                sprintf(
                    "Unsupported input type '%s' for load data object operator at transformation position %s",
                    $inputType,
                    $index
                )
            );
        }
    }
}
