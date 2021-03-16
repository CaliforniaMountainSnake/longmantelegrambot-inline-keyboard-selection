<?php

namespace CaliforniaMountainSnake\InlineKeyboardSelection;

trait InlineKeyboardSelectionLangValues
{
    abstract public function getLangValueWrongValueSingle(): string;

    abstract public function getLangValueWrongValueMultiple(): string;

    abstract public function getLangValueNothingSelectedError(): string;

    abstract public function getLangValueSelectedValuePrefix(): string;

    abstract public function getLangValueOkButtonName(): string;

    abstract public function getLangValueBackButtonName(): string;

    abstract public function getLangValueClearButtonName(): string;

    abstract public function getLangValueAllButtonName(): string;
}
