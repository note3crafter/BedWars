<?php

namespace xenialdan\customui\windows;

class ServerForm extends CustomForm
{
    protected $iconURL = '';

    final public function jsonSerialize()
    {
        $data = [
            'type' => 'custom_form',
            'title' => $this->title,
            'content' => []
        ];
        if ($this->iconURL != '') {
            $data['icon'] = [
                "type" => "url",
                "data" => $this->iconURL
            ];
        }
        foreach ($this->elements as $element) {
            $data['content'][] = $element;
        }
        return $data;
    }

    public function getIconURL(): string
    {
        return $this->iconURL;
    }

    public function setIconURL(string $iconURL): void
    {
        $this->iconURL = $iconURL;
    }
}
