<?php

namespace OpenDialogAi\OperationEngine\Operations;

use OpenDialogAi\OperationEngine\AbstractOperation;

class TimePassedGreaterThanOperation extends AbstractOperation
{
    static $name = 'time_passed_greater_than';

    public function execute()
    {
        $attribute = reset($this->attributes);

        // We are checking for type since the default behaviour is to return an empty string if an attribute
        // is not set, which would allow this operation to proceed.
        if (($attribute->getValue() === null) || !is_int($attribute->getValue())) {
            return false;
        }

        if ($this->parameters['value'] && (now()->timestamp - $this->parameters['value']) > $attribute->getValue()) {
            return true;
        }
        return false;
    }

    public static function getAllowedParameters(): array
    {
        return [
            'required' => [
                'value',
            ],
        ];
    }
}
