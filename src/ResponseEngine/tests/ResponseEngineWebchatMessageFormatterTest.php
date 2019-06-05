<?php

namespace OpenDialogAi\ResponseEngine\Tests;

use OpenDialogAi\Core\Tests\TestCase;
use OpenDialogAi\ResponseEngine\Message\WebChatMessageFormatter;

class ResponseEngineWebchatMessageFormatterTest extends TestCase
{
    public function testEmptyMessage()
    {
        $markup = '<message disable_text="1"><empty-message></empty-message></message>';
        $formatter = new WebChatMessageFormatter;
        $messages = $formatter->getMessages($markup);
        $this->assertEquals(true, $messages[0]->isEmpty());
        $this->assertEquals(1, $messages[0]->getData()['disable_text']);

        $markup = '<message disable_text="0"><empty-message></empty-message></message>';
        $formatter = new WebChatMessageFormatter;
        $messages = $formatter->getMessages($markup);
        $this->assertEquals(true, $messages[0]->isEmpty());
        $this->assertEquals(0, $messages[0]->getData()['disable_text']);
    }

    public function testTextMessage()
    {
        $markup = '<message disable_text="1"><text-message>hi there</text-message></message>';
        $formatter = new WebChatMessageFormatter;
        $messages = $formatter->getMessages($markup);
        $this->assertEquals('hi there', $messages[0]->getText());
        $this->assertEquals(1, $messages[0]->getData()['disable_text']);

        $markup = <<<EOT
<message disable_text="0">
  <text-message>
    hi there
  </text-message>
</message>
EOT;

        $formatter = new WebChatMessageFormatter;
        $messages = $formatter->getMessages($markup);
        $this->assertEquals('hi there', $messages[0]->getText());
        $this->assertEquals(0, $messages[0]->getData()['disable_text']);
    }

    public function testImageMessage()
    {
        $markup = '<message disable_text="1"><image-message link_new_tab="1"><link>https://www.opendialog.ai</link><src>https://www.opendialog.ai/assets/images/logo.svg</src></image-message></message>';
        $formatter = new WebChatMessageFormatter;
        $messages = $formatter->getMessages($markup);
        $message = $messages[0];
        $this->assertEquals('https://www.opendialog.ai', $message->getImgLink());
        $this->assertEquals('https://www.opendialog.ai/assets/images/logo.svg', $message->getImgSrc());
        $this->assertEquals(true, $message->getLinkNewTab());
        $this->assertEquals(1, $message->getData()['disable_text']);

        $markup = <<<EOT
<message disable_text="0">
  <image-message link_new_tab="0">
    <link>
      https://www.opendialog.ai
    </link>
    <src>
      https://www.opendialog.ai/assets/images/logo.svg
    </src>
  </image-message>
</message>
EOT;

        $formatter = new WebChatMessageFormatter;
        $messages = $formatter->getMessages($markup);
        $message = $messages[0];
        $this->assertEquals('https://www.opendialog.ai', $message->getImgLink());
        $this->assertEquals('https://www.opendialog.ai/assets/images/logo.svg', $message->getImgSrc());
        $this->assertEquals(false, $message->getLinkNewTab());
        $this->assertEquals(0, $message->getData()['disable_text']);
    }

    public function testButtonMessage()
    {
        $markup = '<message disable_text="1"><button-message clear_after_interaction="1"><button><text>Yes</text><callback>callback_yes</callback><value>true</value></button><button><text>No</text><callback>callback_no</callback><value>false</value></button></button-message></message>';
        $formatter = new WebChatMessageFormatter;
        $messages = $formatter->getMessages($markup);
        $message = $messages[0];

        $expectedOutput = [
            [
                'text' => 'Yes',
                'callback_id' => 'callback_yes',
                'value' => 'true',
            ],
            [
                'text' => 'No',
                'callback_id' => 'callback_no',
                'value' => 'false',
            ],
        ];

        $this->assertEquals(true, $message->getData()['clear_after_interaction']);
        $this->assertEquals(true, $message->getData()['disable_text']);
        $this->assertEquals($expectedOutput, $message->getButtonsArray());

        $markup = <<<EOT
<message disable_text="0">
  <button-message clear_after_interaction="0">
    <button>
      <text>
        Yes
      </text>
      <callback>
        callback_yes
      </callback>
      <value>
        true
      </value>
    </button>
    <button>
      <text>
        No
      </text>
      <callback>
        callback_no
      </callback>
      <value>
        false
      </value>
    </button>
  </button-message>
</message>
EOT;

        $formatter = new WebChatMessageFormatter;
        $messages = $formatter->getMessages($markup);
        $message = $messages[0];

        $expectedOutput = [
            [
                'text' => 'Yes',
                'callback_id' => 'callback_yes',
                'value' => 'true',
            ],
            [
                'text' => 'No',
                'callback_id' => 'callback_no',
                'value' => 'false',
            ],
        ];

        $this->assertEquals(false, $message->getData()['clear_after_interaction']);
        $this->assertEquals(false, $message->getData()['disable_text']);
        $this->assertEquals($expectedOutput, $message->getButtonsArray());
    }

    public function testRichMessage1()
    {
        $markup = <<<EOT
<message disable_text="0">
  <rich-message>
    <title>Message Title</title>
    <subtitle>This is a subtitle</subtitle>
    <text>Here is a bit of text about this thing</text>
    <button>
      <text>Test</text>
      <tab_switch>false</tab_switch>
      <callback>callback</callback>
      <value>value</value>
      <link>https://www.opendialog.ai</link>
    </button>
    <image>
      <src>https://www.opendialog.ai/assets/images/logo.svg</src>
      <url new_tab="true">https://www.opendialog.ai</url>
    </image>
  </rich-message>
</message>
EOT;
        $formatter = new WebChatMessageFormatter;
        $messages = $formatter->getMessages($markup);
        $message = $messages[0];

        $expectedOutput = [
            'title' => 'Message Title',
            'subtitle' => 'This is a subtitle',
            'text' => 'Here is a bit of text about this thing',
            'button' => [
                'text' => 'Test',
                'tab_switch' => false,
                'callback' => 'callback',
                'value' => 'value',
                'link' => 'https://www.opendialog.ai',
            ],
            'image' => [
                'src' => 'https://www.opendialog.ai/assets/images/logo.svg',
                'url' => 'https://www.opendialog.ai',
                'link_new_tab' => true,
            ],
        ];

        $this->assertEquals(false, $message->getData()['disable_text']);
        $this->assertArraySubset($expectedOutput, $message->getData());
    }

    public function testRichMessage2()
    {
        $markup = <<<EOT
<message disable_text="0">
  <rich-message>
    <title>Message Title</title>
    <subtitle>This is a subtitle</subtitle>
    <text>Here is a bit of text about this thing</text>
    <button>
      <text>Test</text>
      <tab_switch>false</tab_switch>
      <callback>callback</callback>
      <value>value</value>
      <link>https://www.opendialog.ai</link>
    </button>
  </rich-message>
</message>
EOT;
        $formatter = new WebChatMessageFormatter;
        $messages = $formatter->getMessages($markup);
        $message = $messages[0];

        $expectedOutput = [
            'title' => 'Message Title',
            'subtitle' => 'This is a subtitle',
            'text' => 'Here is a bit of text about this thing',
            'button' => [
                'text' => 'Test',
                'tab_switch' => false,
                'callback' => 'callback',
                'value' => 'value',
                'link' => 'https://www.opendialog.ai',
            ],
        ];

        $this->assertEquals(false, $message->getData()['disable_text']);
        $this->assertArraySubset($expectedOutput, $message->getData());
    }

    public function testRichMessage3()
    {
        $markup = <<<EOT
<message disable_text="0">
  <rich-message>
    <title>Message Title</title>
    <subtitle>This is a subtitle</subtitle>
    <text>Here is a bit of text about this thing</text>
    <image>
      <src>https://www.opendialog.ai/assets/images/logo.svg</src>
      <url new_tab="true">https://www.opendialog.ai</url>
    </image>
  </rich-message>
</message>
EOT;
        $formatter = new WebChatMessageFormatter;
        $messages = $formatter->getMessages($markup);
        $message = $messages[0];

        $expectedOutput = [
            'title' => 'Message Title',
            'subtitle' => 'This is a subtitle',
            'text' => 'Here is a bit of text about this thing',
            'image' => [
                'src' => 'https://www.opendialog.ai/assets/images/logo.svg',
                'url' => 'https://www.opendialog.ai',
                'link_new_tab' => true,
            ],
        ];

        $this->assertEquals(false, $message->getData()['disable_text']);
        $this->assertArraySubset($expectedOutput, $message->getData());
    }
}
