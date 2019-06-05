<?php

namespace OpenDialogAi\Core\Utterances;

use OpenDialogAi\Core\Utterances\Exceptions\FieldNotSupported;

/**
 * Trigger utterances do not include text
 */
abstract class TriggerUtterance extends BaseUtterance
{
    const TYPE = 'trigger';

    /**
     * @inheritdoc
     */
    public function getText(): string
    {
        throw new FieldNotSupported('Text field is not supported by trigger utterances');
    }

    /**
     * @inheritdoc
     */
    public function setText(string $text): void
    {
        throw new FieldNotSupported('Text field is not supported by trigger utterances');
    }
}
