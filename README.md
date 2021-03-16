# longmantelegrambot-inline-keyboard-selection
Utils that allows to get from a user some values using inline keyboard inside a longman/telegram-bot command.

## Install:
### Require this package with Composer
Install this package through [Composer](https://getcomposer.org/).
Edit your project's `composer.json` file to require `californiamountainsnake/longmantelegrambot-inline-keyboard-selection`:
```json
{
    "name": "yourproject/yourproject",
    "type": "project",
    "require": {
        "php": "^7.3",
        "californiamountainsnake/longmantelegrambot-inline-keyboard-selection": "*"
    }
}
```
and run `composer update`

### or
run this command in your command line:
```bash
composer require californiamountainsnake/longmantelegrambot-inline-keyboard-selection
```


### Usage
1. Create the lang file `telegrambot/keyboard_selection`:
```php
<?php
return [
    'selected_value_prefix' => '☑ ',
    'button_clear' => 'Reset',
    'button_all' => 'All',
    'button_ok' => 'OK',

    'wrong_value_single' => 'Wrong value! Select one value from the keyboard.',
    'wrong_value_multiple' => 'Wrong value! Select one or more values from the keyboard.',
    'nothing_selected_error' => 'You select nothing! Select one or more values from the keyboard.',
];
```
2. Use trait in your bot command:
```php
<?php
class MyCommand extends \Longman\TelegramBot\Commands\Command {
    use OneSelection;
    
    public function execute(): ServerResponse
    {
        $isSelected = $this->oneSelection(
            'my_one_selection_1',
            [
                'value1' => 'name1',
                'value2' => 'name2',
            ],
            'Please, select one value!',
            $this->getMessage()->getText(),
            true
        );
        if (!$isSelected) {
            return $this->emptyResponse();
        }
    
        return $this->sendTextMessage (\print_r($this->getOneSelectionResult('my_one_selection_1'), true));
    }
}
```
