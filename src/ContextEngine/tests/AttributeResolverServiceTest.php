<?php

namespace OpenDialogAi\ContextManager\Tests;

use OpenDialogAi\ContextEngine\AttributeResolver\AttributeResolver;
use OpenDialogAi\Core\Attribute\StringAttribute;
use OpenDialogAi\Core\Tests\TestCase;

class AttributeResolverServiceTest extends TestCase
{
    /** @var AttributeResolver */
    private $attributeResolver;

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->attributeResolver = $this->app->make(AttributeResolver::ATTRIBUTE_RESOLVER);
    }

    public function testContextServiceCreation()
    {
        $this->assertTrue($this->attributeResolver instanceof AttributeResolver);
    }

    public function testAccessToSupportedAttributes()
    {
        $supportedAttributes = $this->attributeResolver->getSupportedAttributes();

        $this->assertTrue(count($supportedAttributes) > 0);
        $this->assertArrayHasKey('user.name', $supportedAttributes);
    }

    public function testAttributeResolution()
    {
        $attribute = $this->attributeResolver->getAttributeFor('user.name', 'John Smith');

        $this->assertTrue($attribute instanceof StringAttribute);
        $this->assertTrue($attribute->getValue() == 'John Smith');
        $this->assertFalse($attribute->getValue() == 'Mario Rossi');
    }
}
