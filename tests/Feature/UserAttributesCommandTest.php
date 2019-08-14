<?php

namespace OpenDialogAi\Core\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use OpenDialogAi\Core\Controllers\OpenDialogController;
use OpenDialogAi\Core\Tests\TestCase;
use OpenDialogAi\Core\Tests\Utils\UtteranceGenerator;
use OpenDialogAi\Core\UserAttributes;

class UserAttributesCommandTest extends TestCase
{
    public function testCachingAttributes()
    {
        $this->publishConversation($this->conversation4());

        $utterance = UtteranceGenerator::generateTextUtterance('Test message');

        $controller = resolve(OpenDialogController::class);

        $controller->runConversation($utterance);

        Artisan::call('attributes:dump');

        $attributes = UserAttributes::all();

        $userId = $utterance->getUser()->getId();

        $this->assertCount(1, $attributes->where('attribute', 'id')->where('value', $userId));
    }
}
