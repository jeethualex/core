<?php

namespace OpenDialogAi\ContextEngine\Contexts\Intent;

use OpenDialogAi\ContextEngine\ContextManager\AbstractContext;
use OpenDialogAi\Core\Attribute\AttributeInterface;

class IntentContext extends AbstractContext
{
    public const INTENT_CONTEXT = '_intent';

    public function __construct()
    {
        parent::__construct(self::INTENT_CONTEXT);
    }

    public function refresh(): void
    {
        /** @var AttributeInterface $attribute */
        foreach ($this->getAttributes() as $attribute) {
            $this->removeAttribute($attribute->getId());
        }
    }
}
