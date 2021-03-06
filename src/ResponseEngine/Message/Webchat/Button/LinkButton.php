<?php

namespace OpenDialogAi\ResponseEngine\Message\Webchat\Button;

class LinkButton extends BaseButton
{
    protected $link = null;

    protected $linkNewTab = true;

    /**
     * @param $text
     * @param $link
     * @param bool $linkNewTab
     * @param bool $display
     * @param string $type
     */
    public function __construct($text, $link, $linkNewTab = false, $display = true, $type = "")
    {
        $this->text = $text;
        $this->link = $link;
        $this->linkNewTab = $linkNewTab;
        $this->display = $display;
        $this->type = $type;
    }

    /**
     * @param $link
     * @return $this
     */
    public function setLink($link)
    {
        $this->link = $link;
        return $this;
    }

    /**
     * @param $linkNewTab
     * @return $this
     */
    public function setLinkNewTab($linkNewTab)
    {
        $this->linkNewTab = $linkNewTab;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getLink()
    {
        return $this->link;
    }

    /**
     * @return bool
     */
    public function getLinkNewTab()
    {
        return $this->linkNewTab;
    }

    public function getData()
    {
        return parent::getData() + [
            'link' => $this->getLink(),
            'link_new_tab' => $this->getLinkNewTab(),
        ];
    }
}
