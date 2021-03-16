<?php

namespace CaliforniaMountainSnake\InlineKeyboardSelection;

use CaliforniaMountainSnake\LongmanTelegrambotInlinemenu\InlineButton\InlineButton;
use CaliforniaMountainSnake\LongmanTelegrambotUtils\AdvancedSendUtils;
use CaliforniaMountainSnake\LongmanTelegrambotUtils\ConversationUtils;
use CaliforniaMountainSnake\LongmanTelegrambotUtils\SendUtils;
use CaliforniaMountainSnake\UtilTraits\ArrayUtils;
use Longman\TelegramBot\Entities\Message;
use Longman\TelegramBot\Exception\TelegramException;

/**
 * Select one value using InlineKeyboard.
 */
trait OneSelection
{
    use ConversationUtils;
    use SendUtils;
    use AdvancedSendUtils;
    use ArrayUtils;
    use InlineKeyboardSelectionLangValues;

    /**
     * Select one value using InlineKeyboard.
     *
     * @param array    $_keyboard_buttons             Multidimensional string array with keyboard buttons.
     * @param string   $_keyboard_message_text        The text that will be shown to user.
     * @param Message  $_user_message                 User's message telegram object.
     * @param callable $_result_callback              The callback in which will be passed the results of selection
     *                                                as a string parameter.
     * @param bool     $_is_force_del_and_send        Always just delete the previous message and send a new one.
     * @param bool     $_is_delete_message_on_success Do delete the message after selection has been completed?
     *
     * @return null|mixed Return value of the result callback or null if a selection is still not completed.
     * @throws TelegramException
     */
    public function oneSelection(
        array $_keyboard_buttons,
        string $_keyboard_message_text,
        Message $_user_message,
        callable $_result_callback,
        bool $_is_force_del_and_send = false,
        bool $_is_delete_message_on_success = true
    ) {
        $text = $_user_message->getText(true) ?? '';
        [$previousMsgId] = $this->getPrevMsgData($this->getOneSelectionTokenName());
        $keyboard = InlineButton::buttonsArray(static::getCommandName(), $_keyboard_buttons);
        $errors = [];

        if ($previousMsgId !== null) {
            // Validation.
            $availableValues = $this->array_keys_recursive($_keyboard_buttons);
            if (!in_array($text, $availableValues, false)) {
                $errors[] = $this->getLangValueWrongValueSingle();
            }

            // Success.
            if (empty($errors)) {
                if ($_is_delete_message_on_success) {
                    $this->deleteMessage($previousMsgId);
                }

                // Clear temp conversation's notes.
                $this->clearOneSelectionResult();
                return $_result_callback($text);
            }
        }

        $this->showAnyMessage($this->getOneSelectionTokenName(), $_keyboard_message_text, null,
            $errors, $keyboard, null, null, $_is_force_del_and_send);
        return null;
    }

    //------------------------------------------------------------------------------------------------------------------

    /**
     * @throws TelegramException
     */
    private function clearOneSelectionResult(): void
    {
        // Delete temp message data in conversation's notes.
        $this->deletePrevMsgData($this->getOneSelectionTokenName());
    }

    /**
     * @return string
     */
    private function getOneSelectionTokenName(): string
    {
        return 'one_selection_result';
    }
}
