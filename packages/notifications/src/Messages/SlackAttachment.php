<?php

namespace Kyqo\Notifications\Messages;

class SlackAttachment
{
    public string  $title    = '';
    public string  $content  = '';
    public ?string $color    = null;
    public ?string $url      = null;
    public array   $fields   = [];

    public function title(string $title, string $url = null): static
    {
        $this->title = $title;
        $this->url   = $url;
        return $this;
    }

    public function content(string $content): static { $this->content = $content; return $this; }
    public function color(string $color): static     { $this->color   = $color;   return $this; }

    public function field(string $title, string $value, bool $short = true): static
    {
        $this->fields[] = ['title' => $title, 'value' => $value, 'short' => $short];
        return $this;
    }

    public function toArray(): array
    {
        $data = [
            'text'   => $this->content,
            'title'  => $this->title,
            'fields' => $this->fields,
        ];
        if ($this->color) $data['color']      = $this->color;
        if ($this->url)   $data['title_link'] = $this->url;
        return $data;
    }
}
