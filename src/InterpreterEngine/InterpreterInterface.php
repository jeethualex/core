<?php

namespace OpenDialogAi\InterpreterEngine;

use OpenDialogAi\Core\Conversation\Intent;
use OpenDialogAi\Core\Utterances\Exceptions\FieldNotSupported;
use OpenDialogAi\Core\Utterances\UtteranceInterface;
use OpenDialogAi\InterpreterEngine\Exceptions\InterpreterNameNotSetException;

interface InterpreterInterface
{
    /**
     * Returns the name of the interpreter. The name should be in the format interpreter.{namespace}.{name}
     * eg interpreter.core.hello
     *
     * @return string
     * @throws InterpreterNameNotSetException
     */
    public static function getName() : string;

    /**
     * Interprets an utterance and returns all matching intents in an array
     *
     * @param UtteranceInterface $utterance
     * @return Intent[]
     * @throws FieldNotSupported
     */
    public function interpret(UtteranceInterface $utterance) : array;
}
