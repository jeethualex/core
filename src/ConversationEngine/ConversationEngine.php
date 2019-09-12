<?php

namespace OpenDialogAi\ConversationEngine;

use Ds\Map;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use OpenDialogAi\ActionEngine\Actions\ActionResult;
use OpenDialogAi\ActionEngine\Exceptions\ActionNotAvailableException;
use OpenDialogAi\ActionEngine\Service\ActionEngineInterface;
use OpenDialogAi\ContextEngine\Contexts\User\UserContext;
use OpenDialogAi\ContextEngine\Exceptions\ContextDoesNotExistException;
use OpenDialogAi\ContextEngine\Facades\AttributeResolver;
use OpenDialogAi\ContextEngine\Facades\ContextService;
use OpenDialogAi\ConversationEngine\ConversationStore\ConversationStoreInterface;
use OpenDialogAi\ConversationEngine\ConversationStore\EIModels\EIModelCondition;
use OpenDialogAi\ConversationEngine\ConversationStore\EIModels\EIModelIntent;
use OpenDialogAi\Core\Attribute\AttributeInterface;
use OpenDialogAi\Core\Conversation\Condition;
use OpenDialogAi\Core\Conversation\Conversation;
use OpenDialogAi\Core\Conversation\Intent;
use OpenDialogAi\Core\Conversation\Scene;
use OpenDialogAi\Core\Graph\Node\NodeDoesNotExistException;
use OpenDialogAi\Core\Utterances\Exceptions\FieldNotSupported;
use OpenDialogAi\Core\Utterances\UtteranceInterface;
use OpenDialogAi\InterpreterEngine\Interpreters\NoMatchIntent;
use OpenDialogAi\InterpreterEngine\Service\InterpreterServiceInterface;

class ConversationEngine implements ConversationEngineInterface
{
    const NO_MATCH = 'intent.core.NoMatch';

    /* @var ConversationStoreInterface */
    private $conversationStore;

    /* @var InterpreterServiceInterface */
    private $interpreterService;

    /* @var ActionEngineInterface */
    private $actionEngine;

    /**
     * @param ConversationStoreInterface $conversationStore
     */
    public function setConversationStore(ConversationStoreInterface $conversationStore): void
    {
        $this->conversationStore = $conversationStore;
    }

    /**
     * @return ConversationStoreInterface
     */
    public function getConversationStore(): ConversationStoreInterface
    {
        return $this->conversationStore;
    }

    /**
     * @param InterpreterServiceInterface $interpreterService
     */
    public function setInterpreterService(InterpreterServiceInterface $interpreterService): void
    {
        $this->interpreterService = $interpreterService;
    }

    /**
     * @param ActionEngineInterface $actionEngine
     */
    public function setActionEngine(ActionEngineInterface $actionEngine): void
    {
        $this->actionEngine = $actionEngine;
    }

    /**
     * @param UserContext $userContext
     * @param UtteranceInterface $utterance
     * @return Intent
     * @throws FieldNotSupported
     * @throws GuzzleException
     * @throws NodeDoesNotExistException
     * @throws ConversationStore\EIModelCreatorException
     */
    public function getNextIntent(UserContext $userContext, UtteranceInterface $utterance): Intent
    {
        /* @var Conversation $ongoingConversation */
        $ongoingConversation = $this->determineCurrentConversation($userContext, $utterance);
        Log::debug(sprintf('Ongoing conversation determined as %s', $ongoingConversation->getId()));

        /* @var Scene $currentScene */
        $currentScene = $userContext->getCurrentScene();

        /** @var Intent $currentIntent */
        $currentIntent = $this->conversationStore->getIntentByUid($userContext->getUser()->getCurrentIntentUid());

        $possibleNextIntents = $currentScene->getNextPossibleBotIntents($currentIntent);

        /* @var Intent $nextIntent */
        $nextIntent = $possibleNextIntents->first()->value;
        ContextService::saveAttribute('conversation.next_intent', $nextIntent->getId());

        if ($nextIntent->completes()) {
            $userContext->moveCurrentConversationToPast();
        } else {
            $userContext->setCurrentIntent($nextIntent);
        }

        return $nextIntent;
    }

    /**
     * @param UserContext $userContext
     * @param UtteranceInterface $utterance
     * @return Conversation
     * @throws GuzzleException
     * @throws NodeDoesNotExistException
     * @throws FieldNotSupported
     * @throws ConversationStore\EIModelCreatorException
     */
    public function determineCurrentConversation(UserContext $userContext, UtteranceInterface $utterance): Conversation
    {
        if ($userContext->isUserHavingConversation()) {
            $ongoingConversation = $userContext->getCurrentConversation();

            ContextService::saveAttribute('conversation.current_conversation', $ongoingConversation->getId());

            Log::debug(
                sprintf(
                    'User %s is having a conversation with id %s',
                    $userContext->getUserId(),
                    $ongoingConversation->getId()
                )
            );

            if ($userContext->currentSpeakerIsBot()) {
                Log::debug(sprintf('Speaker was bot.'));
                $ongoingConversation = $this->updateConversationFollowingUserInput($userContext, $utterance);

                if (!isset($ongoingConversation)) {
                    Log::debug(sprintf('No intent for ongoing conversation matched, simulating a NoMatch.'));
                    $utterance->setCallbackId(self::NO_MATCH);
                    return self::determineCurrentConversation($userContext, $utterance);
                }
            }

            return $ongoingConversation;
        }

        $ongoingConversation = $this->setCurrentConversation($userContext, $utterance);
        if (!isset($ongoingConversation)) {
            Log::debug(sprintf('No opening conversation found for utterance, simulating a NoMatch.'));
            $utterance->setCallbackId(self::NO_MATCH);
            return self::determineCurrentConversation($userContext, $utterance);
        }

        Log::debug(
            sprintf(
                'Got a matching conversation for user %s with id %s',
                $userContext->getUserId(),
                $ongoingConversation->getId()
            )
        );

        // Start from the top - we should eventually have a conversation.
        return self::determineCurrentConversation($userContext, $utterance);
    }

    /**
     * @param UserContext $userContext
     * @param UtteranceInterface $utterance
     * @return Conversation
     * @throws GuzzleException
     * @throws NodeDoesNotExistException
     * @throws ConversationStore\EIModelCreatorException
     */
    public function updateConversationFollowingUserInput(
        UserContext $userContext,
        UtteranceInterface $utterance
    ): ?Conversation {
        /* @var Scene $currentScene */
        $currentScene = $userContext->getCurrentScene();

        /* @var EIModelIntent $currentIntent */
        $currentIntent = $userContext->getCurrentIntent();

        if (!ContextService::hasContext('conversation')) {
            ContextService::createContext('conversation');
        }

        ContextService::saveAttribute('conversation.current_scene', $currentScene->getId());
        ContextService::saveAttribute('conversation.current_intent', $currentIntent->getIntentId());

        $possibleNextIntents = $currentScene->getNextPossibleUserIntents($userContext->getCurrentIntent());
        Log::debug(sprintf('There are %s possible next intents.', count($possibleNextIntents)));

        $defaultIntent = $this->interpreterService->getDefaultInterpreter()->interpret($utterance)[0];
        Log::debug(sprintf('Default intent is %s', $defaultIntent->getId()));

        $matchingIntents = $this->getMatchingIntents($utterance, $possibleNextIntents, $defaultIntent);

        if (count($matchingIntents) >= 1) {
            Log::debug(sprintf('There are %s matching intents', count($matchingIntents)));

            $nextIntent = $matchingIntents->getBestMatch();
            Log::debug(sprintf('We found a matching intent %s', $nextIntent->getId()));
            $userContext->setCurrentIntent($nextIntent);

            ContextService::saveAttribute('conversation.interpreted_intent', $nextIntent->getId());

            $this->storeIntentAttributes($nextIntent);

            if ($nextIntent->causesAction()) {
                $this->performIntentAction($userContext, $nextIntent);
            }

            return $userContext->getCurrentConversation();
        }

        // What the user says does not match anything expected in the current conversation so complete it and
        // pretend we received a no match intent.
        Log::debug('No matching intent, moving conversation to past state');
        $userContext->moveCurrentConversationToPast();
        Log::debug('No match found, dropping out of current conversation.');
        //@todo This should be an exception
        return null;
    }

    /**
     * There is no ongoing conversation for the current user so we will attempt to find
     * a matching new conversation or return a core-level NoMatch conversation if nothing else
     * works.
     *
     * @param UserContext $userContext
     * @param UtteranceInterface $utterance
     * @return Conversation | null
     * @throws GuzzleException
     * @throws NodeDoesNotExistException
     * @throws ConversationStore\EIModelCreatorException
     */
    private function setCurrentConversation(UserContext $userContext, UtteranceInterface $utterance): ?Conversation
    {
        $defaultIntent = $this->interpreterService->getDefaultInterpreter()->interpret($utterance)[0];
        Log::debug(sprintf('Default intent is %s', $defaultIntent->getId()));

        $openingIntents = $this->conversationStore->getAllEIModelOpeningIntents();
        Log::debug(sprintf('Found %s opening intents.', count($openingIntents)));

        $matchingIntents = $this->matchOpeningIntents($defaultIntent, $utterance, $openingIntents->getIntents());
        Log::debug(sprintf('Found %s matching intents.', count($matchingIntents)));

        if (count($matchingIntents) === 0) {
            return null;
        }

        /* @var EIModelIntent $intent */
        $intent = $matchingIntents->last()->value;
        Log::debug(sprintf('Select %s as matching intent.', $intent->getIntentId()));

        $this->storeIntentAttributesFromOpeningIntent($intent);

        // We specify the conversation to be cloned as we will be re-persisting it as a user conversation next
        /** @var Conversation $conversation */
        $conversation = $this->conversationStore->getConversation($intent->getConversationUid(), true);

        // TODO can we avoid building, cloning and re-persisting the conversation here. EG clone directly in DGRAPH
        // TODO and store the resulting ID against the user

        $userContext->setCurrentConversation($conversation);

        /** @var Intent $currentIntent */
        $currentIntent = $this->conversationStore->getOpeningIntentByConversationIdAndOrder(
            $userContext->getUser()->getCurrentConversationUid(),
            $intent->getOrder()
        );

        $userContext->setCurrentIntent($currentIntent);

        /* @var Intent $currentIntent */
        Log::debug(sprintf('Set current intent as %s', $currentIntent->getId()));
        ContextService::saveAttribute('conversation.interpreted_intent', $currentIntent->getId());
        ContextService::saveAttribute('conversation.current_scene', 'opening_scene');

        if ($currentIntent->causesAction()) {
            $this->performIntentAction($userContext, $currentIntent);
        }

        // For this intent get the matching conversation - we are pulling this back out from the user
        // so that we have the copy from the graph.
        // TODO do we need to get the entire conversation again?
        return $this->conversationStore->getConversation($intent->getConversationUid());
    }

    /**
     * @param Intent $defaultIntent
     * @param UtteranceInterface $utterance
     * @param Map $validOpeningIntents
     * @return Map
     */
    private function matchOpeningIntents(
        Intent $defaultIntent,
        UtteranceInterface $utterance,
        Map $validOpeningIntents
    ): Map {
        $matchingIntents = new Map();

        /* @var EIModelIntent $validIntent */
        foreach ($validOpeningIntents as $key => $validIntent) {
            if ($validIntent->hasInterpreter()) {
                $intentsFromInterpreter = $this->interpreterService
                    ->getInterpreter($validIntent->getInterpreterId())
                    ->interpret($utterance);

                // For each intent from the interpreter check to see if it matches the opening intent candidate.
                foreach ($intentsFromInterpreter as $interpretedIntent) {
                    $validIntent->setInterpretedIntent($interpretedIntent);

                    if ($this->intentHasEnoughConfidence($interpretedIntent, $validIntent)) {
                        $matchingIntents->put($validIntent->getConversationId(), $validIntent);
                    }
                }
            } else if ($this->intentHasEnoughConfidence($defaultIntent, $validIntent)) {
                $validIntent->setInterpretedIntent($defaultIntent);
                $matchingIntents->put($validIntent->getConversationId(), $validIntent);
            }
        }

        // Check conditions for each conversation
        $matchingIntents = $this->filterOpeningIntentsForConditions($matchingIntents);

        $matchingIntents = $this->filterNoMatchIntents($matchingIntents);

        return $matchingIntents;
    }

    /**
     * @param Map $intentsToCheck
     * @return Map
     */
    private function filterOpeningIntentsForConditions(Map $intentsToCheck): Map
    {
        $matchingIntents = new Map();

        /* @var EIModelIntent $intent */
        foreach ($intentsToCheck as $intent) {
            if ($intent->hasConditions()) {
                $pass = true;
                $conditions = $intent->getConditions();

                /* @var EIModelCondition $condition */
                foreach ($conditions as $conditionModel) {
                    $attributeName = $conditionModel->getAttributeName();

                    try {
                        $actualAttribute = ContextService::getAttribute($attributeName, $conditionModel->getContextId());
                    } catch (Exception $e) {
                        Log::debug($e->getMessage());
                        // If the attribute does not exist create one with a null value since we may be testing
                        // for its existence.
                        $actualAttribute = AttributeResolver::getAttributeFor($attributeName, null);
                    }

                    /* @var Condition $condition */
                    $condition = $this->conversationStore->getConversationConverter()->convertCondition($conditionModel);

                    if (!$condition->compareAgainst($actualAttribute)) {
                        $pass = false;
                    }
                }

                if ($pass) {
                    $matchingIntents->put($intent->getConversationId(), $intent);
                }
            } else {
                $matchingIntents->put($intent->getConversationId(), $intent);
            }
        }

        return $matchingIntents;
    }

    /**
     * Filters out no match intents if we have more than 1 intent.
     * Any non-no match intent should be considered more valid.
     *
     * @param Map $matchingIntents
     * @return mixed
     */
    private function filterNoMatchIntents($matchingIntents)
    {
        if ($matchingIntents->count() === 1) {
            return $matchingIntents;
        }

        return $matchingIntents->filter(function ($intentName, EIModelIntent $intent) {
            return $intent->getIntentId() !== NoMatchIntent::NO_MATCH;
        });
    }

    /**
     * Stores the Intent entities from an opening intent by pulling out the interpreted intent which contains the
     * interpreted attributes and the expected attributes that are set against the Opening Intent
     *
     * @param EIModelIntent $intent
     */
    public function storeIntentAttributesFromOpeningIntent(EIModelIntent $intent): void
    {
        $this->storeIntentAttributes($intent->getInterpretedIntent(), $intent->getExpectedAttributeContexts());
    }

    /**
     * Stores the non-core attributes from an Intent to a context.
     * Expected attributes are passed into the function or retrieved from the Intent to determine which context each
     * attribute should be saved to. If one is not defined for the attribute, it is saved to the session context
     *
     * @param Intent $intent
     * @param Map|null $expectedAttributes
     */
    private function storeIntentAttributes(Intent $intent, Map $expectedAttributes = null): void
    {
        if ($expectedAttributes === null) {
            $expectedAttributes = $intent->getExpectedAttributeContexts();
        }

        $contextsUpdated = [];
        /** @var AttributeInterface $attribute */
        foreach ($intent->getNonCoreAttributes() as $attribute) {
            $attributeName = $attribute->getId();

            $context = ContextService::getSessionContext();
            if ($expectedAttributes->hasKey($attributeName)) {
                $contextId = $expectedAttributes->get($attributeName);
                try {
                    $context = ContextService::getContext($contextId);
                } catch (ContextDoesNotExistException $e) {
                    // phpcs:ignore
                    Log::error(sprintf('Expected attribute context %s does not exist, using session context', $contextId));
                }
            }

            Log::debug(sprintf('Storing attribute %s in %s context', $attribute->getId(), $context->getId()));
            $context->addAttribute($attribute);

            $contextsUpdated[$context->getId()] = $context->getId();
        }

        $this->persistContexts($contextsUpdated);
    }

    /**
     * Persists the contexts given.
     *
     * @param array $contexts Array of context IDs to be persisted
     */
    private function persistContexts(array $contexts)
    {
        foreach ($contexts as $contextId) {
            $context = ContextService::getContext($contextId);
            $context->persist();
        }
    }

    /**
     * @param Intent $interpretedIntent
     * @param EIModelIntent $validIntent
     * @return bool
     */
    private function intentHasEnoughConfidence(Intent $interpretedIntent, EIModelIntent $validIntent): bool
    {
        return $interpretedIntent->getId() === $validIntent->getIntentId() &&
            $interpretedIntent->getConfidence() >= $validIntent->getConfidence();
    }

    /**
     * Returns a map of matching intents
     *
     * @param UtteranceInterface $utterance
     * @param Map $nextIntents
     * @param Intent $defaultIntent
     * @return MatchingIntents
     * @throws NodeDoesNotExistException
     */
    private function getMatchingIntents(
        UtteranceInterface $utterance,
        Map $nextIntents,
        Intent $defaultIntent
    ): MatchingIntents {
        $matchingIntents = new MatchingIntents();

        /* @var Intent $validIntent */
        foreach ($nextIntents as $validIntent) {
            if ($validIntent->hasInterpreter()) {
                $interpretedIntents = $this->interpreterService->getInterpreter($validIntent->getInterpreter()->getId())
                    ->interpret($utterance);
            } else {
                $interpretedIntents = [$defaultIntent];
            }

            foreach ($interpretedIntents as $interpretedIntent) {
                if ($interpretedIntent->matches($validIntent)) {
                    $validIntent->copyNonCoreAttributes($interpretedIntent);
                    $matchingIntents->addMatchingIntent($validIntent);
                }
            }
        }

        return $matchingIntents;
    }

    /**
     * Performs the action associated with the intent and stores the outcome against the user
     * TODO - a conversation should be able to decide where to store attributes
     *
     * @param UserContext $userContext
     * @param Intent $nextIntent
     * @throws NodeDoesNotExistException
     */
    public function performIntentAction(UserContext $userContext, Intent $nextIntent): void
    {
        Log::debug(
            sprintf(
                'Current intent %s causes action %s',
                $nextIntent->getId(),
                $nextIntent->getAction()->getId()
            )
        );

        try {
            /* @var ActionResult $actionResult */
            $actionResult = $this->actionEngine->performAction($nextIntent->getAction()->getId());
            $userContext->addActionResult($actionResult);
            Log::debug(sprintf('Adding action result to user context'));
        } catch (ActionNotAvailableException $e) {
            Log::warning(sprintf('Action %s has not been bound.', $nextIntent->getAction()->getId()));
        }
    }
}
