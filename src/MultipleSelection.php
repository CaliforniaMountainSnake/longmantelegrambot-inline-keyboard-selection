<?php

namespace CaliforniaMountainSnake\InlineKeyboardSelection;

use CaliforniaMountainSnake\LongmanTelegrambotInlinemenu\InlineButton\InlineButton;
use CaliforniaMountainSnake\LongmanTelegrambotUtils\AdvancedSendUtils;
use CaliforniaMountainSnake\LongmanTelegrambotUtils\ConversationUtils;
use CaliforniaMountainSnake\LongmanTelegrambotUtils\SendUtils;
use CaliforniaMountainSnake\UtilTraits\ArrayUtils;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
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
    use InlineKeyboardSelectionLangValues;

    /**
     * Select multiple values using InlineKeyboard.
     *
     * @param array         $_keyboard_buttons             Multidimensional string array with keyboard buttons.
     * @param string        $_keyboard_message_text        The text that will be shown to user.
     * @param Message       $_user_message                 User's message telegram object.
     * @param callable|null $_save_data_callback           The callback in which will be passed the results of
     *                                                     selection
     *                                                     as an array parameter. Called first relative to success or
     *                                                     back callbacks.
     * @param callable      $_success_callback             The callback that will be executed in case of success
     *                                                     selection.
     * @param callable|null $_back_callback                The callback that will be executed if the user pressed
     *                                                     "back" button.
     * @param array|null    $_preselected_values           Default checked values on the keyboard.
     * @param bool          $_is_force_del_and_send        Always just delete the previous message and send a new one.
     * @param bool          $_is_delete_message_on_success Do delete the message after selection has been completed?
     *
     * @return null|mixed Return value of the result callback or null if a selection is still not completed.
     * @throws TelegramException
     */
    public function multipleSelection(
        array $_keyboard_buttons,
        string $_keyboard_message_text,
        Message $_user_message,
        ?callable $_save_data_callback,
        callable $_success_callback,
        ?callable $_back_callback = null,
        ?array $_preselected_values = null,
        bool $_is_force_del_and_send = false,
        bool $_is_delete_message_on_success = true
    ) {
        $text = $_user_message->getText(true) ?? '';
        $selectionResult = $this->getMultipleSelectionResult();

        // Create selection.
        if ($selectionResult === null) {
            $this->createMultipleSelectionResult($_preselected_values);

            $this->showAnyMessage($this->getNoteNameMultipleSelectionResult(), $_keyboard_message_text, null,
                null, $this->buildMultipleSelectionKeyboard($_keyboard_buttons, $_back_callback),
                null, null, $_is_force_del_and_send);
            return null;
        }

        // validate user's text.
        $validator = $this->getButtonsValidator($text, $_keyboard_buttons);
        if (!$validator->fails()) {
            // Update selected values.
            $selectionResult = $this->updateMultipleSelectionResult($text);

            // Check for emptiness only if "ok" button pressed.
            if ($text === $this->getLangValueOkButtonName()) {
                $validator = $this->getSelectedValuesNotEmptyValidator($selectionResult);
                $validator->fails();
            }
        }
        $errors = $validator->errors();

        // Process command buttons.
        if (empty($errors->toArray())) {
            // Clear.
            if ($text === $this->getLangValueClearButtonName()) {
                $this->setMultipleSelectionResultNote([]);
            }

            // All.
            if ($text === $this->getLangValueAllButtonName()) {
                $this->setMultipleSelectionResultNote($this->array_keys_recursive($_keyboard_buttons));
            }

            // Ok
            if ($text === $this->getLangValueOkButtonName()) {
                $this->deleteMultipleSelectionMsgIfNeed($_is_delete_message_on_success);
                $this->clearMultipleSelectionResult();
                $_save_data_callback !== null && $_save_data_callback($selectionResult);

                return $_success_callback($selectionResult);
            }

            // Back
            if ($_back_callback !== null && $text === $this->getLangValueBackButtonName()) {
                $this->deleteMultipleSelectionMsgIfNeed($_is_delete_message_on_success);
                $this->clearMultipleSelectionResult();
                $_save_data_callback !== null && $_save_data_callback($selectionResult);

                return $_back_callback($selectionResult);
            }
        }

        // Update message.
        $this->showAnyMessage($this->getNoteNameMultipleSelectionResult(), $_keyboard_message_text, null,
            $errors->toArray(), $this->buildMultipleSelectionKeyboard($_keyboard_buttons, $_back_callback),
            null, null, $_is_force_del_and_send);
        return null;
    }

    //------------------------------------------------------------------------------------------------------------------

    /**
     * @param array         $_keyboard_buttons
     * @param callable|null $_back_callback
     *
     * @return Keyboard
     */
    protected function buildMultipleSelectionKeyboard(
        array $_keyboard_buttons,
        ?callable $_back_callback = null
    ): Keyboard {
        $result = $this->getMultipleSelectionResult() ?? [];

        array_walk_recursive($_keyboard_buttons, function (&$visibleValue, &$realValue) use ($result) {
            $isButtonSelected = in_array($realValue, $result, false);
            if ($isButtonSelected) {
                $visibleValue = $this->getLangValueSelectedValuePrefix() . $visibleValue;
            }
        });

        $buttonsRowExtra = [];
        $buttonsRowPermanent = [
            $this->getLangValueClearButtonName() => $this->getLangValueClearButtonName(),
            $this->getLangValueAllButtonName() => $this->getLangValueAllButtonName(),
        ];

        if ($_back_callback === null) {
            $buttonsRowPermanent[$this->getLangValueOkButtonName()] = $this->getLangValueOkButtonName();
        } else {
            $buttonsRowExtra = [
                $this->getLangValueBackButtonName() => $this->getLangValueBackButtonName(),
                $this->getLangValueOkButtonName() => $this->getLangValueOkButtonName(),
            ];
        }

        $_keyboard_buttons[] = $buttonsRowPermanent;
        !empty($buttonsRowExtra) && $_keyboard_buttons[] = $buttonsRowExtra;

        return InlineButton::buttonsArray(static::getCommandName(), $_keyboard_buttons);
    }

    //------------------------------------------------------------------------------------------------------------------

    /**
     * @param bool $_is_delete_message
     */
    private function deleteMultipleSelectionMsgIfNeed(bool $_is_delete_message): void
    {
        if (!$_is_delete_message) {
            return;
        }

        [$previousMsgId] = $this->getPrevMsgData($this->getNoteNameMultipleSelectionResult());
        $this->deleteMessage($previousMsgId);
    }

    /**
     * @throws TelegramException
     */
    private function clearMultipleSelectionResult(): void
    {
        // Delete temp message data in conversation's notes.
        $this->deletePrevMsgData($this->getNoteNameMultipleSelectionResult());

        // Delete the conversation's note with selection result.
        $this->deleteConversationNotes([$this->getNoteNameMultipleSelectionResult()]);
    }

    /**
     * @param array|null $_preselected_values
     *
     * @return array
     * @throws TelegramException
     */
    private function createMultipleSelectionResult(?array $_preselected_values = null): array
    {
        $result = $this->getMultipleSelectionResult();
        $defaultSet = array_values($_preselected_values ?? []);
        if ($result === null) {
            $this->setMultipleSelectionResultNote($defaultSet);
            return $defaultSet;
        }

        return $result;
    }

    /**
     * @return array|null
     */
    private function getMultipleSelectionResult(): ?array
    {
        return $this->getNote($this->getNoteNameMultipleSelectionResult());
    }

    /**
     * @param array|null $_new_value
     *
     * @throws TelegramException
     */
    private function setMultipleSelectionResultNote(?array $_new_value): void
    {
        $this->setConversationNotes([
            $this->getNoteNameMultipleSelectionResult() => $_new_value,
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
        // Get selection result.
        $selectionResult = $this->getMultipleSelectionResult();

        // Skip command buttons.
        if (in_array($_text, $this->getAllCommandButtonsNames(), true)) {
            return $selectionResult;
        }

        // Update values.
        $searchedKey = array_search($_text, $selectionResult, false);
        if ($searchedKey === false) {
            $selectionResult[] = $_text;
        } else {
            unset ($selectionResult[$searchedKey]);
        }

        $this->setMultipleSelectionResultNote($selectionResult);
        return $selectionResult;
    }

    //------------------------------------------------------------------------------------------------------------------

    /**
     * @param string $_current_text
     * @param array  $_keyboard_buttons
     *
     * @return Validator
     */
    private function getButtonsValidator(string $_current_text, array $_keyboard_buttons): Validator
    {
        $availableValues = array_merge($this->array_keys_recursive($_keyboard_buttons),
            $this->getAllCommandButtonsNames());


        return ValidatorFacade::make(
            ['text' => $_current_text],
            ['text' => Rule::in($availableValues)],
            ['in' => $this->getLangValueWrongValueMultiple()]);
    }

    /**
     * @param array $_selected_values
     *
     * @return Validator
     */
    private function getSelectedValuesNotEmptyValidator(array $_selected_values): Validator
    {
        return ValidatorFacade::make(
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
                'required' => $this->getLangValueNothingSelectedError(),
                'min' => $this->getLangValueNothingSelectedError(),
            ]);
    }

    //------------------------------------------------------------------------------------------------------------------

    /**
     * @return string
     */
    private function getNoteNameMultipleSelectionResult(): string
    {
        return 'multiple_selection_result';
    }

    /**
     * @return array
     */
    private function getAllCommandButtonsNames(): array
    {
        return [
            $this->getLangValueOkButtonName(),
            $this->getLangValueBackButtonName(),
            $this->getLangValueAllButtonName(),
            $this->getLangValueClearButtonName()
        ];
    }
}
