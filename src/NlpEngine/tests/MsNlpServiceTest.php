<?php

namespace OpenDialogAi\NlpEngine\Tests;

use OpenDialogAi\Core\NlpEngine\MicrosoftRepository\MsClient;
use OpenDialogAi\Core\NlpEngine\MicrosoftRepository\MsLanguageEntity;
use OpenDialogAi\Core\NlpEngine\NlpEntities;
use OpenDialogAi\Core\NlpEngine\NlpLanguage;
use OpenDialogAi\Core\NlpEngine\NlpSentiment;
use OpenDialogAi\Core\NlpEngine\Service\MsNlpService;
use OpenDialogAi\Core\Tests\TestCase;

class MsNlpServiceTest extends TestCase
{
    /** @var \OpenDialogAi\Core\NlpEngine\Service\MsNlpService */
    private $msNlpService;

    /** @var \Mockery\LegacyMockInterface|\Mockery\MockInterface  */
    private $guzzleClientMock;

    /** @var \Mockery\LegacyMockInterface|\Mockery\MockInterface|\OpenDialogAi\Core\NlpEngine\MicrosoftRepository\MsClient  */
    private $clientMock;

    public function setUp(): void
    {
        parent::setUp();
        $this->guzzleClientMock = \Mockery::mock(app()->make('MsClient'));
        $this->app->instance('MsClient', $this->clientMock);
        $this->clientMock = \Mockery::mock(MsClient::class);
        $this->msNlpService = new MsNlpService($this->getTestStringForNlp(), $this->clientMock);
    }

    public function testItsInstatiatesCorrectServiceClass()
    {
        $this->assertInstanceOf(MsNlpService::class, $this->msNlpService);
    }

    public function testItGetsLanguageFromMs()
    {
        $this->clientMock->shouldReceive('getLanguage')->once()->andReturn($this->getTestResponse());
        $language = $this->msNlpService->getLanguage();

        $this->assertEquals($language->getInput(), $this->getTestStringForNlp());
        $this->assertEquals($language->getLanguageName(), 'English');
        $this->assertEquals($language->getIsoName(), 'en');
        $this->assertEquals($language->getScore(), 1.0);
    }

    public function testItGetsSentimentFromMs()
    {
        $this->clientMock->shouldReceive('getSentiment')->once()->andReturn($this->getSentimentTestResponse());
        $nlpSentiment = $this->msNlpService->getSentiment();

        $this->assertEquals($nlpSentiment->getInput(), $this->getTestStringForNlp());
        $this->assertEquals($nlpSentiment->getScore(), 0.98837846517562866);
    }

    public function testItGetsEntitiesFromMs()
    {
        $this->clientMock->shouldReceive('getEntities')->once()->andReturn($this->getEntitiesTestResponse());
        $nlpEntities = $this->msNlpService->getEntities();

        $this->assertInstanceOf(NlpEntities::class, $nlpEntities);
    }

    /**
     * @return string
     */
    public function getTestStringForNlp(): string
    {
        return 'Hello World.';
    }

    /**
     * @return \OpenDialogAi\Core\NlpEngine\NlpLanguage
     */
    private function getTestResponse(): NlpLanguage
    {
        $nplLanguage = new NlpLanguage();
        $nplLanguage->setInput($this->getTestStringForNlp());
        $nplLanguage->setLanguageName('English');
        $nplLanguage->setIsoName('en');
        $nplLanguage->setScore(1.0);
        return $nplLanguage;
    }

    /**
     * @return \OpenDialogAi\Core\NlpEngine\NlpSentiment
     */
    private function getSentimentTestResponse(): NlpSentiment
    {
        $nlpSentiment = new NlpSentiment();
        $nlpSentiment->setScore(0.98837846517562866);
        $nlpSentiment->setInput($this->getTestStringForNlp());

        return $nlpSentiment;
    }

    /**
     * @return \OpenDialogAi\Core\NlpEngine\NlpEntities
     */
    private function getEntitiesTestResponse(): NlpEntities
    {
        return new NlpEntities();
    }
}