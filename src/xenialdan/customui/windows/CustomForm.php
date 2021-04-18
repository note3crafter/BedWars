<?php

namespace xenialdan\customui\windows;

use pocketmine\Player;
use xenialdan\customui\elements\Button;
use xenialdan\customui\elements\UIElement;

class CustomForm implements CustomUI
{
    use CallableTrait;

    protected $title = '';
    protected $elements = [];
    private $id;

    public function __construct($title)
    {
        $this->title = $title;
    }

    public function addElement(UIElement $element)
    {
        $this->elements[] = $element;
    }

    public function jsonSerialize()
    {
        $data = [
            'type' => 'custom_form',
            'title' => $this->title,
            'content' => []
        ];
        foreach ($this->elements as $element) {
            $data['content'][] = $element;
        }
        return $data;
    }

    final public function getTitle()
    {
        return $this->title;
    }

    public function getContent(): array
    {
        return $this->elements;
    }

    public function setID(int $id)
    {
        $this->id = $id;
    }

    public function getID(): int
    {
        return $this->id;
    }

    public function getElement(int $index)
    {
        return $this->elements[$index];
    }

    public function setElement(UIElement $element, int $index)
    {
        if ($element instanceof Button) return;
        $this->elements[$index] = $element;
    }

    public function handleResponse(Player $player, $data): void
    {
        if (empty($data)) {
            $this->close($player);
            return;
        }
        $return = [];
        foreach ($data as $elementKey => $elementValue) {
            if (isset($this->elements[$elementKey])) {
                if (!is_null($value = $this->elements[$elementKey]->handle($elementValue, $player))) $return[] = $value;
            } else {
                error_log(__CLASS__ . '::' . __METHOD__ . " Element with index {$elementKey} doesn't exists.");
            }
        }

        $callable = $this->getCallable();
        if ($callable !== null) {
            $callable($player, $return);
        }
    }
}
