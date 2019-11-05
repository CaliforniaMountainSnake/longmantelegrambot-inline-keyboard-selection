<?php

namespace CaliforniaMountainSnake\InlineKeyboardSelection;

use CaliforniaMountainSnake\LongmanTelegrambotInlinemenu\InlineButton\InlineButton;
use CaliforniaMountainSnake\LongmanTelegrambotUtils\AdvancedSendUtils;
use CaliforniaMountainSnake\LongmanTelegrambotUtils\ConversationUtils;
use CaliforniaMountainSnake\LongmanTelegrambotUtils\SendUtils;
use CaliforniaMountainSnake\SimpleLaravelAuthSystem\AuthValidatorService;
use CaliforniaMountainSnake\UtilTraits\ArrayUtils;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\Message;
use Longman\TelegramBot\Exception\TelegramException;

/**
 * Select multiple values using InlineKeyboard.
 */
trait MultipleSelection
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
     * @return AuthValidatorService
     */
    abstract public function getValidatorService(): AuthValidatorService;

    /**
     * Select multiple values using InlineKeyboard.
     *
     * @param array    $_keyboard_buttons             Multidimensional string array with keyboard buttons.
     * @param string   $_keyboard_message_text        The text that will be shown to user.
     * @param Message  $_user_message                 User's message telegram object.
     * @param callable $_result_callback              The callback in which will be passed the results of selection
     *                                                as an array parameter.
     * @param bool     $_is_force_del_and_send        Always just delete the previous message and send a new one.
     * @param bool     $_is_delete_message_on_success Do delete the message after selection has been completed?
     *
     * @return null|mixed Return value of the result callback or null if a selection is still not completed.
     * @throws TelegramException
     */
    public function multipleSelection(
        array $_keyboard_buttons,
        string $_keyboard_message_text,
        Message $_user_message,
        callable $_result_callback,
        bool $_is_force_del_and_send = false,
        bool $_is_delete_message_on_success = true
    ) {
        $text = $_user_message->getText(true) ?? '';
        $selectionResult = $this->getMultipleSelectionResult();

        // Create selection.
        if ($selectionResult === null) {
            $this->createMultipleSelectionResult();

            $this->showAnyMessage($this->getMultipleSelectionTokenName(), $_keyboard_message_text, null,
                null, $this->buildMultipleSelectionKeyboard($_keyboard_buttons),
                null, null, $_is_force_del_and_send);
            return null;
        }

        // validate user's text.
        $validator = $this->getButtonsValidator($text, $_keyboard_buttons);
        if (!$validator->fails()) {
            // Update selected values.
            $selectionResult = $this->updateMultipleSelectionResult($text);

            $validator = $this->getSelectedValuesNotEmptyValidator($selectionResult);
            $validator->fails();
        }
        $errors = $validator->errors();

        // Process command buttons.
        if (empty($errors->toArray())) {
            // Clear.
            if ($text === $this->getClearButtonName()) {
                $this->updateMultipleSelectionResultNote([]);
            }
            // All.
            if ($text === $this->getAllButtonName()) {
                $this->updateMultipleSelectionResultNote($this->array_keys_recursive($_keyboard_buttons));
            }
            // Ok.
            if ($text === $this->getOkButtonName()) {
                if ($_is_delete_message_on_success) {
                    [$previousMsgId] = $this->getPrevMsgData($this->getMultipleSelectionTokenName());
                    $this->deleteMessage($previousMsgId);
                }

                // Clear temp conversation's notes.
                $this->clearMultipleSelectionResult();
                return $_result_callback($selectionResult);
            }
        }

        // Update message.
        $this->showAnyMessage($this->getMultipleSelectionTokenName(), $_keyboard_message_text, null,
            $errors->toArray(), $this->buildMultipleSelectionKeyboard($_keyboard_buttons),
            null, null, $_is_force_del_and_send);
        return null;
    }

    //------------------------------------------------------------------------------------------------------------------

    /**
     * @param array $_keyboard_buttons
     *
     * @return Keyboard
     */
    protected function buildMultipleSelectionKeyboard(array $_keyboard_buttons): Keyboard
    {
        $result = $this->getMultipleSelectionResult() ?? [];

        \array_walk_recursive($_keyboard_buttons, function (&$visibleButton, &$realValue) use ($result) {
            $isButtonSelected = \in_array($realValue, $result, false);
            if ($isButtonSelected) {
                $visibleButton = $this->getSelectedValuePrefix() . $visibleButton;
            }
        });

        $_keyboard_buttons[] = [
            $this->getClearButtonName() => $this->getClearButtonName(),
            $this->getAllButtonName() => $this->getAllButtonName(),
            $this->getOkButtonName() => $this->getOkButtonName(),
        ];
        return InlineButton::buttonsArray(self::getCommandName(), $_keyboard_buttons);
    }

    //------------------------------------------------------------------------------------------------------------------

    /**
     * @throws TelegramException
     */
    private function clearMultipleSelectionResult(): void
    {
        // Delete temp message data in conversation's notes.
        $this->deletePrevMsgData($this->getMultipleSelectionTokenName());

        // Delete the conversation's note with selection result.
        $this->updateMultipleSelectionResultNote(null);
    }

    /**
     * @return array|null
     */
    private function getMultipleSelectionResult(): ?array
    {
        return $this->getNote($this->getMultipleSelectionTokenName());
    }

    /**
     * @param array $_new_value
     *
     * @throws TelegramException
     */
    private function updateMultipleSelectionResultNote(array $_new_value): void
    {
        $this->setConversationNotes([
            $this->getMultipleSelectionTokenName() => $_new_value,
        ]);
    }

    /**
     * @param string $_text
     *
     * @return array
     * @throws TelegramException
     */
    private function updateMultipleSelectionResult(string $_text): array
    {
        // Create selection if not exists.
        $selectionResult = $this->getMultipleSelectionResult();
        if ($selectionResult === null) {
            $selectionResult = $this->createMultipleSelectionResult();
            return $selectionResult;
        }

        // Skip command buttons.
        if ($_text === $this->getOkButtonName() || $_text === $this->getClearButtonName()) {
            return $selectionResult;
        }

        // Update values.
        if (isset($selectionResult[$_text])) {
            unset ($selectionResult[$_text]);
        } else {
            $selectionResult[$_text] = $_text;
        }

        $this->updateMultipleSelectionResultNote($selectionResult);
        return $selectionResult;
    }

    /**
     * @return array
     * @throws TelegramException
     */
    private function createMultipleSelectionResult(): array
    {
        $result = $this->getMultipleSelectionResult();
        if ($result === null) {
            $this->updateMultipleSelectionResultNote([]);
            return [];
        }

        return $result;
    }

    /**
     * @param string $_current_text
     * @param array  $_keyboard_buttons
     *
     * @return Validator
     */
    private function getButtonsValidator(string $_current_text, array $_keyboard_buttons): Validator
    {
        $availableValues = \array_merge($this->array_keys_recursive($_keyboard_buttons),
            [$this->getOkButtonName(), $this->getAllButtonName(), $this->getClearButtonName()]);

        return $this->getValidatorService()->makeValidator(
            ['text' => $_current_text],
            ['text' => Rule::in($availableValues)],
            ['in' => __('telegrambot/keyboard_selection.wrong_value_multiple')]);
    }

    /**
     * @param array $_selected_values
     *
     * @return Validator
     */
    private function getSelectedValuesNotEmptyValidator(array $_selected_values): Validator
    {
        return $this->getValidatorService()->makeValidator(
            ['values' => $_selected_values],
            [
                'values' => [
                    'required',
                    'bail',
                    'array',
                    'min:1',
                ]
            ],
            [
                'required' => __('telegrambot/keyboard_selection.nothing_selected_error'),
                'min' => __('telegrambot/keyboard_selection.nothing_selected_error'),
            ]);
    }

    //------------------------------------------------------------------------------------------------------------------

    /**
     * @return string
     */
    private function getMultipleSelectionTokenName(): string
    {
        return 'multiple_selection_result';
    }

    /**
     * @return string
     */
    private function getOkButtonName(): string
    {
        return __('telegrambot/keyboard_selection.button_ok');
    }

    /**
     * @return string
     */
    private function getClearButtonName(): string
    {
        return __('telegrambot/keyboard_selection.button_clear');
    }

    /**
     * @return string
     */
    private function getAllButtonName(): string
    {
        return __('telegrambot/keyboard_selection.button_all');
    }

    /**
     * @return string
     */
    private function getSelectedValuePrefix(): string
    {
        return __('telegrambot/keyboard_selection.selected_value_prefix');
    }
}
