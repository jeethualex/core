<?php

namespace OpenDialogAi\ResponseEngine\Message\Webchat\Form;

class FormSelectElement extends FormElement
{
    /**
     * @var array The options for the select element [name => value]
     */
    private $options = [];

    /**
     * @param $name
     * @param $display
     * @param $required
     * @param $options
     * @param $defaultValue
     */
    public function __construct($name, $display, $required = false, $options = [], $defaultValue = '')
    {
        parent::__construct($name, $display, $required);

        $this->options = $options;
        $this->defaultValue = $defaultValue;
    }

    /**
     * @param $options
     * @return $this
     */
    public function setOptions($options)
    {
        $this->options = $options;
        return $this;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return parent::getData() + [
            'element_type' => 'select',
            'options' => $this->getOptions()
        ];
    }
}
