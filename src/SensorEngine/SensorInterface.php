<?php

namespace OpenDialogAi\SensorEngine;

use Illuminate\Http\Request;
use OpenDialogAi\Core\Utterances\UtteranceInterface;
use OpenDialogAi\SensorEngine\Exceptions\SensorNameNotSetException;

/**
 * This is a placeholder interface for what a sensor needs to do
 */
interface SensorInterface
{
    public function interpret(Request $request) : UtteranceInterface;
}
