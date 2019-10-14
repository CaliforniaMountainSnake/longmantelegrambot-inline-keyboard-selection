<?php

namespace CaliforniaMountainSnake\InlineKeyboardSelection;

use CaliforniaMountainSnake\LongmanTelegrambotInlinemenu\InlineButton\InlineButton;
use CaliforniaMountainSnake\LongmanTelegrambotUtils\AdvancedSendUtils;
use CaliforniaMountainSnake\LongmanTelegrambotUtils\ConversationUtils;
use CaliforniaMountainSnake\LongmanTelegrambotUtils\SendUtils;
use CaliforniaMountainSnake\SimpleLaravelAuthSystem\AuthValidatorService;
use CaliforniaMountainSnake\UtilTraits\ArrayUtils;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;
use Longman\TelegramBot\Entities\InlineKeyboard;
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
     * @param string  $_selection_unique_token Unique selection's note name.
     * @param array   $_keyboard_buttons       Multidimensional string array with keyboard buttons.
     * @param string  $_keyboard_message_text
     * @param Message $_user_message           User's message
     * @param bool    $_delete_message         Delete the message after selection?
     *
     * @return bool Закончен ли выбор значений.
     * @throws BindingResolutionException
     * @throws TelegramException
     */
    public function multipleSelection(
        string $_selection_unique_token,
        array $_keyboard_buttons,
        string $_keyboard_message_text,
        Message $_user_message,
        bool $_delete_message = true
    ): bool {
        $text = $_user_message->getText(true) ?? '';
        $selectionResult = $this->getMultipleSelectionResult($_selection_unique_token);

        // Create selection.
        if ($selectionResult === null) {
            $this->createMultipleSelectionResult($_selection_unique_token);

            $this->showAnyMessage($_selection_unique_token, $_keyboard_message_text, null, null,
                $this->buildMultipleSelectionKeyboard($_selection_unique_token, $_keyboard_buttons));
            return false;
        }

        // validate user's text.
        $validator = $this->getButtonsValidator($text, $_keyboard_buttons);
        if (!$validator->fails()) {
            // Update selected values.
            $selectionResult = $this->updateMultipleSelectionResult($_selection_unique_token, $text);

            $validator = $this->getSelectedValuesNotEmptyValidator($selectionResult);
            $validator->fails();
        }
        $errors = $validator->errors();

        // Process command buttons.
        if (empty($errors->toArray())) {
            // Clear.
            if ($text === $this->getClearButtonName()) {
                $this->updateMultipleSelectionResultNote($_selection_unique_token, []);
            }
            // All.
            if ($text === $this->getAllButtonName()) {
                $this->updateMultipleSelectionResultNote($_selection_unique_token,
                    $this->array_keys_recursive($_keyboard_buttons));
            }
            // Ok.
            if ($text === $this->getOkButtonName()) {
                if ($_delete_message) {
                    [$previousMsgId, $previousMsgType] = $this->getPrevMsgData($_selection_unique_token);
                    $this->deleteMessage($previousMsgId);
                }
                return true;
            }
        }

        // Update message.
        $this->showAnyMessage($_selection_unique_token, $_keyboard_message_text, null, $errors->toArray(),
            $this->buildMultipleSelectionKeyboard($_selection_unique_token, $_keyboard_buttons));
        return false;
    }

    /**
     * @param string $_selection_unique_token
     *
     * @return array|null
     */
    public function getMultipleSelectionResult(string $_selection_unique_token): ?array
    {
        return $this->getNote($this->getMultipleSelectionResultNote($_selection_unique_token));
    }

    //------------------------------------------------------------------------------------------------------------------

    /**
     * @param string $_selection_unique_token
     * @param array  $_new_value
     *
     * @throws TelegramException
     */
    private function updateMultipleSelectionResultNote(string $_selection_unique_token, array $_new_value): void
    {
        $this->setConversationNotes([
            $this->getMultipleSelectionResultNote($_selection_unique_token) => $_new_value,
        ]);
    }

    /**
     * @param string $_selection_unique_token
     * @param string $_text
     *
     * @return array
     * @throws BindingResolutionException
     * @throws TelegramException
     */
    private function updateMultipleSelectionResult(string $_selection_unique_token, string $_text): array
    {
        // Create selection if not exists.
        $selectionResult = $this->getMultipleSelectionResult($_selection_unique_token);
        if ($selectionResult === null) {
            $selectionResult = $this->createMultipleSelectionResult($_selection_unique_token);
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

        $this->updateMultipleSelectionResultNote($_selection_unique_token, $selectionResult);
        return $selectionResult;
    }

    /**
     * @param string $_selection_unique_token
     *
     * @return array
     * @throws TelegramException
     */
    private function createMultipleSelectionResult(string $_selection_unique_token): array
    {
        $result = $this->getMultipleSelectionResult($_selection_unique_token);
        if ($result === null) {
            $this->updateMultipleSelectionResultNote($_selection_unique_token, []);
            return [];
        }

        return $result;
    }

    /**
     * @param string $_selection_unique_token
     * @param array  $_keyboard_buttons
     *
     * @return InlineKeyboard
     * @throws BindingResolutionException
     */
    private function buildMultipleSelectionKeyboard(
        string $_selection_unique_token,
        array $_keyboard_buttons
    ): InlineKeyboard {
        $result = $this->getMultipleSelectionResult($_selection_unique_token) ?? [];

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

    /**
     * @param string $_current_text
     * @param array  $_keyboard_buttons
     *
     * @return Validator
     * @throws BindingResolutionException
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
     * @throws BindingResolutionException
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
     * @param string $_selection_unique_token
     *
     * @return string
     */
    private function getMultipleSelectionResultNote(string $_selection_unique_token): string
    {
        return $_selection_unique_token . '_result';
    }

    /**
     * @return string
     * @throws BindingResolutionException
     */
    private function getOkButtonName(): string
    {
        return __('telegrambot/keyboard_selection.button_ok');
    }

    /**
     * @return string
     * @throws BindingResolutionException
     */
    private function getClearButtonName(): string
    {
        return __('telegrambot/keyboard_selection.button_clear');
    }

    /**
     * @return string
     * @throws BindingResolutionException
     */
    private function getAllButtonName(): string
    {
        return __('telegrambot/keyboard_selection.button_all');
    }

    /**
     * @return string
     * @throws BindingResolutionException
     */
    private function getSelectedValuePrefix(): string
    {
        return __('telegrambot/keyboard_selection.selected_value_prefix');
    }
}
