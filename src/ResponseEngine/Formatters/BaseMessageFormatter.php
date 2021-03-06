<?php

namespace OpenDialogAi\ResponseEngine\Formatters;

use OpenDialogAi\Core\Traits\HasName;

abstract class BaseMessageFormatter implements MessageFormatterInterface
{
    use HasName;

    public static $name = 'base';
}
