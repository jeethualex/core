<?php

namespace OpenDialogAi\MessageBuilder\Message;

class RichMessage extends BaseRichMessage
{
    public function getMarkUp()
    {
        $buttonMarkUp = $this->getButtonMarkUp();

        $imageMarkUp = $this->getImageMarkUp();

        return <<<EOT
<rich-message>
    <title>$this->title</title>
    <subtitle>$this->subtitle</subtitle>
    <text>$this->text</text>
    $buttonMarkUp
    $imageMarkUp
</rich-message>
EOT;
    }
}
