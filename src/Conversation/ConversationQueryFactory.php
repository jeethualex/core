<?php


namespace OpenDialogAi\Core\Conversation;


use OpenDialogAi\Core\Graph\DGraph\DGraphClient;
use OpenDialogAi\Core\Graph\DGraph\DGraphQuery;

/**
 * Helper methods for forming queries to extract information from Dgraph.
 */
class ConversationQueryFactory
{
    public static function getConversationTemplateIds(DGraphClient $client)
    {
        $dGraphQuery = new DGraphQuery();

        $dGraphQuery->eq(Model::EI_TYPE, Model::CONVERSATION_TEMPLATE)
            ->setQueryGraph([
               MODEL::UID,
               MODEL::ID
            ]);

        $response = $client->query($dGraphQuery)->getData();
        return $response;
    }


    /**
     * This queries Dgraph and retrieves a rebuilt Conversation Object.
     * @param string $conversationUid
     * @param DGraphClient $client
     * @return Conversation
     */
    public static function getConversationFromDgraph(string $conversationUid, DGraphClient $client, $clone = false)
    {
        $dGraphQuery = new DGraphQuery();

        $dGraphQuery->uid($conversationUid)
            ->setQueryGraph([
                Model::UID,
                Model::ID,
                Model::EI_TYPE,
                Model::HAS_OPENING_SCENE => self::getSceneGraph(),
                Model::HAS_SCENE => self::getSceneGraph()
            ]);

        $response = $client->query($dGraphQuery)->getData()[0];
        return self::buildConversationFromDgraphData($response, $clone);
    }

    /**
     * @return array
     */
    private static function getSceneGraph()
    {
        return [
            Model::UID,
            Model::ID,
            Model::HAS_USER_PARTICIPANT => self::getParticipantGraph(),
            Model::HAS_BOT_PARTICIPANT => self::getParticipantGraph()
        ];
    }

    /**
     * @return array
     */
    private static function getParticipantGraph()
    {
        return [
            Model::UID,
            Model::ID,
            Model::SAYS => self::getIntentGraph(),
            Model::SAYS_ACROSS_SCENES => self::getIntentGraph(),
            Model::LISTENS_FOR => self::getIntentGraph(),
            Model::LISTENS_FOR_ACROSS_SCENES => self::getIntentGraph(),
        ];
    }

    /**
     * @return array
     */
    private static function getIntentGraph()
    {
        return [
            Model::UID,
            Model::ID,
            Model::ORDER,
            Model::COMPLETES,
            Model::CAUSES_ACTION => self::getActionGraph(),
            Model::HAS_INTERPRETER => self::getInterpreterGraph(),
            Model::LISTENED_BY_FROM_SCENES => [
                Model::UID,
                Model::ID,
                Model::USER_PARTICIPATES_IN => [
                    Model::ID,
                ],
                Model::BOT_PARTICIPATES_IN => [
                    Model::ID,
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    private static function getActionGraph()
    {
        return [
            Model::UID,
            Model::ID,
        ];
    }

    /**
     * @return array
     */
    private static function getInterpreterGraph()
    {
        return [
            Model::UID,
            Model::ID
        ];
    }

    /**
     * @param array $data
     * @param bool $clone
     * @return Conversation
     */
    private static function buildConversationFromDgraphData(array $data, bool $clone = false)
    {
        $cm = new ConversationManager($data[Model::ID]);
        $clone ? false : $cm->getConversation()->setUid($data[Model::UID]);
        $cm->getConversation()->setConversationType($data[Model::EI_TYPE]);

        // First create all the scenes
        self::createScenesFromDgraphData($cm, $data);

        // Now populate the scenes with data.
        self::createSceneFromDgraphData($cm, $data[Model::HAS_OPENING_SCENE][0], $clone);

        // Cycle through all the other scenes and set those up as well.
        if (isset($data[Model::HAS_SCENE])) {
            foreach ($data[Model::HAS_SCENE] as $scene) {
                self::createSceneFromDgraphData($cm, $scene, $clone);
            }
        }

        return $cm->getConversation();
    }

    /**
     * @param ConversationManager $cm
     * @param $data
     */
    private static function createScenesFromDgraphData(ConversationManager $cm, $data)
    {
        foreach ($data[Model::HAS_OPENING_SCENE] as $openingScene) {
            $cm->createScene($openingScene[Model::ID], true);
        }

        if (isset($data[Model::HAS_SCENE])) {
            foreach ($data[Model::HAS_SCENE] as $scene) {
                $cm->createScene($scene[Model::ID], false);
            }
        }
    }

    /**
     * @param ConversationManager $cm
     * @param $data
     * @param bool $opening
     * @param bool $clone
     */
    private static function createSceneFromDgraphData(ConversationManager $cm, $data, bool $clone = false)
    {
        $scene = $cm->getScene($data[Model::ID]);
        $clone ? false : $scene->setUid($data[Model::UID]);
        $clone ? false: $scene->getUser()->setUid($data[Model::HAS_USER_PARTICIPANT][0][Model::UID]);
        $clone ? false: $scene->getBot()->setUid($data[Model::HAS_BOT_PARTICIPANT][0][Model::UID]);

        self::updateParticipantFromDgraphData(
            $scene->getId(), $scene->getUser(), $cm, $data[Model::HAS_USER_PARTICIPANT][0], $clone
        );

        self::updateParticipantFromDgraphData(
            $scene->getId(), $scene->getBot(), $cm, $data[Model::HAS_BOT_PARTICIPANT][0], $clone
        );
    }

    /**
     * @param $sceneId
     * @param Participant $participant
     * @param ConversationManager $cm
     * @param $data
     * @param bool $clone
     */
    private static function updateParticipantFromDgraphData(
        $sceneId,
        Participant $participant,
        ConversationManager $cm,
        $data,
        bool $clone = false
    ) {
        if (isset($data[Model::SAYS])) {
            foreach ($data[Model::SAYS] as $intentData) {
                $intent = new Intent($intentData[Model::ID]);
                $clone ? false : $intent->setUid($intentData[Model::UID]);
                $intent->setAttribute(Model::COMPLETES, $intentData[MODEL::COMPLETES]);
                if (isset($intentData[Model::CAUSES_ACTION])) {
                    $action = new Action($intentData[Model::CAUSES_ACTION][0][Model::ID]);
                    $clone ? false : $action->setUid($intentData[Model::CAUSES_ACTION][0][Model::UID]);
                    $intent->addAction($action);
                }
                if (isset($intentData[Model::HAS_INTERPRETER])) {
                    $interpreter = new Interpreter($intentData[Model::HAS_INTERPRETER][0][Model::ID]);
                    $clone ? false : $interpreter->setUid($intentData[Model::HAS_INTERPRETER][0][Model::UID]);
                    $intent->addInterpreter($interpreter);
                }

                if ($participant->isUser()) {
                    $cm->userSaysToBot($sceneId, $intent, $intentData[Model::ORDER]);
                }

                if ($participant->isBot()) {
                    $cm->botSaysToUser($sceneId, $intent, $intentData[Model::ORDER]);
                }
            }
        }
        if (isset($data[Model::SAYS_ACROSS_SCENES])) {
            foreach ($data[Model::SAYS_ACROSS_SCENES] as $intentData) {
                $intent = new Intent($intentData[Model::ID]);
                $clone ? false : $intent->setUid($intentData[Model::UID]);
                $intent->setAttribute(Model::COMPLETES, $intentData[MODEL::COMPLETES]);
                if (isset($intentData[Model::CAUSES_ACTION])) {
                    $action = new Action($intentData[Model::CAUSES_ACTION][0][Model::ID]);
                    $clone ? false : $action->setUid($intentData[Model::CAUSES_ACTION][0][Model::UID]);
                    $intent->addAction($action);
                }
                if (isset($intentData[Model::HAS_INTERPRETER])) {
                    $interpreter = new Interpreter($intentData[Model::HAS_INTERPRETER][0][Model::ID]);
                    $clone ? false : $interpreter->setUid($intentData[Model::HAS_INTERPRETER][0][Model::UID]);
                    $intent->addInterpreter($interpreter);
                }

                $endingSceneId = $intentData[Model::LISTENED_BY_FROM_SCENES][0][Model::USER_PARTICIPATES_IN][0][Model::ID];

                if ($participant->isUser()) {
                    $cm->userSaysToBotAcrossScenes($sceneId, $endingSceneId, $intent, $intentData[Model::ORDER]);
                }

                if ($participant->isBot()) {
                    $cm->botSaysToUserAcrossScenes($sceneId, $endingSceneId, $intent, $intentData[Model::ORDER]);
                }
            }
        }
    }
}
