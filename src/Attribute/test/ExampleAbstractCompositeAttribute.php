<?php

namespace OpenDialogAi\Core\Attribute\test;

use OpenDialogAi\Core\Attribute\Composite\AbstractCompositeAttribute;

/**
 * Class ExampleAbstractCompositeAttribute
 *
 * @package OpenDialogAi\Core\Attribute\test
 */
class ExampleAbstractCompositeAttribute extends AbstractCompositeAttribute
{

    /**
     * @var string
     */
    protected $attributeCollectionType = ExampleAbstractAttributeCollection::class;
}
