<?php

namespace CaliforniaMountainSnake\InlineKeyboardSelection;

use CaliforniaMountainSnake\LongmanTelegrambotInlinemenu\InlineButton\InlineButton;
use CaliforniaMountainSnake\LongmanTelegrambotUtils\AdvancedSendUtils;
use CaliforniaMountainSnake\LongmanTelegrambotUtils\ConversationUtils;
use CaliforniaMountainSnake\LongmanTelegrambotUtils\SendUtils;
use CaliforniaMountainSnake\UtilTraits\ArrayUtils;
use Illuminate\Contracts\Container\BindingResolutionException;
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

    /**
     * @return string
     */
    abstract public static function getCommandName(): string;

    /**
     * Select one value using InlineKeyboard.
     *
     * @param string $_selection_unique_token
     * @param array  $_keyboard_buttons
     * @param string $_message_text
     * @param string $_user_text
     *
     * @param bool   $_delete_message
     *
     * @return bool
     * @throws BindingResolutionException
     * @throws TelegramException
     */
    public function oneSelection(
        string $_selection_unique_token,
        array $_keyboard_buttons,
        string $_message_text,
        string $_user_text,
        bool $_delete_message = false
    ): bool {
        [$previousMsgId, $previousMsgType] = $this->getPrevMsgData($_selection_unique_token);
        $keyboard = InlineButton::buttonsArray(self::getCommandName(), $_keyboard_buttons);
        $errors = [];

        if ($previousMsgId === null) {
            $this->showAnyMessage($_selection_unique_token, $_message_text, null, $errors, $keyboard);
            return false;
        }

        // Validation.
        $availableValues = $this->array_keys_recursive($_keyboard_buttons);
        if (!\in_array($_user_text, $availableValues, false)) {
            $errors[] = __('telegrambot/keyboard_selection.wrong_value_single');
        }

        // Success.
        if (empty($errors)) {
            // Store result.
            $this->setConversationNotes([
                $this->getOneSelectionResultNoteName($_selection_unique_token) => $_user_text,
            ]);

            // Delete temp message data.
            $this->deletePrevMsgData($_selection_unique_token);

            // Delete temp message if necessary.
            $_delete_message && $this->deleteMessage($previousMsgId);
            return true;
        }

        $this->showAnyMessage($_selection_unique_token, $_message_text, null, $errors, $keyboard);
        return false;
    }

    /**
     * @param string $_selection_unique_token
     *
     * @return string|null
     */
    public function getOneSelectionResult(string $_selection_unique_token): ?string
    {
        return $this->getNote($this->getOneSelectionResultNoteName($_selection_unique_token));
    }

    //------------------------------------------------------------------------------------------------------------------

    /**
     * @param string $_selection_unique_token
     *
     * @return string
     */
    private function getOneSelectionResultNoteName(string $_selection_unique_token): string
    {
        return $_selection_unique_token . '_result';
    }
}
